<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * My Account deposits tab.
 */
class APD_MyAccount {

    public function __construct() {
        // Register endpoint
        add_action( 'init', array( $this, 'add_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        // Add menu item
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
        // Render content
        add_action( 'woocommerce_account_deposits_endpoint', array( $this, 'render_content' ) );
    }

    /**
     * Register the endpoint.
     */
    public function add_endpoint() {
        add_rewrite_endpoint( 'deposits', EP_ROOT | EP_PAGES );
    }

    /**
     * Add query variable.
     */
    public function query_vars( $vars ) {
        $vars[] = 'deposits';
        return $vars;
    }

    /**
     * Add "Deposits" tab to My Account menu.
     */
    public function add_menu_item( $items ) {
        $new_items = array();
        foreach ( $items as $key => $label ) {
            if ( 'orders' === $key ) {
                $new_items[ $key ] = $label;
                $new_items['deposits'] = __( 'Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce' );
            } else {
                $new_items[ $key ] = $label;
            }
        }
        return $new_items;
    }

    /**
     * Render deposits content.
     */
    public function render_content() {
        $customer_id = get_current_user_id();
        if ( ! $customer_id ) return;

        // Get orders with deposit
        $orders = wc_get_orders( array(
            'customer_id' => $customer_id,
            'limit'       => 20,
            'status'      => array( 'partially-paid', 'completed', 'processing', 'on-hold' ),
            'meta_query'  => array(
                array(
                    'key'     => '_apd_is_deposit',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            ),
        ) );

        $settings       = get_option( 'apd_settings', array() );
        $pay_btn_label  = $settings['pay_button_label'] ?? __( 'Pay Remaining Balance', 'advanced-partial-payment-or-deposit-for-woocommerce' );

        include APD_PLUGIN_DIR . 'public/views/myaccount-deposits.php';
    }
}
