<?php
/**
 * Plugin Name: Deposit & Partial Payment Solution for WooCommerce - WpDepositly | MagePeople
 * Plugin URI: http://mage-people.com
 * Description: This plugin will add Partial Payment System in the Woocommerce Plugin its also support Woocommerce Event Manager Plugin.
 * Version: 3.0.0
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

if (mepp_woocommerce_is_active()) :
    require_once( plugin_dir_path( __FILE__ ) . '/inc/mepp-functions.php' );
    require_once( plugin_dir_path( __FILE__ ) . '/inc/mepp-migration.php' );


    /**
         *  Main MEPP_Advance_Deposits class
         *
         */
        class MEPP_Advance_Deposits {
                public $cart; // Instance of MEPP_Cart
                public $coupons; // Instance of MEPP_Coupons
                public $add_to_cart; // Instance of MEPP_Add_To_Cart
                public $orders; // Instance of MEPP_Orders
                public $taxonomies; // Instance of MEPP_Taxonomies
                public $reminders; // Instance of MEPP_Reminders
                public $emails; // Instance of MEPP_Emails
                public $checkout; // Instance of MEPP_Checkout
                public $compatibility; // All compatibility classes are loaded in this var
                public $admin_product; // Instance of MEPP_Admin_Product
                public $admin_order; // Instance of MEPP_Admin_Order
                public $admin_list_table_orders; // Instance of MEPP_Admin_List_Table_Orders
                public $admin_list_table_partial_payments; // Instance of MEPP_Admin_List_Table_Partial_Payments
                public $admin_settings; // Instance of MEPP_Admin_Settings
                public $admin_reports; // Instance of MEPP_Admin_Reports

                // Properties for notices and version disabled state
                public $admin_notices = []; // Stores notices before output function
                public $wc_version_disabled = false; // Stores version disabled state

            /**
             *  Returns the global instance
             *
             * @param array $GLOBALS ...
             * @return mixed
             */
            public static function & get_singleton()
            {
                if (!isset($GLOBALS['mepp'])) $GLOBALS['mepp'] = new MEPP_Advance_Deposits();
                return $GLOBALS['mepp'];
            }

                /**
             *  Enqueues front-end styles
             *
             * @return void
             */
            public function enqueue_styles(){
                if ($this->wc_version_disabled) return;
                if (!$this->is_disabled()) {
                wp_enqueue_style('toggle-switch', plugins_url('assets/css/admin-style.css', __FILE__), array(), MEPP_VERSION, 'screen');
                wp_enqueue_style('wc-deposits-frontend-styles', plugins_url('assets/css/style.css', __FILE__), array(), MEPP_VERSION);

                if (is_cart() || is_checkout()) {
                    $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
                    wp_register_script('jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip' . $suffix . '.js', array('jquery'), WC_VERSION, true);
                    wp_enqueue_script('wc-deposits-cart', MEPP_PLUGIN_URL . '/assets/js/add-to-cart.js', array('jquery'), MEPP_VERSION, true);
                    wp_enqueue_script('jquery-tiptip');
                    wp_enqueue_style('wc-deposits-frontend-styles', plugins_url('assets/css/style.css', __FILE__), array(), MEPP_VERSION);
                }
            }
        }

         /**
         *  Display all buffered admin notices
         *
         * @return void
         */
        public function show_admin_notices() {
            if (is_array($this->admin_notices) && !empty($this->admin_notices)) {
          try {
            foreach ($this->admin_notices as $notice) {
                $dismissible = isset($notice['dismissible']) && $notice['dismissible'] ? 'is-dismissible' : '';
                ?>
                <div class='<?php echo $dismissible; ?> notice notice-<?php echo esc_attr($notice['type']); ?>'>
                    <p><?php echo $notice['content']; ?></p>
                </div>
                <?php
            }
            } catch (Exception $e) {
                // Silence any exceptions
            }
            }
        }

        /**
         *  Constructor
         *
         * @return void
         */
        private function __construct()
        {
            // Check if WooCommerce is not active
                if (!mepp_woocommerce_is_active()) {
                add_action('admin_notices', array($this, 'woocommerce_not_active_notice'));
                return;
                }
                    // Redirect to plugin settings page after WooCommerce logic
    
			
            define('MEPP_VERSION', '1.3.3');
            define('MEPP_TEMPLATE_PATH', untrailingslashit(plugin_dir_path(__FILE__)) . '/theme/');
            define('MEPP_PLUGIN_PATH', plugin_dir_path(__FILE__));
            define('MEPP_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));
            define('MEPP_MAIN_FILE', __FILE__);
            define('MEPP_PAYMENT_PLAN_TAXONOMY', 'mepp_payment_plan');

            $this->compatibility = new stdClass();

            if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            if (is_admin()) {
                // translators: %1$s is a placeholder for the plugin name, %2$s is a placeholder for the required PHP version
                $message = sprintf(esc_html__('%1$s Requires PHP version %2$s or higher.', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html__('WooCommerce Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'), '5.6');
                add_action('admin_notices', function() use ($message) {
                    echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
                });
            }
            return;
            }
            add_action('init', array($this, 'load_plugin_textdomain'), 0);
            add_action('init', array($this, 'register_order_status'));          
            if (!did_action('woocommerce_init')) {
                add_action('woocommerce_init', array($this, 'email_inc'));
                add_action('woocommerce_init', array($this, 'mepp_admin_inc'));
                add_action('woocommerce_init', array($this, 'inc'));
            }
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts_and_styles'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
            if (is_admin()) {

                //plugin row urls in plugins page
                add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
                add_action('admin_notices', array($this, 'show_admin_notices'));
                add_action('current_screen', array($this, 'mepp_screen'), 10);
                add_action('init', 'MagePeople\MEPP\MEPP_Advance_Deposits::plugin_activated', 100); //plugin activated is not called with automatic updates anymore.

            }
            add_filter('the_content', array($this,'mepp_modify_cart_content'), 9999); 
            add_filter('the_content', array($this,'mepp_modify_checkout_content'), 9999);
                    }

                    // Function to modify cart content on the cart page
            function mepp_modify_cart_content($content) {
                // Check if we are on the cart page
                if (is_cart()) {
                    // Add styles to hide specific classes on the cart page
                    $style = '<style>
                        .wc-block-components-sidebar-layout,
                        .wc-block-cart,
                        .wp-block-woocommerce-filled-cart-block,
                        .wp-block-woocommerce-cart {
                            display: none !important;
                        }
                        .woocommerce-cart.alignwide.is-loading {
                            display: block !important; /* Display the WooCommerce cart */
                        }
                    </style>';

                    // Check if the cart shortcode is not already present
                    if (!has_shortcode($content, 'woocommerce_cart')) {
                        $content .= $style;
                        $content .= do_shortcode('[woocommerce_cart]');
                    } else {
                        // Only add the styles if the shortcode is already present
                        $content .= $style;
                    }
                }
                return $content;
            }

            // Function to modify checkout content on the checkout page
            function mepp_modify_checkout_content($content) {
                // Check if we are on the checkout page
                if (is_checkout()) {
                    // Add styles to hide specific classes on the checkout page
                    $style = '<style>
                        .wc-block-components-sidebar-layout,
                        .wc-block-cart,
                        .wp-block-woocommerce-filled-cart-block,
                        .wp-block-woocommerce-cart {
                            display: none !important;
                        }
                        .woocommerce-cart.alignwide.is-loading {
                            display: block !important; /* Display the WooCommerce cart */
                        }
                    </style>';

                    // Check if the checkout shortcode is not already present
                    if (!has_shortcode($content, 'woocommerce_checkout')) {
                        $content .= $style;
                        $content .= do_shortcode('[woocommerce_checkout]');
                    } else {
                        // Only add the styles if the shortcode is already present
                        $content .= $style;
                    }
                }
                return $content;
            }

            

        /**
         * Display additional links in plugin row located in plugins page
         *
         * @param array $links
         * @param string $file
         * @return array
         */
        function plugin_row_meta($links, $file)
        {
            if ($file === 'advanced-partial-payment-or-deposit-for-woocommerce/advanced-partial-payment-or-deposit-for-woocommerce.php') {

                $row_meta = array(
                    'settings' => '<a href="' . esc_url(admin_url('/admin.php?page=admin-mepp-deposits&tab=settings_tabs_mepp&section=mepp_general')) . '"> ' . esc_html__('Settings', 'text-domain') . '</a>',
                    'documentation' => '<a target="_blank" href="' . esc_url('#') . '"> ' . esc_html__('Documentation', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>',
                    'support' => '<a target="_blank" href="' . esc_url('#') . '"> ' . esc_html__('Support', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>',
                );

                // translators: %s is a placeholder for the plugin name
                $row_meta['view-details'] = sprintf(
                    '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
                    esc_url(network_admin_url('plugin-install.php?tab=plugin-information&plugin=' . urlencode('advanced-partial-payment-or-deposit-for-woocommerce') . '&TB_iframe=true&width=600&height=550')),
                    sprintf(esc_html__('More information about %s', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html__('WooCommerce Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce')),
                    esc_attr__('WooCommerce Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    esc_html__('View details', 'advanced-partial-payment-or-deposit-for-woocommerce')
                );

                $links = array_merge($links, $row_meta);
            }

            return $links;
        }


        /**
         *   load plugin's translated strings
         * @brief Localisation
         *
         * @return void
         */
        public function load_plugin_textdomain()
        {


            load_plugin_textdomain('advanced-partial-payment-or-deposit-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/language/');
        }

   


        /**
         *  Email inc
         *
         * @return void
         * @since 1.3
         *
         */

        public function email_inc()
        {
            if ($this->wc_version_disabled) return;
            require_once 'inc/class-mepp-emails.php';
            $this->emails = MEPP_Emails::instance();

            require_once 'inc/class-mepp-reminders.php';
            $this->reminders = new MEPP_Reminders();


        }

        /**
         *  Load classes
         *
         * @return void
         */
        public function inc()
        {

            if ($this->wc_version_disabled) return;
            if (!$this->is_disabled()) {

                require_once('inc/class-mepp-cart.php');
                require_once('inc/class-mepp-coupons.php');
                require_once('inc/class-mepp-checkout.php');

                $this->cart = new MEPP_Cart();
                $this->checkout = new MEPP_Checkout();
                $this->coupons = new MEPP_Coupons();


                if (!mepp_checkout_mode()) {
                    require_once('inc/class-mepp-add-to-cart.php');
                    $this->add_to_cart = new MEPP_Add_To_Cart();

                }


            }

            require_once('inc/admin/class-mepp-admin-taxonomies.php');
            require_once('inc/class-mepp-payment.php');
            require_once('inc/class-mepp-orders.php');
            $this->orders = new MEPP_Orders();
            $this->taxonomies = new MEPP_Taxonomies();


            /**
             * 3RD PARTY COMPATIBILITY
             */

            if (is_plugin_active('woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php')) {
                $this->compatibility->pdf_invoices = require_once('inc/compatibility/pdf-invoices/main.php');
            }

            if (is_plugin_active('woocommerce-bookings/woocommerce-bookings.php')) {
                $this->compatibility->wc_bookings = require_once('inc/compatibility/mepp-bookings-compatibility.php');
            }

            if (is_plugin_active('woocommerce-gateway-paypal-express-checkout/woocommerce-gateway-paypal-express-checkout.php')) {
                $this->compatibility->wc_ppec = require_once('inc/compatibility/mepp-ppec-compatibility.php');
            }

        }


        /**
         *  load proper admin list table class based on current screen
         * @return void
         */
        function mepp_screen()
        {

            if ($this->wc_version_disabled) return;

            $screen_id = false;

            if (function_exists('get_current_screen')) {
                $screen = get_current_screen();
                $screen_id = isset($screen, $screen->id) ? $screen->id : '';
            }

            if (!empty($_REQUEST['screen'])) { // WPCS: input var ok.
                $screen_id = wc_clean(wp_unslash($_REQUEST['screen'])); // WPCS: input var ok, sanitization ok.
            }


            switch ($screen_id) {
                case 'edit-shop_order' :
                    require_once('inc/admin/class-admin-list-table-orders.php.php');
                    $this->admin_list_table_orders = new MEPP_Admin_List_Table_Orders($this);
                    break;
                case 'edit-mepp_payment' :
                    require_once('inc/admin/class-admin-list-table-partial-payments.php');
                    $this->admin_list_table_partial_payments = new MEPP_Admin_List_Table_Partial_Payments();
                    break;

            }
        }

        /**
         *  Load admin inc
         *
         * @return void
         */
        public function mepp_admin_inc()
        {
            if ($this->wc_version_disabled) return;

            require_once('inc/admin/class-mepp-admin-settings.php');
            require_once('inc/admin/class-mepp-admin-order.php');

            $this->admin_settings = new MEPP_Admin_Settings($this);
            $this->admin_order = new MEPP_Admin_Order($this);

            require_once('inc/admin/class-mepp-admin-product.php');
            $this->admin_product = new MEPP_Admin_Product($this);
            add_filter('woocommerce_admin_reports', array($this, 'admin_reports'));
        }

        /**
         *  Load reports functionality
         * @param $reports
         * @return mixed
         */
        public function admin_reports($reports)
        {
            if (!$this->admin_reports) {
                $admin_reports = require_once('inc/admin/class-mepp-admin-reports.php');
                $this->admin_reports = $admin_reports;
            }
            return $this->admin_reports->admin_reports($reports);
        }

        /**
         *  Load admin scripts and styles
         * @return void
         */
        public function enqueue_admin_scripts_and_styles()
        {
            wp_enqueue_script('jquery');
            wp_enqueue_style('wc-deposits-admin-style', plugins_url('assets/css/admin-style.css', __FILE__), MEPP_VERSION);
        }

       
        /**
         *  Add a new notice
         *
         * @param $content String notice contents
         * @param $type String Notice class
         *
         * @return void
         */
        public function enqueue_admin_notice($content, $type, $dismissible = false)
        {
            array_push($this->admin_notices, array('content' => $content, 'type' => $type, 'dismissible' => $dismissible));
        }

        /**
         *  checks if plugin frontend functionality is disabled sitewide
         * @return bool
         */
        public function is_disabled()
        {
            return get_option('mepp_site_wide_disable') === 'yes';
        }
        /**
         *  Register custom order status partially-paid
         *
         * @return void
         * @since 1.3
         *
         */
            public function register_order_status(){
                    register_post_status('wc-partially-paid', array(
                    'label' => _x('Partially Paid', 'Order status', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'public' => true,
                    'exclude_from_search' => false,
                    'show_in_admin_all_list' => true,
                    'show_in_admin_status_list' => true,
                    // translators: %s is a placeholder for the number of orders with this status
                    'label_count' => _n_noop('Partially Paid <span class="count">(%s)</span>',
                        'Partially Paid <span class="count">(%s)</span>', 'advanced-partial-payment-or-deposit-for-woocommerce')
                ));
            }

        /**
         *  plugin activation hook , schedule action 'mepp_job_scheduler'
         * @return void
         */
        public static function plugin_activated()
        {
            if (function_exists('WC')) {
                $next = WC()->queue()->get_next('mepp_job_scheduler');
                if (!$next) {
                    $timestamp = time() + DAY_IN_SECONDS;
                    WC()->queue()->cancel_all('mepp_job_scheduler');
                    WC()->queue()->schedule_recurring($timestamp, DAY_IN_SECONDS, 'mepp_job_scheduler', array(), 'MEPP');
                }
            }

        }

        /**
         *  plugin deactivation hook , remove scheduled action 'mepp_job_scheduler'
         * @return void
         */
        public static function plugin_deactivated()
        {
            if (function_exists('WC')) {

                WC()->queue()->cancel_all('mepp_job_scheduler');
            }

            wp_clear_scheduled_hook('woocommerce_deposits_second_payment_reminder');
            delete_option('mepp_instance');

        }

    }
// Install the singleton instance
MEPP_Advance_Deposits::get_singleton();
	
    register_activation_hook(__FILE__, array('\MagePeople\MEPP\MEPP_Advance_Deposits', 'plugin_activated'));
    register_deactivation_hook(__FILE__, array('\MagePeople\MEPP\MEPP_Advance_Deposits', 'plugin_deactivated'));

endif;
