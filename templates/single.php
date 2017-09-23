<?php
/**
 * RSS2 Feed Template for Podcasts.
 *
 * @package WordPress
 */
	$pods = get_option( 'hpm_podcast_settings' );
	if ( !empty( $pods['upload-flats'] ) ) :
		if ( $pods['upload-flats'] == 's3' ) :
			$base_url = 'https://s3-'.$pods['credentials']['s3']['region'].'.amazonaws.com/'.$pods['credentials']['s3']['bucket'].'/'.$pods['credentials']['s3']['folder'].'/';
		elseif ( $pods['upload-flats'] == 'ftp' || $pods['upload-flats'] == 'sftp' ) :
			if ( !empty( $pods['credentials'][$pods['upload-flats']]['folder'] ) ) :
				$folder = "/".$pods['credentials'][$pods['upload-flats']]['folder']."/";
			else :
				$folder = "/";
			endif;
			$base_url = $pods['credentials'][$pods['upload-flats']]['url'].$folder;
		endif;
	else :
		$uploads = wp_upload_dir();
		$base_url = $uploads['basedir'].'/hpm-podcasts/';
	endif;
	while ( have_posts() ) : the_post();
		header('Content-Type: ' . feed_content_type('rss2') . '; charset=' . get_option('blog_charset'), true);
		if ( $pods['upload-flats'] == 'database' ) :
			echo get_transient( 'hpm_podcast-'.$post->post_name );
		else :
			echo file_get_contents( $base_url.$post->post_name.".xml" );
		endif;
	endwhile;
?>
