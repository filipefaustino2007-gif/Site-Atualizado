document.addEventListener("DOMContentLoaded", () => {

  /* =========================================
     1) LINK ATIVO NA NAVBAR
     ========================================= */
  const current = window.location.pathname.split("/").pop();

  const servicosPages = ["servico.php", "servico1.php", "servico2.php", "servico3.php"];
  const portfolioPages = ["portfolio.php", "portfolio_res.php", "portfolio_urb.php", "portfolio_com.php", "portfolio_ind.php"];

  document.querySelectorAll(".navbar a").forEach(link => {
    const href = link.getAttribute("href");
    if (href === current) link.classList.add("active");
  });

  if (servicosPages.includes(current)) {
    document.querySelector(".servicos-dropdown .dropdown-head > a")?.classList.add("active");
  }

  if (portfolioPages.includes(current)) {
    document.querySelector(".portfolio-dropdown .dropdown-head > a")?.classList.add("active");
  }

  /* =========================================
     2) SELETOR DE IDIOMA (CORRIGIDO)
     ========================================= */
  const languageSelector = document.querySelector(".language-selector");
  const languageToggle   = document.querySelector(".language-toggle");
  const languageMenu     = document.querySelector(".language-menu");
  const languageForm     = document.querySelector(".language-form");
  const languageInput    = document.querySelector("input[name='lang']");

  const closeLanguageMenu = () => {
    if (!languageSelector || !languageToggle) return;
    languageSelector.classList.remove("open");
    languageToggle.setAttribute("aria-expanded", "false");
  };

  const toggleLanguageMenu = (e) => {
    e.stopPropagation();
    if (!languageSelector || !languageToggle) return;

    const isOpen = languageSelector.classList.toggle("open");
    languageToggle.setAttribute("aria-expanded", String(isOpen));
  };

  languageToggle?.addEventListener("click", toggleLanguageMenu);

  languageMenu?.addEventListener("click", (event) => {
    const option = event.target.closest(".language-option");
    if (!option || !languageInput || !languageForm) return;

    const selectedLang = option.dataset.lang;
    if (!selectedLang) return;

    languageInput.value = selectedLang;
    languageForm.submit();
  });

  document.addEventListener("click", (event) => {
    if (!languageSelector?.classList.contains("open")) return;
    if (!languageSelector.contains(event.target)) closeLanguageMenu();
  });

  /* =========================================
     3) MENU MOBILE / TABLET
     ========================================= */
  const MENU_BP  = 1024;
  const toggleBtn = document.querySelector(".menu-toggle");
  const nav = document.querySelector(".navbar");

  if (!toggleBtn || !nav) return;

  const isMenuMode = () => window.innerWidth <= MENU_BP;

  let overlay = document.querySelector(".menu-overlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.className = "menu-overlay";
    document.body.appendChild(overlay);
  }

  const closeDropdowns = () => {
    nav.querySelectorAll(".servicos-dropdown.open, .portfolio-dropdown.open")
      .forEach(d => d.classList.remove("open"));
  };

  const openMenu = () => {
    if (!isMenuMode()) return;
    nav.classList.add("active");
    toggleBtn.classList.add("active");
    overlay.classList.add("active");
    document.body.style.overflow = "hidden";
  };

  const closeMenu = () => {
    if (!isMenuMode()) return;
    nav.classList.remove("active");
    toggleBtn.classList.remove("active");
    overlay.classList.remove("active");
    document.body.style.overflow = "";
    closeDropdowns();
    closeLanguageMenu();
  };

  toggleBtn.addEventListener("click", () => {
    nav.classList.contains("active") ? closeMenu() : openMenu();
  });

  overlay.addEventListener("click", closeMenu);

  /* =========================================
     4) DROPDOWNS (SERVIÇOS / PORTFOLIO)
     ========================================= */
  nav.querySelectorAll(".dropdown-toggle").forEach(btn => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!isMenuMode()) return;

      const dropdown = btn.closest(".servicos-dropdown, .portfolio-dropdown");
      if (!dropdown) return;

      nav.querySelectorAll(".servicos-dropdown.open, .portfolio-dropdown.open")
        .forEach(d => { if (d !== dropdown) d.classList.remove("open"); });

      dropdown.classList.toggle("open");
    });
  });

  /* =========================================
     5) FECHAR MENU AO CLICAR EM LINKS
     ========================================= */
  nav.querySelectorAll("a").forEach(link => {
    link.addEventListener("click", () => {
      if (!isMenuMode()) return;
      if (link.closest(".dropdown-head")) return;
      if (link.closest(".language-selector")) return;
      closeMenu();
    });
  });

  /* =========================================
     6) ESC FECHA TUDO
     ========================================= */
  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    closeLanguageMenu();
    closeMenu();
  });

  /* =========================================
     7) RESIZE → LIMPAR ESTADOS
     ========================================= */
  window.addEventListener("resize", () => {
    if (isMenuMode()) return;
    nav.classList.remove("active");
    toggleBtn.classList.remove("active");
    overlay.classList.remove("active");
    document.body.style.overflow = "";
    closeDropdowns();
    closeLanguageMenu();
  });

});
