from __future__ import annotations

import tempfile
import unittest
from decimal import Decimal
from pathlib import Path

from openpyxl import Workbook

from build_crosswalk_data import match_hris, read_dtr
from build_payslip_oracle import earnings_components, known_additions, money, unique_oracle_by_name


class FujiReconciliationTest(unittest.TestCase):
    @staticmethod
    def hris_candidate(status: str = "Active", daily_salary: float = 600) -> dict[str, object]:
        return {
            "employee_id": 1001,
            "payroll_employee_id": "P1001",
            "first_name": "Sample",
            "last_name": "Employee",
            "display_name": "Employee, Sample",
            "daily_salary": daily_salary,
            "status": status,
        }

    def test_dtr_extracts_sil_and_aggregate_adjustment_columns(self) -> None:
        workbook = Workbook()
        worksheet = workbook.active
        values: list[object] = [None] * 71
        values[0] = 1
        values[1] = "1001"
        values[2] = "SAMPLE, EMPLOYEE"
        values[20] = 10
        values[56] = 1.5
        values[57] = 125.25
        worksheet.append(values)

        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "synthetic-fuji.xlsx"
            workbook.save(path)
            rows = read_dtr(path)

        self.assertEqual(len(rows), 1)
        self.assertEqual(rows[0]["sil_days"], 1.5)
        self.assertEqual(rows[0]["adjustment_amount"], 125.25)

    def test_aggregate_adjustment_prevents_component_double_counting(self) -> None:
        row = {
            "meal_allowance": 300,
            "perfect_attendance": 50,
            "quarterly_attendance": 25,
            "sil_days": 1,
            "adjustment_amount": 100,
            "adjustment_perfect_attendance": 40,
            "adjustment_quarterly_attendance": 30,
            "adjustment_meal_allowance": 20,
        }
        self.assertEqual(known_additions(row, Decimal("600")), Decimal("1075"))

    def test_component_adjustments_are_fallback_when_aggregate_is_zero(self) -> None:
        row = {
            "meal_allowance": 300,
            "perfect_attendance": 0,
            "quarterly_attendance": 0,
            "sil_days": 0,
            "adjustment_amount": 0,
            "adjustment_perfect_attendance": 40,
            "adjustment_quarterly_attendance": 30,
            "adjustment_meal_allowance": 20,
        }
        self.assertEqual(known_additions(row, Decimal("600")), Decimal("390"))

    def test_overtime_components_are_rounded_before_totaling(self) -> None:
        row = {
            "overtime_hours": 1,
            "night_diff_hours": 0,
            "night_diff_ot_hours": 0,
            "regular_holiday_hours": 0,
            "regular_holiday_ot_hours": 0,
            "regular_holiday_night_diff_hours": 0,
            "regular_holiday_nd_ot_hours": 0,
            "special_holiday_hours": 0,
            "special_holiday_ot_hours": 0,
            "special_holiday_night_diff_hours": 0,
            "special_holiday_nd_ot_hours": 0,
            "rest_day_hours": 0,
            "rest_day_ot_hours": 0,
            "rest_day_night_diff_hours": 0,
            "rest_day_nd_ot_hours": 0,
        }
        components = earnings_components(row, Decimal("600"))
        total = money(sum((money(component) for component in components), Decimal("0")))
        self.assertEqual(total, Decimal("93.75"))

    def test_inactive_exact_name_requires_owner_review(self) -> None:
        rows = [{"source_name": "EMPLOYEE, SAMPLE"}]
        match_hris(rows, [self.hris_candidate(status="Inactive")])

        self.assertEqual(rows[0]["hris_match_status"], "Owner review required")
        self.assertEqual(rows[0]["payroll_readiness_status"], "Blocked - inactive employee")

    def test_zero_salary_is_separate_from_identity_confidence(self) -> None:
        rows = [{"source_name": "EMPLOYEE, SAMPLE"}]
        match_hris(rows, [self.hris_candidate(daily_salary=0)])

        self.assertEqual(rows[0]["hris_match_status"], "Suggested - high confidence")
        self.assertEqual(rows[0]["payroll_readiness_status"], "Blocked - salary missing")

    def test_duplicate_oracle_names_fail_closed(self) -> None:
        oracle = [
            {"employee_name": "EMPLOYEE, SAMPLE", "gross": 100},
            {"employee_name": "EMPLOYEE, SAMPLE", "gross": 200},
        ]

        with self.assertRaisesRegex(ValueError, "Duplicate employee names"):
            unique_oracle_by_name(oracle)


if __name__ == "__main__":
    unittest.main()
