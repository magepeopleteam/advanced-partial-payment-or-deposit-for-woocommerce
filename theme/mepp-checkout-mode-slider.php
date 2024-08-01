<?php
$hide = get_option('mepp_hide_ui_when_forced','no') === 'yes';

?>

<tr  class="deposit-buttons">
    <td colspan="2">
        <div id='<?php echo $basic_buttons ? 'basic-wc-deposits-options-form' : 'wc-deposits-options-form'; ?>'>

            <div class="<?php echo $hide? 'mepp_hidden ':'' ?>  <?php echo $basic_buttons ? 'basic-switch-Advanced Partial Payment and Deposit For Woocommerce' : 'deposit-options switch-toggle switch-candy switch-Advanced Partial Payment and Deposit For Woocommerce'; ?>">
                <input id='pay-deposit' name='deposit-radio'
                       type='radio' <?php echo checked($default_checked, 'deposit'); ?> class='input-radio'
                       value='deposit'>
                <label id="pay-deposit-label"
                       for='pay-deposit'><?php echo esc_html__($deposit_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></label>
                <?php if ($basic_buttons) { ?> <br/> <?php } ?>
                <?php if (isset($force_deposit) && $force_deposit === 'yes') { ?>
                    <input id='pay-full-amount' name='deposit-radio' type='radio'
                           class='input-radio'
                           disabled>
                    <label id="pay-full-amount-label" for='pay-full-amount'
                           onclick=''><?php echo esc_html__($full_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></label>
                <?php } else { ?>
                    <input id='pay-full-amount' name='deposit-radio'
                           type='radio' <?php echo checked($default_checked, 'full');; ?> class='input-radio'
                           value='full'>
                    <label id="pay-full-amount-label" for='pay-full-amount'
                           onclick=''><?php echo esc_html__($full_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></label>
                <?php } ?>
                <a class='wc-deposits-switcher'></a>
            </div>
            <span class='deposit-message' id='wc-deposits-notice'></span>

        </div>

        <?php if ($has_payment_plan && $default_checked === 'deposit') { ?>
            <div id="mepp-payment-plans">
                <fieldset>
                    <ul>
                        <?php

                        foreach ($payment_plans as $plan_id => $payment_plan) {
                            //if no plan selected , set first plan as selected
                            if (empty($selected_plan)) $selected_plan = $plan_id;
                            ?>
                            <li>

                                <strong>
                                    <input data-id="<?php echo $plan_id; ?>" <?php checked($selected_plan, $plan_id); ?>
                                           type="radio" class="option-input radio" value="<?php echo $plan_id; ?>"
                                           name="mepp-selected-plan"/>
                                    <?php echo $payment_plan['name']; ?>
                                    <?php
                                    if ($selected_plan == $plan_id) {
                                        //display plan details
                                        $display_plan  = WC()->cart->deposit_info['payment_schedule'];
                                        ?>
                                        <span> <a data-expanded="no"
                                                  data-view-text="<?php echo esc_html__('View details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"
                                                  data-hide-text="<?php echo esc_html__('Hide details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"
                                                  data-id="<?php echo $plan_id; ?>"
                                                  class="mepp-view-plan-details"><?php echo esc_html__('View details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></a>
                                        </span>
                                        <div style="display:none" class="mepp-single-plan"
                                             id="plan-details-<?php echo $plan_id; ?>">

                                            <?php
                                            $payment_timestamp = current_time('timestamp');
                                            foreach ($display_plan as $payment_timestamp => $plan_line) {
                                                if(isset($plan_line['timestamp'])) $payment_timestamp = $plan_line['timestamp'];
                                                echo '<span>' . wc_price($plan_line['total']) . ' ' . date_i18n(get_option('date_format'), $payment_timestamp) . '</span><br/>';
                                            }


                                            ?>
                                        </div>
                                        <?php

                                    }
                                    ?>


                                </strong>
                            </li>
                        <?php } ?>
                    </ul>
                </fieldset>
            </div>
        <?php } ?>


    </td>
</tr>
