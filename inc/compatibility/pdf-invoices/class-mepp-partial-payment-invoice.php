<?php /** @noinspection PhpMultipleClassDeclarationsInspection */

namespace WPO\WC\PDF_Invoices\Documents;



if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if(!class_exists('MEPP_Partial_Payment_Invoice')):

    class MEPP_Partial_Payment_Invoice extends Order_Document_Methods {
        /**
         * Init/load the order object.
         *
         * @param  int|object|\WC_Order $order Order to init.
         */
        public function __construct( $order = 0 ) {
            // set properties
            $this->type		= 'partial_payment_invoice';

            $this->title	= esc_html__( 'Partial Payment Invoice', 'advanced-partial-payment-or-deposit-for-woocommerce' );
            $this->icon		= WPO_WCPDF()->plugin_url() . "/assets/images/invoice.png";

            // Call parent constructor
            parent::__construct( $order );
        }

        public function use_historical_settings() {
            $document_settings = get_option( 'wpo_wcpdf_documents_settings_'.$this->get_type() );
            // this setting is inverted on the frontend so that it needs to be actively/purposely enabled to be used
            if (!empty($document_settings) && isset($document_settings['use_latest_settings'])) {
                $use_historical_settings = false;
            } else {
                $use_historical_settings = true;
            }
            return apply_filters( 'wpo_wcpdf_document_use_historical_settings', $use_historical_settings, $this );
        }


        public function get_invoice_date() {
            if ( $invoice_date = $this->get_date('partial_payment_invoice') ) {
                return $invoice_date->date_i18n( apply_filters( 'wpo_wcpdf_date_format', wc_date_format(), $this ) );
            } else {
                return '';
            }
        }

        public function get_title() {
            // override/not using $this->title to allow for language switching!
            return apply_filters( "wpo_wcpdf_{$this->slug}_title", esc_html__( 'Partial Payment Invoice', 'advanced-partial-payment-or-deposit-for-woocommerce' ), $this );
        }

        public function init() {
            // store settings in order
            if ( !empty( $this->order ) ) {
                $common_settings = WPO_WCPDF()->settings->get_common_document_settings();
                $document_settings = get_option( 'wpo_wcpdf_documents_settings_'.$this->get_type() );
                $settings = (array) $document_settings + (array) $common_settings;
                $this->order->update_meta_data("_wcpdf_{$this->slug}_settings", $settings );
            }

            $this->set_date( current_time( 'timestamp', true ) );
            $this->init_number();
        }

        public function exists() {
            return !empty( $this->data['number'] );
        }


        public function get_invoice_number() {
            // Call the woocommerce_invoice_number filter and let third-party plugins set a number.
            // Default is null, so we can detect whether a plugin has set the invoice number
            $third_party_invoice_number = apply_filters( 'woocommerce_invoice_number', null, $this->order_id );
            if ($third_party_invoice_number !== null) {
                return $third_party_invoice_number;
            }

            if ( $invoice_number = $this->get_number('partial_payment_invoice') ) {

                return $invoice_number->get_formatted();
            } else {
                return '';
            }
        }


        public function init_number() {
            // If a third-party plugin claims to generate invoice numbers, trigger this instead
            if ( apply_filters( 'woocommerce_invoice_number_by_plugin', false ) || apply_filters( 'wpo_wcpdf_external_invoice_number_enabled', false, $this ) ) {
                $invoice_number = apply_filters( 'woocommerce_generate_invoice_number', null, $this->order );
                $invoice_number = apply_filters( 'wpo_wcpdf_external_invoice_number', $invoice_number, $this );
                if ( is_numeric($invoice_number) || $invoice_number instanceof Document_Number ) {
                    $this->set_number( $invoice_number );
                } else {
                    // invoice number is not numeric, treat as formatted
                    // try to extract meaningful number data
                    $formatted_number = $invoice_number;
                    $number = (int) preg_replace('/\D/', '', $invoice_number);
                    $invoice_number = compact( 'number', 'formatted_number' );
                    $this->set_number( $invoice_number );
                }
                return $invoice_number;
            }

            $number_store_method = WPO_WCPDF()->settings->get_sequential_number_store_method();
            $number_store = new Sequential_Number_Store( 'partial_payment_invoice_number', $number_store_method );
            // reset invoice number yearly
            if ( isset( $this->settings['reset_number_yearly'] ) ) {
                $current_year = date("Y");
                $last_number_year = $number_store->get_last_date('Y');
                // check if we need to reset
                if ( $current_year != $last_number_year ) {
                    $number_store->set_next( 1 );
                }
            }

            $invoice_date = $this->get_date();

            $invoice_number = $number_store->increment( $this->order_id, $invoice_date->date_i18n( 'Y-m-d H:i:s' ) );

            $this->set_number( $invoice_number );

            return $invoice_number;
        }

        public function get_filename( $context = 'download', $args = array() ) {
            $order_count = isset($args['order_ids']) ? count($args['order_ids']) : 1;

            $name = _n( 'partial_payment_invoice', 'partial_payment_invoices', $order_count, 'woocommerce-pdf-invoices-packing-slips' );

            if ( $order_count == 1 ) {
                if ( isset( $this->settings['display_number'] ) ) {
                    $suffix = (string) $this->get_number();
                } else {
                    if ( empty( $this->order ) ) {
                        $order = wc_get_order ( $args['order_ids'][0] );
                        $suffix = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : '';
                    } else {
                        $suffix = method_exists( $this->order, 'get_order_number' ) ? $this->order->get_order_number() : '';
                    }
                }
            } else {
                $suffix = date('Y-m-d'); // 2020-11-11
            }

            $filename = $name . '-' . $suffix . '.pdf';

            // Filter filename
            $order_ids = isset($args['order_ids']) ? $args['order_ids'] : array( $this->order_id );
            $filename = apply_filters( 'wpo_wcpdf_filename', $filename, $this->get_type(), $order_ids, $context );

            // sanitize filename (after filters to prevent human errors)!
            return sanitize_file_name( $filename );
        }


        /**
         * Initialise settings
         */
        public function init_settings() {
            // Register settings.
            $page = $option_group = $option_name = 'wpo_wcpdf_documents_settings_partial_payment_invoice';

            $settings_fields = array(
                array(
                    'type'			=> 'section',
                    'id'			=> 'partial_payment_invoice',
                    'title'			=> '',
                    'callback'		=> 'section',
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'enabled',
                    'title'			=> esc_html__( 'Enable', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'		=> $option_name,
                        'id'				=> 'enabled',
                    )
                ),

                array(
                    'type'			=> 'setting',
                    'id'			=> 'display_shipping_address',
                    'title'			=> esc_html__( 'Display shipping address', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'		=> $option_name,
                        'id'				=> 'display_shipping_address',
                        'description'		=> esc_html__( 'Display shipping address (in addition to the default billing address) if different from billing address', 'woocommerce-pdf-invoices-packing-slips' ),
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'display_email',
                    'title'			=> esc_html__( 'Display email address', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'		=> $option_name,
                        'id'				=> 'display_email',
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'display_phone',
                    'title'			=> esc_html__( 'Display phone number', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'		=> $option_name,
                        'id'				=> 'display_phone',
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'display_date',
                    'title'			=> esc_html__( 'Display invoice date', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'		=> $option_name,
                        'id'				=> 'display_date',
                        'value' 			=> 'invoice_date',
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'display_number',
                    'title'			=> esc_html__( 'Display invoice number', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'		=> $option_name,
                        'id'				=> 'display_number',
                        'value' 			=> 'invoice_number',
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'next_invoice_number',
                    'title'			=> esc_html__( 'Next invoice number (without prefix/suffix etc.)', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'next_number_edit',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'store'			=> 'partial_payment_invoice_number',
                        'size'			=> '10',
                        'description'	=> esc_html__( 'This is the number that will be used for the next document. By default, numbering starts from 1 and increases for every new document. Note that if you override this and set it lower than the current/highest number, this could create duplicate numbers!', 'woocommerce-pdf-invoices-packing-slips' ),
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'number_format',
                    'title'			=> esc_html__( 'Number format', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'multiple_text_input',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'			=> $option_name,
                        'id'					=> 'number_format',
                        'fields'				=> array(
                            'prefix'			=> array(
                                'placeholder'	=> esc_html__( 'Prefix' , 'woocommerce-pdf-invoices-packing-slips' ),
                                'size'			=> 20,
                                'description'	=> esc_html__( 'to use the invoice year and/or month, use [partial_payment_invoice_year] or [partial_payment_invoice_month] respectively' , 'woocommerce-pdf-invoices-packing-slips' ),
                            ),
                            'suffix'			=> array(
                                'placeholder'	=> esc_html__( 'Suffix' , 'woocommerce-pdf-invoices-packing-slips' ),
                                'size'			=> 20,
                                'description'	=> '',
                            ),
                            'padding'			=> array(
                                'placeholder'	=> esc_html__( 'Padding' , 'woocommerce-pdf-invoices-packing-slips' ),
                                'size'			=> 20,
                                'type'			=> 'number',
                                'description'	=> esc_html__( 'enter the number of digits here - enter "6" to display 42 as 000042' , 'woocommerce-pdf-invoices-packing-slips' ),
                            ),
                        ),
                        'description'			=> esc_html__( 'note: if you have already created a custom invoice number format with a filter, the above settings will be ignored' , 'woocommerce-pdf-invoices-packing-slips' ),
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'reset_number_yearly',
                    'title'			=> esc_html__( 'Reset invoice number yearly', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'		=> $option_name,
                        'id'				=> 'reset_number_yearly',
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'my_account_buttons',
                    'title'			=> esc_html__( 'Allow My Account invoice download', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'select',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'	=> $option_name,
                        'id'			=> 'my_account_buttons',
                        'options' 		=> array(
                            'available'	=> esc_html__( 'Only when an invoice is already created/emailed' , 'woocommerce-pdf-invoices-packing-slips' ),
                            'custom'	=> esc_html__( 'Only for specific order statuses (define below)' , 'woocommerce-pdf-invoices-packing-slips' ),
                            'always'	=> esc_html__( 'Always' , 'woocommerce-pdf-invoices-packing-slips' ),
                            'never'		=> esc_html__( 'Never' , 'woocommerce-pdf-invoices-packing-slips' ),
                        ),
                        'custom'		=> array(
                            'type'		=> 'multiple_checkboxes',
                            'args'		=> array(
                                'option_name'	=> $option_name,
                                'id'			=> 'my_account_restrict',
                                'fields'		=> $this->get_wc_order_status_list(),
                            ),
                        ),
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'invoice_number_column',
                    'title'			=> esc_html__( 'Enable invoice number column in the orders list', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'	=> $option_name,
                        'id'			=> 'invoice_number_column',
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'disable_free',
                    'title'			=> esc_html__( 'Disable for free products', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'	=> $option_name,
                        'id'			=> 'disable_free',
                        'description'	=> esc_html__( "Disable automatic creation/attachment when only free products are ordered", 'woocommerce-pdf-invoices-packing-slips' ),
                    )
                ),
                array(
                    'type'			=> 'setting',
                    'id'			=> 'use_latest_settings',
                    'title'			=> esc_html__( 'Always use most current settings', 'woocommerce-pdf-invoices-packing-slips' ),
                    'callback'		=> 'checkbox',
                    'section'		=> 'partial_payment_invoice',
                    'args'			=> array(
                        'option_name'	=> $option_name,
                        'id'			=> 'use_latest_settings',
                        'description'	=> esc_html__( "When enabled, the document will always reflect the most current settings (such as footer text, document name, etc.) rather than using historical settings.", 'woocommerce-pdf-invoices-packing-slips' )
                            . "<br>"
                            . wp_kses(__( "<strong>Caution:</strong> enabling this will also mean that if you change your company name or address in the future, previously generated documents will also be affected.", 'woocommerce-pdf-invoices-packing-slips' ),array('strong' => array())),
                    )
                ),
            );

            // remove/rename some fields when invoice number is controlled externally
            if( apply_filters('woocommerce_invoice_number_by_plugin', false) ) {
                $remove_settings = array( 'next_invoice_number', 'number_format', 'reset_number_yearly' );
                foreach ($settings_fields as $key => $settings_field) {
                    if (in_array($settings_field['id'], $remove_settings)) {
                        unset($settings_fields[$key]);
                    } elseif ( $settings_field['id'] == 'display_number' ) {
                        // alternate description for invoice number
                        $invoice_number_desc = esc_html__( 'Invoice numbers are created by a third-party extension.', 'woocommerce-pdf-invoices-packing-slips' );
                        if ( $config_link = apply_filters( 'woocommerce_invoice_number_configuration_link', null ) ) {
                            $invoice_number_desc .= ' '.sprintf(wp_kses(__( 'Configure it <a href="%s">here</a>.', 'woocommerce-pdf-invoices-packing-slips' ),array('a' =>array('href'))), esc_attr( $config_link ) );
                        }
                        $settings_fields[$key]['args']['description'] = '<i>'.$invoice_number_desc.'</i>';
                    }
                }
            }

            // allow plugins to alter settings fields
            $settings_fields = apply_filters( 'wpo_wcpdf_settings_fields_documents_invoice', $settings_fields, $page, $option_group, $option_name );
            WPO_WCPDF()->settings->add_settings_fields( $settings_fields, $page, $option_group, $option_name );
            return;

        }

    }


    return new MEPP_Partial_Payment_Invoice();
endif;