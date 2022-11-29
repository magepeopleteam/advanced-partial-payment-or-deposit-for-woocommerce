<?php
if (!defined('ABSPATH')) {
    die;
}

add_action('admin_enqueue_scripts', 'mep_pp_add_admin_scripts', 10, 1);
if (!function_exists('mep_pp_add_admin_scripts')) {
    function mep_pp_add_admin_scripts($hook)
    {
        wp_enqueue_style('mep-pp-admin-style', plugin_dir_url(__DIR__) . 'asset/css/admin.css', array());
        wp_enqueue_script('mep--pp-admin-script', plugin_dir_url(__DIR__) . '/asset/js/admin.js', array(), time(), true);
        wp_localize_script('mep--pp-admin-script', 'wcpp_php_vars', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'WCPP_PLUGIN_URL' => WCPP_PLUGIN_URL,
        ));
    }
}
add_action('wp_enqueue_scripts', 'mep_pp_add_scripts', 10, 1);
if (!function_exists('mep_pp_add_scripts')) {
    function mep_pp_add_scripts($hook)
    {
        wp_enqueue_style('mep-pp-admin-style', plugin_dir_url(__DIR__) . 'asset/css/style.css', array());
        wp_enqueue_script('mep--pp-script', plugin_dir_url(__DIR__) . '/asset/js/public.js', array('jquery'), time(), true);
        wp_localize_script('mep--pp-script', 'wcpp_php_vars', array('ajaxurl' => admin_url('admin-ajax.php')));
    }
}

add_action('mep_pricing_table_head_after_price_col', 'mep_pp_price_col_head');
if (!function_exists('mep_pp_price_col_head')) {
    function mep_pp_price_col_head()
    {
?>
        <th><?php _e('Partial', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
    <?php
    }
}
add_action('mep_pricing_table_empty_after_price_col', 'mep_pp_price_empty_col');
if (!function_exists('mep_pp_price_empty_col')) {
    function mep_pp_price_empty_col()
    {
    ?>
        <td><input type="number" size="4" pattern="[0-9]*" class="mp_formControl" step="0.001" name="option_price_pp[]" placeholder="Ex: 10" value="" /></td>
    <?php
    }
}

add_action('mep_pricing_table_data_after_price_col', 'mep_pp_price_col_data', 10, 2);
if (!function_exists('mep_pp_price_col_data')) {
    function mep_pp_price_col_data($data, $event_id)
    {
    ?>
        <td><input type="number" size="4" pattern="[0-9]*" class="mp_formControl" step="0.001" name="option_price_pp[]" placeholder="Ex: 10" value="<?php echo esc_attr($data['option_price_pp']); ?>" /></td>
        <?php
    }
}

if (!function_exists('mep_product_exists')) {
    function mep_product_exists($id)
    {
        return is_string(get_post_status($id));
    }
}

add_filter('mep_ticket_type_arr_save', 'mep_pp_save_data', 99);
if (!function_exists('mep_pp_save_data')) {
    function mep_pp_save_data($data)
    {
        $spp = $_POST['option_price_pp'] ? mep_pp_sanitize_array($_POST['option_price_pp']) : [];
        if (sizeof($spp) > 0) {
            $count = count($spp);
            for ($i = 0; $i < $count; $i++) {
                $new[$i]['option_price_pp'] = !empty($spp[$i]) ? stripslashes(strip_tags($spp[$i])) : '';
            }
            $final_data = mep_merge_saved_array($data, $new);
        } else {
            $final_data = $data;
        }
        return $final_data;
    }
}

add_action('mep_ticket_type_list_row_end', 'mep_pp_ticket_type_list_data', 10, 2);
if (!function_exists('mep_pp_ticket_type_list_data')) {
    function mep_pp_ticket_type_list_data($field, $event_id)
    {
        $saldo_price = array_key_exists('option_price_pp', $field) && !empty($field['option_price_pp']) ? $field['option_price_pp'] : 0;
        $deposit_type = get_post_meta($event_id, '_mep_pp_deposits_type', true) ? get_post_meta($event_id, '_mep_pp_deposits_type', true) : 'percent';
        $deposit_status = get_post_meta($event_id, '_mep_enable_pp_deposit', true) ? get_post_meta($event_id, '_mep_enable_pp_deposit', true) : 'no';
        if ($deposit_status == 'yes' && array_key_exists('option_price_pp', $field) && $deposit_type == 'ticket_type') {
        ?>
            <td>
                <span class="tkt-pric">
                    <?php echo mepp_get_option('mepp_text_translation_string_deposit', __('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce')); ?>:
                </span> <strong><?php echo wc_price(mep_get_price_including_tax($event_id, $saldo_price)); ?></strong>
                <input type="hidden" name="option_price_pp[]" value="<?php echo esc_attr($saldo_price); ?>">
            </td>
        <?php
            add_filter('mep_hidden_row_colspan_no', 'mep_pp_modify_hudden_col_no');
        }
    }
}

if (!function_exists('mep_pp_modify_hudden_col_no')) {
    function mep_pp_modify_hudden_col_no($current)
    {
        $current = 4;
        return $current;
    }
}

if (!function_exists('meppp_pp_deposit_to_pay')) {
    /**
     *  Amount to pay for now html
     */
    function meppp_pp_deposit_to_pay()
    {

        // Loop over $cart items
        $total_pp_deposit = 0; // no value
        $cart_has_payment_plan = false; // Check cart has payment plan deposit system. init false
        $order_payment_plan = array();
        $has_deposit_type_minimum = false;
        $order_amount = 0;
        $due_amount = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {


            if (function_exists('mep_product_exists')) {
                $linked_event_id = get_post_meta($cart_item['product_id'], 'link_mep_event', true) ? get_post_meta($cart_item['product_id'], 'link_mep_event', true) : $cart_item['product_id'];
                $product_id = mep_product_exists($linked_event_id) ? $linked_event_id : $cart_item['product_id'];
            } else {
                $product_id = $cart_item['product_id'];
            }

            $total_pp_deposit += (meppp_is_product_type_pp_deposit($product_id) && isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit') ? $cart_item['_pp_deposit'] : null;
            $due_amount += (meppp_is_product_type_pp_deposit($product_id) && isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit') ? $cart_item['_pp_due_payment'] : null;

            if (isset($cart_item['_pp_deposit_system'])) {
                if ($cart_item['_pp_deposit_system'] === 'minimum_amount') {
                    $has_deposit_type_minimum = true;
                }

                if ($cart_item['_pp_deposit_system'] == 'payment_plan') {
                    // $cart_has_payment_plan = true;
                    $order_payment_plan = isset($cart_item['_pp_order_payment_terms']) ? $cart_item['_pp_order_payment_terms'] : array();
                }
            }

            $order_amount += $cart_item['line_subtotal'];
        }

        // $order_amount = WC()->cart->get_total('f') + $due_amount;

        $enable_checkout_zero_price = apply_filters('mepp_enable_zero_price_checkout', 'no');
        //        if (mepp_check_paypal_has() && !$has_deposit_type_minimum) {
        //        if (mepp_check_paypal_has()) {
        //            $value = $enable_checkout_zero_price == 'yes' ? WC()->cart->get_total('f') - $total_pp_deposit : WC()->cart->get_total('f');
        //        } else {
        //            $value = $enable_checkout_zero_price == 'yes' ? WC()->cart->get_total('f') - $total_pp_deposit : WC()->cart->get_total('f') - $total_pp_deposit;
        //        }

        $value = $enable_checkout_zero_price == 'yes' ? WC()->cart->get_total('f') - $total_pp_deposit : WC()->cart->get_total('f');

        echo apply_filters('mep_pp_deposit_top_pay_checkout_page_html', wc_price($value), $value, $order_amount, $order_payment_plan, $product_id); // WPCS: XSS ok.
    }
}

if (!function_exists('meppp_available_payment_methods')) {
    function meppp_available_payment_methods()
    {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $enabled_gateways = [];

        if ($gateways) {
            foreach ($gateways as $gateway) {

                if ($gateway->enabled == 'yes') {
                    $enabled_gateways[] = $gateway->id;
                }
            }
        }

        return $enabled_gateways;
    }
}

if (!function_exists('meppp_due_to_pay')) {
    /**
     *  Amount due html
     */
    function meppp_due_to_pay()
    {

        // Loop over $cart items
        $due_value = 0; // no value
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

            $linked_event_id = '';
            if (function_exists('mep_product_exists')) {

                if (get_post_meta($cart_item['product_id'], 'link_mep_event', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_mep_event', true);
                } elseif (get_post_meta($cart_item['product_id'], 'link_wbtm_bus', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_wbtm_bus', true);
                } elseif (get_post_meta($cart_item['product_id'], 'link_wc_product', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_wc_product', true);
                } elseif (get_post_meta($cart_item['product_id'], 'link_ttbm_id', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_ttbm_id', true);
                } else {
                    $product_id = $cart_item['product_id'];
                }

                $product_id = $linked_event_id && mep_product_exists($linked_event_id) ? $linked_event_id : $cart_item['product_id'];
            } else {
                $product_id = $cart_item['product_id'];
            }

            // if (function_exists('mep_product_exists')) {
            //     $linked_event_id = get_post_meta($cart_item['product_id'], 'link_mep_event', true) ? get_post_meta($cart_item['product_id'], 'link_mep_event', true) : $cart_item['product_id'];
            //     $product_id = mep_product_exists($linked_event_id) ? $linked_event_id : $cart_item['product_id'];
            // } else {
            //     $product_id = $cart_item['product_id'];
            // }

            $due_value += (meppp_is_product_type_pp_deposit($product_id) && isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit') ? $cart_item['_pp_due_payment'] : null;
        }
        if (WC()->session->get('dfwc_shipping_fee')) {
            $due_value += absint(WC()->session->get('dfwc_shipping_fee'));
        }

        echo '<input type="hidden" name="manually_due_amount" value="' . esc_attr($due_value) . '" />';
        echo apply_filters('woocommerce_pp_deposit_top_pay_html', wc_price($due_value)); // WPCS: XSS ok.
    }
}

if (!function_exists('meppp_display_to_pay_html')) {
    /**
     * Cart & checkout page hook for
     * display deposit table
     */
    function meppp_display_to_pay_html()
    {
        ?>
        <tr class="order-topay">
            <th><?php echo esc_html(meppp_get_option('mepp_text_translation_string_to_pay', 'To Pay')); ?></th>
            <td data-title="<?php echo esc_html(meppp_get_option('mepp_text_translation_string_to_pay', 'To Pay')); ?>"><?php meppp_pp_deposit_to_pay(); ?></td>
        </tr>
        <tr class="order-duepay">
            <th><?php echo mepp_get_option('mepp_text_translation_string_due_amount', __('Due Payment:', 'advanced-partial-payment-or-deposit-for-woocommerce')) ?></th>
            <td data-title="<?php echo mepp_get_option('mepp_text_translation_string_due_amount', __('Due Payment:', 'advanced-partial-payment-or-deposit-for-woocommerce')) ?>">
                <?php meppp_due_to_pay(); ?>
                <?php echo (WC()->session->get('dfwc_shipping_fee')) ? '<small>' . esc_html(apply_filters('dfwc_after_pp_due_payment_label', null)) . '</small>' : null; ?>
            </td>
        </tr>
        <?php
    }
}

if (!function_exists('meppp_cart_have_pp_deposit_item')) {

    function meppp_cart_have_pp_deposit_item()
    {
        $cart_item_pp_deposit = [];
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {

            $linked_event_id = '';
            if (function_exists('mep_product_exists')) {

                if (get_post_meta($cart_item['product_id'], 'link_mep_event', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_mep_event', true);
                } elseif (get_post_meta($cart_item['product_id'], 'link_wbtm_bus', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_wbtm_bus', true);
                } elseif (get_post_meta($cart_item['product_id'], 'link_wc_product', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_wc_product', true);
                } elseif (get_post_meta($cart_item['product_id'], 'link_ttbm_id', true)) {
                    $linked_event_id = get_post_meta($cart_item['product_id'], 'link_ttbm_id', true);
                } else {
                    $product_id = $cart_item['product_id'];
                }


                $product_id = $linked_event_id && mep_product_exists($linked_event_id) ? $linked_event_id : $cart_item['product_id'];
            } else {
                $product_id = $cart_item['product_id'];
            }
            // var_dump(isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit');
            // var_dump(wcpp_is_deposit_enabled($product_id));
            // var_dump(isset($cart_item['_pp_deposit_type']));
            $cart_item_pp_deposit[] = (wcpp_is_deposit_enabled($product_id)['is_enable'] && isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit') ? $cart_item['_pp_deposit_type'] : null;
        }

        if (!array_filter($cart_item_pp_deposit)) {
            return false;
        }
        return true;
    }
}

if (!function_exists('meppp_is_product_type_pp_deposit')) {
    /**
     * check for if product type is deposit/partial
     * @return boolen
     */
    function meppp_is_product_type_pp_deposit($product_id)
    {
        $exclude_global_setting = get_post_meta($product_id, '_mep_exclude_from_global_deposit', true) ?: 'no';
        $product_deposit_enable = get_post_meta($product_id, '_mep_enable_pp_deposit', true);

        $is_deposit = false;

        if ($exclude_global_setting === 'yes' && $product_deposit_enable === 'yes') {
            $is_deposit = true;
        } elseif ($exclude_global_setting === 'no' && apply_filters('global_product_type_pp_deposit', false)) {
            $is_deposit = true;
        } else {
            $is_deposit = false;
        }

        return $is_deposit;
    }
}

// Global Partial Enable/Disable
add_filter('global_product_type_pp_deposit', 'mep_global_product_type_pp_deposit_control', 10);
function mep_global_product_type_pp_deposit_control()
{
    $global_enable = get_option('mepp_enable_partial_by_default') ? get_option('mepp_enable_partial_by_default') : 'no';

    return $global_enable === 'yes' ? true : false;
}

/**
 * Get deposit Orders
 *
 * @param array $args
 * @return void
 */

if (!function_exists('mep_pp_get_orders')) {
    function mep_pp_get_orders($args = array())
    {
        $defaults = array(
            'numberposts' => '',
            'offset' => '',
            'meta_query' => array(
                array(
                    'key' => 'paying_pp_due_payment',
                    'value' => 1,
                    'compare' => '=',
                ),
            ),
            'meta_key' => 'deposit_value',
            'post_type' => wc_get_order_types('view-orders'),
            'post_status' => array_keys(wc_get_order_statuses()),
            'orderby' => 'date',
            'order' => 'DESC',

        );

        $args = wp_parse_args($args, $defaults);

        $items = array();
        foreach (get_posts($args) as $key => $order) {
            # code...
            $deposit_value = get_post_meta($order->ID, 'deposit_value', true);
            $due_payment = get_post_meta($order->ID, 'due_payment', true);
            $total_value = get_post_meta($order->ID, 'total_value', true);
            // fw_print($order);
            $items[$key]['name'] = $order->ID;
            $items[$key]['date'] = $order->post_date;
            $items[$key]['status'] = $order->post_status;
            $items[$key]['deposit'] = apply_filters('woocommerce_pp_deposit_top_pay_html', wc_price($deposit_value));
            $items[$key]['due'] = ($due_payment > 0) ? apply_filters('woocommerce_pp_deposit_top_pay_html', wc_price($due_payment)) : '-';
            $items[$key]['total'] = apply_filters('woocommerce_pp_deposit_top_pay_html', wc_price($total_value));
        }

        return $items;
    }
}
/**
 * count Deposit orders
 * @return int
 */
if (!function_exists('mep_pp_count')) {
    function mep_pp_count()
    {
        $defaults = array(
            'numberposts' => -1,
            'meta_key' => 'paying_pp_due_payment',
            'meta_value' => '1',
            'post_type' => wc_get_order_types('view-orders'),
            'post_status' => array_keys(wc_get_order_statuses()),

        );
        $items = get_posts($defaults);
        return count($items);
    }
}

if (!function_exists('meppp_get_option')) {
    function meppp_get_option($option, $default = '', $section = 'deposits_settings')
    {
        $txt = get_option($option);

        if ($txt) {
            return $txt;
        }

        return $default;
    }
}

add_action('woocommerce_before_add_to_cart_button', 'mep_pp_show_payment_option', 10, 2);
add_action('mep_before_add_cart_btn', 'mep_pp_show_payment_option', 10, 2);
add_action('wbbm_before_add_cart_btn', 'mep_pp_show_payment_option', 10, 2);
add_action('wbtm_before_add_cart_btn', 'mep_pp_show_payment_option', 10, 2);
add_action('ttbm_before_add_cart_btn', 'mep_pp_show_payment_option', 10, 2);
if (!function_exists('mep_pp_show_payment_option')) {
    function mep_pp_show_payment_option($product_id, $check_link_id = true)
    {
        // Check user and role
        if (apply_filters('mepp_user_role_allow', 'go') === 'stop') {
            return false;
        }

        $product_id = $product_id ? $product_id : get_the_id();

        if (function_exists('mep_product_exists') && $check_link_id) {
            if (get_post_meta($product_id, 'link_mep_event', true)) {
                $linked_event_id = get_post_meta($product_id, 'link_mep_event', true);
            } elseif (get_post_meta($product_id, 'link_wbtm_bus', true)) {
                $linked_event_id = get_post_meta($product_id, 'link_wbtm_bus', true);
            } elseif (get_post_meta($product_id, 'link_wc_product', true)) {
                $linked_event_id = get_post_meta($product_id, 'link_wc_product', true);
            } elseif (get_post_meta($product_id, 'link_ttbm_id', true)) {
                $linked_event_id = get_post_meta($product_id, 'link_ttbm_id', true);
            } else {
                $linked_event_id = null;
            }
            if ($linked_event_id) {
                if (!wcppe_enable_for_event()) {
                    return null;
                }

                $product_id = mep_product_exists($linked_event_id) ? $linked_event_id : $product_id;
            }
        }

        if (get_post_type($product_id) !== 'product') {
            if (wcppe_enable_for_event()) {
                mep_pp_show_payment_option_html($product_id);
            }
        } else {
            mep_pp_show_payment_option_html($product_id);
        }

        // wcpp_test();
    }
}

if (!function_exists('mep_pp_show_payment_option_html')) {
    function mep_pp_show_payment_option_html($event_id)
    {
        $isForcePartialPayment = apply_filters('mepp_force_partial_payment', 'no');

        if (meppp_is_product_type_pp_deposit($event_id)) {
            $_pp_deposit_value = get_post_meta($event_id, '_mep_pp_deposits_value', true) ? get_post_meta($event_id, '_mep_pp_deposits_value', true) : 0;

            $product_price = 0;
            $product_type = get_post_type($event_id);
            if ($product_type === 'mep_events' || $product_type === 'wbtm_bus' || $product_type === 'wbbm_bus' || $product_type === 'ttbm_tour') {
                // For Event product
            } else {
                $woo_product = wc_get_product($event_id);
                if ($woo_product->is_type('variable')) {
                    $product_price = $woo_product->get_variation_price('max');
                } else {
                    $product_price = $woo_product->get_price();
                }
            }
            // ticket_type
            // get payment plan id of this event
            $global_deposit_type = get_option('mepp_default_partial_type') ? get_option('mepp_default_partial_type') : 'percent';
            $global_deposit_value = get_option('mepp_default_partial_amount') ? get_option('mepp_default_partial_amount') : '';

            $is_exclude_from_global = get_post_meta($event_id, '_mep_exclude_from_global_deposit', true);
            $is_deposit_enable = get_post_meta($event_id, '_mep_enable_pp_deposit', true);
            
            $is_deposit_enabled_for_this_product = wcpp_is_deposit_enabled($event_id); // get array
            if(!$is_deposit_enabled_for_this_product['is_enable']) return 0; // Deposit is not enabled

            // Deposit Enabled

            if ($is_deposit_enabled_for_this_product['setting_level'] === 'local') { // From Product setting
                $_pp_deposit_value = get_post_meta($event_id, '_mep_pp_deposits_value', true) ? get_post_meta($event_id, '_mep_pp_deposits_value', true) : 0;
                $deposit_type = get_post_meta($event_id, '_mep_pp_deposits_type', true) ? get_post_meta($event_id, '_mep_pp_deposits_type', true) : '';
                $_pp_minimum_value = get_post_meta($event_id, '_mep_pp_minimum_value', true) ? get_post_meta($event_id, '_mep_pp_minimum_value', true) : 0;
                $_pp_payment_plan_ids = get_post_meta($event_id, '_mep_pp_payment_plan', true);
                $_pp_payment_plan_ids = $_pp_payment_plan_ids ? maybe_unserialize($_pp_payment_plan_ids) : array();
            } else { // From global setting
                $_pp_deposit_value = $_pp_minimum_value = $global_deposit_value;
                $deposit_type = $global_deposit_type;
                if ($deposit_type === 'minimum_amount') {
                    if (!$_pp_deposit_value) {
                        return 0;
                    }
                }
                $_pp_payment_plan_ids = get_option('mepp_default_payment_plan') ? maybe_unserialize(get_option('mepp_default_payment_plan')) : [];

                $default_partial_for_page = apply_filters('mepp_partial_option_for_page', 'product_detail');
                if ($default_partial_for_page === 'checkout') {
                    echo '<input type="hidden" name="deposit-mode" value="checkout" />';
                    return 0;
                }
            }

            // Exception handle for deposit type
            if (!wcppe_enable_for_event()) {
                if ($deposit_type === 'minimum_amount' || $deposit_type === 'payment_plan') {
                    update_option('mepp_default_partial_type', 'percent');
                    update_post_meta($event_id, '_mep_pp_deposits_type', 'percent');
                    $deposit_type = 'percent';
                }
            }

            if ($deposit_type === 'percent' || $deposit_type === 'fixed') {
                if (!$_pp_deposit_value) {
                    return 0;
                }
            }
            if ($deposit_type === 'minimum_amount') {
                if (!$_pp_minimum_value) {
                    return 0;
                }
            }

        ?>
            <div class="mep-pp-payment-btn-wraper">
                <input type="hidden" name='currency_symbol' value="<?php echo get_woocommerce_currency_symbol(); ?>">
                <input type="hidden" name='currency_position' value="<?php echo get_option('woocommerce_currency_pos'); ?>">
                <input type="hidden" name='currency_decimal' value="<?php echo wc_get_price_decimal_separator(); ?>">
                <input type="hidden" name='currency_thousands_separator' value="<?php echo wc_get_price_thousand_separator(); ?>">
                <input type="hidden" name='currency_number_of_decimal' value="<?php echo wc_get_price_decimals(); ?>">
                <input type="hidden" name="payment_plan" value="<?php echo esc_attr($deposit_type); ?>" data-percent="<?php echo esc_attr($_pp_deposit_value); ?>">
                <?php if (apply_filters('mep_pp_frontend_cart_radio_input', true)) { ?>
                    <ul class="mep-pp-payment-terms">
                        <li>
                            <label for="mep_pp_partial_payment">
                                <input type="radio" id='mep_pp_partial_payment' name="deposit-mode" value="check_pp_deposit" <?php if (meppp_is_product_type_pp_deposit($event_id)) {
                                                                                                                                    echo 'Checked';
                                                                                                                                } ?> />
                                <?php echo mepp_get_option('mepp_text_translation_string_pay_deposit', __('Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce')); ?>
                                <?php if ($deposit_type == 'manual') { ?>
                                    <input type="number" class="mep-pp-user-amountinput" data-deposit-type="<?php echo $deposit_type; ?>" name="user-deposit-amount" value="<?php echo esc_attr($_pp_deposit_value); ?>" min="<?php echo esc_attr($_pp_deposit_value); ?>" max="">
                                <?php
                                } elseif ($deposit_type == 'ticket_type') {
                                ?>
                                    <span id='mep_pp_ticket_type_partial_total'></span>
                                    <input type="hidden" class="mep-pp-user-amountinput" data-deposit-type="<?php echo $deposit_type; ?>" name="user-deposit-amount" value="">
                                <?php
                                } elseif ($deposit_type == 'minimum_amount') { ?>
                                    <input type="text" class="mep-pp-user-amountinput" data-deposit-type="<?php echo $deposit_type; ?>" name="user-deposit-amount" value="<?php echo esc_attr($_pp_minimum_value); ?>" min="<?php echo esc_attr($_pp_minimum_value); ?>">
                                <?php
                                } elseif ($deposit_type == 'percent') {
                                    echo esc_attr($_pp_deposit_value) . '%';
                                } elseif ($deposit_type == 'fixed') {
                                    echo wc_price(esc_attr($_pp_deposit_value));
                                    _e(' Only', 'advanced-partial-payment-or-deposit-for-woocommerce');
                                } else {
                                    echo '';
                                }
                                ?>
                            </label>
                        </li>
                        <?php if ($isForcePartialPayment != 'yes') : ?>
                            <li>
                                <label for='mep_pp_full_payment'>
                                    <input type="radio" id='mep_pp_full_payment' name="deposit-mode" value="no-deposit" />
                                    <?php echo mepp_get_option('mepp_text_translation_string_full_payment', __('Full Payment', 'advanced-partial-payment-or-deposit-for-woocommerce')); ?>
                                </label>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <?php ($deposit_type == 'payment_plan' || $deposit_type == 'percent' || $deposit_type == 'minimum_amount') ? do_action('mep_payment_plan_list', $_pp_payment_plan_ids, $event_id, $deposit_type) : null; ?>
                <?php } else {
                    echo '<input type="hidden" name="deposit-mode" value="check_pp_deposit">';
                } ?>
            </div>
        <?php
        } else {
            echo '<input type="hidden" name="deposit-mode" value="no-deposit">';
        }
    }
}

// add_filter('mep_event_cart_item_data', 'mep_pp_event_cart_item_data', 90, 6);
if (!function_exists('mep_pp_event_cart_item_data')) {
    function mep_pp_event_cart_item_data($cart_item_data, $product_id, $total_price, $user, $ticket_type_arr, $event_extra)
    {
        if (meppp_is_product_type_pp_deposit($product_id)) {
            $deposit_type = get_post_meta($product_id, '_mep_pp_deposits_type', true) ? get_post_meta($product_id, '_mep_pp_deposits_type', true) : 'percent';
            $_pp_deposit_value = get_post_meta($product_id, '_mep_pp_deposits_value', true) ? get_post_meta($product_id, '_mep_pp_deposits_value', true) : 0;

            if ($deposit_type == 'percent') {
                $deposit_value = ($_pp_deposit_value / 100) * $total_price;
            } elseif ($deposit_type == 'manual' || $deposit_type == 'ticket_type') {
                $deposit_value = isset($_POST['user-deposit-amount']) && !empty($_POST['user-deposit-amount']) ? sanitize_text_field($_POST['user-deposit-amount']) : 0;
            } else {
                $deposit_value = $_pp_deposit_value;
            }

            $cart_item_data['_pp_deposit'] = $deposit_value;
            $cart_item_data['_pp_due_payment'] = $total_price - $deposit_value;
            $cart_item_data['_pp_deposit_type'] = sanitize_text_field($_POST['deposit-mode']);
        }
        return $cart_item_data;
    }
}

add_filter('woocommerce_add_to_cart_validation', 'mep_pp_validate_frontend_input', 50, 3);
if (!function_exists('mep_pp_validate_frontend_input')) {
    function mep_pp_validate_frontend_input($passed, $product_id, $quantity)
    {

        $linked_event_id = get_post_meta($product_id, 'link_mep_event', true) ? get_post_meta($product_id, 'link_mep_event', true) : $product_id;
        if (function_exists('mep_product_exists')) {
            $product_id = mep_product_exists($linked_event_id) ? $linked_event_id : $product_id;
        }

        $is_allow_regular_partial_product_in_cart = mepp_get_option('meppp_allow_regular_and_deposit_product_in_cart', 'yes');
        if ($is_allow_regular_partial_product_in_cart === 'no') {
            $passed = apply_filters('woocommerce_add_to_cart_validation_additional', $passed, $product_id);
        }

        if (meppp_is_product_type_pp_deposit($product_id) == false) {
            return $passed;
        }

        return $passed;
    }
}

add_filter('woocommerce_add_to_cart_validation_additional', 'mep_pp_woocommerce_add_to_cart_validation_additional_callback', 10, 2);
if (!function_exists('mep_pp_woocommerce_add_to_cart_validation_additional_callback')) {
    function mep_pp_woocommerce_add_to_cart_validation_additional_callback($passed, $product_id)
    {

        if (!WC()->cart->is_empty() && meppp_cart_have_pp_deposit_item() && meppp_is_product_type_pp_deposit($product_id) == false && apply_filters('deposits_mode', true)) {
            $passed = false;
            wc_add_notice(__('We detected that your cart has Deposit products. Please remove them before being able to add this product.', 'advanced-partial-payment-or-deposit-for-woocommerce'), 'error');
            return $passed;
        }

        if (!WC()->cart->is_empty() && meppp_cart_have_pp_deposit_item() == false && meppp_is_product_type_pp_deposit($product_id) && apply_filters('deposits_mode', true)) {
            $passed = false;
            wc_add_notice(__('We detected that your cart has Regular products. Please remove them before being able to add this product.', 'advanced-partial-payment-or-deposit-for-woocommerce'), 'error');
            return $passed;
        }

        return $passed;
    }
}

add_filter('mep_event_attendee_dynamic_data', 'mep_pp_event_pp_deposit_data_save', 90, 6);
if (!function_exists('mep_pp_event_pp_deposit_data_save')) {
    function mep_pp_event_pp_deposit_data_save($the_array, $pid, $type, $order_id, $event_id, $_user_info)
    {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $item_id => $item_values) {
            $item_id = $item_id;
        }
        $time_slot = wc_get_order_item_meta($item_id, '_DueAmount', true) ? wc_get_order_item_meta($item_id, '_DueAmount', true) : 0;

        if ($time_slot > 0) {
            $the_array[] = array(
                'name' => 'ea_partial_status',
                'value' => 'partial_payment',
            );
        }
        return $the_array;
    }
}

add_filter('mep_sold_meta_query_or_attribute', 'mep_pp_partial_meta_query');
if (!function_exists('mep_pp_partial_meta_query')) {
    function mep_pp_partial_meta_query($current_query)
    {
        $partial_meta_condition = array(
            'key' => 'ea_partial_status',
            'value' => 'partial_payment',
            'compare' => '=',
        );
        return array_merge($current_query, $partial_meta_condition);
    }
}

// Add per payment to post type 'mep_pp_history'
if (!function_exists('mep_pp_history_add')) {
    function mep_pp_history_add($order_id, $data, $parent_id)
    {

        $order = wc_get_order($order_id);
        $order_status = $order->get_status();

        if ($order_status == 'partially-paid' || $order_status == 'pending') {
            $postdata = array(
                'post_type' => 'mep_pp_history',
                'post_status' => 'publish',
            );
            $post = wp_insert_post($postdata);
            if ($post) {
                update_post_meta($post, 'order_id', $order_id);
                update_post_meta($post, 'parent_order_id', $parent_id);
                update_post_meta($post, 'deposite_amount', $data['deposite_amount']);
                update_post_meta($post, 'due_amount', $data['due_amount']);
                update_post_meta($post, 'payment_date', $data['payment_date']);
                update_post_meta($post, 'payment_method', $data['payment_method']);
            }
        }
    }
}

if (!function_exists('mep_pp_get_order_due_amount')) {
    function mep_pp_get_order_due_amount($order_id)
    {
        $args = array(
            'post_type' => 'mep_pp_history',
            'posts_per_page' => 1,
            'orderby' => 'date',
            // 'order'             => 'asc',
            'meta_query' => array(
                array(
                    'key' => 'order_id',
                    'value' => $order_id,
                    'compare' => '=',
                ),
            ),
        );
        $loop = new WP_Query($args);
        $payment_id = 0;
        foreach ($loop->posts as $value) {
            # code...
            $payment_id = $value->ID;
        }

        $due = $payment_id > 0 && get_post_meta($payment_id, 'due_amount', true) ? get_post_meta($payment_id, 'due_amount', true) : 0;
        return $due;
    }
}

// Get history by order_id
if (!function_exists('mep_pp_history_get')) {
    function mep_pp_history_get($order_id, $title = true, $payable = true)
    {
        ob_start();

        $due_payment = get_post_meta($order_id, 'due_payment', true);

        $pp_deposit_system = get_post_meta($order_id, '_pp_deposit_system', true);
        $permition_for_next_payment = get_post_meta($order_id, 'zero_price_checkout_allow', true); // Only for Zero price Checkout Order

        $args = array(
            'post_type' => 'mep_pp_history',
            'posts_per_page' => -1,
            'order' => 'asc',
            'orderby' => 'ID',
            'meta_query' => array(
                array(
                    'key' => 'parent_order_id',
                    'value' => $order_id,
                    'compare' => '=',
                ),
            ),
        );

        $pp_history = new WP_Query($args);
        $count = $pp_history->post_count;

        $payment_term_pay_now_appear = true; // Only For Payment Terms

        if ($count > 0) :
        ?>

            <?php echo ($title ? '<h2 class="woocommerce-column__title">' . __("Payment history", "advanced-partial-payment-or-deposit-for-woocommerce") . '</h2>' : null); ?>
            <table class="mepp-table mep-pp-history-table woocommerce-table" style="width:100%;">
                <thead>
                    <tr>
                        <th><?php esc_attr_e('Sl.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                        <th><?php esc_attr_e('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                        <th><?php esc_attr_e('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                        <th><?php esc_attr_e('Due', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                        <th><?php esc_attr_e('Payment Method', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                        <th><?php esc_attr_e('Status', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    $x = 1;
                    while ($pp_history->have_posts()) :
                        $pp_history->the_post();
                        $id = get_the_ID();
                        //                        $order_id           = esc_attr(get_post_meta($id, 'order_id', true));
                        $this_order = wc_get_order($order_id);
                        $amount = esc_attr(get_post_meta($id, 'deposite_amount', true));
                        $due = (float) get_post_meta($id, 'due_amount', true);
                        $date = esc_attr(get_post_meta($id, 'payment_date', true));
                        $payment_method = esc_attr(get_post_meta($id, 'payment_method', true));

                        $pay_button = '';
                        $status = '';
                        if ($pp_deposit_system == 'zero_price_checkout' && $permition_for_next_payment == 'yes' && $due_payment != 0) { // Only For zero price checkout and permmited for payment and Amount Due
                            $pay_button = sprintf('<a href="%s" class="mep_due_pay_btn">%s</a>', $this_order->get_checkout_payment_url(), __('Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce'));
                        } elseif ($pp_deposit_system == 'zero_price_checkout' && $permition_for_next_payment == 'yes' && $due_payment == 0) { // Only For zero price checkout and permmited for payment and Amount all paid
                            $status = 'Fully Paid';
                        } elseif ($pp_deposit_system == 'zero_price_checkout' && $permition_for_next_payment == 'no' && $payment_method == '') { // Only For zero price checkout and Not permmited for payment
                            // $pay_button = '';
                            //
                        } elseif ($pp_deposit_system != 'zero_price_checkout' && $due_payment == 0) { // Not zero price checkout
                            $status = $x == $count ? __('Fully Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') : __('Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce');
                        } elseif ($pp_deposit_system != 'zero_price_checkout' && $due_payment > 0) { // Not zero price checkout
                            $status = $payment_method ? __('Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') : '';
                            if ($pp_deposit_system == 'payment_plan') {

                                if (!$payment_method && $payment_term_pay_now_appear) {
                                    $pay_button = sprintf('<a href="%s" class="mep_due_pay_btn">%s</a>', $this_order->get_checkout_payment_url(), __('Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce'));
                                    $due = $due == 0 ? $due_payment : $due;
                                    $payment_term_pay_now_appear = false;
                                }
                            } else {
                                $pay_button = ($due > 0 && $x == $count) ? sprintf('<a href="%s" class="mep_due_pay_btn">%s</a>', $this_order->get_checkout_payment_url(), __('Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce')) : '';
                            }
                        }

                        if (!$payable) {
                            $pay_button = '';
                        }

                        echo '<tr>';
                        echo '<td>' . (esc_attr($x)) . '</td>';
                        echo '<td>' . date(get_option('date_format'), strtotime($date)) . '</td>';
                        echo '<td class="mep_style_ta_r">' . wc_price($amount) . '</td>';
                        echo '<td class="mep_style_ta_r ' . (($due > 0 && $x == $count) ? "mep_current_last_due" : null) . '">' . wc_price(esc_html($due)) . $pay_button . '</td>';
                        echo '<td class="mep_style_tt_upper">' . esc_html($payment_method) . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '</tr>';
                        $x++;
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </tbody>
            </table>

        <?php

        endif;

        return ob_get_clean();
    }
}

// Get Order detail by Order ID
function mep_pp_partial_order_detail_get($order_id, $isTitle)
{
    if ($order_id) {
        $order = wc_get_order($order_id);

        ob_start();
        ?>

        <?php echo ($isTitle ? '<h2 class="woocommerce-column__title">' . __("Order Detail", "advanced-partial-payment-or-deposit-for-woocommerce") . '</h2>' : null); ?>
        <table class="mepp-table mep-pp-order-detail-table woocommerce-table" style="width:100%;text-align:left">
            <thead>
                <tr>
                    <th><?php esc_attr_e('Sl.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th><?php esc_attr_e('Image', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th><?php esc_attr_e('Product Name', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th><?php esc_attr_e('Unit Price', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th><?php esc_attr_e('Quantity', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th><?php esc_attr_e('Total', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                </tr>
            </thead>
            <tbody>

                <?php
                $x = 1;
                foreach ($order->get_items() as $item) :
                    $product = $item->get_product();

                    echo '<tr>';
                    echo '<td>' . (esc_attr($x)) . '</td>';
                    echo '<td><img class="wcpp-img-sm" src="' . wp_get_attachment_url($product->get_image_id()) . '" alt="Product Image"></td>';
                    echo '<td>' . $item->get_name() . '</td>';
                    echo '<td>' . wc_price($product->get_price()) . '</td>';
                    echo '<td>' . $item->get_quantity() . '</td>';
                    echo '<td>' . wc_price($product->get_price() * $item->get_quantity()) . '</td>';
                    echo '</tr>';
                    $x++;
                endforeach;
                ?>
            </tbody>
        </table>

    <?php

        return ob_get_clean();
    }
}

// Overwrite woocommerce template [form-pay.php]
add_filter('woocommerce_locate_template', 'mep_pp_template', 1, 3);
if (!function_exists('mep_pp_template')) {
    function mep_pp_template($template, $template_name, $template_path)
    {
        global $woocommerce;
        $_template = $template;
        if (!$template_path) {
            $template_path = $woocommerce->template_url;
        }

        $plugin_path = untrailingslashit(plugin_dir_path(__FILE__)) . '/templates/woocommerce/';

        // Look within passed path within the theme - this is priority
        $template = locate_template(
            array(
                $template_path . $template_name,
                $template_name,
            )
        );

        if (!$template && file_exists($plugin_path . $template_name)) {
            $template = $plugin_path . $template_name;
        }

        if (!$template) {
            $template = $_template;
        }

        return $template;
    }
}

// Mepp Date
function mepp_date($date)
{
    $wp_format = get_option('date_format');
    if ($date) {
        $date = date($wp_format, strtotime($date));
    }
    return $date;
}

// Get Deposit Type Display name
if (!function_exists('mep_pp_deposti_type_display_name')) {
    function mep_pp_deposti_type_display_name($deposit_type, $cart_item, $with_value = false)
    {
        $name = '';
        if ($deposit_type) {
            switch ($deposit_type) {
                case 'percent':
                    $name = 'Percent';
                    $name = $with_value ? $cart_item['_pp_deposit_value'] . ' ' . $name : $name;
                    break;
                case 'fixed':
                    $name = 'Fixed';
                    $name = $with_value ? wc_price($cart_item['_pp_deposit_value']) . ' ' . $name : $name;
                    break;
                case 'minimum_amount':
                    $name = 'Minimum Amount';
                    // $name = $with_value ? wc_price($cart_item['_pp_deposit_value_strict']) . ' ' . $name : $name;
                    break;
                case 'payment_plan':
                    $name = 'Payment Plan';
                    $name = $with_value ? $cart_item['_pp_deposit_payment_plan_name'] . ' ' . $name : $name;
                    break;
                case 'zero_price_checkout':
                    $name = 'Checkout with Zero Price';
                    $name = $with_value ? $cart_item['_pp_deposit_payment_plan_name'] . ' ' . $name : $name;
                    break;
                default:
            }
        }

        return $name;
    }
}

/*
 * Get next payment order id
 * @param Parent Order Id
 * @return next payable order id
 * */
if (!function_exists('mep_get_next_payment_order_id')) {
    function mep_get_next_payment_order_id($order_id)
    {
        if (!$order_id)
            return 0;

        $payment_plan_args = array(
            'post_type' => 'wcpp_payment',
            'post_parent' => $order_id,
            'post_status' => 'wc-pending',
            'orderby' => 'ID',
            'order' => 'asc'
        );

        $payment_plan_data = new WP_Query($payment_plan_args);

        return $payment_plan_data->posts[0] ? $payment_plan_data->posts[0]->ID : null;
    }
}

if (!function_exists('mep_get_enable_payment_gateway')) {
    function mep_get_enable_payment_gateway()
    {
        $gateways = WC()->payment_gateways->get_available_payment_gateways();
        $enabled_gateways = [];
        if ($gateways) {
            foreach ($gateways as $gateway) {
                if ($gateway->enabled == 'yes') {
                    $enabled_gateways[] = $gateway;
                }
            }
        }

        return $enabled_gateways;
    }
}

// Get payment term name by id
if (!function_exists('mep_pp_payment_plan_name')) {
    function mep_pp_payment_plan_name($plan_id)
    {
        $name = '';
        if ($plan_id) {
            $name = '<strong>' . get_term($plan_id)->name . '</strong>';
        }
        return $name;
    }
}

if (!function_exists('mepp_get_option')) {
    function mepp_get_option($meta_key, $default = null)
    {
        return get_option($meta_key) ? get_option($meta_key) : esc_html__($default);
    }
}

// Payment plan in product page
add_action('mep_payment_plan_list', 'mep_payment_plan_list_callback', 10, 3);
if (!function_exists('mep_payment_plan_list_callback')) {
    function mep_payment_plan_list_callback($plan_ids, $product_id, $deposit_type)
    {
        if (mep_is_zero_price_checkout_allow() == 'yes') {
            return;
        }

        $product_type = get_post_type($product_id);
        $total_price = 0;
        $variations_price = array();
        if ($product_type != 'mep_events' && $product_type != 'wbbm_bus' && $product_type != 'wbtm_bus' && $product_type != 'ttbm_tour') {
            $product = wc_get_product($product_id);
            $total_price = wc_get_price_including_tax($product);

            if ($product->is_type('variable')) {
                // Product has variations
                $variations = $product->get_available_variations();
                if ($variations) {
                    foreach ($variations as $variation) {
                        if ($variation['variation_is_active']) {
                            $variations_price[] = array(
                                'variation_id' => $variation['variation_id'],
                                'price' => $variation['display_price'],
                                'regular_price' => $variation['display_regular_price'],
                            );
                        }
                    }
                }

                $total_price = wc_get_price_including_tax($product);
            } else {
                $total_price = wc_get_price_including_tax($product);
            }
        }
        $_pp_deposit_value = get_post_meta($product_id, '_mep_pp_deposits_value', true) ? get_post_meta($product_id, '_mep_pp_deposits_value', true) : 0;
        ob_start();
    ?>
        <div class="mep-product-payment-plans" data-total-price="<?php echo esc_attr($total_price); ?>">
            <div class="mep-single-plan-wrap">
                <input type="hidden" name="payment_plan" value="<?php echo esc_attr($deposit_type); ?>" data-percent="<?php echo esc_attr($_pp_deposit_value); ?>">
                <?php if ($plan_ids && $deposit_type == 'payment_plan') :
                    $i = 0;
                    foreach ($plan_ids as $plan) :
                        $data = get_term_meta($plan);
                ?>
                        <div>
                            <label>
                                <input type="radio" name="mep_payment_plan" <?php echo esc_html($i) == 0 ? "checked" : ""; ?> value="<?php echo esc_attr($plan); ?>" />
                                <?php echo get_term($plan)->name; ?>

                            </label>
                            <span class="mep-pp-show-detail"><?php esc_attr_e('View Details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                            <?php mep_payment_plan_detail($data, $total_price); ?>
                        </div>
                    <?php
                        $i++;
                    endforeach;
                elseif ($deposit_type == 'percent') :
                    ?><p>
                        <strong><?php esc_attr_e('Deposit Amount :', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                            <span class="payment_amount"></span></strong>
                    </p>
                <?php
                endif;
                ?>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }
}

// Show payment plan detail
if (!function_exists('mep_payment_plan_detail')) {
    function mep_payment_plan_detail($data, $total)
    {
        if (mep_is_zero_price_checkout_allow() == 'yes') {
            return;
        }

        if ($data) {
            // $plan_schedule = maybe_unserialize(maybe_unserialize($data['mepp_plan_schedule'][0]));
            $down_payment = (float) $data['mepp_plan_schedule_initial_pay_parcent'][0];
            $payment_schdule = maybe_unserialize((maybe_unserialize($data['mepp_plan_schedule'][0])));
            ob_start();
            $percent = 0;
            if ($payment_schdule) {
                foreach ($payment_schdule as $payments) {
                    $percent = $percent + $payments['plan_schedule_parcent'];
                }
            }
        ?>
            <div class="mep-single-plan plan-details">
                <div>
                    <p><?php esc_attr_e('Payments Total', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                        <strong class="total_pp_price" data-init-total="<?php echo esc_attr($total); ?>" data-total-percent="<?php echo esc_attr($percent) + esc_attr($down_payment); ?>"></strong>
                    </p>
                </div>
                <div>
                    <p><?php esc_attr_e('Pay Deposit:', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                        <strong class="total_deposit" data-deposit="<?php echo esc_attr($down_payment); ?>"></strong>
                    </p>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><?php esc_attr_e('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                            <th><?php esc_attr_e('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payment_schdule) :
                            $date = date('Y-m-d');
                            foreach ($payment_schdule as $plan) :
                                $date = mep_payment_plan_date($plan["plan_schedule_date_after"], $plan["plan_schedule_parcent_date_type"], $date);
                        ?>
                                <tr>
                                    <td><?php echo date(get_option('date_format'), strtotime($date)); ?></td>
                                    <td data-payment-plan="<?php echo esc_attr($plan["plan_schedule_parcent"]); ?>"></td>
                                </tr>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
    <?php

            echo ob_get_clean();
        } else {
            return null;
        }
    }
}

// Get percentage value
if (!function_exists('mep_percentage_value')) {
    function mep_percentage_value($percent_amount, $total_amount)
    {
        return ($total_amount * $percent_amount) / 100;
    }
}
// Get payment date
if (!function_exists('mep_payment_plan_date')) {
    function mep_payment_plan_date($date_after, $date_type, $date)
    {
        $date = date('Y-m-d', strtotime(sprintf('+%d %s', $date_after, $date_type), strtotime($date)));
        return $date;
    }
}

if (!function_exists('mep_pp_sanitize_array')) {
    function mep_pp_sanitize_array($array_or_string)
    {
        if (is_string($array_or_string)) {
            $array_or_string = sanitize_text_field($array_or_string);
        } elseif (is_array($array_or_string)) {
            foreach ($array_or_string as $key => &$value) {
                if (is_array($value)) {
                    $value = mep_pp_sanitize_array($value);
                } else {
                    $value = sanitize_text_field($value);
                }
            }
        }
        return $array_or_string;
    }
}

// Get Zero price checkout allow from setting
function mep_is_zero_price_checkout_allow()
{
    $res = apply_filters('mepp_enable_zero_price_checkout', 'no');

    return $res;
}

add_filter('woocommerce_my_account_my_orders_actions', 'mep_conditional_pay_button_my_orders_actions', 10, 2);
function mep_conditional_pay_button_my_orders_actions($actions, $order)
{

    $order_id = $order->get_id();
    $is_payable = get_post_meta($order_id, 'zero_price_checkout_allow', true);
    if ($is_payable == 'no') {
        unset($actions['pay']);
    }
    return $actions;
}

// Check if partial payment enable addon for event is activated
function wcppe_enable_for_event()
{
    return is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php');
}

// Check if Mage Bus plugins active
function wcppe_is_mage_bus_active()
{
    return (is_plugin_active('bus-ticket-booking-with-seat-reservation/woocommerce-bus.php') || is_plugin_active('bus-booking-manager/woocommerce-bus.php') ? true : false);
}

// Escape Html
if (!function_exists('mep_esc_html')) {
    function mep_esc_html($string)
    {
        $allow_attr = array(
            'input' => array(
                'br' => [],
                'type' => [],
                'class' => [],
                'id' => [],
                'name' => [],
                'value' => [],
                'size' => [],
                'placeholder' => [],
                'min' => [],
                'max' => [],
                'checked' => [],
                'required' => [],
                'disabled' => [],
                'readonly' => [],
                'step' => [],
                'data-default-color' => [],
            ),
            'p' => [
                'class' => [],
            ],
            'img' => [
                'class' => [],
                'id' => [],
                'src' => [],
                'alt' => [],
            ],
            'fieldset' => [
                'class' => [],
            ],
            'label' => [
                'for' => [],
                'class' => [],
            ],
            'select' => [
                'class' => [],
                'name' => [],
                'id' => [],
            ],
            'option' => [
                'class' => [],
                'value' => [],
                'id' => [],
                'selected' => [],
            ],
            'textarea' => [
                'class' => [],
                'rows' => [],
                'id' => [],
                'cols' => [],
                'name' => [],
            ],
            'h2' => ['class' => [], 'id' => []],
            'a' => ['class' => [], 'id' => [], 'href' => []],
            'div' => ['class' => [], 'id' => [], 'data' => []],
            'span' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'i' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'table' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'tr' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'td' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'thead' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'tbody' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'th' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'svg' => [
                'class' => [],
                'id' => [],
                'width' => [],
                'height' => [],
                'viewBox' => [],
                'xmlns' => [],
            ],
            'g' => [
                'fill' => [],
            ],
            'path' => [
                'd' => [],
            ],
            'br' => array(),
            'em' => array(),
            'strong' => array(),
        );
        return wp_kses($string, $allow_attr);
    }
}

function deposit_type_name($type): string
{
    $deposit_type_name = '';
    if ($type) {
        switch ($type) {
            case 'minimum_amount':
                $deposit_type_name = 'Minimum Amount';
                break;
            case 'percent':
                $deposit_type_name = 'Percentage of Amount';
                break;
            case 'fixed':
                $deposit_type_name = 'Fixed Amount';
                break;
            default:
                $deposit_type_name = 'Payment Plan';
        }
    }

    return $deposit_type_name;
}

/* Partial Order List
 * @param WP_Query object
 * return Table with data
 * */
function wcpp_get_partial_order_data($query)
{

    ?>
    <table class="mepp-table wcpp-partial-order-table">
        <thead>
            <tr>
                <th><?php _e('Order', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th><?php _e('Order Date', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th><?php _e('Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th><?php _e('Order Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th><?php _e('Total Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th><?php _e('Order Due', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th><?php _e('Action', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($query) :
                while ($query->have_posts()) :
                    $query->the_post();
                    $order_id = get_the_ID();
                    $order = wc_get_order($order_id);

                    $woo_order_edit_page_url = get_admin_url() . 'post.php?post=' . $order_id . '&action=edit';

                    echo '<tr>';
                    echo '<td>#' . $order_id . '</td>';
                    echo '<td>' . mepp_date($order->get_date_created()) . '</td>';
                    echo '<td>' . deposit_type_name($order->get_meta('_pp_deposit_system')) . '</td>';
                    echo '<td class="mep_style_ta_r">' . wc_price($order->get_meta('total_value')) . '</td>';
                    echo '<td class="mep_style_ta_r">' . wc_price($order->get_meta('deposit_value')) . '</td>';
                    echo '<td class="mep_style_ta_r">' . wc_price($order->get_meta('due_payment')) . '</td>';
                    echo '<td><div class="wcpp-partial-order-action-container">';
                    echo '<img class="wcpp-partial-order-action-loader" src="' . WCPP_PLUGIN_URL . 'asset/img/wcpp-loader.gif' . '" alt="loader image">';
                    echo '<button class="wcpp-btn mep-view-order" data-order-id="' . $order_id . '">' . __("View Order", "advanced-partial-payment-or-deposit-for-woocommerce") . '</button>';
                    echo '<a href="' . $woo_order_edit_page_url . '" target="_blank" class="wcpp-btn mep-edit-order" data-order-id="' . $order_id . '">' . __("Edit Order", "advanced-partial-payment-or-deposit-for-woocommerce") . '</a>';
                    echo '<button class="wcpp-btn mep-view-history" data-order-id="' . $order_id . '">' . __("View History", "advanced-partial-payment-or-deposit-for-woocommerce") . '</button>';
                    echo '</div></td>';
                    echo '</tr>';
                endwhile;
            endif; ?>
        </tbody>
    </table>
    <?php
}

// Get Partial Payment History Ajax
add_action('wp_ajax_mepp_admin_get_partial_history', 'mepp_admin_get_partial_history');
add_action('wp_ajax_nopriv_mepp_admin_get_partial_history', 'mepp_admin_get_partial_history');
function mepp_admin_get_partial_history()
{
    $order_id = $_POST['order_id'];

    $html = '<tr class="wcpp-data-tr"><td colspan="7">';
    $html .= mep_pp_history_get($order_id, true, false);
    $html .= '</td></tr>';

    echo $html;
    exit();
}

// Get Partial Order Detail Ajax
add_action('wp_ajax_mepp_admin_get_partial_order_detail', 'mepp_admin_get_partial_order_detail');
add_action('wp_ajax_nopriv_mepp_admin_get_partial_order_detail', 'mepp_admin_get_partial_order_detail');

function mepp_admin_get_partial_order_detail()
{
    $order_id = $_POST['order_id'];

    $html = '<tr class="wcpp-order-detail-tr"><td colspan="7">';
    $html .= mep_pp_partial_order_detail_get($order_id, true);
    $html .= '</td></tr>';

    echo $html;
    exit();
}

// Order Filter ajax
add_action('wp_ajax_mepp_admin_partial_order_filter', 'mepp_admin_partial_order_filter');
add_action('wp_ajax_nopriv_mepp_admin_partial_order_filter', 'mepp_admin_partial_order_filter');
function mepp_admin_partial_order_filter()
{
    $html = "<p class='wcpp-no-data'>" . __('No data found!', 'advanced-partial-payment-or-deposit-for-woocommerce') . "</p>";
    $value = $_POST['value'];
    $filter_type = $_POST['filter_type'];

    $args = array(
        'post_type' => 'shop_order',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_pp_deposit_system',
                'compare' => 'EXISTS',
            ),
            array(
                'key' => 'total_value',
                'compare' => '!=',
                'value' => 0
            ),
            array(
                'key' => 'due_payment',
                'value' => 0,
                'compare' => '>',
            )
        )
    );

    if ($value) {
        switch ($filter_type) {
            case 'order_id':
                $args = array_merge($args, array(
                    'p' => $value,
                ));
                break;
            case 'deposit_type':
                $args['meta_query'][] = array(
                    'key' => '_pp_deposit_system',
                    'compare' => '=',
                    'value' => $value
                );
                break;
        }
    }

    $query = new WP_Query($args);
    wp_reset_postdata();

    if ($query->found_posts > 0) {
        $html = wcpp_get_partial_order_data($query);
    }

    echo $html;
    exit();
}

// Get Partial Payment Reminder log
add_action('wp_ajax_mepp_admin_get_partial_reminder_log', 'mepp_admin_get_partial_reminder_log');
add_action('wp_ajax_nopriv_mepp_admin_get_partial_reminder_log', 'mepp_admin_get_partial_reminder_log');
function mepp_admin_get_partial_reminder_log()
{
    $order_id = $_POST['order_id'];

    $html = '<tr class="wcpp-data-tr"><td colspan="8">';
    $html .= mep_pp_reminder_log_get($order_id);
    $html .= '</td></tr>';

    echo $html;
    exit();
}

// Reminder Log Html
function mep_pp_reminder_log_get($order_id)
{
    $next_reminder_date = '';
    $due_amount = get_post_meta($order_id, 'due_payment', true);
    $get_next_reminder_date = get_post_meta($order_id, '_wc_pp_next_payment_reminder_date', true);
    $current_date = strtotime('now');
    if ($get_next_reminder_date > $current_date) {
        $next_reminder_date = '<p style="margin:0"><span style="color:red">' . __('Next Reminder Date', 'advanced-partial-payment-or-deposit-for-woocommerce') . ': </span> ' . date(get_option('date_format'), get_post_meta($order_id, '_wc_pp_next_payment_reminder_date', true)) . '</p>';
        $next_reminder_date .= '<img class="mepp-inner-loading" style="width: 25px;vertical-align: top;" src="' . WCPP_PLUGIN_URL . '/asset/img/wcpp-loader.gif" />';
        $next_reminder_date .= '<button class="mepp-next-reminder-send-now" data-order-id="' . $order_id . '" data-order-type="parent">' . __("Send Now", "advanced-partial-payment-or-deposit-for-woocommerce") . '</button>';
    }

    $args = array(
        'post_type' => 'wcpp_payment',
        'post_status' => 'any',
        'post_parent' => $order_id,
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'asc',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_wc_pp_reminder_email_sent',
                'value' => 'yes',
                'compare' => '=',
            ),
        ),
    );

    $reminder_log = new WP_Query($args);
    $count = $reminder_log->found_posts;

    if ($count) :
        ob_start();

        if ($due_amount > 0 && $next_reminder_date) : // Reminder date and send now
    ?>
            <div class="mepp_partial_inner_meta mepp_d_abs" style="right:2px;top:2px;margin:0">
                <?php
                echo $next_reminder_date;
                ?>
            </div>
        <?php
        endif; // Reminder date and send now END

        echo '<h2 class="woocommerce-column__title">' . __("Partial Payment Reminder log", "advanced-partial-payment-or-deposit-for-woocommerce") . '</h2>';
        ?>
        <table class="mepp-table mep-pp-history-table woocommerce-table" style="width:100%;text-align:left">
            <thead>
                <tr>
                    <th style="text-align:left"><?php esc_attr_e('Sl.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th style="text-align:left"><?php esc_attr_e('Reminder Email Sent Date', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th style="text-align:left"><?php esc_attr_e('Payment Receive Date', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th style="text-align:left"><?php esc_attr_e('Status', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                </tr>
            </thead>
            <tbody>

                <?php
                $x = 1;
                while ($reminder_log->have_posts()) :
                    $reminder_log->the_post();
                    $id = get_the_ID();
                    $order = wc_get_order($id);

                    if ($order->get_status() == 'completed') {
                        $payment_date = date(get_option('date_format') . ' H:i a', strtotime(get_post_meta($id, '_completed_date', true)));
                    }

                    echo '<tr>';
                    echo '<td>' . (esc_attr($x)) . '</td>';
                    echo '<td><span>' . date(get_option('date_format') . ' H:i', $order->get_meta('_wc_pp_reminder_email_sent_date')) . '</span>';

                    if ($order->get_status() == 'pending') :
                        echo '<div class="mepp-resend-conatiner">';
                        echo '<img class="mepp-inner-loading" style="width: 25px;vertical-align: top;" src="' . WCPP_PLUGIN_URL . '/asset/img/wcpp-loader.gif" />';
                        echo '<button class="mepp-next-reminder-send-now mepp-resen-btn" data-order-id="' . $id . '" data-order-type="child">' . __('Re-Send', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</button>';
                        echo '</div>';
                    endif;

                    echo '</td>';
                    echo '<td>' . (isset($payment_date) ? $payment_date : null) . '</td>';
                    echo '<td>' . ucfirst($order->get_status()) . '</td>';
                    echo '</tr>';
                    $x++;
                endwhile;
                ?>
            </tbody>
        </table>

    <?php
    else :
        if ($due_amount > 0 && $next_reminder_date) :
            echo $next_reminder_date;
        endif;
    endif;

    wp_reset_postdata();
    return ob_get_clean();
}

// Resend Next payment reminder email
add_action('wp_ajax_mepp_admin_resend_next_reminder', 'mepp_re_send_next_payment_reminder');
add_action('wp_ajax_nopriv_mepp_admin_resend_next_reminder', 'mepp_re_send_next_payment_reminder');
function mepp_re_send_next_payment_reminder()
{
    $order_id = $_POST['order_id'];
    if ($order_id) {
        $res = do_action('mep_pp_partial_email', $order_id);

        echo $res;
    }

    exit();
}

// Send Now Next payment reminder email
add_action('wp_ajax_mepp_admin_send_now_next_reminder', 'mepp_admin_send_now_next_reminder');
add_action('wp_ajax_nopriv_mepp_admin_send_now_next_reminder', 'mepp_admin_send_now_next_reminder');
function mepp_admin_send_now_next_reminder()
{
    $message = 'error';
    $order_id = $_POST['order_id'];
    $order_type = $_POST['order_type'];

    if ($order_id) {
        if ($order_type === 'parent') {
            $args = array(
                'post_type' => 'wcpp_payment',
                'post_parent' => $order_id,
                'post_status' => 'wc-pending',
                'posts_per_page' => 1
            );
            $order = new WP_Query($args);
            if ($order->found_posts > 0) {
                $id = $order->posts[0]->ID;
                do_action('mep_pp_partial_email', $order_id);
                update_post_meta($id, '_wc_pp_reminder_email_sent', true);

                $message = 'success';
            }
        } else {
            do_action('mep_pp_partial_email', $order_id);
            update_post_meta($order_id, '_wc_pp_reminder_email_sent', true);

            $message = 'success';
        }

        echo $message;
    }

    exit();
}

add_action('wp_ajax_wcpp_deposit_type_switch_frontend', 'wcpp_deposit_type_switch_frontend');
add_action('wp_ajax_nopriv_wcpp_deposit_type_switch_frontend', 'wcpp_deposit_type_switch_frontend');
function wcpp_deposit_type_switch_frontend()
{

	// Check user and role
	if (apply_filters('mepp_user_role_allow', 'go') === 'stop') {
		return 0;
	}
    $payment_type = $_POST['payment_type'];
    $payment_plan_id = isset($_POST['payment_plan_id']) ? $_POST['payment_plan_id'] : '';
    $default_deposit_value = (float)get_option('mepp_default_partial_amount') ? get_option('mepp_default_partial_amount') : 0;
    $deposit_type = get_option('mepp_default_partial_type') ? get_option('mepp_default_partial_type') : 'percent';
    if ($deposit_type !== 'payment_plan' && !$default_deposit_value) {
        return 0;
    }

    $cart = WC()->cart->cart_contents;
    foreach ($cart as $key => $item) {

        $deposit_amount = 0;
        $line_total = (float)$item['line_total'];

        if ($payment_type === 'check_pp_deposit') {
            $item['_pp_deposit_type'] = 'check_pp_deposit';
            $item['_pp_deposit_system'] = $deposit_type;
            if ($deposit_type === 'percent') {
                $deposit_amount = ($line_total * $default_deposit_value) / 100;
            } else {
                $deposit_amount = $default_deposit_value * $item['quantity'];
            }

            $item['_pp_deposit_value'] = $default_deposit_value;

            if ($payment_plan_id) {
                $payment_terms = mep_make_payment_terms($line_total, $payment_plan_id, $line_total);
                $item['_pp_deposit'] = $payment_terms['deposit_amount'];
                $item['_pp_due_payment'] = $line_total - $payment_terms['deposit_amount'];

                $item['_pp_order_payment_terms'] = $payment_terms['payment_terms'];
                $item['_pp_deposit_payment_plan_name'] = mep_pp_payment_plan_name($payment_plan_id);
                $item['_pp_deposit_payment_plan_id'] = $payment_plan_id;
            } else {
                $item['_pp_deposit'] = $deposit_amount;

                $item['_pp_due_payment'] = $line_total - $item['_pp_deposit'];
                $item['_pp_order_payment_terms'] = '';
                $item['_pp_deposit_payment_plan_name'] = '';
            }
        } else {
            $item['_pp_deposit_type'] = 'check_full';
            $item['_pp_deposit_system'] = '';
            $item['_pp_deposit'] = 0;
            $item['_pp_deposit_value'] = 0;
            $item['_pp_due_payment'] = 0;
            $item['_pp_order_payment_terms'] = '';
            $item['_pp_deposit_payment_plan_name'] = '';
            $item['_pp_deposit_payment_plan_id'] = '';
        }

        WC()->cart->cart_contents[$key] = $item;
    }

    WC()->cart->set_session(); // Finaly Update Cart
    echo $deposit_type;
    exit();
}

function mep_make_payment_terms($total_amount, $payment_plan_id, $new_total = 0)
{
    $payment_terms = array();
    $deposit_amount = 0;
    $due_amount = 0;
    $this_plan = get_term_meta($payment_plan_id);
    $this_plan_schedule = maybe_unserialize((maybe_unserialize($this_plan['mepp_plan_schedule'][0])));
    if ($this_plan_schedule) {
        $date = date('Y-m-d');
        $down_payment = $this_plan['mepp_plan_schedule_initial_pay_parcent'][0];
        $payment_terms[] = array(
            'id' => '',
            'title' => 'Deposit',
            'type' => 'deposit',
            'date' => $date,
            'total' => mep_percentage_value($down_payment, $total_amount),
            'due' => $new_total - mep_percentage_value($down_payment, $total_amount),
        );

        $deposit_amount = mep_percentage_value($down_payment, $total_amount);
        $due_amount = $new_total - mep_percentage_value($down_payment, $total_amount);
        $total_percent = $down_payment;
        foreach ($this_plan_schedule as $schedule) {
            $date = mep_payment_plan_date($schedule["plan_schedule_date_after"], $schedule["plan_schedule_parcent_date_type"], $date);
            $amount = mep_percentage_value($schedule["plan_schedule_parcent"], $total_amount);
            $total_percent += $schedule["plan_schedule_parcent"];
            
            $due_amount = $due_amount - $amount;
            $payment_terms[] = array(
                'id' => '',
                'title' => 'Future Payment',
                'type' => 'future_payment',
                'date' => $date,
                'total' => $amount,
                'due' => $due_amount,
            );

            // if ($total_percent >= 100) break;
        }
    }

    return array(
        'deposit_amount' => $deposit_amount,
        'payment_terms' => $payment_terms,
    );
}

function mep_modal_html()
{
    ?>
    <!-- The Modal -->
    <div id="mepModal" class="modal">

        <!-- Modal content -->
        <div class="modal-content">
            <div class="modal-header">
                <span class="mepModalclose">&times;</span>
                <h2></h2>
            </div>
            <div class="modal-body"></div>
            <div class="modal-footer"></div>
        </div>

    </div>
<?php
}

/* Get the deposit amount
 * @param int $product_id
 * @param string $deposit_type
 * @return $deposit_amount
 * */
if (!function_exists('mepp_get_deposit_amount')) {
    function mepp_get_deposit_amount($product_id)
    {
        $deposit_amount = 0;

        if (function_exists('mep_product_exists')) {
            if (get_post_meta($product_id, 'link_mep_event', true)) {
                $linked_event_id = get_post_meta($product_id, 'link_mep_event', true);
            } else {
                $linked_event_id = null;
            }

            if ($linked_event_id) {
                $product_id = mep_product_exists($linked_event_id) ? $linked_event_id : $product_id;
            }
        }

        $is_exclude_from_global = get_post_meta($product_id, '_mep_exclude_from_global_deposit', true);
        $is_deposit_enable = get_post_meta($product_id, '_mep_enable_pp_deposit', true);

        $default_deposit_type = get_option('mepp_default_partial_type') ? get_option('mepp_default_partial_type') : '';
        $default_deposit_value = get_option('mepp_default_partial_amount') ? get_option('mepp_default_partial_amount') : '';

        $is_global = false;
        // Deposit value & deposit type
        if ($is_exclude_from_global === 'yes' && $is_deposit_enable === 'yes') {
            $deposit_value = get_post_meta($product_id, '_mep_pp_deposits_value', true) ? get_post_meta($product_id, '_mep_pp_deposits_value', true) : 0;
            $minimum_value = get_post_meta($product_id, '_mep_pp_minimum_value', true) ? get_post_meta($product_id, '_mep_pp_minimum_value', true) : 0;
            $deposit_type = get_post_meta($product_id, '_mep_pp_deposits_type', true) ? get_post_meta($product_id, '_mep_pp_deposits_type', true) : '';
            if ($deposit_type === 'minimum_amount') {
                $deposit_value = $minimum_value;
            }
        } else {
            $show_partial_page = get_option('mepp_partial_enable_for_page') ?: 'product_detail';
            // if ($show_partial_page === 'checkout') return 0;
            $deposit_value = $default_deposit_value;
            $deposit_type = $default_deposit_type;
            $is_global = true;
        }

        $product_type = get_post_type($product_id);
        $product_price_total = 0;
        $quantity = 1;

        // Price
        if ($product_type == 'mep_events') {
            //        $product_price_total = $cart_item_data['line_total'];
        } else {
            $product = wc_get_product($product_id);
            $product_price_total = wc_get_price_including_tax($product) * $quantity;
            if ($product->is_type('variable')) {
                return $deposit_amount;
                // Product has variations
                $variation_id = sanitize_text_field($_POST['variation_id']);
                $product = new WC_Product_Variation($variation_id);
                $product_price_total = wc_get_price_including_tax($product) * $quantity;
            }
        }

        if ($deposit_value) {
            $deposit_value = (float) $deposit_value;
        } else {
            return 0;
        }

        if ($deposit_type == 'percent') {
            $deposit_amount = ($deposit_value / 100) * $product_price_total;
        } elseif ($deposit_type == 'minimum_amount') {
            $deposit_amount = $is_global ? $deposit_value / $quantity : $minimum_value / $quantity;
        } elseif ($deposit_type == 'payment_plan') {
            $deposit_amount = 0;
        } else {
            $deposit_amount = $deposit_value / $quantity;
        }

        return $deposit_amount;
    }
}

// Is there PayPal in payment method?
function mepp_check_paypal_has()
{
    return in_array('ppcp-gateway', meppp_available_payment_methods());
}

if (!function_exists('mep_esc_html')) {
    function mep_esc_html($string)
    {
        $allow_attr = array(
            'input' => array(
                'br' => [],
                'type' => [],
                'class' => [],
                'id' => [],
                'name' => [],
                'value' => [],
                'size' => [],
                'placeholder' => [],
                'min' => [],
                'max' => [],
                'checked' => [],
                'required' => [],
                'disabled' => [],
                'readonly' => [],
                'step' => [],
                'data-default-color' => [],
            ),
            'p' => [
                'class' => []
            ],
            'img' => [
                'class' => [],
                'id' => [],
                'src' => [],
                'alt' => [],
            ],
            'fieldset' => [
                'class' => []
            ],
            'label' => [
                'for' => [],
                'class' => []
            ],
            'select' => [
                'class' => [],
                'name' => [],
                'id' => [],
            ],
            'option' => [
                'class' => [],
                'value' => [],
                'id' => [],
                'selected' => [],
            ],
            'textarea' => [
                'class' => [],
                'rows' => [],
                'id' => [],
                'cols' => [],
                'name' => [],
            ],
            'h2' => ['class' => [], 'id' => [],],
            'a' => ['class' => [], 'id' => [], 'href' => [],],
            'div' => ['class' => [], 'id' => [], 'data' => [],],
            'span' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'i' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'table' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'tr' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'td' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'thead' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'tbody' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'th' => [
                'class' => [],
                'id' => [],
                'data' => [],
            ],
            'svg' => [
                'class' => [],
                'id' => [],
                'width' => [],
                'height' => [],
                'viewBox' => [],
                'xmlns' => [],
            ],
            'g' => [
                'fill' => [],
            ],
            'path' => [
                'd' => [],
            ],
            'br' => array(),
            'em' => array(),
            'strong' => array(),
        );
        return wp_kses($string, $allow_attr);
    }
}

function wcpp_is_deposit_enabled($product_id)
{
    $data = array(
        'is_enable' => false,
        'setting_level' => ''
    );

    if(!$product_id) return $data;

    $global_deposit_enable = get_option('mepp_enable_partial_by_default') ? get_option('mepp_enable_partial_by_default') : 'no';
    $is_deposit_enabled_localy = get_post_meta($product_id, '_mep_enable_pp_deposit', true);
    $exclude_from_global = get_post_meta($product_id, '_mep_exclude_from_global_deposit', true);
    $deposit_type_localy = get_post_meta($product_id, '_mep_pp_deposits_type', true);

    if( $exclude_from_global === 'yes' ) {      // Local setting
        if($deposit_type_localy === 'minimum_amount') {
            $value = get_post_meta($product_id, '_mep_pp_minimum_value', true) ? get_post_meta($product_id, '_mep_pp_minimum_value', true) : 0;
        } elseif($deposit_type_localy === 'percent' || $deposit_type_localy === 'fixed') {
            $value = get_post_meta($product_id, '_mep_pp_deposits_value', true) ? get_post_meta($product_id, '_mep_pp_deposits_value', true) : 0;
        } else {
            $value = true;
        }
        $data['is_enable'] = $is_deposit_enabled_localy === 'yes' && $value ? true : false;
        $data['setting_level'] = 'local';
    } else {                                    // global setting
        $value = get_option('mepp_default_partial_amount') ? get_option('mepp_default_partial_amount') : 0;
        $data['is_enable'] = ($global_deposit_enable === 'yes' && (float) $value > 0) ? true : false;
        $data['setting_level'] = 'global';
    }

    return $data;
}

// For testing
function wcpp_test()
{
    $args = array(
        'post_type' => 'wcpp_payment',
        'post_status' => 'wc-pending',
        'posts_per_page' => -1,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => '_wc_pp_payment_type',
                'value' => 'last',
                'compare' => '=',
            )
        )
    );

    //query for all partially-paid orders
    $partiall_orders = new WP_Query($args);

    while ($partiall_orders->have_posts()) :
        $partiall_orders->the_post();
        $order_id = $partiall_orders->post->ID;
        $order = wc_get_order($order_id);

        $parent_order_id = $order->get_parent_id();
        $parent_order = wc_get_order($parent_order_id);
        if($parent_order) {
            if ($parent_order->get_status() !== 'partially-paid') {
                continue;
            }
        }
    endwhile;
    wp_reset_postdata();
}

// Check mage event type plugin activate
function wcpp_is_event_type_plugin_active() {
    $is_active = false;

    if(is_plugin_active('tour-booking-manager/tour-booking-manager.php') || is_plugin_active('mage-eventpress/woocommerce-event-press.php')) {
        $is_active = true;
    }

    return $is_active;
}

// Get the Mep product id
function wcpp_get_mep_product_id($product_id) {
    if (function_exists('mep_product_exists')) {
        if (get_post_meta($product_id, 'link_mep_event', true)) {
            $linked_event_id = get_post_meta($product_id, 'link_mep_event', true);
        } elseif (get_post_meta($product_id, 'link_wbtm_bus', true)) {
            $linked_event_id = get_post_meta($product_id, 'link_wbtm_bus', true);
        } elseif (get_post_meta($product_id, 'link_ttbm_id', true)) {
            $linked_event_id = get_post_meta($product_id, 'link_ttbm_id', true);
        } else {
            $linked_event_id = null;
        }

        if ($linked_event_id) {
            $product_id = mep_product_exists($linked_event_id) ? $linked_event_id : $product_id;
        }
    }

    return $product_id;
}