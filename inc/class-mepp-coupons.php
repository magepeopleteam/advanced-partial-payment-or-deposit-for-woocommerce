<?php
namespace MagePeople\MEPP;


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
if (class_exists('MEPP_Coupons')) return;

/**
 * Class MEPP_Coupons
 */
class MEPP_Coupons
{


    /**
     * MEPP_Coupons constructor.
     */
    public function __construct()
    {
        add_filter('woocommerce_coupon_data_tabs', array($this, 'coupon_data_tabs'));
        add_action('woocommerce_coupon_data_panels', array($this, 'coupon_data_panels'), 10, 2);
        add_filter('woocommerce_coupon_error', array($this, 'coupon_deposit_error'), 10, 3);
        add_action('woocommerce_process_shop_coupon_meta', array($this, 'save_coupon_meta'));
        add_filter('woocommerce_coupon_is_valid', array($this, 'coupon_is_valid'), 10, 2);
        add_action('woocommerce_cart_loaded_from_session',array($this,'apply_auto_coupons'));
        add_action('woocommerce_checkout_update_order_review',array($this,'apply_auto_coupons'));
    }

    function apply_auto_coupons(){

        //update list of automatically applied coupons
        if(!is_object(WC()->cart) || WC()->cart->is_empty()) return;
        $auto_coupons = get_option('wc_deposit_auto_applied_coupons_for_full', array());
        if(empty($auto_coupons)) return;

        $deposit_in_cart = MEPP()->cart::is_deposit_in_cart();
        if(mepp_checkout_mode() && !defined('WOOCOMMERCE_CHECKOUT')) return;

        if($deposit_in_cart){

            foreach($auto_coupons as $coupon_id){
                $coupon_id = absint($coupon_id);
                $coupon = new \WC_Coupon($coupon_id);

                if(!$coupon || $coupon->get_status() !== 'publish') continue;
                $coupon_code = $coupon->get_code();
                if(WC()->cart->has_discount($coupon_code)){
                    WC()->cart->remove_coupon($coupon_code);
                }
            }
        } else {

            foreach($auto_coupons as $coupon_id){
                $coupon_id = absint($coupon_id);
                $coupon = new \WC_Coupon($coupon_id);
                if(!$coupon || $coupon->get_status() !== 'publish') continue;

                $coupon_code = $coupon->get_code();
                if(!WC()->cart->has_discount($coupon_code)){
                    WC()->cart->apply_coupon($coupon_code);
                }
            }
        }


    }

    function coupon_data_tabs($tabs)
    {
        $tabs['deposits'] = array(
            'label' => esc_html__('Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'target' => 'mepp_coupon_data',
            'class' => 'mepp_coupon_data',
        );
        return $tabs;
    }

    function coupon_data_panels($coupon_id, $coupon)
    {
        ?>
        <div id="mepp_coupon_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                woocommerce_wp_checkbox(
                    array(
                        'id' => 'mepp_disable_for_deposits',
                        'label' => esc_html__('Disable for deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'description' => esc_html__('Check this box if the coupon can be used only when customer is paying full amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'value' => wc_bool_to_string($coupon->get_meta('mepp_disable_for_deposits'))
                    )
                );
                woocommerce_wp_checkbox(
                    array(
                        'id' => 'mepp_automatically_apply_for_full',
                        'label' => esc_html__('Automatically apply for full amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'description' => esc_html__('Check this box if the coupon should be automatically applied in cart & checkout when customer is paying full amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'value' => wc_bool_to_string($coupon->get_meta('mepp_automatically_apply_for_full'))
                    )
                );

                ?>
            </div>
        </div>

        <?php
    }

    function save_coupon_meta($coupon_id)
    {

        $coupon = new \WC_Coupon($coupon_id);

        $allow_for_deposits = isset($_POST['mepp_disable_for_deposits']);
        $apply_for_full = isset($_POST['mepp_automatically_apply_for_full']);
        $coupon->update_meta_data('mepp_disable_for_deposits', $allow_for_deposits);
        $coupon->update_meta_data('mepp_automatically_apply_for_full', $apply_for_full);
        $coupon->save();

        //update list of automatically applied coupons
        $auto_coupons = get_option('wc_deposit_auto_applied_coupons_for_full', array());
        if ($apply_for_full) {
            if (empty($auto_coupons) || !in_array($coupon_id,$auto_coupons)) {
                $auto_coupons[] = $coupon_id;
            }
        } else {
            foreach($auto_coupons as $key => $auto_coupon){
                if($auto_coupon === $coupon_id){
                    unset($auto_coupons[$key]);
                    break;
                }
            }
        }
        update_option('wc_deposit_auto_applied_coupons_for_full', $auto_coupons);


    }

    function coupon_is_valid($valid, $coupon)
    {
        if ((is_cart() || is_checkout()) || (wp_doing_ajax() && isset($_REQUEST['wc-ajax']) && $_REQUEST['wc-ajax'] === 'apply_coupon')) {

            $deposit_in_cart = MEPP()->cart::is_deposit_in_cart();
            if($deposit_in_cart){
                $valid = !$coupon->get_meta('mepp_disable_for_deposits');
            }
        }

        return $valid;
    }


    function coupon_deposit_error($err, $err_code, $coupon)
    {
        $deposit_in_cart = MEPP()->cart::is_deposit_in_cart();

        if ($deposit_in_cart && $coupon->get_meta('mepp_disable_for_deposits') && $err_code !== 200) {
            $message = esc_html__('Coupon {coupon_code} is not valid for partial payments', 'advanced-partial-payment-or-deposit-for-woocommerce');
            $err = str_replace('{coupon_code}', $coupon->get_code(), $message);

        }

        return $err;
    }

}