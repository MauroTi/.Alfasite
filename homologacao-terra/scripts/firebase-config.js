// Configuração pública do app Web Firebase; não contém credenciais administrativas.
// Restrinja a chave de API às APIs e origens necessárias antes da publicação.
export const firebaseConfig = Object.freeze({
  apiKey: "REPLACE_WITH_FIREBASE_WEB_API_KEY",
  authDomain: "REPLACE_WITH_FIREBASE_AUTH_DOMAIN",
  projectId: "REPLACE_WITH_FIREBASE_PROJECT_ID",
  storageBucket: "REPLACE_WITH_FIREBASE_STORAGE_BUCKET",
  messagingSenderId: "REPLACE_WITH_FIREBASE_SENDER_ID",
  appId: "REPLACE_WITH_FIREBASE_WEB_APP_ID",
});
