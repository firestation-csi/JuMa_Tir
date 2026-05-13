CREATE TABLE IF NOT EXISTS admin_user_credentials (
    id             INT          NOT NULL AUTO_INCREMENT,
    admin_user_id  INT          NOT NULL,
    credential_id  VARBINARY(512) NOT NULL,
    public_key     VARBINARY(1024) NOT NULL,
    sign_count     INT UNSIGNED NOT NULL DEFAULT 0,
    name           VARCHAR(100) NOT NULL DEFAULT '',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_credential_id (credential_id),
    INDEX idx_admin_user_id (admin_user_id),
    CONSTRAINT fk_admin_user_credentials_user FOREIGN KEY (admin_user_id)
        REFERENCES admin_users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
