<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('Mepp_Admin_Menu')) {
    class Mepp_Admin_Menu
    {
        protected $menu_title;

        public function __construct()
        {
            $this->menu_title = 'Partial Payment';

            add_action('admin_menu', array($this, 'init'));
        }

        public function init()
        {
            add_menu_page($this->menu_title, $this->menu_title, 'manage_options', 'mage-partial', null, 'dashicons-megaphone', 50);

            add_submenu_page('mage-partial', 'Partial Order', 'Partial Order', 'manage_options', 'mage-partial', array($this, 'partial_order_screen'), 1);

            do_action('wcpp_partial_payment_menu');

            // add_submenu_page('mage-partial', 'Reminder Log', 'Reminder Log', 'manage_options', 'mage-reminder-log', array($this, 'reminder_log_screen'), 3);
            do_action('wcpp_reminder_log_menu');

            // if pro is not active include payment plan menu
            if (!is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) :
                add_submenu_page('mage-partial', 'Payment Plan', 'Payment Plan', 'manage_options', 'mage-partial-payment-plan', array($this, 'wcpp_payment_plan_page'), 4);
            endif;

            // add_submenu_page('mage-partial', 'Setting', 'Setting', 'manage_options', 'admin.php?page=wc-settings&tab=settings_tab_mage_partial', null, 4);
            add_submenu_page('mage-partial', 'Partial Settings', 'Settings', 'manage_options', 'mage-partial-setting', array($this, 'partial_setting_screen'), 6);
        }

        public function partial_setting_screen()
        {
            if (isset($_POST['mepp_setting_save'])) {
                isset($_POST['meppp_force_partial_payment']) ? update_option('meppp_force_partial_payment', mep_esc_html($_POST['meppp_force_partial_payment'])) : update_option('meppp_force_partial_payment', 'no');
                isset($_POST['meppp_checkout_zero_price']) ? update_option('meppp_checkout_zero_price', mep_esc_html($_POST['meppp_checkout_zero_price'])) : update_option('meppp_checkout_zero_price', 'no');
                isset($_POST['mepp_admin_notify_partial_payment']) ? update_option('mepp_admin_notify_partial_payment', mep_esc_html($_POST['mepp_admin_notify_partial_payment'])) : update_option('mepp_admin_notify_partial_payment', 'no');

                isset($_POST['meppp_quantity_reduce_on']) ? update_option('meppp_quantity_reduce_on', mep_esc_html($_POST['meppp_quantity_reduce_on'])) : update_option('meppp_quantity_reduce_on', 'full');

                isset($_POST['meppp_shop_pay_deposit_btn']) ? update_option('meppp_shop_pay_deposit_btn', mep_esc_html($_POST['meppp_shop_pay_deposit_btn'])) : update_option('meppp_shop_pay_deposit_btn', 'off');

                if (isset($_POST['meppp_payment_methods_allow'])) {
                    if ($_POST['meppp_payment_methods_allow']) {
                        update_option('meppp_payment_methods_allow', maybe_serialize($_POST['meppp_payment_methods_allow']));
                    }
                }

                if (isset($_POST['meppp_user_roles_allow'])) {
                    if ($_POST['meppp_user_roles_allow']) {
                        update_option('meppp_user_roles_allow', maybe_serialize($_POST['meppp_user_roles_allow']));
                    }
                } else {
                    update_option('meppp_user_roles_allow', array());
                }

                update_option('mepp_enable_partial_by_default', $_POST['mepp_enable_partial_by_default']);
                update_option('mepp_default_partial_type', $_POST['mepp_default_partial_type']);
                update_option('mepp_default_partial_amount', $_POST['mepp_default_partial_amount']);
                if (isset($_POST['mepp_partial_enable_for_page'])) {
                    update_option('mepp_partial_enable_for_page', $_POST['mepp_partial_enable_for_page']);
                }

                if (isset($_POST['mepp_partial_enable_for_cart_page'])) {
                    update_option('mepp_partial_enable_for_cart_page', $_POST['mepp_partial_enable_for_cart_page']);
                } else {
                    update_option('mepp_partial_enable_for_cart_page', 'no');
                }

                if (isset($_POST['mepp_default_payment_plan'])) {
                    update_option('mepp_default_payment_plan', maybe_serialize($_POST['mepp_default_payment_plan']));
                } else {
                    update_option('mepp_default_payment_plan', '');
                }

                if (isset($_POST['mepp_deposit_custom_message'])) {
                    update_option('mepp_deposit_custom_message', mep_esc_html($_POST['mepp_deposit_custom_message']));
                }

                if (isset($_POST['meppp_tax_amount_added'])) {
                    update_option('meppp_tax_amount_added', $_POST['meppp_tax_amount_added']);
                } else {
                    update_option('meppp_tax_amount_added', 'deposit');
                }

                if (isset($_POST['meppp_shipping_amount_added'])) {
                    update_option('meppp_shipping_amount_added', $_POST['meppp_shipping_amount_added']);
                } else {
                    update_option('meppp_shipping_amount_added', 'deposit');
                }

                update_option('mepp_text_translation_string_pay_deposit', mep_esc_html($_POST['mepp_text_translation_string_pay_deposit']));
                update_option('mepp_text_translation_string_full_payment', mep_esc_html($_POST['mepp_text_translation_string_full_payment']));
                update_option('mepp_text_translation_string_payment_total', mep_esc_html($_POST['mepp_text_translation_string_payment_total']));
                update_option('mepp_text_translation_string_deposit', mep_esc_html($_POST['mepp_text_translation_string_deposit']));
                update_option('mepp_text_translation_string_due_amount', mep_esc_html($_POST['mepp_text_translation_string_due_amount']));
                update_option('mepp_text_translation_string_partially_paid', mep_esc_html($_POST['mepp_text_translation_string_partially_paid']));
                update_option('mepp_text_translation_string_due_payment', mep_esc_html($_POST['mepp_text_translation_string_due_payment']));
                update_option('mepp_text_translation_string_deposit_type', mep_esc_html($_POST['mepp_text_translation_string_deposit_type']));
                update_option('mepp_text_translation_string_to_pay', mep_esc_html($_POST['mepp_text_translation_string_to_pay']));
                update_option('mepp_text_translation_string_pay_due_payment', mep_esc_html($_POST['mepp_text_translation_string_pay_due_payment']));
                update_option('mepp_text_translation_string_pay_deposit', mep_esc_html($_POST['mepp_text_translation_string_pay_deposit']));

                if (isset($_POST['mepp_enable_second_payment_reminder'])) {
                    update_option('mepp_enable_second_payment_reminder', $_POST['mepp_enable_second_payment_reminder']);
                } else {
                    update_option('mepp_enable_second_payment_reminder', '');
                }
                if (isset($_POST['mepp_day_before_second_payment_reminder'])) {
                    update_option('mepp_day_before_second_payment_reminder', mep_esc_html($_POST['mepp_day_before_second_payment_reminder']));
                }
                if (isset($_POST['mepp_enable_payment_plan_payment_reminder'])) {
                    update_option('mepp_enable_payment_plan_payment_reminder', $_POST['mepp_enable_payment_plan_payment_reminder']);
                }
                if (isset($_POST['mepp_day_before_payment_plan_reminder'])) {
                    update_option('mepp_day_before_payment_plan_reminder', mep_esc_html($_POST['mepp_day_before_payment_plan_reminder']));
                }
                if (isset($_POST['mepp_payment_plan_email_content'])) {
                    update_option('mepp_payment_plan_email_content', mep_esc_html($_POST['mepp_payment_plan_email_content']));
                }

                if (isset($_POST['mepp_style_partial_option_bgc'])) {
                    update_option('mepp_style_partial_option_bgc', $_POST['mepp_style_partial_option_bgc']);
                } else {
                    update_option('mepp_style_partial_option_bgc', '');
                }
                if (isset($_POST['mepp_style_partial_option_txtc'])) {
                    update_option('mepp_style_partial_option_txtc', $_POST['mepp_style_partial_option_txtc']);
                } else {
                    update_option('mepp_style_partial_option_txtc', '');
                }
                if (isset($_POST['mepp_style_custom_msg_bgc'])) {
                    update_option('mepp_style_custom_msg_bgc', $_POST['mepp_style_custom_msg_bgc']);
                } else {
                    update_option('mepp_style_custom_msg_bgc', '');
                }
                if (isset($_POST['mepp_style_custom_msg_txtc'])) {
                    update_option('mepp_style_custom_msg_txtc', $_POST['mepp_style_custom_msg_txtc']);
                } else {
                    update_option('mepp_style_custom_msg_txtc', '');
                }
                if (isset($_POST['mepp_style_history_table_bgc'])) {
                    update_option('mepp_style_history_table_bgc', $_POST['mepp_style_history_table_bgc']);
                } else {
                    update_option('mepp_style_history_table_bgc', '');
                }
            }

            if (isset($_POST['mepp_setting_save'])) {
                echo '<span id="mepp-setting-saved-notification" class="updated notice is-dismissible"><p><strong>Settings Saved.</strong></p></span>';
                /*
                echo '<script>';
                echo 'setTimeout(function() {document.getElementById("mepp-setting-saved-notification").remove()}, 3000)';
                echo '</script>';
                */
            }

            $checkout_mode = get_option('mepp_partial_enable_for_page');
?>

            <div class="mepp-admin-setting-page">
                <div class="mepp-admin-page-header">
                    <h3><?php echo wcpp_get_plugin_data('Name'); ?><small><?php echo wcpp_get_plugin_data('Version'); ?></small></h3>
                    <?php if ($checkout_mode === 'checkout') : ?>
                        <span id="wcpp_checkout_mode"><?php _e('Checkout Mode Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                    <?php endif; ?>
                </div>
                <div class="mepp-admin-page-content">
                    <?php $this->setting_content(); ?>
                </div>

                <?php if (!wcppe_enable_for_event()) : ?>
                    <div class="mepp-admin-page-promotion">
                        <?php $this->promotion(); ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php
        }

        protected function setting_content()
        {
            global $wp_roles;

            // Payment Methods
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            $enabled_gateways = [];
            if ($gateways) {
                foreach ($gateways as $gateway) {

                    if ($gateway->enabled == 'yes') {

                        $enabled_gateways[$gateway->id] = $gateway->title;
                    }
                }
            }

            // User Roles
            $user_roles = array();
            if ($wp_roles->roles) {
                foreach ($wp_roles->roles as $role) {
                    $user_roles[strtolower($role['name'])] = $role['name'];
                }
            }

            // Get Data
            // $checkout_manual_pay = get_option('meppp_checkout_manual_pay');
            // $order_manual_pay = get_option('meppp_order_manual_pay');
            $quantity_reduce_on = get_option('meppp_quantity_reduce_on') ? get_option('meppp_quantity_reduce_on') : 'full';
            $shop_pay_deposit_btn = get_option('meppp_shop_pay_deposit_btn') ? get_option('meppp_shop_pay_deposit_btn') : 'off';
            $enable_partial_by_default = get_option('mepp_enable_partial_by_default');
            $default_partial_type = get_option('mepp_default_partial_type');
            $default_partial_amount = get_option('mepp_default_partial_amount');
            $partial_enable_for_page = get_option('mepp_partial_enable_for_page', 'product_detail');
            $admin_notify_partial_payment = get_option('mepp_admin_notify_partial_payment');

            $mepp_text_translation_string_pay_deposit = get_option('mepp_text_translation_string_pay_deposit');
            $mepp_text_translation_string_full_payment = get_option('mepp_text_translation_string_full_payment');
            $mepp_text_translation_string_payment_total = get_option('mepp_text_translation_string_payment_total');
            $mepp_text_translation_string_deposit = get_option('mepp_text_translation_string_deposit');
            $mepp_text_translation_string_due_amount = get_option('mepp_text_translation_string_due_amount');
            $mepp_text_translation_string_partially_paid = get_option('mepp_text_translation_string_partially_paid');
            $mepp_text_translation_string_due_payment = get_option('mepp_text_translation_string_due_payment');
            $mepp_text_translation_string_deposit_type = get_option('mepp_text_translation_string_deposit_type');
            $mepp_text_translation_string_to_pay = get_option('mepp_text_translation_string_to_pay');
            $mepp_text_translation_string_pay_due_payment = get_option('mepp_text_translation_string_pay_due_payment');
            $mepp_text_translation_string_pay_deposit = get_option('mepp_text_translation_string_pay_deposit');
            $partial_enable_for_cart_page = get_option('mepp_partial_enable_for_cart_page');
        ?>

            <div class="mepp-tab-container">
                <div class="mepp-tab-menu">
                    <ul class="mepp-ul">
                        <li><a href="#" class="mepp-tab-a active-a" data-id="general"><i class="fas fa-home"></i> <?php _e('General', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></a></li>

                        <li><a href="#" class="mepp-tab-a" data-id="site-wide"><i class="fas fa-globe"></i> <?php _e('Site-wide', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></a></li>
                        <li><a href="#" class="mepp-tab-a" data-id="checkout-mode"><i class="fas fa-shopping-cart"></i> <?php _e('Checkout Mode', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></a></li>

                        <?php do_action('mepp_style_setting_tab'); ?>

                        <li><a href="#" class="mepp-tab-a" data-id="translation"><i class="fas fa-language"></i> <?php _e('Translation', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></a>
                        </li>
                        <?php do_action('mepp_reminder_setting_tab'); ?>
                        <li><a href="#" class="mepp-tab-a" data-id="license"><i class="far fa-id-badge"></i>  <?php _e('License', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></a>
                        </li>
                    </ul>
                </div>
                <!--end of tab-menu-->

                <form action="" method="POST">

                    <div class="mepp-tab mepp-tab-active" data-id="general">
                        <div class="mepp-tab-content">
                            <table>
                                <tr>
                                    <th>
                                        <label for="meppp_quantity_reduce_on"><?php _e('Stock reduce on', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <select name="meppp_quantity_reduce_on" id="meppp_quantity_reduce_on">
                                            <option value="deposit" <?php echo $quantity_reduce_on === 'deposit' ? 'selected' : '' ?>><?php echo __('Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                            <option value="full" <?php echo $quantity_reduce_on === 'full' ? 'selected' : '' ?>><?php echo __('Full Payment', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                        </select>
                                        <span class="mepp-input-desc"><?php _e('Stock reduce on which payment?', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="meppp_shop_pay_deposit_btn"><?php _e('On/Off Pay Deposit button in product list', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <select name="meppp_shop_pay_deposit_btn" id="meppp_shop_pay_deposit_btn">
                                            <option value="off" <?php echo $shop_pay_deposit_btn === 'off' ? 'selected' : '' ?>><?php echo __('Off', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                            <option value="on" <?php echo $shop_pay_deposit_btn === 'on' ? 'selected' : '' ?>><?php echo __('On', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                        </select>
                                        <span class="mepp-input-desc"><?php _e('It enables the pay deposit button in shop product list.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_admin_notify_partial_payment"><?php _e('Admin will get email', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" name="mepp_admin_notify_partial_payment" id="mepp_admin_notify_partial_payment" <?php echo $admin_notify_partial_payment === 'yes' ? 'checked' : '' ?> value="yes">
                                        <span class="mepp-checkbox-label"><?php _e('Admin will get email notification when a customer make a partial payment.', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                                    </td>
                                </tr>
                                <?php do_action('mepp_general_setting_pro'); ?>
                            </table>
                        </div>
                    </div>
                    <!--end of tab one-->

                    <div class="mepp-tab " data-id="site-wide">
                        <div class="mepp-tab-content">
                            <table>
                                <tr>
                                    <th>
                                        <label for="mepp_enable_partial_by_default"><?php _e('Enable Partial By Default', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <select name="mepp_enable_partial_by_default" id="mepp_enable_partial_by_default">
                                            <option value="no" <?php echo $enable_partial_by_default === 'no' ? 'selected' : '' ?>><?php echo __('No', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                            <option value="yes" <?php echo $enable_partial_by_default === 'yes' ? 'selected' : '' ?>><?php echo __('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                        </select>
                                        <span class="mepp-input-desc"><?php _e('If "yes", then deposit partial will be enabled globaly. <br> That means all the products partial enable by default.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_default_partial_type"><?php _e('Default Partial Type', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <select name="mepp_default_partial_type" id="mepp_default_partial_type">
                                            <option value="percent" <?php echo $default_partial_type === 'percent' ? 'selected' : '' ?>><?php _e('Percentage of Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                            <option value="fixed" <?php echo $default_partial_type === 'fixed' ? 'selected' : '' ?>><?php _e('Fixed Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                            <?php do_action('mepp_payment_plan_option') ?>
                                        </select>
                                        <span class="mepp-input-desc"><?php _e('Default deposit type.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                                    </td>
                                </tr>
                                <tr class="mepp-payment-deposit-value">
                                    <th>
                                        <label for="mepp_default_partial_amount"><?php _e('Default Partial Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="mepp_default_partial_amount" id="mepp_default_partial_amount" value="<?php echo $default_partial_amount ?>">
                                        <span class="mepp-input-desc"><?php _e('Default partial value.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                                    </td>
                                </tr>
                                <?php do_action('mepp_default_setting_pro') ?>
                            </table>
                        </div>
                    </div>
                    <!--end of tab two-->

                    <div class="mepp-tab " data-id="checkout-mode">
                        <div class="mepp-tab-content">
                            <table>
                                <tr>
                                    <th>
                                        <label for="mepp_partial_enable_for_page"><?php _e('Enable checkout mode', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <select name="mepp_partial_enable_for_page" id="mepp_partial_enable_for_page">
                                            <option value="product_detail" <?php echo $partial_enable_for_page === 'product_detail' ? 'selected' : '' ?>><?php echo __('No', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                            <?php do_action('mepp_show_partial_option_checkout') ?>
                                        </select>
                                        <span class="mepp-input-desc"><?php _e('If value is "yes" then partial option show on checkout page instead of product page. And deposit configuration will work from site-wide settings', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_partial_enable_for_cart_page"><?php _e('Show Partial Option in Cart page', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td>
                                        <input type="checkbox" name="mepp_partial_enable_for_cart_page" id="mepp_partial_enable_for_cart_page" <?php echo $partial_enable_for_cart_page === 'yes' ? 'checked' : '' ?> value="yes">
                                        <span class="mepp-checkbox-label"><?php _e('Enable it, If you want to show partial option in Cart page.', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                                        <span class="mepp-input-desc"><?php _e('<strong>Note:</strong> This option will work if "Show Partial option" value is checkout.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                                    </td>
                                </tr>

                            </table>
                        </div>
                    </div>

                    <div class="mepp-tab " data-id="translation">
                        <div class="mepp-tab-content">
                            <table>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_pay_deposit"><?php _e('Label for text: Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_pay_deposit" id="mepp_text_translation_string_pay_deposit" value="<?php echo $mepp_text_translation_string_pay_deposit ?: 'Pay Deposit'; ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_full_payment"><?php _e('Label for text: Full Payment', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_full_payment" id="mepp_text_translation_string_full_payment" value="<?php echo $mepp_text_translation_string_full_payment ?: 'Full Payment' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_payment_total"><?php _e('Label for text: Payments Total', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_payment_total" id="mepp_text_translation_string_payment_total" value="<?php echo $mepp_text_translation_string_payment_total ?: 'Payments Total' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_deposit"><?php _e('Label for text: Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_deposit" id="mepp_text_translation_string_deposit" value="<?php echo $mepp_text_translation_string_deposit ?: 'Deposit' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_due_amount"><?php _e('Label for text: Due Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_due_amount" id="mepp_text_translation_string_due_amount" value="<?php echo $mepp_text_translation_string_due_amount ?: 'Due Amount' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_partially_paid"><?php _e('Label for text: Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_partially_paid" id="mepp_text_translation_string_partially_paid" value="<?php echo $mepp_text_translation_string_partially_paid ?: 'Partially Paid' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_due_payment"><?php _e('Label for text: Due Payment', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_due_payment" id="mepp_text_translation_string_due_payment" value="<?php echo $mepp_text_translation_string_due_payment ?: 'Due Payment' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_deposit_type"><?php _e('Label for text: Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_deposit_type" id="mepp_text_translation_string_deposit_type" value="<?php echo $mepp_text_translation_string_deposit_type ?: 'Deposit Type' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_to_pay"><?php _e('Label for text: To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_to_pay" id="mepp_text_translation_string_to_pay" value="<?php echo $mepp_text_translation_string_to_pay ?: 'To Pay' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_pay_due_payment"><?php _e('Label for text: Pay Due Payment', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_pay_due_payment" id="mepp_text_translation_string_pay_due_payment" value="<?php echo $mepp_text_translation_string_pay_due_payment ?: 'Pay Due Payment' ?>">
                                    </td>
                                </tr>
                                <tr>
                                    <th>
                                        <label for="mepp_text_translation_string_pay_deposit"><?php _e('Label for text: Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </th>
                                    <td><input type="text" name="mepp_text_translation_string_pay_deposit" id="mepp_text_translation_string_pay_deposit" value="<?php echo $mepp_text_translation_string_pay_deposit ?: 'Pay Deposit' ?>">
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <!--end of tab three-->


                    <div class="mepp-tab " data-id="reminder">
                        <div class="mepp-tab-content">
                            <table>
                                <?php do_action('mepp_reminder_setting_pro'); ?>
                            </table>
                        </div>
                    </div>
                    <!--end of tab four-->

                    <!-- Style Tab content -->
                    <div class="mepp-tab " data-id="style">
                        <div class="mepp-tab-content">
                            <table>
                                <?php do_action('mepp_style_setting_content'); ?>
                            </table>
                        </div>
                    </div>
                    <!-- Style Tab content END -->


                    <div class="mepp-tab " data-id="license">
                        <div class="mepp-tab-content">
                            <div class='mep-licensing-page'>
                                <h3>Advanced Partial/Deposit Payment For Woocommerce Licensing</h3>
                                <p>Thanks you for using our Advanced Partial/Deposit Payment For Woocommerce plugin. This plugin is free and no license is required. We have some Additional addon to enhace feature of this plugin functionality. If you have any addon you need to enter a valid license for that plugin below. </p>
                                <div class="mep_licensae_info"></div>
                                <table class='wp-list-table widefat striped posts mep-licensing-table'>
                                    <thead>
                                        <tr>
                                            <th>Plugin Name</th>
                                            <th width=5%>Order No</th>
                                            <th width=25%>Expire on</th>
                                            <th width=30%>License Key</th>
                                            <th width=10%>Status</th>
                                            <th width=10%>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php do_action('mepp_license_setting_pro'); ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!--end of tab four-->
                    <?php do_action('mepp_settings_page_content');  ?>
                    <input type="submit" name="mepp_setting_save" value="<?php _e('Save', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>">
                </form>
            </div>
            <!--end of container-->

        <?php
        }

        protected function promotion()
        {
        ?>

            <div class="mepp-promotion-container">
                <h3 class="mepp-promotin-title"><?php _e('Use our Premium Features Now', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></h3>
                <ul class="mepp-promotional-item-container">
                    <li>Payment plans</li>
                    <li>Deposit Minimum Custom Amount</li>
                    <li>Enable deposits option on checkout and Cart Page</li>
                    <li>Payment Email Reminder</li>
                    <li>Force deposit on product page and Checkout page</li>
                    <li>Payment method Restriction on Deposit</li>
                    <li>User role deposit restriction</li>
                    <li>Enable zero payment order as Partial</li>
                    <li>And many more...</li>
                </ul>

                <a class="mepp-upgrade-pro-btn" href="https://mage-people.com/product/advanced-deposit-partial-payment-for-woocommerce-pro/" target="_blank"><?php _e('Upgrade to Pro', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></a>
            </div>

        <?php
        }

        public function partial_order_screen()
        {
            echo '<h1 class="mepp-page-heading">' . __('Partial Order', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</h1>'; // Page Heading

            $partial_orders = $this->partial_order_query(); // Data Query

            $this->partial_order_filter(); // Data Filter

            // Data Output
        ?>
            <div class="mepp-table-container">
                <?php wcpp_get_partial_order_data($partial_orders) ?>
            </div>

        <?php
            mep_modal_html();
        }

        public function partial_order_query()
        {
            $args = array(
                'post_type' => 'shop_order',
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'desc',
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => '_pp_deposit_system',
                        'compare' => 'EXISTS',
                    ),
                    array(
                        'key' => 'due_payment',
                        'value' => 0,
                        'compare' => '>',
                    )
                )
            );

            $data = new WP_Query($args);

            wp_reset_postdata();

            return $data->found_posts > 0 ? $data : null;
        }

        public function partial_order_filter()
        {
            if (!is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) {
                $wcpp_pro_plugin_status = 'disabled';
                $wcpp_pro_feature_label = __(' (Pro Feature)', 'advanced-partial-payment-or-deposit-for-woocommerce');
            } else {
                $wcpp_pro_plugin_status = '';
                $wcpp_pro_feature_label = '';
            }
        ?>
            <div class="mepp-filter-container">
                <form action="">
                    <div class="mepp-form-inner">
                        <div class="mepp-form-group">
                            <label for="filter_order_id"><?php _e('Order No', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                            <input type="text" id="filter_order_id" data-filter-type="order_id" placeholder="#0001">
                        </div>
                        <div class="mepp-form-group">
                            <label for="filter_deposit_type"><?php _e('Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                            <select name="" id="filter_deposit_type" data-filter-type="deposit_type">
                                <option value=""><?php _e('Select Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></option>
                                <option value="percent"><?php _e('Percentage of Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></option>
                                <option value="fixed"><?php _e('Fixed Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></option>
                                <option value="minimum_amount" <?php echo $wcpp_pro_plugin_status; ?>><?php _e('Minimum Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?><?php echo $wcpp_pro_feature_label; ?></option>
                                <option value="payment_plan" <?php echo $wcpp_pro_plugin_status; ?>><?php _e('Payment Plan', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?><?php echo $wcpp_pro_feature_label; ?></option>
                            </select>
                        </div>
                        <div class="mepp-form-group wcpp-filter-loader">
                            <img src="<?php echo WCPP_PLUGIN_URL . 'asset/img/wcpp-loader.gif' ?>" alt="">
                        </div>
                    </div>
                </form>
            </div>

<?php
        }

        public function wcpp_payment_plan_page()
        {
            $wcpp_pro_ad_img_url = WCPP_PLUGIN_URL . "/asset/img/wcpp_pro_discount_ad.png";
            echo '<div class="wcpp_notice_info">';
            echo __('Please install the <strong>Advanced Partial/Deposit Payment For Woocommerce Pro</strong> plugin to get payment plan feature.', 'advanced-partial-payment-or-deposit-for-woocommerce');
            echo '<a href="' . esc_url("https://mage-people.com/product/advanced-deposit-partial-payment-for-woocommerce-pro/") . '" class="wcpp_get_pro_btn">' . esc_html__('GET PRO', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>';
            echo '</div>';
            echo '<div class="wcpp_pro_ad_wrapper">';
            echo '<a href="' . esc_url("https://mage-people.com/product/advanced-deposit-partial-payment-for-woocommerce-pro/") . '">';
            echo '<img src="' . esc_url($wcpp_pro_ad_img_url) . '"/>';
            echo '</a>';
            echo '</div>';
        }
    }

    new Mepp_Admin_Menu;
}
