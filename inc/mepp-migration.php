<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Migration of 2024 Function.....
function migrate_partial_payment_meta() {

    $mepp_migration_done_2024 = get_option('mepp_migration_done_2024') ? get_option('mepp_migration_done_2024') : 'no';
    if($mepp_migration_done_2024 == 'no'){

        $old_sidewide_value                 = get_option('mepp_enable_partial_by_default') ? get_option('mepp_enable_partial_by_default') : 'no';
        $old_sidewide_partial_type          = get_option('mepp_default_partial_type');
        $old_sidewide_partial_value         = get_option('mepp_default_partial_amount');

        if($old_sidewide_value == 'yes'){
            update_option('mepp_storewide_deposit_enabled','yes');
            update_option('mepp_storewide_deposit_amount_type',$old_sidewide_partial_type);
            update_option('mepp_storewide_deposit_amount',$old_sidewide_partial_value);
        }


        // Query all posts (adjust post type if necessary)
        $args = array(
            'post_type' => 'product', // Change this to the correct post type if needed
            'posts_per_page' => -1 // Get all posts
        );
        
        $query = new WP_Query($args);

        // Check if posts are found
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                // Get the current post ID _mep_exclude_from_global_deposit
                $post_id                        = get_the_ID();
                $old_enable_deposit             = get_post_meta($post_id, '_mep_enable_pp_deposit', true);
                $old_deposits_type              = get_post_meta($post_id, '_mep_pp_deposits_type', true);
                $old_deposits_value             = get_post_meta($post_id, '_mep_pp_deposits_value', true);
                $old_inharit_deposits_value     = get_post_meta($post_id, '_mep_exclude_from_global_deposit', true) ? get_post_meta($post_id, '_mep_exclude_from_global_deposit', true) : 'no';

        
                if ($old_enable_deposit !== '') {
                    update_post_meta($post_id, '_mepp_enable_deposit', $old_enable_deposit);
                }
        
                if ($old_deposits_type !== '') {
                    update_post_meta($post_id, '_mepp_amount_type', $old_deposits_type);
                }
        
                if ($old_deposits_value !== '') {
                    update_post_meta($post_id, '_mepp_deposit_amount', $old_deposits_value);
                }

                if ($old_inharit_deposits_value !== '') {
                    update_post_meta($post_id, '_mepp_inherit_storewide_settings', $old_inharit_deposits_value);
                }            

            }
            // Reset post data
            wp_reset_postdata();
        }
        update_option('mepp_migration_done_2024','yes');
    }
}
// Hook the function to an action that runs once (like admin_init)
add_action('admin_init', 'migrate_partial_payment_meta');