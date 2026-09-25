<?php
/**
 * Uninstall routine.
 *
 * Runs when the site owner deletes the plugin from wp-admin.
 *
 * @package AdvancedPartialPayment
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Meta keys the plugin stores on products and product categories.
 *
 * @return string[]
 */
function apd_uninstall_entity_meta_keys() {
	return array(
		'_apd_enable_deposit',
		'_apd_force_deposit',
		'_apd_deposit_type',
		'_apd_deposit_value',
		'_apd_min_deposit',
		'_apd_max_deposit',
		'_apd_flexible_payments',
		'_apd_assigned_plans',
		'_apd_flexible_min_payment',
		'_apd_flexible_min_payment_type',
	);
}

/**
 * Meta keys the plugin stores on WooCommerce orders.
 *
 * @return string[]
 */
function apd_uninstall_order_meta_keys() {
	return array(
		'_apd_is_deposit',
		'_apd_deposit_amount',
		'_apd_total_amount',
		'_apd_amount_paid',
		'_apd_balance_due',
		'_apd_payment_history',
		'_apd_balance_payment_pending',
		'_apd_balance_payment_awaiting_offline',
		'_apd_released_gateway_payments',
		'_apd_due_balance',
		'_apd_due_date',
	);
}

/**
 * Removes the plugin's deposit settings from every product.
 *
 * @return void
 */
function apd_uninstall_clear_product_meta() {
	$product_ids = get_posts(
		array(
			'post_type'     => 'product',
			'post_status'   => 'any',
			'numberposts'   => -1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		)
	);

	foreach ( (array) $product_ids as $product_id ) {
		foreach ( apd_uninstall_entity_meta_keys() as $meta_key ) {
			delete_post_meta( $product_id, $meta_key );
		}
	}
}

/**
 * Removes the plugin's deposit settings from every product category.
 *
 * @return void
 */
function apd_uninstall_clear_category_meta() {
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_wp_error( $terms ) ) {
		return;
	}

	foreach ( (array) $terms as $term_id ) {
		foreach ( apd_uninstall_entity_meta_keys() as $meta_key ) {
			delete_term_meta( $term_id, $meta_key );
		}
	}
}

/**
 * Removes the plugin's deposit bookkeeping from every order, HPOS or legacy.
 *
 * @return void
 */
function apd_uninstall_clear_order_meta() {
	$meta_keys   = apd_uninstall_order_meta_keys();
	$hpos_active = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

	if ( $hpos_active && function_exists( 'wc_get_orders' ) ) {
		$order_ids = wc_get_orders(
			array(
				'limit'  => -1,
				'return' => 'ids',
				'type'   => 'shop_order',
			)
		);

		foreach ( (array) $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			foreach ( $meta_keys as $meta_key ) {
				$order->delete_meta_data( $meta_key );
			}

			$order->save_meta_data();
		}

		return;
	}

	$order_ids = get_posts(
		array(
			'post_type'     => 'shop_order',
			'post_status'   => 'any',
			'numberposts'   => -1,
			'fields'        => 'ids',
			'no_found_rows' => true,
		)
	);

	foreach ( (array) $order_ids as $order_id ) {
		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta( $order_id, $meta_key );
		}
	}
}

$apd_settings = get_option( 'apd_settings', array() );

wp_clear_scheduled_hook( 'apd_daily_balance_check' );
wp_clear_scheduled_hook( 'apd_send_payment_reminders' );

// Always remove plugin bookkeeping options, never customer/operational data.
delete_option( 'apd_version' );
delete_option( 'apd_rewrite_version' );
delete_option( 'apd_migration_from_mepp_done' );

$delete_everything = isset( $apd_settings['delete_data_on_uninstall'] ) && 'yes' === $apd_settings['delete_data_on_uninstall'];

if ( ! $delete_everything ) {
	return;
}

delete_option( 'apd_settings' );
delete_option( 'apd_payment_plans' );

apd_uninstall_clear_product_meta();
apd_uninstall_clear_category_meta();
apd_uninstall_clear_order_meta();
