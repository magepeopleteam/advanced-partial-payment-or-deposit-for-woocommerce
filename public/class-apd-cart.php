<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cart deposit integration.
 */
class APD_Cart {

    public function __construct() {
        // Display deposit info in cart
        add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'display_cart_deposit_totals' ) );
        add_action( 'woocommerce_after_cart_item_name', array( $this, 'display_cart_item_deposit' ), 10, 2 );
        // Modify cart item display
        add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'cart_item_subtotal' ), 10, 3 );
        // Charge only the deposit. Applied to the calculated cart total so the classic
        // cart, the classic checkout, the Cart/Checkout blocks, the Store API and order
        // creation all agree on the same payable figure.
        add_filter( 'woocommerce_calculated_total', array( $this, 'apply_deposit_total_adjustment' ), 20, 2 );
        // Persist cart data
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
        // AJAX update payment type
        add_action( 'wp_ajax_apd_update_cart_payment_type', array( $this, 'ajax_update_payment_type' ) );
        add_action( 'wp_ajax_nopriv_apd_update_cart_payment_type', array( $this, 'ajax_update_payment_type' ) );
        // AJAX update payment type for ALL cart items (block checkout support)
        add_action( 'wp_ajax_apd_update_cart_payment_type_all', array( $this, 'ajax_update_payment_type_all' ) );
        add_action( 'wp_ajax_nopriv_apd_update_cart_payment_type_all', array( $this, 'ajax_update_payment_type_all' ) );
    }

    /**
     * Display deposit breakdown in cart totals.
     */
    public function display_cart_deposit_totals() {
        $deposit_engine = APD_Deposit::instance();
        $summary = $deposit_engine->get_cart_payment_summary();

        if ( empty( $summary['has_deposit'] ) ) {
            return;
        }

        $settings      = get_option( 'apd_settings', array() );
        $deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' );
        $balance_label = $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment-or-deposit-for-woocommerce' );
        $deposit_total = $summary['deposit_amount'];
        $balance_due   = $summary['balance_due'];

        include APD_PLUGIN_DIR . 'public/views/cart-deposit-summary.php';
    }

    /**
     * Show deposit info under cart item name.
     */
    public function display_cart_item_deposit( $cart_item, $cart_item_key ) {
        $deposit_engine = APD_Deposit::instance();
        if ( ! $deposit_engine->is_deposit_enabled( $cart_item['product_id'] ) ) {
            return;
        }

        $pay_deposit = isset( $cart_item['apd_pay_deposit'] ) ? $cart_item['apd_pay_deposit'] : 'yes';
        if ( $pay_deposit !== 'yes' ) {
            return;
        }

        $product = wc_get_product( $cart_item['product_id'] );
        if ( ! $product ) return;

        $deposit = $deposit_engine->get_cart_item_deposit( $cart_item );

        $settings     = get_option( 'apd_settings', array() );
        $deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' );

        echo '<div class="apd-cart-item-deposit">';
        echo '<small class="apd-cart-deposit-tag">' . esc_html( $deposit_label ) . ': ' . wc_price( $deposit ) . '</small>';
        echo '</div>';
    }

    /**
     * Modify cart item subtotal to show deposit amount.
     */
    public function cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
        $deposit_engine = APD_Deposit::instance();
        if ( ! $deposit_engine->is_deposit_enabled( $cart_item['product_id'] ) ) {
            return $subtotal;
        }

        $pay_deposit = isset( $cart_item['apd_pay_deposit'] ) ? $cart_item['apd_pay_deposit'] : 'yes';
        if ( $pay_deposit !== 'yes' ) {
            return $subtotal;
        }

        $product  = wc_get_product( $cart_item['product_id'] );
        if ( ! $product ) return $subtotal;

        $total_dep = $deposit_engine->get_cart_item_deposit( $cart_item );

        $settings      = get_option( 'apd_settings', array() );
        $deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' );

        return $subtotal . '<br><small class="apd-subtotal-deposit">' . esc_html( $deposit_label ) . ': ' . wc_price( $total_dep ) . '</small>';
    }

    /**
     * Restore cart item data from session.
     */
    public function get_cart_item_from_session( $cart_item, $values ) {
        if ( isset( $values['apd_pay_deposit'] ) ) {
            $cart_item['apd_pay_deposit'] = $values['apd_pay_deposit'];
        }
        if ( isset( $values['apd_custom_deposit'] ) ) {
            $cart_item['apd_custom_deposit'] = floatval( $values['apd_custom_deposit'] );
        }
        return $cart_item;
    }

    /**
     * Reduce the payable cart total to the deposit amount.
     *
     * This runs on every cart calculation. It used to be applied as a negative cart
     * fee, and only on requests that looked like REST/JSON — which meant the classic
     * checkout displayed the full price on page load but created the order from the
     * reduced total, and left gateways initialising against the wrong amount.
     *
     * @param float   $total Calculated cart total.
     * @param WC_Cart $cart  Cart object.
     * @return float
     */
    public function apply_deposit_total_adjustment( $total, $cart = null ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return $total;
        }

        if ( ! $cart instanceof WC_Cart ) {
            return $total;
        }

        $summary = APD_Deposit::instance()->get_cart_payment_summary();

        if ( empty( $summary['has_deposit'] ) || $summary['balance_due'] <= 0 ) {
            return $total;
        }

        $deposit = floatval( $summary['deposit_amount'] );

        // Never raise the total, and never reduce it to nothing.
        if ( $deposit <= 0 || $deposit >= floatval( $total ) ) {
            return $total;
        }

        return round( $deposit, wc_get_price_decimals() );
    }

    /**
     * AJAX: Update payment type in cart.
     */
    public function ajax_update_payment_type() {
        check_ajax_referer( 'apd_public_nonce', 'nonce' );

        $cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? '' ) );
        $payment_type  = sanitize_text_field( wp_unslash( $_POST['payment_type'] ?? 'deposit' ) );

        if ( ! $cart_item_key ) {
            wp_send_json_error( __( 'Invalid item.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $cart = WC()->cart->get_cart();
        if ( isset( $cart[ $cart_item_key ] ) ) {
            $product_id      = intval( $cart[ $cart_item_key ]['product_id'] ?? 0 );
            $deposit_engine  = APD_Deposit::instance();

            if ( ! $product_id || ! $deposit_engine->is_deposit_enabled( $product_id ) ) {
                wp_send_json_error( __( 'Deposits are not available for this item.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
            }

            $forced_deposit  = $deposit_engine->is_force_deposit_enabled( $product_id );

            WC()->cart->cart_contents[ $cart_item_key ]['apd_pay_deposit'] = ( $forced_deposit || $payment_type === 'deposit' ) ? 'yes' : 'no';
            WC()->cart->set_session();
            wp_send_json_success();
        }

        wp_send_json_error( __( 'Item not found.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
    }

    /**
     * AJAX: Update payment type for ALL deposit-eligible cart items.
     * Used by block cart/checkout toggle.
     */
    public function ajax_update_payment_type_all() {
        check_ajax_referer( 'apd_public_nonce', 'nonce' );

        $payment_type = sanitize_text_field( wp_unslash( $_POST['payment_type'] ?? 'deposit' ) );
        $cart_changed = false;

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product_id = intval( $cart_item['product_id'] ?? 0 );
            if ( ! $product_id ) {
                continue;
            }

            $deposit_engine = APD_Deposit::instance();
            if ( ! $deposit_engine->is_deposit_enabled( $product_id ) ) {
                continue;
            }

            $forced_deposit = $deposit_engine->is_force_deposit_enabled( $product_id );
            if ( $forced_deposit ) {
                continue; // Can't toggle forced deposits.
            }

            WC()->cart->cart_contents[ $cart_item_key ]['apd_pay_deposit'] = ( $payment_type === 'deposit' ) ? 'yes' : 'no';
            $cart_changed = true;
        }

        if ( $cart_changed ) {
            WC()->cart->set_session();
            WC()->cart->calculate_totals();
        }

        wp_send_json_success( array(
            'payment_type' => $payment_type,
            'cart_changed' => $cart_changed,
        ) );
    }
}
