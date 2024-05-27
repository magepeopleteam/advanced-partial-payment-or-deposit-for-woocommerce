<?php

namespace MagePeople\MEPP;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'MEPP_Admin_List_Table_Partial_Payments', false ) ) {
    return;
}


if(!function_exists('WC')) return;  // too early load
if ( ! class_exists( 'WC_Admin_List_Table', false ) ) {
    include_once WC()->plugin_path().'/inc/admin/abstract-class-wc-admin-list-table.php';
}
use WC_Admin_List_Table;

/**
 * MEPP_Admin_List_Table_Partial_Payments Class.
 */
class MEPP_Admin_List_Table_Partial_Payments extends WC_Admin_List_Table {

    /**
     * Post type.
     *
     * @var string
     */
    protected $list_table_type = 'mepp_payment';

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct();
        add_action( 'admin_notices', array( $this, 'bulk_admin_notices' ) );
        add_filter( 'get_search_query', array( $this, 'search_label' ) );
        add_filter( 'query_vars', array( $this, 'add_custom_query_var' ) );
        add_action( 'parse_query', array( $this, 'search_custom_fields' ) );
    }
    /**
     *   Render blank state.
     * @return void
     */
    protected function render_blank_state() {
        echo '<div class="woocommerce-BlankState">';

        echo '<h2 class="woocommerce-BlankState-message">' . esc_html__( 'No partial payments found.', 'advanced-partial-payment-or-deposit-for-woocommerce' ) . '</h2>';

        echo '</div>';
    }
    /**
     *   Define primary column.
     *
     * @return string
     */
    protected function get_primary_column() {
        return 'order_number';
    }

    /**
     *  Get row actions to show in the list table.
     *
     * @param array   $actions Array of actions.
     * @param \WP_Post $post Current post object.
     * @return array
     */
    protected function get_row_actions( $actions, $post ) {
        return array();
    }

    /**
     *  Define hidden columns.
     *
     * @return array
     */
    protected function define_hidden_columns() {
        return array(
            'shipping_address',
            'billing_address',
            'wc_actions',
        );
    }

    /**
     *  Define which columns are sortable.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function define_sortable_columns( $columns ) {
        $custom = array(
            'order_number' => 'ID',
            'order_total'  => 'order_total',
            'order_date'   => 'date',
        );
        unset( $columns['comments'] );

        return wp_parse_args( $custom, $columns );
    }

    /**
     *  Define which columns to show on this screen.
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function define_columns( $columns ) {
        $show_columns                     = array();
        $show_columns['cb']               = $columns['cb'];
        $show_columns['order_number']     = esc_html__( 'Partial Payment', 'advanced-partial-payment-or-deposit-for-woocommerce' );
        $show_columns['order_date']       = esc_html__( 'Date', 'woocommerce' );
        $show_columns['order_status']     = esc_html__( 'Status', 'woocommerce' );
        $show_columns['billing_address']  = esc_html__( 'Billing', 'woocommerce' );
        $show_columns['shipping_address'] = esc_html__( 'Ship to', 'woocommerce' );
        $show_columns['order_total']      = esc_html__( 'Total', 'woocommerce' );
        $show_columns['wc_actions']       = esc_html__( 'Actions', 'woocommerce' );
        $show_columns['mepp_parent_order']       = esc_html__( 'Order', 'woocommerce' );

        wp_enqueue_script( 'wc-orders' );

        return $show_columns;
    }
    /**
     *  Define bulk actions.
     *
     * @param array $actions Existing actions.
     * @return array
     */
    public function define_bulk_actions( $actions ) {
        if ( isset( $actions['edit'] ) ) {
            unset( $actions['edit'] );
        }

        $actions['mark_on-hold']    = esc_html__( 'Change status to on-hold', 'woocommerce' );
        $actions['mark_completed']  = esc_html__( 'Change status to completed', 'woocommerce' );

        if ( wc_string_to_bool( get_option( 'woocommerce_allow_bulk_remove_personal_data', 'no' ) ) ) {
            $actions['remove_personal_data'] = esc_html__( 'Remove personal data', 'woocommerce' );
        }

        return $actions;
    }
    /**
     *  Pre-fetch any data for the row each column has access to it. the_order global is there for bw compat.
     *
     * @param int $post_id Post ID being shown.
     */
    protected function prepare_row_data( $post_id ) {
        global $the_order;

        if ( empty( $this->object ) || $this->object->get_id() !== $post_id ) {
            $this->object = wc_get_order( $post_id );
            $the_order    = $this->object;
        }
    }
    /**
     *  Render column: order_number.
     */
    protected function render_order_number_column() {
        $buyer = '';

        if ( $this->object->get_billing_first_name() || $this->object->get_billing_last_name() ) {
            /* translators: 1: first name 2: last name */
            $buyer = trim( sprintf( _x( '%1$s %2$s', 'full name', 'woocommerce' ), $this->object->get_billing_first_name(), $this->object->get_billing_last_name() ) );
        } elseif ( $this->object->get_billing_company() ) {
            $buyer = trim( $this->object->get_billing_company() );
        } elseif ( $this->object->get_customer_id() ) {
            $user  = get_user_by( 'id', $this->object->get_customer_id() );
            $buyer = ucwords( $user->display_name );
        }

        if ( $this->object->get_status() === 'trash' ) {
            echo '<strong>#' . esc_attr( $this->object->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong>';
        } else {
            echo '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $this->object->get_id() ) ) . '&action=edit' ) . '" class="order-view"><strong>#' . esc_attr( $this->object->get_order_number() ) . ' ' . esc_html( $buyer ) . '</strong></a>';
        }
    }
    /**
     *  Render column: order_status.
     */
    protected function render_order_status_column() {
        $tooltip                 = '';
        $comment_count           = get_comment_count( $this->object->get_id() );
        $approved_comments_count = absint( $comment_count['approved'] );

        if ( $approved_comments_count ) {
            $latest_notes = wc_get_order_notes(
                array(
                    'order_id' => $this->object->get_id(),
                    'limit'    => 1,
                    'orderby'  => 'date_created_gmt',
                )
            );

            $latest_note = current( $latest_notes );

            if ( isset( $latest_note->content ) && 1 === $approved_comments_count ) {
                $tooltip = wc_sanitize_tooltip( $latest_note->content );
            } elseif ( isset( $latest_note->content ) ) {
                /* translators: %d: notes count */
                $tooltip = wc_sanitize_tooltip( $latest_note->content . '<br/><small style="display:block">' . sprintf( _n( 'Plus %d other note', 'Plus %d other notes', ( $approved_comments_count - 1 ), 'woocommerce' ), $approved_comments_count - 1 ) . '</small>' );
            } else {
                /* translators: %d: notes count */
                $tooltip = wc_sanitize_tooltip( sprintf( _n( '%d note', '%d notes', $approved_comments_count, 'woocommerce' ), $approved_comments_count ) );
            }
        }

        if ( $tooltip ) {
            printf( '<mark class="order-status %s tips" data-tip="%s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $this->object->get_status() ) ), wp_kses_post( $tooltip ), esc_html( wc_get_order_status_name( $this->object->get_status() ) ) );
        } else {
            printf( '<mark class="order-status %s"><span>%s</span></mark>', esc_attr( sanitize_html_class( 'status-' . $this->object->get_status() ) ), esc_html( wc_get_order_status_name( $this->object->get_status() ) ) );
        }
    }

    /**
     *   Render column: order_date.
     */
    protected function render_order_date_column() {
        $order_timestamp = $this->object->get_date_created() ? $this->object->get_date_created()->getTimestamp() : '';

        if ( ! $order_timestamp ) {
            echo '&ndash;';
            return;
        }

        // Check if the order was created within the last 24 hours, and not in the future.
        if ( $order_timestamp > strtotime( '-1 day', current_time( 'timestamp', true ) ) && $order_timestamp <= current_time( 'timestamp', true ) ) {
            $show_date = sprintf(
            /* translators: %s: human-readable time difference */
                _x( '%s ago', '%s = human-readable time difference', 'woocommerce' ),
                human_time_diff( $this->object->get_date_created()->getTimestamp(), current_time( 'timestamp', true ) )
            );
        } else {
            $show_date = $this->object->get_date_created()->date_i18n( apply_filters( 'woocommerce_admin_order_date_format', esc_html__( 'M j, Y', 'woocommerce' ) ) );
        }
        printf(
            '<time datetime="%1$s" title="%2$s">%3$s</time>',
            esc_attr( $this->object->get_date_created()->date( 'c' ) ),
            esc_html( $this->object->get_date_created()->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ),
            esc_html( $show_date )
        );
    }

    /**
     *   Render column: order_total.
     */
    protected function render_order_total_column() {
        if ( $this->object->get_payment_method_title() ) {
            /* translators: %s: method */
            echo '<span class="tips" data-tip="' . esc_attr( sprintf( esc_html__( 'via %s', 'woocommerce' ), $this->object->get_payment_method_title() ) ) . '">' . wp_kses_post( $this->object->get_formatted_order_total() ) . '</span>';
        } else {
            echo wp_kses_post( $this->object->get_formatted_order_total() );
        }
    }

    /**
     *  Render column: mepp_parent_order.
     */
    protected function render_mepp_parent_order_column() {
        if($this->object->get_parent_id()){
            $parent = wc_get_order($this->object->get_parent_id());
            if($parent){
                echo '<a target="_blank" href="' . esc_url(  $parent->get_edit_order_url() ) . '">#' . $parent->get_order_number() . '</a>';

            }

        }

    }
    /**
     * Render column: wc_actions.
     */
    protected function render_wc_actions_column() {
        echo '<p>';

        do_action( 'woocommerce_admin_order_actions_start', $this->object );

        $actions = array();

        if ( $this->object->has_status( array( 'pending', 'partially-paid', 'processing' ) ) ) {
            $actions['complete'] = array(
                'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $this->object->get_id() ), 'woocommerce-mark-order-status' ),
                'name'   => esc_html__( 'Complete', 'woocommerce' ),
                'action' => 'complete',
            );
        }

        $actions = apply_filters( 'woocommerce_admin_order_actions', $actions, $this->object );

        echo wc_render_action_buttons( $actions ); // WPCS: XSS ok.

        do_action( 'woocommerce_admin_order_actions_end', $this->object );

        echo '</p>';
    }

    /**
     * Render column: billing_address.
     */
    protected function render_billing_address_column() {
        $address = $this->object->get_formatted_billing_address();

        if ( $address ) {
            echo esc_html( preg_replace( '#<br\s*/?>#i', ', ', $address ) );

            if ( $this->object->get_payment_method() ) {
                /* translators: %s: payment method */
                echo '<span class="description">' . sprintf( esc_html__( 'via %s', 'woocommerce' ), esc_html( $this->object->get_payment_method_title() ) ) . '</span>'; // WPCS: XSS ok.
            }
        } else {
            echo '&ndash;';
        }
    }

    /**
     *  Render column: shipping_address.
     */
    protected function render_shipping_address_column() {
        $address = $this->object->get_formatted_shipping_address();

        if ( $address ) {
            echo '<a target="_blank" href="' . esc_url( $this->object->get_shipping_address_map_url() ) . '">' . esc_html( preg_replace( '#<br\s*/?>#i', ', ', $address ) ) . '</a>';
            if ( $this->object->get_shipping_method() ) {
                /* translators: %s: shipping method */
                echo '<span class="description">' . sprintf( esc_html__( 'via %s', 'woocommerce' ), esc_html( $this->object->get_shipping_method() ) ) . '</span>'; // WPCS: XSS ok.
            }
        } else {
            echo '&ndash;';
        }
    }

    /**
     *  Handle bulk actions.
     *
     * @param  string $redirect_to URL to redirect to.
     * @param  string $action      Action name.
     * @param  array  $ids         List of ids.
     * @return string
     */
    public function handle_bulk_actions( $redirect_to, $action, $ids ) {
        $ids     = apply_filters( 'woocommerce_bulk_action_ids', array_reverse( array_map( 'absint', $ids ) ), $action, 'order' );
        $changed = 0;

        if ( 'remove_personal_data' === $action ) {
            $report_action = 'removed_personal_data';

            foreach ( $ids as $id ) {
                $order = wc_get_order( $id );

                if ( $order ) {
                    do_action( 'woocommerce_remove_order_personal_data', $order );
                    $changed++;
                }
            }
        } elseif ( false !== strpos( $action, 'mark_' ) ) {
            $order_statuses = wc_get_order_statuses();
            $new_status     = substr( $action, 5 ); // Get the status name from action.
            $report_action  = 'marked_' . $new_status;

            // Sanity check: bail out if this is actually not a status, or is not a registered status.
            if ( isset( $order_statuses[ 'wc-' . $new_status ] ) ) {
                // Initialize payment gateways in case order has hooked status transition actions.
                WC()->payment_gateways();

                foreach ( $ids as $id ) {
                    $order = wc_get_order( $id );
                    $order->update_status( $new_status, esc_html__( 'Order status changed by bulk edit:', 'woocommerce' ), true );
                    do_action( 'woocommerce_order_edit_status', $id, $new_status );
                    $changed++;
                }
            }
        }

        if ( $changed ) {
            $redirect_to = add_query_arg(
                array(
                    'post_type'   => $this->list_table_type,
                    'bulk_action' => $report_action,
                    'changed'     => $changed,
                    'ids'         => join( ',', $ids ),
                ),
                $redirect_to
            );
        }

        return esc_url_raw( $redirect_to );
    }

    /**
     * Show confirmation message that order status changed for number of orders.
     */
    public function bulk_admin_notices() {
        global $post_type, $pagenow;

        // Bail out if not on shop order list page.
        if ( 'edit.php' !== $pagenow || 'mepp_payment' !== $post_type || ! isset( $_REQUEST['bulk_action'] ) ) { // WPCS: input var ok, CSRF ok.
            return;
        }

        $order_statuses = wc_get_order_statuses();
        $number         = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0; // WPCS: input var ok, CSRF ok.
        $bulk_action    = wc_clean( wp_unslash( $_REQUEST['bulk_action'] ) ); // WPCS: input var ok, CSRF ok.

        // Check if any status changes happened.
        foreach ( $order_statuses as $slug => $name ) {
            if ( 'marked_' . str_replace( 'wc-', '', $slug ) === $bulk_action ) { // WPCS: input var ok, CSRF ok.
                /* translators: %d: orders count */
                $message = sprintf( _n( '%d order status changed.', '%d order statuses changed.', $number, 'woocommerce' ), number_format_i18n( $number ) );
                echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
                break;
            }
        }

        if ( 'removed_personal_data' === $bulk_action ) { // WPCS: input var ok, CSRF ok.
            /* translators: %d: orders count */
            $message = sprintf( _n( 'Removed personal data from %d order.', 'Removed personal data from %d orders.', $number, 'woocommerce' ), number_format_i18n( $number ) );
            echo '<div class="updated"><p>' . esc_html( $message ) . '</p></div>';
        }
    }

    /**
     *  See if we should render search filters or not.
     */
    public function restrict_manage_posts() {
        global $typenow;

        if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true ) ) {
            $this->render_filters();
        }
    }

    /**
     *  Render any custom filters and search inputs for the list table.
     */
    protected function render_filters() {
        $user_string = '';
        $user_id     = '';

        if ( ! empty( $_GET['_customer_user'] ) ) { // phpcs:disable  WordPress.Security.NonceVerification.NoNonceVerification
            $user_id = absint( $_GET['_customer_user'] ); // WPCS: input var ok, sanitization ok.
            $user    = get_user_by( 'id', $user_id );

            $user_string = sprintf(
            /* translators: 1: user display name 2: user ID 3: user email */
                esc_html__( '%1$s (#%2$s &ndash; %3$s)', 'woocommerce' ),
                $user->display_name,
                absint( $user->ID ),
                $user->user_email
            );
        }
        ?>
        <select class="wc-customer-search" name="_customer_user" data-placeholder="<?php esc_attr_e( 'Filter by registered customer', 'woocommerce' ); ?>" data-allow_clear="true">
            <option value="<?php echo esc_attr( $user_id ); ?>" selected="selected"><?php echo htmlspecialchars( wp_kses_post( $user_string ) ); // htmlspecialchars to prevent XSS when rendered by selectWoo. ?><option>
        </select>
        <?php
    }

    /**
     *  Handle any filters.
     *
     * @param array $query_vars Query vars.
     * @return array
     */
    public function request_query( $query_vars ) {
        global $typenow;

        if ( in_array( $typenow, wc_get_order_types( 'order-meta-boxes' ), true ) ) {
            return $this->query_filters( $query_vars );
        }

        return $query_vars;
    }

    /**
     *  Handle any custom filters.
     *
     * @param array $query_vars Query vars.
     * @return array
     */
    protected function query_filters( $query_vars ) {
        global $wp_post_statuses;

        // Filter the orders by the posted customer.
        if ( ! empty( $_GET['_customer_user'] ) ) { // WPCS: input var ok.
            // @codingStandardsIgnoreStart.
            $query_vars['meta_query'] = array(
                array(
                    'key'     => '_customer_user',
                    'value'   => (int) $_GET['_customer_user'], // WPCS: input var ok, sanitization ok.
                    'compare' => '=',
                ),
            );
            // @codingStandardsIgnoreEnd
        }

        // Sorting.
        if ( isset( $query_vars['orderby'] ) ) {
            if ( 'order_total' === $query_vars['orderby'] ) {
                // @codingStandardsIgnoreStart
                $query_vars = array_merge( $query_vars, array(
                    'meta_key'  => '_order_total',
                    'orderby'   => 'meta_value_num',
                ) );
                // @codingStandardsIgnoreEnd
            }
        }

        // Status.
        if ( empty( $query_vars['post_status'] ) ) {
            $post_statuses = wc_get_order_statuses();

            foreach ( $post_statuses as $status => $value ) {
                if ( isset( $wp_post_statuses[ $status ] ) && false === $wp_post_statuses[ $status ]->show_in_admin_all_list ) {
                    unset( $post_statuses[ $status ] );
                }
            }

            $query_vars['post_status'] = array_keys( $post_statuses );
        }
        return $query_vars;
    }

    /**
     *  Change the label when searching orders.
     *
     * @param mixed $query Current search query.
     * @return string
     */
    public function search_label( $query ) {
        global $pagenow, $typenow;

        if ( 'edit.php' !== $pagenow || 'mepp_payment' !== $typenow || ! get_query_var( 'mepp_payment_search' ) || ! isset( $_GET['s'] ) ) { // phpcs:disable  WordPress.Security.NonceVerification.NoNonceVerification
            return $query;
        }

        return wc_clean( wp_unslash( $_GET['s'] ) ); // WPCS: input var ok, sanitization ok.
    }

    /**
     *  Query vars for custom searches.
     *
     * @param mixed $public_query_vars Array of query vars.
     * @return array
     */
    public function add_custom_query_var( $public_query_vars ) {
        $public_query_vars[] = 'mepp_payment_search';
        return $public_query_vars;
    }

    /**
     *  Search custom fields as well as content.
     *
     * @param \WP_Query $wp Query object.
     */
    public function search_custom_fields( $wp ) {
        global $pagenow;

        if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['s'] ) || 'mepp_payment' !== $wp->query_vars['post_type'] || ! isset( $_GET['s'] ) ) { // phpcs:disable  WordPress.Security.NonceVerification.NoNonceVerification
            return;
        }

        $post_ids = wc_order_search( wc_clean( wp_unslash( $_GET['s'] ) ) ); // WPCS: input var ok, sanitization ok.

        if ( ! empty( $post_ids ) ) {
            // Remove "s" - we don't want to search order name.
            unset( $wp->query_vars['s'] );

            // so we know we're doing this.
            $wp->query_vars['mepp_payment_search'] = true;

            // Search by found posts.
            $wp->query_vars['post__in'] = array_merge( $post_ids, array( 0 ) );
        }
    }
}
