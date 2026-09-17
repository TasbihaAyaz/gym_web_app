/* Searchable select — enhances select.searchable / select[data-searchable] */
"use strict";

(function () {
  function buildSearchable(select) {
    if (!select || select.dataset.searchableReady === "1") return;
    select.dataset.searchableReady = "1";

    const wrap = document.createElement("div");
    wrap.className = "ss-wrap" + (select.disabled ? " is-disabled" : "");

    const display = document.createElement("button");
    display.type = "button";
    display.className = "ss-display";
    display.setAttribute("aria-haspopup", "listbox");

    const label = document.createElement("span");
    label.className = "ss-label";

    const chev = document.createElement("span");
    chev.className = "ss-chev";
    chev.innerHTML = '<svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>';

    display.appendChild(label);
    display.appendChild(chev);

    const panel = document.createElement("div");
    panel.className = "ss-panel";
    panel.hidden = true;

    const searchWrap = document.createElement("div");
    searchWrap.className = "ss-search";
    searchWrap.innerHTML = '<svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>';

    const search = document.createElement("input");
    search.type = "search";
    search.className = "ss-search-input";
    search.placeholder = select.dataset.searchPlaceholder || "Type to search...";
    search.autocomplete = "off";
    searchWrap.appendChild(search);

    const list = document.createElement("div");
    list.className = "ss-list";
    list.setAttribute("role", "listbox");

    const empty = document.createElement("div");
    empty.className = "ss-empty";
    empty.textContent = "No matches found";
    empty.hidden = true;

    panel.appendChild(searchWrap);
    panel.appendChild(list);
    panel.appendChild(empty);

    select.classList.add("ss-native");
    select.tabIndex = -1;
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    wrap.appendChild(display);
    wrap.appendChild(panel);

    function optionText(opt) {
      return (opt.textContent || "").trim();
    }

    function syncLabel() {
      const selected = select.options[select.selectedIndex];
      if (selected && selected.value !== "") {
        label.textContent = optionText(selected);
        label.classList.remove("is-placeholder");
      } else {
        label.textContent = select.dataset.placeholder || optionText(select.options[0]) || "Select...";
        label.classList.add("is-placeholder");
      }
    }

    function renderList(filter) {
      const q = (filter || "").trim().toLowerCase();
      list.innerHTML = "";
      let shown = 0;

      Array.from(select.options).forEach((opt) => {
        if (opt.value === "" && opt.disabled) return;

        const text = optionText(opt);
        if (q && !text.toLowerCase().includes(q) && !(opt.value || "").toLowerCase().includes(q)) {
          return;
        }

        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "ss-option" + (opt.selected ? " is-selected" : "") + (opt.value === "" ? " is-clear" : "");
        btn.dataset.value = opt.value;
        btn.textContent = text || "—";
        btn.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();
          select.value = opt.value;
          select.dispatchEvent(new Event("change", { bubbles: true }));
          syncLabel();
          close();
        });
        list.appendChild(btn);
        shown++;
      });

      empty.hidden = shown > 0;
    }

    function open() {
      if (select.disabled) return;
      closeAll();
      wrap.classList.add("is-open");
      panel.hidden = false;
      display.setAttribute("aria-expanded", "true");
      search.value = "";
      renderList("");
      setTimeout(() => search.focus(), 0);
    }

    function close() {
      wrap.classList.remove("is-open");
      panel.hidden = true;
      display.setAttribute("aria-expanded", "false");
    }

    function closeAll() {
      document.querySelectorAll(".ss-wrap.is-open").forEach((el) => {
        el.classList.remove("is-open");
        const p = el.querySelector(".ss-panel");
        if (p) p.hidden = true;
        const d = el.querySelector(".ss-display");
        if (d) d.setAttribute("aria-expanded", "false");
      });
    }

    display.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (wrap.classList.contains("is-open")) close();
      else open();
    });

    search.addEventListener("input", function () {
      renderList(search.value);
    });

    search.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        e.preventDefault();
        close();
        display.focus();
      }
      if (e.key === "Enter") {
        e.preventDefault();
        const first = list.querySelector(".ss-option:not(.is-clear)") || list.querySelector(".ss-option");
        first?.click();
      }
    });

    select.addEventListener("change", syncLabel);

    syncLabel();
  }

  function initAll(root) {
    (root || document)
      .querySelectorAll("select.searchable, select[data-searchable]")
      .forEach(buildSearchable);
  }

  document.addEventListener("click", function (e) {
    if (!e.target.closest(".ss-wrap")) {
      document.querySelectorAll(".ss-wrap.is-open").forEach((el) => {
        el.classList.remove("is-open");
        const p = el.querySelector(".ss-panel");
        if (p) p.hidden = true;
      });
    }
  });

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".ss-wrap.is-open").forEach((el) => {
        el.classList.remove("is-open");
        const p = el.querySelector(".ss-panel");
        if (p) p.hidden = true;
      });
    }
  });

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initAll());
  } else {
    initAll();
  }

  window.FitSearchableSelect = { init: initAll, enhance: buildSearchable };
})();
