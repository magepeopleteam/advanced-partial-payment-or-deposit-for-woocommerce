<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_cats = get_terms( array(
    'taxonomy'   => 'product_cat',
    'hide_empty' => false,
) );
?>
<div class="apd-tab-header">
    <h2><?php esc_html_e( 'Category Deposit Rules', 'advanced-partial-payment' ); ?></h2>
    <p><?php esc_html_e( 'Set deposit rules per product category. Products inherit these settings if no product-level override exists.', 'advanced-partial-payment' ); ?></p>
</div>

<div class="apd-card">
    <div class="apd-card-header">
        <h3><?php esc_html_e( 'Add Category Rule', 'advanced-partial-payment' ); ?></h3>
    </div>
    <div class="apd-card-body">
        <form id="apd-category-form">
            <div class="apd-grid apd-grid-4">
                <div class="apd-field">
                    <label><?php esc_html_e( 'Category', 'advanced-partial-payment' ); ?></label>
                    <select name="category_id" id="apd-cat-select" class="apd-select">
                        <option value=""><?php esc_html_e( 'Select Category', 'advanced-partial-payment' ); ?></option>
                        <?php foreach ( $product_cats as $cat ) : ?>
                        <option value="<?php echo esc_attr( $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?> (<?php echo esc_html( $cat->count ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="apd-field">
                    <label><?php esc_html_e( 'Enable', 'advanced-partial-payment' ); ?></label>
                    <select name="enable_deposit" class="apd-select">
                        <option value="yes"><?php esc_html_e( 'Yes', 'advanced-partial-payment' ); ?></option>
                        <option value="no"><?php esc_html_e( 'No', 'advanced-partial-payment' ); ?></option>
                    </select>
                </div>
                <div class="apd-field">
                    <label><?php esc_html_e( 'Type', 'advanced-partial-payment' ); ?></label>
                    <select name="deposit_type" class="apd-select">
                        <option value="percentage"><?php esc_html_e( 'Percentage', 'advanced-partial-payment' ); ?></option>
                        <option value="fixed"><?php esc_html_e( 'Fixed', 'advanced-partial-payment' ); ?></option>
                    </select>
                </div>
                <div class="apd-field">
                    <label><?php esc_html_e( 'Value', 'advanced-partial-payment' ); ?></label>
                    <div class="apd-field-with-btn">
                        <input type="number" name="deposit_value" step="0.01" min="0" class="apd-input" placeholder="50" />
                        <button type="submit" class="apd-btn apd-btn-primary"><?php esc_html_e( 'Add Rule', 'advanced-partial-payment' ); ?></button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="apd-card">
    <div class="apd-card-header">
        <h3><?php esc_html_e( 'Existing Category Rules', 'advanced-partial-payment' ); ?></h3>
    </div>
    <div class="apd-card-body">
        <table class="apd-table" id="apd-category-rules-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Category', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Type', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Products', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'advanced-partial-payment' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $has_rules = false;
                foreach ( $product_cats as $cat ) :
                    $enabled = get_term_meta( $cat->term_id, '_apd_enable_deposit', true );
                    $type    = get_term_meta( $cat->term_id, '_apd_deposit_type', true );
                    $value   = get_term_meta( $cat->term_id, '_apd_deposit_value', true );

                    if ( empty( $enabled ) && empty( $type ) ) continue;
                    $has_rules = true;
                ?>
                <tr data-cat-id="<?php echo esc_attr( $cat->term_id ); ?>">
                    <td><strong><?php echo esc_html( $cat->name ); ?></strong></td>
                    <td>
                        <span class="apd-status-dot <?php echo $enabled === 'yes' ? 'apd-status-active' : 'apd-status-inactive'; ?>"></span>
                        <?php echo $enabled === 'yes' ? esc_html__( 'Active', 'advanced-partial-payment' ) : esc_html__( 'Inactive', 'advanced-partial-payment' ); ?>
                    </td>
                    <td><span class="apd-badge"><?php echo esc_html( ucfirst( $type ?: 'global' ) ); ?></span></td>
                    <td><?php echo esc_html( $value ); ?><?php echo $type === 'percentage' ? '%' : ( $value ? ' ' . get_woocommerce_currency_symbol() : '' ); ?></td>
                    <td><?php echo esc_html( $cat->count ); ?></td>
                    <td>
                        <button type="button" class="apd-btn apd-btn-small apd-btn-danger apd-delete-category-rule"
                                data-cat-id="<?php echo esc_attr( $cat->term_id ); ?>">
                            <?php esc_html_e( 'Remove', 'advanced-partial-payment' ); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if ( ! $has_rules ) : ?>
                <tr class="apd-empty-row">
                    <td colspan="6">
                        <div class="apd-empty-state">
                            <span class="dashicons dashicons-category"></span>
                            <p><?php esc_html_e( 'No category deposit rules defined yet.', 'advanced-partial-payment' ); ?></p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
