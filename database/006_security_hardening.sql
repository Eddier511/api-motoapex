-- Apply once after 005. Preserves existing data. Queue contains no plaintext email or reset token.
CREATE TABLE password_recovery_jobs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 subject_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 encrypted_email TEXT NULL,
 status ENUM('pending','processing','sent','discarded','failed') NOT NULL DEFAULT 'pending',
 attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 available_at DATETIME NOT NULL,
 expires_at DATETIME NOT NULL,
 lease_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
 lease_until DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_recovery_claim(status,available_at),
 INDEX idx_recovery_lease(status,lease_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Password guesses must not maintain a global account lock that also blocks correct credentials.
UPDATE users SET locked_until=NULL WHERE locked_until IS NOT NULL;
INSERT INTO schema_migrations(version) VALUES ('006_security_hardening');
