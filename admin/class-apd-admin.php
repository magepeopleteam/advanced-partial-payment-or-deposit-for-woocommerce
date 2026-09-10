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
            __( 'Deposit Settings', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
            __( 'Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
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
                array( 'jquery', 'wp-util', 'wp-i18n' ),
                APD_VERSION,
                true
            );
            $this->localize_admin_script( 'apd-admin' );
            wp_add_inline_style( 'apd-admin', $this->get_notice_visibility_css() );
        }

        // Product edit page
        $screen = get_current_screen();
        if ( $screen && $screen->id === 'product' ) {
            wp_enqueue_style( 'apd-admin-product', APD_PLUGIN_URL . 'admin/css/apd-admin.css', array(), APD_VERSION );
            wp_enqueue_script( 'apd-admin-product', APD_PLUGIN_URL . 'admin/js/apd-admin.js', array( 'jquery', 'wp-i18n' ), APD_VERSION, true );
            $this->localize_admin_script( 'apd-admin-product' );
        }
    }

    /**
     * Attach the shared data object and the JS translations to an admin script handle.
     *
     * Both admin handles load the same apd-admin.js, so both need `apd_admin` and both
     * need their own wp_set_script_translations() call — script translations are keyed
     * by handle, not by file.
     *
     * @param string $handle Registered script handle.
     */
    private function localize_admin_script( $handle ) {
        wp_localize_script( $handle, 'apd_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'apd_admin_nonce' ),
            'strings'  => array(
                'saving'       => __( 'Saving...', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'saved'        => __( 'Settings saved successfully!', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'error'        => __( 'Error saving settings. Please try again.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'confirm'      => __( 'Are you sure?', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
            ),
        ) );

        wp_set_script_translations(
            $handle,
            'advanced-partial-payment-or-deposit-for-woocommerce',
            APD_PLUGIN_DIR . 'languages'
        );
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
                'label' => __( 'General', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-admin-settings',
                'pro'   => false,
            ),
            'labels'     => array(
                'label' => __( 'Labels', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-tag',
                'pro'   => false,
            ),
            'products'   => array(
                'label' => __( 'Products', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-products',
                'pro'   => false,
            ),
            'categories' => array(
                'label' => __( 'Categories', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-category',
                'pro'   => false,
            ),
            'emails'     => array(
                'label' => __( 'Emails', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-email-alt',
                'pro'   => false,
            ),
        );

        // Pro tabs (shown as locked if pro not active)
        $pro_tabs = array(
            'partial-payments' => array(
                'label' => __( 'Flexible Payments', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-money-alt',
                'pro'   => true,
            ),
            'payment-plans' => array(
                'label' => __( 'Payment Plans', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-calendar-alt',
                'pro'   => true,
            ),
            'min-max'       => array(
                'label' => __( 'Min / Max', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-arrow-up-alt',
                'pro'   => true,
            ),
            'gateway-rules' => array(
                'label' => __( 'Gateway Rules', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-shield',
                'pro'   => true,
            ),
            'reminders'     => array(
                'label' => __( 'Reminders', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-bell',
                'pro'   => true,
            ),
            'reports'       => array(
                'label' => __( 'Reports', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-chart-bar',
                'pro'   => true,
            ),
            'conditional-rules' => array(
                'label' => __( 'Conditional Rules', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-randomize',
                'pro'   => true,
            ),
            'license'       => array(
                'label' => __( 'License', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                'icon'  => 'dashicons-admin-network',
                'pro'   => true,
            ),
        );

        $tabs = array_merge( $tabs, $pro_tabs );

        return apply_filters( 'apd_admin_tabs', $tabs );
    }
}
