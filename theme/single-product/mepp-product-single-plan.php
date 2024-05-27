<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

?>
<li>
    <input data-id="<?php echo $plan_id; ?>" <?php echo $count === 0 ? 'checked' : ''; ?>
           type="radio" class="option-input" value="<?php echo $plan_id; ?>"
           name="<?php echo $product->get_id(); ?>-selected-plan"/>
    <label><?php echo $payment_plan['name']; ?></label>


    <span> <a data-expanded="no"
              data-view-text="<?php esc_html_e('View details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"
              data-hide-text="<?php esc_html_e('Hide details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"
              data-id="<?php echo $plan_id; ?>"
              class="mepp-view-plan-details"><?php esc_html_e('View details', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></a></span>

    <div style="display:none" class="mepp-single-plan plan-details-<?php echo $plan_id; ?>"
    >


        <div>
            <p><?php esc_html_e($payment_plan['description'], 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></p>
        </div>

        <?php if ($product->get_type() !== 'grouped') { ?>


            <div>
                <p><strong><?php esc_html_e('Payments Total', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                        : <?php echo wc_price($payment_plan['plan_total']); ?></strong></p>
            </div>

            <div>
                <p><?php esc_html_e($deposit_text, 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                    : <?php echo wc_price($payment_plan['deposit_amount']); ?></p>
            </div>

            <table class="payment-plan-table">
                <thead>
                <th><?php esc_html_e('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
                <th><?php esc_html_e('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></th>
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
                        <td><?php echo date_i18n(get_option('date_format'), $payment_timestamp) ?></td>
                        <td><?php echo wc_price($plan_line['line_amount']); ?></td>
                    </tr>
                    <?php


                }

                ?>
                </tbody>
            </table>
        <?php } ?>
    </div>
</li>