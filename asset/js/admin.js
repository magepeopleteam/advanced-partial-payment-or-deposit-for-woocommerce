(function ($) {
    'use strict';

    const wcpp_loader_html = `<img id="wcpp-loader" src="${wcpp_php_vars.WCPP_PLUGIN_URL}asset/img/wcpp-loader.gif" alt="Data loader">`;

    $(document).ready(function () {
        $('#_mep_pp_deposits_type[name="_mep_pp_deposits_type"]').trigger('change');

        // Init data
        let checkout_mode = $("#mepp_partial_enable_for_page").find(":selected").val();

        // Call init function
        wcpp_init([checkout_mode]);

        // $('.mep-view-reminder-log').trigger('click');

        // Inherit site wide setting
        let inherit_setting = $("#_mep_exclude_from_global_deposit").find(":selected").val();
        wcpp_inherit_setting_control($("#_mep_exclude_from_global_deposit"), inherit_setting);
        $("#_mep_exclude_from_global_deposit").change(function() {
            const value = $(this).find(':selected').val();
            wcpp_inherit_setting_control($(this), value);
        });

        // Initail deposit type check in setting
        const default_deposit_type = $('select[name="mepp_default_partial_type"] option:selected').val();
        if(default_deposit_type === 'payment_plan') {
            $('.mepp-payment-plan-sett').show();
            $('.mepp-payment-deposit-value').hide();
        } else {
            $('.mepp-payment-plan-sett').hide();
            $('.mepp-payment-deposit-value').show();
        }

        // Change deposit type in setting
        $('select[name="mepp_default_partial_type"]').change(function() {
            const selected_val = $("option:selected", this).val();
            if(selected_val === 'payment_plan') {
                $('.mepp-payment-plan-sett').show();
                $('.mepp-payment-deposit-value').hide();
            } else {
                $('.mepp-payment-plan-sett').hide();
                $('.mepp-payment-deposit-value').show();
            }
        })

        // mepp TAB
        $('.mepp-tab-a').click(function (e) {
            e.preventDefault();
            $('.mepp-tab-a').removeClass('active-a')
            $(this).addClass('active-a')

            $(".mepp-tab").removeClass('mepp-tab-active');
            $(".mepp-tab[data-id='" + $(this).attr('data-id') + "']").addClass("mepp-tab-active");
            $(this).parent().find(".tab-a").addClass('active-a');
        });

        // Settings
        $("#mepp_partial_enable_for_page").change(function() {
            const $this = $(this);
            wcpp_checkout_mode_change($this.val())
        });

    });
    $(document).on('change', 'div.tab-content #_mep_pp_deposits_type[name="_mep_pp_deposits_type"] ', function (e) {
        e.preventDefault();
        let value=$(this).val();
        let parent=$(this).closest('table');
        let depositTarget=parent.find('[name="_mep_pp_deposits_value"]');
        let paymentTarget=parent.find('[name="_mep_pp_payment_plan[]"]');
        let customTarget=parent.find('[name="_mep_pp_minimum_value"]');
        if(value==='percent' || value==='fixed'){
            depositTarget.closest('tr').slideDown(250);
            paymentTarget.closest('tr').slideUp(250);
            customTarget.closest('tr').slideUp(250);
        } else if(value==='payment_plan'){
            depositTarget.closest('tr').slideUp(250);
            customTarget.closest('tr').slideUp(250);
            paymentTarget.closest('tr').slideDown(250);
        }else{
            depositTarget.closest('tr').slideUp(250);
            paymentTarget.closest('tr').slideUp(250);
            customTarget.closest('tr').slideDown(250);
        }
    });
    $(document).on('change', '#woo_desposits_options [name="_mep_pp_deposits_type"] ', function (e) {
        e.preventDefault();
        let value=$(this).val();
        let parent=$(this).closest('#woo_desposits_options');
        let depositTarget=parent.find('[name="_mep_pp_deposits_value"]');
        let paymentTarget=parent.find('[name="_mep_pp_payment_plan[]"]');
        let customTarget=parent.find('[name="_mep_pp_minimum_value"]');
        if(value==='percent' || value==='fixed'){
            depositTarget.closest('p').slideDown(250);
            paymentTarget.closest('p').slideUp(250);
            customTarget.closest('p').slideUp(250);
        } else if(value==='payment_plan'){
            depositTarget.closest('p').slideUp(250);
            customTarget.closest('p').slideUp(250);
            paymentTarget.closest('p').slideDown(250);
        }else{
            depositTarget.closest('p').slideUp(250);
            paymentTarget.closest('p').slideUp(250);
            customTarget.closest('p').slideDown(250);
        }
    });

    // Partial order data filter
    $('#filter_order_id').on('change keyup copy paste cut', function() {
        wcpp_partial_order_filter($(this));
    })

    $('#filter_deposit_type').on('change', function() {
        wcpp_partial_order_filter($(this));
    })

    // View Partial Payment History
    $(document).on('click', '.mep-view-history', function() {
        mepp_admin_get_partial_history($(this));
        $('html, body').animate({
            scrollTop: $(this).offset().top - 200
        }, 1000);
    })

    // View order detail
    $(document).on('click', '.mep-view-order', function() {
        mepp_admin_get_partial_order_detail($(this));
        $('html, body').animate({
            scrollTop: $(this).offset().top - 200
        }, 1000);
    })

    // View Partial Payment Reminder log
    $(document).on('click', '.mep-view-reminder-log', function() {
        mepp_admin_get_partial_reminder_log($(this));
        $('html, body').animate({
            scrollTop: $(this).offset().top - 200
        }, 1000);
    })

    // Rs-end Next Payment Reminder
    $(document).on('click', '.resend_next_reminder', function() {
        const $this = $(this);
        const order_id = $this.attr('data-this-order-id');

        if(order_id) {
            $.ajax({
                url: wcpp_php_vars.ajaxurl,
                type: 'POST',
                dataType: 'html',
                data: {
                    order_id,
                    action: 'mepp_admin_resend_next_reminder'
                },
                beforeSend: function() {
                    // $('#mepModal .modal-body').append(wcpp_loader_html);
                    // parent.find('.wcpp-partial-order-action-loader').css('visibility', 'visible');
                },
                success: function(data) {

                }
            })
        }
    });

    // Send Next Payment Reminder Now
    $(document).on('click', '.mepp-next-reminder-send-now', function() {
        const $this = $(this);
        const order_id = $this.attr('data-order-id');
        const order_type = $this.attr('data-order-type');

        if(order_id) {
            $.ajax({
                url: wcpp_php_vars.ajaxurl,
                type: 'POST',
                dataType: 'html',
                data: {
                    order_type,
                    order_id,
                    action: 'mepp_admin_send_now_next_reminder'
                },
                beforeSend: function() {
                    $this.prev('.mepp-inner-loading').show();
                },
                success: function(data) {
                    if(data === 'success') {
                        alert('Reminder Email Sent.')
                    } else {
                        alert('Reminder Email not Sent!.')
                    }
                    $this.prev('.mepp-inner-loading').hide();
                }
            })
        }
    })

    // Mepp Modal
    const mep_modal = $("#mepModal");

    // Get the button that opens the modal
    const btn = $('.mepModalBtn');

    // Get the <span> element that closes the modal
    const close = $('.mepModalclose');

    // When the user clicks the button, open the modal
    $(document).on('click', '.mepModalBtn', function() {
        mep_modal.show();
        // mepp_admin_get_partial_history($(this))

    });
    // When the user clicks on <span> (x), close the modal
    $(document).on('click', '.mepModalclose',function() {
        mep_modal.hide();
        $('#mepModal .modal-body').empty();
    });

    // When the user clicks anywhere outside of the modal, close it
    // window.onclick = function(event) {
    //     if (event.target == mep_modal) {
    //         mep_modal.hide();
    //     }
    // }
    // Mepp Modal END

    // Partial Payment history - Open in Modal
    // function mepp_admin_get_partial_history($this) {
    //     const order_id = $this.attr('data-order-id');
    //     if(order_id) {
    //         const title = `Partial Payment History (#${order_id})`
    //         $.ajax({
    //             url: wcpp_php_vars.ajaxurl,
    //             type: 'POST',
    //             dataType: 'html',
    //             data: {
    //                 order_id,
    //                 action: 'mepp_admin_get_partial_history'
    //             },
    //             beforeSend: function() {
    //                 $('#mepModal .modal-body').append(wcpp_loader_html);
    //             },
    //             success: function(data) {
    //                 $('#mepModal .modal-header h2').html(title);
    //                 $('#mepModal .modal-body').html(data);
    //             }
    //         })
    //     }
    // }

    // Partial Payment history - open inside table
    function mepp_admin_get_partial_history($this) {
        const parent = $this.parents('tr');
        const order_id = $this.attr('data-order-id');

        if (parent.next('.wcpp-data-tr').hasClass('show')) {
            parent.next('.wcpp-data-tr').remove();
            return;
        }

        $('.wcpp-partial-order-table tr.wcpp-data-tr').each(function () {
            if ($(this).hasClass('show')) {
                $(this).removeClass('show');
                $(this).hide();
            }
        });

        if(order_id) {
            const title = `Partial Payment History (#${order_id})`
            $.ajax({
                url: wcpp_php_vars.ajaxurl,
                type: 'POST',
                dataType: 'html',
                data: {
                    order_id,
                    action: 'mepp_admin_get_partial_history'
                },
                beforeSend: function() {
                    // $('#mepModal .modal-body').append(wcpp_loader_html);
                    parent.find('.wcpp-partial-order-action-loader').css('visibility', 'visible');
                },
                success: function(data) {
                    if (parent.next('.wcpp-data-tr').children().length == 0) {
                        $(data).insertAfter(parent);
                        parent.next('.wcpp-data-tr').slideDown(100);
                    }
                    if (parent.next('.wcpp-data-tr').hasClass('show')) {
                        parent.next('.wcpp-data-tr').remove();
                    } else {
                        parent.next('.wcpp-data-tr').slideDown(100);
                    }
                    parent.next('.wcpp-data-tr').toggleClass('show');

                    parent.find('.wcpp-partial-order-action-loader').css('visibility', 'hidden');
                }
            })
        }
    }

    // Partial Payment Reminder Log - open inside table
    function mepp_admin_get_partial_reminder_log($this) {
        const parent = $this.parents('tr');
        const order_id = $this.attr('data-order-id');

        $('.wcpp-partial-order-table tr').removeClass('active');

        if (parent.next('.wcpp-data-tr').hasClass('show')) {
            parent.removeClass('active');
            parent.next('.wcpp-data-tr').remove();
            return;
        }

        $('.wcpp-partial-order-table tr.wcpp-data-tr').each(function () {
            if ($(this).hasClass('show')) {
                $(this).removeClass('show');
                $(this).hide();
            }
        });

        if(order_id) {
            const title = `Partial Payment History (#${order_id})`
            $.ajax({
                url: wcpp_php_vars.ajaxurl,
                type: 'POST',
                dataType: 'html',
                data: {
                    order_id,
                    action: 'mepp_admin_get_partial_reminder_log'
                },
                beforeSend: function() {
                    parent.find('.wcpp-partial-order-action-loader').css('visibility', 'visible');
                },
                success: function(data) {
                    if (parent.next('.wcpp-data-tr').children().length == 0) {
                        $(data).insertAfter(parent);
                        parent.next('.wcpp-data-tr').slideDown(100);
                    } else {
                        parent.next().remove();
                        $(data).insertAfter(parent);
                        parent.next('.wcpp-data-tr').slideDown(100);
                    }
                    if (parent.next('.wcpp-data-tr').hasClass('show')) {
                        parent.next('.wcpp-data-tr').remove();
                    } else {
                        parent.next('.wcpp-data-tr').slideDown(100);
                    }
                    parent.toggleClass('active');
                    parent.next('.wcpp-data-tr').toggleClass('show');

                    parent.find('.wcpp-partial-order-action-loader').css('visibility', 'hidden');
                }
            })
        }
    }

    // Partial Payment history - open inside table
    function mepp_admin_get_partial_order_detail($this) {
        const parent = $this.parents('tr');
        const order_id = $this.attr('data-order-id');

        if (parent.next('.wcpp-order-detail-tr').hasClass('show')) {
            parent.next('.wcpp-order-detail-tr').remove();
            return;
        }

        $('.wcpp-partial-order-table tr.wcpp-order-detail-tr').each(function () {
            if ($(this).hasClass('show')) {
                $(this).removeClass('show');
                $(this).hide();
            }
        });

        if(order_id) {
            const title = `Partial Payment History (#${order_id})`
            $.ajax({
                url: wcpp_php_vars.ajaxurl,
                type: 'POST',
                dataType: 'html',
                data: {
                    order_id,
                    action: 'mepp_admin_get_partial_order_detail'
                },
                beforeSend: function() {
                    // $('#mepModal .modal-body').append(wcpp_loader_html);
                    parent.find('.wcpp-partial-order-action-loader').css('visibility', 'visible');
                },
                success: function(data) {
                    if (parent.next('.wcpp-order-detail-tr').children().length == 0) {
                        $(data).insertAfter(parent);
                        parent.next('.wcpp-order-detail-tr').slideDown(100);
                    }
                    if (parent.next('.wcpp-order-detail-tr').hasClass('show')) {
                        parent.next('.wcpp-order-detail-tr').remove();
                    } else {
                        parent.next('.wcpp-order-detail-tr').slideDown(100);
                    }
                    parent.next('.wcpp-order-detail-tr').toggleClass('show');

                    parent.find('.wcpp-partial-order-action-loader').css('visibility', 'hidden');
                }
            })
        }
    }

    // Partial Order filter
    function wcpp_partial_order_filter($this) {
        const filter_type = $this.attr('data-filter-type')
        const val = $this.val();
        $.ajax({
            url: wcpp_php_vars.ajaxurl,
            type: 'POST',
            dataType: 'html',
            data: {
                value: val,
                filter_type: filter_type,
                action: 'mepp_admin_partial_order_filter'
            },
            beforeSend: function() {
                $('.wcpp-filter-loader').show();
            },
            success: function(data) {
                $('.mepp-table-container').html(data);
                $('.wcpp-filter-loader').hide();
            }
        })
    }

    function myFunction() {
        alert("The context menu is about to be shown");
      }

    // Inherit function
    function wcpp_inherit_setting_control($this, value) {
        const isEvent = $('body').hasClass('post-type-mep_events');
        const isTour = $("body").hasClass("post-type-ttbm_tour");
        if(isEvent || isTour) { // Only event and tour
            const selectTr = $this.parents("tr");
            if(value === "yes") { // global setting
                selectTr.nextAll('tr').hide();
            } else { // local setting
                selectTr.nextAll('tr').show();
                $('#_mep_pp_deposits_type[name="_mep_pp_deposits_type"]').trigger('change');
            }
        } else { // others
            const target = $(".wcpp_local_setting");
            if (value === "yes") {
              target.hide();
            } else {
              target.show();
            }
        }
        
    }

    // Checkout mode changes
    function wcpp_checkout_mode_change(value) {
        const target = $("#mepp_partial_enable_for_cart_page");
        const targetTr = target.parents('tr');
        if(value === 'checkout') {
            targetTr.show();
        } else {
            targetTr.hide();
        }
    }

    // Init function call
    function wcpp_init(data) {
        const [checkout_mode] = data;

        if (checkout_mode) wcpp_checkout_mode_change(checkout_mode);
    }

})(jQuery);

// Other code using $ as an alias to the other library