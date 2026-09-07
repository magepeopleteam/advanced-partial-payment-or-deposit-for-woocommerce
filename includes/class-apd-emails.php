<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Custom WooCommerce email notifications for deposits.
 */
class APD_Emails {

    public function __construct() {
        add_filter( 'woocommerce_email_classes', array( $this, 'register_emails' ) );
        add_action( 'apd_deposit_payment_complete', array( $this, 'trigger_deposit_received' ), 10, 2 );
        add_action( 'apd_full_payment_complete', array( $this, 'trigger_payment_complete' ), 10, 2 );
    }

    /**
     * Register custom email classes.
     *
     * @param array $email_classes WooCommerce email classes.
     * @return array
     */
    public function register_emails( $email_classes ) {
        require_once APD_PLUGIN_DIR . 'includes/emails/class-apd-email-deposit-received.php';
        require_once APD_PLUGIN_DIR . 'includes/emails/class-apd-email-balance-due.php';
        require_once APD_PLUGIN_DIR . 'includes/emails/class-apd-email-payment-complete.php';

        $email_classes['APD_Email_Deposit_Received'] = new APD_Email_Deposit_Received();
        $email_classes['APD_Email_Balance_Due']      = new APD_Email_Balance_Due();
        $email_classes['APD_Email_Payment_Complete'] = new APD_Email_Payment_Complete();

        return $email_classes;
    }

    /**
     * Trigger deposit received email.
     *
     * @param int      $order_id Order ID.
     * @param WC_Order $order    Order object.
     */
    public function trigger_deposit_received( $order_id, $order ) {
        if ( 'yes' !== apd_get_option( 'email_deposit_received', 'yes' ) ) {
            return;
        }

        WC()->mailer();
        do_action( 'apd_email_deposit_received_notification', $order_id );
    }

    /**
     * Trigger full payment complete email.
     *
     * @param int      $order_id Order ID.
     * @param WC_Order $order    Order object.
     */
    public function trigger_payment_complete( $order_id, $order ) {
        if ( 'yes' !== apd_get_option( 'email_payment_complete', 'yes' ) ) {
            return;
        }

        WC()->mailer();
        do_action( 'apd_email_payment_complete_notification', $order_id );
    }

    /**
     * Get editable email template defaults.
     *
     * @return array<string,array<string,string>>
     */
    public static function get_email_template_defaults() {
        return array(
            'deposit_received' => array(
                'title'       => __( 'Deposit Received', 'advanced-partial-payment' ),
                'description' => __( 'Sent to the customer when their deposit payment is confirmed.', 'advanced-partial-payment' ),
                'subject'     => __( 'Your deposit payment for Order #{order_number}', 'advanced-partial-payment' ),
                'heading'     => __( 'Deposit Payment Received', 'advanced-partial-payment' ),
                'body'        => implode(
                    "\n\n",
                    array(
                        __( 'Hi {customer_first_name},', 'advanced-partial-payment' ),
                        __( 'Thank you for your deposit payment for order #{order_number}.', 'advanced-partial-payment' ),
                        '[deposit_summary]',
                        __( 'You can pay your remaining balance from your account page or payment link when you are ready.', 'advanced-partial-payment' ),
                    )
                ),
                'button_label' => '',
            ),
            'balance_due' => array(
                'title'       => __( 'Balance Due Reminder', 'advanced-partial-payment' ),
                'description' => __( 'Sent as a reminder when a customer has an outstanding balance.', 'advanced-partial-payment' ),
                'subject'     => __( 'Balance payment due for Order #{order_number}', 'advanced-partial-payment' ),
                'heading'     => __( 'Balance Payment Due', 'advanced-partial-payment' ),
                'body'        => implode(
                    "\n\n",
                    array(
                        __( 'Hi {customer_first_name},', 'advanced-partial-payment' ),
                        __( 'This is a reminder that you still have an outstanding balance for order #{order_number}.', 'advanced-partial-payment' ),
                        '[deposit_summary]',
                        '[pay_balance_button]',
                    )
                ),
                'button_label' => __( 'Pay Balance Now', 'advanced-partial-payment' ),
            ),
            'payment_complete' => array(
                'title'       => __( 'Payment Complete', 'advanced-partial-payment' ),
                'description' => __( 'Sent when the customer has paid the full remaining balance.', 'advanced-partial-payment' ),
                'subject'     => __( 'Payment complete for Order #{order_number}', 'advanced-partial-payment' ),
                'heading'     => __( 'Payment Complete', 'advanced-partial-payment' ),
                'body'        => implode(
                    "\n\n",
                    array(
                        __( 'Hi {customer_first_name},', 'advanced-partial-payment' ),
                        __( 'Great news! Your order #{order_number} has been fully paid.', 'advanced-partial-payment' ),
                        '[deposit_summary]',
                        __( 'Your order is now complete. Thank you for completing your payment.', 'advanced-partial-payment' ),
                    )
                ),
                'button_label' => '',
            ),
        );
    }

    /**
     * Get template config merged with saved settings.
     *
     * @param string $template_id Template key.
     * @return array<string,string>
     */
    public static function get_email_template_config( $template_id ) {
        $defaults = self::get_email_template_defaults();
        $settings = get_option( 'apd_settings', array() );
        $default  = $defaults[ $template_id ] ?? array();
        $subject  = $settings[ 'email_' . $template_id . '_subject' ] ?? '';
        $heading  = $settings[ 'email_' . $template_id . '_heading' ] ?? '';
        $body     = $settings[ 'email_' . $template_id . '_body' ] ?? '';
        $button   = $settings[ 'email_' . $template_id . '_button_label' ] ?? '';

        return array(
            'title'        => $default['title'] ?? '',
            'description'  => $default['description'] ?? '',
            'subject'      => '' !== trim( $subject ) ? $subject : ( $default['subject'] ?? '' ),
            'heading'      => '' !== trim( $heading ) ? $heading : ( $default['heading'] ?? '' ),
            'body'         => '' !== trim( wp_strip_all_tags( $body ) ) ? $body : ( $default['body'] ?? '' ),
            'button_label' => '' !== trim( $button ) ? $button : ( $default['button_label'] ?? '' ),
        );
    }

    /**
     * Get placeholder replacements.
     *
     * @param WC_Order    $order           Order object.
     * @param array|false $deposit_details Deposit details.
     * @param bool        $formatted       Whether to format amounts with wc_price().
     * @return array<string,string>
     */
    public static function get_placeholder_values( $order, $deposit_details = false, $formatted = true ) {
        $first_name = $order ? $order->get_billing_first_name() : '';
        $total      = $deposit_details ? floatval( $deposit_details['total_amount'] ?? 0 ) : 0;
        $deposit    = $deposit_details ? floatval( $deposit_details['deposit_amount'] ?? 0 ) : 0;
        $paid       = $deposit_details ? floatval( $deposit_details['amount_paid'] ?? 0 ) : 0;
        $balance    = $deposit_details ? floatval( $deposit_details['balance_due'] ?? 0 ) : 0;

        if ( $formatted ) {
            $total   = wc_price( $total );
            $deposit = wc_price( $deposit );
            $paid    = wc_price( $paid );
            $balance = wc_price( $balance );
        } else {
            $total   = wc_format_decimal( $total, wc_get_price_decimals() );
            $deposit = wc_format_decimal( $deposit, wc_get_price_decimals() );
            $paid    = wc_format_decimal( $paid, wc_get_price_decimals() );
            $balance = wc_format_decimal( $balance, wc_get_price_decimals() );
        }

        return array(
            '{customer_first_name}' => $first_name,
            '{order_number}'        => $order ? $order->get_order_number() : '',
            '{order_date}'          => $order && $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
            '{total_amount}'        => $total,
            '{deposit_amount}'      => $deposit,
            '{amount_paid}'         => $paid,
            '{balance_due}'         => $balance,
            '{pay_balance_url}'     => $order ? $order->get_checkout_payment_url() : '',
            '{site_title}'          => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
        );
    }

    /**
     * Render email body content from editable template fields.
     *
     * @param string     $template_id Template key.
     * @param WC_Order   $order       Order object.
     * @param array|bool $deposit     Deposit details.
     * @param bool       $plain_text  Whether to render plain text.
     * @return string
     */
    public static function render_email_body( $template_id, $order, $deposit = false, $plain_text = false ) {
        $config = self::get_email_template_config( $template_id );
        $body   = $config['body'] ?? '';

        $body = strtr( $body, self::get_placeholder_values( $order, $deposit, ! $plain_text ) );
        $body = strtr(
            $body,
            array(
                '[deposit_summary]'   => self::render_deposit_summary_block( $template_id, $order, $deposit, $plain_text ),
                '[pay_balance_button]' => self::render_pay_balance_block( $template_id, $order, $plain_text ),
            )
        );

        if ( $plain_text ) {
            $body = str_replace( array( "\r\n", "\r" ), "\n", $body );
            return trim( preg_replace( "/\n{3,}/", "\n\n", wp_strip_all_tags( $body ) ) );
        }

        return wpautop( wp_kses_post( $body ) );
    }

    /**
     * Render summary block token for the email body.
     *
     * @param string     $template_id Template key.
     * @param WC_Order   $order       Order object.
     * @param array|bool $deposit     Deposit details.
     * @param bool       $plain_text  Whether to render plain text.
     * @return string
     */
    private static function render_deposit_summary_block( $template_id, $order, $deposit, $plain_text = false ) {
        if ( ! $order ) {
            return '';
        }

        if ( $plain_text ) {
            $lines   = array();
            $lines[] = sprintf( __( 'Order Number: #%s', 'advanced-partial-payment' ), $order->get_order_number() );

            if ( $deposit ) {
                if ( 'payment_complete' === $template_id ) {
                    $lines[] = sprintf( __( 'Total Paid: %s', 'advanced-partial-payment' ), wc_price( $deposit['total_amount'] ?? 0 ) );
                } elseif ( 'balance_due' === $template_id ) {
                    $lines[] = sprintf( __( 'Total Amount: %s', 'advanced-partial-payment' ), wc_price( $deposit['total_amount'] ?? 0 ) );
                    $lines[] = sprintf( __( 'Amount Paid: %s', 'advanced-partial-payment' ), wc_price( $deposit['amount_paid'] ?? 0 ) );
                    $lines[] = sprintf( __( 'Balance Due: %s', 'advanced-partial-payment' ), wc_price( $deposit['balance_due'] ?? 0 ) );
                } else {
                    $lines[] = sprintf( __( 'Total Amount: %s', 'advanced-partial-payment' ), wc_price( $deposit['total_amount'] ?? 0 ) );
                    $lines[] = sprintf( __( 'Deposit Paid: %s', 'advanced-partial-payment' ), wc_price( $deposit['deposit_amount'] ?? 0 ) );
                    $lines[] = sprintf( __( 'Balance Due: %s', 'advanced-partial-payment' ), wc_price( $deposit['balance_due'] ?? 0 ) );
                }
            }

            return implode( "\n", $lines );
        }

        ob_start();
        ?>
        <table cellspacing="0" cellpadding="6" style="width:100%;border:1px solid #e5e5e5;margin-bottom:20px;" border="1">
            <tr>
                <th style="text-align:left;padding:12px;"><?php esc_html_e( 'Order Number', 'advanced-partial-payment' ); ?></th>
                <td style="padding:12px;">#<?php echo esc_html( $order->get_order_number() ); ?></td>
            </tr>
            <?php if ( $deposit ) : ?>
                <?php if ( 'payment_complete' === $template_id ) : ?>
                    <tr>
                        <th style="text-align:left;padding:12px;"><?php esc_html_e( 'Total Paid', 'advanced-partial-payment' ); ?></th>
                        <td style="padding:12px;"><?php echo wp_kses_post( wc_price( $deposit['total_amount'] ?? 0 ) ); ?></td>
                    </tr>
                <?php elseif ( 'balance_due' === $template_id ) : ?>
                    <tr>
                        <th style="text-align:left;padding:12px;"><?php esc_html_e( 'Total Amount', 'advanced-partial-payment' ); ?></th>
                        <td style="padding:12px;"><?php echo wp_kses_post( wc_price( $deposit['total_amount'] ?? 0 ) ); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:12px;"><?php esc_html_e( 'Amount Paid', 'advanced-partial-payment' ); ?></th>
                        <td style="padding:12px;"><?php echo wp_kses_post( wc_price( $deposit['amount_paid'] ?? 0 ) ); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:12px;color:#e74c3c;font-weight:bold;"><?php esc_html_e( 'Balance Due', 'advanced-partial-payment' ); ?></th>
                        <td style="padding:12px;color:#e74c3c;font-weight:bold;"><?php echo wp_kses_post( wc_price( $deposit['balance_due'] ?? 0 ) ); ?></td>
                    </tr>
                <?php else : ?>
                    <tr>
                        <th style="text-align:left;padding:12px;"><?php esc_html_e( 'Total Amount', 'advanced-partial-payment' ); ?></th>
                        <td style="padding:12px;"><?php echo wp_kses_post( wc_price( $deposit['total_amount'] ?? 0 ) ); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:12px;"><?php esc_html_e( 'Deposit Paid', 'advanced-partial-payment' ); ?></th>
                        <td style="padding:12px;"><?php echo wp_kses_post( wc_price( $deposit['deposit_amount'] ?? 0 ) ); ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:12px;color:#e74c3c;font-weight:bold;"><?php esc_html_e( 'Balance Due', 'advanced-partial-payment' ); ?></th>
                        <td style="padding:12px;color:#e74c3c;font-weight:bold;"><?php echo wp_kses_post( wc_price( $deposit['balance_due'] ?? 0 ) ); ?></td>
                    </tr>
                <?php endif; ?>
            <?php endif; ?>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render pay balance button token for the email body.
     *
     * @param string   $template_id Template key.
     * @param WC_Order $order       Order object.
     * @param bool     $plain_text  Whether to render plain text.
     * @return string
     */
    private static function render_pay_balance_block( $template_id, $order, $plain_text = false ) {
        if ( 'balance_due' !== $template_id || ! $order ) {
            return '';
        }

        $config = self::get_email_template_config( $template_id );
        $label  = $config['button_label'] ?: __( 'Pay Balance Now', 'advanced-partial-payment' );
        $url    = $order->get_checkout_payment_url();

        if ( $plain_text ) {
            return sprintf( __( '%1$s: %2$s', 'advanced-partial-payment' ), $label, $url );
        }

        return sprintf(
            '<p><a href="%1$s" style="background:#2271b1;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">%2$s</a></p>',
            esc_url( $url ),
            esc_html( $label )
        );
    }
}
