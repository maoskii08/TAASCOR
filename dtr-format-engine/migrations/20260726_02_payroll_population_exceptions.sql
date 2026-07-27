-- Reference-population exceptions for DTR-to-payslip reconciliation.
-- This table records evidence and owner disposition only. It never creates
-- employee, DTR, payroll, deduction, loan, or payslip rows.

CREATE TABLE IF NOT EXISTS payroll_population_exceptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    exception_uid VARCHAR(80) NOT NULL,
    batch_id INT NOT NULL,
    client_id INT NOT NULL,
    reference_type VARCHAR(40) NOT NULL DEFAULT 'expected_payslip',
    reference_employee_name VARCHAR(255) NOT NULL,
    normalized_reference_name VARCHAR(255) NOT NULL,
    matched_employee_id INT NULL,
    exception_code VARCHAR(64) NOT NULL DEFAULT 'PAYSLIP_WITHOUT_DTR',
    severity VARCHAR(8) NOT NULL DEFAULT 'P0',
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    disposition VARCHAR(64) NULL,
    evidence_payload LONGTEXT NULL,
    resolution_reason VARCHAR(1000) NULL,
    created_by VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by VARCHAR(120) NULL,
    resolved_at DATETIME NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_population_exception_uid (exception_uid),
    UNIQUE KEY uq_payroll_population_exception_identity (
        batch_id, reference_type, normalized_reference_name, exception_code
    ),
    KEY idx_payroll_population_exception_batch (batch_id, status, severity),
    KEY idx_payroll_population_exception_client (client_id, status),
    KEY idx_payroll_population_exception_employee (matched_employee_id),
    CONSTRAINT fk_payroll_population_exception_batch
        FOREIGN KEY (batch_id) REFERENCES dtr_upload_batches (id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_population_exception_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id),
    CONSTRAINT fk_payroll_population_exception_employee
        FOREIGN KEY (matched_employee_id) REFERENCES employee_list (employee_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
