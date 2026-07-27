-- Exact, transaction-coupled audit evidence for Additional and Deduction changes.
--
-- One immutable event stores the complete payroll scope, reason/evidence, and a
-- canonical JSON snapshot of every inserted or deleted adjustment row. The
-- application also writes one compact pointer to the legacy logs table in the
-- same transaction. This migration never mutates existing payroll data.

CREATE TABLE IF NOT EXISTS payroll_adjustment_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uid VARCHAR(64) NOT NULL,
    adjustment_kind VARCHAR(24) NOT NULL,
    operation VARCHAR(40) NOT NULL,
    client_name VARCHAR(190) NOT NULL,
    cut_off VARCHAR(50) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    pay_day DATE NOT NULL,
    change_reason VARCHAR(255) NOT NULL,
    evidence_reference VARCHAR(255) NOT NULL,
    source_filename VARCHAR(255) NULL,
    row_count INT UNSIGNED NOT NULL,
    rows_payload LONGTEXT NOT NULL,
    payload_hash CHAR(64) NOT NULL,
    actor VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payroll_adjustment_audit_event (event_uid),
    KEY idx_payroll_adjustment_audit_scope (
        client_name, period_start, period_end, pay_day, adjustment_kind
    ),
    KEY idx_payroll_adjustment_audit_actor (actor, created_at),
    KEY idx_payroll_adjustment_audit_hash (payload_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
