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
	protected $data = [];

	/**
	 * Initiate new async request
	 */
	public function __construct() {
		add_action( 'rest_api_init', function() {
			register_rest_route( 'hpm-podcast/v1', '/upload/(?P<feed>[a-zA-Z0-9\-_]+)/(?P<id>[\d]+)/(?P<attach>[\d]+)/process',
				[
					'methods'  => 'POST',
					'callback' => [ $this, 'maybe_handle' ],
					'args' => [
						'id' => [
							'required' => true
						],
						'feed' => [
							'required' => true
						],
						'attach' => [
							'required' => true
						]
					]
				]
			);
		});
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
		return WP_HOME . '/wp-json/hpm-podcast/v1/upload/'.$this->data['feed'].'/'.$this->data['id'].'/'.$this->data['attach'].'/process';
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

		return [
			'timeout'   => 0.01,
			'blocking'  => false,
			'body'      => $this->data,
			'cookies'   => $_COOKIE,
			'sslverify' => apply_filters( 'https_local_ssl_verify', false )
		];
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
		$attach = $_REQUEST['attach'];
		$pods = get_option( 'hpm_podcast_settings' );
		$ds = DIRECTORY_SEPARATOR;
		
		if ( empty( $pods['upload-media'] ) ) :
			update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'error', 'message' => esc_html__( 'No media upload target was selected. Please check your settings.', 'hpm-podcasts' ) ] );
			return false;
		endif;

		$message = '';
		$download = false;

		$dir = wp_upload_dir();
		$save = $dir['basedir'];

		$url = wp_get_attachment_url( $attach );
		log_it( $url );
		$metadata = get_post_meta( $attach, '_wp_attachment_metadata', true );

		if ( strpos( $url, $dir['baseurl'] ) !== FALSE ) :
			$meta = get_post_meta( $attach, '_wp_attached_file', true );
			$local = $save . $ds . $meta;
			$path = pathinfo( $meta );
			update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'in progress', 'message' => esc_html__( 'Podcast file exists on the local server, proceeding.', 'hpm-podcasts' ) ] );
		else :
			$download = true;
			$parse = parse_url( $url );
			$path = pathinfo( $parse['path'] );
			$local = $save . $ds . $path['basename'];
			update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'in progress', 'message' => esc_html__( 'Podcast file is being downloaded to the local server.', 'hpm-podcasts' ) ] );
			$remote = wp_remote_get( esc_url_raw( $url ) );
			if ( is_wp_error( $remote ) ) :
				update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'error', 'message' => esc_html__( 'Unable to download your media file to the local server. Please try again.', 'hpm-podcasts' ) ] );
				return false;
			else :
				$remote_body = wp_remote_retrieve_body( $remote );
			endif;
			if ( !file_put_contents( $local, $remote_body ) ) :
				update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'error', 'message' => esc_html__( 'Unable to download your media file to the local server. Please try again.', 'hpm-podcasts' ) ] );
				return false;
			endif;
		endif;

		update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'in progress', 'message' => esc_html__( 'Podcast file downloaded to local server. Connecting to remote host.', 'hpm-podcasts' ) ] );

		if ( $pods['upload-media'] == 'sftp' ) :
			$short = $pods['credentials']['sftp'];
			$autoload = $ds . 'vendor' . $ds . 'autoload.php';
			if ( file_exists( HPM_PODCAST_PLUGIN_DIR . $autoload ) ) :
				require HPM_PODCAST_PLUGIN_DIR . $autoload;
			else :
				require SITE_ROOT . $autoload;
			endif;
			$sftp = new \phpseclib\Net\SFTP( $short['host'] );
			if ( defined( 'HPM_SFTP_PASSWORD' ) ) :
				$sftp_password = HPM_SFTP_PASSWORD;
			elseif ( !empty( $short['password'] ) ) :
				$sftp_password = $short['password'];
			else :
				update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'error', 'message' => esc_html__( 'No SFTP password provided. Please check your settings.', 'hpm-podcasts' ) ] );
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

			update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'in progress', 'message' => esc_html__( 'Remote host connected. Starting upload.', 'hpm-podcasts' ) ] );

			if ( !$sftp->put( $path['basename'], $local, \phpseclib\Net\SFTP::SOURCE_LOCAL_FILE ) ) :
				$message = "The file could not be saved on the SFTP server. Please verify your permissions on that server and try again.";
			endif;
			unset( $sftp );
			$sg_url = $short['url'].'/'.$feed.'/'.$path['basename'];
		else :
			update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'error', 'message' => esc_html__( 'No media upload target was selected. Please check your settings.', 'hpm-podcasts' ) ] );
			return false;
		endif;
		if ( empty( $message ) ) :
			if ( $download ) :
				unlink( $local );
			endif;
			if ( !empty( $sg_url ) ) :
				$enclose = [
					'url' => $sg_url,
					'filesize' => $metadata['filesize'],
					'mime' => $metadata['mime_type'],
					'length' => $metadata['length_formatted']
				];
				update_post_meta( $id, 'hpm_podcast_enclosure', $enclose );
				$ep_meta = get_post_meta( $id, 'hpm_podcast_ep_meta', true );
				if ( !empty( $ep_meta ) ) :
					$ep_meta['feed'] = $feed;
				else :
					$ep_meta = [ 'feed' => $feed, 'description' => '' ];
				endif;
				update_post_meta( $id, 'hpm_podcast_ep_meta', $ep_meta );

				update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'success', 'message' => esc_html__( 'Podcast media file uploaded successfully.', 'hpm-podcasts' ) ] );
				return true;
			else :
				update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'error', 'message' => esc_html__( 'Unable to determine the remote URL of your media file. Please check your settings and try again.', 'hpm-podcasts' ) ] );
				return false;
			endif;
		else :
			update_post_meta( $id, 'hpm_podcast_status', [ 'status' => 'error', 'message' => esc_html__( $message, 'hpm-podcasts' ) ] );
			return false;
		endif;
	}
}