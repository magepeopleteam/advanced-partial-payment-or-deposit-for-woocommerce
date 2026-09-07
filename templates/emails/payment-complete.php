<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email );

echo wp_kses_post( APD_Emails::render_email_body( 'payment_complete', $order, $deposit, false ) );

do_action( 'woocommerce_email_footer', $email );
