<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}


$meta_key = 'amount_type';
$amount_type = get_term_meta($plan_id, $meta_key, true);
?>
<li>
    <div class="plan-items">
        <div class="items">
            <label>
                <input data-id="<?php echo $plan_id; ?>" <?php echo $count === 0 ? 'checked' : ''; ?>
                type="radio" class="option-input" value="<?php echo $plan_id; ?>"
                name="<?php echo $product->get_id(); ?>-selected-plan"/>
                <?php echo $payment_plan['name']; ?>
            </label>
        </div>


        <span class="view-details"> <a data-expanded="no"
                data-view-text="<?php esc_html_e('View details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"
                data-hide-text="<?php esc_html_e('Hide details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"
                data-id="<?php echo $plan_id; ?>"
                class="mepp-view-plan-details"><?php esc_html_e('View details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></a></span>
    </div>
    <?php if( $amount_type === 'minimum' ){
        ?>
    <div style="display:none" class="mepp-single-plan plan-details-<?php echo $plan_id; ?>">

        <div>
            <?php esc_html_e($payment_plan['description'], 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
        </div>

        <?php if ($product->get_type() !== 'grouped') { ?>
            <table class="payment-plan-table">
                <tr>
                    <th width="60%"><?php esc_html_e('Payments Total', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                    <td><?php echo wc_price($payment_plan['plan_total']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e($deposit_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                    <td>
<!--                        --><?php //echo wc_price($payment_plan['deposit_amount']); ?>
                        <input type="number" name="mepp_minimum_amount" value="<?php echo esc_attr( $payment_plan['deposit_amount'] )?>">
                    </td>
                </tr>
            </table>
            <table class="payment-plan-table">
                <thead>
                <th width="60%"><?php esc_html_e('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th style="text-align: right;"><?php esc_html_e('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                </thead>
                <tbody>
                <?php
                $payment_timestamp = current_time('timestamp');
                foreach ($payment_plan['details']['payment-plan'] as $plan_line) {

                    if (isset($plan_line['date']) && !empty($plan_line['date'])) {
                        $payment_timestamp = strtotime($plan_line['date']);
                    } else {
                        $after = $plan_line['after'];
                        $after_term = $plan_line['after-term'];
                        $payment_timestamp = strtotime(date('Y-m-d', $payment_timestamp) . "+{$plan_line['after']} {$plan_line['after-term']}s");
                    }

                    ?>
                    <tr>
                        <td style="padding: 5px;"><?php echo date_i18n(get_option('date_format'), $payment_timestamp) ?></td>
                        <td><?php echo wc_price($plan_line['line_amount']); ?></td>
                    </tr>
                    <?php


                }

                ?>
                </tbody>
            </table>
        <?php } ?>
    </div>
    <?php }else{?>
        <div style="display:none" class="mepp-single-plan plan-details-<?php echo $plan_id; ?>">

            <div>
                <?php esc_html_e($payment_plan['description'], 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
            </div>

            <?php if ($product->get_type() !== 'grouped') { ?>
                <table class="payment-plan-table">
                    <tr>
                        <th width="60%"><?php esc_html_e('Payments Total', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                        <td><?php echo wc_price($payment_plan['plan_total']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e($deposit_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                        <td><?php echo wc_price($payment_plan['deposit_amount']); ?></td>
                    </tr>
                </table>
                <table class="payment-plan-table">
                    <thead>
                    <th width="60%"><?php esc_html_e('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    <th style="text-align: right;"><?php esc_html_e('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                    </thead>
                    <tbody>
                    <?php
                    $payment_timestamp = current_time('timestamp');
                    foreach ($payment_plan['details']['payment-plan'] as $plan_line) {

                        if (isset($plan_line['date']) && !empty($plan_line['date'])) {
                            $payment_timestamp = strtotime($plan_line['date']);
                        } else {
                            $after = $plan_line['after'];
                            $after_term = $plan_line['after-term'];
                            $payment_timestamp = strtotime(date('Y-m-d', $payment_timestamp) . "+{$plan_line['after']} {$plan_line['after-term']}s");
                        }

                        ?>
                        <tr>
                            <td style="padding: 5px;"><?php echo date_i18n(get_option('date_format'), $payment_timestamp) ?></td>
                            <td><?php echo wc_price($plan_line['line_amount']); ?></td>
                        </tr>
                        <?php


                    }

                    ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    <?php }?>
</li>