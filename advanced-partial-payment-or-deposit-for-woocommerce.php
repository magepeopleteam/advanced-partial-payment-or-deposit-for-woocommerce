<?php
/**
 * Plugin Name: Deposit & Partial Payment Solution for WooCommerce - WpDepositly | MagePeople
 * Plugin URI: http://mage-people.com
 * Description: This plugin will add Partial Payment System in the Woocommerce Plugin its also support Woocommerce Event Manager Plugin.
 * Version: 2.2.5
 * Author: MagePeople Team
 * Author URI: http://www.mage-people.com/
 * Text Domain: advanced-partial-payment-or-deposit-for-woocommerce
 * Domain Path: /language
 */

namespace MagePeople\MEPP;

use stdClass;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Check if WooCommerce is active
 */
function mepp_woocommerce_is_active()
{
    if (!function_exists('is_plugin_active_for_network')) {
        require_once(ABSPATH . '/wp-admin/includes/plugin.php');
    }

    // Check if WooCommerce is active
    $woocommerce_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));

    if (!$woocommerce_active && is_admin()) {
        // WooCommerce is not active, display notice using JavaScript
        add_action('admin_footer', function() {
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Create notice element
                    var notice = '<div class="notice notice-error">';
                    notice += '<p><?php _e( 'Deposit & Partial Payment Solution for WooCommerce - WpDepositly requires WooCommerce to be installed and activated.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></p>';
                    // Add Install WooCommerce button
                    notice += '<p><a href="<?php echo admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'); ?>" class="button-primary"><?php _e( 'Install WooCommerce', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></a></p>';
                    notice += '</div>';
                    // Prepend notice to admin page
                    $('#wpbody-content').prepend(notice);
                });
            </script>
            <?php
        });
    }

    return $woocommerce_active;
}

require_once(__DIR__ . '/dependentclassfile.php');


if (mepp_woocommerce_is_active()) :
    require_once( plugin_dir_path( __FILE__ ) . '/inc/mepp-functions.php' );


    // Install the singleton instance
    MEPP_Advance_Deposits::get_singleton();
	
    register_activation_hook(__FILE__, array('\MagePeople\MEPP\MEPP_Advance_Deposits', 'plugin_activated'));
    register_deactivation_hook(__FILE__, array('\MagePeople\MEPP\MEPP_Advance_Deposits', 'plugin_deactivated'));

endif;

