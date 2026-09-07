<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$tabs = APD_Admin::get_tabs();
?>
<div class="wrap apd-wrap">
    <div class="apd-dashboard">
        <!-- Header -->
        <div class="apd-header">
            <div class="apd-header-inner">
                <div class="apd-header-left">
                    <span class="dashicons dashicons-money-alt apd-header-icon"></span>
                    <div>
                        <h1><?php esc_html_e( 'Deposit Settings', 'advanced-partial-payment' ); ?></h1>
                        <span class="apd-version">v<?php echo esc_html( APD_VERSION ); ?></span>
                    </div>
                </div>
                <div class="apd-header-right">
                    <?php if ( ! apd_is_pro_active() ) : ?>
                    <a href="#" class="apd-upgrade-btn">
                        <span class="dashicons dashicons-star-filled"></span>
                        <?php esc_html_e( 'Upgrade to Pro', 'advanced-partial-payment' ); ?>
                    </a>
                    <?php else : ?>
                    <span class="apd-pro-badge">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'Pro Active', 'advanced-partial-payment' ); ?>
                    </span>
                    <?php endif; ?>
                    <?php do_action( 'apd_dashboard_header_badges' ); ?>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="apd-body">
            <!-- Sidebar -->
            <div class="apd-sidebar">
                <nav class="apd-nav">
                    <?php
                    $first     = true;
                    $has_separator = false;
                    foreach ( $tabs as $tab_id => $tab ) :
                        if ( $tab['pro'] && ! $has_separator ) :
                            $has_separator = true;
                            ?>
                            <div class="apd-nav-separator">
                                <span><?php esc_html_e( 'Pro Features', 'advanced-partial-payment' ); ?></span>
                            </div>
                        <?php endif; ?>
                        <a href="#"
                           class="apd-nav-item<?php echo $first ? ' active' : ''; ?><?php echo $tab['pro'] ? ' apd-pro-tab' : ''; ?>"
                           data-tab="<?php echo esc_attr( $tab_id ); ?>">
                            <span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span>
                            <span class="apd-nav-label"><?php echo esc_html( $tab['label'] ); ?></span>
                            <?php if ( $tab['pro'] && ! apd_is_pro_active() ) : ?>
                                <span class="apd-pro-badge-small">PRO</span>
                            <?php endif; ?>
                        </a>
                        <?php
                        $first = false;
                    endforeach;
                    ?>
                </nav>
            </div>

            <!-- Content -->
            <div class="apd-content">
                <!-- Toast notification -->
                <div class="apd-toast" id="apd-toast"></div>

                <?php
                $first = true;
                foreach ( $tabs as $tab_id => $tab ) :
                    ?>
                    <div class="apd-tab-content<?php echo $first ? ' active' : ''; ?>"
                         id="apd-tab-<?php echo esc_attr( $tab_id ); ?>">
                        <?php
                        if ( $tab['pro'] && ! apd_is_pro_active() ) {
                            // Show pro upgrade notice
                            ?>
                            <div class="apd-pro-notice">
                                <div class="apd-pro-notice-icon">
                                    <span class="dashicons dashicons-lock"></span>
                                </div>
                                <h2><?php echo esc_html( $tab['label'] ); ?></h2>
                                <p><?php esc_html_e( 'This feature is available in the Pro version.', 'advanced-partial-payment' ); ?></p>
                                <p><?php esc_html_e( 'Unlock payment plans, min/max deposits, gateway rules, auto reminders, reports, and more!', 'advanced-partial-payment' ); ?></p>
                                <a href="#" class="button button-primary button-hero apd-upgrade-btn-large">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php esc_html_e( 'Upgrade to Pro', 'advanced-partial-payment' ); ?>
                                </a>
                            </div>
                            <?php
                        } else {
                            $tab_file = APD_PLUGIN_DIR . 'admin/views/tabs/' . $tab_id . '.php';
                            // Allow pro addon to override tab files
                            $tab_file = apply_filters( 'apd_tab_file_' . $tab_id, $tab_file );
                            if ( file_exists( $tab_file ) ) {
                                include $tab_file;
                            }
                        }
                        ?>
                    </div>
                    <?php
                    $first = false;
                endforeach;
                ?>
            </div>
        </div>
    </div>
</div>
