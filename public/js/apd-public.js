/**
 * Advanced Partial Payment - Frontend Public JavaScript
 */
(function ($) {
    'use strict';

    var APD_Public = {
        init: function () {
            this.bindDepositToggle();
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
