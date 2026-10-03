(() => {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    function dismiss(notification) {
        if (!notification || notification.dataset.dismissed === "true") {
            return;
        }

        notification.dataset.dismissed = "true";
        notification.classList.add("is-dismissing");

        const remove = () => {
            const stack = notification.parentElement;
            notification.remove();
            if (stack && !stack.querySelector("[data-flash-notification]")) {
                stack.remove();
            }
        };

        if (reduceMotion.matches) {
            remove();
            return;
        }

        notification.addEventListener("transitionend", remove, { once: true });
        window.setTimeout(remove, 450);
    }

    document.querySelectorAll("[data-flash-notification]").forEach((notification) => {
        const type = notification.dataset.flashType || "info";
        const delay = type === "error" ? 6500 : (type === "warning" ? 5200 : 4200);
        let timer = null;
        let remaining = delay;
        let startedAt = 0;

        const startTimer = () => {
            if (timer || notification.dataset.dismissed === "true") {
                return;
            }
            startedAt = Date.now();
            timer = window.setTimeout(() => dismiss(notification), remaining);
        };

        const pauseTimer = () => {
            if (!timer) {
                return;
            }
            window.clearTimeout(timer);
            timer = null;
            remaining = Math.max(600, remaining - (Date.now() - startedAt));
        };

        notification.querySelector("[data-flash-close]")?.addEventListener("click", () => dismiss(notification));
        notification.addEventListener("mouseenter", pauseTimer);
        notification.addEventListener("mouseleave", startTimer);
        notification.addEventListener("focusin", pauseTimer);
        notification.addEventListener("focusout", startTimer);
        startTimer();
    });
})();
