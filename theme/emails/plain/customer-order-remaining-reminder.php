<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

echo $email_heading . "\n\n";

echo $payment_message;

echo "\n****************************************************\n\n";

do_action('woocommerce_email_before_order_table', $order, $sent_to_admin, $plain_text, $email);

echo sprintf(__('Order number: %s', 'advanced-partial-payment-or-deposit-for-woocommerce'), $order->get_order_number()) . "\n";
echo sprintf(__('Order date: %s', 'advanced-partial-payment-or-deposit-for-woocommerce'), date_i18n(wc_date_format(), strtotime($order->get_date_created()))) . "\n";

do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);

echo wc_get_email_order_items($order);


echo "----------\n\n";

if ($totals = $order->get_order_item_totals()) {
    foreach ($totals as $total) {
        echo $total['label'] . "\t " . $total['value'] . "\n";
    }
}

echo "\n****************************************************\n\n";

do_action('woocommerce_email_after_order_table', $order, $sent_to_admin, $plain_text, $email );

do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);

echo "\n****************************************************\n\n";


/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n----------------------------------------\n\n";
}


echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));
