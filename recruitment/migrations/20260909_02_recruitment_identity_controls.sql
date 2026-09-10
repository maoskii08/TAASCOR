-- TAASCOR recruitment R1 identity and control foundation.
-- Additive and rerunnable. No production data is inserted.

CREATE TABLE IF NOT EXISTS recruitment_candidate_consents (
    consent_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id BIGINT UNSIGNED NOT NULL,
    notice_version VARCHAR(80) NOT NULL,
    purpose_code VARCHAR(80) NOT NULL,
    action VARCHAR(20) NOT NULL,
    evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (consent_id),
    KEY idx_recruitment_candidate_consents (candidate_id, purpose_code, recorded_at),
    CONSTRAINT fk_recruitment_candidate_consents_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_staff_capability_grants (
    grant_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(190) NOT NULL,
    capability VARCHAR(100) NOT NULL,
    scope_type VARCHAR(32) NOT NULL DEFAULT 'global',
    scope_reference VARCHAR(190) NOT NULL DEFAULT '*',
    granted_by_username VARCHAR(190) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoked_by_username VARCHAR(190) NULL,
    PRIMARY KEY (grant_id),
    UNIQUE KEY uq_recruitment_staff_grant (username, capability, scope_type, scope_reference),
    KEY idx_recruitment_staff_effective_grants (username, capability, revoked_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_notification_outbox (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id BIGINT UNSIGNED NOT NULL,
    message_type VARCHAR(80) NOT NULL,
    idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    payload_json LONGTEXT NOT NULL,
    delivery_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    attempt_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    sent_at DATETIME NULL,
    last_error_code VARCHAR(80) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    UNIQUE KEY uq_recruitment_notification_idempotency (idempotency_key),
    KEY idx_recruitment_notification_delivery (delivery_status, available_at, claimed_at),
    CONSTRAINT fk_recruitment_notification_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_document_requests (
    request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    purpose_code VARCHAR(80) NOT NULL,
    classification VARCHAR(40) NOT NULL,
    request_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    due_at DATETIME NULL,
    requested_by_username VARCHAR(190) NOT NULL,
    requested_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    UNIQUE KEY uq_recruitment_document_request_public_id (public_id),
    KEY idx_recruitment_document_request_queue (application_id, request_status, due_at),
    CONSTRAINT fk_recruitment_document_request_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_documents (
    document_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    request_id BIGINT UNSIGNED NOT NULL,
    candidate_id BIGINT UNSIGNED NOT NULL,
    storage_key VARCHAR(255) NOT NULL,
    original_name_ciphertext LONGTEXT NOT NULL,
    content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    media_type VARCHAR(100) NOT NULL,
    byte_size BIGINT UNSIGNED NOT NULL,
    scan_status VARCHAR(32) NOT NULL DEFAULT 'quarantined',
    review_status VARCHAR(32) NOT NULL DEFAULT 'not_available',
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    scanned_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    reviewed_by_username VARCHAR(190) NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (document_id),
    UNIQUE KEY uq_recruitment_document_public_id (public_id),
    UNIQUE KEY uq_recruitment_document_storage_key (storage_key),
    KEY idx_recruitment_document_review (request_id, scan_status, review_status, deleted_at),
    CONSTRAINT fk_recruitment_document_request
        FOREIGN KEY (request_id) REFERENCES recruitment_document_requests (request_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_document_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recruitment_schema_migrations (migration_name)
VALUES ('20260909_02_recruitment_identity_controls.sql')
ON DUPLICATE KEY UPDATE migration_name = VALUES(migration_name);
