<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Cart deposit summary.
 *
 * @var float  $deposit_total
 * @var float  $balance_due
 * @var string $deposit_label
 * @var string $balance_label
 */
?>
<tr class="apd-cart-deposit-row">
    <th><?php echo esc_html( $deposit_label ); ?> <small><?php esc_html_e( '(To Pay Now)', 'advanced-partial-payment' ); ?></small></th>
    <td data-title="<?php echo esc_attr( $deposit_label ); ?>">
        <strong class="apd-text-primary"><?php echo wc_price( $deposit_total ); ?></strong>
    </td>
</tr>
<tr class="apd-cart-balance-row">
    <th><?php echo esc_html( $balance_label ); ?> <small><?php esc_html_e( '(Pay Later)', 'advanced-partial-payment' ); ?></small></th>
    <td data-title="<?php echo esc_attr( $balance_label ); ?>">
        <strong class="apd-text-warning"><?php echo wc_price( $balance_due ); ?></strong>
    </td>
</tr>
