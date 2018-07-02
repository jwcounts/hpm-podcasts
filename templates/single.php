<?php
/**
 * RSS2 Feed Template for Podcasts.
 *
 * @package WordPress
 */
	header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
	while ( have_posts() ) : the_post();
		echo get_option( 'hpm_podcast-'.$post->post_name );
	endwhile; ?>
