# Fuji payroll reconciliation

These private-data tools compare the Fuji timekeeping summary with the text extracted from an expected payslip PDF and the employee records in HRIS.

The input files and generated JSON contain employee names, identifiers, attendance, and payroll values. Keep them outside Git and write results only to the ignored `private-output/` directory.

## Setup

```powershell
python -m pip install -r tools/fuji-reconciliation/requirements.txt
```

Set local database connection values in the environment. Do not place credentials in command history or source files.

```powershell
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3306'
$env:DB_DATABASE = 'taascor_hris'
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''
```

## Build the private employee crosswalk

```powershell
python tools/fuji-reconciliation/build_crosswalk_data.py `
  --dtr 'C:\private\TIME KEEPING.xlsx' `
  --pdf-text 'C:\private\expected-payslips.txt' `
  --client-id 264 `
  --output tools/fuji-reconciliation/private-output/crosswalk_data.json
```

Set `MYSQL_BIN` if `mysql` is not already on `PATH`.

## Run the earnings oracle

```powershell
python tools/fuji-reconciliation/build_payslip_oracle.py `
  --pdf-text 'C:\private\expected-payslips.txt' `
  --crosswalk tools/fuji-reconciliation/private-output/crosswalk_data.json `
  --output tools/fuji-reconciliation/private-output/payslip_oracle_regression.json
```

## Validated rule

The private June 16-30 sample reconciled all 691 DTR employees for basic pay, component-rounded overtime, known additions, and gross pay. Source column `BE` supplies SIL days and `BF` supplies the aggregate adjustment amount; when `BF` is present it takes precedence over the component adjustment columns to prevent double-counting.

This validates earnings only. Statutory contributions, loans, other deductions, taxable pay, and net pay still require HRIS payroll rules and owner-approved employee mappings.

## Fail-closed payroll release comparison

After exporting the candidate run to a private JSON file, compare every employee and batch total before approval:

```powershell
python tools/fuji-reconciliation/payroll_release_gate.py `
  --oracle-regression tools/fuji-reconciliation/private-output/payslip_oracle_regression.json `
  --actual-payroll C:\private\fuji-candidate-payroll.json `
  --output tools/fuji-reconciliation/private-output/payroll_release_gate.json
```

The command fails unless the employee population, component amounts, gross-to-net equation, deduction/loan ledgers, source metadata, and ruleset hashes reconcile. `--allow-missing-ledgers` is diagnostic only and must not be used for payroll release.

## Tests

```powershell
python tools/fuji-reconciliation/test_reconciliation.py
python tools/fuji-reconciliation/test_payroll_release_gate.py
```
