<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Category deposit meta fields.
 */
class APD_Category_Meta {

    public function __construct() {
        add_action( 'product_cat_add_form_fields', array( $this, 'add_category_fields' ) );
        add_action( 'product_cat_edit_form_fields', array( $this, 'edit_category_fields' ), 10, 1 );
        add_action( 'created_product_cat', array( $this, 'save_category_meta' ), 10, 1 );
        add_action( 'edited_product_cat', array( $this, 'save_category_meta' ), 10, 1 );
    }

    /**
     * Add fields to new category page.
     */
    public function add_category_fields() {
        ?>
        <div class="form-field">
            <label><?php esc_html_e( 'Enable Deposit', 'advanced-partial-payment' ); ?></label>
            <select name="_apd_enable_deposit">
                <option value=""><?php esc_html_e( 'Use Global Setting', 'advanced-partial-payment' ); ?></option>
                <option value="yes"><?php esc_html_e( 'Yes', 'advanced-partial-payment' ); ?></option>
                <option value="no"><?php esc_html_e( 'No', 'advanced-partial-payment' ); ?></option>
            </select>
        </div>
        <div class="form-field">
            <label><?php esc_html_e( 'Force Deposit Only', 'advanced-partial-payment' ); ?></label>
            <select name="_apd_force_deposit">
                <option value=""><?php esc_html_e( 'Use Global Setting', 'advanced-partial-payment' ); ?></option>
                <option value="yes"><?php esc_html_e( 'Yes', 'advanced-partial-payment' ); ?></option>
                <option value="no"><?php esc_html_e( 'No', 'advanced-partial-payment' ); ?></option>
            </select>
            <p class="description"><?php esc_html_e( 'Force products in this category to show only the deposit/partial payment option.', 'advanced-partial-payment' ); ?></p>
        </div>
        <div class="form-field">
            <label><?php esc_html_e( 'Deposit Type', 'advanced-partial-payment' ); ?></label>
            <select name="_apd_deposit_type">
                <option value="global"><?php esc_html_e( 'Use Global Setting', 'advanced-partial-payment' ); ?></option>
                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'advanced-partial-payment' ); ?></option>
                <option value="percentage"><?php esc_html_e( 'Percentage', 'advanced-partial-payment' ); ?></option>
            </select>
        </div>
        <div class="form-field">
            <label><?php esc_html_e( 'Deposit Value', 'advanced-partial-payment' ); ?></label>
            <input type="number" step="0.01" min="0" name="_apd_deposit_value" placeholder="<?php esc_attr_e( 'e.g. 50', 'advanced-partial-payment' ); ?>" />
        </div>
        <?php $this->render_payment_plan_add_fields(); ?>
        <?php
    }

    /**
     * Edit fields on category edit page.
     */
    public function edit_category_fields( $term ) {
        $enable = get_term_meta( $term->term_id, '_apd_enable_deposit', true );
        $force  = get_term_meta( $term->term_id, '_apd_force_deposit', true );
        $type   = get_term_meta( $term->term_id, '_apd_deposit_type', true );
        $value  = get_term_meta( $term->term_id, '_apd_deposit_value', true );
        ?>
        <tr class="form-field">
            <th><label><?php esc_html_e( 'Enable Deposit', 'advanced-partial-payment' ); ?></label></th>
            <td>
                <select name="_apd_enable_deposit">
                    <option value="" <?php selected( $enable, '' ); ?>><?php esc_html_e( 'Use Global Setting', 'advanced-partial-payment' ); ?></option>
                    <option value="yes" <?php selected( $enable, 'yes' ); ?>><?php esc_html_e( 'Yes', 'advanced-partial-payment' ); ?></option>
                    <option value="no" <?php selected( $enable, 'no' ); ?>><?php esc_html_e( 'No', 'advanced-partial-payment' ); ?></option>
                </select>
            </td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e( 'Force Deposit Only', 'advanced-partial-payment' ); ?></label></th>
            <td>
                <select name="_apd_force_deposit">
                    <option value="" <?php selected( $force, '' ); ?>><?php esc_html_e( 'Use Global Setting', 'advanced-partial-payment' ); ?></option>
                    <option value="yes" <?php selected( $force, 'yes' ); ?>><?php esc_html_e( 'Yes', 'advanced-partial-payment' ); ?></option>
                    <option value="no" <?php selected( $force, 'no' ); ?>><?php esc_html_e( 'No', 'advanced-partial-payment' ); ?></option>
                </select>
                <p class="description"><?php esc_html_e( 'Force products in this category to show only the deposit/partial payment option.', 'advanced-partial-payment' ); ?></p>
            </td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e( 'Deposit Type', 'advanced-partial-payment' ); ?></label></th>
            <td>
                <select name="_apd_deposit_type">
                    <option value="global" <?php selected( $type, 'global' ); ?>><?php esc_html_e( 'Use Global Setting', 'advanced-partial-payment' ); ?></option>
                    <option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount', 'advanced-partial-payment' ); ?></option>
                    <option value="percentage" <?php selected( $type, 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'advanced-partial-payment' ); ?></option>
                </select>
            </td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e( 'Deposit Value', 'advanced-partial-payment' ); ?></label></th>
            <td>
                <input type="number" step="0.01" min="0" name="_apd_deposit_value" value="<?php echo esc_attr( $value ); ?>" />
            </td>
        </tr>
        <?php $this->render_payment_plan_edit_fields( $term ); ?>
        <?php
    }

    /**
     * Save category deposit meta.
     */
    public function save_category_meta( $term_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_product_terms' ) ) {
            return;
        }

        $fields = array( '_apd_enable_deposit', '_apd_force_deposit', '_apd_deposit_type', '_apd_deposit_value' );
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_term_meta( $term_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }

        if ( apd_is_pro_active() && class_exists( 'APD_Payment_Plans' ) ) {
            $assigned_plans = isset( $_POST['_apd_assigned_plans'] )
                ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['_apd_assigned_plans'] ) )
                : array();

            update_term_meta( $term_id, '_apd_assigned_plans', array_values( array_unique( $assigned_plans ) ) );
        }
    }

    /**
     * Render payment-plan assignment on the category add screen when Pro is active.
     */
    private function render_payment_plan_add_fields() {
        if ( ! apd_is_pro_active() || ! class_exists( 'APD_Payment_Plans' ) ) {
            return;
        }

        $plans = APD_Payment_Plans::get_plans();
        ?>
        <div class="form-field">
            <label><?php esc_html_e( 'Assigned Payment Plans', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' ); ?></label>
            <div style="max-height:220px;overflow:auto;padding:10px 12px;border:1px solid #dcdcde;border-radius:4px;background:#fff;">
                <?php if ( ! empty( $plans ) ) : ?>
                    <?php foreach ( $plans as $plan_id => $plan ) : ?>
                        <label style="display:block;margin:0 0 8px;">
                            <input type="checkbox" name="_apd_assigned_plans[]" value="<?php echo esc_attr( $plan_id ); ?>" />
                            <strong><?php echo esc_html( $plan['name'] ?? $plan_id ); ?></strong>
                            <span style="color:#646970;">
                                <?php
                                printf(
                                    '(%1$s, %2$d %3$s)',
                                    esc_html( ucfirst( $plan['price_type'] ?? 'percentage' ) ),
                                    ! empty( $plan['installments'] ) && is_array( $plan['installments'] ) ? count( $plan['installments'] ) : 0,
                                    esc_html__( 'installments', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' )
                                );
                                ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="description"><?php esc_html_e( 'No payment plans found yet. Create plans from Deposits > Payment Plans.', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' ); ?></p>
                <?php endif; ?>
            </div>
            <p class="description"><?php esc_html_e( 'Optional Pro override. If no product-level plans are assigned, these category plans will be used for products in this category.', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' ); ?></p>
        </div>
        <?php
    }

    /**
     * Render payment-plan assignment on the category edit screen when Pro is active.
     *
     * @param WP_Term $term Category term.
     */
    private function render_payment_plan_edit_fields( $term ) {
        if ( ! apd_is_pro_active() || ! class_exists( 'APD_Payment_Plans' ) ) {
            return;
        }

        $plans    = APD_Payment_Plans::get_plans();
        $assigned = (array) get_term_meta( $term->term_id, '_apd_assigned_plans', true );
        ?>
        <tr class="form-field">
            <th><label><?php esc_html_e( 'Assigned Payment Plans', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' ); ?></label></th>
            <td>
                <div style="max-height:240px;overflow:auto;padding:10px 12px;border:1px solid #dcdcde;border-radius:4px;background:#fff;">
                    <?php if ( ! empty( $plans ) ) : ?>
                        <?php foreach ( $plans as $plan_id => $plan ) : ?>
                            <label style="display:block;margin:0 0 8px;">
                                <input
                                    type="checkbox"
                                    name="_apd_assigned_plans[]"
                                    value="<?php echo esc_attr( $plan_id ); ?>"
                                    <?php checked( in_array( $plan_id, $assigned, true ) ); ?>
                                />
                                <strong><?php echo esc_html( $plan['name'] ?? $plan_id ); ?></strong>
                                <span style="color:#646970;">
                                    <?php
                                    printf(
                                        '(%1$s, %2$d %3$s)',
                                        esc_html( ucfirst( $plan['price_type'] ?? 'percentage' ) ),
                                        ! empty( $plan['installments'] ) && is_array( $plan['installments'] ) ? count( $plan['installments'] ) : 0,
                                        esc_html__( 'installments', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' )
                                    );
                                    ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="description"><?php esc_html_e( 'No payment plans found yet. Create plans from Deposits > Payment Plans.', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' ); ?></p>
                    <?php endif; ?>
                </div>
                <p class="description"><?php esc_html_e( 'Optional Pro override. Product-specific plan assignments take priority over category plan assignments.', 'advanced-partial-payment-or-deposit-for-woocommerce-pro' ); ?></p>
            </td>
        </tr>
        <?php
    }
}
