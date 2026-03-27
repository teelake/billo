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
    const addBtn = document.getElementById("invoice-add-line");
    if (!tbody || !addBtn) {
        return;
    }
    const tpl = document.getElementById("invoice-line-empty-row");
    if (!tpl || !(tpl instanceof HTMLTemplateElement)) {
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
        window.dispatchEvent(new CustomEvent("billo-invoice-lines-changed"));
    };

    const bindRemove = (row) => {
        row.querySelectorAll(".invoice-line-remove").forEach((btn) => {
            btn.addEventListener("click", () => {
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

/** Invoice form: live subtotal / VAT / WHT / total (matches server document-tax math) */
(function () {
    const root = document.querySelector("[data-invoice-form-totals]");
    const tbody = document.querySelector("[data-invoice-lines]");
    if (!root || !tbody) {
        return;
    }
    const docTaxOn = root.getAttribute("data-doc-tax") === "1";

    const whtRates = (() => {
        const el = document.getElementById("billo-invoice-wht-rates");
        if (!el) {
            return {};
        }
        try {
            const o = JSON.parse(el.textContent || "{}");
            return o !== null && typeof o === "object" ? o : {};
        } catch (_e) {
            return {};
        }
    })();

    const fmt = (cur, num) => {
        const n = Number.isFinite(num) ? num : 0;
        const s = n.toFixed(2);
        return `${cur} ${s}`;
    };

    const lineSubtotal = () => {
        let s = 0;
        tbody.querySelectorAll("[data-invoice-line]").forEach((row) => {
            const qEl = row.querySelector('[data-line-field="quantity"]');
            const uEl = row.querySelector('[data-line-field="unit_amount"]');
            const q = parseFloat(qEl instanceof HTMLInputElement ? qEl.value : "0");
            const u = parseFloat(uEl instanceof HTMLInputElement ? uEl.value : "0");
            if (!Number.isFinite(q) || !Number.isFinite(u)) {
                return;
            }
            s += q * u;
        });
        return Math.round(s * 100) / 100;
    };

    const sync = () => {
        const curSelect = document.getElementById("currency");
        const cur = curSelect instanceof HTMLSelectElement ? curSelect.value : "NGN";
        const sub = lineSubtotal();
        let vat = 0;
        let wht = 0;
        let grand = sub;

        const elSub = root.querySelector("[data-total-sub]");
        const elTax = root.querySelector("[data-total-tax]");
        const elGrand = root.querySelector("[data-total-grand]");

        let taxDisplay = "—";

        if (docTaxOn) {
            const vatOn = document.getElementById("inv-apply-vat");
            const vatRateEl = document.getElementById("inv-vat-rate");
            const whtOn = document.getElementById("inv-apply-wht");
            const whtSel = document.getElementById("inv-wht-id");
            const vApply = vatOn instanceof HTMLInputElement ? vatOn.checked : false;
            const wApply = whtOn instanceof HTMLInputElement ? whtOn.checked : false;
            const vr = vatRateEl instanceof HTMLInputElement ? parseFloat(vatRateEl.value) : 0;
            const wid = whtSel instanceof HTMLSelectElement ? whtSel.value : "";
            const wr =
                wid !== "" && Object.prototype.hasOwnProperty.call(whtRates, wid)
                    ? Number(whtRates[wid])
                    : NaN;

            vat =
                vApply && Number.isFinite(vr) ? Math.round(sub * (vr / 100) * 100) / 100 : 0;
            const gross = Math.round((sub + vat) * 100) / 100;
            wht =
                wApply && Number.isFinite(wr)
                    ? Math.round(sub * (wr / 100) * 100) / 100
                    : 0;
            grand = Math.round((gross - wht) * 100) / 100;
            if (grand < 0) {
                grand = 0;
            }
            const parts = [];
            if (vApply && vat > 0.0001) {
                parts.push(`VAT ${fmt(cur, vat)}`);
            } else if (vApply) {
                parts.push(`VAT ${fmt(cur, 0)}`);
            }
            if (wApply && wht > 0.0001) {
                parts.push(`WHT ${fmt(cur, wht)} (deducted)`);
            }
            taxDisplay = parts.length ? parts.join(" · ") : "—";
        } else {
            grand = sub;
        }

        if (elSub) {
            elSub.textContent = fmt(cur, sub);
        }
        if (elTax) {
            elTax.textContent = taxDisplay;
        }
        if (elGrand) {
            elGrand.innerHTML = `<strong>${fmt(cur, grand)}</strong>`;
        }
    };

    tbody.addEventListener("input", (e) => {
        if (e.target instanceof HTMLElement && e.target.closest("[data-invoice-line]")) {
            sync();
        }
    });
    document.addEventListener("billo-invoice-lines-changed", sync);
    ["inv-apply-vat", "inv-apply-wht", "inv-wht-id", "currency"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener("change", sync);
        }
    });
    const vatRate = document.getElementById("inv-vat-rate");
    if (vatRate) {
        vatRate.addEventListener("input", sync);
    }
    sync();
})();

/** Long forms: tab panels (invoice form, platform configuration, etc.) */
(function () {
    document.querySelectorAll("[data-billo-form-tabs]").forEach((root) => {
        const tabs = root.querySelectorAll("[data-billo-tab]");
        const panels = root.querySelectorAll("[data-billo-panel]");
        if (!tabs.length || !panels.length) {
            return;
        }
        const defaultId =
            root.getAttribute("data-billo-default-tab") ||
            tabs[0].getAttribute("data-billo-tab") ||
            "";
        const valid = new Set(
            Array.from(tabs).map((b) => b.getAttribute("data-billo-tab") || ""),
        );

        const show = (id) => {
            let next = id;
            if (!valid.has(next)) {
                next = defaultId;
            }
            tabs.forEach((btn) => {
                const on = (btn.getAttribute("data-billo-tab") || "") === next;
                btn.setAttribute("aria-selected", on ? "true" : "false");
                btn.tabIndex = on ? 0 : -1;
            });
            panels.forEach((p) => {
                const on = (p.getAttribute("data-billo-panel") || "") === next;
                if (on) {
                    p.removeAttribute("hidden");
                } else {
                    p.setAttribute("hidden", "");
                }
            });
            try {
                const path = window.location.pathname || "";
                const search = window.location.search || "";
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, "", path + search + "#" + next);
                }
            } catch (_e) {
                /* ignore */
            }
        };

        tabs.forEach((btn) => {
            btn.addEventListener("click", () => {
                show(btn.getAttribute("data-billo-tab") || defaultId);
            });
        });
        const hash = (window.location.hash || "").replace(/^#/, "").toLowerCase();
        if (valid.has(hash)) {
            show(hash);
        } else {
            show(defaultId);
        }
    });
})();

/** Client-side table filter: wrap table in [data-billo-filter-table], add input [data-billo-filter-input] */
(function () {
    document.querySelectorAll("[data-billo-filter-table]").forEach((wrap) => {
        const input = wrap.querySelector("[data-billo-filter-input]");
        const table = wrap.querySelector("table");
        if (!input || !table || !(input instanceof HTMLInputElement)) {
            return;
        }
        const run = () => {
            const q = input.value.trim().toLowerCase();
            table.querySelectorAll("tbody tr").forEach((tr) => {
                const blob =
                    tr.getAttribute("data-billo-search") ||
                    (tr.textContent || "").replace(/\s+/g, " ").trim();
                tr.hidden = q !== "" && !blob.toLowerCase().includes(q);
            });
        };
        input.addEventListener("input", run);
        run();
    });
})();

/** Searchable Nigerian bank field (business settings) */
(function () {
    const root = document.querySelector("[data-billo-bank-combobox]");
    const dataEl = document.getElementById("billo-ng-banks-data");
    if (!root || !dataEl) {
        return;
    }
    let banks = [];
    try {
        banks = JSON.parse(dataEl.textContent || "[]");
    } catch (e) {
        banks = [];
    }
    if (!Array.isArray(banks)) {
        banks = [];
    }
    const search = root.querySelector(".billo-combobox__search");
    const list = root.querySelector(".billo-combobox__list");
    const codeInput = root.querySelector('input[name="invoice_bank_code"]');
    if (!search || !list || !(search instanceof HTMLInputElement) || !(codeInput instanceof HTMLInputElement)) {
        return;
    }

    const normalize = (s) => (s || "").toLowerCase().trim();

    const render = (query) => {
        list.innerHTML = "";
        const q = normalize(query);
        const max = 60;
        let n = 0;
        for (let i = 0; i < banks.length && n < max; i++) {
            const b = banks[i];
            if (!b || typeof b.name !== "string" || typeof b.code !== "string") {
                continue;
            }
            const name = b.name;
            const code = b.code;
            const hay = normalize(`${name} ${code}`);
            if (q !== "" && !hay.includes(q)) {
                continue;
            }
            const li = document.createElement("li");
            li.className = "billo-combobox__item";
            li.setAttribute("role", "option");
            li.dataset.code = code;
            li.dataset.name = name;
            li.textContent = name;
            list.appendChild(li);
            n++;
        }
        list.hidden = n === 0;
    };

    search.addEventListener("input", () => {
        codeInput.value = "";
        render(search.value);
    });

    search.addEventListener("focus", () => {
        render(search.value);
    });

    list.addEventListener("mousedown", (e) => {
        e.preventDefault();
    });

    list.addEventListener("click", (e) => {
        const li = e.target && e.target.closest ? e.target.closest(".billo-combobox__item") : null;
        if (!li || !(li instanceof HTMLElement)) {
            return;
        }
        const name = li.dataset.name || "";
        const code = li.dataset.code || "";
        search.value = name;
        codeInput.value = code;
        list.hidden = true;
    });

    document.addEventListener("click", (e) => {
        if (e.target instanceof Node && !root.contains(e.target)) {
            list.hidden = true;
        }
    });
})();

/** Searchable client field (invoice create/edit) */
(function () {
    const root = document.querySelector("[data-billo-client-combobox]");
    const dataEl = document.getElementById("billo-invoice-clients-data");
    if (!root || !dataEl) {
        return;
    }
    let clients = [];
    try {
        clients = JSON.parse(dataEl.textContent || "[]");
    } catch (e) {
        clients = [];
    }
    if (!Array.isArray(clients)) {
        clients = [];
    }
    const search = root.querySelector(".billo-combobox__search");
    const list = root.querySelector(".billo-combobox__list");
    const idInput = root.querySelector('input[name="client_id"]');
    if (!search || !list || !(search instanceof HTMLInputElement) || !(idInput instanceof HTMLInputElement)) {
        return;
    }

    const normalize = (s) => (s || "").toLowerCase().trim();

    const render = (query) => {
        list.innerHTML = "";
        const q = normalize(query);
        const max = 50;
        let n = 0;
        for (let i = 0; i < clients.length && n < max; i++) {
            const c = clients[i];
            if (!c || typeof c.id !== "number") {
                continue;
            }
            const main = typeof c.name === "string" ? c.name : "";
            const sub = typeof c.email === "string" ? c.email : "";
            const co = typeof c.company === "string" ? c.company : "";
            const hay = normalize(`${main} ${sub} ${co}`);
            if (q !== "" && !hay.includes(q)) {
                continue;
            }
            const li = document.createElement("li");
            li.className = "billo-combobox__item";
            li.setAttribute("role", "option");
            li.dataset.id = String(c.id);
            li.dataset.label = main;
            li.appendChild(document.createTextNode(main));
            const subParts = [sub, co].filter(Boolean);
            if (subParts.length) {
                const span = document.createElement("span");
                span.className = "billo-combobox__sub";
                span.textContent = subParts.join(" · ");
                li.appendChild(span);
            }
            list.appendChild(li);
            n++;
        }
        list.hidden = n === 0;
    };

    search.addEventListener("input", () => {
        idInput.value = "";
        render(search.value);
    });

    search.addEventListener("focus", () => {
        render(search.value);
    });

    list.addEventListener("mousedown", (e) => {
        e.preventDefault();
    });

    list.addEventListener("click", (e) => {
        const li = e.target && e.target.closest ? e.target.closest(".billo-combobox__item") : null;
        if (!li || !(li instanceof HTMLElement)) {
            return;
        }
        const id = li.dataset.id || "";
        const label = li.dataset.label || "";
        idInput.value = id;
        search.value = label;
        list.hidden = true;
    });

    document.addEventListener("click", (e) => {
        if (e.target instanceof Node && !root.contains(e.target)) {
            list.hidden = true;
        }
    });
})();

/** Signup & password reset: grid-friendly live validation (rules mirror App\Support\PasswordRules) */
(function () {
    const MIN_LEN = 10;
    const MAX_LEN = 128;

    /**
     * @returns {{ level: string, text: string, feedbackClass: string, inputValid: boolean | null }}
     */
    function assessPassword(pw) {
        if (pw.length === 0) {
            return { level: "empty", text: "", feedbackClass: "field-feedback--muted", inputValid: null };
        }
        if (pw.length < MIN_LEN) {
            return {
                level: "weak",
                text: "Too short — use at least " + MIN_LEN + " characters.",
                feedbackClass: "field-feedback--weak",
                inputValid: false,
            };
        }
        if (pw.length > MAX_LEN) {
            return {
                level: "weak",
                text: "Too long — maximum " + MAX_LEN + " characters.",
                feedbackClass: "field-feedback--weak",
                inputValid: false,
            };
        }
        if (!/\p{L}/u.test(pw)) {
            return {
                level: "weak",
                text: "Add at least one letter.",
                feedbackClass: "field-feedback--weak",
                inputValid: false,
            };
        }
        if (!/\d/.test(pw)) {
            return {
                level: "weak",
                text: "Add at least one number.",
                feedbackClass: "field-feedback--weak",
                inputValid: false,
            };
        }
        if (!/[A-Z]/.test(pw) && !/[^A-Za-z0-9]/.test(pw)) {
            return {
                level: "weak",
                text: "Add an uppercase letter or a symbol (! @ # …).",
                feedbackClass: "field-feedback--weak",
                inputValid: false,
            };
        }
        const strong =
            pw.length >= 12 &&
            /[a-z]/.test(pw) &&
            /[A-Z]/.test(pw) &&
            /\d/.test(pw) &&
            /[^A-Za-z0-9]/.test(pw);
        if (strong) {
            return {
                level: "strong",
                text: "Strong password.",
                feedbackClass: "field-feedback--strong",
                inputValid: true,
            };
        }
        return {
            level: "good",
            text: "Good — meets our requirements.",
            feedbackClass: "field-feedback--good",
            inputValid: true,
        };
    }

    function setFeedback(el, message, className) {
        if (!el) {
            return;
        }
        el.textContent = message;
        el.className = "field-feedback " + (className || "field-feedback--muted");
    }

    function setInputState(input, state) {
        if (!input) {
            return;
        }
        input.classList.remove("input--error", "input--ok");
        if (state === "error") {
            input.classList.add("input--error");
        }
        if (state === "ok") {
            input.classList.add("input--ok");
        }
    }

    function assessConfirm(pw, confirm, feedbackEl, confirmInput) {
        if (confirm.length === 0) {
            setFeedback(feedbackEl, "", "field-feedback--muted");
            setInputState(confirmInput, null);
            return { ok: null };
        }
        if (pw === confirm) {
            setFeedback(feedbackEl, "Matches password.", "field-feedback--good");
            setInputState(confirmInput, "ok");
            return { ok: true };
        }
        setFeedback(feedbackEl, "Does not match password.", "field-feedback--weak");
        setInputState(confirmInput, "error");
        return { ok: false };
    }

    function bindPwPair(pw, cf, strengthEl, confirmFb) {
        const syncPw = () => {
            const a = assessPassword(pw.value);
            setFeedback(strengthEl, a.text, a.feedbackClass);
            if (a.level === "empty") {
                setInputState(pw, null);
            } else if (a.inputValid === false) {
                setInputState(pw, "error");
            } else if (a.inputValid === true) {
                setInputState(pw, "ok");
            }
            assessConfirm(pw.value, cf.value, confirmFb, cf);
        };
        pw.addEventListener("input", syncPw);
        cf.addEventListener("input", syncPw);
        return syncPw;
    }

    function initSignupForm(form) {
        const pw = form.querySelector("#password");
        const cf = form.querySelector("#password_confirm");
        const strengthEl = form.querySelector("#password-strength");
        const confirmFb = form.querySelector("#password-confirm-feedback");
        if (!pw || !cf || !strengthEl || !confirmFb) {
            return;
        }

        const name = form.querySelector("#name");
        const email = form.querySelector("#email");
        const org = form.querySelector("#organization_name");
        const nameFb = form.querySelector("#name-feedback");
        const emailFb = form.querySelector("#email-feedback");
        const orgFb = form.querySelector("#organization-feedback");

        const emailOk = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);

        const syncPw = bindPwPair(pw, cf, strengthEl, confirmFb);

        name?.addEventListener("blur", () => {
            if (!nameFb || !name) {
                return;
            }
            if (!name.value.trim()) {
                setFeedback(nameFb, "Enter your name.", "field-feedback--weak");
                setInputState(name, "error");
            } else {
                setFeedback(nameFb, "", "field-feedback--muted");
                setInputState(name, "ok");
            }
        });

        org?.addEventListener("blur", () => {
            if (!orgFb || !org) {
                return;
            }
            if (!org.value.trim()) {
                setFeedback(orgFb, "Enter your organization name.", "field-feedback--weak");
                setInputState(org, "error");
            } else {
                setFeedback(orgFb, "", "field-feedback--muted");
                setInputState(org, "ok");
            }
        });

        email?.addEventListener("blur", () => {
            if (!emailFb || !email || email.readOnly) {
                return;
            }
            if (!emailOk(email.value.trim())) {
                setFeedback(emailFb, "Enter a valid email address.", "field-feedback--weak");
                setInputState(email, "error");
            } else {
                setFeedback(emailFb, "", "field-feedback--muted");
                setInputState(email, "ok");
            }
        });

        form.addEventListener("submit", (e) => {
            let bad = false;
            if (name && nameFb && !name.value.trim()) {
                setFeedback(nameFb, "Enter your name.", "field-feedback--weak");
                setInputState(name, "error");
                bad = true;
            }
            if (org && orgFb && !org.readOnly && !org.value.trim()) {
                setFeedback(orgFb, "Enter your organization name.", "field-feedback--weak");
                setInputState(org, "error");
                bad = true;
            }
            if (email && emailFb && !email.readOnly && !emailOk(email.value.trim())) {
                setFeedback(emailFb, "Enter a valid email address.", "field-feedback--weak");
                setInputState(email, "error");
                bad = true;
            }
            const a = assessPassword(pw.value);
            if (a.inputValid !== true) {
                bad = true;
                setFeedback(
                    strengthEl,
                    a.level === "empty" ? "Enter a password." : a.text,
                    a.level === "empty" ? "field-feedback--weak" : a.feedbackClass,
                );
                setInputState(pw, "error");
            }
            const matchOk = pw.value === cf.value && cf.value.length > 0;
            if (!matchOk) {
                bad = true;
                if (cf.value.length > 0) {
                    setFeedback(confirmFb, "Does not match password.", "field-feedback--weak");
                } else {
                    setFeedback(confirmFb, "Confirm your password.", "field-feedback--weak");
                }
                setInputState(cf, "error");
            }
            if (bad) {
                e.preventDefault();
            }
        });

        syncPw();
    }

    function initPasswordResetForm(form) {
        const pw = form.querySelector("#password");
        const cf = form.querySelector("#password_confirm");
        const strengthEl = form.querySelector("#password-strength");
        const confirmFb = form.querySelector("#password-confirm-feedback");
        if (!pw || !cf || !strengthEl || !confirmFb) {
            return;
        }

        const syncPw = bindPwPair(pw, cf, strengthEl, confirmFb);

        form.addEventListener("submit", (e) => {
            let bad = false;
            const a = assessPassword(pw.value);
            if (a.inputValid !== true) {
                bad = true;
                setFeedback(
                    strengthEl,
                    a.level === "empty" ? "Enter a password." : a.text,
                    a.level === "empty" ? "field-feedback--weak" : a.feedbackClass,
                );
                setInputState(pw, "error");
            }
            const matchOk = pw.value === cf.value && cf.value.length > 0;
            if (!matchOk) {
                bad = true;
                if (cf.value.length > 0) {
                    setFeedback(confirmFb, "Does not match password.", "field-feedback--weak");
                } else {
                    setFeedback(confirmFb, "Confirm your password.", "field-feedback--weak");
                }
                setInputState(cf, "error");
            }
            if (bad) {
                e.preventDefault();
            }
        });

        syncPw();
    }

    document.querySelectorAll("form[data-signup-form]").forEach(initSignupForm);
    document.querySelectorAll("form[data-password-reset-form]").forEach(initPasswordResetForm);
})();

/** App sidebar (dashboard) + mobile overlay */
(function () {
    const sidebar = document.getElementById("app-sidebar");
    const toggle = document.getElementById("app-sidebar-toggle");
    const scrim = document.getElementById("app-sidebar-scrim");
    if (!sidebar || !toggle) {
        return;
    }

    const mq = window.matchMedia("(max-width: 900px)");

    const setOpen = (open) => {
        sidebar.classList.toggle("is-open", open);
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
        toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
        if (mq.matches) {
            sidebar.setAttribute("aria-hidden", open ? "false" : "true");
        } else {
            sidebar.removeAttribute("aria-hidden");
        }
        if (scrim) {
            scrim.toggleAttribute("hidden", !open || !mq.matches);
            if (open && mq.matches) {
                scrim.removeAttribute("tabindex");
            } else {
                scrim.setAttribute("tabindex", "-1");
            }
        }
    };

    toggle.addEventListener("click", () => {
        setOpen(!sidebar.classList.contains("is-open"));
    });

    if (scrim) {
        scrim.addEventListener("click", () => setOpen(false));
    }

    sidebar.querySelectorAll("a.app-sidebar__link").forEach((link) => {
        link.addEventListener("click", () => {
            if (mq.matches) {
                setOpen(false);
            }
        });
    });

    const onMq = () => {
        if (!mq.matches) {
            setOpen(false);
        }
    };
    mq.addEventListener("change", onMq);
    onMq();
    if (mq.matches) {
        setOpen(false);
    }

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && sidebar.classList.contains("is-open")) {
            setOpen(false);
        }
    });
})();
