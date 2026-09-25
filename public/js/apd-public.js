/**
 * Advanced Partial Payment - Frontend Public JavaScript
 */
(function () {
    'use strict';

    var APD_Public = {
        init: function () {
            this.bindBalanceAmount();
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
