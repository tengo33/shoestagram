(() => {
    const body = document.body;

    if (!body || !body.classList.contains("backoffice-page")) {
        return;
    }

    const sidebar = document.querySelector("#backofficeSidebar");
    const openButton = document.querySelector("[data-sidebar-open]");
    const closeButtons = document.querySelectorAll("[data-sidebar-close]");
    const collapseButton = document.querySelector("[data-sidebar-collapse]");
    const desktopQuery = window.matchMedia("(min-width: 1181px)");
    const storageKey = "shoestagram-backoffice-sidebar-collapsed";
    let returnFocus = null;

    function storedCollapsePreference() {
        try {
            return window.localStorage.getItem(storageKey) === "true";
        } catch (error) {
            return false;
        }
    }

    function saveCollapsePreference(isCollapsed) {
        try {
            window.localStorage.setItem(storageKey, String(isCollapsed));
        } catch (error) {
            // The sidebar still works when storage is unavailable.
        }
    }

    function syncCollapseControl() {
        if (!collapseButton) {
            return;
        }

        const isCollapsed = body.classList.contains("is-sidebar-collapsed");
        collapseButton.setAttribute("aria-expanded", String(!isCollapsed));
        collapseButton.setAttribute("aria-label", isCollapsed ? "Expand navigation" : "Collapse navigation");
        collapseButton.setAttribute("title", isCollapsed ? "Expand navigation" : "Collapse navigation");

        const label = collapseButton.querySelector("span");
        if (label) {
            label.textContent = isCollapsed ? "Expand sidebar" : "Collapse sidebar";
        }
    }

    function setDrawer(open) {
        body.classList.toggle("is-sidebar-open", open);

        if (openButton) {
            openButton.setAttribute("aria-expanded", String(open));
        }

        if (sidebar) {
            sidebar.setAttribute("aria-hidden", String(!desktopQuery.matches && !open));
        }

        if (open) {
            returnFocus = document.activeElement;
            const firstLink = sidebar ? sidebar.querySelector("a, button") : null;
            if (firstLink) {
                window.setTimeout(() => firstLink.focus(), 80);
            }
        } else if (returnFocus && typeof returnFocus.focus === "function") {
            returnFocus.focus();
            returnFocus = null;
        }
    }

    function syncForViewport() {
        if (desktopQuery.matches) {
            body.classList.remove("is-sidebar-open");
            body.classList.toggle("is-sidebar-collapsed", storedCollapsePreference());
            if (sidebar) {
                sidebar.removeAttribute("aria-hidden");
            }
            if (openButton) {
                openButton.setAttribute("aria-expanded", "false");
            }
        } else {
            body.classList.remove("is-sidebar-collapsed");
            if (sidebar) {
                sidebar.setAttribute("aria-hidden", String(!body.classList.contains("is-sidebar-open")));
            }
        }

        syncCollapseControl();
    }

    if (openButton) {
        openButton.addEventListener("click", () => setDrawer(true));
    }

    closeButtons.forEach((button) => button.addEventListener("click", () => setDrawer(false)));

    if (collapseButton) {
        collapseButton.addEventListener("click", () => {
            if (!desktopQuery.matches) {
                setDrawer(false);
                return;
            }

            const isCollapsed = !body.classList.contains("is-sidebar-collapsed");
            body.classList.toggle("is-sidebar-collapsed", isCollapsed);
            saveCollapsePreference(isCollapsed);
            syncCollapseControl();
        });
    }

    if (sidebar) {
        sidebar.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                if (!desktopQuery.matches) {
                    setDrawer(false);
                }
            });
        });
    }

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && body.classList.contains("is-sidebar-open")) {
            setDrawer(false);
            return;
        }

        if (event.key !== "Tab" || !body.classList.contains("is-sidebar-open") || !sidebar) {
            return;
        }

        const focusable = Array.from(sidebar.querySelectorAll("a[href], button:not([disabled])"));
        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    document.querySelectorAll(".table-wrap").forEach((tableWrap) => {
        if (!tableWrap.hasAttribute("tabindex")) {
            tableWrap.setAttribute("tabindex", "0");
        }
        if (!tableWrap.hasAttribute("aria-label")) {
            tableWrap.setAttribute("aria-label", "Scrollable data table");
        }
    });

    if (typeof desktopQuery.addEventListener === "function") {
        desktopQuery.addEventListener("change", syncForViewport);
    } else if (typeof desktopQuery.addListener === "function") {
        desktopQuery.addListener(syncForViewport);
    }

    syncForViewport();
    window.requestAnimationFrame(() => body.classList.add("is-backoffice-ready"));
})();
