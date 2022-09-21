<?php
/**
 *  Author: MagePeople Team
 *  Developer: Ariful
 *  Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WCPP_Pro_Settings')) {
    class WCPP_Pro_Settings{
        public function __construct()
        {
            add_action('mepp_general_setting_pro', [$this, 'mepp_general_setting_pro'], 10);
    
            add_action('mepp_default_setting_pro', [$this, 'mepp_default_setting_pro'], 10);
    
            add_action('mepp_reminder_setting_tab', [$this, 'mepp_reminder_setting_tab'], 10);
            add_action('mepp_reminder_setting_pro', [$this, 'mepp_reminder_setting_pro'], 10);
    
            add_action('mepp_payment_plan_option', [$this, 'mepp_payment_plan_option_pro'], 10);
            add_action('mepp_show_partial_option_checkout', [$this, 'mepp_show_partial_option_checkout_pro'], 10);
        }
    
        public function mepp_show_partial_option_checkout_pro()
        {
            ?>
            <option value="" disabled><?php echo __('Checkout (Pro Feature)', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
            <?php
        }
    
        public function mepp_payment_plan_option_pro()
        {
            $default_partial_type = get_option('mepp_default_partial_type');
            ?>
            <option value="" disabled><?php echo __('Minimum Amount (Pro Feature)', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
            <option value="payment_plan" disabled><?php echo __('Payment Plan (Pro Feature)', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
            <?php
        }
    
        public function mepp_default_setting_pro()
        {
            $all_plans = array(
                '3-months-installment' => '3 Months Installment',
                '6-months-installment' => '6 Months Installment',
            );
            ?>
            <tr class="mepp-payment-plan-sett">
                <th>
                    <label for="mepp_default_payment_plan" class="wcpp_text_disabled"><?php _e('Default Payment Plans', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <select name="mepp_default_payment_plan[]" id="mepp_default_payment_plan" multiple disabled>
                        <?php if ($all_plans) : foreach ($all_plans as $id => $plan) : ?>
                            <option value=""><?php echo $plan; ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('Default payment plan(s).', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="mepp_partial_enable_for_cart_page" class="wcpp_text_disabled"><?php _e('Show Partial Option in Cart page', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <input type="checkbox" id="mepp_partial_enable_for_cart_page" disabled>
                    <span class="mepp-checkbox-label wcpp_text_disabled"><?php _e('Enable it, If you want to show partial option in Cart page.', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('<strong>Note:</strong> This option will work if "Show Partial option" value is checkout.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                </td>
            </tr>
    
            <?php
        }
    
        public function mepp_reminder_setting_tab()
        {
            ?>
    
            <li><a href="#" class="mepp-tab-a"
                   data-id="reminder"><?php _e('Email Reminder', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></a>
            </li>
    
            <?php
        }
    
        public function mepp_reminder_setting_pro()
        {
            ?>
    
            <tr>
                <th><label for="mepp_enable_second_payment_reminder" class="wcpp_text_disabled"><?php _e('Enable Second Payment Reminder after "X" Days', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <input type="checkbox"
                           id="mepp_enable_second_payment_reminder" disabled>
                    <span class="mepp-checkbox-label wcpp_text_disabled"><?php _e('Check this to enable if you want to send reminder email after "X" Days', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="mepp_day_before_second_payment_reminder" class="wcpp_text_disabled"><?php _e('Days after Last payment reminder', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <input type="text" id="mepp_day_before_second_payment_reminder" disabled>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('Duration between partial payment and next payment (in days)', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                </td>
            </tr>
            <tr>
                <th><label for="mepp_enable_payment_plan_payment_reminder" class="wcpp_text_disabled"><?php _e('Enable Payment plan reminder', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label></th>
                <td>
                    <input type="checkbox" id="mepp_enable_payment_plan_payment_reminder" disabled>
                    <span class="mepp-checkbox-label wcpp_text_disabled"><?php _e('Check this to enable if you want to send reminder email after "X" Days', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="mepp_day_before_payment_plan_reminder" class="wcpp_text_disabled"><?php _e('Days before Payment plan reminder', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <input type="text" id="mepp_day_before_payment_plan_reminder" disabled>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('Duration between partial payment and next payment (in days)', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="mepp_payment_plan_email_content" class="wcpp_text_disabled"><?php _e('Reminder content', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <textarea id="mepp_payment_plan_email_content" cols="30"
                              rows="5" disabled></textarea>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('Reminder content', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                </td>
            </tr>
    
            <?php
        }
    
        public function mepp_general_setting_pro()
        {
            $user_roles = array(
                'administrator' => 'Administrator',
                'editor'        => 'Editor',
                'author'        => 'Author',
                'contributor'   => 'Contributor',
                'subscriber'    => 'Subscriber',
                'customer'      => 'Customer',
                'shop-manager'  => 'Shop manager',             
            );

            $enabled_gateways = array(
                'woocommerce-payments'  => 'WooCommerce Payments',
                'direct-bank-transfer'  => 'Direct bank transfer',
                'check-payments'        => 'Check payments',
                'cash-on-delivery'      => 'Cash on delivery',
                'stripe'                => 'Stripe',
                'paypal'                => 'Paypal',
            );
            ?>
            <tr>
                <th>
                    <label for="meppp_force_partial_payment" class="wcpp_text_disabled"><?php _e('Force Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <select id="meppp_force_partial_payment" disabled>
                        <option><?php _e('No', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                        <option><?php _e('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                    </select>
                </td>
            </tr>
    
            <tr>
                <th>
                    <label for="meppp_checkout_zero_price" class="wcpp_text_disabled"><?php _e('Allow Zero Price Checkout?', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <input type="checkbox"
                           id="meppp_checkout_zero_price" disabled>
                    <span class="mepp-checkbox-label wcpp_text_disabled"><?php _e('If you want to allow to user can make order with zero price', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                </td>
            </tr>
    
            <tr>
                <th>
                    <label for="mepp_deposit_custom_message" class="wcpp_text_disabled"><?php _e('Custom Message about deposit type', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <input type="text"
                           id="mepp_deposit_custom_message"
                           value="You can Order this product with deposit" disabled>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('Custom message show in product page about deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></span>
                </td>
            </tr>
    
            <tr>
                <th>
                    <label for="meppp_payment_methods_allow" class="wcpp_text_disabled"><?php _e('Allow Payment Methods for Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <select id="meppp_payment_methods_allow" multiple="multiple" disabled>
                        <?php foreach ($enabled_gateways as $key => $payment) :
                            echo '<option>' . $payment . '</option>';
                        endforeach; ?>
                    </select>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('Selected payment methods only allow on partial payment.<br>Default: All allowed', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="meppp_user_roles_allow" class="wcpp_text_disabled"><?php _e('Allow User Roles for Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce') ?><span class="wcpp_pro_text"><?php _e('Pro', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></label>
                </th>
                <td>
                    <select name="meppp_user_roles_allow[]" id="meppp_user_roles_allow" multiple="multiple" disabled>
                        <?php foreach ($user_roles as $key => $role) :
                            echo '<option>' . $role . '</option>';
                        endforeach; ?>
                    </select>
                    <span class="mepp-input-desc wcpp_text_disabled"><?php _e('Selected user roles only allow for partial payment.<br>Default: All allowed', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span>
                </td>
            </tr>
            <?php
        }
    }
    if (! is_plugin_active( 'mage-partial-payment-pro/mage_partial_pro.php' ) ) {
        new WCPP_Pro_Settings();
    }   
}