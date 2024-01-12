<?php

/*
 * Plugin Name: WP Core Monitor
 * Description: A debugging tool to track the origin of the wp_redirect()
 * Version:     0.9.0
 * Author:      Maciej Bis
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

class WPCoreMonitor {

	public function __construct() {
		add_filter( 'wp_redirect', array( $this, 'debug_wp_redirect' ), 10, 2 );
	}

	/**
	 * Generate a table displaying the backtrace information.
	 *
	 * @return string HTML table
	 */
	public function get_debug_backtrace_table() {
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

		if ( count( $backtrace ) > 4 ) {
			$backtrace = array_slice( $backtrace, 4 );

			$table = sprintf( '<h3>%s</h3>', __( 'Backtrace', 'permalink-manager' ) );
			$table .= sprintf( '<table class="backtrace-table"><thead><tr><th>%s</th><th>%s</th><th>%s</th></tr></thead><tbody>', __( 'Function', 'permalink-manager' ), __( 'Source', 'permalink-manager' ), __( 'File', 'permalink-manager' ) );

			foreach ( $backtrace as $trace ) {
				if ( isset( $trace['function'], $trace['line'] ) ) {
					$backtrace_info = $this->get_backtrace_info( $trace );

					$table .= sprintf( '<tr> <td>%s</td> <td>%s</td> <td>%s</td> </tr>', $backtrace_info['function'], $backtrace_info['source'], $backtrace_info['path'] );
				}
			}

			$table .= '</tbody></table>';
		}

		return ( ! empty( $table ) ) ? $table : '';
	}

	/**
	 * Extract relevant backtrace information.
	 *
	 * @param array $trace
	 *
	 * @return array Backtrace details like function, source, name, and path.
	 */
	public function get_backtrace_info( $trace ) {
		$plugins_dir = WP_PLUGIN_DIR;
		$themes_dir  = get_theme_root();

		if ( isset( $trace['file'] ) ) {
			$function      = esc_html( $trace['function'] );
			$file_path     = $trace['file'];
			$file_path_rel = str_replace( ABSPATH, '', $file_path );
			$file_path_rel .= ( ! empty( $trace['line'] ) ) ? ":{$trace['line']}" : '';

			if ( strpos( $file_path, $plugins_dir ) === 0 ) {
				$source = sprintf( '<strong>%s</strong><br /> %s', __( 'Plugin', 'permalink-manager' ), $this->get_plugin_name( $file_path ) );
			} else if ( strpos( $file_path, $themes_dir ) === 0 ) {
				$source = sprintf( '<strong>%s</strong>:', __( 'Theme', 'permalink-manager' ) );
			} else {
				$source = sprintf( '<strong>%s</strong>', __( 'WordPress Core', 'permalink-manager' ) );
			}
		}

		return array(
			'function' => ( ! empty( $function ) ) ? $function : __( 'n/a', 'permalink-manager' ),
			'source'   => ( ! empty( $source ) ) ? $source : __( 'n/a', 'permalink-manager' ),
			'name'     => ( ! empty( $name ) ) ? $name : '',
			'path'     => ( ! empty( $file_path_rel ) ) ? $file_path_rel : '',
		);
	}

	/**
	 * Get the plugin name from the file path.
	 *
	 * @param string $file_path Plugin file path.
	 *
	 * @return string Plugin name.
	 */
	public function get_plugin_name( $file_path ) {
		$plugins_dir   = WP_PLUGIN_DIR;
		$relative_path = str_replace( $plugins_dir, '', $file_path );
		$plugin_parts  = explode( DIRECTORY_SEPARATOR, $relative_path );

		if ( isset( $plugin_parts[1] ) ) {
			return $this->get_plugin_name_by_dir( $plugin_parts[1] );
		} else {
			return $file_path;
		}
	}

	/**
	 * Get the plugin name by its path.
	 *
	 * @param string $plugin_dir
	 *
	 * @return string Plugin name
	 */
	function get_plugin_name_by_dir( $plugin_dir ) {
		$plugins = get_plugins();

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			// Check if the plugin directory matches the provided directory name
			if ( substr( $plugin_file, 0, strlen( $plugin_dir ) ) === $plugin_dir ) {
				return $plugin_data['Name'];
			}
		}

		return '';
	}

	/**
	 * JavaScript for redirect countdown.
	 *
	 * @return string
	 */
	public function debug_wp_redirect_js() {
		return '<script>
                var countdownInterval;
                function customRedirect(count) {
                    document.getElementById("countdown").innerText = count;

                    if (count > 0) {
                        countdownInterval = setTimeout(function() { customRedirect(count - 1); }, 1000);
                    } else {
                        var redirectLink = document.getElementById("redirect-link");
                        var targetUrl = redirectLink.getAttribute("data-url");
                        window.location.href = targetUrl;
                    }
                }
                customRedirect(10); // Start the countdown

                function stopRedirect() {
                    clearTimeout(countdownInterval);
                }
              </script>';
	}

	/**
	 * Basic CSS for styling the backtrace table.
	 *
	 * @return string
	 */
	public function debug_wp_redirect_css() {
		return '<style>
                .backtrace-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    font-size: 90%;
                }

                .backtrace-table th, .backtrace-table td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                }

                .backtrace-table th {
                    background-color: #f2f2f2;
                }
              </style>';
	}

	/**
	 * Handle the wp_redirect action and display the debug information
	 *
	 * @param string $location Redirect location.
	 * @param int    $status   HTTP response status code.
	 */
	public function debug_wp_redirect( $location, $status = 302 ) {
		if ( is_admin() ) {
			return $location;
		}

		$html = sprintf( '<h2>%2$s <span id="countdown">10</span> %3$s</h2>
			<h3 id="redirect-link" data-url="%1$s">%4$s:</h3>
			<pre><code>%1$s</code></pre>
			<p><a href="%1$s" class="button">%5$s</a> <a href="#" onclick="stopRedirect()" class="button">%6$s</a></p>',
			esc_url( $location ),
			__( 'Redirecting in', 'permalink-manager' ),
			__( 'seconds...', 'permalink-manager' ),
			__( 'Target URL', 'permalink-manager' ),
			__( 'Redirect now', 'permalink-manager' ),
			__( 'Stop redirect', 'permalink-manager' )
		);

		$html .= $this->get_debug_backtrace_table();
		$html .= $this->debug_wp_redirect_js();
		$html .= $this->debug_wp_redirect_css();

		// Output the HTML content
		wp_die( $html, __( 'Redirecting...', 'permalink-manager' ), array( 'response' => $status ) );
	}
}
new WPCoreMonitor();