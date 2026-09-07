<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin deactivator.
 */
class APD_Deactivator {

    /**
     * Run on deactivation.
     */
    public static function deactivate() {
        // Clear any scheduled events
        wp_clear_scheduled_hook( 'apd_daily_balance_check' );
        wp_clear_scheduled_hook( 'apd_send_payment_reminders' );
        flush_rewrite_rules();
    }
}
