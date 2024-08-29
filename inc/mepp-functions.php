<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use MagePeople\MEPP\MEPP_Admin_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Update Order Status to "Partially Paid" if the Order is "On Hold"
 */
function update_order_status_to_partially_paid($order_id) {
    $order = wc_get_order($order_id);

    if ($order) {
        // Get the current status
        $current_status = $order->get_status();

        // Check if the order status is "on-hold"
        if ($current_status === 'on-hold') {
            // Update the status to "partially-paid"
            $order->update_status('partially-paid', 'Order status updated to completed because it was on-hold.');
        }
    }
}
add_action('woocommerce_order_status_on-hold', 'update_order_status_to_partially_paid', 15, 1);

if (!function_exists('mepp_get_option')) {
    function mepp_get_option($meta_key, $default = null)
    {
        return get_option($meta_key) ? get_option($meta_key) : esc_html__($default);
    }
}

add_action('wp_footer', 'remove_duplicate_order_review_data');
function remove_duplicate_order_review_data() {
    if (is_checkout() && isset($_GET['pay_for_order'])) {
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                const orderReview = document.querySelectorAll('#order_review');
                if (orderReview.length > 1) {
                    for (let i = 1; i < orderReview.length; i++) {
                        orderReview[i].remove();
                    }
                }
            });
        </script>
        <?php
    }
}

add_filter('the_content', 'mepp_modify_cart_checkout_content', 20);

function mepp_modify_cart_checkout_content($content) {
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
        if (strpos($content, '[woocommerce_cart]') === false) {
            // Ensure the styles and shortcode are appended correctly
            return $style . do_shortcode('[woocommerce_cart]');
        } else {
            return $style . $content;
        }
    }

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
        if (strpos($content, '[woocommerce_checkout]') === false) {
            // Ensure the styles and shortcode are appended correctly
            return $style . do_shortcode('[woocommerce_checkout]');
        } else {
            return $style . $content;
        }
    }

    return $content;
}





// Check if the Pro plugin is active
function mepp_check_pro_plugin_and_prompt_update() {
    $pro_plugin = 'mage-partial-payment-pro/mage_partial_pro.php';
    $plugin_name = 'Advanced Partial/Deposit Payment For Woocommerce PRO';
    
    if (function_exists('wcpp_deactivate_depends_on_dependancy')) {
        // Display the update notice
        add_action('admin_notices', function() use ($plugin_name) {
            $class = 'notice notice-warning';
            $update_url = '#'; // Replace with your custom update URL
            $message = sprintf(
                __('Than you very much for updated the <strong>Deposit & Partial Payment Solution for WooCommerce - WpDepositly</strong> to the 3.0.0 version. We added a massive update in this version and its required the <strong>Advanced Partial/Deposit Payment For WooCommerce PRO</strong> version 3.0.0 but you have installed the old version which is not working with this version. Please go to plugin list page to update the <strong>Advanced Partial/Deposit Payment For WooCommerce PRO</strong> version to the latest version. If you do not see the update notification please log on to <a href="https://mage-people.com/my-account/" target="_blank">Mage-People.com</a> site in the My Account Page you will get the Latest version of the <strong>Advanced Partial/Deposit Payment For WooCommerce PRO.</strong>', 'text-domain'),
                $plugin_name,
                esc_url($update_url)
            );
            printf('<div style="padding:10px;font-size:15px;line-height:25px"class="%1$s">%2$s</div>', esc_attr($class), $message);
        });
    }
}

// Hook the function to admin_init
add_action('admin_init', 'mepp_check_pro_plugin_and_prompt_update');

/**
 * Set a transient on plugin activation to trigger the redirect.
 */
function mepp_set_activation_redirect() {
    set_transient('mepp_activation_redirect', true, 30);
}
add_action('activate_advanced-partial-payment-or-deposit-for-woocommerce/advanced-partial-payment-or-deposit-for-woocommerce.php', 'mepp_set_activation_redirect');

/**
 * Redirect to plugin settings page after activation.
 */
function mepp_redirect_to_settings_page() {
    // Check if the transient is set
    if (get_transient('mepp_activation_redirect')) {
        // Delete the transient to prevent multiple redirects
        delete_transient('mepp_activation_redirect');
        
        // Check if the user has permission to access the settings page
        if (current_user_can('manage_options')) {
            // Redirect to the plugin settings page
            wp_safe_redirect(admin_url('admin.php?page=admin-mepp-deposits'));
            exit;
        }
    }
}
add_action('admin_init', 'mepp_redirect_to_settings_page');




// Add "Pay Deposit" button next to "Add to Cart" button on product list.
add_action('woocommerce_after_shop_loop_item', 'mepp_add_pay_deposit_button', 9);

function mepp_add_pay_deposit_button() {
    global $product;

    // Check if mepp_storewide_deposit_enabled_btn is set to yes
    $storewide_deposit_btn_enabled = get_option('mepp_storewide_deposit_enabled_btn', 'no');

    // Check if deposits are enabled for the product and mepp_storewide_deposit_enabled_btn is yes
    if (mepp_is_product_deposit_enabled($product->get_id()) && $storewide_deposit_btn_enabled === 'yes') {
        echo '<div style="text-align:center">';
        echo '<a href="' . esc_url(get_permalink($product->get_id())) . '" class="wp-block-button__link wp-element-button wc-block-components-product-button__button add_to_cart_button ajax_add_to_cart product_type_simple has-font-size has-small-font-size has-text-align-center wc-interactive pay-deposit-button">' . __('Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>';
        echo '</div>';
    }
}

// Hook into the action that handles completion of the second payment
add_action('woocommerce_order_status_completed', 'mepp_update_main_order_status_on_second_payment_completion', 10, 1);

function mepp_update_main_order_status_on_second_payment_completion($order_id) {
    $order = wc_get_order($order_id);

    // Check if it's a child order (second payment)
    if ($order && $order->get_parent_id() > 0) {
        $parent_order_id = $order->get_parent_id();
        $parent_order = wc_get_order($parent_order_id);

        // Check if the parent order exists and if it's partially paid
        if ($parent_order && $parent_order->get_status() === 'partially-paid') {
            $child_orders = wc_get_orders(array(
                'parent' => $parent_order_id,
                'type' => 'mepp_payment',
                'status' => array('completed', 'processing', 'on-hold', 'pending')
            ));

            $total_paid = 0;

            foreach ($child_orders as $child_order) {
                $total_paid += $child_order->get_total();
            }

            // Compare the total amount of child orders with the total amount of the parent order
            if ($total_paid >= $parent_order->get_total()) {
                $parent_order->update_status('completed');
            }
        }
    }
}

/**
 * Add partially paid status option to event plugin's filter
 */
function mepp_add_partially_paid_status_option($name) {
    $new_name = array(
        'partially-paid'  => esc_html__( 'Partially Paid', 'tour-booking-manager' ),
     );
     return array_merge($name, $new_name);
}
add_filter('mep_event_seat_status_name_partial', 'mepp_add_partially_paid_status_option');

add_action('init', 'mepp_register_mepp_payment_post_type', 6);
/**
         * Register mepp_payment custom order type
         * @return void
         */
        function mepp_register_mepp_payment_post_type()
        {

            if (!function_exists('wc_register_order_type')) return;
            wc_register_order_type(
                'mepp_payment',

                array(
                    // register_post_type() params
                    'labels' => array(
                        'name' => esc_html__('Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'singular_name' => esc_html__('Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'edit_item' => esc_html_x('Edit Partial Payment', 'custom post type setting', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'search_items' => esc_html__('Search Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'parent' => esc_html_x('Order', 'custom post type setting', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'menu_name' => esc_html__('Partial order list', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    ),
                    'public' => false,
                    'show_ui' => true,
                    'capability_type' => 'shop_order',
                    'capabilities' => array(
                        'create_posts' => 'do_not_allow',
                    ),
                    'map_meta_cap' => true,
                    'publicly_queryable' => false,
                    'exclude_from_search' => true,
                    'show_in_menu' => 'woocommerce',
                    'hierarchical' => false,
                    'show_in_nav_menus' => false,
                    'rewrite' => false,
                    'query_var' => false,
                    'supports' => array('title', 'comments', 'custom-fields'),
                    'has_archive' => false,

                    // wc_register_order_type() params
                    'exclude_from_orders_screen' => true,
                    'add_order_meta_boxes' => true,
                    'exclude_from_order_count' => true,
                    'exclude_from_order_views' => true,
                    'exclude_from_order_webhooks' => true,
    //                    'exclude_from_order_reports' => true,
    //                    'exclude_from_order_sales_reports' => true,
                    'class_name' => 'MEPP_Payment',
                )

            );
        }

        add_action('plugins_loaded', 'ppcp_early_compatibility_register', 9);
        add_action('before_woocommerce_init', 'declare_hpos_compatibility');
       /**
         * Declare compatibility with Custom order tables
         * @return void
         */
        function declare_hpos_compatibility()
        {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }

        /**
         * WooCommerce PayPal Payments trigger the container action at plugins_loaded, so we need to register the hookcall back early as well
         * @return void
         */
        function ppcp_early_compatibility_register()
        {
            if (is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php')) {
                $this->compatibility->wc_ppcp = require_once('inc/compatibility/mepp-ppcp-compatibility.php');
            }
        }

        /**
 * Checks installed WC version against minimum version required
 * @return void
 */
function mepp_check_version_disable()
{
    if (function_exists('WC') && version_compare(WC()->version, '3.7.0', '<')) {

        $this->wc_version_disabled = true;

        if (is_admin()) {
            // translators: %1$s is a placeholder for the plugin name, %2$s is a placeholder for the required WooCommerce version
            $message = sprintf(esc_html__('%1$s Requires WooCommerce version %2$s or higher.', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html__('WooCommerce Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'), '3.7.0');
            add_action('admin_notices', function() use ($message) {
                echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
            });
        }
    }
}     
add_action('init','mepp_check_version_disable', 0);
/**
 * @return mixed
 */
function mepp_deposit_breakdown_tooltip()
{

    $display_tooltip = get_option('mepp_breakdown_cart_tooltip') === 'yes';


    $tooltip_html = '';

    if ($display_tooltip && isset(WC()->cart->deposit_info['deposit_breakdown']) && is_array(WC()->cart->deposit_info['deposit_breakdown'])) {

        $labels = apply_filters('mepp_deposit_breakdown_tooltip_labels', array(
            'cart_items' => esc_html__('Cart items', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'fees' => esc_html__('Fees', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'taxes' => esc_html__('Tax', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'shipping' => esc_html__('Shipping', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        ));

        $deposit_breakdown = WC()->cart->deposit_info['deposit_breakdown'];
        $tip_information = '<ul>';
        foreach ($deposit_breakdown as $component_key => $component) {

            if (!isset($labels[$component_key])) continue;
            if ($component === 0) {
                continue;
            }
            switch ($component_key) {
                case 'cart_items' :
                    $tip_information .= '<li>' . $labels['cart_items'] . ' : ' . wc_price($component) . '</li>';

                    break;
                case 'fees' :
                    $tip_information .= '<li>' . $labels['fees'] . ' : ' . wc_price($component) . '</li>';
                    break;
                case 'taxes' :
                    $tip_information .= '<li>' . $labels['taxes'] . ' : ' . wc_price($component) . '</li>';

                    break;
                case 'shipping' :
                    $tip_information .= '<li>' . $labels['shipping'] . ' : ' . wc_price($component) . '</li>';

                    break;

                default :
                    break;
            }
        }

        $tip_information .= '</ul>';

        $tooltip_html = '<span id="deposit-help-tip" data-tip="' . esc_attr($tip_information) . '">&#63;</span>';
    }

    return apply_filters('woocommerce_deposits_tooltip_html', $tooltip_html);
}


/** http://jaspreetchahal.org/how-to-lighten-or-darken-hex-or-rgb-color-in-php-and-javascript/
 * @param $color_code
 * @param int $percentage_adjuster
 * @return array|string
 * @author Jaspreet Chahal
 */
function mepp_adjust_colour($color_code, $percentage_adjuster = 0)
{
    $percentage_adjuster = round($percentage_adjuster / 100, 2);

    if (is_array($color_code)) {
        $r = $color_code["r"] - (round($color_code["r"]) * $percentage_adjuster);
        $g = $color_code["g"] - (round($color_code["g"]) * $percentage_adjuster);
        $b = $color_code["b"] - (round($color_code["b"]) * $percentage_adjuster);

        $adjust_color = array("r" => round(max(0, min(255, $r))),
            "g" => round(max(0, min(255, $g))),
            "b" => round(max(0, min(255, $b))));
    } elseif (preg_match("/#/", $color_code)) {
        $hex = str_replace("#", "", $color_code);
        $r = (strlen($hex) == 3) ? hexdec(substr($hex, 0, 1) . substr($hex, 0, 1)) : hexdec(substr($hex, 0, 2));
        $g = (strlen($hex) == 3) ? hexdec(substr($hex, 1, 1) . substr($hex, 1, 1)) : hexdec(substr($hex, 2, 2));
        $b = (strlen($hex) == 3) ? hexdec(substr($hex, 2, 1) . substr($hex, 2, 1)) : hexdec(substr($hex, 4, 2));
        $r = round($r - ($r * $percentage_adjuster));
        $g = round($g - ($g * $percentage_adjuster));
        $b = round($b - ($b * $percentage_adjuster));

        $adjust_color = "#" . str_pad(dechex(max(0, min(255, $r))), 2, "0", STR_PAD_LEFT)
            . str_pad(dechex(max(0, min(255, $g))), 2, "0", STR_PAD_LEFT)
            . str_pad(dechex(max(0, min(255, $b))), 2, "0", STR_PAD_LEFT);

    } else {
        $adjust_color = new WP_Error('', 'Invalid Color format');
    }


    return $adjust_color;
}

/**
 * @brief returns the frontend colours from the WooCommerce settings page, or the defaults.
 *
 * @return array
 */

function mepp_woocommerce_frontend_colours()
{
    $colors = (array)get_option('woocommerce_colors');
    if (empty($colors['primary']))
        $colors['primary'] = '#ad74a2';
    if (empty($colors['secondary']))
        $colors['secondary'] = '#f7f6f7';
    if (empty($colors['highlight']))
        $colors['highlight'] = '#85ad74';
    if (empty($colors['content_bg']))
        $colors['content_bg'] = '#ffffff';
    return $colors;
}


/**
 * @return bool
 */
function mepp_checkout_mode()
{

    return get_option('mepp_checkout_mode_enabled') === 'yes';
}

/**
 * @param $product
 * @return float
 */
function mepp_calculate_product_deposit($product)
{


    $deposit_enabled = mepp_is_product_deposit_enabled($product->get_id());
    $product_type = $product->get_type();
    if ($deposit_enabled) {


        $deposit = mepp_get_product_deposit_amount($product->get_id());
        $amount_type = mepp_get_product_deposit_amount_type($product->get_id());


        $woocommerce_prices_include_tax = get_option('woocommerce_prices_include_tax');

        if ($woocommerce_prices_include_tax === 'yes') {

            $amount = wc_get_price_including_tax($product);

        } else {
            $amount = wc_get_price_excluding_tax($product);

        }

        switch ($product_type) {


            case 'subscription' :
                if (class_exists('WC_Subscriptions_Product')) {

                    $amount = \WC_Subscriptions_Product::get_sign_up_fee($product);
                    if ($amount_type === 'fixed') {
                    } else {
                        $deposit = $amount * ($deposit / 100.0);
                    }

                }
                break;
            case 'yith_bundle' :
                $amount = $product->price_per_item_tot;
                if ($amount_type === 'fixed') {
                } else {
                    $deposit = $amount * ($deposit / 100.0);
                }
                break;
            case 'variable' :

                if ($amount_type === 'fixed') {
                } else {
                    $deposit = $amount * ($deposit / 100.0);
                }
                break;

            default:


                if ($amount_type !== 'fixed') {

                    $deposit = $amount * ($deposit / 100.0);
                }

                break;
        }

        return floatval($deposit);
    }
}

/**
 * @brief checks if deposit is enabled for product
 * @param $product_id
 * @return mixed
 */
function mepp_is_product_deposit_enabled($product_id)
{
    $enabled = false;
    $product = wc_get_product($product_id);
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $enabled = get_option('mepp_storewide_deposit_enabled', 'no') === 'yes';
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $enabled = $product->get_meta('_mepp_enable_deposit', true) === 'yes';
                } else {
                    $enabled = $parent->get_meta('_mepp_enable_deposit', true) === 'yes';
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $enabled = get_option('mepp_storewide_deposit_enabled', 'no') === 'yes';
            } else {
                $enabled = $product->get_meta('_mepp_enable_deposit', true) === 'yes';
            }
        }


    }

    return apply_filters('mepp_product_enable_deposit', $enabled, $product_id);

}

function mepp_is_product_deposit_forced($product_id)
{
    $forced = false;
    $product = wc_get_product($product_id);
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $forced = get_option('mepp_storewide_deposit_force_deposit', 'no') === 'yes';
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $forced = $product->get_meta('_mepp_force_deposit', true) === 'yes';
                } else {
                    $forced = $parent->get_meta('_mepp_force_deposit', true) === 'yes';
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $forced = get_option('mepp_storewide_deposit_force_deposit', 'no') === 'yes';
            } else {
                $forced = $product->get_meta('_mepp_force_deposit', true) === 'yes';
            }
        }
    }

    return apply_filters('mepp_product_force_deposit', $forced, $product_id);

}

function mepp_get_product_deposit_amount($product_id)
{
    $amount = false;
    $product = wc_get_product($product_id);

    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount = get_option('mepp_storewide_deposit_amount', '50');
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $amount = $product->get_meta('_mepp_deposit_amount', true);
                } else {
                    $amount = $parent->get_meta('_mepp_deposit_amount', true);
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount = get_option('mepp_storewide_deposit_amount', '50');
            } else {
                $amount = $product->get_meta('_mepp_deposit_amount', true);
            }
        }
    }


    return apply_filters('mepp_product_deposit_amount', $amount, $product_id);

}

function mepp_get_product_deposit_amount_type($product_id)
{

    $amount_type = false;
    $product = wc_get_product($product_id);


    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount_type = get_option('mepp_storewide_deposit_amount_type', 'percent');
            } else {

                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $amount_type = $product->get_meta('_mepp_amount_type', true);
                } else {
                    $amount_type = $parent->get_meta('_mepp_amount_type', true);
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');
            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount_type = get_option('mepp_storewide_deposit_amount_type', 'percent');
            } else {
                $amount_type = $product->get_meta('_mepp_amount_type', true);
            }
        }
    }
    return apply_filters('mepp_product_deposit_amount_type', $amount_type, $product_id);
}


function mepp_get_product_available_plans($product_id)
{
    $product = wc_get_product($product_id);
    $plans = array();
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $plans = get_option('mepp_storewide_deposit_payment_plans', array());
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';
                if ($override) {
                    $plans = $product->get_meta('_mepp_payment_plans', true);
                } else {
                    $plans = $parent->get_meta('_mepp_payment_plans', true);
                }
            }

        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');
            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $plans = get_option('mepp_storewide_deposit_payment_plans', array());
            } else {
                $plans = $product->get_meta('_mepp_payment_plans', true);
            }
        }
    }

    return apply_filters('mepp_product_deposit_available_plans', $plans, $product_id);
}

function mepp_delete_current_schedule($order)
{

    $payments = mepp_get_order_partial_payments($order->get_id(), [], false);
    foreach ($payments as $payment) {
        wp_delete_post(absint($payment), true);
    }

    $order->delete_meta_data('_mepp_payment_schedule');
    $order->save();

}


function mepp_create_payment_schedule($order, $sorted_schedule = array())
{

    /**   START BUILD PAYMENT SCHEDULE**/
    try {

        //fix wpml language
        $wpml_lang = $order->get_meta('wpml_language', true);
        $partial_payments_structure = apply_filters('mepp_partial_payments_structure', get_option('mepp_partial_payments_structure', 'single'), 'order');

        foreach ($sorted_schedule as $partial_key => $payment) {

            $partial_payment = new MEPP_Payment();


            //migrate all fields from parent order


            $partial_payment->set_customer_id($order->get_user_id());


            if ($partial_payments_structure === 'single') {
                $amount = $payment['total'];
                //allow partial payments to be inserted only as a single fee without item details
                $name = esc_html__('Partial Payment for order %s', 'advanced-partial-payment-or-deposit-for-woocommerce');
                $partial_payment_name = apply_filters('mepp_partial_payment_name', sprintf($name, $order->get_order_number()), $payment, $order->get_id());


                $item = new WC_Order_Item_Fee();
                $item->set_props(
                    array(
                        'total' => $amount
                    )
                );
                $item->set_name($partial_payment_name);
                $partial_payment->add_item($item);
                $partial_payment->set_total($amount);


            } else {
                MEPP_Advance_Deposits_Admin_order::create_partial_payment_items($partial_payment, $order, $payment['details']);
                $partial_payment->save();
//                $partial_payment->recalculate_coupons();
                $partial_payment->calculate_totals(false);
                $partial_payment->add_meta_data('_mepp_partial_payment_itemized', 'yes');

            }
            $is_vat_exempt = $order->get_meta('is_vat_exempt', true);

            if (isset($payment['timestamp']) && is_numeric($payment['timestamp'])) {
                $partial_payment->add_meta_data('_mepp_partial_payment_date', $payment['timestamp']);
            }

            if (isset($payment['details'])) {
                $partial_payment->add_meta_data('_mepp_partial_payment_details', $payment['details']);
            }
            $partial_payment->set_parent_id($order->get_id());
            $partial_payment->add_meta_data('is_vat_exempt', $is_vat_exempt);
            $partial_payment->add_meta_data('_mepp_payment_type', $payment['type'], true);
            $partial_payment->set_currency($order->get_currency());
            $partial_payment->set_prices_include_tax($order->get_prices_include_tax());
            $partial_payment->set_customer_ip_address($order->get_customer_ip_address());
            $partial_payment->set_customer_user_agent($order->get_customer_user_agent());

            if ($order->get_status() === 'partially-paid' && $payment['type'] === 'deposit') {

                //we need to save to generate id first
                $partial_payment->set_status('completed');

            }

            if (!empty($wpml_lang)) {
                $partial_payment->update_meta_data('wpml_language', $wpml_lang);
            }


            if (floatval($partial_payment->get_total()) == 0.0) $partial_payment->set_status('completed');

            $partial_payment->save();
            do_action('mepp_partial_payment_created', $partial_payment->get_id(), 'backend');

            $sorted_schedule[$partial_key]['id'] = $partial_payment->get_id();

        }
        return $sorted_schedule;
    } catch (\Exception $e) {
        print_r(new WP_Error('error', $e->getMessage()));
    }

}

function mepp_get_order_partial_payments($order_id, $args = array(), $object = true)
{
    $default_args = array(
        'parent' => $order_id,
        'type' => 'mepp_payment',
        'limit' => -1,
        'status' => array_keys(wc_get_order_statuses())
    );

    $args = ($args) ? wp_parse_args($args, $default_args) : $default_args;

    $orders = array();

    //get children of order
    $partial_payments = wc_get_orders($args);
    foreach ($partial_payments as $partial_payment) {
        $orders[] = ($object) ? wc_get_order($partial_payment->get_id()) : $partial_payment->get_id();
    }
    return $orders;
}

add_action('woocommerce_after_dashboard_status_widget', 'mepp_status_widget_partially_paid');
function mepp_status_widget_partially_paid()
{
    if (!current_user_can('edit_shop_orders')) {
        return;
    }
    $partially_paid_count = 0;
    foreach (wc_get_order_types('order-count') as $type) {
        $counts = (array)wp_count_posts($type);
        $partially_paid_count += isset($counts['wc-partially-paid']) ? $counts['wc-partially-paid'] : 0;
    }
    ?>
    <li class="partially-paid-orders">
        <a href="<?php echo admin_url('edit.php?post_status=wc-partially-paid&post_type=shop_order'); ?>">
            <?php
            printf(
                _n('<strong>%s order</strong> partially paid', '<strong>%s orders</strong> partially paid', $partially_paid_count, 'advanced-partial-payment-or-deposit-for-woocommerce'),
                $partially_paid_count
            );
            ?>
        </a>
    </li>
    <style>
        #woocommerce_dashboard_status .wc_status_list li.partially-paid-orders a::before {
            content: '\e011';
            color: #ffba00;}
    </style>
    <?php
}

function mepp_remove_order_deposit_data($order)
{

    $order->delete_meta_data('_mepp_order_version');
    $order->delete_meta_data('_mepp_order_has_deposit');
    $order->delete_meta_data('_mepp_deposit_paid');
    $order->delete_meta_data('_mepp_second_payment_paid');
    $order->delete_meta_data('_mepp_deposit_amount');
    $order->delete_meta_data('_mepp_second_payment');
    $order->delete_meta_data('_mepp_deposit_breakdown');
    $order->delete_meta_data('_mepp_deposit_payment_time');
    $order->delete_meta_data('_mepp_second_payment_reminder_email_sent');
    $order->save();

}

function mepp_validate_customer_eligibility($enabled)
{
    //user restriction
    $allow_deposit_for_guests = get_option('mepp_restrict_deposits_for_logged_in_users_only', 'no');

    if ($allow_deposit_for_guests !== 'no' && is_user_logged_in() && isset($_POST['createaccount']) && $_POST['createaccount'] == 1) {
        //account created during checkout
        $enabled = false;

    } elseif (is_user_logged_in()) {

        $disabled_user_roles = get_option('mepp_disable_deposit_for_user_roles', array());
        if (!empty($disabled_user_roles)) {

            foreach ($disabled_user_roles as $disabled_user_role) {

                if (wc_current_user_has_role($disabled_user_role)) {

                    $enabled = false;
                }
            }
        }
    } else {
        if ($allow_deposit_for_guests !== 'no') {
            $enabled = false;
        }
    }

    return $enabled;
}

add_filter('mepp_deposit_enabled_for_customer', 'mepp_validate_customer_eligibility');

function mepp_valid_parent_statuses_for_partial_payment()
{
    return apply_filters('mepp_valid_parent_statuses_for_partial_payment', array('partially-paid'));
}

function mepp_partial_payment_complete_order_status()
{
    return apply_filters('mepp_partial_payment_complete_order_status', 'partially-paid');
}

function MEPP()
{
    return \MagePeople\MEPP\MEPP_Advance_Deposits::get_singleton();
}

add_action('mepp_job_scheduler', 'mepp_backward_compatibility_cron_trigger');
function mepp_backward_compatibility_cron_trigger()
{
    do_action('woocommerce_deposits_second_payment_reminder');
}


function mepp_is_mepp_payment_screen()
{


    if (!function_exists('get_current_screen')) return false;
    $screen = get_current_screen();

    $hpos_enabled = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    $screen_name = $hpos_enabled && function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('mepp_payment') : 'mepp_payment';
    return $screen->id == $screen_name;

}


function mepp_is_shop_order_screen()
{


    if (!function_exists('get_current_screen')) return false;
    $screen = get_current_screen();

    $hpos_enabled = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    $screen_name = $hpos_enabled && function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

    return $screen->id == $screen_name;
}