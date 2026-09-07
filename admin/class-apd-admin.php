<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class — registers menu, enqueues assets, renders dashboard.
 */
class APD_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    /**
     * Register top-level admin menu.
     */
    public function register_menu() {
        add_menu_page(
            __( 'Deposit Settings', 'advanced-partial-payment' ),
            __( 'Deposits', 'advanced-partial-payment' ),
            'manage_woocommerce',
            'apd-deposits',
            array( $this, 'render_dashboard' ),
            'dashicons-money-alt',
            56
        );
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_assets( $hook ) {
        // Dashboard page
        if ( 'toplevel_page_apd-deposits' === $hook ) {
            wp_enqueue_style(
                'apd-admin',
                APD_PLUGIN_URL . 'admin/css/apd-admin.css',
                array(),
                APD_VERSION
            );
            wp_enqueue_script(
                'apd-admin',
                APD_PLUGIN_URL . 'admin/js/apd-admin.js',
                array( 'jquery', 'wp-util' ),
                APD_VERSION,
                true
            );
            wp_localize_script( 'apd-admin', 'apd_admin', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'apd_admin_nonce' ),
                'strings'  => array(
                    'saving'       => __( 'Saving...', 'advanced-partial-payment' ),
                    'saved'        => __( 'Settings saved successfully!', 'advanced-partial-payment' ),
                    'error'        => __( 'Error saving settings. Please try again.', 'advanced-partial-payment' ),
                    'confirm'      => __( 'Are you sure?', 'advanced-partial-payment' ),
                ),
            ) );
            wp_add_inline_style( 'apd-admin', $this->get_notice_visibility_css() );
        }

        // Product edit page
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'product' ) {
            wp_enqueue_style( 'apd-admin-product', APD_PLUGIN_URL . 'admin/css/apd-admin.css', array(), APD_VERSION );
            wp_enqueue_script( 'apd-admin-product', APD_PLUGIN_URL . 'admin/js/apd-admin.js', array( 'jquery' ), APD_VERSION, true );
        }
    }

    /**
     * Hide unrelated admin notices on the Deposits dashboard only.
     *
     * Global WordPress/plugin notices remain visible everywhere else in wp-admin.
     *
     * @return string
     */
    private function get_notice_visibility_css() {
        return "
body.toplevel_page_apd-deposits #wpbody-content > .notice:not(.apd-admin-notice):not(.apd-keep-admin-notice),
body.toplevel_page_apd-deposits #wpbody-content > .update-nag:not(.apd-admin-notice):not(.apd-keep-admin-notice),
body.toplevel_page_apd-deposits #wpbody-content > .updated:not(.apd-admin-notice):not(.apd-keep-admin-notice),
body.toplevel_page_apd-deposits #wpbody-content > .error:not(.apd-admin-notice):not(.apd-keep-admin-notice),
body.toplevel_page_apd-deposits #wpbody-content > .is-dismissible:not(.apd-admin-notice):not(.apd-keep-admin-notice) {
    display: none !important;
}

body.toplevel_page_apd-deposits .apd-dashboard {
    margin-top: 18px;
}
";
    }

    /**
     * Render the dashboard.
     */
    public function render_dashboard() {
        include APD_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    /**
     * Get dashboard tabs (free + pro placeholders).
     */
    public static function get_tabs() {
        $tabs = array(
            'general'    => array(
                'label' => __( 'General', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-admin-settings',
                'pro'   => false,
            ),
            'labels'     => array(
                'label' => __( 'Labels', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-tag',
                'pro'   => false,
            ),
            'products'   => array(
                'label' => __( 'Products', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-products',
                'pro'   => false,
            ),
            'categories' => array(
                'label' => __( 'Categories', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-category',
                'pro'   => false,
            ),
            'emails'     => array(
                'label' => __( 'Emails', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-email-alt',
                'pro'   => false,
            ),
        );

        // Pro tabs (shown as locked if pro not active)
        $pro_tabs = array(
            'payment-plans' => array(
                'label' => __( 'Payment Plans', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-calendar-alt',
                'pro'   => true,
            ),
            'min-max'       => array(
                'label' => __( 'Min / Max', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-arrow-up-alt',
                'pro'   => true,
            ),
            'gateway-rules' => array(
                'label' => __( 'Gateway Rules', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-shield',
                'pro'   => true,
            ),
            'reminders'     => array(
                'label' => __( 'Reminders', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-bell',
                'pro'   => true,
            ),
            'reports'       => array(
                'label' => __( 'Reports', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-chart-bar',
                'pro'   => true,
            ),
            'conditional-rules' => array(
                'label' => __( 'Conditional Rules', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-randomize',
                'pro'   => true,
            ),
            'license'       => array(
                'label' => __( 'License', 'advanced-partial-payment' ),
                'icon'  => 'dashicons-admin-network',
                'pro'   => true,
            ),
        );

        $tabs = array_merge( $tabs, $pro_tabs );

        return apply_filters( 'apd_admin_tabs', $tabs );
    }
}
