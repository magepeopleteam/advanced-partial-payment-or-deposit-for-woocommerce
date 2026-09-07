<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'apd_settings', array() );
?>
<div class="apd-tab-header">
    <h2><?php esc_html_e( 'Label Customization', 'advanced-partial-payment' ); ?></h2>
    <p><?php esc_html_e( 'Customize the text displayed to customers throughout the deposit process.', 'advanced-partial-payment' ); ?></p>
</div>

<form class="apd-settings-form" data-tab="labels">
    <div class="apd-card">
        <div class="apd-card-header">
            <h3><?php esc_html_e( 'Display Labels', 'advanced-partial-payment' ); ?></h3>
        </div>
        <div class="apd-card-body">
            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-deposit-label"><?php esc_html_e( 'Deposit Label', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Label shown for the deposit amount.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <input type="text" name="deposit_label" id="apd-deposit-label"
                           value="<?php echo esc_attr( $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' ) ); ?>"
                           class="apd-input" />
                </div>
            </div>

            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-due-balance-label"><?php esc_html_e( 'Due Balance Label', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Label shown for the remaining balance.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <input type="text" name="due_balance_label" id="apd-due-balance-label"
                           value="<?php echo esc_attr( $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment' ) ); ?>"
                           class="apd-input" />
                </div>
            </div>

            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-pay-button-label"><?php esc_html_e( 'Pay Button Label', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Text on the pay balance button in My Account.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <input type="text" name="pay_button_label" id="apd-pay-button-label"
                           value="<?php echo esc_attr( $settings['pay_button_label'] ?? __( 'Pay Remaining Balance', 'advanced-partial-payment' ) ); ?>"
                           class="apd-input" />
                </div>
            </div>
        </div>
    </div>

    <div class="apd-card">
        <div class="apd-card-header">
            <h3><?php esc_html_e( 'Product Page Text', 'advanced-partial-payment' ); ?></h3>
        </div>
        <div class="apd-card-body">
            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-deposit-text"><?php esc_html_e( 'Deposit Option Text', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Text for deposit radio button. Use {deposit_amount} as placeholder.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <input type="text" name="deposit_text" id="apd-deposit-text"
                           value="<?php echo esc_attr( $settings['deposit_text'] ?? 'Pay a deposit of {deposit_amount}' ); ?>"
                           class="apd-input" />
                </div>
            </div>

            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-full-payment-text"><?php esc_html_e( 'Full Payment Text', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Text for full payment radio button. Use {full_amount} as placeholder.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <input type="text" name="full_payment_text" id="apd-full-payment-text"
                           value="<?php echo esc_attr( $settings['full_payment_text'] ?? 'Pay full amount of {full_amount}' ); ?>"
                           class="apd-input" />
                </div>
            </div>
        </div>
    </div>

    <div class="apd-form-actions">
        <button type="submit" class="apd-btn apd-btn-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php esc_html_e( 'Save Labels', 'advanced-partial-payment' ); ?>
        </button>
    </div>
</form>
