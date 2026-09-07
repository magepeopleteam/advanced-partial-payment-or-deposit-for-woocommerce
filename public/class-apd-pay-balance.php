<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pay Balance endpoint for customers.
 */
class APD_Pay_Balance {

    public function __construct() {
        add_action( 'init', array( $this, 'add_endpoint' ) );
        add_filter( 'query_vars', array( $this, 'query_vars' ) );
        add_action( 'template_redirect', array( $this, 'handle_pay_balance' ) );
        // AJAX pay balance
        add_action( 'wp_ajax_apd_create_balance_order', array( $this, 'create_balance_order' ) );
    }

    public function add_endpoint() {
        add_rewrite_endpoint( 'pay-deposit-balance', EP_ROOT | EP_PAGES );
    }

    public function query_vars( $vars ) {
        $vars[] = 'pay-deposit-balance';
        return $vars;
    }

    /**
     * Handle the pay balance request.
     */
    public function handle_pay_balance() {
        if ( ! isset( $_GET['apd_pay_balance'] ) ) {
            return;
        }

        $order_id = intval( $_GET['apd_pay_balance'] );
        $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'apd_pay_balance_' . $order_id ) ) {
            wc_add_notice( __( 'Invalid request.', 'advanced-partial-payment' ), 'error' );
            wp_redirect( wc_get_account_endpoint_url( 'deposits' ) );
            exit;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || ! APD_Order::is_deposit_order( $order ) ) {
            wc_add_notice( __( 'Invalid order.', 'advanced-partial-payment' ), 'error' );
            wp_redirect( wc_get_account_endpoint_url( 'deposits' ) );
            exit;
        }

        // Check ownership
        if ( $order->get_customer_id() !== get_current_user_id() ) {
            wc_add_notice( __( 'You do not have permission to pay this balance.', 'advanced-partial-payment' ), 'error' );
            wp_redirect( wc_get_account_endpoint_url( 'deposits' ) );
            exit;
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details || $details['balance_due'] <= 0 ) {
            wc_add_notice( __( 'This order has no outstanding balance.', 'advanced-partial-payment' ), 'notice' );
            wp_redirect( wc_get_account_endpoint_url( 'deposits' ) );
            exit;
        }

        // Update the order total to balance due and redirect to payment
        $order->update_meta_data( '_apd_balance_payment_pending', $details['balance_due'] );
        $order->set_total( $details['balance_due'] );
        if ( $order->get_status() !== 'partially-paid' ) {
            $order->set_status( 'partially-paid', __( 'Customer started a remaining balance payment.', 'advanced-partial-payment' ) );
        }
        $order->save();

        // Redirect to pay page
        wp_redirect( $order->get_checkout_payment_url() );
        exit;
    }

    /**
     * Generate pay balance URL.
     */
    public static function get_pay_balance_url( $order_id ) {
        return wp_nonce_url(
            add_query_arg( 'apd_pay_balance', $order_id, wc_get_page_permalink( 'myaccount' ) ),
            'apd_pay_balance_' . $order_id
        );
    }

    /**
     * AJAX: Create a balance payment order.
     */
    public function create_balance_order() {
        check_ajax_referer( 'apd_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'Please log in to pay the balance.', 'advanced-partial-payment' ) );
        }

        $order_id = intval( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) {
            wp_send_json_error( __( 'Invalid order.', 'advanced-partial-payment' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) {
            wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment' ) );
        }

        if ( ! APD_Order::is_deposit_order( $order ) ) {
            wp_send_json_error( __( 'This order is not a deposit order.', 'advanced-partial-payment' ) );
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details || $details['balance_due'] <= 0 ) {
            wp_send_json_error( __( 'This order has no outstanding balance.', 'advanced-partial-payment' ) );
        }

        $pay_url = self::get_pay_balance_url( $order_id );
        wp_send_json_success( array( 'pay_url' => $pay_url ) );
    }
}
