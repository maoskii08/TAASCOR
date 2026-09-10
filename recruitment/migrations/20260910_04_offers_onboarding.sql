-- TAASCOR recruitment R3 offers and pre-employment onboarding.
-- Additive and rerunnable. No production or synthetic records are inserted.

CREATE TABLE IF NOT EXISTS recruitment_offers (
    offer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    current_version INT UNSIGNED NOT NULL DEFAULT 0,
    prepared_by_username VARCHAR(190) NOT NULL,
    approved_by_username VARCHAR(190) NULL,
    approved_at DATETIME NULL,
    delivered_at DATETIME NULL,
    expires_at DATETIME NULL,
    responded_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (offer_id),
    UNIQUE KEY uq_recruitment_offer_public_id (public_id),
    UNIQUE KEY uq_recruitment_offer_application (application_id),
    KEY idx_recruitment_offer_queue (status, expires_at, updated_at),
    CONSTRAINT fk_recruitment_offer_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_offer_versions (
    offer_version_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    offer_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    terms_ciphertext LONGTEXT NOT NULL,
    content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    prepared_by_username VARCHAR(190) NOT NULL,
    prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    superseded_at DATETIME NULL,
    PRIMARY KEY (offer_version_id),
    UNIQUE KEY uq_recruitment_offer_version (offer_id, version_number),
    KEY idx_recruitment_offer_version_hash (content_sha256),
    CONSTRAINT fk_recruitment_offer_version_offer
        FOREIGN KEY (offer_id) REFERENCES recruitment_offers (offer_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_offer_approvals (
    offer_approval_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    offer_id BIGINT UNSIGNED NOT NULL,
    offer_version_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(32) NOT NULL,
    decided_by_username VARCHAR(190) NOT NULL,
    decision_reason VARCHAR(500) NOT NULL,
    decided_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (offer_approval_id),
    KEY idx_recruitment_offer_approvals (offer_id, decided_at),
    CONSTRAINT fk_recruitment_offer_approval_offer
        FOREIGN KEY (offer_id) REFERENCES recruitment_offers (offer_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_offer_approval_version
        FOREIGN KEY (offer_version_id) REFERENCES recruitment_offer_versions (offer_version_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_offer_responses (
    offer_response_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    offer_id BIGINT UNSIGNED NOT NULL,
    offer_version_id BIGINT UNSIGNED NOT NULL,
    response VARCHAR(32) NOT NULL,
    response_note_ciphertext LONGTEXT NULL,
    evidence_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    responded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (offer_response_id),
    UNIQUE KEY uq_recruitment_offer_response (offer_id),
    CONSTRAINT fk_recruitment_offer_response_offer
        FOREIGN KEY (offer_id) REFERENCES recruitment_offers (offer_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_offer_response_version
        FOREIGN KEY (offer_version_id) REFERENCES recruitment_offer_versions (offer_version_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_onboarding_templates (
    template_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    template_name VARCHAR(190) NOT NULL,
    worker_type VARCHAR(60) NOT NULL,
    location_scope VARCHAR(190) NOT NULL DEFAULT '*',
    client_scope VARCHAR(190) NOT NULL DEFAULT '*',
    version_number INT UNSIGNED NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    approved_by_username VARCHAR(190) NULL,
    approved_at DATETIME NULL,
    created_by_username VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    retired_at DATETIME NULL,
    PRIMARY KEY (template_id),
    UNIQUE KEY uq_recruitment_onboarding_template_public (public_id),
    UNIQUE KEY uq_recruitment_onboarding_template_version (template_name, version_number),
    KEY idx_recruitment_onboarding_template_match (status, worker_type, location_scope, client_scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_onboarding_template_items (
    template_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(80) NOT NULL,
    title VARCHAR(190) NOT NULL,
    purpose_text VARCHAR(500) NOT NULL,
    visibility_text VARCHAR(500) NOT NULL,
    item_type VARCHAR(40) NOT NULL,
    classification VARCHAR(40) NOT NULL,
    due_offset_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    required_flag TINYINT(1) NOT NULL DEFAULT 1,
    dependency_item_code VARCHAR(80) NULL,
    display_order SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (template_item_id),
    UNIQUE KEY uq_recruitment_onboarding_template_item (template_id, item_code),
    CONSTRAINT fk_recruitment_onboarding_template_item_template
        FOREIGN KEY (template_id) REFERENCES recruitment_onboarding_templates (template_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_onboarding_cases (
    onboarding_case_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    offer_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'not_started',
    owner_username VARCHAR(190) NOT NULL,
    target_start_date DATE NULL,
    readiness_prepared_by_username VARCHAR(190) NULL,
    readiness_prepared_at DATETIME NULL,
    readiness_approved_by_username VARCHAR(190) NULL,
    readiness_approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    cancelled_at DATETIME NULL,
    PRIMARY KEY (onboarding_case_id),
    UNIQUE KEY uq_recruitment_onboarding_case_public (public_id),
    UNIQUE KEY uq_recruitment_onboarding_case_application (application_id),
    KEY idx_recruitment_onboarding_case_queue (status, owner_username, target_start_date),
    CONSTRAINT fk_recruitment_onboarding_case_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_onboarding_case_offer
        FOREIGN KEY (offer_id) REFERENCES recruitment_offers (offer_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_onboarding_case_template
        FOREIGN KEY (template_id) REFERENCES recruitment_onboarding_templates (template_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_onboarding_items (
    onboarding_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    onboarding_case_id BIGINT UNSIGNED NOT NULL,
    source_template_item_id BIGINT UNSIGNED NOT NULL,
    item_code VARCHAR(80) NOT NULL,
    title VARCHAR(190) NOT NULL,
    purpose_text VARCHAR(500) NOT NULL,
    visibility_text VARCHAR(500) NOT NULL,
    item_type VARCHAR(40) NOT NULL,
    classification VARCHAR(40) NOT NULL,
    required_flag TINYINT(1) NOT NULL,
    due_at DATETIME NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    completed_at DATETIME NULL,
    reviewed_by_username VARCHAR(190) NULL,
    reviewed_at DATETIME NULL,
    review_reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (onboarding_item_id),
    UNIQUE KEY uq_recruitment_onboarding_item_public (public_id),
    UNIQUE KEY uq_recruitment_onboarding_item_code (onboarding_case_id, item_code),
    KEY idx_recruitment_onboarding_item_queue (status, due_at, classification),
    CONSTRAINT fk_recruitment_onboarding_item_case
        FOREIGN KEY (onboarding_case_id) REFERENCES recruitment_onboarding_cases (onboarding_case_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_onboarding_item_template_item
        FOREIGN KEY (source_template_item_id) REFERENCES recruitment_onboarding_template_items (template_item_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_onboarding_item_events (
    item_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    onboarding_item_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    actor_type VARCHAR(32) NOT NULL,
    actor_reference VARCHAR(190) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_event_id),
    KEY idx_recruitment_onboarding_item_timeline (onboarding_item_id, occurred_at),
    CONSTRAINT fk_recruitment_onboarding_item_event_item
        FOREIGN KEY (onboarding_item_id) REFERENCES recruitment_onboarding_items (onboarding_item_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_notification_attempts (
    attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    provider_name VARCHAR(80) NOT NULL,
    provider_reference VARCHAR(190) NULL,
    attempt_number SMALLINT UNSIGNED NOT NULL,
    outcome VARCHAR(32) NOT NULL,
    error_code VARCHAR(80) NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (attempt_id),
    UNIQUE KEY uq_recruitment_notification_attempt (notification_id, attempt_number),
    KEY idx_recruitment_notification_provider (provider_name, provider_reference),
    CONSTRAINT fk_recruitment_notification_attempt_notification
        FOREIGN KEY (notification_id) REFERENCES recruitment_notification_outbox (notification_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_candidate_notification_preferences (
    preference_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    candidate_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(32) NOT NULL,
    message_type VARCHAR(80) NOT NULL,
    enabled_flag TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (preference_id),
    UNIQUE KEY uq_recruitment_candidate_preference (candidate_id, channel, message_type),
    CONSTRAINT fk_recruitment_candidate_preference_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_candidate_messages (
    message_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    candidate_id BIGINT UNSIGNED NOT NULL,
    application_id BIGINT UNSIGNED NULL,
    direction VARCHAR(20) NOT NULL,
    message_type VARCHAR(60) NOT NULL,
    subject_ciphertext LONGTEXT NOT NULL,
    body_ciphertext LONGTEXT NOT NULL,
    sender_type VARCHAR(32) NOT NULL,
    sender_reference VARCHAR(190) NOT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    PRIMARY KEY (message_id),
    UNIQUE KEY uq_recruitment_candidate_message_public (public_id),
    KEY idx_recruitment_candidate_messages (candidate_id, sent_at),
    KEY idx_recruitment_application_messages (application_id, sent_at),
    CONSTRAINT fk_recruitment_candidate_message_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_candidate_message_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_candidate_privacy_requests (
    privacy_request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    candidate_id BIGINT UNSIGNED NOT NULL,
    request_type VARCHAR(40) NOT NULL,
    request_ciphertext LONGTEXT NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'received',
    owner_username VARCHAR(190) NULL,
    due_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    resolution_ciphertext LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (privacy_request_id),
    UNIQUE KEY uq_recruitment_privacy_request_public (public_id),
    KEY idx_recruitment_privacy_request_queue (status, due_at, owner_username),
    CONSTRAINT fk_recruitment_privacy_request_candidate
        FOREIGN KEY (candidate_id) REFERENCES recruitment_candidates (candidate_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recruitment_schema_migrations (migration_name)
VALUES ('20260910_04_offers_onboarding.sql')
ON DUPLICATE KEY UPDATE migration_name = VALUES(migration_name);
