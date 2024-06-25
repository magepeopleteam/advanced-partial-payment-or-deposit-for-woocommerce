<?php

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use MagePeople\MEPP\MEPP_Admin_Order;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

function send_partially_paid_order_email_notification($order_id) {
    $order = wc_get_order($order_id);
    
    // Initialize variables to track if minimum amount paid and remaining amount are present
    $minimum_amount_present = false;
    $remaining_amount_present = false;
    
    // Loop through order items to check if either minimum amount or remaining amount is present
    foreach ($order->get_items() as $item_id => $item) {
        $minimum_amount_paid = floatval(wc_get_order_item_meta($item_id, 'Minimum Amount', true));
        $product = $item->get_product();
        $price = floatval($product->get_price());
        $quantity = intval($item->get_quantity());
        $remaining_amount = ($price * $quantity) - $minimum_amount_paid;
        
        if ($minimum_amount_paid > 0) {
            $minimum_amount_present = true;
        }
        
        if ($remaining_amount > 0) {
            $remaining_amount_present = true;
        }
    }
    
    // Check if both minimum amount paid and remaining amount are present
    if ($minimum_amount_present && $remaining_amount_present) {
        // Initialize email content with default WooCommerce heading
        $email_content = '<h2 style="margin-bottom: 20px;">' . esc_html__('Thank you for your order', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</h2>';
        
        // Get customer's name
        $customer_name = sanitize_text_field($order->get_billing_first_name());
        
        // Add personalized greeting
        $email_content .= '<p>' . sprintf(esc_html__('Hi %s,', 'advanced-partial-payment-or-deposit-for-woocommerce'), $customer_name) . '</p>';
        $email_content .= '<p>' . sprintf(esc_html__('Just letting you know, we have received your partial order #%s and it is now being processed.', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html($order_id)) . '</p>';
        
        // Add payment method information
        if ($order->get_payment_method() === 'cod') {
            $email_content .= '<p>' . esc_html__('Pay with cash upon delivery.', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</p>';
        }
        
        // Add order details in h1 tag
        $email_content .= '<h1>' . sprintf(esc_html__('Order #%s - %s', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html($order_id), esc_html($order->get_date_created()->format('F j, Y'))) . '</h1>';
        
        // Start table for product details
        $email_content .= '<table style="width:100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 20px;">';
        $email_content .= '<thead><tr style="background-color: #f2f2f2;">';
        $email_content .= '<th style="padding: 10px; text-align: left;">' . esc_html__('Product', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th>';
        $email_content .= '<th style="padding: 10px; text-align: center;">' . esc_html__('Quantity', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th>';
        $email_content .= '<th style="padding: 10px; text-align: center;">' . esc_html__('Price', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th>';
        $email_content .= '<th style="padding: 10px; text-align: center;">' . esc_html__('Minimum Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th>';
        $email_content .= '<th style="padding: 10px; text-align: center;">' . esc_html__('Due', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th>';
        $email_content .= '</tr></thead><tbody>';
        
        // Loop through order items to include product details in the table
        foreach ($order->get_items() as $item_id => $item) {
            $product_name = sanitize_text_field($item->get_name());
            $quantity = intval($item->get_quantity());
            $product = $item->get_product();
            $price = floatval($product->get_price());
            $minimum_amount_paid = floatval(wc_get_order_item_meta($item_id, 'Minimum Amount', true));
            $remaining_amount = ($price * $quantity) - $minimum_amount_paid;
            
            // Add row for each product
            $email_content .= '<tr>';
            $email_content .= '<td style="padding: 10px; text-align: left;">' . esc_html($product_name) . '</td>';
            $email_content .= '<td style="padding: 10px; text-align: center;">' . esc_html($quantity) . '</td>';
            $email_content .= '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($price)) . '</td>';
            $email_content .= '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($minimum_amount_paid)) . '</td>';
            $email_content .= '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($remaining_amount)) . '</td>';
            $email_content .= '</tr>';
        }
        
        // Close the table for product details
        $email_content .= '</tbody></table>';
        
        // Get payment history
        ob_start();
        display_payment_history_on_thankyou($order_id);
        $payment_history = ob_get_clean();
        
        // Include payment history in the email content
        $email_content .= $payment_history;
        
        // Send email to admin
        $admin_email = sanitize_email(get_option('admin_email'));
        $admin_subject = esc_html__('Partially Paid Order Notification', 'advanced-partial-payment-or-deposit-for-woocommerce');
        $admin_headers = array('Content-Type: text/html; charset=UTF-8');
        if (is_email($admin_email)) {
            wp_mail($admin_email, $admin_subject, $email_content, $admin_headers);
        }
        
        // Send email to customer
        $customer_email = sanitize_email($order->get_billing_email());
        $customer_subject = esc_html__('Partially Paid Order Notification', 'advanced-partial-payment-or-deposit-for-woocommerce');
        $customer_headers = array('Content-Type: text/html; charset=UTF-8');
        if (is_email($customer_email)) {
            wp_mail($customer_email, $customer_subject, $email_content, $customer_headers);
        }
    }
}
add_action('woocommerce_order_status_partially-paid', 'send_partially_paid_order_email_notification', 10, 1);

/**
 * Add custom field to cart item data.
 */
function add_minimum_amount_to_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity) {
    $minimum_amount = isset($_POST['custom-deposit-amount']) ? sanitize_text_field($_POST['custom-deposit-amount']) : '';

    if (!empty($minimum_amount)) {
        $cart_item_data['minimum_amount'] = $minimum_amount;
    }

    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'add_minimum_amount_to_cart_item_data', 10, 4);

/**
 * Display custom field value on the cart and checkout pages.
 */
function display_minimum_amount_on_cart($item_data, $cart_item) {
    if (isset($cart_item['minimum_amount'])) {
        $item_data[] = array(
            'key'   => esc_html__('Minimum Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'value' => wp_kses_post(wc_price($cart_item['minimum_amount'])),
        );

        // Calculate remaining amount for future payments
        $product = wc_get_product($cart_item['product_id']);
        $product_price = floatval($product->get_price());
        $deposit_amount = floatval($cart_item['minimum_amount']);
        $remaining_amount = ($product_price * intval($cart_item['quantity'])) - $deposit_amount;

        $item_data[] = array(
            'key'   => esc_html__('Future payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'value' => wp_kses_post(wc_price($remaining_amount)),
        );
    }

    return $item_data;
}
add_filter('woocommerce_get_item_data', 'display_minimum_amount_on_cart', 10, 2);

/**
 * Add custom field to order item meta.
 */
function add_minimum_amount_to_order_item_meta($item_id, $cart_item) {
    if (isset($cart_item['minimum_amount'])) {
        wc_add_order_item_meta($item_id, esc_html__('Minimum Amount', 'advanced-partial-payment-or-deposit-for-woocommerce'), sanitize_text_field($cart_item['minimum_amount']));
    }
}
add_action('woocommerce_new_order_item', 'add_minimum_amount_to_order_item_meta', 10, 2);



/**
 * Adjust order total at checkout to be the deposit amount.
 */
function adjust_order_total_for_deposit($cart_total) {
    $cart = WC()->cart;
    $deposit_amount = 0;

    // Loop through cart items to calculate deposit amount
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['minimum_amount'])) {
            $deposit_amount += floatval($cart_item['minimum_amount']);
        }
    }

    if ($deposit_amount > 0) {
        $cart_total = $deposit_amount;
    }

    return $cart_total;
}
add_filter('woocommerce_calculated_total', 'adjust_order_total_for_deposit', 10, 1);

/**
 * Display minimum deposit amount and remaining amount in cart totals.
 */
function display_minimum_deposit_and_remaining_amount_in_cart_totals() {
    $cart = WC()->cart;
    $deposit_amount = 0;
    $remaining_amount = 0;

    // Loop through cart items to calculate deposit amount and remaining amount
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['minimum_amount'])) {
            $deposit_amount += floatval($cart_item['minimum_amount']);
            
            // Calculate remaining amount for future payments
            $product = wc_get_product($cart_item['product_id']);
            $product_price = floatval($product->get_price());
            $remaining_amount += ($product_price * intval($cart_item['quantity'])) - floatval($cart_item['minimum_amount']);
        }
    }

    if ($deposit_amount > 0) {
        ?>
        <tr class="deposit-amount">
            <th><?php esc_html_e('To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <td data-title="<?php esc_html_e('To Pay', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"><?php echo wp_kses_post(wc_price($deposit_amount)); ?></td>
        </tr>
        <tr class="remaining-amount">
            <th><?php esc_html_e('Future payments', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?></th>
            <td data-title="<?php esc_html_e('Future payments', 'advanced-partial-payment-or-deposit-for-woocommerce'); ?>"><?php echo wp_kses_post(wc_price($remaining_amount)); ?></td>
        </tr>
        <?php
    }
}
add_action('woocommerce_cart_totals_before_order_total', 'display_minimum_deposit_and_remaining_amount_in_cart_totals');
add_action('woocommerce_review_order_before_order_total', 'display_minimum_deposit_and_remaining_amount_in_cart_totals');

/**
 * Display Payment History on Thank You Page
 */
function display_payment_history_on_thankyou($order_id) {
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    // Retrieve deposit type
    $items = $order->get_items();
    $count = 1;
    
    $minimum_amount_total = 0; // Initialize total minimum amount paid
    $remaining_amount_total = 0; // Initialize total remaining amount
    
    // Calculate total minimum amount paid and remaining amount
    foreach ($items as $item) {
        $minimum_amount_paid = floatval(wc_get_order_item_meta($item->get_id(), 'Minimum Amount', true));
        $product = $item->get_product();
        $product_price = floatval($product->get_price());
        $quantity = intval($item->get_quantity());
        $remaining_amount = ($product_price * $quantity) - $minimum_amount_paid;
        
        $additional_payments = floatval($order->get_meta('_additional_payments', true));
        $remaining_amount -= $additional_payments;

        $minimum_amount_total += $minimum_amount_paid;
        $remaining_amount_total += max(0, $remaining_amount); // Ensure remaining amount is not negative
    }

    // Display payment history only if either minimum amount or remaining amount is greater than zero
    if ($minimum_amount_total > 0 || $remaining_amount_total > 0) {
        echo '<h2>' . esc_html__('Payment History', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</h2>';
        echo '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 20px;"><thead><tr style="background-color: #f12971;color: white;"><th style="padding: 10px;">' . esc_html__('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Payment Method', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Amount Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Due', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Status', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th></tr></thead><tbody>';

        foreach ($items as $item) {
            $minimum_amount_paid = floatval(wc_get_order_item_meta($item->get_id(), 'Minimum Amount', true));
            $product = $item->get_product();
            $product_price = floatval($product->get_price());
            $quantity = intval($item->get_quantity());
            $remaining_amount = ($product_price * $quantity) - $minimum_amount_paid;

            $additional_payments = floatval($order->get_meta('_additional_payments', true));
            $remaining_amount -= $additional_payments;

            echo '<tr style="border: 1px solid #ccc;">';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_date_created()->date('Y-m-d')) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_payment_method_title()) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($minimum_amount_paid)) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price(($product_price * $quantity) - $minimum_amount_paid)) . '<br><a href="' . esc_url(wc_get_checkout_url() . 'order-pay/' . $order_id . '/?pay_for_order=true&key=' . $order->get_order_key() . '&payment=partial_payment&remaining_amount=' . $remaining_amount . '&item_id=' . $item->get_id()) . '" class="button pay-now-button">' . esc_html__('Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a></td>';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html__('Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</td>';
            echo '</tr>';

            // Display additional payment details if any
            if ($additional_payments > 0) {
                echo '<tr style="border: 1px solid #ccc;">';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_date_created()->date('Y-m-d')) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_payment_method_title()) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($additional_payments)) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price(0)) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html__('Completed', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</td>';
                echo '</tr>';
            }

            $count++;
        }

        echo '</tbody></table>';

        // Hide Pay Now button if remaining amount is zero
        if ($remaining_amount_total <= 0) {
            echo '<style>.pay-now-button { display: none; }</style>';
        }
    }
}
add_action('woocommerce_thankyou', 'display_payment_history_on_thankyou', 10, 1);



/**
 * Display Remaining Amount on Order Pay Page
 */
function display_remaining_amount_on_order_pay_page($order) {
    if (isset($_GET['remaining_amount'])) {
        $remaining_amount = floatval(sanitize_text_field($_GET['remaining_amount']));

        if ($remaining_amount > 0) {
            echo '<tr class="remaining-amount">';
                echo '<th>' . esc_html__('Remaining Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th>';
                echo '<td data-title="' . esc_html__('Remaining Amount', 'advanced-partial-payment-or-deposit-for-woocommerce') . '">' . wp_kses_post(wc_price($remaining_amount)) . '</td>';
            echo '</tr>';
        }
    }
}
add_action('woocommerce_review_order_before_order_total', 'display_remaining_amount_on_order_pay_page');

/**
 * Adjust Order Total for Remaining Amount
 */
function adjust_order_total_for_remaining_amount($total, $order) {
    if (isset($_GET['remaining_amount'])) {
        $remaining_amount = floatval(sanitize_text_field($_GET['remaining_amount']));

        if ($remaining_amount > 0) {
            // Update the order total to reflect the remaining amount
            $total = $remaining_amount;
        }
    }

    return $total;
}
add_filter('woocommerce_order_get_total', 'adjust_order_total_for_remaining_amount', 10, 2);

/**
 * Change Order Status to "Partially Paid" After Order Placement
 */
function change_order_status_to_partially_paid($order_id) {
    $order = wc_get_order($order_id);

    if ($order) {
        $order->update_status('partially-paid');
    }
}
add_action('woocommerce_thankyou', 'change_order_status_to_partially_paid', 15, 1);



/**
 * Update order after partial payment
 */
function update_order_after_partial_payment($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    $remaining_amount = isset($_GET['remaining_amount']) ? floatval(sanitize_text_field($_GET['remaining_amount'])) : 0;
    $item_id = isset($_GET['item_id']) ? intval(sanitize_text_field($_GET['item_id'])) : 0; // Retrieve item ID
    if ($remaining_amount > 0) {
        $order->add_meta_data('_additional_payments', $remaining_amount, true);
        $order->add_order_note(sprintf(__('An additional payment of %s has been received for item ID %s.', 'advanced-partial-payment-or-deposit-for-woocommerce'), wc_price($remaining_amount), $item_id)); // Include item ID in the note

        // Update order total
        $total = $order->get_total() - $remaining_amount;
        $order->set_total($total);

        // Check if the remaining amount covers the order total or more
        if ($total <= 0) {
            $order->update_status('completed');
        } else {
            $order->update_status('partially-paid');
        }

        $order->save();
    }

    // Check if the order is fully paid and update status to completed
    if ($order->get_total() <= 0 && $order->get_status() === 'partially-paid') {
        $order->update_status('completed');
        $original_total = $order->get_subtotal();
        $order->set_total($original_total);
        $order->save();
    }
}
add_action('woocommerce_order_status_changed', 'update_order_after_partial_payment', 20, 1);

/**
 * Display Payment History on View Order Page
 */
function display_payment_history_on_view_order($order_id) {

    $selected_deposit_type = isset($_POST['_mepp_amount_type']) ? sanitize_text_field($_POST['_mepp_amount_type']) : '';
    
    if ($selected_deposit_type !== 'minimum_amount') {
        return; // Exit the function without displaying payment history
    }
    
    $order = wc_get_order($order_id);
    $items = $order->get_items();
    $count = 1;
    
    $minimum_amount_total = 0; // Initialize total minimum amount paid
    $remaining_amount_total = 0; // Initialize total remaining amount
    
    // Calculate total minimum amount paid and remaining amount
    foreach ($items as $item) {
        $minimum_amount_paid = floatval(wc_get_order_item_meta($item->get_id(), 'Minimum Amount', true));
        $product = $item->get_product();
        $product_price = floatval($product->get_price());
        $quantity = intval($item->get_quantity());
        $remaining_amount = ($product_price * $quantity) - $minimum_amount_paid;
        
        $additional_payments = floatval($order->get_meta('_additional_payments', true));
        $remaining_amount -= $additional_payments;

        $minimum_amount_total += $minimum_amount_paid;
        $remaining_amount_total += max(0, $remaining_amount); // Ensure remaining amount is not negative
    }

    // Display payment history only if either minimum amount or remaining amount is greater than zero
        if ($minimum_amount_total > 0 || $remaining_amount_total > 0) {
        echo '<h2>' . esc_html__('Payment History', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</h2>';
        echo '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 20px;"><thead><tr style="background-color: #f12971;color: white;"><th style="padding: 10px;">' . esc_html__('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Payment Method', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Amount Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Due', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Status', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th></tr></thead><tbody>';

        foreach ($items as $item) {
            $minimum_amount_paid = floatval(wc_get_order_item_meta($item->get_id(), 'Minimum Amount', true));
            $product = $item->get_product();
            $product_price = floatval($product->get_price());
            $quantity = intval($item->get_quantity());
            $remaining_amount = ($product_price * $quantity) - $minimum_amount_paid;

            $additional_payments = floatval($order->get_meta('_additional_payments', true));
            $remaining_amount -= $additional_payments;

            echo '<tr style="border: 1px solid #ccc;">';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_date_created()->date('Y-m-d')) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_payment_method_title()) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($minimum_amount_paid)) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($remaining_amount)) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html__('Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</td>';
            echo '</tr>';

            // Display "Pay Now" button if remaining amount is not zero
            if ($remaining_amount > 0) {
                echo '<tr style="border: 1px solid #ccc;">';
                echo '<td colspan="6" style="padding: 10px; text-align: center;"><a href="' . esc_url(wc_get_checkout_url() . 'order-pay/' . $order_id . '/?pay_for_order=true&key=' . $order->get_order_key() . '&payment=partial_payment&remaining_amount=' . $remaining_amount . '&item_id=' . $item->get_id()) . '" class="button pay-now-button">' . esc_html__('Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a></td>';
                echo '</tr>';
            }

            // Display additional payment details if any
            if ($additional_payments > 0) {
                echo '<tr style="border: 1px solid #ccc;">';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_date_created()->date('Y-m-d')) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_payment_method_title()) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($additional_payments)) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">0.00</td>';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html__('Completed', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</td>';
                echo '</tr>';
            }

            $count++;
        }

        echo '</tbody></table>';
    }
}
add_action('woocommerce_view_order', 'display_payment_history_on_view_order', 10, 1);



/**
 * Display Payment History on Admin Order Page
 */
function display_payment_history_on_admin_order($order_id) {

    $selected_deposit_type = isset($_POST['_mepp_amount_type']) ? sanitize_text_field($_POST['_mepp_amount_type']) : '';
    
    if ($selected_deposit_type !== 'minimum_amount') {
        return; // Exit the function without displaying payment history
    }
    $order = wc_get_order($order_id);
    $items = $order->get_items();
    $count = 1;
    
    $minimum_amount_total = 0; // Initialize total minimum amount paid
    $remaining_amount_total = 0; // Initialize total remaining amount
    
    // Calculate total minimum amount paid and remaining amount
    foreach ($items as $item) {
        $minimum_amount_paid = floatval(wc_get_order_item_meta($item->get_id(), 'Minimum Amount', true));
        $product = $item->get_product();
        $product_price = floatval($product->get_price());
        $quantity = intval($item->get_quantity());
        $remaining_amount = ($product_price * $quantity) - $minimum_amount_paid;
        
        $additional_payments = floatval($order->get_meta('_additional_payments', true));
        $remaining_amount -= $additional_payments;

        $minimum_amount_total += $minimum_amount_paid;
        $remaining_amount_total += max(0, $remaining_amount); // Ensure remaining amount is not negative
    }

    // Display payment history only if either minimum amount or remaining amount is greater than zero
        if ($minimum_amount_total > 0 || $remaining_amount_total > 0) {
            echo '<div id="payment-history" class="order_data_column">';
        echo '<h2>' . esc_html__('Payment History', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</h2>';
        echo '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ccc; margin-bottom: 20px;"><thead><tr style="background-color: #f12971;color: white;"><th style="padding: 10px;">' . esc_html__('Payment Date', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Payment Method', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Amount Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Due', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th><th style="padding: 10px;">' . esc_html__('Status', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</th></tr></thead><tbody>';

        foreach ($items as $item) {
            $minimum_amount_paid = floatval(wc_get_order_item_meta($item->get_id(), 'Minimum Amount', true));
            $product = $item->get_product();
            $product_price = floatval($product->get_price());
            $quantity = intval($item->get_quantity());
            $remaining_amount = ($product_price * $quantity) - $minimum_amount_paid;

            $additional_payments = floatval($order->get_meta('_additional_payments', true));
            $remaining_amount -= $additional_payments;

            echo '<tr style="border: 1px solid #ccc;">';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_date_created()->date('Y-m-d')) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_payment_method_title()) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($minimum_amount_paid)) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($remaining_amount)) . '</td>';
            echo '<td style="padding: 10px; text-align: center;">' . esc_html__('Partially Paid', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</td>';
            echo '</tr>';

            // Display "Pay Now" button if remaining amount is not zero
            if ($remaining_amount > 0) {
                echo '<tr style="border: 1px solid #ccc;">';
                echo '<td colspan="6" style="padding: 10px; text-align: center;"><a href="' . esc_url(wc_get_checkout_url() . 'order-pay/' . $order_id . '/?pay_for_order=true&key=' . $order->get_order_key() . '&payment=partial_payment&remaining_amount=' . $remaining_amount . '&item_id=' . $item->get_id()) . '" class="button pay-now-button">' . esc_html__('Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a></td>';
                echo '</tr>';
            }

            // Display additional payment details if any
            if ($additional_payments > 0) {
                echo '<tr style="border: 1px solid #ccc;">';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_date_created()->date('Y-m-d')) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html($order->get_payment_method_title()) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">' . wp_kses_post(wc_price($additional_payments)) . '</td>';
                echo '<td style="padding: 10px; text-align: center;">0.00</td>';
                echo '<td style="padding: 10px; text-align: center;">' . esc_html__('Completed', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</td>';
                echo '</tr>';
            }

            $count++;
        }

        echo '</tbody></table>';
        echo '</div>';
    // Add JavaScript to move payment history to desired location
    echo '<script>
        jQuery(document).ready(function($) {
            $("#payment-history").insertAfter("#woocommerce-order-items");
        });
    </script>';
    }
}
add_action('woocommerce_admin_order_data_after_order_details', 'display_payment_history_on_admin_order', 10, 1);






/**
 * Set a transient on plugin activation to trigger the redirect.
 */
function mepp_set_activation_redirect() {
    set_transient('mepp_activation_redirect', true, 30);
}
add_action('activate_advanced-partial-payment-or-deposit-for-woocommerce/advanced-partial-payment-or-deposit-for-woocommerce.php', 'mepp_set_activation_redirect');

/**
 * Redirect to plugin settings page after activation.
 */
function mepp_redirect_to_settings_page() {
    // Check if the transient is set
    if (get_transient('mepp_activation_redirect')) {
        // Delete the transient to prevent multiple redirects
        delete_transient('mepp_activation_redirect');
        
        // Check if the user has permission to access the settings page
        if (current_user_can('manage_options')) {
            // Redirect to the plugin settings page
            wp_safe_redirect(admin_url('admin.php?page=admin-mepp-deposits'));
            exit;
        }
    }
}
add_action('admin_init', 'mepp_redirect_to_settings_page');




// Add "Pay Deposit" button next to "Add to Cart" button on product list.
add_action('woocommerce_after_shop_loop_item', 'add_pay_deposit_button', 9);

function add_pay_deposit_button() {
    global $product;

    // Check if mepp_storewide_deposit_enabled_btn is set to yes
    $storewide_deposit_btn_enabled = get_option('mepp_storewide_deposit_enabled_btn', 'no');

    // Check if deposits are enabled for the product and mepp_storewide_deposit_enabled_btn is yes
    if (mepp_is_product_deposit_enabled($product->get_id()) && $storewide_deposit_btn_enabled === 'yes') {
        echo '<a href="' . esc_url(get_permalink($product->get_id())) . '" class="button pay-deposit-button">' . __('Pay Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce') . '</a>';
    }
}

// Hook into the action that handles completion of the second payment
add_action('woocommerce_order_status_completed', 'update_main_order_status_on_second_payment_completion', 10, 1);

function update_main_order_status_on_second_payment_completion($order_id) {
    $order = wc_get_order($order_id);

    // Check if it's a child order (second payment)
    if ($order && $order->get_parent_id() > 0) {
        $parent_order_id = $order->get_parent_id();
        $parent_order = wc_get_order($parent_order_id);

        // Check if the parent order exists and if it's partially paid
        if ($parent_order && $parent_order->get_status() === 'partially-paid') {
            $child_orders = wc_get_orders(array(
                'parent' => $parent_order_id,
                'type' => 'mepp_payment',
                'status' => array('completed', 'processing', 'on-hold', 'pending')
            ));

            $total_paid = 0;

            foreach ($child_orders as $child_order) {
                $total_paid += $child_order->get_total();
            }

            // Compare the total amount of child orders with the total amount of the parent order
            if ($total_paid >= $parent_order->get_total()) {
                $parent_order->update_status('completed');
            }
        }
    }
}

/**
 * Add partially paid status option to event plugin's filter
 */
function add_partially_paid_status_option($name) {
    $new_name = array(
        'partially-paid'  => esc_html__( 'Partially Paid', 'tour-booking-manager' ),
     );
     return array_merge($name, $new_name);
}
add_filter('mep_event_seat_status_name_partial', 'add_partially_paid_status_option');

add_action('init', 'register_mepp_payment_post_type', 6);
/**
         * Register mepp_payment custom order type
         * @return void
         */
        function register_mepp_payment_post_type()
        {

            if (!function_exists('wc_register_order_type')) return;
            wc_register_order_type(
                'mepp_payment',

                array(
                    // register_post_type() params
                    'labels' => array(
                        'name' => esc_html__('Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'singular_name' => esc_html__('Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'edit_item' => esc_html_x('Edit Partial Payment', 'custom post type setting', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'search_items' => esc_html__('Search Partial Payments', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'parent' => esc_html_x('Order', 'custom post type setting', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                        'menu_name' => esc_html__('Partial order list', 'advanced-partial-payment-or-deposit-for-woocommerce'),
                    ),
                    'public' => false,
                    'show_ui' => true,
                    'capability_type' => 'shop_order',
                    'capabilities' => array(
                        'create_posts' => 'do_not_allow',
                    ),
                    'map_meta_cap' => true,
                    'publicly_queryable' => false,
                    'exclude_from_search' => true,
                    'show_in_menu' => 'woocommerce',
                    'hierarchical' => false,
                    'show_in_nav_menus' => false,
                    'rewrite' => false,
                    'query_var' => false,
                    'supports' => array('title', 'comments', 'custom-fields'),
                    'has_archive' => false,

                    // wc_register_order_type() params
                    'exclude_from_orders_screen' => true,
                    'add_order_meta_boxes' => true,
                    'exclude_from_order_count' => true,
                    'exclude_from_order_views' => true,
                    'exclude_from_order_webhooks' => true,
    //                    'exclude_from_order_reports' => true,
    //                    'exclude_from_order_sales_reports' => true,
                    'class_name' => 'MEPP_Payment',
                )

            );
        }

        add_action('plugins_loaded', 'ppcp_early_compatibility_register', 9);
        add_action('before_woocommerce_init', 'declare_hpos_compatibility');
       /**
         * Declare compatibility with Custom order tables
         * @return void
         */
        function declare_hpos_compatibility()
        {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }

        /**
         * WooCommerce PayPal Payments trigger the container action at plugins_loaded, so we need to register the hookcall back early as well
         * @return void
         */
        function ppcp_early_compatibility_register()
        {
            if (is_plugin_active('woocommerce-paypal-payments/woocommerce-paypal-payments.php')) {
                $this->compatibility->wc_ppcp = require_once('inc/compatibility/mepp-ppcp-compatibility.php');
            }
        }

        /**
 * Checks installed WC version against minimum version required
 * @return void
 */
function check_version_disable()
{
    if (function_exists('WC') && version_compare(WC()->version, '3.7.0', '<')) {

        $this->wc_version_disabled = true;

        if (is_admin()) {
            // translators: %1$s is a placeholder for the plugin name, %2$s is a placeholder for the required WooCommerce version
            $message = sprintf(esc_html__('%1$s Requires WooCommerce version %2$s or higher.', 'advanced-partial-payment-or-deposit-for-woocommerce'), esc_html__('WooCommerce Deposits', 'advanced-partial-payment-or-deposit-for-woocommerce'), '3.7.0');
            add_action('admin_notices', function() use ($message) {
                echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
            });
        }
    }
}     
add_action('init','check_version_disable', 0);
/**
 * @return mixed
 */
function mepp_deposit_breakdown_tooltip()
{

    $display_tooltip = get_option('mepp_breakdown_cart_tooltip') === 'yes';


    $tooltip_html = '';

    if ($display_tooltip && isset(WC()->cart->deposit_info['deposit_breakdown']) && is_array(WC()->cart->deposit_info['deposit_breakdown'])) {

        $labels = apply_filters('mepp_deposit_breakdown_tooltip_labels', array(
            'cart_items' => esc_html__('Cart items', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'fees' => esc_html__('Fees', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'taxes' => esc_html__('Tax', 'advanced-partial-payment-or-deposit-for-woocommerce'),
            'shipping' => esc_html__('Shipping', 'advanced-partial-payment-or-deposit-for-woocommerce'),
        ));

        $deposit_breakdown = WC()->cart->deposit_info['deposit_breakdown'];
        $tip_information = '<ul>';
        foreach ($deposit_breakdown as $component_key => $component) {

            if (!isset($labels[$component_key])) continue;
            if ($component === 0) {
                continue;
            }
            switch ($component_key) {
                case 'cart_items' :
                    $tip_information .= '<li>' . $labels['cart_items'] . ' : ' . wc_price($component) . '</li>';

                    break;
                case 'fees' :
                    $tip_information .= '<li>' . $labels['fees'] . ' : ' . wc_price($component) . '</li>';
                    break;
                case 'taxes' :
                    $tip_information .= '<li>' . $labels['taxes'] . ' : ' . wc_price($component) . '</li>';

                    break;
                case 'shipping' :
                    $tip_information .= '<li>' . $labels['shipping'] . ' : ' . wc_price($component) . '</li>';

                    break;

                default :
                    break;
            }
        }

        $tip_information .= '</ul>';

        $tooltip_html = '<span id="deposit-help-tip" data-tip="' . esc_attr($tip_information) . '">&#63;</span>';
    }

    return apply_filters('woocommerce_deposits_tooltip_html', $tooltip_html);
}


/** http://jaspreetchahal.org/how-to-lighten-or-darken-hex-or-rgb-color-in-php-and-javascript/
 * @param $color_code
 * @param int $percentage_adjuster
 * @return array|string
 * @author Jaspreet Chahal
 */
function mepp_adjust_colour($color_code, $percentage_adjuster = 0)
{
    $percentage_adjuster = round($percentage_adjuster / 100, 2);

    if (is_array($color_code)) {
        $r = $color_code["r"] - (round($color_code["r"]) * $percentage_adjuster);
        $g = $color_code["g"] - (round($color_code["g"]) * $percentage_adjuster);
        $b = $color_code["b"] - (round($color_code["b"]) * $percentage_adjuster);

        $adjust_color = array("r" => round(max(0, min(255, $r))),
            "g" => round(max(0, min(255, $g))),
            "b" => round(max(0, min(255, $b))));
    } elseif (preg_match("/#/", $color_code)) {
        $hex = str_replace("#", "", $color_code);
        $r = (strlen($hex) == 3) ? hexdec(substr($hex, 0, 1) . substr($hex, 0, 1)) : hexdec(substr($hex, 0, 2));
        $g = (strlen($hex) == 3) ? hexdec(substr($hex, 1, 1) . substr($hex, 1, 1)) : hexdec(substr($hex, 2, 2));
        $b = (strlen($hex) == 3) ? hexdec(substr($hex, 2, 1) . substr($hex, 2, 1)) : hexdec(substr($hex, 4, 2));
        $r = round($r - ($r * $percentage_adjuster));
        $g = round($g - ($g * $percentage_adjuster));
        $b = round($b - ($b * $percentage_adjuster));

        $adjust_color = "#" . str_pad(dechex(max(0, min(255, $r))), 2, "0", STR_PAD_LEFT)
            . str_pad(dechex(max(0, min(255, $g))), 2, "0", STR_PAD_LEFT)
            . str_pad(dechex(max(0, min(255, $b))), 2, "0", STR_PAD_LEFT);

    } else {
        $adjust_color = new WP_Error('', 'Invalid Color format');
    }


    return $adjust_color;
}

/**
 * @brief returns the frontend colours from the WooCommerce settings page, or the defaults.
 *
 * @return array
 */

function mepp_woocommerce_frontend_colours()
{
    $colors = (array)get_option('woocommerce_colors');
    if (empty($colors['primary']))
        $colors['primary'] = '#ad74a2';
    if (empty($colors['secondary']))
        $colors['secondary'] = '#f7f6f7';
    if (empty($colors['highlight']))
        $colors['highlight'] = '#85ad74';
    if (empty($colors['content_bg']))
        $colors['content_bg'] = '#ffffff';
    return $colors;
}


/**
 * @return bool
 */
function mepp_checkout_mode()
{

    return get_option('mepp_checkout_mode_enabled') === 'yes';
}

/**
 * @param $product
 * @return float
 */
function mepp_calculate_product_deposit($product)
{


    $deposit_enabled = mepp_is_product_deposit_enabled($product->get_id());
    $product_type = $product->get_type();
    if ($deposit_enabled) {


        $deposit = mepp_get_product_deposit_amount($product->get_id());
        $amount_type = mepp_get_product_deposit_amount_type($product->get_id());


        $woocommerce_prices_include_tax = get_option('woocommerce_prices_include_tax');

        if ($woocommerce_prices_include_tax === 'yes') {

            $amount = wc_get_price_including_tax($product);

        } else {
            $amount = wc_get_price_excluding_tax($product);

        }

        switch ($product_type) {


            case 'subscription' :
                if (class_exists('WC_Subscriptions_Product')) {

                    $amount = \WC_Subscriptions_Product::get_sign_up_fee($product);
                    if ($amount_type === 'fixed') {
                    } else {
                        $deposit = $amount * ($deposit / 100.0);
                    }

                }
                break;
            case 'yith_bundle' :
                $amount = $product->price_per_item_tot;
                if ($amount_type === 'fixed') {
                } else {
                    $deposit = $amount * ($deposit / 100.0);
                }
                break;
            case 'variable' :

                if ($amount_type === 'fixed') {
                } else {
                    $deposit = $amount * ($deposit / 100.0);
                }
                break;

            default:


                if ($amount_type !== 'fixed') {

                    $deposit = $amount * ($deposit / 100.0);
                }

                break;
        }

        return floatval($deposit);
    }
}

/**
 * @brief checks if deposit is enabled for product
 * @param $product_id
 * @return mixed
 */
function mepp_is_product_deposit_enabled($product_id)
{
    $enabled = false;
    $product = wc_get_product($product_id);
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $enabled = get_option('mepp_storewide_deposit_enabled', 'no') === 'yes';
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $enabled = $product->get_meta('_mepp_enable_deposit', true) === 'yes';
                } else {
                    $enabled = $parent->get_meta('_mepp_enable_deposit', true) === 'yes';
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $enabled = get_option('mepp_storewide_deposit_enabled', 'no') === 'yes';
            } else {
                $enabled = $product->get_meta('_mepp_enable_deposit', true) === 'yes';
            }
        }


    }

    return apply_filters('mepp_product_enable_deposit', $enabled, $product_id);

}

function mepp_is_product_deposit_forced($product_id)
{
    $forced = false;
    $product = wc_get_product($product_id);
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $forced = get_option('mepp_storewide_deposit_force_deposit', 'no') === 'yes';
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $forced = $product->get_meta('_mepp_force_deposit', true) === 'yes';
                } else {
                    $forced = $parent->get_meta('_mepp_force_deposit', true) === 'yes';
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $forced = get_option('mepp_storewide_deposit_force_deposit', 'no') === 'yes';
            } else {
                $forced = $product->get_meta('_mepp_force_deposit', true) === 'yes';
            }
        }
    }

    return apply_filters('mepp_product_force_deposit', $forced, $product_id);

}

function mepp_get_product_deposit_amount($product_id)
{
    $amount = false;
    $product = wc_get_product($product_id);

    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount = get_option('mepp_storewide_deposit_amount', '50');
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $amount = $product->get_meta('_mepp_deposit_amount', true);
                } else {
                    $amount = $parent->get_meta('_mepp_deposit_amount', true);
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount = get_option('mepp_storewide_deposit_amount', '50');
            } else {
                $amount = $product->get_meta('_mepp_deposit_amount', true);
            }
        }
    }


    return apply_filters('mepp_product_deposit_amount', $amount, $product_id);

}

function mepp_get_product_deposit_amount_type($product_id)
{

    $amount_type = false;
    $product = wc_get_product($product_id);


    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount_type = get_option('mepp_storewide_deposit_amount_type', 'percent');
            } else {

                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';

                if ($override) {
                    $amount_type = $product->get_meta('_mepp_amount_type', true);
                } else {
                    $amount_type = $parent->get_meta('_mepp_amount_type', true);
                }
            }


        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');
            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $amount_type = get_option('mepp_storewide_deposit_amount_type', 'percent');
            } else {
                $amount_type = $product->get_meta('_mepp_amount_type', true);
            }
        }
    }
    return apply_filters('mepp_product_deposit_amount_type', $amount_type, $product_id);
}


function mepp_get_product_available_plans($product_id)
{
    $product = wc_get_product($product_id);
    $plans = array();
    if ($product) {

        // if it is a variation , check variation directly
        if ($product->get_type() === 'variation') {
            $parent_id = $product->get_parent_id();
            $parent = wc_get_product($parent_id);
            $inherit = $parent->get_meta('_mepp_inherit_storewide_settings');

            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $plans = get_option('mepp_storewide_deposit_payment_plans', array());
            } else {
                $override = $product->get_meta('_mepp_override_product_settings', true) === 'yes';
                if ($override) {
                    $plans = $product->get_meta('_mepp_payment_plans', true);
                } else {
                    $plans = $parent->get_meta('_mepp_payment_plans', true);
                }
            }

        } else {

            $inherit = $product->get_meta('_mepp_inherit_storewide_settings');
            if (empty($inherit) || $inherit === 'yes') {
                // get global setting
                $plans = get_option('mepp_storewide_deposit_payment_plans', array());
            } else {
                $plans = $product->get_meta('_mepp_payment_plans', true);
            }
        }
    }

    return apply_filters('mepp_product_deposit_available_plans', $plans, $product_id);
}

function mepp_delete_current_schedule($order)
{

    $payments = mepp_get_order_partial_payments($order->get_id(), [], false);
    foreach ($payments as $payment) {
        wp_delete_post(absint($payment), true);
    }

    $order->delete_meta_data('_mepp_payment_schedule');
    $order->save();

}


function mepp_create_payment_schedule($order, $sorted_schedule = array())
{

    /**   START BUILD PAYMENT SCHEDULE**/
    try {

        //fix wpml language
        $wpml_lang = $order->get_meta('wpml_language', true);
        $partial_payments_structure = apply_filters('mepp_partial_payments_structure', get_option('mepp_partial_payments_structure', 'single'), 'order');

        foreach ($sorted_schedule as $partial_key => $payment) {

            $partial_payment = new MEPP_Payment();


            //migrate all fields from parent order


            $partial_payment->set_customer_id($order->get_user_id());


            if ($partial_payments_structure === 'single') {
                $amount = $payment['total'];
                //allow partial payments to be inserted only as a single fee without item details
                $name = esc_html__('Partial Payment for order %s', 'advanced-partial-payment-or-deposit-for-woocommerce');
                $partial_payment_name = apply_filters('mepp_partial_payment_name', sprintf($name, $order->get_order_number()), $payment, $order->get_id());


                $item = new WC_Order_Item_Fee();
                $item->set_props(
                    array(
                        'total' => $amount
                    )
                );
                $item->set_name($partial_payment_name);
                $partial_payment->add_item($item);
                $partial_payment->set_total($amount);


            } else {
                MEPP_Advance_Deposits_Admin_order::create_partial_payment_items($partial_payment, $order, $payment['details']);
                $partial_payment->save();
//                $partial_payment->recalculate_coupons();
                $partial_payment->calculate_totals(false);
                $partial_payment->add_meta_data('_mepp_partial_payment_itemized', 'yes');

            }
            $is_vat_exempt = $order->get_meta('is_vat_exempt', true);

            if (isset($payment['timestamp']) && is_numeric($payment['timestamp'])) {
                $partial_payment->add_meta_data('_mepp_partial_payment_date', $payment['timestamp']);
            }

            if (isset($payment['details'])) {
                $partial_payment->add_meta_data('_mepp_partial_payment_details', $payment['details']);
            }
            $partial_payment->set_parent_id($order->get_id());
            $partial_payment->add_meta_data('is_vat_exempt', $is_vat_exempt);
            $partial_payment->add_meta_data('_mepp_payment_type', $payment['type'], true);
            $partial_payment->set_currency($order->get_currency());
            $partial_payment->set_prices_include_tax($order->get_prices_include_tax());
            $partial_payment->set_customer_ip_address($order->get_customer_ip_address());
            $partial_payment->set_customer_user_agent($order->get_customer_user_agent());

            if ($order->get_status() === 'partially-paid' && $payment['type'] === 'deposit') {

                //we need to save to generate id first
                $partial_payment->set_status('completed');

            }

            if (!empty($wpml_lang)) {
                $partial_payment->update_meta_data('wpml_language', $wpml_lang);
            }


            if (floatval($partial_payment->get_total()) == 0.0) $partial_payment->set_status('completed');

            $partial_payment->save();
            do_action('mepp_partial_payment_created', $partial_payment->get_id(), 'backend');

            $sorted_schedule[$partial_key]['id'] = $partial_payment->get_id();

        }
        return $sorted_schedule;
    } catch (\Exception $e) {
        print_r(new WP_Error('error', $e->getMessage()));
    }

}

function mepp_get_order_partial_payments($order_id, $args = array(), $object = true)
{
    $default_args = array(
        'parent' => $order_id,
        'type' => 'mepp_payment',
        'limit' => -1,
        'status' => array_keys(wc_get_order_statuses())
    );

    $args = ($args) ? wp_parse_args($args, $default_args) : $default_args;

    $orders = array();

    //get children of order
    $partial_payments = wc_get_orders($args);
    foreach ($partial_payments as $partial_payment) {
        $orders[] = ($object) ? wc_get_order($partial_payment->get_id()) : $partial_payment->get_id();
    }
    return $orders;
}

add_action('woocommerce_after_dashboard_status_widget', 'mepp_status_widget_partially_paid');
function mepp_status_widget_partially_paid()
{
    if (!current_user_can('edit_shop_orders')) {
        return;
    }
    $partially_paid_count = 0;
    foreach (wc_get_order_types('order-count') as $type) {
        $counts = (array)wp_count_posts($type);
        $partially_paid_count += isset($counts['wc-partially-paid']) ? $counts['wc-partially-paid'] : 0;
    }
    ?>
    <li class="partially-paid-orders">
        <a href="<?php echo admin_url('edit.php?post_status=wc-partially-paid&post_type=shop_order'); ?>">
            <?php
            printf(
                _n('<strong>%s order</strong> partially paid', '<strong>%s orders</strong> partially paid', $partially_paid_count, 'advanced-partial-payment-or-deposit-for-woocommerce'),
                $partially_paid_count
            );
            ?>
        </a>
    </li>
    <style>
        #woocommerce_dashboard_status .wc_status_list li.partially-paid-orders a::before {
            content: '\e011';
            color: #ffba00;}
    </style>
    <?php
}

function mepp_remove_order_deposit_data($order)
{

    $order->delete_meta_data('_mepp_order_version');
    $order->delete_meta_data('_mepp_order_has_deposit');
    $order->delete_meta_data('_mepp_deposit_paid');
    $order->delete_meta_data('_mepp_second_payment_paid');
    $order->delete_meta_data('_mepp_deposit_amount');
    $order->delete_meta_data('_mepp_second_payment');
    $order->delete_meta_data('_mepp_deposit_breakdown');
    $order->delete_meta_data('_mepp_deposit_payment_time');
    $order->delete_meta_data('_mepp_second_payment_reminder_email_sent');
    $order->save();

}

function mepp_validate_customer_eligibility($enabled)
{
    //user restriction
    $allow_deposit_for_guests = get_option('mepp_restrict_deposits_for_logged_in_users_only', 'no');

    if ($allow_deposit_for_guests !== 'no' && is_user_logged_in() && isset($_POST['createaccount']) && $_POST['createaccount'] == 1) {
        //account created during checkout
        $enabled = false;

    } elseif (is_user_logged_in()) {

        $disabled_user_roles = get_option('mepp_disable_deposit_for_user_roles', array());
        if (!empty($disabled_user_roles)) {

            foreach ($disabled_user_roles as $disabled_user_role) {

                if (wc_current_user_has_role($disabled_user_role)) {

                    $enabled = false;
                }
            }
        }
    } else {
        if ($allow_deposit_for_guests !== 'no') {
            $enabled = false;
        }
    }

    return $enabled;
}

add_filter('mepp_deposit_enabled_for_customer', 'mepp_validate_customer_eligibility');

function mepp_valid_parent_statuses_for_partial_payment()
{
    return apply_filters('mepp_valid_parent_statuses_for_partial_payment', array('partially-paid'));
}

function mepp_partial_payment_complete_order_status()
{
    return apply_filters('mepp_partial_payment_complete_order_status', 'partially-paid');
}

function MEPP()
{
    return \MagePeople\MEPP\MEPP_Advance_Deposits::get_singleton();
}

add_action('mepp_job_scheduler', 'mepp_backward_compatibility_cron_trigger');
function mepp_backward_compatibility_cron_trigger()
{
    do_action('woocommerce_deposits_second_payment_reminder');
}


function mepp_is_mepp_payment_screen()
{


    if (!function_exists('get_current_screen')) return false;
    $screen = get_current_screen();

    $hpos_enabled = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    $screen_name = $hpos_enabled && function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('mepp_payment') : 'mepp_payment';
    return $screen->id == $screen_name;

}


function mepp_is_shop_order_screen()
{


    if (!function_exists('get_current_screen')) return false;
    $screen = get_current_screen();

    $hpos_enabled = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    $screen_name = $hpos_enabled && function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';

    return $screen->id == $screen_name;
}