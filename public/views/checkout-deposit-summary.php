<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Checkout deposit summary.
 *
 * @var float  $deposit_total
 * @var float  $balance_due
 * @var string $deposit_label
 * @var string $balance_label
 */
?>
<tr class="apd-checkout-deposit-row">
    <th><?php echo esc_html( $deposit_label ); ?> <small><?php esc_html_e( '(To Pay Now)', 'advanced-partial-payment' ); ?></small></th>
    <td>
        <strong style="color:#6366f1;"><?php echo wc_price( $deposit_total ); ?></strong>
    </td>
</tr>
<tr class="apd-checkout-balance-row">
    <th><?php echo esc_html( $balance_label ); ?> <small><?php esc_html_e( '(Pay Later)', 'advanced-partial-payment' ); ?></small></th>
    <td>
        <strong style="color:#f59e0b;"><?php echo wc_price( $balance_due ); ?></strong>
    </td>
</tr>
