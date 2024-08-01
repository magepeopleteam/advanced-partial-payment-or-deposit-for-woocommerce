<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (!class_exists('MEPP_Advance_Deposits_Email_Customer_Deposit_Paid')) :
    /**
     * Customer Partially Paid Email
     *
     * An email sent to the customer when a new order is partially paid.
     *
     */
    class MEPP_Advance_Deposits_Email_Customer_Deposit_Paid extends WC_Email
    {
        public  $partial_payment = false;

        /**
         * Constructor
         */
        function __construct()
        {

            $this->id = 'customer_deposit_partially_paid';
            $this->title = esc_html__('Deposit Payment Received', 'advanced-partial-payment-or-deposit-for-woocommerce');
            $this->customer_email = true;

            $this->description = esc_html__('This is an order notification sent to the customer after deposit is paid, containing order details and a link to pay the remaining balance.', 'advanced-partial-payment-or-deposit-for-woocommerce');


            $this->template_html = 'emails/customer-order-partially-paid.php';
            $this->template_plain = 'emails/plain/customer-order-partially-paid.php';

            // Triggers for this email

            add_action('woocommerce_order_status_processing_notification',array($this,'maybe_trigger'));
            add_action('woocommerce_order_status_completed_notification',array($this,'maybe_trigger'));

            // Call parent constructor
            parent::__construct();
            $this->template_base = MEPP_TEMPLATE_PATH;
        }


        function maybe_trigger($order_id, $order = false){
            if ($order_id && !is_a($order, 'WC_Order')) {
                $order = wc_get_order($order_id);
                if($order && $order->get_type() === 'mepp_payment' && $order->get_meta('_mepp_payment_type', true)  === 'deposit' ){
                    $parent = wc_get_order($order->get_parent_id());
                    $this->partial_payment = $order;
                    $this->trigger($parent->get_id());
                }
            }
        }

        /**
         * Trigger the sending of this email.
         *
         * @param int $order_id The order ID.
         * @param WC_Order $order Order object.
         */
        public function trigger($order_id, $order = false)
        {

            $order = wc_get_order($order_id);

            if ($order) {
                if ($order->get_type() === 'mepp_payment') return;
                $this->object = $order;
                if(!$this->partial_payment) {
                    //get the partial payment manually, this is suitable when the trigger is not order status change of
                    // completed status , such as email preview plugins.
                    $payments = mepp_get_order_partial_payments($order->get_id(), array(
                        'meta_query' => array(
                            array('key' => '_mepp_payment_type',
                                'value' => 'deposit',
                                'compare' => '=',
                            ),
                        )
                    ));

                    $this->partial_payment = $payments[0]; // it will always be one result as a deposit payment
                }

                $this->object = $order;
                $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
                $this->placeholders['{partial_payment_number}'] = $this->partial_payment->get_order_number();
                $this->object->update_meta_data('_mepp_current_payment',$this->partial_payment->get_id());
                if (in_array($this->object->get_status(), mepp_valid_parent_statuses_for_partial_payment()) && get_option('mepp_remaining_payable', 'yes') === 'yes') {
                    $payment_link_text = get_option('mepp_payment_link_text', esc_html__('Payment Link', 'advanced-partial-payment-or-deposit-for-woocommerce'));
                    if(empty($payment_link_text)){
                        $payment_link_text = esc_html__('Payment Link', 'advanced-partial-payment-or-deposit-for-woocommerce');
                    }
                    $this->placeholders['{mepp_payment_link}'] = '<a href="' . esc_url($this->object->get_checkout_payment_url()) . '">' . $payment_link_text . '</a>';
                } else {
                    $this->placeholders['{mepp_payment_link}'] = '';
                }
                $this->recipient = $this->object->get_billing_email();

                if (!$this->is_enabled() || !$this->get_recipient()) {
                    return;
                }

                $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                $this->object->delete_meta_data('_mepp_current_payment');

            }
        }


        /**
         * get_content_html function.
         *
         * @access public
         */
        function get_content_html()
        {

            return wc_get_template_html($this->template_html, array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'email_text' => $this->get_email_text(),
                'payment_text' => $this->get_payment_text(),
                'additional_content' => version_compare(WOOCOMMERCE_VERSION, '3.7.0', '<') ? '' : $this->get_additional_content(), 'sent_to_admin' => false,
                'plain_text' => false,
                'email' => $this,
            ), '', $this->template_base);
        }

        /**
         * get_content_plain function.
         *
         * @access public
         */
        function get_content_plain()
        {

            return wc_get_template_html($this->template_html, array(
                'order' => $this->object,
                'email_heading' => $this->get_heading(),
                'additional_content' => version_compare(WOOCOMMERCE_VERSION, '3.7.0', '<') ? '' : $this->get_additional_content(), 'sent_to_admin' => false,
                'email_text' => $this->get_email_text(),
                'payment_text' => $this->get_payment_text(),
                'plain_text' => true,
                'email' => $this,
            ), '', $this->template_base);
        }


        public function get_default_subject()
        {
            return esc_html__('Your {site_title} order receipt from {order_date}', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }

        /**
         * Get email heading.
         *
         * @return string
         * @since  3.1.0
         */
        public function get_default_heading()
        {
            return esc_html__('Thank you for your order', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }

        public function get_default_email_text()
        {
            return esc_html__("Your deposit has been received and your order is now being processed. Your order details are shown below for your reference:", 'advanced-partial-payment-or-deposit-for-woocommerce');

        }

        public function get_default_payment_text()
        {
            return esc_html__('To pay the remaining balance, please visit this {mepp_payment_link}', 'advanced-partial-payment-or-deposit-for-woocommerce');
        }

        function get_email_text()
        {
            $text = $this->get_option('email_text', $this->get_default_email_text());
            return $this->format_string($text);
        }

        function get_payment_text()
        {
            $text = $this->get_option('payment_text', $this->get_default_payment_text());
            return $this->format_string($text);
        }

        public function init_form_fields()
        {
            /* translators: %s: list of placeholders */
            $placeholder_text = sprintf(wp_kses(__('Available placeholders: %s', 'woocommerce'), array('code' => array())), '<code>' . esc_html(implode('</code>, <code>', array_keys($this->placeholders))) . '</code>');
            $this->form_fields = array(
                'enabled' => array(
                    'title' => esc_html__('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => esc_html__('Enable this email notification', 'woocommerce'),
                    'default' => 'yes',
                ),
                'subject' => array(
                    'title' => esc_html__('Subject', 'woocommerce'),
                    'type' => 'text',
                    'description' => $placeholder_text,
                    'desc_tip' => true,
                    'placeholder' => $this->get_default_subject(),
                    'default' => $this->get_default_subject(),
                ),
                'heading' => array(
                    'title' => esc_html__('Email heading', 'woocommerce'),
                    'type' => 'text',
                    'desc_tip' => true,
                    'description' => $placeholder_text,
                    'placeholder' => $this->get_default_heading(),
                    'default' => $this->get_default_heading(),
                ),
                'email_text' => array(
                    'title' => esc_html__('Email text', 'woocommerce'),
                    'placeholder' => $this->get_default_email_text(),
                    'default' => $this->get_default_email_text(),
                    'description' => esc_html__('Text to appear as email content message', 'advanced-partial-payment-or-deposit-for-woocommerce') . ' ' . $placeholder_text,
                    'css' => 'width:400px; height: 75px;',
                    'type' => 'textarea',
                    'desc_tip' => true,
                ),
                'payment_text' => array(
                    'title' => esc_html__('Payment text', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'description' => esc_html__('Text to appear with payment link', 'advanced-partial-payment-or-deposit-for-woocommerce') . '. ' . $placeholder_text,
                    'placeholder' => $this->get_default_payment_text(),
                    'default' => $this->get_default_payment_text(),
                    'css' => 'width:400px; height: 75px;',
                    'type' => 'textarea',
                    'desc_tip' => true,
                ),
                'additional_content' => array(
                    'title' => esc_html__('Additional content', 'woocommerce'),
                    'description' => esc_html__('Text to appear below the main email content.', 'woocommerce') . ' ' . $placeholder_text . ' {mepp_payment_link}',
                    'placeholder' => esc_html__('N/A', 'woocommerce'),
                    'css' => 'width:400px; height: 75px;',
                    'type' => 'textarea',
                    'default' => $this->get_default_additional_content(),
                    'desc_tip' => true,
                ),
                'email_type' => array(
                    'title' => esc_html__('Email type', 'woocommerce'),
                    'type' => 'select',
                    'description' => esc_html__('Choose which format of email to send.', 'woocommerce'),
                    'default' => 'html',
                    'class' => 'email_type wc-enhanced-select',
                    'options' => $this->get_email_type_options(),
                    'desc_tip' => true,
                ),
            );
        }

    }

endif;

return new MEPP_Advance_Deposits_Email_Customer_Deposit_Paid();
