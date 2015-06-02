<?php
/**
 * WordPress Bookmarks Press_this
 * @version 0.1.0
 * @package WordPress Bookmarks
 */

class WPB_Press_This {

	/**
	 * The bookmark link retrieved by pressthis
	 * @var string
	 */
	protected $link = '';

	/**
	 * The post terms array
	 * @var array
	 */
	protected $terms = array();

	/**
	 * Existing post id if found
	 * @var int
	 */
	protected $existing_bookmark_id = 0;

	/**
	 * Initiate our hooks
	 * @since 0.1.0
	 */
	public function hooks() {
		add_filter( 'press_this_data', array( $this, 'get_link' ) );
		add_action( 'wp_insert_post_data', array( $this, 'save_link_to_excerpt' ), 10, 2 );
		add_action( 'save_post', array( $this, 'set_post_format' ), 99 );
		add_action( 'set_object_terms', array( $this, 'save_all_category_parents' ), 10, 4 );

		add_action( 'admin_print_styles-press-this.php', array( $this, 'custom_styles' ) );
		add_action( 'admin_footer-press-this.php', array( $this, 'custom_js' ) );
	}

	public function custom_styles() {
		?>
		<style type="text/css">
			li.popular-category > div {
				background: rgba(0, 187, 255, 0.18);
			}
			.categories-select li {
				background: rgba(0, 187, 255, 0.1);
				background: rgba(0, 160, 210, 0.1);
				padding-left: 10px;
			}
			.category {
				border-bottom: 1px solid #ddd;
			}
			.categories-select ul .category {
			  padding-left: 24px !important;
			}
		</style>
		<?php
	}

	public function custom_js() {

		if ( $this->existing_bookmark_id ) {
			$edit_link = get_edit_post_link( $this->existing_bookmark_id );
			$message = sprintf( __( 'Found a bookmark with this URL. <a href="%s">%s</a>?', 'wordpress-bookmarks' ), esc_url( $edit_link ), __( 'Edit Instead', 'wordpress-bookmarks' ) );
		}

		?>
		<script type="text/javascript">
		jQuery(document).ready(function($){
			$( '.post-format-link' ).trigger( 'click' );
			$( '.dashicons.dashicons-category' ).parents( '.post-options .post-option' ).trigger( 'click' );
			<?php if ( $this->existing_bookmark_id ) : ?>
			$( document.getElementById( 'title-container' ) ).before( '<p id="message" class="update"><?php echo $message ?></p>' );
			<?php endif; ?>
		});
		</script>
		<?php
	}

	/**
	 * Grab the link from the pressthis data
	 *
	 * @since  0.1.0
	 *
	 * @param  array  $data Array of pressthis data
	 *
	 * @return array        Array of pressthis data
	 */
	function get_link( $data ) {

		if ( ! empty( $data['_links']['canonical'] ) ) {
			$this->link = $data['_links']['canonical'];
		}

		if ( ! $this->link && ! empty( $data['u'] ) ) {
			$this->link = $data['u'];
		}

		// Send the data back
		return $data;
	}

	/**
	 * Set the post excerpt to the link
	 *
	 * @since  0.1.0
	 *
	 * @param  int  $post_id The post ID
	 */
	public function save_link_to_excerpt( $data, $postarr ) {
		// if we match our conditions
		if (
			$this->link
			&& ! empty( $_POST['_links']['canonical'] )
			&& $this->link == $_POST['_links']['canonical']
		) {

			$data['post_excerpt'] = $this->link;

			$posts = get_posts( array(
				'posts_per_page' => 1,
				'meta_key'       => 'bookmark_url',
				'meta_value'     => $this->link,
				'fields'         => 'ids',
			) );

			if ( ! empty( $posts ) ) {
				$this->existing_bookmark_id = $posts[0];
			}

		}

		return $data;
	}

	public function set_post_format( $post_id ) {
		if ( $this->link ) {
			set_post_format( $post_id, 'link' );
			update_post_meta( $post_id, 'bookmark_url', $this->link );
		}
	}

	public function save_all_category_parents( $post_id, $terms, $tt_ids, $taxonomy ) {
		static $prevent_recursion = false;

		if ( $prevent_recursion || 'category' != $taxonomy || empty( $terms ) || ! get_post_meta( $post_id, 'bookmark_url', 1 ) ) {
			return;
		}

		$prevent_recursion = true;

		$this->terms = array();
		foreach ( $terms as $term_id ) {
			$this->get_parents( $term_id );
		}

		wp_set_post_categories( $post_id, $this->terms );
	}

	public function get_parents( $term_id ) {
		$this->terms[] = $term_id;
		$term = get_term( $term_id, 'category' );

		if ( $term->parent ) {
			$this->get_parents( $term->parent );
		}
	}

}
