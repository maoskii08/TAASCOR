-- Multi-client governed DTR intake foundation.
--
-- Safety boundary:
--   * adapter profiles are immutable, versioned configuration snapshots;
--   * approval history is append-only;
--   * every new real upload owns an immutable client/site/profile identity;
--   * existing batches are backfilled from their current template once;
--   * no canonical DTR, payroll, employee, or payslip rows are written.

CREATE TABLE IF NOT EXISTS dtr_adapter_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    profile_uid VARCHAR(80) NOT NULL,
    client_id INT NOT NULL,
    location_id INT NULL,
    template_id INT NOT NULL,
    adapter_key VARCHAR(120) NOT NULL,
    adapter_version VARCHAR(80) NOT NULL,
    display_name VARCHAR(180) NOT NULL,
    parser_key VARCHAR(80) NOT NULL,
    file_type VARCHAR(20) NOT NULL,
    identity_policy VARCHAR(48) NOT NULL DEFAULT 'approved_mapping_required',
    configuration_payload LONGTEXT NOT NULL,
    configuration_hash CHAR(64) NOT NULL,
    profile_status VARCHAR(32) NOT NULL DEFAULT 'draft',
    effective_from DATE NOT NULL,
    effective_to DATE NULL,
    created_by VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by VARCHAR(120) NULL,
    approved_at DATETIME NULL,
    approval_reason VARCHAR(1000) NULL,
    retired_by VARCHAR(120) NULL,
    retired_at DATETIME NULL,
    retirement_reason VARCHAR(1000) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dtr_adapter_profiles_uid (profile_uid),
    UNIQUE KEY uq_dtr_adapter_profiles_version (
        client_id, adapter_key, adapter_version
    ),
    KEY idx_dtr_adapter_profiles_lookup (
        client_id, location_id, profile_status, effective_from, effective_to
    ),
    KEY idx_dtr_adapter_profiles_template (template_id, profile_status),
    KEY idx_dtr_adapter_profiles_hash (configuration_hash),
    CONSTRAINT fk_dtr_adapter_profiles_client
        FOREIGN KEY (client_id) REFERENCES taascor_client (client_id),
    CONSTRAINT fk_dtr_adapter_profiles_template
        FOREIGN KEY (template_id) REFERENCES dtr_format_templates (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dtr_adapter_profile_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uid VARCHAR(80) NOT NULL,
    profile_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    previous_status VARCHAR(32) NULL,
    resulting_status VARCHAR(32) NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    evidence_payload LONGTEXT NOT NULL,
    evidence_hash CHAR(64) NOT NULL,
    actor VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dtr_adapter_profile_events_uid (event_uid),
    KEY idx_dtr_adapter_profile_events_profile (profile_id, created_at),
    KEY idx_dtr_adapter_profile_events_actor (actor, created_at),
    CONSTRAINT fk_dtr_adapter_profile_events_profile
        FOREIGN KEY (profile_id) REFERENCES dtr_adapter_profiles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @schema_name = DATABASE();

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'client_id'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN client_id INT NULL AFTER template_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'location_id'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN location_id INT NULL AFTER client_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'adapter_profile_id'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN adapter_profile_id BIGINT UNSIGNED NULL AFTER location_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'adapter_key'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN adapter_key VARCHAR(120) NULL AFTER adapter_profile_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'adapter_version'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN adapter_version VARCHAR(80) NULL AFTER adapter_key',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'adapter_config_hash'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN adapter_config_hash CHAR(64) NULL AFTER adapter_version',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'identity_policy'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN identity_policy VARCHAR(48) NOT NULL DEFAULT ''approved_mapping_required'' AFTER adapter_config_hash',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'upload_idempotency_key'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN upload_idempotency_key CHAR(64) NULL AFTER checksum',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'accepted_row_count'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN accepted_row_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER row_count',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'rejected_row_count'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN rejected_row_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER accepted_row_count',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'was_truncated'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN was_truncated TINYINT(1) NOT NULL DEFAULT 0 AFTER rejected_row_count',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Freeze the original client/site ownership of all legacy batches once.
UPDATE dtr_upload_batches b
INNER JOIN dtr_format_templates t ON t.id = b.template_id
SET b.client_id = COALESCE(b.client_id, t.client_id),
    b.location_id = COALESCE(b.location_id, t.location_id)
WHERE b.client_id IS NULL OR b.location_id IS NULL;

UPDATE dtr_upload_batches
SET identity_policy = 'trusted_hris_identifier'
WHERE is_synthetic = 1 OR source_context = 'synthetic_batch2';

UPDATE dtr_upload_batches
SET accepted_row_count = GREATEST(row_count - error_count, 0),
    rejected_row_count = error_count
WHERE accepted_row_count = 0
  AND rejected_row_count = 0
  AND row_count > 0;

SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND INDEX_NAME = 'idx_dtr_upload_batches_client_period'
);
SET @ddl = IF(
    @index_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD KEY idx_dtr_upload_batches_client_period (client_id, uploaded_at, processing_status)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND INDEX_NAME = 'idx_dtr_upload_batches_adapter'
);
SET @ddl = IF(
    @index_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD KEY idx_dtr_upload_batches_adapter (adapter_profile_id, adapter_version)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND INDEX_NAME = 'uq_dtr_upload_batches_idempotency'
);
SET @ddl = IF(
    @index_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD UNIQUE KEY uq_dtr_upload_batches_idempotency (upload_idempotency_key)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema_name
      AND BINARY TABLE_NAME = BINARY 'dtr_upload_batches'
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_dtr_upload_batches_client'
);
SET @ddl = IF(
    @constraint_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD CONSTRAINT fk_dtr_upload_batches_client FOREIGN KEY (client_id) REFERENCES taascor_client (client_id)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraint_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE BINARY CONSTRAINT_SCHEMA = BINARY @schema_name
      AND BINARY TABLE_NAME = BINARY 'dtr_upload_batches'
      AND BINARY CONSTRAINT_NAME = BINARY 'fk_dtr_upload_batches_adapter_profile'
);
SET @ddl = IF(
    @constraint_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD CONSTRAINT fk_dtr_upload_batches_adapter_profile FOREIGN KEY (adapter_profile_id) REFERENCES dtr_adapter_profiles (id)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
