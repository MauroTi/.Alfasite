-- Executar apenas no banco de homologação após criar uma conta de teste no Firebase.
-- Troque os três marcadores por UID, e-mail e autor de uma conta sintética de TESTE.
-- Nunca use UID/e-mail de produção neste arquivo.
INSERT INTO authorized_users (firebase_uid, email, role, active, require_linked_methods, created_by)
VALUES ('UID_FIREBASE_TESTE', 'administrador.teste@example.invalid', 'superadmin', 1, 0, 'bootstrap-homologacao');