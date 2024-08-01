<?php

/**
 * @author Shahadat Hossain <raselsha@email.com>
 * @since 3.0.2
 * @version 1.0.0
 */

namespace MagePeople\MEPP;

if( ! defined('ABSPATH') ) exit;

class MEPP_Pro_Ads{
    
    public static $link = 'https://mage-people.com/product/advanced-deposit-partial-payment-for-woocommerce-pro/';

    public function __construct()
    {
        add_action('admin_init', array($this, 'show_pro_advertise'));
    }

    public function show_pro_advertise(){
        if( ! defined('MEPP_PRO_VERSION_ACTIVE')){
            add_filter( 'mepp_settings_tabs', [$this,'show_advertise']) ; 
        }
    }

    public function show_advertise($menu){
        $ads_menu = array(
            'checkout_mode_ads' =>  '<i class="fas fa-money-check-alt"></i> ' . __('Checkout Mode', 'advanced-partial-payment-or-deposit-for-woocommerce') . '<i>&nbsp; PRO</i>',
            'future_payment_ads' => '<i class="far fa-credit-card"></i> ' . __('Future Payments & Reminders', 'advanced-partial-payment-or-deposit-for-woocommerce') . '<i>&nbsp; PRO</i>',
        );
        return array_merge($menu, $ads_menu);
    }

    public static function get_pro_purchage_link(){
        return self::$link;
    }

    public static function get_pro_purchage_button(){
        $link = MEPP_Pro_Ads::get_pro_purchage_link();
        $button = '<a href="'.$link.'" class="button button-primary" target="_blank">'.__('Buy Pro','advanced-partial-payment-or-deposit-for-woocommerce').'</a>';
        
        if( ! defined('MEPP_PRO_VERSION_ACTIVE')){
            echo $button;
        }
    }

}

new MEPP_Pro_Ads();