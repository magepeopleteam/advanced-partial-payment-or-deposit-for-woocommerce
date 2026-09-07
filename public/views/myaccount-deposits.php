<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * My Account deposits tab content.
 *
 * @var array  $orders
 * @var string $pay_btn_label
 */
?>
<div class="apd-myaccount-deposits">
    <h3><?php esc_html_e( 'My Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></h3>

    <?php if ( ! empty( $orders ) ) : ?>
    <table class="woocommerce-orders-table apd-deposits-table">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Order', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Date', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Total', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Paid', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Balance', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Status', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></th>
                <th><?php esc_html_e( 'Action', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $orders as $order ) :
                $details = APD_Order::get_deposit_details( $order );
                if ( ! $details ) continue;
            ?>
            <tr>
                <td><a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
                <td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
                <td><?php echo wc_price( $details['total_amount'] ); ?></td>
                <td class="apd-text-success"><?php echo wc_price( $details['amount_paid'] ); ?></td>
                <td class="<?php echo $details['balance_due'] > 0 ? 'apd-text-danger' : 'apd-text-success'; ?>">
                    <strong><?php echo wc_price( $details['balance_due'] ); ?></strong>
                </td>
                <td>
                    <?php if ( $details['balance_due'] > 0 ) : ?>
                        <span class="apd-status-badge apd-status-pending"><?php esc_html_e( 'Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                    <?php else : ?>
                        <span class="apd-status-badge apd-status-complete"><?php esc_html_e( 'Fully Paid', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $details['balance_due'] > 0 ) : ?>
                        <a href="<?php echo esc_url( APD_Pay_Balance::get_pay_balance_url( $order->get_id() ) ); ?>"
                           class="woocommerce-button button apd-pay-balance-btn">
                            <?php echo esc_html( $pay_btn_label ); ?>
                        </a>
                    <?php else : ?>
                        <span class="apd-paid-check">✓</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else : ?>
    <div class="woocommerce-message woocommerce-message--info">
        <p><?php esc_html_e( 'No deposit orders found.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></p>
    </div>
    <?php endif; ?>
</div>
