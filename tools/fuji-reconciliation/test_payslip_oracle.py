from __future__ import annotations

import unittest

from build_payslip_oracle import parse_block


class PayslipOracleTest(unittest.TestCase):
    def test_attendance_deduction_is_separated_from_printed_subtotal(self) -> None:
        block = """
Payroll Period 06/16/2026 To 06/30/2026 Pay Date 07/13/2026
Employee Name SAMPLE, EMPLOYEE                                      Department NO DEPARTMENT
BASIC RATE DAILY 600.00 SSS 1,200.00
BASIC 10.00 6,000.00 PHILHEALTH 50.00
PAGIBIG
OTHER EARNINGS 930.00 WTAX (tax code Z)
VL LATE/UNDERTIME 0.1500 11.25 TOTAL OTHER ADDITIONS 930.00
TOTAL OTHER DEDUCTIONS 130.00
TOTAL DEDUCTIONS 1,563.16 TOTAL OT 5.00 40.00 5,451.91
NET PAY 10,807.50 Signature Date Received
GROSS 12,381.91
TAXABLE 4,738.75
"""
        row = parse_block(block, 0)
        self.assertEqual(row["employee_name"], "SAMPLE, EMPLOYEE")
        self.assertEqual(row["late_undertime_hours"], 0.15)
        self.assertEqual(row["late_undertime_amount"], 11.25)
        self.assertEqual(row["printed_total_deductions"], 1563.16)
        self.assertEqual(row["effective_total_deductions"], 1574.41)
        self.assertEqual(row["total_deductions"], 1574.41)
        self.assertEqual(row["total_ot"], 5451.91)


if __name__ == "__main__":
    unittest.main()
