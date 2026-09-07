<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
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
        <span class="apd-deposit-title"><?php esc_html_e( 'Choose Your Deposit Amount', 'advanced-partial-payment' ); ?></span>
    </div>
    <div class="apd-deposit-options" style="padding:16px;">
        <!-- Hidden radio: always deposit -->
        <input type="hidden" name="apd_payment_type" value="deposit" />

        <div class="apd-minmax-info" style="display:flex;justify-content:space-between;font-size:13px;color:#64748b;margin-bottom:8px;">
            <span><?php printf( esc_html__( 'Min: %s', 'advanced-partial-payment' ), wc_price( $min_deposit ) ); ?></span>
            <span><?php printf( esc_html__( 'Max: %s', 'advanced-partial-payment' ), wc_price( $max_deposit ) ); ?></span>
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
                    <?php printf( esc_html__( 'Pay full amount of %s instead', 'advanced-partial-payment' ), wc_price( $price ) ); ?>
                </span>
            </label>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function($){
    var price    = <?php echo esc_js( $price ); ?>;
    var currency = <?php echo wp_json_encode( $currency ); ?>;
    var min      = <?php echo esc_js( $min_deposit ); ?>;
    var max      = <?php echo esc_js( $max_deposit ); ?>;
    var lastDeposit = <?php echo esc_js( $default_deposit ); ?>;

    function updateBalance( deposit ) {
        var balance = Math.max( 0, price - deposit );
        $('#apd-balance-display').text( currency + balance.toFixed(2) );
    }

    // Sync slider → input
    $('#apd-deposit-range').on('input', function(){
        var val = parseFloat($(this).val());
        lastDeposit = val;
        $('#apd-deposit-input').val(val.toFixed(2));
        updateBalance(val);
    });

    // Sync input → slider
    $('#apd-deposit-input').on('input', function(){
        var val = parseFloat($(this).val()) || 0;
        if (val < min) val = min;
        if (val > max) val = max;
        lastDeposit = val;
        $('#apd-deposit-range').val(val);
        updateBalance(val);
    });

    // Full payment toggle
    $('#apd-pay-full-toggle').on('change', function(){
        if ($(this).is(':checked')) {
            var current = parseFloat($('#apd-deposit-input').val()) || min;
            if (current >= min && current <= max) {
                lastDeposit = current;
            }
            $('input[name="apd_payment_type"]').val('full');
            $('#apd-deposit-input').val(price.toFixed(2)).prop('disabled', true);
            $('#apd-deposit-range').val(price).prop('disabled', true);
            updateBalance(price);
        } else {
            $('input[name="apd_payment_type"]').val('deposit');
            var prev = lastDeposit;
            if (prev < min) prev = min;
            if (prev > max) prev = max;
            $('#apd-deposit-input').val(prev.toFixed(2)).prop('disabled', false);
            $('#apd-deposit-range').val(prev).prop('disabled', false);
            updateBalance(prev);
        }
    });
})(jQuery);
</script>
