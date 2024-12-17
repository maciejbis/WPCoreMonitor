<?php

/**
 * Class WPCoreMonitor_Hooks
 *
 * Find the WordPress hooks in plugins and themes.
 */
class WPCoreMonitor_Hooks {
	/**
	 * Number of files to process per batch.
	 *
	 * @var int
	 */
	private $batch_size = 25;

	/**
	 * The tab ID
	 *
	 * @var string
	 */
	private $tab_id = 'hooks';

	public function __construct() {
		add_action( 'wp_ajax_wcom_process_hooks_batch', array( $this, 'ajax_process_batch' ) );
		add_filter( 'wpcoremonitor_admin_tabs', array( $this, 'register_tab' ), 10 );
		add_filter( 'plugin_row_meta', array( $this, 'plugins_quick_link' ), 25, 4 );
	}

	/**
	 * Add quick links for "Plugins" admin dashboard
	 *
	 * @param $links
	 * @param $plugin_file_name
	 * @param $plugin_data
	 * @param $status
	 *
	 * @return mixed
	 */
	function plugins_quick_link( $links, $plugin_file_name, $plugin_data, $status ) {
		$link_url = add_query_arg( array(
			'tab'          => 'hooks',
			'dir-selector' => $plugin_file_name,
		), menu_page_url( 'wpcoremonitor', false ) );

		$links[] = sprintf( "<a href=\"%s\">%s</a>", esc_attr( $link_url ), __( 'Scan for hooks', 'wpcoremonitor' ) );

		return $links;
	}

	/**
	 * Register tab for admin dashboard.
	 *
	 * @param $tabs
	 *
	 * @return void
	 */
	public function register_tab( $tabs ) {
		$tabs[ $this->tab_id ] = array(
			'title'            => __( 'Hooks Scanner', 'wpcoremonitor' ),
			'content_callback' => array( $this, 'tab_content' )
		);

		return $tabs;
	}

	/**
	 * Outputs the content for the hooks scanner tab.
	 *
	 * @return void
	 */
	public function tab_content() {
		?>
        <div id="wcom-hooks-scanner" class="wcom-hooks-scanner">
            <h2><?php esc_html_e( 'Hooks Scanner', 'wpcoremonitor' ); ?></h2>

            <div class="wcom-hooks-scanner-header">
                <form id="wcom-hooks-scanner-controls" method="POST">
                    <label for="dir-selector"><?php esc_html_e( 'Select plugin/theme:', 'wpcoremonitor' ); ?></label>
                    <select name="dir-selector" id="dir-selector">
						<?php
						$plugins = get_plugins();
						$themes  = wp_get_themes();

						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
						$selected = ( ! empty( $_GET['dir-selector'] ) ) ? esc_attr( wp_unslash( $_GET['dir-selector'] ) ) : '';

						printf( '<optgroup label="%s">', esc_attr__( 'Plugins', 'wpcoremonitor' ) );
						foreach ( $plugins as $plugin_key => $this_plugin ) {
							$plugin_name = esc_attr( $this_plugin['Name'] );
							$plugin_key  = esc_attr( $plugin_key );
							echo sprintf( "\n\t<option data-type=\"plugin\" %s value=\"%s\">%s</option>", selected( $selected, $plugin_key, false ), esc_attr( $plugin_key ), esc_attr( $plugin_name ) );
						}
						echo '</optgroup>';

						printf( '<optgroup label="%s">', esc_attr__( 'Themes', 'wpcoremonitor' ) );
						foreach ( $themes as $theme_key => $this_theme ) {
							$theme_name = esc_attr( $this_theme['Name'] );
							$theme_key  = esc_attr( $theme_key );
							echo sprintf( "\n\t<option data-type=\"theme\" %s value=\"%s\">%s</option>", selected( $selected, $theme_key, false ), esc_attr( $theme_key ), esc_attr( $theme_name ) );
						}
						echo '</optgroup>';

						?>
                    </select>
					<?php submit_button( __( 'Start Scan', 'wpcoremonitor' ), 'primary', 'Start Scan', false, array( 'id' => 'start-scan' ) ); ?>
                </form>

                <div class="hooks-scanner-progress" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width: 0"></div>
                    </div>
                    <div class="progress-text">0%</div>
                </div>
            </div>

            <div class="hooks-scanner-results" style="display: none;">
                <h2 class="hooks-scanner-heading">Found Hooks</h2>
                <div class="results-content"></div>
            </div>
        </div>
		<?php
	}

	/**
	 * Handles the AJAX request for processing found files.
	 *
	 * @return void
	 */
	public function ajax_process_batch() {
		check_ajax_referer( 'hooks-scanner-nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'wpcoremonitor' ) );
		}

		if ( empty( $_POST['scan_id'] ) ) {
			if ( empty( $_POST['scan_dir'] ) || empty( $_POST['scan_type'] ) ) {
				wp_send_json_error( __( 'Plugin/theme not found', 'wpcoremonitor' ) );
			}

			$scan_meta = array(
				'dir'  => sanitize_text_field( wp_unslash( $_POST['scan_dir'] ) ),
				'type' => sanitize_text_field( wp_unslash( $_POST['scan_type'] ) )
			);

			$files = $this->collect_php_files( $scan_meta['dir'], $scan_meta['type'] );
			if ( $files === false ) {
				wp_send_json_error( __( 'Plugin/theme files not found', 'wpcoremonitor' ) );
			}

			$batches = array_chunk( $files, $this->batch_size );

			$scan_id     = uniqid( 'wcom_hooks_scan_' );
			$batch_index = 0;
			$processed   = 0;
			$total       = count( $files );

			set_transient( $scan_id . '_batches', $batches, HOUR_IN_SECONDS );
			set_transient( $scan_id . '_meta', $scan_meta, HOUR_IN_SECONDS );
		} else if ( isset( $_POST['batch_index'] ) && isset( $_POST['processed_files'] ) && isset( $_POST['total_files'] ) ) {
			$scan_id = sanitize_text_field( wp_unslash( $_POST['scan_id'] ) );

			$batch_index = intval( $_POST['batch_index'] );
			$processed   = intval( $_POST['processed_files'] );
			$total       = intval( $_POST['total_files'] );

			$scan_meta = get_transient( $scan_id . '_meta' );
			$batches   = get_transient( $scan_id . '_batches' );
		} else {
			wp_send_json_error( __( 'Missing POST data', 'wpcoremonitor' ) );

			return;
		}

		if ( ! $batches || ! isset( $batches[ $batch_index ] ) || empty( $scan_meta['type'] ) || empty( $scan_meta['dir'] ) ) {
			wp_send_json_error( __( 'Invalid batch', 'wpcoremonitor' ) );
		}

		$batch_files = $batches[ $batch_index ];
		$results     = array();

		foreach ( $batch_files as $file ) {
			$results[] = $this->scan_file( $file, $scan_meta['dir'], $scan_meta['type'] );
			$processed ++;
		}

		wp_send_json_success( array(
			'scan_id'         => $scan_id,
			'batch_index'     => $batch_index,
			'total_batches'   => count( $batches ),
			'processed_files' => $processed,
			'total_files'     => $total,
			'found_hooks'     => array_filter( $results )
		) );
	}

	/**
	 * Identify PHP files from the specified source theme/plugin directory.
	 *
	 * @param string $source_file Path to the plugin/theme.
	 * @param string $scan_type Type of scan (plugin|theme).
	 *
	 * @return array|false List of PHP files or false on failure.
	 */
	private function collect_php_files( $source_file, $scan_type = '' ) {
		$files = [];

		if ( $scan_type === 'plugin' ) {
			$source_path = WP_CONTENT_DIR . '/plugins/' . $source_file;
			$source_dir  = dirname( $source_path );
		} else if ( $scan_type == 'theme' ) {
			$source_dir = WP_CONTENT_DIR . '/themes/' . $source_file;
		} else {
			return $files;
		}

		// Single plugin file
		if ( ! empty( $source_path ) && strpos( $source_file, '/' ) === false ) {
			if ( file_exists( $source_path ) && pathinfo( $source_path, PATHINFO_EXTENSION ) === 'php' ) {
				$files[] = $source_path;
			}
		} // Plugin/theme directory
        elseif ( is_dir( $source_dir ) ) {
			try {
				$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $source_dir, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS ), RecursiveIteratorIterator::SELF_FIRST );

				foreach ( $iterator as $file ) {
					if ( $file->isFile() && $file->getExtension() === 'php' ) {
						$files[] = $file->getPathname();
					}
				}
			} catch ( Exception ) {
				return false; // Return false on failure to handle exceptions gracefully
			}
		} else {
			return false; // Invalid plugin file or directory
		}

		return $files;
	}

	/**
	 * Scan a PHP file for hooks by extracting do_action/apply_filters calls.
	 *
	 * @param string $file Path to the PHP file to scan.
	 * @param string $scan_dir
	 * @param string $scan_type
	 *
	 * @return array Array of found hooks categorized as actions or filters.
	 */
	private function scan_file( $file, $scan_dir, $scan_type ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content  = file_get_contents( $file ); // Read the entire file content
		$results  = array();
		$rel_path = preg_replace( '/(.*wp-content\/(?:plugins|themes))\/(.*)/', '$2', $file );

		// Regex to match single-line and multi-line do_action/apply_filters calls
		$pattern = '/(do_action|apply_filters)\s*\((?:[^\(\)]*|\([^\)]*\))*\)/';

		// Find all matches
		preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE );

		foreach ( $matches[0] as $match ) {
			$full_match = $match[0]; // The full match of do_action or apply_filters
			$offset     = $match[1]; // Byte offset of the match in the file

			// Determine the line number from the offset
			$lines_up_to_offset = substr( $content, 0, $offset );
			$line_number        = substr_count( $lines_up_to_offset, "\n" ) + 1;
			$line_content       = esc_html( preg_replace( '/\s+/', ' ', $full_match ) );

			// Classify the match as an action or filter
			if ( strpos( $full_match, 'do_action' ) !== false ) {
				$results[ $rel_path ]['actions'][] = array(
					'line'    => $line_number,
					'content' => $line_content,
				);
			} elseif ( strpos( $full_match, 'apply_filters' ) !== false ) {
				$results[ $rel_path ]['filters'][] = array(
					'line'    => $line_number,
					'content' => $line_content,
				);
			}
		}

		// Add extra meta data
		if ( ! empty( $results ) ) {
			if ( $scan_type == 'theme' ) {
				$edit_url = add_query_arg( array(
					'theme' => preg_replace( '/^([^\/]+)\/(.*)/', '$1', $rel_path ), // Get the theme name
					'file'  => preg_replace( '/^([^\/]+)\/(.*)/', '$2', $rel_path ), // Get rid of the theme name (top dir)
				), admin_url( 'theme-editor.php' ) );
			} else {
				$edit_url = add_query_arg( array(
					'plugin' => $scan_dir,
					'file'   => $rel_path,
				), admin_url( 'plugin-editor.php' ) );
			}

			$results[ $rel_path ]['meta']['editURL'] = esc_js( $edit_url );
		}

		return $results;
	}

}