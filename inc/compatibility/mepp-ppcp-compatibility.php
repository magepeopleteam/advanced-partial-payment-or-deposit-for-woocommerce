<?php

namespace MagePeople\MEPP;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Item as Item;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\OrderEndpoint as OrderEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Money;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PurchaseUnit;
use WooCommerce\PayPalCommerce\ApiClient\Factory\AmountFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\OrderFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PurchaseUnitFactory;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WC_PPCP_Compatibility
{

    function __construct()
    {
        add_filter('mepp_cart_deposit_enabled', array($this, 'disable_checkout_mode'), 100);
        add_filter('mepp_cart_display_ui', array($this, 'disable_checkout_mode'), 100);
        add_action('wc_ajax_ppc-create-order',array($this,'modify_cart_data'),0);
    }

    /*****
     * Disable checkout mode UI if deposit authorized from cart
     * @param $val
     * @return false|mixed
     */
    function disable_checkout_mode($val)
    {
        if(is_checkout() && (!wp_doing_ajax() || (wp_doing_ajax() && did_action('woocommerce_checkout_update_order_review')) ) && mepp_checkout_mode() && WC()->session->get('ppcp') ){
            $ppcp_session =  WC()->session->get('ppcp');
            if(is_a($ppcp_session,'WooCommerce\PayPalCommerce\Session\SessionHandler') && $ppcp_session->order()){
                return false;
            }
        }
        return $val;
    }

    function modify_cart_data(){
        $stream = file_get_contents('php://input');
        $json = json_decode($stream, true);

        if (isset($json['context']) && $json['context'] === 'cart'||  $json['context'] === 'checkout') {

            if(mepp_checkout_mode()){

                $enabled = false;
                if (isset($json['form'])) {
                    if(is_string($json['form'])){
                        parse_str($json['form'], $form);

                    } else {
                        $form = $json['form'];
                    }
                    if (isset($form['deposit-radio']) && $form['deposit-radio'] === 'deposit') {
                        $enabled = true;
                    }
                }

            } else {
                // check if there is deposit product in cart
                $enabled = false;
                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    if (isset($cart_item['deposit'],$cart_item['deposit']['enable']) && $cart_item['deposit']['enable'] === 'yes' ) {
                        $enabled = true;
                        break;
                    }
                }

            }
            if(!$enabled) return;
            MEPP()->cart->calculate_deposit_totals(WC()->cart);
            WC()->cart->set_total(WC()->cart->deposit_info['deposit_amount']);
        }



    }


}

return new WC_PPCP_Compatibility();