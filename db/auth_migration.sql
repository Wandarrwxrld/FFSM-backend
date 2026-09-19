-- ============================================================================
-- FFMS — AUTH MIGRATION
-- Adds real email verification, password reset, and API bearer-token tables
-- on top of the existing schema (gemini-code-1788557271192.sql / ffms_schema.sql).
-- Run this AFTER the base schema has been created.
-- ============================================================================

USE ffms_db;

-- Track whether an account's email has actually been confirmed.
ALTER TABLE users
  ADD COLUMN email_verified_at DATETIME NULL AFTER status;

-- One row per verification email sent. Token itself is never stored —
-- only its SHA-256 hash — so a leaked database dump can't be used to
-- verify arbitrary accounts.
CREATE TABLE email_verifications (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash)
) ENGINE=InnoDB;

-- One row per "forgot password" request. Same hashed-token approach.
CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash)
) ENGINE=InnoDB;

-- Bearer tokens issued at login. The front end sends this back as
-- "Authorization: Bearer <token>" on every API call — this is what
-- replaces PHP sessions, since the front end (Netlify) and the backend
-- (wherever it's hosted) are on different domains.
CREATE TABLE auth_tokens (
    token_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    user_agent VARCHAR(255),
    ip_address VARCHAR(45),
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash)
) ENGINE=InnoDB;
