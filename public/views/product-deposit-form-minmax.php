<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; }
/**
 * Min / Max deposit form — customer chooses their own deposit amount within a range.
 *
 * @var float  $price           Product price
 * @var float  $min_deposit     Minimum deposit allowed
 * @var float  $max_deposit     Maximum deposit allowed
 * @var float  $default_deposit Default deposit value
 * @var bool   $allow_full      Whether full payment is allowed
 * @var string $deposit_label   Label for "Deposit"
 * @var string $balance_label   Label for "Due Balance"
 */
$currency = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
?>
<div class="apd-product-deposit-form">
	<div class="apd-deposit-header">
		<span class="apd-deposit-icon">💰</span>
		<span class="apd-deposit-title"><?php esc_html_e( 'Choose Your Deposit Amount', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
	</div>
	<div class="apd-deposit-options" style="padding:16px;">
		<!-- Hidden radio: always deposit -->
		<input type="hidden" name="apd_payment_type" value="deposit" />

		<div class="apd-minmax-info" style="display:flex;justify-content:space-between;font-size:13px;color:#64748b;margin-bottom:8px;">
			<span><?php /* translators: %s: formatted minimum deposit amount. */ printf( esc_html__( 'Min: %s', 'advanced-partial-payment-or-deposit-for-woocommerce' ), wp_kses_post( wc_price( $min_deposit ) ) ); ?></span>
			<span><?php /* translators: %s: formatted maximum deposit amount. */ printf( esc_html__( 'Max: %s', 'advanced-partial-payment-or-deposit-for-woocommerce' ), wp_kses_post( wc_price( $max_deposit ) ) ); ?></span>
		</div>

		<div class="apd-minmax-slider" style="margin-bottom:12px;">
			<input type="range" name="apd_custom_deposit_range"
					min="<?php echo esc_attr( $min_deposit ); ?>"
					max="<?php echo esc_attr( $max_deposit ); ?>"
					value="<?php echo esc_attr( $default_deposit ); ?>"
					step="0.01"
					id="apd-deposit-range"
					style="width:100%;accent-color:#6366f1;" />
		</div>

		<div class="apd-minmax-amount" style="display:flex;gap:12px;align-items:center;">
			<div style="flex:1;">
				<label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;"><?php echo esc_html( $deposit_label ); ?></label>
				<div class="apd-input-group" style="display:flex;border:2px solid #e2e8f0;border-radius:8px;overflow:hidden;">
					<span style="background:#f8fafc;padding:8px 10px;font-size:13px;color:#64748b;border-right:1px solid #e2e8f0;"><?php echo esc_html( $currency ); ?></span>
					<input type="number" name="apd_custom_deposit"
							id="apd-deposit-input"
							value="<?php echo esc_attr( $default_deposit ); ?>"
							min="<?php echo esc_attr( $min_deposit ); ?>"
							max="<?php echo esc_attr( $max_deposit ); ?>"
							step="0.01"
							style="border:none;padding:8px 10px;width:100%;font-size:14px;font-weight:600;outline:none;" />
				</div>
			</div>
			<div style="flex:1;">
				<label style="font-size:12px;font-weight:600;color:#334155;display:block;margin-bottom:4px;"><?php echo esc_html( $balance_label ); ?></label>
				<div style="background:#f8fafc;border:2px solid #e2e8f0;border-radius:8px;padding:8px 12px;font-size:14px;font-weight:600;color:#6366f1;" id="apd-balance-display">
					<?php echo esc_html( $currency . number_format( $price - $default_deposit, 2 ) ); ?>
				</div>
			</div>
		</div>

		<?php if ( $allow_full ) : ?>
		<div style="margin-top:12px;padding-top:12px;border-top:1px solid #f1f5f9;">
			<label class="apd-deposit-option" style="margin:0;">
				<input type="checkbox" id="apd-pay-full-toggle" value="1" style="margin-right:8px;" />
				<span style="font-size:13px;color:#334155;">
					<?php /* translators: %s: formatted full product price. */ printf( esc_html__( 'Pay full amount of %s instead', 'advanced-partial-payment-or-deposit-for-woocommerce' ), wp_kses_post( wc_price( $price ) ) ); ?>
				</span>
			</label>
		</div>
		<?php endif; ?>
	</div>
</div>

<script>
(function(){
	var script   = document.currentScript;
	var form     = script ? script.previousElementSibling : null;
	var price    = <?php echo esc_js( $price ); ?>;
	var currency = <?php echo wp_json_encode( $currency ); ?>;
	var min      = <?php echo esc_js( $min_deposit ); ?>;
	var max      = <?php echo esc_js( $max_deposit ); ?>;
	var lastDeposit = <?php echo esc_js( $default_deposit ); ?>;
	var range;
	var input;
	var balance;
	var fullToggle;

	if (!form || !form.classList.contains('apd-product-deposit-form')) {
		return;
	}

	range = form.querySelector('#apd-deposit-range');
	input = form.querySelector('#apd-deposit-input');
	balance = form.querySelector('#apd-balance-display');
	fullToggle = form.querySelector('#apd-pay-full-toggle');

	function updateBalance( deposit ) {
		var amount = Math.max( 0, price - deposit );
		balance.textContent = currency + amount.toFixed(2);
	}

	// Sync slider → input
	range.addEventListener('input', function(){
		var val = parseFloat(range.value);
		lastDeposit = val;
		input.value = val.toFixed(2);
		updateBalance(val);
	});

	// Sync input → slider
	input.addEventListener('input', function(){
		var val = parseFloat(input.value) || 0;
		if (val < min) val = min;
		if (val > max) val = max;
		lastDeposit = val;
		range.value = val;
		updateBalance(val);
	});

	// Full payment toggle
	if (fullToggle) {
		fullToggle.addEventListener('change', function(){
			if (fullToggle.checked) {
				var current = parseFloat(input.value) || min;
				if (current >= min && current <= max) {
					lastDeposit = current;
				}
				form.querySelector('input[name="apd_payment_type"]').value = 'full';
				input.value = price.toFixed(2);
				input.disabled = true;
				range.value = price;
				range.disabled = true;
				updateBalance(price);
			} else {
				form.querySelector('input[name="apd_payment_type"]').value = 'deposit';
				var prev = lastDeposit;
				if (prev < min) prev = min;
				if (prev > max) prev = max;
				input.value = prev.toFixed(2);
				input.disabled = false;
				range.value = prev;
				range.disabled = false;
				updateBalance(prev);
			}
		});
	}
})();
</script>
