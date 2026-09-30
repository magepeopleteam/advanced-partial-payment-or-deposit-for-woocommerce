/**
 * Advanced Partial Payment - Frontend Public JavaScript
 */
(function () {
    'use strict';

    var APD_Public = {
        init: function () {
            this.bindBalanceAmount();
            this.bindProductPrice();
        },

        /**
         * Let a price calculator update the deposit figures on the product page.
         *
         * The product form is rendered once from the product's base price. A configurator
         * that changes the price in the browser can report the new unit price with either
         *   jQuery(document.body).trigger('apd_product_price_changed', [price]);
         * or
         *   window.apdUpdateProductPrice(price);
         * A price of 0 or less puts the server-rendered figures back.
         */
        bindProductPrice: function () {
            var self = this;

            window.apdUpdateProductPrice = function (price) {
                self.updateProductPrice(price);
            };

            if (window.jQuery) {
                window.jQuery(document.body).on('apd_product_price_changed', function (event, price) {
                    self.updateProductPrice(price);
                });
            }
        },

        updateProductPrice: function (price) {
            var self = this,
                cfg = (window.apd_public && window.apd_public.price) || {},
                decimals = typeof cfg.decimals === 'undefined' ? 2 : parseInt(cfg.decimals, 10),
                factor = Math.pow(10, decimals);

            price = parseFloat(price);

            document.querySelectorAll('.apd-product-deposit-form[data-apd-deposit-type]').forEach(function (form) {
                var type = form.getAttribute('data-apd-deposit-type'),
                    value = parseFloat(form.getAttribute('data-apd-deposit-value')),
                    depositInput = form.querySelector('input[name="apd_payment_type"][value="deposit"]'),
                    fullInput = form.querySelector('input[name="apd_payment_type"][value="full"]'),
                    depositOption = depositInput ? depositInput.closest('.apd-deposit-option') : null,
                    fullOption = fullInput ? fullInput.closest('.apd-deposit-option') : null,
                    targets = {
                        deposit: depositOption ? depositOption.querySelector('.apd-option-label') : null,
                        balance: depositOption ? depositOption.querySelector('.apd-option-detail') : null,
                        full: fullOption ? fullOption.querySelector('.apd-option-label') : null
                    },
                    deposit,
                    amounts;

                // Remember the server-rendered figures once, so they can be put back.
                if (!form.apdOriginal) {
                    form.apdOriginal = {};
                    Object.keys(targets).forEach(function (key) {
                        if (targets[key]) {
                            form.apdOriginal[key] = targets[key].innerHTML;
                        }
                    });
                }

                if (isNaN(price) || price <= 0 || isNaN(value) || ('percentage' !== type && 'fixed' !== type)) {
                    Object.keys(targets).forEach(function (key) {
                        if (targets[key] && typeof form.apdOriginal[key] !== 'undefined') {
                            targets[key].innerHTML = form.apdOriginal[key];
                        }
                    });
                    return;
                }

                deposit = 'fixed' === type ? Math.min(value, price) : price * Math.min(value, 100) / 100;
                deposit = Math.round(deposit * factor) / factor;

                amounts = {
                    deposit: deposit,
                    balance: Math.round((price - deposit) * factor) / factor,
                    full: price
                };

                Object.keys(targets).forEach(function (key) {
                    var amount = targets[key] ? targets[key].querySelector('.woocommerce-Price-amount') : null,
                        holder;

                    if (!amount) {
                        return;
                    }

                    holder = amount.querySelector('bdi') || amount;
                    holder.textContent = self.formatPrice(amounts[key]).replace(/&nbsp;/g, ' ');
                });
            });
        },

        /**
         * Format an amount the way wc_price() would, from the settings PHP handed over.
         */
        formatPrice: function (amount) {
            var cfg = (window.apd_public && window.apd_public.price) || {},
                decimals = typeof cfg.decimals === 'undefined' ? 2 : parseInt(cfg.decimals, 10),
                fixed = Math.abs(amount).toFixed(decimals),
                parts = fixed.split('.'),
                whole = parts[0],
                fraction = parts.length > 1 ? parts[1] : '';

            whole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousand_sep || ',');

            var value = fraction ? whole + (cfg.decimal_sep || '.') + fraction : whole;

            return (cfg.format || '%1$s%2$s')
                .replace('%1$s', cfg.symbol || '')
                .replace('%2$s', value);
        },

        /**
         * Keep the pay button naming the amount actually in the box.
         *
         * The button is the form's submit either way; this only stops it from reading as
         * "pay the whole balance" once the customer has typed an amount of their own.
         */
        bindBalanceAmount: function () {
            var self = this;

            function updateButton(event) {
                var input = event.target.closest('.apd-pay-balance-amount'),
                    form,
                    button,
                    amount,
                    max,
                    template;

                if (!input) {
                    return;
                }

                form = input.closest('form');
                button = form ? form.querySelector('.apd-pay-balance-btn[data-apd-pay-template]') : null;

                if (!button) {
                    return;
                }

                amount = parseFloat(input.value);
                max = parseFloat(input.getAttribute('max'));

                // Mid-edit the box can be empty or out of range. Leave the last good
                // label alone rather than flashing a nonsense amount at the customer.
                if (isNaN(amount) || amount <= 0) {
                    return;
                }

                if (!isNaN(max) && amount > max) {
                    amount = max;
                }

                template = button.getAttribute('data-apd-pay-template');
                button.textContent = String(template).replace('%s', self.formatPrice(amount));
            }

            document.addEventListener('input', updateButton);
            document.addEventListener('change', updateButton);
        },
    };

    APD_Public.init();

})();
