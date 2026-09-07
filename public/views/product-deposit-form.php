<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Product page deposit form.
 *
 * @var float  $price
 * @var float  $deposit_amount
 * @var float  $due_balance
 * @var bool   $allow_full
 * @var string $deposit_text
 * @var string $full_text
 */
?>
<div class="apd-product-deposit-form">
    <div class="apd-deposit-header">
        <span class="apd-deposit-icon">💰</span>
        <span class="apd-deposit-title"><?php esc_html_e( 'Payment Options', 'advanced-partial-payment' ); ?></span>
    </div>
    <div class="apd-deposit-options">
        <label class="apd-deposit-option apd-deposit-option-active">
            <input type="radio" name="apd_payment_type" value="deposit" checked />
            <div class="apd-option-content">
                <span class="apd-option-radio"></span>
                <div class="apd-option-text">
                    <span class="apd-option-label"><?php echo wp_kses_post( $deposit_text ); ?></span>
                    <span class="apd-option-detail">
                        <?php echo esc_html( $balance_label ); ?>: <?php echo wc_price( $due_balance ); ?>
                    </span>
                </div>
            </div>
        </label>

        <?php if ( $allow_full ) : ?>
        <label class="apd-deposit-option">
            <input type="radio" name="apd_payment_type" value="full" />
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
