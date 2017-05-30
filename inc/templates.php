<?php
/**
 * Sets single template for podcast feed.  Can be overridden by template file in podcasts folder in theme
 */
add_filter('single_template', 'hpm_podcasts_single_template');
function hpm_podcasts_single_template( $single ) {
	global $post;
	if ( $post->post_type == "podcasts" ) :
		if ( file_exists( get_stylesheet_directory() . '/podcasts/single.php' ) ) :
			return get_stylesheet_directory() . '/podcasts/single.php';
		else :
			return HPM_PODCAST_PLUGIN_DIR . 'templates/single.php';
		endif;
	endif;
	return $single;
}

/**
 * Sets archive template for podcasts.  Can be overridden by template file in podcasts folder in theme
 */
add_filter('archive_template', 'hpm_podcasts_archive_template');
function hpm_podcasts_archive_template( $archive_template ) {
	global $post;
	if ( is_post_type_archive ( 'podcasts' ) ) :
		if ( file_exists( get_stylesheet_directory() . '/podcasts/archive.php' ) ) :
			return get_stylesheet_directory() . '/podcasts/archive.php';
		else :
			return HPM_PODCAST_PLUGIN_DIR . 'templates/archive.php';
		endif;
	endif;
	return $archive_template;
}