-- Requer uma conta administrativa do MySQL. Preserva todos os registros atuais.
ALTER TABLE alfatek_auth.auth_audit_log
  MODIFY COLUMN event_type ENUM(
    'login_succeeded',
    'login_denied',
    'logout',
    'employee_created',
    'employee_updated',
    'employee_deactivated',
    'employee_reactivated',
    'employee_password_reset_requested',
    'employee_firebase_account_created',
    'employee_password_set_by_admin'
  ) NOT NULL;
