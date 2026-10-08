import { initializeApp } from "https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js";
import {
  browserSessionPersistence,
  EmailAuthProvider,
  getAuth,
  getAdditionalUserInfo,
  GoogleAuthProvider,
  linkWithCredential,
  linkWithPopup,
  onAuthStateChanged,
  sendEmailVerification,
  sendPasswordResetEmail,
  reauthenticateWithPopup,
  setPersistence,
  signInWithEmailAndPassword,
  signInWithPopup,
  signOut,
  unlink,
} from "https://www.gstatic.com/firebasejs/12.19.0/firebase-auth.js";
import { firebaseConfig } from "./firebase-config.js";

const firebaseConfigured = Object.values(firebaseConfig).every((value) => typeof value === "string" && value !== "" && !value.startsWith("REPLACE_WITH_"));
if (!firebaseConfigured) {
  const notice = document.querySelector("#auth-status");
  if (notice) {
    notice.querySelector("strong").textContent = "Homologação ainda não configurada";
    notice.querySelector("span").textContent = "Configure um projeto Firebase de teste e HTTPS antes de entrar. Nenhuma conta ou senha será enviada nesta etapa.";
  }
  for (const control of document.querySelectorAll("#login-form button, #google-signin, #verify-email")) control.disabled = true;
} else {
const firebaseApp = initializeApp(firebaseConfig);
const authInstance = getAuth(firebaseApp);
const form = document.querySelector("#login-form");
if (form) {
  const emailInput = document.querySelector("#email");
  const passwordInput = document.querySelector("#password");
  const emailButton = document.querySelector("#email-signin");
  const googleButton = document.querySelector("#google-signin");
  const resetButton = document.querySelector("#password-reset");
  const verifyButton = document.querySelector("#verify-email");
  const statusBox = document.querySelector("#auth-status");
  const statusTitle = statusBox.querySelector("strong");
  const statusText = statusBox.querySelector("span");
  const errorBox = document.querySelector("#auth-error");
  const successBox = document.querySelector("#auth-success");
    const isLocalLoginOrigin = window.location.hostname === "localhost";
  if (!window.isSecureContext || window.location.protocol !== "https:") {
    statusTitle.textContent = "Aguarde configuração de HTTPS";
    statusText.textContent = "O login de homologação exige HTTPS válido. Não envie credenciais por HTTP.";
    for (const control of [emailButton, googleButton, resetButton, verifyButton].filter(Boolean)) control.disabled = true;
    form.hidden = true;
    document.querySelector("#google-signin").hidden = true;
    document.querySelector(".auth-divider").hidden = true;
  } else {
  const auth = authInstance;

  let busy = true;
  let csrfToken = "";
  const authReady = setPersistence(auth, browserSessionPersistence);
  const csrfReady = fetch("api.php?action=csrf", { credentials: "same-origin", cache: "no-store" })
    .then(async (response) => {
      const result = await response.json();
      if (!response.ok || typeof result.csrfToken !== "string") throw new Error("Não foi possível preparar a sessão segura de homologação.");
      csrfToken = result.csrfToken;
    });

  function announce(message, kind = "error") {
    errorBox.hidden = kind !== "error";
    successBox.hidden = kind !== "success";
    (kind === "error" ? errorBox : successBox).textContent = message;
  }

  function setBusy(value) {
    busy = value;
    for (const control of [emailButton, googleButton, resetButton, verifyButton].filter(Boolean)) {
      control.disabled = value;
    }
  }

  function friendlyError(error) {
    const messages = {
      "auth/invalid-credential": "E-mail ou senha inválidos.",
      "auth/invalid-email": "Informe um endereço de e-mail válido.",
      "auth/user-disabled": "Esta conta está desativada. Procure o administrador.",
      "auth/too-many-requests": "Muitas tentativas. Aguarde um pouco e tente novamente.",
      "auth/popup-closed-by-user": "A janela de autenticação foi fechada antes da conclusão.",
      "auth/popup-blocked": "O navegador bloqueou a janela de autenticação. Permita pop-ups para localhost.",
      "auth/network-request-failed": "Não foi possível alcançar o Firebase. Confira a conexão e tente novamente.",
      "auth/unauthorized-domain": "O host local não está autorizado nas configurações do Firebase Authentication.",
    };
    return messages[error?.code] ?? "Não foi possível autenticar. Confira os dados ou tente novamente.";
  }

  async function createPortalSession(user) {
    await csrfReady;
    await user.reload();
    if (!user.emailVerified) {
      verifyButton.hidden = false;
      statusTitle.textContent = "Confirme seu endereço de e-mail";
      statusText.textContent = user.email
        ? `O Firebase ainda não confirmou ${user.email}. Solicite um link de verificação abaixo e abra-o na caixa de entrada dessa conta.`
        : "O Firebase ainda não confirmou o endereço desta conta. Solicite um link abaixo e confira sua caixa de entrada.";
      announce("O e-mail ainda não foi confirmado. Use o botão para solicitar um novo link.");
      return false;
    }

    const idToken = await user.getIdToken(true);
    const response = await fetch("api.php?action=session", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": csrfToken },
      body: JSON.stringify({ idToken }),
    });
    const result = await response.json().catch(() => ({}));

    if (!response.ok) {
      if (response.status === 403) {
        await signOut(auth);
        throw new Error(result.message ?? "Sua conta ainda não está autorizada pela Alfatek.");
      }
      if (response.status === 409 && result.linkRequired) {
        const linkPanel = document.querySelector("#account-link-panel");
        if (linkPanel) {
          linkPanel.hidden = false;
          document.querySelector("#account-link-status").textContent = result.message;
          linkPanel.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      }
      throw new Error(result.message ?? "O servidor local não conseguiu validar sua sessão.");
    }

    window.location.assign("portal.php");
    return true;
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (busy) return;
    announce("");
    setBusy(true);
    try {
      await authReady;
      const credential = await signInWithEmailAndPassword(auth, emailInput.value.trim(), passwordInput.value);
      passwordInput.value = "";
      await createPortalSession(credential.user);
    } catch (error) {
      announce(error.message && !error.code ? error.message : friendlyError(error));
    } finally {
      setBusy(false);
    }
  });

  googleButton.addEventListener("click", async () => {
    if (busy) return;
    announce("");
    setBusy(true);
    try {
      await authReady;
      const provider = new GoogleAuthProvider();
      provider.setCustomParameters({ prompt: "select_account" });
      const credential = await signInWithPopup(auth, provider);
      await createPortalSession(credential.user);
    } catch (error) {
      announce(error.message && !error.code ? error.message : friendlyError(error));
    } finally {
      setBusy(false);
    }
  });

  resetButton.addEventListener("click", async () => {
    const email = emailInput.value.trim();
    if (!email) {
      emailInput.focus();
      announce("Informe seu e-mail para receber o link de redefinição.");
      return;
    }
    setBusy(true);
    try {
      await authReady;
      await sendPasswordResetEmail(auth, email);
      announce("Se houver uma conta para esse endereço, o Firebase enviará as instruções de redefinição.", "success");
    } catch (error) {
      announce(friendlyError(error));
    } finally {
      setBusy(false);
    }
  });

  verifyButton.addEventListener("click", async () => {
    if (!auth.currentUser) return;
    setBusy(true);
    try {
      await authReady;
      await sendEmailVerification(auth.currentUser);
      announce("Link de verificação enviado. Confira sua caixa de entrada e o spam.", "success");
    } catch (error) {
      announce(friendlyError(error));
    } finally {
      setBusy(false);
    }
  });

  async function restoreSession() {
    try {
      await csrfReady;
      const response = await fetch("api.php?action=me", { credentials: "same-origin", cache: "no-store" });
      if (response.ok) {
        window.location.assign("portal.php");
        return;
      }
      await authReady;
      if (auth.currentUser) {
        setBusy(true);
        try {
          await createPortalSession(auth.currentUser);
        } finally {
          setBusy(false);
        }
      } else {
        setBusy(false);
      }
    } catch (error) {
      setBusy(true);
      announce(error.message && !error.code ? error.message : "A configuração de homologação ainda não está pronta.");
      statusTitle.textContent = "Finalize a configuração do servidor";
      statusText.textContent = "Nenhum dado de login foi enviado. Configure PHP, MySQL, HTTPS e o arquivo privado antes de liberar a autenticação.";
    }
  }

  onAuthStateChanged(auth, async (user) => {
    await authReady.catch(() => {});
    if (!user || busy) return;
    try {
      setBusy(true);
      await createPortalSession(user);
    } catch (error) {
      announce(error.message && !error.code ? error.message : friendlyError(error));
    } finally {
      setBusy(false);
    }
  });

  restoreSession();
  }
}

const accountLinkPanel = document.querySelector("#account-link-panel");
if (accountLinkPanel) {
  const methodsText = document.querySelector("#account-link-methods");
  const passwordForm = document.querySelector("#link-password-form");
  const passwordInput = document.querySelector("#link-password");
  const passwordConfirmInput = document.querySelector("#link-password-confirm");
  const googleLinkButton = document.querySelector("#link-google-button");
  const linkStatus = document.querySelector("#account-link-status");

  function setLinkBusy(busy) {
    for (const control of [passwordInput, passwordConfirmInput, passwordForm.querySelector("button"), googleLinkButton]) {
      if (control) control.disabled = busy;
    }
  }

  function renderLinkedMethods(user) {
    const providers = new Set(user.providerData.map((provider) => provider.providerId));
    const hasGoogle = providers.has("google.com");
    const hasPassword = providers.has("password");
    methodsText.textContent = [hasGoogle ? "Google" : null, hasPassword ? "e-mail/senha" : null]
      .filter(Boolean).join(" e ") || "Nenhum método compatível foi encontrado para esta conta.";
    passwordForm.hidden = hasPassword || !user.email;
    googleLinkButton.hidden = hasGoogle;
    if (hasGoogle && hasPassword) {
      methodsText.textContent = "Google e e-mail/senha já estão vinculados a esta mesma conta.";
    } else if (hasGoogle) {
      methodsText.textContent = "Conta Google confirmada. Defina uma senha abaixo para também entrar com e-mail e senha.";
    } else if (hasPassword) {
      methodsText.textContent = "E-mail/senha vinculados. Você também pode vincular uma conta Google com o mesmo e-mail.";
    }
  }

  onAuthStateChanged(authInstance, (user) => {
    if (!user) {
      methodsText.textContent = "Entre novamente no portal para gerenciar os métodos desta conta.";
      passwordForm.hidden = true;
      googleLinkButton.hidden = true;
      return;
    }
    renderLinkedMethods(user);
  });

  passwordForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const user = authInstance.currentUser;
    if (!user || !user.email) {
      linkStatus.textContent = "Sua sessão Firebase expirou. Saia e entre novamente pelo Google.";
      return;
    }
    const password = passwordInput.value;
    if (password.length < 12) {
      linkStatus.textContent = "Use uma senha com pelo menos 12 caracteres.";
      passwordInput.focus();
      return;
    }
    if (password !== passwordConfirmInput.value) {
      linkStatus.textContent = "As senhas digitadas não coincidem.";
      passwordConfirmInput.focus();
      return;
    }

    setLinkBusy(true);
    linkStatus.textContent = "Vinculando a senha à conta autenticada…";
    try {
      const credential = EmailAuthProvider.credential(user.email, password);
      try {
        await linkWithCredential(user, credential);
      } catch (error) {
        if (error?.code !== "auth/requires-recent-login" || !user.providerData.some((provider) => provider.providerId === "google.com")) throw error;
        await reauthenticateWithPopup(user, new GoogleAuthProvider());
        await linkWithCredential(user, credential);
      }
      await user.reload();
      passwordForm.reset();
      renderLinkedMethods(user);
      await user.getIdToken(true);
      linkStatus.textContent = "Senha vinculada. Você já pode entrar com Google ou e-mail/senha usando a mesma conta.";
      if (accountLinkPanel.classList.contains("login-account-link")) window.location.reload();
    } catch (error) {
      const messages = {
        "auth/email-already-in-use": "Este e-mail já está associado a outra conta Firebase. Entre pelo método já existente e peça ajuda ao administrador para evitar contas duplicadas.",
        "auth/credential-already-in-use": "Esta credencial já pertence a outra identidade Firebase. Não vinculamos contas automaticamente apenas pelo e-mail.",
        "auth/provider-already-linked": "O método e-mail/senha já está vinculado a esta conta.",
        "auth/weak-password": "O Firebase recusou a senha. Escolha uma senha mais forte e tente novamente.",
        "auth/requires-recent-login": "Por segurança, saia, entre novamente com Google e repita a vinculação.",
        "auth/network-request-failed": "Não foi possível alcançar o Firebase. Confira sua conexão e tente novamente.",
      };
      linkStatus.textContent = messages[error?.code] ?? "Não foi possível vincular a senha. Tente novamente ou fale com o administrador.";
    } finally {
      setLinkBusy(false);
    }
  });

  googleLinkButton.addEventListener("click", async () => {
    const user = authInstance.currentUser;
    if (!user) {
      linkStatus.textContent = "Sua sessão Firebase expirou. Saia e entre novamente.";
      return;
    }
    setLinkBusy(true);
    linkStatus.textContent = "Confirme no Google a conta que deseja vincular…";
    const provider = new GoogleAuthProvider();
    provider.setCustomParameters({ prompt: "select_account" });
    try {
      const result = await linkWithPopup(user, provider);
      const profile = getAdditionalUserInfo(result)?.profile;
      const googleEmail = typeof profile?.email === "string" ? profile.email.toLowerCase() : "";
      if (!googleEmail || googleEmail !== user.email?.toLowerCase()) {
        await unlink(result.user, "google.com");
        throw new Error("google-email-mismatch");
      }
      await user.reload();
      renderLinkedMethods(user);
      linkStatus.textContent = "Conta Google vinculada com o mesmo e-mail. Os dois métodos usam agora a mesma identidade.";
      await user.getIdToken(true);
      if (accountLinkPanel.classList.contains("login-account-link")) window.location.reload();
    } catch (error) {
      const messages = {
        "auth/credential-already-in-use": "Esta conta Google já está vinculada a outro cadastro Firebase. Não mesclamos contas automaticamente; procure o administrador.",
        "auth/provider-already-linked": "Uma conta Google já está vinculada a este usuário.",
        "auth/popup-closed-by-user": "A janela do Google foi fechada antes de concluir a vinculação.",
        "auth/popup-blocked": "O navegador bloqueou a janela do Google. Permita pop-ups para localhost.",
        "auth/requires-recent-login": "Por segurança, saia, entre novamente com sua senha e repita a vinculação.",
        "google-email-mismatch": "A conta Google escolhida tem outro e-mail. O vínculo foi cancelado; escolha a conta com o mesmo endereço.",
      };
      linkStatus.textContent = messages[error?.code] ?? "Não foi possível vincular a conta Google. Tente novamente ou fale com o administrador.";
    } finally {
      setLinkBusy(false);
    }
  });
}

const logoutButton = document.querySelector("#logout-button");
if (logoutButton) {
  logoutButton.addEventListener("click", async () => {
    logoutButton.disabled = true;
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";
      const response = await fetch("api.php?action=logout", {
        method: "POST",
        credentials: "same-origin",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrfToken,
        },
        body: "{}",
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.message ?? "Não foi possível encerrar a sessão.");
      const { getAuth: loadAuth, signOut: signOutUser } = await import("https://www.gstatic.com/firebasejs/12.19.0/firebase-auth.js");
      const { initializeApp: loadApp } = await import("https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js");
      const { firebaseConfig: config } = await import("./firebase-config.js");
      await signOutUser(loadAuth(loadApp(config)));
      window.location.assign("index.html");
    } catch (error) {
      logoutButton.disabled = false;
      window.alert(error.message);
    }
  });
}

}
