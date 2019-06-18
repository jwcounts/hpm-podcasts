<?php
/**
 * RSS2 Feed Template for Podcasts.
 *
 * @package WordPress
 */
	header('Content-Type: text/xml; charset=' . get_option('blog_charset'), true);
	while ( have_posts() ) : the_post();
		echo get_option( 'hpm_podcast-'.$post->post_name );
	endwhile; ?>
