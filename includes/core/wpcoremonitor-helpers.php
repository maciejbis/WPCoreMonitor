<?php

/**
 * Class WPCoreMonitor_Helpers
 */
class WPCoreMonitor_Helpers {

	/**
	 * Generate a debug backtrace table.
	 *
	 * @param array $data_array
	 * @param array $columns
	 *
	 * @return string The generated HTML table.
	 */
	function generate_debug_table( $data_array, $columns ) {
		$table = '<table class="backtrace-table"><thead><tr>';

		foreach ( $columns as $column ) {
			$table .= sprintf( "<th>%s</th>", $column['heading'] );
		}

		$table .= '</tr></thead><tbody>';

		if ( is_array( $data_array ) ) {
			foreach ( $data_array as $trace ) {
				$row = array();
				foreach ( $columns as $column ) {
					$key = $column['key'];

					if ( isset( $trace[ $key ] ) ) {
						$row[] = $trace[ $key ];
					} else {
						$row[] = '';
					}
				}

				$table .= ( ! empty( $row ) ) ? sprintf( "<tr><td>%s</td></tr>", implode( '</td><td>', $row ) ) : '';
			}
		}

		$table .= '</tbody></table>';

		return $table;
	}

	/**
	 * Generate a table displaying the backtrace information.
	 *
	 * @return string HTML table
	 */
	public function get_debug_backtrace_table() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );

		if ( count( $backtrace ) > 4 ) {
			$backtrace = array_slice( $backtrace, 4 );

			foreach ( $backtrace as &$trace ) {
				if ( isset( $trace['function'], $trace['line'] ) ) {
					$trace = $this->get_backtrace_info( $trace );
				} else {
					$trace = array();
				}
			}

			$columns = array(
				array( 'heading' => __( 'Function', 'wpcoremonitor' ), 'key' => 'function' ),
				array( 'heading' => __( 'Source', 'wpcoremonitor' ), 'key' => 'source' ),
				array( 'heading' => __( 'File', 'wpcoremonitor' ), 'key' => 'path' ),
			);

			$table = $this->generate_debug_table( $backtrace, $columns );
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
				$source = sprintf( '<strong>%s</strong><br /> %s', __( 'Plugin', 'wpcoremonitor' ), $this->get_plugin_name( $file_path ) );
			} else if ( strpos( $file_path, $themes_dir ) === 0 ) {
				$source = sprintf( '<strong>%s</strong>:', __( 'Theme', 'wpcoremonitor' ) );
			} else {
				$source = sprintf( '<strong>%s</strong>', __( 'WordPress Core', 'wpcoremonitor' ) );
			}
		}

		return array(
			'function' => ( ! empty( $function ) ) ? $function : __( 'n/a', 'wpcoremonitor' ),
			'source'   => ( ! empty( $source ) ) ? $source : __( 'n/a', 'wpcoremonitor' ),
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
	public function get_js_output( $file = 'wpcoremonitor' ) {
		$js_file_path = sprintf( "%s/assets/%s.js", WPCOREMONITOR_PLUGIN_DIR, $file );

		if ( file_exists( $js_file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$js = '<script>' . file_get_contents( $js_file_path ) . '</script>';
		} else {
			$js = '';
		}

		return $js;
	}

	/**
	 * Basic CSS for styling the backtrace table.
	 *
	 * @return string
	 */
	public function get_css_output( $file = 'wpcoremonitor' ) {
		$css_file_path = sprintf( "%s/assets/%s.css", WPCOREMONITOR_PLUGIN_DIR, $file );

		if ( file_exists( $css_file_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$css = sprintf( '<style>%s</style>', file_get_contents( $css_file_path ) );
		} else {
			$css = '';
		}

		return $css;
	}

}