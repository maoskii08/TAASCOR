-- TAASCOR recruitment R4 employee conversion and reconciliation.
-- Additive and rerunnable. This migration never creates or changes employees.

CREATE TABLE IF NOT EXISTS recruitment_employee_conversion_requests (
    conversion_request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    onboarding_case_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    employee_payload_ciphertext LONGTEXT NOT NULL,
    employee_payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    duplicate_check_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    duplicate_check_evidence_json LONGTEXT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'draft',
    prepared_by_username VARCHAR(190) NOT NULL,
    prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by_username VARCHAR(190) NULL,
    approved_at DATETIME NULL,
    executed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (conversion_request_id),
    UNIQUE KEY uq_recruitment_conversion_public (public_id),
    UNIQUE KEY uq_recruitment_conversion_onboarding (onboarding_case_id),
    UNIQUE KEY uq_recruitment_conversion_idempotency (idempotency_key),
    KEY idx_recruitment_conversion_queue (status, duplicate_check_status, updated_at),
    CONSTRAINT fk_recruitment_conversion_onboarding_case
        FOREIGN KEY (onboarding_case_id) REFERENCES recruitment_onboarding_cases (onboarding_case_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_conversion_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_employee_conversion_reviews (
    conversion_review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversion_request_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(32) NOT NULL,
    reviewed_by_username VARCHAR(190) NOT NULL,
    decision_reason VARCHAR(500) NOT NULL,
    payload_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (conversion_review_id),
    KEY idx_recruitment_conversion_reviews (conversion_request_id, reviewed_at),
    CONSTRAINT fk_recruitment_conversion_review_request
        FOREIGN KEY (conversion_request_id) REFERENCES recruitment_employee_conversion_requests (conversion_request_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_candidate_employee_links (
    candidate_employee_link_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversion_request_id BIGINT UNSIGNED NOT NULL,
    candidate_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    employee_reference VARCHAR(190) NOT NULL,
    linked_by_username VARCHAR(190) NOT NULL,
    linked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reversed_at DATETIME NULL,
    reversal_reason VARCHAR(500) NULL,
    PRIMARY KEY (candidate_employee_link_id),
    UNIQUE KEY uq_recruitment_candidate_employee_conversion (conversion_request_id),
    UNIQUE KEY uq_recruitment_candidate_employee_application (application_id),
    KEY idx_recruitment_candidate_employee_reference (employee_reference, reversed_at),
    CONSTRAINT fk_recruitment_candidate_employee_conversion
        FOREIGN KEY (conversion_request_id) REFERENCES recruitment_employee_conversion_requests (conversion_request_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_candidate_employee_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_candidate_employee_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_employee_conversion_reconciliations (
    reconciliation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversion_request_id BIGINT UNSIGNED NOT NULL,
    employee_reference VARCHAR(190) NULL,
    outcome VARCHAR(32) NOT NULL,
    expected_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    observed_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    details_json LONGTEXT NOT NULL,
    reconciled_by_username VARCHAR(190) NOT NULL,
    reconciled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reconciliation_id),
    KEY idx_recruitment_conversion_reconciliation (conversion_request_id, reconciled_at),
    KEY idx_recruitment_conversion_reconciliation_outcome (outcome, reconciled_at),
    CONSTRAINT fk_recruitment_conversion_reconciliation_request
        FOREIGN KEY (conversion_request_id) REFERENCES recruitment_employee_conversion_requests (conversion_request_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recruitment_schema_migrations (migration_name)
VALUES ('20260910_05_employee_conversion.sql')
ON DUPLICATE KEY UPDATE migration_name = VALUES(migration_name);
