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
        $order    = wc_get_order( $order_id );

        if ( ! $order || ! APD_Order::is_deposit_order( $order ) ) {
            $this->reject( __( 'Invalid order.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        if ( ! $this->can_pay_balance( $order ) ) {
            $this->reject( __( 'You do not have permission to pay this balance.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details || $details['balance_due'] <= 0 ) {
            $this->reject( __( 'This order has no outstanding balance.', 'advanced-partial-payment-or-deposit-for-woocommerce' ), 'notice' );
        }

        // Resolve server-side. With flexible payments off this is always the full balance.
        $amount = APD_Order::sanitize_balance_payment_amount(
            $order,
            isset( $_GET['apd_amount'] ) ? wc_format_decimal( wp_unslash( $_GET['apd_amount'] ) ) : 0
        );

        if ( $amount <= 0 ) {
            $this->reject( __( 'Please enter a valid payment amount.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        // Charge exactly what was authorised and remember it for finalization.
        $order->update_meta_data( '_apd_balance_payment_pending', $amount );
        $order->set_total( $amount );
        if ( $order->get_status() !== 'partially-paid' ) {
            $order->set_status( 'partially-paid', __( 'Customer started a remaining balance payment.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }
        $order->save();

        // Redirect to pay page
        wp_safe_redirect( $order->get_checkout_payment_url() );
        exit;
    }

    /**
     * Decide whether the current visitor may pay this order's balance.
     *
     * Two ways in, mirroring WooCommerce itself:
     *  - a matching order key, which is how emailed links and guest orders authenticate;
     *  - a nonce plus real ownership, which is how the My Account button authenticates.
     *
     * A guest order has no owner, so `get_customer_id() === get_current_user_id()` would
     * be 0 === 0 for any logged-out visitor. Such orders must present the order key.
     *
     * @param WC_Order $order Order object.
     * @return bool
     */
    private function can_pay_balance( $order ) {
        $key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
        if ( $key && hash_equals( (string) $order->get_order_key(), $key ) ) {
            return true;
        }

        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'apd_pay_balance_' . $order->get_id() ) ) {
            return false;
        }

        $customer_id = $order->get_customer_id();

        if ( ! $customer_id || ! is_user_logged_in() ) {
            return false;
        }

        return $customer_id === get_current_user_id();
    }

    /**
     * Send the customer back to the deposits list with a message.
     *
     * @param string $message Notice text.
     * @param string $type    WooCommerce notice type.
     */
    private function reject( $message, $type = 'error' ) {
        wc_add_notice( $message, $type );
        wp_safe_redirect( wc_get_account_endpoint_url( 'deposits' ) );
        exit;
    }

    /**
     * Generate pay balance URL for the logged-in My Account flow.
     *
     * @param int   $order_id Order ID.
     * @param float $amount   Optional specific amount to put towards the balance.
     * @return string
     */
    public static function get_pay_balance_url( $order_id, $amount = 0 ) {
        $args = array( 'apd_pay_balance' => $order_id );

        if ( $amount > 0 ) {
            $args['apd_amount'] = $amount;
        }

        return wp_nonce_url(
            add_query_arg( $args, wc_get_page_permalink( 'myaccount' ) ),
            'apd_pay_balance_' . $order_id
        );
    }

    /**
     * Generate a pay balance URL suitable for emails.
     *
     * Emails are sent from cron or admin context and may be opened days later by a
     * logged-out customer, so a user-bound, expiring nonce is the wrong credential
     * here. The order key is what WooCommerce itself puts in order emails.
     *
     * @param WC_Order $order Order object.
     * @return string
     */
    public static function get_email_pay_balance_url( $order ) {
        return add_query_arg(
            array(
                'apd_pay_balance' => $order->get_id(),
                'key'             => $order->get_order_key(),
            ),
            wc_get_page_permalink( 'myaccount' )
        );
    }

    /**
     * AJAX: Create a balance payment order.
     */
    public function create_balance_order() {
        check_ajax_referer( 'apd_public_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( __( 'Please log in to pay the balance.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $order_id = intval( $_POST['order_id'] ?? 0 );
        if ( ! $order_id ) {
            wp_send_json_error( __( 'Invalid order.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $order = wc_get_order( $order_id );
        if ( ! $order || $order->get_customer_id() !== get_current_user_id() ) {
            wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        if ( ! APD_Order::is_deposit_order( $order ) ) {
            wp_send_json_error( __( 'This order is not a deposit order.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details || $details['balance_due'] <= 0 ) {
            wp_send_json_error( __( 'This order has no outstanding balance.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $amount = APD_Order::sanitize_balance_payment_amount(
            $order,
            isset( $_POST['amount'] ) ? wc_format_decimal( wp_unslash( $_POST['amount'] ) ) : 0
        );

        if ( $amount <= 0 ) {
            wp_send_json_error( __( 'Please enter a valid payment amount.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $pay_url = self::get_pay_balance_url( $order_id, $amount );
        wp_send_json_success( array( 'pay_url' => $pay_url, 'amount' => $amount ) );
    }
}
