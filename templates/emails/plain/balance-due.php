<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo esc_html( APD_Emails::render_email_body( 'balance_due', $order, $deposit, true ) );
