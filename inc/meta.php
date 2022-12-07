<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.


add_action('admin_init', 'mep_pp_meta_boxs');
if (!function_exists('mep_pp_meta_boxs')) {
    function mep_pp_meta_boxs()
    {
        if (!wcppe_enable_for_event()) return;

        if (!is_plugin_active('mage-eventpress/woocommerce-event-press.php')) {
            return;
        }

        $email_body_meta_boxs = array(
            'page_nav'     => __('Partial Payment Info', 'mage-eventpress-gq'),
            'priority' => 10,
            'sections' => array(
                'section_2' => array(
                    'title'     =>     __('', 'mage-eventpress-gq'),
                    'description'     => __('', 'mage-eventpress-gq'),
                    'options'     => array(
                        apply_filters('mepp_exclude_product_from_default_setting_event', []),
                        array(
                            'id'    => '_mep_enable_pp_deposit',
                            'title'    => __('Enable Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            'details'  => __('Enable deposits feature for this event?.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            'type'    => 'checkbox',
                            'default'    => '',
                            'args'    => array(
                                'yes'  => __('Enable deposits feature.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            ),
                        ),
                        array(
                            'id'            => '_mep_pp_deposits_type',
                            'title'            => __('Deposit type', 'mage-eventpress'),
                            'details'        => __('', 'mage-eventpress'),
                            'multiple'        => false,
                            'type'            => 'select',
                            'args'            => apply_filters('mepp_event_partial_list_option', array(
                                'percent'       => __('Percentage of Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                                'fixed'         => __('Fixed Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            ))
                        ),
                        array(
                            'id'            => '_mep_pp_deposits_value',
                            'title'            => __('Deposit Value', 'mage-eventpress'),
                            'details'        => __('Enter the value for deposit. only number allow. If you select User Custom Amount then this value will be the minimum amount of the value.', 'mage-eventpress'),
                            'type'            => 'text',
                            'default'        => "",
                            'placeholder'   => __("10", 'mage-eventpress'),
                        ),
                        array(
                            'id'            => '_mep_pp_minimum_value',
                            'title'            => __('Minimum Value', 'mage-eventpress'),
                            'details'        => __('Enter a minimum value. This is the Minimum Payment Amount of this Deposit Type', 'mage-eventpress'),
                            'type'            => 'text',
                            'default'        => "",
                            'placeholder'   => __("10", 'mage-eventpress'),
                        ),
                        array(
                            'id'            => '_mep_pp_payment_plan',
                            'title'            => __('Payment plan(s)', 'mage-eventpress'),
                            'details'        => __('', 'mage-eventpress'),
                            'multiple'        => true,
                            'type'            => 'select',
                            'args'            => apply_filters('mepp_payment_plan_options', array())
                        )
                    )
                ),

            ),
        );
        $email_body_meta_args = array(
            'meta_box_id'               => 'mep_event_pp_meta_boxes',
            'meta_box_title'            => '<span class="dashicons dashicons-email"></span>&nbsp;' . __('Partial Payment', 'mage-eventpress'),
            //'callback'       => '_meta_box_callback',
            'screen'                    => array('mep_events'),
            'context'                   => 'normal', // 'normal', 'side', and 'advanced'
            'priority'                  => 'low', // 'high', 'low'
            'callback_args'             => array(),
            'nav_position'              => 'none', // right, top, left, none
            'item_name'                 => "MagePeople",
            'item_version'              => "2.0",
            'panels'                     => array(
                'mep_debug_wc_list' => $email_body_meta_boxs
            ),
        );

        if (class_exists('AddMetaBox')) {
            new AddMetaBox($email_body_meta_args);
        }
    }
}



add_action('save_post', 'mep_pp_save', 99);
if (!function_exists('mep_pp_save')) {
    function mep_pp_save($post_id)
    {
        global $wpdb;

        if (empty($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(wp_unslash($_POST['woocommerce_meta_nonce']), 'woocommerce_save_data')) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) == 'mep_events') {
            $linked_wc_id              = get_post_meta($post_id, 'link_wc_product', true) ? get_post_meta($post_id, 'link_wc_product', true) : $post_id;
            $_mep_enable_pp_deposit    = sanitize_text_field($_POST['_mep_enable_pp_deposit']);
            $_mep_pp_deposits_type     = sanitize_text_field($_POST['_mep_pp_deposits_type']);
            $_mep_pp_deposits_value    = sanitize_text_field($_POST['_mep_pp_deposits_value']);

            update_post_meta($linked_wc_id, '_mep_enable_pp_deposit', $_mep_enable_pp_deposit);
            update_post_meta($linked_wc_id, '_mep_pp_deposits_type', $_mep_pp_deposits_type);
            update_post_meta($linked_wc_id, '_mep_pp_deposits_value', $_mep_pp_deposits_value);
        } else {
            $payment_plans = isset($_POST['_mep_pp_payment_plan']) ? $_POST['_mep_pp_payment_plan'] : array();
            update_post_meta($post_id, '_mep_pp_payment_plan', $payment_plans);
        }
    }
}


add_action('woocommerce_product_data_tabs', 'mep_pp_pp_deposits_options_tab');
if (!function_exists('mep_pp_pp_deposits_options_tab')) {
    function mep_pp_pp_deposits_options_tab($tabs)
    {
        $tabs['deposits'] = array(
            'label'  => __('Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'target' => 'woo_desposits_options',
            'class'  => array('show_if_simple', 'show_if_variable'),
        );
        return $tabs;
    }
}


add_action('woocommerce_product_data_panels', 'mep_pp_pp_deposits_options_fileds');
if (!function_exists('mep_pp_pp_deposits_options_fileds')) {
    function mep_pp_pp_deposits_options_fileds()
    {
        $checkout_mode = get_option('mepp_partial_enable_for_page');
        $partial_data = wcpp_partial_setting_level_data(get_the_ID());
        $status_class = $partial_data['is_enable'] ? 'enabled' : 'disabled';
?>

        <div id="woo_desposits_options" class="panel woocommerce_options_panel">
            <?php if ($checkout_mode !== 'checkout') : ?>
                <div class="options_group">
                    <?php
                    // Exclude product deposit setting from default setting (Woo Product)
                    echo '<div class="wcpp_local_setting_include_inherite">';
                    woocommerce_wp_select(
                        array(
                            'id'      => '_mep_exclude_from_global_deposit',
                            'label'   => __('Inherit site-wide setting', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            'class'   => 'wc-enhanced-select',
                            'options' => [
                                'no' => __('No', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                                'yes'   => __('Yes', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            ],
                            'value'   => get_post_meta(get_the_ID(), '_mep_exclude_from_global_deposit', true),
                        )
                    );
                    echo '</div>';
                    ?>
                </div>
                <div class="options_group">
                <?php
                    echo '<div class="wcpp_local_setting">';
                    woocommerce_wp_checkbox(
                        array(
                            'id'          => '_mep_enable_pp_deposit',
                            'label'       => __('Enable Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            'value'       => get_post_meta(get_the_ID(), '_mep_enable_pp_deposit', true),
                            'description' => __('Enable deposits feature for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        )
                    );
                    woocommerce_wp_select(
                        array(
                            'id'      => '_mep_pp_deposits_type',
                            'label'   => __('Deposit type', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            'class'   => 'wc-enhanced-select',
                            'options' => apply_filters('mepp_product_partial_list_option', [
                                'percent' => __('Percentage of Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                                'fixed'   => __('Fixed Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            ]),
                            'value'   => get_post_meta(get_the_ID(), '_mep_pp_deposits_type', true),
                        )
                    );
                    woocommerce_wp_text_input(
                        array(
                            'id'          => '_mep_pp_deposits_value',
                            'label'       => __('Deposit Value *', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            'placeholder' => '',
                            'value'       => get_post_meta(get_the_ID(), '_mep_pp_deposits_value', true),
                            'style'       => 'width:60px;',
                            'description' => __('Enter the value for deposit. only number allow.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        )
                    );
                    woocommerce_wp_text_input(
                        array(
                            'id'          => '_mep_pp_minimum_value',
                            'label'       => __('Minimum Value *', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                            'placeholder' => '',
                            'value'       => get_post_meta(get_the_ID(), '_mep_pp_minimum_value', true),
                            'style'       => 'width:60px;',
                            'description' => __('Enter a minimum value. This is the Minimum Payment Amount of this Deposit Type. Only Number allow.', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        )
                    );
                    do_action('mep_pp_payment_plan_woo_product');
                    do_action('mep_pp_pp_deposits_options_fileds');
                    echo '</div>';
                    echo '</div>';
            else :
                echo '<h3>' . __("Checkout mode enabled", "advanced-partial-payment-or-deposit-for-woocommerce") . '</h3>';
                printf('<h4>%s. <a href="%s">%s</a> </h4>', __('If you would like to use deposit product basis, please disable checkout mode.', 'advanced-partial-payment-or-deposit-for-woocommerce'), get_admin_url(null, '/admin.php?page=mage-partial-setting'), __('Go to plugin setting', 'advanced-partial-payment-or-deposit-for-woocommerce'));
            endif; ?>
                <div class="wcpp-status-container">
                    <h3><?php _e('Partial status for this product', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></h3>
                    <p><strong>Partial Payment </strong>: <span class="wcpp-status-flag <?php echo $status_class ?>"><?php echo $partial_data['is_enable'] ? 'Enabled' : 'Disabled' ?></span></p>
                    <p><strong>Deposit Type </strong>: <span><?php echo ucfirst(str_replace('_', ' ', $partial_data['deposit_type'])) ?></span></p>
                    <?php if (is_array($partial_data['deposit_value'])) : ?>
                        <!-- For Payment Plan -->
                        <p><strong>Deposit value </strong>: <span><?php echo (implode(',', $partial_data['deposit_value'])) ?></span></p>
                    <?php elseif (!$partial_data['deposit_value']) : ?>
                        <!-- Value not set yet -->
                        <p><strong>Deposit value </strong>: <span style="color:#795548"><span class="dashicons dashicons-info"></span><?php _e('Partial value is not set so partial is now disabled for this product. Please set a value.', 'advanced-partial-payment-or-deposit-for-woocommerce') ?></span></p>
                    <?php else : ?>
                        <!-- For others plan -->
                        <p><strong>Deposit value </strong>: <span><?php echo wc_price($partial_data['deposit_value']) ?></span></p>
                    <?php endif; ?>
                    <p><strong>Setting </strong>: <span><?php echo ucfirst($partial_data['setting_level']); ?></span></p>
                </div>
        </div>

<?php }
}


add_action('woocommerce_process_product_meta', 'mep_pp_pp_deposits_product_fields_save');
if (!function_exists('mep_pp_pp_deposits_product_fields_save')) {
    function mep_pp_pp_deposits_product_fields_save($post_id)
    {
        $field_mep_enable_pp_deposit = isset($_POST['_mep_enable_pp_deposit']) ? 'yes' : 'no';
        $field_mep_exclude_from_global_deposit = isset($_POST['_mep_exclude_from_global_deposit']) ? $_POST['_mep_exclude_from_global_deposit'] : 'no';
        $field_mep_pp_deposits_type  = sanitize_text_field($_POST['_mep_pp_deposits_type']);
        $field_mep_pp_deposits_value = sanitize_text_field($_POST['_mep_pp_deposits_value']);
        $field_mep_pp_minimum_value = sanitize_text_field($_POST['_mep_pp_minimum_value']);
        update_post_meta($post_id, '_mep_enable_pp_deposit', $field_mep_enable_pp_deposit);
        update_post_meta($post_id, '_mep_exclude_from_global_deposit', $field_mep_exclude_from_global_deposit);
        if (!empty($field_mep_pp_deposits_type)) {
            update_post_meta($post_id, '_mep_pp_deposits_type', $field_mep_pp_deposits_type);
        }
        if (!empty($field_mep_pp_deposits_value)) {
            update_post_meta($post_id, '_mep_pp_deposits_value', intval($field_mep_pp_deposits_value));
        } else {
            update_post_meta($post_id, '_mep_pp_deposits_value', 0);
        }
        if (!empty($field_mep_pp_minimum_value)) {
            update_post_meta($post_id, '_mep_pp_minimum_value', intval($field_mep_pp_minimum_value));
        } else {
            update_post_meta($post_id, '_mep_pp_minimum_value', 0);
        }
    }
}

// Payment Plan field on woocommerce product
add_action('mep_pp_payment_plan_woo_product', 'mep_pp_payment_plan_woo_product_callback');
if (!function_exists('mep_pp_payment_plan_woo_product_callback')) {
    function mep_pp_payment_plan_woo_product_callback()
    {

        global $post;
        if (is_null($post)) return;
        $product = wc_get_product($post->ID);
        if (!$product) return;

        if (is_plugin_active('mage-partial-payment-pro/mage_partial_pro.php')) {
            $payment_plans = get_terms(
                array(
                    'taxonomy'      => 'mepp_payment_plan',
                    'hide_empty'    => false
                )
            );
            $all_plans = array();
            if (!empty($payment_plans)) {
                foreach ($payment_plans as $payment_plan) {
                    $all_plans[$payment_plan->term_id] = $payment_plan->name;
                }
            }
            woocommerce_wp_select(array(
                'id'            => "_mep_pp_payment_plan",
                'name'          => "_mep_pp_payment_plan[]",
                'label'         => esc_html__('Payment plan(s)', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'description'   => esc_html__('Selected payment plan(s) will be available for customers to choose from', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'value'         => $product->get_meta('_mep_pp_payment_plan'),
                'options'       => $all_plans,
                'desc_tip'      => true,
                'style'         => 'width:50%;',
                'class'         => 'wc-enhanced-select ',
                'wrapper_class' => '',
                'custom_attributes' => array(
                    'multiple'  => 'multiple'
                )

            ));
        }
    }
}
