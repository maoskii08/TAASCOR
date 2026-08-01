-- Local-first DTR employee identity resolution and notification schema.
-- Safe scope: metadata/staging only. This migration does not write canonical
-- dtr_upload, payroll_summary, employee_list, loans, or contribution tables.

CREATE TABLE IF NOT EXISTS employee_identity_map (
    id INT NOT NULL AUTO_INCREMENT,
    client_id INT NOT NULL,
    source_namespace VARCHAR(120) NOT NULL,
    source_employee_id VARCHAR(120) NOT NULL,
    employee_id INT NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'approved',
    approved_by VARCHAR(120) NOT NULL,
    approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_from DATE NULL,
    effective_to DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_identity_map_source (
        client_id,
        source_namespace,
        source_employee_id
    ),
    KEY idx_employee_identity_map_employee (employee_id),
    KEY idx_employee_identity_map_status (status),
    CONSTRAINT fk_employee_identity_map_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id),
    CONSTRAINT fk_employee_identity_map_employee
        FOREIGN KEY (employee_id) REFERENCES employee_list (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dtr_employee_exceptions (
    id INT NOT NULL AUTO_INCREMENT,
    batch_id INT NOT NULL,
    staging_row_id INT NOT NULL,
    exception_code VARCHAR(64) NOT NULL,
    severity VARCHAR(8) NOT NULL DEFAULT 'P0',
    source_employee_id VARCHAR(120) NULL,
    source_employee_name VARCHAR(255) NULL,
    suggested_employee_id INT NULL,
    suggested_employee_name VARCHAR(255) NULL,
    candidate_payload LONGTEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'open',
    resolution_type VARCHAR(40) NULL,
    resolved_employee_id INT NULL,
    resolution_reason VARCHAR(500) NULL,
    created_by VARCHAR(120) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_by VARCHAR(120) NULL,
    resolved_at DATETIME NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dtr_employee_exception (
        batch_id,
        staging_row_id,
        exception_code
    ),
    KEY idx_dtr_employee_exception_status (status, severity),
    KEY idx_dtr_employee_exception_batch (batch_id, status),
    KEY idx_dtr_employee_exception_source (source_employee_id),
    CONSTRAINT fk_dtr_employee_exception_batch
        FOREIGN KEY (batch_id) REFERENCES dtr_upload_batches (id) ON DELETE CASCADE,
    CONSTRAINT fk_dtr_employee_exception_row
        FOREIGN KEY (staging_row_id) REFERENCES dtr_upload_staging_rows (id) ON DELETE CASCADE,
    CONSTRAINT fk_dtr_employee_exception_suggested
        FOREIGN KEY (suggested_employee_id) REFERENCES employee_list (employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_dtr_employee_exception_resolved
        FOREIGN KEY (resolved_employee_id) REFERENCES employee_list (employee_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_events (
    id INT NOT NULL AUTO_INCREMENT,
    fingerprint VARCHAR(190) NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    severity VARCHAR(8) NOT NULL DEFAULT 'P0',
    title VARCHAR(255) NOT NULL,
    message VARCHAR(1000) NOT NULL,
    batch_id INT NULL,
    client_id INT NULL,
    open_count INT NOT NULL DEFAULT 0,
    target_url VARCHAR(500) NULL,
    payload LONGTEXT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'open',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_events_fingerprint (fingerprint),
    KEY idx_notification_events_status (status, severity, updated_at),
    KEY idx_notification_events_batch (batch_id),
    CONSTRAINT fk_notification_events_batch
        FOREIGN KEY (batch_id) REFERENCES dtr_upload_batches (id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_events_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_recipients (
    id INT NOT NULL AUTO_INCREMENT,
    notification_id INT NOT NULL,
    user_name VARCHAR(120) NOT NULL,
    read_at DATETIME NULL,
    acknowledged_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_recipient (notification_id, user_name),
    KEY idx_notification_recipient_user (user_name, read_at),
    CONSTRAINT fk_notification_recipient_event
        FOREIGN KEY (notification_id) REFERENCES notification_events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
