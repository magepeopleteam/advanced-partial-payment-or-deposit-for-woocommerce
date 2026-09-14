/**
 * Advanced Partial Payment - Frontend Public JavaScript
 */
(function ($) {
    'use strict';

    var APD_Public = {
        init: function () {
            this.bindDepositToggle();
            this.bindBalanceAmount();
        },

        /**
         * Format an amount the way wc_price() would, from the settings PHP handed over.
         */
        formatPrice: function (amount) {
            var cfg = (window.apd_public && apd_public.price) || {},
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

            $(document).on('input change', '.apd-pay-balance-amount', function () {
                var $input = $(this),
                    $button = $input.closest('form').find('.apd-pay-balance-btn[data-apd-pay-template]');

                if (!$button.length) {
                    return;
                }

                var amount = parseFloat($input.val()),
                    max = parseFloat($input.attr('max'));

                // Mid-edit the box can be empty or out of range. Leave the last good
                // label alone rather than flashing a nonsense amount at the customer.
                if (isNaN(amount) || amount <= 0) {
                    return;
                }

                if (!isNaN(max) && amount > max) {
                    amount = max;
                }

                $button.text(
                    String($button.data('apdPayTemplate')).replace('%s', self.formatPrice(amount))
                );
            });
        },

        /**
         * Handle deposit/full payment toggle on product page.
         */
        bindDepositToggle: function () {
            $(document).on('change', '.apd-deposit-option input[type="radio"]', function () {
                var $option = $(this).closest('.apd-deposit-option');

                // Update active state
                $('.apd-deposit-option .apd-option-content').css({
                    'border-color': '#e5e7eb',
                    'background': '#fff',
                });
                $option.find('.apd-option-content').css({
                    'border-color': '#6366f1',
                    'background': '#eef2ff',
                });
            });
        },
    };

    $(document).ready(function () {
        APD_Public.init();
    });

})(jQuery);
