<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="apd-tab-header">
    <h2><?php esc_html_e( 'Product Deposit Settings', 'advanced-partial-payment' ); ?></h2>
    <p><?php esc_html_e( 'Configure deposit settings on individual products to override the global settings.', 'advanced-partial-payment' ); ?></p>
</div>

<div class="apd-card">
    <div class="apd-card-header">
        <h3><?php esc_html_e( 'How It Works', 'advanced-partial-payment' ); ?></h3>
    </div>
    <div class="apd-card-body">
        <div class="apd-info-box">
            <div class="apd-info-icon">
                <span class="dashicons dashicons-info-outline"></span>
            </div>
            <div class="apd-info-content">
                <p><?php esc_html_e( 'Per-product deposit settings can be configured directly from the product edit page.', 'advanced-partial-payment' ); ?></p>
                <ol>
                    <li><?php esc_html_e( 'Go to Products → Edit a product', 'advanced-partial-payment' ); ?></li>
                    <li><?php esc_html_e( 'Click the "Deposit" tab in the Product Data panel', 'advanced-partial-payment' ); ?></li>
                    <li><?php esc_html_e( 'Enable deposit and set the type (Fixed/Percentage) and value', 'advanced-partial-payment' ); ?></li>
                    <li><?php esc_html_e( 'Save the product', 'advanced-partial-payment' ); ?></li>
                </ol>
            </div>
        </div>

        <div class="apd-priority-info">
            <h4><?php esc_html_e( 'Priority Order', 'advanced-partial-payment' ); ?></h4>
            <div class="apd-priority-chain">
                <div class="apd-priority-item apd-priority-high">
                    <span class="apd-priority-num">1</span>
                    <span><?php esc_html_e( 'Product Level', 'advanced-partial-payment' ); ?></span>
                    <small><?php esc_html_e( 'Highest priority', 'advanced-partial-payment' ); ?></small>
                </div>
                <span class="apd-priority-arrow">→</span>
                <div class="apd-priority-item apd-priority-mid">
                    <span class="apd-priority-num">2</span>
                    <span><?php esc_html_e( 'Category Level', 'advanced-partial-payment' ); ?></span>
                    <small><?php esc_html_e( 'Medium priority', 'advanced-partial-payment' ); ?></small>
                </div>
                <span class="apd-priority-arrow">→</span>
                <div class="apd-priority-item apd-priority-low">
                    <span class="apd-priority-num">3</span>
                    <span><?php esc_html_e( 'Global Setting', 'advanced-partial-payment' ); ?></span>
                    <small><?php esc_html_e( 'Default fallback', 'advanced-partial-payment' ); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="apd-card">
    <div class="apd-card-header">
        <h3><?php esc_html_e( 'Products with Custom Deposits', 'advanced-partial-payment' ); ?></h3>
    </div>
    <div class="apd-card-body">
        <?php
        $products_with_deposit = get_posts( array(
            'post_type'   => 'product',
            'numberposts' => 20,
            'meta_query'  => array(
                array(
                    'key'     => '_apd_enable_deposit',
                    'value'   => 'yes',
                    'compare' => '=',
                ),
            ),
        ) );

        if ( ! empty( $products_with_deposit ) ) :
        ?>
        <table class="apd-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Product', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Deposit Type', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Value', 'advanced-partial-payment' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'advanced-partial-payment' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $products_with_deposit as $p ) :
                    $type  = get_post_meta( $p->ID, '_apd_deposit_type', true );
                    $value = get_post_meta( $p->ID, '_apd_deposit_value', true );
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $p->post_title ); ?></strong></td>
                    <td><span class="apd-badge"><?php echo esc_html( ucfirst( $type ) ); ?></span></td>
                    <td><?php echo esc_html( $value ); ?><?php echo $type === 'percentage' ? '%' : ' ' . get_woocommerce_currency_symbol(); ?></td>
                    <td>
                        <a href="<?php echo esc_url( get_edit_post_link( $p->ID ) ); ?>" class="apd-btn apd-btn-small">
                            <?php esc_html_e( 'Edit', 'advanced-partial-payment' ); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <div class="apd-empty-state">
            <span class="dashicons dashicons-products"></span>
            <p><?php esc_html_e( 'No products with custom deposit settings yet.', 'advanced-partial-payment' ); ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>
