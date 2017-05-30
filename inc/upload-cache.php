<?php
/**
 * Uploads
 *
 * @param $arg1 | string, post ID
 * @param $arg2 | string, podcast feed name
 *
 * @return bool
 */
function hpm_podcast_media_upload( $arg1, $arg2 ) {
    if ( empty( $arg2 ) || empty( $arg1 ) ) :
        return false;
    endif;
    $pods = get_option( 'hpm_podcasts' );
    if ( empty( $pods['upload-media'] ) ) :
        return false;
    endif;
    $dir = wp_upload_dir();
    $save = $dir['basedir'];
    $media = get_attached_media( 'audio', $arg1 );
    if ( empty( $media ) ) :
        $media = get_attached_media( 'video', $arg1 );
    endif;
    if ( empty ( $media ) ) :
        return false;
    endif;
    $med = reset( $media );
    $url = wp_get_attachment_url( $med->ID );
    $parse = parse_url( $url );
    $path = pathinfo( $parse['path'] );
    $local = $save.$path['basename'];
    file_put_contents( $local, fopen( $url, 'r' ) );
    if ( $pods['upload-media'] == 'ftp' ) :
        try {
            $con = ftp_connect($pods['credentials']['ftp']['host']);
            if ( false === $con ) :
                throw new Exception('The podcasting plugin was unable to connect to the FTP server.  Please check your FTP Host URL or IP and try again.');
            endif;

            $loggedIn = ftp_login( $con, $pods['credentials']['ftp']['username'], $pods['credentials']['ftp']['password'] );
            if ( false === $loggedIn ) :
                throw new Exception('The podcasting plugin was unable to log in to the FTP server.  Please check your credentials and try again.');
            endif;

            if ( !ftp_chdir( $con, $arg2 ) ) :
                ftp_mkdir( $con, $arg2 );
                ftp_chdir( $con, $arg2 );
            endif;
            if ( ftp_put( $con, $path['basename'], $local, FTP_BINARY ) ) :
                $upload_status = true;
            else :
                throw new Exception("There was an error uploading audio for this article: ".get_site_url()."/wp-admin/post.php?post=".$arg1."&action=edit" );
            endif;
            ftp_close( $con );
        } catch ( Exception $e ) {
            $upload_status = false;
            $message = $e->getMessage();
        }
    elseif ( $pods['upload-media'] == 'sftp' ) :
        $path = HPM_PODCAST_PLUGIN_DIR .'phpseclib';
        set_include_path(get_include_path() . PATH_SEPARATOR . $path);
        include( 'Net/SFTP.php' );
        $sftp = new Net_SFTP( $pods['credentials']['sftp']['host'] );
        if ( !$sftp->login( $pods['credentials']['sftp']['username'], $pods['credentials']['sftp']['password'] ) ) :
            $upload_status = false;
            $message = 'SFTP Login Failed.  Please check your credentials and try again.';
        endif;

        if ( !$sftp->chdir( $arg2 ) ) :
            $sftp->mkdir( $arg2 );
            $sftp->chdir( $arg2 );
        endif;
        if ( $sftp->put( $path['basename'], $local, NET_SFTP_LOCAL_FILE ) ) :
           $upload_status = true;
        else :
            $upload_status = false;
            $message = "There was an error uploading audio for this article: ".get_site_url()."/wp-admin/post.php?post=".$arg1."&action=edit";
        endif;
        unset( $sftp );
    elseif ( $pods['upload-media'] == 's3' ) :
        require HPM_PODCAST_PLUGIN_DIR . 'aws/aws-autoloader.php';
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region'  => $pods['credentials']['s3']['region'],
            'credentials' => [
                'key' => $pods['credentials']['s3']['key'],
                'secret' => $pods['credentials']['s3']['secret']
            ]
        ]);
        try {
            $result = $client->putObject(array(
                'Bucket' => $pods['credentials']['s3']['bucket'],
                'Key' => $pods['credentials']['s3']['folder'].'/'.$arg2.'/'.$podcast_title.'.xml',
                'Body' => $local,
                'ACL' => 'public-read',
                'ContentType' => $med->post_mime_type
            ));
            $upload_status = true;
        } catch (S3Exception $e) {
            $upload_status = false;
            $message = $e->getMessage();
        } catch (AwsException $e) {
            $upload_status = false;
            $message = $e->getAwsRequestId() . "\n" . $e->getAwsErrorType() . "\n" . $e->getAwsErrorCode();
        }
    else :
        return false;
    endif;
    if ( $upload_status ) :
        unlink( $local );
        $sg_url = $pods['upload']['upload_url'].$arg2.'/'.$path['basename'];
        $hpm_pod_sg_file = metadata_exists( 'post', $arg1, 'hpm_podcast_sg_file' );
        if ( $hpm_pod_sg_file ) :
            update_post_meta( $arg1, 'hpm_podcast_sg_file', $sg_url );
        else :
            add_post_meta( $arg1, 'hpm_podcast_sg_file', $sg_url, true );
        endif;
        wp_mail( $pods['email'], 'Podcast Audio Uploaded Successfully', "Audio for this article was uploaded successfully:\n\n".get_site_url()."/wp-admin/post.php?post=".$arg1."&action=edit\n\n".$sg_url );
    else :
        wp_mail( $pods['email'], 'Error Uploading Podcast Audio', $message );
    endif;
}
add_action( 'hpm_podcast_media', 'hpm_podcast_media_upload', 10, 3 );

/**
 * Pull a list of podcasts, generate the feeds, and save them as flat XML files, either locally, or in the FTP, SFTP
 * or S3 bucket defined
 */
function hpmv2_podcast_generate() {
    $pods = get_option( 'hpm_podcasts' );
    if ( !empty( $pods['https'] ) ) :
        $protocol = 'https://';
        $_SERVER['HTTPS'] = 'on';
    else :
        $protocol = 'http://';
    endif;
    global $wpdb;
    $error = '';
    $podcasts = new WP_Query(
        array(
            'post_type' => 'podcasts',
            'post_status' => 'publish',
            'posts_per_page' => -1
        )
    );
    if ( !empty( $pods['recurrence'] ) ) :
        if ( $pods['recurrence'] == 'hpm_5min' ) :
            $frequency = '5';
        elseif ( $pods['recurrence'] == 'hpm_15min' ) :
            $frequency = '15';
        elseif ( $pods['recurrence'] == 'hpm_30min' ) :
            $frequency = '30';
        elseif ( $pods['recurrence'] == 'hourly' ) :
            $frequency = '60';
        else :
            $frequency = '60';
        endif;
    else :
        $frequency = '60';
    endif;
    while ( $podcasts->have_posts() ) :
        $podcasts->the_post();
        $pod_id = get_the_ID();
        $catslug = get_post_meta( $pod_id, 'hpm_pod_cat', true );
        $podlink = get_post_meta( $pod_id, 'hpm_pod_link', true );
        ob_start();
        echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
        do_action( 'rss_tag_pre', 'rss2' ); ?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom" <?php do_action( 'rss2_ns' ); ?>>
<?php
        $main_image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
        $categories = array(
            'prime' => explode( '||', $podlink['cat-prime'] ),
            'second' => explode( '||', $podlink['cat-second'] ),
            'third' => explode( '||', $podlink['cat-third'] ),
        );
        $podcast_title = $podcasts->post->post_name; ?>
<channel>
    <title><?php the_title_rss(); ?></title>
    <atom:link href="<?php echo get_the_permalink(); ?>" rel="self" type="application/rss+xml" />
    <link><?php echo $podlink['page']; ?></link>
    <description><![CDATA[<?php the_content_feed(); ?>]]></description>
    <language><?php bloginfo_rss( 'language' ); ?></language>
    <copyright>All Rights Reserved</copyright>
    <webMaster><?php echo $pods['owner']['name']."(".$pods['owner']['email'].")"; ?></webMaster>
    <ttl><?php echo $frequency; ?></ttl>
    <pubDate><?php echo date('r'); ?></pubDate>
    <itunes:summary><![CDATA[<?php the_content_feed(); ?>]]></itunes:summary>
    <itunes:owner>
        <itunes:name><?php echo $pods['owner']['name']; ?></itunes:name>
        <itunes:email><?php echo $pods['owner']['email']; ?></itunes:email>
    </itunes:owner>
    <itunes:keywords><?php echo strip_tags( get_the_tag_list( '', ',', '' ) ); ?></itunes:keywords>
    <itunes:subtitle><?PHP echo get_the_excerpt();  ?></itunes:subtitle>
    <itunes:author><?php
        if ( function_exists( 'coauthors' ) ) :
            coauthors(', ', ', ', '', '', true);
        else :
            echo get_the_author();
        endif; ?></itunes:author>
    <itunes:explicit>no</itunes:explicit>
<?PHP
        foreach ( $categories as $podcat ) :
            if ( count( $podcat ) == 2 ) : ?>
    <itunes:category text="<?PHP echo htmlentities( $podcat[0] ); ?>">
        <itunes:category text="<?PHP echo htmlentities( $podcat[1] ); ?>" />
    </itunes:category>
<?PHP
            else :
                if ( !empty( $podcat[0] ) ) :
?>		<itunes:category text="<?PHP echo htmlentities( $podcat[0] ); ?>" />
<?PHP
                endif;
            endif;
        endforeach;
        if ( !empty( $main_image ) ) :
?>		<itunes:image href="<?PHP echo $main_image[0]; ?>" />
    <image>
        <url><?php echo $main_image[0]; ?></url>
        <title><?PHP the_title_rss(); ?></title>
    </image>
<?php
        endif;
        do_action( 'rss2_head');
        $perpage = "";
        if ( !empty( $podlink['limit'] ) && $podlink['limit'] != 0 && is_numeric( $podlink['limit'] ) ) :
            $perpage = " LIMIT 0,".$podlink['limit'];
        endif;
        $podeps = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS $wpdb->posts.*
                FROM $wpdb->posts,wp_term_relationships
                WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id
                    AND $wpdb->term_relationships.term_taxonomy_id IN ($catslug)
                    AND $wpdb->posts.post_type = 'post'
                    AND $wpdb->posts.post_status = 'publish'
                    AND $wpdb->posts.ID IN (
                        SELECT DISTINCT post_parent
                        FROM $wpdb->posts
                        WHERE post_parent > 0
                            AND post_type = 'attachment'
                            AND (
                                post_mime_type = 'audio/mpeg'
                                OR post_mime_type = 'video/mp4'
                                OR post_mime_type = 'audio/mp4'
                            )
                    )
                GROUP BY $wpdb->posts.ID
                ORDER BY $wpdb->posts.post_date DESC
                $perpage",
            OBJECT
        );
        if ( !empty( $podeps ) ) :
            foreach ( $podeps as $pod ) :
                $epid = $pod->ID;
                $media = $wpdb->get_results(
                    "SELECT ID,post_title
                        FROM $wpdb->posts
                        WHERE post_type = 'attachment'
                            AND (
                                post_mime_type = 'audio/mpeg'
                                OR post_mime_type = 'video/mp4'
                                OR post_mime_type = 'audio/mp4'
                            )
                            AND post_parent = $epid",
                    OBJECT
                );
                $m = reset( $media );
                $url = wp_get_attachment_url( $m->ID );
                $attr = get_post_meta( $m->ID, '_wp_attachment_metadata', true );
                $pod_image = wp_get_attachment_image_src( get_post_thumbnail_id( $epid ), 'full' );
                $tags = wp_get_post_tags( $epid );
                $tag_array = array();
                foreach ( $tags as $t ) :
                    $tag_array[] = $t->name;
                endforeach;
                $pod_desc = get_post_meta( $epid, 'hpm_podcast_ep_meta', true );
                $sg_file = get_post_meta( $epid, 'hpm_podcast_sg_file', true );
                if ( empty( $sg_file ) ) :
                    $media_file = $protocol.( !empty( $podlink['blubrry'] ) ? "media.blubrry.com/" .$podlink['blubrry']."/" : '' ).$url;
                else :
                    $media_file = $sg_file;
                endif;
                $content = "<p>".wp_trim_words( strip_shortcodes( $pod->post_content ), 75, '... <a href="'.get_the_permalink( $epid ).'">Read More</a>' )."</p>"; ?>
    <item>
        <title><?php echo apply_filters('the_title_rss', $pod->post_title ); ?></title>
        <link><?php echo get_the_permalink( $epid ); ?></link>
        <pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true, $epid ), false); ?></pubDate>
        <guid isPermaLink="true"><?php echo get_the_permalink( $epid ); ?></guid>
        <description><![CDATA[<?php echo ( !empty( $pod_desc['description'] ) ? $pod_desc['description'] : $content ); ?>]]></description>
        <itunes:keywords><?php echo implode( ',', $tag_array ); ?></itunes:keywords>
        <itunes:summary><![CDATA[<?php echo ( !empty( $pod_desc['description'] ) ? $pod_desc['description'] : $content ); ?>]]></itunes:summary>
<?php
                if ( !empty( $pod_image ) ) :
?>			<itunes:image href="<?PHP echo $pod_image[0]; ?>"/>
<?php
                endif;
                if ( in_array( 'explicit', $tag_array ) ) : ?>
        <itunes:explicit>yes</itunes:explicit>
<?php
                else : ?>
        <itunes:explicit>no</itunes:explicit>
<?php
                endif; ?>
        <enclosure url="<?PHP echo $media_file; ?>" length="<?PHP echo $attr['filesize']; ?>" type="<?php echo $attr['mime_type']; ?>" />
        <itunes:duration><?PHP echo $attr['length_formatted']; ?></itunes:duration>
<?php do_action( 'rss2_item' ); ?>
    </item>
<?php
            endforeach;
        endif; ?>
</channel>
</rss>
<?php
        $getContent = ob_get_contents();
        ob_end_clean();
        $getContent_mini = trim( preg_replace( '/\s+/', ' ', $getContent ) );
        if ( !empty( $pods['upload-flats'] ) ) :
            if ( $pods['upload-flats'] == 's3' ) :
	            require HPM_PODCAST_PLUGIN_DIR . 'aws/aws-autoloader.php';
	            $client = new Aws\S3\S3Client([
		            'version' => 'latest',
		            'region'  => $pods['credentials']['s3']['region'],
		            'credentials' => [
			            'key' => $pods['credentials']['s3']['key'],
			            'secret' => $pods['credentials']['s3']['secret']
		            ]
	            ]);
                try {
                    $result = $client->putObject(array(
                        'Bucket' => $pods['credentials']['s3']['bucket'],
                        'Key' => ( !empty( $pods['credentials']['s3']['folder'] ) ? $pods['credentials']['s3']['folder'].'/' : '' ).$podcast_title.'.xml',
                        'Body' => $getContent_mini,
                        'ACL' => 'public-read',
                        'ContentType' => 'application/rss+xml'
                    ));
                } catch ( S3Exception $e ) {
                    $error .= $podcast_title."\n".$e->getMessage()."\n\n";
                } catch ( AwsException $e ) {
                    $error .= $podcast_title . "\n" . $e->getAwsRequestId() . "\n" . $e->getAwsErrorType() . "\n" . $e->getAwsErrorCode() . "\n\n";
                }
            elseif ( $pods['upload-media'] == 'ftp' ) :
                try {
                    $con = ftp_connect($pods['credentials']['ftp']['host']);
                    if ( false === $con ) :
                        throw new Exception('The podcasting plugin was unable to connect to the FTP server.  Please check your FTP Host URL or IP and try again.');
                    endif;

                    $loggedIn = ftp_login($con,  $pods['credentials']['ftp']['username'], $pods['credentials']['ftp']['password'] );
                    if ( false === $loggedIn ) :
                        throw new Exception('The podcasting plugin was unable to log in to the FTP server.  Please check your credentials and try again.');
                    endif;

                    if ( ! ftp_put( $con, $podcast_title.'.xml', $getContent_mini, FTP_BINARY ) ) :
                        throw new Exception("There was an error uploading audio for this article: ".$podcast_title );
                    endif;
                    ftp_close( $con );
                } catch (Exception $e) {
                    $error .= $e->getMessage();
                }
            elseif ( $pods['upload-media'] == 'sftp' ) :
                $path = HPM_PODCAST_PLUGIN_DIR .'phpseclib';
                set_include_path(get_include_path() . PATH_SEPARATOR . $path);
                include( 'Net/SFTP.php' );
                $sftp = new Net_SFTP( $pods['credentials']['sftp']['host'] );
                if ( !$sftp->login( $pods['credentials']['sftp']['username'], $pods['credentials']['sftp']['password'] ) ) :
                    $error .= 'SFTP Login Failed.  Please check your credentials and try again.';
                endif;

                if ( ! $sftp->put( $podcast_title.'.xml', $getContent_mini, NET_SFTP_LOCAL_FILE ) ) :
                    $error .= "There was an error uploading audio for this article: ".$podcast_title;
                endif;
                unset( $sftp );
            endif;
        else :
            $uploads = wp_upload_dir();
            if ( !file_exists( $uploads['basedir'].'/hpm-podcasts' ) ) :
                mkdir( $uploads['basedir'].'/hpm-podcasts' );
            endif;
            $file_write = file_put_contents( $uploads['basedir'].'/hpm-podcasts/'.$podcast_title.'.xml',
                $getContent_mini );
            if ( $file_write === FALSE ) :
                $error .= $podcast_title."\nThere was an error writing your cache file into the Uploads directory.  Please check the error log.\n\n";
            endif;
        endif;
	    sleep(30);
    endwhile;
    if ( !empty( $error ) ) :
        wp_mail( $pods['upload']['email'], 'Error Generating Podcast Feeds', "There was an error generating the podcast feeds.  See below:\n".$error );
    endif;
}