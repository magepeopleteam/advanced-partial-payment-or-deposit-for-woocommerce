<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!$order = wc_get_order($order_id)) {
    return;
}

?>

<h2 class="woocommerce-column__title"><?php echo esc_html__('Partial payments summary', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></h2>

<table class="woocommerce-table woocommerce_deposits_parent_order_summary order-summary-table">

    <thead>
    <tr>
        <th><?php echo esc_html__('Payment', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
        <th><?php echo esc_html__('Payment ID', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
        
        <th><?php echo esc_html__('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
        <th><?php echo esc_html__('Payment Method', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
        <th><?php echo esc_html__('Status', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>

        <?php if (is_account_page() && function_exists('WPO_WCPDF')): ?>
            <th></th>
        <?php endif; ?>
        <th><?php echo esc_html__('Action', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
    </tr>
    </thead>

    <tbody>
    <?php
    $last_payment_id = null; // Initialize last payment ID
    foreach ($schedule as $timestamp => $payment) {
        $title = isset($payment['title']) ? $payment['title'] : (isset($payment['timestamp']) ? date_i18n(wc_date_format(), $payment['timestamp']) : '-');
        $title = apply_filters('wc_deposits_partial_payment_title', $title, $payment);

        $payment_order = isset($payment['id']) && !empty($payment['id']) ? wc_get_order($payment['id']) : false;
        if (!$payment_order) continue;

        $payment_id = $payment_order->get_order_number();
        $status = wc_get_order_status_name($payment_order->get_status());
        $amount = $payment_order->get_total();
        $price_args = array('currency' => $payment_order->get_currency());
        $link = '';
        $payment_method = $payment_order->get_payment_method_title();
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
                        $classes = $invoice && $invoice->exists() ? 'wcdp_invoice_exists' : '';
                        if ($invoice_allowed) {
                            $link .= '<a class="button btn ' . $classes . '" href="' . wp_nonce_url(admin_url("admin-ajax.php?action=generate_wpo_wcpdf&document_type=partial_payment_invoice&order_ids=" . $payment_order->get_id()), 'generate_wpo_wcpdf') . '">';
                            $link .= esc_html__('PDF Invoice', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>';
                        }
                    }
                }
            }
        }

        $view_link = esc_url(wc_get_account_endpoint_url('orders') . '#' . $payment_order->get_id());
        $show_pay_button = !in_array($payment_order->get_status(), array('on-hold', 'partially-paid','completed'));

        $order_id = $payment_order->get_id();
        $order_key = $payment_order->get_order_key(); // Get the order key
        $payment_url = esc_url(wc_get_endpoint_url('order-pay', $order_id, wc_get_checkout_url()) . '?pay_for_order=true&key=' . $order_key . '&payment=partial_payment');

        // Track the last payment ID
        $last_payment_id = $payment_order->get_id();
        ?>

        <tr class="order_item">
            <td><?php echo $title; ?></td>
            <td><?php echo $payment_id; ?></td>
           
            <td><?php echo wc_price($amount, $price_args); ?></td>
            <td><?php echo esc_html($payment_method); ?></td>
            <td><?php echo $status; ?></td>
            <?php if (is_account_page() && function_exists('WPO_WCPDF')): ?>
                <td><?php echo $link; ?></td>
            <?php endif; ?>
            <td>
                <?php if ($show_pay_button): ?>
                    <a class="woocommerce-button wp-element-button button pay" href="<?php echo $payment_url; ?>">
                        <?php echo esc_html__('Pay', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
    <?php } ?>

    </tbody>

    <tfoot>

    </tfoot>
</table>

<style>
    .order-summary-table {
        width: 100%;
        border-collapse: collapse;
        margin: 20px 0;
        font-family: Arial, sans-serif;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        animation: fadeIn 1s ease-in-out;
    }

    .order-summary-table th, .order-summary-table td {
        padding: 15px;
        text-align: left;
        border: 1px solid #ddd;
        animation: slideIn 0.5s ease-in-out;
    }

    .order-summary-table th {
        background-color: #0675c4;
        color: #f9f9f9;
        font-weight: bold;
    }

    .order-summary-table tr:hover {
        background-color: #f1f1f1;
        transition: background-color 0.3s ease-in-out;
    }

    .order-summary-table td {
        animation: bounceIn 1s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideIn {
        from { transform: translateX(-10px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }

    @keyframes bounceIn {
        0%, 20%, 50%, 80%, 100% {
            transform: translateY(0);
        }
        40% {
            transform: translateY(-10px);
        }
        60% {
            transform: translateY(-5px);
        }
    }

    .order-summary-table a {
        text-decoration: none;
        transition: color 0.3s ease-in-out;
    }

    .order-summary-table a:hover {
        color: #eef0ff;
        background: darkblue;
    }
</style>

<?php
// Check if the last payment is completed and update order status
if ($last_payment_id) {
    $last_payment_order = wc_get_order($last_payment_id);
    if ($last_payment_order && $last_payment_order->get_status() === 'partially-paid') {
        $order->update_status('completed');
    }
}
?>
