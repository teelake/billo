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
