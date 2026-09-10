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
                        <?php if ( APD_Order::are_partial_balance_payments_enabled( $order ) ) : ?>
                            <?php
                            $apd_bounds = APD_Order::get_balance_payment_bounds( $order );
                            $apd_step   = 1 / pow( 10, wc_get_price_decimals() );
                            ?>
                            <form method="get" class="apd-pay-balance-form"
                                  action="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
                                <input type="hidden" name="apd_pay_balance" value="<?php echo esc_attr( $order->get_id() ); ?>" />
                                <input type="hidden" name="_wpnonce"
                                       value="<?php echo esc_attr( wp_create_nonce( 'apd_pay_balance_' . $order->get_id() ) ); ?>" />
                                <label class="screen-reader-text" for="apd-amount-<?php echo esc_attr( $order->get_id() ); ?>">
                                    <?php esc_html_e( 'Amount to pay', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                                </label>
                                <input type="number" class="apd-pay-balance-amount"
                                       id="apd-amount-<?php echo esc_attr( $order->get_id() ); ?>"
                                       name="apd_amount"
                                       step="<?php echo esc_attr( $apd_step ); ?>"
                                       min="<?php echo esc_attr( $apd_bounds['min'] ); ?>"
                                       max="<?php echo esc_attr( $apd_bounds['max'] ); ?>"
                                       value="<?php echo esc_attr( $apd_bounds['max'] ); ?>" required />
                                <button type="submit" class="woocommerce-button button apd-pay-balance-btn">
                                    <?php echo esc_html( $pay_btn_label ); ?>
                                </button>
                                <?php if ( $apd_bounds['min'] < $apd_bounds['max'] ) : ?>
                                    <small class="apd-pay-balance-hint">
                                        <?php
                                        printf(
                                            /* translators: 1: minimum payment amount, 2: outstanding balance. */
                                            esc_html__( 'Pay any amount from %1$s up to %2$s.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                                            wp_kses_post( wc_price( $apd_bounds['min'] ) ),
                                            wp_kses_post( wc_price( $apd_bounds['max'] ) )
                                        );
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </form>
                        <?php else : ?>
                            <a href="<?php echo esc_url( APD_Pay_Balance::get_pay_balance_url( $order->get_id() ) ); ?>"
                               class="woocommerce-button button apd-pay-balance-btn">
                                <?php echo esc_html( $pay_btn_label ); ?>
                            </a>
                        <?php endif; ?>
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
