<?php

/*
 * Plugin Name:       WP Core Monitor
 * Plugin URI:        https://wcom.pro?utm_source=plugin
 * Description:       A lightweight debug tool that monitors calls to the wp_redirect() function to determine which plugins or themes are using redirection.
 * Version:           1.1.0
 * Author:            Maciej Bis
 * Author URI:        http://maciejbis.net/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wpcoremonitor
 */

/**
 * Main class
 */
class WPCoreMonitor {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.1.0';

	/**
	 * The class instance
	 *
	 * @var WPCoreMonitor
	 */
	protected static $instance = null;

	/**
	 * The plugin classes array
	 *
	 * @var array
	 */
	private $includes = [];

	/**
	 * The settings class instance
	 *
	 * @var WPCoreMonitor_Settings
	 */
	public $settings = null;

	/**
	 * The helper class instance
	 *
	 * @var WPCoreMonitor_Helpers
	 */
	public $helpers = null;

	/**
	 * Retrieve main WPCoreMonitor instance.
	 *
	 * Ensure only one instance is loaded or can be loaded.
	 *
	 * @return WPCoreMonitor
	 */
	public static function get() {
		if ( is_null( self::$instance ) && ! ( self::$instance instanceof WPCoreMonitor ) ) {
			self::$instance = new WPCoreMonitor();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Load the plugin.
	 */
	private function init() {
		// Define plugin constants.
		$this->declare_constants();

		// Include & instantiate required classes.
		$this->includes();

		// Localize the plugin
		add_action( 'init', array( $this, 'localize_me' ), 1 );

		$this->settings = $this->includes['settings'];
		$this->helpers  = $this->includes['helpers'];
	}

	/**
	 * Define the constants.
	 */
	private function declare_constants() {
		define( 'WPCOREMONITOR_VER', $this->version );
		define( 'WPCOREMONITOR_PLUGIN_FILE', __FILE__ );
		define( 'WPCOREMONITOR_PLUGIN_DIR', untrailingslashit( dirname( WPCOREMONITOR_PLUGIN_FILE ) ) );
		define( 'WPCOREMONITOR_PLUGIN_URL', untrailingslashit( plugins_url( '', WPCOREMONITOR_PLUGIN_FILE ) ) );
	}

	/**
	 * Include the plugin files.
	 */
	private function includes() {
		$classes = array(
			'core'    => array(
				'helpers'  => 'WPCoreMonitor_Helpers',
				'admin'    => 'WPCoreMonitor_Admin',
				'settings' => 'WPCoreMonitor_Settings',
			),
			'modules' => array(
				'redirects' => 'WPCoreMonitor_Redirects',
				'hooks'     => 'WPCoreMonitor_Hooks'
			)
		);

		// Load classes and set-up their instances
		foreach ( $classes as $class_type => $classes_array ) {
			foreach ( $classes_array as $class => $class_name ) {
				$filename = WPCOREMONITOR_PLUGIN_DIR . "/includes/{$class_type}/wpcoremonitor-{$class}.php";

				if ( file_exists( $filename ) ) {
					require_once $filename;
					if ( $class_name ) {
						$this->includes[ $class ] = new $class_name();
					}
				}
			}
		}
	}

	/**
	 * Localize this plugin
	 */
	function localize_me() {
		load_plugin_textdomain( 'wpcoremonitor', false, basename( WPCOREMONITOR_PLUGIN_DIR ) . "/languages" );
	}

}

/**
 * Returns the main instance of WP Core Monitor
 *
 * @return WPCoreMonitor
 */
function wp_core_monitor() {
	return WPCoreMonitor::get();
}
wp_core_monitor();