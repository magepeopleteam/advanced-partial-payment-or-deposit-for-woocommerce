<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!$order = wc_get_order($order_id)) {
    return;
}

// Initialize the variable to false
$show_pay_now_button = false;

// Check if _mepp_payment_schedule is set to 'yes'
$payment_schedule_enabled = get_post_meta($order_id, '_mepp_payment_schedule', true) === 'yes';
?> 

<h2 class="woocommerce-column__title" style="background-color: #f2f2f2; padding: 10px;"><?php echo esc_html__('Partial payments summary', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></h2>

<table class="woocommerce-table woocommerce_deposits_parent_order_summary" style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr style="background-color: #ddd;">
            <th style="padding: 10px;background: yellow;"><?php echo esc_html__('Payment', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <th style="padding: 10px;background: yellow;"><?php echo esc_html__('Payment ID', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <th style="padding: 10px;background: yellow;"><?php echo esc_html__('Status', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <th style="padding: 10px;background: yellow;"><?php echo esc_html__('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <th style="padding: 10px;background: yellow;"><?php echo esc_html__('Payment Method', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <?php if ($show_pay_now_button) : ?>
            <th style="padding: 10px;background: yellow;"><?php echo esc_html__('Payment Options', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($schedule as $timestamp => $payment) :

$title = '';

if(isset($payment['title'])){

    $title  = $payment['title'];
} else {
    if(isset($payment['timestamp'])){
        $timestamp = $payment['timestamp'];
    }

    if (!is_numeric($timestamp)) {
        $title = '-';
    } else {
        $title = date_i18n(wc_date_format(), $timestamp);
    }
}

$title = apply_filters('wc_deposits_partial_payment_title',$title,$payment);

$payment_order = false;
if(isset($payment['id']) && !empty($payment['id'])) $payment_order = wc_get_order($payment['id']);

if(!$payment_order) continue;
$payment_id = $payment_order ? $payment_order->get_order_number(): '-';
$status = $payment_order ? wc_get_order_status_name($payment_order->get_status()) : '-';
$amount = $payment_order ? $payment_order->get_total() : $payment['total'];
$price_args = array('currency' => $payment_order->get_currency());
$link = '';
            // New conditional check to show the "Pay Now" button only if the status indicates pending payment
            if ($payment_order->get_status() === 'pending') {
                $show_pay_now_button = true;
            } else {
                $show_pay_now_button = false;
            }

            if (is_account_page() && function_exists('WPO_WCPDF')) {
                $documents = WPO_WCPDF()->documents->get_documents();

                if ($documents) {
                    foreach ($documents as $document) {

                        if ($document->is_enabled() && $document->get_type() === 'partial_payment_invoice') {

                            $invoice = wcpdf_get_document('partial_payment_invoice', $payment_order, false);
                            $button_setting = $invoice->get_setting('my_account_buttons', 'available');

                            switch ($button_setting) {
                                case 'available':
                                    $invoice_allowed = $invoice->exists();
                                    break;
                                case 'always':
                                    $invoice_allowed = true;
                                    break;
                                case 'never':
                                    $invoice_allowed = false;
                                    break;
                                case 'custom':
                                    $allowed_statuses = $invoice->get_setting('my_account_restrict', array());
                                    $invoice_allowed = !empty($allowed_statuses) && in_array($payment_order->get_status(), array_keys($allowed_statuses));
                                    break;
                            }

                            $classes = $invoice && $invoice->exists() ? 'mepp_invoice_exists' : '';

                            if ($invoice_allowed) {
                                $link .= '<a class="button btn ' . $classes . '" href="' . wp_nonce_url(admin_url("admin-ajax.php?action=generate_wpo_wcpdf&document_type=partial_payment_invoice&order_ids=" . $payment_order->get_id()), 'generate_wpo_wcpdf') . '">' . esc_html__('PDF Invoice', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>';
                            }
                        }
                    }
                }
            }
        ?>
            <tr class="order_item" style="background-color: #fff;">
                <td style="padding: 10px;border: 1px solid grey;"><?php echo $title; ?></td>
                <td style="padding: 10px;border: 1px solid grey;"><?php echo $payment_id; ?></td>
                <td style="padding: 10px;border: 1px solid grey;"><?php echo $status; ?></td>
                <td style="padding: 10px;border: 1px solid grey;"><?php echo wc_price($amount, $price_args); ?></td>
                <td style="padding: 10px;border: 1px solid grey;"><?php echo $payment_order->get_payment_method_title(); ?></td>
                <?php if ($show_pay_now_button) : ?>
                <td style="padding: 10px;border: 1px solid grey;">
                    <button class="pay-now-btn" data-order-id="<?php echo esc_attr($order->get_id()); ?>" style="padding: 10px; background-color: #4CAF50; color: white; border: none; cursor: pointer; border-radius: 5px;">
                        <?php esc_html_e('Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                    </button>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
    (function($) {
        $(document).ready(function() {
            $('.pay-now-btn').click(function() {
                var orderId = $(this).data('order-id');
                if (orderId) {
                    window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>' + '?order-pay=' + orderId + '&custom_action=pay_now';
                }
            });
        });
    })(jQuery);
</script>

<?php

?>
