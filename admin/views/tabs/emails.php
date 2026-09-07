<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings        = get_option( 'apd_settings', array() );
$email_templates = APD_Emails::get_email_template_defaults();
$email_cards     = array(
    'deposit_received' => array(
        'enabled_key' => 'email_deposit_received',
        'icon'        => 'dashicons-yes-alt',
        'gradient'    => 'linear-gradient(135deg,#667eea,#764ba2)',
    ),
    'balance_due' => array(
        'enabled_key' => 'email_balance_due',
        'icon'        => 'dashicons-bell',
        'gradient'    => 'linear-gradient(135deg,#f093fb,#f5576c)',
    ),
    'payment_complete' => array(
        'enabled_key' => 'email_payment_complete',
        'icon'        => 'dashicons-awards',
        'gradient'    => 'linear-gradient(135deg,#4facfe,#00f2fe)',
    ),
);
?>
<div class="apd-tab-header">
    <h2><?php esc_html_e( 'Email Notifications', 'advanced-partial-payment' ); ?></h2>
    <p><?php esc_html_e( 'Configure which deposit-related emails are sent and edit the built-in email templates directly from here.', 'advanced-partial-payment' ); ?></p>
</div>

<form class="apd-settings-form" data-tab="emails">
    <div class="apd-card">
        <div class="apd-card-body">
            <div class="apd-email-cards">
                <?php foreach ( $email_cards as $email_key => $card ) : ?>
                    <?php $template = APD_Emails::get_email_template_config( $email_key ); ?>
                    <div class="apd-email-card">
                        <div class="apd-email-card-icon" style="background:<?php echo esc_attr( $card['gradient'] ); ?>;">
                            <span class="dashicons <?php echo esc_attr( $card['icon'] ); ?>"></span>
                        </div>
                        <div class="apd-email-card-content">
                            <h4><?php echo esc_html( $template['title'] ); ?></h4>
                            <p><?php echo esc_html( $template['description'] ); ?></p>
                        </div>
                        <div class="apd-email-card-toggle">
                            <label class="apd-toggle">
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr( $card['enabled_key'] ); ?>"
                                    value="yes"
                                    <?php checked( $settings[ $card['enabled_key'] ] ?? 'yes', 'yes' ); ?>
                                />
                                <span class="apd-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="apd-card">
        <div class="apd-card-header">
            <h3><?php esc_html_e( 'Email Templates', 'advanced-partial-payment' ); ?></h3>
        </div>
        <div class="apd-card-body">
            <div class="apd-info-box" style="margin-bottom:18px;">
                <div class="apd-info-icon">
                    <span class="dashicons dashicons-editor-help"></span>
                </div>
                <div class="apd-info-content">
                    <p><?php esc_html_e( 'Predefined templates are loaded below. You can edit the subject, heading, and body. Available placeholders: {customer_first_name}, {order_number}, {order_date}, {total_amount}, {deposit_amount}, {amount_paid}, {balance_due}, {pay_balance_url}, {site_title}. Available body tokens: [deposit_summary], [pay_balance_button].', 'advanced-partial-payment' ); ?></p>
                </div>
            </div>

            <?php foreach ( $email_templates as $email_key => $default_template ) : ?>
                <?php $template = APD_Emails::get_email_template_config( $email_key ); ?>
                <div class="apd-card" style="margin-bottom:18px;">
                    <div class="apd-card-header">
                        <h3><?php echo esc_html( $template['title'] ); ?></h3>
                    </div>
                    <div class="apd-card-body">
                        <div class="apd-field-row">
                            <div class="apd-field-label">
                                <label><?php esc_html_e( 'Email Subject', 'advanced-partial-payment' ); ?></label>
                                <p class="apd-field-desc"><?php esc_html_e( 'Customer email subject line.', 'advanced-partial-payment' ); ?></p>
                            </div>
                            <div class="apd-field-input" style="min-width:420px;">
                                <input
                                    type="text"
                                    name="email_<?php echo esc_attr( $email_key ); ?>_subject"
                                    class="apd-input"
                                    style="max-width:420px;"
                                    value="<?php echo esc_attr( $template['subject'] ); ?>"
                                />
                            </div>
                        </div>

                        <div class="apd-field-row">
                            <div class="apd-field-label">
                                <label><?php esc_html_e( 'Email Heading', 'advanced-partial-payment' ); ?></label>
                                <p class="apd-field-desc"><?php esc_html_e( 'Main heading shown inside the email template.', 'advanced-partial-payment' ); ?></p>
                            </div>
                            <div class="apd-field-input" style="min-width:420px;">
                                <input
                                    type="text"
                                    name="email_<?php echo esc_attr( $email_key ); ?>_heading"
                                    class="apd-input"
                                    style="max-width:420px;"
                                    value="<?php echo esc_attr( $template['heading'] ); ?>"
                                />
                            </div>
                        </div>

                        <?php if ( 'balance_due' === $email_key ) : ?>
                            <div class="apd-field-row">
                                <div class="apd-field-label">
                                    <label><?php esc_html_e( 'Pay Button Label', 'advanced-partial-payment' ); ?></label>
                                    <p class="apd-field-desc"><?php esc_html_e( 'Label used for the [pay_balance_button] token.', 'advanced-partial-payment' ); ?></p>
                                </div>
                                <div class="apd-field-input" style="min-width:420px;">
                                    <input
                                        type="text"
                                        name="email_balance_due_button_label"
                                        class="apd-input"
                                        style="max-width:420px;"
                                        value="<?php echo esc_attr( $template['button_label'] ); ?>"
                                    />
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="apd-field-row">
                            <div class="apd-field-label">
                                <label><?php esc_html_e( 'Email Body Template', 'advanced-partial-payment' ); ?></label>
                                <p class="apd-field-desc"><?php esc_html_e( 'Edit the predefined template text. The placeholders and body tokens above are supported.', 'advanced-partial-payment' ); ?></p>
                            </div>
                            <div class="apd-field-input" style="min-width:560px;align-items:flex-start;">
                                <textarea
                                    name="email_<?php echo esc_attr( $email_key ); ?>_body"
                                    class="apd-input"
                                    rows="9"
                                    style="max-width:560px;min-height:220px;"
                                ><?php echo esc_textarea( $template['body'] ); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="apd-form-actions">
        <button type="submit" class="apd-btn apd-btn-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php esc_html_e( 'Save Email Settings', 'advanced-partial-payment' ); ?>
        </button>
    </div>
</form>
