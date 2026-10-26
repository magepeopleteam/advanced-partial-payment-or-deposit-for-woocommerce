<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class APD_Migration {

	/**
	 * Option recording that imported plans were checked for the 4.0.x import bug.
	 */
	const PLAN_REPAIR_OPTION = 'apd_plan_import_repair';

	public function __construct() {
		add_action( 'admin_notices', array( $this, 'show_migration_notice' ) );
		add_action( 'admin_notices', array( $this, 'show_plan_repair_notice' ) );
		add_action( 'wp_ajax_apd_sync_old_data', array( $this, 'ajax_sync_old_data' ) );
		add_action( 'admin_footer', array( $this, 'migration_script' ) );
		add_action( 'admin_init', array( $this, 'maybe_repair_imported_plans' ) );
	}

	public function show_migration_notice() {
		if ( ! isset( $_GET['page'] ) || 'apd-deposits' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
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
				<strong><?php esc_html_e( 'Legacy Data Detected', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></strong><br>
				<?php esc_html_e( 'We noticed you have data from the old Mage Partial Payment plugin. Would you like to sync your old settings, product configurations, and orders to the new system?', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
			</p>
			<p>
				<button type="button" class="button button-primary" id="apd-start-migration"><?php esc_html_e( 'Sync Old Data Now', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></button>
				<button type="button" class="button" id="apd-dismiss-migration"><?php esc_html_e( 'I will do this later', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></button>
			</p>
			<div id="apd-migration-progress" style="display:none; margin-top:10px;">
				<p id="apd-migration-status"><?php esc_html_e( 'Initializing...', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></p>
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
		if ( ! isset( $_GET['page'] ) || 'apd-deposits' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
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
							nonce: '<?php echo esc_js( wp_create_nonce( 'apd_migration_nonce' ) ); ?>'
						},
						success: function(response) {
							if (response.success) {
								$('#apd-migration-status').text(response.data.message);
								$('#apd-migration-bar').val(response.data.progress);

								if (response.data.next_step !== 'done') {
									apd_run_migration(response.data.next_step, response.data.next_offset);
								} else {
									$('#apd-migration-status').text('<?php esc_html_e( 'Migration completed successfully!', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>');
									setTimeout(function() {
										$('#apd-migration-notice').slideUp();
									}, 3000);
								}
							} else {
								$('#apd-migration-status').text('<?php esc_html_e( 'Error:', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?> ' + response.data);
							}
						},
						error: function() {
							$('#apd-migration-status').text('<?php esc_html_e( 'An error occurred during migration. Please try again.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>');
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
			wp_send_json_error( __( 'Permission denied.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) );
		}

		$step       = isset( $_POST['step'] ) ? sanitize_text_field( wp_unslash( $_POST['step'] ) ) : 'global_settings';
		$offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size = 50;

		switch ( $step ) {
			case 'global_settings':
				$this->migrate_global_settings();
				wp_send_json_success(
					array(
						'message'     => __( 'Global settings and categories migrated.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
						'progress'    => 10,
						'next_step'   => 'payment_plans',
						'next_offset' => 0,
					)
				);
				break;

			case 'payment_plans':
				$this->migrate_payment_plans();
				wp_send_json_success(
					array(
						'message'     => __( 'Payment plans migrated.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
						'progress'    => 20,
						'next_step'   => 'products',
						'next_offset' => 0,
					)
				);
				break;

			case 'products':
				$args     = array(
					'post_type'      => 'product',
					'posts_per_page' => $batch_size,
					'offset'         => $offset,
					'post_status'    => 'any',
					'fields'         => 'ids',
				);
				$products = get_posts( $args );

				if ( ! empty( $products ) ) {
					foreach ( $products as $post_id ) {
						$this->migrate_product_meta( $post_id );
					}
					wp_send_json_success(
						array(
							/* translators: %d: number of products migrated so far. */
							'message'     => sprintf( __( 'Migrated %d products...', 'advanced-partial-payment-or-deposit-for-woocommerce' ), $offset + count( $products ) ),
							'progress'    => 40,
							'next_step'   => 'products',
							'next_offset' => $offset + $batch_size,
						)
					);
				} else {
					wp_send_json_success(
						array(
							'message'     => __( 'Products migration complete.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
							'progress'    => 60,
							'next_step'   => 'orders',
							'next_offset' => 0,
						)
					);
				}
				break;

			case 'orders':
				$args = array(
					'post_type'      => 'shop_order',
					'posts_per_page' => $batch_size,
					'offset'         => $offset,
					'post_status'    => 'any',
					'fields'         => 'ids',
				);

				// For HPOS compatibility
				if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
					$orders = wc_get_orders(
						array(
							'limit'  => $batch_size,
							'offset' => $offset,
							'return' => 'ids',
						)
					);
				} else {
					$orders = get_posts( $args );
				}

				if ( ! empty( $orders ) ) {
					foreach ( $orders as $order_id ) {
						$this->migrate_order_meta( $order_id );
					}
					wp_send_json_success(
						array(
							/* translators: %d: number of orders migrated so far. */
							'message'     => sprintf( __( 'Migrated %d orders...', 'advanced-partial-payment-or-deposit-for-woocommerce' ), $offset + count( $orders ) ),
							'progress'    => 90,
							'next_step'   => 'orders',
							'next_offset' => $offset + $batch_size,
						)
					);
				} else {
					update_option( 'apd_migration_from_mepp_done', 'yes' );
					wp_send_json_success(
						array(
							'message'     => __( 'All data migrated successfully.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
							'progress'    => 100,
							'next_step'   => 'done',
							'next_offset' => 0,
						)
					);
				}
				break;
		}
	}

	private function migrate_global_settings() {
		$apd_settings = get_option( 'apd_settings', array() );

		// Map global settings
		$apd_settings['enable_deposit'] = get_option( 'mepp_storewide_deposit_enabled', 'no' );
		$old_amount_type                = get_option( 'mepp_storewide_deposit_amount_type', 'percent' );
		$apd_settings['deposit_type']   = ( $old_amount_type === 'percent' ) ? 'percentage' : 'fixed';
		$apd_settings['deposit_value']  = get_option( 'mepp_storewide_deposit_amount', '' );
		$apd_settings['force_deposit']  = get_option( 'mepp_storewide_deposit_force_deposit', 'no' );

		// Migrate Pro Gateway Rules
		if ( class_exists( 'WooCommerce' ) ) {
			$gateways          = WC()->payment_gateways()->payment_gateways();
			$gateway_modes     = isset( $apd_settings['gateway_checkout_modes'] ) ? $apd_settings['gateway_checkout_modes'] : array();
			$has_gateway_rules = false;

			foreach ( $gateways as $gateway_id => $gateway ) {
				$old_control = get_option( 'mepp_gateway_control_' . $gateway_id );
				if ( $old_control === 'force_full' || $old_control === 'hide_ui' ) {
					$gateway_modes[ $gateway_id ] = array(
						'mode'    => 'full',
						'value'   => 0,
						'plan_id' => '',
					);
					$has_gateway_rules            = true;
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
                // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Standard core pattern: lazily populate the real $wp_roles global when nothing has instantiated it yet.
				$wp_roles = new WP_Roles();
			}
			$all_roles                              = array_keys( $wp_roles->roles );
			$allowed_roles                          = array_diff( $all_roles, $disabled_roles );
			$apd_settings['conditional_user_roles'] = array_values( $allowed_roles );
		}

		update_option( 'apd_settings', $apd_settings );

		// Migrate categories
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);

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
		$terms = self::get_legacy_plan_terms();

		if ( empty( $terms ) ) {
			return;
		}

		$new_plans = get_option( 'apd_payment_plans', array() );
		$new_plans = is_array( $new_plans ) ? $new_plans : array();

		foreach ( $terms as $term ) {
			$plan_id = 'plan_' . $term->term_id;

			if ( isset( $new_plans[ $plan_id ] ) ) {
				continue;
			}

			$amount_type = get_term_meta( $term->term_id, 'amount_type', true );

			$new_plans[ $plan_id ] = array(
				'id'           => $plan_id,
				'name'         => $term->name,
				'description'  => $term->description,
				'price_type'   => 'fixed' === $amount_type ? 'fixed' : 'percentage',
				'status'       => 'active',
				'installments' => self::convert_legacy_plan( $term->term_id ),
				'created'      => current_time( 'mysql' ),
				'updated'      => current_time( 'mysql' ),
			);
		}

		update_option( 'apd_payment_plans', $new_plans );
	}

	/**
	 * Payment plan terms left behind by the 3.x plugin.
	 *
	 * @return WP_Term[]
	 */
	private static function get_legacy_plan_terms() {
		if ( ! taxonomy_exists( 'mepp_payment_plan' ) ) {
			register_taxonomy(
				'mepp_payment_plan',
				'product',
				array(
					'public'    => false,
					'rewrite'   => false,
					'query_var' => false,
				)
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'mepp_payment_plan',
				'hide_empty' => false,
			)
		);

		return is_wp_error( $terms ) || ! is_array( $terms ) ? array() : $terms;
	}

	/**
	 * Schedule rows of a 3.x payment plan.
	 *
	 * The 3.x plan editor posted its jQuery Repeater value as JSON, which wraps the rows
	 * in the repeater list name: {"payment-plan":[{...},{...}]}. Reading the wrapper as
	 * the row list is what turned every imported plan into a deposit plus one empty row.
	 *
	 * @param int $term_id Legacy plan term ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_legacy_plan_rows( $term_id ) {
		$details = get_term_meta( $term_id, 'payment_details', true );

		if ( is_string( $details ) ) {
			$decoded = json_decode( $details, true );

			if ( ! is_array( $decoded ) ) {
				$decoded = json_decode( wp_unslash( $details ), true );
			}

			$details = $decoded;
		}

		if ( ! is_array( $details ) ) {
			return array();
		}

		if ( isset( $details['payment-plan'] ) ) {
			$details = $details['payment-plan'];
		}

		return is_array( $details ) ? array_values( array_filter( $details, 'is_array' ) ) : array();
	}

	/**
	 * Convert a 3.x payment plan into installment rules.
	 *
	 * The 3.x plugin counted every "after" offset from the previous payment, and a row with
	 * a set date restarted that chain from the date. The new rules count "after" from the
	 * purchase, so the offsets are added up here. A row that follows a set date can only be
	 * expressed as a date of its own, so it becomes one.
	 *
	 * @param int $term_id Legacy plan term ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function convert_legacy_plan( $term_id ) {
		$installments = array(
			array(
				'amount'          => floatval( get_term_meta( $term_id, 'deposit_percentage', true ) ),
				'due_type'        => 'immediately',
				'due_after_value' => 0,
				'due_after_unit'  => 'day',
				'due_fixed_date'  => '',
			),
		);

		$offset = array(
			'day'   => 0,
			'week'  => 0,
			'month' => 0,
		);
		$anchor = null;

		foreach ( self::get_legacy_plan_rows( $term_id ) as $row ) {
			$installment = array(
				'amount'          => floatval( $row['percentage'] ?? 0 ),
				'due_type'        => 'after_purchase',
				'due_after_value' => 0,
				'due_after_unit'  => 'day',
				'due_fixed_date'  => '',
			);

			// 3.x decided "set date" by the date being filled in, not by the checkbox.
			$date = isset( $row['date'] ) && is_scalar( $row['date'] ) ? trim( (string) $row['date'] ) : '';
			$date = '' !== $date ? date_create_immutable( $date, new DateTimeZone( 'UTC' ) ) : false;

			if ( $date ) {
				$anchor = $date;
			} else {
				list( $value, $unit ) = self::get_legacy_row_offset( $row );

				if ( $anchor ) {
					$anchor = $anchor->modify( "+{$value} {$unit}" );
				} else {
					$offset[ $unit ] += $value;
					list( $installment['due_after_value'], $installment['due_after_unit'] ) = self::collapse_offset( $offset );
				}
			}

			if ( $anchor ) {
				$installment['due_type']       = 'fixed_date';
				$installment['due_fixed_date'] = $anchor->format( 'Y-m-d' );
			}

			$installments[] = $installment;
		}

		return $installments;
	}

	/**
	 * A 3.x row's "after" offset in a unit the new rules support.
	 *
	 * @param array<string,mixed> $row Legacy schedule row.
	 * @return array{0:int,1:string} Value and unit (day, week or month).
	 */
	private static function get_legacy_row_offset( $row ) {
		$value = max( 0, intval( $row['after'] ?? 0 ) );
		$unit  = isset( $row['after-term'] ) && is_scalar( $row['after-term'] ) ? strtolower( rtrim( trim( (string) $row['after-term'] ), 's' ) ) : 'day';

		if ( 'year' === $unit ) {
			return array( $value * 12, 'month' );
		}

		return array( $value, in_array( $unit, array( 'day', 'week', 'month' ), true ) ? $unit : 'day' );
	}

	/**
	 * Express an accumulated offset as one value and unit.
	 *
	 * @param array<string,int> $offset Days, weeks and months since the purchase.
	 * @return array{0:int,1:string}
	 */
	private static function collapse_offset( $offset ) {
		$used = array_filter( $offset );

		if ( empty( $used ) ) {
			return array( 0, 'day' );
		}

		if ( 1 === count( $used ) ) {
			$unit = (string) key( $used );

			return array( (int) $used[ $unit ], $unit );
		}

		// Mixed units only have an exact single-unit form when no months are involved.
		// With months the closest is a 30-day month.
		return array( (int) ( $offset['month'] * 30 + $offset['week'] * 7 + $offset['day'] ), 'day' );
	}

	/**
	 * The installments the 4.0.x import produced for a 3.x plan.
	 *
	 * Kept only so the repair can recognise a plan that is still exactly as that import
	 * left it. Do not use it to import anything.
	 *
	 * @param int $term_id Legacy plan term ID.
	 * @return array<int,array<string,mixed>>
	 */
	private static function convert_legacy_plan_as_imported_by_4_0( $term_id ) {
		$payment_details = get_term_meta( $term_id, 'payment_details', true );
		$payment_details = is_string( $payment_details ) ? json_decode( $payment_details, true ) : $payment_details;

		$installments = array(
			array(
				'amount'          => floatval( get_term_meta( $term_id, 'deposit_percentage', true ) ),
				'due_type'        => 'immediately',
				'due_after_value' => 0,
				'due_after_unit'  => 'day',
				'due_fixed_date'  => '',
			),
		);

		if ( ! is_array( $payment_details ) ) {
			return $installments;
		}

		foreach ( $payment_details as $detail ) {
			$detail   = is_array( $detail ) ? $detail : array();
			$due_type = ( isset( $detail['date_checkbox'] ) && 'on' === $detail['date_checkbox'] ) ? 'fixed_date' : 'after_purchase';
			$unit     = 'day';

			if ( isset( $detail['after-term'] ) && is_string( $detail['after-term'] ) ) {
				$old_unit = strtolower( rtrim( $detail['after-term'], 's' ) );
				if ( in_array( $old_unit, array( 'day', 'week', 'month' ), true ) ) {
					$unit = $old_unit;
				} elseif ( 'year' === $old_unit ) {
					$unit            = 'month';
					$detail['after'] = intval( $detail['after'] ?? 1 ) * 12;
				}
			}

			$installments[] = array(
				'amount'          => floatval( $detail['percentage'] ?? 0 ),
				'due_type'        => $due_type,
				'due_after_value' => intval( $detail['after'] ?? 0 ),
				'due_after_unit'  => $unit,
				'due_fixed_date'  => $detail['date'] ?? '',
			);
		}

		return $installments;
	}

	/**
	 * Whether two installment lists describe the same schedule.
	 *
	 * @param mixed $a Installments.
	 * @param mixed $b Installments.
	 * @return bool
	 */
	private static function same_installments( $a, $b ) {
		$normalize = static function ( $installments ) {
			if ( ! is_array( $installments ) ) {
				return array();
			}

			return array_map(
				static function ( $inst ) {
					$inst = is_array( $inst ) ? $inst : array();

					return array(
						round( floatval( $inst['amount'] ?? 0 ), 6 ),
						(string) ( $inst['due_type'] ?? '' ),
						intval( $inst['due_after_value'] ?? 0 ),
						(string) ( $inst['due_after_unit'] ?? '' ),
						is_scalar( $inst['due_fixed_date'] ?? '' ) ? (string) ( $inst['due_fixed_date'] ?? '' ) : '',
					);
				},
				array_values( $installments )
			);
		};

		return $normalize( $a ) === $normalize( $b );
	}

	/**
	 * Rebuild imported plans that the 4.0.x import broke.
	 *
	 * Only a plan whose installments are still exactly what that import produced is
	 * rebuilt. A plan the shop has edited since is left alone and reported instead.
	 *
	 * @return array{repaired:string[],skipped:string[]}
	 */
	public static function repair_imported_plans() {
		$result = array(
			'repaired' => array(),
			'skipped'  => array(),
		);

		$plans = get_option( 'apd_payment_plans', array() );

		if ( ! is_array( $plans ) || empty( $plans ) ) {
			return $result;
		}

		$changed = false;

		foreach ( self::get_legacy_plan_terms() as $term ) {
			$plan_id = 'plan_' . $term->term_id;

			if ( empty( $plans[ $plan_id ]['installments'] ) ) {
				continue;
			}

			$current = $plans[ $plan_id ]['installments'];
			$correct = self::convert_legacy_plan( $term->term_id );

			if ( self::same_installments( $current, $correct ) ) {
				continue;
			}

			$name = (string) ( $plans[ $plan_id ]['name'] ?? $term->name );

			if ( ! self::same_installments( $current, self::convert_legacy_plan_as_imported_by_4_0( $term->term_id ) ) ) {
				$result['skipped'][] = $name;
				continue;
			}

			$plans[ $plan_id ]['installments'] = $correct;
			$plans[ $plan_id ]['updated']      = current_time( 'mysql' );
			$result['repaired'][]              = $name;
			$changed                           = true;
		}

		if ( $changed ) {
			update_option( 'apd_payment_plans', $plans );
		}

		return $result;
	}

	/**
	 * Run the imported-plan repair once, in an administrator's request.
	 */
	public function maybe_repair_imported_plans() {
		if ( false !== get_option( self::PLAN_REPAIR_OPTION, false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$result           = self::repair_imported_plans();
		$result['notice'] = ( $result['repaired'] || $result['skipped'] ) ? 'pending' : 'none';
		$result['time']   = current_time( 'mysql' );

		update_option( self::PLAN_REPAIR_OPTION, $result, false );
	}

	/**
	 * Report the imported-plan repair once, on the Deposits screen.
	 */
	public function show_plan_repair_notice() {
		if ( ! isset( $_GET['page'] ) || 'apd-deposits' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
			return;
		}

		$result = get_option( self::PLAN_REPAIR_OPTION, false );

		if ( ! is_array( $result ) || 'pending' !== ( $result['notice'] ?? '' ) ) {
			return;
		}

		$result['notice'] = 'shown';
		update_option( self::PLAN_REPAIR_OPTION, $result, false );

		$repaired = array_map( 'strval', (array) ( $result['repaired'] ?? array() ) );
		$skipped  = array_map( 'strval', (array) ( $result['skipped'] ?? array() ) );
		?>
		<div class="notice notice-success is-dismissible">
			<?php if ( $repaired ) : ?>
				<p>
					<strong><?php esc_html_e( 'Imported payment plans repaired', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></strong><br>
					<?php
					printf(
						/* translators: %s: comma-separated plan names. */
						esc_html__( 'These plans were imported from the old plugin with their installments missing and have been rebuilt from the original schedule: %s', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
						esc_html( implode( ', ', $repaired ) )
					);
					?>
				</p>
			<?php endif; ?>
			<?php if ( $skipped ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: comma-separated plan names. */
						esc_html__( 'These imported plans were changed after the import and were left as they are. Please check their installments under Payment Plans: %s', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
						esc_html( implode( ', ', $skipped ) )
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
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
