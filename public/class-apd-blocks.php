<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce Cart/Checkout Blocks compatibility.
 */
class APD_Blocks {

    /**
     * Store API namespace used by the blocks integration.
     */
    const STORE_API_NAMESPACE = 'advanced-partial-payment';

    public function __construct() {
        // WooCommerce fires woocommerce_blocks_loaded on plugins_loaded:10 and this
        // plugin boots on plugins_loaded:20, so the action has normally already run by
        // the time we get here. Hooking it then would silently never fire, leaving the
        // Cart and Checkout blocks without any deposit data to render.
        if ( did_action( 'woocommerce_blocks_loaded' ) ) {
            $this->register_store_api_data();
        } else {
            add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_data' ) );
        }

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register cart extension data for Cart and Checkout blocks.
     */
    public function register_store_api_data() {
        if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
            return;
        }

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
                'namespace'       => self::STORE_API_NAMESPACE,
                'schema_callback' => array( $this, 'get_cart_item_schema' ),
                'data_callback'   => array( $this, 'get_cart_item_data' ),
                'schema_type'     => ARRAY_A,
            )
        );

        woocommerce_store_api_register_endpoint_data(
            array(
                'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
                'namespace'       => self::STORE_API_NAMESPACE,
                'schema_callback' => array( $this, 'get_cart_schema' ),
                'data_callback'   => array( $this, 'get_cart_data' ),
                'schema_type'     => ARRAY_A,
            )
        );
    }

    /**
     * Enqueue lightweight blocks filters for cart and checkout pages.
     */
    public function enqueue_assets() {
        $is_block_cart     = is_cart() && $this->page_uses_block( 'cart' );
        $is_block_checkout = is_checkout()
            && ! ( function_exists( 'is_order_received_page' ) && is_order_received_page() )
            && $this->page_uses_block( 'checkout' );

        if ( ! $is_block_cart && ! $is_block_checkout ) {
            return;
        }

        if ( ! wp_script_is( 'wc-blocks-checkout', 'registered' ) ) {
            return;
        }

        wp_enqueue_script(
            'apd-blocks',
            APD_PLUGIN_URL . 'public/js/apd-blocks.js',
            array( 'wc-blocks-checkout', 'wp-data' ),
            APD_VERSION,
            true
        );

        $settings = get_option( 'apd_settings', array() );

        wp_localize_script(
            'apd-blocks',
            'apd_blocks',
            array(
                'deposit_label' => $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' ),
                'balance_label' => $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment' ),
                'to_pay_now'    => __( 'To Pay Now', 'advanced-partial-payment' ),
                'pay_later'     => __( 'Pay Later', 'advanced-partial-payment' ),
                'ajax_url'      => admin_url( 'admin-ajax.php' ),
                'nonce'         => wp_create_nonce( 'apd_public_nonce' ),
                'strings'       => array(
                    'pay_deposit' => __( 'Pay Deposit', 'advanced-partial-payment' ),
                    'pay_full'    => __( 'Pay Full Amount', 'advanced-partial-payment' ),
                ),
            )
        );
    }

    /**
     * Work out whether the cart or checkout page is rendered with the block.
     *
     * The page content is authoritative: a page holding the shortcode is classic even
     * on a block theme. Only when the page says neither do we fall back to assuming a
     * block theme renders it from a template. Getting this wrong loads the blocks
     * script onto the classic checkout, where it subscribes to the Store API data
     * store and re-renders for a UI that is not on the page.
     *
     * @param string $page 'cart' or 'checkout'.
     * @return bool
     */
    private function page_uses_block( $page ) {
        $block     = 'cart' === $page ? 'woocommerce/cart' : 'woocommerce/checkout';
        $shortcode = 'cart' === $page ? 'woocommerce_cart' : 'woocommerce_checkout';
        $page_id   = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( $page ) : 0;
        $content   = $page_id > 0 ? (string) get_post_field( 'post_content', $page_id ) : '';

        if ( $content && has_block( $block, $content ) ) {
            return true;
        }

        if ( $content && has_shortcode( $content, $shortcode ) ) {
            return false;
        }

        return function_exists( 'wc_current_theme_is_fse_theme' ) && wc_current_theme_is_fse_theme();
    }

    /**
     * Store API schema for cart item level deposit data.
     *
     * @return array<string,array<string,mixed>>
     */
    public function get_cart_item_schema() {
        return array(
            'has_deposit' => array(
                'description' => __( 'Whether this cart item is being paid as a deposit.', 'advanced-partial-payment' ),
                'type'        => 'boolean',
                'readonly'    => true,
            ),
            'deposit_amount_html' => array(
                'description' => __( 'Formatted deposit amount for this cart item.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'deposit_label' => array(
                'description' => __( 'Deposit label for this cart item.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
        );
    }

    /**
     * Store API schema for block totals labels.
     *
     * @return array<string,array<string,mixed>>
     */
    public function get_cart_schema() {
        return array(
            'has_deposit' => array(
                'description' => __( 'Whether the cart includes at least one deposit payment item.', 'advanced-partial-payment' ),
                'type'        => 'boolean',
                'readonly'    => true,
            ),
            'deposit_total_label' => array(
                'description' => __( 'The label shown beside the payable amount in Cart and Checkout blocks.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'total_value_label' => array(
                'description' => __( 'The formatted total value text for Cart and Checkout blocks.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'deposit_amount_html' => array(
                'description' => __( 'Formatted deposit amount shown in Cart and Checkout blocks.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'balance_due_html' => array(
                'description' => __( 'Formatted balance due amount shown in Cart and Checkout blocks.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'deposit_description' => array(
                'description' => __( 'Description shown below the deposit label in Cart and Checkout blocks.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'balance_description' => array(
                'description' => __( 'Description shown below the balance due label in Cart and Checkout blocks.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
            'balance_label' => array(
                'description' => __( 'Balance due label shown in Cart and Checkout blocks.', 'advanced-partial-payment' ),
                'type'        => 'string',
                'readonly'    => true,
            ),
        );
    }

    /**
     * Store API data consumed by cart line item enhancements.
     *
     * @param array $cart_item Cart item array.
     * @return array<string,mixed>
     */
    public function get_cart_item_data( $cart_item ) {
        $product_id = isset( $cart_item['product_id'] ) ? intval( $cart_item['product_id'] ) : 0;

        if ( ! $product_id || ! APD_Deposit::instance()->is_deposit_enabled( $product_id ) ) {
            return array(
                'has_deposit'         => false,
                'deposit_amount_html' => '',
                'deposit_label'       => '',
            );
        }

        $pay_deposit = isset( $cart_item['apd_pay_deposit'] ) ? $cart_item['apd_pay_deposit'] : 'yes';

        if ( 'yes' !== $pay_deposit ) {
            return array(
                'has_deposit'         => false,
                'deposit_amount_html' => '',
                'deposit_label'       => '',
            );
        }

        $total_deposit  = APD_Deposit::instance()->get_cart_item_deposit( $cart_item );
        $settings       = get_option( 'apd_settings', array() );
        $deposit_label  = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' );

        return array(
            'has_deposit'         => true,
            'deposit_amount_html' => wc_price( $total_deposit ),
            'deposit_label'       => $deposit_label,
        );
    }

    /**
     * Store API data consumed by Cart and Checkout block filters.
     *
     * @return array<string,mixed>
     */
    public function get_cart_data() {
        $summary       = APD_Deposit::instance()->get_cart_payment_summary();
        $settings      = get_option( 'apd_settings', array() );
        $deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' );

        return array(
            'has_deposit'         => ! empty( $summary['has_deposit'] ),
            'deposit_total_label' => sprintf(
                '%1$s (%2$s)',
                $deposit_label,
                __( 'To Pay Now', 'advanced-partial-payment' )
            ),
            'total_value_label'   => __( 'Pay <price/> now', 'advanced-partial-payment' ),
            'deposit_amount_html' => wc_price( $summary['deposit_amount'] ),
            'balance_due_html'    => wc_price( $summary['balance_due'] ),
            'deposit_description' => __( 'To Pay Now', 'advanced-partial-payment' ),
            'balance_description' => __( 'Pay Later', 'advanced-partial-payment' ),
            'balance_label'       => $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment' ),
        );
    }
}
