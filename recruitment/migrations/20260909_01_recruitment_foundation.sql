-- TAASCOR recruitment foundation
-- Additive and rerunnable. This migration creates no applicant, employee, job,
-- requisition, onboarding, payroll, DTR, or production data.

CREATE TABLE IF NOT EXISTS recruitment_schema_migrations (
    migration_name VARCHAR(190) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_candidates (
    candidate_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    email_lookup_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email_ciphertext LONGTEXT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    account_status VARCHAR(32) NOT NULL DEFAULT 'pending_verification',
    email_verified_at DATETIME NULL,
    session_version INT UNSIGNED NOT NULL DEFAULT 1,
    failed_login_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    privacy_notice_version VARCHAR(80) NOT NULL,
    privacy_acknowledged_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (candidate_id),
    UNIQUE KEY uq_recruitment_candidates_public_id (public_id),
    UNIQUE KEY uq_recruitment_candidates_email_hash (email_lookup_hash),
    KEY idx_recruitment_candidates_status (account_status, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_candidate_tokens (
    token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id BIGINT UNSIGNED NOT NULL,
    purpose VARCHAR(32) NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_recruitment_candidate_token_hash (token_hash),
    KEY idx_recruitment_candidate_tokens_lookup (candidate_id, purpose, expires_at, consumed_at),
    CONSTRAINT fk_recruitment_candidate_tokens_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_auth_attempts (
    attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identifier_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    network_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    outcome VARCHAR(32) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    KEY idx_recruitment_auth_attempts_identifier (identifier_hash, attempted_at),
    KEY idx_recruitment_auth_attempts_network (network_fingerprint, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_requisitions (
    requisition_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    requisition_code VARCHAR(40) NOT NULL,
    title VARCHAR(190) NOT NULL,
    headcount INT UNSIGNED NOT NULL,
    employment_type VARCHAR(60) NOT NULL,
    client_id INT NULL,
    client_location_id INT NULL,
    hiring_owner_username VARCHAR(190) NOT NULL,
    recruitment_owner_username VARCHAR(190) NULL,
    requested_by_username VARCHAR(190) NOT NULL,
    business_reason TEXT NOT NULL,
    target_start_date DATE NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    submitted_at DATETIME NULL,
    approved_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (requisition_id),
    UNIQUE KEY uq_recruitment_requisitions_public_id (public_id),
    UNIQUE KEY uq_recruitment_requisitions_code (requisition_code),
    KEY idx_recruitment_requisitions_queue (status, recruitment_owner_username, target_start_date),
    KEY idx_recruitment_requisitions_client (client_id, client_location_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_requisition_approvals (
    approval_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requisition_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(32) NOT NULL,
    decided_by_username VARCHAR(190) NOT NULL,
    decision_reason VARCHAR(500) NOT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (approval_id),
    KEY idx_recruitment_requisition_approvals (requisition_id, decided_at),
    CONSTRAINT fk_recruitment_requisition_approvals_requisition
        FOREIGN KEY (requisition_id) REFERENCES recruitment_requisitions (requisition_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_jobs (
    job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requisition_id BIGINT UNSIGNED NOT NULL,
    public_id CHAR(36) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    title VARCHAR(190) NOT NULL,
    location_label VARCHAR(190) NOT NULL,
    work_arrangement VARCHAR(60) NOT NULL,
    employment_type VARCHAR(60) NOT NULL,
    summary VARCHAR(500) NOT NULL,
    description_html MEDIUMTEXT NOT NULL,
    requirements_html MEDIUMTEXT NOT NULL,
    hiring_process_html MEDIUMTEXT NOT NULL,
    publication_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    content_version INT UNSIGNED NOT NULL DEFAULT 1,
    opens_at DATETIME NULL,
    closes_at DATETIME NULL,
    published_at DATETIME NULL,
    published_by_username VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    UNIQUE KEY uq_recruitment_jobs_public_id (public_id),
    UNIQUE KEY uq_recruitment_jobs_slug (slug),
    KEY idx_recruitment_jobs_publication (publication_status, opens_at, closes_at, published_at),
    CONSTRAINT fk_recruitment_jobs_requisition
        FOREIGN KEY (requisition_id) REFERENCES recruitment_requisitions (requisition_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_applications (
    application_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    candidate_id BIGINT UNSIGNED NOT NULL,
    job_id BIGINT UNSIGNED NOT NULL,
    current_status VARCHAR(40) NOT NULL DEFAULT 'draft',
    job_snapshot LONGTEXT NOT NULL,
    candidate_summary_ciphertext LONGTEXT NULL,
    submitted_at DATETIME NULL,
    withdrawn_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (application_id),
    UNIQUE KEY uq_recruitment_applications_public_id (public_id),
    UNIQUE KEY uq_recruitment_applications_candidate_job (candidate_id, job_id),
    KEY idx_recruitment_applications_queue (current_status, updated_at),
    CONSTRAINT fk_recruitment_applications_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_applications_job
        FOREIGN KEY (job_id) REFERENCES recruitment_jobs (job_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_application_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    candidate_message VARCHAR(500) NULL,
    reason_code VARCHAR(80) NULL,
    changed_by_type VARCHAR(32) NOT NULL,
    changed_by_reference VARCHAR(190) NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_recruitment_application_events_timeline (application_id, changed_at),
    CONSTRAINT fk_recruitment_application_events_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_audit_events (
    audit_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(100) NOT NULL,
    actor_type VARCHAR(32) NOT NULL,
    actor_reference VARCHAR(190) NOT NULL,
    subject_type VARCHAR(60) NOT NULL,
    subject_reference VARCHAR(190) NOT NULL,
    reason_code VARCHAR(80) NULL,
    metadata_json LONGTEXT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_event_id),
    KEY idx_recruitment_audit_subject (subject_type, subject_reference, occurred_at),
    KEY idx_recruitment_audit_actor (actor_type, actor_reference, occurred_at),
    KEY idx_recruitment_audit_event (event_type, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recruitment_schema_migrations (migration_name)
VALUES ('20260909_01_recruitment_foundation.sql')
ON DUPLICATE KEY UPDATE migration_name = VALUES(migration_name);
