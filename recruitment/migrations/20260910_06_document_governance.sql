CREATE TABLE IF NOT EXISTS recruitment_document_reviews (
    review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    decision VARCHAR(32) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    reviewer_username VARCHAR(190) NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    KEY idx_recruitment_document_reviews (document_id, reviewed_at),
    CONSTRAINT fk_recruitment_document_review_document FOREIGN KEY (document_id)
        REFERENCES recruitment_documents (document_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recruitment_document_events (
    event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(64) NOT NULL,
    actor_type VARCHAR(32) NOT NULL,
    actor_reference VARCHAR(190) NOT NULL,
    metadata_json JSON NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_recruitment_document_events (document_id, occurred_at),
    CONSTRAINT fk_recruitment_document_event_document FOREIGN KEY (document_id)
        REFERENCES recruitment_documents (document_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO recruitment_schema_migrations (migration_name)
VALUES ('20260910_06_document_governance.sql')
ON DUPLICATE KEY UPDATE migration_name = VALUES(migration_name);
