<?php

namespace MagePeople\MEPP;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 *   Admin Order functionality
 *
 */
class MEPP_Admin_Order
{
    /**
     * MEPP_Admin_Order constructor.
     */
    public function __construct()
    {
        if (!mepp_checkout_mode()) {
            add_action('woocommerce_admin_order_item_headers', array($this, 'admin_order_item_headers'));
            add_action('woocommerce_admin_order_item_values', array($this, 'admin_order_item_values'), 10, 3);
        }

        // Hook the order admin page
        $hpos_enabled = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
        if ($hpos_enabled) {
            add_action('admin_print_scripts-woocommerce_page_wc-orders', array($this, 'enqueue_scripts'));

        } else {
            add_action('admin_enqueue_scripts', array($this, 'legacy_enqueue_scripts'));

        }

        add_action('woocommerce_admin_order_totals_after_total', array($this, 'admin_order_totals_after_total'));
        add_action('wp_ajax_mepp_recalculate_deposit', array($this, 'recalculate_deposit_callback'));
        add_action('woocommerce_order_item_add_action_buttons', array($this, 'recalculate_deposit_button'));
        add_filter('woocommerce_order_actions', array($this, 'order_actions'), 10, 2);
        add_filter('woocommerce_resend_order_emails_available', array($this, 'resend_order_emails_available'));

        add_action('woocommerce_ajax_add_order_item_meta', array($this, 'add_order_item_meta'), 10, 2);
        add_action('wp_ajax_mepp_reload_partial_payments_metabox', array($this, 'ajax_partial_payments_summary'), 10);
        add_action('wp_ajax_mepp_get_recalculate_deposit_modal', array($this, 'get_recalculate_deposit_modal'), 10);

        add_action('add_meta_boxes', array($this, 'partial_payments_metabox'), 31, 2);

        add_filter('request', array($this, 'request_query'));

        //reminder for after X days setting
        add_action('woocommerce_order_action_customer_second_payment_reminder', array($this, 'customer_second_payment_reminder'));

        add_action('admin_footer-woocommerce_page_wc-orders--mepp_payment', array($this, 'remove_statuses_for_partial_payment'));

        add_action('woocommerce_order_after_calculate_totals', array($this, 'totals_recalculated_callback'), 10, 2);

        add_action('woocommerce_process_shop_order_meta', array($this, 'process_payment_date_datepicker_values'));


        add_action('woocommerce_order_status_partially-paid', array($this, 'early_update_partial_payments'), 0);

    }

    /**
     * Automatically complete deposit partial payment when main order is set to partially-paid
     * @param $order_id
     * @return void
     */
    public function early_update_partial_payments($order_id)
    {
        if (!did_action('woocommerce_process_shop_order_meta')) return;
        //limit the screen id

        $order = wc_get_order($order_id);
        if (!$order) return;

        $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

        if (!is_array($payment_schedule) || empty($payment_schedule)) return;
        //manually mark deposit partial payment as completed
        foreach ($payment_schedule as $payment) {
            if ($payment['type'] !== 'deposit') continue;
            $partial_payment = wc_get_order($payment['id']);
            if ($partial_payment && $partial_payment->needs_payment()) {
                $partial_payment->set_status('completed');
                $partial_payment->save();
            }

        }

        $order->update_meta_data('_mepp_deposit_paid', 'yes');
        $order->update_meta_data('_mepp_second_payment_paid', 'no');
        $order->update_meta_data('_mepp_deposit_payment_time', current_time('timestamp'));
        $order->update_meta_data('_mepp_second_payment_reminder_email_sent', 'no');

    }


    /**
     * Order recalculated callback to trigger maybe_adjust_deposit_order_total
     * @param $and_taxes
     * @param $order
     */
    function totals_recalculated_callback($and_taxes, $order)
    {
        $this->maybe_adjust_deposit_order_total($order);
    }

    /**
     * Maybe adjust partial payments amounts in case order total is changed
     * @param $order
     * @return void
     */
    function maybe_adjust_deposit_order_total($order)
    {


        $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

        if (!empty($payment_schedule) && is_array($payment_schedule)) {

            $total = 0.0;

            $due_payments = array();
            $due_payments_total = 0.0;

            foreach ($payment_schedule as $payment) {

                $payment_order = wc_get_order($payment['id']);
                if ($payment_order) {
                    if ($payment['type'] !== 'deposit' && $payment_order->get_status() !== 'completed') {
                        $due_payments[] = $payment_order;
                        $due_payments_total += floatval($payment_order->get_total());

                    }

                    $total += $payment_order->get_total();
                }

            }
            if ($total <= 0) return;
            $difference = floatval($order->get_total()) - $total;
            if ($difference > 0 || $difference < 0) {

                $positive = $difference > 0;
                //rounding fix
                $difference = abs($difference);
                $diff_record = $difference;
                $count = 0;

                foreach ($due_payments as $due_payment) {
                    $count++;
                    //calculate percentage
                    $percentage = floatval($due_payment->get_total()) / $due_payments_total * 100;
                    $amount = $difference / 100 * $percentage;
                    if (count($due_payments) === $count) {
                        //last item
                        $amount = $diff_record;
                    } else {
                        $diff_record -= $amount;
                    }

                    $itemized = $order->get_meta('_mepp_itemized_payments') === 'yes';
                    if ($itemized) {

                        $item = new \WC_Order_Item_Fee();
                        if (!$positive) {
                            $amount *= -1;
                        }
                        $item->set_props(
                            array(
                                'total' => $amount
                            )
                        );
                        $partial_payment_adjustment = apply_filters('mepp_partial_payment_adjustment_title', esc_html__('Amount adjustment', 'advanced-partial-payment-or-deposit-for-woocommerce'), $due_payment);
                        $item->set_name($partial_payment_adjustment);
                        $due_payment->add_item($item);
                        $due_payment->set_total($amount);

                    } else {
                        foreach ($due_payment->get_fees() as $item) {
                            if ($positive) {
                                $item->set_total(floatval($item->get_total()) + $amount);

                            } else {
                                $item->set_total(floatval($item->get_total()) - $amount);

                            }
                            $item->save();
                        }
                    }
                    //prevent duplication
                    remove_action('woocommerce_order_after_calculate_totals', array($this, 'totals_recalculated_callback'), 10, 2);
                    $due_payment->calculate_totals(false);
                    $due_payment->save();

                }

                //update legacy meta
                $second_payment = $order->get_meta('_mepp_second_payment', true);

                if ($positive) {
                    $second_payment += $difference;
                } else {
                    $second_payment -= $difference;

                }
                //update value

                $order->update_meta_data('_mepp_second_payment', wc_format_decimal(floatval($second_payment)));
                $order->save();
            }

        }

    }

    /**
     *  Remove the Statuses Partially-Paid and Processing from available Statuses in Partial Payments UI
     * @return void
     */
    function remove_statuses_for_partial_payment()
    {

        if (isset($_GET['action']) && $_GET['action'] === 'edit') {

            ob_start(); ?>
            <script>
                jQuery(document).ready(function ($) {
                    'use strict';
                    var order_status = $('select#order_status');
                    order_status.find('option[value="wc-partially-paid"]').remove();
                    order_status.find('option[value="wc-processing"]').remove();
                })
            </script>
            <?php echo ob_get_clean();
        }

    }

    /**
     * Removes all Deposit related meta and partial-payments from order
     * @param $order_id
     * @return void
     */
    function remove_all_order_deposit_data($order_id)
    {
        $order = wc_get_order($order_id);
        foreach ($order->get_items() as $order_item) {
            $order_item->delete_meta_data('wc_deposit_meta');
            $order_item->save();
        }
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
        mepp_delete_current_schedule($order);
        $order->save();
    }


    /**
     * Callback for recalculate deposit button ajax call
     * @return void
     */
    function recalculate_deposit_callback()
    {
        check_ajax_referer('order-item', 'security');

        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $order_id = isset($_POST['order_id']) && !empty($_POST['order_id']) ? sanitize_text_field($_POST['order_id']) : false;

        if (!$order_id) {
            wp_send_json_error();
            wp_die();
        }

        if (isset($_POST['remove_all_data']) && $_POST['remove_all_data'] === 'yes') {
            $this->remove_all_order_deposit_data($order_id);
            wp_send_json_success();
            wp_die();
        }


        $order = wc_get_order($order_id);

        $items = $order->get_items();
        if (empty($items)) {
            wp_send_json_error();
            wp_die();
        }
        $form_data = $_POST['order_items'];
        $data = array();
        $data['fees'] = isset($form_data['mepp_fees_handling']) ? $form_data['mepp_fees_handling'] : get_option('mepp_fees_handling', 'split');
        $data['taxes'] = isset($form_data['mepp_taxes_handling']) ? $form_data['mepp_taxes_handling'] : get_option('mepp_taxes_handling', 'split');
        $data['shipping'] = isset($form_data['mepp_shipping_handling']) ? $form_data['mepp_shipping_handling'] : get_option('mepp_shipping_handling', 'split');
        $data['shipping_taxes'] = isset($form_data['mepp_shipping_taxes_handling']) ? $form_data['mepp_shipping_taxes_handling'] : get_option('mepp_shipping_taxes_handling', 'split');
        $data['coupons'] = isset($form_data['mepp_coupons_handling']) ? $form_data['mepp_coupons_handling'] : get_option('mepp_coupons_handling', 'split');
        if (isset($form_data['mepp_deposit_enabled_checkout_mode']) && $form_data['mepp_deposit_enabled_checkout_mode'] === 'yes') {
            //checkout mode calculation
            $data['checkout_mode'] = array();
            $data['checkout_mode']['amount'] = $form_data['mepp_deposit_amount_checkout_mode'];
            $data['checkout_mode']['amount_type'] = $form_data['mepp_deposit_amount_type_checkout_mode'];
            $data['checkout_mode']['selected_plan'] = $data['checkout_mode']['amount_type'] === 'payment_plan' ? $form_data['mepp_payment_plan_checkout_mode'] : false;

        } elseif (isset($_POST['order_items'])) {

            $items = array();
            foreach ($order->get_items() as $order_item) {
                //scan through the submitted form data and build new meta

                if (isset($form_data['mepp_deposit_enabled_' . $order_item->get_id()]) && $form_data['mepp_deposit_enabled_' . $order_item->get_id()] === 'yes') {
                    $item_amount = $form_data['mepp_deposit_amount_' . $order_item->get_id()];
                    $item_amount_type = $form_data['mepp_deposit_amount_type_' . $order_item->get_id()];
                    if ($item_amount_type === 'percentage') $item_amount_type = 'percent'; // different value between checkout mode and product-based
                    $selected_plan = $item_amount_type === 'payment_plan' ? $form_data['mepp_payment_plan_' . $order_item->get_id()] : false;
                    $items[$order_item->get_id()] = array('enabled' => 'yes', 'item_amount' => $item_amount, 'item_amount_type' => $item_amount_type, 'selected_plan' => $selected_plan);
                }
            }
            $data['items'] = $items;

        } else {

            //something is wrong
            wp_send_json_error();
            wp_die();
        }

        MEPP_Admin_Order::recalculate_deposit_for_order($order, $data);

        wp_send_json_success(array());
        wp_die();
    }

    /**
     * Recalculate deposit values for order based on populated data
     * @param $order \WC_Order
     * @param $data
     * @return void
     */
    static function recalculate_deposit_for_order($order, $data)
    {
        do_action('mepp_before_recalculate_deposit_for_order', $order->get_id(), $data);
        $taxes_handling = empty($data['taxes']) ? get_option('mepp_taxes_handling', 'split') : $data['taxes'];
        $fees_handling = empty($data['fees']) ? get_option('mepp_fees_handling', 'split') : $data['fees'];
        $shipping_handling = empty($data['shipping']) ? get_option('mepp_shipping_handling', 'split') : $data['shipping'];
        $coupons_handling = empty($data['coupons']) ? get_option('mepp_coupons_handling', 'split') : $data['coupons'];
        //checkout mode not using the setting but using the input
        if (isset($data['checkout_mode']) && is_array($data['checkout_mode'])) {

            $deposit_amount = $data['checkout_mode']['amount'];
            $amount_type = $data['checkout_mode']['amount_type'];
            $selected_plan = $amount_type === 'payment_plan' && isset($data['checkout_mode']['selected_plan']) ? $data['checkout_mode']['selected_plan'] : false;

            switch ($amount_type) {
                case 'payment_plan':
                    $plan_amount_type = get_term_meta($selected_plan, 'amount_type', true);
                    if (empty($plan_amount_type)) $plan_amount_type = 'percentage'; // backward compatibility ,fallback to percentage if type not detected

                    if ($plan_amount_type === 'fixed') {
                        //if plan is fixed , distribute the total over all products
                        $order_items_count = count($order->get_items());

                        $plan_payment_details = get_term_meta($selected_plan, 'payment_details', true);
                        $plan_payment_details = json_decode($plan_payment_details, true);

                        foreach ($order->get_items() as $order_item) {
                            $order_item->delete_meta_data('wc_deposit_meta');

                            $plan_details = array();
                            foreach ($plan_payment_details['payment-plan'] as $plan_detail) {
                                $plan_detail['percentage'] = $plan_detail['percentage'] / $order_items_count / $order_item->get_quantity();
                                $plan_details[] = $plan_detail;
                            }
                            $deposit_percentage = get_term_meta($selected_plan, 'deposit_percentage', true);
                            $deposit_percentage = floatval($deposit_percentage) / $order_items_count / $order_item->get_quantity();
                            $deposit_meta = MEPP_Admin_Order::calculate_deposit_for_order_item($order_item, $amount_type, 0, $selected_plan, array('deposit_percentage' => $deposit_percentage, 'payment_details' => array('payment-plan' => $plan_details)), $taxes_handling);
                            $order_item->update_meta_data('wc_deposit_meta', $deposit_meta);
                            $order_item->save();

                        }
                    } else {
                        foreach ($order->get_items() as $order_item) {
                            $order_item->delete_meta_data('wc_deposit_meta');
                            $deposit_meta = MEPP_Admin_Order::calculate_deposit_for_order_item($order_item, $amount_type, 0, $selected_plan, array(), $taxes_handling);
                            $order_item->update_meta_data('wc_deposit_meta', $deposit_meta);
                            $order_item->save();
                        }

                    }
                    break;
                case 'fixed':
                    $total_amount = $deposit_amount;

                    foreach ($order->get_items() as $order_item) {

                        $order_item->delete_meta_data('wc_deposit_meta');
                        if ($order_item->get_subtotal('edit') >= $total_amount) {
                            // the whole deposit fits in this item
                            $item_amount = $total_amount;
                            $total_amount = 0.0;
                        } else {
                            $item_amount = $order_item->get_subtotal('edit');
                            $total_amount -= $item_amount;
                        }
                        $deposit_meta = MEPP_Admin_Order::calculate_deposit_for_order_item($order_item, $amount_type, $item_amount / $order_item->get_quantity(), $selected_plan, array(), $taxes_handling);
                        $order_item->update_meta_data('wc_deposit_meta', $deposit_meta);
                        $order_item->save();
                    }
                    break;
                case 'percentage' :
                    $amount_type = 'percent'; // fix amount type value from checkout mode

                    foreach ($order->get_items() as $order_item) {
                        $order_item->delete_meta_data('wc_deposit_meta');
                        $item_amount = $deposit_amount;
                        $deposit_meta = MEPP_Admin_Order::calculate_deposit_for_order_item($order_item, $amount_type, $item_amount, $selected_plan, array(), $taxes_handling);
                        $order_item->update_meta_data('wc_deposit_meta', $deposit_meta);
                        $order_item->save();
                    }
                    break;
            }

        } else {
            foreach ($order->get_items() as $order_item) {
                //remove current order item meta for deposits
                $order_item->delete_meta_data('wc_deposit_meta');
                //scan through the submitted form data and build new meta
                if (isset($data['items'][$order_item->get_id()]['enabled']) && $data['items'][$order_item->get_id()]['enabled'] === 'yes') {
                    $item_amount = $data['items'][$order_item->get_id()]['item_amount'];
                    $item_amount_type = $data['items'][$order_item->get_id()]['item_amount_type'];

                    $selected_plan = $item_amount_type === 'payment_plan' ? $data['items'][$order_item->get_id()]['selected_plan'] : false;

                    $deposit_meta = MEPP_Admin_Order::calculate_deposit_for_order_item($order_item, $item_amount_type, $item_amount, $selected_plan, array(), $taxes_handling);
                } else {
                    $deposit_meta = MEPP_Admin_Order::calculate_deposit_for_order_item($order_item, 'full');
                }

                $order_item->update_meta_data('wc_deposit_meta', $deposit_meta);
            }
        }
        /****
         * @var $order WC_Order
         */
        $order->calculate_totals();
        //each item already assigned , now we can get item deposit and tax deposit from values
        $items_deposit_total = 0.0;
        $items_tax_total = 0.0;
        $deposit_details = array('items' => array());
        foreach ($order->get_items() as $item) {
            $deposit_meta = $item->get_meta('wc_deposit_meta');
            $items_deposit_total += $deposit_meta['deposit'];
            $items_tax_total += $deposit_meta['tax'];
            $deposit_details['items'][$item->get_id()] = array('name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'amount' => floatval($deposit_meta['deposit']),
                'tax' => floatval($deposit_meta['tax']),
                'total' => floatval($deposit_meta['deposit']) + floatval($deposit_meta['tax'])
            );
        }
        $division = $order->get_subtotal() == 0 ? 1 : $order->get_subtotal();
        $deposit_percentage = round($items_deposit_total / $division * 100, 1);


        $fees = MEPP_Admin_Order::calculate_fees_breakdown_for_order($items_deposit_total, $deposit_percentage, $order, $fees_handling);

        $deposit_details['fees'] = $fees['deposit_details'];
        $remaining_details['fees'] = $fees['remaining_details'];

        $shipping = MEPP_Admin_Order::calculate_shipping_breakdown_for_order($items_deposit_total, $deposit_percentage, $order, $shipping_handling);
        $deposit_details['shipping'] = $shipping['deposit_details'];
        $remaining_details['shipping'] = $shipping['remaining_details'];

        $discount = MEPP_Admin_Order::calculate_discount_breakdown_for_order($items_deposit_total, $items_tax_total, $deposit_percentage, $order, $coupons_handling);
        $deposit_details['discount'] = $discount['deposit_details'];
        $remaining_details['discount'] = $discount['remaining_details'];

        $deposit_breakdown = array(
            'cart_items' => $items_deposit_total,
            'taxes' => $items_tax_total,
            'fees' => $fees['deposit'],
            'shipping' => $shipping['deposit'],
            'discount' => $discount['deposit'],
        );

        // store new breakdown
        $order->update_meta_data('_mepp_deposit_breakdown', $deposit_breakdown);

        $total_deposit = $items_deposit_total + $items_tax_total + $fees['deposit'] + $shipping['deposit'] - $discount['deposit'];

        $total_deposit = round($total_deposit, wc_get_price_decimals());


        //disable deposit emails during partial payments recreation and calculation
        //suppress partial payment and deposit payment complete emails in this process
        add_filter('woocommerce_email_enabled_customer_deposit_partially_paid', '__return_false', 99);
        add_filter('woocommerce_email_enabled_customer_partially_paid', '__return_false', 99);
        add_filter('woocommerce_email_enabled_partial_payment', '__return_false', 99);

        mepp_delete_current_schedule($order);
        if (is_numeric($total_deposit) && $total_deposit < floatval($order->get_total())) {

            $order->update_meta_data('_mepp_order_has_deposit', 'yes');

            $remaining_amounts = array(
                'fees' => $fees['remaining'],
                'shipping' => $shipping['remaining'],
                'discount' => $discount['remaining'],
            );

            $partial_payments_schedule = MEPP_Admin_Order::build_payment_schedule($remaining_details, $remaining_amounts, $order);

            foreach ($order->get_items() as $item_key => $item) {

                if (isset($deposit_details['items'][$item->get_id()])) {
                    $item_discount = $item->get_subtotal() - $item->get_total();
                    $item_details = $deposit_details['items'][$item->get_id()];
                    if ($item_discount !== 0.0) {
                        $division = $item->get_subtotal() == 0 ? 1 : $item->get_subtotal();
                        $percentage = round($item_details['amount'] / $division * 100, 1);
                        $deposit_details['items'][$item->get_id()]['subtotal'] = $item->get_total() / 100 * $percentage;
                        $deposit_details['items'][$item->get_id()]['subtotal_tax'] = $item->get_total_tax() / 100 * $percentage;

                    }
                }
            }

            $deposit_data = array(
                'id' => '',
                'title' => __('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'deposit',
                'details' => $deposit_details,
                'total' => $total_deposit,
            );


            $partial_payments_schedule = array('deposit' => $deposit_data) + $partial_payments_schedule;

            $schedule = mepp_create_payment_schedule($order, $partial_payments_schedule);
            $order->update_meta_data('_mepp_payment_schedule', $schedule);

            $future_payments = round($order->get_total() - $total_deposit, wc_get_price_decimals());

            $order->update_meta_data('_mepp_deposit_amount', wc_format_decimal($total_deposit));
            $order->update_meta_data('_mepp_second_payment', wc_format_decimal($future_payments));

            $partial_payments_structure = apply_filters('mepp_partial_payments_structure', get_option('mepp_partial_payments_structure', 'single'), 'order');

            if ($partial_payments_structure !== 'single') {
                $order->update_meta_data('_mepp_itemized_payments', 'yes');
            } else {
                $order->update_meta_data('_mepp_itemized_payments', 'no');
            }

        } else {
            $order->delete_meta_data('_mepp_order_has_deposit');
            $order->delete_meta_data('_mepp_deposit_amount');
            $order->delete_meta_data('_mepp_second_payment');
            $order->delete_meta_data('_mepp_itemized_payments');
        }

        $order->save();
        //remove the email suppression
        remove_filter('woocommerce_email_enabled_customer_deposit_partially_paid', '__return_false', 99);
        remove_filter('woocommerce_email_enabled_customer_partially_paid', '__return_false', 99);
        remove_filter('woocommerce_email_enabled_partial_payment', '__return_false', 99);

        do_action('mepp_after_recalculate_deposit_for_order', $order->get_id(), $data);

    }

    /**
     * Calculate and prepare deposit meta for order item
     * @param $order_item \WC_Order_Item_Product
     * @param $item_amount_type
     * @param $amount_or_percentage
     * @param $selected_plan
     * @param $plan_override
     * @param $taxes_handling
     * @return string[]
     */
    static function calculate_deposit_for_order_item(\WC_Order_Item_Product $order_item, $item_amount_type, float $amount_or_percentage = 0, int $selected_plan = 0, array $plan_override = array(), string $taxes_handling = ''): array
    {

        $taxes_handling = empty($taxes_handling) ? get_option('mepp_taxes_handling', 'split') : $taxes_handling;
        $deposit_meta = array('enable' => 'no');
        $product = $order_item->get_product();
        if ($product) {
            $second_payment_due_after = get_option('mepp_second_payment_due_after', '');
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
                    if (is_array($plan_payment_details) && is_array($plan_payment_details['payment-plan']) && !empty($plan_payment_details['payment-plan'])) {
                        //calculate each payment
                        $schedule = array();
                        $payment_date = current_time('timestamp');
                        $quantity = $order_item->get_quantity();
                        $original_subtotal = $order_item->get_subtotal('edit');
                        $original_subtotal_tax = $order_item->get_subtotal_tax('edit');
                        $original_price = $original_subtotal;
                        if (wc_prices_include_tax()) {
                            $original_price += $original_subtotal_tax;
                        }
                        $original_price = round($original_price, wc_get_price_decimals());


                        if ($plan_amount_type === 'fixed') {
                            $plan_deposit_amount = isset($plan_override['deposit_percentage']) ? $plan_override['deposit_percentage'] : get_term_meta($selected_plan, 'deposit_percentage', true);
                            $plan_total = floatval($plan_deposit_amount) + array_sum(array_column($plan_payment_details['payment-plan'], 'percentage'));
                            $plan_deposit_amount *= $quantity;
                            $plan_total *= $quantity;
                            $plan_tax_total = round(wc_get_price_including_tax($product, array('price' => $plan_total)) - wc_get_price_excluding_tax($product, array('price' => $plan_total)), wc_get_price_decimals());

                        } else {
                            //get deposit percentage from meta
                            $plan_deposit_percentage = isset($plan_override['deposit_percentage']) ? $plan_override['deposit_percentage'] : get_term_meta($selected_plan, 'deposit_percentage', true);
                            //we need to calculate total cost in case it is more than 100%
                            $plan_total_percentage = floatval($plan_deposit_percentage) + array_sum(array_column($plan_payment_details['payment-plan'], 'percentage'));


                            // prepare display of payment plans
                            $plan_total = $original_price / 100 * $plan_total_percentage;
                            $plan_total = round($plan_total, wc_get_price_decimals());
                            $plan_tax_total = round(wc_get_price_including_tax($product, array('price' => $plan_total)) - wc_get_price_excluding_tax($product, array('price' => $plan_total)), wc_get_price_decimals());
                            $plan_deposit_amount = round($original_price / 100 * $plan_deposit_percentage, wc_get_price_decimals());
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

                        $price_total = $plan_total;
                        if (wc_prices_include_tax()) {
                            $price_total += $plan_tax_total;   //separate taxes for sake of tax display settings
                        }

                        $order_item->set_subtotal($price_total);
                        $order_item->set_total($price_total);

                        $deposit_meta['enable'] = 'yes';
                        $deposit_meta['payment_plan'] = $selected_plan;
                        $deposit_meta['deposit'] = $plan_deposit_amount;
                        $deposit_meta['remaining'] = $plan_total - $plan_deposit_amount;
                        $deposit_meta['total'] = $plan_total;
                        $deposit_meta['tax_total'] = $plan_tax_total;
                        $deposit_meta['tax'] = $plan_deposit_tax;
                        $deposit_meta['payment_schedule'] = $schedule;
                    }
                    break;
                case'percent':
                case 'fixed':
                    $item_price = floatval($order_item->get_subtotal('edit'));
                    $item_tax = floatval($order_item->get_subtotal_tax('edit'));
                    $quantity = $order_item->get_quantity();

                    if ($item_tax > 0 && wc_prices_include_tax()) {
                        $item_price += $item_tax;
                        $item_price = round($item_price, wc_get_price_decimals());
                    }


                    //only update on quantity for fixed , no need for percentage
                    $item_deposit_amount = $item_amount_type === 'fixed' ? floatval($amount_or_percentage) * $quantity : floatval(($item_price * $amount_or_percentage) / 100);
                    $item_deposit_amount = round($item_deposit_amount, wc_get_price_decimals());

                    if ($item_amount_type === 'fixed') {
                        $division = $item_price == 0 ? 1 : $item_price;
                        $percentage = $item_deposit_amount / $division * 100;

                    } else {
                        $percentage = $amount_or_percentage;
                    }


                    $item_deposit_tax = 0.0;
                    if ($item_tax > 0) {

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

                        $item_deposit_tax = round($item_deposit_tax, wc_get_price_decimals());

                    }

                    if ($item_tax > 0 && wc_prices_include_tax()) {
                        $item_price -= $item_tax;
                        $item_deposit_amount -= ($item_tax / 100) * $percentage;
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
                case 'full' :
                default:
                    //when an item is paid in full  , just set the values for calculation
                    $item_subtotal = $order_item->get_total('edit');
                    $item_tax = $order_item->get_total_tax('edit');
                    $deposit_meta['enable'] = 'no';
                    $deposit_meta['deposit'] = $item_subtotal;
                    $deposit_meta['tax_total'] = $item_tax;

                    switch ($taxes_handling) {
                        case 'deposit' :
                        case 'split' :

                            $deposit_meta['tax'] = $item_tax;
                            break;
                        default :
                            $deposit_meta['tax'] = 0.0;;
                            break;
                    }


                    $deposit_meta['remaining'] = 0;
                    $deposit_meta['total'] = $item_subtotal;
                    $deposit_meta['payment_schedule'] = array(); //payment scheudule is empty since full amount is paid with deposit

                    break;
            }
        }

        return apply_filters('mepp_order_item_deposit_data', $deposit_meta, $order_item);
    }

    static function create_partial_payment_items($partial_payment, $order, $details)
    {

        $items_tax = 0.0;
        $fees_tax = 0.0;
        $shipping_tax = 0.0;
        $discount_tax = 0.0;

        foreach ($order->get_items() as $order_item) {
            /***
             * @var $order_item \WC_Order_Item_Product
             */
            if (isset($details['items'][$order_item->get_id()])) {
                $item_details = $details['items'][$order_item->get_id()];
                $item = new \WC_Order_Item_Product();
                $item->add_meta_data('_mepp_parent_item_id', $order_item->get_id());
                $item->save();

                $payment_subtotal = isset($item_details['subtotal']) ? $item_details['subtotal'] : $item_details['amount'];
                $payment_subtotal_tax = isset($item_details['subtotal_tax']) ? $item_details['subtotal_tax'] : $item_details['tax'];
                $product = $order_item->get_product();
                $item->set_props(
                    array(
                        'quantity' => $order_item->get_quantity(),
                        'variation' => $order_item->get_variation_id(),
                    )
                );
                $parent_item_taxes = $order_item->get_taxes();
                $items_tax += $item_details['tax'];

                $count = 0;

                $item->set_total(floatval($payment_subtotal));
                $item->set_subtotal($item_details['amount']);
                $item->set_subtotal_tax($payment_subtotal_tax);
                $item->set_total_tax($item_details['tax']);
                $item->save();

                if ($product) {
                    $item->set_props(
                        array(
                            'name' => $product->get_name(),
                            'tax_class' => $product->get_tax_class(),
                            'product_id' => $product->is_type('variation') ? $product->get_parent_id() : $product->get_id(),
                            'variation_id' => $product->is_type('variation') ? $product->get_id() : 0,
                        )
                    );
                }
                $item->save();
                // Add item to order and save.
                $partial_payment->add_item($item);
            } else {
                if (apply_filters('mepp_add_empty_items_to_partial_payments', false)) {
                    $item = new \WC_Order_Item_Product();
                    $item->add_meta_data('_mepp_parent_item_id', $order_item->get_id());
                    $item->save();
                    $product = $order_item->get_product();
                    $item->set_props(
                        array(
                            'quantity' => $order_item->get_quantity(),
                            'variation' => $order_item->get_variation_id(),
                            'subtotal' => 0,
                            'total' => 0,
                            'subtotal_tax' => 0,
                            'total_tax' => 0,
                        )
                    );

                    if ($product) {
                        $item->set_props(
                            array(
                                'name' => $product->get_name(),
                                'tax_class' => $product->get_tax_class(),
                                'product_id' => $product->is_type('variation') ? $product->get_parent_id() : $product->get_id(),
                                'variation_id' => $product->is_type('variation') ? $product->get_id() : 0,
                            )
                        );
                    }

                    // Add item to order and save.
                    $partial_payment->add_item($item);
                }
            }
        }
        foreach ($order->get_fees() as $order_fee) {
            if (isset($details['fees'][$order_fee->get_id()])) {
                $fee_details = $details['fees'][$order_fee->get_id()];

                /***
                 * @var $order_fee \WC_Order_Item_Fee
                 */
                $fee = new \WC_Order_Item_Fee();
                $fee->add_meta_data('_mepp_parent_item_id', $order_fee->get_id());
                $fee->save();
                $fee->set_props(
                    array(
                        'name' => $order_fee->get_name(),
                        'tax_class' => $order_fee->get_tax_class(),
                        'amount' => $fee_details['amount'],
                        'total' => $fee_details['amount'],
                        'total_tax' => $fee_details['tax'],
                    )
                );
                $fees_tax += $fee_details['tax'];
                $parent_item_taxes = $order_item->get_taxes();
                $items_tax += $item_details['tax'];
                $item_taxes = array();
                foreach ($parent_item_taxes['subtotal'] as $tax_rate_id => $tax) {
                    if ($count === 0) {
                        $item_taxes['subtotal'][$tax_rate_id] = $item_details['tax'];
                    } else {
                        $item_taxes['subtotal'][$tax_rate_id] = 0;
                    }
                    $count++;
                }
                $count = 0;
                foreach ($parent_item_taxes['total'] as $tax_rate_id => $tax) {
                    if ($count === 0) {
                        $item_taxes['total'][$tax_rate_id] = $item_details['tax'];
                    } else {
                        $item_taxes['total'][$tax_rate_id] = 0;
                    }
                    $count++;
                }

                $item->set_taxes($item_taxes);
                // Add item to order and save.
                $partial_payment->add_item($fee);
            } else {
                if (apply_filters('mepp_add_empty_fees_to_partial_payments', false)) {
                    $fee = new \WC_Order_Item_Fee();
                    $fee->add_meta_data('_mepp_parent_item_id', $order_fee->get_id());
                    $fee->save();
                    $fee->set_props(
                        array(
                            'name' => $order_fee->get_name(),
                            'tax_class' => $order_fee->get_tax_class(),
                            'amount' => 0,
                            'total' => 0,
                            'total_tax' => 0
                        )
                    );

                    // Add item to order and save.
                    $partial_payment->add_item($fee);
                }
            }
        }
        foreach ($order->get_shipping_methods() as $order_shipping) {
            if (isset($details['shipping'][$order_shipping->get_id()])) {
                $shipping_details = $details['shipping'][$order_shipping->get_id()];

                $shipping = new \WC_Order_Item_Shipping();
                $shipping->add_meta_data('_mepp_parent_item_id', $order_shipping->get_id());
                $shipping->save();
                /***
                 * @var $order_shipping \WC_Order_Item_Shipping
                 */
                $shipping->set_props(
                    array(
                        'method_title' => $order_shipping->get_method_title(),
                        'method_id' => $order_shipping->get_method_id(),
                        'instance_id' => $order_shipping->get_instance_id(),
                        'total' => wc_format_decimal($shipping_details['amount']),
                    )
                );
                $shipping_tax += $shipping_details['tax'];

                // Add item to order and save.
                $partial_payment->add_item($shipping);

            } else {
                if (apply_filters('mepp_add_empty_shipping_methods_to_partial_payments', false)) {

                    $shipping = new \WC_Order_Item_Shipping();
                    $shipping->add_meta_data('_mepp_parent_item_id', $order_shipping->get_id());
                    $shipping->save();
                    /***
                     * @var $order_shipping \WC_Order_Item_Shipping
                     */
                    $shipping->set_props(
                        array(
                            'method_title' => $order_shipping->get_method_title(),
                            'method_id' => $order_shipping->get_method_id(),
                            'instance_id' => $order_shipping->get_instance_id(),
                            'total' => 0,
                        )
                    );

                    // Add item to order and save.
                    $partial_payment->add_item($shipping);
                }
            }
        }

        foreach ($order->get_coupons() as $order_coupon) {
            if (isset($details['discount'][$order_coupon->get_code()])) {
                $discount_details = $details['discount'][$order_coupon->get_code()];
                $discount = new \WC_Order_Item_Coupon();
                $discount->add_meta_data('_mepp_parent_item_id', $order_coupon->get_id());
                /***
                 * @var $order_coupon \WC_Order_Item_Coupon
                 */
                $discount->set_props(
                    array(
                        'code' => $order_coupon->get_code(),
                        'discount' => $discount_details['amount'],
                        'discount_tax' => $discount_details['tax'],
                    )
                );
                $discount->save();

                $discount_tax += $discount_details['tax'];
                /*** @var $partial_payment \MEPP_Payment * */
                // Add item to order and save.
                $partial_payment->add_item($discount);

            } else {
                if (apply_filters('mepp_add_empty_coupons_to_partial_payments', false)) {

                    $discount = new \WC_Order_Item_Coupon();
                    $discount->add_meta_data('_mepp_parent_item_id', $order_coupon->get_id());
                    $discount->save();
                    /***
                     * @var $order_coupon \WC_Order_Item_Coupon
                     */
                    $discount->set_props(
                        array(
                            'code' => $order_coupon->get_code(),
                            'discount' => 0,
                            'discount_tax' => 0,
                        )
                    );

                    // Add item to order and save.
                    $partial_payment->add_item($discount);
                }
            }
        }

        if (!empty($details['items'])) $partial_payment->set_cart_tax($items_tax);
        if (!empty($details['shipping'])) $partial_payment->set_shipping_tax($items_tax);
        if (!empty($details['discount'])) $partial_payment->set_discount_tax($items_tax);


        $partial_payment->save();

        $partial_payment->calculate_totals(true);

        return $partial_payment;
    }

    /**
     * Calculate deposit fees for order
     * @param $deposit
     * @param $order
     * @return array
     */
    static function calculate_deposit_fees_for_order($deposit, $order)
    {
        $fees_handling = get_option('mepp_fees_handling');
        $total_fees = floatval($order->get_total_fees());

        $deposit_fees = array('deposit' => 0.0, 'remaining' => $total_fees);

        switch ($fees_handling) {
            case 'deposit' :
                $deposit_fees['deposit'] = $total_fees;
                break;

            case 'split' :
                $deposit_percentage = round($deposit / $order->get_subtotal('edit') * 100, 1);
                $deposit_fees['deposit'] = $total_fees * $deposit_percentage / 100;

                break;
        }
        $deposit_fees['remaining'] = $total_fees - $deposit_fees['deposit'];

        return $deposit_fees;
    }

    /**
     * Calculate deposit shipping for order
     * @param $deposit
     * @param $order
     * @return array
     */
    static function calculate_deposit_shipping_for_order($deposit, $order)
    {
        $shipping_handling = get_option('mepp_shipping_handling');
        $total_shipping = floatval($order->get_shipping_total());

        $deposit_shipping = array('deposit' => 0.0, 'remaining' => $total_shipping);

        switch ($shipping_handling) {
            case 'deposit' :
                $deposit_shipping['deposit'] = $total_shipping;
                break;

            case 'split' :
                $deposit_percentage = round($deposit / $order->get_subtotal('edit') * 100, 1);
                $deposit_shipping['deposit'] = $total_shipping * $deposit_percentage / 100;

                break;
        }
        $deposit_shipping['remaining'] = $total_shipping - $deposit_shipping['deposit'];

        return $deposit_shipping;
    }

    /**
     * Calculate deposit discount for order
     * @param $deposit
     * @param $order \WC_Order
     * @return array
     */
    static function calculate_discount_breakdown_for_order($deposit, $deposit_tax, $percentage, $order, $coupons_handling)
    {

        $discount_breakdown = array('deposit' => 0.0, 'remaining' => 0.0, 'deposit_details' => array(), 'remaining_details' => array());

        $total_discount = $order->get_total_discount();
        $total_discount_tax = floatval($order->get_discount_tax());
        $total_remaining = $order->get_subtotal('edit') - $deposit;
        $total_remaining_tax = $order->get_total_tax('edit') - $deposit_tax;
        $coupons_total = 0;
        $coupons_total_tax = 0;
        $coupons = $order->get_coupons();
        foreach ($coupons as $code => $coupon) {
            $coupon_total = $coupon->get_discount('edit');
            $coupon_tax = $coupon->get_discount_tax('edit');
            $coupons_total += $coupon_total;
            $coupons_total_tax += $coupon_tax;

        }

        if (($order->get_discount_total() + $order->get_discount_tax()) > $coupons_total + $coupons_total_tax) {

            $temp_coupon = new \WC_Coupon('mepp_adjustment');
            $coupons['mepp_adjustment'] = $temp_coupon;
        }
        foreach ($coupons as $code => $coupon) {

            if ($code === 'mepp_adjustment') {
                $coupon_code = $code;
                $coupon_total = $order->get_discount_total() - $coupons_total;
                $coupon_tax = $order->get_discount_tax() - $coupons_total_tax;
            } else {

                /**
                 * @var $coupon \WC_Order_Item_Coupon
                 */
                $coupon_code = $coupon->get_code();
                $coupon_total = $coupon->get_discount();
                $coupon_tax = $coupon->get_discount_tax();
            }

            switch ($coupons_handling) {
                case 'deposit' :

                    $discount_amount = floatval($coupon_total);
                    $discount_tax = floatval($coupon_tax);
                    if (($discount_amount + $discount_tax) > $deposit + $deposit_tax) {
                        $discount_amount = $deposit - $deposit_tax;
                        $discount_tax = $deposit_tax;

                        //send remaining record of discount to deposit
                        $remaining_details = array('name' => $coupon_code,
                            'amount' => floatval($coupon_total - $discount_amount),
                            'tax' => $coupon_tax - $discount_tax
                        );

                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                        $discount_breakdown['remaining_details'][$coupon_code] = $remaining_details;
                        $discount_breakdown['remaining'] += $remaining_details['total'];
                    }

                    $deposit_details = array('name' => $coupon_code,
                        'amount' => floatval($discount_amount),
                        'tax' => $discount_tax
                    );

                    $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                    $discount_breakdown['deposit_details'][$coupon_code] = $deposit_details;
                    $discount_breakdown['deposit'] += $deposit_details['total'];

                    break;
                case 'split' :

                    $discount_amount = $coupon_total * $percentage / 100;
                    $discount_tax = floatval($coupon_tax) * $percentage / 100;
                    // put the calculated values in deposit breakdown then calculate and insert remaining
                    $deposit_details = array('name' => $coupon_code,
                        'amount' => floatval($discount_amount),
                        'tax' => $discount_tax
                    );

                    $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                    $discount_breakdown['deposit_details'][$coupon_code] = $deposit_details;

                    //now do remaining
                    $remaining_details = array('name' => $coupon_code,
                        'amount' => floatval($coupon_total - $deposit_details['amount']),
                        'tax' => $coupon_tax - $discount_tax
                    );


                    $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];

                    $discount_breakdown['deposit'] += $deposit_details['total'];
                    $discount_breakdown['remaining_details'][$coupon_code] = $remaining_details;
                    break;
                default:
                    $discount_amount = floatval($coupon_total);
                    $discount_tax = floatval($coupon_tax);

                    if (($discount_amount + $discount_tax) > $total_remaining + $total_remaining_tax) {
                        $discount_amount = $total_remaining;
                        $discount_tax = $total_remaining_tax;

                        //send remaining record of discount to deposit
                        $deposit_details = array('name' => $coupon_code,
                            'amount' => floatval($coupon_total - $discount_amount),
                            'tax' => $coupon_tax - $discount_tax
                        );

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $discount_breakdown['deposit_details'][$coupon_code] = $deposit_details;
                        $discount_breakdown['deposit'] += $deposit_details['total'];
                    }

                    $remaining_details = array('name' => $coupon_code,
                        'amount' => floatval($discount_amount),
                        'tax' => $discount_tax
                    );
                    $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                    $discount_breakdown['remaining_details'][$coupon_code] = $remaining_details;

                    break;
            }
        }

        $discount_breakdown['remaining'] = $total_discount - $discount_breakdown['deposit'];

        return apply_filters('mepp_order_discount_breakdown', $discount_breakdown, $deposit, $percentage, $order);
    }


    static function calculate_fees_breakdown_for_order($deposit, $percentage, $order, $fees_handling)
    {
        $total_fees = floatval($order->get_total_fees());
        $fees_breakdown = array('deposit' => 0.0, 'remaining' => 0.0, 'deposit_details' => array(), 'remaining_details' => array());
        if (!empty($order->get_fees())) {
            foreach ($order->get_fees() as $fee) {
                /**
                 * @var $fee \WC_Order_Item_Fee
                 */
                switch ($fees_handling) {
                    case 'deposit' :

                        // put entire fees in deposit details
                        $fee_amount = floatval($fee->get_total());
                        $fee_tax = floatval($fee->get_total_tax());

                        $deposit_details = array('name' => $fee->get_name(),
                            'amount' => floatval($fee_amount),
                            'tax' => $fee_tax
                        );

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $fees_breakdown['deposit_details'][$fee->get_id()] = $deposit_details;
                        $fees_breakdown['deposit'] += $deposit_details['total'];

                        break;
                    case 'split' :


                        $fee_amount = floatval($fee->get_total()) * $percentage / 100;
                        $fee_tax = floatval($fee->get_total_tax()) * $percentage / 100;


                        // put the calculated values in deposit breakdown then calculate and insert remaining
                        $deposit_details = array('name' => $fee->get_name(),
                            'amount' => floatval($fee_amount),
                            'tax' => $fee_tax
                        );

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $fees_breakdown['deposit_details'][$fee->get_id()] = $deposit_details;

                        //now do remaining
                        $remaining_details = array('name' => $fee->get_name(),
                            'amount' => floatval($fee->get_total() - $deposit_details['amount']),
                            'tax' => $fee->get_total_tax() - $fee_tax
                        );

                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];

                        $fees_breakdown['deposit'] += $deposit_details['total'];
                        $fees_breakdown['remaining_details'][$fee->get_id()] = $remaining_details;

                        break;
                    default:
                        // put entire fees in remaining details
                        $fee_amount = floatval($fee->get_total());
                        $fee_tax = floatval($fee->get_total_tax());

                        $remaining_details = array('name' => $fee->get_name(),
                            'amount' => floatval($fee_amount),
                            'tax' => $fee_tax
                        );

                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                        $fees_breakdown['remaining_details'][$fee->get_id()] = $remaining_details;

                        break;
                }

            }

            $fees_breakdown['remaining'] = $total_fees - $fees_breakdown['deposit'];
        }
        return apply_filters('mepp_order_fees_breakdown', $fees_breakdown, $deposit, $percentage, $order);
    }

    static function calculate_shipping_breakdown_for_order($deposit, $percentage, $order, $shipping_handling)
    {

        $shipping_breakdown = array('deposit' => 0.0, 'remaining' => 0.0, 'deposit_details' => array(), 'remaining_details' => array());

        /**
         * @var $order \WC_Order
         */
        if (!empty($order->get_shipping_methods())) {
            $total_shipping = floatval($order->get_shipping_total());
            $total_shipping += floatval($order->get_shipping_tax());

            foreach ($order->get_shipping_methods() as $shipping_method) {

                $shipping_taxes = $shipping_method->get_total_tax();
                switch ($shipping_handling) {
                    case 'deposit' :

                        // put entire fees in deposit details
                        $shipping_amount = floatval($shipping_method->get_total());
                        $shipping_tax = $shipping_taxes;

                        $deposit_details = array('name' => $shipping_method->get_name(),
                            'amount' => floatval($shipping_amount),
                            'tax' => $shipping_tax
                        );

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $shipping_breakdown['deposit_details'][$shipping_method->get_id()] = $deposit_details;
                        $shipping_breakdown['deposit'] += $deposit_details['total'];

                        break;
                    case 'split' :


                        $shipping_amount = $shipping_method->get_total() * $percentage / 100;
                        $shipping_tax = floatval($shipping_taxes) * $percentage / 100;


                        // put the calculated values in deposit breakdown then calculate and insert remaining
                        $deposit_details = array('name' => $shipping_method->get_name(),
                            'amount' => floatval($shipping_amount),
                        );
                        $deposit_details['tax'] = $shipping_tax;

                        $deposit_details['total'] = $deposit_details['amount'] + $deposit_details['tax'];
                        $shipping_breakdown['deposit_details'][$shipping_method->get_id()] = $deposit_details;

                        //now do remaining
                        $remaining_details = array('name' => $shipping_method->get_name(),
                            'amount' => floatval($shipping_method->get_total() - $deposit_details['amount']),
                        );

                        $remaining_details['tax'] = $shipping_taxes - $shipping_tax;
                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];


                        $shipping_breakdown['deposit'] += $deposit_details['total'];
                        $shipping_breakdown['remaining_details'][$shipping_method->get_id()] = $remaining_details;

                        break;
                    default:
                        // put entire fees in remaining details
                        $shipping_amount = floatval($shipping_method->get_total());
                        $shipping_tax = $shipping_taxes;
                        $remaining_details = array('name' => $shipping_method->get_name(),
                            'amount' => floatval($shipping_amount),
                            'tax' => $shipping_tax
                        );

                        $remaining_details['total'] = $remaining_details['amount'] + $remaining_details['tax'];
                        $shipping_breakdown['remaining_details'][$shipping_method->get_id()] = $remaining_details;

                        break;
                }


            }

            $shipping_breakdown['remaining'] = $total_shipping - $shipping_breakdown['deposit'];
        }

        return apply_filters('mepp_cart_shipping_breakdown', $shipping_breakdown, $deposit, $order);
    }


    /**
     * Build Payment schedule
     * @param $remaining_amounts
     * @param $order
     * @return array
     */
    static function build_payment_schedule($remaining_details, $remaining_amounts, $order)
    {

        /**   START BUILD PAYMENT SCHEDULE**/

        $schedule = array();

        foreach ($order->get_items() as $item) {

            //combine all the payment schedules
            $deposit_meta = $item->get_meta('wc_deposit_meta');

            if (!empty($deposit_meta['payment_schedule'])) {

                $item_discount = $item->get_subtotal() - $item->get_total();
                $count = 0;


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
                    $single_payment_data['details']['items'][$item->get_id()] = array('name' => $item->get_name(),
                        'quantity' => $item->get_quantity(),
                        'amount' => floatval($single_payment['amount']),
                        'tax' => floatval($single_payment['tax']),
                        'total' => floatval($single_payment['amount']) + floatval($single_payment['tax'])

                    );
                    if ($item_discount !== 0.0) {
                        $division = $item->get_subtotal() == 0 ? 1 : $item->get_subtotal();

                        $percentage = round($single_payment['amount'] / $division * 100, 1);
                        $single_payment_data['details']['items'][$item->get_id()] ['subtotal'] = $item->get_total() / 100 * $percentage;
                        $single_payment_data['details']['items'][$item->get_id()] ['subtotal_tax'] = $item->get_total_tax() / 100 * $percentage;
                    }

                    $existing = false;
                    foreach ($schedule as $entry_key => $entry) {
                        if (isset($entry['timestamp']) && $entry['timestamp'] == $timestamp && !isset($entry['details']['items'][$item->get_id()])) {
                            //combine or not
                            $existing = true;
                            $entry['total'] += $single_payment_data['total'];
                            $entry['details']['items'][$item->get_id()] = $single_payment_data['details']['items'][$item->get_id()];
                            $schedule[$entry_key] = $entry;
                            break;
                        }
                    }
                    if (!$existing) {
                        $schedule[] = $single_payment_data;
                    }
                }
            }
        }

        $timestamps = array();

        foreach (array_keys($schedule) as $key => $node) {
            if ($key === 'unlimited') {
                // now that we collected payment without days in 'unlimited' and set its title, we can restore its date format for sorting purpose
                $timestamp = strtotime(date('Y-m-d', current_time('timestamp')) . "+1 days");
                $schedule[$key]['timestamp'] = $timestamp;
                $key = $timestamp;
            }
            $timestamps[$key] = $node;
        }
        array_multisort($timestamps, SORT_ASC, array_keys($schedule));

        $sorted_schedule = array();
        foreach ($timestamps as $timestamp) {

            $sorted_schedule[$timestamp] = $schedule[$timestamp];
        }

        $schedule = $sorted_schedule;

        // add any fees /taxes / shipping / shipping taxes amounts
        $schedule_total = array_sum(array_column($schedule, 'total'));

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
                            $amount = $equal_payments ? $item['amount'] / count($schedule) : round($item['amount'] / 100 * $percentage, wc_get_price_decimals());
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

        return apply_filters('mepp_order_payment_schedule', $schedule, $remaining_amounts, $order);
    }


    /**
     * Output "Recalculate Deposit" button in order editor
     * @brief output recalculate deposit button
     * @param $order
     */
    function recalculate_deposit_button($order)
    {

        if (!$order->is_editable())
            return;

        $recalculate_deposit_msg = esc_html__('Are you sure? this action is irreversible.', 'advanced-partial-payment-or-deposit-for-woocommerce');


        ?>
        <button type="button" data-msg="<?php echo $recalculate_deposit_msg; ?>"
                data-order-id="<?php echo $order->get_id(); ?>"
                id="mepp_recalculate_deposit"
                class="button button-primary"><?php echo esc_html__('Recalculate Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></button>
        <?php wp_nonce_field('mepp_recalculate_deposit_verify', 'mepp_recalculate_deposit_field', true, true); ?>
        <script>
            <?php // this function needs to be inline for now because it wont work after ajax operations if left in file?>


            jQuery(document).ready(function ($) {
                'use strict';

                var request = false;
                $('#mepp_recalculate_deposit').on('click', function () {
                    if (request !== false) return false;
                    var btn = $(this);

                    $('#woocommerce-order-items').block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });


                    var data = {
                        action: 'mepp_get_recalculate_deposit_modal',
                        order_id: $(this).data('order-id'),
                        security: $('#mepp_recalculate_deposit_field').val()
                    };

                    request = $.ajax({
                        url: mepp_data.ajax_url,
                        data: data,
                        type: 'POST',

                        success: function (response) {
                            $('#woocommerce-order-items').unblock();
                            if (response.success) {
                                delete window.wp.template.cache["mepp-modal-recalculate-deposit"];
                                $('#tmpl-mepp-modal-recalculate-deposit').html(response.data.html);
                                btn.WCBackboneModal({
                                    template: 'mepp-modal-recalculate-deposit',
                                });

                                request = false;
                            } else {
                                alert(response.data);
                            }

                        }
                    });

                    return false;

                });


            });
        </script>
        <?php
    }

    /**
     *
     * When a product is added via order management, this function checks if deposit is enabled and should be calculaed for this product
     * @param $item_id
     * @param $item
     */
    public
    function add_order_item_meta($item_id, $item)
    {

        $product = $item->get_product();

        $default_checked = get_option('mepp_default_option', 'deposit');

        //if plugin is in checkout mode return
        if (mepp_checkout_mode() || $default_checked === 'full')
            return;

        if (mepp_is_product_deposit_enabled($product->get_id())) {
            $deposit = mepp_calculate_product_deposit($product);

            $woocommerce_prices_include_tax = get_option('woocommerce_prices_include_tax');

            if ($woocommerce_prices_include_tax === 'yes') {

                $amount = wc_get_price_including_tax($product);

            } else {
                $amount = wc_get_price_excluding_tax($product);

            }
            $deposit = $deposit * $item->get_quantity();
            $amount = $amount * $item->get_quantity();

            if ($deposit < $amount && $deposit > 0) {

                $deposit_meta['enable'] = 'yes';
                $deposit_meta['deposit'] = $deposit;
                $deposit_meta['remaining'] = $amount - $deposit;
                $deposit_meta['total'] = $amount;
                $item->add_meta_data('wc_deposit_meta', $deposit_meta, true);
                $item->save();


            }
        }

    }

    /**
     * Trigger Customer Partial Payment reminder email  when sent manually from order actions
     * @param $order
     */
    function customer_second_payment_reminder($order)
    {
        do_action('woocommerce_before_resend_order_emails', $order, 'second_payment_reminder');

        // Send reminder email
        do_action('woocommerce_deposits_second_payment_reminder_email', $order->get_id());

        // Note the event.
        $order->add_order_note(esc_html__('Partial Payment reminder email manually sent to customer.', 'advanced-partial-payment-or-deposit-for-woocommerce'), false, true);

        do_action('woocommerce_after_resend_order_email', $order, 'second_payment_reminder');


    }

    /**
     * Adds partially-paid and partial payment reminder emails to resend emails list
     * @param $emails_available
     * @return array
     */
    public
    function resend_order_emails_available($emails_available)
    {

        $emails_available[] = 'customer_partially_paid';
        $emails_available[] = 'customer_second_payment_reminder';

        return $emails_available;
    }

    /**
     * Add Email Partial Payment Reminder to order actions
     * @param $emails_available
     * @return mixed
     */
    public
    function order_actions($emails_available, $order)
    {

        if (!$order) return $emails_available;

        if ($order->get_type() === 'mepp_payment') return $emails_available;
        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

        if ($order_has_deposit === 'yes') {
            $emails_available['customer_second_payment_reminder'] = esc_html__('Email Partial Payment Reminder', 'advanced-partial-payment-or-deposit-for-woocommerce');

        }

        return $emails_available;
    }


    function legacy_enqueue_scripts()
    {


        $is_order_editor = false;

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen)
                $is_order_editor = $screen->id === 'shop_order';
        }

        if ($is_order_editor) {

            $order_id = isset($_GET['post']) && !empty($_GET['post']) ? $_GET['post'] : false;
            $order = $order_id ? wc_get_order($order_id) : false;
            $original_total = $order ? wc_format_localized_price($order->get_meta('_mepp_original_total', true)) : null;


            wp_enqueue_script('jquery.bind-first', MEPP_PLUGIN_URL . '/assets/js/jquery.bind-first-0.2.3.min.js', array(), MEPP_VERSION);
            wp_enqueue_script('wc-deposits-admin-orders', MEPP_PLUGIN_URL . '/assets/js/admin/Admin.js', array('jquery', 'wc-admin-order-meta-boxes'), MEPP_VERSION, true);
            wp_localize_script('wc-deposits-admin-orders', 'mepp_data',
                array('decimal_separator' => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'number_of_decimals' => wc_get_price_decimals(),
                    'currency_symbol' => get_woocommerce_currency_symbol(),
                    'original_total' => $original_total,
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'security' => wp_create_nonce('wc-deposits-partial-payments-refresh'),

                ));
        }
    }

    /**
     * Enqueue scripts for order editor page
     * @return void
     */
    function enqueue_scripts()
    {


        //identify single order page
        if (isset($_GET['action']) && $_GET['action'] === 'edit') {


            $order_id = isset($_GET['post']) && !empty($_GET['post']) ? $_GET['post'] : false;
            $order = $order_id ? wc_get_order($order_id) : false;
            $original_total = $order ? wc_format_localized_price($order->get_meta('_mepp_original_total', true)) : null;


            wp_enqueue_script('jquery.bind-first', MEPP_PLUGIN_URL . '/assets/js/jquery.bind-first-0.2.3.min.js', array(), MEPP_VERSION);
            //check if hpos is enabled

            wp_enqueue_script('wc-deposits-admin-orders', MEPP_PLUGIN_URL . '/assets/js/admin/Admin.js', array('jquery', 'wc-admin-order-meta-boxes'), MEPP_VERSION, true);
            wp_localize_script('wc-deposits-admin-orders', 'mepp_data',
                array('decimal_separator' => wc_get_price_decimal_separator(),
                    'thousand_separator' => wc_get_price_thousand_separator(),
                    'number_of_decimals' => wc_get_price_decimals(),
                    'currency_symbol' => get_woocommerce_currency_symbol(),
                    'original_total' => $original_total,
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'security' => wp_create_nonce('wc-deposits-partial-payments-refresh'),

                ));
        }
    }

    /**
     * Adjust Order item table headers to display deposit and future payments
     * @return void
     */
    public
    function admin_order_item_headers()
    {

        if (wp_doing_ajax()) {
            $order_id = isset($_POST['order_id']) ? $_POST['order_id'] : false;
        } else {
            global $post;
            $order_id = $post ? $post->ID : false;
        }
        if (!$order_id) return;
        $order = wc_get_order($order_id);
        if (!$order || $order->get_type() === 'mepp_payment') return;


        ?>
        <th class="deposit-paid"><?php echo esc_html__('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
        <th class="deposit-remaining"><?php echo esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
        <?php
    }

    /**
     * @brief Adjust Order item table values to display deposit and future payments
     * @param $product
     * @param $item
     * @param $item_id
     */
    public
    function admin_order_item_values($product, $item, $item_id)
    {
        $deposit_meta = null;
        if ($product) {
            $deposit_meta = isset($item['wc_deposit_meta']) ? $item['wc_deposit_meta'] : null;

        }

        $order_id = wc_get_order_id_by_order_item_id($item_id);
        $order = wc_get_order($order_id);
        if (!$order || $order->get_type() === 'mepp_payment') return;

        $paid = '';
        $remaining = '';
        $price_args = array();
        $price_args = array('currency', $order->get_currency());


        if ($product && isset($deposit_meta, $deposit_meta['enable']) && $deposit_meta['enable'] === 'yes') {
            $item_meta = maybe_unserialize($item['wc_deposit_meta']);
            if (is_array($item_meta) && isset($item_meta['deposit']))
                $paid = round($item_meta['deposit'], wc_get_price_decimals());
            if (is_array($item_meta) && isset($item_meta['remaining']))
                $remaining = round($item_meta['remaining'], wc_get_price_decimals());

        }
        ?>
        <td class="deposit-paid">
            <div class="view">
                <?php
                if ($paid)
                    echo wc_price($paid, $price_args);
                ?>
            </div>
            <?php if ($product) { ?>
                <div class="edit" style="display: none;">
                    <label>
                        <input type="text" disabled="disabled" name="deposit_paid[<?php echo absint($item_id); ?>]"
                               placeholder="<?php echo wc_format_localized_price(0); ?>"
                               value="<?php echo $paid; ?>"
                               class="deposit_paid wc_input_price"
                               data-total="<?php echo $paid; ?>"/>
                    </label>
                </div>
            <?php } ?>
        </td>
        <td class="deposit-remaining">
            <div class="view">
                <?php
                if ($remaining)
                    echo wc_price($remaining, $price_args);
                ?>
            </div>
            <?php if ($product) { ?>
                <div class="edit" style="display: none;">

                    <label>
                        <input type="text" disabled="disabled" name="deposit_remaining[<?php echo absint($item_id); ?>]"
                               placeholder="<?php echo wc_format_localized_price(0); ?>"
                               value="<?php echo $remaining; ?>"
                               class="deposit_remaining wc_input_price"
                               data-total="<?php echo $remaining; ?>"/>
                    </label>
                </div>
            <?php } ?>
        </td>
        <?php
    }

    /**
     * Controls order totals in order editor
     * @param $order_id
     */
    public
    function admin_order_totals_after_total($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order->get_type() === 'mepp_payment') return;
        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
        if ($order_has_deposit !== 'yes') return;
        $payments = mepp_get_order_partial_payments($order_id);
        $deposit = 0.0;
        $remaining = 0.0;

        foreach ($payments as $payment) {
            if ($payment->get_meta('_mepp_payment_type', true) === 'deposit') {
                $deposit += $payment->get_total() - $payment->get_total_refunded();
            } else {
                $remaining += $payment->get_total() - $payment->get_total_refunded();
            }
        }
        ?>
        <tr>
            <td class="label"><?php echo wc_help_tip(esc_html__('Note: Deposit amount is affected by settings for fees, taxes & shipping handling', 'advanced-partial-payment-or-deposit-for-woocommerce')); ?><?php echo esc_html__('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                :
            </td>
            <td></td>
            <td class="total paid"><?php echo wc_price($deposit, array('currency' => $order->get_currency())); ?></td>

        </tr>


        <tr class="mepp-remaining">
            <td class="label"><?php echo esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>:</td>
            <td></td>
            <td class="total remaining"><?php echo wc_price($remaining, array('currency' => $order->get_currency())); ?></td>
        </tr> <?php
    }


    /**
     * Display metabox on Partial payment editor page
     * @return void
     */
    function original_order_metabox($order_or_post)
    {

        $id = $order_or_post instanceof \WP_Post ? $order_or_post->ID : $order_or_post->get_id();
        $order = wc_get_order($id);
        $parent = wc_get_order($order->get_parent_id());
        if (!$parent) return;
        ?>
        <p><?php echo sprintf(esc_html__('This is a partial payment for order %s', 'advanced-partial-payment-or-deposit-for-woocommerce'), $parent->get_order_number()); ?>
        </p>
        <a class="button btn" href="
                  <?php echo esc_url($parent->get_edit_order_url()); ?> "> <?php echo esc_html__('View', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?> </a>
        <?php
        $payment_date = $order->get_meta('_mepp_partial_payment_date', true);

        ?>
        <hr/>
        <label for="partial_payment_datepicker">

            <?php echo esc_html__('Due date:', 'advanced-partial-payment-or-deposit-for-woocommerce') ?>
        </label>
        <input value="<?php echo is_numeric($payment_date) ? date('Y-m-d', $payment_date) : ''; ?>"
               type="date" name="mepp_partial_payment_date" id="partial_payment_datepicker"/>
        <?php
        do_action('mepp_partial_original_order_metabox', $order->get_id());
    }

    /**
     * Display partial payments metabox on order editor page
     * @return void
     */
    function partial_payments_metabox($post_type, $post_or_order_object)
    {

        $order = ($post_or_order_object instanceof \WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
        if (!is_a($order, 'WC_Order')) return;
        if ($order) {
            if ($order->get_type() === 'mepp_payment') {
                $screen = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
                    ? wc_get_page_screen_id('mepp_payment')
                    : 'mepp_payment';


                add_meta_box('mepp_partial_payments',
                    esc_html__('Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    array($this, 'original_order_metabox'),
                    $screen,
                    'side',
                    'high'
                );

            } elseif ($order->get_type() === 'shop_order') {

                $screen = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
                    ? wc_get_page_screen_id('shop-order')
                    : 'shop_order';

                $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true) === 'yes';

                if ($order_has_deposit || $order->is_editable()) {

                    add_meta_box('mepp_partial_payments',
                        esc_html__('Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        array($this, 'partial_payments_summary'),
                        $screen,
                        'normal',
                        'high');

                }
            }
        }

    }


    /**
     * Display all partial payments statuses if no post_status is set in query vars
     * @param $query_vars
     * @return mixed
     */
    function request_query($query_vars)
    {

        if (isset($query_vars['post_type']) && $query_vars['post_type'] === 'mepp_payment') {
            // Status.
            if (empty($query_vars['post_status'])) {
                $post_statuses = wc_get_order_statuses();

                foreach ($post_statuses as $status => $value) {
                    if (isset($wp_post_statuses[$status]) && false === $wp_post_statuses[$status]->show_in_admin_all_list) {
                        unset($post_statuses[$status]);
                    }
                }

                $query_vars['post_status'] = array_keys($post_statuses);
            }
        }
        return $query_vars;

    }

    /**
     * Update Partial payments metabox via AJAX
     * @return void
     */
    function ajax_partial_payments_summary()
    {

        check_ajax_referer('wc-deposits-partial-payments-refresh', 'security');
        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $order_id = absint($_POST['order_id']);
        $order = wc_get_order($order_id);

        if ($order) {
            ob_start();
            include('views/edit-order-partial-payments.php');
            $html = ob_get_clean();
            wp_send_json_success(array('html' => $html));

        }

        wp_die();
    }

    /**
     * Generate Recalculate Deposit Modal data via AJAX
     * @return void
     */
    function get_recalculate_deposit_modal()
    {
        check_ajax_referer('mepp_recalculate_deposit_verify', 'security');
        if (!current_user_can('edit_shop_orders')) {
            wp_die(-1);
        }

        $order_id = absint($_POST['order_id']);
        $order = wc_get_order($order_id);


        if ($order) {
            if ($order->get_status() === 'draft' || $order->get_status() === 'auto-draft') {

                wp_send_json_error(esc_html__('Please save order before calculating deposit.', 'advanced-partial-payment-or-deposit-for-woocommerce'));
                wp_die();
            }

            ob_start();
            include('views/order-recalculate-deposit-modal.php');
            $html = ob_get_clean();
            wp_send_json_success(array('html' => $html));

        }

        wp_die();

    }

    /**
     * Callback for Partial Payments metabox
     * @return void
     */
    function partial_payments_summary($order_or_post)
    {

        $id = $order_or_post instanceof \WP_Post ? $order_or_post->ID : $order_or_post->get_id();
        $order = wc_get_order($id);
        $parent = wc_get_order($order->get_parent_id());

        include('views/edit-order-partial-payments.php');

    }

    /**
     * Save the value of partial payment due date in Partial Payments metabox
     * @param $post_id
     * @return void
     */
    function process_payment_date_datepicker_values($post_id)
    {

        $partial_payment = wc_get_order($post_id);
        if (!$partial_payment || $partial_payment->get_type() !== 'mepp_payment') return;

        //custom reminder date
        $partial_payment_date = isset($_POST['mepp_partial_payment_date']) ? $_POST['mepp_partial_payment_date'] : '';
        $current_date = $partial_payment->get_meta('_mepp_partial_payment_date', true);

        if (!empty($partial_payment_date) && intval($current_date) !== strtotime($partial_payment_date)) {
            $new_timestamp = strtotime($partial_payment_date);

            $parent = wc_get_order($partial_payment->get_parent_id());
            $payment_schedule = $parent->get_meta('_mepp_payment_schedule', true);
            foreach ($payment_schedule as $key => $single_payment) {
                if ($partial_payment->get_id() == $single_payment['id']) {
                    $payment_schedule[$key]['timestamp'] = $new_timestamp;
                }
            }

            $parent->update_meta_data('_mepp_payment_schedule', $payment_schedule);
            $parent->save();

            $partial_payment->update_meta_data('_mepp_partial_payment_date', $new_timestamp);
            $partial_payment->save();
        }

    }

}