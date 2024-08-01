<?php

namespace MagePeople\MEPP;


class  MEPP_Taxonomies
{


    function __construct()
    {

        add_action('init', array($this, 'register_payment_plan_taxonomy'), 10);
        add_action('mepp_payment_plan_edit_form_fields', array($this, 'payment_plan_form_fields'), 20);
        add_action('mepp_payment_plan_edit_form', array($this, 'payment_plan_table'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'), 10, 2);
        add_action('edit_terms', array($this, 'edit_terms'), 10, 2);
        add_action('mepp_payment_plan_term_new_form_tag', array($this, 'new_form_tag'), 10, 2);
        add_action('admin_enqueue_scripts', array($this, 'add_inline_style'));

        add_action('created_term', array($this, 'redirect_after_term_creation'), 10, 3);
        
       
    }
    
    function redirect_after_term_creation($term_id, $tt_id, $taxonomy)
{
    // Check if the created term belongs to the desired taxonomy
    if ($taxonomy === MEPP_PAYMENT_PLAN_TAXONOMY) {
        $redirect_url = admin_url("term.php?taxonomy={$taxonomy}&tag_ID={$term_id}&post_type=post");

        // Generate inline script to handle the redirection
        echo "<script>window.location.replace('{$redirect_url}');</script>";
        exit();
    }
}
    function add_inline_style()
    {
        $screen = get_current_screen();
        if ($screen->id === 'edit-mepp_payment_plan') {
            $style = '.term-slug-wrap , td.slug , th.column-slug , td.column-posts , th.column-posts { display:none!important; }';
            wp_add_inline_style('wc-deposits-admin-style', $style);
        }
    }

    function new_form_tag()
    {
        echo 'data-mepp-form="yes"';
    }

    function enqueue_scripts()
    {
        $wc_ip_taxonomy = false;

        if (function_exists('get_current_screen')) {
            $screen = get_current_screen();
            if ($screen) {
                $wc_ip_taxonomy = $screen->id === 'edit-' . MEPP_PAYMENT_PLAN_TAXONOMY;
            }

        }

        if ($wc_ip_taxonomy) {

            wp_enqueue_script('mepp_pb_jquery_repeater', MEPP_PLUGIN_URL . '/assets/js/admin/jquery.repeater.js', array('jquery'), MEPP_VERSION);
            wp_enqueue_script('mepp_pb_taxonomy_manager', MEPP_PLUGIN_URL . '/assets/js/admin/Admin.js', array('accounting'), MEPP_VERSION);
            wp_localize_script('mepp_pb_taxonomy_manager', 'wcip_data', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'currency_format_num_decimals' => 0,
                'currency_format_symbol'       => get_woocommerce_currency_symbol(),
                'currency_format_decimal_sep'  => esc_attr( wc_get_price_decimal_separator() ),
                'currency_format_thousand_sep' => esc_attr( wc_get_price_thousand_separator() ),
                'currency_format'              => esc_attr( str_replace( array( '%1$s', '%2$s' ), array( '%s', '%v' ), get_woocommerce_price_format() ) ),

            ));
            
        }
    }

    /**
     * Registers mepp_payment_plan taxonomy for products
     */
function register_payment_plan_taxonomy()
{
    // Check if PRO functions exist
    if (function_exists('sr_partial_patment_menu_pro') || function_exists('checkout_mood_pro') || function_exists('second_payment_pro') || function_exists('custom_mepp_amount_type_options') || function_exists('mepp_settings_dropdown_options_pro')) {
        register_taxonomy(
            MEPP_PAYMENT_PLAN_TAXONOMY,
            array('product'),
            array(
                'label' => esc_html__('Payment plans', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                'labels' => array(
                    'name' => esc_html__('Payment plans', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'singular_name' => esc_html__('Payment plan', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'menu_name' => esc_html__('Payment plans', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'search_items' => esc_html__('Search plans', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'all_items' => esc_html__('All plans', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'edit_item' => esc_html__('Edit plan', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'update_item' => esc_html__('Update plan', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'add_new_item' => esc_html__('Add new plan', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'new_item_name' => esc_html__('New plan name', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'add_or_remove_items' => esc_html__('Add or remove plans', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    'not_found' => esc_html__('No plans found', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                ),
                'capabilities' => array(
                    'manage_terms' => 'manage_product_terms',
                    'edit_terms' => 'edit_product_terms',
                    'delete_terms' => 'delete_product_terms',
                    'assign_terms' => 'assign_product_terms',
                ),
                'hierarchical' => false,
                'meta_box_cb' => false,
                'show_ui' => true,
                'show_in_nav_menus' => true,
                'query_var' => is_admin(),
                'rewrite' => false,
                'public' => false
            )
        );
    }
}

    /**
     * adds payment plan fields to mepp_payment_plan taxonomy editor page
     * @param $tag
     */
    function payment_plan_table($tag)
    {

        $term_types = array(
            'day' => 'Day(s)',
            'week' => 'Week(s)',
            'month' => 'Month(s)',
            'year' => 'Year(s)'
        );

        $deposit_percentage = get_term_meta($tag->term_id, 'deposit_percentage', true);
        $payment_details = get_term_meta($tag->term_id, 'payment_details', true);
        ob_start();


        ?>

        <hr/>
        <h3> <?php echo esc_html__('Plan schedule', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></h3>
        <br/>
        <table class="widefat striped" data-populate='<?php echo $payment_details; ?>' id="payment_plan_details">
            <thead>

            <tr>
                <th>&nbsp;</th>
                <th><?php echo esc_html__('Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
                <th colspan="2"> <?php echo esc_html__('Set date', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>
                    / <?php echo esc_html__('After', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?> </th>
                <td>&nbsp;</td>
            </tr>

            </thead>

            <tbody data-repeater-list="payment-plan">
            <tr>

                <td><strong> #1 </strong></td>
                <td class="single_payment">
                    <input name="deposit-percentage" type="number" min="0" step="0.1"
                           value="<?php echo $deposit_percentage; ?>"/>
                </td>
                <td colspan="2"><?php echo esc_html__('Immediately', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></td>
                <td>&nbsp;</td>
            </tr>

            <tr class="single_payment" data-repeater-item>
                <td>
                    <strong> #2 </strong>
                </td>
                <td>
                    <input name="percentage" min="0.1" step="0.1" type="number" required="required"/>
                </td>
                <td>
                    <input class="mepp-pp-date" name="date" type="date" required="required"/>
                    <input class="mepp-pp-after" name="after" min="1" step="1" type="number" required="required"/>
                    <select class="mepp-pp-after-term" required="required" name="after-term">
                        <?php
                        foreach ($term_types as $key => $term_type) {
                            ?>
                        <option value="<?php echo $key; ?>"> <?php echo $term_type; ?> </option><?php
                        }
                        ?>
                    </select>
                </td>
                <td>
                    <input value="on" name="date_checkbox" type="checkbox" class="mepp_pp_set_date"/>
                    <label for="mepp_pp_set_date"><?php echo esc_html__('Set a date', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?> </label>
                </td>
                <td>
                    <input data-repeater-delete class="button" type="button" value="Delete"/>
                </td>
            </tr>

            </tbody>
            <tfoot>
            <tr>
                <td colspan="5"><input data-repeater-create class="button" type="button" value="Add"/></td>
            </tr>
            <tr>
                <td colspan="5">
                    <p> <?php echo esc_html__('Total:', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?> <span id="total_percentage"> </span></p>
                </td>
            </tr>
            </tfoot>
        </table>


        <?php
        echo ob_get_clean();
    }

    function payment_plan_form_fields($term)
    {
        $amount_type = get_term_meta($term->term_id, 'amount_type', true);

        if (empty($amount_type)) $amount_type = 'percentage';
        ob_start();
        ?>
        <tr class="form-field">
            <th scope="row"><label
                        for="amount_type"> <?php echo esc_html__('Amount Type', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></label></th>
            <td>
                <select name="amount_type" id="amount_type">
                    <option <?php selected($amount_type, 'percentage'); ?>
                            value="percentage"><?php echo esc_html__('Percentage', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                    <option <?php selected($amount_type, 'fixed'); ?>
                            value="fixed"><?php echo esc_html__('Fixed', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></option>
                </select></td>
        </tr>
        <?php
        echo ob_get_clean();
    }

    /**
     * Saves custom term meta for mepp_payment_plan when payment plan data is saved
     * @param $term_id
     * @param $taxonomy
     */
    function edit_terms($term_id, $taxonomy)
    {

        if ($taxonomy === MEPP_PAYMENT_PLAN_TAXONOMY) {

            $payment_details = isset($_POST['payment-details']) ? $_POST['payment-details'] : array();
            $amount_type = isset($_POST['amount_type']) ? $_POST['amount_type'] : 'percentage';

            $deposit_percentage = isset($_POST['deposit-percentage']) ? $_POST['deposit-percentage'] : 0.0;
            update_term_meta($term_id, 'deposit_percentage', $deposit_percentage);
            update_term_meta($term_id, 'payment_details', $payment_details);
            update_term_meta($term_id, 'amount_type', $amount_type);
        }

    }
}