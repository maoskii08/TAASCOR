-- Password-reset security foundation.
-- Apply before enabling the public forgot-password workflow.

SET @add_reset_token_sql = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'taascor_user_access'
          AND COLUMN_NAME = 'reset_token'
    ) = 0,
    'ALTER TABLE taascor_user_access ADD COLUMN reset_token CHAR(64) NULL',
    'SELECT 1'
);
PREPARE add_reset_token_statement FROM @add_reset_token_sql;
EXECUTE add_reset_token_statement;
DEALLOCATE PREPARE add_reset_token_statement;

SET @add_reset_expires_sql = IF(
    (
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'taascor_user_access'
          AND COLUMN_NAME = 'reset_expires'
    ) = 0,
    'ALTER TABLE taascor_user_access ADD COLUMN reset_expires DATETIME NULL',
    'SELECT 1'
);
PREPARE add_reset_expires_statement FROM @add_reset_expires_sql;
EXECUTE add_reset_expires_statement;
DEALLOCATE PREPARE add_reset_expires_statement;

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    outcome VARCHAR(32) NOT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_password_reset_username_window (username_hash, requested_at),
    KEY idx_password_reset_ip_window (ip_hash, requested_at),
    KEY idx_password_reset_retention (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
