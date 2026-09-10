/**
 * Advanced Partial Payment - Admin Dashboard JavaScript
 */
(function ($) {
    'use strict';

    // wp-i18n is a declared dependency of this script. The guard keeps the panel
    // rendering in English rather than throwing if it is ever missing.
    var i18n    = ( window.wp && window.wp.i18n ) ? window.wp.i18n : null;
    var __      = i18n ? i18n.__ : function ( text ) { return text; };
    var sprintf = ( i18n && i18n.sprintf ) ? i18n.sprintf : function ( format ) {
        var args = Array.prototype.slice.call( arguments, 1 );
        var next = 0;
        return String( format ).replace( /%(?:(\d+)\$)?([sd])/g, function ( match, position ) {
            return position ? args[ parseInt( position, 10 ) - 1 ] : args[ next++ ];
        } );
    };

    var APD_Admin = {
        init: function () {
            this.bindTabNavigation();
            this.bindSettingsForms();
            this.bindCategoryForm();
            this.bindDeleteCategoryRule();
            this.bindManualPayment();
            this.bindDepositTypeSuffix();
            this.bindProductDepositSummary();
        },

        // =============================================
        // Tab Navigation
        // =============================================
        bindTabNavigation: function () {
            $(document).on('click', '.apd-nav-item', function (e) {
                e.preventDefault();
                var tab = $(this).data('tab');

                // Update nav
                $('.apd-nav-item').removeClass('active');
                $(this).addClass('active');

                // Update content
                $('.apd-tab-content').removeClass('active');
                $('#apd-tab-' + tab).addClass('active');

                // Save state
                if (window.history && window.history.replaceState) {
                    var url = new URL(window.location);
                    url.searchParams.set('tab', tab);
                    window.history.replaceState({}, '', url);
                }
            });

            // Restore tab from URL
            var urlParams = new URLSearchParams(window.location.search);
            var tabParam = urlParams.get('tab');
            if (tabParam) {
                var $tab = $('.apd-nav-item[data-tab="' + tabParam + '"]');
                if ($tab.length) {
                    $tab.trigger('click');
                }
            }
        },

        // =============================================
        // Settings Form (AJAX Save)
        // =============================================
        bindSettingsForms: function () {
            var self = this;
            $(document).on('submit', '.apd-settings-form', function (e) {
                e.preventDefault();

                var $form = $(this);
                var $btn  = $form.find('.apd-btn-primary');
                var tab   = $form.data('tab');

                // Store original button HTML before changing it
                var originalBtnHtml = $btn.html();

                // Serialize form data
                var data = $form.serialize();
                data += '&action=apd_save_settings&nonce=' + apd_admin.nonce + '&tab=' + tab;

                // Disable button
                $btn.prop('disabled', true).html(
                    '<span class="dashicons dashicons-update apd-spin"></span> ' + apd_admin.strings.saving
                );

                $.post(apd_admin.ajax_url, data)
                    .done(function (response) {
                        if (response.success) {
                            self.showToast(response.data || apd_admin.strings.saved, 'success');
                        } else {
                            self.showToast(response.data || apd_admin.strings.error, 'error');
                        }
                    })
                    .fail(function () {
                        self.showToast(apd_admin.strings.error, 'error');
                    })
                    .always(function () {
                        $btn.prop('disabled', false).html(originalBtnHtml);
                    });
            });
        },

        // =============================================
        // Category Deposit Form
        // =============================================
        bindCategoryForm: function () {
            var self = this;
            $(document).on('submit', '#apd-category-form', function (e) {
                e.preventDefault();

                var $form = $(this);
                var data  = $form.serialize();
                data += '&action=apd_save_category_deposit&nonce=' + apd_admin.nonce;

                $.post(apd_admin.ajax_url, data)
                    .done(function (response) {
                        if (response.success) {
                            self.showToast(response.data, 'success');
                            // Reload page to show updated table
                            setTimeout(function () {
                                window.location.reload();
                            }, 800);
                        } else {
                            self.showToast(response.data || apd_admin.strings.error, 'error');
                        }
                    })
                    .fail(function () {
                        self.showToast(apd_admin.strings.error, 'error');
                    });
            });
        },

        // =============================================
        // Delete Category Rule
        // =============================================
        bindDeleteCategoryRule: function () {
            var self = this;
            $(document).on('click', '.apd-delete-category-rule', function () {
                if (!confirm(apd_admin.strings.confirm)) return;

                var $btn  = $(this);
                var catId = $btn.data('cat-id');

                $.post(apd_admin.ajax_url, {
                    action: 'apd_delete_category_deposit',
                    nonce: apd_admin.nonce,
                    category_id: catId,
                })
                    .done(function (response) {
                        if (response.success) {
                            $btn.closest('tr').fadeOut(300, function () {
                                $(this).remove();
                            });
                            self.showToast(response.data, 'success');
                        } else {
                            self.showToast(response.data || apd_admin.strings.error, 'error');
                        }
                    });
            });
        },

        // =============================================
        // Manual Payment (Order Page)
        // =============================================
        bindManualPayment: function () {
            var self = this;
            $(document).on('click', '#apd-record-payment', function () {
                var $btn    = $(this);
                var orderId = $btn.data('order-id');
                var amount  = parseFloat($('#apd-manual-amount').val());

                if (!amount || amount <= 0) {
                    self.showToast(__('Please enter a valid amount.', 'advanced-partial-payment-or-deposit-for-woocommerce'), 'error');
                    return;
                }

                $btn.prop('disabled', true).text(__('Recording...', 'advanced-partial-payment-or-deposit-for-woocommerce'));

                $.post(apd_admin.ajax_url, {
                    action: 'apd_record_payment',
                    nonce: apd_admin.nonce,
                    order_id: orderId,
                    amount: amount,
                })
                    .done(function (response) {
                        if (response.success) {
                            self.showToast(response.data, 'success');
                            setTimeout(function () {
                                window.location.reload();
                            }, 1000);
                        } else {
                            self.showToast(response.data || apd_admin.strings.error, 'error');
                            $btn.prop('disabled', false).text(__('Record', 'advanced-partial-payment-or-deposit-for-woocommerce'));
                        }
                    })
                    .fail(function () {
                        self.showToast(apd_admin.strings.error, 'error');
                        $btn.prop('disabled', false).text(__('Record', 'advanced-partial-payment-or-deposit-for-woocommerce'));
                    });
            });
        },

        // =============================================
        // Deposit Type → Suffix Update
        // =============================================
        bindDepositTypeSuffix: function () {
            $(document).on('change', '#apd-deposit-type', function () {
                var type = $(this).val();
                var $suffix = $('#apd-value-suffix');
                if ($suffix.length) {
                    $suffix.text(type === 'percentage' ? '%' : '$');
                }
            });
        },

        // =============================================
        // Product Deposit Summary
        // =============================================
        bindProductDepositSummary: function () {
            var $summary = $('#apd-product-deposit-insight');

            if (!$summary.length) {
                return;
            }

            var rawContext = $summary.attr('data-context');
            if (!rawContext) {
                return;
            }

            var context = {};
            try {
                context = JSON.parse(rawContext);
            } catch (e) {
                return;
            }

            var self = this;

            var renderSummary = function () {
                var productEnable = $('#_apd_enable_deposit').val() || '';
                var productForce = $('#_apd_force_deposit').val() || '';
                var productType = $('#_apd_deposit_type').val() || 'global';
                var productValue = $('#_apd_deposit_value').val() || '';
                var productMin = $('#_apd_min_deposit').val() || '';
                var productMax = $('#_apd_max_deposit').val() || '';
                var assignedPlanIds = self.collectSelectedPlanIds();
                var productPrice = self.getCurrentProductPrice(context.product_price || 0);

                var effectiveEnable = self.getEffectiveEnable(productEnable, context);
                var effectiveForce = self.getEffectiveForce(productForce, context);
                var effectiveType = self.getEffectiveType(productType, context);
                var effectiveMinMax = self.getEffectiveMinMax(effectiveType.value, productMin, productMax, context);
                var effectivePlans = self.getEffectivePlans(effectiveType.value, assignedPlanIds, context);
                var effectiveValue = self.getEffectiveValue(effectiveType.value, productType, productValue, effectiveMinMax, effectivePlans, context);
                var effectiveScope = self.getOverallScope(effectiveEnable, effectiveForce, effectiveType, effectiveValue);
                var preview = self.getDepositPreview(effectiveEnable.value, effectiveForce.value, effectiveType.value, effectiveValue.value, productPrice, context, effectiveMinMax, effectivePlans);

                self.toggleProductTypePanels(productType, effectiveType.value);
                self.toggleFlexiblePanel(effectiveType.value);

                var effectiveScopeLabel = self.formatScopeLabel(effectiveScope);
                var enabledLabel = effectiveEnable.value === 'yes'
                    ? __('Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce')
                    : __('Disabled', 'advanced-partial-payment-or-deposit-for-woocommerce');

                $('#apd-effective-enabled-badge')
                    .removeClass('apd-pill-success apd-pill-danger')
                    .addClass(effectiveEnable.value === 'yes' ? 'apd-pill-success' : 'apd-pill-danger')
                    .text(enabledLabel);

                $('#apd-effective-mode-badge')
                    .removeClass('apd-pill-info apd-pill-warning')
                    .addClass(effectiveScope === 'product' ? 'apd-pill-info' : 'apd-pill-warning')
                    .text(effectiveScopeLabel);

                $('#apd-effective-enable-value').text(enabledLabel);
                $('#apd-effective-enable-source').text(self.formatSourceLine(effectiveEnable.source));

                $('#apd-effective-type-value').text(self.formatTypeLabel(effectiveType.value));
                $('#apd-effective-type-source').text(self.formatSourceLine(effectiveType.source));

                $('#apd-effective-value-value').text(self.formatValueLabel(effectiveType.value, effectiveValue.value, context));
                $('#apd-effective-value-source').text(self.formatSourceLine(effectiveValue.source));

                $('#apd-effective-force-value').text(effectiveForce.value === 'yes'
                    ? __('Forced', 'advanced-partial-payment-or-deposit-for-woocommerce')
                    : __('Not forced', 'advanced-partial-payment-or-deposit-for-woocommerce'));
                $('#apd-effective-force-source').text(self.formatSourceLine(effectiveForce.source));

                $('#apd-effective-scope-value').text(effectiveScopeLabel);
                $('#apd-effective-scope-meta').text(self.getScopeMeta(effectiveScope, effectiveForce.source || effectiveType.source));

                $('#apd-product-setting-line').text(self.getProductLine(productEnable, productForce, productType, productValue, productMin, productMax, assignedPlanIds, context));
                $('#apd-category-setting-line').text(self.getCategoryLine(context));
                $('#apd-global-setting-line').text(self.getGlobalLine(context));

                $('#apd-preview-price').text(self.formatMoney(productPrice, context));
                $('#apd-preview-deposit').text(preview.deposit);
                $('#apd-preview-balance').text(preview.balance);
                $('#apd-preview-note').text(preview.note);
            };

            renderSummary();

            $(document).on('change input', '#_apd_enable_deposit, #_apd_force_deposit, #_apd_deposit_type, #_apd_deposit_value, #_apd_min_deposit, #_apd_max_deposit, #_apd_flexible_payments, #_price, #_regular_price, #_sale_price, input[name="_apd_assigned_plans[]"]', renderSummary);
            $(document).on('change', '#apd-product-plans-section input[name="_apd_assigned_plans[]"], #apd_payment_plans_data input[name="_apd_assigned_plans[]"]', function () {
                var value = $(this).val();
                var isChecked = $(this).is(':checked');

                $('input[name="_apd_assigned_plans[]"][value="' + value + '"]').not(this).prop('checked', isChecked);
            });
        },

        toggleFlexiblePanel: function (effectiveType) {
            // Flexible Payments is a single always-visible dropdown now, nothing to
            // reveal. Only the Payment Plan conflict still needs handling: a plan
            // schedules the balance into fixed instalments, which is the opposite of
            // paying any amount whenever, so when Flexible Payments is on it is the
            // plan that stands down.
            var flexible = $('#_apd_flexible_payments').val() === 'yes';
            var isPlan = effectiveType === 'payment_plan';

            $('#_apd_deposit_type option[value="payment_plan"]').prop('disabled', flexible);

            if (flexible) {
                $('#apd-product-plans-section').hide();
            }

            $('#apd-product-flexible-plan-note').toggle(flexible && isPlan);
        },

        toggleProductTypePanels: function (selectedType, effectiveType) {
            var showValue = selectedType === 'fixed' || selectedType === 'percentage';
            var showMinMax = selectedType === 'min_max' || (selectedType === 'global' && effectiveType === 'min_max');
            var showPlans = selectedType === 'payment_plan' || (selectedType === 'global' && effectiveType === 'payment_plan');

            $('#apd-product-value-section').toggle(showValue);
            $('#apd-product-minmax-section').toggle(showMinMax);
            $('#apd-product-plans-section').toggle(showPlans);

            $('#apd-summary-minmax-section').toggle(effectiveType === 'min_max');
            $('#apd-summary-plans-section').toggle(effectiveType === 'payment_plan');
        },

        collectSelectedPlanIds: function () {
            return $('input[name="_apd_assigned_plans[]"]:checked').map(function () {
                return $(this).val();
            }).get();
        },

        getEffectiveEnable: function (productEnable, context) {
            if (productEnable === 'yes' || productEnable === 'no') {
                return {
                    value: productEnable,
                    source: this.sourceLabel('product'),
                    sourceKey: 'product',
                };
            }

            if (context.category && (context.category.enable === 'yes' || context.category.enable === 'no')) {
                return {
                    value: context.category.enable,
                    source: this.sourceLabel('category', context.category.name),
                    sourceKey: 'category',
                };
            }

            return {
                value: context.global.enable || 'yes',
                source: this.sourceLabel('global'),
                sourceKey: 'global',
            };
        },

        getEffectiveForce: function (productForce, context) {
            if (productForce === 'yes' || productForce === 'no') {
                return {
                    value: productForce,
                    source: this.sourceLabel('product'),
                    sourceKey: 'product',
                };
            }

            if (context.category && (context.category.force === 'yes' || context.category.force === 'no')) {
                return {
                    value: context.category.force,
                    source: this.sourceLabel('category', context.category.name),
                    sourceKey: 'category',
                };
            }

            return {
                value: (context.global && context.global.force) || 'no',
                source: this.sourceLabel('global'),
                sourceKey: 'global',
            };
        },

        getEffectiveType: function (productType, context) {
            if (productType && productType !== 'global') {
                return {
                    value: productType,
                    source: this.sourceLabel('product'),
                    sourceKey: 'product',
                };
            }

            if (context.category && context.category.type && context.category.type !== 'global') {
                return {
                    value: context.category.type,
                    source: this.sourceLabel('category', context.category.name),
                    sourceKey: 'category',
                };
            }

            return {
                value: context.global.type || 'percentage',
                source: this.sourceLabel('global'),
                sourceKey: 'global',
            };
        },

        getEffectiveMinMax: function (effectiveType, productMin, productMax, context) {
            var globalMin = parseFloat((context.min_max && context.min_max.global_min) || 0);
            var globalMax = parseFloat((context.min_max && context.min_max.global_max) || 0);
            var min = productMin !== '' ? parseFloat(productMin || 0) : globalMin;
            var max = productMax !== '' ? parseFloat(productMax || 0) : globalMax;
            var sourceKey = (productMin !== '' || productMax !== '') ? 'product' : 'global';

            if (isNaN(min)) {
                min = globalMin;
            }
            if (isNaN(max)) {
                max = globalMax;
            }
            if (max > 0 && min > max) {
                min = max;
            }

            var effectiveKey = effectiveType === 'min_max' ? sourceKey : 'global';

            return {
                min: min,
                max: max,
                source: this.sourceLabel(effectiveKey),
                sourceKey: effectiveKey,
            };
        },

        getEffectivePlans: function (effectiveType, assignedPlanIds, context) {
            var plans = (context.plans && context.plans.available ? context.plans.available : []).filter(function (plan) {
                return (plan.status || 'active') === 'active';
            });

            if (effectiveType !== 'payment_plan') {
                return {
                    plans: plans,
                    count: plans.length,
                    source: this.sourceLabel('plans_available'),
                    sourceKey: 'plans_available',
                };
            }

            if (assignedPlanIds.length) {
                plans = plans.filter(function (plan) {
                    return assignedPlanIds.indexOf(plan.id) !== -1;
                });

                return {
                    plans: plans,
                    count: plans.length,
                    source: this.sourceLabel('product'),
                    sourceKey: 'product',
                };
            }

            return {
                plans: plans,
                count: plans.length,
                source: this.sourceLabel('plans_all'),
                sourceKey: 'plans_all',
            };
        },

        getEffectiveValue: function (effectiveType, productType, productValue, effectiveMinMax, effectivePlans, context) {
            if (effectiveType === 'payment_plan') {
                return {
                    value: effectivePlans,
                    source: effectivePlans.source,
                    sourceKey: effectivePlans.sourceKey,
                };
            }

            if (effectiveType === 'min_max') {
                return {
                    value: effectiveMinMax,
                    source: effectiveMinMax.source,
                    sourceKey: effectiveMinMax.sourceKey,
                };
            }

            if (productType && productType !== 'global' && productValue !== '') {
                return {
                    value: parseFloat(productValue || 0),
                    source: this.sourceLabel('product'),
                    sourceKey: 'product',
                };
            }

            if (context.category && context.category.type && context.category.type !== 'global' && context.category.value !== '') {
                return {
                    value: parseFloat(context.category.value || 0),
                    source: this.sourceLabel('category', context.category.name),
                    sourceKey: 'category',
                };
            }

            return {
                value: parseFloat(context.global.value || 0),
                source: this.sourceLabel('global'),
                sourceKey: 'global',
            };
        },

        /**
         * Scopes and sources are compared as stable keys and only turned into
         * text at render time, so translating the labels cannot change which
         * branch is taken.
         */
        getOverallScope: function (effectiveEnable, effectiveForce, effectiveType, effectiveValue) {
            var scopes = [
                this.sourceToScope(effectiveEnable.sourceKey),
                this.sourceToScope(effectiveForce.sourceKey),
                this.sourceToScope(effectiveType.sourceKey),
                this.sourceToScope(effectiveValue.sourceKey),
            ];

            if (scopes.indexOf('product') !== -1) {
                return 'product';
            }

            if (scopes.indexOf('category') !== -1) {
                return 'category';
            }

            return 'global';
        },

        sourceToScope: function (sourceKey) {
            if (sourceKey === 'product') {
                return 'product';
            }
            if (sourceKey === 'category') {
                return 'category';
            }
            return 'global';
        },

        /**
         * Translated label for a source key.
         *
         * @param {string} sourceKey  One of product|category|global|plans_available|plans_all.
         * @param {string} [name]     Category name, used by the category source.
         * @return {string}
         */
        sourceLabel: function (sourceKey, name) {
            switch (sourceKey) {
                case 'product':
                    return __('Product override', 'advanced-partial-payment-or-deposit-for-woocommerce');
                case 'category':
                    /* translators: %s: product category name. */
                    return sprintf(__('Category: %s', 'advanced-partial-payment-or-deposit-for-woocommerce'), name || '');
                case 'plans_available':
                    return __('Available plans', 'advanced-partial-payment-or-deposit-for-woocommerce');
                case 'plans_all':
                    return __('All active plans', 'advanced-partial-payment-or-deposit-for-woocommerce');
                default:
                    return __('Global settings', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
        },

        formatSourceLine: function (source) {
            /* translators: %s: where the effective setting comes from. */
            return sprintf(__('Source: %s', 'advanced-partial-payment-or-deposit-for-woocommerce'), source);
        },

        formatScopeLabel: function (scopeKey) {
            if (scopeKey === 'product') {
                return __('Product Override', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
            if (scopeKey === 'category') {
                return __('Category Fallback', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
            return __('Global Default', 'advanced-partial-payment-or-deposit-for-woocommerce');
        },

        getCurrentProductPrice: function (fallback) {
            var candidates = ['#_price', '#_sale_price', '#_regular_price'];

            for (var i = 0; i < candidates.length; i++) {
                var value = parseFloat($(candidates[i]).val());
                if (!isNaN(value) && value > 0) {
                    return value;
                }
            }

            return parseFloat(fallback || 0);
        },

        getDepositPreview: function (enabled, force, type, value, price, context, effectiveMinMax, effectivePlans) {
            var notAvailable = __('N/A', 'advanced-partial-payment-or-deposit-for-woocommerce');
            var forceNote = force === 'yes'
                ? __('Full payment is hidden.', 'advanced-partial-payment-or-deposit-for-woocommerce')
                : __('Full payment is still allowed.', 'advanced-partial-payment-or-deposit-for-woocommerce');

            if (enabled !== 'yes') {
                return {
                    deposit: __('Disabled', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    balance: notAvailable,
                    note: __('Deposit is currently disabled for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                };
            }

            if (!price || price <= 0) {
                return {
                    deposit: notAvailable,
                    balance: notAvailable,
                    note: __('Save a product price to preview the payable deposit and due balance.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                };
            }

            if (type === 'payment_plan') {
                if (!value || !value.plans || !value.plans.length) {
                    return {
                        deposit: __('No plans', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        balance: notAvailable,
                        note: __('Create or assign at least one active plan to preview the first installment.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    };
                }

                var firstPlan = value.plans[0];
                var firstInstallment = this.getFirstPlanInstallmentAmount(firstPlan, price);
                var remaining = Math.max(0, price - firstInstallment);

                return {
                    deposit: this.formatMoney(firstInstallment, context),
                    balance: this.formatMoney(remaining, context),
                    /* translators: %s: payment plan name. */
                    note: sprintf(__('Preview based on the first installment of "%s".', 'advanced-partial-payment-or-deposit-for-woocommerce'), firstPlan.name || __('the selected plan', 'advanced-partial-payment-or-deposit-for-woocommerce')) + ' ' + forceNote,
                };
            }

            if (type === 'min_max') {
                var minDeposit = Math.max(0, parseFloat((effectiveMinMax && effectiveMinMax.min) || 0));
                var maxDeposit = Math.max(minDeposit, parseFloat((effectiveMinMax && effectiveMinMax.max) || 0));

                if (maxDeposit <= 0) {
                    return {
                        deposit: __('Range not set', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        balance: notAvailable,
                        note: __('Set product or global min/max values to preview the customer-selectable range.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    };
                }

                var minBalance = Math.max(0, price - maxDeposit);
                var maxBalance = Math.max(0, price - minDeposit);

                return {
                    deposit: this.formatMoneyRange(minDeposit, maxDeposit, context),
                    balance: this.formatMoneyRange(minBalance, maxBalance, context),
                    note: __('Preview based on the current min/max range customers can choose.', 'advanced-partial-payment-or-deposit-for-woocommerce') + ' ' + forceNote,
                };
            }

            var deposit = 0;
            var numericValue = parseFloat(value || 0);

            if (type === 'fixed') {
                deposit = Math.min(numericValue, price);
            } else {
                deposit = price * (Math.min(numericValue, 100) / 100);
            }

            var balance = Math.max(0, price - deposit);

            return {
                deposit: this.formatMoney(deposit, context),
                balance: this.formatMoney(balance, context),
                note: __('Preview based on the current product price.', 'advanced-partial-payment-or-deposit-for-woocommerce') + ' ' + forceNote,
            };
        },

        getFirstPlanInstallmentAmount: function (plan, price) {
            if (!plan || !plan.installments || !plan.installments.length) {
                return 0;
            }

            var firstInstallment = plan.installments[0];
            var amount = parseFloat(firstInstallment.amount || 0);

            if ((plan.price_type || 'percentage') === 'fixed') {
                return Math.min(amount, price);
            }

            return price * (Math.min(amount, 100) / 100);
        },

        formatTypeLabel: function (type) {
            if (type === 'fixed') {
                return __('Fixed Amount', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
            if (type === 'min_max') {
                return __('Min / Max', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
            if (type === 'payment_plan') {
                return __('Payment Plan', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
            return __('Percentage', 'advanced-partial-payment-or-deposit-for-woocommerce');
        },

        formatValueLabel: function (type, value, context) {
            if (type === 'payment_plan') {
                var count = value && value.count ? value.count : 0;
                if (!count) {
                    return __('No active plans', 'advanced-partial-payment-or-deposit-for-woocommerce');
                }
                if (count === 1) {
                    return __('1 plan selected', 'advanced-partial-payment-or-deposit-for-woocommerce');
                }
                /* translators: %d: number of payment plans available. */
                return sprintf(__('%d plans available', 'advanced-partial-payment-or-deposit-for-woocommerce'), count);
            }

            if (type === 'min_max') {
                var min = value && typeof value.min !== 'undefined' ? parseFloat(value.min || 0) : 0;
                var max = value && typeof value.max !== 'undefined' ? parseFloat(value.max || 0) : 0;

                if (max <= 0) {
                    return __('Range not set', 'advanced-partial-payment-or-deposit-for-woocommerce');
                }

                return this.formatMoneyRange(min, max, context);
            }

            var numericValue = parseFloat(value || 0);
            if (type === 'fixed') {
                return this.formatMoney(numericValue, context);
            }
            return numericValue.toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: 2,
            }) + '%';
        },

        getScopeMeta: function (scopeKey, source) {
            if (scopeKey === 'product') {
                return __('This product is using its own deposit configuration.', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
            if (scopeKey === 'category') {
                /* translators: %s: source the setting falls back to, e.g. a category name. */
                return sprintf(__('Using fallback from %s.', 'advanced-partial-payment-or-deposit-for-woocommerce'), source);
            }
            return __('Using the global deposit configuration.', 'advanced-partial-payment-or-deposit-for-woocommerce');
        },

        getProductLine: function (enable, force, type, value, productMin, productMax, assignedPlanIds, context) {
            var usesFallback = __('Uses fallback', 'advanced-partial-payment-or-deposit-for-woocommerce');
            var enableLabel = enable === 'yes' ? __('Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce') : (enable === 'no' ? __('Disabled', 'advanced-partial-payment-or-deposit-for-woocommerce') : usesFallback);
            var forceLabel = force === 'yes' ? __('Forced', 'advanced-partial-payment-or-deposit-for-woocommerce') : (force === 'no' ? __('Not forced', 'advanced-partial-payment-or-deposit-for-woocommerce') : usesFallback);
            var typeLabel = type && type !== 'global' ? this.formatTypeLabel(type) : usesFallback;
            var valueLabel = usesFallback;

            if (type === 'payment_plan') {
                valueLabel = this.formatValueLabel(type, this.getEffectivePlans(type, assignedPlanIds, context), context);
            } else if (type === 'min_max') {
                valueLabel = this.formatValueLabel(type, this.getEffectiveMinMax(type, productMin, productMax, context), context);
            } else if (type && type !== 'global' && value !== '') {
                valueLabel = this.formatValueLabel(type, value, context);
            }

            /* translators: 1: availability label, 2: force-deposit label, 3: deposit type label, 4: deposit value label. */
            return sprintf(__('Availability: %1$s | Force only: %2$s | Type: %3$s | Value: %4$s', 'advanced-partial-payment-or-deposit-for-woocommerce'), enableLabel, forceLabel, typeLabel, valueLabel);
        },

        getCategoryLine: function (context) {
            var notSet = __('Not set', 'advanced-partial-payment-or-deposit-for-woocommerce');
            var categoryName = (context.category && context.category.name) ? context.category.name : __('No category override', 'advanced-partial-payment-or-deposit-for-woocommerce');
            var enableLabel = context.category && context.category.enable ? (context.category.enable === 'yes' ? __('Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce') : __('Disabled', 'advanced-partial-payment-or-deposit-for-woocommerce')) : notSet;
            var forceLabel = context.category && context.category.force ? (context.category.force === 'yes' ? __('Forced', 'advanced-partial-payment-or-deposit-for-woocommerce') : __('Not forced', 'advanced-partial-payment-or-deposit-for-woocommerce')) : notSet;
            var typeLabel = context.category && context.category.type && context.category.type !== 'global' ? this.formatTypeLabel(context.category.type) : notSet;
            var valueLabel = notSet;

            if (context.category && context.category.type === 'payment_plan') {
                valueLabel = __('Uses available payment plans', 'advanced-partial-payment-or-deposit-for-woocommerce');
            } else if (context.category && context.category.type === 'min_max') {
                valueLabel = this.formatValueLabel('min_max', {
                    min: parseFloat((context.min_max && context.min_max.global_min) || 0),
                    max: parseFloat((context.min_max && context.min_max.global_max) || 0),
                }, context);
            } else if (context.category && context.category.value !== '') {
                valueLabel = this.formatValueLabel(context.category.type || 'percentage', context.category.value, context);
            }

            /* translators: 1: category name, 2: availability label, 3: force-deposit label, 4: deposit type label, 5: deposit value label. */
            return sprintf(__('%1$s | Availability: %2$s | Force only: %3$s | Type: %4$s | Value: %5$s', 'advanced-partial-payment-or-deposit-for-woocommerce'), categoryName, enableLabel, forceLabel, typeLabel, valueLabel);
        },

        getGlobalLine: function (context) {
            var enableLabel = context.global.enable === 'yes' ? __('Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce') : __('Disabled', 'advanced-partial-payment-or-deposit-for-woocommerce');
            var forceLabel = context.global.force === 'yes' ? __('Forced', 'advanced-partial-payment-or-deposit-for-woocommerce') : __('Not forced', 'advanced-partial-payment-or-deposit-for-woocommerce');
            var typeLabel = this.formatTypeLabel(context.global.type || 'percentage');
            var valueLabel;

            if ((context.global.type || 'percentage') === 'payment_plan') {
                valueLabel = this.formatValueLabel('payment_plan', this.getEffectivePlans('payment_plan', [], context), context);
            } else if ((context.global.type || 'percentage') === 'min_max') {
                valueLabel = this.formatValueLabel('min_max', {
                    min: parseFloat((context.min_max && context.min_max.global_min) || 0),
                    max: parseFloat((context.min_max && context.min_max.global_max) || 0),
                }, context);
            } else {
                valueLabel = this.formatValueLabel(context.global.type || 'percentage', context.global.value || 0, context);
            }

            /* translators: 1: availability label, 2: force-deposit label, 3: deposit type label, 4: deposit value label. */
            return sprintf(__('Availability: %1$s | Force only: %2$s | Type: %3$s | Value: %4$s', 'advanced-partial-payment-or-deposit-for-woocommerce'), enableLabel, forceLabel, typeLabel, valueLabel);
        },

        formatMoney: function (amount, context) {
            var numericAmount = parseFloat(amount || 0);
            var decimals = parseInt(context.price_decimals || 2, 10);
            var formatted = numericAmount.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });
            var symbol = context.currency_symbol || '$';
            var position = context.currency_pos || 'left';

            switch (position) {
                case 'right':
                    return formatted + symbol;
                case 'left_space':
                    return symbol + ' ' + formatted;
                case 'right_space':
                    return formatted + ' ' + symbol;
                case 'left':
                default:
                    return symbol + formatted;
            }
        },

        formatMoneyRange: function (minAmount, maxAmount, context) {
            var low = Math.min(minAmount, maxAmount);
            var high = Math.max(minAmount, maxAmount);

            if (low === high) {
                return this.formatMoney(low, context);
            }

            return this.formatMoney(low, context) + ' - ' + this.formatMoney(high, context);
        },

        // =============================================
        // Toast Notification
        // =============================================
        showToast: function (message, type) {
            var $toast = $('#apd-toast');
            if (!$toast.length) {
                $toast = $('<div class="apd-toast" id="apd-toast"></div>').appendTo('body');
            }

            $toast
                .removeClass('apd-toast-success apd-toast-error show')
                .addClass('apd-toast-' + type)
                .text(message)
                .addClass('show');

            setTimeout(function () {
                $toast.removeClass('show');
            }, 3500);
        },
    };

    $(document).ready(function () {
        APD_Admin.init();
    });

    // Add spin animation
    $('<style>')
        .text('.apd-spin { animation: apd-spin 1s linear infinite; } @keyframes apd-spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }')
        .appendTo('head');

})(jQuery);
