<?php

namespace MagePeople\MEPP;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Item as Item;
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_Germanized_Compatibility
{

    function __construct()
    {

//        add_filter('ppcp_request_args', array($this, 'cart_payment_details'));


    }

    function cart_payment_details($args)
    {

        if(wp_doing_ajax() && did_action('wc_ajax_ppc-create-order')){
            if(MEPP()->cart::is_deposit_in_cart()){
                $body = json_decode($args['body']);
//                $amount = $this->amount_factory->from_wc_cart( $cart );
//                $items  = $this->item_factory->from_wc_cart( $cart );
//                $body->purchase_units[0]->amount->value =
            }

        }
        var_dump(wp_debug_backtrace_summary());
        wp_die();
        return $args;
        $settings = wc_gateway_ppec()->settings;
        $decimals = $settings->is_currency_supports_zero_decimal() ? 0 : 2;

        if (is_checkout_pay_page() || isset($_GET['woo-paypal-return'])) {
            //for already created partial payments
            if (isset($_GET['woo-paypal-return'])) {

                $session = WC()->session->get('paypal');
                if (empty($session)) {
                    return $details;
                }
                $order = wc_get_order($session->order_id);

            } else {

                global $wp;
                $order_id = $wp->query_vars['order-pay'];
                $order = wc_get_order($order_id);
            }

            if (!$order || $order->get_type() !== 'mepp_payment') return $details;
            $name = '';
            foreach ($order->get_fees() as $fee) {
                $name = $fee->get_name();
            }
            $details = array(
                'total_item_amount' => round($order->get_total(), $decimals),
                'shipping' => 0,
                'ship_discount_amount' => 0,
                'items' => array(array(
                    'name' => $name,
                    'quantity' => 1,
                    'sku' => '',
                    'amount' => round($order->get_total(), $decimals)
                )),
                'order_tax' => 0,
                'order_total' => round($order->get_total(), $decimals)
            );


        } else {

            if (mepp_checkout_mode() && !is_checkout()) return $details;
            $items = false;
            if (isset(WC()->cart->deposit_info) && WC()->cart->deposit_info['deposit_enabled']) {

                $details['order_total'] = WC()->cart->deposit_info['deposit_amount'];
                $details['total_item_amount'] = round(WC()->cart->deposit_info['deposit_breakdown']['cart_items'], $decimals);
                $details['order_tax'] = WC()->cart->deposit_info['deposit_breakdown']['taxes'];
                $details['shipping'] = WC()->cart->deposit_info['deposit_breakdown']['shipping'];
                $items = array(
                    'name' => 'Deposit Payment',
                    'description' => '',
                    'quantity' => 1,
                    'amount' => round(WC()->cart->deposit_info['deposit_breakdown']['cart_items'], $decimals)
                );
                if (WC()->cart->deposit_info['deposit_breakdown']['discount'] > 0) {
                    $details['total_item_amount'] -= WC()->cart->deposit_info['deposit_breakdown']['discount'];
                    $items['amount'] -= WC()->cart->deposit_info['deposit_breakdown']['discount'];
                }
            }
            if (is_array($items)) $details['items'] = array($items);

        }


        return $details;
    }


}

return new WC_PPCP_Compatibility();