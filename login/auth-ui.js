(() => {
    const rules = [
        { test: (value) => value.length >= 8, label: "8+ characters" },
        { test: (value) => /[A-Z]/.test(value), label: "uppercase" },
        { test: (value) => /[a-z]/.test(value), label: "lowercase" },
        { test: (value) => /[0-9]/.test(value), label: "number" },
        { test: (value) => /[^A-Za-z0-9]/.test(value), label: "special character" },
    ];

    function bindPasswordToggles() {
        document.querySelectorAll("[data-password-toggle]").forEach((button) => {
            if (button.dataset.boundPasswordToggle === "true") {
                return;
            }

            const input = document.getElementById(button.getAttribute("data-password-toggle"));
            const icon = button.querySelector("i");

            if (!input || !icon) {
                return;
            }

            const updateToggleState = (isVisible) => {
                icon.className = isVisible ? "fas fa-eye-slash" : "fas fa-eye";
                button.classList.toggle("is-visible", isVisible);
                button.setAttribute("aria-label", isVisible ? "Hide password" : "Show password");
                button.setAttribute("aria-pressed", isVisible ? "true" : "false");
            };

            button.dataset.boundPasswordToggle = "true";
            updateToggleState(input.getAttribute("type") === "text");
            button.addEventListener("click", () => {
                const shouldShow = input.getAttribute("type") === "password";
                input.setAttribute("type", shouldShow ? "text" : "password");
                updateToggleState(shouldShow);
            });
        });
    }

    function bindAuthTransitions() {
        const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

        document.querySelectorAll("[data-auth-transition]").forEach((link) => {
            link.addEventListener("click", (event) => {
                if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || reducedMotion.matches) {
                    return;
                }

                event.preventDefault();
                document.body.classList.add("is-auth-leaving");
                window.setTimeout(() => window.location.assign(link.href), 160);
            });
        });

        window.addEventListener("pageshow", () => document.body.classList.remove("is-auth-leaving"));
    }

    function passwordStrength(value) {
        const passed = rules.filter((rule) => rule.test(value));

        if (!value) {
            return {
                level: "empty",
                message: "Use at least 8 characters with uppercase, lowercase, number, and special character.",
            };
        }

        if (passed.length <= 2) {
            return {
                level: "weak",
                message: "Weak password. Add " + rules.filter((rule) => !rule.test(value)).map((rule) => rule.label).join(", ") + ".",
            };
        }

        if (passed.length <= 4) {
            return {
                level: "fair",
                message: "Almost strong. Add " + rules.filter((rule) => !rule.test(value)).map((rule) => rule.label).join(", ") + ".",
            };
        }

        return {
            level: "strong",
            message: "Strong password.",
        };
    }

    function bindPasswordStrength() {
        document.querySelectorAll("[data-password-strength]").forEach((input) => {
            const field = input.closest(".field");
            const feedback = field ? field.querySelector("[data-password-feedback]") : null;

            if (!feedback) {
                return;
            }

            const update = () => {
                const result = passwordStrength(input.value);
                feedback.textContent = result.message;
                feedback.dataset.strength = result.level;
            };

            input.addEventListener("input", update);
            update();
        });
    }

    function bindMultiChoiceSelects() {
        document.querySelectorAll(".multi-choice-select").forEach((select) => {
            const countLabel = select.querySelector("summary small");
            const inputs = select.querySelectorAll("input[type='checkbox']");

            if (!countLabel || inputs.length === 0) {
                return;
            }

            const update = () => {
                const selectedCount = Array.from(inputs).filter((input) => input.checked).length;
                countLabel.textContent = selectedCount + " selected";
            };

            inputs.forEach((input) => input.addEventListener("change", update));
            update();
        });
    }

    document.addEventListener("DOMContentLoaded", () => {
        bindPasswordToggles();
        bindPasswordStrength();
        bindMultiChoiceSelects();
        bindAuthTransitions();
    });
})();
