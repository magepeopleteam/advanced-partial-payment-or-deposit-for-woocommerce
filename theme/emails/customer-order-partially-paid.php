<?php

/**
 *  Partial  payment template for customer email
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

?>

<?php do_action('woocommerce_email_header', $email_heading, $email); ?>


<p><?php     echo wp_kses_post( wpautop( wptexturize( __( $email_text, 'advanced-partial-payment-or-deposit-for-woocommerce' ) ) ) ); ?></p>

<?php

if ($order->needs_payment() && get_option('mepp_remaining_payable', 'yes') === 'yes'){
    ?> <p><?php   echo wp_kses_post( wpautop( wptexturize( __( $payment_text, 'advanced-partial-payment-or-deposit-for-woocommerce' ) ) ) ); ?></p>
    <?php
}?>

<?php

/**
 * @hooked WC_Emails::order_details() Shows the order details table.
 * @hooked WC_Structured_Data::generate_order_data() Generates structured data.
 * @hooked WC_Structured_Data::output_structured_data() Outputs structured data.
 * @since 2.5.0
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/**
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );


/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
    echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}


/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );


