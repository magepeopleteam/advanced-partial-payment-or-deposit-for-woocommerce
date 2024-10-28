<?php

namespace MagePeople\MEPP;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * @brief Adds a new panel to the WooCommerce Settings
 *
 */
 
 
class MEPP_Admin_Settings
{

    public function __construct()
    {


        $allowed_html = array(
            'a' => array('href' => array(), 'title' => array()),
            'br' => array(), 'em' => array(),
            'strong' => array(), 'p' => array(),
            's' => array(), 'strike' => array(),
            'del' => array(), 'u' => array(), 'b' => array()
        );


        // Hook the settings page
        add_action( 'admin_menu', array($this, 'sr_partial_patment_menu') );

        // add_filter('woocommerce_settings_tabs_array', array($this, 'settings_tabs_array'), 21);
        add_action('woocommerce_settings_wc-deposits', array($this, 'settings_tabs_mepp'));
        // add_action('woocommerce_update_options_wc-deposits', array($this, 'update_options_mepp'));
        add_action('admin_init', array($this, 'update_options_mepp'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_settings_script'));

        add_action('woocommerce_admin_field_deposit_buttons_color', array($this, 'deposit_buttons_color'));
        // reminder datepicker
        add_action('woocommerce_admin_field_reminder_datepicker', array($this, 'reminder_datepicker'));

    }


    public function sr_partial_patment_menu(): void {
    add_menu_page(
        'Partial Payment',       // Main menu name
        'Partial Payment',       // Main menu label
        'manage_options',
        'admin-mepp-deposits',
        array($this, 'settings_tabs_mepp'),
        'dashicons-money',       // Icon URL or CSS class
        10
    );

    // Add Submenus
    add_submenu_page(
        'admin-mepp-deposits',    // Parent menu slug
        'Settings',              // Submenu label
        'Settings',              // Submenu label
        'manage_options',
        'admin-mepp-deposits',    // Submenu slug (same as parent)
        array($this, 'settings_tabs_mepp') // Callback function for Settings page
    );



    add_submenu_page(
        'admin-mepp-deposits',    // Parent menu slug
        'Partial Order List',    // Submenu label
        'Partial Order List',    // Submenu label
        'manage_options',
        'admin.php?page=wc-orders--mepp_payment', // Partial Order List URL
        ''
    );
}


   
    public function enqueue_settings_script()
    {

        if (function_exists('get_current_screen')) {

            if (isset($_GET['page']) && $_GET['page'] === 'admin-mepp-deposits' /*&& isset($_GET['tab']) && $_GET['tab'] === 'wc-deposits'*/) {

                wp_enqueue_style('wp-color-picker');
                wp_enqueue_script('jquery-ui-datepicker');
                wp_enqueue_script('wc-deposits-admin-settings', MEPP_PLUGIN_URL . '/assets/js/admin/Admin.js', array('jquery', 'wp-color-picker'), MEPP_VERSION);
                wp_localize_script('wc-deposits-admin-settings', 'mepp', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'strings' => array(
                        'success' => esc_html__('Updated successfully', 'advanced-partial-payment-or-deposit-for-woocommerce')
                    )

                ));
            }

        }

        wp_enqueue_script('wc-deposits-admin-custom', MEPP_PLUGIN_URL . '/assets/js/admin/custom.js', array('jquery'), MEPP_VERSION);

    }


    public function settings_tabs_array($tabs)
    {

        $tabs['wc-deposits'] = esc_html__('Advanced Partial Payment and Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce');
        return $tabs;
    }

    /**
     * @brief Write out settings html
     *
     * @param array $settings ...
     * @return void
     */
public function settings_tabs_mepp()
{
    $mode_notice = mepp_checkout_mode() ? '<span style="padding:5px 10px; color:#fff; position: relative; top: 10px; background-color:rgba(146, 52, 129, 0.8);">' . esc_html__('Checkout Mode Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</span>' : '';
    $debug_mode_notice = get_option('mepp_debug_mode', 'no') === 'yes' ? '<span style="padding:5px 10px; color:#fff; background-color:rgba(255,63,76,0.8);">' . esc_html__('Debugging Mode Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</span>' : '';
    ?>

    <div>
        <?php echo $mode_notice . $debug_mode_notice; ?>
    </div>




    <?php 
    $settings_tabs = apply_filters('mepp_settings_tabs', array(
        'mepp_general' => __('<i class="fas fa-tools"></i> General Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'display_text' => __('<i class="fas fa-palette"></i> Display & Text', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'gateways' => __('<i class="fas fa-shield-alt"></i> Gateways', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'license' => __('<i class="fas fa-certificate"></i> License', 'advanced-partial-payment-or-deposit-for-woocommerce'),
    ));
    
    ?>

    <div class="advanced-partial-payment">
        <header>
            <h2><?php echo esc_html__('Deposit & Partial Payment Solution for WooCommerce ', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?> <span><?php echo __(' V','advanced-partial-payment-or-deposit-for-woocommerce').esc_html(MEPP_VERSION); ?></span> </h2>
            <?php MEPP_Pro_Ads::get_purchase_button(); ?>
        </header>
        <div class="partial-settings-area">
            <div class="mepp-nav-tab-wrapper" >
                <?php
                $count = 0;
                foreach ($settings_tabs as $key => $tab_name) {
                    $url = admin_url('admin.php?page=admin-mepp-deposits&tab=wc-deposits&section=' . $key);
                    $count++;
                    $active = isset($_GET['section']) ? $key === $_GET['section'] : $count === 1;
                    ?>
                    <a href="<?php echo $url; ?>" class="mepp nav-tab <?php echo $active ? 'mepp-nav-tab-active' : ''; ?>" data-target="<?php echo $key; ?>"><?php echo $tab_name; ?></a>
                    <?php
                }
                ?>
            </div>

            <div  class="mepp-nav-tab-content">
                <form method="post" id="settings-form">

                <?php
                // echo tabs content
                $count = 0;
                foreach ($settings_tabs as $key => $tab_name) {
                    $count++;
                    $active = isset($_GET['section']) ? $key === $_GET['section'] : $count === 1;
                    if (method_exists($this, "tab_{$key}_output")) {
                        $this->{"tab_{$key}_output"}($active);
                    }
                }
                // allow addons to add their own tab content
                do_action('mepp_after_settings_tabs_content');
                ?>
                <?php wp_nonce_field('sr_mepp_nonce'); ?>
                <div>
                <?php submit_button(); ?>
                <?php echo '<input type="hidden" name="auto_submit_action" value="auto_submit">'; ?>
                </div>
                </form>
            </div>
        </div>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
        var tabs = document.querySelectorAll('.mepp.nav-tab');
       
        // Function to handle tab click
        function handleTabClick(event) {
            // Remove active class from all tabs
            tabs.forEach(function(tab) {
                tab.classList.remove('mepp-nav-tab-active');
            });
            // Add active class to the clicked tab
            event.target.classList.add('mepp-nav-tab-active');
            // Store active tab index in sessionStorage
            var tabIndex = Array.from(tabs).indexOf(event.target);
            sessionStorage.setItem('activeTabIndex', tabIndex);
        }

        // Add click event listener to each tab
        tabs.forEach(function(tab) {
            tab.addEventListener('click', handleTabClick);
        });

        // Check if there's a stored active tab index and apply active class
        var activeTabIndex = sessionStorage.getItem('activeTabIndex');
        if (activeTabIndex !== null) {
            // Remove active class from all tabs (again to ensure no conflict)
            tabs.forEach(function(tab) {
                tab.classList.remove('mepp-nav-tab-active');
            });
            // Add active class to the tab based on stored index
            tabs[activeTabIndex].classList.add('mepp-nav-tab-active');
        }
            });
        </script>
    </div>
    <?php
}



    /*** BEGIN TABS CONTENT CALLBACKS **/

    function tab_mepp_general_output($active)
    {
        $class = $active ? '' : 'hidden';
        ?>
        
        <div id="mepp_general" class="mepp-tab-content wrap mepp-custom-container <?php echo $class; ?>">

            <?php
            $roles_array = array();
            $user_roles = array_reverse(get_editable_roles());
            foreach ($user_roles as $key => $user_role) {

                $roles_array[$key] = $user_role['name'];
            }
            $manage_plans_link = sprintf(wp_kses(__(' <a  target="_blank" href="%s"> Manage Payment Plans</a>', 'advanced-partial-payment-or-deposit-for-woocommerce'), array('a' => array('href' => array(), 'target' => array()))), admin_url('/edit-tags.php?taxonomy=mepp_payment_plan&post_type=product'));

            //payment plans
            $payment_plans = get_terms(array(
                    'taxonomy' => MEPP_PAYMENT_PLAN_TAXONOMY,
                    'hide_empty' => false
                )
            );
            $all_plans = array();
            foreach ($payment_plans as $payment_plan) {
                if(isset($payment_plan->term_id)){
                    $all_plans[$payment_plan->term_id] = $payment_plan->name;
                }
                
            }
            ?>

            <?php $general_settings = array(


                /*
                 * Site-wide settings
                 */

                'deposit_storewide_values' => array(

                    'name' => esc_html__('Global Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'mepp_deposit_storewide_values',
                    'class' => 'deposits_deposit_storewide_values',
                ),

                'enable_storewide_deposit' => array(
                    'name' => esc_html__('Enable deposit by default', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'desc_tip' =>true,
                    'options' => array(
                        'no' => esc_html__('No', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'yes' => esc_html__('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    ),
                    'desc' => esc_html__('Enable this to require a deposit for all products by default.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_storewide_deposit_enabled',
                    'default' => 'no'
                ),
                
                //    'enable_storewide_deposit_details' => array(
                //     'name' => esc_html__('On/Off Pay Deposit details in cart & checkout page', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'select',
                //     'desc_tip' =>true,
                //     'options' => array(
                //         'no' => esc_html__('No', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'yes' => esc_html__('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     ),
                //     'desc' => esc_html__('On/Off Pay Deposit details in cart & checkout page.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_storewide_deposit_enabled_details',
                //     'default' => 'yes'
                // ),
                
                    'enable_storewide_deposit_btn' => array(
                    'name' => esc_html__('On/Off Pay Deposit button in product list', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'desc_tip' =>true,
                    'options' => array(
                        'no' => esc_html__('No', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'yes' => esc_html__('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    ),
                    'desc' => esc_html__('Choose whether to enable the Pay Deposit button in the product list.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_storewide_deposit_enabled_btn',
                    'default' => 'yes'
                ),
                'storewide_deposit_force_deposit' => array(
                    'name' => esc_html__('Force deposit by default', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'desc_tip' =>true,
                    'options' => array(
                        'no' => esc_html__('No', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'yes' => esc_html__('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    ), 'desc' => esc_html__('If you enable this, the customer will not be allowed to make a full payment.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_storewide_deposit_force_deposit',
                    'default' => 'no'

                ),
             'storewide_deposit_amount_type' => array(
    'name' => esc_html__('Default Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
    'type' => 'select',
    'desc_tip' => true,
    'desc' => esc_html__('Choose amount type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
    'id' => 'mepp_storewide_deposit_amount_type',
    'options' => apply_filters('mepp_settings_dropdown_options', array(
        'fixed' => esc_html__('Fixed', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'percent' => esc_html__('Percentage', 'advanced-partial-payment-or-deposit-for-woocommerce'),
    )),
    'default' => 'percent'
),

                'storewide_deposit_amount' => array(
                    'name' => esc_html__('Default Deposit Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'number',
                    'desc_tip' =>true,
                    'desc' => esc_html__('Amount of deposit.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_storewide_deposit_amount',
                    'default' => '50',
                    'custom_attributes' => array(
                        'min' => '0.0',
                        'step' => '0.01'
                    )
                ),
                'storewide_deposit_payment_plans' => array(
                    'name' => esc_html__('Default Payment plan(s)', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'multiselect',
                    'desc_tip' =>true,
                    'class' => 'chosen_select',
                    'options' => $all_plans,
                    'desc' => esc_html__('Selected payment plan(s) will be available for customers to choose from.  ', 'advanced-partial-payment-or-deposit-for-woocommerce') . $manage_plans_link,
                    'id' => 'mepp_storewide_deposit_payment_plans',
                ),
                'deposit_storewide_values_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_deposit_storewide_values_end'
                ),
                'sitewide_title' => array(
                    'name' => esc_html__('Site-wide Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'mepp_site_wide_title'
                ),
                // 'deposits_disable' => array(
                //     'name' => esc_html__('Disable Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'title',
                //     'type' => 'checkbox',
                //     'desc' => esc_html__('Check this to disable all deposit functionality with one click.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_site_wide_disable',
                    
                // ),


                'deposits_default' => array(
                    'name' => esc_html__('Default Selection', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'desc_tip' =>true,
                    'desc' => esc_html__('Select the default deposit option.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_default_option',
                    'options' => array(
                        'deposit' => esc_html__('Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'full' => esc_html__('Full Amount', 'advanced-partial-payment-or-deposit-for-woocommerce')
                    ),
                    'default' => 'deposit'
                ),
                'deposits_stock' => array(
                    'name' => esc_html__('Reduce Stocks On', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'desc_tip' =>true,
                    'desc' => esc_html__('Choose when to reduce stocks.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_reduce_stock',
                    'options' => array(
                        'deposit' => esc_html__('Deposit Payment', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'full' => esc_html__('Full Payment', 'advanced-partial-payment-or-deposit-for-woocommerce')
                    ),
                    'default' => 'full'
                ),
                // 'partially_paid_orders_editable' => array(
                //     'name' => esc_html__('Make partially paid orders editable', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'checkbox',
                //     'desc' => esc_html__('Check to make orders editable while in "partially paid" status', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_partially_paid_orders_editable',
                // ),

                'order_list_table_show_has_deposit' => array(
                    'name' => esc_html__('Show "has deposit" column in admin order list table', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Check to show a column in admin order list indicating if order has deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_order_list_table_show_has_deposit',
                    'default' => 'yes',
                ),

                'disable_deposit_for_user_roles' => array(
                    'name' => esc_html__('Disable deposit for selected user roles', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'multiselect',
                    'desc_tip' =>true,
                    'class' => 'chosen_select',
                    'options' => $roles_array,
                    'desc' => esc_html__('Disable deposit for selected user roles', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_disable_deposit_for_user_roles',
                ),

                'restrict_deposits_for_logged_in_users_only' => array(
                    'name' => esc_html__('Restrict deposits for logged-in users only', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Check this to disable all deposit functionality for guests', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_restrict_deposits_for_logged_in_users_only',
                ),
                'sitewide_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_site_wide_end'
                ),
            //    'calculation_and_structure' => array(

            //         'name' => esc_html__('Calculation & Structure', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            //         'type' => 'title',
            //         'desc' => '',
            //         'id' => 'mepp_calculation_and_structure'
            //     ),
                // 'partial_payments_structure' => array(
                //     'name' => esc_html__('Partial Payments Structure', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'select',
                //     'desc_tip' =>true,
                //     'desc' => esc_html__('Choose how partial payments are created. If single is checked, partial payment will consist of a single fee. 
                //                                If "Copy main order items" is selected, items of main order will be created in partial payment.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_partial_payments_structure',
                //     'default' => 'single',
                //     'options' => array(
                //         'single' => esc_html__('Single fee item', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'full' => esc_html__('Copy main order items', 'advanced-partial-payment-or-deposit-for-woocommerce')
                //     )
                // ),
                // 'taxes_handling' => array(
                //     'name' => esc_html__('Taxes Collection Method', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'select',
                //     'desc_tip' =>true,
                //     'desc' => esc_html__('Choose how to handle taxes.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_taxes_handling',
                //     'options' => array(
                //         'deposit' => esc_html__('with deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'split' => esc_html__('Split according to deposit amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'full' => esc_html__('with future payment(s)', 'advanced-partial-payment-or-deposit-for-woocommerce')
                //     )
                // ),
                // 'fees_handling' => array(
                //     'name' => esc_html__('Fees Collection Method', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'select',
                //     'desc_tip' =>true,
                //     'desc' => esc_html__('Choose how to handle fees.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_fees_handling',
                //     'options' => array(
                //         'deposit' => esc_html__('with deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'split' => esc_html__('Split according to deposit amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'full' => esc_html__('with future payment(s)', 'advanced-partial-payment-or-deposit-for-woocommerce')
                //     )
                // ),
                // 'shipping_handling' => array(
                //     'name' => esc_html__('Shipping Handling Method', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'select',
                //     'desc_tip' =>true,
                //     'desc' => esc_html__('Choose how to handle shipping.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_shipping_handling',
                //     'options' => array(
                //         'deposit' => esc_html__('with deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'split' => esc_html__('Split according to deposit amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'full' => esc_html__('with future payment(s)', 'advanced-partial-payment-or-deposit-for-woocommerce')
                //     )
                // ),
                // 'coupons_handling' => array(
                //     'name' => esc_html__('Discount Coupons Handling', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'select',
                //     'desc_tip' =>true,
                //     'desc' => esc_html__('Choose how to handle coupon discounts', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_coupons_handling',
                //     'options' => array(
                //         'deposit' => esc_html__('Deduct from deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'split' => esc_html__('Split according to deposit amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //         'second_payment' => esc_html__('Deduct from future payment(s)', 'advanced-partial-payment-or-deposit-for-woocommerce')
                //     ),
                //     'default' => 'second_payment'
                // ),
                // 'calculation_and_structure_end' => array(
                //     'type' => 'sectionend',
                //     'id' => 'mepp_calculation_and_structure_end'
                // ),

            );
           


           woocommerce_admin_fields($general_settings);

            ?>
            <?php do_action('mepp_settings_tabs_general_tab'); ?>

        </div>
        <?php
    }

    function tab_display_text_output($active)
    {

        $class = $active ? '' : 'hidden';
        ?>
        <div id="display_text" class="mepp-tab-content wrap mepp-custom-container <?php echo $class; ?>">
            <?php
            $text_to_replace = esc_html__('Text to replace ', 'advanced-partial-payment-or-deposit-for-woocommerce');

            $strings_settings = array(

                'display_title' => array(
                    // 'name' => esc_html__('Display & Text', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    'id' => 'mepp_display_text_title'
                ),
                // 'hide_when_forced' => array(
                //     'name' => esc_html__('Hide Deposit UI when forced', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'checkbox',
                //     'desc' => esc_html__('Check this to hide deposit UI when deposit is forced ', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_hide_ui_when_forced',
                // ),
                // 'override_payment_form' => array(
                //     'name' => esc_html__('Override payment form', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'checkbox',
                //     'desc' => esc_html__('allow overriding "form-pay.php" template to display original order details during partial payment checkout', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_override_payment_form',
                //     'default' => 'yes',
                // ),
                // 'deposits_tax' => array(
                //     'name' => esc_html__('Display Taxes In Product page', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'checkbox',
                //     'desc' => esc_html__('Check this to count taxes as part of deposits for purposes of display to the customer in product page.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_tax_display',
                // ),
                // 'deposits_tax_cart' => array(
                //     'name' => esc_html__('Display taxes in cart item Details', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'checkbox',
                //     'desc' => esc_html__('Check this to count taxes as part of deposits for purposes of display to the customer in cart item details', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_tax_display_cart_item',
                // ),
                // 'deposits_breakdown_cart_tooltip' => array(
                //     'name' => esc_html__('Display Deposit-breakdown Tooltip in cart', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'type' => 'checkbox',
                //     'desc' => esc_html__('Check to display tooltip in cart totals detailing deposit breakdown', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                //     'id' => 'mepp_breakdown_cart_tooltip',
                // ),
                'display_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_display_text_end'
                ),


                /*
                 * Section for buttons
                 */

                'buttons_title' => array(
                    'name' => esc_html__('Color and Button Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    //'desc' => wp_kses(__('No HTML allowed. Text will be translated to the user if a translation is available.<br/>Please note that any overflow will be hidden, since button width is theme-dependent.', 'advanced-partial-payment-or-deposit-for-woocommerce'), array('br' => array())),
                    'id' => 'mepp_buttons_title'
                ),

                'basic_radio_buttons' => array(
                    'name' => esc_html__('Use Basic Deposit Buttons', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Use basic radio buttons for deposits, Check this if you are facing issues with deposits slider buttons in product page, ', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_use_basic_radio_buttons',
                    'default' => 'no',
                ),
                'buttons_color' => array(
                    'type' => 'deposit_buttons_color',
                    'class' => 'deposit_buttons_color_html',
                ),
                'buttons_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_buttons_end'
                ),
                'deposit_choice_strings_title' => array(
                    'name' => esc_html__('Deposit choice strings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    // 'desc' => esc_html__('No HTML allowed. Text will be translated to the user if a translation is available.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_strings_title'
                )
            ,
                'deposits_button_deposit' => array(
                    'name' => esc_html__('Deposit Button Text', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'desc' => esc_html__('Text displayed in the \'Pay Deposit\' button.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_button_deposit',
                    'default' => 'Pay Deposit'
                ),
                'deposits_button_full' => array(
                    'name' => esc_html__('Full Amount Button Text', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'desc' => esc_html__('Text displayed in the \'Full Amount\' button.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_button_full_amount',
                    'default' => 'Full Amount'
                ),
                'deposit_choice_strings_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_deposit_choice_strings_end'
                ),
                'checkout_and_order_strings' => array(
                    'name' => esc_html__('Checkout & Order strings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    // 'desc' => esc_html__('No HTML allowed. Text will be translated to the user if a translation is available.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_strings_title'
                ),

                'deposits_to_pay_text' => array(
                    'name' => esc_html__('To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'desc' => $text_to_replace . '<b>' . esc_html__('To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</b>',
                    'id' => 'mepp_to_pay_text',
                    'default' => 'To Pay'
                ),
                'deposits_deposit_amount_text' => array(
                    'name' => esc_html__('Deposit Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'desc' => $text_to_replace . '<b>' . esc_html__('Deposit Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</b>',
                    'id' => 'mepp_deposit_amount_text',
                    'default' => 'Deposit Amount'
                ),
                'deposits_second_payment_text' => array(
                    'name' => esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'desc' => $text_to_replace . '<b>' . esc_html__('Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</b>',
                    'id' => 'mepp_second_payment_text',
                    'default' => 'Future Payments'
                ),
                'deposits_deposit_option_text' => array(
                    'name' => esc_html__('Deposit Option', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'desc' => $text_to_replace . '<b>' . esc_html__('Deposit Option', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</b>',
                    'id' => 'mepp_deposit_option_text',
                    'default' => 'Deposit Option'
                ),

                'deposits_payment_link_text' => array(
                    'name' => esc_html__('Payment Link', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'desc' => $text_to_replace . '<b>' . esc_html__('Payment Link', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</b>',
                    'id' => 'mepp_payment_link_text',
                    'default' => 'Payment Link'
                ),

                'strings_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_strings_end'
                ),
                /*
                 * Section for messages
                 */

                'messages_title' => array(
                    'name' => esc_html__('Messages', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    //'desc' => esc_html__('Please check the documentation for allowed HTML tags.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_messages_title'
                ),
                'deposits_message_deposit' => array(
                    'name' => esc_html__('Deposit Message', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'textarea',
                    'desc' => esc_html__('Message to show when \'Pay Deposit\' is selected on the product\'s page.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_message_deposit',
                ),
                'deposits_message_full' => array(
                    'name' => esc_html__('Full Amount Message', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'textarea',
                    'desc' => __('Message to show when \'Full Amount\' is selected on the product\'s page.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_message_full_amount',
                ),
                'messages_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_messages_end'
                ),


            );
            woocommerce_admin_fields($strings_settings);
            ?>
            <?php do_action('mepp_settings_tabs_display_text_tab'); ?>
        </div>
        <?php
    }

    function tab_checkout_mode_output($active)
    {
        $class = $active ? '' : 'hidden';
        ?>
        <div id="checkout_mode" class="mepp-tab-content wrap mepp-custom-container <?php echo $class; ?>">
            <?php

            $cart_checkout_settings = array(

                'checkout_mode_title' => array(
                    'name' => esc_html__('Deposit on Checkout Mode', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    'desc' => esc_html__('changes the way deposits work to be based on total amount at checkout button', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_messages_title'
                ),
                'enable_checkout_mode' => array(
                    'name' => esc_html__('Enable checkout mode', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        'no' => esc_html__('No', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'yes' => esc_html__('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    ),
                    'desc' => esc_html__('Enable checkout mode, which makes deposits calculate based on total amount during checkout instead of per product.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_checkout_mode_enabled',
                ),
                'checkout_mode_force_deposit' => array(
                    'name' => esc_html__('Force deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'options' => array(
                        'no' => esc_html__('No', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'yes' => esc_html__('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    ),
                    'desc' => esc_html__('Force Checkout Mode Deposit, the customer will not be allowed to make a full payment during checkout.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_checkout_mode_force_deposit',
                ),
                'checkout_mode_amount_type' => array(
                    'name' => esc_html__('Amount Type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'desc' => esc_html__('Choose amount type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_checkout_mode_deposit_amount_type',
                    'options' => array(
                        'fixed' => esc_html__('Fixed', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'percentage' => esc_html__('Percentage', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'payment_plan' => esc_html__('Payment plan', 'advanced-partial-payment-or-deposit-for-woocommerce')
                    ),
                    'default' => 'percentage'
                ),
                'checkout_mode_amount_deposit_amount' => array(
                    'name' => esc_html__('Deposit Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'number',
                    'desc' => esc_html__('Amount of deposit ( should not be more than 99 for percentage or more than order total for fixed', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_checkout_mode_deposit_amount',
                    'default' => '50',
                    'custom_attributes' => array(
                        'min' => '0.0',
                        'step' => '0.01'
                    )
                ),


            );


            //payment plans
            $payment_plans = get_terms(array(
                    'taxonomy' => MEPP_PAYMENT_PLAN_TAXONOMY,
                    'hide_empty' => false
                )
            );

            $all_plans = array();
            foreach ($payment_plans as $payment_plan) {
                $all_plans[$payment_plan->term_id] = $payment_plan->name;
            }

            $cart_checkout_settings['checkout_mode_payment_plans'] = array(
                'name' => esc_html__('Payment plan(s)', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'multiselect',
                'class' => 'chosen_select',
                'options' => $all_plans,
                'desc' => esc_html__('Selected payment plan(s) will be available for customers to choose from', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_checkout_mode_payment_plans'
            );

            $cart_checkout_settings['checkout_mode_end'] = array(
                'type' => 'sectionend',
                'id' => 'mepp_checkout_mode_end'
            );


            woocommerce_admin_fields($cart_checkout_settings);

            ?>
            <?php do_action('mepp_settings_tabs_checkout_mode_tab'); ?>

        </div>

        <?php

    }

    function tab_second_payment_output($active)
    {
        $class = $active ? '' : 'hidden';

        ?>
        <div id="second_payment" class="mepp-tab-content wrap mepp-custom-container <?php echo $class; ?>" >


            <?php

            $reminder_settings = array(
                'second_payment_settings' => array(
                    'name' => esc_html__('Future Payments Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    'id' => 'mepp_second_payment_settings_title'
                ),
                'deposits_payaple' => array(
                    'name' => esc_html__('Enable Future Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'checkbox',
                    'desc' => esc_html__('Uncheck this to prevent the customer from making any payment beyond deposit. (You\'ll have to manually mark the orders as completed)',
                        'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'id' => 'mepp_remaining_payable',
                    'default' => 'yes',
                ),
            );

            $reminder_settings['second_payment_due_after'] = array(
                'name' => esc_html__('Days before Second Payment is due', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'number',
                'desc' => esc_html__('Number of days before second payment is due ( if no payment plan with dates assigned, leave field empty for unlimited days )', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_second_payment_due_after',
                'default' => ''
            );
            $statuses = array();
            foreach (wc_get_is_paid_statuses() as $status) {
                $statuses[$status] = wc_get_order_status_name($status);
            }

            $reminder_settings['order_fully_paid_status'] = array(
                'name' => esc_html__('Order fully paid status', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'select',
                'desc' => esc_html__('Order status when all partial payments are completed', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_order_fully_paid_status',
                'options' => $statuses
            );

            $reminder_settings['second_payment_settings_end'] = array(
                'type' => 'sectionend',
                'id' => 'mepp_second_payment_settings_end'
            );

            $reminder_settings['reminder_settings'] = array(
                'name' => esc_html__('Reminder Email Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'title',
                'desc' => esc_html__('This section cover automation of reminder emails. ( You can always send a reminder manually from order actions ) ', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_reminder_settings_title'
            );

            $reminder_settings['enable_second_payment_reminder'] = array(
                'name' => esc_html__('Enable Partial Payment Reminder after "X" Days from deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'checkbox',
                'desc' => esc_html__('Check this to enable sending payment reminder email automatically after X number of days of deposit payment.',
                    'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_enable_second_payment_reminder',
                'default' => 'no',
            );
            $reminder_settings['second_payment_reminder_duration'] = array(
                'name' => esc_html__('Partial Payment Reminder after "X" days from deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'number',
                'desc' => esc_html__('Duration between partial payment and payment reminder (in days)', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_second_payment_reminder_duration',
                'default' => '14'
            );
            $reminder_settings['enable_partial_payment_reminder'] = array(
                'name' => sprintf(esc_html__('Enable %s "X" days before due date', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html__('Partial Payment reminder', 'advanced-partial-payment-or-deposit-for-woocommerce')),
                'type' => 'checkbox',
                'desc' => sprintf(esc_html__('Check this to enable %s "X" days before due date', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html__('Partial Payment reminder', 'advanced-partial-payment-or-deposit-for-woocommerce')),
                'id' => 'mepp_enable_partial_payment_reminder',
                'default' => 'no',
            );
            $reminder_settings['partial_payment_reminder_x_days_before_due_date'] = array(
                'name' => esc_html__('Partial Payment Reminder "X" days before due date', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'number',
                'desc' => esc_html__('Send a reminder email x days before partial payment due date', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_partial_payment_reminder_x_days_before_due_date',
                'default' => '3'
            );
            $reminder_settings['reminder_settings_end'] = array(
                'type' => 'sectionend',
                'id' => 'mepp_reminder_settings_end'
            );

            $reminder_settings['custom_reminder_datepicker_title'] = array(
                'name' => esc_html__('Custom Remainder Email Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'title',
                'id' => 'mepp_custom_reminder_datepicker_title'
            );

            $reminder_settings['reminder_datepicker'] = array(
                'type' => 'reminder_datepicker',
                'class' => 'reminder_datepicker_html',
            );
            $reminder_settings['custom_reminder_datepicker_end'] = array(
                'type' => 'sectionend',
                'id' => 'mepp_custom_reminder_datepicker_end'
            );

            woocommerce_admin_fields($reminder_settings);

            ?>
            <?php do_action('mepp_settings_tabs_second_payment_tab'); ?>

        </div>

        <?php
    }

    function tab_gateways_output($active)
    {
        $class = $active ? '' : 'hidden';

        ?>
        <div id="gateways" class="mepp-tab-content wrap mepp-custom-container <?php echo $class; ?>">

            <?php

            /*
     * Allowed gateways
     */

            $gateways_settings = array();

            $gateways_settings['gateways_title'] = array(
                'name' => esc_html__('Disallowed Gateways', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'title',
                // 'desc' => esc_html__('Disallow the following gateways when there is a deposit in the cart.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_gateways_title'
            );

            $gateways_array = array();
            $gateways = WC()->payment_gateways()->payment_gateways();
            if (isset($gateways['wc-booking-gateway'])) unset($gateways['wc-booking-gateway']);// Protect the wc-booking-gateway

            foreach ($gateways as $key => $gateway) {

                $gateways_array[$key] = $gateway->title;
            }


            $gateways_settings['mepp_disallowed_gateways_for_deposit'] = array(
                'name' => esc_html__('Disallowed For Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'multiselect',
                'class' => 'chosen_select',
                'options' => $gateways_array,
                'desc' => esc_html__('Disallowed For Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_disallowed_gateways_for_deposit',
            );

            $gateways_settings['mepp_disallowed_gateways_for_second_payment'] = array(
                'name' => esc_html__('Disallowed For Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'type' => 'multiselect',
                'class' => 'chosen_select',
                'options' => $gateways_array,
                'desc' => esc_html__('Disallowed For Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'id' => 'mepp_disallowed_gateways_for_second_payment',
            );


            $gateways_settings['gateways_end'] = array(
                'type' => 'sectionend',
                'id' => 'mepp_gateways_end'
            );


            woocommerce_admin_fields($gateways_settings);

            ?>
            <?php do_action('mepp_settings_tabs_gateways_tab'); ?>

        </div>

        <?php
    }

    function tab_license_output($active)
{
    $class = $active ? '' : 'hidden';

    ?>
    <div id="license" class="mepp-tab-content wrap mepp-custom-container <?php echo $class; ?>">

        <?php

        /*
 * Allowed license
 */

        $license_settings = array();

        $license_settings['license_title'] = array(
            'name' => esc_html__('Advanced Partial/Deposit Payment For Woocommerce Licensing', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'type' => 'title',
            'desc' => esc_html__('Thanks you for using our Advanced Partial/Deposit Payment For Woocommerce plugin. This plugin is free and no license is required. We have some Additional addon to enhance feature of this plugin functionality. If you have any addon you need to enter a valid license for that plugin below.                .', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'id' => 'mepp_license_title'
        );

        // Ensure $license is initialized as an array
        $license = array();

        foreach ($license as $key => $license_item) {

            // Assuming $license_item is an object with a 'title' property
            $license_array[$key] = $license_item->title;
        }

        $license_settings['license_end'] = array(
            'type' => 'sectionend',
            'id' => 'mepp_license_end'
        );

        woocommerce_admin_fields($license_settings);

        ?>
        <?php do_action('mepp_settings_tabs_license_tab'); ?>
        <div class='mep_licensae_info'></div>
        <?php do_action('mepp_license_setting_pro'); ?>
    </div>

    <?php
}


   

    function tab_advanced_output($active)
    {
        $class = $active ? '' : 'hidden';
        ?>
        <div id="advanced" class="mepp-tab-content wrap <?php echo $class; ?>">
            <?php

            $advanced_fields = array(
                'advanced_title' => array(
                    'name' => __('Advanced', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'title',
                    'desc' => '',
                    'id' => 'mepp_advanced_title'
                ),

                'advanced_end' => array(
                    'type' => 'sectionend',
                    'id' => 'mepp_advanced_end'
                )
            );
            woocommerce_admin_fields($advanced_fields);
            ?>
        </div>

        <?php
    }

    /*** END TABS CONTENT CALLBACKS **/

    /*** BEGIN DEPOSIT OPTIONS CUSTOM FIELDS CALLBACKS **/
    function reminder_datepicker()
    {

        $reminder_date = get_option('mepp_reminder_datepicker');
        ob_start();

        ?>
        <script>
            jQuery(function ($) {
                'use strict';

                $("#reminder_datepicker").datepicker({

                    dateFormat: "dd-mm-yy",
                    minDate: new Date()

                }).datepicker("setDate", "<?php echo $reminder_date; ?>");
            });
        </script>
        <p>
            <b><?php echo esc_html__('If you would like to send out all partial payment reminders on a specific date in the future, set a date below.', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></b>
        </p>
        <p> <?php echo esc_html__('Next Custom Reminder Date :', 'advanced-partial-payment-or-deposit-for-woocommerce') ?> <input type="text"
         name="mepp_reminder_datepicker"
            id="reminder_datepicker">
        </p>
        <?php
        echo ob_get_clean();
    }

    public function deposit_buttons_color()
    {

        $colors = get_option('mepp_deposit_buttons_colors',array('primary'=>'','secondary'=>'','highlight'=>''));
        $primary_color = $colors['primary'];
        $secondary_color = $colors['secondary'];
        $highlight_color = $colors['highlight'];;

        ?>
        <tr class="">
            <th scope="row"
                class="titledesc"><?php echo esc_html__('Deposit Buttons Primary Colour', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <td class="forminp forminp-checkbox">
                <fieldset>
                    <input type="text" name="mepp_deposit_buttons_colors_primary" class="deposits-color-field"
                           value="<?php echo $primary_color; ?>">
                </fieldset>
            </td>
        </tr>
        <tr class="">
            <th scope="row"
                class="titledesc"><?php echo esc_html__('Deposit Buttons Secondary Colour', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <td class="forminp forminp-checkbox">
                <fieldset>
                    <input type="text" name="mepp_deposit_buttons_colors_secondary" class="deposits-color-field"
                           value="<?php echo $secondary_color; ?>">
                </fieldset>
            </td>
        </tr>
        <tr class="">
            <th scope="row"
                class="titledesc"><?php echo esc_html__('Deposit Buttons Highlight Colour', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <td class="forminp forminp-checkbox">
                <fieldset>
                    <input type="text" name="mepp_deposit_buttons_colors_highlight" class="deposits-color-field"
                           value="<?php echo $highlight_color; ?>">
                </fieldset>
            </td>
        </tr>
        <?php
    }

    /*** END  DEPOSIT OPTIONS CUSTOM FIELDS CALLBACKS **/


   

    /**
     * @brief Save all settings on POST
     *
     * @return void
     */
    public function update_options_mepp()
    {
        
        if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'sr_mepp_nonce' ) ) {
            
            $allowed_html = array(
                'strong' => array(),
                'p' => array(),
                'br' => array(),
                'em' => array(),
                'b' => array(),
                's' => array(),
                'strike' => array(),
                'del' => array(),
                'u' => array(),
                'i' => array(),
                'a' => array(
                    'target' => array(),
                    'href' => array()
                )
            );

            $settings = array();


            $settings ['mepp_site_wide_disable'] = isset($_POST['mepp_site_wide_disable']) ? 'yes' : 'no';

            $settings['mepp_default_option'] = isset($_POST['mepp_default_option']) ?
                ($_POST['mepp_default_option'] === 'deposit' ? 'deposit' : 'full') : 'deposit';

            $settings['mepp_reduce_stock'] = isset($_POST['mepp_reduce_stock']) ?
                ($_POST['mepp_reduce_stock'] === 'deposit' ? 'deposit' : 'full') : 'full';
            $settings['mepp_tax_display'] = isset($_POST['mepp_tax_display']) ? 'yes' : 'no';
            $settings['mepp_tax_display_cart_item'] = isset($_POST['mepp_tax_display_cart_item']) ? 'yes' : 'no';
            $settings['mepp_breakdown_cart_tooltip'] = isset($_POST['mepp_breakdown_cart_tooltip']) ? 'yes' : 'no';
            $settings['mepp_override_payment_form'] = isset($_POST['mepp_override_payment_form']) ? 'yes' : 'no';
            $settings['mepp_hide_ui_when_forced'] = isset($_POST['mepp_hide_ui_when_forced']) ? 'yes' : 'no';
            $settings['mepp_use_basic_radio_buttons'] = isset($_POST['mepp_use_basic_radio_buttons']) ? 'yes' : 'no';

            $settings ['mepp_partially_paid_orders_editable'] = isset($_POST['mepp_partially_paid_orders_editable']) ? 'yes' : 'no';
            $settings ['mepp_order_list_table_show_has_deposit'] = isset($_POST['mepp_order_list_table_show_has_deposit']) ? 'yes' : 'no';
            $settings ['mepp_disable_deposit_for_user_roles'] = isset($_POST['mepp_disable_deposit_for_user_roles']) ? $_POST['mepp_disable_deposit_for_user_roles'] : array();
            $settings ['mepp_restrict_deposits_for_logged_in_users_only'] = isset($_POST['mepp_restrict_deposits_for_logged_in_users_only']) ? 'yes' : 'no';


            //STRINGS
            $settings['mepp_to_pay_text'] = isset($_POST['mepp_to_pay_text']) ? esc_html($_POST['mepp_to_pay_text']) : 'To Pay';
            $settings['mepp_second_payment_text'] = isset($_POST['mepp_second_payment_text']) ? esc_html($_POST['mepp_second_payment_text']) : 'Future Payments';
            $settings['mepp_deposit_amount_text'] = isset($_POST['mepp_deposit_amount_text']) ? esc_html($_POST['mepp_deposit_amount_text']) : 'Deposit Amount';
            $settings['mepp_deposit_option_text'] = isset($_POST['mepp_deposit_option_text']) ? esc_html($_POST['mepp_deposit_option_text']) : 'Deposit Option';
            $settings['mepp_payment_link_text'] = isset($_POST['mepp_payment_link_text']) ? esc_html($_POST['mepp_payment_link_text']) : 'Payment Link';

            $settings['mepp_deposit_buttons_colors'] = array(

                'primary' => isset($_POST['mepp_deposit_buttons_colors_primary']) ? $_POST['mepp_deposit_buttons_colors_primary'] : false,
                'secondary' => isset($_POST['mepp_deposit_buttons_colors_secondary']) ? $_POST['mepp_deposit_buttons_colors_secondary'] : false,
                'highlight' => isset($_POST['mepp_deposit_buttons_colors_highlight']) ? $_POST['mepp_deposit_buttons_colors_highlight'] : false
            );

            $settings['mepp_checkout_mode_enabled'] = isset($_POST['mepp_checkout_mode_enabled']) ? $_POST['mepp_checkout_mode_enabled'] : 'no';
            $settings['mepp_checkout_mode_force_deposit'] = isset($_POST['mepp_checkout_mode_force_deposit']) ? $_POST['mepp_checkout_mode_force_deposit'] : 'no';
            $settings['mepp_checkout_mode_deposit_amount'] = isset($_POST['mepp_checkout_mode_deposit_amount']) ? $_POST['mepp_checkout_mode_deposit_amount'] : '0';
            $settings['mepp_checkout_mode_deposit_amount_type'] = isset($_POST['mepp_checkout_mode_deposit_amount_type']) ? $_POST['mepp_checkout_mode_deposit_amount_type'] : 'percentage';
            $settings['mepp_checkout_mode_payment_plans'] = isset($_POST['mepp_checkout_mode_payment_plans']) ? $_POST['mepp_checkout_mode_payment_plans'] : array();

            $settings['mepp_partial_payments_structure'] = isset($_POST['mepp_partial_payments_structure']) ? $_POST['mepp_partial_payments_structure'] : 'single';
            $settings['mepp_fees_handling'] = isset($_POST['mepp_fees_handling']) ? $_POST['mepp_fees_handling'] : 'split';
            $settings['mepp_taxes_handling'] = isset($_POST['mepp_taxes_handling']) ? $_POST['mepp_taxes_handling'] : 'split';
            $settings['mepp_shipping_handling'] = isset($_POST['mepp_shipping_handling']) ? $_POST['mepp_shipping_handling'] : 'split';
            $settings['mepp_coupons_handling'] = isset($_POST['mepp_coupons_handling']) ? $_POST['mepp_coupons_handling'] : 'full';



            $settings['mepp_remaining_payable'] = isset($_POST['mepp_remaining_payable']) ? 'yes' : 'yes';
            $settings['mepp_enable_second_payment_reminder'] = isset($_POST['mepp_enable_second_payment_reminder']) ? 'yes' : 'no';
            $settings['mepp_second_payment_due_after'] = isset($_POST['mepp_second_payment_due_after']) ? $_POST['mepp_second_payment_due_after'] : '';
            $settings['mepp_second_payment_reminder_duration'] = isset($_POST['mepp_second_payment_reminder_duration']) ? $_POST['mepp_second_payment_reminder_duration'] : '0';
            $settings['mepp_button_deposit'] = isset($_POST['mepp_button_deposit']) ? esc_html($_POST['mepp_button_deposit']) : esc_html__('Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce');
            $settings['mepp_button_full_amount'] = isset($_POST['mepp_button_full_amount']) ? esc_html($_POST['mepp_button_full_amount']) : esc_html__('Full Amount', 'advanced-partial-payment-or-deposit-for-woocommerce');
            $settings['mepp_message_deposit'] = isset($_POST['mepp_message_deposit']) ? wp_kses($_POST['mepp_message_deposit'], $allowed_html) : '';
            $settings['mepp_message_full_amount'] = isset($_POST['mepp_message_full_amount']) ? wp_kses($_POST['mepp_message_full_amount'], $allowed_html) : '';


            //partial payment reminder
            $settings['mepp_order_fully_paid_status'] = isset($_POST['mepp_order_fully_paid_status']) ? $_POST['mepp_order_fully_paid_status'] : 'processing';


            $settings['mepp_enable_partial_payment_reminder'] = isset($_POST['mepp_enable_partial_payment_reminder']) ? 'yes' : 'no';
            $settings['mepp_partial_payment_reminder_x_days_before_due_date'] = isset($_POST['mepp_partial_payment_reminder_x_days_before_due_date']) ? $_POST['mepp_partial_payment_reminder_x_days_before_due_date'] : '3';

            //gateway options
            $settings ['mepp_disallowed_gateways_for_deposit'] = isset($_POST['mepp_disallowed_gateways_for_deposit']) ? $_POST['mepp_disallowed_gateways_for_deposit'] : array();
            $settings ['mepp_disallowed_gateways_for_second_payment'] = isset($_POST['mepp_disallowed_gateways_for_second_payment']) ? $_POST['mepp_disallowed_gateways_for_second_payment'] : array();


            //custom reminder date
            $settings['mepp_reminder_datepicker'] = isset($_POST['mepp_reminder_datepicker']) ? $_POST['mepp_reminder_datepicker'] : '';


            //storewide deposit settings
            $settings['mepp_storewide_deposit_enabled_details'] = $_POST['mepp_storewide_deposit_enabled_details'] ?? 'yes';
            $settings['mepp_storewide_deposit_enabled_btn'] = $_POST['mepp_storewide_deposit_enabled_btn'] ?? 'yes';
            $settings['mepp_storewide_deposit_enabled'] = $_POST['mepp_storewide_deposit_enabled'] ?? 'no';
            $settings['mepp_storewide_deposit_force_deposit'] = isset($_POST['mepp_storewide_deposit_force_deposit']) ? $_POST['mepp_storewide_deposit_force_deposit'] : 'no';
            $settings['mepp_storewide_deposit_amount'] = $_POST['mepp_storewide_deposit_amount'] ?? '50';
            if(empty($_POST['mepp_storewide_deposit_amount'])) $settings['mepp_storewide_deposit_amount']  = '50';
            $settings['mepp_storewide_deposit_amount_type'] = isset($_POST['mepp_storewide_deposit_amount_type']) ? $_POST['mepp_storewide_deposit_amount_type'] : 'percent';
            $settings['mepp_storewide_deposit_payment_plans'] = isset($_POST['mepp_storewide_deposit_payment_plans']) ? $_POST['mepp_storewide_deposit_payment_plans'] : array();


            foreach ($settings as $key => $setting) {
                update_option($key, $setting);

            }
        }


    }

    // ads menu tab content method

    public function tab_checkout_mode_ads_output($active){
        $class = $active ? '' : 'hidden';
        ?>
            <div id="checkout_mode_ads" class="mepp-tab-content mepp-custom-container <?php echo $class; ?>"> 
                <div class="pro-ads">
                <a target="_blank" href="<?php echo esc_attr(MEPP_Pro_Ads::get_purchase_link()) ?>" class="button button-primary"><?php _e('Buy pro','advanced-partial-payment-or-deposit-for-woocommerce'); ?></a>
                    <img src="<?php echo MEPP_PLUGIN_URL; ?>/assets/images/checkout-ads.png" alt="" >
                </div>
            </div>
        <?php
    }

    public function tab_future_payment_ads_output($active){
        $class = $active ? '' : 'hidden';
        ?>
            <div id="future_payment_ads" class="mepp-tab-content mepp-custom-container <?php echo $class; ?>">
                <div class="pro-ads">
                    <a target="_blank" href="<?php echo esc_attr(MEPP_Pro_Ads::get_purchase_link()) ?>" class="button button-primary"><?php _e('Buy pro','advanced-partial-payment-or-deposit-for-woocommerce'); ?></a>
                    <img src="<?php echo MEPP_PLUGIN_URL; ?>/assets/images/future-payment-ads.png" alt="" class="pro-ads">
                </div>
            </div>
        <?php
    }

}