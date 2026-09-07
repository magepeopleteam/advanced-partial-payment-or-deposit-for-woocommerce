<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo esc_html( APD_Emails::render_email_body( 'deposit_received', $order, $deposit, true ) );
