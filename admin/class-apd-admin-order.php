<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin order metabox and deposit column in orders list.
 */
class APD_Admin_Order {

    public function __construct() {
        // Metabox on order edit page
        add_action( 'add_meta_boxes', array( $this, 'add_deposit_metabox' ) );
        // Custom columns in orders list
        add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_columns' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_columns' ), 10, 2 );
        // HPOS columns
        add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_columns' ) );
        add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_order_columns_hpos' ), 10, 2 );
        // AJAX: Record manual payment
        add_action( 'wp_ajax_apd_record_payment', array( $this, 'ajax_record_payment' ) );
        // Order status styling
        add_action( 'admin_head', array( $this, 'order_status_styles' ) );
    }

    /**
     * Add metabox to order edit page.
     */
    public function add_deposit_metabox() {
        $screen = wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id( 'shop-order' )
            : 'shop_order';

        add_meta_box(
            'apd-deposit-details',
            __( '💰 Deposit Details', 'advanced-partial-payment' ),
            array( $this, 'render_deposit_metabox' ),
            $screen,
            'side',
            'high'
        );

        add_meta_box(
            'apd-deposit-payment-record',
            __( 'Deposit Payment Record', 'advanced-partial-payment' ),
            array( $this, 'render_payment_record_metabox' ),
            $screen,
            'normal',
            'default'
        );
    }

    /**
     * Render deposit metabox.
     */
    public function render_deposit_metabox( $post_or_order ) {
        $order = $this->get_order_object( $post_or_order );

        if ( ! $order || ! APD_Order::is_deposit_order( $order ) ) {
            echo '<p style="color:#999;">' . esc_html__( 'This is not a deposit order.', 'advanced-partial-payment' ) . '</p>';
            return;
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details ) {
            return;
        }
        ?>
        <div class="apd-metabox-content">
            <table class="apd-metabox-table" style="width:100%;border-collapse:collapse;">
                <tr>
                    <td style="padding:8px 0;color:#666;"><?php esc_html_e( 'Full Order Total', 'advanced-partial-payment' ); ?></td>
                    <td style="padding:8px 0;text-align:right;font-weight:600;"><?php echo wc_price( $details['total_amount'] ); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#666;"><?php esc_html_e( 'Deposit Amount', 'advanced-partial-payment' ); ?></td>
                    <td style="padding:8px 0;text-align:right;color:#2271b1;font-weight:600;"><?php echo wc_price( $details['deposit_amount'] ); ?></td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#666;"><?php esc_html_e( 'Total Paid', 'advanced-partial-payment' ); ?></td>
                    <td style="padding:8px 0;text-align:right;color:#00a32a;font-weight:600;"><?php echo wc_price( $details['amount_paid'] ); ?></td>
                </tr>
                <tr style="border-top:2px solid #e2e4e7;">
                    <td style="padding:10px 0;font-weight:700;color:#333;"><?php esc_html_e( 'Balance Due', 'advanced-partial-payment' ); ?></td>
                    <td style="padding:10px 0;text-align:right;font-weight:700;color:<?php echo $details['balance_due'] > 0 ? '#d63638' : '#00a32a'; ?>;font-size:16px;">
                        <?php echo wc_price( $details['balance_due'] ); ?>
                    </td>
                </tr>
            </table>

            <?php if ( $details['balance_due'] > 0 ) : ?>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e2e4e7;">
                <p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#666;">
                    <?php esc_html_e( 'Record Manual Payment', 'advanced-partial-payment' ); ?>
                </p>
                <div style="display:flex;gap:6px;">
                    <input type="number" id="apd-manual-amount" step="0.01" min="0.01"
                           max="<?php echo esc_attr( $details['balance_due'] ); ?>"
                           value="<?php echo esc_attr( $details['balance_due'] ); ?>"
                           style="flex:1;min-width:0;" placeholder="<?php esc_attr_e( 'Amount', 'advanced-partial-payment' ); ?>" />
                    <button type="button" class="button button-primary" id="apd-record-payment"
                            data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
                        <?php esc_html_e( 'Record', 'advanced-partial-payment' ); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $details['history'] ) ) : ?>
            <div style="margin-top:12px;padding-top:12px;border-top:1px solid #e2e4e7;">
                <p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#666;">
                    <?php esc_html_e( 'Payment History', 'advanced-partial-payment' ); ?>
                </p>
                <?php foreach ( $details['history'] as $index => $entry ) : ?>
                <div style="padding:8px 0;border-bottom:1px solid #f0f0f1;">
                    <div style="display:flex;justify-content:space-between;gap:8px;font-size:12px;color:#555;">
                        <span style="font-weight:600;color:#1d2327;">
                            <?php echo esc_html( sprintf( __( 'Payment %d', 'advanced-partial-payment' ), $index + 1 ) ); ?>
                        </span>
                        <span style="font-weight:600;"><?php echo wc_price( floatval( $entry['amount'] ?? 0 ) ); ?></span>
                    </div>
                    <div style="margin-top:4px;font-size:11px;color:#50575e;">
                        <?php echo esc_html( $this->get_payment_type_label( $entry ) ); ?>
                        <?php if ( ! empty( $entry['note'] ) ) : ?>
                            · <?php echo esc_html( $entry['note'] ); ?>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:11px;color:#999;margin-top:3px;"><?php echo esc_html( $entry['date'] ?? '' ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a full-width payment record panel on the order edit screen.
     */
    public function render_payment_record_metabox( $post_or_order ) {
        $order = $this->get_order_object( $post_or_order );

        if ( ! $order || ! APD_Order::is_deposit_order( $order ) ) {
            echo '<p style="color:#999;">' . esc_html__( 'No deposit payment record available for this order.', 'advanced-partial-payment' ) . '</p>';
            return;
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details ) {
            echo '<p style="color:#999;">' . esc_html__( 'No payment record found.', 'advanced-partial-payment' ) . '</p>';
            return;
        }

        $history       = is_array( $details['history'] ) ? $details['history'] : array();
        $running_total = 0;
        ?>
        <div class="apd-admin-payment-record">
            <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px;">
                <div style="padding:12px 14px;border:1px solid #e2e4e7;border-radius:8px;background:#fff;">
                    <div style="font-size:11px;font-weight:600;color:#646970;text-transform:uppercase;"><?php esc_html_e( 'Full Order Total', 'advanced-partial-payment' ); ?></div>
                    <div style="margin-top:6px;font-size:18px;font-weight:700;color:#1d2327;"><?php echo wc_price( $details['total_amount'] ); ?></div>
                </div>
                <div style="padding:12px 14px;border:1px solid #e2e4e7;border-radius:8px;background:#fff;">
                    <div style="font-size:11px;font-weight:600;color:#646970;text-transform:uppercase;"><?php esc_html_e( 'Deposit Amount', 'advanced-partial-payment' ); ?></div>
                    <div style="margin-top:6px;font-size:18px;font-weight:700;color:#2271b1;"><?php echo wc_price( $details['deposit_amount'] ); ?></div>
                </div>
                <div style="padding:12px 14px;border:1px solid #e2e4e7;border-radius:8px;background:#fff;">
                    <div style="font-size:11px;font-weight:600;color:#646970;text-transform:uppercase;"><?php esc_html_e( 'Total Paid', 'advanced-partial-payment' ); ?></div>
                    <div style="margin-top:6px;font-size:18px;font-weight:700;color:#00a32a;"><?php echo wc_price( $details['amount_paid'] ); ?></div>
                </div>
                <div style="padding:12px 14px;border:1px solid #e2e4e7;border-radius:8px;background:#fff;">
                    <div style="font-size:11px;font-weight:600;color:#646970;text-transform:uppercase;"><?php esc_html_e( 'Balance Due', 'advanced-partial-payment' ); ?></div>
                    <div style="margin-top:6px;font-size:18px;font-weight:700;color:<?php echo $details['balance_due'] > 0 ? '#d63638' : '#00a32a'; ?>;"><?php echo wc_price( $details['balance_due'] ); ?></div>
                </div>
            </div>

            <table class="widefat striped" style="border:1px solid #e2e4e7;">
                <thead>
                    <tr>
                        <th style="width:80px;"><?php esc_html_e( '#', 'advanced-partial-payment' ); ?></th>
                        <th style="width:160px;"><?php esc_html_e( 'Payment', 'advanced-partial-payment' ); ?></th>
                        <th style="width:180px;"><?php esc_html_e( 'Date', 'advanced-partial-payment' ); ?></th>
                        <th><?php esc_html_e( 'Note', 'advanced-partial-payment' ); ?></th>
                        <th style="width:140px;text-align:right;"><?php esc_html_e( 'Amount', 'advanced-partial-payment' ); ?></th>
                        <th style="width:160px;text-align:right;"><?php esc_html_e( 'Running Paid', 'advanced-partial-payment' ); ?></th>
                        <th style="width:160px;text-align:right;"><?php esc_html_e( 'Balance After', 'advanced-partial-payment' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $history ) ) : ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e( 'No payment entries recorded yet.', 'advanced-partial-payment' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $history as $index => $entry ) : ?>
                            <?php
                            $amount        = floatval( $entry['amount'] ?? 0 );
                            $running_total += $amount;
                            $balance_after = max( 0, floatval( $details['total_amount'] ) - $running_total );
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html( sprintf( __( 'Payment %d', 'advanced-partial-payment' ), $index + 1 ) ); ?></strong></td>
                                <td><?php echo esc_html( $this->get_payment_type_label( $entry ) ); ?></td>
                                <td><?php echo esc_html( $entry['date'] ?? '' ); ?></td>
                                <td><?php echo esc_html( $entry['note'] ?? '' ); ?></td>
                                <td style="text-align:right;font-weight:600;"><?php echo wc_price( $amount ); ?></td>
                                <td style="text-align:right;"><?php echo wc_price( $running_total ); ?></td>
                                <td style="text-align:right;color:<?php echo $balance_after > 0 ? '#d63638' : '#00a32a'; ?>;font-weight:600;"><?php echo wc_price( $balance_after ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Add deposit status column to orders list.
     */
    public function add_order_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( 'order_total' === $key ) {
                $new_columns['apd_deposit_status'] = __( 'Deposit', 'advanced-partial-payment' );
            }
        }
        return $new_columns;
    }

    /**
     * Render deposit column (legacy).
     */
    public function render_order_columns( $column, $post_id ) {
        if ( 'apd_deposit_status' === $column ) {
            $order = wc_get_order( $post_id );
            $this->render_deposit_column_content( $order );
        }
    }

    /**
     * Render deposit column (HPOS).
     */
    public function render_order_columns_hpos( $column, $order ) {
        if ( 'apd_deposit_status' === $column ) {
            $this->render_deposit_column_content( $order );
        }
    }

    /**
     * Column content.
     */
    private function render_deposit_column_content( $order ) {
        if ( ! $order || ! APD_Order::is_deposit_order( $order ) ) {
            echo '<span style="color:#999;">—</span>';
            return;
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details ) {
            return;
        }

        if ( $details['balance_due'] > 0 ) {
            printf(
                '<span style="color:#d63638;font-weight:600;" title="%s">%s %s</span>',
                esc_attr__( 'Balance Due', 'advanced-partial-payment' ),
                esc_html__( 'Due:', 'advanced-partial-payment' ),
                wp_kses_post( wc_price( $details['balance_due'] ) )
            );
        } else {
            echo '<span style="color:#00a32a;font-weight:600;">✓ ' . esc_html__( 'Paid', 'advanced-partial-payment' ) . '</span>';
        }
    }

    /**
     * AJAX: Record manual payment.
     */
    public function ajax_record_payment() {
        check_ajax_referer( 'apd_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment' ) );
        }

        $order_id = intval( $_POST['order_id'] ?? 0 );
        $amount   = floatval( $_POST['amount'] ?? 0 );

        if ( ! $order_id || $amount <= 0 ) {
            wp_send_json_error( __( 'Invalid data.', 'advanced-partial-payment' ) );
        }

        $result = APD_Order::record_payment( $order_id, $amount, __( 'Manual payment recorded by admin', 'advanced-partial-payment' ) );

        if ( $result ) {
            wp_send_json_success( __( 'Payment recorded!', 'advanced-partial-payment' ) );
        } else {
            wp_send_json_error( __( 'Failed to record payment.', 'advanced-partial-payment' ) );
        }
    }

    /**
     * Custom styling for partially-paid status in orders list.
     */
    public function order_status_styles() {
        $screen = get_current_screen();
        if ( ! $screen ) return;
        ?>
        <style>
            .order-status.status-partially-paid {
                background: #fff3e0;
                color: #e65100;
            }
            mark.partially-paid {
                background: #fff3e0;
                color: #e65100;
            }
            @media (max-width: 1280px) {
                .apd-admin-payment-record > div:first-child {
                    grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                }
            }
            @media (max-width: 782px) {
                .apd-admin-payment-record > div:first-child {
                    grid-template-columns: 1fr !important;
                }
            }
        </style>
        <?php
    }

    /**
     * Get a WC order object from either a post or an order instance.
     *
     * @param mixed $post_or_order Post or order.
     * @return WC_Order|false
     */
    private function get_order_object( $post_or_order ) {
        return ( $post_or_order instanceof WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;
    }

    /**
     * Format a readable payment type label.
     *
     * @param array $entry Payment history entry.
     * @return string
     */
    private function get_payment_type_label( $entry ) {
        $type = $entry['type'] ?? '';

        switch ( $type ) {
            case 'deposit':
                return __( 'Initial Deposit', 'advanced-partial-payment' );
            case 'balance_payment':
                return __( 'Remaining Balance', 'advanced-partial-payment' );
            default:
                return __( 'Payment', 'advanced-partial-payment' );
        }
    }
}
