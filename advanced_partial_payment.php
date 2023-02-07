<?php
/**
 * Plugin Name: Advanced Partial/Deposit Payment For Woocommerce
 * Plugin URI: http://mage-people.com
 * Description: This plugin will add Partial Payment System in the Woocommerce Plugin its also support Woocommerce Event Manager Plugin.
 * Version: 2.1.4
 * Author: MagePeople Team
 * Author URI: http://www.mage-people.com/
 * Text Domain: advanced-partial-payment-or-deposit-for-woocommerce
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Initialize the plugin tracker
 *
 * @return void
 */
function appsero_init_tracker_advanced_partial_payment_or_deposit_for_woocommerce() {

    if ( ! class_exists( 'Appsero\Client' ) ) {
      require_once __DIR__ . '/lib/appsero/src/Client.php';
    }

    $client = new Appsero\Client( '32e33bac-f5fd-446b-8451-4bf9942e57e8', 'Advanced Partial Payment and Deposit For Woocommerce', __FILE__ );

    // Active insights
    $client->insights()->init();

}

appsero_init_tracker_advanced_partial_payment_or_deposit_for_woocommerce();

/**
 * Check if WooCommerce is active
 */
function wcpp_woocommerce_is_active()
{
    if (!function_exists('is_plugin_active_for_network'))
        require_once(ABSPATH . '/wp-admin/includes/plugin.php');
    // Check if WooCommerce is active
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        return is_plugin_active_for_network('woocommerce/woocommerce.php');
    }
    return true;
}

// Get plugin data
function wcpp_get_plugin_data($data) {
    $get_wcpp_plugin_data = get_plugin_data( __FILE__ );
    $wcpp_data = $get_wcpp_plugin_data[$data];
    return $wcpp_data;
}

// Added Settings link to plugin action links
add_filter( 'plugin_action_links', 'wcpp_plugin_action_link', 10, 2 );

function wcpp_plugin_action_link( $links_array, $plugin_file_name ){

	if( strpos( $plugin_file_name, basename(__FILE__) ) ) {

		array_unshift( $links_array, '<a href="'.esc_url(admin_url()).'admin.php?page=mage-partial-setting">'.__('Settings','advanced-partial-payment-or-deposit-for-woocommerce').'</a>');
	}
	
	return $links_array;
}

// Added links to plugin row meta
add_filter( 'plugin_row_meta', 'wcpp_plugin_row_meta', 10, 2 );
 
function wcpp_plugin_row_meta( $links_array, $plugin_file_name ) {

    if( strpos( $plugin_file_name, basename(__FILE__) ) ) {

        if(!is_plugin_active( 'mage-partial-payment-pro/mage_partial_pro.php')){
            $wbbm_links = array(
                'docs' => '<a href="'.esc_url("https://docs.mage-people.com/advanced-partial-payment-or-deposit-for-woocommerce/").'" target="_blank">'.__('Docs','advanced-partial-payment-or-deposit-for-woocommerce').'</a>',
                'support' => '<a href="'.esc_url("https://mage-people.com/my-account").'" target="_blank">'.__('Support','advanced-partial-payment-or-deposit-for-woocommerce').'</a>',
                'get_pro' => '<a href="'.esc_url("https://mage-people.com/product/advanced-deposit-partial-payment-for-woocommerce-pro/").'" target="_blank" class="wcpp_plugin_pro_meta_link">'.__('Upgrade to PRO Version','advanced-partial-payment-or-deposit-for-woocommerce').'</a>'             
                );            
        }else{
            $wbbm_links = array(
                'docs' => '<a href="'.esc_url("https://docs.mage-people.com/advanced-partial-payment-or-deposit-for-woocommerce/").'" target="_blank">'.__('Docs','advanced-partial-payment-or-deposit-for-woocommerce').'</a>',
                'support' => '<a href="'.esc_url("https://mage-people.com/my-account").'" target="_blank">'.__('Support','advanced-partial-payment-or-deposit-for-woocommerce').'</a>'            
                );            
        }        
        $links_array = array_merge( $links_array, $wbbm_links );
    }
     
    return $links_array;
}


include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (wcpp_woocommerce_is_active()) {
    define('WCPP_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('WCPP_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('WC_PP_Basic_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/inc/templates/');
    require_once(dirname(__FILE__) . "/inc/file_include.php");

} else {

    function mep_pp_not_active_warning()
    {
        $class = 'notice notice-error';
        $message = __('Advanced Partial/Deposit Payment For Woocommerce is Dependent on Woocommerce', 'advanced-partial-payment-or-deposit-for-woocommerce');
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    add_action('admin_notices', 'mep_pp_not_active_warning');
}

add_action('init', 'wcpp_language_load');
if (!function_exists('wcpp_language_load')) {
    function wcpp_language_load()
    {
        $plugin_dir = basename(dirname(__DIR__)) . "/languages/";
        load_plugin_textdomain('advanced-partial-payment-or-deposit-for-woocommerce', false, $plugin_dir);
    }
}

/*************************
Check the required plugins
***************************/
require_once(dirname(__FILE__) . "/inc/class-wcpp-required-plugins.php");