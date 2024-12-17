<?php

/**
 * Class WPCoreMonitor_Admin
 */
class WPCoreMonitor_Admin {

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add settings page to the "Tools" section.
	 */
	public function add_settings_page() {
		add_submenu_page( 'tools.php', __( 'WP Core Monitor', 'wpcoremonitor' ), __( 'WP Core Monitor', 'wpcoremonitor' ), 'manage_options', 'wpcoremonitor', array( $this, 'admin_page_content' ) );
	}

	/**
	 * Enqueue CSS & JS assets for the admin page.
	 *
	 * @return void
	 */
	function enqueue_assets() {
		$current_screen = get_current_screen();

		// Check if we are on the 'tools_page_wpcoremonitor_settings' page
		if ( $current_screen && $current_screen->id == 'tools_page_wpcoremonitor' ) {
			wp_enqueue_script( 'wp-element' );
			wp_enqueue_script( 'wp-components' );
			wp_enqueue_script( 'wp-editor' );
			wp_enqueue_style( 'wp-components' );

			wp_enqueue_script( 'wpcoremonitor-admin', WPCOREMONITOR_PLUGIN_URL . '/assets/wpcoremonitor-admin.js', array( 'wp-element', 'wp-components', 'wp-editor' ), WPCOREMONITOR_VER, true );
			wp_enqueue_style( 'wpcoremonitor-admin', WPCOREMONITOR_PLUGIN_URL . '/assets/wpcoremonitor-admin.css', array(), WPCOREMONITOR_VER );

			wp_localize_script( 'wpcoremonitor-admin', 'wpcoremonitor', array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'hooks-scanner-nonce' )
			) );
		}
	}

	/**
	 * Display the settings page content
	 */
	public function admin_page_content() {
		$tabs = apply_filters( 'wpcoremonitor_admin_tabs', array() );

		$default_tab = ( is_array( $tabs ) && count( $tabs ) > 1 ) ? array_keys( array_slice( $tabs, 0, 1, true ) )[0] : '';
		//phpcs:disable WordPress.Security.NonceVerification.Recommended
		$active_tab  = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $default_tab;
		?>
        <div class="wrap wcom">
            <h2><?php echo esc_html__( 'WP Core Monitor', 'wpcoremonitor' ); ?></h2>

            <div id="wcom-tabs">
                <div class="nav-tab-wrapper">
					<?php
					foreach ( $tabs as $tab_id => $tab ) {
						$active_tab_class = ( $active_tab === $tab_id ) ? 'nav-tab-active' : '';
						printf( '<a href="#%1$s" class="nav-tab %2$s" data-tab="%1$s">%3$s</a>', esc_attr( $tab_id ), esc_attr( $active_tab_class ), esc_html( $tab['title'] ) );
					}
					?>
                </div>

				<?php
				foreach ( $tabs as $tab_id => $tab ) {
					printf( '<div id="%s" class="wcom-tab-content">', esc_attr( $tab_id ) );

					if ( is_callable( $tab['content_callback'] ) ) {
						call_user_func( $tab['content_callback'] );
					}

					echo '</div>';
				}
				?>
            </div>
        </div>
		<?php
	}
}
