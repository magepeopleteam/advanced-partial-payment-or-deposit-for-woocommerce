<?php

if( ! defined( 'ABSPATH' ) ){
	exit; // Exit if accessed directly
}

if( ! class_exists( 'MEPP_Advance_Deposits_Email_Full_Payment' ) ):
	
	/**
	 * @brief Full Payment Email
	 *
	 * An email sent to the admin when an order is fully paid for.
	 *
	 */
	class MEPP_Advance_Deposits_Email_Full_Payment extends WC_Email{
		
		/**
		 * Constructor
		 */
		function __construct(){
			
			$this->id = 'full_payment';
			$this->title = esc_html__( 'Full Payment' , 'advanced-partial-payment-or-deposit-for-woocommerce' );
			$this->description = esc_html__( 'Full payment emails are sent when an order is fully paid.' , 'advanced-partial-payment-or-deposit-for-woocommerce' );
			
			$this->heading = esc_html__( 'Order fully paid' , 'advanced-partial-payment-or-deposit-for-woocommerce' );
			$this->subject = esc_html__( '[{site_title}] Order fully paid ({order_number}) - {order_date}' , 'advanced-partial-payment-or-deposit-for-woocommerce' );
			
			$this->template_html = 'emails/admin-order-fully-paid.php';
			$this->template_plain = 'emails/plain/admin-order-fully-paid.php';
            $partial_payment_status = mepp_partial_payment_complete_order_status();
			// Triggers for this email
			add_action( 'woocommerce_order_status_'.$partial_payment_status.'_to_processing_notification' , array( $this , 'trigger' ) );
			add_action( 'woocommerce_order_status_'.$partial_payment_status.'_to_completed_notification' , array( $this , 'trigger' ) );

			// Call parent constructor
			parent::__construct();
			
			$this->template_base = MEPP_TEMPLATE_PATH;
			
			// Other settings
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
			

		}
		
		/**
		 * trigger function.
		 *
		 * @access public
         *
		 * @return void
		 */
		function trigger( $order_id ){
			
			if( $order_id ){
				$this->object = wc_get_order( $order_id );

                $this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
                $this->placeholders['{order_number}'] = $this->object->get_order_number();

			}
			
			if( ! $this->is_enabled() || ! $this->get_recipient() ){
				return;
			}
			
			$this->send( $this->get_recipient() , $this->get_subject() , $this->get_content() , $this->get_headers() , $this->get_attachments() );
		}
		
		/**
		 * get_content_html function.
		 *
		 * @access public
		 * @return string
		 */
		function get_content_html(){
			return wc_get_template_html( $this->template_html , array(
				'order' => $this->object ,
				'email_heading' => $this->get_heading() ,
                'additional_content' => version_compare( WOOCOMMERCE_VERSION, '3.7.0' ,'<') ?'' : $this->get_additional_content() ,                'sent_to_admin' => true ,
				'plain_text' => false ,
				'email' => $this
			) , '' , $this->template_base );
		}
		
		/**
		 * get_content_plain function.
		 *
		 * @access public
		 * @return string
		 */
		function get_content_plain(){
			
			return wc_get_template_html( $this->template_plain , array(
				'order' => $this->object ,
				'email_heading' => $this->get_heading() ,
                'additional_content' => version_compare( WOOCOMMERCE_VERSION, '3.7.0' ,'<') ?'' : $this->get_additional_content() ,                'sent_to_admin' => true ,
				'plain_text' => false ,
				'email' => $this
			) , '' , $this->template_base );
		}
		
		/**
		 * Initialise Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' => esc_html__( 'Enable/Disable' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					'type' => 'checkbox' ,
					'label' => esc_html__( 'Enable this email notification' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					'default' => 'yes'
				) ,
				'recipient' => array(
					'title' => esc_html__( 'Recipient(s)' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					'type' => 'text' ,
					'description' => sprintf( wp_kses(__( 'Enter recipients (comma separated) for this email. Defaults to <code>%s</code>.' , 'advanced-partial-payment-or-deposit-for-woocommerce' ), array('code'=>array()))  , esc_attr( get_option( 'admin_email' ) ) ) ,
					'placeholder' => '' ,
					'default' => ''
				) ,
				'subject' => array(
					'title' => esc_html__( 'Subject' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					'type' => 'text' ,
					'description' => sprintf( wp_kses(__( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.' , 'advanced-partial-payment-or-deposit-for-woocommerce' ), array('code'=>array()))  , $this->subject ) ,
					'placeholder' => '' ,
					'default' => ''
				) ,
				'heading' => array(
					'title' => esc_html__( 'Email Heading' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					'type' => 'text' ,
					'description' => sprintf( wp_kses(__( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.' , 'advanced-partial-payment-or-deposit-for-woocommerce' ), array('code'=>array()))  , $this->heading ) ,
					'placeholder' => '' ,
					'default' => ''
				) ,
				'email_type' => array(
					'title' => esc_html__( 'Email type' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					'type' => 'select' ,
					'description' => esc_html__( 'Choose which format of email to send.' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					'default' => 'html' ,
					'class' => 'email_type' ,
					'options' => array(
						'plain' => esc_html__( 'Plain text' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
						'html' => esc_html__( 'HTML' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
						'multipart' => esc_html__( 'Multipart' , 'advanced-partial-payment-or-deposit-for-woocommerce' ) ,
					)
				)
			);
		}
	}

endif;

return new MEPP_Advance_Deposits_Email_Full_Payment();
