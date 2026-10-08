CREATE TABLE authorized_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  firebase_uid VARCHAR(128) NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  role ENUM('employee','leader','supervisor','admin','superadmin') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  require_linked_methods TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(254) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT chk_authorized_email_lower CHECK (email = LOWER(email))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  firebase_uid VARCHAR(128) NULL,
  email VARCHAR(254) NOT NULL,
  actor_email VARCHAR(254) NULL,
  target_email VARCHAR(254) NULL,
  event_type ENUM('login_succeeded','login_denied','logout','employee_created','employee_updated','employee_deactivated','employee_reactivated','employee_password_reset_requested','employee_firebase_account_created','employee_password_set_by_admin') NOT NULL,
  details_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auth_audit_email_created (email, created_at),
  INDEX idx_auth_audit_actor_created (actor_email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- O banco começa sem usuários; cadastre o primeiro administrador pelo procedimento privado descrito no guia.