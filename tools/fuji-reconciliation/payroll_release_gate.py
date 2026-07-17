from __future__ import annotations

import argparse
import json
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
from pathlib import Path
from typing import Any


CENT = Decimal("0.01")

EXPECTED_TO_ACTUAL = {
    "expected_basic_pay": "basic_pay",
    "expected_total_ot": "total_ot",
    "expected_other_earnings": "other_earnings",
    "expected_gross": "gross",
    "expected_sss": "sss",
    "expected_philhealth": "philhealth",
    "expected_pagibig": "pagibig",
    "expected_tax": "withholding_tax",
    "expected_other_deductions": "other_deductions",
    "expected_total_deductions": "total_deductions",
    "expected_taxable": "taxable",
    "expected_net_pay": "net_pay",
}

REQUIRED_RUN_METADATA = (
    "run_uid",
    "source_checksum",
    "ruleset_key",
    "ruleset_version",
    "ruleset_hash",
)


def decimal_value(value: Any) -> Decimal:
    if isinstance(value, Decimal):
        return value
    if value in (None, ""):
        raise ValueError("missing monetary value")
    try:
        return Decimal(str(value).replace(",", ""))
    except (InvalidOperation, ValueError) as error:
        raise ValueError(f"invalid monetary value: {value!r}") from error


def money(value: Any) -> Decimal:
    return decimal_value(value).quantize(CENT, rounding=ROUND_HALF_UP)


def unique_by_source(rows: list[dict[str, Any]], label: str) -> dict[str, dict[str, Any]]:
    indexed: dict[str, dict[str, Any]] = {}
    duplicates: list[str] = []
    missing = 0
    for row in rows:
        source_id = str(row.get("source_id", "")).strip()
        if not source_id:
            missing += 1
            continue
        if source_id in indexed:
            duplicates.append(source_id)
        indexed[source_id] = row
    if missing:
        raise ValueError(f"{label} contains {missing} rows without source_id")
    if duplicates:
        values = ", ".join(sorted(set(duplicates))[:10])
        raise ValueError(f"{label} contains duplicate source_id values: {values}")
    return indexed


def compare_release(
    expected_rows: list[dict[str, Any]],
    actual_rows: list[dict[str, Any]],
    run: dict[str, Any],
    *,
    require_ledgers: bool = True,
) -> dict[str, Any]:
    expected = unique_by_source(expected_rows, "expected payroll")
    actual = unique_by_source(actual_rows, "actual payroll")
    blockers: list[dict[str, Any]] = []

    for field in REQUIRED_RUN_METADATA:
        if not str(run.get(field, "")).strip():
            blockers.append({"code": "RUN_METADATA_MISSING", "field": field})

    missing_source_ids = sorted(set(expected) - set(actual))
    unexpected_source_ids = sorted(set(actual) - set(expected))
    for source_id in missing_source_ids:
        blockers.append({"code": "PAYSLIP_MISSING", "source_id": source_id})
    for source_id in unexpected_source_ids:
        blockers.append({"code": "PAYSLIP_UNEXPECTED", "source_id": source_id})

    component_exact = {field: 0 for field in EXPECTED_TO_ACTUAL.values()}
    component_totals = {
        field: {"expected": Decimal("0"), "actual": Decimal("0")}
        for field in EXPECTED_TO_ACTUAL.values()
    }
    compared = 0

    for source_id in sorted(set(expected) & set(actual)):
        expected_row = expected[source_id]
        actual_row = actual[source_id]
        compared += 1

        for expected_field, actual_field in EXPECTED_TO_ACTUAL.items():
            try:
                expected_amount = money(expected_row.get(expected_field))
                actual_amount = money(actual_row.get(actual_field))
            except ValueError as error:
                blockers.append(
                    {
                        "code": "COMPONENT_VALUE_INVALID",
                        "source_id": source_id,
                        "component": actual_field,
                        "detail": str(error),
                    }
                )
                continue

            component_totals[actual_field]["expected"] += expected_amount
            component_totals[actual_field]["actual"] += actual_amount
            if expected_amount == actual_amount:
                component_exact[actual_field] += 1
            else:
                blockers.append(
                    {
                        "code": "COMPONENT_MISMATCH",
                        "source_id": source_id,
                        "component": actual_field,
                        "expected": str(expected_amount),
                        "actual": str(actual_amount),
                        "difference": str(actual_amount - expected_amount),
                    }
                )

        try:
            gross = money(actual_row.get("gross"))
            deductions = money(actual_row.get("total_deductions"))
            net_pay = money(actual_row.get("net_pay"))
            if money(gross - deductions) != net_pay:
                blockers.append(
                    {
                        "code": "NET_PAY_EQUATION_MISMATCH",
                        "source_id": source_id,
                        "gross": str(gross),
                        "total_deductions": str(deductions),
                        "net_pay": str(net_pay),
                    }
                )
        except ValueError:
            pass

        if require_ledgers:
            ledger_pairs = (
                ("employee_loan", "loan_ledger_total", "LOAN_LEDGER_MISMATCH"),
                ("other_deductions", "other_deduction_ledger_total", "DEDUCTION_LEDGER_MISMATCH"),
            )
            for summary_field, ledger_field, code in ledger_pairs:
                try:
                    summary_amount = money(actual_row.get(summary_field))
                    ledger_amount = money(actual_row.get(ledger_field))
                except ValueError as error:
                    blockers.append(
                        {
                            "code": "LEDGER_VALUE_MISSING",
                            "source_id": source_id,
                            "component": ledger_field,
                            "detail": str(error),
                        }
                    )
                    continue
                if summary_amount != ledger_amount:
                    blockers.append(
                        {
                            "code": code,
                            "source_id": source_id,
                            "summary": str(summary_amount),
                            "ledger": str(ledger_amount),
                            "difference": str(ledger_amount - summary_amount),
                        }
                    )

    batch_components: dict[str, dict[str, str]] = {}
    for component, totals in component_totals.items():
        expected_total = money(totals["expected"])
        actual_total = money(totals["actual"])
        batch_components[component] = {
            "expected": str(expected_total),
            "actual": str(actual_total),
            "difference": str(actual_total - expected_total),
        }
        if expected_total != actual_total:
            blockers.append(
                {
                    "code": "BATCH_COMPONENT_MISMATCH",
                    "component": component,
                    "expected": str(expected_total),
                    "actual": str(actual_total),
                    "difference": str(actual_total - expected_total),
                }
            )

    return {
        "status": "pass" if not blockers else "blocked",
        "run_uid": str(run.get("run_uid", "")),
        "expected_population": len(expected),
        "actual_population": len(actual),
        "compared_population": compared,
        "missing_population": len(missing_source_ids),
        "unexpected_population": len(unexpected_source_ids),
        "component_exact": component_exact,
        "batch_components": batch_components,
        "blocker_count": len(blockers),
        "blockers": blockers,
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Fail-closed Fuji payroll release comparison against the private expected-payslip oracle."
    )
    parser.add_argument("--oracle-regression", required=True, type=Path)
    parser.add_argument("--actual-payroll", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--allow-missing-ledgers", action="store_true")
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    oracle_payload = json.loads(args.oracle_regression.read_text(encoding="utf-8"))
    actual_payload = json.loads(args.actual_payroll.read_text(encoding="utf-8"))
    result = compare_release(
        list(oracle_payload.get("regression", [])),
        list(actual_payload.get("rows", [])),
        dict(actual_payload.get("run", {})),
        require_ledgers=not args.allow_missing_ledgers,
    )
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({key: value for key, value in result.items() if key != "blockers"}, indent=2))
    raise SystemExit(0 if result["status"] == "pass" else 1)


if __name__ == "__main__":
    main()
