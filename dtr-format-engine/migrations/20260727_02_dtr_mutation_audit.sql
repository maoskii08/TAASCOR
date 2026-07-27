-- Immutable, transaction-coupled evidence for governed DTR mutations.
--
-- Each event preserves the exact employee/payroll scope plus canonical before
-- and after JSON. The application writes this event and one compact pointer in
-- the legacy logs table inside the same transaction as the payroll mutation.
-- Missing audit storage therefore fails the mutation closed.

CREATE TABLE IF NOT EXISTS dtr_mutation_audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_uid VARCHAR(64) NOT NULL,
    operation VARCHAR(32) NOT NULL,
    scope_kind VARCHAR(24) NOT NULL,
    employee_id BIGINT UNSIGNED NULL,
    client_name VARCHAR(190) NOT NULL,
    cut_off VARCHAR(50) NULL,
    branch_id BIGINT UNSIGNED NULL,
    client_location_id BIGINT UNSIGNED NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    pay_day DATE NOT NULL,
    actor VARCHAR(120) NOT NULL,
    change_reason TEXT NOT NULL,
    evidence_reference TEXT NOT NULL,
    before_payload LONGTEXT NOT NULL,
    after_payload LONGTEXT NOT NULL,
    before_hash CHAR(64) NOT NULL,
    after_hash CHAR(64) NOT NULL,
    scope_payload LONGTEXT NOT NULL,
    scope_hash CHAR(64) NOT NULL,
    calculator_routine VARCHAR(64) NULL,
    calculator_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dtr_mutation_audit_event (event_uid),
    KEY idx_dtr_mutation_audit_scope (
        client_name, pay_day, scope_kind, employee_id, operation
    ),
    KEY idx_dtr_mutation_audit_actor (actor, created_at),
    KEY idx_dtr_mutation_audit_before_hash (before_hash),
    KEY idx_dtr_mutation_audit_after_hash (after_hash),
    KEY idx_dtr_mutation_audit_scope_hash (scope_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade an already-applied version of this migration without discarding any
-- existing EDIT/BENEFIT evidence. Dynamic guards keep this file rerunnable on
-- MySQL/MariaDB versions that do not support ADD COLUMN IF NOT EXISTS.
SET @schema_name = DATABASE();

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_mutation_audit_events'
      AND COLUMN_NAME = 'scope_kind'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_mutation_audit_events ADD COLUMN scope_kind VARCHAR(24) NULL AFTER operation',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_mutation_audit_events'
      AND COLUMN_NAME = 'branch_id'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_mutation_audit_events ADD COLUMN branch_id BIGINT UNSIGNED NULL AFTER cut_off',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_mutation_audit_events'
      AND COLUMN_NAME = 'client_location_id'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_mutation_audit_events ADD COLUMN client_location_id BIGINT UNSIGNED NULL AFTER branch_id',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_mutation_audit_events'
      AND COLUMN_NAME = 'scope_payload'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_mutation_audit_events ADD COLUMN scope_payload LONGTEXT NULL AFTER after_hash',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_mutation_audit_events'
      AND COLUMN_NAME = 'scope_hash'
);
SET @ddl = IF(
    @column_exists = 0,
    'ALTER TABLE dtr_mutation_audit_events ADD COLUMN scope_hash CHAR(64) NULL AFTER scope_payload',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Bulk events are represented by an explicit PAYROLL_SCOPE with a NULL
-- employee/cutoff. Existing employee events remain EMPLOYEE scoped.
ALTER TABLE dtr_mutation_audit_events
    MODIFY COLUMN employee_id BIGINT UNSIGNED NULL,
    MODIFY COLUMN cut_off VARCHAR(50) NULL;

UPDATE dtr_mutation_audit_events
SET scope_kind = CASE
    WHEN operation = 'DELETE_BULK' THEN 'PAYROLL_SCOPE'
    ELSE 'EMPLOYEE'
END
WHERE scope_kind IS NULL OR scope_kind = '';

-- Earlier bulk events used employee/cutoff sentinels because those columns were
-- not nullable. Normalize them before rebuilding the canonical scope evidence.
UPDATE dtr_mutation_audit_events
SET employee_id = NULL,
    cut_off = NULL,
    scope_payload = NULL,
    scope_hash = NULL
WHERE operation = 'DELETE_BULK'
  AND (employee_id IS NOT NULL OR cut_off IS NOT NULL);

UPDATE dtr_mutation_audit_events
SET scope_payload = CONCAT(
    '{"branch_id":',
    IF(branch_id IS NULL, 'null', CAST(branch_id AS CHAR)),
    ',"client_location_id":',
    IF(client_location_id IS NULL, 'null', CAST(client_location_id AS CHAR)),
    ',"client_name":',
    JSON_QUOTE(client_name),
    ',"cut_off":',
    IF(cut_off IS NULL, 'null', JSON_QUOTE(cut_off)),
    ',"employee_id":',
    IF(employee_id IS NULL, 'null', CAST(employee_id AS CHAR)),
    ',"pay_day":',
    JSON_QUOTE(CAST(pay_day AS CHAR)),
    ',"period_end":',
    IF(period_end IS NULL, 'null', JSON_QUOTE(CAST(period_end AS CHAR))),
    ',"period_start":',
    IF(period_start IS NULL, 'null', JSON_QUOTE(CAST(period_start AS CHAR))),
    ',"scope_kind":',
    JSON_QUOTE(scope_kind),
    '}'
)
WHERE scope_payload IS NULL OR scope_payload = '';

UPDATE dtr_mutation_audit_events
SET scope_hash = SHA2(scope_payload, 256)
WHERE scope_hash IS NULL OR CHAR_LENGTH(scope_hash) <> 64;

ALTER TABLE dtr_mutation_audit_events
    MODIFY COLUMN scope_kind VARCHAR(24) NOT NULL,
    MODIFY COLUMN scope_payload LONGTEXT NOT NULL,
    MODIFY COLUMN scope_hash CHAR(64) NOT NULL;

SET @scope_index_columns = (
    SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',')
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_mutation_audit_events'
      AND INDEX_NAME = 'idx_dtr_mutation_audit_scope'
);
SET @ddl = IF(
    @scope_index_columns = 'client_name,pay_day,scope_kind,employee_id,operation',
    'SELECT 1',
    IF(
        @scope_index_columns IS NULL,
        'ALTER TABLE dtr_mutation_audit_events ADD KEY idx_dtr_mutation_audit_scope (client_name, pay_day, scope_kind, employee_id, operation)',
        'ALTER TABLE dtr_mutation_audit_events DROP INDEX idx_dtr_mutation_audit_scope, ADD KEY idx_dtr_mutation_audit_scope (client_name, pay_day, scope_kind, employee_id, operation)'
    )
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'dtr_mutation_audit_events'
      AND INDEX_NAME = 'idx_dtr_mutation_audit_scope_hash'
);
SET @ddl = IF(
    @index_exists = 0,
    'ALTER TABLE dtr_mutation_audit_events ADD KEY idx_dtr_mutation_audit_scope_hash (scope_hash)',
    'SELECT 1'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
