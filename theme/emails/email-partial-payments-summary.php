<?php
/**
 *  Email Original  Order details Summary
 * This template displays a summary of original order details
 */



if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$text_align = is_rtl() ? 'right' : 'left';
?>
<h2><?php esc_html_e('Partial Payments Summary','advanced-partial-payment-or-deposit-for-woocommerce'); ?></h2>

<div style="margin-bottom: 40px;">
    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif;" border="1">
        <thead>
        <tr>

        <tr>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e('Payment','advanced-partial-payment-or-deposit-for-woocommerce'); ?> </th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e('Payment ID','advanced-partial-payment-or-deposit-for-woocommerce'); ?> </th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e('Status','advanced-partial-payment-or-deposit-for-woocommerce'); ?> </th>
            <th class="td" scope="col" style="text-align:<?php echo esc_attr( $text_align ); ?>;"><?php esc_html_e('Amount','advanced-partial-payment-or-deposit-for-woocommerce'); ?> </th>

        </tr>
        </thead>
        <tbody>
        <?php foreach($schedule as $timestamp => $payment){

            $date = '';
            if (isset($payment['title'])) {

                $date = $payment['title'];
            } else {
                if(isset($payment['timestamp'])){
                    $timestamp = $payment['timestamp'];
                }

                if (!is_numeric($timestamp)) {
                    $date = '-';
                } else {
                    $date = date_i18n(wc_date_format(), $timestamp);
                }
            }

            $date = apply_filters('mepp_partial_payment_title', $date, $payment);

            $payment_order = false;
            if(isset($payment['id']) && !empty($payment['id'])) $payment_order = wc_get_order($payment['id']);

            if(!$payment_order) continue;
            $payment_id =  $payment_order->get_order_number();
            $status =  wc_get_order_status_name($payment_order->get_status());
            if($payment_order->get_meta('_mepp_payment_complete') === 'yes' && $payment_order->get_status() === 'pending'){
                $status = wc_get_order_status_name('completed');
            }
            $amount = $payment_order->get_total();
            $price_args = array('currency' => $payment_order->get_currency());

            ?>
            <tr class="order_item">
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
                    <?php esc_html_e($date); ?>
                </td>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
                    <?php esc_html_e($payment_id); ?>
                </td>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
                    <?php esc_html_e($status); ?>

                </td>
                <td class="td" style="text-align:<?php echo esc_attr( $text_align ); ?>; vertical-align: middle; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; word-wrap:break-word;">
                    <?php echo wc_price($amount,$price_args); ?>
                </td>
            </tr>
            <?php
        } ?>
        </tbody>
        <tfoot>

        </tfoot>
    </table>
</div>

