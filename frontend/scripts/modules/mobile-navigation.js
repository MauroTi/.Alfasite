export function initMobileNavigation() {
  const button = document.querySelector(".menu-toggle");
  const navigation = document.querySelector("#site-nav");
  if (!button || !navigation) return;

  const closeMenu = () => {
    button.setAttribute("aria-expanded", "false");
    button.querySelector(".sr-only").textContent = "Abrir menu";
    navigation.classList.remove("is-open");
    document.body.classList.remove("menu-open");
  };

  button.addEventListener("click", () => {
    const isOpen = button.getAttribute("aria-expanded") === "true";
    button.setAttribute("aria-expanded", String(!isOpen));
    button.querySelector(".sr-only").textContent = isOpen
      ? "Abrir menu"
      : "Fechar menu";
    navigation.classList.toggle("is-open", !isOpen);
    document.body.classList.toggle("menu-open", !isOpen);
  });

  navigation.addEventListener("click", (event) => {
    if (event.target.closest("a")) closeMenu();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
      button.focus();
    }
  });

  window.addEventListener("resize", () => {
    if (window.innerWidth > 680) closeMenu();
  });
}
