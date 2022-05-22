<?php
//echo '<pre>';print_r($order->get_billing_first_name());die;
$setting_content = get_option('mepp_payment_plan_email_content');
$order_pay_link = $order->get_checkout_payment_url();
$order_data = $order->get_data();
$grand_total = 0;
$tax_total = 0;
if(isset($order_data['total_tax'])) {
    $tax_total = $order_data['total_tax'];
    $grand_total = $order->get_subtotal() + $tax_total;
}
?>


<div class="wcpp_email_template_container"
     style="max-width: 600px;margin: 0 auto;background: #fff;border: 1px solid #d3d3d3;border-radius: 3px;">
    <div class="wcpp-email-header" style="background: #2C98DA;padding: 20px 50px;">
        <h2 style="color: #fff;font-size: 28px;font-weight: 300;">Partial order
            #<?php echo $order->get_id(); ?></h2>
    </div>
    <div class="wcpp-email-content" style="padding: 30px 50px;">
        Hi <?php echo $order->get_billing_first_name() ?>,<br>
        <br>
        <?php
        echo $setting_content;
        printf("%s has made partial payment for order #%s. Order detail has appeared below.", 'You', $order->get_id());
        echo '<br>';
        ?>

        <div class="wcpp_order_summary" style="margin-top: 20px;">
            <span style="color:#2C98DA;font-size: 18px;"><?php printf("[Order #%s] (%s)", $order->get_id(), date(get_option('date_format'), strtotime($order->get_date_created()))); ?></span>
            <table style="border-collapse: collapse;width:100%">
                <thead>
                <tr>
                    <th style="text-align: left;border: 2px solid #ddd;padding: 5px 8px;">Product</th>
                    <th style="text-align: left;border: 2px solid #ddd;padding: 5px 8px;">Quantity</th>
                    <th style="text-align: left;border: 2px solid #ddd;padding: 5px 8px;">Price</th>
                </tr>
                </thead>
                <tbody>

                <?php foreach($order->get_items() as $item) : ?>
                <tr>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;">
                        <?php echo $item->get_name(); ?>
                    </td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;">
                        <?php echo $item->get_quantity(); ?>
                    </td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;">
                        <?php echo wc_price($item->get_total()); ?>
                    </td>
                </tr>
                <?php endforeach ?>

                <tr>
                    <td colspan="2" style="padding: 5px 8px;border: 2px solid #ddd;border-top: 5px solid #c7c7c7;">
                        Subtotal:
                    </td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;border-top: 5px solid #c7c7c7;"><?php echo wc_price($order->get_subtotal()); ?></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 8px;border: 2px solid #ddd;">
                        Tax:
                    </td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;"><?php echo wc_price($tax_total); ?></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 8px;border: 2px solid #ddd;border-top: 5px solid #c7c7c7;">
                        Total:
                    </td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;border-top: 5px solid #c7c7c7;"><?php echo wc_price($grand_total); ?></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 8px;border: 2px solid #ddd;">Paid:</td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;"><?php echo wc_price($order->get_total()); ?></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 8px;border: 2px solid #ddd;">Due:</td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;"><?php echo wc_price($order->get_meta('due_payment')); ?></td>
                </tr>
                <tr>
                    <td colspan="2" style="padding: 5px 8px;border: 2px solid #ddd;">Payment method:</td>
                    <td style="padding: 5px 8px;border: 2px solid #ddd;"><?php echo $order->get_payment_method_title(); ?></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php echo '<h3 style="margin: 0 50px;color: #2C98DA;">'.__("Billing address", 'advanced-partial-payment-or-deposit-for-woocommerce').'</h3>' ?>
    <div class="wcpp-billing-info-container" style="margin: 15px 50px;padding: 5px 5px;border: 1px solid #ddd;">
        <?php echo $order->get_formatted_billing_address(); ?>
    </div>
    <div class="wcpp-email-footer" style="padding: 30px 50px;">
        Thanks for using <?php echo get_bloginfo(); ?>
    </div>
</div>