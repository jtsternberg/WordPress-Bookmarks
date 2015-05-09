<?php
/**
 * WordPress Bookmarks Forward_links
 * @version 0.1.0
 * @package WordPress Bookmarks
 */

class WPB_Forward_Links {

	/**
	 * Initiate our hooks
	 * @since 0.1.0
	 */
	public function hooks() {
		add_filter( 'post_link', array( $this, 'post_link' ), 10, 2 );
		add_filter( 'page_link', array( $this, 'post_link' ), 10, 2 );
	}

	function post_link( $link, $post ) {
		$post_id = is_a( $post, 'WP_Post' ) ? $post->ID : $post;

		if ( ! $post_id ) {
			return $link;
		}

		$url = $this->retrieve_url_from_post_excerpt( $post_id );

		if ( $url ) {
			return $url;
		}

		return $link;
	}

	public function retrieve_url_from_post_excerpt( $_post ) {
		global $post;

		if ( is_numeric( $_post ) ) {
			$_post = isset( $post->ID ) && $post->ID == $_post ? $post : get_post( $_post );
		}
		if ( ! isset( $_post->post_excerpt ) ) {
			return false;
		}

		$post_excerpt = str_ireplace( array( 'http://', 'https://' ), '', $_post->post_excerpt );

		if ( false === stripos( $post_excerpt, '.' ) ) {
			return false;
		}

		return $post_excerpt;
	}

}
