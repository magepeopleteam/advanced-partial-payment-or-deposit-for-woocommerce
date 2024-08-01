<?php


if (!defined('ABSPATH')) {
    exit;
}


if (!class_exists('MEPP_Advance_Deposits_Pdf_Invoices_compatibility')) {
    /***
     * @const  WPO_WCPDF_VERSION WPO_WCPDF()->version
     * @const  WOOCOMMERCE_VERSION WC()->version
     */
    class MEPP_Advance_Deposits_Pdf_Invoices_compatibility
    {

        function __construct()
        {

            add_filter('mepp_admin_partial_payment_actions', array($this, 'partial_payment_invoices'), 10, 2);
            add_filter('wpo_wcpdf_document_classes', array($this, 'document_classes'));
            add_filter('wpo_wcpdf_template_file', array($this, 'template_file'), 10, 2);
            add_filter('wpo_wcpdf_meta_box_actions', array($this, 'meta_box_actions'), 10);
            add_filter('wpo_wcpdf_listing_actions', array($this, 'listing_actions'), 10);
            add_action('add_meta_boxes_mepp_payment', array($this, 'add_meta_boxes'));
            add_action('admin_enqueue_scripts', array($this, 'backend_scripts_styles'));
            add_action('save_post', array($this, 'save_invoice_number_date'));

        }

        function listing_actions($actions)
        {

            if (isset($actions['partial_payment_invoice'])) unset($actions['partial_payment_invoice']);

            return $actions;
        }

        /**
         * Save invoice number
         */
        public function save_invoice_number_date($post_id)
        {
            $post_type = get_post_type($post_id);
            if ($post_type == 'mepp_payment') {
                // bail if this is not an actual 'Save order' action
                if (!isset($_POST['action']) || $_POST['action'] != 'editpost') {
                    return;
                }

                $order = wc_get_order($post_id);
                if ($invoice = wcpdf_get_document('partial_payment_invoice', $order, false)) {
                    if (!empty($_POST['wcpdf_invoice_date'])) {
                        $date = $_POST['wcpdf_invoice_date'];
                        $hour = !empty($_POST['wcpdf_invoice_date_hour']) ? $_POST['wcpdf_invoice_date_hour'] : '00';
                        $minute = !empty($_POST['wcpdf_invoice_date_minute']) ? $_POST['wcpdf_invoice_date_minute'] : '00';

                        // clean & sanitize input
                        $date = date('Y-m-d', strtotime($date));
                        $hour = sprintf('%02d', intval($hour));
                        $minute = sprintf('%02d', intval($minute));
                        $invoice_date = "{$date} {$hour}:{$minute}:00";

                        // set date
                        $invoice->set_date($invoice_date);
                    } elseif (empty($_POST['wcpdf_invoice_date']) && !empty($_POST['_wcpdf_invoice_number'])) {
                        $invoice->set_date(current_time('timestamp', true));
                    }

                    if (isset($_POST['_wcpdf_invoice_number'])) {
                        // sanitize
                        $invoice_number = sanitize_text_field($_POST['_wcpdf_invoice_number']);
                        // set number
                        $invoice->set_number($invoice_number);
                    }

                    $invoice->save();
                }
            }
        }

        /**
         * Load styles & scripts
         */
        public function backend_scripts_styles($hook)
        {

            global $post_type;
            if ($post_type == 'mepp_payment') {

                // STYLES
                wp_enqueue_style('thickbox');

                wp_enqueue_style(
                    'wpo-wcpdf-order-styles',
                    WPO_WCPDF()->plugin_url() . '/assets/css/order-styles.css',
                    array(),
                    WPO_WCPDF_VERSION
                );

                if (version_compare(WOOCOMMERCE_VERSION, '2.1') >= 0) {
                    // WC 2.1 or newer (MP6) is used: bigger buttons
                    wp_enqueue_style(
                        'wpo-wcpdf-order-styles-buttons',
                        WPO_WCPDF()->plugin_url() . '/assets/css/order-styles-buttons.css',
                        array(),
                        WPO_WCPDF_VERSION
                    );
                } else {
                    // legacy WC 2.0 styles
                    wp_enqueue_style(
                        'wpo-wcpdf-order-styles-buttons',
                        WPO_WCPDF()->plugin_url() . '/assets/css/order-styles-buttons-wc20.css',
                        array(),
                        WPO_WCPDF_VERSION
                    );
                }

                // SCRIPTS
                wp_enqueue_script(
                    'wpo-wcpdf',
                    WPO_WCPDF()->plugin_url() . '/assets/js/order-script.js',
                    array('jquery'),
                    WPO_WCPDF_VERSION
                );

                $bulk_actions = array();
                $documents = WPO_WCPDF()->documents->get_documents();
                foreach ($documents as $document) {
                    $bulk_actions[$document->get_type()] = "PDF " . $document->get_title();
                }
                $bulk_actions = apply_filters('wpo_wcpdf_bulk_actions', $bulk_actions);

                wp_localize_script(
                    'wpo-wcpdf',
                    'wpo_wcpdf_ajax',
                    array(
                        'ajaxurl' => admin_url('admin-ajax.php'), // URL to WordPress ajax handling page
                        'nonce' => wp_create_nonce('generate_wpo_wcpdf'),
                        'bulk_actions' => array_keys($bulk_actions),
                        'confirm_delete' => esc_html__('Are you sure you want to delete this document? This cannot be undone.', 'woocommerce-pdf-invoices-packing-slips'),
                    )
                );
            }

            // only load on our own settings page
            // maybe find a way to refer directly to WPO\WC\PDF_Invoices\Settings::$options_page_hook ?
            if ($hook == 'woocommerce_page_wpo_wcpdf_options_page' || $hook == 'settings_page_wpo_wcpdf_options_page' || (isset($_GET['page']) && $_GET['page'] == 'wpo_wcpdf_options_page')) {
                wp_enqueue_style(
                    'wpo-wcpdf-settings-styles',
                    WPO_WCPDF()->plugin_url() . '/assets/css/settings-styles.css',
                    array('woocommerce_admin_styles'),
                    WPO_WCPDF_VERSION
                );
                wp_add_inline_style('wpo-wcpdf-settings-styles', ".next-number-input.ajax-waiting {
				background-image: url(" . WPO_WCPDF()->plugin_url() . '/assets/images/spinner.gif' . ") !important;
				background-position: 95% 50% !important;
				background-repeat: no-repeat !important;
			}");

                // SCRIPTS
                wp_enqueue_script('wc-enhanced-select');
                wp_enqueue_script(
                    'wpo-wcpdf-admin',
                    WPO_WCPDF()->plugin_url() . '/assets/js/admin-script.js',
                    array('jquery', 'wc-enhanced-select'),
                    WPO_WCPDF_VERSION
                );
                wp_localize_script(
                    'wpo-wcpdf-admin',
                    'wpo_wcpdf_admin',
                    array(
                        'ajaxurl' => admin_url('admin-ajax.php'),
                    )
                );

                wp_enqueue_media();
                wp_enqueue_script(
                    'wpo-wcpdf-media-upload',
                    WPO_WCPDF()->plugin_url() . '/assets/js/media-upload.js',
                    array('jquery'),
                    WPO_WCPDF_VERSION
                );
            }
        }


        /**
         * Add the meta box on the single order page
         */
        public function add_meta_boxes()
        {
            // Invoice number & date
            add_meta_box(
                'wpo_wcpdf-data-input-box',
                esc_html__('PDF Invoice data', 'woocommerce-pdf-invoices-packing-slips'),
                array($this, 'data_input_box_content'),
                'mepp_payment',
                'normal',
                'default'
            );

        }

        /**
         * Document objects are created in order to check for existence and retrieve data,
         * but we don't want to store the settings for uninitialized documents.
         * Only use in frontend/backed (page requests), otherwise settings will never be stored!
         */
        public function disable_storing_document_settings()
        {
            add_filter('wpo_wcpdf_document_store_settings', array($this, 'return_false'), 9999);
        }

        public function return_false()
        {
            return false;
        }

        /**
         * Add metabox for invoice number & date
         */
        public function dat1a_input_box_content($post)
        {
            $order = wc_get_order($post->ID);
            $this->disable_storing_document_settings();

            do_action('wpo_wcpdf_meta_box_start', $post->ID);

            if ($invoice = wcpdf_get_document('partial_payment_invoice', $order)) {


                $invoice_number = $invoice->get_number();
                $invoice_date = $invoice->get_date();

                ?>
                <div class="wcpdf-data-fields" data-document="partial_payment_invoice"
                     data-order_id="<?php echo $order->get_id() ?>">
                    <h4><?php echo $invoice->get_title(); ?><?php if ($invoice->exists()) : ?><span
                                class="wpo-wcpdf-edit-date-number dashicons dashicons-edit"></span><span
                                class="wpo-wcpdf-delete-document dashicons dashicons-trash"
                                data-nonce="<?php echo wp_create_nonce("wpo_wcpdf_delete_document"); ?>"></span><?php endif; ?>
                    </h4>

                    <!-- Read only -->
                    <div class="read-only">
                        <?php if ($invoice->exists()) : ?>
                            <div class="invoice-number">
                                <p class="form-field _wcpdf_invoice_number_field ">
                                <p>
                                    <span><strong><?php echo esc_html__('Invoice Number', 'woocommerce-pdf-invoices-packing-slips'); ?>:</strong></span>
                                    <span><?php if (!empty($invoice_number)) echo $invoice_number->get_formatted(); ?></span>
                                </p>
                                </p>
                            </div>

                            <div class="invoice-date">
                                <p class="form-field form-field-wide">
                                <p>
                                    <span><strong><?php echo esc_html__('Invoice Date:', 'woocommerce-pdf-invoices-packing-slips'); ?></strong></span>
                                    <span><?php if (!empty($invoice_date)) echo $invoice_date->date_i18n(wc_date_format() . ' @ ' . wc_time_format()); ?></span>
                                </p>
                                </p>
                            </div>
                        <?php else : ?>
                            <span class="wpo-wcpdf-set-date-number button"><?php echo esc_html__('Set invoice number & date', 'woocommerce-pdf-invoices-packing-slips') ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Editable -->
                    <div class="editable">
                        <p class="form-field _wcpdf_invoice_number_field ">
                            <label for="_wcpdf_invoice_number"><?php echo esc_html__('Invoice Number (unformatted!)', 'woocommerce-pdf-invoices-packing-slips'); ?>
                                :</label>
                            <?php if ($invoice->exists() && !empty($invoice_number)) : ?>
                                <input type="text" class="short" style="" name="_wcpdf_invoice_number"
                                       id="_wcpdf_invoice_number" value="<?php echo $invoice_number->get_plain(); ?>"
                                       disabled="disabled">
                            <?php else : ?>
                                <input type="text" class="short" style="" name="_wcpdf_invoice_number"
                                       id="_wcpdf_invoice_number" value="" disabled="disabled">
                            <?php endif; ?>
                        </p>
                        <p class="form-field form-field-wide">
                            <label for="wcpdf_invoice_date"><?php echo esc_html__('Invoice Date:', 'woocommerce-pdf-invoices-packing-slips'); ?></label>
                            <?php if ($invoice->exists() && !empty($invoice_date)) : ?>
                                <input type="text" class="date-picker-field" name="wcpdf_invoice_date"
                                       id="wcpdf_invoice_date" maxlength="10"
                                       value="<?php echo $invoice_date->date_i18n('Y-m-d'); ?>"
                                       pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])"
                                       disabled="disabled"/>@<input type="number" class="hour"
                                                                    placeholder="<?php echo esc_html__('h', 'woocommerce') ?>"
                                                                    name="wcpdf_invoice_date_hour"
                                                                    id="wcpdf_invoice_date_hour" min="0" max="23"
                                                                    size="2"
                                                                    value="<?php echo $invoice_date->date_i18n('H') ?>"
                                                                    pattern="([01]?[0-9]{1}|2[0-3]{1})"/>:<input
                                        type="number" class="minute"
                                        placeholder="<?php echo esc_html__('m', 'woocommerce') ?>"
                                        name="wcpdf_invoice_date_minute" id="wcpdf_invoice_date_minute" min="0" max="59"
                                        size="2" value="<?php echo $invoice_date->date_i18n('i'); ?>"
                                        pattern="[0-5]{1}[0-9]{1}"/>
                            <?php else : ?>
                                <input type="text" class="date-picker-field" name="wcpdf_invoice_date"
                                       id="wcpdf_invoice_date" maxlength="10" disabled="disabled" value=""
                                       pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])"/>@<input
                                        type="number" class="hour" disabled="disabled"
                                        placeholder="<?php echo esc_html__('h', 'woocommerce') ?>"
                                        name="wcpdf_invoice_date_hour"
                                        id="wcpdf_invoice_date_hour" min="0" max="23" size="2" value=""
                                        pattern="([01]?[0-9]{1}|2[0-3]{1})"/>:<input type="number" class="minute"
                                                                                     placeholder="<?php echo esc_html__('m', 'woocommerce') ?>"
                                                                                     name="wcpdf_invoice_date_minute"
                                                                                     id="wcpdf_invoice_date_minute"
                                                                                     min="0" max="59" size="2" value=""
                                                                                     pattern="[0-5]{1}[0-9]{1}"
                                                                                     disabled="disabled"/>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <?php
            }

            do_action('wpo_wcpdf_meta_box_end', $post->ID);
        }

        public function data_input_box_content($post)
        {
            $order = wc_get_order($post->ID);
            $this->disable_storing_document_settings();
            $invoice = wcpdf_get_document('partial_payment_invoice', $order);

            do_action('wpo_wcpdf_meta_box_start', $order, $this);

            if ($invoice) {
                // data
                $data = array(
                    'number' => array(
                        'label' => __('Invoice Number:', 'woocommerce-pdf-invoices-packing-slips'),
                    ),
                    'date' => array(
                        'label' => __('Invoice Date:', 'woocommerce-pdf-invoices-packing-slips'),
                    ),
                    'notes' => array(
                        'label' => __('Notes (printed in the invoice):', 'woocommerce-pdf-invoices-packing-slips'),
                    ),
                );
                // outpuT
                $this->output_number_date_edit_fields($invoice, $data);
            }

            do_action('wpo_wcpdf_meta_box_end', $order, $this);
        }



        public function output_number_date_edit_fields( $document, $data ) {
            if( empty( $document ) || empty( $data ) ) return;
            $data = $this->get_current_values_for_document( $document, $data );
            ?>
            <div class="wcpdf-data-fields" data-document="<?= esc_attr( $document->get_type() ); ?>" data-order_id="<?php echo esc_attr( $document->order->get_id() ); ?>">
                <section class="wcpdf-data-fields-section number-date">
                    <!-- Title -->
                    <h4>
                        <?php echo wp_kses_post( $document->get_title() ); ?>
                        <?php if( $document->exists() && ( isset( $data['number'] ) || isset( $data['date'] ) ) ) : ?>
                            <span class="wpo-wcpdf-edit-date-number dashicons dashicons-edit"></span>
                            <span class="wpo-wcpdf-delete-document dashicons dashicons-trash" data-action="delete" data-nonce="<?php echo wp_create_nonce( "wpo_wcpdf_delete_document" ); ?>"></span>
                            <?php do_action( 'wpo_wcpdf_document_actions', $document ); ?>
                        <?php endif; ?>
                    </h4>

                    <!-- Read only -->
                    <div class="read-only">
                        <?php if( $document->exists() ) : ?>
                            <?php if( isset( $data['number'] ) ) : ?>
                                <div class="<?= esc_attr( $document->get_type() ); ?>-number">
                                    <p class="form-field <?= esc_attr( $data['number']['name'] ); ?>_field">
                                    <p>
                                        <span><strong><?= wp_kses_post( $data['number']['label'] ); ?></strong></span>
                                        <span><?= esc_attr( $data['number']['formatted'] ); ?></span>
                                    </p>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <?php if( isset( $data['date'] ) ) : ?>
                                <div class="<?= esc_attr( $document->get_type() ); ?>-number">
                                    <p class="form-field form-field-wide">
                                    <p>
                                        <span><strong><?= wp_kses_post( $data['date']['label'] ); ?></strong></span>
                                        <span><?= esc_attr( $data['date']['formatted'] ); ?></span>
                                    </p>
                                    </p>
                                </div>
                            <?php endif; ?>
                            <?php do_action( 'wpo_wcpdf_meta_box_after_document_data', $document, $document->order ); ?>
                        <?php else : ?>
                            <?php /* translators: document title */ ?>
                            <span class="wpo-wcpdf-set-date-number button"><?php printf( esc_html__( 'Set %s number & date', 'woocommerce-pdf-invoices-packing-slips' ), wp_kses_post( $document->get_title() ) ); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Editable -->
                    <div class="editable">
                        <?php if( isset( $data['number'] ) ) : ?>
                            <p class="form-field <?= esc_attr( $data['number']['name'] ); ?>_field">
                                <label for="<?= esc_attr( $data['number']['name'] ); ?>"><?= wp_kses_post( $data['number']['label'] ); ?></label>
                                <input type="text" class="short" style="" name="<?= esc_attr( $data['number']['name'] ); ?>" id="<?= esc_attr( $data['number']['name'] ); ?>" value="<?= esc_attr( $data['number']['plain'] ); ?>" disabled="disabled" > (<?= esc_html__( 'unformatted!', 'woocommerce-pdf-invoices-packing-slips' ); ?>)
                            </p>
                        <?php endif; ?>
                        <?php if( isset( $data['date'] ) ) : ?>
                            <p class="form-field form-field-wide">
                                <label for="<?= esc_attr( $data['date']['name'] ); ?>[date]"><?= wp_kses_post( $data['date']['label'] ); ?></label>
                                <input type="text" class="date-picker-field" name="<?= esc_attr( $data['date']['name'] ); ?>[date]" id="<?= esc_attr( $data['date']['name'] ); ?>[date]" maxlength="10" value="<?= esc_attr( $data['date']['date'] ); ?>" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" disabled="disabled"/>@<input type="number" class="hour" disabled="disabled" placeholder="<?php esc_attr_e( 'h', 'woocommerce' ); ?>" name="<?= esc_attr( $data['date']['name'] ); ?>[hour]" id="<?= esc_attr( $data['date']['name'] ); ?>[hour]" min="0" max="23" size="2" value="<?= esc_attr( $data['date']['hour'] ); ?>" pattern="([01]?[0-9]{1}|2[0-3]{1})" />:<input type="number" class="minute" placeholder="<?php esc_attr_e( 'm', 'woocommerce' ); ?>" name="<?= esc_attr( $data['date']['name'] ); ?>[minute]" id="<?= esc_attr( $data['date']['name'] ); ?>[minute]" min="0" max="59" size="2" value="<?= esc_attr( $data['date']['minute'] ); ?>" pattern="[0-5]{1}[0-9]{1}"  disabled="disabled" />
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Document Notes -->
                    <?php if( array_key_exists( 'notes', $data ) ) : ?>

                        <?php do_action( 'wpo_wcpdf_meta_box_before_document_notes', $document, $document->order ); ?>

                        <!-- Read only -->
                        <div class="read-only">
                            <span><strong><?= wp_kses_post( $data['notes']['label'] ); ?></strong></span>
                            <span class="wpo-wcpdf-edit-document-notes dashicons dashicons-edit" data-edit="notes"></span>
                            <p><?= ( $data['notes']['value'] == strip_tags( $data['notes']['value'] ) ) ? wp_kses_post( nl2br( $data['notes']['value'] ) ) : wp_kses_post( $data['notes']['value'] ); ?></p>
                        </div>
                        <!-- Editable -->
                        <div class="editable-notes">
                            <p class="form-field form-field-wide">
                                <label for="<?= esc_attr( $data['notes']['name'] ); ?>"><?= wp_kses_post( $data['notes']['label'] ); ?></label>
                            <p><textarea name="<?= esc_attr( $data['notes']['name'] ); ?>" class="<?= esc_attr( $data['notes']['name'] ); ?>" cols="60" rows="5" disabled="disabled"><?= wp_kses_post( $data['notes']['value'] ); ?></textarea></p>
                            </p>
                        </div>

                        <?php do_action( 'wpo_wcpdf_meta_box_after_document_notes', $document, $document->order ); ?>

                    <?php endif; ?>
                    <!-- / Document Notes -->

                </section>

                <!-- Save/Cancel buttons -->
                <section class="wcpdf-data-fields-section wpo-wcpdf-document-buttons">
                    <div>
                        <a class="button button-primary wpo-wcpdf-save-document" data-nonce="<?php echo wp_create_nonce( "wpo_wcpdf_save_document" ); ?>" data-action="save"><?php echo esc_html__( 'Save changes', 'woocommerce-pdf-invoices-packing-slips' ); ?></a>
                        <a class="button wpo-wcpdf-cancel"><?php echo esc_html__( 'Cancel', 'woocommerce-pdf-invoices-packing-slips' ); ?></a>
                    </div>
                </section>
                <!-- / Save/Cancel buttons -->
            </div>
            <?php
        }
        public function get_current_values_for_document( $document, $data ) {
            $current = array(
                'number' => array(
                    'plain'     => $document->exists() && ! empty( $document->get_number() ) ? $document->get_number()->get_plain() : '',
                    'formatted' => $document->exists() && ! empty( $document->get_number() ) ? $document->get_number()->get_formatted() : '',
                    'name'      => "_wcpdf_{$document->slug}_number",
                ),
                'date' => array(
                    'formatted' => $document->exists() && ! empty( $document->get_date() ) ? $document->get_date()->date_i18n( wc_date_format().' @ '.wc_time_format() ) : '',
                    'date'      => $document->exists() && ! empty( $document->get_date() ) ? $document->get_date()->date_i18n( 'Y-m-d' ) : date_i18n( 'Y-m-d' ),
                    'hour'      => $document->exists() && ! empty( $document->get_date() ) ? $document->get_date()->date_i18n( 'H' ) : date_i18n( 'H' ),
                    'minute'    => $document->exists() && ! empty( $document->get_date() ) ? $document->get_date()->date_i18n( 'i' ) : date_i18n( 'i' ),
                    'name'      => "_wcpdf_{$document->slug}_date",
                ),
            );

            if ( !empty( $data['notes'] ) ) {
                $current['notes'] = array(
                    'value' => $document->get_document_notes(),
                    'name'  =>"_wcpdf_{$document->slug}_notes",
                );
            }

            foreach ( $data as $key => $value ) {
                if ( isset( $current[$key] ) ) {
                    $data[$key] = array_merge( $current[$key], $value );
                }
            }

            return apply_filters( 'wpo_wcpdf_current_values_for_document', $data, $document );
        }


        function meta_box_actions($actions)
        {
            if (isset($actions['partial_payment_invoice'])) {
                unset($actions['partial_payment_invoice']);
            }
            return $actions;

        }


        function partial_payment_invoices($actions, $partial_payment)
        {

            $documents = WPO_WCPDF()->documents->get_documents();

            if ($documents) {

                foreach ($documents as $document) {

                    if ($document->is_enabled() && $document->get_type() === 'partial_payment_invoice') {

                        $invoice = wcpdf_get_document('partial_payment_invoice', $partial_payment, false);

                        $classes = $invoice && $invoice->exists() ? 'mepp_invoice_exists' : '';

                        $actions['pdf_invoice'] = '<a class="button btn ' . $classes . '" href="';
                        $actions['pdf_invoice'] .= wp_nonce_url(admin_url("admin-ajax.php?action=generate_wpo_wcpdf&document_type=partial_payment_invoice&order_ids=" . $partial_payment->get_id()), 'generate_wpo_wcpdf') . '">';
                        $actions['pdf_invoice'] .= esc_html__('PDF Invoice', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>';
                    }
                }


            }


            return $actions;
        }

        function document_classes($documents)
        {
            if (!class_exists('WPO\WC\PDF_Invoices\Documents\MEPP_Partial_Payment_Invoice') && file_exists(MEPP_PLUGIN_PATH . 'inc/compatibility/pdf-invoices/class-mepp-partial-payment-invoice.php')) {
                ;
                $documents['WPO\WC\PDF_Invoices\Documents\MEPP_Partial_Payment_Invoice'] = include MEPP_PLUGIN_PATH . 'inc/compatibility/pdf-invoices/class-mepp-partial-payment-invoice.php';

            }

            return $documents;
        }


        function template_file($path, $type)
        {


            if (strpos($path, 'partial_payment_invoice.php') !== false && $type === 'partial_payment_invoice') {

                $path = plugin_dir_path(__FILE__) . 'partial_payment_invoice.php';
            }
            return $path;
        }


    }
}

return new MEPP_Advance_Deposits_Pdf_Invoices_compatibility();
