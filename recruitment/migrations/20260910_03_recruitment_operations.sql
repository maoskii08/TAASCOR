-- TAASCOR recruitment R2 operations.
-- Additive and rerunnable. No production or synthetic records are inserted.

CREATE TABLE IF NOT EXISTS recruitment_job_publication_events (
    publication_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    content_version INT UNSIGNED NOT NULL,
    content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    channel VARCHAR(80) NOT NULL DEFAULT 'taascor_website',
    actor_username VARCHAR(190) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (publication_event_id),
    KEY idx_recruitment_job_publication_timeline (job_id, occurred_at),
    CONSTRAINT fk_recruitment_job_publication_job
        FOREIGN KEY (job_id) REFERENCES recruitment_jobs (job_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_application_assignments (
    assignment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    assignee_username VARCHAR(190) NOT NULL,
    assignment_role VARCHAR(40) NOT NULL DEFAULT 'recruiter',
    assigned_by_username VARCHAR(190) NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at DATETIME NULL,
    released_by_username VARCHAR(190) NULL,
    release_reason VARCHAR(500) NULL,
    PRIMARY KEY (assignment_id),
    KEY idx_recruitment_assignment_active (application_id, released_at, assignee_username),
    KEY idx_recruitment_assignment_workload (assignee_username, released_at, assigned_at),
    CONSTRAINT fk_recruitment_assignment_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_application_staff_notes (
    note_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_id BIGINT UNSIGNED NOT NULL,
    note_ciphertext LONGTEXT NOT NULL,
    visibility VARCHAR(32) NOT NULL DEFAULT 'recruitment',
    created_by_username VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    superseded_at DATETIME NULL,
    superseded_by_note_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (note_id),
    KEY idx_recruitment_staff_notes_application (application_id, created_at),
    CONSTRAINT fk_recruitment_staff_note_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_staff_note_superseded
        FOREIGN KEY (superseded_by_note_id) REFERENCES recruitment_application_staff_notes (note_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_interviews (
    interview_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) NOT NULL,
    application_id BIGINT UNSIGNED NOT NULL,
    interview_type VARCHAR(60) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'draft',
    starts_at_utc DATETIME NOT NULL,
    ends_at_utc DATETIME NOT NULL,
    timezone_name VARCHAR(80) NOT NULL,
    location_type VARCHAR(32) NOT NULL,
    location_ciphertext LONGTEXT NULL,
    instructions_ciphertext LONGTEXT NULL,
    accessibility_route VARCHAR(255) NULL,
    created_by_username VARCHAR(190) NOT NULL,
    sent_at DATETIME NULL,
    confirmed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (interview_id),
    UNIQUE KEY uq_recruitment_interview_public_id (public_id),
    KEY idx_recruitment_interview_schedule (starts_at_utc, status),
    KEY idx_recruitment_interview_application (application_id, starts_at_utc),
    CONSTRAINT fk_recruitment_interview_application
        FOREIGN KEY (application_id) REFERENCES recruitment_applications (application_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_interview_participants (
    participant_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    interview_id BIGINT UNSIGNED NOT NULL,
    participant_type VARCHAR(32) NOT NULL,
    participant_reference VARCHAR(190) NOT NULL,
    panel_role VARCHAR(60) NULL,
    invitation_status VARCHAR(32) NOT NULL DEFAULT 'pending',
    responded_at DATETIME NULL,
    PRIMARY KEY (participant_id),
    UNIQUE KEY uq_recruitment_interview_participant (interview_id, participant_type, participant_reference),
    KEY idx_recruitment_participant_schedule (participant_reference, invitation_status),
    CONSTRAINT fk_recruitment_participant_interview
        FOREIGN KEY (interview_id) REFERENCES recruitment_interviews (interview_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_scorecard_criteria (
    criterion_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    criterion_code VARCHAR(80) NOT NULL,
    criterion_label VARCHAR(190) NOT NULL,
    guidance TEXT NOT NULL,
    weight_bps SMALLINT UNSIGNED NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL,
    active_from_version INT UNSIGNED NOT NULL,
    retired_at DATETIME NULL,
    PRIMARY KEY (criterion_id),
    UNIQUE KEY uq_recruitment_scorecard_criterion (job_id, criterion_code, active_from_version),
    KEY idx_recruitment_scorecard_criteria_active (job_id, retired_at, display_order),
    CONSTRAINT fk_recruitment_scorecard_criterion_job
        FOREIGN KEY (job_id) REFERENCES recruitment_jobs (job_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_interview_scorecards (
    scorecard_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    interview_id BIGINT UNSIGNED NOT NULL,
    participant_id BIGINT UNSIGNED NOT NULL,
    scorecard_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    recommendation VARCHAR(40) NULL,
    submitted_at DATETIME NULL,
    content_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (scorecard_id),
    UNIQUE KEY uq_recruitment_interview_scorecard (interview_id, participant_id),
    KEY idx_recruitment_scorecard_status (interview_id, scorecard_status),
    CONSTRAINT fk_recruitment_scorecard_interview
        FOREIGN KEY (interview_id) REFERENCES recruitment_interviews (interview_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_scorecard_participant
        FOREIGN KEY (participant_id) REFERENCES recruitment_interview_participants (participant_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_interview_scores (
    score_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scorecard_id BIGINT UNSIGNED NOT NULL,
    criterion_id BIGINT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    evidence_ciphertext LONGTEXT NOT NULL,
    PRIMARY KEY (score_id),
    UNIQUE KEY uq_recruitment_interview_score (scorecard_id, criterion_id),
    CONSTRAINT fk_recruitment_score_scorecard
        FOREIGN KEY (scorecard_id) REFERENCES recruitment_interview_scorecards (scorecard_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_recruitment_score_criterion
        FOREIGN KEY (criterion_id) REFERENCES recruitment_scorecard_criteria (criterion_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recruitment_schema_migrations (migration_name)
VALUES ('20260910_03_recruitment_operations.sql')
ON DUPLICATE KEY UPDATE migration_name = VALUES(migration_name);
