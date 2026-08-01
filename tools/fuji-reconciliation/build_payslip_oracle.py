from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from decimal import Decimal, ROUND_HALF_UP
from pathlib import Path


NUMBER_RE = re.compile(r"-?[0-9][0-9,]*\.[0-9]+")


def dec(value: object) -> Decimal:
    if value in (None, ""):
        return Decimal("0")
    return Decimal(str(value).replace(",", ""))


def money(value: Decimal) -> Decimal:
    return value.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)


def as_number(value: Decimal | None) -> float | None:
    return float(value) if value is not None else None


def amounts(text: str) -> list[Decimal]:
    return [dec(match.group(0)) for match in NUMBER_RE.finditer(text)]


def amount_after(line: str, marker: str) -> Decimal:
    position = line.upper().find(marker.upper())
    if position < 0:
        return Decimal("0")
    found = amounts(line[position + len(marker) :])
    return found[0] if found else Decimal("0")


def first_line(lines: list[str], pattern: str) -> str:
    regex = re.compile(pattern, re.IGNORECASE)
    return next((line for line in lines if regex.search(line)), "")


def earnings_total(line: str, label_pattern: str) -> Decimal:
    """Return the printed earnings amount without consuming right-side deduction data."""
    match = re.search(label_pattern, line, re.IGNORECASE)
    if not match:
        return Decimal("0")
    earnings_side = line[match.end() :]
    deduction_marker = re.search(r"\s{2,}(?:SSS|PAGIBIG|COMPANY)(?:\s|$)", earnings_side, re.IGNORECASE)
    if deduction_marker:
        earnings_side = earnings_side[: deduction_marker.start()]
    found = amounts(earnings_side)
    return found[-1] if found else Decimal("0")


def parse_block(block: str, index: int) -> dict[str, object]:
    lines = [line.rstrip() for line in block.splitlines() if line.strip() and "PAGE BREAK" not in line]
    header = first_line(lines, r"Payroll Period")
    header_match = re.search(
        r"Payroll Period\s+(\d{2}/\d{2}/\d{4})\s+To\s+(\d{2}/\d{2}/\d{4})\s+Pay Date\s+(\d{2}/\d{2}/\d{4})",
        header,
        re.IGNORECASE,
    )
    employee_line = first_line(lines, r"Employee Name")
    employee_match = re.search(r"Employee Name\s+(.+)$", employee_line, re.IGNORECASE)
    name = employee_match.group(1).strip() if employee_match else ""
    name = re.split(r"\s{2,}(?:Department|Branch)\b", name, maxsplit=1, flags=re.IGNORECASE)[0].strip()

    basic_rate_line = first_line(lines, r"^\s*BASIC RATE")
    basic_line = first_line(lines, r"^\s*BASIC(?!\s+RATE)\s+")
    basic_rate_values = amounts(basic_rate_line)
    basic_values = amounts(basic_line)

    regular_ot_line = first_line(lines, r"^\s*REGULAR OT")
    regular_holiday_line = first_line(lines, r"^\s*REGULAR HOL")
    special_holiday_line = first_line(lines, r"^\s*SPECIAL HOL")
    rest_day_line = first_line(lines, r"^\s*DAY OFF")
    rest_regular_line = first_line(lines, r"^\s*DO/REG")
    rest_special_line = first_line(lines, r"^\s*DO/SPC")
    total_ot_line = first_line(lines, r"\bTOTAL OT(?!HER)")

    gross_values = amounts(first_line(lines, r"^\s*GROSS\s+"))
    taxable_values = amounts(first_line(lines, r"^\s*TAXABLE(?!\s+ALLOW)\s+"))
    total_deduction_line = first_line(lines, r"TOTAL DEDUCTIONS")
    total_deduction_values = amounts(total_deduction_line)
    net_pay_line = first_line(lines, r"NET PAY")
    other_earnings_values = amounts(first_line(lines, r"^\s*OTHER EARNINGS"))
    total_other_addition_line = first_line(lines, r"TOTAL OTHER ADDITIONS")
    total_other_deduction_line = first_line(lines, r"TOTAL OTHER DEDUCTIONS")

    top_lines = lines[:16]
    philhealth_line = first_line(top_lines, r"PHILHEALTH")
    pagibig_line = first_line(top_lines, r"^\s*PAGIBIG|\sPAGIBIG\s*$")
    wtax_line = first_line(top_lines, r"WTAX")
    absent_line = first_line(top_lines, r"ABSENT")
    total_ot_match = re.search(r"\bTOTAL OT(?!HER)", total_ot_line, re.IGNORECASE)
    ot_values = amounts(total_ot_line[total_ot_match.end() :]) if total_ot_match else []
    daily_rate = basic_rate_values[0] if basic_rate_values else None
    gross = gross_values[0] if gross_values else None
    net_pay_values = amounts(net_pay_line)
    net_pay = amount_after(net_pay_line, "NET PAY") if net_pay_values else None
    printed_total_deductions = (
        amount_after(total_deduction_line, "TOTAL DEDUCTIONS")
        if total_deduction_values
        else None
    )
    effective_total_deductions = (
        money(gross - net_pay)
        if gross is not None and net_pay is not None
        else None
    )
    late_undertime_amount = (
        money(effective_total_deductions - printed_total_deductions)
        if effective_total_deductions is not None
        and printed_total_deductions is not None
        else Decimal("0")
    )
    if late_undertime_amount < 0:
        raise ValueError(
            f"Payslip {index + 1} has printed deductions greater than gross minus net pay"
        )
    late_undertime_hours = (
        (late_undertime_amount * Decimal("8") / daily_rate).quantize(Decimal("0.0001"))
        if daily_rate not in (None, Decimal("0"))
        else Decimal("0")
    )
    record = {
        "oracle_index": index + 1,
        "employee_name": name,
        "period_start": header_match.group(1) if header_match else "",
        "period_end": header_match.group(2) if header_match else "",
        "pay_date": header_match.group(3) if header_match else "",
        "daily_rate": as_number(daily_rate),
        "basic_days": as_number(basic_values[0] if basic_values else None),
        "basic_pay": as_number(basic_values[1] if len(basic_values) > 1 else None),
        "other_earnings": as_number(other_earnings_values[0] if other_earnings_values else Decimal("0")),
        "sss": as_number(amount_after(basic_rate_line, "SSS")),
        "philhealth": as_number(amount_after(philhealth_line, "PHILHEALTH")),
        "pagibig": as_number(amount_after(pagibig_line, "PAGIBIG")),
        "withholding_tax": as_number(amount_after(wtax_line, "WTAX (tax code Z)")),
        "absent_deduction": as_number(amount_after(absent_line, "ABSENT")),
        "late_undertime": as_number(late_undertime_hours),
        "late_undertime_hours": as_number(late_undertime_hours),
        "late_undertime_amount": as_number(late_undertime_amount),
        "regular_ot_total": as_number(earnings_total(regular_ot_line, r"REGULAR OT")),
        "regular_holiday_total": as_number(earnings_total(regular_holiday_line, r"REGULAR HOL\.")),
        "special_holiday_total": as_number(earnings_total(special_holiday_line, r"SPECIAL HOL\.")),
        "rest_day_total": as_number(earnings_total(rest_day_line, r"DAY OFF")),
        "rest_regular_holiday_total": as_number(earnings_total(rest_regular_line, r"DO/REG\. HOL")),
        "rest_special_holiday_total": as_number(earnings_total(rest_special_line, r"DO/SPC HOL\.")),
        "total_ot": as_number(ot_values[-1] if ot_values else Decimal("0")),
        "total_ot_hours_printed": [as_number(value) for value in ot_values[:-1]],
        "gross": as_number(gross),
        "taxable": as_number(taxable_values[0] if taxable_values else None),
        "total_other_additions_printed": as_number(
            amount_after(total_other_addition_line, "TOTAL OTHER ADDITIONS")
            if amounts(total_other_addition_line)
            else None
        ),
        "total_other_deductions": as_number(
            amount_after(total_other_deduction_line, "TOTAL OTHER DEDUCTIONS")
            if amounts(total_other_deduction_line)
            else Decimal("0")
        ),
        "printed_total_deductions": as_number(printed_total_deductions),
        "effective_total_deductions": as_number(effective_total_deductions),
        "total_deductions": as_number(effective_total_deductions),
        "net_pay": as_number(net_pay),
    }
    return record


def earnings_components(row: dict[str, object], rate: Decimal) -> list[Decimal]:
    hourly = rate / Decimal("8")
    return [
        hourly * Decimal("1.25") * dec(row["overtime_hours"]),
        hourly * Decimal("0.10") * dec(row["night_diff_hours"]),
        hourly * Decimal("1.25") * Decimal("0.10") * dec(row["night_diff_ot_hours"]),
        hourly * dec(row["regular_holiday_hours"]),
        hourly * Decimal("2.60") * dec(row["regular_holiday_ot_hours"]),
        hourly * Decimal("2.00") * Decimal("0.10") * dec(row["regular_holiday_night_diff_hours"]),
        hourly * Decimal("4.00") * Decimal("0.10") * dec(row["regular_holiday_nd_ot_hours"]),
        hourly * Decimal("1.30") * dec(row["special_holiday_hours"]),
        hourly * Decimal("1.30") * Decimal("1.30") * dec(row["special_holiday_ot_hours"]),
        hourly * Decimal("1.30") * Decimal("0.10") * dec(row["special_holiday_night_diff_hours"]),
        hourly * Decimal("1.69") * Decimal("0.10") * dec(row["special_holiday_nd_ot_hours"]),
        hourly * Decimal("1.30") * dec(row["rest_day_hours"]),
        hourly * Decimal("1.30") * Decimal("1.30") * dec(row["rest_day_ot_hours"]),
        hourly * Decimal("1.30") * Decimal("0.10") * dec(row["rest_day_night_diff_hours"]),
        hourly * Decimal("1.69") * Decimal("0.10") * dec(row["rest_day_nd_ot_hours"]),
    ]


def known_additions(row: dict[str, object], rate: Decimal) -> Decimal:
    base_keys = [
        "meal_allowance",
        "perfect_attendance",
        "quarterly_attendance",
    ]
    adjustment_detail_keys = [
        "adjustment_perfect_attendance",
        "adjustment_quarterly_attendance",
        "adjustment_meal_allowance",
    ]
    adjustment_amount = dec(row["adjustment_amount"])
    adjustment = adjustment_amount if adjustment_amount != 0 else sum(
        (dec(row[key]) for key in adjustment_detail_keys), Decimal("0")
    )
    return (
        sum((dec(row[key]) for key in base_keys), Decimal("0"))
        + (dec(row["sil_days"]) * rate)
        + adjustment
    )


def unique_oracle_by_name(oracle: list[dict[str, object]]) -> dict[str, dict[str, object]]:
    counts = Counter(str(row["employee_name"]) for row in oracle)
    duplicates = sorted(name for name, count in counts.items() if count > 1)
    if duplicates:
        raise ValueError(
            "Duplicate employee names in the payslip oracle require a unique identifier: "
            + ", ".join(duplicates)
        )
    return {str(row["employee_name"]): row for row in oracle}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Reconcile Fuji DTR earnings to a private expected-payslip text oracle."
    )
    parser.add_argument("--pdf-text", required=True, type=Path)
    parser.add_argument("--crosswalk", required=True, type=Path)
    parser.add_argument(
        "--output",
        type=Path,
        default=Path(__file__).resolve().parent
        / "private-output"
        / "payslip_oracle_regression.json",
    )
    return parser.parse_args()


def main() -> None:
    args = parse_args()
    text = args.pdf_text.read_text(encoding="utf-8")
    oracle = [
        parse_block(block, index)
        for index, block in enumerate(
            block for block in text.split("TAASCOR MANAGEMENT & GENERAL SERVICES CORPORATION") if "Employee Name" in block
        )
    ]
    crosswalk = json.loads(args.crosswalk.read_text(encoding="utf-8"))
    oracle_by_name = unique_oracle_by_name(oracle)
    regression = []
    for dtr_row in crosswalk["rows"]:
        expected = oracle_by_name.get(str(dtr_row["pdf_name"]))
        if expected is None:
            continue
        rate = dec(expected["daily_rate"])
        basic = money(rate * dec(dtr_row["days_worked"]))
        components = earnings_components(dtr_row, rate)
        precise_ot = money(sum(components, Decimal("0")))
        component_rounded_ot = money(sum((money(value) for value in components), Decimal("0")))
        additions = money(known_additions(dtr_row, rate))
        expected_additions = dec(expected["other_earnings"])
        gross_with_expected_additions = money(basic + precise_ot + expected_additions)
        gross_with_dtr_additions = money(basic + precise_ot + additions)
        gross_component_with_expected_additions = money(basic + component_rounded_ot + expected_additions)
        gross_component_with_dtr_additions = money(basic + component_rounded_ot + additions)
        expected_basic = dec(expected["basic_pay"])
        expected_ot = dec(expected["total_ot"])
        expected_gross = dec(expected["gross"])
        regression.append(
            {
                "source_id": dtr_row["source_id"],
                "source_name": dtr_row["source_name"],
                "pdf_name": dtr_row["pdf_name"],
                "pdf_match_status": dtr_row["pdf_match_status"],
                "suggested_employee_id": dtr_row["suggested_employee_id"],
                "hris_match_status": dtr_row["hris_match_status"],
                "expected_daily_rate": as_number(rate),
                "expected_basic_days": expected["basic_days"],
                "dtr_days_worked": dtr_row["days_worked"],
                "expected_basic_pay": expected["basic_pay"],
                "calculated_basic_pay": as_number(basic),
                "basic_difference": as_number(basic - expected_basic),
                "expected_total_ot": expected["total_ot"],
                "calculated_precise_total_ot": as_number(precise_ot),
                "component_rounded_total_ot": as_number(component_rounded_ot),
                "precise_ot_difference": as_number(precise_ot - expected_ot),
                "component_rounding_difference": as_number(component_rounded_ot - expected_ot),
                "expected_other_earnings": expected["other_earnings"],
                "known_dtr_additions": as_number(additions),
                "addition_difference": as_number(additions - expected_additions),
                "expected_gross": expected["gross"],
                "gross_using_expected_additions": as_number(gross_with_expected_additions),
                "gross_using_dtr_additions": as_number(gross_with_dtr_additions),
                "gross_oracle_addition_difference": as_number(gross_with_expected_additions - expected_gross),
                "gross_dtr_addition_difference": as_number(gross_with_dtr_additions - expected_gross),
                "gross_component_using_expected_additions": as_number(gross_component_with_expected_additions),
                "gross_component_using_dtr_additions": as_number(gross_component_with_dtr_additions),
                "gross_component_oracle_addition_difference": as_number(gross_component_with_expected_additions - expected_gross),
                "gross_component_dtr_addition_difference": as_number(gross_component_with_dtr_additions - expected_gross),
                "expected_sss": expected["sss"],
                "expected_philhealth": expected["philhealth"],
                "expected_pagibig": expected["pagibig"],
                "expected_tax": expected["withholding_tax"],
                "expected_other_deductions": expected["total_other_deductions"],
                "expected_attendance_deduction": expected["late_undertime_amount"],
                "expected_printed_total_deductions": expected["printed_total_deductions"],
                "expected_total_deductions": expected["total_deductions"],
                "expected_taxable": expected["taxable"],
                "expected_net_pay": expected["net_pay"],
                "has_leave_or_adjustment": any(
                    dec(dtr_row[key]) != 0
                    for key in [
                        "sil_days",
                        "adjustment_amount",
                        "paternity_leave",
                        "adjustment_hours",
                        "adjustment_ot_hours",
                        "adjustment_night_diff_hours",
                        "adjustment_nd_ot_hours",
                        "adjustment_sil",
                        "adjustment_legal_holiday",
                    ]
                ),
            }
        )

    def exact(field: str, tolerance: Decimal = Decimal("0.005")) -> int:
        return sum(1 for row in regression if abs(dec(row[field])) <= tolerance)

    summary = {
        "oracle_payslips": len(oracle),
        "regression_rows": len(regression),
        "basic_exact": exact("basic_difference"),
        "precise_ot_exact": exact("precise_ot_difference"),
        "component_rounded_ot_exact": exact("component_rounding_difference"),
        "known_additions_exact": exact("addition_difference"),
        "gross_with_expected_additions_exact": exact("gross_oracle_addition_difference"),
        "gross_with_dtr_additions_exact": exact("gross_dtr_addition_difference"),
        "gross_component_with_expected_additions_exact": exact("gross_component_oracle_addition_difference"),
        "gross_component_with_dtr_additions_exact": exact("gross_component_dtr_addition_difference"),
        "rows_with_leave_or_adjustment": sum(1 for row in regression if row["has_leave_or_adjustment"]),
        "total_expected_gross": as_number(sum((dec(row["expected_gross"]) for row in regression), Decimal("0"))),
        "total_expected_net_pay": as_number(sum((dec(row["expected_net_pay"]) for row in regression), Decimal("0"))),
        "total_expected_attendance_deductions": as_number(sum((dec(row["expected_attendance_deduction"]) for row in regression), Decimal("0"))),
        "total_expected_printed_deductions": as_number(sum((dec(row["expected_printed_total_deductions"]) for row in regression), Decimal("0"))),
        "total_expected_deductions": as_number(sum((dec(row["expected_total_deductions"]) for row in regression), Decimal("0"))),
    }
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps({"summary": summary, "oracle": oracle, "regression": regression}, ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
