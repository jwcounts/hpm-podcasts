<?php
/**
 * @link 			https://github.com/jwcounts/hpm-podcasts
 * @since  			1.5
 * @package  		HPM-Podcasts
 *
 * @wordpress-plugin
 * Plugin Name: 	HPM Podcasts
 * Plugin URI: 		https://github.com/jwcounts/hpm-podcasts
 * Description: 	A plugin that allows you to create a podcast feed from any category, as well as
 * Version: 		1.5
 * Author: 			Jared Counts
 * Author URI: 		https://www.houstonpublicmedia.org/staff/jared-counts/
 * License: 		GPL-2.0+
 * License URI: 	http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: 	hpm-podcasts
 *
 * Works best with Wordpress 4.6.0+
 */

class HPM_Podcasts {

	/**
	 * @var HPM_Media_Upload
	 */
	protected $process_upload;

	protected $options;

	protected $last_update;

	public function __construct() {
		define( 'HPM_PODCAST_PLUGIN_DIR', plugin_dir_path(__FILE__) );
		add_action( 'plugins_loaded', [ $this, 'init' ] );
		add_action( 'init', [ $this, 'create_type' ] );
		register_activation_hook( __FILE__, [ $this, 'activation' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivation' ] );
	}
	/**
	 * Init
	 */

	public function init() {
		$this->options = get_option( 'hpm_podcast_settings' );
		$this->last_update = get_option( 'hpm_podcast_last_update' );

		require_once HPM_PODCAST_PLUGIN_DIR . 'classes' . DIRECTORY_SEPARATOR . 'class-background-process.php';

		$this->process_upload = new HPM_Media_Upload();

		add_filter( 'cron_schedules', [ $this, 'cron' ], 10, 2 );
		add_action( 'hpm_podcast_update_refresh', [ $this, 'generate' ] );
		add_filter( 'pre_update_option_hpm_podcast_settings', [ $this, 'options_clean' ], 10, 2 );

		// Setup meta box in article editor
		add_action( 'load-post.php', [ $this, 'description_setup' ] );
		add_action( 'load-post-new.php', [ $this, 'description_setup' ] );

		// Add edit capabilities to selected roles
		add_action( 'admin_init', [ $this, 'add_role_caps' ], 999 );

		// Setup metadata for podcast feeds
		add_action( 'load-post.php', [ $this, 'meta_setup' ] );
		add_action( 'load-post-new.php', [ $this, 'meta_setup' ] );

		// Register page templates
		add_filter( 'archive_template', [ $this, 'archive_template' ] );
		add_filter( 'single_template', [ $this, 'single_template' ] );
		remove_all_actions( 'do_feed_rss2' );
		add_action( 'do_feed_rss2', [ $this, 'feed_template' ], 10, 1 );

		// Create menu in Admin Dashboard
		add_action( 'admin_menu', [ $this, 'create_menu' ] );

		//Adds meta query to always list podcast archive in alphabetical order
		add_action( 'pre_get_posts', [ $this, 'meta_query' ] );

		// Register WP-REST API endpoints
		add_action( 'rest_api_init', function() {
			register_rest_route( 'hpm-podcast/v1', '/refresh', [
				'methods'  => 'GET',
				'callback' => [ $this, 'generate' ]
			] );

			register_rest_route( 'hpm-podcast/v1', '/upload/(?P<feed>[a-zA-Z0-9\-_]+)/(?P<id>[\d]+)/(?P<attach>[\d]+)', [
				'methods'  => 'GET',
				'callback' => [ $this, 'upload'],
				'args' => [
					'id' => [
						'required' => true
					],
					'feed' => [
						'required' => true
					]
				]
			 ] );

			register_rest_route( 'hpm-podcast/v1', '/upload/(?P<id>[\d]+)/progress', [
				'methods'  => 'GET',
				'callback' => [ $this, 'upload_progress'],
				'args' => [
					'id' => [
						'required' => true
					]
				]
			] );
		} );

		// Make sure that the proper cron job is scheduled
		if ( ! wp_next_scheduled( 'hpm_podcast_update_refresh' ) ) :
			wp_schedule_event( time(), $this->options['recurrence'], 'hpm_podcast_update_refresh' );
		endif;
	}

	/**
	 * Add in new cron schedule options
	 *
	 * @param $schedules
	 *
	 * @return mixed
	 */
	public function cron( $schedules ) {
		$schedules['hpm_15min'] = [
			'interval' => 900,
			'display' => __( 'Every 15 Minutes' )
		];
		$schedules['hpm_30min'] = [
			'interval' => 1800,
			'display' => __( 'Every 30 Minutes' )
		];
		return $schedules;
	}

	public function options_clean( $new_value, $old_value ) {
		$find = [ '{/$}', '{^/}' ];
		$replace = [ '', '' ];
		foreach ( $new_value['credentials'] as $credk => $credv ) :
			foreach ( $credv as $k => $v ) :
				if ( !empty( $v ) && ( $k != 'key' || $k != 'secret' || $k != 'password' ) ) :
					$new_value['credentials'][$credk][$k] = preg_replace( $find, $replace, $v );
				elseif ( $k == 'key' || $k == 'secret' || $k == 'password' ) :
					if ( $v == 'Set in wp-config.php' ) :
						$new_value['credentials'][$credk][$k] = '';
					endif;
				endif;
			endforeach;
		endforeach;
		if ( $new_value['recurrence'] != $old_value['recurrence'] ) :
			if ( ! wp_next_scheduled( 'hpm_podcast_update_refresh' ) ) :
				wp_schedule_event( time(), $new_value['recurrence'], 'hpm_podcast_update_refresh' );
			else :
				wp_clear_scheduled_hook( 'hpm_podcast_update_refresh' );
				wp_schedule_event( time(), $new_value['recurrence'], 'hpm_podcast_update_refresh' );
			endif;
		endif;
		return $new_value;
	}

	public function activation() {
		$pods = [
			'owner' => [
				'name' => '',
				'email' => ''
			],
			'recurrence' => 'hourly',
			'roles' => ['editor','administrator'],
			'upload-media' => 'sftp',
			'upload-flats' => 'database',
			'credentials' => [
				'sftp' => [
					'host' => '',
					'url' => '',
					'username' => '',
					'password' => '',
					'folder' => ''
				]
			],
			'https' => ''
		];
		update_option( 'hpm_podcast_settings', $pods, false );
		update_option( 'hpm_podcast_last_update', 'none', false );
		$this->options = $pods;
		$this->last_update = 'none';
		HPM_Podcasts::create_type();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( 'hpm_podcast_update_refresh' ) ) :
			wp_schedule_event( time(), 'hourly', 'hpm_podcast_update_refresh' );
		endif;
		if ( wp_mkdir_p( get_stylesheet_directory() . '/hpm-podcasts/' ) ) :
			copy( HPM_PODCAST_PLUGIN_DIR . 'templates/single.php', get_stylesheet_directory() . '/hpm-podcasts/single.php' );
			copy( HPM_PODCAST_PLUGIN_DIR . 'templates/archive.php', get_stylesheet_directory() . '/hpm-podcasts/archive.php' );
		endif;
	}

	public function deactivation() {
		wp_clear_scheduled_hook( 'hpm_podcast_update_refresh' );
		delete_option( 'hpm_podcast_settings' );
		delete_option( 'hpm_podcast_last_update' );
		flush_rewrite_rules();
	}

	public function single_template( $single ) {
		global $post;
		if ( $post->post_type == "podcasts" ) :
			if ( file_exists( get_stylesheet_directory() . '/hpm-podcasts/single.php' ) ) :
				return get_stylesheet_directory() . '/hpm-podcasts/single.php';
			else :
				return HPM_PODCAST_PLUGIN_DIR . 'templates/single.php';
			endif;
		endif;
		return $single;
	}

	public function archive_template( $archive_template ) {
		global $post;
		if ( is_post_type_archive ( 'podcasts' ) ) :
			if ( file_exists( get_stylesheet_directory() . '/hpm-podcasts/archive.php' ) ) :
				return get_stylesheet_directory() . '/hpm-podcasts/archive.php';
			else :
				return HPM_PODCAST_PLUGIN_DIR . 'templates/archive.php';
			endif;
		endif;
		return $archive_template;
	}

	public function feed_template() {
		if ( 'podcasts' === get_query_var( 'post_type' ) ) :
			if ( file_exists( get_stylesheet_directory() . '/hpm-podcasts/single.php' ) ) :
				load_template( get_stylesheet_directory() . '/hpm-podcasts/single.php' );
			else :
				load_template( HPM_PODCAST_PLUGIN_DIR . 'templates/single.php' );
			endif;
		elseif ( file_exists( get_stylesheet_directory() . '/feed-rss2.php' ) ) :
			load_template( get_stylesheet_directory() . '/feed-rss2.php' );
		else :
			get_template_part( 'feed', 'rss2' );
		endif;
	}

	/**
	 * Creating and setting up the metadata boxes in the post editor
	 */

	public function description_setup() {
		add_action( 'add_meta_boxes', [ $this, 'add_description' ] );
		add_action( 'save_post', [ $this, 'save_description' ], 10, 2 );
	}

	public function add_description() {
		add_meta_box(
			'hpm-podcast-meta-class',
			esc_html__( 'Podcast Feed Information', 'hpm-podcasts' ),
			[ $this, 'description_box' ],
			'post',
			'advanced',
			'default'
		);
	}

	/**
	 * Adds a textarea for podcast feed-specific excerpts.
	 *
	 * Also, if you are storing your media files on another server, an option to assign your media file to a certain
	 * feed, so that the files can be organized on the remote server, will appear, as well as an area for manual URL entry.
	 */
	public function description_box( $object, $box ) {
		$pods = $this->options;
		global $post;
		$post_old = $post;
		wp_nonce_field( basename( __FILE__ ), 'hpm_podcast_class_nonce' );
		$hpm_pod_desc = get_post_meta( $object->ID, 'hpm_podcast_ep_meta', true );
		if ( empty( $hpm_pod_desc ) ) :
			$hpm_pod_desc = [ 'title' => '', 'feed' => '', 'description' => '', 'episode' => '', 'season' => '', 'episodeType'
			=> 'full' ];
		endif;
		include __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR .'post-editor.php';
		wp_reset_query();
		$post = $post_old;
	}

	/**
	 * Saving the media file feed and episode-specific description in postmeta.
	 *
	 * If your media files are being uploaded to another service, this function will also kick off a cron job to handle
	 * the upload.
	 *
	 * @param $post_id
	 * @param $post
	 *
	 * @return mixed
	 */
	public function save_description( $post_id, $post ) {
		if ( empty( $_POST['hpm_podcast_class_nonce'] ) || !wp_verify_nonce( $_POST['hpm_podcast_class_nonce'], basename( __FILE__ ) ) ) :
			return $post_id;
		endif;
		global $wpdb;
		$pods = $this->options;

		$post_type = get_post_type_object( $post->post_type );

		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) :
			return $post_id;
		endif;

		$hpm_podcast = [
			'feed' => ( !empty( $_POST['hpm-podcast-ep-feed'] ) ? $_POST['hpm-podcast-ep-feed'] : '' ),
			'title' => ( !empty( $_POST['hpm-podcast-title'] ) ? sanitize_text_field( $_POST['hpm-podcast-title'] ) : '' ),
			'description' => balanceTags( $_POST['hpm-podcast-description'], true ),
			'episode' => ( isset( $_POST['hpm-podcast-episode'] ) ? sanitize_text_field( $_POST['hpm-podcast-episode'] ) :	'' ),
			'episodeType' => $_POST['hpm-podcast-episodetype'],
			'season' => ( isset( $_POST['hpm-podcast-season'] ) ? sanitize_text_field( $_POST['hpm-podcast-season'] ) : '' ),
		];

		update_post_meta( $post_id, 'hpm_podcast_ep_meta', $hpm_podcast );

		$sg_url = ( isset( $_POST['hpm-podcast-sg-file'] ) ? sanitize_text_field( $_POST['hpm-podcast-sg-file'] ) : '' );

		$hpm_enclose = get_post_meta( $post_id, 'hpm_podcast_enclosure', true );

		if ( !empty( $pods['upload-media'] ) ) :
			if ( !empty( $sg_url ) ) :
				if ( !empty( $hpm_enclose ) ) :
					if ( $hpm_enclose['url'] !== $sg_url ) :
						$hpm_enclose['url'] = $sg_url;
						update_post_meta( $post_id, 'hpm_podcast_enclosure', $hpm_enclose );
					endif;
				endif;
			endif;
		endif;
	}

	/**
	 * Create custom post type to house our podcast feeds
	 */
	public function create_type() {
		register_post_type( 'podcasts', [
			'labels' => [
				'name' => __( 'Podcasts' ),
				'singular_name' => __( 'Podcast' ),
				'menu_name' => __( 'Podcasts' ),
				'add_new_item' => __( 'Add New Podcast' ),
				'edit_item' => __( 'Edit Podcast' ),
				'new_item' => __( 'New Podcast' ),
				'view_item' => __( 'View Podcast' ),
				'search_items' => __( 'Search Podcasts' ),
				'not_found' => __( 'Podcast Not Found' ),
				'not_found_in_trash' => __( 'Podcast not found in trash' )
			],
			'description' => 'Feed information for locally-produced podcasts',
			'public' => true,
			'menu_position' => 20,
			'menu_icon' => 'dashicons-playlist-audio',
			'has_archive' => true,
			'rewrite' => [
				'slug' => __( 'podcasts' ),
				'with_front' => false,
				'feeds' => true,
				'pages' => true
			],
			'supports' => [ 'title', 'editor', 'thumbnail', 'author', 'excerpt' ],
			'taxonomies' => [ 'post_tag' ],
			'capability_type' => [ 'hpm_podcast', 'hpm_podcasts' ],
			'map_meta_cap' => true
		]);
	}

	/**
	 * Add capabilities to the selected roles (default is admin only)
	 */
	public function add_role_caps() {
		$pods = $this->options;
		foreach( $pods['roles'] as $the_role ) :
			$role = get_role($the_role);
			$role->add_cap( 'read' );
			$role->add_cap( 'read_hpm_podcast');
			$role->add_cap( 'read_private_hpm_podcasts' );
			$role->add_cap( 'edit_hpm_podcast' );
			$role->add_cap( 'edit_hpm_podcasts' );
			$role->add_cap( 'edit_others_hpm_podcasts' );
			$role->add_cap( 'edit_published_hpm_podcasts' );
			$role->add_cap( 'publish_hpm_podcasts' );
			$role->add_cap( 'delete_others_hpm_podcasts' );
			$role->add_cap( 'delete_private_hpm_podcasts' );
			$role->add_cap( 'delete_published_hpm_podcasts' );
		endforeach;
	}

	/**
	 * Add meta boxes to the post editor for the podcast feeds
	 */
	public function meta_setup() {
		add_action( 'add_meta_boxes', [ $this, 'add_meta' ] );
		add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
	}

	public function add_meta() {
		add_meta_box(
			'hpm-podcast-meta-class',
			esc_html__( 'Podcast Metadata', 'hpm-podcasts' ),
			[ $this, 'meta_box' ],
			'podcasts',
			'normal',
			'core'
		);
	}

	/**
	 * Set up metadata for this feed: iTunes categories, episode archive link, iTunes link, Google Play link, number of
	 * episodes in the feed, feed-specific analytics, etc.
	 *
	 * @param $object
	 * @param $box
	 *
	 * @return mixed
	 */
	public function meta_box( $object, $box ) {
		wp_nonce_field( basename( __FILE__ ), 'hpm_podcast_class_nonce' );
		$exists_cat  = metadata_exists( 'post', $object->ID, 'hpm_pod_cat' );
		$exists_link = metadata_exists( 'post', $object->ID, 'hpm_pod_link' );

		if ( $exists_cat ) :
			$hpm_podcast_cat = get_post_meta( $object->ID, 'hpm_pod_cat', true );
			if ( empty( $hpm_podcast_cat ) ) :
				$hpm_podcast_cat = '';
			endif;
		else :
			$hpm_podcast_cat = '';
		endif;
		if ( $exists_link ) :
			$hpm_podcast_link = get_post_meta( $object->ID, 'hpm_pod_link', true );
			if ( empty( $hpm_podcast_link ) ) :
				$hpm_podcast_link = [
					'page'       => '',
					'limit'      => 0,
					'itunes'     => '',
					'gplay'      => '',
					'stitcher'   => '',
					'analytics'  => '',
					'categories' => [ 'first' => '', 'second' => '', 'third' => '' ],
					'type'       => 'episodic'
				];
			endif;
		else :
			$hpm_podcast_link = [
				'page'       => '',
				'limit'      => 0,
				'itunes'     => '',
				'gplay'      => '',
				'stitcher'   => '',
				'analytics'  => '',
				'categories' => [ 'first' => '', 'second' => '', 'third' => '' ],
				'type'       => 'episodic'
			];
		endif;
		include __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'post-type.php';
	}

	/**
	 * Save the above metadata in postmeta
	 *
	 * @param $post_id
	 * @param $post
	 *
	 * @return mixed
	 */
	public function save_meta( $post_id, $post ) {
		if ( $post->post_type == 'podcasts' ) :
			if ( !isset( $_POST['hpm_podcast_class_nonce'] ) || !wp_verify_nonce( $_POST['hpm_podcast_class_nonce'], basename( __FILE__ ) ) )
				return $post_id;

			$post_type = get_post_type_object( $post->post_type );

			if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
				return $post_id;

			$hpm_podcast_cat = $_POST['hpm-podcast-cat'];
			$hpm_podcast_link = [
				'page' => ( isset( $_POST['hpm-podcast-link'] ) ? sanitize_text_field( $_POST['hpm-podcast-link'] ) : '' ),
				'itunes' => ( isset( $_POST['hpm-podcast-link-itunes'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-itunes'] ) : '' ),
				'gplay' => ( isset( $_POST['hpm-podcast-link-gplay'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-gplay'] ) : '' ),
				'stitcher' => ( isset( $_POST['hpm-podcast-link-stitcher'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-stitcher'] ) : '' ),
				'radiopublic' => ( isset( $_POST['hpm-podcast-link-radiopublic'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-radiopublic'] ) : '' ),
				'pcast' => ( isset( $_POST['hpm-podcast-link-pcast'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-pcast'] ) : '' ),
				'limit' => ( isset( $_POST['hpm-podcast-limit'] ) ? sanitize_text_field( $_POST['hpm-podcast-limit'] ) : 0 ),
				'categories' => [
					'first' => $_POST['hpm-podcast-icat-first'],
					'second' => $_POST['hpm-podcast-icat-second'],
					'third' => $_POST['hpm-podcast-icat-third']
				],
				'type' => $_POST['hpm-podcast-type']
			];

			update_post_meta( $post_id, 'hpm_pod_cat', $hpm_podcast_cat );
			update_post_meta( $post_id, 'hpm_pod_link', $hpm_podcast_link );
		endif;
	}

	/**
	 * Creates the Settings menu in the Admin Dashboard
	 */
	public function create_menu() {
		add_submenu_page( 'edit.php?post_type=podcasts', 'HPM Podcast Settings', 'Settings', 'manage_options', 'hpm-podcast-settings', [ $this, 'settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Registers the settings group for HPM Podcasts
	 */
	public function register_settings() {
		register_setting( 'hpm-podcast-settings-group', 'hpm_podcast_settings' );
	}

	/**
	 * Creates the Settings menu in the Admin Dashboard
	 */
	public function settings_page() {
		$pods = $this->options;
		$pods_last = $this->last_update;
		$upload_sftp = ' hidden';
		if ( !empty( $pods_last ) ) :
			$last_refresh = date( 'F j, Y @ g:i A', $pods_last );
		else :
			$last_refresh = 'Never';
		endif;
		include __DIR__ . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'admin.php';
	}

	/**
	 * Uploads
	 *
	 * @param WP_REST_Request $request This function accepts a rest request to process data.
	 *
	 * @return mixed
	 */
	public function upload( WP_REST_Request $request ) {
		if ( empty( $request['feed'] ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( 'Unable to upload media. Please choose a podcast feed.', 'hpm-podcasts' ), [ 'status' => 500 ] );
		elseif ( empty( $request['id'] ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No post ID provided, cannot upload media. Please save your post and try again.', 'hpm-podcasts' ), [ 'status' => 500 ] );
		endif;

		$this->process_upload->data( [ 'id' => $request['id'], 'feed' => $request['feed'], 'attach' => $request['attach'] ] )->dispatch();
		update_post_meta( $request['id'], 'hpm_podcast_status', [ 'status' => 'in-progress', 'message' => esc_html__( 'Upload process initializing.', 'hpm-podcasts' ) ] );
		
		return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( 'Podcast upload started successfully.', 'hpm-podcasts' ), 'data' => [ 'status' => 200 ] ] );
	}

	/**
	 * Upload progress reports
	 *
	 * @param WP_REST_Request $request This function accepts a rest request to process data.
	 *
	 * @return mixed
	 */
	public function upload_progress( WP_REST_Request $request ) {
		if ( empty( $request['id'] ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No post ID provided, cannot find upload status. Please save your post and try again.', 'hpm-podcasts' ), [ 'status' => 500 ] );
		endif;

		$status = get_post_meta( $request['id'], 'hpm_podcast_status', true );

		if ( empty( $status ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No upload status found, please try your upload again.', 'hpm-podcasts' ), [ 'status' => 500 ] );
		else :
			if ( $status['status'] == 'error' ) :
				return new WP_Error( 'rest_api_sad', esc_html__( $status['message'], 'hpm-podcasts' ), [ 'status' => 500 ] );
			elseif ( $status['status'] == 'in progress' ) :
				return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( $status['message'], 'hpm-podcasts' ), 'data' => [ 'current' => 'in-progress', 'status' => 200 ] ] );
			elseif ( $status['status'] == 'success' ) :
				delete_post_meta( $request['id'], 'hpm_podcast_status', '' );
				$data = get_post_meta( $request['id'], 'hpm_podcast_enclosure', true );
				return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( $status['message'], 'hpm-podcasts' ), 'data' => [ 'url' => $data['url'], 'current' => 'success', 'status' => 200 ] ] );
			endif;
		endif;
	}

	/**
	 * Pull a list of podcasts, generate the feeds, and save them as flat XML files, either locally, or in the FTP, SFTP
	 * or S3 bucket defined
	 *
	 * @return mixed
	 */
	public function generate( WP_REST_Request $request = null ) {
		$pods = $this->options;
		$ds = DIRECTORY_SEPARATOR;
		if ( !empty( $pods['https'] ) ) :
			$protocol = 'https://';
			$_SERVER['HTTPS'] = 'on';
		else :
			$protocol = 'http://';
		endif;
		$error = '';
		$dir = wp_upload_dir();
		$save = $dir['basedir'];
		if ( class_exists( 'feed_json' ) ) :
			$feed_json = true;
			$json = [
				'version' => 'https://jsonfeed.org/version/1',
				'title' => '',
				'home_page_url' => '',
				'feed_url' => '',
				'description' => '',
				'icon' => '',
				'favicon' => '',
				'categories' => [],
				'keywords' => [],
				'author' => [
					'name' => '',
					'email' => ''
				],
				'items' => []
			];
		else :
			$feed_json = false;
		endif;

		$podcasts = new WP_Query([	
			'post_type' => 'podcasts',
			'post_status' => 'publish',
			'posts_per_page' => -1
		]);
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
		global $post;
		if ( $podcasts->have_posts() ) :
			while ( $podcasts->have_posts() ) :
				$podcasts->the_post();
				$pod_id = get_the_ID();
				$catslug = get_post_meta( $pod_id, 'hpm_pod_cat', true );
				$podlink = get_post_meta( $pod_id, 'hpm_pod_link', true );
				$last_id = get_post_meta( $pod_id, 'hpm_pod_last_id', true );
				$current_post = $post;
				$podcast_title = $podcasts->post->post_name;
				$perpage = -1;
				if ( !empty( $podlink['limit'] ) && $podlink['limit'] != 0 && is_numeric( $podlink['limit'] ) ) :
					$perpage = $podlink['limit'];
				endif;
				$podeps = new WP_Query([
					'post_type' => 'post',
					'post_status' => 'publish',
					'cat' => $catslug,
					'posts_per_page' => $perpage,
					'meta_query' => [[
						'key' => 'hpm_podcast_enclosure',
						'compare' => 'EXISTS'
					]]
				]);
				if ( $podeps->have_posts() && $request === null ) :
					$first_id = $podeps->post->ID;
					$modified = get_the_modified_date('u', $first_id );
					if ( !empty( $last_id['id'] ) && $last_id['id'] == $first_id && $last_id['modified'] == $modified ) :
						continue;
					endif;
				endif;
				$main_image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
				$favicon = wp_get_attachment_image_src( get_post_thumbnail_id(), 'thumb' );
				$categories = [];
				foreach ( $podlink['categories'] as $pos => $cats ) :
					$categories[$pos] = explode( '||', $cats );
				endforeach;
				$pod_tags = wp_get_post_tags( $pod_id );
				$pod_tag_array = [];
				foreach ( $pod_tags as $t ) :
					$pod_tag_array[] = $t->name;
				endforeach;

				if ( $feed_json ) :
					$json['title'] = get_the_title();
					$json['home_page_url'] = $podlink['page'];
					$json['feed_url'] = get_the_permalink().'/feed/json';
					$json['description'] = get_the_content();
					$json['icon'] = $main_image[0];
					$json['favicon'] = $favicon[0];
					$json['author']['name'] = $pods['owner']['name'];
					$json['author']['email'] = $pods['owner']['email'];
					$json['keywords'] = $pod_tag_array;
					foreach ( $categories as $cats ) :
						foreach ( $cats as $ca ) :
							$json['categories'][] = $ca;
						endforeach;
					endforeach;
					$json['items'] = [];
				endif;

				ob_start();
				echo "<?xml version=\"1.0\" encoding=\"".get_option('blog_charset')."\"?>\n";
				do_action( 'rss_tag_pre', 'rss2' ); ?>
			<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom" <?php do_action( 'rss2_ns' ); ?>>
				<channel>
					<title><?php the_title_rss(); ?></title>
					<atom:link href="<?php echo get_the_permalink(); ?>" rel="self" type="application/rss+xml" />
					<link><?php echo $podlink['page']; ?></link>
					<description><![CDATA[<?php the_content_feed(); ?>]]></description>
					<language><?php bloginfo_rss( 'language' ); ?></language>
					<copyright>&#x2117; &amp; &#xA9; <?PHP echo date('Y'); ?> Houston Public Media</copyright>
					<ttl><?php echo $frequency; ?></ttl>
					<pubDate><?php echo date('r'); ?></pubDate>
					<itunes:summary><![CDATA[<?php the_content_feed(); ?>]]></itunes:summary>
					<itunes:owner>
						<itunes:name><![CDATA[<?php echo $pods['owner']['name']; ?>]]></itunes:name>
						<itunes:email><?php echo $pods['owner']['email']; ?></itunes:email>
					</itunes:owner>
					<itunes:keywords><![CDATA[<?php echo implode( ', ', $pod_tag_array ); ?>]]></itunes:keywords>
					<itunes:subtitle><?PHP echo get_the_excerpt();  ?></itunes:subtitle>
					<itunes:author><?php echo $pods['owner']['name']; ?></itunes:author>
					<itunes:explicit><?php
						if ( in_array( 'explicit', $pod_tag_array ) ) :
							echo "yes";
						else :
							echo "no";
						endif; ?></itunes:explicit>
					<itunes:type><?php echo $podlink['type']; ?></itunes:type>
					<?PHP
					foreach ( $categories as $podcat ) :
						if ( count( $podcat ) == 2 ) : ?>
							<itunes:category text="<?PHP echo htmlentities( $podcat[0] ); ?>">
								<itunes:category text="<?PHP echo htmlentities( $podcat[1] ); ?>" />
							</itunes:category>
							<?PHP
						else :
							if ( !empty( $podcat[0] ) ) : ?>
								<itunes:category text="<?PHP echo htmlentities( $podcat[0] ); ?>" />
								<?PHP
							endif;
						endif;
					endforeach;
					if ( !empty( $main_image ) ) : ?>
						<itunes:image href="<?PHP echo $main_image[0]; ?>" />
						<image>
							<url><?php echo $main_image[0]; ?></url>
							<title><?PHP the_title_rss(); ?></title>
						</image>
						<?php
					endif;
					do_action( 'rss2_head');
					if ( $podeps->have_posts() ) :
						while ( $podeps->have_posts() ) :
							$podeps->the_post();
							$epid = get_the_ID();
							if ( $podeps->current_post == 0 ) :
								$last = [ 'id' => $epid, 'modified' => get_the_modified_time( 'u' ) ];
								update_post_meta( $pod_id, 'hpm_pod_last_id', $last );
							endif;
							$a_meta = get_post_meta( $epid, 'hpm_podcast_enclosure', true );
							$pod_image = wp_get_attachment_image_src( get_post_thumbnail_id( $epid ), 'full' );
							$tags = wp_get_post_tags( $epid );
							$tag_array = [];
							foreach ( $tags as $t ) :
								$tag_array[] = $t->name;
							endforeach;
							$pod_desc = get_post_meta( $epid, 'hpm_podcast_ep_meta', true );
							
							$media_file = str_replace( [ 'http://', 'https://' ], [ $protocol, $protocol ], $a_meta['url'] );
							
							if ( !empty( $pod_desc['title'] ) ) :
								$item_title = $pod_desc['title'];
							else :
								$item_title = get_the_title();
							endif;

							$content = "<p>".wp_trim_words( strip_shortcodes( get_the_content() ), 75, '... <a href="'.get_the_permalink().'">Read More</a>' )."</p>";
							if ( $feed_json ) :
								$json['items'][] = [
									'id' => $epid,
									'title' => $item_title,
									'permalink' => get_permalink(),
									'content_html' => apply_filters( 'hpm_filter_text', get_the_content() ),
									'content_text' => strip_shortcodes( wp_strip_all_tags( get_the_content() ) ),
									'excerpt' => get_the_excerpt(),
									'date_published' => get_the_date( 'c', '', '', false),
									'date_modified' => get_the_modified_date( 'c', '', '', false),
									'author' => coauthors( '; ', '; ', '', '', false ),
									'thumbnail' => $pod_image,
									'attachments' => [
										'url' => $media_file,
										'mime_type' => $a_meta['mime'],
										'filesize' => $a_meta['filesize'],
										'duration_in_seconds' => $a_meta['length']
									],
									'season' => $pod_desc['season'],
									'episode' => $pod_desc['episode'],
									'episodeType' => $pod_desc['episodeType']
								];
							endif; ?>
							<item>
								<title><?php echo $item_title; ?></title>
								<link><?php the_permalink(); ?></link>
								<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true, $epid ), false); ?></pubDate>
								<guid isPermaLink="true"><?php the_permalink(); ?></guid>
								<description><![CDATA[<?php echo ( !empty( $pod_desc['description'] ) ? $pod_desc['description'] : $content ); ?>]]></description>
								<author><?php
									if ( function_exists( 'coauthors' ) ) :
										coauthors(', ', ', ', '', '', true);
									else :
										echo get_the_author();
									endif; ?></author>
								<itunes:author><?php
									if ( function_exists( 'coauthors' ) ) :
										coauthors(', ', ', ', '', '', true);
									else :
										echo get_the_author();
									endif; ?></itunes:author>
								<itunes:keywords><![CDATA[<?php echo implode( ',', $tag_array ); ?>]]></itunes:keywords>
								<itunes:summary><![CDATA[<?php echo ( !empty( $pod_desc['description'] ) ? $pod_desc['description'] : $content ); ?>]]></itunes:summary>
<?php
								if ( !empty( $pod_image ) ) : ?>
									<itunes:image href="<?PHP echo $pod_image[0]; ?>"/>
									<?php
								endif; ?>
								<itunes:explicit><?php
									if ( in_array( 'explicit', $tag_array ) ) :
										echo "yes";
									else :
										echo "no";
									endif; ?></itunes:explicit>
								<enclosure url="<?PHP echo $media_file; ?>" length="<?PHP echo $a_meta['filesize']; ?>" type="<?php echo $a_meta['mime']; ?>" />
								<itunes:duration><?PHP echo $a_meta['length']; ?></itunes:duration>
<?php
									if ( !empty( $pod_desc['episode'] ) ) : ?>
								<itunes:episode><?php echo $pod_desc['episode']; ?></itunes:episode>
<?php
									endif;
									if ( !empty( $pod_desc['episodeType'] ) ) : ?>
								<itunes:episodeType><?php echo $pod_desc['episodeType']; ?></itunes:episodeType>
<?php
									endif;
									if ( !empty( $pod_desc['season'] ) ) : ?>
								<itunes:season><?php echo $pod_desc['season']; ?></itunes:season>
<?php
									endif;
									do_action( 'rss2_item' ); ?>
							</item>
							<?php
						endwhile;
					endif;
					wp_reset_query();
					$post = $current_post; ?>
				</channel>
				</rss><?php
				$getContent = ob_get_contents();
				ob_end_clean();
				update_option( 'hpm_podcast-'.$podcast_title, $getContent, false );
				if ( $feed_json ) :
					update_option( 'hpm_podcast-json-'.$podcast_title, json_encode( $json ), false );
				endif;
				sleep(5);
			endwhile;
			if ( !empty( $error ) ) :
				return new WP_Error( 'rest_api_sad', esc_html__( $error, 'hpm-podcasts' ), [ 'status' => 500 ] );
			else :
				$t = time();
				$update_last = $this->last_update;
				$offset = get_option('gmt_offset')*3600;
				$time = $t + $offset;
				$date = date( 'F j, Y @ g:i A', $time );
				if ( $update_last == 'none' ) :
					add_option( 'hpm_podcast_last_update', $time, false );
				else :
					update_option( 'hpm_podcast_last_update', $time, false );
				endif;
				return rest_ensure_response( [ 'code' => 'rest_api_success', 'message' => esc_html__( 'Podcast feeds successfully updated!', 'hpm-podcasts' ), 'data' => [ 'date' => $date, 'timestamp' => $time, 'status' =>
					200 ] ] );
			endif;
		else :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No podcast feeds have been defined. Please create one and try again.', 'hpm-podcasts' ), [ 'status' => 500 ] );
		endif;
	}

	/*
	 * Display podcasts and shows alphabetically instead of by creation date
	 */
	public function meta_query( $query ) {
		if ( $query->is_archive() && $query->is_main_query() ) :
			$pod_check = $query->get( 'post_type' );
			if ( $pod_check == 'podcasts' ) :
				$query->set( 'orderby', 'post_title' );
				$query->set( 'order', 'ASC' );
			endif;
		endif;
	}
}

new HPM_Podcasts();