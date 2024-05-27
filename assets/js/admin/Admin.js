jQuery(document).ready(function ($) {
    'use strict';
    /*global woocommerce_admin_meta_boxes */

    $('body')
        .on('change', '.woocommerce_order_items input.deposit_paid', function () {
            var row = $(this).closest('tr.item');
            var paid = $(this).val();
            var remaining = $('input.deposit_remaining', row);
            var total = $('input.line_total', row);
            if (paid !== '' && parseFloat(total.val()) - parseFloat(paid) > 0)
                remaining.val(parseFloat(total.val()) - parseFloat(paid));
            else
                remaining.val('');
        })
        .on('change', '.woocommerce_order_items input.line_total', function () {
            var row = $(this).closest('tr.item');
            var remaining = $('input.deposit_remaining', row);
            var paid = $('input.deposit_paid', row);
            var total = $(this).val();
            if (paid.val() !== '' && parseFloat(total) - parseFloat(paid.val()) >= 0)
                remaining.val(parseFloat(total) - parseFloat(paid.val()));
            else
                remaining.val('');
        })
        .on('change', '.woocommerce_order_items input.quantity', function () {
            var row = $(this).closest('tr.item');
            var remaining = $('input.deposit_remaining', row);
            var paid = $('input.deposit_paid', row);
            var total = $('input.line_total');
            setTimeout(function () {
                if (paid.val() !== '' && remaining.val() !== '' && parseFloat(total.val()) - parseFloat(paid.val()) >= 0)
                    remaining.val(parseFloat(total.val()) - parseFloat(paid.val()));
                else
                    remaining.val('');
            }, 0);
        })
        .on('change', '.wc-order-totals .edit input#_order_remaining', function () {
            // update paid amount when remaining changes
            var remaining = $(this);
            var paid = $('.wc-order-totals .edit input#_order_paid');
            var total = $('.wc-order-totals .edit input#_order_total');
            setTimeout(function () {
                if (remaining.val() !== '' && total.val() !== '')
                    paid.val(parseFloat(total.val()) - parseFloat(remaining.val()));
                else
                    paid.val('');
            }, 0);
        })
        .on('change', '.wc-order-totals .edit input#_order_paid', function () {
            // update remaining amount when paid amount changes
            var paid = $(this);
            var remaining = $('.wc-order-totals .edit input#_order_remaining');
            var total = $('.wc-order-totals .edit input#_order_total');
            setTimeout(function () {
                if (paid.val() !== '' && total.val() !== '')
                    remaining.val(parseFloat(total.val()) - parseFloat(paid.val()));
                else
                    remaining.val('');
            }, 100);
        })
        .on('change', '.wc-order-totals .edit input#_order_total', function () {
            // update remaining amount when total amount changes
            var total = $(this);
            var remaining = $('.wc-order-totals .edit input#_order_remaining');
            var paid = $('.wc-order-totals .edit input#_order_paid');
            setTimeout(function () {
                if (paid.val() !== '' && total.val() !== '')
                    remaining.val(parseFloat(total.val()) - parseFloat(paid.val()));
                else
                    remaining.val('');
            }, 0);
        });


    var mepp_recalculate_modal = {
        target: 'mepp-modal-recalculate-deposit',
        init: function () {
            $(document.body)
                .on('wc_backbone_modal_loaded', this.backbone.init)
                .on('wc_backbone_modal_response', this.backbone.response);
        },
        blockOrderItems: function () {
            $('#woocommerce-order-items').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },
        unblockOrderItems: function () {
            $('#woocommerce-order-items').unblock();
        },
        backbone: {
            init: function (e, target) {
                if (target === mepp_recalculate_modal.target) {
                    $.each($('.mepp_calculator_modal_row'), function (index, row) {
                        var enabled = $($(row).find('input.mepp_enable_deposit'));
                        var deposit_amount = $($(row).find('input.mepp_deposit_amount'));
                        var payment_plan = $($(row).find('select.mepp_payment_plan')).select2();
                        var amount_type = $($(row).find('select.mepp_deposit_amount_type'));
                        enabled.on('change', function () {
                            if ($(this).is(':checked')) {
                                $(row).find(':input:not(.mepp_enable_deposit)').removeAttr('disabled');

                            } else {
                                $(row).find(':input:not(.mepp_enable_deposit)').attr('disabled', 'disabled');

                            }
                        });
                        //one time exec
                        if (amount_type.val() !== 'payment_plan') payment_plan.next().addClass('mepp-hidden');

                        amount_type.on('change', function () {
                            if ($(this).val() === 'payment_plan') {
                                payment_plan.next().removeClass('mepp-hidden');
                                deposit_amount.addClass('mepp-hidden');
                            } else {
                                payment_plan.next().addClass('mepp-hidden');
                                deposit_amount.removeClass('mepp-hidden');
                            }
                        });

                    });

                    $('#remove_deposit_data').on('click', function () {
                        if (!window.confirm('Are you sure?')) {
                            return;
                        }
                        $('#mepp-modal-recalculate-form').append('<input type="hidden"  value="yes" name="mepp_remove_all_data" />');
                        $('.wc-backbone-modal.mepp-recalculate-deposit-modal #btn-ok').click();
                    });
                }

            },
            response: function (e, target, form_data) {

                if (target === mepp_recalculate_modal.target) {
                    mepp_recalculate_modal.blockOrderItems();
                    var data;
                    data = {
                        action: 'mepp_recalculate_deposit',
                        order_id: woocommerce_admin_meta_boxes.post_id,
                        security: woocommerce_admin_meta_boxes.order_item_nonce,
                    };

                    if(typeof form_data.mepp_remove_all_data  !== 'undefined' && form_data.mepp_remove_all_data === 'yes'){
                        data.remove_all_data = 'yes';
                    } else {
                        data.order_items = form_data;
                    }

                    $.ajax({
                        url: woocommerce_admin_meta_boxes.ajax_url,
                        data: data,
                        type: 'POST',

                        success: function (response) {

                            if (response.success) {
                                mepp_recalculate_modal.unblockOrderItems();
                                $('#_order_deposit').remove();
                                location.reload();
                            }
                        }
                    });
                }
            }
        }
    };
    mepp_recalculate_modal.init();


});
jQuery(document).ready(function ($) {
    'use strict';

    var deposit_variation = function () {
        var update_plan = function (loop, amount_type) {
            if ($('#_mepp_override_product_settings' + loop).is(':checked')) {
                if (amount_type === 'payment_plan') {
                    $('._mepp_payment_plans' + loop + '_field').removeClass('hidden');
                    $('._mepp_deposit_amount' + loop + '_field ').addClass('hidden');
                } else {
                    $('._mepp_payment_plans' + loop + '_field').addClass('hidden');
                    $('._mepp_deposit_amount' + loop + '_field ').removeClass('hidden');
                }
            }

        };

        $('.mepp_override_product_settings').change(function () {

            var loop = $(this).data('loop');
            if ($(this).is(':checked')) {

                $('.mepp_field' + loop).removeClass('hidden');
                $(this).parent().parent().find('.mepp_varitaion_amount_type').trigger('change');

            } else {
                $('.mepp_field' + loop).addClass('hidden');
            }

        });

        $('.mepp_override_product_settings').trigger('change');

        $('.mepp_varitaion_amount_type').change(function () {
            var loop = $(this).data('loop');
            update_plan(loop, $(this).val());
        });

        $('.mepp_varitaion_amount_type').trigger('change');
    };

    // $( '#variable_product_options' ).trigger( 'woocommerce_variations_added', 1 );
    $('#woocommerce-product-data').on('woocommerce_variations_loaded', deposit_variation);
    $('#variable_product_options').on('woocommerce_variations_added', deposit_variation);

//payment plans toggle
    $('#_mepp_amount_type').change(function () {

        if ($('.mepp_override_product_settings').is(':checked')) {

            if ($(this).val() === 'payment_plan') {
                $('._mepp_payment_plans_field').removeClass('hidden');
                $('._mepp_deposit_amount_field ').addClass('hidden');
            } else {
                $('._mepp_payment_plans_field').addClass('hidden');
                $('._mepp_deposit_amount_field ').removeClass('hidden');
            }
        }

    });


    $('#_mepp_amount_type').change(function () {


        if ($(this).val() === 'payment_plan') {
            $('._mepp_payment_plans_field').removeClass('hidden');
            $('._mepp_deposit_amount_field ').addClass('hidden');
        } else {
            $('._mepp_payment_plans_field').addClass('hidden');
            $('._mepp_deposit_amount_field ').removeClass('hidden');
        }


    });



    $('#_mepp_inherit_storewide_settings').change(function () {
        $('.mepp_deposit_values.options_group').toggleClass('hidden');
    });

//reminder datepicker
    $("#reminder_datepicker").datepicker({
        dateFormat: "dd-mm-yy",
        minDate: new Date()
    });

});
jQuery(document).ready(function ($) {
    'use strict';

    $('.deposits-color-field').wpColorPicker();

    //checkout fields toggle payment plans field


    var checkout_mode_toggle = function () {

        var payment_plan_row = $('#mepp_checkout_mode_payment_plans').parent().parent();
        var amount_row = $('#mepp_checkout_mode_deposit_amount').parent().parent();
        if ($('#mepp_checkout_mode_deposit_amount_type').val() === 'payment_plan') {
            amount_row.slideUp('fast');
            payment_plan_row.slideDown('fast');
            $('#mepp_checkout_mode_payment_plans').attr('required','required');

        } else {
            payment_plan_row.slideUp('fast');
            amount_row.slideDown('fast');
            $('#mepp_checkout_mode_payment_plans').removeAttr('required');

        }

    };
    $('#mepp_checkout_mode_deposit_amount_type').change(function () {
        checkout_mode_toggle();
    });

    var storewide_deposit_toggle = function () {

        var payment_plan_row = $('#mepp_storewide_deposit_payment_plans').parent().parent();
        var amount_row = $('#mepp_storewide_deposit_amount').parent().parent();

        if ($('#mepp_storewide_deposit_amount_type').val() === 'payment_plan') {
            amount_row.slideUp('fast');
            payment_plan_row.slideDown('fast');
            $('#mepp_storewide_deposit_payment_plans').attr('required','required');
        } else {
            payment_plan_row.slideUp('fast');
            $('#mepp_storewide_deposit_payment_plans').removeAttr('required');

            amount_row.slideDown('fast');
        }

    };
    $('#mepp_storewide_deposit_amount_type').change(function () {
        storewide_deposit_toggle();
    });


    storewide_deposit_toggle();
    checkout_mode_toggle();

    // if()
    // tabs

    if ($('.mepp.nav-tab').length > 0) {


        var switchTab = function (target) {
            $('.mepp-tab-content').hide();
            $('.mepp.nav-tab').removeClass('nav-tab-active');
            $('.mepp.nav-tab[data-target="' + target + '"]').addClass('nav-tab-active');
            var ele = $('#' + target);
            ele.show();
        };

        $('.mepp.nav-tab').on('click', function (e) {
            e.preventDefault();
            var target = $(this).data('target');
            location.hash = target;
            switchTab(target);
            $('html,body').animate({
                scrollTop: '-=200px'
            });

            return false;

        });

        if(window.location.hash.length > 0){
            $('.mepp.nav-tab[data-target="' + window.location.hash.split('#')[1] + '"]').trigger('click');
        }
    }
});


jQuery(document).ready(function ($) {
    'use strict';
    var plan_container = $('#payment_plan_details');

    if (plan_container.length > 0) {
        var plan_total = 0.0;
        var repeater_instance;
        var data = plan_container.data('populate');
        var update_date_display = function(checkbox){

            var row = $(checkbox).parent().parent();
            if(checkbox.is(':checked')){
                row.find('.mepp-pp-after').hide().attr('disabled','disabled');
                row.find('.mepp-pp-after-term').hide().attr('disabled','disabled');
                row.find('.mepp-pp-date').show().removeAttr('disabled');
            } else {
                row.find('.mepp-pp-after').show().removeAttr('disabled');
                row.find('.mepp-pp-after-term').show().removeAttr('disabled');
                row.find('.mepp-pp-date').hide().attr('disabled','disabled').val('');
            }

        };
        var update_total = function () {

            var plans = plan_container.find('.single_payment');

            plan_total = 0.0;
            $.each(plans, function (index, single_plan) {
                var field = $(single_plan).find('input').first();
                var field_val = $(field).val();
                if (field_val.length > 0) {

                    field_val = parseFloat(field_val);
                    if (typeof field_val === 'number') {
                        plan_total = plan_total + field_val;
                    }
                }
            });

            if($('#amount_type').val() === 'fixed'){
                $( '#total_percentage' ).html( accounting.formatMoney( Math.round((plan_total + Number.EPSILON) * 100) / 100, {
                    symbol:    wcip_data.currency_format_symbol,
                    decimal:   wcip_data.currency_format_decimal_sep,
                    thousand:  wcip_data.currency_format_thousand_sep,
                    precision: wcip_data.currency_format_num_decimals,
                    format:    wcip_data.currency_format
                } ) );
            } else {
                $('#total_percentage').text(Math.round((plan_total + Number.EPSILON) * 100) / 100 + '%');

            }
        };

        repeater_instance = plan_container.repeater({
            initEmpty: false,
            isFirstItemUndeletable: true,
            defaultValues: {
                'percent': '1.0',
                'after': '1',
                'after-term': 'day'
            },
            show: function () {

                var siblings_count = $(this).siblings().length;
                var number = siblings_count + 1;

                $(this).children().first('td').text('#' + number);
                $(this).slideDown();

                $('.single_payment input').on('input', function () {
                    update_total();
                });

                //fix issue with jquery repeater, for the init row
                $(this).find('.mepp_pp_set_date').on('change', function () {
                    update_date_display($(this));
                });

                $.each($(this).find('.mepp_pp_set_date'),function(index,checkbox){
                    update_date_display($(checkbox));
                });

            },
            hide: function (deleteElement) {
                    var count = 1;
                    $.each($(this).siblings(), function (index, sibling) {
                        $(sibling).children().first('td').text('#' + count);
                        count++;
                    });
                    $(this).slideUp(deleteElement);



            },
            ready: function (){

                $('.single_payment input').on('input', function () {
                    update_total();
                });

                window.setTimeout(function(){
                    update_total();
                },1000);

                $.each(plan_container.find('.mepp_pp_set_date'),function(index,checkbox){
                    update_date_display($(checkbox));
                });
            }
        });
        if(typeof data['payment-plan'] !== 'undefined'){
            repeater_instance.setList(data['payment-plan']);
        }

        //fix issue with jquery repeater, for the init row
        $('.mepp_pp_set_date').on('change', function () {
            update_date_display($(this));
        });

        //submission
        $('#edittag').submit(function () {
            var values = plan_container.repeaterVal();
            delete values['deposit-percentage'];
            $(this).append('<input name="payment-details" type="hidden" value=\'' + JSON.stringify(values) + '\'  />');

            return true;
        });
        $('#amount_type').on('change',function(){
            update_total();
        })
    }


});

jQuery(document).ready(function($) {
    "use strict";
    var plan_container = $("#payment_plan_details");
    if (plan_container.length > 0) {
        var plan_total = 0;
        var repeater_instance;
        var data = plan_container.data("populate");
        var update_date_display = function(checkbox) {
            var row = $(checkbox).parent().parent();
            if (checkbox.is(":checked")) {
                row.find(".mepp-pp-after").hide().attr("disabled", "disabled");
                row.find(".mepp-pp-after-term").hide().attr("disabled", "disabled");
                row.find(".mepp-pp-date").show().removeAttr("disabled")
            } else {
                row.find(".mepp-pp-after").show().removeAttr("disabled");
                row.find(".mepp-pp-after-term").show().removeAttr("disabled");
                row.find(".mepp-pp-date").hide().attr("disabled", "disabled").val("")
            }
        };
        var update_total = function() {
            var plans = plan_container.find(".single_payment");
            plan_total = 0;
            $.each(plans, function(index, single_plan) {
                var field = $(single_plan).find("input").first();
                var field_val = $(field).val();
                if (field_val.length > 0) {
                    field_val = parseFloat(field_val);
                    if (typeof field_val === "number") {
                        plan_total = plan_total + field_val
                    }
                }
            });
            if ($("#amount_type").val() === "fixed") {
                $("#total_percentage").html(accounting.formatMoney(Math.round((plan_total + Number.EPSILON) * 100) / 100, {
                    symbol: wcip_data.currency_format_symbol,
                    decimal: wcip_data.currency_format_decimal_sep,
                    thousand: wcip_data.currency_format_thousand_sep,
                    precision: wcip_data.currency_format_num_decimals,
                    format: wcip_data.currency_format
                }))
            } else {
                $("#total_percentage").text(Math.round((plan_total + Number.EPSILON) * 100) / 100 + "%")
            }
        };
        repeater_instance = plan_container.repeater({
            initEmpty: false,
            isFirstItemUndeletable: true,
            defaultValues: {
                percent: "1.0",
                after: "1",
                "after-term": "day"
            },
            show: function() {
                var siblings_count = $(this).siblings().length;
                var number = siblings_count + 1;
                $(this).children().first("td").text("#" + number);
                $(this).slideDown();
                $(".single_payment input").on("input", function() {
                    update_total()
                });
                $(this).find(".mepp_pp_set_date").on("change", function() {
                    update_date_display($(this))
                });
                $.each($(this).find(".mepp_pp_set_date"), function(index, checkbox) {
                    update_date_display($(checkbox))
                })
            },
            hide: function(deleteElement) {
                var count = 1;
                $.each($(this).siblings(), function(index, sibling) {
                    $(sibling).children().first("td").text("#" + count);
                    count++
                });
                $(this).slideUp(deleteElement)
            },
            ready: function() {
                $(".single_payment input").on("input", function() {
                    update_total()
                });
                window.setTimeout(function() {
                    update_total()
                }, 1e3);
                $.each(plan_container.find(".mepp_pp_set_date"), function(index, checkbox) {
                    update_date_display($(checkbox))
                })
            }
        });
        if (typeof data["payment-plan"] !== "undefined") {
            repeater_instance.setList(data["payment-plan"])
        }
        $(".mepp_pp_set_date").on("change", function() {
            update_date_display($(this))
        });
        $("#edittag").submit(function() {
            var values = plan_container.repeaterVal();
            delete values["deposit-percentage"];
            $(this).append('<input name="payment-details" type="hidden" value=\'' + JSON.stringify(values) + "'  />");
            return true
        });
        $("#amount_type").on("change", function() {
            update_total()
        })
    }
});

jQuery(document).ready(function($) {
    "use strict";
    $(".deposits-color-field").wpColorPicker();
    var checkout_mode_toggle = function() {
        var payment_plan_row = $("#mepp_checkout_mode_payment_plans").parent().parent();
        var amount_row = $("#mepp_checkout_mode_deposit_amount").parent().parent();
        if ($("#mepp_checkout_mode_deposit_amount_type").val() === "payment_plan") {
            amount_row.slideUp("fast");
            payment_plan_row.slideDown("fast");
            $("#mepp_checkout_mode_payment_plans").attr("required", "required")
        } else {
            payment_plan_row.slideUp("fast");
            amount_row.slideDown("fast");
            $("#mepp_checkout_mode_payment_plans").removeAttr("required")
        }
    };
    $("#mepp_checkout_mode_deposit_amount_type").change(function() {
        checkout_mode_toggle()
    });
    var storewide_deposit_toggle = function() {
        var payment_plan_row = $("#mepp_storewide_deposit_payment_plans").parent().parent();
        var amount_row = $("#mepp_storewide_deposit_amount").parent().parent();
        if ($("#mepp_storewide_deposit_amount_type").val() === "payment_plan") {
            amount_row.slideUp("fast");
            payment_plan_row.slideDown("fast");
            $("#mepp_storewide_deposit_payment_plans").attr("required", "required")
        } else {
            payment_plan_row.slideUp("fast");
            $("#mepp_storewide_deposit_payment_plans").removeAttr("required");
            amount_row.slideDown("fast")
        }
    };
    $("#mepp_storewide_deposit_amount_type").change(function() {
        storewide_deposit_toggle()
    });
    storewide_deposit_toggle();
    checkout_mode_toggle();
    $("#mepp_verify_purchase_code").on("click", function(e) {
        e.preventDefault();
        var purchase_code = $("#mepp_purchase_code").val();
        if (purchase_code.length < 1) {
            window.alert("Purchase code cannot be empty");
            return false
        }
        $(this).attr("disabled", "disabled");
        $("#mepp_verify_purchase_container").prepend('<img src="images/loading.gif" />');
        var data = {
            action: "mepp_verify_purchase_code",
            purchase_code: $("#mepp_purchase_code").val(),
            nonce: $("#mepp_verify_purchase_code_nonce").val()
        };
        $.post(mepp.ajax_url, data).done(function(res) {
            if (res.success) {
                $("#mepp_verify_purchase_code").removeAttr("disabled");
                $("#mepp_verify_purchase_container").empty().append('<span style="color:green;">' + res.data + "</span>")
            } else {
                $("#mepp_verify_purchase_code").removeAttr("disabled");
                $("#mepp_verify_purchase_container").empty().append('&nbsp;<span style="color:red;" >' + res.data + "</span>")
            }
        }).fail(function() {
            $(this).removeAttr("disabled");
            window.alert("Error occurred")
        })
    });
    if ($(".mepp.nav-tab").length > 0) {
        var switchTab = function(target) {
            $(".mepp-tab-content").hide();
            $(".mepp.nav-tab").removeClass("nav-tab-active");
            $('.mepp.nav-tab[data-target="' + target + '"]').addClass("nav-tab-active");
            var ele = $("#" + target);
            ele.show()
        };
        $(".mepp.nav-tab").on("click", function(e) {
            e.preventDefault();
            var target = $(this).data("target");
            location.hash = target;
            switchTab(target);
            $("html,body").animate({
                scrollTop: "-=200px"
            });
            return false
        });
        if (window.location.hash.length > 0) {
            $('.mepp.nav-tab[data-target="' + window.location.hash.split("#")[1] + '"]').trigger("click")
        }
    }
});