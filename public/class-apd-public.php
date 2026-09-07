<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Public (frontend) hooks — enqueue assets.
 */
class APD_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        // Show deposit selector on product page
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_product_deposit_form' ), 25 );
        // Add deposit choice to cart item data
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
    }

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_assets() {
        if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
            return;
        }

        wp_enqueue_style(
            'apd-public',
            APD_PLUGIN_URL . 'public/css/apd-public.css',
            array(),
            APD_VERSION
        );
        wp_enqueue_script(
            'apd-public',
            APD_PLUGIN_URL . 'public/js/apd-public.js',
            array( 'jquery' ),
            APD_VERSION,
            true
        );
        wp_localize_script( 'apd-public', 'apd_public', array(
            'ajax_url'    => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'apd_public_nonce' ),
            'currency'    => get_woocommerce_currency_symbol(),
        ) );
    }

    /**
     * Show deposit/full-payment radio selector on single product page.
     */
    public function render_product_deposit_form() {
        global $product;
        if ( ! $product ) return;

        $deposit_engine = APD_Deposit::instance();
        $deposit_type   = $deposit_engine->get_deposit_type( $product->get_id() );

        // Allow pro addons to suppress this form entirely
        if ( apply_filters( 'apd_suppress_deposit_form', false, $product->get_id() ) ) {
            return;
        }

        // Payment plans from the pro addon replace the free deposit form only when the
        // effective deposit type is set to payment plans.
        if ( $deposit_type === 'payment_plan' && class_exists( 'APD_Payment_Plans' ) ) {
            $plans = APD_Payment_Plans::get_plans_for_product( $product->get_id() );

            if ( ! empty( $plans ) ) {
                return;
            }
        }

        if ( ! $deposit_engine->is_deposit_enabled( $product->get_id() ) ) {
            return;
        }

        // Payment Plan type: the pro plan selector will handle it
        if ( $deposit_type === 'payment_plan' ) {
            return;
        }

        $price          = floatval( $product->get_price() );
        $allow_full     = $deposit_engine->is_full_payment_allowed( $product->get_id() );

        $settings       = get_option( 'apd_settings', array() );
        $deposit_label  = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' );
        $balance_label  = $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment' );
        $deposit_text   = $settings['deposit_text'] ?? 'Pay a deposit of {deposit_amount}';
        $full_text      = $settings['full_payment_text'] ?? 'Pay full amount of {full_amount}';

        // Min/Max type: show a range input so customer chooses their deposit
        if ( $deposit_type === 'min_max' && defined( 'APD_PRO_VERSION' ) ) {
            $global_min = floatval( apd_get_option( 'min_deposit_amount', 0 ) );
            $global_max = floatval( apd_get_option( 'max_deposit_amount', 0 ) );
            $prod_min   = get_post_meta( $product->get_id(), '_apd_min_deposit', true );
            $prod_max   = get_post_meta( $product->get_id(), '_apd_max_deposit', true );
            $min_deposit = ( $prod_min !== '' && $prod_min !== false ) ? floatval( $prod_min ) : $global_min;
            $max_deposit = ( $prod_max !== '' && $prod_max !== false ) ? floatval( $prod_max ) : $global_max;

            // Sensible defaults
            if ( $min_deposit <= 0 ) $min_deposit = round( $price * 0.10, 2 ); // 10% floor
            if ( $max_deposit <= 0 || $max_deposit > $price ) $max_deposit = $price;
            if ( $min_deposit > $max_deposit ) $min_deposit = $max_deposit;

            $default_deposit = $min_deposit;

            include APD_PLUGIN_DIR . 'public/views/product-deposit-form-minmax.php';
            return;
        }

        $deposit_amount = $deposit_engine->get_deposit_amount( $product->get_id(), $price );
        $due_balance    = $price - $deposit_amount;
        $deposit_value  = $deposit_engine->get_deposit_value( $product->get_id() );

        if ( 'percentage' === $deposit_type ) {
            $deposit_text = sprintf(
                __( 'Pay %1$s%% deposit now (%2$s)', 'advanced-partial-payment' ),
                wc_format_localized_decimal( $deposit_value ),
                wc_price( $deposit_amount )
            );
        } elseif ( 'fixed' === $deposit_type ) {
            $deposit_text = sprintf(
                __( 'Pay fixed deposit of %s', 'advanced-partial-payment' ),
                wc_price( $deposit_amount )
            );
        } else {
            $deposit_text = str_replace( '{deposit_amount}', wc_price( $deposit_amount ), $deposit_text );
        }

        $full_text    = str_replace( '{full_amount}', wc_price( $price ), $full_text );

        include APD_PLUGIN_DIR . 'public/views/product-deposit-form.php';
    }

    /**
     * Add deposit choice to cart item data.
     */
    public function add_cart_item_data( $cart_item_data, $product_id ) {
        $deposit_engine = APD_Deposit::instance();
        $payment_type   = isset( $_POST['apd_payment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['apd_payment_type'] ) ) : '';

        if ( $deposit_engine->is_force_deposit_enabled( $product_id ) ) {
            $cart_item_data['apd_pay_deposit'] = 'yes';
        } elseif ( $payment_type ) {
            $cart_item_data['apd_pay_deposit'] = 'deposit' === $payment_type ? 'yes' : 'no';
        } else {
            // Default: pay deposit if enabled
            if ( $deposit_engine->is_deposit_enabled( $product_id ) ) {
                $cart_item_data['apd_pay_deposit'] = 'yes';
            }
        }

        if (
            'yes' === ( $cart_item_data['apd_pay_deposit'] ?? 'no' ) &&
            'min_max' === $deposit_engine->get_deposit_type( $product_id ) &&
            isset( $_POST['apd_custom_deposit'] )
        ) {
            $product = wc_get_product( $product_id );
            $price   = $product ? floatval( $product->get_price() ) : 0;

            $cart_item_data['apd_custom_deposit'] = $deposit_engine->sanitize_custom_deposit(
                $product_id,
                floatval( wp_unslash( $_POST['apd_custom_deposit'] ) ),
                $price
            );
        }

        return $cart_item_data;
    }
}
