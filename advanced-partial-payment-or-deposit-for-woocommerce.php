<?php
/**
 * Plugin Name:       Advanced Partial Payment or Deposit for WooCommerce
 * Plugin URI:        https://www.mage-people.com
 * Description:       Accept partial payments, deposits, and installments on your WooCommerce store. Supports fixed, percentage, category-wise deposits with a professional admin dashboard.
 * Version:           4.0.2
 * Author:            MagePeople Team
 * Author URI:        https://www.mage-people.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       advanced-partial-payment-or-deposit-for-woocommerce
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to:   8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants
 */
define( 'APD_VERSION', '4.0.2' );
define( 'APD_PLUGIN_FILE', __FILE__ );
define( 'APD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'APD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'APD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'APD_REWRITE_VERSION', '1.0.0-account-endpoints-1' );

/**
 * Check if WooCommerce is active
 */
function apd_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return false;
    }
    return true;
}

/**
 * Set a transient on plugin activation to trigger the
 * WooCommerce check / redirect on next admin page load.
 */
function apd_activate() {
    set_transient( 'apd_plugin_activated', true, 60 );
    require_once APD_PLUGIN_DIR . 'includes/class-apd-activator.php';
    APD_Activator::activate();
}
register_activation_hook( __FILE__, 'apd_activate' );

/**
 * Always load the WooCommerce Installer module in admin.
 * It handles: activation redirect when WooCommerce IS active,
 * and shows the beautiful popup when WooCommerce is NOT active.
 */
if ( is_admin() ) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    require_once APD_PLUGIN_DIR . 'includes/class-apd-woo-installer.php';
}

/**
 * Register public rewrite endpoints used by My Account flows.
 * Only register when WooCommerce is active.
 */
function apd_register_rewrite_endpoints() {
    add_rewrite_endpoint( 'deposits', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'pay-deposit-balance', EP_ROOT | EP_PAGES );
}
add_action( 'init', 'apd_register_rewrite_endpoints', 5 );

/**
 * Flush rewrites once when endpoint definitions change.
 */
function apd_maybe_flush_rewrite_rules() {
    $stored_version = get_option( 'apd_rewrite_version', '' );

    if ( APD_REWRITE_VERSION === $stored_version ) {
        return;
    }

    apd_register_rewrite_endpoints();
    flush_rewrite_rules( false );
    update_option( 'apd_rewrite_version', APD_REWRITE_VERSION );
}
add_action( 'init', 'apd_maybe_flush_rewrite_rules', 99 );

/**
 * Deactivation hook
 */
function apd_deactivate() {
    require_once APD_PLUGIN_DIR . 'includes/class-apd-deactivator.php';
    APD_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'apd_deactivate' );

/**
 * HPOS compatibility declaration
 */
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
});

/**
 * Initialize the plugin
 */
function apd_init() {
    if ( ! apd_check_woocommerce() ) {
        return;
    }

    // Load text domain
    load_plugin_textdomain( 'advanced-partial-payment-or-deposit-for-woocommerce', false, dirname( APD_PLUGIN_BASENAME ) . '/languages' );

    // Include core files
    require_once APD_PLUGIN_DIR . 'includes/class-apd-deposit.php';
    require_once APD_PLUGIN_DIR . 'includes/class-apd-order.php';
    require_once APD_PLUGIN_DIR . 'includes/class-apd-emails.php';

    // Admin
    if ( is_admin() ) {
        require_once APD_PLUGIN_DIR . 'admin/class-apd-admin.php';
        require_once APD_PLUGIN_DIR . 'admin/class-apd-admin-settings.php';
        require_once APD_PLUGIN_DIR . 'admin/class-apd-admin-order.php';
        require_once APD_PLUGIN_DIR . 'admin/class-apd-product-meta.php';
        require_once APD_PLUGIN_DIR . 'admin/class-apd-category-meta.php';
        require_once APD_PLUGIN_DIR . 'admin/class-apd-migration.php';

        new APD_Admin();
        new APD_Admin_Settings();
        new APD_Admin_Order();
        new APD_Product_Meta();
        new APD_Category_Meta();
        new APD_Migration();
    }

    // Frontend
    if ( ! is_admin() || wp_doing_ajax() ) {
        require_once APD_PLUGIN_DIR . 'public/class-apd-public.php';
        require_once APD_PLUGIN_DIR . 'public/class-apd-cart.php';
        require_once APD_PLUGIN_DIR . 'public/class-apd-blocks.php';
        require_once APD_PLUGIN_DIR . 'public/class-apd-checkout.php';
        require_once APD_PLUGIN_DIR . 'public/class-apd-myaccount.php';
        require_once APD_PLUGIN_DIR . 'public/class-apd-pay-balance.php';

        new APD_Public();
        new APD_Cart();
        new APD_Blocks();
        new APD_Checkout();
        new APD_MyAccount();
        new APD_Pay_Balance();
    }

    // Initialize deposit engine (global)
    APD_Deposit::instance();
    APD_Order::instance();

    /**
     * Fires after the plugin is fully loaded.
     */
    do_action( 'apd_loaded' );
}
add_action( 'plugins_loaded', 'apd_init', 20 );

/**
 * Helper: Get plugin option
 */
function apd_get_option( $key, $default = '' ) {
    $options = get_option( 'apd_settings', array() );
    return isset( $options[ $key ] ) ? $options[ $key ] : $default;
}

/**
 * Helper: Check if pro addon is active
 */
function apd_is_pro_active() {
    return defined( 'APD_PRO_VERSION' );
}
