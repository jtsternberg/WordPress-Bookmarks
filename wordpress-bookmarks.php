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

	protected $url           = '';
	protected $path          = '';
	protected $basename      = '';
	protected $cli           = null;
	protected $pressthis     = null;
	protected $forward_links = null;
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

		$this->plugin_classes();
		$this->hooks();
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 * @since 0.1.0
	 */
	function plugin_classes() {
		if ( defined('WP_CLI') && WP_CLI ) {
			$this->cli = new WPB_Chrome_Bookmarks_CLI();
			WP_CLI::add_command( 'chrome_bookmarks', 'WPB_Chrome_Bookmarks_CLI' );
		}

		$this->pressthis     = new WPB_Press_This();
		$this->forward_links = new WPB_Forward_Links();
	}

	/**
	 * Add hooks and filters
	 * @since 0.1.0
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ) );
		$this->pressthis->hooks();
		$this->forward_links->hooks();
	}

	/**
	 * Init hooks
	 * @since  0.1.0
	 * @return null
	 */
	public function init() {
		load_plugin_textdomain( 'wordpress-bookmarks', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
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
			case 'cli':
			case 'pressthis':
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

	/**
	 * This plugin's directory
	 * @since  0.1.0
	 * @param  string $path (optional) appended path
	 * @return string       Directory and path
	 */
	public static function dir( $path = '' ) {
		static $dir;
		$dir = $dir ? $dir : trailingslashit( dirname( __FILE__ ) );
		return $dir . $path;
	}

	/**
	 * This plugin's url
	 * @since  0.1.0
	 * @param  string $path (optional) appended path
	 * @return string       URL and path
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );
		return $url . $path;
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
