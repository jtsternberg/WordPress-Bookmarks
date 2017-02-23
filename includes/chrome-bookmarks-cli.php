<?php
/**
 * WordPress Bookmarks Chrome_bookmarks_cli
 * @version 0.1.0
 * @package WordPress Bookmarks
 */

class WPB_Chrome_Bookmarks_CLI {

	/**
	 * The default path to the bookmarks json file on OSX
	 *
	 * @var string
	 */
	public $default_chrome_bookmark_path = '/Users/%s/Library/Application Support/Google/Chrome/Default/Bookmarks';

	/**
	 * Whether current script output should be verbose
	 *
	 * @var boolean
	 */
	public $verbose = false;

	/**
	 * Whether to log when a post exists already
	 *
	 * @var boolean
	 */
	public $log_existing = false;

	/**
	 * Depth of category looping. Helps determine category parents, etc
	 *
	 * @var integer
	 */
	public $depth = 0;

	/**
	 * A count of the number of bookmarks to be imported
	 *
	 * @var integer
	 */
	public $bookmarks_to_import = 0;

	/**
	 * A count of the number of categories to be imported
	 *
	 * @var integer
	 */
	public $categories_to_import = 0;

	/**
	 * A count of the number of bookmarks that have been imported
	 *
	 * @var integer
	 */
	public $bookmarks_imported = 0;

	/**
	 * A count of the number of bookmarks that have been skipped for some reason
	 *
	 * @var integer
	 */
	public $bookmarks_skipped = 0;

	/**
	 * A count of the number of terms that have been imported
	 *
	 * @var integer
	 */
	public $terms_imported = 0;

	/**
	 * A count of the number of terms that have been skipped for some reason
	 *
	 * @var integer
	 */
	public $terms_skipped = 0;

	/**
	 * A count of the number of bookmarks wich were processed.
	 * Should match self::$bookmarks_to_import when complete
	 *
	 * @var integer
	 */
	public $bookmarks_processed = 0;

	/**
	 * Array of categories to set on a bookmark
	 *
	 * @var array
	 */
	public $categories_to_set = array();

	/**
	 * Instance of WP_CLI Progress Bar
	 *
	 * @see progress_bar()
	 * @var null
	 */
	public $progress_bar = null;

	/**
	 * CAUTION! Deletes ALL categories if a chrome bookmark post is found
	 * as well as all bookmark posts
	 *
	 * @subcommand delete_all
	 */
	public function delete_all() {
		global $blog_id;

		$details = get_blog_details( $blog_id );

		$proceed = \cli\choose( sprintf( __( "Delete all bookmark posts and ALL categories for the site at %s " ), isset( $details->siteurl ) ? $details->siteurl : 'unknown' ) );

		if ( 'y' !== strtolower( $proceed ) ) {
			$this->error_exit( 'Bailing' );
		}

		$posts = get_posts( array(
			'posts_per_page' => -1,
			'no_paging'      => true,
			'fields'         => 'ids',
			'meta_key'       => 'chrome_date_added',
		) );

		if ( empty( $posts ) ) {
			$this->error_exit( 'No Chrome bookmark posts found to delete.' );
		}

		$terms = get_terms( 'category', array(
			'hide_empty' => false,
			'fields'     => 'ids',
		) );

		if ( count( $terms ) > 1 ) {
			$this->inline_message( 'delete $terms' );
			$this->progress_bar( count( $terms ), 'terms', 'Deleting' );

			$count = 0;
			$deleted = array( 'true' => array(), 'false' => array() );
			foreach ( $terms as $term ) {
				$count++;
				if ( wp_delete_term( $term, 'category' ) ) {
					$deleted['true'][] = $term;
				} else {
					$deleted['false'][] = $term;
				}

				if ( ( $count > 1 ) && 0 == ( ( $count % 100 ) ) ) {
					if ( ! empty( $deleted['false'] ) ) {
						$this->inline_message( 'failed deleting: ' . print_r( $deleted['false'], true ) );
					}
					$deleted = array( 'true' => array(), 'false' => array() );
					self::stop_the_insanity( .25 );
				}

				$this->progress_bar( 'tick' );
			}

			$this->progress_bar( 'finish' );
			$this->inline_message( '$terms deleted' );

			self::stop_the_insanity( 2 );
		}

		$this->inline_message( 'delete $posts' );
		$this->progress_bar( count( $posts ), 'posts', 'Deleting' );

		$count = 0;
		$deleted = array( 'true' => array(), 'false' => array() );
		foreach ( $posts as $post ) {
			$count++;
			if ( wp_delete_post( $post, true ) ) {
				$deleted['true'][] = $post;
			} else {
				$deleted['false'][] = $post;
			}

			if ( ( $count > 1 ) && 0 == ( ( $count % 100 ) ) ) {
				if ( ! empty( $deleted['false'] ) ) {
					$this->inline_message( 'failed deleting: ' . print_r( $deleted['false'], true ) );
				}
				$deleted = array( 'true' => array(), 'false' => array() );
				self::stop_the_insanity( .25 );
			}

			$this->progress_bar( 'tick' );
		}

		$this->progress_bar( 'finish' );
		$this->success_exit( '$posts deleted' );
	}

	/**
	 * Stop an in-progress import
	 *
	 * @subcommand stop_import
	 */
	public function stop_import( $args, $assoc_args ) {
		if ( ! $this->check_if_importing() ) {
			$this->error_exit( 'No import currently in progress.' );
		}

		delete_option( 'chrome_bookmarks_importing' );

		$this->success_exit( 'Import stopped.' );
	}

	/**
	 * Import Chrome bookmarks to WordPress
	 *
	 * ## OPTIONS
	 *
	 * [--osx_user=<osx_user>]
	 * : If on OSX, provide your OSX username to detect the location of the bookmark json file
	 *
	 * [--bookmarks_json_path=<bookmarks_json_path>]
	 * : If user does not work, provide the explicit file path to the chrome bookmark json file
	 *
	 * [--verbose=<verbose>]
	 * : Output all the names of the terms and bookmarks as they are processed
	 *
	 * [--log_existing=<log_existing>]
	 * : Send names of existing terms/bookmarks to the error/debug log
	 *
	 * ## EXAMPLES
	 *
	 *     wp chrome_bookmarks import_bookmarks --osx_user=JT
	 *
	 *     wp chrome_bookmarks import_bookmarks --bookmarks_json_path=/Users/JT/Library/Application Support/Google/Chrome/Default/Bookmarks
	 *
	 * @subcommand import_bookmarks
	 */
	public function import_bookmarks( $args, $assoc_args ) {

		if ( ! isset( $assoc_args['osx_user'] ) && ! isset( $assoc_args['bookmarks_json_path'] ) ) {
			$choice = \cli\choose( 'Please select to provide an OSX username (U) or a file path (p) to the Chrome json bookmark file.', $choice = 'UP', $default = 'U' );

			if ( 'p' == $choice ) {
				$assoc_args['bookmarks_json_path'] = \cli\prompt( 'Please enter the file path to the Chrome json bookmark file' );
			} else {
				$assoc_args['osx_user'] = \cli\prompt( 'Please enter the OSX username' );
			}
		}

		if ( ! isset( $assoc_args['osx_user'] ) && ! isset( $assoc_args['bookmarks_json_path'] ) ) {
			$this->error_exit( 'Either the osx_user or bookmarks_json_path is necessary.' );
		}

		$this->verbose = ! empty( $assoc_args['verbose'] );
		$this->log_existing = ! empty( $assoc_args['log_existing'] );

		if ( $this->check_if_importing() ) {
			$this->error_exit( 'Already importing!' );
		}

		update_option( 'chrome_bookmarks_importing', true );

		$file = ! empty( $assoc_args['bookmarks_json_path'] )
			? $assoc_args['bookmarks_json_path']
			: sprintf( $this->default_chrome_bookmark_path, $assoc_args['osx_user'] );

		if ( ! file_exists( $file ) ) {
			$this->try_again();
		}

		$json = json_decode( file_get_contents( $file ), true );
		$json = $json['roots'];
		unset( $json['synced'] );
		unset( $json['sync_transaction_version'] );
		// $this->error_exit( '$json: '. print_r( $json, true ) );

		foreach ( $json as $title => $root ) {
			$meta = array(
				'date_added' => $root['date_added'],
				'date_modified' => $root['date_modified'],
				'id' => $root['id'],
			);
			$bookmarks[] = array(
				'cat_name'  => $root['name'],
				'cat_meta'  => $meta,
				'bookmarks' => $this->get_from_json( $root, $this->categories ),
			);
		}

		$this->inline_message( '' );
		$this->inline_message( 'Bookmarks to import: '. $this->bookmarks_to_import );
		$this->inline_message( 'Categories to import: '. $this->categories_to_import );
		$this->inline_message( '' );

		// $this->error_exit( 'bookmarks: '. print_r( $bookmarks, true ) );

		// $this->error_exit( print_r( array(
		// 	'Bookmarks to import' => $this->bookmarks_to_import,
		// 	'Categories to import' => $this->categories_to_import,
		// ), true ) );

		// $this->inline_message( 'delete $posts' );

		$this->progress_bar( $this->bookmarks_to_import, 'bookmarks. Processed:', 'Importing' );

		$book_mark_bars = $bookmarks[0];
		// $this->error_exit( print_r( $bookmarks, true ) );
		// print( '$book_mark_bars: '. print_r( $book_mark_bars, true ) );
		// $this->error_exit( '$book_mark_bars: '. print_r( $book_mark_bars, true ) );
		$bookmarks = $bookmarks[1]['bookmarks'];
		$bookmarks[] = $book_mark_bars;
		// $bookmarks['Other Bookmarks']['Bookmarks Bar'] = $bookmarks['Bookmarks Bar'];
		// $bookmarks = $bookmarks['Other Bookmarks'];

		$this->import_to_wp( $bookmarks );

		$this->progress_bar( 'finish' );

		delete_option( 'chrome_bookmarks_importing' );

		$this->inline_message( '' );
		$this->inline_message( sprintf( 'Bookmarks: %d imported | %d skipped', $this->bookmarks_imported, $this->bookmarks_skipped ) );
		$this->inline_message( sprintf( 'Categories: %d imported | %d skipped', $this->terms_imported, $this->terms_skipped ) );
		$this->inline_message( '' );
		// $this->inline_message( '$this->categories: '. print_r( $this->categories, true ) );
		// $this->error_exit( 'bookmarks: '. print_r( $bookmarks, true ) );
		$this->success_exit( 'Bookmarks import has been completed.' );

	}

	protected function get_from_json( $data, &$categories = array() ) {
		$return = array();
		foreach ( $data['children'] as $child ) {
			if ( ! in_array( $child['type'], array( 'url', 'folder' ) ) ) {
				$this->error_exit( '$child: '. print_r( $child, true ) );
			}

			if ( 'folder' == $child['type'] ) {
				$this->categories_to_import++;

				$categories[ $child['name'] ] = array();
				// $return['cat_name'] = $child['name'];
				// $return['bookmarks'] = $this->get_from_json( $child, $categories[ $child['name'] ] );
				$meta = array(
					'date_added'    => isset( $child['date_added'] ) ? $child['date_added'] : '',
					'date_modified' => isset( $child['date_modified'] ) ? $child['date_modified'] : '',
					'id'            => isset( $child['id'] ) ? $child['id'] : '',
				);
				$return[] = array(
					'cat_name'  => $child['name'],
					'cat_meta'  => $meta,
					'bookmarks' => $this->get_from_json( $child, $categories[ $child['name'] ] ),
				);

			} else {

				// $this->error_exit( '$child: '. print_r( $child, true ) );
				unset( $child['meta_info'] );
				unset( $child['sync_transaction_version'] );
				unset( $child['type'] );

				$this->bookmarks_to_import++;
				$return[] = $child;
			}

		}

		return $return;
	}

	protected function import_to_wp( $bookmarks, $depth = 0 ) {
		$this->depth++;
		$unset = false;
		foreach ( $bookmarks as $bookmark ) {

			if ( isset( $bookmark['cat_name'] ) ) {
				if ( ! isset( $bookmark['cat_meta'] ) ) {
					$this->error_exit( 'cat_meta: '. print_r( array(
						'$bookmark'  => $bookmark,
						'$bookmarks' => $bookmarks,
					), true ) );
				}
				$this->insert_category( $bookmark['cat_name'], $bookmark['cat_meta'] );
				$this->import_to_wp( $bookmark['bookmarks'], $depth + 1 );

				unset( $this->categories_to_set[ $this->depth ] );
				$this->depth--;
				$unset = true;

			} else {
				$this->import_bookmark( $bookmark, $depth );
			}

			if ( ( $this->bookmarks_processed > 1 ) && 0 == ( ( $this->bookmarks_processed % 100 ) ) ) {

				if ( ! $this->check_if_importing() ) {

					$this->inline_message( "\n" );
					$this->inline_message( sprintf( 'Bookmarks completed: %d imported | %d skipped', $this->bookmarks_imported, $this->bookmarks_skipped ) );
					$this->inline_message( sprintf( 'Categories completed: %d imported | %d skipped', $this->terms_imported, $this->terms_skipped ) );
					$this->inline_message( '' );

					$this->error_exit( 'Import Stopped!' );
				}

				self::stop_the_insanity( .5 );
			}

		}

	}

	protected function insert_category( $category, $meta ) {
		$args = array(
			'description' => wp_json_encode( $meta ),
		);

		if ( array_key_exists( ( $this->depth - 1 ), $this->categories_to_set ) ) {
			$parent = $this->categories_to_set[ $this->depth - 1 ];
			$args['parent'] = $parent['term_id'];
		}

		$ids = wp_insert_term( $category, 'category', $args );

		if ( ! is_wp_error( $ids ) ) {

			$term_id = $ids['term_id'];
			$this->verbose_line( 'Term inserted ('. $this->depth .'): '. $category );
			$this->terms_imported++;

		} else {

			if ( 'term_exists' == $ids->get_error_code() ) {
				$term_id = $ids->get_error_data( 'term_exists' );
				$this->verbose_line( 'Term exists ('. $this->depth .'): '. $category );
			} else {
				$this->inline_message( '$category: '. print_r( $category, true ) );
				$this->inline_message( '$args: '. print_r( $args, true ) );
				$this->inline_message( '$this->categories_to_set: '. print_r( $this->categories_to_set, true ) );
				$this->error_exit( '$ids error: '. print_r( $ids, true ) );
			}

			$this->terms_skipped++;
		}

		$this->categories_to_set[ $this->depth ] = array(
			'term_id'   => $term_id,
			'term_name' => $category,
		);

		return $term_id;
	}

	protected function import_bookmark( $bookmark, $depth = '' ) {

		$exists = $this->post_exists( $bookmark );

		if ( $exists ) {
			$this->add_terms_to_bookmark_post( $exists, $depth );
		} else {
			$this->insert_bookmark( $bookmark, $depth );
		}

		$this->bookmarks_processed++;
		$this->progress_bar( 'tick' );
	}

	protected function insert_bookmark( $bookmark, $depth = '' ) {

		$post_data = array(
			'post_title'    => wp_kses_post( $bookmark['name'] ),
			'post_excerpt'  => $bookmark['url'],
			'post_status'   => 'publish',
			'post_category' => array_filter( $this->terms_to_attach( $depth ) ),
		);

		// if ( false !== strpos( $bookmark['name'], 'Everything We Know About Facebook' ) ) {
		// 	$this->inline_message( 'Everything We Know About Facebook...' );
		// 	$this->inline_message( '$depth: '. print_r( $depth, true ) );
		// 	$this->inline_message( '$this->depth: '. print_r( $this->depth, true ) );
		// 	$this->inline_message( '$this->categories_to_set: '. print_r( $this->categories_to_set, true ) );
		// 	$this->error_exit( '$post_data: '. print_r( $post_data, true ) );
		// }
		$post_id = wp_insert_post( $post_data );

		if ( $post_id ) {

			$this->bookmarks_imported++;

			update_post_meta( $post_id, 'chrome_date_added', $bookmark['date_added'] );
			update_post_meta( $post_id, 'chrome_id', $bookmark['id'] );
			update_post_meta( $post_id, 'bookmark_url', $bookmark['url'] );
			set_post_format( $post_id, 'link' );

			$this->verbose_line( 'Post Inserted: '. $bookmark['name'] );

		} else {
			$this->bookmarks_skipped++;
			$this->verbose_line( 'Failed to insert: '. $bookmark['name'] );
		}
	}

	protected function add_terms_to_bookmark_post( $post, $depth ) {
		$terms = wp_get_object_terms( $post->ID, 'category', array(
			'fields' => 'ids',
		) );
		$terms += $this->terms_to_attach( $depth );
		$to_set = array_unique( $terms );

		if ( $to_set != $terms ) {
			wp_set_object_terms( $post->ID, array_unique( $terms ), 'category' );
			$this->verbose_line( 'Terms Updated: '. print_r( $post->post_title, true ) );
		} else {
			$this->verbose_line( "Already exists: '{$post->post_title}'" );
		}
		$this->bookmarks_skipped++;
	}

	protected function terms_to_attach( $depth ) {
		$terms = array();
		for ( $i = $depth; $i > 0; $i-- ) {

			if ( isset( $this->categories_to_set[ $i ] ) ) {
				$terms[] = $this->categories_to_set[ $i ]['term_id'];
			}
		}

		return $terms;
	}

	protected function post_exists( $bookmark ) {
		$query_args = array(
			'post_type'      => 'post',
			'meta_key'       => 'bookmark_url',
			'meta_value'     => $bookmark['url'],
			'posts_per_page' => 1,
		);
		$posts = get_posts( $query_args );

		if ( ! $posts || ! isset( $posts[0] ) ) {
			$exists = get_page_by_title( $bookmark['name'], OBJECT, 'post' );
			$post = $exists && ! is_wp_error( $exists ) ? $exists : false;

			if ( $exists && $this->log_existing ) {
				error_log( '$exists, line '. __LINE__ .': '. print_r( array(
					'$bookmark'  => $bookmark,
					'$post'      => $post,
					'$post_meta' => get_post_meta( $post->ID ),
					'$posts'     => $posts,
				), true ) );
			}
		} else {
			$post = $posts[0];
		}

		return $post;
	}

	protected function check_if_importing() {
		global $wpdb;
		$option = 'chrome_bookmarks_importing';

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $option ) );

		return ! empty( $row );
	}

	protected function try_again() {
		$this->error_exit( 'No Bookmarks file found. Try specifying the path to the Chrome Bookmarks json file via: --bookmarks_json_path=<bookmarks_json_path>' );
	}

	protected function success_exit( $msg ) {
		WP_CLI::success( $msg );
		exit();
	}

	protected function error_exit( $msg ) {
		WP_CLI::error( $msg );
	}

	protected function inline_message( $msg ) {
		WP_CLI::line( $msg );
	}

	protected function verbose_line( $msg ) {
		if ( $this->verbose ) {
			$this->inline_message( $msg );
		}
	}

	/**
	 * Progress Bar for WP_CLI
	 *
	 * @TODO get this working to keep it in the logging class instead of migration helper
	 * Currently 'works' but does not 'tick' properly and I cannot figure out why - jay
	 * Also set it to private so it's inaccessible.
	 *
	 * @param int $param
	 * @param string $object
	 *
	 * @return bool|null
	 */
	private function progress_bar( $param = 0, $object = '', $action = 'Migrating' ) {

		$object = empty( $object ) ? 'items' : $object;
		$action = ucfirst( $action );
		if ( $param && is_numeric( $param ) ) {
			$this->progress_bar = \WP_CLI\Utils\make_progress_bar( "$action $param $object", $param );
		} elseif ( 'tick' == $param ) {
			$this->progress_bar->tick();
		} elseif ( 'finish' == $param ) {
			$this->progress_bar->finish();
		}

		return $this->progress_bar;
	}

	public static function stop_the_insanity( $sleep_time = 0 ) {

		if ( $sleep_time ) {
			usleep( $sleep_time * 1000000 );
		}

		global $wpdb, $wp_object_cache;

		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();

		if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset(); // important
		}

	}
}
