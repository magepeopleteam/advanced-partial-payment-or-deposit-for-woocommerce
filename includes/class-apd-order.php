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

        $this->reconcile_deposit_order(
            $order,
            $order->get_status(),
            __( 'Balance payment completed on thank-you page.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
        );
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

        $this->reconcile_deposit_order(
            $order,
            $to_status,
            __( 'Balance payment recorded after order status update.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
        );
    }

    /**
     * Reconcile a deposit order against the status it just landed on.
     *
     * These are fallbacks for gateways that never call payment_complete(). A status on
     * its own is not proof of payment, so a pending balance is only written off when
     * self::is_balance_payment_captured() confirms funds were actually taken.
     *
     * @param WC_Order $order         Order object.
     * @param string   $status        Status being evaluated.
     * @param string   $finalize_note Note stored against a captured balance payment.
     */
    private function reconcile_deposit_order( $order, $status, $finalize_note ) {
        if ( ! in_array( $status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
            return;
        }

        if ( self::has_pending_balance_payment( $order ) ) {
            if ( self::is_balance_payment_captured( $order, $status ) ) {
                $this->finalize_pending_balance_payment( $order, $finalize_note );
                return;
            }

            // No funds captured: keep the balance owed instead of closing the order out.
            if ( self::is_offline_payment_method( $order ) ) {
                $this->hold_uncaptured_balance_payment( $order, $status );
            }
            return;
        }

        if ( self::order_has_outstanding_balance( $order ) ) {
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

            /**
             * A cancelled order is a decision the shop made, so paying a balance must not
             * silently reinstate it. Opt back in only if a shop really wants that.
             */
            if ( apply_filters( 'apd_allow_payment_on_cancelled_order', false, $order ) ) {
                $statuses[] = 'cancelled';
            }
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

        // Any recorded payment supersedes an in-flight attempt, so drop the markers to
        // stop a stale pending amount from being banked a second time later on.
        $order->delete_meta_data( '_apd_balance_payment_pending' );
        $order->delete_meta_data( '_apd_balance_payment_awaiting_offline' );

        // Never bank more than is actually owed. The admin box already caps its input
        // client-side; this makes the server agree, so "paid" can't exceed the total.
        $outstanding = max( 0, $total - $amount_paid );
        $amount      = min( round( floatval( $amount ), wc_get_price_decimals() ), $outstanding );

        if ( $amount <= 0 ) {
            return false;
        }

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
     * Whether the shop allows a customer to pay a balance off in freely chosen amounts.
     *
     * Off by default: the free plugin always charges the whole outstanding balance.
     * The Pro "Flexible Payments" setting turns this on.
     *
     * @param WC_Order|null $order Order object.
     * @return bool
     */
    public static function are_partial_balance_payments_enabled( $order = null ) {
        return (bool) apply_filters( 'apd_allow_partial_balance_payments', false, $order );
    }

    /**
     * Resolve the per-product / per-category Flexible Payments override for an order.
     *
     * Walks the order's line items, checking product meta first and then the product's
     * categories, mirroring how deposit settings resolve elsewhere. An explicit "yes"
     * anywhere in the order wins, because the setting is a customer convenience and a
     * mixed basket should not take it away.
     *
     * @param WC_Order|int|null $order Order object or ID.
     * @return string 'yes', 'no', or '' to inherit the global setting.
     */
    public static function get_flexible_payment_override( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
            return '';
        }

        $found_no = false;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            if ( ! $product_id ) {
                continue;
            }

            $override = get_post_meta( $product_id, '_apd_flexible_payments', true );

            // Fall back to the product's categories when the product itself inherits.
            if ( 'yes' !== $override && 'no' !== $override ) {
                $product = wc_get_product( $product_id );

                if ( $product ) {
                    foreach ( $product->get_category_ids() as $cat_id ) {
                        $cat_override = get_term_meta( $cat_id, '_apd_flexible_payments', true );

                        if ( 'yes' === $cat_override || 'no' === $cat_override ) {
                            $override = $cat_override;
                            break;
                        }
                    }
                }
            }

            if ( 'yes' === $override ) {
                return 'yes';
            }

            if ( 'no' === $override ) {
                $found_no = true;
            }
        }

        return $found_no ? 'no' : '';
    }

    /**
     * Resolve a per-product / per-category minimum for a single balance payment.
     *
     * Percentages are resolved against the full booking total here rather than the
     * remaining balance, so the floor does not creep downwards as the customer pays.
     * Where a basket carries several overrides the lowest wins, matching the
     * most-permissive rule used for the on/off override.
     *
     * @param WC_Order|int|null $order Order object or ID.
     * @return float|null Amount, or null to fall back to the global setting.
     */
    public static function get_flexible_minimum_override( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order || ! method_exists( $order, 'get_items' ) ) {
            return null;
        }

        $total  = floatval( $order->get_meta( '_apd_total_amount' ) );
        $lowest = null;

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            if ( ! $product_id ) {
                continue;
            }

            $value = get_post_meta( $product_id, '_apd_flexible_min_payment', true );
            $type  = get_post_meta( $product_id, '_apd_flexible_min_payment_type', true );

            // Fall back to the product's categories when the product sets nothing.
            if ( '' === $value || null === $value || false === $value ) {
                $product = wc_get_product( $product_id );

                if ( $product ) {
                    foreach ( $product->get_category_ids() as $cat_id ) {
                        $cat_value = get_term_meta( $cat_id, '_apd_flexible_min_payment', true );

                        if ( '' !== $cat_value && null !== $cat_value && false !== $cat_value ) {
                            $value = $cat_value;
                            $type  = get_term_meta( $cat_id, '_apd_flexible_min_payment_type', true );
                            break;
                        }
                    }
                }
            }

            if ( '' === $value || null === $value || false === $value ) {
                continue;
            }

            $amount = floatval( $value );

            if ( $amount <= 0 ) {
                continue;
            }

            if ( 'percentage' === $type && $total > 0 ) {
                $amount = ( $total * min( $amount, 100 ) ) / 100;
            }

            $amount = round( $amount, wc_get_price_decimals() );

            if ( null === $lowest || $amount < $lowest ) {
                $lowest = $amount;
            }
        }

        return $lowest;
    }

    /**
     * Smallest and largest amount a customer may put towards a balance right now.
     *
     * @param WC_Order|int $order Order object or ID.
     * @return array{min:float,max:float}
     */
    public static function get_balance_payment_bounds( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        $decimals = wc_get_price_decimals();
        $max      = $order ? round( floatval( $order->get_meta( '_apd_balance_due' ) ), $decimals ) : 0;
        $min      = $max;

        if ( $max > 0 && self::are_partial_balance_payments_enabled( $order ) ) {
            $min = round( floatval( apply_filters( 'apd_min_balance_payment', 0, $order, $max ) ), $decimals );

            // Fall back to the smallest unit the currency can express.
            if ( $min <= 0 ) {
                $min = 1 / pow( 10, $decimals );
            }

            // A minimum larger than what is left just means "pay it off".
            if ( $min > $max ) {
                $min = $max;
            }
        }

        return array( 'min' => $min, 'max' => $max );
    }

    /**
     * Resolve how much a customer is actually allowed to pay towards a balance.
     *
     * Always returns the full outstanding balance while flexible payments are off, so
     * the amount can never be steered from the request in the default configuration.
     *
     * @param WC_Order|int $order     Order object or ID.
     * @param mixed        $requested Amount asked for, from the request.
     * @return float
     */
    public static function sanitize_balance_payment_amount( $order, $requested ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        $bounds = self::get_balance_payment_bounds( $order );

        if ( ! self::are_partial_balance_payments_enabled( $order ) ) {
            return $bounds['max'];
        }

        $amount = round( floatval( $requested ), wc_get_price_decimals() );

        // No amount asked for means "settle the whole thing".
        if ( $amount <= 0 ) {
            return $bounds['max'];
        }

        if ( $amount < $bounds['min'] ) {
            $amount = $bounds['min'];
        }

        if ( $amount > $bounds['max'] ) {
            $amount = $bounds['max'];
        }

        return $amount;
    }

    /**
     * Payment methods that confirm an order without capturing any money.
     *
     * Offline gateways move an order to on-hold or processing as a promise to pay
     * later, so they can never be treated as a settled balance payment on their own.
     *
     * @return string[]
     */
    public static function get_offline_payment_methods() {
        return array_filter( (array) apply_filters(
            'apd_offline_payment_methods',
            array( 'cod', 'bacs', 'cheque' )
        ) );
    }

    /**
     * Check whether the payment method on an order captures funds.
     *
     * @param WC_Order|int|null $order Order object or ID.
     * @return bool
     */
    public static function is_offline_payment_method( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return false;
        }

        $payment_method = $order->get_payment_method();

        return $payment_method && in_array( $payment_method, self::get_offline_payment_methods(), true );
    }

    /**
     * Check whether a status transition really represents money collected for the balance.
     *
     * @param WC_Order $order  Order object.
     * @param string   $status Status being evaluated.
     * @return bool
     */
    public static function is_balance_payment_captured( $order, $status ) {
        // on-hold is WooCommerce's "awaiting payment" state, never a settled payment.
        if ( ! in_array( $status, array( 'processing', 'completed' ), true ) ) {
            return false;
        }

        if ( self::is_offline_payment_method( $order ) ) {
            return false;
        }

        return (bool) apply_filters( 'apd_is_balance_payment_captured', true, $order, $status );
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
        $order->delete_meta_data( '_apd_balance_payment_awaiting_offline' );
        $order->save();

        self::record_payment( $order->get_id(), $pending_balance_payment, $note );
    }

    /**
     * Keep an uncaptured balance payment owed instead of writing it off as paid.
     *
     * The customer picked an offline gateway, which only records an intent to pay, so
     * the balance stays due and the order stays payable until the shop records the
     * money manually from the order screen.
     *
     * @param WC_Order $order  Order object.
     * @param string   $status Status the order just landed on.
     */
    private function hold_uncaptured_balance_payment( $order, $status ) {
        $payment_method = $order->get_payment_method();
        $dirty          = false;

        // Flag per gateway so the note is written once, not on every thank-you page hit.
        if ( $order->get_meta( '_apd_balance_payment_awaiting_offline' ) !== $payment_method ) {
            $order->update_meta_data( '_apd_balance_payment_awaiting_offline', $payment_method );
            $order->add_order_note(
                sprintf(
                    /* translators: 1: payment method title, 2: outstanding balance amount. */
                    __( 'Remaining balance payment was started with %1$s, which does not capture funds. %2$s is still outstanding and must be recorded manually once the money is received.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    $order->get_payment_method_title() ? $order->get_payment_method_title() : $payment_method,
                    wc_price( floatval( $order->get_meta( '_apd_balance_payment_pending' ) ) )
                )
            );
            $dirty = true;
        }

        // A paid-looking status would hide the outstanding balance from the shop.
        if ( in_array( $status, array( 'processing', 'completed' ), true ) && self::order_has_outstanding_balance( $order ) ) {
            $order->set_status( 'partially-paid' );
            $dirty = true;
        }

        if ( $dirty ) {
            $order->save();
        }
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
