from __future__ import annotations

import unittest

from payroll_release_gate import compare_release


def expected_row(source_id: str = "1001") -> dict[str, object]:
    return {
        "source_id": source_id,
        "expected_basic_pay": 6000,
        "expected_total_ot": 500,
        "expected_other_earnings": 250,
        "expected_gross": 6750,
        "expected_sss": 500,
        "expected_philhealth": 200,
        "expected_pagibig": 100,
        "expected_tax": 50,
        "expected_other_deductions": 25,
        "expected_attendance_deduction": 25,
        "expected_printed_total_deductions": 875,
        "expected_total_deductions": 900,
        "expected_taxable": 5950,
        "expected_net_pay": 5850,
    }


def actual_row(source_id: str = "1001") -> dict[str, object]:
    return {
        "source_id": source_id,
        "basic_pay": 6000,
        "total_ot": 500,
        "other_earnings": 250,
        "gross": 6750,
        "sss": 500,
        "philhealth": 200,
        "pagibig": 100,
        "withholding_tax": 50,
        "other_deductions": 25,
        "attendance_deduction": 25,
        "printed_total_deductions": 875,
        "total_deductions": 900,
        "taxable": 5950,
        "net_pay": 5850,
        "employee_loan": 0,
        "loan_ledger_total": 0,
        "other_deduction_ledger_total": 25,
    }


def run_metadata() -> dict[str, object]:
    return {
        "run_uid": "RUN-TEST-1",
        "source_checksum": "abc123",
        "ruleset_key": "FUJI-SEMI-MONTHLY",
        "ruleset_version": 1,
        "ruleset_hash": "rules123",
    }


class PayrollReleaseGateTest(unittest.TestCase):
    def test_exact_employee_and_batch_reconciliation_passes(self) -> None:
        result = compare_release([expected_row()], [actual_row()], run_metadata())
        self.assertEqual(result["status"], "pass")
        self.assertEqual(result["blocker_count"], 0)
        self.assertEqual(result["component_exact"]["net_pay"], 1)

    def test_one_cent_difference_blocks_release(self) -> None:
        actual = actual_row()
        actual["net_pay"] = 5849.99
        result = compare_release([expected_row()], [actual], run_metadata())
        codes = {blocker["code"] for blocker in result["blockers"]}
        self.assertEqual(result["status"], "blocked")
        self.assertIn("COMPONENT_MISMATCH", codes)
        self.assertIn("NET_PAY_EQUATION_MISMATCH", codes)

    def test_printed_and_attendance_deductions_must_reconcile_to_effective_total(self) -> None:
        actual = actual_row()
        actual["attendance_deduction"] = 24.99
        result = compare_release([expected_row()], [actual], run_metadata())
        codes = {blocker["code"] for blocker in result["blockers"]}
        self.assertEqual(result["status"], "blocked")
        self.assertIn("COMPONENT_MISMATCH", codes)
        self.assertIn("DEDUCTION_TOTAL_EQUATION_MISMATCH", codes)

    def test_missing_and_unexpected_population_block_release(self) -> None:
        result = compare_release(
            [expected_row("1001")],
            [actual_row("2002")],
            run_metadata(),
        )
        codes = {blocker["code"] for blocker in result["blockers"]}
        self.assertIn("PAYSLIP_MISSING", codes)
        self.assertIn("PAYSLIP_UNEXPECTED", codes)

    def test_loan_ledger_difference_blocks_release(self) -> None:
        actual = actual_row()
        actual["employee_loan"] = 100
        actual["loan_ledger_total"] = 75
        result = compare_release([expected_row()], [actual], run_metadata())
        self.assertIn(
            "LOAN_LEDGER_MISMATCH",
            {blocker["code"] for blocker in result["blockers"]},
        )

    def test_missing_ruleset_metadata_blocks_release(self) -> None:
        run = run_metadata()
        run["ruleset_hash"] = ""
        result = compare_release([expected_row()], [actual_row()], run)
        self.assertIn(
            "RUN_METADATA_MISSING",
            {blocker["code"] for blocker in result["blockers"]},
        )

    def test_duplicate_source_ids_fail_closed(self) -> None:
        with self.assertRaisesRegex(ValueError, "duplicate source_id"):
            compare_release(
                [expected_row(), expected_row()],
                [actual_row()],
                run_metadata(),
            )


if __name__ == "__main__":
    unittest.main()
