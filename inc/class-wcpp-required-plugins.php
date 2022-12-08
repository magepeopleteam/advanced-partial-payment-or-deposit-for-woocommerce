<?php
/**
 *  Required Plugins Notification
 *  Dev: Ariful
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if (!class_exists('WCPP_Required_Plugins')) {

class WCPP_Required_Plugins
{
	public function __construct() {
		add_action( 'admin_notices',array($this,'wcpp_admin_notices'));
        add_action( 'admin_menu', array( $this, 'wcpp_plugins_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'wcpp_plugin_activate' ) );
	}

	public function wcpp_plugin_page_location(){

		$location = 'plugins.php';

		return $location;	
	}

	public function wcpp_plugins_admin_menu() {
			add_submenu_page(
				$this->wcpp_plugin_page_location(),
				__( 'Install WCPP Plugins', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
				__( 'Install WCPP Plugins', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
				'manage_options',
				'wcpp-plugins',
				array($this,'wcpp_plugin_page')
			);
    }

	public function wcpp_chk_plugin_folder_exist($slug){
		$plugin_dir = ABSPATH . 'wp-content/plugins/'.$slug;
		if(is_dir($plugin_dir)){
			return true;
		}
		else{
			return false;
		}		
	}

	public function wcpp_plugin_activate(){
		if(isset($_GET['wcpp_plugin_activate']) && !is_plugin_active( $_GET['wcpp_plugin_activate'] )){
			$slug = $_GET['wcpp_plugin_activate'];
			$activate = activate_plugin( $slug );
			$url = admin_url( $this->wcpp_plugin_page_location().'?page=wcpp-plugins' );
			echo '<script>
			var url = "'.$url.'";
			window.location.replace(url);
			</script>';
		}
		else{
			return false;
		}
	}

	public function wcpp_mpdf_plugin_install(){

		if(!current_user_can('administrator')) {
			exit;
		}

		if(isset($_GET['wcpp_plugin_install']) && $this->wcpp_chk_plugin_folder_exist($_GET['wcpp_plugin_install']) == false){
			$slug = $_GET['wcpp_plugin_install'];
			if($slug != 'magepeople-pdf-support-master'){
				$action = 'install-plugin';
				$url = wp_nonce_url(
					add_query_arg(
						array(
							'action' => $action,
							'plugin' => $slug
						),
						admin_url( 'update.php' )
					),
					$action.'_'.$slug
				);
				if(isset($url)){
					echo '<script>
						str = "'.$url.'";
						var url = str.replace(/&amp;/g, "&");
						window.location.replace(url);
						</script>';
				}


			}
			else{
				return false;
			}
		}
		else{
			return false;
		}
	}	

	public function wcpp_wp_plugin_activation_url($slug){
		if($this->wcpp_plugin_page_location() == 'plugins.php'){
			$url = admin_url($this->wcpp_plugin_page_location()).'?page=wcpp-plugins&wcpp_plugin_activate='.$slug;
		}
		else{
			$url = admin_url($this->wcpp_plugin_page_location()).'&page=wcpp-plugins&wcpp_plugin_activate='.$slug;
		}

		return $url;
	}

	public function wcpp_plugin_page(){	
		$button_wc = '';
		$button_wcpp = '';

		/* WooCommerce */
		if($this->wcpp_chk_plugin_folder_exist('woocommerce') == false) {;
			$button_wc = '<a href="'.esc_url($this->wcpp_wp_plugin_installation_url('woocommerce')).'" class="wcpp_plugin_btn">'.esc_html__('Install','advanced-partial-payment-or-deposit-for-woocommerce').'</a>';
		}
		elseif($this->wcpp_chk_plugin_folder_exist('woocommerce') == true && !is_plugin_active( 'woocommerce/woocommerce.php')){
			$button_wc = '<a href="'.esc_url($this->wcpp_wp_plugin_activation_url('woocommerce/woocommerce.php')).'" class="wcpp_plugin_btn">'.esc_html__('Activate','advanced-partial-payment-or-deposit-for-woocommerce').'</a>';
		}
		else{
			$button_wc = '<span class="wcpp_plugin_status">'.esc_html__('Activated','advanced-partial-payment-or-deposit-for-woocommerce').'</span>';
		}

		/* Advanced – Deposit & Partial Payments for WooCommerce */
		if($this->wcpp_chk_plugin_folder_exist('advanced-partial-payment-or-deposit-for-woocommerce') == false) {;
			$button_wcpp = '<a href="'.esc_url($this->wcpp_wp_plugin_installation_url('advanced-partial-payment-or-deposit-for-woocommerce')).'" class="wcpp_plugin_btn">'.esc_html__('Install','advanced-partial-payment-or-deposit-for-woocommerce').'</a>';
		}
		elseif($this->wcpp_chk_plugin_folder_exist('advanced-partial-payment-or-deposit-for-woocommerce') == true && !is_plugin_active( 'advanced-partial-payment-or-deposit-for-woocommerce/advanced_partial_payment.php')){
			$button_wcpp = '<a href="'.esc_url($this->wcpp_wp_plugin_activation_url('advanced-partial-payment-or-deposit-for-woocommerce/advanced_partial_payment.php')).'" class="wcpp_plugin_btn">'.esc_html__('Activate','advanced-partial-payment-or-deposit-for-woocommerce').'</a>';
		}
		else{
			$button_wcpp = '<span class="wcpp_plugin_status">'.esc_html__('Activated','advanced-partial-payment-or-deposit-for-woocommerce').'</span>';
		}		
		?>
		<div class="wrap wcpp_plugin_page_wrap">
			<table>
				<thead>
					<tr>
						<th colspan="2"><?php esc_html_e('Advanced – Deposit & Partial Payments for WooCommerce Required Plugins','advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php esc_html_e('WooCommerce','advanced-partial-payment-or-deposit-for-woocommerce'); ?></td>
						<td><?php echo $button_wc; ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e('Advanced – Deposit & Partial Payments for WooCommerce','advanced-partial-payment-or-deposit-for-woocommerce'); ?></td>
						<td><?php echo $button_wcpp; ?></td>
					</tr>										
				</tbody>
			</table>
		</div>
		<style>
		.wcpp_plugin_page_wrap{
			margin-left: 15px;
			margin-right: 15px;			
		}
		.wcpp_plugin_page_wrap table{
			width: 100%;
			border-collapse: collapse;
			border: 1px solid #d3d3d3;
		}
		.wcpp_plugin_page_wrap table tr{
			border-bottom: 1px solid #d3d3d3;
			background-color: #fff;
		}
		.wcpp_plugin_page_wrap table tr th{
			background: #162748;
			color: #fff;
		}
		.wcpp_plugin_page_wrap table tr th,
		.wcpp_plugin_page_wrap table tr td{
			padding: 15px;
			text-align: left;
		}
		.wcpp_plugin_page_wrap .wcpp_plugin_status{
			color: #1c931c;
		}
		.wcpp_plugin_page_wrap .wcpp_plugin_btn{
			background-color: #22D02D;
			color: #fff;
			text-decoration: none;
			padding: 8px;
			transition: 0.2s;
			border-radius: 5px;
		}
		.wcpp_plugin_page_wrap .wcpp_plugin_btn:hover{
			background-color: #0FA218;
			color: #fff;
			transition: 0.2s;
		}
		</style>
		<?php

		$this->wcpp_mpdf_plugin_install();
	}

	public function wcpp_wp_plugin_installation_url($slug){

		if($slug){

			$url = admin_url($this->wcpp_plugin_page_location()).'?page=wcpp-plugins&wcpp_plugin_install='.$slug;			
		}
		else{

			$url = '';
		}

		return $url;		
	}

	public function wcpp_required_plugin_list(){
		
		$list = array();

		if( $this->wcpp_chk_plugin_folder_exist('woocommerce') == false ) {
			$list[] = __('WooCommerce','advanced-partial-payment-or-deposit-for-woocommerce');
		}
		if( $this->wcpp_chk_plugin_folder_exist('advanced-partial-payment-or-deposit-for-woocommerce')  == false) {
			$list[] = __('Advanced – Deposit & Partial Payments for WooCommerce','advanced-partial-payment-or-deposit-for-woocommerce');			
		}
		return $list;		
	}

	public function wcpp_inactive_plugin_list(){
		
		$list = array();

		if($this->wcpp_chk_plugin_folder_exist('woocommerce') == true && !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$list[] = __('WooCommerce','advanced-partial-payment-or-deposit-for-woocommerce');
		}
		if($this->wcpp_chk_plugin_folder_exist('advanced-partial-payment-or-deposit-for-woocommerce') == true && !is_plugin_active( 'advanced-partial-payment-or-deposit-for-woocommerce/advanced_partial_payment.php' ) ) {
			$list[] = __('Advanced – Deposit & Partial Payments for WooCommerce','advanced-partial-payment-or-deposit-for-woocommerce');			
		}
		return $list;		
	}	

	public function wcpp_admin_notices(){

		$url = admin_url($this->wcpp_plugin_page_location()).'?page=wcpp-plugins';	
		
		$required_plugins = $this->wcpp_required_plugin_list();
		$inactive_plugins = $this->wcpp_inactive_plugin_list();
		$total_r_plugins = count($required_plugins);
		$total_i_plugins = count($inactive_plugins);

		if($total_r_plugins > 0 || $total_i_plugins > 0){
		?>
		<div class="notice notice-success is-dismissible">
			<?php
			echo '<p>';
			echo '<strong>';

			if($total_r_plugins > 0){
				$i = 1;
				if($total_r_plugins == 1){
					echo __('Advanced – Deposit & Partial Payments for WooCommerce required the following plugin: ','advanced-partial-payment-or-deposit-for-woocommerce');
				}
				else{
					echo __('Advanced – Deposit & Partial Payments for WooCommerce required the following plugins: ','advanced-partial-payment-or-deposit-for-woocommerce');
				}

				echo '<i>';
				
				foreach ($required_plugins as $plugin) {
					if($i < $total_r_plugins){
						echo $plugin.', ';
					}
					else{
						echo $plugin.'.';
					}
	
					$i++;
				}
				echo '</i>';
				echo '<br/>';
			}

			if($total_i_plugins > 0){
				$i = 1;
				echo __('Advanced – Deposit & Partial Payments for WooCommerce: ','advanced-partial-payment-or-deposit-for-woocommerce');
				echo '<br>';
				if($total_i_plugins == 1){
					echo __('The following required plugin is currently inactive: ','advanced-partial-payment-or-deposit-for-woocommerce');
				}
				else{
					echo __('The following required plugins are currently inactive: ','advanced-partial-payment-or-deposit-for-woocommerce');
				}				

				echo '<i>';

				foreach ($inactive_plugins as $plugin) {
					if($i < $total_i_plugins){
						echo $plugin.', ';
					}
					else{
						echo $plugin.'.';
					}

					$i++;
				}
				echo '</i>';
				echo '<br/>';
			}

			if($total_r_plugins > 0){
				echo '<a href="'.esc_url($url).'">';
				echo __('Begin installing plugins','advanced-partial-payment-or-deposit-for-woocommerce');
				echo '</a>';
			}

			if($total_r_plugins > 0 && $total_i_plugins > 0){
				echo ' | ';
			}
			
			if($total_i_plugins > 0){
				echo '<a href="'.esc_url($url).'">';
				echo __('Activate installed plugin','advanced-partial-payment-or-deposit-for-woocommerce');
				echo '</a>';
			}

			echo '</strong>';
			echo '</p>';
			?>
		</div>
		<?php
		}	
	}
}
}
new WCPP_Required_Plugins();
