<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin activator.
 */
class APD_Activator {

    /**
     * Run on activation.
     */
    public static function activate() {
        self::create_default_options();
        self::register_order_statuses();
        if ( function_exists( 'apd_register_rewrite_endpoints' ) ) {
            apd_register_rewrite_endpoints();
        }
        flush_rewrite_rules();
        update_option( 'apd_rewrite_version', APD_REWRITE_VERSION );
    }

    /**
     * Set default options.
     */
    private static function create_default_options() {
        $defaults = array(
            'enable_deposit'       => 'yes',
            'deposit_type'         => 'percentage',
            'deposit_value'        => '50',
            'allow_full_payment'   => 'yes',
            'force_deposit'        => 'no',
            'deposit_label'        => __( 'Deposit', 'advanced-partial-payment' ),
            'due_balance_label'    => __( 'Due Balance', 'advanced-partial-payment' ),
            'pay_button_label'     => __( 'Pay Remaining Balance', 'advanced-partial-payment' ),
            'deposit_text'         => __( 'Pay a deposit of {deposit_amount}', 'advanced-partial-payment' ),
            'full_payment_text'    => __( 'Pay full amount of {full_amount}', 'advanced-partial-payment' ),
            'email_deposit_received' => 'yes',
            'email_balance_due'    => 'yes',
            'email_payment_complete' => 'yes',
        );

        if ( ! get_option( 'apd_settings' ) ) {
            update_option( 'apd_settings', $defaults );
        }

        // Version
        update_option( 'apd_version', APD_VERSION );
    }

    /**
     * Register custom order status.
     */
    private static function register_order_statuses() {
        // Just ensure WC is ready; actual registration happens via hook
    }
}
