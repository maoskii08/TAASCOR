#!/usr/bin/env python3
"""Read-only exact-commit parity verification for the TAASCOR web root."""

from __future__ import annotations

import argparse
import hashlib
import json
import posixpath
import re
import shlex
import subprocess
import sys
import tarfile
import tempfile
from pathlib import Path, PurePosixPath
from typing import BinaryIO, Iterable


EXCLUDED_TOP_LEVEL = {
    "audit_reports",
    "database",
    "tests",
    "tools",
}
EXCLUDED_SEGMENTS = {"migrations", "tests"}
EXCLUDED_ROOT_FILES = {
    ".env.example",
    ".gitattributes",
    ".gitignore",
    ".gitleaks.toml",
    "PRODUCT.md",
    "README.md",
}
# These tracked files support local recovery/analysis tooling and are not part
# of the deployable HRIS runtime. Keep every exception explicit and reviewed.
EXCLUDED_RUNTIME_PATHS = {
    "dtr-format-engine/config/multiclient_validation_profiles.json",
    "dtr-format-engine/model/BulkSampleTemplateIntegrator.php",
}
CONTROL_CHARACTERS = re.compile(r"[\x00-\x1f\x7f]")
SHA256_PATTERN = re.compile(r"^[0-9a-f]{64}$")


def sha256_stream(stream: BinaryIO) -> tuple[str, int]:
    digest = hashlib.sha256()
    size = 0
    while True:
        chunk = stream.read(1024 * 1024)
        if not chunk:
            break
        digest.update(chunk)
        size += len(chunk)
    return digest.hexdigest(), size


def is_runtime_path(raw_path: str) -> bool:
    path = PurePosixPath(raw_path.replace("\\", "/"))
    parts = path.parts
    if not parts or path.is_absolute() or ".." in parts:
        return False
    if CONTROL_CHARACTERS.search(raw_path):
        raise RuntimeError(f"Tracked path contains unsupported control characters: {raw_path!r}")
    if parts[0] in EXCLUDED_TOP_LEVEL or parts[0] in EXCLUDED_ROOT_FILES:
        return False
    if path.as_posix() in EXCLUDED_RUNTIME_PATHS:
        return False
    if parts[0].startswith(".env"):
        return False
    if ".example." in parts[-1] or parts[-1].endswith(".example"):
        return False
    if any(part in EXCLUDED_SEGMENTS for part in parts):
        return False
    return True


def git_output(repo: Path, *arguments: str) -> str:
    completed = subprocess.run(
        ["git", "-C", str(repo), *arguments],
        check=False,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        encoding="utf-8",
        errors="replace",
    )
    if completed.returncode != 0:
        raise RuntimeError(completed.stderr.strip() or "Git command failed")
    return completed.stdout.strip()


def build_exact_commit_manifest(repo: Path, git_ref: str) -> tuple[str, dict[str, dict[str, object]], int]:
    repo = repo.resolve()
    commit = git_output(repo, "rev-parse", "--verify", f"{git_ref}^{{commit}}")
    manifest: dict[str, dict[str, object]] = {}
    excluded_count = 0

    with tempfile.TemporaryFile() as archive_file:
        completed = subprocess.run(
            ["git", "-C", str(repo), "archive", "--format=tar", commit],
            check=False,
            stdout=archive_file,
            stderr=subprocess.PIPE,
        )
        if completed.returncode != 0:
            error = completed.stderr.decode("utf-8", errors="replace").strip()
            raise RuntimeError(error or "Unable to archive the verified commit")

        archive_file.seek(0)
        with tarfile.open(fileobj=archive_file, mode="r:") as archive:
            for member in archive:
                if member.isdir():
                    continue
                relative = member.name.replace("\\", "/")
                if not is_runtime_path(relative):
                    excluded_count += 1
                    continue
                if not member.isfile():
                    raise RuntimeError(f"Runtime symlinks or special files are not supported: {relative}")
                extracted = archive.extractfile(member)
                if extracted is None:
                    raise RuntimeError(f"Unable to read archived file: {relative}")
                digest, size = sha256_stream(extracted)
                manifest[relative] = {"sha256": digest, "bytes": size}

    if not manifest:
        raise RuntimeError("The verified commit produced an empty runtime manifest")
    return commit, dict(sorted(manifest.items())), excluded_count


def manifest_digest(manifest: dict[str, dict[str, object]]) -> str:
    digest = hashlib.sha256()
    for path, entry in manifest.items():
        digest.update(path.encode("utf-8"))
        digest.update(b"\0")
        digest.update(str(entry["sha256"]).encode("ascii"))
        digest.update(b"\0")
        digest.update(str(entry["bytes"]).encode("ascii"))
        digest.update(b"\n")
    return digest.hexdigest()


def load_env(path: Path) -> dict[str, str]:
    values: dict[str, str] = {}
    for raw_line in path.read_text(encoding="utf-8-sig").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        values[key.strip()] = value.strip().strip('"').strip("'")
    return values


def connect(env_path: Path):
    try:
        import paramiko
    except ImportError as error:
        raise RuntimeError("Remote parity requires the paramiko package") from error

    env = load_env(env_path)
    required = ["SSH_HOST", "SSH_USERNAME", "SSH_PASSWORD"]
    missing = [key for key in required if not env.get(key)]
    if missing:
        raise RuntimeError("Missing required SSH fields: " + ", ".join(missing))

    client = paramiko.SSHClient()
    client.load_system_host_keys()
    known_hosts = Path.home() / ".ssh" / "known_hosts"
    if known_hosts.exists():
        client.load_host_keys(str(known_hosts))
    client.set_missing_host_key_policy(paramiko.RejectPolicy())
    client.connect(
        hostname=env["SSH_HOST"],
        port=int(env.get("SSH_PORT") or "22"),
        username=env["SSH_USERNAME"],
        password=env["SSH_PASSWORD"],
        look_for_keys=False,
        allow_agent=False,
        timeout=20,
        banner_timeout=20,
        auth_timeout=20,
    )
    return client


def chunks(values: list[str], size: int) -> Iterable[list[str]]:
    for offset in range(0, len(values), size):
        yield values[offset:offset + size]


def remote_hashes(client, production_root: str, paths: list[str]) -> dict[str, str | None]:
    results: dict[str, str | None] = {}
    for batch in chunks(paths, 75):
        commands = []
        for relative in batch:
            remote_path = posixpath.join(production_root, relative)
            quoted_relative = shlex.quote(relative)
            quoted_remote = shlex.quote(remote_path)
            commands.append(
                f"if [ -f {quoted_remote} ]; then "
                f"printf '%s\\t' {quoted_relative}; sha256sum -- {quoted_remote} | cut -d ' ' -f 1; "
                f"else printf '%s\\tMISSING\\n' {quoted_relative}; fi"
            )

        _stdin, stdout, stderr = client.exec_command("\n".join(commands), timeout=60)
        output = stdout.read().decode("utf-8", errors="replace")
        error = stderr.read().decode("utf-8", errors="replace")
        code = stdout.channel.recv_exit_status()
        if code != 0:
            raise RuntimeError(f"Remote hash command failed ({code}): {error.strip()}")

        for line in output.splitlines():
            if "\t" not in line:
                raise RuntimeError("Remote hash output was malformed")
            relative, value = line.split("\t", 1)
            if relative not in batch:
                raise RuntimeError(f"Remote hash output included an unexpected path: {relative}")
            if value == "MISSING":
                results[relative] = None
            elif SHA256_PATTERN.fullmatch(value):
                results[relative] = value
            else:
                raise RuntimeError(f"Remote hash output was invalid for: {relative}")

        omitted = [relative for relative in batch if relative not in results]
        if omitted:
            raise RuntimeError("Remote hash output omitted: " + ", ".join(omitted))
    return results


def write_result(result: dict[str, object], output_path: Path | None) -> None:
    rendered = json.dumps(result, indent=2) + "\n"
    if output_path is not None:
        output_path.parent.mkdir(parents=True, exist_ok=True)
        output_path.write_text(rendered, encoding="utf-8")
    print(rendered, end="")


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--repo", type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument("--git-ref", default="HEAD", help="Exact commit or ref to verify")
    parser.add_argument("--manifest-only", action="store_true", help="Build the exact-commit manifest without connecting")
    parser.add_argument("--list-files", action="store_true", help="Include the expected file manifest in JSON output")
    parser.add_argument("--env", type=Path, help="Ignored file containing SSH_HOST/PORT/USERNAME/PASSWORD")
    parser.add_argument("--production-root", help="Confirmed absolute production web root")
    parser.add_argument("--output", type=Path, help="Optional JSON evidence path")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    try:
        commit, manifest, excluded_count = build_exact_commit_manifest(args.repo, args.git_ref)
        result: dict[str, object] = {
            "schema_version": 1,
            "mode": "manifest-only" if args.manifest_only else "production-parity",
            "success": True,
            "commit": commit,
            "git_ref": args.git_ref,
            "runtime_files_expected": len(manifest),
            "excluded_tracked_files": excluded_count,
            "manifest_sha256": manifest_digest(manifest),
        }
        if args.list_files:
            result["expected_files"] = manifest

        if args.manifest_only:
            write_result(result, args.output)
            return 0
        if args.env is None or not args.production_root:
            raise RuntimeError("Remote parity requires --env and --production-root")
        if not args.production_root.startswith("/") or args.production_root == "/":
            raise RuntimeError("--production-root must be a specific absolute directory")

        client = connect(args.env)
        try:
            live = remote_hashes(client, args.production_root.rstrip("/"), list(manifest))
        finally:
            client.close()

        missing = [path for path, value in live.items() if value is None]
        mismatches = [
            {
                "path": path,
                "expected_sha256": manifest[path]["sha256"],
                "live_sha256": value,
            }
            for path, value in live.items()
            if value is not None and value != manifest[path]["sha256"]
        ]
        matched = len(manifest) - len(missing) - len(mismatches)
        result.update({
            "success": not missing and not mismatches,
            "host_key_verified": True,
            "production_root": args.production_root.rstrip("/"),
            "live_files_checked": len(live),
            "live_files_matched": matched,
            "missing_files": missing,
            "mismatches": mismatches,
        })
        write_result(result, args.output)
        return 0 if result["success"] else 1
    except Exception as error:
        print(json.dumps({"success": False, "error": str(error)}, indent=2), file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
