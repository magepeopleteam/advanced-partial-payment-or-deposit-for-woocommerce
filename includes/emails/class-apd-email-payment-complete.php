<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Full Payment Complete Email.
 */
class APD_Email_Payment_Complete extends WC_Email {

    public function __construct() {
        $config = APD_Emails::get_email_template_config( 'payment_complete' );

        $this->id             = 'apd_payment_complete';
        $this->title          = __( 'Full Payment Complete', 'advanced-partial-payment' );
        $this->description    = __( 'Sent to the customer when the full balance has been paid.', 'advanced-partial-payment' );
        $this->heading        = $config['heading'];
        $this->subject        = $config['subject'];
        $this->template_base  = APD_PLUGIN_DIR . 'templates/';
        $this->template_html  = 'emails/payment-complete.php';
        $this->template_plain = 'emails/plain/payment-complete.php';
        $this->customer_email = true;

        add_action( 'apd_email_payment_complete_notification', array( $this, 'trigger' ), 10, 1 );

        parent::__construct();
    }

    public function trigger( $order_id ) {
        if ( $order_id ) {
            $this->object    = wc_get_order( $order_id );
            $this->recipient = $this->object->get_billing_email();
            $details         = APD_Order::get_deposit_details( $this->object );

            $this->placeholders = array_merge(
                $this->placeholders,
                APD_Emails::get_placeholder_values( $this->object, $details, false )
            );
        }

        if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
            return;
        }

        $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
    }

    public function get_content_html() {
        $order   = $this->object;
        $details = APD_Order::get_deposit_details( $order );
        return wc_get_template_html(
            $this->template_html,
            array(
                'order'         => $order,
                'deposit'       => $details,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this,
            ),
            '',
            $this->template_base
        );
    }

    public function get_content_plain() {
        $order   = $this->object;
        $details = APD_Order::get_deposit_details( $order );
        return wc_get_template_html(
            $this->template_plain,
            array(
                'order'         => $order,
                'deposit'       => $details,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email'         => $this,
            ),
            '',
            $this->template_base
        );
    }
}
