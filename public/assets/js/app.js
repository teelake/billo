/**
 * billo — minimal UI behaviors
 */
(function () {
    const toggle = document.querySelector(".nav-toggle");
    const nav = document.querySelector("#primary-nav");
    if (!toggle || !nav) {
        return;
    }

    toggle.addEventListener("click", () => {
        const open = nav.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
        toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    });

    nav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", () => {
            nav.classList.remove("is-open");
            toggle.setAttribute("aria-expanded", "false");
            toggle.setAttribute("aria-label", "Open menu");
        });
    });
})();

/** Invoice line editor: add/remove rows, assign indexed `lines[n][field]` names */
(function () {
    const tbody = document.querySelector("[data-invoice-lines]");
    const tpl = document.getElementById("invoice-line-empty-row");
    const addBtn = document.getElementById("invoice-add-line");
    if (!tbody || !tpl || !addBtn || !(tpl instanceof HTMLTemplateElement)) {
        return;
    }

    const renumber = () => {
        const rows = tbody.querySelectorAll("[data-invoice-line]");
        rows.forEach((row, idx) => {
            row.querySelectorAll("[data-line-field]").forEach((el) => {
                const field = el.getAttribute("data-line-field");
                if (field && el instanceof HTMLInputElement) {
                    el.name = `lines[${idx}][${field}]`;
                }
            });
        });
        tbody.dataset.nextIndex = String(rows.length);
    };

    const bindRemove = (row) => {
        row.querySelectorAll(".invoice-line-remove").forEach((btn) => {
            btn.addEventListener("click", () => {
                if (tbody.querySelectorAll("[data-invoice-line]").length <= 1) {
                    return;
                }
                row.remove();
                renumber();
            });
        });
    };

    tbody.querySelectorAll("[data-invoice-line]").forEach((row) => bindRemove(row));

    addBtn.addEventListener("click", () => {
        const frag = tpl.content.cloneNode(true);
        const row = frag.querySelector("[data-invoice-line]");
        if (!row) {
            return;
        }
        tbody.appendChild(row);
        bindRemove(row);
        renumber();
    });

    renumber();
})();
