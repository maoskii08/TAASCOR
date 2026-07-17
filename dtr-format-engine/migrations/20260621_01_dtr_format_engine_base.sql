-- DTR Format Engine base schema.
-- Scope: module-owned template and upload staging tables only.
-- Safety: creates new tables only; does not alter canonical DTR, payroll,
-- employee, client, or historical payroll tables.

CREATE TABLE IF NOT EXISTS dtr_format_templates (
    id INT NOT NULL AUTO_INCREMENT,
    template_name VARCHAR(150) NOT NULL,
    client_id INT NULL,
    location_id INT NULL,
    source_type VARCHAR(80) NOT NULL,
    file_type VARCHAR(20) NOT NULL DEFAULT 'xlsx',
    expected_headers TEXT NOT NULL,
    date_format VARCHAR(50) NOT NULL,
    time_format VARCHAR(50) NOT NULL,
    employee_identifier_field VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by VARCHAR(120) NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dtr_format_templates_name (template_name),
    KEY idx_dtr_format_templates_client (client_id),
    KEY idx_dtr_format_templates_location (location_id),
    KEY idx_dtr_format_templates_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dtr_format_template_fields (
    id INT NOT NULL AUTO_INCREMENT,
    template_id INT NOT NULL,
    source_header VARCHAR(150) NOT NULL,
    canonical_field VARCHAR(150) NOT NULL,
    data_type VARCHAR(40) NOT NULL DEFAULT 'text',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    transform_rule VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dtr_format_template_fields_template (template_id),
    KEY idx_dtr_format_template_fields_canonical (canonical_field),
    CONSTRAINT fk_dtr_format_template_fields_template
        FOREIGN KEY (template_id)
        REFERENCES dtr_format_templates (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dtr_upload_batches (
    id INT NOT NULL AUTO_INCREMENT,
    batch_uid VARCHAR(64) NOT NULL,
    template_id INT NULL,
    original_filename VARCHAR(255) NOT NULL,
    uploaded_by VARCHAR(120) NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checksum VARCHAR(128) NULL,
    row_count INT NOT NULL DEFAULT 0,
    validation_status VARCHAR(40) NOT NULL DEFAULT 'not_validated',
    error_count INT NOT NULL DEFAULT 0,
    processing_status VARCHAR(40) NOT NULL DEFAULT 'staged',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dtr_upload_batches_uid (batch_uid),
    KEY idx_dtr_upload_batches_template (template_id),
    KEY idx_dtr_upload_batches_status (validation_status, processing_status),
    CONSTRAINT fk_dtr_upload_batches_template
        FOREIGN KEY (template_id)
        REFERENCES dtr_format_templates (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dtr_upload_staging_rows (
    id INT NOT NULL AUTO_INCREMENT,
    batch_id INT NOT NULL,
    source_row_number INT NOT NULL,
    raw_payload LONGTEXT NULL,
    validation_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    error_summary TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dtr_upload_staging_rows_batch (batch_id),
    KEY idx_dtr_upload_staging_rows_status (validation_status),
    CONSTRAINT fk_dtr_upload_staging_rows_batch
        FOREIGN KEY (batch_id)
        REFERENCES dtr_upload_batches (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
