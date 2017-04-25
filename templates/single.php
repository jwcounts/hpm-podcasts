<?php
/**
 * RSS2 Feed Template for Podcasts.
 *
 * @package WordPress
 */
	while ( have_posts() ) : the_post();
		$podtitle = $post->post_name;
		$content = file_get_contents( 'http://s3-us-west-2.amazonaws.com/hpmwebv2/assets/podcasts/'.$podtitle.'.xml' );
		header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
		echo $content;
	endwhile;
?>
