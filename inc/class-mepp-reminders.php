<?php
namespace MagePeople\MEPP;

use Automattic\WooCommerce\Utilities\OrderUtil;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MEPP_Reminders
{
    function __construct()
    {

        //reminder for datepicker setting
        add_action('mepp_job_scheduler', array($this, 'second_payment_datepicker_reminder'));

        //reminder for after X days setting
        $second_payment_reminder = get_option('mepp_enable_second_payment_reminder');
        $partial_payment_reminder = get_option('mepp_enable_partial_payment_reminder');

        if ($second_payment_reminder === 'yes') {

            add_action('mepp_job_scheduler', array($this, 'second_payment_reminder'));

        }

        if ($partial_payment_reminder === 'yes') {
            add_action('mepp_job_scheduler', array($this, 'payment_plan_partial_payment_reminder'));
        }

        /** PRODUCT BASED REMINDERS */


        //the core reminder cron hook
        add_action('mepp_job_scheduler', array($this, 'second_payment_product_based_reminder'));


    }

    function payment_plan_partial_payment_reminder()
    {

        $reminder_days = get_option('mepp_partial_payment_reminder_x_days_before_due_date');
        $date = date("Y-m-d", current_time('timestamp'));
        $target_due_date = strtotime("$date +{$reminder_days} days");


        if (empty($reminder_days)) return;

        $args = array(
            'status' => array('wc-pending', 'wc-failed'), // Specify the order status\
            'type' => 'mepp_payment',
            'limit' => -1, // Limit of orders
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => '_mepp_payment_type',
                    'value' => 'partial_payment',
                    'compare' => '='
                ),
                array(
                    'key' => '_mepp_partial_payment_date',
                    'value' => array(strtotime($date), $target_due_date),
                    'compare' => 'EXISTS',
                ),
                array(
                    'key' => '_mepp_partial_payment_date',
                    'value' => array(strtotime($date), $target_due_date),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                ),
                array(
                    'key' => '_mepp_partial_payment_reminder_email_sent',
                    'value' => 'yes',
                    'compare' => '!='
                ),
            )
        );


// Query for all relevant orders
        $orders = wc_get_orders($args);
        foreach ($orders as $order) {
            if (!$order->meta_exists('_mepp_partial_payment_date') || $order->get_meta('_mepp_partial_payment_reminder_email_sent') === 'yes') continue;

            $order->update_meta_data('_mepp_partial_payment_reminder_email_sent', 'yes');
            $order->update_meta_data('_mepp_partial_payment_reminder_email_sent_time', current_time('timestamp'));
            $order->save();
            do_action('woocommerce_deposits_second_payment_reminder_email', $order->get_parent_id(), true, $order->get_id());
        }

    }


    /**
     * @brief handle second payment reminder email triggered by product datepicker setting
     */
    function second_payment_product_based_reminder()
    {

        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_key' => '_mepp_pbr_reminder_date',
            'meta_value' => date('d-m-Y'),
            'meta_compare' => '=',
        );

        $products = wc_get_products($args);

        if (empty($products)) return;

        foreach ($products as $product) {

            $order_ids = $this->retrieve_orders_ids_from_a_product_id(intval($product->get_id()));
            if (!empty($order_ids)) {
                $orders = wc_get_orders(array('include' => $order_ids, 'meta_key' => '_mepp_second_payment_reminder_email_pbr_time', 'meta_compare' => 'EXISTS'));
                foreach ($orders as $order) {

                    if ($order && $order->needs_payment()) {
                        $reminder_already_sent = $order->get_meta('_mepp_second_payment_reminder_email_pbr_sent', true);

                        if ($reminder_already_sent !== 'yes') {

                            do_action('woocommerce_deposits_second_payment_reminder_email', $order->get_id());

                            $order->update_meta_data('_mepp_second_payment_reminder_email_pbr_sent', 'yes');
                            $order->update_meta_data('_mepp_second_payment_reminder_email_pbr_time', current_time('timestamp'));
                            $order->save_meta_data();
                            $order->save();
                        }

                    }

                }
            }
        }
    }

    /**
     * @brief handle second payment reminder email triggered by datepicker setting
     */
    function second_payment_datepicker_reminder()
    {

        $reminder_date = get_option('mepp_reminder_datepicker');

        if (date('Y-m-d', current_time('timestamp')) == date('Y-m-d', strtotime($reminder_date))) {

            $args = array(
                'status' => 'partially-paid', // Using 'status' instead of 'post_status'
                'limit' => -1, // 'posts_per_page' is replaced with 'limit'
                // Add additional arguments as needed
            );

            // Using wc_get_orders to query orders
            $partially_paid_orders = wc_get_orders($args);

            foreach ($partially_paid_orders as $order) {
                $order_id = $order->get_id();

                do_action('woocommerce_deposits_second_payment_reminder_email', $order_id);
            }

        }


    }

    /**
     * @brief handles second payment reminder email trigger
     */
    public function second_payment_reminder()
    {


        $reminder_duration_option = get_option('mepp_second_payment_reminder_duration');
        $reminder_duration = intval($reminder_duration_option);
        $reminder_duration = $reminder_duration * 24 * 60 * 60;
        $reminder_duration = current_time('timestamp') + $reminder_duration;


        // Define your meta query arguments
        $args = array(
            'status' => 'partially-paid', // Order status
            'limit' => -1, // Limiting number of results
            'type' => 'shop_order', // Post type
            'meta_query' => array(

                array(
                    'key' => '_mepp_second_payment_reminder_email_sent', // Meta key for order total
                    'compare' => 'NOT EXISTS'
                ),
            ),
        );

        // Get orders based on the meta query
        $partially_paid_orders = wc_get_orders($args);

        // Loop through the orders
        foreach ($partially_paid_orders as $order) {
            // Do something with each order
            $order_id = $order->get_id();
            if ($order->meta_exists('_mepp_second_payment_reminder_email_sent')) continue;
            $deposit_payment_date = $order->get_meta('_mepp_deposit_payment_time', true);

            if ($deposit_payment_date > 0) {
                $now = strtotime(date('Y-m-d', current_time('timestamp')));
                $duration_since_deposit_paid = round($now - intval($deposit_payment_date));

                //change the date to days
                $duration_since_deposit_paid = round($duration_since_deposit_paid / (60 * 60 * 24));


                if (intval($duration_since_deposit_paid) >= intval($reminder_duration_option)) {

                    do_action('woocommerce_deposits_second_payment_reminder_email', $order_id);
                    $order->update_meta_data('_mepp_second_payment_reminder_email_sent', 'yes');
                    $order->update_meta_data('_mepp_second_payment_reminder_email_time', current_time('timestamp'));
                    $order->save_meta_data();
                    $order->save();
                }
            }
        }
    }

    function retrieve_orders_ids_from_a_product_id($product_id)
    {
        global $wpdb;

        $orders_statuses = "'wc-partially-paid'";


        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $orders_table = $wpdb->prefix . 'wc_orders';
            $id_column = 'id';
            $status_column = 'status';
        } else {
            $orders_table = $wpdb->prefix . 'posts';
            $id_column = 'ID';
            $status_column = 'post_status';
        }

        $orders_ids = $wpdb->get_col("
        SELECT DISTINCT woi.order_id
        FROM {$wpdb->prefix}woocommerce_order_itemmeta as woim, 
             {$wpdb->prefix}woocommerce_order_items as woi, 
             $orders_table as o
        WHERE  woi.order_item_id = woim.order_item_id
        AND woi.order_id = o.$id_column
        AND o.$status_column IN ( $orders_statuses )
        AND woim.meta_key LIKE '_product_id'
        AND woim.meta_value LIKE '$product_id'
        ORDER BY woi.order_item_id DESC"
        );
        // Return an array of Orders IDs for the given product ID
        return $orders_ids;
    }

}