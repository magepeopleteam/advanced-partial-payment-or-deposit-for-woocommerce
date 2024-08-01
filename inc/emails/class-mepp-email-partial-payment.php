<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('MEPP_Advance_Deposits_Email_Partial_Payment')):

    /**
     * @brief Partial Payment Email
     *
     * An email sent to the admin when a new order is partially paid.
     *
     */
    class MEPP_Advance_Deposits_Email_Partial_Payment extends WC_Email
    {

        public $partial_payment;

        /**
         * Constructor
         */
        function __construct()
        {

            $this->id = 'partial_payment';
            $this->title = esc_html__('Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce');
            $this->description = esc_html__('Partial payment emails are sent when an order is partially paid.', 'advanced-partial-payment-or-deposit-for-woocommerce');

            $this->heading = esc_html__('Order partially paid', 'advanced-partial-payment-or-deposit-for-woocommerce');
            $this->subject = esc_html__('[{site_title}] Order partially paid ({order_number}) - {order_date}', 'advanced-partial-payment-or-deposit-for-woocommerce');

            $this->template_html = 'emails/admin-order-partially-paid.php';
            $this->template_plain = 'emails/plain/admin-order-partially-paid.php';
            $partial_payment_status = mepp_partial_payment_complete_order_status();

            // Triggers for this email
            add_action('woocommerce_order_status_completed_notification', array($this, 'maybe_trigger'));

            // Call parent constructor
            parent::__construct();

            $this->template_base = MEPP_TEMPLATE_PATH;

            // Other settings
            $this->recipient = $this->get_option('recipient', get_option('admin_email'));

        }


        function maybe_trigger($order_id, $order = false)
        {
            if ($order_id && !is_a($order, 'WC_Order')) {
                $order = wc_get_order($order_id);
                if ($order && $order->get_type() === 'mepp_payment') {
                    $parent = wc_get_order($order->get_parent_id());
                    if($parent->get_status() !== 'processing' && $parent->get_status() !== 'completed'){
                        $this->partial_payment = $order;
                        $this->trigger($parent->get_id());
                    }
                }
            }
        }

        /**
         * trigger function.
         *
         * @access public
         * @return void
         */
        function trigger($order_id)
        {
            if (did_action('woocommerce_process_shop_order_meta')) return; // make sure not to trigger when admin save parent order from backend
            $order = wc_get_order($order_id);
            if ($order) {
                if ($order->get_type() === 'mepp_payment') return;
                $this->object = $order;

                $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();

                if (!$this->is_enabled() || !$this->get_recipient()) {
                    return;
                }

                $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());

            }
 }

        /**
         * get_content_html function.
         *
         * @access public
         * @return string
         */
        function get_content_html()
        {

            return wc_get_template_html($this->template_html, array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => version_compare(WOOCOMMERCE_VERSION, '3.7.0', '<') ? '' : $this->get_additional_content(), 'sent_to_admin' => true,
                'plain_text' => false,
                'email' => $this
            ), '', $this->template_base);
        }

        /**
         * get_content_plain function.
         *
         * @access public
         * @return string
         */
        function get_content_plain()
        {

            return wc_get_template_html($this->template_plain, array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => version_compare(WOOCOMMERCE_VERSION, '3.7.0', '<') ? '' : $this->get_additional_content(),
                'sent_to_admin' => true,
                'plain_text' => true,
                'email' => $this
            ), '', $this->template_base);
        }

        /**
         * Initialise Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => esc_html__('Enable/Disable', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => esc_html__('Enable this email notification', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'default' => 'yes'
                ),
                'recipient' => array(
                    'title' => esc_html__('Recipient(s)', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'description' => sprintf(wp_kses(__('Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.', 'advanced-partial-payment-or-deposit-for-woocommerce'), array('code' => array())), esc_attr(get_option('admin_email'))),
                    'placeholder' => '',
                    'default' => ''
                ),
                'subject' => array(
                    'title' => esc_html__('Subject', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'description' => sprintf(wp_kses(__('This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'advanced-partial-payment-or-deposit-for-woocommerce'), array('code' => array())), $this->subject),
                    'placeholder' => '',
                    'default' => ''
                ),
                'heading' => array(
                    'title' => esc_html__('Email Heading', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'text',
                    'description' => sprintf(wp_kses(__('This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'advanced-partial-payment-or-deposit-for-woocommerce'), array('code' => array())), $this->heading),
                    'placeholder' => '',
                    'default' => ''
                ),
                'email_type' => array(
                    'title' => esc_html__('Email type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'type' => 'select',
                    'description' => esc_html__('Choose which format of email to send.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'default' => 'html',
                    'class' => 'email_type',
                    'options' => array(
                        'plain' => esc_html__('Plain text', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'html' => esc_html__('HTML', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'multipart' => esc_html__('Multipart', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    )
                )
            );
        }
    }

endif;

return new MEPP_Advance_Deposits_Email_Partial_Payment();
