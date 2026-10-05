-- Apply once after 004. Existing sessions must reauthenticate after this security upgrade.
ALTER TABLE user_sessions ADD permission_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL, ADD mfa_verified_at DATETIME NULL;
UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE revoked_at IS NULL;
ALTER TABLE user_mfa ADD last_counter BIGINT NOT NULL DEFAULT -1;
CREATE TABLE auth_challenges (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 session_id BIGINT UNSIGNED NULL,
 token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 purpose ENUM('mfa_login','password_change','reauth') NOT NULL,
 action VARCHAR(60) NULL,
 permission_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 password_fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 mfa_verified_at DATETIME NULL,
 expires_at DATETIME NOT NULL,
 used_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 FOREIGN KEY (session_id) REFERENCES user_sessions(id) ON DELETE CASCADE,
 INDEX idx_auth_challenge_expiry(expires_at)
) ENGINE=InnoDB;
INSERT INTO schema_migrations (version) VALUES ('005_accounts');
