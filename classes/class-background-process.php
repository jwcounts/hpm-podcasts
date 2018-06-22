<?php

class HPM_Media_Upload {

	/**
	 * Data
	 *
	 * (default value: array())
	 *
	 * @var array
	 * @access protected
	 */
	protected $data = array();

	/**
	 * Initiate new async request
	 */
	public function __construct() {
		add_action( 'rest_api_init', function(){
			register_rest_route( 'hpm-podcast/v1', '/upload/(?P<feed>[a-zA-Z0-9\-_]+)/(?P<id>[\d]+)/process',
				array(
				'methods'  => 'POST',
				'callback' => array( $this, 'maybe_handle'),
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
	}

	/**
	 * Set data used during the request
	 *
	 * @param array $data Data.
	 *
	 * @return $this
	 */
	public function data( $data ) {
		$this->data = $data;

		return $this;
	}

	/**
	 * Dispatch the async request
	 *
	 * @return array|WP_Error
	 */
	public function dispatch() {
		$url  = $this->get_query_url();
		$args = $this->get_post_args();

		return wp_remote_post( esc_url_raw( $url ), $args );
	}

	/**
	 * Get query URL
	 *
	 * @return string
	 */
	protected function get_query_url() {
		return site_url( '/wp-json/hpm-podcast/v1/upload/'.$this->data['feed'].'/'.$this->data['id'].'/process' );
	}

	/**
	 * Get post args
	 *
	 * @return array
	 */
	protected function get_post_args() {
		if ( property_exists( $this, 'post_args' ) ) {
			return $this->post_args;
		}

		return array(
			'timeout'   => 0.01,
			'blocking'  => false,
			'body'      => $this->data,
			'cookies'   => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
		);
	}

	/**
	 * Maybe handle
	 *
	 * Check for correct nonce and pass to handler.
	 */
	public function maybe_handle() {

		// Don't lock up other requests while processing
		session_write_close();

		$this->handle();

		wp_die();
	}

	/**
	 * Handle
	 *
	 * Override this method to perform any actions required
	 * during the async request.
	 */
	protected function handle() {
		$id = $_REQUEST['id'];
		$feed = $_REQUEST['feed'];
		$pods = get_option( 'hpm_podcast_settings' );
		$ds = DIRECTORY_SEPARATOR;
		
		if ( empty( $pods['upload-media'] ) ) :
			update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'No media upload target was selected. Please check your settings.', 'hpm-podcasts' ) ) );
			return false;
		endif;

		$message = '';
		$download = false;

		$dir = wp_upload_dir();
		$save = $dir['basedir'];
		$media = get_attached_media( 'audio', $id );
		if ( empty ( $media ) ) :
			$enclosure = get_post_meta( $id, 'hpm_podcast_enclosure');
			if ( empty( $enclosure ) ) :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'No audio are attached to this post. Please attach one and try again.', 'hpm-podcasts' ) ) );
				return false;
			else :
				if ( strpos( $enclosure['url'], $pods['credentials'][$pods['upload-media']]['url'] ) === FALSE ) :
					$url = $enclosure['url'];
					$metadata = array(
						'filesize' => $enclosure['filesize'],
						'mime_type' => $enclosure['mime'],
						'length_formatted' => $enclosure['length'],
						'url' => $enclosure['url']
					);
				else :
					update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'No audio are attached to this post and the enclosure audio already exists on the server. Please attach one and try again.',	'hpm-podcasts' ) ) );
					return false;
				endif;
			endif;
		else :
			if ( count( $media ) > 1 ) :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'More than one audio file has been attached to this post. Please delete the extra audio files and try again.', 'hpm-podcasts' ) ) );
				return false;
			endif;
			$med = reset( $media );
			$med_id = $med->ID;
			$url = wp_get_attachment_url( $med_id );
			$metadata = get_post_meta( $med_id, '_wp_attachment_metadata', true );
		endif;

		if ( strpos( $url, $dir['baseurl'] ) !== FALSE ) :
			$meta = get_post_meta( $med_id, '_wp_attached_file', true );
			$local = $save . $ds . $meta;
			$path = pathinfo( $meta );
			update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'in progress', 'message' => esc_html__( 'Podcast file exists on the local server, proceeding.', 'hpm-podcasts' ) ) );
		else :
			$download = true;
			$parse = parse_url( $url );
			$path = pathinfo( $parse['path'] );
			$local = $save . $ds . $path['basename'];
			update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'in progress', 'message' => esc_html__( 'Podcast file is being downloaded to the local server.', 'hpm-podcasts' ) ) );
			$remote = wp_remote_get( esc_url_raw( $url ) );
			if ( is_wp_error( $remote ) ) :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'Unable to download your media file to the local server. Please try again.', 'hpm-podcasts' ) ) );
				return false;
			else :
				$remote_body = wp_remote_retrieve_body( $remote );
			endif;
			if ( !file_put_contents( $local, $remote_body ) ) :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'Unable to download your media file to the local server. Please try again.', 'hpm-podcasts' ) ) );
				return false;
			endif;
		endif;

		update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'in progress', 'message' => esc_html__( 'Podcast file downloaded to local server. Connecting to remote host.', 'hpm-podcasts' ) ) );

		if ( $pods['upload-media'] == 'ftp' ) :
			$short = $pods['credentials']['ftp'];
			if ( defined( 'HPM_FTP_PASSWORD' ) ) :
				$ftp_password = HPM_FTP_PASSWORD;
			elseif ( !empty( $short['password'] ) ) :
				$ftp_password = $short['password'];
			else :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'No FTP password provided. Please check your settings.', 'hpm-podcasts' ) ) );
				return false;
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

				if ( !ftp_chdir( $con, $feed ) ) :
					ftp_mkdir( $con, $feed );
					ftp_chdir( $con, $feed );
				endif;

				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'in progress', 'message' => esc_html__( 'Remote host connected. Starting upload.', 'hpm-podcasts' ) ) );

				if ( ! ftp_put( $con, $path['basename'], $local, FTP_BINARY ) ) :
					throw new Exception("The file could not be saved on the FTP server. Please verify your permissions on that server and try again." );
				endif;
				ftp_close( $con );

				$sg_url = $short['url'].'/'.$feed.'/'.$path['basename'];

			} catch ( Exception $e ) {
				$message = $e->getMessage();
			}
		elseif ( $pods['upload-media'] == 'sftp' ) :
			$short = $pods['credentials']['sftp'];
			require HPM_PODCAST_PLUGIN_DIR . $ds . 'vendor' . $ds . 'autoload.php';
			$sftp = new \phpseclib\Net\SFTP( $short['host'] );
			if ( defined( 'HPM_SFTP_PASSWORD' ) ) :
				$sftp_password = HPM_SFTP_PASSWORD;
			elseif ( !empty( $short['password'] ) ) :
				$sftp_password = $short['password'];
			else :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'No SFTP password provided. Please check your settings.', 'hpm-podcasts' ) ) );
				return false;
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

			if ( !$sftp->chdir( $feed ) ) :
				$sftp->mkdir( $feed );
				$sftp->chdir( $feed );
			endif;

			update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'in progress', 'message' => esc_html__( 'Remote host connected. Starting upload.', 'hpm-podcasts' ) ) );

			if ( !$sftp->put( $path['basename'], $local, \phpseclib\Net\SFTP::SOURCE_LOCAL_FILE ) ) :
				$message = "The file could not be saved on the SFTP server. Please verify your permissions on that server and try again.";
			endif;
			unset( $sftp );
			$sg_url = $short['url'].'/'.$feed.'/'.$path['basename'];
		elseif ( $pods['upload-media'] == 's3' ) :
			if ( !class_exists('\Aws\S3\S3Client') ) :
				require __DIR__ . $ds . 'vendor' . $ds . 'aws.phar';
			endif;
			$short = $pods['credentials']['s3'];
			if ( defined( 'AWS_ACCESS_KEY_ID' ) && defined( 'AWS_SECRET_ACCESS_KEY' ) ) :
				$aws_key = AWS_ACCESS_KEY_ID;
				$aws_secret = AWS_SECRET_ACCESS_KEY;
			elseif ( !empty( $short['key'] ) && !empty( $short['secret'] ) ) :
				$aws_key = $short['key'];
				$aws_secret = $short['secret'];
			else :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'No S3 credentials provided. Please check your settings.', 'hpm-podcasts' ) ) );
				return false;
			endif;
			require HPM_PODCAST_PLUGIN_DIR . $ds . 'vendor' . $ds . 'autoload.php';
			$client = new Aws\S3\S3Client([
				'version' => 'latest',
				'region'  => $short['region'],
				'credentials' => [
					'key' => $aws_key,
					'secret' => $aws_secret
				]
			]);

			if ( !empty( $short['folder'] ) ) :
				$folder = $short['folder'].'/';
			else :
				$folder = '';
			endif;

			update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'in progress', 'message' => esc_html__( 'Remote host connected. Starting upload.', 'hpm-podcasts' ) ) );

			try {
				$result = $client->putObject(array(
					'Bucket' => $short['bucket'],
					'Key' => $folder.$feed.'/'.$path['basename'],
					'SourceFile' => $local,
					'ACL' => 'public-read',
					'ContentType' => $med->post_mime_type
				));
			} catch ( S3Exception $e ) {
				$message = $e->getMessage();
			} catch ( AwsException $e ) {
				$message = $e->getAwsRequestId() . "\n" . $e->getAwsErrorType() . "\n" . $e->getAwsErrorCode();
			}
			$sg_url = 'https://s3-'.$short['region'].'.amazonaws.com/'.$short['bucket'].'/'.$folder.$feed.'/'.$path['basename'];
		else :
			update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'No media upload target was selected. Please check your settings.', 'hpm-podcasts' ) ) );
			return false;
		endif;
		if ( empty( $message ) ) :
			if ( $download ) :
				unlink( $local );
			endif;
			if ( !empty( $sg_url ) ) :
				$enclose = array(
					'url' => $sg_url,
					'filesize' => $metadata['filesize'],
					'mime' => $metadata['mime_type'],
					'length' => $metadata['length_formatted']
				);
				update_post_meta( $id, 'hpm_podcast_enclosure', $enclose );
				$ep_meta = get_post_meta( $id, 'hpm_podcast_ep_meta', true );
				if ( !empty( $ep_meta ) ) :
					$ep_meta['feed'] = $feed;
				else :
					$ep_meta = array( 'feed' => $feed, 'description' => '' );
				endif;
				update_post_meta( $id, 'hpm_podcast_ep_meta', $ep_meta );

				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'success', 'message' => esc_html__( 'Podcast media file uploaded successfully.', 'hpm-podcasts' ) ) );
				return true;
			else :
				update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( 'Unable to determine the remote URL of your media file. Please check your settings and try again.', 'hpm-podcasts' ) ) );
				return false;
			endif;
		else :
			update_post_meta( $id, 'hpm_podcast_status', array( 'status' => 'error', 'message' => esc_html__( $message, 'hpm-podcasts' ) ) );
			return false;
		endif;
	}
}