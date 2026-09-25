<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order deposit tracking and balance management.
 */
class APD_Order {

	private static $instance = null;

	/**
	 * Orders whose in-flight status transition was made by hand, keyed by order ID.
	 *
	 * @var array<int,bool>
	 */
	private $manual_transitions = array();

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
		// woocommerce_order_status_{to} fires just before woocommerce_order_status_changed and
		// is the only transition hook that says whether a person made the change.
		foreach ( array( 'processing', 'completed', 'on-hold' ) as $watched_status ) {
			add_action( 'woocommerce_order_status_' . $watched_status, array( $this, 'remember_manual_transition' ), 1, 3 );
		}
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_finalize_pending_balance_payment_on_status_change' ), 10, 4 );
		add_filter( 'woocommerce_payment_complete_order_status', array( $this, 'payment_complete_order_status' ), 10, 3 );
		// Allow partially-paid orders to be paid
		add_filter( 'woocommerce_valid_order_statuses_for_payment', array( $this, 'valid_statuses_for_payment' ), 10, 2 );
		// Add partially paid to valid statuses for order received page
		add_filter( 'woocommerce_valid_order_statuses_for_payment_complete', array( $this, 'valid_statuses_for_payment_complete' ), 10, 2 );
		// Full order value + deposit / balance rows in order totals. Registered here, not in
		// the frontend classes, because order emails are also sent from admin and cron requests.
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'order_item_totals_deposit_rows' ), 10, 3 );
	}

	/**
	 * Save deposit data to order meta.
	 */
	public function save_deposit_data( $order ) {
		$deposit_engine = APD_Deposit::instance();
		$summary        = $deposit_engine->get_cart_payment_summary();

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
		$order->update_meta_data(
			'_apd_payment_history',
			array(
				array(
					'type'   => 'deposit',
					'amount' => $deposit_amount,
					'date'   => current_time( 'mysql' ),
					'note'   => __( 'Initial deposit payment', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
				),
			)
		);

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
		$is_manual = ! empty( $this->manual_transitions[ $order_id ] );
		unset( $this->manual_transitions[ $order_id ] );

		if ( ! $order || ! self::is_deposit_order( $order ) ) {
			return;
		}

		// Keep the decision on the order, so a later automatic pass (e.g. the customer
		// reopening the thank-you page) does not undo it either.
		if ( $is_manual ) {
			$order->update_meta_data( '_apd_manual_status', $to_status );
			$order->save_meta_data();
		} elseif ( '' !== (string) $order->get_meta( '_apd_manual_status' ) ) {
			$order->delete_meta_data( '_apd_manual_status' );
			$order->save_meta_data();
		}

		$this->reconcile_deposit_order(
			$order,
			$to_status,
			__( 'Balance payment recorded after order status update.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
			$is_manual
		);
	}

	/**
	 * Remember that the status transition in progress was made by a person.
	 *
	 * The admin order screen, the orders list bulk actions and the list's quick status
	 * buttons all flag their transitions as manual. WooCommerce versions that do not pass
	 * the transition data leave $transition empty, which keeps the previous behaviour.
	 *
	 * @param int           $order_id   Order ID.
	 * @param WC_Order|null $order      Order object.
	 * @param array         $transition Status transition data.
	 */
	public function remember_manual_transition( $order_id, $order = null, $transition = array() ) {
		if ( is_array( $transition ) && ! empty( $transition['manual'] ) ) {
			$this->manual_transitions[ $order_id ] = true;
		} else {
			unset( $this->manual_transitions[ $order_id ] );
		}
	}

	/**
	 * Reconcile a deposit order against the status it just landed on.
	 *
	 * These are fallbacks for gateways that never call payment_complete(). A status on
	 * its own is not proof of payment, so a pending balance is only written off when
	 * self::is_balance_payment_captured() confirms funds were actually taken.
	 *
	 * A status chosen by hand is the shop's decision (e.g. moving a deposit order into
	 * production while the balance is collected on completion), so it is never pushed
	 * back to partially-paid. The balance stays owed either way.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $status        Status being evaluated.
	 * @param string   $finalize_note Note stored against a captured balance payment.
	 * @param bool     $is_manual     Whether a person made this status change.
	 */
	private function reconcile_deposit_order( $order, $status, $finalize_note, $is_manual = false ) {
		if ( ! in_array( $status, array( 'processing', 'completed', 'on-hold' ), true ) ) {
			return;
		}

		if ( ! $is_manual && $status === $order->get_meta( '_apd_manual_status' ) ) {
			$is_manual = true;
		}

		if ( self::has_pending_balance_payment( $order ) ) {
			if ( self::is_balance_payment_captured( $order, $status ) ) {
				$this->finalize_pending_balance_payment( $order, $finalize_note );
				return;
			}

			// No funds captured: keep the balance owed instead of closing the order out.
			if ( self::is_offline_payment_method( $order ) ) {
				$this->hold_uncaptured_balance_payment( $order, $status, $is_manual );
			}
			return;
		}

		if ( ! $is_manual && self::order_has_outstanding_balance( $order ) ) {
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
	 * Show the full order value and the deposit split in an order's totals table.
	 *
	 * A deposit order's total is the amount charged in the current payment, so the core
	 * totals table (used by every order email, the thank-you page and My Account) showed
	 * the deposit as "Total" with nothing to say a balance is still owed. The Total row
	 * now shows the full order value, followed by the deposit and the remaining balance.
	 *
	 * @param array    $rows        Totals rows.
	 * @param WC_Order $order       Order object.
	 * @param string   $tax_display Tax display mode.
	 * @return array
	 */
	public function order_item_totals_deposit_rows( $rows, $order = null, $tax_display = '' ) {
		if ( ! is_array( $rows ) || ! $order instanceof WC_Order || ! isset( $rows['order_total'] ) ) {
			return $rows;
		}

		if ( ! apply_filters( 'apd_display_full_order_total', true, $order ) || ! self::is_deposit_order( $order ) ) {
			return $rows;
		}

		// On the pay page the total is exactly what the customer is about to pay.
		if ( function_exists( 'is_checkout_pay_page' ) && is_checkout_pay_page() ) {
			return $rows;
		}

		$details = self::get_deposit_details( $order );

		if ( ! $details || $details['total_amount'] <= 0 ) {
			return $rows;
		}

		$currency = array( 'currency' => $order->get_currency() );
		$decimals = wc_get_price_decimals();

		// Swap the charged amount for the full value, keeping WooCommerce's tax note.
		// A refunded order keeps WooCommerce's own struck-through net total untouched.
		if ( ! $order->get_total_refunded() && 0.0 !== round( $details['total_amount'] - (float) $order->get_total(), $decimals ) ) {
			$charged = wc_price( $order->get_total(), $currency );
			$value   = $rows['order_total']['value'];
			$at      = strpos( $value, $charged );

			if ( false !== $at ) {
				$rows['order_total']['value'] = substr_replace( $value, wc_price( $details['total_amount'], $currency ), $at, strlen( $charged ) );
			}
		}

		$settings      = get_option( 'apd_settings', array() );
		$deposit_label = $settings['deposit_label'] ?? __( 'Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' );
		$balance_label = $settings['due_balance_label'] ?? __( 'Due Balance', 'advanced-partial-payment-or-deposit-for-woocommerce' );

		$deposit_rows = array(
			'apd_deposit' => array(
				'label' => $deposit_label . ':',
				'value' => wc_price( $details['deposit_amount'], $currency ),
			),
		);

		// Only worth a row once something beyond the deposit has been paid.
		if ( round( $details['amount_paid'] - $details['deposit_amount'], $decimals ) > 0 ) {
			$deposit_rows['apd_amount_paid'] = array(
				'label' => __( 'Total Paid', 'advanced-partial-payment-or-deposit-for-woocommerce' ) . ':',
				'value' => wc_price( $details['amount_paid'], $currency ),
			);
		}

		if ( $details['balance_due'] > 0 ) {
			$deposit_rows['apd_balance_due'] = array(
				'label' => $balance_label . ':',
				'value' => wc_price( $details['balance_due'], $currency ),
			);
		}

		// Directly under the Total row.
		$position = array_search( 'order_total', array_keys( $rows ), true );

		return array_slice( $rows, 0, $position + 1, true ) + $deposit_rows + array_slice( $rows, $position + 1, null, true );
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

		// Persist the new balance BEFORE the status transition fires. The stock
		// deferral (Order Workflow "reduce on full payment") re-reads the order
		// during the completing transition; with the old balance still stored it
		// would keep holding the stock forever.
		$order->save();

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
			/**
			 * Status a deposit order lands on once the balance reaches zero.
			 *
			 * Shops with a fulfilment step after payment can land fully-paid orders on
			 * "processing" instead of "completed" — the Pro addon exposes this as a
			 * setting under Order Workflow.
			 *
			 * @param string   $status Order status without the wc- prefix.
			 * @param WC_Order $order  Order that was just paid off.
			 */
			$fully_paid_status = apply_filters( 'apd_fully_paid_status', 'completed', $order );
			$order->set_status( $fully_paid_status, __( 'Full balance paid.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
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

		return array(
			'min' => $min,
			'max' => $max,
		);
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
	 * Meta keys a gateway reads back to decide it has already been paid for this order.
	 *
	 * Only the keys that actually gate a second payment belong here. Descriptive leftovers
	 * of the previous charge — the charge id, the payment method, the balance transaction —
	 * are deliberately left alone: they gate nothing, the next payment overwrites them, and
	 * keeping them means an abandoned attempt does not cost the order its deposit reference.
	 *
	 * @param WC_Order|null $order Order the fingerprint belongs to.
	 * @return string[]
	 */
	public static function get_gateway_payment_meta_keys( $order = null ) {
		return array_filter(
			(array) apply_filters(
				'apd_gateway_payment_meta_keys',
				array(
					// WooPayments: the stored intent is fetched and its amount compared with the
					// order total, and the succeeded status suppresses the failure that follows.
					'_intent_id',
					'_intention_status',
					// The equivalent markers on the other gateways that reuse a stored intent.
					'_stripe_intent_id',
					'_ppcp_paypal_order_id',
				),
				$order
			)
		);
	}

	/**
	 * Stand down the gateway's "this order is already paid" fingerprint before a balance payment.
	 *
	 * A gateway records the intent and charge of the last successful payment against the order
	 * and reads them back to refuse a second one. WooPayments compares the stored intent's amount
	 * with the current order total, and on a deposit order those can never match: the deposit was
	 * charged, and the total is now the balance being collected. It throws, and because the *old*
	 * intent is still marked succeeded it then treats the throw as a post-payment hiccup, reports
	 * success to the customer and returns them to the thank-you page. No new payment is created,
	 * nothing is captured, and the balance is left untouched.
	 *
	 * A deposit order is paid more than once by design, so the fingerprint has to be released
	 * before each new leg. It is archived under our own meta and named in an order note first, so
	 * the reference to the earlier charge survives for reconciliation and refunds.
	 *
	 * @param WC_Order|int $order Order about to be sent to the pay page.
	 * @return void
	 */
	public static function release_gateway_payment_fingerprint( $order ) {
		if ( is_numeric( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return;
		}

		$archived = array();

		foreach ( self::get_gateway_payment_meta_keys( $order ) as $key ) {
			$value = $order->get_meta( $key );

			if ( '' === $value || null === $value || false === $value ) {
				continue;
			}

			$archived[ $key ] = $value;
			$order->delete_meta_data( $key );
		}

		if ( empty( $archived ) ) {
			return;
		}

		$log = $order->get_meta( '_apd_released_gateway_payments' );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'date'    => current_time( 'mysql' ),
			'gateway' => $order->get_payment_method(),
			'meta'    => $archived,
		);

		$order->update_meta_data( '_apd_released_gateway_payments', $log );
		$order->save();

		// Name the reference we released so the note stays useful on any gateway.
		$reference = '';

		foreach ( array( '_intent_id', '_stripe_intent_id', '_ppcp_paypal_order_id' ) as $ref_key ) {
			if ( ! $reference && ! empty( $archived[ $ref_key ] ) ) {
				$reference = $archived[ $ref_key ];
			}
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: payment gateway title, 2: transaction reference of the previous payment. */
				__( 'Released the %1$s payment reference (%2$s) held against this order so the remaining balance can be taken as a new payment. The reference is kept on the order for reconciliation.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
				$order->get_payment_method_title() ? $order->get_payment_method_title() : $order->get_payment_method(),
				$reference ? $reference : __( 'no reference stored', 'advanced-partial-payment-or-deposit-for-woocommerce' )
			)
		);
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
		return array_filter(
			(array) apply_filters(
				'apd_offline_payment_methods',
				array( 'cod', 'bacs', 'cheque' )
			)
		);
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
	 * @param WC_Order $order     Order object.
	 * @param string   $status    Status the order just landed on.
	 * @param bool     $is_manual Whether a person chose that status.
	 */
	private function hold_uncaptured_balance_payment( $order, $status, $is_manual = false ) {
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
		if ( ! $is_manual && in_array( $status, array( 'processing', 'completed' ), true ) && self::order_has_outstanding_balance( $order ) ) {
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
