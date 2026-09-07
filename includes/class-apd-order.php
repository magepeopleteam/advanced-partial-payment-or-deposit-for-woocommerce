<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Order deposit tracking and balance management.
 */
class APD_Order {

    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Save deposit info when order is created
        add_action( 'woocommerce_checkout_order_created', array( $this, 'save_deposit_data' ), 10, 1 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_deposit_data' ), 10, 1 );
        // After payment complete, check if partially paid
        add_action( 'woocommerce_payment_complete', array( $this, 'maybe_set_partially_paid' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'maybe_finalize_pending_balance_payment' ), 1, 1 );
        add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_finalize_pending_balance_payment_on_status_change' ), 10, 4 );
        add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'payment_complete_order_status' ), 10, 3 );
        // Allow partially-paid orders to be paid
        add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'valid_statuses_for_payment' ), 10, 2 );
        // Add partially paid to valid statuses for order received page
        add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'valid_statuses_for_payment_complete' ), 10, 2 );
    }

    /**
     * Save deposit data to order meta.
     */
    public function save_deposit_data( $order ) {
        $deposit_engine = APD_Deposit::instance();
        $summary         = $deposit_engine->get_cart_payment_summary();

        if ( empty( $summary['has_deposit'] ) ) {
            return;
        }

        $full_total     = $summary['full_total'];
        $deposit_amount = $summary['deposit_amount'];
        $balance_due    = $summary['balance_due'];

        // Nothing is actually deferred, so this is a normal full payment. Guarding here
        // stops a misconfigured deposit from writing a zero-value order.
        if ( $deposit_amount <= 0 || $balance_due <= 0 ) {
            return;
        }

        // Save meta
        $order->update_meta_data( '_apd_is_deposit', 'yes' );
        $order->update_meta_data( '_apd_deposit_amount', $deposit_amount );
        $order->update_meta_data( '_apd_total_amount', $full_total );
        $order->update_meta_data( '_apd_amount_paid', $deposit_amount );
        $order->update_meta_data( '_apd_balance_due', $balance_due );
        $order->update_meta_data( '_apd_payment_history', array(
            array(
                'type'   => 'deposit',
                'amount' => $deposit_amount,
                'date'   => current_time( 'mysql' ),
                'note'   => __( 'Initial deposit payment', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
            ),
        ) );

        // Set the order total to deposit amount
        $order->set_total( $deposit_amount );
        $order->save();
    }

    /**
     * Set order to partially-paid after deposit payment completes.
     */
    public function maybe_set_partially_paid( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $is_deposit = $order->get_meta( '_apd_is_deposit' );
        if ( $is_deposit !== 'yes' ) {
            return;
        }

        $pending_balance_payment = floatval( $order->get_meta( '_apd_balance_payment_pending' ) );
        if ( $pending_balance_payment > 0 ) {
            $this->finalize_pending_balance_payment(
                $order,
                __( 'Balance payment received.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
            );
            return;
        }

        $balance_due = floatval( $order->get_meta( '_apd_balance_due' ) );
        if ( $balance_due > 0 ) {
            $order->set_status( 'partially-paid', __( 'Deposit payment received. Balance due: ', 'advanced-partial-payment-or-deposit-for-woocommerce' ) . wc_price( $balance_due ) );
            $order->save();

            do_action( 'apd_deposit_payment_complete', $order_id, $order );
        }
    }

    /**
     * Finalize a pending balance payment on the thank-you page for gateways that do not call payment_complete().
     *
     * @param int $order_id Order ID.
     */
    public function maybe_finalize_pending_balance_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order || ! self::is_deposit_order( $order ) ) {
            return;
        }

        if ( self::has_pending_balance_payment( $order ) && in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {
            $this->finalize_pending_balance_payment(
                $order,
                __( 'Balance payment completed on thank-you page.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
            );
            return;
        }

        if ( self::order_has_outstanding_balance( $order ) && in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ), true ) ) {
            $this->normalize_outstanding_deposit_status(
                $order,
                __( 'Deposit payment received. Balance is still due.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
            );
        }
    }

    /**
     * Finalize pending balance payments when gateways move the order status without firing payment_complete().
     *
     * @param int      $order_id     Order ID.
     * @param string   $from_status  Previous status.
     * @param string   $to_status    New status.
     * @param WC_Order $order        Order object.
     */
    public function maybe_finalize_pending_balance_payment_on_status_change( $order_id, $from_status, $to_status, $order ) {
        if ( ! $order || ! self::is_deposit_order( $order ) ) {
            return;
        }

        if ( self::has_pending_balance_payment( $order ) && in_array( $to_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
            $this->finalize_pending_balance_payment(
                $order,
                __( 'Balance payment recorded after order status update.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
            );
            return;
        }

        if ( self::order_has_outstanding_balance( $order ) && in_array( $to_status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
            $this->normalize_outstanding_deposit_status(
                $order,
                __( 'Deposit payment received. Balance is still due.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
            );
        }
    }

    /**
     * Force deposit orders with outstanding balances into a payable partial status after payment completion.
     *
     * @param string   $status   Next order status.
     * @param int      $order_id Order ID.
     * @param WC_Order $order    Order object.
     * @return string
     */
    public function payment_complete_order_status( $status, $order_id, $order ) {
        if ( ! $order ) {
            $order = wc_get_order( $order_id );
        }

        if ( $order && self::order_has_outstanding_balance( $order ) ) {
            return 'partially-paid';
        }

        return $status;
    }

    /**
     * Allow partially-paid orders to be paid.
     */
    public function valid_statuses_for_payment( $statuses, $order = null ) {
        $statuses[] = 'partially-paid';

        if ( self::order_has_outstanding_balance( $order ) ) {
            $statuses[] = 'processing';
            $statuses[] = 'completed';
            $statuses[] = 'on-hold';
            $statuses[] = 'cancelled';
        }

        return array_values( array_unique( $statuses ) );
    }

    /**
     * Allow partially-paid in payment complete statuses.
     */
    public function valid_statuses_for_payment_complete( $statuses, $order = null ) {
        $statuses[] = 'partially-paid';

        if ( self::order_has_outstanding_balance( $order ) || self::has_pending_balance_payment( $order ) ) {
            $statuses[] = 'processing';
            $statuses[] = 'completed';
            $statuses[] = 'on-hold';
            $statuses[] = 'cancelled';
        }

        return array_values( array_unique( $statuses ) );
    }

    /**
     * Record a balance payment.
     */
    public static function record_payment( $order_id, $amount, $note = '' ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return false;
        }

        $amount_paid = floatval( $order->get_meta( '_apd_amount_paid' ) );
        $total       = floatval( $order->get_meta( '_apd_total_amount' ) );

        $new_paid    = $amount_paid + $amount;
        $new_balance = max( 0, $total - $new_paid );

        $order->update_meta_data( '_apd_amount_paid', round( $new_paid, wc_get_price_decimals() ) );
        $order->update_meta_data( '_apd_balance_due', round( $new_balance, wc_get_price_decimals() ) );

        if ( $new_balance > 0 ) {
            $order->set_total( round( $new_balance, wc_get_price_decimals() ) );
            $order->set_status( 'partially-paid' );
        } else {
            $order->set_total( round( $total, wc_get_price_decimals() ) );
        }

        // Add to payment history
        $history = $order->get_meta( '_apd_payment_history' );
        if ( ! is_array( $history ) ) {
            $history = array();
        }
        $history[] = array(
            'type'   => 'balance_payment',
            'amount' => $amount,
            'date'   => current_time( 'mysql' ),
            'note'   => $note ? $note : __( 'Balance payment recorded', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
        );
        $order->update_meta_data( '_apd_payment_history', $history );

        $is_fully_paid = $new_balance <= 0;

        // If fully paid, update status
        if ( $is_fully_paid ) {
            $order->set_status( 'completed', __( 'Full balance paid.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
        }

        $order->add_order_note(
            sprintf(
                /* translators: 1: payment amount, 2: balance due */
                __( 'Balance payment of %1$s recorded. Remaining balance: %2$s', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                wc_price( $amount ),
                wc_price( $new_balance )
            )
        );

        $order->save();

        if ( $is_fully_paid ) {
            try {
                do_action( 'apd_full_payment_complete', $order_id, $order );
            } catch ( \Throwable $e ) {
                wc_caught_exception( $e );
            }
        }

        return true;
    }

    /**
     * Check if order is a deposit order.
     */
    public static function is_deposit_order( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }
        return $order && $order->get_meta( '_apd_is_deposit' ) === 'yes';
    }

    /**
     * Check whether a deposit order still has balance due.
     *
     * @param WC_Order|int|null $order Order object or ID.
     * @return bool
     */
    public static function order_has_outstanding_balance( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order || ! self::is_deposit_order( $order ) ) {
            return false;
        }

        return floatval( $order->get_meta( '_apd_balance_due' ) ) > 0;
    }

    /**
     * Check whether a balance payment is currently in progress for an order.
     *
     * @param WC_Order|int|null $order Order object or ID.
     * @return bool
     */
    public static function has_pending_balance_payment( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order || ! self::is_deposit_order( $order ) ) {
            return false;
        }

        return floatval( $order->get_meta( '_apd_balance_payment_pending' ) ) > 0;
    }

    /**
     * Get deposit details for an order.
     */
    public static function get_deposit_details( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order || ! self::is_deposit_order( $order ) ) {
            return false;
        }

        return array(
            'is_deposit'     => true,
            'deposit_amount' => floatval( $order->get_meta( '_apd_deposit_amount' ) ),
            'total_amount'   => floatval( $order->get_meta( '_apd_total_amount' ) ),
            'amount_paid'    => floatval( $order->get_meta( '_apd_amount_paid' ) ),
            'balance_due'    => floatval( $order->get_meta( '_apd_balance_due' ) ),
            'history'        => $order->get_meta( '_apd_payment_history' ),
        );
    }

    /**
     * Finalize the currently pending balance payment for a deposit order.
     *
     * @param WC_Order $order Order object.
     * @param string   $note  Payment note.
     */
    private function finalize_pending_balance_payment( $order, $note ) {
        $pending_balance_payment = floatval( $order->get_meta( '_apd_balance_payment_pending' ) );

        if ( $pending_balance_payment <= 0 ) {
            return;
        }

        $order->delete_meta_data( '_apd_balance_payment_pending' );
        $order->save();

        self::record_payment( $order->get_id(), $pending_balance_payment, $note );
    }

    /**
     * Force deposit orders with an outstanding balance into the partially-paid status.
     *
     * @param WC_Order $order Order object.
     * @param string   $note  Status note.
     */
    private function normalize_outstanding_deposit_status( $order, $note ) {
        if ( 'partially-paid' === $order->get_status() || ! self::order_has_outstanding_balance( $order ) ) {
            return;
        }

        $order->set_status( 'partially-paid', $note );
        $order->save();

        do_action( 'apd_deposit_payment_complete', $order->get_id(), $order );
    }
}
