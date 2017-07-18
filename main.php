<?php
/**
 * @link 			https://github.com/jwcounts/hpm-podcasts
 * @since  			20170714
 * @package  		HPM-Podcasts
 *
 * @wordpress-plugin
 * Plugin Name: 	HPM Podcasts
 * Plugin URI: 		https://github.com/jwcounts/hpm-podcasts
 * Description: 	A plugin that allows you to create a podcast feed from any category, as well as 
 * Version: 		20170714
 * Author: 			Jared Counts
 * Author URI: 		http://www.houstonpublicmedia.org/staff/jared-counts/
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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'create_type' ) );
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
	}
	/**
	* Init
	*/
	public function init() {
		$this->options = get_option( 'hpm_podcast_settings' );
		$this->last_update = get_option( 'hpm_podcast_last_update' );

		require_once HPM_PODCAST_PLUGIN_DIR . 'classes' . DIRECTORY_SEPARATOR . 'class-background-process.php';

		$this->process_upload = new HPM_Media_Upload();

		add_filter( 'cron_schedules', array( $this, 'cron' ), 10, 2 );
		add_action( 'hpm_podcast_update_refresh', array( $this, 'generate' ) );
		add_filter( 'pre_update_option_hpm_podcast_settings', array( $this, 'options_clean' ), 10, 2 );

		// Setup meta box in article editor
		add_action( 'load-post.php', array( $this, 'description_setup' ) );
		add_action( 'load-post-new.php', array( $this, 'description_setup' ) );

		// Add edit capabilities to selected roles
		add_action( 'admin_init', array( $this, 'add_role_caps' ), 999 );

		// Setup metadata for podcast feeds
		add_action( 'load-post.php', array( $this, 'meta_setup' ) );
		add_action( 'load-post-new.php', array( $this, 'meta_setup' ) );

		// Register page templates
		add_filter( 'archive_template', array( $this, 'archive_template' ) );
		add_filter( 'single_template', array( $this, 'single_template' ) );

		// Create menu in Admin Dashboard
		add_action('admin_menu', array( $this, 'create_menu' ) );

		// Register WP-REST API endpoints
		add_action( 'rest_api_init', function(){
			register_rest_route( 'hpm-podcast/v1', '/refresh', array(
				'methods'  => 'GET',
				'callback' => array( $this, 'generate' )
			) );

			register_rest_route( 'hpm-podcast/v1', '/upload/(?P<feed>[a-zA-Z0-9\-_]+)/(?P<id>[\d]+)', array(
				'methods'  => 'GET',
				'callback' => array( $this, 'upload'),
				'args' => array(
					'id' => array(
						'required' => true
					),
					'feed' => array(
						'required' => true
					)
				)
			) );

			register_rest_route( 'hpm-podcast/v1', '/upload/(?P<feed>[a-zA-Z0-9\-_]+)/(?P<id>[\d]+)/progress', array(
				'methods'  => 'GET',
				'callback' => array( $this, 'upload_progress'),
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
		$schedules['hpm_5min'] = array(
			'interval' => 300,
			'display' => __( 'Every 5 Minutes' )
		);
		$schedules['hpm_15min'] = array(
			'interval' => 900,
			'display' => __( 'Every 15 Minutes' )
		);
		$schedules['hpm_30min'] = array(
			'interval' => 1800,
			'display' => __( 'Every 30 Minutes' )
		);
		return $schedules;
	}

	public function options_clean( $new_value, $old_value ) {
		$find = array( '{/$}', '{^/}' );
		$replace = array( '', '' );
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
		$pods = array(
			'owner' => array(
				'name' => '',
				'email' => ''
			),
			'recurrence' => 'hourly',
			'roles' => array('editor','administrator'),
			'upload-flats' => '',
			'upload-media' => '',
			'credentials' => array(
				'ftp' => array(
					'host' => '',
					'url' => '',
					'username' => '',
					'password' => '',
					'folder' => ''
				),
				'sftp' => array(
					'host' => '',
					'url' => '',
					'username' => '',
					'password' => '',
					'folder' => ''
				),
				's3' => array(
					'folder' => '',
					'bucket' => '',
					'region' => '',
					'key' => '',
					'secret' => ''
				)
			),
			'https' => ''
		);
		update_option( 'hpm_podcast_settings', $pods );
		update_option( 'hpm_podcast_last_update', 'none' );
		$this->options = $pods;
		$this->last_update = 'none';
		HPM_Podcasts::create_type();
		flush_rewrite_rules();
		if ( ! wp_next_scheduled( 'hpm_podcast_update_refresh' ) ) :
			wp_schedule_event( time(), 'hourly', 'hpm_podcast_update_refresh' );
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
			if ( file_exists( get_stylesheet_directory() . '/podcasts/single.php' ) ) :
				return get_stylesheet_directory() . '/podcasts/single.php';
			else :
				return HPM_PODCAST_PLUGIN_DIR . 'templates/single.php';
			endif;
		endif;
		return $single;
	}

	public function archive_template( $archive_template ) {
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

	/**
	 * Creating and setting up the metadata boxes in the post editor
	 */

	public function description_setup() {
		add_action( 'add_meta_boxes', array( $this, 'add_description' ) );
		add_action( 'save_post', array( $this, 'save_description' ), 10, 2 );
	}

	public function add_description() {
		add_meta_box(
			'hpm-podcast-meta-class',
			esc_html__( 'Podcast Feed Information', 'hpm-podcasts' ),
			array( $this, 'description_box' ),
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
			$hpm_pod_desc = array( 'feed' => '', 'description' => '' );
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

		$hpm_podcast = array(
			'feed' => ( !empty( $_POST['hpm-podcast-ep-feed'] ) ? $_POST['hpm-podcast-ep-feed'] : '' ),
			'description' => balanceTags( $_POST['hpm-podcast-description'], true )
		);

		if ( !empty( $hpm_podcast['feed'] ) || !empty( $hpm_podcast['description'] ) ) :
			update_post_meta( $post_id, 'hpm_podcast_ep_meta', $hpm_podcast );
		endif;

		$sg_url = ( isset( $_POST['hpm-podcast-sg-file'] ) ? sanitize_text_field( $_POST['hpm-podcast-sg-file'] ) : '' );

		$enclose = get_post_meta( $post_id, 'enclosure', true );
		$hpm_enclose = get_post_meta( $post_id, 'hpm_podcast_enclosure', true );

		if ( !empty( $pods['upload-media'] ) ) :
			if ( !empty( $sg_url ) ) :
				if ( !empty( $hpm_enclose ) ) :
					if ( $hpm_enclose['url'] !== $sg_url ) :
						$hpm_enclose['url'] = $sg_url;
						update_post_meta( $post_id, 'hpm_podcast_enclosure', $hpm_enclose );
					endif;
				elseif ( !empty( $enclose ) ) :
					$enc_exp = explode( "\n", $enclose );
					foreach ( $enc_exp as $k => $v ) :
						$enc_exp[$k] = trim( $v );
					endforeach;
					if ( $enc_exp[0] !== $sg_url ) :
						$url = parse_url( $enc_exp[0] );
						$path = pathinfo( $url['path'] );
						$base = $path['basename'];
						$attach = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE guid LIKE '%$base' AND post_type = 'attachment' LIMIT 1",
							OBJECT );
						if ( !empty( $attach ) ) :
							$meta = get_post_meta( $attach[0]->ID, '_wp_attachment_metadata', true );
							$new_enclose = array(
								'url' => $sg_url,
								'filesize' => $enc_exp[1],
								'mime' => $enc_exp[2],
								'length' => $meta['length_formatted']
							);
							update_post_meta( $post_id, 'hpm_podcast_enclosure', $new_enclose );
						endif;
					endif;
				endif;
			else :
				$media = get_attached_media( 'audio', $post_id );
				if ( !empty( $media ) ) :
					$med = reset( $media );
					$url = wp_get_attachment_url( $med->ID );
					$meta = get_post_meta( $med->ID, '_wp_attachment_metadata', true );
					$new_enclose = array(
						'url' => $url,
						'filesize' => $meta['filesize'],
						'mime' => $meta['mime_type'],
						'length' => $meta['length_formatted']
					);
					update_post_meta( $post_id, 'hpm_podcast_enclosure', $new_enclose );
				endif;
			endif;
		else :
			if ( !empty( $enclose ) ) :
				$enc_exp = explode( "\n", $enclose );
				foreach ( $enc_exp as $k => $v ) :
					$enc_exp[$k] = trim( $v );
				endforeach;
				$url = parse_url( $enc_exp[0] );
				$path = pathinfo( $url['path'] );
				$base = $path['basename'];
				$attach = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE guid LIKE '%$base' AND post_type = 'attachment' LIMIT 1",
					OBJECT );
				if ( !empty( $attach ) ) :
					$meta = get_post_meta( $attach[0]->ID, '_wp_attachment_metadata', true );
					$new_enclose = array(
						'url' => $enc_exp[0],
						'filesize' => $enc_exp[1],
						'mime' => $enc_exp[2],
						'length' => $meta['length_formatted']
					);
					update_post_meta( $post_id, 'hpm_podcast_enclosure', $new_enclose );
				endif;
			else :
				$media = get_attached_media( 'audio', $post_id );
				if ( !empty( $media ) ) :
					$med = reset( $media );
					$url = wp_get_attachment_url( $med->ID );
					$meta = get_post_meta( $med->ID, '_wp_attachment_metadata', true );
					$new_enclose = array(
						'url' => $url,
						'filesize' => $meta['filesize'],
						'mime' => $meta['mime_type'],
						'length' => $meta['length_formatted']
					);
					update_post_meta( $post_id, 'hpm_podcast_enclosure', $new_enclose );
				endif;
			endif;
		endif;
	}

	/**
	 * Create custom post type to house our podcast feeds
	 */
	public function create_type() {
		register_post_type( 'podcasts',
			array(
				'labels' => array(
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
				),
				'description' => 'Feed information for locally-produced podcasts',
				'public' => true,
				'menu_position' => 20,
				'menu_icon' => 'dashicons-playlist-audio',
				'has_archive' => true,
				'rewrite' => array(
					'slug' => __( 'podcasts' ),
					'with_front' => false,
					'feeds' => false,
					'pages' => true
				),
				'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'excerpt' ),
				'taxonomies' => array( 'post_tag' ),
				'capability_type' => array( 'hpm_podcast', 'hpm_podcasts' ),
				'map_meta_cap' => true
			)
		);
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
	}

	public function add_meta() {
		add_meta_box(
			'hpm-podcast-meta-class',
			esc_html__( 'Podcast Metadata', 'hpm-podcasts' ),
			array( $this, 'meta_box' ),
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
				$hpm_podcast_link = array(
					'page'       => '',
					'limit'      => 0,
					'itunes'     => '',
					'gplay'      => '',
					'stitcher'   => '',
					'analytics'  => '',
					'categories' => array( 'first' => '', 'second' => '', 'third' => '' )
				);
			else :
				if ( empty( $hpm_podcast_link['categories'] ) ) :
					$hpm_podcast_link['categories'] = array(
						'first'  => $hpm_podcast_link['cat-prime'],
						'second' => $hpm_podcast_link['cat-second'],
						'third'  => $hpm_podcast_link['cat-third']
					);
					unset( $hpm_podcast_link['cat-prime'] );
					unset( $hpm_podcast_link['cat-second'] );
					unset( $hpm_podcast_link['cat-third'] );
				endif;
			endif;
		else :
			$hpm_podcast_link = array(
				'page'       => '',
				'limit'      => 0,
				'itunes'     => '',
				'gplay'      => '',
				'stitcher'   => '',
				'analytics'  => '',
				'categories' => array( 'first' => '', 'second' => '', 'third' => '' )
			);
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
			$hpm_podcast_link = array(
				'page' => ( isset( $_POST['hpm-podcast-link'] ) ? sanitize_text_field( $_POST['hpm-podcast-link'] ) : '' ),
				'itunes' => ( isset( $_POST['hpm-podcast-link-itunes'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-itunes'] ) : '' ),
				'gplay' => ( isset( $_POST['hpm-podcast-link-gplay'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-gplay'] ) : '' ),
				'stitcher' => ( isset( $_POST['hpm-podcast-link-stitcher'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-stitcher'] ) : '' ),
				'analytics' => ( isset( $_POST['hpm-podcast-analytics'] ) ? sanitize_text_field( $_POST['hpm-podcast-analytics'] ) : '' ),
				'limit' => ( isset( $_POST['hpm-podcast-limit'] ) ? sanitize_text_field( $_POST['hpm-podcast-limit'] ) : 0 ),
				'categories' => array(
					'first' => $_POST['hpm-podcast-icat-first'],
					'second' => $_POST['hpm-podcast-icat-second'],
					'third' => $_POST['hpm-podcast-icat-third']
				)
			);

			update_post_meta( $post_id, 'hpm_pod_cat', $hpm_podcast_cat );
			update_post_meta( $post_id, 'hpm_pod_link', $hpm_podcast_link );
		endif;
	}

	/**
	 * Creates the Settings menu in the Admin Dashboard
	 */
	public function create_menu() {
		add_submenu_page( 'edit.php?post_type=podcasts', 'HPM Podcast Settings', 'Settings', 'manage_options', 'hpm-podcast-settings', array( $this, 'settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
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
		$upload_s3 = $upload_ftp = $upload_sftp = ' hidden';
		if ( !empty( $pods['upload-flats'] ) ) :
			$uflats = $pods['upload-flats'];
			${"upload_$uflats"} = '';
		endif;
		if ( !empty( $pods['upload-media'] ) ) :
			$umedia = $pods['upload-media'];
			${"upload_$umedia"} = '';
		endif;
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
			return new WP_Error( 'rest_api_sad', esc_html__( 'Unable to upload media. Please choose a podcast feed.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		elseif ( empty( $request['id'] ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No post ID provided, cannot upload media. Please save your post and try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		endif;

		$this->process_upload->data( array( 'id' => $request['id'], 'feed' => $request['feed'] ) )->dispatch();

		return rest_ensure_response( array( 'code' => 'rest_api_success', 'message' => esc_html__( 'Podcast upload started successfully.', 'hpm-podcasts' ), 'data' => array( 'status' => 200 ) ) );
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
			return new WP_Error( 'rest_api_sad', esc_html__( 'No post ID provided, cannot find upload status. Please save your post and try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		endif;

		$status = get_post_meta( $request['id'], 'hpm_podcast_status', true );

		if ( empty( $status ) ) :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No upload status found, please try your upload again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		else :
			if ( $status['status'] == 'error' ) :
				return new WP_Error( 'rest_api_sad', esc_html__( $status['message'], 'hpm-podcasts' ), array( 'status' => 500
				) );
			elseif ( $status['status'] == 'in progress' ) :
				return rest_ensure_response( array( 'code' => 'rest_api_success', 'message' => esc_html__( $status['message'], 'hpm-podcasts' ), 'data' => array( 'current' => 'in-progress', 'status' => 200 ) ) );
			elseif ( $status['status'] == 'success' ) :
				delete_post_meta( $request['id'], 'hpm_podcast_status', '' );
				$data = get_post_meta( $request['id'], 'hpm_podcast_enclosure', true );
				return rest_ensure_response( array( 'code' => 'rest_api_success', 'message' => esc_html__( $status['message'], 'hpm-podcasts' ), 'data' => array( 'url' => $data['url'], 'current' => 'success', 'status' => 200 ) ) );
			endif;
		endif;
	}

	/**
	 * Pull a list of podcasts, generate the feeds, and save them as flat XML files, either locally, or in the FTP, SFTP
	 * or S3 bucket defined
	 *
	 * @return mixed
	 */
	public function generate() {
		$pods = $this->options;
		$ds = DIRECTORY_SEPARATOR;
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

				if ( class_exists( 'Amazon_Web_Services' ) ) :
					$client = Aws\S3\S3Client::factory(array(
						'key' => $aws_key,
						'secret' => $aws_secret
					));
				else :
					require __DIR__ . $ds . 'vendor' . $ds . 'aws' . $ds . 'aws-autoloader.php';
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
				$ipath = __DIR__ . $ds . 'vendor' . $ds . 'phpseclib';
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
			if ( !file_exists( $save . $ds . 'hpm-podcasts' ) ) :
				mkdir( $save . $ds . 'hpm-podcasts' );
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
		global $post;
		if ( $podcasts->have_posts() ) :
			while ( $podcasts->have_posts() ) :
				$podcasts->the_post();
				$pod_id = get_the_ID();
				$catslug = get_post_meta( $pod_id, 'hpm_pod_cat', true );
				$podlink = get_post_meta( $pod_id, 'hpm_pod_link', true );
				$current_post = $post;
				ob_start();
				echo "<?xml version=\"1.0\" encoding=\"".get_option('blog_charset')."\"?>\n";
				do_action( 'rss_tag_pre', 'rss2' ); ?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom" <?php do_action( 'rss2_ns' ); ?>>
<?php
				$main_image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
				$categories = array();
				foreach ( $podlink['categories'] as $pos => $cats ) :
					$categories[$pos] = explode( '||', $cats );
				endforeach;
				$podcast_title = $podcasts->post->post_name;
				$pod_tags = wp_get_post_tags( $pod_id );
				$pod_tag_array = array();
				foreach ( $pod_tags as $t ) :
					$pod_tag_array[] = $t->name;
				endforeach;?>
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
		<itunes:keywords><![CDATA[<?php echo implode( ', ', $pod_tag_array ); ?>]]></itunes:keywords>
		<itunes:subtitle><?PHP echo get_the_excerpt();  ?></itunes:subtitle>
		<itunes:author><?php
				if ( function_exists( 'coauthors' ) ) :
					coauthors(', ', ', ', '', '', true);
				else :
					echo get_the_author();
				endif; ?></itunes:author>
		<itunes:explicit><?php
				if ( in_array( 'explicit', $pod_tag_array ) ) :
					echo "yes";
				else :
					echo "no";
				endif; ?></itunes:explicit>
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
			$perpage = -1;
			if ( !empty( $podlink['limit'] ) && $podlink['limit'] != 0 && is_numeric( $podlink['limit'] ) ) :
				$perpage = $podlink['limit'];
			endif;
			$podeps = new WP_Query(
				array(
					'post_type' => 'post',
					'post_status' => 'publish',
					'cat' => $catslug,
					'posts_per_page' => $perpage,
					'meta_query' => array(
						array(
							'key' => 'hpm_podcast_enclosure',
							'compare' => 'EXISTS'
						)
					)
				)
			);
			if ( $podeps->have_posts() ) :
				while ( $podeps->have_posts() ) :
					$podeps->the_post();
					$epid = get_the_ID();
					$a_meta = get_post_meta( $epid, 'hpm_podcast_enclosure', true );
					$pod_image = wp_get_attachment_image_src( get_post_thumbnail_id( $epid ), 'full' );
					$tags = wp_get_post_tags( $epid );
					$tag_array = array();
					foreach ( $tags as $t ) :
						$tag_array[] = $t->name;
					endforeach;
					$pod_desc = get_post_meta( $epid, 'hpm_podcast_ep_meta', true );
					if ( !empty( $podlink['blubrry'] ) ) :
						$media_url = str_replace( array( 'http://', 'https://' ), array( '', '' ), $a_meta['url'] );
						$media_file = $protocol."media.blubrry.com/".$podlink['blubrry']."/".$media_url;
					else :
						$media_file = str_replace( array( 'http://', 'https://' ), array( $protocol, $protocol ), $a_meta['url'] );
					endif;
					$content = "<p>".wp_trim_words( strip_shortcodes( get_the_content() ), 75, '... <a href="'.get_the_permalink().'">Read More</a>' )."</p>"; ?>
		<item>
			<title><?php the_title_rss(); ?></title>
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
<?php do_action( 'rss2_item' ); ?>
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
				if ( !empty( $pods['upload-flats'] ) ) :
					if ( $pods['upload-flats'] == 's3' ) :
						try {
							$result = $client->putObject(array(
								'Bucket' => $short['bucket'],
								'Key' => ( !empty( $short['folder'] ) ? $short['folder'].'/' : '' ) .$podcast_title.'.xml',
								'Body' => $getContent,
								'ACL' => 'public-read',
								'ContentType' => 'application/rss+xml'
							));
						} catch ( S3Exception $e ) {
							$error .= $podcast_title.": ".$e->getMessage()."<br /><br />";
						} catch ( AwsException $e ) {
							$error .= $podcast_title . ": " . $e->getAwsRequestId() . "<br />" . $e->getAwsErrorType() . "<br />" . $e->getAwsErrorCode() . "<br /><br />";
						}
					elseif ( $pods['upload-flats'] == 'ftp' ) :
						$local = $save . $ds . $podcast_title . '.xml';
						if ( !file_put_contents( $local, $getContent ) ) :
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
						$local = $save . $ds . $podcast_title . '.xml';
						if ( !file_put_contents( $local, $getContent ) ) :
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
						$option = get_option( 'hpm_podcast-'.$podcast_title );
						if ( empty( $option ) ) :
							add_option( 'hpm_podcast-'.$podcast_title, $getContent );
						else :
							update_option( 'hpm_podcast-'.$podcast_title, $getContent );
						endif;
					else :
						$error .= "No flat file upload target defined. Please check your settings and try again.";
					endif;
				else :
					$file_write = file_put_contents( $save.$ds.'hpm-podcasts'.$ds.$podcast_title.'.xml', $getContent );
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
				$update_last = $this->last_update;
				$offset = get_option('gmt_offset')*3600;
				$time = $t + $offset;
				$date = date( 'F j, Y @ g:i A', $time );
				if ( $update_last == 'none' ) :
					add_option( 'hpm_podcast_last_update', $time );
				else :
					update_option( 'hpm_podcast_last_update', $time );
				endif;
				return rest_ensure_response( array( 'code' => 'rest_api_success', 'message' => esc_html__('Podcast feeds successfully updated!', 'hpm-podcasts' ), 'data' => array( 'date' => $date, 'timestamp' => $time, 'status' =>
					200 ) ) );
			endif;
		else :
			return new WP_Error( 'rest_api_sad', esc_html__( 'No podcast feeds have been defined. Please create one and try again.', 'hpm-podcasts' ), array( 'status' => 500 ) );
		endif;
	}
}

new HPM_Podcasts();