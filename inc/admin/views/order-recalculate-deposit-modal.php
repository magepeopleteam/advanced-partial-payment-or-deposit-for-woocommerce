<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * @var WC_Order $order
 */
if ($order && $order->get_type() !== 'mepp_payment') {

    $payment_plans = get_terms(array(
            'taxonomy' => MEPP_PAYMENT_PLAN_TAXONOMY,
            'hide_empty' => false
        )
    );

    $handling_options = array(
        'deposit' => esc_html__('with deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'split' => esc_html__('Split according to deposit amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'full' => esc_html__('with future payment(s)', 'advanced-partial-payment-or-deposit-for-woocommerce')
    );
    $discount_handling_options =  array(
        'deposit' => esc_html__('Deduct from deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'split' => esc_html__('Split according to deposit amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        'second_payment' => esc_html__('Deduct from future payment(s)', 'advanced-partial-payment-or-deposit-for-woocommerce')
    );

    $all_plans = array();
    foreach ($payment_plans as $payment_plan) {
        $all_plans[$payment_plan->term_id] = $payment_plan->name;
    }
    ?>
    <div class="wc-backbone-modal mepp-recalculate-deposit-modal">
        <div class="wc-backbone-modal-content">

            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1><?php echo esc_html__('Recalculate Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></h1>
                    <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text">Close modal panel</span>
                    </button>
                </header>
                <article>
                    <?php if (mepp_checkout_mode()) {

                        $deposit_enabled = get_option('mepp_checkout_mode_enabled');
                        $deposit_amount = get_option('mepp_checkout_mode_deposit_amount');
                        $amount_type = get_option('mepp_checkout_mode_deposit_amount_type');

                        ?>
                        <form id="mepp-modal-recalculate-form" action="" method="post">
                            <table class="widefat">
                                <thead>

                                <tr>
                                    <th><?php echo esc_html__('Enable Deposit', 'woocommerce'); ?></th>
                                    <th><?php echo esc_html__('Amount Type', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                                    <th><?php echo esc_html__('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr class="mepp_calculator_modal_row">
                                    <td><label>
                                            <input <?php echo $deposit_enabled ? 'checked="checked"' : ''; ?> value="yes"
                                                                                                                  name="mepp_deposit_enabled_checkout_mode"
                                                                                                                  class="mepp_enable_deposit"
                                                                                                                  type="checkbox"/>
                                        </label>
                                    </td>
                                    <td>
                                        <label>
                                            <select class="widefat mepp_deposit_amount_type"
                                                    name="mepp_deposit_amount_type_checkout_mode" <?php echo $deposit_enabled ? '' : 'disabled'; ?> >
                                                <option <?php selected('fixed', $amount_type); ?>
                                                        value="fixed"><?php echo esc_html__('Fixed', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                                <option <?php selected('percentage', $amount_type); ?>
                                                        value="percentage"><?php echo esc_html__('Percentage', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                                <option <?php selected('payment_plan', $amount_type); ?>
                                                        value="payment_plan"><?php echo esc_html__('Payment plan', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                            </select>
                                        </label>
                                    </td>
                                    <td style="min-width: 250px;">
                                        <label>
                                            <input name="mepp_deposit_amount_checkout_mode" <?php echo $deposit_enabled ? '' : 'disabled'; ?>
                                                   type="number" value="<?php echo $deposit_amount; ?>"
                                                   class="widefat mepp_deposit_amount <?php echo $amount_type === 'payment_plan' ? ' mepp-hidden' : ''; ?>"/>
                                        </label>
                                        <label>
                                            <select <?php echo $deposit_enabled ? '' : 'disabled'; ?>
                                                    class="<?php echo $amount_type === 'payment_plan' ? '' : 'mepp-hidden'; ?> mepp_payment_plan"
                                                    name="mepp_payment_plan_checkout_mode">  <?php
                                                foreach ($all_plans as $key => $plan) {
                                                    ?>
                                                    <option value="<?php echo $key; ?>"><?php echo $plan; ?></option><?php
                                                }
                                                ?>
                                            </select>
                                        </label>
                                    </td>
                                </tr>
                                </tbody>
                                <tfoot>
                                <?php
                                $fees_handling = get_option('mepp_fees_handling','split');
                                $taxes_handling = get_option('mepp_taxes_handling','split');
                                $shipping_handling = get_option('mepp_shipping_handling','split');
                                $shipping_taxes_handling = get_option('mepp_shipping_taxes_handling','split');
                                $discount_from_deposit = get_option('mepp_coupons_handling', 'second_payment');

                                ?>
                                <tr>
                                    <td colspan="3" style=" padding:30px 0 0 0; "><h3
                                                style="margin-bottom: 3px;"><?php echo esc_html__('Additional Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></h3>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-left:0;" colspan="2" >
                                        <label for="mepp_fees_handling"><?php echo esc_html__('Fees Collection Method', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_fees_handling">
                                                    <?php
                                                    foreach ($handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option <?php selected($fees_handling,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>
                                <tr>
                                    <td style="padding-left:0;" colspan="2"><label
                                                for="mepp_taxes_handling"><?php echo esc_html__('Taxes Collection Method', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_taxes_handling">
                                                    <?php
                                                    foreach ($handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option  <?php selected($taxes_handling,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>
                                <tr>
                                    <td style="padding-left:0;" colspan="2"><label
                                                for="mepp_shipping_handling"><?php echo esc_html__('Shipping Handling Method', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_shipping_handling">
                                                    <?php
                                                    foreach ($handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option <?php selected($shipping_handling,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>


                                <tr>
                                    <td style="padding-left:0;" colspan="2"><label
                                                for="mepp_coupons_handling"><?php echo esc_html__('Discount Coupons Handling', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_coupons_handling">
                                                    <?php
                                                    foreach ($discount_handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option <?php selected($discount_from_deposit,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>

                                </tfoot>

                            </table>
                        </form>
                        <?php
                    } else {
                        ?>
                        <form id="mepp-modal-recalculate-form" action="" method="post">
                            <table class="widefat">
                                <thead>

                                <tr>
                                    <th><?php echo esc_html__('Enable Deposit', 'woocommerce'); ?></th>
                                    <th><?php echo esc_html__('Order Item', 'woocommerce'); ?></th>
                                    <th><?php echo esc_html__('Amount Type', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                                    <th><?php esc_html_e('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                                </tr>
                                </thead>
                                <tbody>

                                <?php
                                foreach ($order->get_items() as $order_Item) {
                                    $item_data = $order_Item->get_meta('wc_deposit_meta', true);

                                    $deposit_enabled = is_array($item_data) && isset($item_data['enable']) && $item_data['enable'] === 'yes';
                                    $product = $order_Item->get_product();
                                    $amount_type = is_array($item_data) && isset($item_data['deposit']) ? 'fixed' : mepp_get_product_deposit_amount_type($product->get_id());
                                    $deposit_amount = is_array($item_data) && isset($item_data['deposit']) ? $item_data['deposit'] : mepp_get_product_deposit_amount($product->get_id());
                                    if(wc_prices_include_tax() && is_array($item_data) && isset($item_data['tax'])){
                                        $deposit_amount += $item_data['tax'];
                                    }
                                    $deposit_amount = round($deposit_amount,wc_get_price_decimals());

                                    ?>
                                    <tr class="mepp_calculator_modal_row">

                                        <td><label>
                                                <input <?php echo $deposit_enabled ? 'checked="checked"' : ''; ?>
                                                            value="yes"
                                                            name="mepp_deposit_enabled_<?php echo $order_Item->get_id() ?>"
                                                            class="mepp_enable_deposit"
                                                            type="checkbox"/>
                                            </label>
                                        </td>
                                        <td><?php echo $order_Item->get_name(); ?></td>
                                        <td>
                                            <label>
                                                <select class="widefat mepp_deposit_amount_type"
                                                        name="mepp_deposit_amount_type_<?php echo $order_Item->get_id() ?>" <?php echo $deposit_enabled ? '' : 'disabled'; ?> >
                                                    <option <?php selected('fixed', $amount_type); ?>
                                                            value="fixed"><?php esc_html_e('Fixed', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                                    <option <?php selected('percent', $amount_type); ?>
                                                            value="percentage"><?php esc_html_e('Percentage', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                                    <option <?php selected('payment_plan', $amount_type); ?>
                                                            value="payment_plan"><?php esc_html_e('Payment plan', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                                                </select>
                                            </label>
                                        </td>
                                        <td style="min-width: 250px;">
                                            <label>
                                                <input name="mepp_deposit_amount_<?php echo $order_Item->get_id() ?>" <?php echo $deposit_enabled ? '' : 'disabled'; ?>
                                                       type="number" value="<?php echo $deposit_amount; ?>"
                                                       class="widefat mepp_deposit_amount <?php echo $amount_type === 'payment_plan' ? ' mepp-hidden' : ''; ?>"/>
                                            </label>
                                            <label>
                                                <select <?php echo $deposit_enabled ? '' : 'disabled'; ?>
                                                        class="widefat <?php echo $amount_type === 'payment_plan' ? '' : 'mepp-hidden'; ?> mepp_payment_plan"
                                                        name="mepp_payment_plan_<?php echo $order_Item->get_id() ?>">  <?php
                                                    foreach ($all_plans as $key => $plan) {
                                                        ?>
                                                        <option
                                                        value="<?php echo $key; ?>"><?php echo $plan; ?></option><?php
                                                    }
                                                    ?>
                                                </select>
                                            </label>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                                </tbody>
                                <tfoot>
                                <?php

                                $fees_handling = get_option('mepp_fees_handling','split');
                                $taxes_handling = get_option('mepp_taxes_handling','split');
                                $shipping_handling = get_option('mepp_shipping_handling','split');
                                $shipping_taxes_handling = get_option('mepp_shipping_taxes_handling','split');
                                $discount_from_deposit = get_option('mepp_coupons_handling', 'second_payment');

                                ?>
                                <tr>
                                    <td colspan="4" style=" padding:30px 0 0 0; "><h3
                                                style="margin-bottom: 3px;"><?php esc_html_e('Additional Settings', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></h3>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-left:0;" colspan="3" >
                                        <label for="mepp_fees_handling"><?php esc_html_e('Fees Collection Method', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_fees_handling">
                                                    <?php
                                                    foreach ($handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option <?php selected($fees_handling,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>
                                <tr>
                                    <td style="padding-left:0;" colspan="3"><label
                                                for="mepp_taxes_handling"><?php esc_html_e('Taxes Collection Method', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_taxes_handling">
                                                    <?php
                                                    foreach ($handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option  <?php selected($taxes_handling,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>
                                <tr>
                                    <td style="padding-left:0;" colspan="3"><label
                                                for="mepp_shipping_handling"><?php esc_html_e('Shipping Handling Method', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_shipping_handling">
                                                    <?php
                                                    foreach ($handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option <?php selected($shipping_handling,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>

                                <tr>
                                    <td style="padding-left:0;" colspan="3"><label
                                                for="mepp_coupons_handling"><?php esc_html_e('Discount Coupons Handling', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></label>
                                    </td>
                                    <td><label>
                                            <select name="mepp_coupons_handling">
                                                    <?php
                                                    foreach ($discount_handling_options as $handling_key => $handling_option) {
                                                        ?>
                                                        <option <?php selected($discount_from_deposit,$handling_key); ?> value="<?php echo $handling_key ?>"> <?php echo $handling_option ?> </option> <?php
                                                    }
                                                    ?>
                                                </select>
                                        </label></td>
                                </tr>

                                </tfoot>

                            </table>
                        </form>
                        <?php
                    } ?>
                </article>
                <footer>
                    <div class="inner">
                        <button id="remove_deposit_data" class=" remove_deposit_data submitdelete button button-secondary button-large"><?php esc_html_e('Remove order deposit data', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></button>
                        <button id="btn-ok" class="button button-primary button-large"><?php esc_html_e('Save', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></button>
                    </div>
                </footer>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop"></div>
    <?php
}
