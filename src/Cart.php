<?php
if (!defined('ABSPATH')) {
    die;
}

class MEP_PP_Cart
{
    public function __construct()
    {
        add_filter('woocommerce_cart_item_name', [$this, 'display_cart_item_pp_deposit_data'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'items_allowed_add_to_cart'], 10, 3);
        add_action('woocommerce_cart_totals_after_order_total', [$this, 'to_pay_html']);
        add_action('woocommerce_update_cart_action_cart_updated', [$this, 'recalculate_cart'], 10, 1);

        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_pp_deposit_data'], 100, 2);

        add_filter( 'woocommerce_cart_totals_order_total_html', [$this, 'cart_total_html'], 10, 1 );
        add_filter('woocommerce_calculated_total', array($this, 'adjust_cart_total'), 100, 1);

        add_action('woocommerce_before_calculate_totals', array($this, 'add_custom_price'));

        do_action('dfwc_cart', $this);
        do_action('appdw_cart', $this);
    }

    public function add_custom_price($cart_object)
    {    
        foreach ($cart_object->cart_contents as $key => $value) {
            // $product_price = get_post_meta($value['product_id'], '_price', true);
            if(isset($value['_pp_deposit_system']) && $value['_pp_deposit_system'] === 'payment_plan' && get_post_type($value['product_id']) === 'product') {
                $product = $value['data'];
                $product_price = $product->get_price();
                $product_qty = $value['quantity'];
                $product_price_subtotal = $product_price * $product_qty;

                $payment_plan = get_term_meta($value['_pp_deposit_payment_plan_id']);
                $down_payment = (float) $payment_plan['mepp_plan_schedule_initial_pay_parcent'][0];
                $payment_schdule = maybe_unserialize((maybe_unserialize($payment_plan['mepp_plan_schedule'][0])));
                $percent = 0;
                if ($payment_schdule) {
                    foreach ($payment_schdule as $payments) {
                        $percent = $percent + $payments['plan_schedule_parcent'];
                    }
                    $percent += $down_payment;
                }
                // Calculate product price according to payment plan
                $subtotal_price = ($product_price_subtotal * $percent) / 100;
                $set_product_price = ($product_price * $percent) / 100;
                // echo '<pre>'; print_r(mep_make_payment_terms($product_price_subtotal, $value['_pp_deposit_payment_plan_id'])); die;
                $value['data']->set_price($set_product_price);
                $value['_pp_due_payment'] = $subtotal_price - ($product_price_subtotal * $down_payment) / 100;
                $value['_pp_order_payment_terms'] = mep_make_payment_terms($product_price_subtotal, $value['_pp_deposit_payment_plan_id'], $subtotal_price)['payment_terms'];
                WC()->cart->cart_contents[$key] = $value;
            }
        }
        WC()->cart->set_session(); // Finaly Update Cart
    }

    public function recalculate_cart($cart_updated)
    {
        if ($cart_updated) {
            WC()->cart->calculate_totals();
            foreach (WC()->cart->get_cart() as $key => $item) {
                if (isset($item['_pp_deposit_type']) && $item['_pp_deposit_type'] == 'check_pp_deposit') {
                    if($item['_pp_deposit_system'] === 'percent') {
                        $item['_pp_deposit'] = ($item['line_total'] * $item['_pp_deposit_value']) / 100;
                    } elseif($item['_pp_deposit_system'] === 'fixed' || $item['_pp_deposit_system'] === 'minimum_amount') {
                        $item['_pp_deposit'] = $item['_pp_deposit_value'] * $item['quantity'];
                    }
                    $item['_pp_due_payment'] = $item['line_subtotal'] - $item['_pp_deposit'];
                }

                WC()->cart->cart_contents[$key] = $item;
            }

            WC()->cart->set_session(); // Finaly Update Cart
        }
    }

    public function to_pay_html()
    {
        if (meppp_cart_have_pp_deposit_item()) {
            meppp_display_to_pay_html();
        }
    }

    public function add_cart_item_pp_deposit_data($cart_item_data, $product_id)
    {
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
                if (!wcppe_enable_for_event()) return $cart_item_data;
                $product_id = mep_product_exists($linked_event_id) ? $linked_event_id : $product_id;
            }
        }

        // Exclude from global setting
        if (apply_filters('mepp_exclude_product_from_default_setting_checking', false, $product_id) === 'stop') {
            return $cart_item_data;
        }

        // Check user and role
        if (apply_filters('mepp_user_role_allow', 'go') === 'stop') {
            return $cart_item_data;
        }

        $deposit_mode = isset($_POST['deposit-mode']) ? $_POST['deposit-mode'] : ''; // Partial option show Product or Checkout page
        $cart_item_data['_pp_deposit_mode'] = $deposit_mode ? $deposit_mode : 'no-deposit';
        if ($deposit_mode == '' || $deposit_mode == 'check_full' || $deposit_mode == 'no-deposit') {
            return $cart_item_data;
        }

        // Global Settings
        $default_deposit_type = get_option('mepp_default_partial_type') ? get_option('mepp_default_partial_type') : '';
        $default_deposit_value = get_option('mepp_default_partial_amount') ? get_option('mepp_default_partial_amount') : '';
        $default_partial_enable = apply_filters('mepp_enable_partial_by_default', 'no');

        if ($deposit_mode) { // From Product Page
            $quantity = isset($_POST['quantity']) ? $_POST['quantity'] : 1;
            $deposit_setting_apply = 'local';
        } else { // From Shop Page
            $quantity = isset($_POST['quantity']) ? $_POST['quantity'] : 1;
            $deposit_mode = 'check_pp_deposit';
            $deposit_setting_apply = 'global';
        }

        // Product Price
        $product_type = get_post_type($product_id);
        $product_price_total = 0;

        if ($product_type == 'mep_events') {
            $product_price_total = $cart_item_data['line_total'];
        } elseif ($product_type == 'wbbm_bus') {
            $product_price_total = $cart_item_data['line_total'];
        } elseif ($product_type == 'wbtm_bus') {
            $product_price_total = $cart_item_data['line_total'];
        } elseif ($product_type == 'ttbm_tour') {
            $product_price_total = $cart_item_data['line_total'];
        } else {
            $product = wc_get_product($product_id);
            if('due' === apply_filters('wcpp_general_setting_values', 'deposit', 'meppp_tax_amount_added')) {
                $product_price_total = wc_get_price_including_tax($product) * (int)sanitize_text_field($quantity);
            } else {
                $product_price_total = $product->get_price() * (int)sanitize_text_field($quantity);
            }
            if ($product->is_type('variable')) {
                // Product has variations
                $variation_id = sanitize_text_field($_POST['variation_id']);
                $product = new WC_Product_Variation($variation_id);
                if ('due' === apply_filters('wcpp_general_setting_values', 'deposit', 'meppp_tax_amount_added')) {
                    $product_price_total = wc_get_price_including_tax($product) * (int)sanitize_text_field($quantity);
                } else {
                    $product_price_total = $product->get_price() * (int)sanitize_text_field($quantity);
                }
            }
        }
        // Product Price END

        // Check is zero price checkout
        $enable_checkout_zero_price = apply_filters('mepp_enable_zero_price_checkout', 'no');
        if ($enable_checkout_zero_price === 'yes') {
            $cart_item_data['_pp_deposit'] = 0;
            $cart_item_data['_pp_deposit_value'] = 0;
            $cart_item_data['_pp_due_payment'] = $product_price_total;
            $cart_item_data['_pp_deposit_type'] = sanitize_text_field($deposit_mode);
            $cart_item_data['_pp_deposit_system'] = 'zero_price_checkout';
            $cart_item_data['_pp_deposit_payment_plan_name'] = '';

            return $cart_item_data;
        }
        $is_exclude_from_global = get_post_meta($product_id, '_mep_exclude_from_global_deposit', true);
        $is_deposit_enable = get_post_meta($product_id, '_mep_enable_pp_deposit', true);

        $setting_from = '';
        if ($is_exclude_from_global === 'yes') { // Product level Setting
            $deposit_type = get_post_meta($product_id, '_mep_pp_deposits_type', true) ? get_post_meta($product_id, '_mep_pp_deposits_type', true) : '';
            $deposit_min_value_strict = get_post_meta($product_id, '_mep_pp_minimum_value', true) ? get_post_meta($product_id, '_mep_pp_minimum_value', true) : '';
            if ($deposit_type === 'minimum_amount') {
                $deposit_value = isset($_POST['user-deposit-amount']) ? sanitize_text_field($_POST['user-deposit-amount']) / sanitize_text_field($quantity) : 0;
            } else {
                $deposit_value = get_post_meta($product_id, '_mep_pp_deposits_value', true) ? get_post_meta($product_id, '_mep_pp_deposits_value', true) : 0;
            }
            $setting_from = 'local';
        } else { // Global level Setting
            $deposit_value = $deposit_min_value_strict = $default_deposit_value;
            $deposit_type = $default_deposit_type;
            $setting_from = 'global';
            // Partial Option Page
            $partial_option_page = apply_filters('mepp_partial_option_for_page', 'product_detail');
            if ($partial_option_page === 'checkout') {
                $cart_item_data['_pp_deposit_system'] = '';
                $cart_item_data['_pp_deposit_type'] = 'check_pp_deposit';
                $cart_item_data['_pp_deposit_setting_from'] = $setting_from;
                // return $cart_item_data;
            }
        }

        // *** Limitation ***

        // Exception handle for deposit type
        if (!wcppe_enable_for_event()) {
            if ($deposit_type === 'minimum_amount' || $deposit_type === 'payment_plan') {
                update_option('mepp_default_partial_type', 'percent');
                $deposit_type = 'percent';
            }
        }

        // Calculate data
        if ($deposit_type == 'percent') { // Percent
            if(!$deposit_value) {
                return $cart_item_data;
            }
            $deposit_amount = ($deposit_value / 100) * $product_price_total;

        } elseif ($deposit_type == 'manual' || $deposit_type == 'ticket_type') {
            $deposit_amount = isset($_POST['user-deposit-amount']) ? sanitize_text_field($_POST['user-deposit-amount']) / sanitize_text_field($quantity) : 0;

        } elseif ($deposit_type == 'minimum_amount') { // Minimum amount
            $default_value = get_option('mepp_default_partial_amount') ? get_option('mepp_default_partial_amount') : 0;
            $deposit_amount = isset($_POST['user-deposit-amount']) ? sanitize_text_field($_POST['user-deposit-amount']) * sanitize_text_field($quantity) : $default_value;
            if(!$deposit_amount) {
                return $cart_item_data;
            }

        } elseif ($deposit_type == 'payment_plan') { // Payment plan
            $get_payment_terms = mep_make_payment_terms($product_price_total, sanitize_text_field($_POST['mep_payment_plan']), $product_price_total);
            $cart_item_data['_pp_order_payment_terms'] = $get_payment_terms['payment_terms'];
            $deposit_amount = $get_payment_terms['deposit_amount'];
            $product_price_total = apply_filters('wcpp_product_price_from_payment_terms', $product_price_total, $get_payment_terms['payment_terms']);

        } else { // Fixed
            if(!$deposit_value) {
                return $cart_item_data;
            }
            $deposit_amount = $deposit_value * sanitize_text_field($quantity);

        }

        $cart_item_data['_pp_deposit'] = $deposit_amount; // Deposit value by user
        $cart_item_data['_pp_deposit_value'] = $deposit_value; // Deposit value by setting
        $cart_item_data['_pp_deposit_value_strict'] = $deposit_min_value_strict; // Deposit minimum value by setting
        $cart_item_data['_pp_due_payment'] = $product_price_total - $deposit_amount; // Due amount
        $cart_item_data['_pp_deposit_type'] = 'check_pp_deposit';
        $cart_item_data['_pp_deposit_system'] = $deposit_type; // Deposit type e.g percent, minimum_amount, fixed
        $cart_item_data['_pp_deposit_setting_from'] = $setting_from; // Setting honour by local or global
        $cart_item_data['_pp_deposit_payment_plan_id'] = isset($_POST['mep_payment_plan']) ? $_POST['mep_payment_plan'] : ''; // Payment plan id
        $cart_item_data['_pp_deposit_payment_plan_name'] = isset($_POST['mep_payment_plan']) ? mep_pp_payment_plan_name(sanitize_text_field($_POST['mep_payment_plan'])) : ''; // Payment plan title
        $cart_item_data['_pp_deposit_mode'] = $deposit_mode; // Deposit Mode e.g check_pp_deposit, check_full
        return $cart_item_data;
    }

    public function items_allowed_add_to_cart($passed, $product_id, $quantity)
    {

        $cart = WC()->cart->cart_contents;
        $deposit_type = isset($_POST['payment_plan']) ? $_POST['payment_plan'] : '';
        $msg = __("You can't add payment plans & another deposit type to the cart. <br>Please buy them in a separate order.", 'advanced-partial-payment-or-deposit-for-woocommerce');

        if ($deposit_type) {
            foreach ($cart as $item) {
                if (isset($item['_pp_deposit_system'])) {
                    if ($deposit_type === 'payment_plan') {
//                        if($item['_pp_deposit_system'] === 'percent' || $item['_pp_deposit_system'] === 'fixed' || $item['_pp_deposit_system'] === 'minimum_amount') {
//                            $passed = false;
//                            // Display a message
//                            $msg = "You can't have both Payment plan and Others Deposit types in the bucket.";
//                            wc_add_notice( __( $msg, "woocommerce" ), "error" );
//                        }
                        $passed = false;
                    }

                    if ($deposit_type !== 'payment_plan') {
                        if ($item['_pp_deposit_system'] === 'payment_plan') {
                            $passed = false;
                        }
                    }
                }
            }
        }

        if(!$passed) {
            // Display a message
            wc_add_notice(__($msg, "woocommerce"), "error");
        }

        return $passed;
    }

    /**
     * @param $cart_total
     */
    public function cart_total_html( $cart_total )
    {
        $cartTotal = WC()->cart->total;
        // Loop over $cart items
        $depositValue = 0; // no value
        $dueValue     = 0; // no value
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $vProductId = ( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
            $dueValue += ( isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit' ) ? $cart_item['_pp_due_payment'] : 0;
        }

        $value = $cartTotal + $dueValue;
        // Tax calculation by setting
        // var_dump(apply_filters('wcpp_general_setting_values', 'deposit', 'meppp_tax_amount_added'));
        // if('due' === apply_filters('wcpp_general_setting_values', 'deposit', 'meppp_tax_amount_added')) {
        //     $value = $value + WC()->cart->get_total_tax();
        // }

        return '<strong>' . wc_price( $value ) . '</strong>';
    }

    public function adjust_cart_total($total)
    {
        $total_deposit = 0;
        $total_due = 0;
        $is_deposit_pass = false;
        $has_deposit_type_minimum = false;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            // echo '<pre>'; print_r($cart_item); die;
            if (isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit') {
                $total_deposit += $cart_item['_pp_deposit'];
                $total_due += $cart_item['_pp_due_payment'];
                $is_deposit_pass = true;
                if ($cart_item['_pp_deposit_system'] === 'minimum_amount') {
                    $has_deposit_type_minimum = true;
                }
            } else {
                $total_deposit += $cart_item['line_total'];
            }
        }

        // if($is_deposit_pass && 'due' === apply_filters('wcpp_general_setting_values', 'deposit', 'meppp_tax_amount_added')) {
        //     // Tax calculation from setting
        //     $total_due = $total_due + WC()->cart->get_total_tax();
        // }

        return $total - $total_due;
    }

    public function display_cart_item_pp_deposit_data($name, $cart_item, $cart_item_key)
    {
        // echo '<pre>';print_r($cart_item);die;
        if (isset($cart_item['_pp_deposit']) && is_cart() && isset($cart_item['_pp_deposit_type']) && $cart_item['_pp_deposit_type'] == 'check_pp_deposit') {
            $name .= sprintf(
                '<strong><p>' . mepp_get_option('mepp_text_translation_string_deposit', __('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce')) . ': %s <br> ' . mepp_get_option('mepp_text_translation_string_due_payment', __('Due Payment', 'advanced-partial-payment-or-deposit-for-woocommerce')) . ': %s <br> ' . mepp_get_option('mepp_text_translation_string_deposit_type', __('Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce')) . ': %s</p></strong>',
                wc_price($cart_item['_pp_deposit']),
                wc_price($cart_item['_pp_due_payment']),
                mep_pp_deposti_type_display_name($cart_item['_pp_deposit_system'], $cart_item, true)
            );
            if (isset($cart_item['_pp_order_payment_terms']) && isset($cart_item['_pp_deposit_system'])) {
                if ($cart_item['_pp_order_payment_terms'] && $cart_item['_pp_deposit_system'] === 'payment_plan') {
                    $name .= '<div class="mep-product-payment-plans"><button class="mepp-payment-plan-cart-btn mep-pp-show-detail">' . __("Show detail", "advanced-partial-payment-or-deposit-for-woocommerce") . '</button>';
                    $name .= '<div class="mep-single-plan plan-details"><table><thead><tr><th>' . __("Payment Date", "advanced-partial-payment-or-deposit-for-woocommerce") . '</th><th>' . __("Amount", "advanced-partial-payment-or-deposit-for-woocommerce") . '</th></tr></thead><tbody>';
                    foreach ($cart_item['_pp_order_payment_terms'] as $plan) {
                        if ($plan['type'] !== 'deposit') {
                            $name .= '<tr><td>' . $plan['date'] . '</td> <td>' . wc_price($plan['total']) . '</td></tr>';
                        }
                    }
                    $name .= '</tbody></table></div></div>';
                }
            }
            return $name;
        }
        return $name;
    }
}

new MEP_PP_Cart();