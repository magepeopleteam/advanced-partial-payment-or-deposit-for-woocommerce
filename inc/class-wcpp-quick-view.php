<?php
/**
 *  Author: MagePeople Team
 *  Developer: Ariful
 *  Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WCPP_Quick_View')) {
    class WCPP_Quick_View{

        public function __construct() {
            $shop_pay_deposit_btn = get_option('meppp_shop_pay_deposit_btn') ? get_option('meppp_shop_pay_deposit_btn') : 'off';

            if($shop_pay_deposit_btn == 'on'):
            add_action('woocommerce_loop_add_to_cart_link',array($this,'wcpp_quick_view_for_shop_loop_item'), 10, 2);
            endif;           
            
            add_action('wp_footer',array($this,'wcpp_quick_view_popup'));
            add_action('wp_footer',array($this,'wcpp_quick_view_script'));
            add_action('wp_enqueue_scripts', array($this,'wcpp_load_quick_view_scripts'));
            add_action('wp_ajax_wcpp_quick_view_popup_content', array($this, 'wcpp_quick_view_popup_content')); 
            add_action('wp_ajax_nopriv_wcpp_quick_view_popup_content', array($this, 'wcpp_quick_view_popup_content')); 
            add_shortcode('wcpp_add_to_cart_form', array($this, 'wcpp_get_cart_form'));      
        }

        public function wcpp_load_quick_view_scripts(){
            wp_enqueue_script('jquery.modal.min', WCPP_PLUGIN_URL . 'asset/js/jquery.modal.min.js', array('jquery'), '0.9.1');
            wp_enqueue_style('jquery.modal.min', WCPP_PLUGIN_URL . 'asset/css/jquery.modal.min.css', false, '0.9.1');
            wp_enqueue_style('dashicons');
            wp_enqueue_script( 'wc-single-product' );        
        }

        public function wcpp_quick_view_for_shop_loop_item($html, $product){
            $product_id  = $product->get_id();
            // Check partial is enable
            if(!wcpp_is_deposit_enabled($product_id)['is_enable']) {
                return $html;
            }

            // Check user and role
            if (apply_filters('mepp_user_role_allow', 'go') === 'stop') {
                return $html;
            }
            
            $mepp_text_translation_string_pay_deposit = get_option('mepp_text_translation_string_pay_deposit');
            if (! $product->is_type( 'variable' ) ) {
                $html  		.= '<a class="button wcpp_quick_view_btn wcpp_qv_btn" data-id="'.$product_id.'">';
                $html 		.= $mepp_text_translation_string_pay_deposit;
                $html 		.= '</a>';
            }
            else{
                $html  		.= '<a href="'.esc_url(get_permalink($product_id)).'" class="button wcpp_qv_btn" data-id="'.$product_id.'">';
                $html 		.= $mepp_text_translation_string_pay_deposit;
                $html 		.= '</a>';                
            }

           
            return $html;
        }

        public function wcpp_quick_view_popup(){
            ?>
            <div class="wcpp_quick_view_wrapper" id="wcpp_quick_view_wrapper"></div>
            <?php
        }

        public function wcpp_quick_view_popup_content(){
            if(isset($_POST['product_id'])):
                $product_id = $_POST['product_id'];
                $product = wc_get_product( $product_id );
                $product_name = $product->get_name();
                $product_price = $product->get_price_html();
                $product_sale = $product->is_on_sale() ? 'Sale' : '';
                $product_short_description = $product->get_short_description();
                $product_sku = $product->get_sku();
                $product_categories = $product->get_categories();
                $product_image_id = $product->get_image_id();
                $product_image_url = wp_get_attachment_image_src($product_image_id) ? wp_get_attachment_image_src($product_image_id, 'full')[0] : '';
            ?>
            <div class="wcpp_single_product_wrapper">
                <div class="wcpp_single_product_col_6">
                    <img src="<?php echo esc_url($product_image_url); ?>" alt="<?php esc_attr_e('Featured Image','advanced-partial-payment-or-deposit-for-woocommerce'); ?>">
                </div>
                <div class="wcpp_single_product_col_6">
                    <div class="wcpp_single_product_title mb-10"><?php echo esc_html($product_name); ?></div>
                    <div class="wcpp_single_product_price mb-10"><?php echo $product_price ?></div>
                    <div class="wcpp_single_product_status mb-10"><?php echo esc_html($product_sale); ?></div>
                    <div class="wcpp_single_product_desc mb-10"><?php echo esc_html($product_short_description); ?></div>
                    <div class="wcpp_single_product_form mb-10">
                    <?php echo do_shortcode('[wcpp_add_to_cart_form id="'.$product_id.'"]'); ?>
                    </div>
                    <div class="wcpp_single_product_sku mb-10"><strong><?php esc_html_e('SKU: ','advanced-partial-payment-or-deposit-for-woocommerce'); ?></strong><?php echo esc_html($product_sku); ?></div>
                    <div class="wcpp_single_product_category"><strong><?php esc_html_e('Category: ','advanced-partial-payment-or-deposit-for-woocommerce'); ?></strong><?php echo $product_categories; ?></div>
                </div>
            </div>
            <?php
            endif;
            ?>
            <a href="#wcpp_quick_view_wrapper" rel="modal:close" class="wcpp_quick_view_close_btn"><span class="dashicons dashicons-no-alt"></span> <?php esc_html_e('Close','advanced-partial-payment-or-deposit-for-woocommerce'); ?></a>
            <?php
            wp_die();
        }

        public function wcpp_get_cart_form($atts){
            if ( empty( $atts ) ) {
                return '';
            }
    
            if ( ! isset( $atts['id'] ) ) {
                return '';
            }
    
            $atts = shortcode_atts( array(
                'id'				  => '',
                'sku'				  => '',
                'status'			  => 'publish',
                'show_price'		  => 'false',
                'hide_quantity'		  => 'false'
            ), $atts, 'product_add_to_cart_form' );
    
            $query_args = array(
                'posts_per_page'      => 1,
                'post_type'           => 'product',
                'post_status'         => $atts['status'],
                'ignore_sticky_posts' => 1,
                'no_found_rows'       => 1
            );
    
            if ( ! empty( $atts['id'] ) ) {
                $query_args['p'] = absint( $atts['id'] );
            }
    
            add_filter( 'woocommerce_add_to_cart_form_action', '__return_empty_string' );

            $single_product = new WP_Query( $query_args );
    
    
            ob_start();
    
            global $wp_query;
    

            $previous_wp_query = $wp_query;

            $wp_query = $single_product;

      
            while ( $single_product->have_posts() ) {
                $single_product->the_post();
    
                ?>
                <div class="product single-product">
                    <?php woocommerce_template_single_add_to_cart(); ?>
                </div>
                <?php
            }
    
            $wp_query = $previous_wp_query;

            wp_reset_postdata();

            remove_filter( 'woocommerce_add_to_cart_form_action', '__return_empty_string' );

            return ob_get_clean();
        }

        public function wcpp_quick_view_script(){
            ?>
            <script type='text/javascript'>    
            jQuery('.wcpp_quick_view_btn').click(function (e) { 
                let product_id = jQuery(this).attr('data-id');
                let ajaxurl = "<?php echo admin_url( 'admin-ajax.php' ); ?>";
                
                jQuery("#wcpp_quick_view_wrapper").modal({
                    escapeClose: false,
                    clickClose: false,
                    showClose: false
                });

                // Get Single Product Details By Product ID
                  
                jQuery.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        'action' : 'wcpp_quick_view_popup_content',
                        'product_id' : product_id
                    },
                    beforeSend: function() {
                        jQuery('.wcpp_quick_view_wrapper').empty();
                        jQuery('.wcpp_quick_view_wrapper').append('<span class="wcpp_qv_preloader dashicons dashicons-update"></span>');
                    },		
                    success: function (response) {
                        jQuery('.wcpp_quick_view_wrapper').remove('wcpp_qv_preloader');
                        jQuery('.wcpp_quick_view_wrapper').html(response);
                        jQuery('form.cart div.quantity [name="quantity"]').trigger("change");
                    }
                }); 
                
                // End: Get Single Product Details By Product ID               
            });
            </script>
            <?php
        }
    }
    new WCPP_Quick_View();
}