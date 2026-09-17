/* Global UI helpers — single capture listener (safe with dashboard scripts) */
"use strict";

(function () {
  if (window.__fgUiBound) return;
  window.__fgUiBound = true;

  var THEME_KEY = "fg-theme";

  function currentTheme() {
    var attr = document.documentElement.getAttribute("data-theme");
    if (attr === "dark" || attr === "light") return attr;
    try {
      var saved = localStorage.getItem(THEME_KEY);
      if (saved === "dark" || saved === "light") return saved;
    } catch (_) {}
    return "light";
  }

  function applyTheme(theme) {
    var next = theme === "dark" ? "dark" : "light";
    document.documentElement.setAttribute("data-theme", next);
    try {
      localStorage.setItem(THEME_KEY, next);
    } catch (_) {}
    var btn = document.getElementById("theme-toggle");
    if (btn) {
      btn.setAttribute("aria-pressed", next === "dark" ? "true" : "false");
      btn.title = next === "dark" ? "Switch to light theme" : "Switch to dark theme";
    }
  }

  function toggleTheme() {
    applyTheme(currentTheme() === "dark" ? "light" : "dark");
  }

  applyTheme(currentTheme());

  document.addEventListener("click", function (e) {
    var btn = e.target.closest("#theme-toggle");
    if (!btn) return;
    e.preventDefault();
    toggleTheme();
  });

  function closeAllMenus(except) {
    document.querySelectorAll(".dropdown-menu.open").forEach((menu) => {
      if (menu !== except) menu.classList.remove("open");
    });
  }

  document.addEventListener("keydown", function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === "k") {
      e.preventDefault();
      document.querySelector("#top-search-form input[name=search]")?.focus();
    }
    if (e.key === "Escape") closeAllMenus();
  });

  // Capture phase so dashboard/app.js handlers cannot double-toggle the menu
  document.addEventListener(
    "click",
    function (e) {
      const toggle = e.target.closest("[data-dropdown-toggle]");

      if (toggle) {
        e.preventDefault();
        e.stopPropagation();

        const menu = toggle.parentElement?.querySelector(".dropdown-menu");
        if (!menu) return;

        const willOpen = !menu.classList.contains("open");
        closeAllMenus();
        if (willOpen) {
          menu.classList.add("open");
          toggle.setAttribute("aria-expanded", "true");
        } else {
          toggle.setAttribute("aria-expanded", "false");
        }
        return;
      }

      if (!e.target.closest(".dropdown-menu")) {
        document.querySelectorAll("[data-dropdown-toggle][aria-expanded='true']").forEach((btn) => {
          btn.setAttribute("aria-expanded", "false");
        });
        closeAllMenus();
      }
    },
    true
  );
})();
