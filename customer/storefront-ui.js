window.ShoestagramUI = (() => {
    const reducedMotionQuery = window.matchMedia ? window.matchMedia("(prefers-reduced-motion: reduce)") : { matches: false };
    let deferredInstallPrompt = null;

    function prefersReducedMotion() {
        return Boolean(reducedMotionQuery.matches);
    }

    function getWishlist() {
        return [];
    }

    function setWishlist(wishlist) {
        const total = Array.isArray(wishlist) ? wishlist.length : getWishlistCount();
        setBadgeCount("#wishlistCount", total);
        hydrateWishlistButtons();
    }

    function getCart() {
        return [];
    }

    function setCart(cart) {
        const total = Array.isArray(cart) ? cart.length : getCartCount();
        setBadgeCount("#cartCount", total);
    }

    function getCartCount() {
        const counter = document.querySelector("#cartCount");
        if (!counter) {
            return 0;
        }

        const rawCount = Number.parseInt(counter.dataset.serverCount || counter.textContent || "0", 10);
        return Number.isFinite(rawCount) && rawCount > 0 ? rawCount : 0;
    }

    function getWishlistCount() {
        const counter = document.querySelector("#wishlistCount");
        if (!counter) {
            return 0;
        }

        const rawCount = Number.parseInt(counter.dataset.serverCount || counter.textContent || "0", 10);
        return Number.isFinite(rawCount) && rawCount > 0 ? rawCount : 0;
    }

    function setBadgeCount(selector, value) {
        document.querySelectorAll(selector).forEach((element) => {
            const nextValue = Number.isFinite(value) && value > 0 ? String(value) : "";
            element.textContent = nextValue;
            element.dataset.serverCount = String(Math.max(0, value));
        });
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="shoestagram-csrf-token"]');
        return meta ? meta.getAttribute("content") || "" : "";
    }

    function isAuthenticated() {
        const meta = document.querySelector('meta[name="shoestagram-authenticated"]');
        return Boolean(meta && meta.getAttribute("content") === "1");
    }

    function showAccountRequiredNotice() {
        showToast("Create an account or sign in first before adding or reserving items.");
    }

    function pulseCartBadge() {
        const badge = document.querySelector("#cartCount");
        if (!badge) {
            return;
        }

        badge.classList.remove("is-pulsing");
        void badge.offsetWidth;
        badge.classList.add("is-pulsing");
    }

    async function submitCartForm(form, trigger = null) {
        if (!form) {
            return false;
        }

        if (!isAuthenticated()) {
            showAccountRequiredNotice();
            return false;
        }

        const payload = new FormData(form);
        const productName = (trigger && trigger.getAttribute("data-product-name")) || form.getAttribute("data-product-name") || "Item";
        const quantity = Math.max(1, Number.parseInt((payload.get("quantity") || "1"), 10) || 1);

        if (payload.has("size") && String(payload.get("size") || "").trim() === "") {
            showToast("Choose a size before adding this item to cart.");
            return false;
        }

        if (trigger) {
            trigger.classList.add("is-adding-to-cart");
            trigger.disabled = true;
        }

        try {
            const response = await fetch(form.getAttribute("action") || "cart-action.php", {
                method: form.getAttribute("method") || "POST",
                credentials: "same-origin",
                body: payload,
                redirect: "follow",
            });

            if (response.redirected && response.url.includes("/login/login.php")) {
                window.location.href = response.url;
                return false;
            }

            if (!response.ok) {
                throw new Error("Cart request failed");
            }

            setBadgeCount("#cartCount", getCartCount() + quantity);
            pulseCartBadge();
            showToast(`${productName} added to cart`);
            return true;
        } catch (error) {
            showToast("Could not add this item to cart");
            return false;
        } finally {
            if (trigger) {
                window.setTimeout(() => {
                    trigger.classList.remove("is-adding-to-cart");
                    trigger.disabled = false;
                }, 350);
            }
        }
    }

    async function addToCart(productId, productName, quantity = 1, trigger = null) {
        if (!isAuthenticated()) {
            showAccountRequiredNotice();
            return false;
        }

        const safeProductId = Number.parseInt(productId, 10);
        const safeQuantity = Math.max(1, Number.parseInt(quantity, 10) || 1);

        if (!Number.isFinite(safeProductId) || safeProductId <= 0) {
            showToast("That item could not be added");
            return false;
        }

        const csrfToken = getCsrfToken();
        if (!csrfToken) {
            showToast("Please refresh the page and try again");
            return false;
        }

        const payload = new FormData();
        payload.append("csrf_token", csrfToken);
        payload.append("action", "add");
        payload.append("product_id", String(safeProductId));
        payload.append("quantity", String(safeQuantity));
        payload.append("redirect", "cart.php");

        if (trigger) {
            trigger.classList.add("is-adding-to-cart");
            trigger.disabled = true;
        }

        try {
            const response = await fetch("cart-action.php", {
                method: "POST",
                credentials: "same-origin",
                body: payload,
                redirect: "follow",
            });

            if (response.redirected && response.url.includes("/login/login.php")) {
                window.location.href = response.url;
                return false;
            }

            if (!response.ok) {
                throw new Error("Cart request failed");
            }

            setBadgeCount("#cartCount", getCartCount() + safeQuantity);
            pulseCartBadge();
            showToast(`${productName || "Item"} added to cart`);
            return true;
        } catch (error) {
            showToast("Could not add this item to cart");
            return false;
        } finally {
            if (trigger) {
                window.setTimeout(() => {
                    trigger.classList.remove("is-adding-to-cart");
                    trigger.disabled = false;
                }, 350);
            }
        }
    }

    function updateCounts() {
        setBadgeCount("#wishlistCount", getWishlistCount() || getWishlist().length);
        setBadgeCount("#cartCount", getCartCount());
    }

    function showToast(message) {
        const existing = document.querySelector(".toast-notification");
        if (existing) {
            existing.remove();
        }

        const toast = document.createElement("div");
        toast.className = "toast-notification";
        const icon = document.createElement("i");
        icon.className = "fas fa-circle-info";
        icon.setAttribute("aria-hidden", "true");
        const copy = document.createElement("span");
        copy.textContent = message;
        toast.setAttribute("role", "status");
        toast.append(icon, copy);
        document.body.appendChild(toast);

        window.setTimeout(() => {
            toast.classList.add("is-leaving");
        }, 2200);

        window.setTimeout(() => {
            toast.remove();
        }, 2600);
    }

    function pulseWishlistButton(button) {
        if (!button) {
            return;
        }

        button.classList.remove("is-liking");
        void button.offsetWidth;
        button.classList.add("is-liking");
    }

    async function toggleWishlist(productId, productName, trigger = null) {
        if (!isAuthenticated()) {
            showAccountRequiredNotice();
            return false;
        }

        const safeProductId = Number.parseInt(productId, 10) || 0;
        if (safeProductId <= 0) {
            showToast("Could not update wishlist");
            return false;
        }

        const wasActive = Boolean(trigger && trigger.classList.contains("is-active"));

        if (trigger) {
            trigger.disabled = true;
            trigger.classList.add("is-liking");
        }

        try {
            const payload = new FormData();
            payload.append("csrf_token", getCsrfToken());
            payload.append("action", "toggle");
            payload.append("product_id", String(safeProductId));
            payload.append("redirect", window.location.pathname.split("/").pop() || "shop.php");

            const response = await fetch("wishlist-action.php", {
                method: "POST",
                credentials: "same-origin",
                body: payload,
                redirect: "follow",
            });

            if (response.redirected && response.url.includes("/login/login.php")) {
                window.location.href = response.url;
                return false;
            }

            if (!response.ok) {
                throw new Error("Wishlist request failed");
            }

            if (trigger) {
                trigger.classList.toggle("is-active", !wasActive);
                trigger.setAttribute("aria-pressed", (!wasActive).toString());

                const icon = trigger.querySelector("i");
                if (icon) {
                    icon.classList.toggle("far", wasActive);
                    icon.classList.toggle("fas", !wasActive);
                }
            }

            const nextCount = Math.max(0, getWishlistCount() + (wasActive ? -1 : 1));
            setBadgeCount("#wishlistCount", nextCount);
            updateCounts();

            showToast(wasActive ? `${productName || "Item"} removed from wishlist` : `${productName || "Item"} saved to wishlist`);
            return true;
        } catch (error) {
            showToast("Could not update wishlist");
            return false;
        } finally {
            if (trigger) {
                window.setTimeout(() => {
                    trigger.classList.remove("is-liking");
                    trigger.disabled = false;
                }, prefersReducedMotion() ? 0 : 180);
            }
        }
    }

    function hydrateWishlistButtons() {
        updateCounts();
    }

    function bindWishlistButtons(root = document) {
        root.querySelectorAll(".js-wishlist-toggle").forEach((button) => {
            if (button.dataset.wishlistBound === "true") {
                return;
            }

            button.dataset.wishlistBound = "true";
            button.addEventListener("click", (event) => {
                event.preventDefault();
                pulseWishlistButton(button);
                void toggleWishlist(
                    button.getAttribute("data-wishlist-id"),
                    button.getAttribute("data-product-name") || "Item",
                    button
                );
            });
        });

        updateCounts();
    }

    function bindCartButtons(root = document) {
        root.querySelectorAll("form.js-cart-form").forEach((form) => {
            if (form.dataset.cartBound === "true") {
                return;
            }

            form.dataset.cartBound = "true";
            form.addEventListener("submit", (event) => {
                event.preventDefault();
                const trigger = form.querySelector("[type='submit']");
                submitCartForm(form, trigger);
            });
        });

        root.querySelectorAll("[data-cart-add]").forEach((button) => {
            if (button.dataset.cartBound === "true") {
                return;
            }

            if (button.closest("form.js-cart-form")) {
                return;
            }

            button.dataset.cartBound = "true";
            button.addEventListener("click", (event) => {
                event.preventDefault();
                const form = button.closest("form");

                if (form) {
                    submitCartForm(form, button);
                    return;
                }

                addToCart(
                    button.getAttribute("data-cart-add"),
                    button.getAttribute("data-product-name") || "Item",
                    button.getAttribute("data-cart-quantity") || 1,
                    button
                );
            });
        });

        updateCounts();
    }

    function bindAccountRequiredActions(root = document) {
        root.querySelectorAll("[data-requires-account]").forEach((element) => {
            if (element.dataset.accountNoticeBound === "true") {
                return;
            }

            if (element.matches("form.js-cart-form")) {
                return;
            }

            element.dataset.accountNoticeBound = "true";

            const guard = (event) => {
                if (isAuthenticated()) {
                    return;
                }

                event.preventDefault();
                showAccountRequiredNotice();
            };

            if (element.matches("form")) {
                element.addEventListener("submit", guard);
            } else {
                element.addEventListener("click", guard);
            }
        });
    }

    function bindCartButton(selector = "#cartButton") {
        const button = document.querySelector(selector);
        if (!button) {
            return;
        }

        if (button.tagName.toLowerCase() === "a") {
            return;
        }

        button.addEventListener("click", () => {
            window.location.href = "cart.php";
        });
    }

    function redirectToShop(rawValue, redirectUrl = "shop.php") {
        const query = String(rawValue || "").trim();
        if (!query) {
            return;
        }

        window.location.href = `${redirectUrl}?search=${encodeURIComponent(query)}`;
    }

    function bindRedirectSearch(selector, redirectUrl = "shop.php") {
        const input = document.querySelector(selector);
        if (!input) {
            return;
        }

        input.addEventListener("keydown", (event) => {
            if (event.key === "Enter") {
                event.preventDefault();
                redirectToShop(event.target.value, redirectUrl);
            }
        });
    }

    function closeAccountMenusOnOutsideClick() {
        document.addEventListener("click", (event) => {
            document.querySelectorAll(".account-menu[open]").forEach((menu) => {
                if (!menu.contains(event.target)) {
                    menu.removeAttribute("open");
                }
            });
        });
    }

    function initRevealAnimations() {
        const revealItems = document.querySelectorAll(".reveal");
        if (!revealItems.length) {
            return;
        }

        if (prefersReducedMotion() || !("IntersectionObserver" in window)) {
            revealItems.forEach((item) => item.classList.add("is-visible"));
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealItems.forEach((item, index) => {
            item.style.transitionDelay = `${Math.min((index % 6) * 45, 225)}ms`;
            observer.observe(item);
        });

        window.setTimeout(() => {
            revealItems.forEach((item) => item.classList.add("is-visible"));
        }, 1200);
    }

    function initAutoCarousel() {
        document.querySelectorAll("[data-auto-carousel]").forEach((carousel) => {
            const track = carousel.querySelector(".carousel-track");
            if (!track) {
                return;
            }

            const useNativeScroll = window.matchMedia("(max-width: 900px)").matches || window.matchMedia("(pointer: coarse)").matches;
            if (prefersReducedMotion() || useNativeScroll || track.children.length <= 1) {
                track.style.transform = "";
                return;
            }

            let offset = 0;
            let isPaused = false;
            let frameId = 0;
            let lastTime = performance.now();
            const speed = Number(carousel.getAttribute("data-carousel-speed") || 0.25);

            function setPaused(nextState) {
                isPaused = nextState;
                if (!nextState) {
                    lastTime = performance.now();
                }
            }

            function animate(now) {
                const width = track.scrollWidth / 2;

                if (!isPaused) {
                    const delta = (now - lastTime) / 16.67;
                    offset += speed * delta;
                }

                lastTime = now;

                if (offset >= width) {
                    offset = 0;
                }

                track.style.transform = `translate3d(${-offset}px, 0, 0)`;
                frameId = window.requestAnimationFrame(animate);
            }

            carousel.addEventListener("mouseenter", () => setPaused(true));
            carousel.addEventListener("mouseleave", () => setPaused(false));
            carousel.addEventListener("focusin", () => setPaused(true));
            carousel.addEventListener("focusout", () => setPaused(false));
            document.addEventListener("visibilitychange", () => setPaused(document.hidden));

            frameId = window.requestAnimationFrame(animate);
        });
    }

    function initCountUp() {
        const counters = document.querySelectorAll("[data-countup]");
        if (!counters.length) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting || entry.target.dataset.counted === "true") {
                    return;
                }

                const target = Number(entry.target.getAttribute("data-countup") || 0);
                const prefix = entry.target.getAttribute("data-prefix") || "";
                const suffix = entry.target.getAttribute("data-suffix") || "";
                const duration = 900;
                const start = performance.now();

                function step(now) {
                    const progress = Math.min((now - start) / duration, 1);
                    const current = Math.round(target * progress);
                    entry.target.textContent = `${prefix}${current.toLocaleString()}${suffix}`;

                    if (progress < 1) {
                        requestAnimationFrame(step);
                    }
                }

                entry.target.dataset.counted = "true";
                requestAnimationFrame(step);
            });
        }, { threshold: 0.35 });

        counters.forEach((counter) => observer.observe(counter));
    }

    function initProductGallery() {
        const mainImage = document.querySelector("[data-gallery-main]");
        if (!mainImage) {
            return;
        }

        document.querySelectorAll("[data-gallery-thumb]").forEach((button) => {
            button.addEventListener("click", () => {
                const src = button.getAttribute("data-gallery-thumb");
                if (!src) {
                    return;
                }

                mainImage.setAttribute("src", src);
                document.querySelectorAll("[data-gallery-thumb]").forEach((item) => item.classList.remove("is-active"));
                button.classList.add("is-active");
            });
        });
    }

    function registerServiceWorker() {
        if (!("serviceWorker" in navigator)) {
            return;
        }

        navigator.serviceWorker.register("../service-worker.js").catch(() => {});
    }

    function initInstallPrompt() {
        const button = document.getElementById("pwaInstallButton");

        window.addEventListener("beforeinstallprompt", (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;

            if (button) {
                button.hidden = false;
            }
        });

        if (!button) {
            return;
        }

        button.addEventListener("click", async () => {
            if (!deferredInstallPrompt) {
                showToast("This device is not ready for installation yet");
                return;
            }

            deferredInstallPrompt.prompt();
            deferredInstallPrompt = null;
            button.hidden = true;
        });
    }

    function syncAcrossTabs() {
        updateCounts();
    }

    function init() {
        document.body.classList.add("ui-ready");
        updateCounts();
        bindWishlistButtons();
        bindCartButtons();
        bindAccountRequiredActions();
        bindCartButton();
        closeAccountMenusOnOutsideClick();
        initRevealAnimations();
        initAutoCarousel();
        initCountUp();
        initProductGallery();
        registerServiceWorker();
        initInstallPrompt();
        hydrateWishlistButtons();
        syncAcrossTabs();
    }

    return {
        addToCart,
        bindCartButton,
        bindCartButtons,
        bindAccountRequiredActions,
        bindRedirectSearch,
        bindWishlistButtons,
        closeAccountMenusOnOutsideClick,
        getCart,
        getCartCount,
        getWishlist,
        hydrateWishlistButtons,
        init,
        initCountUp,
        initProductGallery,
        initRevealAnimations,
        redirectToShop,
        setCart,
        setWishlist,
        showToast,
        toggleWishlist,
        updateCounts,
    };
})();

document.addEventListener("DOMContentLoaded", () => {
    if (window.ShoestagramUI) {
        window.ShoestagramUI.init();
    }
});
