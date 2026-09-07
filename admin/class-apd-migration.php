<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class APD_Migration {

    public function __construct() {
        add_action( 'admin_notices', array( $this, 'show_migration_notice' ) );
        add_action( 'wp_ajax_apd_sync_old_data', array( $this, 'ajax_sync_old_data' ) );
        add_action( 'admin_footer', array( $this, 'migration_script' ) );
    }

    public function show_migration_notice() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'apd-deposits' ) {
            return;
        }

        if ( get_option( 'apd_migration_from_mepp_done' ) === 'yes' ) {
            return;
        }

        if ( ! $this->has_old_data_to_migrate() ) {
            return;
        }

        ?>
        <div class="notice notice-info" id="apd-migration-notice" style="display:block !important; border-left-color: #4338ca;">
            <p>
                <strong><?php esc_html_e( 'Legacy Data Detected', 'advanced-partial-payment' ); ?></strong><br>
                <?php esc_html_e( 'We noticed you have data from the old Mage Partial Payment plugin. Would you like to sync your old settings, product configurations, and orders to the new system?', 'advanced-partial-payment' ); ?>
            </p>
            <p>
                <button type="button" class="button button-primary" id="apd-start-migration"><?php esc_html_e( 'Sync Old Data Now', 'advanced-partial-payment' ); ?></button>
                <button type="button" class="button" id="apd-dismiss-migration"><?php esc_html_e( 'I will do this later', 'advanced-partial-payment' ); ?></button>
            </p>
            <div id="apd-migration-progress" style="display:none; margin-top:10px;">
                <p id="apd-migration-status"><?php esc_html_e( 'Initializing...', 'advanced-partial-payment' ); ?></p>
                <progress id="apd-migration-bar" value="0" max="100" style="width: 100%;"></progress>
            </div>
        </div>
        <?php
    }

    private function has_old_data_to_migrate() {
        global $wpdb;

        // Check options table
        $has_options = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 1", 'mepp\_%' ) );
        if ( $has_options ) {
            return true;
        }

        // Check postmeta table
        $has_meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->postmeta} WHERE meta_key LIKE %s LIMIT 1", '\_mepp\_%' ) );
        if ( $has_meta ) {
            return true;
        }

        return false;
    }

    public function migration_script() {
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'apd-deposits' ) {
            return;
        }

        if ( get_option( 'apd_migration_from_mepp_done' ) === 'yes' ) {
            return;
        }

        if ( ! $this->has_old_data_to_migrate() ) {
            return;
        }
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('#apd-dismiss-migration').on('click', function() {
                    $('#apd-migration-notice').hide();
                });

                $('#apd-start-migration').on('click', function() {
                    $(this).prop('disabled', true);
                    $('#apd-dismiss-migration').prop('disabled', true);
                    $('#apd-migration-progress').show();
                    
                    apd_run_migration('global_settings', 0);
                });

                function apd_run_migration(step, offset) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'apd_sync_old_data',
                            step: step,
                            offset: offset,
                            nonce: '<?php echo wp_create_nonce( "apd_migration_nonce" ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#apd-migration-status').text(response.data.message);
                                $('#apd-migration-bar').val(response.data.progress);

                                if (response.data.next_step !== 'done') {
                                    apd_run_migration(response.data.next_step, response.data.next_offset);
                                } else {
                                    $('#apd-migration-status').text('<?php esc_html_e('Migration completed successfully!', 'advanced-partial-payment'); ?>');
                                    setTimeout(function() {
                                        $('#apd-migration-notice').slideUp();
                                    }, 3000);
                                }
                            } else {
                                $('#apd-migration-status').text('<?php esc_html_e('Error:', 'advanced-partial-payment'); ?> ' + response.data);
                            }
                        },
                        error: function() {
                            $('#apd-migration-status').text('<?php esc_html_e('An error occurred during migration. Please try again.', 'advanced-partial-payment'); ?>');
                        }
                    });
                }
            });
        </script>
        <?php
    }

    public function ajax_sync_old_data() {
        check_ajax_referer( 'apd_migration_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment' ) );
        }

        $step = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : 'global_settings';
        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $batch_size = 50;

        switch ( $step ) {
            case 'global_settings':
                $this->migrate_global_settings();
                wp_send_json_success( array(
                    'message' => __( 'Global settings and categories migrated.', 'advanced-partial-payment' ),
                    'progress' => 10,
                    'next_step' => 'payment_plans',
                    'next_offset' => 0
                ) );
                break;

            case 'payment_plans':
                $this->migrate_payment_plans();
                wp_send_json_success( array(
                    'message' => __( 'Payment plans migrated.', 'advanced-partial-payment' ),
                    'progress' => 20,
                    'next_step' => 'products',
                    'next_offset' => 0
                ) );
                break;

            case 'products':
                $args = array(
                    'post_type'      => 'product',
                    'posts_per_page' => $batch_size,
                    'offset'         => $offset,
                    'post_status'    => 'any',
                    'fields'         => 'ids'
                );
                $products = get_posts( $args );

                if ( ! empty( $products ) ) {
                    foreach ( $products as $post_id ) {
                        $this->migrate_product_meta( $post_id );
                    }
                    wp_send_json_success( array(
                        'message' => sprintf( __( 'Migrated %d products...', 'advanced-partial-payment' ), $offset + count($products) ),
                        'progress' => 40,
                        'next_step' => 'products',
                        'next_offset' => $offset + $batch_size
                    ) );
                } else {
                    wp_send_json_success( array(
                        'message' => __( 'Products migration complete.', 'advanced-partial-payment' ),
                        'progress' => 60,
                        'next_step' => 'orders',
                        'next_offset' => 0
                    ) );
                }
                break;

            case 'orders':
                $args = array(
                    'post_type'      => 'shop_order',
                    'posts_per_page' => $batch_size,
                    'offset'         => $offset,
                    'post_status'    => 'any',
                    'fields'         => 'ids'
                );
                
                // For HPOS compatibility
                if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
                    $orders = wc_get_orders( array(
                        'limit'  => $batch_size,
                        'offset' => $offset,
                        'return' => 'ids'
                    ) );
                } else {
                    $orders = get_posts( $args );
                }

                if ( ! empty( $orders ) ) {
                    foreach ( $orders as $order_id ) {
                        $this->migrate_order_meta( $order_id );
                    }
                    wp_send_json_success( array(
                        'message' => sprintf( __( 'Migrated %d orders...', 'advanced-partial-payment' ), $offset + count($orders) ),
                        'progress' => 90,
                        'next_step' => 'orders',
                        'next_offset' => $offset + $batch_size
                    ) );
                } else {
                    update_option( 'apd_migration_from_mepp_done', 'yes' );
                    wp_send_json_success( array(
                        'message' => __( 'All data migrated successfully.', 'advanced-partial-payment' ),
                        'progress' => 100,
                        'next_step' => 'done',
                        'next_offset' => 0
                    ) );
                }
                break;
        }
    }

    private function migrate_global_settings() {
        $apd_settings = get_option( 'apd_settings', array() );

        // Map global settings
        $apd_settings['enable_deposit'] = get_option( 'mepp_storewide_deposit_enabled', 'no' );
        $old_amount_type = get_option( 'mepp_storewide_deposit_amount_type', 'percent' );
        $apd_settings['deposit_type'] = ( $old_amount_type === 'percent' ) ? 'percentage' : 'fixed';
        $apd_settings['deposit_value'] = get_option( 'mepp_storewide_deposit_amount', '' );
        $apd_settings['force_deposit'] = get_option( 'mepp_storewide_deposit_force_deposit', 'no' );
        
        // Migrate Pro Gateway Rules
        if ( class_exists( 'WooCommerce' ) ) {
            $gateways = WC()->payment_gateways()->payment_gateways();
            $gateway_modes = isset( $apd_settings['gateway_checkout_modes'] ) ? $apd_settings['gateway_checkout_modes'] : array();
            $has_gateway_rules = false;

            foreach ( $gateways as $gateway_id => $gateway ) {
                $old_control = get_option( 'mepp_gateway_control_' . $gateway_id );
                if ( $old_control === 'force_full' || $old_control === 'hide_ui' ) {
                    $gateway_modes[ $gateway_id ] = array(
                        'mode'  => 'full',
                        'value' => 0,
                        'plan_id' => ''
                    );
                    $has_gateway_rules = true;
                }
            }

            if ( $has_gateway_rules ) {
                $apd_settings['gateway_checkout_modes'] = $gateway_modes;
            }
        }
        
        // Migrate Pro User Role / Guest Restrictions
        $restrict_guests = get_option( 'mepp_restrict_deposits_for_logged_in_users_only' );
        if ( $restrict_guests === 'yes' ) {
            $apd_settings['conditional_allow_guests'] = 'no';
        }

        $disabled_roles = get_option( 'mepp_disable_deposit_for_user_roles' );
        if ( ! empty( $disabled_roles ) && is_array( $disabled_roles ) ) {
            // New plugin uses "Allowed Roles". We need to get all roles and exclude the disabled ones.
            global $wp_roles;
            if ( ! isset( $wp_roles ) ) {
                $wp_roles = new WP_Roles();
            }
            $all_roles = array_keys( $wp_roles->roles );
            $allowed_roles = array_diff( $all_roles, $disabled_roles );
            $apd_settings['conditional_user_roles'] = array_values( $allowed_roles );
        }

        update_option( 'apd_settings', $apd_settings );

        // Migrate categories
        $terms = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                $cat_id = $term->term_id;
                
                $enable_deposit = get_option( 'mepp_category_' . $cat_id . '_enable_deposit', '' );
                if ( $enable_deposit && $enable_deposit !== 'inherit' ) {
                    update_term_meta( $cat_id, '_apd_enable_deposit', $enable_deposit );
                }

                $amount_type = get_option( 'mepp_category_' . $cat_id . '_amount_type', '' );
                if ( $amount_type && $amount_type !== 'inherit' ) {
                    $new_type = ( $amount_type === 'percent' ) ? 'percentage' : 'fixed';
                    update_term_meta( $cat_id, '_apd_deposit_type', $new_type );
                }

                $amount = get_option( 'mepp_category_' . $cat_id . '_amount', '' );
                if ( $amount !== '' ) {
                    update_term_meta( $cat_id, '_apd_deposit_value', $amount );
                }

                $force = get_option( 'mepp_category_' . $cat_id . '_force_deposit', '' );
                if ( $force === 'yes' ) {
                    update_term_meta( $cat_id, '_apd_force_deposit', 'yes' );
                }
            }
        }
    }

    private function migrate_payment_plans() {
        if ( ! taxonomy_exists( 'mepp_payment_plan' ) ) {
            register_taxonomy( 'mepp_payment_plan', 'product' );
        }

        $terms = get_terms( array(
            'taxonomy'   => 'mepp_payment_plan',
            'hide_empty' => false,
        ) );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return;
        }

        $new_plans = get_option( 'apd_payment_plans', array() );

        foreach ( $terms as $term ) {
            $plan_id = 'plan_' . $term->term_id;
            
            if ( isset( $new_plans[ $plan_id ] ) ) {
                continue;
            }

            $amount_type = get_term_meta( $term->term_id, 'amount_type', true );
            if ( empty( $amount_type ) ) {
                $amount_type = 'percentage';
            }

            $deposit_percentage = get_term_meta( $term->term_id, 'deposit_percentage', true );
            $payment_details_json = get_term_meta( $term->term_id, 'payment_details', true );
            
            // Payment details could be a JSON string or an array depending on how WP handled it.
            $payment_details = is_string( $payment_details_json ) ? json_decode( $payment_details_json, true ) : $payment_details_json;

            $installments = array();

            // First installment (immediate deposit)
            $installments[] = array(
                'amount' => floatval( $deposit_percentage ),
                'due_type' => 'immediately',
                'due_after_value' => 0,
                'due_after_unit' => 'day',
                'due_fixed_date' => '',
            );

            // Subsequent installments
            if ( is_array( $payment_details ) ) {
                foreach ( $payment_details as $detail ) {
                    $due_type = ( isset( $detail['date_checkbox'] ) && $detail['date_checkbox'] === 'on' ) ? 'fixed_date' : 'after_purchase';
                    
                    // Map old terms (day, week, month, year) to new (day, week, month)
                    $unit = 'day';
                    if ( isset( $detail['after-term'] ) ) {
                        $old_unit = strtolower( rtrim( $detail['after-term'], 's' ) );
                        if ( in_array( $old_unit, array( 'day', 'week', 'month' ) ) ) {
                            $unit = $old_unit;
                        } elseif ( $old_unit === 'year' ) {
                            $unit = 'month';
                            $detail['after'] = intval( $detail['after'] ?? 1 ) * 12;
                        }
                    }

                    $installments[] = array(
                        'amount' => floatval( $detail['percentage'] ?? 0 ),
                        'due_type' => $due_type,
                        'due_after_value' => intval( $detail['after'] ?? 0 ),
                        'due_after_unit' => $unit,
                        'due_fixed_date' => $detail['date'] ?? '',
                    );
                }
            }

            $new_plans[ $plan_id ] = array(
                'id'            => $plan_id,
                'name'          => $term->name,
                'description'   => $term->description,
                'price_type'    => $amount_type,
                'status'        => 'active',
                'installments'  => $installments,
                'created'       => current_time( 'mysql' ),
                'updated'       => current_time( 'mysql' ),
            );
        }

        update_option( 'apd_payment_plans', $new_plans );
    }

    private function migrate_product_meta( $post_id ) {
        $enable_deposit = get_post_meta( $post_id, '_mepp_enable_deposit', true );
        if ( $enable_deposit !== '' ) {
            update_post_meta( $post_id, '_apd_enable_deposit', $enable_deposit );
        }

        $amount_type = get_post_meta( $post_id, '_mepp_amount_type', true );
        if ( $amount_type !== '' ) {
            $new_type = ( $amount_type === 'percent' ) ? 'percentage' : $amount_type;
            update_post_meta( $post_id, '_apd_deposit_type', $new_type );
        }

        $deposit_amount = get_post_meta( $post_id, '_mepp_deposit_amount', true );
        if ( $deposit_amount !== '' ) {
            update_post_meta( $post_id, '_apd_deposit_value', $deposit_amount );
        }

        // Pro: Payment Plans assignment
        $old_assigned_plans = get_post_meta( $post_id, '_mepp_payment_plans', true );
        if ( ! empty( $old_assigned_plans ) && is_array( $old_assigned_plans ) ) {
            $new_assigned_plans = array();
            foreach ( $old_assigned_plans as $pid ) {
                $new_assigned_plans[] = 'plan_' . $pid;
            }
            update_post_meta( $post_id, '_apd_assigned_plans', $new_assigned_plans );
        }
    }

    private function migrate_order_meta( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Depending on HPOS, meta is accessed via order object methods
        $is_deposit = $order->get_meta( '_mepp_is_deposit' );
        if ( $is_deposit === 'yes' ) {
            $order->update_meta_data( '_apd_is_deposit', 'yes' );
            
            $deposit_amount = $order->get_meta( '_mepp_deposit_amount' );
            if ( $deposit_amount !== '' ) {
                $order->update_meta_data( '_apd_deposit_amount', $deposit_amount );
            }

            $due_amount = $order->get_meta( '_mepp_due_amount' );
            if ( $due_amount !== '' ) {
                $order->update_meta_data( '_apd_due_balance', $due_amount );
            }

            // In old plugin due date might be _mepp_payment_schedule or _mepp_due_date
            $due_date = $order->get_meta( '_mepp_due_date' );
            if ( $due_date !== '' ) {
                $order->update_meta_data( '_apd_due_date', $due_date );
            }

            $order->save_meta_data();
        }
    }

}
