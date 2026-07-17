-- Immutable DTR-to-payroll import run foundation.
--
-- Safety boundary:
--   * creates module-owned tables only;
--   * snapshots staged input and normalized payroll-basis rows by run;
--   * never writes legacy DTR, payroll, contribution, loan, or payslip tables;
--   * every CREATE is rerunnable through IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS notification_delivery_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_uid VARCHAR(80) NOT NULL,
    notification_id INT NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    channel VARCHAR(24) NOT NULL DEFAULT 'in_app',
    delivery_payload LONGTEXT NOT NULL,
    delivery_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    sent_at DATETIME NULL,
    last_error VARCHAR(1000) NULL,
    last_error_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_delivery_uid (delivery_uid),
    UNIQUE KEY uq_notification_delivery_idempotency (idempotency_key),
    KEY idx_notification_delivery_target (notification_id, recipient, channel, delivery_status),
    KEY idx_notification_delivery_dispatch (delivery_status, available_at, id),
    KEY idx_notification_delivery_recipient (recipient, channel, delivery_status),
    CONSTRAINT fk_notification_delivery_event
        FOREIGN KEY (notification_id) REFERENCES notification_events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_uid VARCHAR(80) NOT NULL,
    source_batch_id INT NOT NULL,
    idempotency_key CHAR(64) NOT NULL,
    client_id INT NOT NULL,
    location_id INT NULL,
    template_id INT NULL,
    source_context VARCHAR(80) NOT NULL,
    source_file_name VARCHAR(255) NOT NULL,
    source_file_checksum VARCHAR(128) NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    pay_date DATE NOT NULL,
    run_type VARCHAR(40) NOT NULL DEFAULT 'dtr_import',
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    identity_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    ruleset_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    calculation_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    reconciliation_status VARCHAR(40) NOT NULL DEFAULT 'pending',
    release_status VARCHAR(40) NOT NULL DEFAULT 'blocked',
    source_row_count INT UNSIGNED NOT NULL DEFAULT 0,
    canonical_row_count INT UNSIGNED NOT NULL DEFAULT 0,
    employee_count INT UNSIGNED NOT NULL DEFAULT 0,
    unresolved_identity_count INT UNSIGNED NOT NULL DEFAULT 0,
    identity_collision_count INT UNSIGNED NOT NULL DEFAULT 0,
    validation_error_count INT UNSIGNED NOT NULL DEFAULT 0,
    release_blocker_count INT UNSIGNED NOT NULL DEFAULT 0,
    input_snapshot_hash CHAR(64) NOT NULL,
    canonical_snapshot_hash CHAR(64) NULL,
    ruleset_key VARCHAR(120) NULL,
    ruleset_version VARCHAR(80) NULL,
    ruleset_hash CHAR(64) NULL,
    maker_created_by VARCHAR(120) NOT NULL,
    maker_created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checker_approved_by VARCHAR(120) NULL,
    checker_approved_at DATETIME NULL,
    released_by VARCHAR(120) NULL,
    released_at DATETIME NULL,
    cancelled_by VARCHAR(120) NULL,
    cancelled_at DATETIME NULL,
    failure_reason VARCHAR(1000) NULL,
    cancellation_reason VARCHAR(1000) NULL,
    lock_version INT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_runs_uid (run_uid),
    UNIQUE KEY uq_payroll_import_runs_idempotency (idempotency_key),
    KEY idx_payroll_import_runs_batch (source_batch_id),
    KEY idx_payroll_import_runs_client_period (client_id, pay_period_start, pay_period_end, pay_date),
    KEY idx_payroll_import_runs_status (status, release_status),
    CONSTRAINT fk_payroll_import_runs_batch
        FOREIGN KEY (source_batch_id) REFERENCES dtr_upload_batches (id),
    CONSTRAINT fk_payroll_import_runs_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id),
    CONSTRAINT fk_payroll_import_runs_template
        FOREIGN KEY (template_id) REFERENCES dtr_format_templates (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_run_inputs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id BIGINT UNSIGNED NOT NULL,
    source_batch_id INT NOT NULL,
    input_kind VARCHAR(40) NOT NULL DEFAULT 'staged_dtr_batch',
    source_name VARCHAR(255) NOT NULL,
    source_checksum VARCHAR(128) NULL,
    snapshot_payload LONGTEXT NOT NULL,
    snapshot_hash CHAR(64) NOT NULL,
    captured_by VARCHAR(120) NOT NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_run_inputs_batch (run_id, source_batch_id, input_kind),
    KEY idx_payroll_import_run_inputs_hash (snapshot_hash),
    CONSTRAINT fk_payroll_import_run_inputs_run
        FOREIGN KEY (run_id) REFERENCES payroll_import_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_import_run_inputs_batch
        FOREIGN KEY (source_batch_id) REFERENCES dtr_upload_batches (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_identity_decisions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    decision_uid VARCHAR(80) NOT NULL,
    client_id INT NOT NULL,
    source_namespace VARCHAR(120) NOT NULL,
    source_employee_id VARCHAR(120) NOT NULL,
    normalized_source_employee_id VARCHAR(120) NOT NULL,
    employee_id INT NULL,
    decision_type VARCHAR(40) NOT NULL,
    decision_status VARCHAR(32) NOT NULL DEFAULT 'approved',
    confidence_score DECIMAL(7,6) NULL,
    evidence_payload LONGTEXT NULL,
    reason VARCHAR(1000) NOT NULL,
    decided_by VARCHAR(120) NOT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    supersedes_decision_id BIGINT UNSIGNED NULL,
    revoked_by VARCHAR(120) NULL,
    revoked_at DATETIME NULL,
    revocation_reason VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_identity_decisions_uid (decision_uid),
    KEY idx_employee_identity_decisions_source (
        client_id, source_namespace, normalized_source_employee_id, decided_at
    ),
    KEY idx_employee_identity_decisions_employee (employee_id, decision_status),
    CONSTRAINT fk_employee_identity_decisions_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id),
    CONSTRAINT fk_employee_identity_decisions_employee
        FOREIGN KEY (employee_id) REFERENCES employee_list (employee_id) ON DELETE SET NULL,
    CONSTRAINT fk_employee_identity_decisions_supersedes
        FOREIGN KEY (supersedes_decision_id) REFERENCES employee_identity_decisions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS employee_identity_aliases (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alias_uid VARCHAR(80) NOT NULL,
    client_id INT NOT NULL,
    source_namespace VARCHAR(120) NOT NULL,
    source_employee_id VARCHAR(120) NOT NULL,
    normalized_source_employee_id VARCHAR(120) NOT NULL,
    employee_id INT NOT NULL,
    decision_id BIGINT UNSIGNED NOT NULL,
    version_no INT UNSIGNED NOT NULL,
    alias_status VARCHAR(32) NOT NULL DEFAULT 'active',
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_by VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_by VARCHAR(120) NULL,
    revoked_at DATETIME NULL,
    revocation_reason VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_identity_aliases_uid (alias_uid),
    UNIQUE KEY uq_employee_identity_aliases_version (
        client_id, source_namespace, normalized_source_employee_id, version_no
    ),
    KEY idx_employee_identity_aliases_lookup (
        client_id, source_namespace, normalized_source_employee_id, alias_status,
        effective_from, effective_to
    ),
    KEY idx_employee_identity_aliases_employee (employee_id, alias_status),
    CONSTRAINT fk_employee_identity_aliases_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id),
    CONSTRAINT fk_employee_identity_aliases_employee
        FOREIGN KEY (employee_id) REFERENCES employee_list (employee_id),
    CONSTRAINT fk_employee_identity_aliases_decision
        FOREIGN KEY (decision_id) REFERENCES employee_identity_decisions (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_run_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id BIGINT UNSIGNED NOT NULL,
    source_batch_id INT NOT NULL,
    source_staging_row_id INT NOT NULL,
    source_row_number INT NOT NULL,
    employee_id INT NOT NULL,
    identity_decision_id BIGINT UNSIGNED NULL,
    source_employee_id VARCHAR(120) NOT NULL,
    source_employee_name_snapshot VARCHAR(255) NULL,
    payroll_employee_id_snapshot VARCHAR(80) NULL,
    employee_name_snapshot VARCHAR(255) NOT NULL,
    client_id INT NOT NULL,
    location_id INT NULL,
    pay_period_start DATE NOT NULL,
    pay_period_end DATE NOT NULL,
    pay_date DATE NOT NULL,
    work_date DATE NULL,
    time_in VARCHAR(32) NULL,
    time_out VARCHAR(32) NULL,
    worked_hours DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    worked_days DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
    row_status VARCHAR(40) NOT NULL DEFAULT 'canonical',
    identity_status VARCHAR(40) NOT NULL DEFAULT 'resolved',
    validation_status VARCHAR(40) NOT NULL DEFAULT 'valid',
    conflict_status VARCHAR(40) NOT NULL DEFAULT 'clear',
    normalized_payload LONGTEXT NOT NULL,
    input_payload_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_run_rows_source (run_id, source_staging_row_id),
    KEY idx_payroll_import_run_rows_employee (run_id, employee_id),
    KEY idx_payroll_import_run_rows_period (client_id, pay_period_start, pay_period_end, pay_date),
    KEY idx_payroll_import_run_rows_source_id (run_id, source_employee_id),
    CONSTRAINT fk_payroll_import_run_rows_run
        FOREIGN KEY (run_id) REFERENCES payroll_import_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_import_run_rows_batch
        FOREIGN KEY (source_batch_id) REFERENCES dtr_upload_batches (id),
    CONSTRAINT fk_payroll_import_run_rows_staging
        FOREIGN KEY (source_staging_row_id) REFERENCES dtr_upload_staging_rows (id),
    CONSTRAINT fk_payroll_import_run_rows_employee
        FOREIGN KEY (employee_id) REFERENCES employee_list (employee_id),
    CONSTRAINT fk_payroll_import_run_rows_decision
        FOREIGN KEY (identity_decision_id) REFERENCES employee_identity_decisions (id) ON DELETE SET NULL,
    CONSTRAINT fk_payroll_import_run_rows_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_run_rule_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id BIGINT UNSIGNED NOT NULL,
    rule_type VARCHAR(80) NOT NULL,
    rule_key VARCHAR(120) NOT NULL,
    rule_version VARCHAR(80) NOT NULL,
    effective_from DATE NULL,
    effective_to DATE NULL,
    rule_snapshot LONGTEXT NOT NULL,
    rule_hash CHAR(64) NOT NULL,
    captured_by VARCHAR(120) NOT NULL,
    captured_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_run_rules (run_id, rule_type, rule_key),
    KEY idx_payroll_import_run_rules_hash (rule_hash),
    CONSTRAINT fk_payroll_import_run_rules_run
        FOREIGN KEY (run_id) REFERENCES payroll_import_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_release_checks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id BIGINT UNSIGNED NOT NULL,
    check_code VARCHAR(100) NOT NULL,
    check_category VARCHAR(80) NOT NULL,
    is_blocking TINYINT(1) NOT NULL DEFAULT 1,
    check_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    summary VARCHAR(500) NULL,
    evidence_payload LONGTEXT NULL,
    executed_by VARCHAR(120) NULL,
    executed_at DATETIME NULL,
    resolved_by VARCHAR(120) NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_release_checks (run_id, check_code),
    KEY idx_payroll_import_release_checks_gate (run_id, is_blocking, check_status),
    CONSTRAINT fk_payroll_import_release_checks_run
        FOREIGN KEY (run_id) REFERENCES payroll_import_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_payslip_artifacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    artifact_uid VARCHAR(80) NOT NULL,
    run_id BIGINT UNSIGNED NOT NULL,
    employee_id INT NOT NULL,
    artifact_type VARCHAR(40) NOT NULL DEFAULT 'payslip_pdf',
    storage_path VARCHAR(1000) NOT NULL,
    content_hash CHAR(64) NOT NULL,
    byte_size BIGINT UNSIGNED NULL,
    artifact_status VARCHAR(32) NOT NULL DEFAULT 'generated',
    generated_by VARCHAR(120) NOT NULL,
    generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified_by VARCHAR(120) NULL,
    verified_at DATETIME NULL,
    published_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_payslip_artifacts_uid (artifact_uid),
    UNIQUE KEY uq_payroll_import_payslip_employee (run_id, employee_id, artifact_type),
    KEY idx_payroll_import_payslip_status (run_id, artifact_status),
    CONSTRAINT fk_payroll_import_payslip_run
        FOREIGN KEY (run_id) REFERENCES payroll_import_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_payroll_import_payslip_employee
        FOREIGN KEY (employee_id) REFERENCES employee_list (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uid VARCHAR(80) NOT NULL,
    aggregate_type VARCHAR(80) NOT NULL,
    aggregate_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_payload LONGTEXT NOT NULL,
    event_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    published_at DATETIME NULL,
    last_error VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_outbox_uid (event_uid),
    KEY idx_payroll_import_outbox_delivery (event_status, available_at, id),
    KEY idx_payroll_import_outbox_aggregate (aggregate_type, aggregate_id),
    CONSTRAINT fk_payroll_import_outbox_run
        FOREIGN KEY (aggregate_id) REFERENCES payroll_import_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Smart payroll is opt-in per client. Merely installing the schema must never
-- disable an existing legacy payroll posting flow.
CREATE TABLE IF NOT EXISTS payroll_import_client_settings (
    client_id INT NOT NULL,
    smart_flow_enabled TINYINT(1) NOT NULL DEFAULT 0,
    enabled_by VARCHAR(120) NULL,
    enabled_at DATETIME NULL,
    rollout_notes VARCHAR(1000) NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (client_id),
    CONSTRAINT fk_payroll_import_client_settings_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_rule_sets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id INT NOT NULL,
    ruleset_key VARCHAR(120) NOT NULL,
    ruleset_version VARCHAR(80) NOT NULL,
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    rules_payload LONGTEXT NOT NULL,
    rules_hash CHAR(64) NOT NULL,
    ruleset_status VARCHAR(32) NOT NULL DEFAULT 'approved',
    approved_by VARCHAR(120) NOT NULL,
    approved_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_rule_sets_version (client_id, ruleset_key, ruleset_version),
    KEY idx_payroll_import_rule_sets_effective (
        client_id, ruleset_status, effective_from, effective_to
    ),
    CONSTRAINT fk_payroll_import_rule_sets_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_legacy_scope_bindings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id BIGINT UNSIGNED NOT NULL,
    client_name VARCHAR(160) NOT NULL,
    pay_day DATE NOT NULL,
    live_snapshot_hash CHAR(64) NOT NULL,
    employee_count INT UNSIGNED NOT NULL,
    payroll_row_count INT UNSIGNED NOT NULL,
    snapshot_payload LONGTEXT NOT NULL,
    bound_by VARCHAR(120) NOT NULL,
    bound_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_legacy_scope_run (run_id),
    KEY idx_payroll_import_legacy_scope_lookup (client_name, pay_day, run_id),
    CONSTRAINT fk_payroll_import_legacy_scope_run
        FOREIGN KEY (run_id) REFERENCES payroll_import_runs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_import_release_locks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id BIGINT UNSIGNED NOT NULL,
    client_name VARCHAR(160) NOT NULL,
    pay_day DATE NOT NULL,
    locked_by VARCHAR(120) NOT NULL,
    locked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_import_release_locks_run (run_id),
    UNIQUE KEY uq_payroll_import_release_locks_scope (client_name, pay_day),
    CONSTRAINT fk_payroll_import_release_locks_run
        FOREIGN KEY (run_id) REFERENCES payroll_import_runs (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
