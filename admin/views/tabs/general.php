<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = get_option( 'apd_settings', array() );
?>
<div class="apd-tab-header">
    <h2><?php esc_html_e( 'General Settings', 'advanced-partial-payment' ); ?></h2>
    <p><?php esc_html_e( 'Configure global deposit settings for your store.', 'advanced-partial-payment' ); ?></p>
</div>

<?php do_action( 'apd_general_tab_after_intro' ); ?>

<form class="apd-settings-form" data-tab="general">
    <div class="apd-card">
        <div class="apd-card-header">
            <h3><?php esc_html_e( 'Deposit Configuration', 'advanced-partial-payment' ); ?></h3>
        </div>
        <div class="apd-card-body">
            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-enable-deposit"><?php esc_html_e( 'Enable Deposits', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Enable or disable the deposit system globally.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <label class="apd-toggle">
                        <input type="checkbox" name="enable_deposit" id="apd-enable-deposit"
                               value="yes" <?php checked( $settings['enable_deposit'] ?? 'yes', 'yes' ); ?> />
                        <span class="apd-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-deposit-type"><?php esc_html_e( 'Default Deposit Type', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Choose how the default deposit amount is calculated.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <select name="deposit_type" id="apd-deposit-type" class="apd-select">
                        <option value="percentage" <?php selected( $settings['deposit_type'] ?? 'percentage', 'percentage' ); ?>><?php esc_html_e( 'Percentage (%)', 'advanced-partial-payment' ); ?></option>
                        <option value="fixed" <?php selected( $settings['deposit_type'] ?? 'percentage', 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount', 'advanced-partial-payment' ); ?></option>
                        <?php if ( defined( 'APD_PRO_VERSION' ) ) : ?>
                        <option value="payment_plan" <?php selected( $settings['deposit_type'] ?? 'percentage', 'payment_plan' ); ?>><?php esc_html_e( 'Payment Plan (Pro)', 'advanced-partial-payment' ); ?></option>
                        <option value="min_max" <?php selected( $settings['deposit_type'] ?? 'percentage', 'min_max' ); ?>><?php esc_html_e( 'Min / Max – Customer Chooses (Pro)', 'advanced-partial-payment' ); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>

            <div class="apd-field-row" id="apd-deposit-value-row">
                <div class="apd-field-label">
                    <label for="apd-deposit-value"><?php esc_html_e( 'Default Deposit Value', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'The default deposit amount or percentage.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <div class="apd-input-group">
                        <input type="number" name="deposit_value" id="apd-deposit-value"
                               value="<?php echo esc_attr( $settings['deposit_value'] ?? '50' ); ?>"
                               step="0.01" min="0" class="apd-input" />
                        <span class="apd-input-suffix" id="apd-value-suffix">%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="apd-card">
        <div class="apd-card-header">
            <h3><?php esc_html_e( 'Payment Options', 'advanced-partial-payment' ); ?></h3>
        </div>
        <div class="apd-card-body">
            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-allow-full-payment"><?php esc_html_e( 'Allow Full Payment', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Allow customers to choose between deposit or full payment.', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <label class="apd-toggle">
                        <input type="checkbox" name="allow_full_payment" id="apd-allow-full-payment"
                               value="yes" <?php checked( $settings['allow_full_payment'] ?? 'yes', 'yes' ); ?> />
                        <span class="apd-toggle-slider"></span>
                    </label>
                </div>
            </div>

            <div class="apd-field-row">
                <div class="apd-field-label">
                    <label for="apd-force-deposit"><?php esc_html_e( 'Force Deposit', 'advanced-partial-payment' ); ?></label>
                    <p class="apd-field-desc"><?php esc_html_e( 'Force customers to pay only the deposit amount (no full payment option).', 'advanced-partial-payment' ); ?></p>
                </div>
                <div class="apd-field-input">
                    <label class="apd-toggle">
                        <input type="checkbox" name="force_deposit" id="apd-force-deposit"
                               value="yes" <?php checked( $settings['force_deposit'] ?? 'no', 'yes' ); ?> />
                        <span class="apd-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="apd-form-actions">
        <button type="submit" class="apd-btn apd-btn-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php esc_html_e( 'Save Settings', 'advanced-partial-payment' ); ?>
        </button>
    </div>
</form>
