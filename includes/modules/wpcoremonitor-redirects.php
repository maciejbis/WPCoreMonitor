<?php

class WPCoreMonitor_Redirects {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 0 );
	}

	/**
	 * Declare the hook
	 */
	public function init() {
		add_filter( 'wp_redirect', array( $this, 'debug_wp_redirect' ), 1, 2 );
	}

	/**
	 * Handle the wp_redirect action and display the debug information
	 *
	 * @param string $location Redirect location.
	 * @param int $status HTTP response status code.
	 */
	public function debug_wp_redirect( $location, $status = 302 ) {
		if ( is_admin() ) {
			return $location;
		}

		// Check if debug redirect is turned on
		$debug_redirect_mode = (int) wp_core_monitor()->settings->get_option( 'debug_redirect_mode' );

		// Check user role
		$allowed_role = wp_core_monitor()->settings->get_option( 'user_role_access' );

		if ( ! $debug_redirect_mode || ( $allowed_role !== 'all' && ! current_user_can( $allowed_role ) ) ) {
			return $location;
		}

		// Do not display Query Monitor debug data
		do_action( 'qm/cease' );

		$html = sprintf( '<h2>%2$s <span id="countdown">10</span> %3$s</h2>
			<h3 id="redirect-link" data-url="%1$s">%4$s:</h3>
			<pre><code>%1$s</code></pre>
			<p><a href="%1$s" class="button">%5$s</a> <a href="#" onclick="stopRedirect()" class="button">%6$s</a></p>', esc_url( $location ), __( 'Redirecting in', 'wpcoremonitor' ), __( 'seconds...', 'wpcoremonitor' ), __( 'Target URL', 'wpcoremonitor' ), __( 'Redirect now', 'wpcoremonitor' ), __( 'Stop redirect', 'wpcoremonitor' ) );

		$html .= wp_core_monitor()->helpers->get_debug_backtrace_table();

		$html .= sprintf( '<p class="security-alert text-muted text-center">%s</p>', __( 'If you are an administrator, please disable <strong>WP Core Monitor</strong> plugin once you have completed troubleshooting the redirect.', 'wpcoremonitor' ) );

		$html .= wp_core_monitor()->helpers->get_js_output();
		$html .= wp_core_monitor()->helpers->get_css_output();

		// Output the HTML content
		wp_die( $html, __( 'Redirecting...', 'wpcoremonitor' ), array( 'response' => $status ) );
	}

}