jQuery(document).ready(function ($) {
    'use strict';

    var options = mepp_add_to_cart_options;

    $.fn.initDepositController = function () {
        var depositController = {
            init: function (form, ajax_reload = false,selection = false) {
                depositController.update_html_request = false;

                var $cart = $(form);
                var $form = $cart.find('.magepeople_mepp_single_deposit_form');
                depositController.$cart = $cart;
                depositController.$form = $form;
                $form.deposit = $form.find('.pay-deposit').get(0);
                $form.full = $form.find('.pay-full-amount').get(0);
                $form.msg = $form.find('.wc-deposits-notice');
                $form.amount = $form.find('.deposit-amount');
                $form.ajax_refreh = $form.data('ajax-refresh');

                $form.payment_plans_container = $form.find(('.mepp-payment-plans'));
                $cart.woocommerce_products_addons = $cart.find('#product-addons-total');

                if(typeof $.epoAPI !== 'undefined'){
                    $.epoAPI.addFilter( 'tcAdjustFormattedFinalTotal', this.tmepo_price_change, 10, 2 );
                }

                $cart.on('change', 'input, select', this.update_status);
                if (!ajax_reload) {

                    //hide deposit form initially in variable product
                    var product_elem = $form.closest('.product');
                    if (product_elem.hasClass('product-type-variable')) {
                        $form.slideUp();
                    }

                    if ($cart.woocommerce_products_addons.length > 0) {
                        $cart.on('updated_addons', this.addons_updated);
                    }

                    $cart.on('show_variation', this.update_variation)
                        .on('click', '.reset_variations', function () {
                            $($form).slideUp();
                        });
                    $cart.on('hide_variation', this.hide);

                }

                if(selection){
                    if(selection === 'deposit'){
                        $(depositController.$form.deposit).attr('checked','checked');
                        $(depositController.$form.full).removeAttr('checked');
                    } else{
                        $(depositController.$form.full).attr('checked','checked');
                        $(depositController.$form.deposit).removeAttr('checked');
                    }
                }
                this.update_status($form);
                $form.on('update_html', this.update_html);

                if ($($form.payment_plans_container).length > 0) {
                    this.update_payment_plans_container();
                }


            },
            hide: function () {
                depositController.$form.slideUp();

            },
            update_payment_plans_container: function () {
                depositController.$form.payment_plans_container.find('a.mepp-view-plan-details').click(function () {
                    var plan_id = $(this).data('id');
                    var selector = '.plan-details-' + plan_id;
                    if ($(this).data('expanded') === 'no') {
                        var text = $(this).data('hide-text');
                        $(this).text(text);
                        $(this).data('expanded', 'yes');
                        depositController.$form.find(selector).slideDown();
                    } else if ($(this).data('expanded') === 'yes') {
                        var text = $(this).data('view-text');
                        $(this).text(text);
                        $(this).data('expanded', 'no');
                        depositController.$form.find(selector).slideUp();
                    }

                });
            },
            tmepo_price_change : function (formatted_final_total,args){

                var product_id = args.epo.product_id;
                if($('.variation_id').length > 0 && $('.variation_id').val() != '0' ){
                    product_id = $('.variation_id').val();
                }
                var data = {
                    price: args.product_total_price / args.cartQty ,
                    product_id: product_id,
                    trigger: 'woocommerce_tm_extra_product_options'
                };
                depositController.$form.trigger('update_html', data);
                return formatted_final_total;
            },
            addons_updated: function () {
                var addons_form = depositController.$cart.woocommerce_products_addons;
                var data = {
                    price: 0,
                    product_id: $(addons_form).data('product-id'),
                    trigger: 'woocommerce_product_addons'
                };
                data.price = $(addons_form).data('price');
                if (depositController.$cart.find('#wc-bookings-booking-form').length) {
                    //addons + bookings
                    if ($('.wc-bookings-booking-cost').length > 0) {

                        var booking_price = parseFloat($('.wc-bookings-booking-cost').attr('data-raw-price'));
                        if (!Number.isNaN(booking_price)) {
                            data.price = booking_price;
                        }
                    } else {
                        data.price = 0;
                    }
                }

                var addons_price = $(addons_form).data('price_data');
                $.each(addons_price, function (index, single_addon) {
                    data.price = data.price + single_addon.cost;
                });
                if(typeof depositController.$cart.woocommerce_products_addons_price == 'undefined'){
                    depositController.$cart.woocommerce_products_addons_price = 0;
                }
                var same_price = data.price === depositController.$cart.woocommerce_products_addons_price;

                if (!same_price) {
                    depositController.$form.trigger('update_html', data);
                }


            },
            update_html: function (e, data) {

                if (!data || depositController.$form.ajax_refreh === 'no') return;
                if (Number.isNaN(data.price)) return;
                if (!data.product_id) return;
                if (depositController.$cart.woocommerce_products_addons.length && data.trigger !== 'woocommerce_product_addons') return;
                if(data.trigger === 'woocommerce_product_addons'){
                    depositController.$cart.woocommerce_products_addons_price = data.price;

                }
                if (depositController.update_html_request) {
                    depositController.update_html_request.abort();
                    depositController.update_html_request = false;
                }

                depositController.$cart.block({
                    message: null,
                    overlayCSS: {
                        background: "#fff",
                        backgroundSize: "16px 16px", opacity: .6
                    }
                });

                var request_data = {
                    action: 'mepp_update_deposit_container',
                    price: data.price,
                    product_id: data.product_id,
                    data: data //allow any other data to be included
                };

                depositController.update_html_request = $.post(options.ajax_url, request_data).done(function (res) {
                    if (res.success) {
                        depositController.$form.replaceWith(res.data);
                        let selection = 'full';
                        if($(depositController.$form.deposit).is(':checked')){
                            selection = 'deposit';
                        }
                        depositController.init(depositController.$cart, true,selection);
                    }

                    if(data.trigger === 'woocommerce_product_addons'){
                        depositController.$cart.woocommerce_products_addons_price = data.price;

                    }
                    depositController.$cart.unblock();
                }).fail(function () {
                    // alert('Error occurred');

                });

            },
            update_status: function () {

                if ($(depositController.$form.deposit).is(':checked')) {
                    if (depositController.$form.payment_plans_container.length > 0) {
                        depositController.$form.payment_plans_container.slideDown();
                    }

                    $(depositController.$form.msg).html(options.message.deposit);
                } else if ($(depositController.$form.full).is(':checked')) {
                    if (depositController.$form.payment_plans_container.length > 0) {
                        depositController.$form.payment_plans_container.slideUp();
                    }
                    $(depositController.$form.msg).html(options.message.full);
                }
            },
            update_variation: function (event, variation) {

                var id = variation.variation_id;
                var data = {
                    product_id: id
                };
                depositController.$form.trigger('update_html', data);
                return;

            }
        };

        depositController.init(this);

    };

    $('body').find('form.cart').each(function (index, elem) {
        $(elem).initDepositController();
    });

});

jQuery(document).ready(function($){
    'use strict';

    var activate_tooltip = function() {
        $('#deposit-help-tip').tipTip({
            'attribute': 'data-tip',
            'fadeIn': 50,
            'fadeOut': 50,
            'delay': 200,
        });
    };

    $(document.body).on('updated_cart_totals updated_checkout',activate_tooltip);

    activate_tooltip();
});

jQuery(document).ready(function($) {
    'use strict';

    $( document.body ).on( 'updated_checkout',function(){

        var options = mepp_checkout_options;
        var form = $('#wc-deposits-options-form');
        var deposit = form.find('#pay-deposit');
        var deposit_label = form.find('#pay-deposit-label');
        var full = form.find('#pay-full-amount');
        var full_label = form.find('#pay-full-amount-label');
        var msg = form.find('#wc-deposits-notice');
        var amount = form.find('#deposit-amount');
        var update_message = function() {

            if (deposit.is(':checked')) {

                msg.html(options.message.deposit);
            } else if (full.is(':checked')) {
                msg.html(options.message.full);
            }
        };


        $('[name="mepp-selected-plan"],[name="deposit-radio"]').on('change',function(){
            $( document.body ).trigger( 'update_checkout');
        });
        $('.checkout').on('change', 'input, select', update_message);
        update_message();

        if ($('#mepp-payment-plans').length > 0) {


            $('#mepp-payment-plans a.mepp-view-plan-details').click(function () {
                var plan_id = $(this).data('id');
                var selector = '#plan-details-' + plan_id;
                if ($(this).data('expanded') === 'no') {
                    var text = $(this).data('hide-text');
                    $(this).text(text);
                    $(this).data('expanded', 'yes');
                    $(selector).slideDown();
                } else if ($(this).data('expanded') === 'yes') {
                    var text = $(this).data('view-text');
                    $(this).text(text);
                    $(this).data('expanded', 'no');
                    $(selector).slideUp();

                }

            });


        }




    });




});

