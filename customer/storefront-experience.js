/* Progressive customer UI only; pricing, inventory and checkout stay server-side. */
(() => {
    'use strict';
    document.addEventListener('DOMContentLoaded', () => {
        if (!document.body.classList.contains('store-body')) return;
        const main = document.querySelector('main');
        if (main) { main.id = 'storefrontMain'; main.tabIndex = -1; }
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        const siteHeader = document.querySelector('.site-header');
        const menu = document.getElementById('storefrontMenu');
        const cart = document.getElementById('addToCartModal');
        let productRequest;
        let sizeStandards = {};
        let sizeLabels = {};
        const dialogOpeners = new WeakMap();

        function syncQuantityControl(control) {
            const input = control.querySelector('input[type="number"]');
            if (!input) return;
            const decrease = control.querySelector('[data-quantity-decrease]');
            const increase = control.querySelector('[data-quantity-increase]');
            const value = Number(input.value);
            const minimum = Number(input.min || 1);
            const maximum = Number(input.max || Number.MAX_SAFE_INTEGER);
            const validWholeNumber = input.value !== '' && Number.isInteger(value);

            if (input.disabled) {
                input.setCustomValidity('');
            } else if (!validWholeNumber || value < minimum) {
                input.setCustomValidity('Enter a positive whole-number quantity.');
            } else if (value > maximum) {
                input.setCustomValidity(`Only ${maximum} unit(s) are currently available.`);
            } else {
                input.setCustomValidity('');
            }

            if (decrease) decrease.disabled = input.disabled || !validWholeNumber || value <= minimum;
            if (increase) increase.disabled = input.disabled || !validWholeNumber || value >= maximum;
        }

        document.querySelectorAll('[data-quantity-control]').forEach(control => {
            const input = control.querySelector('input[type="number"]');
            const decrease = control.querySelector('[data-quantity-decrease]');
            const increase = control.querySelector('[data-quantity-increase]');
            if (!input) return;

            const stepQuantity = delta => {
                const minimum = Number(input.min || 1);
                const maximum = Number(input.max || Number.MAX_SAFE_INTEGER);
                const current = Number.isInteger(Number(input.value)) ? Number(input.value) : minimum;
                input.value = String(Math.min(maximum, Math.max(minimum, current + delta)));
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.focus();
            };

            if (decrease && !decrease.hasAttribute('data-server-quantity-step')) decrease.addEventListener('click', () => stepQuantity(-1));
            if (increase && !increase.hasAttribute('data-server-quantity-step')) increase.addEventListener('click', () => stepQuantity(1));
            input.addEventListener('input', () => syncQuantityControl(control));
            input.addEventListener('change', () => syncQuantityControl(control));
            syncQuantityControl(control);
        });

        if (siteHeader) {
            let headerFrame = 0;
            const syncHeader = () => {
                siteHeader.classList.toggle('is-scrolled', window.scrollY > 8);
                headerFrame = 0;
            };
            window.addEventListener('scroll', () => {
                if (!headerFrame) headerFrame = window.requestAnimationFrame(syncHeader);
            }, { passive: true });
            syncHeader();
        }

        const openDialog = (dialog, opener = document.activeElement) => {
            if (!dialog || dialog.open) return;
            dialogOpeners.set(dialog, opener);
            dialog.showModal();
            document.body.classList.add('dialog-open');
        };
        document.querySelector('[data-open-menu]')?.addEventListener('click', event => openDialog(menu, event.currentTarget));
        document.querySelectorAll('.store-dialog').forEach(dialog => {
            dialog.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => dialog.close()));
            dialog.addEventListener('click', event => {
                if (event.target !== dialog) return;
                const rect = dialog.getBoundingClientRect();
                if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
            });
            dialog.addEventListener('close', () => {
                // A queued close event must not abort a newly reopened product dialog.
                if (dialog.open) return;
                if (!document.querySelector('.store-dialog[open]')) document.body.classList.remove('dialog-open');
                if (dialog === cart) productRequest?.abort();
                const opener = dialogOpeners.get(dialog);
                if (opener?.isConnected) opener.focus({ preventScroll: true });
            });
        });

        // Retain the native select as the form's source of truth. Chips reflect its options.
        function enhanceSizes(select) {
            const choices = document.createElement('div');
            choices.className = 'size-choices';
            choices.setAttribute('role', 'group');
            const label = document.querySelector(`label[for="${select.id}"]`);
            if (label) { label.id = `${select.id}Label`; choices.setAttribute('aria-labelledby', label.id); }
            select.after(choices);
            select.classList.add('size-native-enhanced');
            select.tabIndex = -1;
            const error = document.createElement('p');
            error.className = 'field-note size-error';
            error.hidden = true;
            error.textContent = 'Please choose an available size.';
            choices.after(error);
            const sync = () => {
                choices.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.size === select.value)));
                if (select.value) { error.hidden = true; select.removeAttribute('aria-invalid'); }
            };
            const render = () => {
                choices.replaceChildren();
                Array.from(select.options).filter(option => option.value).forEach(option => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'size-choice';
                    button.textContent = option.value;
                    button.setAttribute('aria-label', option.textContent);
                    button.dataset.size = option.value;
                    button.disabled = select.disabled || option.disabled;
                    button.addEventListener('click', () => { select.value = option.value; select.dispatchEvent(new Event('change', { bubbles: true })); });
                    choices.append(button);
                });
                sync();
            };
            select.addEventListener('change', sync);
            select.addEventListener('invalid', event => {
                event.preventDefault(); error.hidden = false; select.setAttribute('aria-invalid', 'true');
                choices.querySelector('button:not(:disabled)')?.focus();
            });
            new MutationObserver(render).observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['disabled'] });
            render();
        }
        document.querySelectorAll('[data-size-choices]').forEach(enhanceSizes);

        const standard = document.getElementById('modalSizeStandard');
        const size = document.getElementById('modalSize');
        const quantity = document.getElementById('modalQuantity');
        const cartForm = document.getElementById('modalCartForm');
        const status = document.getElementById('modalStatus');
        const summary = document.getElementById('modalProductSummary');
        function syncQuickSizes() {
            size.replaceChildren(new Option('Select a size', ''));
            (sizeStandards[standard.value] || []).forEach(value => size.add(new Option(`${sizeLabels[standard.value] || standard.value} ${value}`, value)));
            size.dispatchEvent(new Event('change', { bubbles: true }));
        }
        standard?.addEventListener('change', syncQuickSizes);
        window.openAddToCartModal = async productId => {
            if (document.querySelector('meta[name="shoestagram-authenticated"]')?.content !== '1') {
                window.location.assign('../login/login.php?redirect=' + encodeURIComponent('../customer/' + location.pathname.split('/').pop() + location.search));
                return;
            }
            if (!cart || !Number.isInteger(Number(productId)) || Number(productId) <= 0) return;
            productRequest?.abort();
            const request = new AbortController();
            productRequest = request;
            cartForm.reset();
            quantity.disabled = true;
            quantity.value = '1';
            quantity.max = '1';
            quantity.dispatchEvent(new Event('change', { bubbles: true }));
            cartForm.hidden = true;
            summary.hidden = true;
            status.textContent = 'Loading product and available sizes…';
            openDialog(cart);
            const timeout = window.setTimeout(() => request.abort(), 12000);
            try {
                const response = await fetch(`get-product-data.php?id=${encodeURIComponent(productId)}`, { credentials: 'same-origin', signal: request.signal, headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Product unavailable');
                const product = await response.json();
                if (request !== productRequest || !cart.open) return;
                if (!product.id || !product.name || !Number.isFinite(Number(product.stock))) throw new Error('Product unavailable');
                document.getElementById('modalProductId').value = product.id;
                document.getElementById('modalProductName').textContent = product.name;
                document.getElementById('modalProductPrice').textContent = product.price;
                const photo = document.getElementById('modalProductImage');
                photo.src = product.image;
                photo.alt = product.name;
                document.getElementById('modalStockInfo').textContent = Number(product.stock) > 0 ? `${product.stock} in stock` : 'Out of stock';
                summary.hidden = false;
                sizeStandards = product.size_standards || { US: product.sizes || [] };
                sizeLabels = product.size_standard_labels || {};
                standard.replaceChildren();
                Object.entries(sizeStandards).forEach(([key, values]) => { if (Array.isArray(values) && values.length) standard.add(new Option(sizeLabels[key] || key, key)); });
                quantity.value = '1';
                quantity.max = String(Math.max(1, Number(product.stock)));
                quantity.disabled = Number(product.stock) <= 0;
                quantity.dispatchEvent(new Event('change', { bubbles: true }));
                syncQuickSizes();
                if (Number(product.stock) <= 0 || !standard.options.length) {
                    status.textContent = 'This product is not available to add right now. Explore another piece from the shop.';
                    return;
                }
                status.textContent = 'Select your size before adding to cart.';
                cartForm.hidden = false;
            } catch (error) {
                if (request === productRequest && cart.open) status.textContent = 'We couldn’t load this product. Please close this window and try again.';
            } finally {
                window.clearTimeout(timeout);
            }
        };

        const filters = document.querySelector('.filter-sidebar');
        if (filters) {
            const smallScreen = window.matchMedia('(max-width: 700px)');
            const updateFilters = () => { filters.open = !smallScreen.matches; };
            updateFilters();
            smallScreen.addEventListener('change', updateFilters);
        }
        document.querySelectorAll('[data-gallery-thumb]').forEach(button => {
            button.setAttribute('aria-pressed', String(button.classList.contains('is-active')));
            button.addEventListener('click', () => document.querySelectorAll('[data-gallery-thumb]').forEach(item => item.setAttribute('aria-pressed', String(item === button))));
        });
        const productCartForm = document.getElementById('productCartForm');
        productCartForm?.addEventListener('submit', event => {
            const selectedSize = document.getElementById('size');
            if (selectedSize && !selectedSize.reportValidity()) event.preventDefault();
        });
        const productQuantity = document.getElementById('quantity');
        const total = document.querySelector('[data-unit-price]');
        const updateTotal = () => {
            const hiddenQuantity = document.getElementById('productCartQuantity');
            if (hiddenQuantity && productQuantity) hiddenQuantity.value = productQuantity.value;
            if (total && productQuantity) total.textContent = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', currencyDisplay: 'code' }).format(Number(total.dataset.unitPrice) * Number(productQuantity.value));
        };
        productQuantity?.addEventListener('input', updateTotal);
        productQuantity?.addEventListener('change', updateTotal);
        updateTotal();

        document.querySelectorAll('input[type="file"][name="payment_proof"]').forEach(input => {
            const feedback = document.createElement('span');
            feedback.className = 'field-note'; feedback.setAttribute('role', 'status'); input.after(feedback);
            input.addEventListener('change', () => {
                const file = input.files[0];
                const tooLarge = file && file.size > 5 * 1024 * 1024;
                input.setCustomValidity(tooLarge ? 'Please choose an image smaller than 5 MB.' : '');
                feedback.textContent = !file ? '' : tooLarge ? 'This image exceeds 5 MB. Choose a smaller file.' : `${file.name} selected. It will be uploaded when you submit your reservation.`;
            });
            const method = document.querySelector('[name="payment_method"]');
            method?.addEventListener('change', () => {
                if (method.value !== 'Online Payment') { input.setCustomValidity(''); feedback.textContent = ''; }
            });
        });

        // Do not disable successful controls: some forms use submit-button names as actions.
        // Listeners run after the existing login guard and any AJAX form handlers.
        document.addEventListener('submit', event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || event.defaultPrevented || form.method.toLowerCase() !== 'post') return;
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            if (event.submitter) { event.submitter.classList.add('is-submitting'); event.submitter.setAttribute('aria-disabled', 'true'); }
        });
        window.addEventListener('pageshow', () => {
            document.querySelectorAll('[data-submitting]').forEach(form => { delete form.dataset.submitting; form.removeAttribute('aria-busy'); });
            document.querySelectorAll('.is-submitting').forEach(button => { button.classList.remove('is-submitting'); button.removeAttribute('aria-disabled'); });
        });

        // Opt in only after JS is ready; content is fully visible without JS.
        if (!reducedMotion.matches && 'IntersectionObserver' in window) {
            const observer = new IntersectionObserver(entries => entries.forEach(entry => {
                if (entry.isIntersecting) { entry.target.classList.remove('reveal-pending'); observer.unobserve(entry.target); }
            }), { threshold: .04, rootMargin: '0px 0px 40px 0px' });
            document.querySelectorAll('main > .reveal').forEach(section => {
                if (section.getBoundingClientRect().top > innerHeight) { section.classList.add('reveal-pending'); observer.observe(section); }
            });
            reducedMotion.addEventListener('change', () => {
                if (reducedMotion.matches) { document.querySelectorAll('.reveal-pending').forEach(section => section.classList.remove('reveal-pending')); observer.disconnect(); }
            });
        }
    });
})();
