-- DTR Format Engine staging metadata required by the parser and preview flow.
-- Scope: alters only module-owned tables created by the base migration.

SET @schema_name = DATABASE();

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'is_synthetic'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN is_synthetic TINYINT(1) NOT NULL DEFAULT 1 AFTER processing_status',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_batches'
      AND COLUMN_NAME = 'source_context'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_batches ADD COLUMN source_context VARCHAR(80) NOT NULL DEFAULT ''synthetic_batch2'' AFTER is_synthetic',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_staging_rows'
      AND COLUMN_NAME = 'parsed_payload'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_staging_rows ADD COLUMN parsed_payload LONGTEXT NULL AFTER raw_payload',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_upload_staging_rows'
      AND COLUMN_NAME = 'is_synthetic'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_upload_staging_rows ADD COLUMN is_synthetic TINYINT(1) NOT NULL DEFAULT 1 AFTER error_summary',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
