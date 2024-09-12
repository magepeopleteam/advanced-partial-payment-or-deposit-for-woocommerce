<?php
$hide = get_option('mepp_hide_ui_when_forced','no') === 'yes';

?>

<tr  class="checkout-deposit-buttons">
    <td colspan="2">
        <div id='<?php echo $basic_buttons ? 'basic-wc-deposits-options-form' : 'wc-deposits-options-form'; ?>'>

            <div class="<?php echo $hide? 'mepp_hidden ':'' ?>  <?php echo $basic_buttons ? 'basic-switch-Advanced' : 'deposit-options switch-toggle switch-candy switch-Advanced'; ?>">
                <label id="pay-deposit-label" class="basic-style" for='pay-deposit'><?php echo esc_html__($deposit_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                    <input id='pay-deposit' name='deposit-radio'
                        type='radio' <?php echo checked($default_checked, 'deposit'); ?> class='input-radio'
                        value='deposit'>
                        <span class="radio-btn"></span>
                </label>
                
                <?php if (isset($force_deposit) && $force_deposit === 'yes') { ?>
                    <label id="pay-full-amount-label" for='pay-full-amount' onclick=''><?php echo esc_html__($full_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                        <input id='pay-full-amount' name='deposit-radio' type='radio'
                            class='input-radio'
                            disabled>
                        <span class="radio-btn"></span>
                    </label>
                <?php } else { ?>
                    <label id="pay-full-amount-label"  class="basic-style" for='pay-full-amount' onclick=''><?php echo esc_html__($full_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                        <input id='pay-full-amount' name='deposit-radio'
                            type='radio' <?php echo checked($default_checked, 'full');; ?> class='input-radio'
                            value='full'>
                            <span class="radio-btn"></span>
                    </label>
                <?php } ?>
                <a class='wc-deposits-switcher'></a>
            </div>
            <span class='deposit-message' id='wc-deposits-notice'></span>
        </div>
        <?php if ($has_payment_plan && $default_checked === 'deposit') { ?>
        <div id="mepp-payment-plans">
            <h2><?php _e('Payment Plan','advanced-partial-payment-or-deposit-for-woocommerce') ?></h2>
            
            <ul class="mepp-payment-plans">
                <?php
                foreach ($payment_plans as $plan_id => $payment_plan) {
                    //if no plan selected , set first plan as selected
                    if (empty($selected_plan)) $selected_plan = $plan_id;
                    ?>
                    <li class="payment-plan-items">
                        <label class="basic-style">
                            <input data-id="<?php echo $plan_id; ?>" <?php checked($selected_plan, $plan_id); ?>
                            type="radio" class="option-input radio" value="<?php echo $plan_id; ?>"
                            name="mepp-selected-plan"/>
                            <span class="radio-btn"></span>
                            <?php echo $payment_plan['name']; ?>
                        </label>
                        <?php
                        if ($selected_plan == $plan_id) {
                            $display_plan  = WC()->cart->deposit_info['payment_schedule'];
                            ?>
                            <ul class="mepp-single-plan" id="plan-details-<?php echo $plan_id; ?>">
                                <?php
                                $payment_timestamp = current_time('timestamp');
                                foreach ($display_plan as $payment_timestamp => $plan_line) {
                                    if(isset($plan_line['timestamp'])) $payment_timestamp = $plan_line['timestamp'];
                                    echo '<li>' . wc_price($plan_line['total']) . ' ' . date_i18n(get_option('date_format'), $payment_timestamp) . '</li>';
                                }
                                ?>
                            </ul>
                            <?php
                        }
                        ?>
                    </li>
                <?php } ?>
            </ul>
            
        </div>
        <?php } ?>
    </td>
</tr>
