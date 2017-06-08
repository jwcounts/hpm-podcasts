<?php
add_action( 'rest_api_init', function(){
	register_rest_route( 'hpm-podcast/v1', '/refresh', array(
		'methods'  => 'GET',
		'callback' => 'hpm_podcast_rest_generate'
	) );

	register_rest_route( 'hpm-podcast/v1', '/upload/(?P<feed>[a-zA-Z0-9\-_]+)/(?P<id>[\d]+)', array(
		'methods'  => 'GET',
		'callback' => 'hpm_podcast_rest_media_upload',
		'args' => array(
			'id' => array(
				'required' => true
			),
			'feed' => array(
				'required' => true
			)
		)
	) );
} );

/**
 * Uploads
 *
 * @param WP_REST_Request $request This function accepts a rest request to process data.
 *
 * @return mixed
 */
function hpm_podcast_rest_media_upload( WP_REST_Request $request ) {
	if ( empty( $request['feed'] ) ) :
		return new WP_Error( 'rest_api_sad', esc_html__( 'Unable to upload media. Please choose a podcast feed.', 'hpm-podcasts' ), array( 'status' => 500 ) );
	elseif ( empty( $request['id'] ) ) :
		return new WP_Error( 'rest_api_sad', esc_html__( 'No post ID provided, cannot upload media. Please save your post and try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
	endif;

	$pods = get_option( 'hpm_podcasts' );

	if ( empty( $pods['upload-media'] ) ) :
		return new WP_Error( 'rest_api_sad', esc_html__( 'No media upload target was selected. Please check your settings.', 'hpm-podcasts' ), array( 'status' => 500 ) );
	endif;

	$message = '';
	$download = false;

	$dir = wp_upload_dir();
	$save = $dir['basedir'];
	$media = get_attached_media( 'audio', $request['id'] );
	if ( empty( $media ) ) :
		$media = get_attached_media( 'video', $request['id'] );
	endif;
	if ( empty ( $media ) ) :
		return new WP_Error( 'rest_api_sad', esc_html__( 'No audio or video files are attached to this post. Please attach one and try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
	endif;

	$med = reset( $media );
	$url = wp_get_attachment_url( $med->ID );
	if ( strpos( $url, $dir['baseurl'] ) !== FALSE ) :
		$meta = get_post_meta( $med->ID, '_wp_attached_file', true );
		$local = $save . DIRECTORY_SEPARATOR . $meta;
		$path = pathinfo( $meta );
	else :
		$download = true;
		$parse = parse_url( $url );
		$path = pathinfo( $parse['path'] );
		$local = $save . DIRECTORY_SEPARATOR . $path['basename'];
		if ( !file_put_contents( $local, file_get_contents( $url ) ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( 'Unable to download your media file to the local server. Please try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		endif;
	endif;

	if ( $pods['upload-media'] == 'ftp' ) :
		$short = $pods['credentials']['ftp'];
		if ( defined( 'HPM_FTP_PASSWORD' ) ) :
			$ftp_password = HPM_FTP_PASSWORD;
		elseif ( !empty( $short['password'] ) ) :
			$ftp_password = $short['password'];
		else :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No FTP password provided. Please check your settings.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		endif;
		try {
			$con = ftp_connect($short['host']);
			if ( false === $con ) :
				throw new Exception("Unable to connect to the FTP server. Please check your FTP Host URL or IP and try again." );
			endif;

			$loggedIn = ftp_login( $con, $short['username'], $ftp_password );
			if ( false === $loggedIn ) :
				throw new Exception("Unable to log in to the FTP server. Please check your credentials and try again." );
			endif;

			if ( !empty( $short['folder'] ) ) :
				if ( !ftp_chdir( $con, $short['folder'] ) ) :
					ftp_mkdir( $con, $short['folder'] );
					ftp_chdir( $con, $short['folder'] );
				endif;
			endif;

			if ( !ftp_chdir( $con, $request['feed'] ) ) :
				ftp_mkdir( $con, $request['feed'] );
				ftp_chdir( $con, $request['feed'] );
			endif;
			if ( ! ftp_put( $con, $path['basename'], $local, FTP_BINARY ) ) :
				throw new Exception("The file could not be saved on the FTP server. Please verify your permissions on that server and try again." );
			endif;
			ftp_close( $con );

			$sg_url = $short['url'].'/'.$request['feed'].'/'.$path['basename'];

		} catch ( Exception $e ) {
			$message = $e->getMessage();
		}
	elseif ( $pods['upload-media'] == 'sftp' ) :
		$short = $pods['credentials']['sftp'];
		$ipath = HPM_PODCAST_PLUGIN_DIR .'vendor' . DIRECTORY_SEPARATOR . 'phpseclib';
		set_include_path(get_include_path() . PATH_SEPARATOR . $ipath);
		include( 'Net/SFTP.php' );
		$sftp = new Net_SFTP( $short['host'] );
		if ( defined( 'HPM_SFTP_PASSWORD' ) ) :
			$sftp_password = HPM_SFTP_PASSWORD;
		elseif ( !empty( $short['password'] ) ) :
			$sftp_password = $short['password'];
		else :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No SFTP password provided. Please check your settings.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		endif;
		if ( !$sftp->login( $short['username'], $sftp_password ) ) :
			$message = "Unable to connect to the SFTP server. Please check your SFTP Host URL or IP and try again.";
		endif;

		if ( !empty( $short['folder'] ) ) :
			if ( !$sftp->chdir( $short['folder'] ) ) :
				$sftp->mkdir( $short['folder'] );
				$sftp->chdir( $short['folder'] );
			endif;
		endif;

		if ( !$sftp->chdir( $request['feed'] ) ) :
			$sftp->mkdir( $request['feed'] );
			$sftp->chdir( $request['feed'] );
		endif;
		if ( !$sftp->put( $path['basename'], $local, NET_SFTP_LOCAL_FILE ) ) :
			$message = "The file could not be saved on the SFTP server. Please verify your permissions on that server and try again.";
		endif;
		unset( $sftp );
		$sg_url = $short['url'].'/'.$request['feed'].'/'.$path['basename'];

	elseif ( $pods['upload-media'] == 's3' ) :
		$short = $pods['credentials']['s3'];
		if ( defined( 'AWS_ACCESS_KEY_ID' ) && defined( 'AWS_SECRET_ACCESS_KEY' ) ) :
			$aws_key = AWS_ACCESS_KEY_ID;
			$aws_secret = AWS_SECRET_ACCESS_KEY;
		elseif ( !empty( $short['key'] ) && !empty( $short['secret'] ) ) :
			$aws_key = $short['key'];
			$aws_secret = $short['secret'];
		else :
			return array( 'state' => 'error', 'message' => 'No S3 credentials provided. Please check your settings.' );
		endif;

		if ( is_plugin_active( 'amazon-web-services/amazon-web-services.php' ) ) :
			$client = Aws\S3\S3Client::factory(array(
				'key' => $aws_key,
				'secret' => $aws_secret
			));
		else :
			require HPM_PODCAST_PLUGIN_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'aws' . DIRECTORY_SEPARATOR . 'aws-autoloader.php';
			$client = new Aws\S3\S3Client([
				'version' => 'latest',
				'region'  => $short['region'],
				'credentials' => [
					'key' => $aws_key,
					'secret' => $aws_secret
				]
			]);
		endif;

		if ( !empty( $short['folder'] ) ) :
			$folder = $short['folder'].'/';
		else :
			$folder = '';
		endif;

		try {
			$result = $client->putObject(array(
				'Bucket' => $short['bucket'],
				'Key' => $folder.$request['feed'].'/'.$path['basename'],
				'SourceFile' => $local,
				'ACL' => 'public-read',
				'ContentType' => $med->post_mime_type
			));
		} catch (S3Exception $e) {
			$message = $e->getMessage();
		} catch (AwsException $e) {
			$message = $e->getAwsRequestId() . "\n" . $e->getAwsErrorType() . "\n" . $e->getAwsErrorCode();
		}
		$sg_url = 'https://s3-'.$short['region'].'.amazonaws.com/'.$short['bucket'].'/'.$folder.$request['feed'].'/'.$path['basename'];
	else :
		return new WP_Error( 'rest_api_sad', esc_html__( 'No media upload target was selected. Please check your settings.', 'hpm-podcasts' ), array( 'status' => 500 ) );
	endif;
	if ( empty( $message ) ) :
		if ( $download ) :
			unlink( $local );
		endif;
		if ( !empty( $sg_url ) ) :
			$hpm_pod_sg_file = metadata_exists( 'post', $request['id'], 'hpm_podcast_sg_file' );
			if ( $hpm_pod_sg_file ) :
				update_post_meta( $request['id'], 'hpm_podcast_sg_file', $sg_url );
			else :
				add_post_meta( $request['id'], 'hpm_podcast_sg_file', $sg_url, true );
			endif;
			return rest_ensure_response( array( 'code' => 'rest_api_success', 'message' => esc_html__( 'Podcast media file uploaded successfully.', 'hpm-podcasts' ), 'data' => array( 'url' => $sg_url, 'status' => 200 ) ) );
		else :
			return new WP_Error( 'rest_api_sad', esc_html__( 'Unable to determine the remote URL of your media file. Please check your settings and try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		endif;
	else :
		return new WP_Error( 'rest_api_sad', esc_html__( $message, 'hpm-podcasts' ), array( 'status' => 500 ) );
	endif;
}

/**
 * Pull a list of podcasts, generate the feeds, and save them as flat XML files, either locally, or in the FTP, SFTP
 * or S3 bucket defined
 *
 * @return mixed
 */
function hpm_podcast_rest_generate() {
	$pods = get_option( 'hpm_podcasts' );
	if ( !empty( $pods['https'] ) ) :
		$protocol = 'https://';
		$_SERVER['HTTPS'] = 'on';
	else :
		$protocol = 'http://';
	endif;
	global $wpdb;
	$error = '';
	$dir = wp_upload_dir();
	$save = $dir['basedir'];
	if ( !empty( $pods['upload-flats'] ) ) :
		if ( $pods['upload-flats'] == 's3' ) :
			$short = $pods['credentials']['s3'];
			if ( defined( 'AWS_ACCESS_KEY_ID' ) && defined( 'AWS_SECRET_ACCESS_KEY' ) ) :
				$aws_key = AWS_ACCESS_KEY_ID;
				$aws_secret = AWS_SECRET_ACCESS_KEY;
			elseif ( !empty( $short['key'] ) && !empty( $short['secret'] ) ) :
				$aws_key = $short['key'];
				$aws_secret = $short['secret'];
			else :
				return new WP_Error( 'rest_api_sad', esc_html__( 'No S3 credentials provided. Please check your settings.', 'hpm-podcasts' ), array( 'status' => 500 ) );
			endif;

			if ( is_plugin_active( 'amazon-web-services/amazon-web-services.php' ) ) :
				$client = Aws\S3\S3Client::factory(array(
					'key' => $aws_key,
					'secret' => $aws_secret
				));
			else :
				require HPM_PODCAST_PLUGIN_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'aws' . DIRECTORY_SEPARATOR . 'aws-autoloader.php';
				$client = new Aws\S3\S3Client([
					'version' => 'latest',
					'region'  => $short['region'],
					'credentials' => [
						'key' => $aws_key,
						'secret' => $aws_secret
					]
				]);
			endif;
		elseif ( $pods['upload-flats'] == 'ftp' ) :
			$short = $pods['credentials']['ftp'];
			if ( defined( 'HPM_FTP_PASSWORD' ) ) :
				$ftp_password = HPM_FTP_PASSWORD;
			elseif ( !empty( $short['password'] ) ) :
				$ftp_password = $short['password'];
			else :
				return new WP_Error( 'rest_api_sad', esc_html__( 'No FTP password provided. Please check your settings.', 'hpm-podcasts' ), array( 'status' => 500 ) );
			endif;
		elseif ( $pods['upload-flats'] == 'sftp' ) :
			$short = $pods['credentials']['sftp'];
			$ipath = HPM_PODCAST_PLUGIN_DIR .'vendor' . DIRECTORY_SEPARATOR . 'phpseclib';
			set_include_path(get_include_path() . PATH_SEPARATOR . $ipath);
			include( 'Net/SFTP.php' );
			if ( defined( 'HPM_SFTP_PASSWORD' ) ) :
				$sftp_password = HPM_SFTP_PASSWORD;
			elseif ( !empty( $short['password'] ) ) :
				$sftp_password = $short['password'];
			else :
				return new WP_Error( 'rest_api_sad', esc_html__( 'No FTP password provided. Please check your settings.', 'hpm-podcasts' ), array( 'status' => 500 ) );
			endif;
		elseif ( $pods['upload-flats'] == 'database' ) :

		else :
			$error .= "No flat file upload target defined. Please check your settings and try again.";
		endif;
	else :
		if ( !file_exists( $save.'/hpm-podcasts' ) ) :
			mkdir( $save.'/hpm-podcasts' );
		endif;
	endif;

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
	if ( $podcasts->have_posts() ) :
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
				$categories = array();
				foreach ( $podlink['categories'] as $pos => $cats ) :
					$categories[$pos] = explode( '||', $cats );
				endforeach;
				$podcast_title = $podcasts->post->post_name; ?>
				<channel>
					<title><?php the_title_rss(); ?></title>
					<atom:link href="<?php echo get_the_permalink(); ?>" rel="self" type="application/rss+xml" />
					<link><?php echo $podlink['page']; ?></link>
					<description><![CDATA[<?php the_content_feed(); ?>]]></description>
					<language><?php bloginfo_rss( 'language' ); ?></language>
					<copyright>All Rights Reserved</copyright>
					<ttl><?php echo $frequency; ?></ttl>
					<pubDate><?php echo date('r'); ?></pubDate>
					<itunes:summary><![CDATA[<?php the_content_feed(); ?>]]></itunes:summary>
					<itunes:owner>
						<itunes:name><![CDATA[<?php echo $pods['owner']['name']; ?>]]></itunes:name>
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
							$url = str_replace( array( 'http://', 'https://' ), array('',''), $url );
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
					try {
						$result = $client->putObject(array(
							'Bucket' => $short['bucket'],
							'Key' => ( !empty( $short['folder'] ) ? $short['folder'].'/' : '' ) .$podcast_title.'.xml',
							'Body' => $getContent_mini,
							'ACL' => 'public-read',
							'ContentType' => 'application/rss+xml'
						));
					} catch ( S3Exception $e ) {
						$error .= $podcast_title.": ".$e->getMessage()."<br /><br />";
					} catch ( AwsException $e ) {
						$error .= $podcast_title . ": " . $e->getAwsRequestId() . "<br />" . $e->getAwsErrorType() . "<br />" . $e->getAwsErrorCode() . "<br /><br />";
					}
				elseif ( $pods['upload-flats'] == 'ftp' ) :
					$local = $save . DIRECTORY_SEPARATOR . $podcast_title . '.xml';
					if ( !file_put_contents( $local, $getContent_mini ) ) :
						return new WP_Error( 'rest_api_sad', esc_html__( 'Could not generate flat file.', 'hpm-podcasts' ), array( 'status' => 500 ) );
					endif;
					try {
						$con = ftp_connect( $short['host'] );
						if ( false === $con ) :
							throw new Exception($podcast_title.": Unable to connect to the FTP server. Please check your FTP Host URL or IP and try again.<br /><br />");
						endif;

						$loggedIn = ftp_login( $con,  $short['username'], $ftp_password );
						if ( false === $loggedIn ) :
							throw new Exception($podcast_title.": Unable to log in to the FTP server. Please check your credentials and try again.<br /><br />");
						endif;
						if ( !empty( $short['folder'] ) ) :
							if ( !ftp_chdir( $con, $short['folder'] ) ) :
								ftp_mkdir( $con, $short['folder'] );
								ftp_chdir( $con, $short['folder'] );
							endif;
						endif;
						if ( ! ftp_put( $con, $podcast_title.'.xml', $local, FTP_BINARY ) ) :
							throw new Exception($podcast_title.": Unable to upload your feed file to the FTP server. Please check your permissions on that server and try again.<br /><br />" );
						endif;
						ftp_close( $con );
					} catch (Exception $e) {
						$error .= $e->getMessage();
					}
					unset( $con );
					unset( $local );
				elseif ( $pods['upload-flats'] == 'sftp' ) :
					$local = $save . DIRECTORY_SEPARATOR . $podcast_title . '.xml';
					if ( !file_put_contents( $local, $getContent_mini ) ) :
						return new WP_Error( 'rest_api_sad', esc_html__( 'Could not generate flat file.', 'hpm-podcasts' ), array( 'status' => 500 ) );
					endif;
					try {
						$sftp = new Net_SFTP( $short['host'] );
						if ( ! $sftp->login( $short['username'], $sftp_password ) ) :
							throw new Exception( $podcast_title . ": SFTP Login Failed. Please check your credentials and try again.<br /><br />" );
						endif;
						if ( !empty( $short['folder'] ) ) :
							if ( !$sftp->chdir( $short['folder'] ) ) :
								$sftp->mkdir( $short['folder'] );
								$sftp->chdir( $short['folder'] );
							endif;
						endif;
						if ( ! $sftp->put( $podcast_title . '.xml', $local, NET_SFTP_LOCAL_FILE ) ) :
							throw new Exception( $podcast_title . ": Unable to upload your feed file to the SFTP server. Please check your permissions on that server and try again.<br /><br />" );
						endif;
					} catch (Exception $e) {
						$error .= $e->getMessage();
					}
					unset( $sftp );
					unset( $local );
				elseif ( $pods['upload-flats'] == 'database' ) :
					$option = get_option( 'hpm_podcasts-'.$podcast_title );
					if ( empty( $option ) ) :
						add_option( 'hpm_podcasts-'.$podcast_title, $getContent_mini );
					else :
						update_option( 'hpm_podcasts-'.$podcast_title, $getContent_mini );
					endif;
				else :
					$error .= "No flat file upload target defined. Please check your settings and try again.";
				endif;
			else :
				$file_write = file_put_contents( $save.'/hpm-podcasts/'.$podcast_title.'.xml',
					$getContent_mini );
				if ( $file_write === FALSE ) :
					$error .= $podcast_title.": There was an error writing your cache file into the Uploads directory. Please check the error log.<br /><br />";
				endif;
			endif;
			sleep(5);
		endwhile;
		if ( !empty( $error ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( $error, 'hpm-podcasts' ), array( 'status' => 500 ) );
		else :
			$t = time();
			$update_last = get_option( 'hpm_podcasts_last_update' );
			$offset = get_option('gmt_offset')*3600;
			$time = $t + $offset;
			$date = date( 'F j, Y @ g:i A', $time );
			if ( empty( $update_last ) ) :
				add_option( 'hpm_podcasts_last_update', $time );
			else :
				update_option( 'hpm_podcasts_last_update', $time );
			endif;
			return rest_ensure_response( array( 'code' => 'rest_api_success', 'message' => esc_html__('Podcast feeds successfully updated!', 'hpm-podcasts' ), 'data' => array( 'date' => $date, 'timestamp' => $time, 'status' =>
				200 ) ) );
		endif;
	else :
		return new WP_Error( 'rest_api_sad', esc_html__( 'No podcast feeds have been defined. Please create one and try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
	endif;
}