<?php

namespace MagePeople\MEPP;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (class_exists('MEPP_Cart')) return;

/**
 * Class MEPP_Cart
 */
class MEPP_Cart
{


    private $has_payment_plans = false;
    private $recalculated = false;


    /**
     *
     * MEPP_Cart constructor.
     */
    public function __construct()
    {
        // Hook cart functionality
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);

        if (!mepp_checkout_mode()) {
            add_action('woocommerce_cart_totals_after_order_total', array($this, 'cart_totals_after_order_total'));
            add_filter('woocommerce_get_item_data', array($this, 'get_item_data'), 10, 2);
            add_action('woocommerce_add_to_cart', array($this, 'is_sold_individually'), 10, 6);
        }

        //have to set very low priority to make sure all other plugins make calculations first

        add_filter('woocommerce_cart_needs_payment', array($this, 'cart_needs_payment'), 10);
        add_action('woocommerce_after_calculate_totals', array($this, 'calculate_deposit_totals'), 1999);

        //compatibility warning purpose only, changed priority for cases where this function was unhooked
        add_filter('woocommerce_calculated_total', array($this, 'calculated_total'), 1002, 2);
          
        add_filter('mepp_cart_customer_validation', array($this, 'cart_customer_validation'));
        
       
    }

    function cart_loaded_from_session()
    {
        wc_doing_it_wrong('calculated_total', 'This function is no longer used during calculations, refer to function "calculate_deposit_totals"', '4.0.0');

    }
    /**
     * Prevents duplicates if the product is set to be individually sold.
     *
     * @throws \Exception if more than 1 item of an individually-sold product is being added to cart.
     */
    public function is_sold_individually($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        $product = wc_get_product($product_id);

        if ($product->is_sold_individually() && isset($cart_item_data['deposit'])) {
            $item_data = $cart_item_data;

            // Get the two possible values of the cart item key.
            if (
                isset($item_data['deposit']['enable']) && $item_data['deposit']['enable'] === 'yes') {
                $key_with_deposit = WC()->cart->generate_cart_id($product_id, $variation_id, $variation, $item_data);
                $item_data['deposit']['enable'] = 'no';
                $item_data['deposit']['override'] = apply_filters('mepp_add_to_cart_deposit_override', array(), $product_id, $variation_id);


                // The value of cart item key if deposit is disabled.
                $key_without_deposit = WC()->cart->generate_cart_id($product_id, $variation_id, $variation, $item_data);
            } else {
                $key_without_deposit = WC()->cart->generate_cart_id($product_id, $variation_id, $variation, $item_data);
                $item_data['deposit']['enable'] = 'yes';
                $item_data['deposit']['override'] = apply_filters('mepp_add_to_cart_deposit_override', array(), $product_id, $variation_id);


                // The value of cart item key if deposit is enabled.
                $key_with_deposit = WC()->cart->generate_cart_id($product_id, $variation_id, $variation, $item_data);
            }

            // Check if any of the cart item keys is being added more than once.
            $item_count = 0;
            foreach (WC()->cart->get_cart_contents() as $item) {
                if (($item['key'] === $key_with_deposit || $item['key'] === $key_without_deposit)) {
                    $item_count += $item['quantity'];
                }
            }

            if ($item_count > 1) {
                /* translators: %s: product name */
                throw new \Exception(sprintf('<a href="%s" class="button wc-forward">%s</a> %s', wc_get_cart_url(), esc_html__('View cart', 'woocommerce'), sprintf(esc_html__('You cannot add another "%s" to your cart.', 'woocommerce'), $product->get_name())));
            }
        }
    }
    
    function cart_customer_validation($validated)
    {

        //user restriction
        $allow_deposit_for_guests = get_option('mepp_restrict_deposits_for_logged_in_users_only', 'no');

        if ($allow_deposit_for_guests !== 'no' && is_user_logged_in() && isset($_POST['createaccount']) && $_POST['createaccount'] == 1) {

            //account created during checkout
            $validated = false;

        } elseif (is_user_logged_in()) {


            $disabled_user_roles = get_option('mepp_disable_deposit_for_user_roles', array());
            if (!empty($disabled_user_roles)) {

                foreach ($disabled_user_roles as $disabled_user_role) {

                    if (wc_current_user_has_role($disabled_user_role)) {

                        $validated = false;
                    }
                }

            }
        } else {
            if ($allow_deposit_for_guests !== 'no') {
                $validated = false;
            }
        }

        return $validated;
    }


    static function build_payment_schedule($remaining_details, $cart)
    {

        /**   START BUILD PAYMENT SCHEDULE**/

        $schedule = array();

        foreach ($cart->get_cart_contents() as $item_key => $item) {

            //combine all the payment schedules
            if (!isset($item['deposit'])) continue;
            $deposit_meta = $item['deposit'];
            if (!empty($deposit_meta['payment_schedule'])) {
                $item_discount = $item['line_subtotal'] - $item['line_total'];
                $count = 0;
                //combine payyment on same day
                foreach ($deposit_meta['payment_schedule'] as $key => $single_payment) {
                    $count++;
                    $timestamp = $single_payment['timestamp'];

                    $single_payment_data = array('details' => array('items' => array()), 'type' => 'partial_payment', 'total' => 0.0);

                    if ($timestamp === 'unlimited') {
                        $future_payment_amount_text = esc_html__(get_option('mepp_second_payment_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');
                        if (empty($future_payment_amount_text)) {
                            $future_payment_amount_text = esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce');
                        }
                        $single_payment_data['title'] = $future_payment_amount_text;
                        $single_payment_data['timestamp'] = $timestamp;

                    } else {
                        $single_payment_data['timestamp'] = $timestamp;
                    }
                    $single_payment_data['total'] += floatval($single_payment['amount']);
                    $single_payment_data['total'] += floatval($single_payment['tax']);

                    $single_payment_data['details']['items'][$item_key] = array(
                        'name' => $item['data']->get_name(),
                        'quantity' => $item['quantity'],
                        'amount' => floatval($single_payment['amount']),
                        'tax' => floatval($single_payment['tax']),
                        'total' => floatval($single_payment['amount']) + floatval($single_payment['tax'])
                    );

                    if ($item_discount !== 0) {
                        $division = $item['line_subtotal'] == 0 ? 1 : $item['line_subtotal'];
                        $percentage = round($single_payment['amount'] / $division * 100, 1);
                        $single_payment_data['details']['items'][$item_key]['subtotal'] = $item['line_total'] / 100 * $percentage;
                        $single_payment_data['details']['items'][$item_key]['subtotal_tax'] = $item['line_tax'] / 100 * $percentage;
                    }

                    $existing = false;
                    foreach ($schedule as $entry_key => $entry) {
                        if (isset($entry['timestamp']) && $entry['timestamp'] == $timestamp && !isset($entry['details']['items'][$item_key])) {
                            //combine or not
                            $existing = true;
                            $entry['total'] += $single_payment_data['total'];
                            $entry['details']['items'][$item_key] = $single_payment_data['details']['items'][$item_key];
                            $schedule[$entry_key] = $entry;
                            break;
                        }
                    }
                    if(!$existing){
                        $schedule[] = $single_payment_data;
                    }
                }

            }
        }

        $timestamps = array();

        foreach (array_keys($schedule) as $arr_key => $node) {
            if ($arr_key === 'unlimited') {
                // now that we collected payment without days in 'unlimited' and set its title, we can restore its date format for sorting purpose
                $timestamp = strtotime(date('Y-m-d', current_time('timestamp')) . "+1 days");
                $schedule[$key]['timestamp'] = $timestamp;
                $arr_key = $timestamp;
            }
            $timestamps[$arr_key] = $node;
        }
        array_multisort($timestamps, SORT_ASC, array_keys($schedule));

        $sorted_schedule = array();
        foreach ($timestamps as $timestamp) {

            $sorted_schedule[] = $schedule[$timestamp];
        }

        $schedule = $sorted_schedule;

        // add any fees /taxes / shipping / shipping taxes amounts
        $schedule_total = array_sum(array_column($schedule, 'total'));
        //this an alternate calculation method to avoid calculating percentages if payments are actually equal
        $equal_payments = true;
        foreach ($schedule as $payment) {
            //if any payment is not equal to first then we dont have equal payment schedule
            if ($payment['total'] != $schedule_total / count($schedule)) {
                $equal_payments = false;
                break;
            }
        }

        if (!empty($remaining_details)) {
            foreach ($remaining_details as $detail_key => $detail) {

                foreach ($detail as $item_key => $item) {
                    $total_record = $item['total'];
                    $amount_record = $item['amount'];
                    $tax_record = $item['tax'];
                    $count = 0;
                    foreach ($schedule as $payment_key => $payment) {
                        $count++;
                        if (count($schedule) === $count) {
                            //last
                            $amount = $amount_record;
                            $tax = $tax_record;
                            $total = $total_record;
                        } else {
                            $percentage = round($payment['total'] / $schedule_total * 100, 1);
                            $amount = $equal_payments ? round($item['amount'] / count($schedule), wc_get_price_decimals()) : round($item['amount'] / 100 * $percentage, wc_get_price_decimals());
                            //                            $amount = $equal_payments ? $item['amount'] / count($schedule) : round($item['amount'] / 100 * $percentage, wc_get_price_decimals());
                            $tax = $equal_payments ? $item['tax'] / count($schedule) : round($item['tax'] / 100 * $percentage, wc_get_price_decimals());
                            $total = $amount + $tax;
                            $total_record -= $total;
                            $amount_record -= $amount;
                            $tax -= $tax;
                        }

                        if ($detail_key === 'discount') {
                            $schedule[$payment_key]['total'] -= $total;
                        } else {
                            $schedule[$payment_key]['total'] += $total;
                        }
                        $schedule[$payment_key]['details'][$detail_key][$item_key] = array(
                            'name' => $item['name'],
                            'amount' => $amount,
                            'tax' => $tax,
                            'total' => $total
                        );
                    }
                }
            }

        }

        return apply_filters('mepp_cart_payment_schedule', $schedule, $remaining_details, $cart);
    }

    /**
     * @brief Display deposit info in cart item meta area
     * @param $item_data
     * @param $cart_item
     * @return array
     */

     public function get_item_data($item_data, $cart_item)
{
    // Check if storewide deposit details are enabled
    $storewide_deposit_enabled_details = get_option('mepp_storewide_deposit_enabled_details', 'yes');
    if ($storewide_deposit_enabled_details !== 'yes') {
        return $item_data; // If not enabled, return the item data as is
    }

    if (!isset(WC()->cart->deposit_info['display_ui']) || WC()->cart->deposit_info['display_ui'] !== true) {
        return $item_data;
    }

    if (isset($cart_item['deposit'], $cart_item['deposit']['enable']) && $cart_item['deposit']['enable'] === 'yes' && isset($cart_item['deposit']['deposit'])) {

        $product = $cart_item['data'];
        if (!$product) {
            return $item_data;
        }

        $tax_display = get_option('mepp_tax_display_cart_item', 'no');

        $deposit = $cart_item['deposit']['deposit'];

        $tax = 0.0;
        $tax_total = 0.0;
        if ($tax_display === 'yes') {
            $tax = $cart_item['deposit']['tax'];
            $tax_total = $cart_item['deposit']['tax_total'];
        }

        $display_deposit = round($deposit + $tax, wc_get_price_decimals());
        $display_remaining = round($cart_item['deposit']['remaining'] + ($tax_total - $tax), wc_get_price_decimals());
        $deposit_amount_text = esc_html__(get_option('mepp_deposit_amount_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');
        if (empty($deposit_amount_text)) {
            $deposit_amount_text = esc_html__('Deposit Amount', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }

        // Append Deposit Amount
        $item_data[] = array(
            'name' => $deposit_amount_text,
            'display' => wc_price($display_deposit, array('ex_tax_label' => $tax_display === 'no')),
            'value' => 'wc_deposit_amount',
        );

        // Append Future Payments
        $future_payment_amount_text = esc_html__(get_option('mepp_second_payment_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');
        if (empty($future_payment_amount_text)) {
            $future_payment_amount_text = esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }
        $item_data[] = array(
            'name' => $future_payment_amount_text,
            'display' => wc_price($display_remaining, array('ex_tax_label' => $tax_display === 'no')),
            'value' => 'wc_deposit_future_payments_amount',
        );

        // Check if _mepp_inherit_storewide_settings is No and _mepp_enable_deposit is Yes
        $inherit_settings = get_post_meta($product->get_id(), '_mepp_inherit_storewide_settings', true);
        $enable_deposit = get_post_meta($product->get_id(), '_mepp_enable_deposit', true);
        $enable_deposit_sitewide = get_option( 'mepp_storewide_deposit_enabled', true);
        $deposit_type = get_post_meta($product->get_id(), '_mepp_amount_type', true); // Fetch deposit type
        $payment_plan_type = get_option('mepp_storewide_deposit_amount_type', '');
            $payment_plan_amount = get_option('mepp_storewide_deposit_amount', '');
        if ($inherit_settings === 'no' && $enable_deposit === 'yes' && $deposit_type !== 'payment_plan') {
            // Append Deposit Type
            $deposit_amount = get_post_meta($product->get_id(), '_mepp_deposit_amount', true);
            if (!empty($deposit_type) && !empty($deposit_amount)) {
                // Check if the deposit type is a percentage
                if ($deposit_type === 'percent') {
                    $deposit_info = $deposit_type . ": " . $deposit_amount . "%";
                } else {
                    $deposit_info = $deposit_type . ": " . wc_price($deposit_amount, array('ex_tax_label' => $tax_display === 'no'));
                }
                $item_data[] = array(
                    'name' => esc_html__('Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'display' => $deposit_info,
                    'value' => 'wc_deposit_type',
                );
            }
        } elseif ($inherit_settings === 'yes' && $enable_deposit_sitewide === 'yes' && $payment_plan_type !== 'payment_plan') {
            // If the storewide deposit type is payment plan, then set the deposit type accordingly
            $payment_plan_type = get_option('mepp_storewide_deposit_amount_type', '');
            $payment_plan_amount = get_option('mepp_storewide_deposit_amount', '');

            if (!empty($payment_plan_type) && !empty($payment_plan_amount)) {
                // Check if the deposit type is a percentage
                if ($payment_plan_type === 'percent') {
                    $deposit_info = $payment_plan_type . ": " . $payment_plan_amount . "%";
                } else {
                    $deposit_info = $payment_plan_type . ": " . wc_price($payment_plan_amount, array('ex_tax_label' => $tax_display === 'no'));
                }
                $item_data[] = array(
                    'name' => esc_html__('Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'display' => $deposit_info,
                    'value' => 'wc_deposit_type',
                );
            }
        }

        if (isset($cart_item['deposit']['payment_plan'])) {
            $payment_plan = get_term_by('id', $cart_item['deposit']['payment_plan'], MEPP_PAYMENT_PLAN_TAXONOMY);
            $item_data[] = array(
                'name' => esc_html__('Payment plan', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'display' => $payment_plan->name,
                'value' => MEPP_PAYMENT_PLAN_TAXONOMY,
            );
            //todo : render payment plan details either per item or in checkout directly ( better checkout)
        }
    }

    return $item_data;
}
    /**
     * @param $cart_item
     * @param $values
     * @return mixed
     */
    public function get_cart_item_from_session($cart_item, $values)
    {

        if (!empty($values['deposit'])) {
            $cart_item['deposit'] = $values['deposit'];
        }
        return $cart_item;
    }


    /**
     * @brief Calculate Deposit and update cart item meta with new values
     * @param $product
     * @param $quantity
     * @param $cart_item_data
     * @param $cart_item_key
     */
    function update_deposit_meta($cart_item, $arg2 = false, $arg3 = false, $arg4 = false)
    {
        if ($arg2 && $arg3 && $arg4) {
            //using deprecated input
            wc_doing_it_wrong('update_deposit_meta', 'update deposit meta accepts a single arg $cart_item', '4.0.0');
            $cart_item = WC()->cart->cart_contents[$arg4];
        }
        $product = $cart_item['data'];
        if (!$product) return;
        if (isset($cart_item['bundled_by'])) $cart_item['deposit']['enable'] = 'no'; //if part of bundle , calculate deposit based on the bundle product

        $override = isset($cart_item['deposit'], $cart_item['deposit']['override']) ? $cart_item['deposit']['override'] : array();
        $deposit_enabled = isset($override['enable']) ? $override['enable'] : mepp_is_product_deposit_enabled($product->get_id());

        //this is only for product based deposits , add cart item data
        if ($deposit_enabled && !empty($cart_item['deposit']) && isset($cart_item['deposit']['enable']) && $cart_item['deposit']['enable'] === 'yes') {
            $item_amount_type = $override['amount_type'] ?? mepp_get_product_deposit_amount_type($product->get_id());
            $amount_or_percentage = $override['amount'] ?? floatval(mepp_get_product_deposit_amount($product->get_id()));
            $selected_plan = $override['payment_plan'] ?? $cart_item['deposit']['payment_plan'] ?? false;
            //item values are set
            $deposit_meta = $this::calculate_deposit_for_cart_item($cart_item, $item_amount_type, $amount_or_percentage, $selected_plan);
        } else {
//            full payment item
            $deposit_meta = $this::calculate_deposit_for_cart_item($cart_item, 'full');
        }
        if (!empty($override)) $deposit_meta['override'] = $override;
        WC()->cart->cart_contents[$cart_item['key']]['deposit'] = apply_filters('mepp_cart_item_deposit_data', $deposit_meta, $cart_item);
    }

    static function calculate_deposit_for_cart_item($cart_item, $item_amount_type, $amount_or_percentage = 0, $selected_plan = false, $plan_override = array(), $taxes_handling = '')
    {
        $enable = 'yes';
        if (mepp_checkout_mode() && !WC()->cart->deposit_info['deposit_enabled'] && MEPP_Cart::checkout_mode_selection() === 'full') {
            $enable = 'no';
        }
        $deposit_meta = array('enable' => $enable);
        $second_payment_due_after = get_option('mepp_second_payment_due_after', '');
        $quantity = $cart_item['quantity'];
        if ($quantity == 0) return $deposit_meta;
        $product = $cart_item['data'];
        $taxes_handling = empty($taxes_handling) ? get_option('mepp_taxes_handling', 'split') : $taxes_handling;
        switch ($item_amount_type) {

            case 'payment_plan':
                //get the plan amounts type , percentage or fixed.
                $plan_amount_type = isset($plan_override['amount_type']) ? $plan_override['amount_type'] : get_term_meta($selected_plan, 'amount_type', true);
                if (empty($plan_amount_type)) $plan_amount_type = 'percentage'; // backward compatibility ,fallback to percentage if type not detected

                if (isset($plan_override['payment_details'])) {
                    $plan_payment_details = $plan_override['payment_details'];
                } else {
                    $plan_payment_details = get_term_meta($selected_plan, 'payment_details', true);
                    $plan_payment_details = json_decode($plan_payment_details, true);
                }

                //details
                if (!is_array($plan_payment_details) || !is_array($plan_payment_details['payment-plan']) || empty($plan_payment_details['payment-plan'])) {
                    return $deposit_meta; // invalid plan details
                }

                //calculate each payment
                $schedule = array();
                $payment_date = current_time('timestamp');


                //get deposit percentage from meta
                $plan_deposit_percentage = isset($plan_override['deposit_percentage']) ? $plan_override['deposit_percentage'] : get_term_meta($selected_plan, 'deposit_percentage', true);
                //we need to calculate total cost in case it is more than 100%
                $plan_total_percentage = floatval($plan_deposit_percentage) + array_sum(array_column($plan_payment_details['payment-plan'], 'percentage'));


                $original_price = $product->get_price();

                if ($enable === 'yes' && intval($plan_total_percentage) !== 100 && WC()->cart->cart_contents[$cart_item['key']]['data']->get_meta('_mepp_recalculated') === 'yes') {
                    $original_price = $product->get_price() / $plan_total_percentage * 100;
                }

                $original_price = round($original_price, wc_get_price_decimals());
                $original_price *= $quantity;
                if ($plan_amount_type === 'fixed') {
                    $plan_total_percentage = 100;
                    $plan_deposit_amount = isset($plan_override['deposit_percentage']) ? $plan_override['deposit_percentage'] : get_term_meta($selected_plan, 'deposit_percentage', true);
                    $plan_total = floatval($plan_deposit_amount) + array_sum(array_column($plan_payment_details['payment-plan'], 'percentage'));
                    $plan_deposit_amount *= $quantity;
                    $plan_total *= $quantity;
                    $plan_tax_total = wc_get_price_including_tax($product, array('price' => $plan_total)) - wc_get_price_excluding_tax($product, array('price' => $plan_total));

                } else {

                    // prepare display of payment plans
                    $plan_total = $original_price / 100 * $plan_total_percentage;
                    $plan_total = round($plan_total, wc_get_price_decimals());
                    $plan_tax_total = wc_get_price_including_tax($product, array('price' => $plan_total)) - wc_get_price_excluding_tax($product, array('price' => $plan_total));
                    $plan_deposit_amount = $original_price / 100 * $plan_deposit_percentage;

                }

                switch ($taxes_handling) {
                    case 'deposit' :
                        $plan_deposit_tax = $plan_tax_total;
                        break;
                    case 'split' :
                        //default tax is the split
                        $plan_deposit_tax = wc_get_price_including_tax($product, array('price' => $plan_deposit_amount)) - wc_get_price_excluding_tax($product, array('price' => $plan_deposit_amount));
                        break;
                    default :
                        $plan_deposit_tax = 0.0;
                        break;
                }
                $plan_deposit_amount = round($plan_deposit_amount, wc_get_price_decimals());
                $plan_deposit_tax = round($plan_deposit_tax, wc_get_price_decimals());
                $plan_tax_total_record = $plan_tax_total - $plan_deposit_tax; // we do this for full amount tax split
                $plan_total_record = $plan_total - $plan_deposit_amount;

                if (wc_prices_include_tax()) {
                    $plan_total -= round(wc_get_price_including_tax($product, array('price' => $plan_total)) - wc_get_price_excluding_tax($product, array('price' => $plan_total)), wc_get_price_decimals());
                    $plan_deposit_amount -= round(wc_get_price_including_tax($product, array('price' => $plan_deposit_amount)) - wc_get_price_excluding_tax($product, array('price' => $plan_deposit_amount)), wc_get_price_decimals());
                }

                $count = 0;
                foreach ($plan_payment_details['payment-plan'] as $plan_detail) {
                    //fix for rounding
                    $count++;
                    $last = $count === count($plan_payment_details['payment-plan']);

                    if (isset($plan_detail['date']) && !empty($plan_detail['date'])) {
                        $payment_date = strtotime($plan_detail['date']);
                    } else {
                        $after = $plan_detail['after'];
                        $after_term = $plan_detail['after-term'];
                        $payment_date = strtotime(date('Y-m-d', $payment_date) . "+{$after} {$after_term}s");
                    }


                    $schedule_line = array();
                    $schedule_line['timestamp'] = $payment_date;
                    if ($last) {
                        $line_amount = $plan_total_record;
                        $line_tax = $plan_tax_total_record;

                        if (wc_prices_include_tax()) {
                            //separate taxes for sake of tax display settings
                            $line_amount -= $line_tax;
                        }
                        $schedule_line['amount'] = round($line_amount, wc_get_price_decimals());
                        $schedule_line['tax'] = round($line_tax, wc_get_price_decimals());
                        $schedule[] = $schedule_line;

                        break;
                    }

                    //set the amount for each payment
                    if ($plan_amount_type === 'fixed') {
                        $line_percentage = round($plan_detail['percentage'] / $plan_total * 100, 1);
                        $line_amount = round($plan_detail['percentage'] * $quantity, wc_get_price_decimals());
                    } else {
                        $line_percentage = $plan_detail['percentage'];
                        $line_amount = round(($original_price / 100 * $line_percentage), wc_get_price_decimals());
                    }
                    $plan_total_record -= $line_amount;

                    //set the tax for each payment
                    switch ($taxes_handling) {
                        case 'deposit' :
                            $line_tax = 0.0;
                            break;
                        case 'split' :
                            //default tax is the split
                            $line_tax = round(wc_get_price_including_tax($product, array('price' => $line_amount)) - wc_get_price_excluding_tax($product, array('price' => $line_amount)), wc_get_price_decimals());
                            break;
                        default :
                            //the tax is being split on all partial payments except deposit
                            $line_tax = round(($plan_tax_total / 100 * $line_percentage), wc_get_price_decimals());
                            break;
                    }

                    $plan_tax_total_record -= $line_tax;

                    if (wc_prices_include_tax()) {
                        //separate taxes for sake of tax display settings
                        $line_amount -= $line_tax;
                    }

                    $schedule_line['amount'] = $line_amount;
                    $schedule_line['tax'] = $line_tax;

                    $schedule[] = $schedule_line;

                }

                //only change pricing when
                if ($plan_amount_type === 'fixed' && WC()->cart->cart_contents[$cart_item['key']]['data']->get_meta('_mepp_recalculated') !== 'yes') {

                    $price_total = $plan_total;
                    if (wc_prices_include_tax()) {
                        $price_total += $plan_tax_total;   //separate taxes for sake of tax display settings
                    }


                    //no need to set pricing while still in cart
                    if (mepp_checkout_mode()) {

                        //make sure we only set the price if deposit is enabled, and revert if disabled
                        if (is_checkout() && WC()->cart->deposit_info['deposit_enabled'] && MEPP_Cart::checkout_mode_selection() === 'deposit') {
                            WC()->cart->cart_contents[$cart_item['key']]['data']->set_price($price_total / $quantity);
                        }

                    } elseif (!mepp_checkout_mode()) {
                        //if not in checkout mode ,can proceed to adjust price directly
                        WC()->cart->cart_contents[$cart_item['key']]['data']->set_price($price_total / $quantity);

                    }
                    WC()->cart->cart_contents[$cart_item['key']]['data']->add_meta_data('_mepp_recalculated', 'yes');

                } else {

                    //if the total percentage is not 100% , then we need to adjust item price first
                    if (intval($plan_total_percentage) !== 100 && WC()->cart->cart_contents[$cart_item['key']]['data']->get_meta('_mepp_recalculated') !== 'yes') {
                        //payment plan total is not exactly 100% so cart item SUBTOTAL is changed.
                        $price_total = $plan_total;
                        if (wc_prices_include_tax()) {
                            $price_total += $plan_tax_total;   //separate taxes for sake of tax display settings
                        }
                        $item_instance = WC()->cart->cart_contents[$cart_item['key']];
                        $item_product = $item_instance['data'];

                        $item_product->set_price(round($price_total, wc_get_price_decimals()) / $quantity);
                        WC()->cart->cart_contents[$cart_item['key']]['data']->add_meta_data('_mepp_recalculated', 'yes');

                    }
                }

                if (mepp_checkout_mode() && !WC()->cart->deposit_info['deposit_enabled'] && MEPP_Cart::checkout_mode_selection() === 'full') {
                    WC()->cart->cart_contents[$cart_item['key']]['data']->set_price($original_price / $quantity);
                }


                $deposit_meta['enable'] = 'yes';
                $deposit_meta['payment_plan'] = $selected_plan;
                $deposit_meta['deposit'] = $plan_deposit_amount;
                $deposit_meta['remaining'] = $plan_total - $plan_deposit_amount;
                $deposit_meta['total'] = $plan_total;
                $deposit_meta['tax_total'] = $plan_tax_total;
                $deposit_meta['tax'] = $plan_deposit_tax;
                $deposit_meta['payment_schedule'] = $schedule;
                break;
            case 'percent':
            case 'fixed':
                $item_price = $cart_item['line_subtotal'];
                $item_tax = $cart_item['line_subtotal_tax'];

                if (wc_prices_include_tax()) {
                    $item_price += $item_tax;
                    $item_price = round($item_price, wc_get_price_decimals());
                }


                $item_deposit_amount = $item_amount_type === 'fixed' ? floatval($amount_or_percentage) : floatval(($item_price * $amount_or_percentage) / 100);


                if ($item_amount_type === 'fixed' && class_exists('WC_Booking') && method_exists($product, 'has_persons') && $product->has_persons() && $product->get_meta('_mepp_enable_per_person', true) == 'yes' && isset($cart_item['booking'], $cart_item['booking']['_persons'])) {
                    $persons = array_sum($cart_item['booking']['_persons']);
                    $item_deposit_amount = $item_deposit_amount * $persons;
                }
                //only update on quantity for fixed , no need for percentage
                if (!mepp_checkout_mode()) {
                    $item_deposit_amount = $item_amount_type === 'fixed' ? $item_deposit_amount * $quantity : $item_deposit_amount;
                }


                switch ($taxes_handling) {
                    case 'deposit' :
                        $item_deposit_tax = $item_tax;
                        break;
                    case 'split' :
                        //default tax is the split
                        $item_deposit_tax = wc_get_price_including_tax($product, array('price' => $item_deposit_amount)) - wc_get_price_excluding_tax($product, array('price' => $item_deposit_amount));
                        break;
                    default :
                        $item_deposit_tax = 0.0;
                        break;
                }

                $item_deposit_amount = round($item_deposit_amount, wc_get_price_decimals());
                $item_deposit_tax = round($item_deposit_tax, wc_get_price_decimals());


                if (wc_prices_include_tax()) {
                    $item_price -= $cart_item['line_subtotal_tax'];
                    $item_deposit_amount -= $item_deposit_tax;

                    if ($item_deposit_amount <= 0) {
                        $item_deposit_amount = 0;
                        if ($taxes_handling === 'deposit' || $taxes_handling === 'split') {
                            $item_deposit_tax = 0;

                        }
                    }
                }

                $deposit_meta['enable'] = 'yes';
                $deposit_meta['deposit'] = $item_deposit_amount;
                $deposit_meta['remaining'] = ($item_price - $item_deposit_amount);
                $deposit_meta['total'] = $item_price;
                $deposit_meta['tax_total'] = $item_tax;
                $deposit_meta['tax'] = $item_deposit_tax;

                $schedule = array();
                // simple deposit , build schedule based on due date if set

                // if second payment has no date then set the date as 1 day after deposit
                if ($deposit_meta['remaining'] > 0 || $item_deposit_tax !== $item_tax) {
                    if (!empty($second_payment_due_after) && is_numeric($second_payment_due_after)) {
                        $after = "+{$second_payment_due_after} days";
                        $payment_date = strtotime(date('Y-m-d', current_time('timestamp')) . "+{$after}");

                    } else {
                        $payment_date = 'unlimited';
                    }
                    $single_payment_data = array();

                    $single_payment_data['timestamp'] = $payment_date;
                    $single_payment_data['amount'] = $deposit_meta['remaining'];
                    $single_payment_data['tax'] = $item_tax - $item_deposit_tax;
                    $schedule[] = $single_payment_data;
                }

                $deposit_meta['payment_schedule'] = $schedule;
                break;
            case 'full':
            default:
                //when an item is paid in full  , just set the values for calculation
                $item_subtotal = $cart_item['line_subtotal'];
                $item_tax = $cart_item['line_subtotal_tax'];
                $deposit_meta['enable'] = 'no';
                $deposit_meta['deposit'] = $item_subtotal;
                $deposit_meta['tax_total'] = $item_tax;
                switch ($taxes_handling) {
                    case 'deposit':
                    case 'split':

                        $deposit_meta['tax'] = $item_tax;
                        break;
                    default:
                        $deposit_meta['tax'] = 0.0;
                        break;
                }

                $deposit_meta['remaining'] = 0;
                $deposit_meta['total'] = $item_subtotal;
                $deposit_meta['payment_schedule'] = array(); //payment schedule is empty since full amount is paid with deposit

                break;
        }


        return $deposit_meta;
    }


    static function calculate_fees_breakdown_for_cart($deposit, $percentage, $cart)
    {
        $fees_handling = get_option('mepp_fees_handling');
        $cart_totals = $cart->get_totals();
        $total_fees = floatval($cart_totals['fee_total']);

        $fees_breakdown = array('deposit' => 0.0, 'deposit_details' => array(), 'remaining_details' => array());
        foreach (WC()->cart->get_fees() as $fee) {

            switch ($fees_handling) {
                case 'deposit':

                    // put entire fees in deposit details
                    $fee_amount = floatval($fee->amount);
                    $fee_tax = 0;
                    if ($fee->tax !== 0) {
                        $fee_tax = $fee->tax;
                    }
                    $deposit_details = array(
                        'name' => $fee->name,
                        'amount' => floatval($fee_amount),
                        'tax' => $fee_tax
                    );

                    $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                    $fees_breakdown['deposit_details'][$fee->id] = $deposit_details;
                    $fees_breakdown['deposit'] += $deposit_details['total'];

                    break;
                case 'split':

                    $fee_amount = round($fee->amount * $percentage / 100, wc_get_price_decimals());
                    $fee_tax = 0;
                    if ($fee->tax !== 0) {
                        $fee_tax = round($fee->tax * $percentage / 100, wc_get_price_decimals());
                    }
                    // put the calculated values in deposit breakdown then calculate and insert remaining
                    $deposit_details = array(
                        'name' => $fee->name,
                        'amount' => floatval($fee_amount),
                        'tax' => $fee_tax
                    );
                    if ($fee->tax !== 0) {
                        $deposit_details['tax'] = $fee_tax;
                    }
                    $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                    $fees_breakdown['deposit_details'][$fee->id] = $deposit_details;

                    //now do remaining
                    $remaining_details = array(
                        'name' => $fee->name,
                        'amount' => floatval($fee->amount - $deposit_details['amount']),
                        'tax' => $fee_tax
                    );
                    if ($fee->tax !== 0) {
                        $remaining_details['tax'] = $fee->tax - $fee_tax;
                    }
                    $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];

                    $fees_breakdown['deposit'] += $deposit_details['total'];
                    $fees_breakdown['remaining_details'][$fee->id] = $remaining_details;

                    break;
                default:
                    // put entire fees in remaining details
                    $fee_amount = floatval($fee->amount);
                    $fee_tax = 0;
                    if ($fee->tax !== 0) {
                        $fee_tax = $fee->tax;
                    }
                    $remaining_details = array(
                        'name' => $fee->name,
                        'amount' => floatval($fee_amount),
                        'tax' => $fee_tax
                    );

                    $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                    $fees_breakdown['remaining_details'][$fee->id] = $remaining_details;

                    break;
            }

        }

        $fees_breakdown['remaining'] = $total_fees - $fees_breakdown['deposit'];

        return apply_filters('mepp_cart_fees_breakdown', $fees_breakdown, $deposit, $percentage, $cart);
    }

    static function calculate_shipping_breakdown_for_cart($deposit, $percentage, $cart)
    {

        $shipping_breakdown = array('deposit' => 0.0, 'remaining' => 0.0, 'deposit_details' => array(), 'remaining_details' => array());
        if (WC()->cart && WC()->cart->needs_shipping()) {


            $shipping_methods = array();
            // Get chosen methods for each package to get our totals.
            foreach (WC()->shipping()->calculate_shipping($cart->get_shipping_packages()) as $key => $package) {
                $chosen_method = wc_get_chosen_shipping_method_for_package($key, $package);
                if ($chosen_method) {
                    $shipping_methods[$key] = $package['rates'][$chosen_method];
                }
            }


            $shipping_handling = get_option('mepp_shipping_handling');
            $cart_totals = $cart->get_totals();
            $total_shipping = floatval($cart_totals['shipping_total']);
            $total_shipping_tax = floatval($cart_totals['shipping_tax']);
            foreach ($shipping_methods as $shipping_method) {

                $shipping_taxes = array_sum($shipping_method->taxes);
                switch ($shipping_handling) {
                    case 'deposit':

                        // put entire fees in deposit details
                        $shipping_amount = floatval($shipping_method->cost);
                        $shipping_tax = $shipping_taxes;

                        $deposit_details = array(
                            'name' => $shipping_method->label,
                            'amount' => floatval($shipping_amount),
                            'tax' => $shipping_tax
                        );

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $shipping_breakdown['deposit_details'][$shipping_method->id] = $deposit_details;
                        $shipping_breakdown['deposit'] += $deposit_details['total'];

                        break;
                    case 'split':

                        $shipping_amount = $shipping_method->cost * $percentage / 100;
                        $shipping_tax = floatval($shipping_taxes) * $percentage / 100;

                        // put the calculated values in deposit breakdown then calculate and insert remaining
                        $deposit_details = array(
                            'name' => $shipping_method->label,
                            'amount' => floatval($shipping_amount),
                        );
                        $deposit_details['tax'] = $shipping_tax;

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $shipping_breakdown['deposit_details'][$shipping_method->id] = $deposit_details;

                        //now do remaining
                        $remaining_details = array(
                            'name' => $shipping_method->label,
                            'amount' => floatval($shipping_method->cost - $deposit_details['amount']),
                        );

                        $remaining_details['tax'] = $shipping_taxes - $shipping_tax;
                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];


                        $shipping_breakdown['deposit'] += $deposit_details['total'];
                        $shipping_breakdown['remaining_details'][$shipping_method->id] = $remaining_details;

                        break;
                    default:
                        // put entire fees in remaining details
                        $shipping_amount = floatval($shipping_method->cost);
                        $shipping_tax = $shipping_taxes;
                        $remaining_details = array(
                            'name' => $shipping_method->label,
                            'amount' => floatval($shipping_amount),
                            'tax' => $shipping_tax
                        );

                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                        $shipping_breakdown['remaining_details'][$shipping_method->id] = $remaining_details;

                        break;
                }


            }

            $shipping_breakdown['remaining'] = $total_shipping + $total_shipping_tax - $shipping_breakdown['deposit'];


        }
        return apply_filters('mepp_cart_shipping_breakdown', $shipping_breakdown, $deposit, $percentage, $cart);
    }

    /**
     * Calculate Discount values for cart
     * @param $deposit
     * @param $cart \WC_Cart
     * @return array
     */
    static function calculate_discount_breakdown_for_cart($deposit, $deposit_tax, $percentage, $cart)
    {

        $discount_handling = get_option('mepp_coupons_handling', 'second_payment');
        $discount_breakdown = array('deposit' => 0.0, 'remaining' => 0.0, 'deposit_details' => array(), 'remaining_details' => array());
        $total_discount = $cart->get_discount_total();
        $total_discount_tax = $cart->get_discount_tax();
        $total_remaining = $cart->get_subtotal() - $deposit;
        $total_remaining_tax = $cart->get_subtotal_tax() - $deposit_tax;
        $deposit_record = $deposit;
        $deposit_tax_record = $deposit_tax;
        $remaining_record = $total_remaining + $total_remaining_tax;
        foreach (WC()->cart->get_coupons() as $code => $coupon) {
            /**
             * @var $coupon \WC_Coupon
             */
            $coupon_code = $coupon->get_code();
            $coupon_total = WC()->cart->get_coupon_discount_amount($code);
            $coupon_tax = WC()->cart->get_coupon_discount_tax_amount($code);

            switch ($discount_handling) {
                case 'deposit':

                    // put entire fees in deposit details
                    $discount_amount = $coupon_total;
                    $discount_tax = $coupon_tax;
                    if ((($discount_amount + $discount_tax) > $deposit + $deposit_tax)) {


                        if (wc_prices_include_tax()){
                            $discount_amount = $deposit;
                        } else {
                            $discount_amount = $deposit - $deposit_tax;

                        }

                        $discount_tax = $deposit_tax;

                        //send remaining record of discount to deposit
                        $remaining_details = array(
                            'name' => $coupon_code,
                            'amount' => floatval($coupon_total - $discount_amount),
                            'tax' => $coupon_tax - $discount_tax
                        );

                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                        $discount_breakdown['remaining_details'][$coupon_code] = $remaining_details;
                        $discount_breakdown['remaining'] += $remaining_details['total'];
                    }

                    if (floatval($discount_amount) > $deposit_record) {
                        $balance = $discount_amount - $deposit_record;
                        $balance_tax = $discount_tax - $deposit_tax_record;
                        $discount_amount = $deposit_record;
                        $discount_tax = $deposit_tax_record;

                        //send remaining record of discount to deposit
                        $remaining_details = array(
                            'name' => $coupon_code,
                            'amount' => floatval($balance),
                            'tax' => $balance_tax
                        );
                    }

                    $deposit_details = array(
                        'name' => $coupon_code,
                        'amount' => floatval($discount_amount),
                        'tax' => $discount_tax
                    );

                    $deposit_record -= (floatval($discount_amount) + $discount_tax);
                    $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                    $discount_breakdown['deposit_details'][$coupon_code] = $deposit_details;
                    $discount_breakdown['deposit'] += $deposit_details['total'];
                    break;
                case 'split':

                    $discount_amount = round($coupon_total * $percentage / 100, wc_get_price_decimals());
                    $discount_tax = round(floatval($coupon_tax) * $percentage / 100, wc_get_price_decimals());
                    // put the calculated values in deposit breakdown then calculate and insert remaining
                    $deposit_details = array(
                        'name' => $coupon_code,
                        'amount' => floatval($discount_amount),
                        'tax' => $discount_tax
                    );

                    $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                    $discount_breakdown['deposit_details'][$coupon_code] = $deposit_details;

                    //now do remaining
                    $remaining_details = array(
                        'name' => $coupon_code,
                        'amount' => floatval($coupon_total - $deposit_details['amount']),
                        'tax' => $coupon_tax - $discount_tax
                    );


                    $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];

                    $discount_breakdown['deposit'] += $deposit_details['total'];
                    $discount_breakdown['remaining_details'][$coupon_code] = $remaining_details;
                    break;
                default:
                    $discount_amount = $coupon_total;
                    $discount_tax = $coupon_tax;

                    if (($discount_amount + $discount_tax) > $total_remaining + $total_remaining_tax) {
                        $discount_amount = $total_remaining;
                        $discount_tax = $total_remaining_tax;

                        //send remaining record of discount to deposit
                        $deposit_details = array(
                            'name' => $coupon_code,
                            'amount' => floatval($coupon_total - $discount_amount),
                            'tax' => $coupon_tax - $discount_tax
                        );

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $discount_breakdown['deposit_details'][$coupon_code] = $deposit_details;
                        $discount_breakdown['deposit'] += $deposit_details['total'];
                    }

                    $remaining_details = array(
                        'name' => $coupon_code,
                        'amount' => floatval($discount_amount),
                        'tax' => $discount_tax
                    );

                    $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                    $discount_breakdown['remaining_details'][$coupon_code] = $remaining_details;

                    break;
            }

        }

        $discount_breakdown['remaining'] = $total_discount + $total_discount_tax - $discount_breakdown['deposit'];

        return apply_filters('mepp_cart_discount_breakdown', $discount_breakdown, $deposit, $percentage, $cart);

    }
    static function get_checkout_mode_available_payment_plans()
    {
        return apply_filters('mepp_checkout_mode_available_payment_plans', get_option('mepp_checkout_mode_payment_plans', array()));
    }

    function get_checkout_payment_plan()
    {
        $payment_plans = self::get_checkout_mode_available_payment_plans();
        if (empty($payment_plans))
            return false;

        $selected_plan = false;
        if (wp_doing_ajax()) {

            if (isset($_POST['post_data'])) {

                parse_str($_POST['post_data'], $post_data);
                $selected_plan = isset($post_data['mepp-selected-plan']) ? $post_data['mepp-selected-plan'] : $payment_plans[0];
            }
            if (isset($_POST['mepp-selected-plan'])) {

                $selected_plan = isset($_POST['mepp-selected-plan']) ? $_POST['mepp-selected-plan'] : $payment_plans[0];
            }
        }
        if (!$selected_plan) {
            //choose first plan as default selected
            foreach ($payment_plans as $key => $plan_id) {
                if (term_exists(absint($plan_id), MEPP_PAYMENT_PLAN_TAXONOMY)) {
                    $selected_plan = $payment_plans[$key];
                    break;
                }
            }

        }

        return $selected_plan;
    }

    static function checkout_mode_selection()
    {
        $checked = get_option('mepp_default_option', 'deposit'); //by default , it's the value from settings
        if (wp_doing_ajax() && isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
            if (isset($post_data['deposit-radio'])) {
                $checked = $post_data['deposit-radio'];
            }

        } elseif (did_action('woocommerce_before_checkout_process') && isset($_POST['deposit-radio'])) {
            //place order scenario , value directly in post
            $checked = $_POST['deposit-radio'];

        }
        return $checked;
    }

    function calculate_deposit_totals($cart)
    {
        //fix issue with WCML when country changes where it runs calculate total on sessionupdate_order_review ajax
        // causing the calculation of wc_get_price_including_tax() to use previous country code instead of the
        // new value as it is not saved yet to customer data by this .
        // Note : did not use is_checkout() cause by this early invocation WOOCOMMERCE_CHECKOUT is not defined yet
        // and even the wp_ajax function wp_ajax_woocommerce_update_order_review is not executed yet
        if (wp_doing_ajax() && isset($_GET['wc-ajax']) && $_GET['wc-ajax'] === 'update_order_review' && !did_action('woocommerce_checkout_update_order_review'))
            return;


        if (!is_array(WC()->cart->deposit_info))
            WC()->cart->deposit_info = array();
        if (!isset(WC()->cart->deposit_info['deposit_enabled']))
            WC()->cart->deposit_info['deposit_enabled'] = false;
        if (!isset(WC()->cart->deposit_info['display_ui']))
            WC()->cart->deposit_info['display_ui'] = false;

        if (mepp_checkout_mode()) {
            // check if deposit is selected
            if (MEPP_Cart::checkout_mode_selection() === 'deposit') {
                WC()->cart->deposit_info['deposit_enabled'] = true;
                WC()->cart->deposit_info['display_ui'] = true;
            } else {
                WC()->cart->deposit_info['deposit_enabled'] = false;
                WC()->cart->deposit_info['display_ui'] = true;

            }

        } else {
            if (MEPP_Cart::is_deposit_in_cart()) {
                WC()->cart->deposit_info['deposit_enabled'] = true;
                WC()->cart->deposit_info['display_ui'] = true;
                //enable deposit for cart in default if an item with active deposit is detected
                // no longer using deposit amount as check because of fixed payment plans where deposit could be more than existing product total
            }
        }


        if (!apply_filters('mepp_deposit_enabled_for_customer', true)) {
            WC()->cart->deposit_info['deposit_enabled'] = false;
            WC()->cart->deposit_info['display_ui'] = false;
        }


        /***
         * final chance to enable / disable deposit using filter.
         * whether deposits are enabled or not we will still do the calculations.
         * This is to allow the utilization of Deposit / full amounts in promos and messages etc
         */
        WC()->cart->deposit_info['deposit_enabled'] = apply_filters('mepp_cart_deposit_enabled', WC()->cart->deposit_info['deposit_enabled'], $cart);
        WC()->cart->deposit_info['display_ui'] = apply_filters('mepp_cart_display_ui', WC()->cart->deposit_info['display_ui'], $cart);
        if (mepp_checkout_mode()) {

            $deposit_amount_meta = get_option('mepp_checkout_mode_deposit_amount');
            $amount_type_meta = get_option('mepp_checkout_mode_deposit_amount_type');
            $selected_plan = $amount_type_meta === 'payment_plan' ? $this->get_checkout_payment_plan() : false;
            switch ($amount_type_meta) {
                case 'payment_plan':
                    $this->has_payment_plans = true;

                    $plan_amount_type = get_term_meta($selected_plan, 'amount_type', true);
                    if (empty($plan_amount_type))
                        $plan_amount_type = 'percentage'; // backward compatibility ,fallback to percentage if type not detected

                    if ($plan_amount_type === 'fixed') {
                        //if plan is fixed , distribute the total over all products
                        $cart_items_count = count(WC()->cart->get_cart_contents());

                        $plan_payment_details = get_term_meta($selected_plan, 'payment_details', true);
                        $plan_payment_details = json_decode($plan_payment_details, true);

                        foreach (WC()->cart->get_cart_contents() as $cart_item_key => $cart_item) {
                            if ($cart_item_key !== $cart_item['key'])
                                $cart_item['key'] = $cart_item_key; //cart item key changed


                            $plan_details = array();
                            foreach ($plan_payment_details['payment-plan'] as $plan_detail) {

                                $plan_detail['percentage'] = $plan_detail['percentage'] / $cart_items_count / $cart_item['quantity'];
                                $plan_details[] = $plan_detail;
                            }
                            $deposit_percentage = get_term_meta($selected_plan, 'deposit_percentage', true);
                            $deposit_percentage = floatval($deposit_percentage) / $cart_items_count / $cart_item['quantity'];
                            $deposit_meta = MEPP_Cart::calculate_deposit_for_cart_item($cart_item, $amount_type_meta, 0, $selected_plan, array('deposit_percentage' => $deposit_percentage, 'payment_details' => array('payment-plan' => $plan_details)));
                            WC()->cart->cart_contents[$cart_item['key']]['deposit'] = apply_filters('mepp_cart_item_deposit_data', $deposit_meta, $cart_item);

                        }
                    } else {
                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                            if ($cart_item_key !== $cart_item['key'])
                                $cart_item['key'] = $cart_item_key; //cart item key changed


                            $deposit_meta = MEPP_Cart::calculate_deposit_for_cart_item($cart_item, $amount_type_meta, 0, $selected_plan);
                            WC()->cart->cart_contents[$cart_item['key']]['deposit'] = apply_filters('mepp_cart_item_deposit_data', $deposit_meta, $cart_item);


                        }

                    }
                    break;

                case 'fixed':

                    if (WC()->cart->get_subtotal() >= $deposit_amount_meta) {
                        $total_amount = $deposit_amount_meta;
                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                            if ($cart_item_key !== $cart_item['key'])
                                $cart_item['key'] = $cart_item_key; //cart item key changed
                            if ($cart_item['line_subtotal'] * $cart_item['quantity'] >= $total_amount) {
                                // the whole deposit fits in this item
                                $item_amount = $total_amount;
                                $total_amount = 0.0;
                            } else {
                                $item_amount = $cart_item['line_subtotal'] * $cart_item['quantity'];
                                $total_amount -= $item_amount;
                            }


                            $deposit_meta = MEPP_Cart::calculate_deposit_for_cart_item($cart_item, $amount_type_meta, $item_amount, $selected_plan);
                            WC()->cart->cart_contents[$cart_item['key']]['deposit'] = apply_filters('mepp_cart_item_deposit_data', $deposit_meta, $cart_item);

                        }
                    } else {

                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                            if ($cart_item_key !== $cart_item['key'])
                                $cart_item['key'] = $cart_item_key; //cart item key changed
                            //disable deposit if it is higher than the total
                            $deposit_meta = MEPP_Cart::calculate_deposit_for_cart_item($cart_item, 'full');
                            WC()->cart->cart_contents[$cart_item['key']]['deposit'] = apply_filters('mepp_cart_item_deposit_data', $deposit_meta, $cart_item);

                        }

                    }
                    break;
                case 'percentage':
                    $amount_type_meta = 'percent'; // fix amount type value from checkout mode
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        if (isset($cart_item['key']) && $cart_item_key !== $cart_item['key'])
                            $cart_item['key'] = $cart_item_key; //cart item key changed
                        $deposit_meta = MEPP_Cart::calculate_deposit_for_cart_item($cart_item, $amount_type_meta, $deposit_amount_meta, $selected_plan);
                        WC()->cart->cart_contents[$cart_item['key']]['deposit'] = apply_filters('mepp_cart_item_deposit_data', $deposit_meta, $cart_item);
                    }
                    break;
            }

        } else {
            //run cart item deposit calculations since some 3rd party plugin such as avatax update cart items at this poind
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if ($cart_item_key !== $cart_item['key'])
                    $cart_item['key'] = $cart_item_key; //cart item key changed
                $this->update_deposit_meta($cart_item);
            }

        }

        //need to readjust pricing once on the checkout level
        if (!$this->recalculated && !did_action('woocommerce_subscription_cart_before_grouping')) {
            $this->recalculated = true;
            WC()->cart->calculate_totals();
        }
        //each item already assigned , now we can get item deposit and tax deposit from values
        $items_deposit_total = 0.0;
        $items_tax_total = 0.0;
        $deposit_details = array('items' => array());
        foreach ($cart->get_cart() as $item_key => $cart_item) {
            $deposit_meta = $cart_item['deposit'];
            if (!isset($deposit_meta['deposit']))
                continue;
            $items_deposit_total += $deposit_meta['deposit'];
            $items_tax_total += $deposit_meta['tax'];
            $deposit_details['items'][$item_key] = array(
                'name' => $cart_item['data']->get_name(),
                'quantity' => $cart_item['quantity'],
                'amount' => floatval($deposit_meta['deposit']),
                'tax' => floatval($deposit_meta['tax']),
                'total' => floatval($deposit_meta['deposit']) + floatval($deposit_meta['tax'])
            );
        }

        if (floatval(WC()->cart->get_subtotal()) <= $items_deposit_total && apply_filters('mepp_enable_if_deposit_equal_full', false)) {
            WC()->cart->deposit_info['deposit_enabled'] = false;
            WC()->cart->deposit_info['display_ui'] = false;
        }


        $division = $cart->get_subtotal() == 0 ? 1 : $cart->get_subtotal();
        $deposit_percentage = round($items_deposit_total / $division * 100, 1);


        $remaining_details = array();
        $fees = $this::calculate_fees_breakdown_for_cart($items_deposit_total, $deposit_percentage, $cart);
        $deposit_details['fees'] = $fees['deposit_details'];
        $remaining_details['fees'] = $fees['remaining_details'];

        $shipping = $this::calculate_shipping_breakdown_for_cart($items_deposit_total, $deposit_percentage, $cart);
        $deposit_details['shipping'] = $shipping['deposit_details'];
        $remaining_details['shipping'] = $shipping['remaining_details'];
        $discount = $this::calculate_discount_breakdown_for_cart($items_deposit_total, $items_tax_total, $deposit_percentage, $cart);
        $deposit_details['discount'] = $discount['deposit_details'];
        $remaining_details['discount'] = $discount['remaining_details'];

        $deposit_breakdown = array(
            'cart_items' => $items_deposit_total,
            'taxes' => $items_tax_total,
            'fees' => $fees['deposit'],
            'shipping' => $shipping['deposit'],
            'discount' => $discount['deposit']
        );
        $total_deposit = $items_deposit_total + $items_tax_total + $fees['deposit'] + $shipping['deposit'];

        $total_deposit -= $discount['deposit'];
        $total_deposit = round($total_deposit, wc_get_price_decimals());

        $total_deposit = apply_filters('woocommerce_deposits_cart_deposit_amount', $total_deposit, $cart->get_total('edit'), $cart);

        //backward compatibility
        if (has_filter('woocommerce_deposits_cart_deposit_amount')) {
            if ($total_deposit === $cart->get_total('edit')) {
                wc_doing_it_wrong('woocommerce_deposits_cart_deposit_amount', 'disabling deposit by setting cart amount to total is no longer supported, please use the filter mepp_cart_deposit_enabled instead.', '4.0.0');
                add_filter('mepp_cart_deposit_enabled', '__return_false'); //still disable
                add_filter('mepp_cart_display_ui', '__return_false'); //still disable
            }
        }


        $deposit_enabled = WC()->cart->deposit_info['deposit_enabled'];
        $display_ui = WC()->cart->deposit_info['display_ui'];

        $cart->deposit_info = array();
        $cart->deposit_info['deposit_breakdown'] = $deposit_breakdown;
        $cart->deposit_info['deposit_amount'] = $total_deposit;
        $cart->deposit_info['has_payment_plans'] = $this->has_payment_plans;
        $payment_schedule = $this::build_payment_schedule($remaining_details, $cart);

        $cart->deposit_info['payment_schedule'] = $payment_schedule;
        $cart->deposit_info['deposit_enabled'] = $deposit_enabled;
        $cart->deposit_info['deposit_details'] = $deposit_details;
        $cart->deposit_info['display_ui'] = $display_ui;


    }

    /**
     * @brief This function is deprecated, kept to avoid errors in case it was called directly
     * @param mixed $cart_total ...
     * @param mixed $cart ...
     *
     * @return float
     * @deprecated 4.0
     */
    public
    function calculated_total(
        $cart_total
    ) {
        //ignore this warning if the function is triggered within this class , the only purpose is to display only for any code triggering it externally
        if (strpos(wp_debug_backtrace_summary(), 'MEPP_Cart->calculated_total') === false) {
            wc_doing_it_wrong('calculated_total', 'This function is no longer used during calculations, refer to function "calculate_deposit_totals"', '4.0.0');
        }

        //backward compatibility for deposit amount being set as cart total to disable deposit
        $total_deposit = apply_filters('woocommerce_deposits_cart_deposit_amount', 0, $cart_total, WC()->cart);
        if (has_filter('woocommerce_deposits_cart_deposit_amount')) {
            if ($total_deposit === $cart_total) {
                wc_doing_it_wrong('woocommerce_deposits_cart_deposit_amount', 'disabling deposit by setting cart amount to total is no longer supported, please use the filter mepp_cart_deposit_enabled instead.', '4.0.0');
                add_filter('mepp_cart_deposit_enabled', '__return_false'); //still disable
                add_filter('mepp_cart_display_ui', '__return_false'); //still disable
            }
        }
        return $cart_total;
    }

    /**
     * @brief Display Deposit and remaining amount in cart totals area
     */
 public function cart_totals_after_order_total() {
     // Check if storewide deposit details are enabled
     $storewide_deposit_enabled_details = get_option('mepp_storewide_deposit_enabled_details', 'yes');
     if ($storewide_deposit_enabled_details === 'no') {
        return;
    }

    if (isset(WC()->cart->deposit_info['display_ui']) && WC()->cart->deposit_info['display_ui'] === true):

        $to_pay_text = esc_html__(get_option('mepp_to_pay_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');
        $future_payment_text = esc_html__(get_option('mepp_second_payment_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');


        if ($to_pay_text === false || $to_pay_text === '') {
            $to_pay_text = esc_html__('To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }

        if ($future_payment_text === false || $future_payment_text === '') {
            $future_payment_text = esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }
        $to_pay_text = stripslashes($to_pay_text);
        $future_payment_text = stripslashes($future_payment_text);


        $deposit_breakdown_tooltip = mepp_deposit_breakdown_tooltip();

        ?>
        <tr class="order-paid">
            <th>
                <?php echo $to_pay_text ?>&nbsp;&nbsp;
                <?php echo $deposit_breakdown_tooltip; ?>
            </th>
            <td data-title="<?php echo $to_pay_text; ?>">
                <strong>
                    <?php echo wc_price(WC()->cart->deposit_info['deposit_amount']); ?>
                </strong>
            </td>
        </tr>
        <tr class="order-remaining">
            <th>
                <?php echo $future_payment_text; ?>
            </th>
            <td data-title="<?php echo $future_payment_text; ?>">
                <strong>
                    <?php echo wc_price(WC()->cart->get_total('edit') - WC()->cart->deposit_info['deposit_amount']); ?>
                </strong>
            </td>
        </tr>
    <?php
    endif;
}



    function cart_needs_payment($needs_payment)
    {

        if (mepp_checkout_mode() && wp_doing_ajax() && isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
            if (isset($post_data['deposit-radio']) && $post_data['deposit-radio'] !== 'deposit')
                return $needs_payment;
        }

        $deposit_enabled = isset(WC()->cart->deposit_info['deposit_enabled'], WC()->cart->deposit_info['deposit_amount'])
            && WC()->cart->deposit_info['deposit_enabled'] === true && WC()->cart->deposit_info['deposit_amount'] <= 0;


        if ($deposit_enabled) {
            $needs_payment = false;
        }
        return $needs_payment;

    }

    /**
     *  method to determine if there is deposit in cart early before deposit calculation functions are triggered
     * @return bool
     */
    static function is_deposit_in_cart()
    {
        $deposit_in_cart = false;
        if (mepp_checkout_mode()) {
            if (wp_doing_ajax()) {

                if (isset($_POST['post_data'])) {
                    parse_str($_POST['post_data'], $post_data);
                }
                if (isset($post_data['deposit-radio']) && $post_data['deposit-radio'] === 'deposit') {
                    $deposit_in_cart = true;
                } elseif (isset($_POST['deposit-radio']) && $_POST['deposit-radio'] === 'deposit') {
                    //final calculation when order is placed
                    $deposit_in_cart = true;
                }

            }

        } else {
            if (WC()->cart && !empty(WC()->cart->get_cart())) {
                foreach (WC()->cart->get_cart() as $item) {
                    if (isset($item['deposit'], $item['deposit']['enable']) && $item['deposit']['enable'] === 'yes') {
                        $deposit_in_cart = true;
                        break;
                    }
                }
            }


        }
        return $deposit_in_cart;
    }
}

