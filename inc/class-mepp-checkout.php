<?php
namespace MagePeople\MEPP;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class MEPP_Checkout
 */
class MEPP_Checkout
{

    public $deposit_enabled;
    public $deposit_amount;
    public $second_payment;

    /**
     * MEPP_Checkout constructor.
     */
    public function __construct()
    {

        if (mepp_checkout_mode()) {


            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 100);
            add_action('woocommerce_checkout_update_order_review', array($this, 'update_order_review'), 10, 1);
            add_action('woocommerce_review_order_after_order_total', array($this, 'checkout_deposit_button'), 50);
        }
        add_action('woocommerce_checkout_order_processed', array($this, 'custom_change_default_order_status'), 10, 3);
        add_action('wp_footer', array($this, 'modify_order_statuses_inline_script'));
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'checkout_create_order_line_item'), 10, 4);
        add_action('woocommerce_deposits_after_create_order', array($this, 'checkout_update_order_meta'), 10);
        add_action('woocommerce_review_order_after_order_total', array($this, 'review_order_after_order_total'));
        // Hook the payments gateways filter to remove the ones we don't want
        add_filter('woocommerce_available_payment_gateways', array($this, 'available_payment_gateways'));

    }

      /**
     * @brief enqeueue scripts
     */
    public function enqueue_scripts()
    {
        $allowed_html = array(
            'strong' => array(),
            'p' => array(),
            'br' => array(),
            'em' => array(),
            'b' => array(),
            's' => array(),
            'strike' => array(),
            'del' => array(),
            'u' => array(),
            'i' => array(),
            'a' => array(
                'href' => array()
            )
        );

        wp_enqueue_script('wc-deposits-checkout', MEPP_PLUGIN_URL . '/assets/js/add-to-cart.js', array('jquery', 'wc-checkout'), MEPP_VERSION, true);
        $message_deposit = wp_kses(__(get_option('mepp_message_deposit'), 'advanced-partial-payment-or-deposit-for-woocommerce'), $allowed_html);
        $message_full_amount = wp_kses(__(get_option('mepp_message_full_amount'), 'advanced-partial-payment-or-deposit-for-woocommerce'), $allowed_html);

        $message_deposit = stripslashes($message_deposit);
        $message_full_amount = stripslashes($message_full_amount);

        $script_args = array(
            'message' => array(
                'deposit' => $message_deposit,
                'full' => $message_full_amount
            )
        );
        wp_localize_script('wc-deposits-checkout', 'mepp_checkout_options', $script_args);

        // prepare inline styles
        $colors = get_option('mepp_deposit_buttons_colors');
        $fallback_colors = mepp_woocommerce_frontend_colours();
        $gstart = $colors['primary'] ? $colors['primary'] : $fallback_colors['primary'];
        $secondary = $colors['secondary'] ? $colors['secondary'] : $fallback_colors['secondary'];
        $highlight = $colors['highlight'] ? $colors['highlight'] : $fallback_colors['highlight'];
        $gend = mepp_adjust_colour($gstart, 15);


        $style = "@media only screen {
            #wc-deposits-options-form input.input-radio:enabled ~ label { color: {$secondary}; }
            #wc-deposits-options-form div a.wc-deposits-switcher {
              background-color: {$gstart};
              background: -moz-gradient(center top, {$gstart} 0%, {$gend} 100%);
              background: -moz-linear-gradient(center top, {$gstart} 0%, {$gend} 100%);
              background: -webkit-gradient(linear, left top, left bottom, from({$gstart}), to({$gend}));
              background: -webkit-linear-gradient({$gstart}, {$gend});
              background: -o-linear-gradient({$gstart}, {$gend});
              background: linear-gradient({$gstart}, {$gend});
            }
            #wc-deposits-options-form .amount { color: {$highlight}; }
            #wc-deposits-options-form .deposit-option { display: inline; }
          }";

        wp_enqueue_style('wc-deposits-frontend-styles-checkout-mode', MEPP_PLUGIN_URL . '/assets/css/admin-style.css', array(), MEPP_VERSION);
        wp_add_inline_style('wc-deposits-frontend-styles-checkout-mode', $style);


    }
    
            function custom_change_default_order_status($order_id, $posted_data, $order) {
                // Change the default order status to 'processing' for new orders
                $order->update_status('processing');
            }

            function modify_order_statuses_inline_script() {
                ?>
                <script>
                jQuery(document).ready(function(){
                    // Find the cell containing "Processing" and replace it with "Partially Paid"
                    jQuery('.woocommerce-table tbody td:contains("Processing")').text('Partially Paid');

                    // Find the cell containing "On hold" and replace it with "Completed"
                    jQuery('.woocommerce-table tbody td:contains("On hold")').text('Completed');
                });
                </script>
                <?php
            }

            // Modify order status in the admin panel
            function custom_modify_order_status_admin_panel($order_id, $old_status, $new_status) {
                // Check if the new status is 'processing'
                if ($new_status === 'processing') {
                    // Update the order status to 'Partially Paid' for partial payment
                    $order = wc_get_order($order_id);
                    if ($order && $order->get_total() > $order->get_total_paid()) {
                        $order->update_status('partially-paid');
                    }
                }
            }


    /**
     *
     * @param $posted_data_string
     */
    public function update_order_review($posted_data_string)
    {

        parse_str($posted_data_string, $posted_data);
        if (!is_array(WC()->cart->deposit_info)) WC()->cart->deposit_info = array();
        if (isset($posted_data['deposit-radio']) && $posted_data['deposit-radio'] === 'deposit') {
            WC()->cart->deposit_info['deposit_enabled'] = true;
            WC()->session->set('deposit_enabled', true);
        } elseif (isset($posted_data['deposit-radio']) && $posted_data['deposit-radio'] === 'full') {
            WC()->cart->deposit_info['deposit_enabled'] = false;
            WC()->session->set('deposit_enabled', false);
        } else {
            $default = get_option('mepp_default_option');
            WC()->cart->deposit_info['deposit_enabled'] = $default === 'deposit' ? true : false;
            WC()->session->set('deposit_enabled', $default === 'deposit' ? true : false);
        }

    }

    /**
     * @brief shows Deposit slider in checkout mode
     */
    public function checkout_deposit_button()
    {

        if (!apply_filters('mepp_deposit_enabled_for_customer', true)) {
            return;
        }
        if (isset(WC()->cart->deposit_info, WC()->cart->deposit_info['display_ui']) && WC()->cart->deposit_info['display_ui'] !== true) {
            return;
        }

        $force_deposit = get_option('mepp_checkout_mode_force_deposit');
        $deposit_amount = get_option('mepp_checkout_mode_deposit_amount');
        $amount_type = get_option('mepp_checkout_mode_deposit_amount_type');

        if ($amount_type === 'fixed' && $deposit_amount >= WC()->cart->total) {
            return;
        }

        $default_checked = get_option('mepp_default_option', 'deposit');
        $basic_buttons = get_option('mepp_use_basic_radio_buttons', true) === 'yes';
        $deposit_text = esc_html__(get_option('mepp_button_deposit'), 'advanced-partial-payment-or-deposit-for-woocommerce');
        $full_text = esc_html__(get_option('mepp_button_full_amount'), 'advanced-partial-payment-or-deposit-for-woocommerce');
        $deposit_option_text = esc_html__(get_option('mepp_deposit_option_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');

        $post_data = array();

        if ($deposit_text === false) {

            $deposit_text = esc_html__('Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce');

        }
        if ($full_text === false) {
            $full_text = esc_html__('Full Amount', 'advanced-partial-payment-or-deposit-for-woocommerce');

        }

        if ($deposit_option_text === false) {
            $deposit_option_text = esc_html__('Deposit Option', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }

        $deposit_text = stripslashes($deposit_text);
        $full_text = stripslashes($full_text);
        $deposit_option_text = stripslashes($deposit_option_text);
        $selected_plan = '';
        $payment_plans = array();
        $amount = isset(WC()->cart->deposit_info, WC()->cart->deposit_info['deposit_amount']) ? WC()->cart->deposit_info['deposit_amount'] : 0.0;
        $has_payment_plans = isset(WC()->cart->deposit_info, WC()->cart->deposit_info['has_payment_plans']) && WC()->cart->deposit_info['has_payment_plans'];


        if (wp_doing_ajax() && isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
            if (isset($post_data['deposit-radio'])) {
                $default_checked = $post_data['deposit-radio'];
            }

        }

        if ($has_payment_plans) {

            $available_plans_meta = MEPP()->cart::get_checkout_mode_available_payment_plans();
            $available_plans = get_terms(array(
                    'taxonomy' => MEPP_PAYMENT_PLAN_TAXONOMY,
                    'hide_empty' => false,
                    'include' => $available_plans_meta,
                )
            );
            if (wp_doing_ajax() && !empty($post_data)) {

                if (isset($post_data['mepp-selected-plan']) && in_array($post_data['mepp-selected-plan'], $available_plans_meta)) {
                    $selected_plan = $post_data['mepp-selected-plan'];
                }
            }
            foreach ($available_plans as $available_plan) {

                $plan_id = $available_plan->term_id;
                //get plan details from meta
                $deposit_percentage = get_term_meta($plan_id, 'deposit_percentage', true);

                $division = $deposit_percentage == 0 ? 1 : $deposit_percentage;
                //calculate deposit amount for the plan
                $deposit_amount = WC()->cart->get_subtotal() / 100 * $division;


                //details
                $payment_details = get_term_meta($plan_id, 'payment_details', true);
                $payment_details = json_decode($payment_details, true);

                if (!is_array($payment_details) || !is_array($payment_details['payment-plan']) || empty($payment_details['payment-plan'])) {
                    return;
                }
                $payment_plans[$available_plan->term_id] = array(
                    'name' => $available_plan->name,
                    'amount' => $deposit_amount,
                    'details' => $payment_details
                );


            }
        }

        $args = array(
            'force_deposit' => $force_deposit,
            'deposit_amount' => $amount,
            'basic_buttons' => $basic_buttons,
            'deposit_text' => $deposit_text,
            'full_text' => $full_text,
            'deposit_option_text' => $deposit_option_text,
            'default_checked' => $default_checked,
            'has_payment_plan' => $has_payment_plans,
            'payment_plans' => $payment_plans,
            'selected_plan' => $selected_plan,
        );

        wc_get_template('mepp-checkout-mode-slider.php', $args, '', MEPP_TEMPLATE_PATH);

    }

    /**
     * @brief adds deposit meta to order line item when created
     * @param $item
     * @param $cart_item_key
     * @param $values
     * @param $order
     */
    public function checkout_create_order_line_item($item, $cart_item_key, $values, $order)
    {

        if ($order->get_type() === 'mepp_payment') return;

        $deposit_meta = isset($values['deposit']) ? $values['deposit'] : false;

        if ($deposit_meta) {
            $item->add_meta_data('wc_deposit_meta', $deposit_meta, true);
        }


    }

    /**
     * @brief Display deposit value in checkout order totals review area
     */
  public function review_order_after_order_total()
{
     // Check if storewide deposit details are enabled
     $storewide_deposit_enabled_details = get_option('mepp_storewide_deposit_enabled_details', 'yes');
     if ($storewide_deposit_enabled_details === 'no') {
        return;
    }

    $display = false;
    if (mepp_checkout_mode()) {
        if (MEPP_Cart::checkout_mode_selection() !== 'full' && isset(WC()->cart->deposit_info['display_ui']) && WC()->cart->deposit_info['display_ui'] === true) {
            $display = true;
        }
    } else {
        if (isset(WC()->cart->deposit_info['display_ui']) && WC()->cart->deposit_info['display_ui'] === true) {
            $display = true;
        }
    }

    if ($display) {

        $to_pay_text = esc_html__(get_option('mepp_to_pay_text', esc_html__('To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce')), 'advanced-partial-payment-or-deposit-for-woocommerce');
        $future_payment_text = esc_html__(get_option('mepp_second_payment_text', esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce')), 'advanced-partial-payment-or-deposit-for-woocommerce');
        $to_pay_text = stripslashes($to_pay_text);
        $future_payment_text = stripslashes($future_payment_text);
        $deposit_breakdown_tooltip = mepp_deposit_breakdown_tooltip();


        ?>

        <tr class="order-paid">
            <th><?php echo $to_pay_text; ?><?php echo $deposit_breakdown_tooltip ?>  </th>
            <td data-title="<?php echo $to_pay_text; ?>">
                <strong><?php echo wc_price(WC()->cart->deposit_info['deposit_amount']); ?></strong>
            </td>
        </tr>
        <tr class="order-remaining">
            <th><?php echo $future_payment_text; ?></th>
            <td data-title="<?php echo $future_payment_text; ?>">
                <strong><?php echo wc_price(WC()->cart->get_total('edit') - WC()->cart->deposit_info['deposit_amount']); ?></strong>
            </td>
        </tr>
        <?php
    }
}


    /**
     * @brief Updates the order metadata with deposit information
     *
     * @param $order_id
     * @return void
     */
    public
    function checkout_update_order_meta($order)
    {

        if ($order->get_type() === 'mepp_payment') {
            return;
        }

        if (isset(WC()->cart->deposit_info['deposit_enabled']) && WC()->cart->deposit_info['deposit_enabled'] === true) {

            $deposit = WC()->cart->deposit_info['deposit_amount'];
            $second_payment = WC()->cart->get_total('edit') - $deposit;
            $deposit_breakdown = WC()->cart->deposit_info['deposit_breakdown'];
            $sorted_schedule = WC()->cart->deposit_info['payment_schedule'];
            $deposit_details = WC()->cart->deposit_info['deposit_details'];
            foreach (WC()->cart->get_cart_contents() as $item_key => $item) {
                $item_key = $item['key'];
                if (isset($deposit_details['items'][$item_key])) {
                    $item_discount = $item['line_subtotal'] - $item['line_total'];
                    $item_details = $deposit_details['items'][$item_key];
                    if ($item_discount !== 0.0) {
                        $division = $item['line_subtotal'] == 0 ? 1 : $item['line_subtotal'];

                        $percentage = round($item_details['amount'] / $division * 100, 1);
                        $deposit_details['items'][$item_key]['subtotal'] = $item['line_total'] / 100 * $percentage;
                        $deposit_details['items'][$item_key]['subtotal_tax'] = $item['line_tax'] / 100 * $percentage;

                    }
                }
            }
            $deposit_data = array(
                'id' => '',
                'title' => esc_html__('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'deposit',
                'timestamp' => current_time('timestamp'),
                'total' => $deposit,
                'details' => $deposit_details,
            );


            $sorted_schedule = array('deposit' => $deposit_data) + $sorted_schedule;

            $order->add_meta_data('_mepp_payment_schedule', $sorted_schedule, true);
            $order->add_meta_data('_mepp_order_version', MEPP_VERSION, true);
            $order->add_meta_data('_mepp_order_has_deposit', 'yes', true);
            $order->add_meta_data('_mepp_deposit_paid', 'no', true);
            $order->add_meta_data('_mepp_second_payment_paid', 'no', true);
            $order->add_meta_data('_mepp_deposit_amount', $deposit, true);
            $order->add_meta_data('_mepp_second_payment', $second_payment, true);
            $order->add_meta_data('_mepp_deposit_breakdown', $deposit_breakdown, true);
            $order->add_meta_data('_mepp_deposit_payment_time', ' ', true);
            $order->add_meta_data('_mepp_second_payment_reminder_email_sent', 'no', true);
            $order->save();


        } elseif (isset(WC()->cart->deposit_info['deposit_enabled']) && WC()->cart->deposit_info['deposit_enabled'] !== true) {
            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

            if ($order_has_deposit === 'yes') {

                $order->delete_meta_data('_mepp_payment_schedule');
                $order->delete_meta_data('_mepp_order_version');
                $order->delete_meta_data('_mepp_order_has_deposit');
                $order->delete_meta_data('_mepp_deposit_paid');
                $order->delete_meta_data('_mepp_second_payment_paid');
                $order->delete_meta_data('_mepp_deposit_amount');
                $order->delete_meta_data('_mepp_second_payment');
                $order->delete_meta_data('_mepp_deposit_breakdown');
                $order->delete_meta_data('_mepp_deposit_payment_time');
                $order->delete_meta_data('_mepp_second_payment_reminder_email_sent');

                // remove deposit meta from items
                foreach ($order->get_items() as $order_item) {
                    $order_item->delete_meta_data('wc_deposit_meta');
                    $order_item->save();
                }
                $order->save();

            }
        }
    }

    /**
     * @brief Removes the unwanted gateways from the settings page when there's a deposit
     *
     * @return mixed
     */
    public function available_payment_gateways($gateways)
    {
        $has_deposit = false;

        $pay_slug = get_option('woocommerce_checkout_pay_endpoint', 'order-pay');
        $order_id = absint(get_query_var($pay_slug));
        $is_paying_deposit = true;
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if (!$order || $order->get_type() !== 'mepp_payment') return $gateways;

            $has_deposit = true;

            if ($order->get_meta('_mepp_payment_type', true) !== 'deposit') {

                $is_paying_deposit = false;
            }


        } else {
            $is_paying_deposit = true;

            if (mepp_checkout_mode() && wp_doing_ajax() && isset($_POST['post_data'])) {
                parse_str($_POST['post_data'], $post_data);

                if (isset($post_data['deposit-radio']) && $post_data['deposit-radio'] === 'deposit') {
                    $has_deposit = true;
                }

            } else {
                if (isset(WC()->cart->deposit_info) && isset(WC()->cart->deposit_info['deposit_enabled']) && WC()->cart->deposit_info['deposit_enabled'] === true) {
                    $has_deposit = true;
                }
            }

        }


        if ($has_deposit) {

            if ($is_paying_deposit) {
                $disallowed_gateways = get_option('mepp_disallowed_gateways_for_deposit');

            } else {
                $disallowed_gateways = get_option('mepp_disallowed_gateways_for_second_payment');

            }

            if (is_array($disallowed_gateways)) {
                foreach ($disallowed_gateways as $value) {
                    unset($gateways[$value]);
                }
            }

        }
        return $gateways;
    }
}
