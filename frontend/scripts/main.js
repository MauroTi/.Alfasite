import { getCompletedYears } from "./modules/company-age.js";
import { initMobileNavigation } from "./modules/mobile-navigation.js";

// Acervo histórico: fundação indicada como fevereiro de 1993.
// O dia 1 é provisório até confirmação documental pela empresa.
const company = {
  foundedOn: "1993-02-01",
};

const foundedDate = new Date(`${company.foundedOn}T12:00:00`);
const currentDate = new Date();
const completedYears = getCompletedYears(foundedDate, currentDate);

document.querySelectorAll("[data-age-number]").forEach((element) => {
  element.textContent = String(completedYears);
});
document.querySelectorAll("[data-company-age]").forEach((element) => {
  element.hidden = false;
});
document.querySelectorAll("[data-founded-year]").forEach((element) => {
  element.textContent = String(foundedDate.getFullYear());
});
document.querySelectorAll("[data-current-year]").forEach((element) => {
  element.textContent = String(currentDate.getFullYear());
});

initMobileNavigation();
