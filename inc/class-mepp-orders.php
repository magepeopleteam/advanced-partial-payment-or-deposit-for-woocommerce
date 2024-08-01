<?php
namespace MagePeople\MEPP;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use WC_Order, WC_Order_Item_Fee, WC_Geolocation, WP_Error, MEPP_Payment;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


/**
 * Class MEPP_Orders
 */
class MEPP_Orders
{


    /**
     * MEPP_Orders constructor.
     */
    public function __construct()
    {

        // Payment complete events
        add_action('woocommerce_order_status_completed', array($this, 'order_status_completed'), 9);
//        add_action('woocommerce_order_status_processing', array($this, 'complete_partial_payments'));
        add_filter('woocommerce_payment_complete_reduce_order_stock', array($this, 'payment_complete_reduce_order_stock'), 10, 2);

        // Order statuses
        add_filter('wc_order_statuses', array($this, 'order_statuses'));
        add_filter('wc_order_is_editable', array($this, 'order_is_editable'), 10, 2);
        add_filter('woocommerce_valid_order_statuses_for_payment_complete', array($this, 'valid_order_statuses_for_payment_complete'), 10, 2);
        add_filter('woocommerce_order_has_status', array($this, 'order_has_status'), 10, 3);
        add_action('woocommerce_order_status_changed', array($this, 'order_status_changed'), 10, 3);
        add_filter('woocommerce_order_needs_payment', array($this, 'needs_payment'), 10, 2);

        add_action('before_woocommerce_pay', array($this, 'redirect_legacy_links'));
        // Order handling
        if (!mepp_checkout_mode()) {
            add_action('woocommerce_new_order_item', array($this, 'add_order_item_meta'), 10, 2);
            add_filter('woocommerce_order_formatted_line_subtotal', array($this, 'order_formatted_line_subtotal'), 10, 3);
        }

        add_filter('woocommerce_payment_complete_order_status', array($this, 'payment_complete_order_status'), 1000, 2);


        add_filter('woocommerce_get_order_item_totals', array($this, 'get_order_item_totals'), 20, 2);

        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hidden_order_item_meta'));

        add_filter('woocommerce_get_checkout_payment_url', array($this, 'checkout_payment_url'), 10, 2);
        add_filter('woocommerce_create_order', array($this, 'create_order'), 10, 2);
        add_action('woocommerce_pre_payment_complete', array($this, 'payment_complete'));


        add_action('woocommerce_thankyou', array($this, 'disable_order_again_for_partial_payments'), 0);
        add_action('woocommerce_order_details_after_order_table', array($this, 'output_myaccount_partial_payments_summary'));

        add_action('delete_post', array($this, 'delete_partial_payments'), 9);
        add_action('wp_trash_post', array($this, 'trash_partial_payments'));
        add_action('untrashed_post', array($this, 'untrash_partial_payments'));
        add_filter('woocommerce_cancel_unpaid_order', array($this, 'cancel_partial_payments'), 10, 2);

        add_filter('pre_trash_post', array($this, 'prevent_user_trash_partial_payments'), 10, 2);
        add_filter('woocommerce_cod_process_payment_order_status', array($this, 'adjust_cod_status_completed'), 10, 2);
        add_action('woocommerce_order_status_partially-paid', 'wc_maybe_reduce_stock_levels');
        add_action('woocommerce_order_status_partially-paid', array($this, 'adjust_second_payment_status'));
        add_filter('woocommerce_order_status_on-hold', array($this, 'set_parent_order_on_hold'));
        add_filter('woocommerce_order_status_failed', array($this, 'set_parent_order_failed'));
        add_filter('woocommerce_order_status_cancelled', array($this, 'set_partial_payments_as_cancelled'));
        add_action('woocommerce_order_status_partially-paid', array($this, 'adjust_booking_status'));
        add_action('mepp_thankyou', array($this, 'output_parent_order_summary'), 10);


        //
        add_filter('woocommerce_locate_template', array($this, 'locate_form_pay_mepp'), 99, 2);
        add_filter('woocommerce_order_number', array($this, 'partial_payment_number'), 10, 2);

        //update coupon usage restriction
        add_action('woocommerce_order_status_partially-paid', 'wc_update_coupon_usage_counts');

        add_action('woocommerce_checkout_update_order_meta', array($this, 'link_order_item_ids'), 10, 2);

        //link partial payment details to order items
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'set_temporary_key_id'), 20, 4);
        add_action('woocommerce_checkout_create_order_fee_item', array($this, 'set_temporary_key_id'), 20, 4);
        add_action('woocommerce_checkout_create_order_shipping_item', array($this, 'set_temporary_key_id'), 20, 4);
        add_action('woocommerce_checkout_create_order_coupon_item', array($this, 'set_temporary_key_id'), 20, 4);

        add_action('woocommerce_order_status_failed', array($this, 'parent_order_status_failed'));
    }

    

    /***
     * When parent order is set to failed, we need to set all partial payments to failed to prevent sending out reminders
     * @param $order_id
     * @return void
     */
    function parent_order_status_failed($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order && $order->get_type() === 'shop_order') {
            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
            if ($order_has_deposit === 'yes') {
                $children = mepp_get_order_partial_payments($order_id);
                foreach ($children as $child) {
                    if ($child->get_status() !== 'failed') {

                        $child->set_status('failed');
                        $child->save();
                    }
                }
            }

        }
    }

    /**
     * @param $item \WC_Order_Item
     * @param $key
     * @param $values
     * @param $order
     * @return void
     */
    function set_temporary_key_id($item, $key, $values, $order)
    {

        if ($order->get_meta('_mepp_order_has_deposit') !== 'yes') return;
        switch ($item->get_type()) {
            case 'line_item' :
                $item->add_meta_data('_mepp_tmp_key', $key);
                break;
            case 'fee' :
                $item->update_meta_data('_mepp_tmp_key', $key);
                break;
            case 'shipping' :
                $item->update_meta_data('_mepp_tmp_key', $key);
                break;
            case 'coupon':
                $item->update_meta_data('_mepp_tmp_key', $key);
                break;
            default:
                break;
        }

    }

    function link_order_item_ids($order_id, $data)
    {
        $order = wc_get_order($order_id);
        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
        if ($order_has_deposit === 'yes') {

            foreach ($order->get_items(array('line_item', 'fee', 'shipping', 'coupon')) as $item) {
                $key = $item->get_meta('_mepp_tmp_key');
                switch ($item->get_type()) {
                    case 'line_item' :
                        $this->link_item_ids($item, $key, $order);
                        break;
                    case 'fee' :
                        $this->link_fee_ids($item, $key, $order);
                        break;
                    case 'shipping' :
                        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
                        foreach (WC()->shipping()->get_packages() as $package_key => $package) {
                            if (isset($chosen_shipping_methods[$package_key], $package['rates'][$chosen_shipping_methods[$package_key]])) {
                                $this->link_shipping_ids($item, $package_key, $package, $order);
                            }
                        }
                        break;
                    case 'coupon':
                        $this->link_coupon_ids($item, $key, $order);
                        break;
                    default:
                        break;
                }
                $item->delete_meta_data('_mepp_tmp_key');
                $item->save();
            }
            $order->save();
        }


    }

    function link_item_ids($item, $cart_item_key, $order)
    {

        $item_id = $item->get_id();

        $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);
        if (!is_array($payment_schedule) || empty($payment_schedule)) return;

        foreach ($payment_schedule as $key => $payment) {

            if (!isset($payment['details'], $payment['details']['items'])) continue;
            foreach ($payment['details']['items'] as $item_key => $item_detail) {
                if ($item_key === $cart_item_key) {
                    //found a match
                    unset($payment_schedule[$key]['details']['items'][$item_key]);
                    $payment_schedule[$key]['details']['items'][$item_id] = $item_detail;
                }
            }
        }
        $order->update_meta_data('_mepp_payment_schedule', $payment_schedule);

    }

    function link_fee_ids($item, $fee_key, $order)
    {

        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

        if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes') {
            $item_id = $item->get_id();

            $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);
            if (!is_array($payment_schedule) || empty($payment_schedule)) return;
            foreach ($payment_schedule as $key => $payment) {

                if (!isset($payment['details'], $payment['details']['fees'])) continue;
                foreach ($payment['details']['fees'] as $item_key => $item_detail) {

                    if ($item_key === $fee_key) {
                        //found a match
                        unset($payment_schedule[$key]['details']['fees'][$item_key]);
                        $payment_schedule[$key]['details']['fees'][$item_id] = $item_detail;
                    }
                }
            }
            $order->update_meta_data('_mepp_payment_schedule', $payment_schedule);
        }
    }

    function link_shipping_ids($item, $package_key, $package, $order)
    {

        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

        if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes') {
            $item_id = $item->get_id();
            $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);
            if (!is_array($payment_schedule) || empty($payment_schedule)) return;
            //get the chosen rate
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            $shipping_rate = $package['rates'][$chosen_shipping_methods[$package_key]];
            foreach ($payment_schedule as $key => $payment) {

                if (!isset($payment['details'], $payment['details']['shipping'])) continue;

                foreach ($payment['details']['shipping'] as $item_key => $item_detail) {
                    if ($item_key === $shipping_rate->id) {
                        //found a match
                        unset($payment_schedule[$key]['details']['shipping'][$item_key]);
                        $payment_schedule[$key]['details']['shipping'][$item_id] = $item_detail;
                    }
                }
            }

            $order->update_meta_data('_mepp_payment_schedule', $payment_schedule);
        }
    }

    function link_coupon_ids($item, $code, $order)
    {

        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

        if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes') {
            $item_id = $item->get_id();
            $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);
            if (!is_array($payment_schedule) || empty($payment_schedule)) return;
            foreach ($payment_schedule as $key => $payment) {

                if (!isset($payment['details'], $payment['details']['discount'])) continue;
                foreach ($payment['details']['discount'] as $item_key => $item_detail) {

                    if ($item_key === $code) {
                        //found a match
                        unset($payment_schedule[$key]['details']['discount'][$item_key]);
                        $payment_schedule[$key]['details']['discount'][$item_id] = $item_detail;
                    }
                }
            }
            $order->update_meta_data('_mepp_payment_schedule', $payment_schedule);
        }
    }


    function complete_partial_payments($order_id)
    {

        $order = wc_get_order($order_id);
        if (!$order) return;
        if ($order->get_type() === 'mepp_payment') {
            $order->update_status('completed');
            $order->payment_complete();
            $order->save();
        }
    }

    function adjust_second_payment_status($order_id)
    {


        if (mepp_is_shop_order_screen()) {
            $order = wc_get_order($order_id);

            if (!$order) return;
            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

            if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes') {

                $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

                if (!is_array($payment_schedule) || empty($payment_schedule) || count($payment_schedule) > 2) return;

                foreach ($payment_schedule as $payment) {

                    // search for second payment and set it to pending
                    if (isset($payment['id']) && isset($payment['type']) && $payment['type'] !== 'deposit') {

                        $second_payment = wc_get_order($payment['id']);
                        if ($second_payment && !$second_payment->needs_payment()) {
                            $second_payment->set_status('pending');
                            $second_payment->save();
                        }
                    }
                }
            }

        }
    }


    /**
     * @brief allow overriding form-pay.php template to display original order details during partial payment
     * @param $template
     * @param $template_name
     * @param $template_path
     * @return string
     */
    function locate_form_pay_mepp($template, $template_name)
    {

        if ($template_name === 'checkout/form-pay.php' && get_option('mepp_override_payment_form', 'no') === 'yes') {

            global $wp;
            $order_id = $wp->query_vars['order-pay'];
            $order = wc_get_order($order_id);
            if (!$order) return $template;

            if ($order->get_type() === 'mepp_payment') {
                $template = MEPP_TEMPLATE_PATH . '/checkout/form-pay.php';
            }
        }


        return $template;

    }

    /**
     * @brief woocommerce bookings compatibility , set bookings to partially-paid when deposit is paid
     */
    function adjust_booking_status($order_id)
    {
        if (method_exists('WC_Booking_Data_Store', 'get_booking_ids_from_order_id')) {
            $booking_ids = \WC_Booking_Data_Store::get_booking_ids_from_order_id($order_id);
            if (is_array($booking_ids) && !empty($booking_ids)) {
                foreach ($booking_ids as $booking_id) {
                    $booking = new \WC_Booking($booking_id);
                    $booking->set_status('wc-partial-payment');
                    $booking->save();
                }
            }
        }

    }

    function set_partial_payments_as_cancelled($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order && $order->get_type() !== 'mepp_payment' && $order->get_meta('_mepp_order_has_deposit', true) === 'yes') {

            $partial_payments = mepp_get_order_partial_payments($order_id);

            foreach ($partial_payments as $single_payment) {

                $single_payment->update_status('cancelled');

            }
        }
    }

    function set_parent_order_failed($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order && $order->get_type() === 'mepp_payment' && $order->get_meta('_mepp_payment_type', true) === 'deposit') {
            $parent = wc_get_order($order->get_parent_id());
            if ($parent) {

                $parent->update_status('failed');
                $parent->save();
            }
        }
    }


    function set_parent_order_on_hold($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order && $order->get_type() === 'mepp_payment') {
            $parent = wc_get_order($order->get_parent_id());
            if ($parent) {

                // if child order payment method is bacs,  apply it to parent to send the right email instructions
                if ($order->get_payment_method() === 'bacs') {
                    $parent->set_payment_method('bacs');
                }

                $parent->set_status('on-hold');
                $parent->save();
            }
        }
    }

    function adjust_cod_status_completed($status, $order)
    {

        if ($order->get_type() === 'mepp_payment') {
            $status = 'on-hold';
        }
        return $status;
    }

    function cancel_partial_payments($cancel, $order)
    {
        if ($order->get_type() === 'mepp_payment') return false;

        return $cancel;

    }

    function prevent_user_trash_partial_payments($trash, $post)
    {

        if (is_object($post) && $post->post_type === 'mepp_payment') {

            $order = wc_get_order($post->ID);
            if ($order) {
                $parent = wc_get_order($order->get_parent_id());
                if ($parent && $parent->get_status() !== 'trash') {
                    return 'forbidden'; //if value is not null , partial payment won't be trashed
                }
            }
        }

        return $trash;
    }

    function untrash_partial_payments($id)
    {

        if (!$id) {
            return;
        }

        $order = wc_get_order($id);

        if ($order && $order->get_type() === 'shop_order') {

            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
            if ($order_has_deposit === 'yes') {

                $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

                if (!is_array($payment_schedule) || empty($payment_schedule)) return;

                foreach ($payment_schedule as $payment) {

                    if (isset($payment['id']) && is_numeric($payment['id'])) {

                        wp_untrash_post($payment['id']);
                    }
                }
            }
        }

    }

    function trash_partial_payments($id)
    {

        if (!current_user_can('delete_posts') || !$id) {
            return;
        }

        $order = wc_get_order($id);
        if ($order && $order->get_type() === 'shop_order') {

            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
            if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes') {

                $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

                if (!is_array($payment_schedule) || empty($payment_schedule)) return;

                //temporarily remove filter
                remove_filter('pre_trash_post', array(MEPP()->orders, 'prevent_user_trash_partial_payments'), 10);
                foreach ($payment_schedule as $payment) {

                    if (isset($payment['id']) && is_numeric($payment['id'])) {

                        wp_trash_post(absint($payment['id']));
                    }
                }
                add_filter('pre_trash_post', array(MEPP()->orders, 'prevent_user_trash_partial_payments'), 10, 2);

            }
        }

    }

    function delete_partial_payments($id)
    {

        if (!current_user_can('delete_posts') || !$id) {
            return;
        }

        $order = wc_get_order($id);
        if ($order && $order->get_type() === 'shop_order') {

            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
            if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes') {

                $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

                if (!is_array($payment_schedule) || empty($payment_schedule)) return;

                foreach ($payment_schedule as $payment) {

                    if (isset($payment['id']) && is_numeric($payment['id'])) {

                        wp_delete_post(absint($payment['id']), true);
                    }
                }
            }
        }

    }

    function disable_order_again_for_partial_payments($order_id)
    {

        $order = wc_get_order($order_id);
        if ($order && $order->get_type() === 'mepp_payment') {
            remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);
            do_action('mepp_thankyou', $order);
            remove_action('woocommerce_order_details_after_order_table', 'woocommerce_order_again_button');
        }

    }

    function output_myaccount_partial_payments_summary($order)
    {
        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

        if (is_account_page() && $order_has_deposit === 'yes' && apply_filters('mepp_myaccount_show_partial_payments_summary', true, $order)) {

            $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);
            if (!is_array($payment_schedule)) return;
            wc_get_template(
                'order/partial-payments-summary.php', array(
                'order_id' => $order->get_id(),
                'schedule' => $payment_schedule
            ),
                '',
                MEPP_TEMPLATE_PATH
            );
        }

    }

    function output_parent_order_summary($partial_payment)
    {


        if ($partial_payment->get_type() === 'mepp_payment') {
            wc_get_template('order/order-summary.php', array('partial_payment' => $partial_payment, 'order_id' => $partial_payment->get_parent_id()), '', MEPP_TEMPLATE_PATH);
        }


    }

    function checkout_payment_url($url, $order)
    {

        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
        if ($order_has_deposit === 'yes' && $order->get_type() !== 'mepp_payment') {

            $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

            if (is_array($payment_schedule) && !empty($payment_schedule)) {


                foreach ($payment_schedule as $payment) {
                    if (!isset($payment['id'])) continue;
                    $payment_order = wc_get_order($payment['id']);

                    if (!$payment_order) continue;//create one

                    if (!$payment_order || !$payment_order->needs_payment() || $payment_order->get_meta('_mepp_payment_complete') === 'yes') {
                        continue;
                    }

                    $url = $payment_order->get_checkout_payment_url();
                    $url = add_query_arg(
                        array(
                            'payment' => $payment['type'],
                        ), $url
                    );
                    //already reached a payable payment
                    break;
                }


            }

        }

        return $url;
    }

    function payment_complete($order_id)
    {

        $order = wc_get_order($order_id);
        if (!$order || $order->get_type() !== 'mepp_payment') return;

        //temporary value while checking for main order
        // the order status for parent is updated at pre_payment_complete so at this point we need to use this meta as flag//temporary value while checking for main order
        $order->update_meta_data('_mepp_payment_complete', 'yes');

        $order->save();
        $parent_id = $order->get_parent_id();
        $parent = wc_get_order($parent_id);

        if (!$parent) return;

        if ($order->get_meta('_mepp_payment_type', true) === 'deposit') {
            $parent->update_meta_data('_mepp_deposit_paid', 'yes');
            $parent->update_meta_data('_mepp_deposit_payment_time', current_time('timestamp'));
        } else {
            $parent->update_meta_data('_mepp_second_payment_paid', 'yes');
        }
        $parent->save();

        $parent->payment_complete();
        $order->delete_meta_data('_mepp_payment_complete');
        $order->save();
    }


    function redirect_legacy_links()
    {
        global $wp;
        if (!empty($wp->query_vars['order-pay'])) {
            $order_id = absint($wp->query_vars['order-pay']);
            $order = wc_get_order($order_id);
            if (!$order) return;
            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

            if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes' && $order->needs_payment()) {

                //make a second check to ensure the order has partial payments

                $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

                if (is_array($payment_schedule) && !empty($payment_schedule)) {
                    wp_redirect($order->get_checkout_payment_url());
                    exit;
                }
            }
        }
    }

    /**
     * @brief filters whether order can be paid for, based on second payment settings
     * @param $needs_payment
     * @param $order
     * @param $valid_statuses
     * @return bool
     */
    public
    function needs_payment($needs_payment, $order)
    {
        $status = $order->get_status();
        $valid_statuses = mepp_valid_parent_statuses_for_partial_payment();
        if ($order->get_type() === 'mepp_payment') {

            $parent = wc_get_order($order->get_parent_id());
            if (!$parent) return false;
            if (is_checkout_pay_page()) {
                try {
                    $payment_type = $order->get_meta('_mepp_payment_type', true);
                    if (($payment_type === 'deposit' && !$parent->needs_payment()) || ($payment_type !== 'deposit' && !in_array($parent->get_status(), $valid_statuses))) {
                        if (did_action('before_woocommerce_pay') && !did_action('after_woocommerce_pay')) {
                            $needs_payment = false;
                            wc_print_notice(sprintf(__('Main order&rsquo;s status is &ldquo;%s&rdquo;&mdash;it cannot be paid for.', 'advanced-partial-payment-or-deposit-for-woocommerce'), wc_get_order_status_name($parent->get_status())), 'notice');
                        }

                    }
                } catch (\Exception $e) {
                    wc_print_notice($e->getMessage(), 'error');
                }

            }
        }

        if (in_array($status, $valid_statuses)) {
            if (get_option('mepp_remaining_payable', 'yes') === 'yes') {

                $needs_payment = true;
            } else {
                $needs_payment = false;
            }
        }
        return $needs_payment;
    }


    /**
     * @brief hides deposit order item meta from frontend display
     * @param $hidden_meta
     * @return array
     */
    public
    function hidden_order_item_meta($hidden_meta)
    {

        $hidden_meta[] = '_mepp_parent_item_id';
        $hidden_meta[] = 'wc_deposit_meta';
        $hidden_meta[] = '_mepp_tmp_key';

        return $hidden_meta;

    }

    /**
     * @brief update order meta based on order status change
     * @param $order_id
     * @param $old_status
     * @param $new_status
     */
    public
    function order_status_changed($order_id, $old_status, $new_status)
    {

        $order = wc_get_order($order_id);
        if (!$order) return;
        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);
        if ($order->get_type() !== 'mepp_payment' && $order_has_deposit === 'yes') {

            $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

            if (!is_array($payment_schedule) || empty($payment_schedule)) return;


            if ($old_status === 'trash') {


                foreach ($payment_schedule as $payment) {

                    if (isset($payment['id']) && is_numeric($payment['id'])) {

                        wp_untrash_post($payment['id']);
                    }
                }

            }

            $deposit_paid = $order->get_meta('_mepp_deposit_paid', true);

            $valid_statuses = mepp_valid_parent_statuses_for_partial_payment();

            //order marked processing /completed manually
            if (in_array($old_status, $valid_statuses) && ($new_status === 'processing' || $new_status === 'completed') && $deposit_paid === 'yes') {

                $order->update_meta_data('_mepp_deposit_paid', 'yes');
                $order->update_meta_data('_mepp_second_payment_paid', 'yes');

                //manually mark deposit partial payment as completed
                foreach ($payment_schedule as $payment) {

                    $partial_payment = wc_get_order($payment['id']);
                    if ($partial_payment) {
                        $partial_payment->set_status('completed');
                        $partial_payment->save();
                    }

                }
            }

            $order->Save();

        }

        // if partial payment status is changed from on-hold to completed
        if ($order->get_type() === 'wcdp_payment' && $order->get_meta('_wc_deposits_payment_type') === 'deposit' && $old_status === 'on-hold' && $new_status === 'completed') {

            $parent = wc_get_order($order->get_parent_id());
            $valid_statuses = mepp_valid_parent_statuses_for_partial_payment();

            if (!$parent || in_array($parent->get_status(), $valid_statuses)) return;
            if ($order->get_meta('_mepp_payment_type', true) === 'deposit') {
                $parent->update_meta_data('_mepp_deposit_paid', 'yes');
                $parent->update_meta_data('_mepp_deposit_payment_time', current_time('timestamp'));
                $parent->save();
                $parent->payment_complete();
            }
        }
    }


    /**
     * @brief update order meta when order is marked completed
     * @param $order_id
     */

    public
    function order_status_completed($order_id)
    {

        $order = wc_get_order($order_id);
        if (!$order) return;
        if ($order->get_type() === 'mepp_payment') {
            //exclude manual editing of parent

            if (mepp_is_mepp_payment_screen()) {
                //make sure we are triggering this only when the partial payment is edited to avoid loop

                $parent = wc_get_order($order->get_parent_id());

                if (!$parent) return;

                if ($order->get_meta('_mepp_payment_type', true) === 'deposit') {
                    $parent->update_meta_data('_mepp_deposit_paid', 'yes');
                }
                $parent->save();
                $parent->payment_complete();
            }

        } else {
            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);


            if ($order_has_deposit === 'yes') {
                $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

                if (is_array($payment_schedule)) {

                    //suppress partial payment and deposit payment complete emails in this process
                    add_filter('woocommerce_email_enabled_customer_deposit_partially_paid', '__return_false', 99);
                    add_filter('woocommerce_email_enabled_customer_partially_paid', '__return_false', 99);
                    add_filter('woocommerce_email_enabled_partial_payment', '__return_false', 99);
                    foreach ($payment_schedule as $payment) {


                        $payment_order = wc_get_order($payment['id']);

                        if ($payment_order && $payment_order->needs_payment()) {
                            $payment_order->payment_complete();
                            $payment_order->save();
                        }
                    }

                    //remove the email suppression
                    remove_filter('woocommerce_email_enabled_customer_deposit_partially_paid', '__return_false', 99);
                    remove_filter('woocommerce_email_enabled_customer_partially_paid', '__return_false', 99);
                    remove_filter('woocommerce_email_enabled_partial_payment', '__return_false', 99);
                }
                $order->update_meta_data('_mepp_deposit_paid', 'yes');
                $order->update_meta_data('_mepp_second_payment_paid', 'yes');

                $order->save();

            }
        }

    }


    /**
     * @brief returns the proper status for order completion
     * @param $new_status
     * @param $order_id
     * @return string
     */
    public
    function payment_complete_order_status($new_status, $order_id)
    {

        $order = wc_get_order($order_id);

        if ($order) {

            //in case the order status for some reason end up as processing
            if ($order->get_type() === 'mepp_payment' && $new_status = 'processing') return 'completed';

            $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true);

            if ($order_has_deposit === 'yes') {
                //check if all payments are done before allowing default transition
                $payment_schedule = $order->get_meta('_mepp_payment_schedule', true);

                if (!is_array($payment_schedule) || empty($payment_schedule)) return $new_status;
                $all_payments_made = true;

                foreach ($payment_schedule as $payment) {

                    $payment_order = wc_get_order($payment['id']);

                    if ($payment_order && ($payment_order->needs_payment() && $payment_order->get_meta('_mepp_payment_complete') !== 'yes')) {
                        $all_payments_made = false;
                        break;
                    }
                }


                if (!$all_payments_made) {
                    $new_status = mepp_partial_payment_complete_order_status();
                } else {
                    //all payments made status
                    $status = get_option('mepp_order_fully_paid_status', $order->needs_processing() ? 'processing' : 'completed');
                    if (empty($status)) {
                        $status = $order->needs_processing() ? 'processing' : 'completed';
                    }
                    $new_status = apply_filters('mepp_order_fully_paid_status', $status, $order_id);
                    $order->update_meta_data('_mepp_second_payment_paid', 'yes'); //BACKWARD COMPATIBILITY
                    $order->save();
                }
            }
        }
        return $new_status;
    }


    /**
     * @brief handle stock reduction on payment completion
     * @param $reduce
     * @param $order_id
     * @return bool
     */
    public
    function payment_complete_reduce_order_stock($reduce, $order_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_type() === 'mepp_payment') return false;


        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true) === 'yes';

        if ($order_has_deposit) {


            $status = $order->get_status();
            $reduce_on = get_option('mepp_reduce_stock', 'full');
            $valid_statuses = mepp_valid_parent_statuses_for_partial_payment();

            if (in_array($status, $valid_statuses) && $reduce_on === 'full') {
                $reduce = false;
            } elseif ($status === 'processing' && $reduce_on === 'deposit') {
                $reduce = false;
            }
        }

        return $reduce;
    }


    /**
     * @param $editable
     * @param $order
     * @return bool
     */
    public
    function order_is_editable($editable, $order)
    {


        if ($order->has_status('partially-paid')) {
            $allow_edit = get_option('mepp_partially_paid_orders_editable', 'no') === 'yes';

            if ($allow_edit) {
                $editable = true;

            } else {

                $editable = false;

            }
        }
        return $editable;
    }


    /**
     * @param $statuses
     * @param $order
     * @return array
     */
    public
    function valid_order_statuses_for_payment_complete($statuses, $order)
    {


        if ($order->get_type() !== 'mepp_payment' && get_option('mepp_remaining_payable', 'yes') === 'yes') {
            $statuses[] = 'partially-paid';
        }
        return $statuses;
    }

    /**
     * @brief Add the new 'Deposit paid' status to orders
     *
     * @return array
     */
    public
    function order_statuses($order_statuses)
    {
        $new_statuses = array();
        // Place the new status after 'Pending payment'
        foreach ($order_statuses as $key => $value) {
            $new_statuses[$key] = $value;
            if ($key === 'wc-pending') {
                $new_statuses['wc-partially-paid'] = esc_html__('Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
        }
        return $new_statuses;
    }

    /**
     * @brief adds the status partially-paid to woocommerce
     * @param $has_status
     * @param $order
     * @param $status
     * @return bool
     */
    public
    function order_has_status($has_status, $order, $status)
    {
        if ($order->get_status() === 'partially-paid') {
            if (is_array($status)) {
                if (in_array('pending', $status)) {
                    $has_status = true;
                }
            } else {
                if ($status === 'pending') {
                    $has_status = true;
                }
            }
        }
        return $has_status;
    }

    /**
     * @brief adds deposit values to order item meta from cart item meta
     * @param $item_id
     * @param $item
     * @param $order_id
     * @throws \Exception
     */
    public
    function add_order_item_meta($item_id, $item)
    {

        if (is_array($item) && isset($item['deposit'])) {
            try {
                wc_add_order_item_meta($item_id, '_wc_deposit_meta', $item['deposit']);
            } catch (\Exception $e) {
                print_r(new WP_Error('error', $e->getMessage()));
            }
        }
    }

    /**
     * @brief handles the display of order item totals in pay for order , my account  and email theme
     * @param $total_rows
     * @param $order
     * @return mixed
     */
    public
    function get_order_item_totals($total_rows, $order)
    {

        $order_has_deposit = $order->get_meta('_mepp_order_has_deposit', true) === 'yes';


        if ($order_has_deposit)  :

            $to_pay_text = esc_html__(get_option('mepp_to_pay_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');
            $deposit_amount_text = esc_html__(get_option('mepp_deposit_amount_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');
            $future_payment_amount_text = esc_html__(get_option('mepp_second_payment_text'), 'advanced-partial-payment-or-deposit-for-woocommerce');

            if (empty($to_pay_text)) {
                $to_pay_text = esc_html__('To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }


            if (empty($deposit_amount_text)) {
                $deposit_amount_text = esc_html__('Deposit Amount', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }
            if (empty($future_payment_amount_text)) {
                $future_payment_amount_text = esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce');
            }

            $to_pay_text = stripslashes($to_pay_text);
            $deposit_amount_text = stripslashes($deposit_amount_text);
            $future_payment_amount_text = stripslashes($future_payment_amount_text);
            $status = $order->get_status();
            $deposit_paid = $order->get_meta('_mepp_deposit_paid', true);
            $second_payment_paid = $order->get_meta('_mepp_second_payment_paid', true);

            $payments = mepp_get_order_partial_payments($order->get_id());
            $deposit_amount = 0.0;
            $second_payment = 0.0;

            foreach ($payments as $payment) {
                if ($payment->get_meta('_mepp_payment_type', true) === 'deposit') {
                    $deposit_amount += $payment->get_total() - $payment->get_total_refunded();
                } else {
                    $second_payment += $payment->get_total() - $payment->get_total_refunded();
                }
            }


            $received_slug = get_option('woocommerce_checkout_order_received_endpoint', 'order-received');
            $pay_slug = get_option('woocommerce_checkout_order_pay_endpoint', 'order-pay');

            $is_checkout = (get_query_var($received_slug) === '' && is_checkout());
            $valid_statuses = mepp_valid_parent_statuses_for_partial_payment();

            $is_paying_remaining = !!get_query_var($pay_slug) && in_array($status, $valid_statuses);
            $is_email = did_action('woocommerce_email_order_details') > 0;


            if (!$is_checkout || $is_email) {

                $total_rows['deposit_amount'] = array(
                    'label' => $deposit_amount_text,
                    'value' => wc_price($deposit_amount, array('currency' => $order->get_currency()))
                );

                $total_rows['second_payment'] = array(
                    'label' => $future_payment_amount_text,
                    'value' => wc_price($second_payment, array('currency' => $order->get_currency()))
                );


            }


            if ($is_checkout && !$is_paying_remaining && !$is_email) {

                $to_pay = false;
                if ($deposit_paid !== 'yes') {
                    $to_pay = $deposit_amount;
                } elseif ($deposit_paid === 'yes' && $second_payment_paid !== 'yes') {
                    $to_pay = $second_payment;
                }
                if ($to_pay) {
                    $total_rows['paid_today'] = array(
                        'label' => $to_pay_text,
                        'value' => wc_price($to_pay, array('currency' => $order->get_currency()))
                    );
                }

            }

            if ($is_checkout && $is_paying_remaining && !$is_email) {

                $partial_payment_id = absint(get_query_var($pay_slug));
                $partial_payment = wc_get_order($partial_payment_id);

                $total_rows['paid_today'] = array(
                    'label' => $to_pay_text,
                    'value' => wc_price($partial_payment->get_total(), array('currency' => $order->get_currency()))
                );
            }
        endif;
        return $total_rows;
    }


    /**
     * @brief handles formatted subtotal display for orders with deposit
     * @param $subtotal
     * @param $item
     * @param $order
     * @return string
     */
    public
    function order_formatted_line_subtotal($subtotal, $item, $order)
    {

        if (did_action('woocommerce_email_order_details')) return $subtotal;


        if ($order->get_meta('_mepp_order_has_deposit', true) === 'yes') {

            $product = $item->get_product();
            if (!$product) return $subtotal;
            if ($product->get_type() === 'bundle' || isset($item['_bundled_by'])) return $subtotal;

            if ($product && isset($item['wc_deposit_meta'])) {
                $deposit_meta = maybe_unserialize($item['wc_deposit_meta']);
            } else {
                return $subtotal;
            }

            if (is_array($deposit_meta) && isset($deposit_meta['enable']) && $deposit_meta['enable'] === 'yes') {
                $tax = get_option('mepp_tax_display', 'no') === 'yes' ? floatval($item['line_tax']) : 0;

                if (wc_prices_include_tax()) {

                    $deposit = $deposit_meta['deposit'];

                } else {
                    $deposit = $deposit_meta['deposit'] + $tax;

                }

                return $subtotal . '<br/>(' .
                    wc_price($deposit, array('currency' => $order->get_currency())) . ' ' . esc_html__('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce') . ')';
            } else {
                return $subtotal;
            }
        } else {
            return $subtotal;
        }
    }


    function create_order($order_id, $checkout)
    {


        //return if there is no deposit in cart
        if (!isset(WC()->cart->deposit_info['deposit_enabled']) || WC()->cart->deposit_info['deposit_enabled'] !== true) {
            return $order_id;
        }

        $data = $checkout->get_posted_data();


        try {
            $order_id = absint(WC()->session->get('order_awaiting_payment'));
            $cart_hash = WC()->cart->get_cart_hash();
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();

            $order = $order_id ? wc_get_order($order_id) : null;


            /**
             * If there is an order pending payment, we can resume it here so
             * long as it has not changed. If the order has changed, i.e.
             * different items or cost, create a new order. We use a hash to
             * detect changes which is based on cart items + order total.
             */
            if ($order && $order->has_cart_hash($cart_hash) && $order->has_status(array('pending', 'failed'))) {
                // Action for 3rd parties.
                do_action('woocommerce_resume_order', $order_id);

                // Remove all items - we will re-add them later.
                $order->remove_order_items();
            } else {
                $order = new WC_Order();
            }


            $fields_prefix = array(
                'shipping' => true,
                'billing' => true,
            );

            $shipping_fields = array(
                'shipping_method' => true,
                'shipping_total' => true,
                'shipping_tax' => true,
            );

            foreach ($data as $key => $value) {


                if (is_callable(array($order, "set_{$key}"))) {
                    $order->{"set_{$key}"}($value);
                    // Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
                } elseif (isset($fields_prefix[current(explode('_', $key))])) {
                    if (!isset($shipping_fields[$key])) {


                        $order->update_meta_data('_' . $key, $value);
                    }
                }
            }

            do_action('woocommerce_deposits_before_create_order', $order);


            $user_agent = wc_get_user_agent();
            /**
             * @var $checkout \WC_Checkout
             */


            $order->add_meta_data('_mepp_order_has_deposit', 'yes', true);
            $order->set_created_via('checkout');
            $order->set_cart_hash($cart_hash);
            $order->set_customer_id(apply_filters('woocommerce_checkout_customer_id', get_current_user_id()));
            $order_vat_exempt = WC()->cart->get_customer()->get_is_vat_exempt() ? 'yes' : 'no';
            $order->add_meta_data('is_vat_exempt', $order_vat_exempt);
            $order->set_currency(get_woocommerce_currency());
            $order->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax'));
            $order->set_customer_ip_address(WC_Geolocation::get_ip_address());
            $order->set_customer_user_agent($user_agent);
            $order->set_customer_note(isset($data['order_comments']) ? $data['order_comments'] : '');
            $order->set_payment_method('');
            $order->set_shipping_total(WC()->cart->get_shipping_total());
            $order->set_discount_total(WC()->cart->get_discount_total());
            $order->set_discount_tax(WC()->cart->get_discount_tax());
            $order->set_cart_tax(WC()->cart->get_cart_contents_tax() + WC()->cart->get_fee_tax());
            $order->set_shipping_tax(WC()->cart->get_shipping_tax());
            $order->set_total(WC()->cart->get_total('edit'));
            $checkout->create_order_line_items($order, WC()->cart);
            $checkout->create_order_fee_lines($order, WC()->cart);
            $checkout->create_order_shipping_lines($order, WC()->session->get('chosen_shipping_methods'), WC()->shipping()->get_packages());
            $checkout->create_order_tax_lines($order, WC()->cart);
            $checkout->create_order_coupon_lines($order, WC()->cart);


            do_action('woocommerce_deposits_after_create_order', $order);

            /**
             * Action hook to adjust order before save.
             *
             * @since 3.0.0
             */
            do_action('woocommerce_checkout_create_order', $order, $data);

            // Save the order.
            $order_id = $order->save();

            do_action('woocommerce_checkout_update_order_meta', $order_id, $data);

            //create all payments
            $order->read_meta_data();
            $payment_schedule = $order->get_meta('_mepp_payment_schedule');
            $deposit_id = null;
            $partial_payments_structure = apply_filters('mepp_partial_payments_structure', get_option('mepp_partial_payments_structure', 'single'), 'checkout');
            if ($partial_payments_structure !== 'single') {
                $order->add_meta_data('_mepp_itemized_payments', 'yes');
            } else {
                $order->add_meta_data('_mepp_itemized_payments', 'no');
            }
            foreach ($payment_schedule as $partial_key => $payment) {

                $partial_payment = new MEPP_Payment();
                $partial_payment->set_customer_id(apply_filters('woocommerce_checkout_customer_id', get_current_user_id()));

                if ($partial_payments_structure === 'single') {
                    $amount = $payment['total'];
                    //allow partial payments to be inserted only as a single fee without item details
                    $name = esc_html__('Partial Payment for order %s', 'advanced-partial-payment-or-deposit-for-woocommerce');
                    $partial_payment_name = apply_filters('mepp_partial_payment_name', sprintf($name, $order->get_order_number()), $payment, $order->get_id());


                    $item = new WC_Order_Item_Fee();
                    $item->set_props(
                        array(
                            'total' => $amount
                        )
                    );
                    $item->set_name($partial_payment_name);
                    $partial_payment->add_item($item);
                    $partial_payment->set_total($amount);

                } else {
                    $amount = $payment['total'];
                    $partial_payment = MEPP_Advance_Deposits_Admin_order::create_partial_payment_items($partial_payment, $order, $payment['details']);
                    $partial_payment->save();
                    $partial_payment->add_meta_data('_mepp_partial_payment_itemized', 'yes');
                    $partial_payment->set_total($amount);
                }

                $partial_payment->set_parent_id($order->get_id());
                $partial_payment->add_meta_data('is_vat_exempt', $order_vat_exempt);
                $partial_payment->add_meta_data('_mepp_payment_type', $payment['type']);

                if (isset($payment['timestamp']) && is_numeric($payment['timestamp'])) {
                    $partial_payment->add_meta_data('_mepp_partial_payment_date', $payment['timestamp']);
                }

                if (isset($payment['details'])) {
                    $partial_payment->add_meta_data('_mepp_partial_payment_details', $payment['details']);
                }

                $partial_payment->set_currency(get_woocommerce_currency());
                $partial_payment->set_prices_include_tax('yes' === get_option('woocommerce_prices_include_tax'));
                $partial_payment->set_customer_ip_address(WC_Geolocation::get_ip_address());
                $partial_payment->set_customer_user_agent($user_agent);

                $partial_payment->save();

                $payment_schedule[$partial_key]['id'] = $partial_payment->get_id();

                //add wpml language for all child orders for wpml
                $wpml_lang = $order->get_meta('wpml_language', true);
                if (!empty($wpml_lang)) {
                    $partial_payment->update_meta_data('wpml_language', $wpml_lang);
                }

                if ($payment['type'] === 'deposit') {
                    //we need to save to generate id first
                    $deposit_id = $partial_payment->get_id();
                    $partial_payment->set_payment_method(isset($available_gateways[$data['payment_method']]) ? $available_gateways[$data['payment_method']] : $data['payment_method']);
                }

                $partial_payment->save();
                do_action('mepp_partial_payment_created', $partial_payment->get_id(), 'checkout');
            }

            //update the schedule meta of parent order
            $order->update_meta_data('_mepp_payment_schedule', $payment_schedule);
            $order->save();
            return absint($deposit_id);

        } catch (\Exception $e) {
            return new WP_Error('checkout-error', $e->getMessage());
        }
    }


    function partial_payment_number($number, $order)
{
    if (is_order_received_page() && did_action('woocommerce_before_thankyou') && !did_action('woocommerce_thankyou')) return $number;

    if ($order && $order->get_type() === 'mepp_payment') {
        $parent = wc_get_order($order->get_parent_id());
        if ($parent) {
            $payment_schedule = $parent->get_meta('_mepp_payment_schedule', true);
            $count = 0;
            $suffix = '-';
            if (!empty($payment_schedule) && is_array($payment_schedule)) {
                foreach ($payment_schedule as $payment) {
                    $count++;
                    if (isset($payment['id']) && $payment['id'] == $order->get_id()) {
                        $suffix .= $count;
                        break;
                    }
                }
            }

            $number = $parent->get_order_number() . $suffix;
        }
    }
    return $number;
}

    static function get_order_balance_details($order)
    {


        $partial_payments = mepp_get_order_partial_payments($order->get_id());
        $paid = 0.0;
        foreach ($partial_payments as $partial_payment) {
            if ($partial_payment->is_paid() || $partial_payment->get_meta('_mepp_payment_complete') === 'yes') {
                $total = $partial_payment->get_total() - floatval($partial_payment->get_total_refunded());
                $paid += $total;
            }
        }

        $order_total = $order->get_total() - floatval($order->get_total_refunded());

        $order_total = round($order_total, wc_get_price_decimals());
        $paid = round($paid, wc_get_price_decimals());
        $unpaid = $order_total - $paid;

        return array('order_total ' => $order_total, 'paid' => $paid, 'unpaid' => $unpaid);
    }
}

