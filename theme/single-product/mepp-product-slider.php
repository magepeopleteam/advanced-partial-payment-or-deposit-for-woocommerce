<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

do_action('mepp_enqueue_product_scripts');
if ($force_deposit === 'yes') $default_checked = 'deposit';
$hide = get_option('mepp_hide_ui_when_forced', 'no') === 'yes';
$storewide_deposit_enabled_details = get_option('mepp_storewide_deposit_enabled_details', 'yes');

?>
<div data-ajax-refresh="<?php echo $ajax_refresh; ?>" data-product_id="<?php echo $product->get_id(); ?>" class='magepeople_mepp_single_deposit_form <?php echo $basic_buttons ? 'basic-wc-deposits-options-form' : 'wc-deposits-options-form'; ?>'>
    <?php
    if ($storewide_deposit_enabled_details !== 'no') {
        if (!$has_payment_plans && $product->get_type() !== 'grouped') {
            // Check if deposit type is minimum_amount
            if ($product->get_meta('_mepp_amount_type', true) === 'minimum_amount') {
                $minimum_amount = $product->get_meta('_mepp_deposit_amount', true);
                if (!empty($minimum_amount)) : ?>
                    <div class="deposit-option">
                        <h2 for="deposit-amount"><?php esc_html_e('Minimum Deposit Amount:', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></h2>
                        <span id="deposit-amount"><?php echo wc_price($minimum_amount); ?></span>
                    </div>
                    <div class="deposit-option">
                        <label for="custom-deposit-amount"><?php esc_html_e('Enter Custom Deposit Amount:', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></label>
                        <input type="number" id="custom-deposit-amount" name="custom-deposit-amount" min="<?php echo $minimum_amount; ?>" step="0.01" required>
                    </div>
                <?php endif;
            } else { ?>
                <h4 class='deposit-option'>
                    <!-- <?php esc_html_e($deposit_option_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                    <br> -->
                    <?php esc_html_e('Deposit Amount :', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                    <?php if ($product->get_type() === 'variable' && $deposit_info['type'] === 'percent') { ?>
                        <span id='deposit-amount'><?php echo $deposit_amount . '%'; ?></span>
                    <?php } else { ?>
                        <span id='deposit-amount'><?php echo wc_price($deposit_amount); ?></span>
                    <?php } ?>
                    <span id='deposit-suffix'><?php echo $suffix; ?></span>
                </h4>
            <?php }
        }
    }
    ?>
<div class="<?php echo $hide ? 'mepp_hidden ' : '' ?><?php echo $basic_buttons ? 'basic-switch-woocommerce-deposits' : 'deposit-options switch-toggle switch-candy switch-woocommerce-deposits'; ?>" style="padding: 20px;  width: 100%;">
    <input id='<?php echo $product->get_id(); ?>-pay-deposit' class='pay-deposit input-radio' name='<?php echo $product->get_id(); ?>-deposit-radio'
           type='radio' <?php checked($default_checked, 'deposit'); ?> value='deposit'>
    <label class="pay-deposit-label" for='<?php echo $product->get_id(); ?>-pay-deposit'>
        <?php esc_html_e($deposit_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
    </label>
    <input id='<?php echo $product->get_id(); ?>-pay-full-amount' class='pay-full-amount input-radio' name='<?php echo $product->get_id(); ?>-deposit-radio' type='radio' <?php checked($default_checked, 'full'); ?>
           <?php echo isset($force_deposit) && $force_deposit === 'yes' ? 'disabled' : ''?> value="full">
    <label class="pay-full-amount-label" for='<?php echo $product->get_id(); ?>-pay-full-amount'>
        <?php esc_html_e($full_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
    </label>
    <a class='wc-deposits-switcher'></a>
</div>

    <span class='deposit-message wc-deposits-notice'></span>
    <?php
    if ($has_payment_plans) {

        ?>
        <div class="mepp-payment-plans">
            <fieldset>
                <ul>
                    <?php
                    $count = 0;
                    foreach ($payment_plans as $plan_id => $payment_plan) {
                         wc_get_template('single-product/mepp-product-single-plan.php',
                                array('count' => $count,
                                    'plan_id' => $plan_id,
                                    'deposit_text' => $deposit_text,
                                    'payment_plan' => $payment_plan,
                                    'product' => $product),
                                '', MEPP_TEMPLATE_PATH);
                        $count++;
                    } ?>
                </ul>

            </fieldset>
        </div>
        <?php
    }
    ?>

</div>

<script>
    jQuery(document).ready(function($) {
        // Hide deposit-option initially if pay-full-amount is checked
        if ($('#<?php echo $product->get_id(); ?>-pay-full-amount').is(':checked')) {
            $('.deposit-option').hide();
        }

        // Toggle deposit-option visibility on radio button change
        $('input[name="<?php echo $product->get_id(); ?>-deposit-radio"]').change(function() {
            if ($(this).val() === 'full') {
                $('.deposit-option').hide();
            } else {
                $('.deposit-option').show();
            }
        });
    });
</script>
