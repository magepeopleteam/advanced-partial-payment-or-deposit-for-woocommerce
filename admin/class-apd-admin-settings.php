<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin settings AJAX handler.
 */
class APD_Admin_Settings {

    public function __construct() {
        add_action( 'wp_ajax_apd_save_settings', array( $this, 'save_settings' ) );
        add_action( 'wp_ajax_apd_save_category_deposit', array( $this, 'save_category_deposit' ) );
        add_action( 'wp_ajax_apd_delete_category_deposit', array( $this, 'delete_category_deposit' ) );
    }

    /**
     * AJAX: Save general settings.
     */
    public function save_settings() {
        check_ajax_referer( 'apd_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : 'general';

        $settings = get_option( 'apd_settings', array() );

        switch ( $tab ) {
            case 'general':
                $settings['enable_deposit']     = isset( $_POST['enable_deposit'] ) ? 'yes' : 'no';
                $settings['deposit_type']       = sanitize_text_field( $_POST['deposit_type'] ?? 'percentage' );
                $settings['deposit_value']      = floatval( $_POST['deposit_value'] ?? 50 );
                $settings['allow_full_payment'] = isset( $_POST['allow_full_payment'] ) ? 'yes' : 'no';
                $settings['force_deposit']      = isset( $_POST['force_deposit'] ) ? 'yes' : 'no';
                break;

            case 'labels':
                $settings['deposit_label']      = sanitize_text_field( $_POST['deposit_label'] ?? '' );
                $settings['due_balance_label']  = sanitize_text_field( $_POST['due_balance_label'] ?? '' );
                $settings['pay_button_label']   = sanitize_text_field( $_POST['pay_button_label'] ?? '' );
                $settings['deposit_text']       = sanitize_text_field( $_POST['deposit_text'] ?? '' );
                $settings['full_payment_text']  = sanitize_text_field( $_POST['full_payment_text'] ?? '' );
                break;

            case 'emails':
                $settings['email_deposit_received']  = isset( $_POST['email_deposit_received'] ) ? 'yes' : 'no';
                $settings['email_balance_due']       = isset( $_POST['email_balance_due'] ) ? 'yes' : 'no';
                $settings['email_payment_complete']  = isset( $_POST['email_payment_complete'] ) ? 'yes' : 'no';

                foreach ( array( 'deposit_received', 'balance_due', 'payment_complete' ) as $email_key ) {
                    $settings[ 'email_' . $email_key . '_subject' ] = sanitize_text_field( wp_unslash( $_POST[ 'email_' . $email_key . '_subject' ] ?? '' ) );
                    $settings[ 'email_' . $email_key . '_heading' ] = sanitize_text_field( wp_unslash( $_POST[ 'email_' . $email_key . '_heading' ] ?? '' ) );
                    $settings[ 'email_' . $email_key . '_body' ]    = wp_kses_post( wp_unslash( $_POST[ 'email_' . $email_key . '_body' ] ?? '' ) );
                }

                $settings['email_balance_due_button_label'] = sanitize_text_field( wp_unslash( $_POST['email_balance_due_button_label'] ?? '' ) );
                break;
        }

        // Allow pro addon to save additional settings
        $settings = apply_filters( 'apd_save_settings', $settings, $tab, $_POST );

        update_option( 'apd_settings', $settings );

        wp_send_json_success( __( 'Settings saved successfully!', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
    }

    /**
     * AJAX: Save category deposit settings.
     */
    public function save_category_deposit() {
        check_ajax_referer( 'apd_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $cat_id       = intval( $_POST['category_id'] ?? 0 );
        $enable       = sanitize_text_field( $_POST['enable_deposit'] ?? 'no' );
        $force        = sanitize_text_field( $_POST['force_deposit'] ?? '' );
        $deposit_type = sanitize_text_field( $_POST['deposit_type'] ?? 'percentage' );
        $deposit_val  = floatval( $_POST['deposit_value'] ?? 0 );

        if ( ! $cat_id ) {
            wp_send_json_error( __( 'Invalid category.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        update_term_meta( $cat_id, '_apd_enable_deposit', $enable );
        update_term_meta( $cat_id, '_apd_force_deposit', $force );
        update_term_meta( $cat_id, '_apd_deposit_type', $deposit_type );
        update_term_meta( $cat_id, '_apd_deposit_value', $deposit_val );

        wp_send_json_success( __( 'Category deposit saved!', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
    }

    /**
     * AJAX: Delete category deposit.
     */
    public function delete_category_deposit() {
        check_ajax_referer( 'apd_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $cat_id = intval( $_POST['category_id'] ?? 0 );
        if ( ! $cat_id ) {
            wp_send_json_error( __( 'Invalid category.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        delete_term_meta( $cat_id, '_apd_enable_deposit' );
        delete_term_meta( $cat_id, '_apd_force_deposit' );
        delete_term_meta( $cat_id, '_apd_deposit_type' );
        delete_term_meta( $cat_id, '_apd_deposit_value' );
        delete_term_meta( $cat_id, '_apd_assigned_plans' );

        wp_send_json_success( __( 'Category deposit removed!', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
    }
}
