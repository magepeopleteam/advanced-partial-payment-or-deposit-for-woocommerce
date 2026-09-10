<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product deposit meta fields on the WooCommerce product edit page.
 */
class APD_Product_Meta {

    public function __construct() {
        // Add deposit tab to product data
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_panel' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
    }

    /**
     * Add "Deposit" tab to product data tabs.
     */
    public function add_product_tab( $tabs ) {
        $tabs['apd_deposit'] = array(
            'label'    => __( 'Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
            'target'   => 'apd_deposit_product_data',
            'class'    => array( 'show_if_simple', 'show_if_variable' ),
            'priority' => 80,
        );
        return $tabs;
    }

    /**
     * Render deposit panel on product edit.
     */
    public function render_product_panel() {
        global $post;
        $product_id = $post->ID;
        $context    = $this->get_product_deposit_context( $product_id );
        ?>
        <div id="apd_deposit_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field" style="padding:10px 12px;">
                    <span class="dashicons dashicons-money-alt" style="color:#2271b1;margin-right:5px;"></span>
                    <strong><?php esc_html_e( 'Deposit Settings', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></strong>
                    <span style="color:#999;font-size:12px;display:block;margin-top:4px;">
                        <?php esc_html_e( 'Override global deposit settings for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                    </span>
                </p>

                <?php
                woocommerce_wp_select( array(
                    'id'          => '_apd_enable_deposit',
                    'label'       => __( 'Enable Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'options'     => array(
                        ''    => __( 'Use Global Setting', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'yes' => __( 'Yes', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'no'  => __( 'No', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    ),
                    'value'       => get_post_meta( $product_id, '_apd_enable_deposit', true ),
                    'desc_tip'    => true,
                    'description' => __( 'Enable or disable deposit for this specific product.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                ) );

                woocommerce_wp_select( array(
                    'id'          => '_apd_force_deposit',
                    'label'       => __( 'Force Deposit Only', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'options'     => array(
                        ''    => __( 'Use Global Setting', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'yes' => __( 'Yes', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'no'  => __( 'No', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    ),
                    'value'       => get_post_meta( $product_id, '_apd_force_deposit', true ),
                    'desc_tip'    => true,
                    'description' => __( 'Force this product to show only the deposit/partial payment option and hide full payment.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                ) );

                $deposit_type_options = array(
                    'global'     => __( 'Use Global Setting', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'fixed'      => __( 'Fixed Amount', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'percentage' => __( 'Percentage', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                );
                if ( defined( 'APD_PRO_VERSION' ) ) {
                    $deposit_type_options['payment_plan'] = __( 'Payment Plan (Pro)', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                    $deposit_type_options['min_max']      = __( 'Min / Max – Customer Chooses (Pro)', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                }
                woocommerce_wp_select( array(
                    'id'          => '_apd_deposit_type',
                    'label'       => __( 'Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'options'     => $deposit_type_options,
                    'value'       => get_post_meta( $product_id, '_apd_deposit_type', true ) ?: 'global',
                    'desc_tip'    => true,
                    'description' => __( 'Choose deposit type for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                ) );

                echo '<div id="apd-product-value-section" class="apd-conditional-settings apd-conditional-settings--value">';
                woocommerce_wp_text_input( array(
                    'id'          => '_apd_deposit_value',
                    'label'       => __( 'Deposit Value', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'type'        => 'number',
                    'value'       => get_post_meta( $product_id, '_apd_deposit_value', true ),
                    'placeholder' => __( 'e.g. 50', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'desc_tip'    => true,
                    'description' => __( 'Enter deposit value. For percentage: enter 50 for 50%. For fixed: enter the exact amount.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'custom_attributes' => array(
                        'step' => '0.01',
                        'min'  => '0',
                    ),
                ) );
                echo '</div>';
                ?>

                <?php
                // Pro: Product-level min/max deposit fields
                if ( defined( 'APD_PRO_VERSION' ) ) :
                ?>
                <div id="apd-product-minmax-section" class="apd-conditional-settings apd-conditional-settings--minmax">
                <p class="form-field" style="padding:10px 12px;margin-top:8px;border-top:1px solid #f0f0f1;">
                    <span class="dashicons dashicons-arrow-up-alt" style="color:#f59e0b;margin-right:5px;"></span>
                    <strong><?php esc_html_e( 'Min / Max Deposit (Pro)', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></strong>
                    <span style="color:#999;font-size:12px;display:block;margin-top:4px;">
                        <?php esc_html_e( 'Override global min/max for this product. Leave empty to use global settings.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                    </span>
                </p>
                <?php
                woocommerce_wp_text_input( array(
                    'id'          => '_apd_min_deposit',
                    'label'       => __( 'Minimum Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'type'        => 'number',
                    'value'       => get_post_meta( $product_id, '_apd_min_deposit', true ),
                    'placeholder' => __( 'Use global', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'desc_tip'    => true,
                    'description' => __( 'Minimum deposit amount for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
                ) );
                woocommerce_wp_text_input( array(
                    'id'          => '_apd_max_deposit',
                    'label'       => __( 'Maximum Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'type'        => 'number',
                    'value'       => get_post_meta( $product_id, '_apd_max_deposit', true ),
                    'placeholder' => __( 'Use global', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'desc_tip'    => true,
                    'description' => __( 'Maximum deposit amount for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
                ) );
                echo '</div>';
                endif;
                ?>

                <?php
                if ( defined( 'APD_PRO_VERSION' ) && class_exists( 'APD_Payment_Plans' ) ) :
                    $all_plans      = APD_Payment_Plans::get_plans();
                    $assigned_plans = (array) get_post_meta( $product_id, '_apd_assigned_plans', true );
                ?>
                <div id="apd-product-plans-section" class="apd-conditional-settings apd-conditional-settings--plans">
                    <p class="form-field" style="padding:10px 12px;margin-top:8px;border-top:1px solid #f0f0f1;">
                        <span class="dashicons dashicons-calendar-alt" style="color:#6366f1;margin-right:5px;"></span>
                        <strong><?php esc_html_e( 'Payment Plan Selection (Pro)', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></strong>
                        <span style="color:#999;font-size:12px;display:block;margin-top:4px;">
                            <?php esc_html_e( 'Choose which plans are available when this product uses the Payment Plan deposit type. Leave all unchecked to use all active plans.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                        </span>
                    </p>

                    <?php if ( ! empty( $all_plans ) ) : ?>
                    <div class="apd-plan-assignment-table">
                        <div class="apd-plan-assignment-table__head">
                            <div class="apd-plan-assignment-table__col apd-plan-assignment-table__col--check"></div>
                            <div class="apd-plan-assignment-table__col apd-plan-assignment-table__col--name"><?php esc_html_e( 'Plan Name', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                            <div class="apd-plan-assignment-table__col apd-plan-assignment-table__col--type"><?php esc_html_e( 'Type', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                            <div class="apd-plan-assignment-table__col apd-plan-assignment-table__col--installments"><?php esc_html_e( 'Installments', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                            <div class="apd-plan-assignment-table__col apd-plan-assignment-table__col--status"><?php esc_html_e( 'Status', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                        </div>
                        <?php foreach ( $all_plans as $plan_id => $plan ) : ?>
                            <?php
                            $installment_count = ! empty( $plan['installments'] ) && is_array( $plan['installments'] ) ? count( $plan['installments'] ) : 0;
                            $status            = $plan['status'] ?? 'active';
                            ?>
                        <label class="apd-plan-assignment-card">
                            <span class="apd-plan-assignment-card__check">
                                <input
                                    type="checkbox"
                                    name="_apd_assigned_plans[]"
                                    value="<?php echo esc_attr( $plan_id ); ?>"
                                    <?php checked( in_array( $plan_id, $assigned_plans, true ) ); ?>
                                />
                            </span>
                            <span class="apd-plan-assignment-card__body">
                                <span class="apd-plan-assignment-card__title-row">
                                    <span class="apd-plan-assignment-card__name">
                                        <strong><?php echo esc_html( $plan['name'] ); ?></strong>
                                        <?php if ( ! empty( $plan['description'] ) ) : ?>
                                        <span class="apd-plan-assignment-card__description"><?php echo esc_html( $plan['description'] ); ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="apd-plan-assignment-card__type">
                                        <span class="apd-plan-assignment-card__badge"><?php echo esc_html( ucfirst( $plan['price_type'] ?? 'percentage' ) ); ?></span>
                                    </span>
                                    <span class="apd-plan-assignment-card__installments">
                                        <?php
                                        printf(
                                            /* translators: %d: number of installments in the payment plan. */
                                            esc_html__( '%d installment(s)', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                                            $installment_count
                                        );
                                        ?>
                                    </span>
                                    <span class="apd-plan-assignment-card__state">
                                        <span class="apd-plan-assignment-card__status apd-plan-assignment-card__status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
                                    </span>
                                </span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="form-field apd-inline-help">
                        <?php esc_html_e( 'Tip: if no plan is checked, all active plans will be shown on the frontend for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                    </p>
                    <?php else : ?>
                    <div class="apd-inline-empty-state">
                        <?php esc_html_e( 'No payment plans created yet. Create plans from Deposits > Payment Plans first.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php
                if ( defined( 'APD_PRO_VERSION' ) ) :
                ?>
                <div id="apd-product-flexible-wrap" class="apd-product-flexible-card">
                    <div class="apd-product-flexible-card__head">
                        <span class="dashicons dashicons-money-alt"></span>
                        <span class="apd-product-flexible-card__title"><?php esc_html_e( 'Remaining Balance (Pro)', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                        <span class="apd-product-flexible-card__desc">
                            <?php esc_html_e( 'A separate setting from the deposit above: this is how the customer pays off whatever is left afterwards.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                        </span>
                    </div>
                    <?php
                    woocommerce_wp_select( array(
                        'id'          => '_apd_flexible_payments',
                        'label'       => __( 'Flexible Payments (Pro)', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'options'     => array(
                            ''    => __( 'Use Global Setting', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                            'yes' => __( 'Yes', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                            'no'  => __( 'No', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        ),
                        'value'       => get_post_meta( $product_id, '_apd_flexible_payments', true ),
                        'desc_tip'    => true,
                        'description' => __( 'Let customers pay the remaining balance in any amount, over as many payments as they like, until this booking is paid in full. Yes or No overrides the global Flexible Payments setting for this product.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    ) );
                    ?>
                    <div id="apd-product-flexible-section" class="apd-conditional-settings apd-conditional-settings--flexible">
                    <?php
                    woocommerce_wp_text_input( array(
                        'id'          => '_apd_flexible_min_payment',
                        'label'       => __( 'Smallest payment allowed', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'type'        => 'number',
                        'value'       => get_post_meta( $product_id, '_apd_flexible_min_payment', true ),
                        'placeholder' => __( 'Use global', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'desc_tip'    => true,
                        'description' => __( 'Smallest amount a customer may put towards this balance. If less than this is left, they can still clear it in full.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'custom_attributes' => array( 'step' => '0.01', 'min' => '0' ),
                    ) );
                    woocommerce_wp_select( array(
                        // Deliberately not called "Type": a second Type dropdown offering
                        // the same option labels as Deposit Type reads as a duplicate of
                        // it, when it only ever describes the minimum directly above.
                        'id'          => '_apd_flexible_min_payment_type',
                        'label'       => __( 'The minimum above is', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        'options'     => array(
                            ''           => __( 'Use the global minimum', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                            'fixed'      => __( 'A flat amount of money', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                            'percentage' => __( 'A percentage of the booking total', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                        ),
                        'value'       => get_post_meta( $product_id, '_apd_flexible_min_payment_type', true ),
                        'desc_tip'    => true,
                        'description' => __( 'This only describes the Minimum Per Payment field above it. It does not change the Deposit Type.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
                    ) );
                    ?>
                    </div>
                    <p id="apd-product-flexible-plan-note" class="apd-product-flexible-card__note">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'Flexible Payments is on, so the Payment Plan deposit type is unavailable for this product: a plan fixes the balance into scheduled instalments, which is the opposite of letting the customer pay any amount whenever they like. Choose another deposit type, or set Flexible Payments back to No to use a plan.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="form-field apd-product-deposit-insight-wrap" style="padding:12px;">
                    <div
                        class="apd-product-deposit-insight"
                        id="apd-product-deposit-insight"
                        data-context="<?php echo esc_attr( wp_json_encode( $context ) ); ?>"
                    >
                        <div class="apd-product-deposit-insight__hero">
                            <div>
                                <div class="apd-product-deposit-insight__eyebrow"><?php esc_html_e( 'Effective Deposit Configuration', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                                <h4><?php esc_html_e( 'Rule Summary', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></h4>
                                <p><?php esc_html_e( 'See whether this product uses its own deposit rule or falls back to category/global settings.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></p>
                            </div>
                            <div class="apd-product-deposit-insight__badges">
                                <span class="apd-pill apd-pill-success" id="apd-effective-enabled-badge"><?php esc_html_e( 'Enabled', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <span class="apd-pill apd-pill-info" id="apd-effective-mode-badge"><?php esc_html_e( 'Product Override', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                            </div>
                        </div>

                        <div class="apd-product-deposit-insight__grid">
                            <div class="apd-summary-card">
                                <span class="apd-summary-card__label"><?php esc_html_e( 'Deposit Availability', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong class="apd-summary-card__value" id="apd-effective-enable-value"></strong>
                                <span class="apd-summary-card__meta" id="apd-effective-enable-source"></span>
                            </div>
                            <div class="apd-summary-card">
                                <span class="apd-summary-card__label"><?php esc_html_e( 'Effective Deposit Type', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong class="apd-summary-card__value" id="apd-effective-type-value"></strong>
                                <span class="apd-summary-card__meta" id="apd-effective-type-source"></span>
                            </div>
                            <div class="apd-summary-card">
                                <span class="apd-summary-card__label"><?php esc_html_e( 'Effective Deposit Value', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong class="apd-summary-card__value" id="apd-effective-value-value"></strong>
                                <span class="apd-summary-card__meta" id="apd-effective-value-source"></span>
                            </div>
                            <div class="apd-summary-card">
                                <span class="apd-summary-card__label"><?php esc_html_e( 'Force Deposit Only', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong class="apd-summary-card__value" id="apd-effective-force-value"></strong>
                                <span class="apd-summary-card__meta" id="apd-effective-force-source"></span>
                            </div>
                            <div class="apd-summary-card">
                                <span class="apd-summary-card__label"><?php esc_html_e( 'Active Rule Scope', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong class="apd-summary-card__value" id="apd-effective-scope-value"></strong>
                                <span class="apd-summary-card__meta" id="apd-effective-scope-meta"></span>
                            </div>
                        </div>

                        <?php
                        // Pro: Min/Max info
                        if ( defined( 'APD_PRO_VERSION' ) ) :
                            $global_min = floatval( apd_get_option( 'min_deposit_amount', 0 ) );
                            $global_max = floatval( apd_get_option( 'max_deposit_amount', 0 ) );
                            $prod_min   = get_post_meta( $product_id, '_apd_min_deposit', true );
                            $prod_max   = get_post_meta( $product_id, '_apd_max_deposit', true );
                            $eff_min    = ( $prod_min !== '' && $prod_min !== false ) ? floatval( $prod_min ) : $global_min;
                            $eff_max    = ( $prod_max !== '' && $prod_max !== false ) ? floatval( $prod_max ) : $global_max;
                            $currency   = get_woocommerce_currency_symbol();
                        ?>
                        <div id="apd-summary-minmax-section" class="apd-product-deposit-insight__grid" style="margin-top:10px;">
                            <div class="apd-summary-card">
                                <span class="apd-summary-card__label"><?php esc_html_e( 'Min Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong class="apd-summary-card__value"><?php echo $eff_min > 0 ? esc_html( $currency . number_format( $eff_min, 2 ) ) : '—'; ?></strong>
                                <span class="apd-summary-card__meta">
                                    <?php
                                    if ( $prod_min !== '' && $prod_min !== false ) {
                                        esc_html_e( 'Source: Product override', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                                    } elseif ( $global_min > 0 ) {
                                        esc_html_e( 'Source: Global settings', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                                    } else {
                                        esc_html_e( 'No minimum set', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="apd-summary-card">
                                <span class="apd-summary-card__label"><?php esc_html_e( 'Max Deposit', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong class="apd-summary-card__value"><?php echo $eff_max > 0 ? esc_html( $currency . number_format( $eff_max, 2 ) ) : '—'; ?></strong>
                                <span class="apd-summary-card__meta">
                                    <?php
                                    if ( $prod_max !== '' && $prod_max !== false ) {
                                        esc_html_e( 'Source: Product override', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                                    } elseif ( $global_max > 0 ) {
                                        esc_html_e( 'Source: Global settings', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                                    } else {
                                        esc_html_e( 'No maximum set', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php
                        // Pro: Payment Plans info
                        if ( defined( 'APD_PRO_VERSION' ) && class_exists( 'APD_Payment_Plans' ) ) :
                            $all_plans    = APD_Payment_Plans::get_plans();
                            $assigned_ids = (array) get_post_meta( $product_id, '_apd_assigned_plans', true );
                            $assigned_ids = array_filter( $assigned_ids );

                            if ( ! empty( $assigned_ids ) ) {
                                $active_plans = array_intersect_key( $all_plans, array_flip( $assigned_ids ) );
                            } else {
                                $active_plans = array_filter( $all_plans, function ( $p ) {
                                    return ( $p['status'] ?? 'active' ) === 'active';
                                } );
                            }
                        ?>
                        <div id="apd-summary-plans-section" style="margin-top:12px;padding:14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                                <strong style="font-size:13px;color:#334155;">
                                    <span class="dashicons dashicons-calendar-alt" style="font-size:15px;width:15px;height:15px;color:#6366f1;margin-right:4px;vertical-align:middle;"></span>
                                    <?php esc_html_e( 'Payment Plans', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                                </strong>
                                <span style="font-size:12px;color:#94a3b8;">
                                    <?php
                                    if ( ! empty( $assigned_ids ) ) {
                                        /* translators: %d: number of payment plans assigned to the product. */
                                        printf( esc_html__( '%d plan(s) assigned to this product', 'advanced-partial-payment-or-deposit-for-woocommerce' ), count( $active_plans ) );
                                    } else {
                                        esc_html_e( 'Using all active plans', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                                    }
                                    ?>
                                </span>
                            </div>
                            <?php if ( ! empty( $active_plans ) ) : ?>
                                <?php foreach ( $active_plans as $pid => $plan ) :
                                    $total_pct = 0;
                                    foreach ( $plan['installments'] as $inst ) $total_pct += floatval( $inst['amount'] );
                                ?>
                                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;background:#fff;margin-bottom:6px;">
                                    <div>
                                        <strong style="font-size:13px;color:#1e293b;"><?php echo esc_html( $plan['name'] ); ?></strong>
                                        <span style="color:#94a3b8;font-size:11px;margin-left:6px;">
                                            <?php echo count( $plan['installments'] ); ?> <?php esc_html_e( 'installments', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <span style="background:#eef2ff;color:#4f46e5;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">
                                            <?php echo esc_html( $total_pct ); ?><?php echo $plan['price_type'] === 'percentage' ? '%' : ' ' . esc_html( get_woocommerce_currency_symbol() ); ?>
                                        </span>
                                        <span style="background:<?php echo ( $plan['status'] ?? 'active' ) === 'active' ? '#d1fae5' : '#f1f5f9'; ?>;color:<?php echo ( $plan['status'] ?? 'active' ) === 'active' ? '#065f46' : '#64748b'; ?>;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;margin-left:4px;">
                                            <?php echo esc_html( ucfirst( $plan['status'] ?? 'active' ) ); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div style="padding:12px;text-align:center;color:#94a3b8;font-size:13px;">
                                    <?php esc_html_e( 'No payment plans available. Create plans in Deposits → Payment Plans.', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <div class="apd-product-deposit-insight__details">
                            <div class="apd-detail-panel">
                                <div class="apd-detail-panel__title"><?php esc_html_e( 'Product Setting', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                                <div class="apd-detail-panel__line" id="apd-product-setting-line"></div>
                            </div>
                            <div class="apd-detail-panel">
                                <div class="apd-detail-panel__title"><?php esc_html_e( 'Category Fallback', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                                <div class="apd-detail-panel__line" id="apd-category-setting-line"></div>
                            </div>
                            <div class="apd-detail-panel">
                                <div class="apd-detail-panel__title"><?php esc_html_e( 'Global Default', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></div>
                                <div class="apd-detail-panel__line" id="apd-global-setting-line"></div>
                            </div>
                        </div>

                        <div class="apd-product-deposit-insight__preview">
                            <div class="apd-preview-price">
                                <span><?php esc_html_e( 'Current Product Price', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                <strong id="apd-preview-price"></strong>
                            </div>
                            <div class="apd-preview-split">
                                <div class="apd-preview-box">
                                    <span><?php esc_html_e( 'Pay Now', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                    <strong id="apd-preview-deposit"></strong>
                                </div>
                                <div class="apd-preview-box">
                                    <span><?php esc_html_e( 'Pay Later', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></span>
                                    <strong id="apd-preview-balance"></strong>
                                </div>
                            </div>
                            <div class="apd-preview-note" id="apd-preview-note"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save product deposit meta.
     */
    public function save_product_meta( $product_id ) {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_product', $product_id ) ) {
            return;
        }

        $fields = array( '_apd_enable_deposit', '_apd_force_deposit', '_apd_deposit_type', '_apd_deposit_value' );

        // Pro: min/max deposit and flexible payment fields
        if ( defined( 'APD_PRO_VERSION' ) ) {
            $fields[] = '_apd_min_deposit';
            $fields[] = '_apd_max_deposit';
            $fields[] = '_apd_flexible_payments';
            $fields[] = '_apd_flexible_min_payment';
            $fields[] = '_apd_flexible_min_payment_type';
        }

        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $product_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            }
        }

        // Pro: payment plan assignment
        if ( defined( 'APD_PRO_VERSION' ) && isset( $_POST['_apd_assigned_plans'] ) && is_array( $_POST['_apd_assigned_plans'] ) ) {
            $assigned_plans = array_map( 'sanitize_text_field', wp_unslash( $_POST['_apd_assigned_plans'] ) );
            update_post_meta( $product_id, '_apd_assigned_plans', array_values( array_unique( $assigned_plans ) ) );
        }
    }

    /**
     * Build product deposit context for the admin summary panel.
     *
     * @param int $product_id Product ID.
     * @return array<string,mixed>
     */
    private function get_product_deposit_context( $product_id ) {
        $product       = wc_get_product( $product_id );
        $product_price = $product ? floatval( $product->get_price() ) : 0;
        $category_data = array(
            'enable' => '',
            'force'  => '',
            'type'   => 'global',
            'value'  => '',
            'name'   => __( 'No category override', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
        );

        if ( $product ) {
            foreach ( $product->get_category_ids() as $category_id ) {
                $term      = get_term( $category_id, 'product_cat' );
                $cat_name  = ( $term && ! is_wp_error( $term ) ) ? $term->name : __( 'Category rule', 'advanced-partial-payment-or-deposit-for-woocommerce' );
                $cat_enable = get_term_meta( $category_id, '_apd_enable_deposit', true );
                $cat_force  = get_term_meta( $category_id, '_apd_force_deposit', true );
                $cat_type   = get_term_meta( $category_id, '_apd_deposit_type', true );
                $cat_value  = get_term_meta( $category_id, '_apd_deposit_value', true );

                if ( '' === $category_data['enable'] && in_array( $cat_enable, array( 'yes', 'no' ), true ) ) {
                    $category_data['enable'] = $cat_enable;
                    $category_data['name']   = $cat_name;
                }

                if ( '' === $category_data['force'] && in_array( $cat_force, array( 'yes', 'no' ), true ) ) {
                    $category_data['force'] = $cat_force;
                    $category_data['name']  = $cat_name;
                }

                if ( 'global' === $category_data['type'] && ! empty( $cat_type ) && 'global' !== $cat_type ) {
                    $category_data['type'] = $cat_type;
                    $category_data['name'] = $cat_name;
                }

                if ( '' === $category_data['value'] && '' !== (string) $cat_value && ! empty( $cat_type ) && 'global' !== $cat_type ) {
                    $category_data['value'] = $cat_value;
                    $category_data['name']  = $cat_name;
                }
            }
        }

        return array(
            'product' => array(
                'enable' => get_post_meta( $product_id, '_apd_enable_deposit', true ),
                'force'  => get_post_meta( $product_id, '_apd_force_deposit', true ),
                'type'   => get_post_meta( $product_id, '_apd_deposit_type', true ) ?: 'global',
                'value'  => get_post_meta( $product_id, '_apd_deposit_value', true ),
            ),
            'category' => $category_data,
            'global' => array(
                'enable' => apd_get_option( 'enable_deposit', 'yes' ),
                'force'  => apd_get_option( 'force_deposit', 'no' ),
                'type'   => apd_get_option( 'deposit_type', 'percentage' ),
                'value'  => apd_get_option( 'deposit_value', 50 ),
            ),
            'min_max' => array(
                'global_min'  => defined( 'APD_PRO_VERSION' ) ? floatval( apd_get_option( 'min_deposit_amount', 0 ) ) : 0,
                'global_max'  => defined( 'APD_PRO_VERSION' ) ? floatval( apd_get_option( 'max_deposit_amount', 0 ) ) : 0,
                'product_min' => defined( 'APD_PRO_VERSION' ) ? get_post_meta( $product_id, '_apd_min_deposit', true ) : '',
                'product_max' => defined( 'APD_PRO_VERSION' ) ? get_post_meta( $product_id, '_apd_max_deposit', true ) : '',
            ),
            'plans' => $this->get_product_plan_context( $product_id ),
            'product_price'    => $product_price,
            'currency_symbol'  => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
            'currency_pos'     => get_option( 'woocommerce_currency_pos', 'left' ),
            'price_decimals'   => wc_get_price_decimals(),
        );
    }

    /**
     * Build product payment plan context for the admin summary panel.
     *
     * @param int $product_id Product ID.
     * @return array<string,mixed>
     */
    private function get_product_plan_context( $product_id ) {
        $context = array(
            'assigned'  => array(),
            'available' => array(),
        );

        if ( ! defined( 'APD_PRO_VERSION' ) || ! class_exists( 'APD_Payment_Plans' ) ) {
            return $context;
        }

        $context['assigned'] = array_values(
            array_filter(
                array_map( 'strval', (array) get_post_meta( $product_id, '_apd_assigned_plans', true ) )
            )
        );

        foreach ( APD_Payment_Plans::get_plans() as $plan_id => $plan ) {
            $context['available'][] = array(
                'id'                 => (string) $plan_id,
                'name'               => $plan['name'] ?? '',
                'status'             => $plan['status'] ?? 'active',
                'price_type'         => $plan['price_type'] ?? 'percentage',
                'installments_count' => ! empty( $plan['installments'] ) && is_array( $plan['installments'] ) ? count( $plan['installments'] ) : 0,
                'installments'       => ! empty( $plan['installments'] ) && is_array( $plan['installments'] ) ? array_values( $plan['installments'] ) : array(),
            );
        }

        return $context;
    }
}
