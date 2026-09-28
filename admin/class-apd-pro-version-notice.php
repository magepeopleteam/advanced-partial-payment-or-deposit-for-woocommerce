<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notice for a Pro addon that is too old for this version of the free plugin.
 *
 * Pro 4.x announces itself with APD_PRO_VERSION. The 3.x addon predates that constant, so a
 * store that updates the free plugin to 4.x but keeps Pro 3.x gets apd_is_pro_active() ===
 * false and every Pro tab shows "Upgrade to Pro" — it looks as if the licence it paid for is
 * gone. The old addon knows nothing about 4.x and cannot explain this, so the free plugin
 * does: it finds the old addon among the active plugins and sends the owner to their
 * MagePeople account to download the current Pro build.
 */
class APD_Pro_Version_Notice {

	/**
	 * Oldest Pro addon that works with this free plugin (the 4.x rewrite).
	 */
	const MIN_PRO_VERSION = '4.0.0';

	/**
	 * Where licence holders download the current Pro addon.
	 */
	const ACCOUNT_URL = 'https://mage-people.com/my-account/';

	/**
	 * User meta holding the Pro version whose notice the user dismissed.
	 */
	const DISMISS_META = 'apd_dismissed_outdated_pro';

	/**
	 * Query argument of the dismiss link.
	 */
	const DISMISS_ARG = 'apd_dismiss_outdated_pro';

	/**
	 * Detection result, memoised for the request. Null until first computed.
	 *
	 * @var array|false|null
	 */
	private $outdated = null;

	public function __construct() {
		add_action( 'admin_init', array( $this, 'maybe_dismiss' ) );
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * The active Pro addon, when it is older than MIN_PRO_VERSION.
	 *
	 * @return array|false Array with 'version', or false when Pro is current or not active.
	 */
	public function get_outdated_pro() {
		if ( null !== $this->outdated ) {
			return $this->outdated;
		}

		$this->outdated = false;

		// A 4.x-era addon is loaded: its own constant is authoritative.
		if ( defined( 'APD_PRO_VERSION' ) ) {
			if ( version_compare( APD_PRO_VERSION, self::MIN_PRO_VERSION, '<' ) ) {
				$this->outdated = array( 'version' => (string) APD_PRO_VERSION );
			}
			return $this->outdated;
		}

		// Older addons define no constant, so read the headers of the active plugins that
		// could be it. Only a handful of files match the slug filter, so this stays cheap.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $this->get_active_plugin_files() as $file ) {
			if ( APD_PLUGIN_BASENAME === $file || ! preg_match( '/partial-payment|deposit|mepp/i', $file ) ) {
				continue;
			}

			$path = WP_PLUGIN_DIR . '/' . $file;
			if ( ! is_readable( $path ) ) {
				continue;
			}

			$data   = get_plugin_data( $path, false, false );
			$vendor = $data['Author'] . ' ' . $data['AuthorURI'] . ' ' . $data['PluginURI'];

			$is_apd_pro = false !== stripos( $vendor, 'mage' )
				&& preg_match( '/partial payment|deposit/i', $data['Name'] )
				&& preg_match( '/\bpro\b/i', $data['Name'] );

			if ( $is_apd_pro && '' !== $data['Version'] && version_compare( $data['Version'], self::MIN_PRO_VERSION, '<' ) ) {
				$this->outdated = array( 'version' => $data['Version'] );
				break;
			}
		}

		return $this->outdated;
	}

	/**
	 * Plugin basenames active on this site, including network-activated ones.
	 *
	 * @return string[]
	 */
	private function get_active_plugin_files() {
		$files = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$files = array_merge( $files, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		return array_unique( array_filter( $files, 'is_string' ) );
	}

	/**
	 * Remember a dismissal for the Pro version that is installed now.
	 *
	 * Stored per version, so the notice returns if a different outdated build is installed.
	 */
	public function maybe_dismiss() {
		if ( empty( $_GET[ self::DISMISS_ARG ] ) ) {
			return;
		}

		check_admin_referer( self::DISMISS_ARG );

		$outdated = $this->get_outdated_pro();
		if ( $outdated && current_user_can( 'activate_plugins' ) ) {
			update_user_meta( get_current_user_id(), self::DISMISS_META, $outdated['version'] );
		}

		wp_safe_redirect( remove_query_arg( array( self::DISMISS_ARG, '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Print the notice.
	 */
	public function render() {
		$outdated = $this->get_outdated_pro();
		if ( ! $outdated ) {
			return;
		}

		$screen      = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$on_settings = $screen && 'toplevel_page_apd-deposits' === $screen->id;

		// The Deposits screen is where Pro looks missing, so it always explains why. Elsewhere
		// only users who can update plugins see it, and they may dismiss it.
		if ( ! $on_settings ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			if ( get_user_meta( get_current_user_id(), self::DISMISS_META, true ) === $outdated['version'] ) {
				return;
			}
		}

		// apd-keep-admin-notice survives the Deposits screen's notice filter; `inline` stops
		// WordPress from moving the notice inside that screen's custom header.
		$classes = 'notice notice-warning apd-keep-admin-notice' . ( $on_settings ? ' inline' : '' );
		?>
		<div class="<?php echo esc_attr( $classes ); ?>">
			<p><strong><?php esc_html_e( 'Advanced Partial Payment or Deposit for WooCommerce: your Pro addon needs an update', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></strong></p>
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: installed Pro addon version, 2: free plugin version, 3: minimum Pro addon version */
						__( 'You are using an old Pro addon (version %1$s) that does not work with version %2$s of the free plugin, so the Pro features are locked. Update the Pro addon to version %3$s or newer to unlock them.', 'advanced-partial-payment-or-deposit-for-woocommerce' ),
						$outdated['version'],
						APD_VERSION,
						self::MIN_PRO_VERSION
					)
				);
				?>
			</p>
			<p><?php esc_html_e( 'Log in to your MagePeople account, download the latest Pro addon, then install it over the old version (Plugins → Add New Plugin → Upload Plugin).', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::ACCOUNT_URL ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Download the latest Pro addon', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
				</a>
				<?php if ( ! $on_settings ) : ?>
					<a class="button button-link" href="<?php echo esc_url( wp_nonce_url( add_query_arg( self::DISMISS_ARG, '1' ), self::DISMISS_ARG ) ); ?>">
						<?php esc_html_e( 'Dismiss', 'advanced-partial-payment-or-deposit-for-woocommerce' ); ?>
					</a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}
