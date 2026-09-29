<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deposits on orders created or edited in the admin.
 *
 * Orders entered by staff (the WooCommerce "Add order" screen, or a booking plugin that
 * builds the order in wp-admin) never pass through the cart, so no deposit is ever
 * calculated for them. This lets an administrator set one on the order itself, and keeps
 * the deposit in place when WooCommerce recalculates the order totals.
 */
class APD_Admin_Order_Deposit {

	/**
	 * Order totals captured just before WooCommerce recalculates them, keyed by order ID.
	 *
	 * @var array<int,float>
	 */
	private $totals_before_recalculation = array();

	public function __construct() {
		add_action( 'wp_ajax_apd_set_order_deposit', array( $this, 'ajax_set_order_deposit' ) );
		add_action( 'wp_ajax_apd_remove_order_deposit', array( $this, 'ajax_remove_order_deposit' ) );
		// Saving the order screen, "Recalculate" and booking plugins that add items all run
		// calculate_totals(), which resets the total to the full value.
		add_action( 'woocommerce_order_before_calculate_totals', array( $this, 'remember_total_before_recalculation' ), 10, 2 );
		add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'restore_deposit_total_after_recalculation' ), 10, 2 );
	}

	/**
	 * Whether administrators may set deposits on orders from the order screen.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return 'yes' === apd_get_option( 'admin_order_deposit', 'yes' );
	}

	/**
	 * Whether a deposit can be added to an order that does not have one yet.
	 *
	 * Only orders that have not been paid qualify. A new order has to be saved first,
	 * because reloading the "Add order" screen starts a different draft.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public static function can_add_deposit( $order ) {
		if ( ! $order instanceof WC_Order || 'shop_order' !== $order->get_type() || APD_Order::is_deposit_order( $order ) ) {
			return false;
		}

		/**
		 * Order statuses on which an administrator may add a deposit.
		 *
		 * @param string[] $statuses Statuses without the wc- prefix.
		 * @param WC_Order $order    Order object.
		 */
		$statuses = (array) apply_filters( 'apd_admin_order_deposit_statuses', array( 'pending', 'on-hold', 'partially-paid' ), $order );

		return in_array( $order->get_status(), $statuses, true ) && ! $order->get_total_refunded();
	}

	/**
	 * Whether the deposit on an order can still be changed or removed.
	 *
	 * Once any money has been recorded the deposit is part of the payment history, so
	 * it is left alone.
	 *
	 * @param WC_Order $order Order object.
	 * @return bool
	 */
	public static function can_edit_deposit( $order ) {
		if ( ! $order instanceof WC_Order || ! APD_Order::is_deposit_order( $order ) ) {
			return false;
		}

		return ! APD_Order::is_initial_deposit_paid( $order )
			&& floatval( $order->get_meta( '_apd_amount_paid' ) ) <= 0
			&& ! APD_Order::has_pending_balance_payment( $order )
			&& ! $order->get_total_refunded()
			&& ! in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed' ), true );
	}

	/**
	 * Work out the deposit for an order value.
	 *
	 * @param float $full_total Full order value.
	 * @param array $rule       Deposit rule: type (fixed|percentage) and value.
	 * @return float
	 */
	public static function calculate_deposit( $full_total, $rule ) {
		$value = isset( $rule['value'] ) ? floatval( $rule['value'] ) : 0;

		if ( isset( $rule['type'] ) && 'percentage' === $rule['type'] ) {
			$value = ( $full_total * $value ) / 100;
		}

		return round( max( 0, $value ), wc_get_price_decimals() );
	}

	/**
	 * Render the set / edit deposit controls inside the Deposit Details metabox.
	 *
	 * The metabox sits inside the order form, so the inputs carry no name attribute:
	 * they are sent by AJAX only and never posted with the order itself.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function render_form( $order ) {
		$is_edit = APD_Order::is_deposit_order( $order );

		if ( $is_edit ) {
			$full_total = floatval( $order->get_meta( '_apd_total_amount' ) );
			$rule       = $order->get_meta( '_apd_deposit_rule' );

			if ( ! is_array( $rule ) || empty( $rule['type'] ) ) {
				$rule = array(
					'type'  => 'fixed',
					'value' => floatval( $order->get_meta( '_apd_deposit_amount' ) ),
				);
			}
		} else {
			$full_total   = floatval( $order->get_total() );
			$default_type = apd_get_option( 'deposit_type', 'percentage' );
			$rule         = array(
				'type'  => 'fixed' === $default_type ? 'fixed' : 'percentage',
				'value' => floatval( apd_get_option( 'deposit_value', 50 ) ),
			);
		}

		$currency_symbol = get_woocommerce_currency_symbol( $order->get_currency() );
		$field_style     = 'width:100%;max-width:100%;box-sizing:border-box;';
		?>
		<div class="apd-order-deposit-form" style="margin-top:12px;padding-top:12px;border-top:1px solid #e2e4e7;"
			data-total="<?php echo esc_attr( wc_format_decimal( $full_total, wc_get_price_decimals() ) ); ?>"
			data-decimals="<?php echo esc_attr( wc_get_price_decimals() ); ?>"
			data-currency="<?php echo esc_attr( html_entity_decode( $currency_symbol, ENT_QUOTES, 'UTF-8' ) ); ?>">
			<p style="margin:0 0 8px;font-weight:600;font-size:12px;text-transform:uppercase;color:#666;">
				<?php echo $is_edit ? esc_html__( 'Change Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ) : esc_html__( 'Set Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
			</p>

			<?php if ( $full_total <= 0 ) : ?>
				<p style="margin:0;color:#646970;"><?php esc_html_e( 'Add the order items and save the order first, then set the deposit here.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></p>
			<?php else : ?>
				<p style="margin:0 0 8px;color:#50575e;">
					<?php
					/* translators: %s: full order total. */
					echo wp_kses_post( sprintf( __( 'Order total: %s', 'advanced-partial-payment-or-deposit-for-woocommerce' ), wc_price( $full_total, array( 'currency' => $order->get_currency() ) ) ) );
					?>
				</p>
				<p style="margin:0 0 8px;">
					<label for="apd-order-deposit-type" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Deposit type', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></label>
					<select id="apd-order-deposit-type" style="<?php echo esc_attr( $field_style ); ?>">
						<option value="fixed" <?php selected( $rule['type'], 'fixed' ); ?>>
							<?php
							/* translators: %s: currency symbol. */
							echo esc_html( sprintf( __( 'Fixed amount (%s)', 'advanced-partial-payment-or-deposit-for-woocommerce' ), html_entity_decode( $currency_symbol, ENT_QUOTES, 'UTF-8' ) ) );
							?>
						</option>
						<option value="percentage" <?php selected( $rule['type'], 'percentage' ); ?>><?php esc_html_e( 'Percentage of order total (%)', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></option>
					</select>
				</p>
				<p style="margin:0 0 8px;">
					<label for="apd-order-deposit-value" style="display:block;margin-bottom:4px;"><?php esc_html_e( 'Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></label>
					<input type="number" id="apd-order-deposit-value" step="any" min="0"
						value="<?php echo esc_attr( wc_format_decimal( $rule['value'] ) ); ?>"
						style="<?php echo esc_attr( $field_style ); ?>" />
				</p>
				<p class="apd-order-deposit-preview" style="margin:0 0 8px;color:#50575e;font-size:12px;"></p>
				<?php if ( ! $is_edit || 'partially-paid' !== $order->get_status() ) : ?>
				<p style="margin:0 0 10px;">
					<label>
						<input type="checkbox" id="apd-order-deposit-received" value="yes" />
						<?php esc_html_e( 'Deposit already received — record it now', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
					</label>
				</p>
				<?php endif; ?>
				<div style="display:flex;gap:6px;flex-wrap:wrap;">
					<button type="button" class="button button-primary" id="apd-set-order-deposit"
						data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php echo $is_edit ? esc_html__( 'Update Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ) : esc_html__( 'Set Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
					</button>
					<?php if ( $is_edit ) : ?>
					<button type="button" class="button" id="apd-remove-order-deposit"
						data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php esc_html_e( 'Remove Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
					</button>
					<?php endif; ?>
				</div>
				<p style="margin:8px 0 0;color:#646970;font-size:11px;">
					<?php esc_html_e( 'The customer pays the deposit first (payment link, or record it here). The rest stays due as the balance.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX: set or change the deposit on an order.
	 */
	public function ajax_set_order_deposit() {
		check_ajax_referer( 'apd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		if ( ! self::is_enabled() ) {
			wp_send_json_error( __( 'Backend order deposits are turned off in Deposits > General.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		$order_id      = absint( $_POST['order_id'] ?? 0 );
		$type          = sanitize_key( wp_unslash( $_POST['deposit_type'] ?? '' ) );
		$value         = round( floatval( wc_format_decimal( wp_unslash( $_POST['deposit_value'] ?? 0 ) ) ), 4 );
		$mark_received = 'yes' === sanitize_key( wp_unslash( $_POST['mark_received'] ?? '' ) );
		$order         = $order_id ? wc_get_order( $order_id ) : false;
		$decimals      = wc_get_price_decimals();

		if ( ! $order instanceof WC_Order || ! in_array( $type, array( 'fixed', 'percentage' ), true ) ) {
			wp_send_json_error( __( 'Invalid data.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		$is_edit = APD_Order::is_deposit_order( $order );

		if ( $is_edit && ! self::can_edit_deposit( $order ) ) {
			wp_send_json_error( __( 'A payment has already been recorded on this order, so its deposit can no longer be changed.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		if ( ! $is_edit && ! self::can_add_deposit( $order ) ) {
			wp_send_json_error( __( 'A deposit can only be added to an unpaid order (Pending payment, On hold or Partially Paid).', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		// On a deposit order the order total is the amount due now, not the order value.
		$full_total = round( $is_edit ? floatval( $order->get_meta( '_apd_total_amount' ) ) : floatval( $order->get_total() ), $decimals );

		if ( $full_total <= 0 ) {
			wp_send_json_error( __( 'The order total is zero. Add the order items and save the order first.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		if ( $value <= 0 || ( 'percentage' === $type && $value >= 100 ) ) {
			wp_send_json_error( __( 'Enter a deposit greater than zero (a percentage must be below 100).', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		$rule    = array(
			'type'  => $type,
			'value' => $value,
		);
		$deposit = self::calculate_deposit( $full_total, $rule );

		if ( $deposit <= 0 || $deposit >= $full_total ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: full order total. */
					__( 'The deposit must be more than zero and less than the order total (%s).', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
					wp_strip_all_tags( wc_price( $full_total, array( 'currency' => $order->get_currency() ) ) )
				)
			);
		}

		APD_Order::apply_deposit_meta( $order, $full_total, $deposit );
		$order->update_meta_data( '_apd_deposit_rule', $rule );
		$order->add_order_note(
			sprintf(
				/* translators: 1: deposit amount, 2: full order total, 3: balance due after the deposit. */
				__( 'Deposit of %1$s set by an administrator. Order total %2$s, balance after the deposit %3$s.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
				wc_price( $deposit, array( 'currency' => $order->get_currency() ) ),
				wc_price( $full_total, array( 'currency' => $order->get_currency() ) ),
				wc_price( $full_total - $deposit, array( 'currency' => $order->get_currency() ) )
			),
			0,
			true
		);
		$order->save();

		if ( $mark_received && ! APD_Order::record_payment( $order->get_id(), $deposit, __( 'Deposit received (recorded by admin)', 'advanced-partial-payment-or-deposit-for-woocommerce' ) ) ) {
			wp_send_json_error( __( 'The deposit was set, but recording it as received failed. Use Record Manual Payment.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		wp_send_json_success(
			$mark_received
				? __( 'Deposit set and recorded as received.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
				: __( 'Deposit set.', 'advanced-partial-payment-or-deposit-for-woocommerce' )
		);
	}

	/**
	 * AJAX: remove an unpaid deposit and return the order to full payment.
	 */
	public function ajax_remove_order_deposit() {
		check_ajax_referer( 'apd_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		$order_id = absint( $_POST['order_id'] ?? 0 );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! self::can_edit_deposit( $order ) ) {
			wp_send_json_error( __( 'This deposit cannot be removed because a payment has already been recorded.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		$full_total = round( floatval( $order->get_meta( '_apd_total_amount' ) ), wc_get_price_decimals() );

		foreach ( self::get_order_deposit_meta_keys() as $meta_key ) {
			$order->delete_meta_data( $meta_key );
		}

		$order->set_total( $full_total );
		$order->add_order_note(
			sprintf(
				/* translators: %s: full order total. */
				__( 'Deposit removed by an administrator. The full order total of %s is now due.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
				wc_price( $full_total, array( 'currency' => $order->get_currency() ) )
			),
			0,
			true
		);
		$order->save();

		wp_send_json_success( __( 'Deposit removed.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
	}

	/**
	 * Remember a deposit order's payable total before WooCommerce recalculates it.
	 *
	 * @param bool     $and_taxes Whether taxes are recalculated too.
	 * @param WC_Order $order     Order object.
	 */
	public function remember_total_before_recalculation( $and_taxes, $order ) {
		if ( $order instanceof WC_Order && APD_Order::is_deposit_order( $order ) ) {
			$this->totals_before_recalculation[ $order->get_id() ] = (float) $order->get_total();
		}
	}

	/**
	 * Put a deposit order's payable total back after WooCommerce recalculates it.
	 *
	 * calculate_totals() sums the items into the full order value. On a deposit order the
	 * total must stay the amount due in the current payment (gateways charge it), so:
	 * - nothing paid yet: the recalculated sum becomes the new order value and the deposit
	 *   is worked out again from its rule, so added or removed items carry through;
	 * - something paid: the total goes back to what it was, and the order value only
	 *   follows the items while WooCommerce still allows them to be edited.
	 *
	 * @param bool     $and_taxes Whether taxes were recalculated too.
	 * @param WC_Order $order     Order object.
	 */
	public function restore_deposit_total_after_recalculation( $and_taxes, $order ) {
		if ( ! $order instanceof WC_Order || ! APD_Order::is_deposit_order( $order ) ) {
			return;
		}

		$order_id = $order->get_id();
		$before   = $this->totals_before_recalculation[ $order_id ] ?? null;
		unset( $this->totals_before_recalculation[ $order_id ] );

		$decimals     = wc_get_price_decimals();
		$recalculated = round( (float) $order->get_total(), $decimals );
		$full_total   = round( floatval( $order->get_meta( '_apd_total_amount' ) ), $decimals );
		$amount_paid  = round( floatval( $order->get_meta( '_apd_amount_paid' ) ), $decimals );
		$currency     = array( 'currency' => $order->get_currency() );

		if ( self::can_edit_deposit( $order ) ) {
			$rule = $order->get_meta( '_apd_deposit_rule' );

			// Checkout deposits carry no rule: keep the amount the customer was quoted.
			if ( ! is_array( $rule ) || empty( $rule['type'] ) ) {
				$rule = array(
					'type'  => 'fixed',
					'value' => floatval( $order->get_meta( '_apd_deposit_amount' ) ),
				);
				$order->update_meta_data( '_apd_deposit_rule', $rule );
			}

			// A deposit that no longer leaves anything for later is simply the full amount.
			$deposit = min( self::calculate_deposit( $recalculated, $rule ), $recalculated );

			if ( 0.0 !== round( $recalculated - $full_total, $decimals ) ) {
				$order->add_order_note(
					sprintf(
						/* translators: 1: new order total, 2: deposit due. */
						__( 'Order total recalculated to %1$s. Deposit due: %2$s.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
						wc_price( $recalculated, $currency ),
						wc_price( $deposit, $currency )
					)
				);
			}

			$order->update_meta_data( '_apd_total_amount', $recalculated );
			$order->update_meta_data( '_apd_deposit_amount', $deposit );
			$order->update_meta_data( '_apd_balance_due', $recalculated );
			$order->set_total( $deposit );
			return;
		}

		$balance_due = round( floatval( $order->get_meta( '_apd_balance_due' ) ), $decimals );

		if ( $order->is_editable() && 0.0 !== round( $recalculated - $full_total, $decimals ) ) {
			$new_balance = max( 0, round( $recalculated - $amount_paid, $decimals ) );

			$order->update_meta_data( '_apd_total_amount', $recalculated );
			$order->update_meta_data( '_apd_balance_due', $new_balance );
			$order->add_order_note(
				sprintf(
					/* translators: 1: new order total, 2: balance due. */
					__( 'Order total recalculated to %1$s. Balance due: %2$s.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
					wc_price( $recalculated, $currency ),
					wc_price( $new_balance, $currency )
				)
			);

			// A total that stood for the balance follows the new balance.
			if ( null !== $before && round( $before - $balance_due, $decimals ) === 0.0 && ! APD_Order::has_pending_balance_payment( $order ) ) {
				$before = $new_balance;
			}
		}

		if ( null !== $before ) {
			$order->set_total( $before );
		}
	}

	/**
	 * Meta keys that make up an order's deposit data.
	 *
	 * @return string[]
	 */
	private static function get_order_deposit_meta_keys() {
		return array(
			'_apd_is_deposit',
			'_apd_deposit_amount',
			'_apd_total_amount',
			'_apd_deposit_paid',
			'_apd_amount_paid',
			'_apd_balance_due',
			'_apd_payment_history',
			'_apd_deposit_rule',
			'_apd_manual_status',
			'_apd_balance_payment_pending',
			'_apd_balance_payment_awaiting_offline',
		);
	}
}
