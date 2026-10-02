(function initializeThemeToggle() {
  const storageKey = "alfatek-theme";
  const button = document.querySelector(".theme-toggle");
  if (!button) return;

  const getSavedTheme = () => {
    try {
      return window.localStorage.getItem(storageKey);
    } catch {
      return null;
    }
  };

  const saveTheme = (theme) => {
    try {
      window.localStorage.setItem(storageKey, theme);
    } catch {
      // A alternância continua ativa nesta visita se o navegador bloquear o armazenamento.
    }
  };

  const systemPreference = window.matchMedia("(prefers-color-scheme: dark)");
  const savedTheme = getSavedTheme();
  const initialTheme = savedTheme === "light" || savedTheme === "dark"
    ? savedTheme
    : systemPreference.matches ? "dark" : "light";

  const applyTheme = (theme) => {
    const isDark = theme === "dark";
    document.documentElement.dataset.theme = theme;
    document.documentElement.style.colorScheme = theme;
    button.setAttribute("aria-pressed", String(isDark));
    button.setAttribute("aria-label", isDark ? "Ativar modo claro" : "Ativar modo escuro");
    button.setAttribute("title", isDark ? "Ativar modo claro" : "Ativar modo escuro");
    button.querySelector(".theme-toggle-icon").textContent = isDark ? "☀" : "☾";
    button.querySelector(".theme-label").textContent = isDark ? "Modo claro" : "Modo escuro";
    button.querySelector(".sr-only").textContent = isDark ? "Ativar modo claro" : "Ativar modo escuro";
    document.querySelector('meta[name="theme-color"]')?.setAttribute(
      "content",
      isDark ? "#111b22" : "#12384d",
    );
  };

  applyTheme(initialTheme);
  button.addEventListener("click", () => {
    const nextTheme = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    applyTheme(nextTheme);
    saveTheme(nextTheme);
  });

  systemPreference.addEventListener("change", (event) => {
    if (getSavedTheme() === "light" || getSavedTheme() === "dark") return;
    applyTheme(event.matches ? "dark" : "light");
  });
})();
