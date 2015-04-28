<?php
/**
* Plugin Name: WordPress Bookmarks
* Plugin URI:  http://dsgnwrks.pro
* Description: Host your bookmarks on WordPress
* Version:     0.1.0
* Author:      Jtsternberg
* Author URI:  http://dsgnwrks.pro
* Donate link: http://dsgnwrks.pro
* License:     GPLv2
* Text Domain: wordpress-bookmarks
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2015 Jtsternberg (email : justin@dsgnwrks.pro)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using generator-plugin-wp
 */


/**
 * Autoloads files with classes when needed
 * @since  0.1.0
 * @param  string $class_name Name of the class being requested
 */
function wordpress_bookmarks_autoload_classes( $class_name ) {
	if ( class_exists( $class_name, false ) || false === stripos( $class_name, 'WPB_' ) ) {
		return;
	}
	$filename = strtolower( str_ireplace(
		array( 'WPB_', '_' ),
		array( '', '-' ),
		$class_name
	) );

	WordPress_Bookmarks::include_file( $filename );
}
spl_autoload_register( 'wordpress_bookmarks_autoload_classes' );


/**
 * Main initiation class
 */
class WordPress_Bookmarks {

	const VERSION = '0.1.0';

	protected $url      = '';
	protected $path     = '';
	protected $basename = '';
	protected static $single_instance = null;

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return WordPress_Bookmarks A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin
	 * @since  0.1.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );

		$instance->plugin_classes();
		$instance->hooks();
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 * @since 0.1.0
	 */
	function plugin_classes() {
		// Attach other plugin classes to the base plugin class.
		// $this->admin = new WPB_Admin( $this );
	}

	/**
	 * Add hooks and filters
	 * @since 0.1.0
	 */
	public function hooks() {
		register_activation_hook( __FILE__, array( $this, '_activate' ) );
		register_deactivation_hook( __FILE__, array( $this, '_deactivate' ) );

		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Activate the plugin
	 * @since  0.1.0
	 */
	function _activate() {
		// Make sure any rewrite functionality has been loaded
		flush_rewrite_rules();
	}

	/**
	 * Deactivate the plugin
	 * Uninstall routines should be in uninstall.php
	 * @since  0.1.0
	 */
	function _deactivate() {}

	/**
	 * Init hooks
	 * @since  0.1.0
	 * @return null
	 */
	public function init() {
		if ( $this->check_requirements() ) {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'wordpress-bookmarks' );
			load_textdomain( 'wordpress-bookmarks', WP_LANG_DIR . '/wordpress-bookmarks/wordpress-bookmarks-' . $locale . '.mo' );
			load_plugin_textdomain( 'wordpress-bookmarks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	}

	/**
	 * Check that all plugin requirements are met
	 * @since  0.1.0
	 * @return boolean
	 */
	public static function meets_requirements() {
		// Do checks for required classes / functions
		// function_exists('') & class_exists('')

		// We have met all requirements
		return true;
	}

	/**
	 * Check if the plugin meets requirements and
	 * disable it if they are not present.
	 * @since  0.1.0
	 * @return boolean result of meets_requirements
	 */
	public function check_requirements() {
		if ( ! $this->meets_requirements() ) {
			// Display our error
			echo '<div id="message" class="error">';
			echo '<p>' . sprintf( __( 'WordPress Bookmarks is missing requirements and has been <a href="%s">deactivated</a>. Please make sure all requirements are available.', 'wordpress-bookmarks' ), admin_url( 'plugins.php' ) ) . '</p>';
			echo '</div>';
			// Deactivate our plugin
			deactivate_plugins( $this->basename );

			return false;
		}

		return true;
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.1.0
	 * @param string $field
	 * @throws Exception Throws an exception if the field is invalid.
	 * @return mixed
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'basename':
			case 'url':
			case 'path':
				return $this->$field;
			default:
				throw new Exception( 'Invalid '. __CLASS__ .' property: ' . $field );
		}
	}

	/**
	 * Include a file from the includes directory
	 * @since  0.1.0
	 * @param  string $filename Name of the file to be included
	 */
	public static function include_file( $filename ) {
		$file = self::dir( 'includes/'. $filename .'.php' );
		if ( file_exists( $file ) ) {
			return include_once( $file );
		}
	}
}

/**
 * Grab the WordPress_Bookmarks object and return it.
 * Wrapper for WordPress_Bookmarks::get_instance()
 */
function wordpress_bookmarks() {
	return WordPress_Bookmarks::get_instance();
}

// Kick it off
wordpress_bookmarks();
