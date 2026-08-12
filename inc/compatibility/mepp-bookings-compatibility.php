<?php
namespace MagePeople\MEPP;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
class WC_Bookings_Compatibility{

    function __construct(){
        add_filter( 'woocommerce_bookings_get_wc_booking_statuses', array( $this, 'add_wc_partial_payment_status' ) );
        add_filter( 'woocommerce_bookings_get_status_label', array( $this, 'add_wc_partial_payment_status' ) );
        add_filter( 'woocommerce_booking_is_paid_statuses', array( $this, 'add_custom_paid_status' ) );
        add_action( 'woocommerce_order_status_on-hold_to_partially-paid', array( $this, 'on_hold_to_partially_paid' ), 20, 2 );
        add_action( 'woocommerce_payment_complete', array( $this, 'save_order_status' ) );
        add_action( 'init', array( $this, 'register_custom_post_status' ),1 );

    }

    function on_hold_to_partially_paid($order_id){
        $booking_ids = \WC_Booking_Data_Store::get_booking_ids_from_order_id( $order_id );

        foreach ( $booking_ids as $booking_id ) {
            $booking = new \WC_Booking( $booking_id );
            $booking->set_status( 'wc-partial-payment' );
            $booking->save();
        }
    }

    public function register_custom_post_status() {
        if ( is_admin() ) {

            register_post_status( 'wc-partial-payment', array(
                'label'                     => '<span class="status-partial-payment tips" data-tip="' . wc_sanitize_tooltip( _x( 'Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce', 'advanced-partial-payment-or-deposit-for-woocommerce' ) ) . '">' . _x( 'Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce', 'advanced-partial-payment-or-deposit-for-woocommerce' ) . '</span>',
                'exclude_from_search'       => false,
                'public'                    => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop( 'Partially Paid <span class="count">(%s)</span>', 'Partially Paid <span class="count">(%s)</span>', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
            ) );
        }
    }

     function save_order_status( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( $order->get_status() === 'partially-paid' ) {
            $booking_ids = \WC_Booking_Data_Store::get_booking_ids_from_order_id( $order_id );

            foreach ( $booking_ids as $booking_id ) {
                $booking = new \WC_Booking( $booking_id );
                $booking->set_status( 'wc-partial-payment' );
                $booking->save();
            }        }
    }


    function add_wc_partial_payment_status($statuses){
        $statuses['wc-partial-payment'] = esc_html__( 'Partially Paid','advanced-partial-payment-or-deposit-for-woocommerce' );
        return $statuses;
    }
     function add_custom_paid_status( $statuses ) {
        $statuses[] = 'wc-partial-payment';
        return $statuses;
    }
}
return new WC_Bookings_Compatibility();