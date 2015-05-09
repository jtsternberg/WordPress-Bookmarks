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
	 * Initiate our hooks
	 * @since 0.1.0
	 */
	public function hooks() {
		add_filter( 'press_this_data', array( $this, 'get_link' ) );
		add_action( 'save_post', array( $this, 'save_link_to_excerpt' ) );
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
	public function save_link_to_excerpt( $post_id ) {
		static $prevent_recursion = false;

		// if we match our conditions
		if (
			! $prevent_recursion
			&& $this->link
			&& ! empty( $_POST['_links']['canonical'] )
			&& $this->link == $_POST['_links']['canonical']
		) {

			$prevent_recursion = true;

			// then update the bookmark post
			$updated = wp_update_post( array(
				'ID'           => $post_id,
				'post_excerpt' => $this->link,
			), true );

		}
	}

}
