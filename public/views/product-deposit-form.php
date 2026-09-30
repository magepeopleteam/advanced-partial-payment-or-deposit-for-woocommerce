<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
/**
 * Product page deposit form.
 *
 * @var float  $price
 * @var float  $deposit_amount
 * @var float  $due_balance
 * @var bool   $allow_full
 * @var bool   $default_full Whether full payment is preselected.
 * @var string $deposit_type  Resolved deposit type, for the live price update in apd-public.js.
 * @var float  $deposit_value Deposit value (percentage or fixed amount).
 * @var string $deposit_text
 * @var string $full_text
 */
$default_full = ! empty( $default_full ) && $allow_full;
?>
<div class="apd-product-deposit-form" data-apd-deposit-type="<?php echo esc_attr( $deposit_type ); ?>" data-apd-deposit-value="<?php echo esc_attr( $deposit_value ); ?>">
	<div class="apd-deposit-header">
		<span class="apd-deposit-icon">💰</span>
		<span class="apd-deposit-title"><?php esc_html_e( 'Payment Options', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
	</div>
	<div class="apd-deposit-options">
		<label class="apd-deposit-option<?php echo $default_full ? '' : ' apd-deposit-option-active'; ?>">
			<input type="radio" name="apd_payment_type" value="deposit" <?php checked( ! $default_full ); ?> />
			<div class="apd-option-content">
				<span class="apd-option-radio"></span>
				<div class="apd-option-text">
					<span class="apd-option-label"><?php echo wp_kses_post( $deposit_text ); ?></span>
					<span class="apd-option-detail">
						<?php echo esc_html( $balance_label ); ?>: <?php echo wp_kses_post( wc_price( $due_balance ) ); ?>
					</span>
				</div>
			</div>
		</label>

		<?php if ( $allow_full ) : ?>
		<label class="apd-deposit-option<?php echo $default_full ? ' apd-deposit-option-active' : ''; ?>">
			<input type="radio" name="apd_payment_type" value="full" <?php checked( $default_full ); ?> />
			<div class="apd-option-content">
				<span class="apd-option-radio"></span>
				<div class="apd-option-text">
					<span class="apd-option-label"><?php echo wp_kses_post( $full_text ); ?></span>
				</div>
			</div>
		</label>
		<?php endif; ?>
	</div>
</div>
