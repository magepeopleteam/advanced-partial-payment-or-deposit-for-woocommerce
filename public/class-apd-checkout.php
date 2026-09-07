<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Checkout deposit integration.
 */
class APD_Checkout {

    public function __construct() {
        // Show deposit summary on checkout
        add_action( 'woocommerce_review_order_after_order_total', array( $this, 'display_checkout_deposit_summary' ) );
        // Modify the order total at checkout for deposit
        add_action( 'woocommerce_checkout_create_order', array( $this, 'modify_order_for_deposit' ), 10, 2 );
        // Show deposit info on order-received page
        add_action( 'woocommerce_thankyou', array( $this, 'thankyou_deposit_info' ), 5, 1 );
        // Show deposit info on order details
        add_action( 'woocommerce_order_details_before_order_table', array( $this, 'maybe_hide_order_again_button' ), 1, 1 );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'order_details_deposit_info' ), 10, 1 );
        // Hide reorder action for deposit orders.
        add_filter( 'woocommerce_valid_order_statuses_for_order_again', array( $this, 'disable_order_again_for_deposits' ), 10, 2 );
    }

    /**
     * Display deposit summary on checkout review.
     */
    public function display_checkout_deposit_summary() {
        $deposit_engine = APD_Deposit::instance();
        $summary = $deposit_engine->get_cart_payment_summary();

        if ( empty( $summary['has_deposit'] ) ) {
            return;
        }

        $settings      = get_option( 'apd_settings', array() );
        $deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' );
        $balance_label = $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment' );
        $deposit_total = $summary['deposit_amount'];
        $balance_due   = $summary['balance_due'];

        include APD_PLUGIN_DIR . 'public/views/checkout-deposit-summary.php';
    }

    /**
     * Modify order creation for deposit.
     */
    public function modify_order_for_deposit( $order, $data ) {
        // The actual deposit data saving is handled by APD_Order class
        // via the woocommerce_checkout_order_created hook
    }

    /**
     * Show deposit info on thank-you page.
     */
    public function thankyou_deposit_info( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! APD_Order::is_deposit_order( $order ) ) {
            return;
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details ) return;

        $settings      = get_option( 'apd_settings', array() );
        $deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' );
        $balance_label = $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment' );
        ?>
        <div class="apd-thankyou-deposit">
            <h3><?php esc_html_e( 'Deposit Payment Summary', 'advanced-partial-payment' ); ?></h3>
            <table class="apd-deposit-table">
                <tr>
                    <td><?php esc_html_e( 'Order Total', 'advanced-partial-payment' ); ?></td>
                    <td><?php echo wc_price( $details['total_amount'] ); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html( $deposit_label ); ?> <?php esc_html_e( 'Paid', 'advanced-partial-payment' ); ?></td>
                    <td class="apd-text-success"><?php echo wc_price( $details['deposit_amount'] ); ?></td>
                </tr>
                <?php if ( $details['balance_due'] > 0 ) : ?>
                <tr class="apd-balance-row">
                    <td><strong><?php echo esc_html( $balance_label ); ?></strong></td>
                    <td class="apd-text-danger"><strong><?php echo wc_price( $details['balance_due'] ); ?></strong></td>
                </tr>
                <?php endif; ?>
            </table>
            <?php if ( $details['balance_due'] > 0 ) : ?>
            <p class="apd-thankyou-note">
                <?php esc_html_e( 'You can pay the remaining balance from your account page.', 'advanced-partial-payment' ); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Show deposit info on order details page.
     */
    public function order_details_deposit_info( $order ) {
        if ( ! APD_Order::is_deposit_order( $order ) ) {
            return;
        }

        $details = APD_Order::get_deposit_details( $order );
        if ( ! $details ) return;

        $settings      = get_option( 'apd_settings', array() );
        $deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment' );
        $balance_label = $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment' );
        ?>
        <div class="apd-order-deposit-details">
            <h3><?php esc_html_e( 'Deposit Information', 'advanced-partial-payment' ); ?></h3>
            <table class="apd-deposit-table">
                <tr>
                    <td><?php esc_html_e( 'Full Order Total', 'advanced-partial-payment' ); ?></td>
                    <td><?php echo wc_price( $details['total_amount'] ); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html( $deposit_label ); ?></td>
                    <td><?php echo wc_price( $details['deposit_amount'] ); ?></td>
                </tr>
                <tr>
                    <td><?php esc_html_e( 'Total Paid', 'advanced-partial-payment' ); ?></td>
                    <td class="apd-text-success"><?php echo wc_price( $details['amount_paid'] ); ?></td>
                </tr>
                <tr class="apd-balance-row">
                    <td><strong><?php echo esc_html( $balance_label ); ?></strong></td>
                    <td class="<?php echo $details['balance_due'] > 0 ? 'apd-text-danger' : 'apd-text-success'; ?>">
                        <strong><?php echo wc_price( $details['balance_due'] ); ?></strong>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Remove WooCommerce's reorder button output for deposit orders.
     *
     * @param WC_Order $order Order object.
     */
    public function maybe_hide_order_again_button( $order ) {
        if ( $order && APD_Order::is_deposit_order( $order ) ) {
            remove_action( 'woocommerce_order_details_after_order_table', 'woocommerce_order_again_button' );
        }
    }

    /**
     * Disable WooCommerce's "Order again" button for deposit orders.
     *
     * @param array    $statuses Allowed statuses.
     * @param WC_Order $order    Order object.
     * @return array
     */
    public function disable_order_again_for_deposits( $statuses, $order = null ) {
        if ( $order && APD_Order::is_deposit_order( $order ) ) {
            return array();
        }

        return $statuses;
    }
}
