<?php
/**
 * Plugin Name: HPM Podcasts
 * Plugin URI: http://www.houstonpublicmedia.org
 * Description: A plugin that allows you to create a podcast feed from any category, either video or audio. It also has the option to periodically cache the feeds as flat XML in Amazon S3 or on a separate FTP to speed up delivery, and to store the media files themselves there as well.
 * Version: 1.0
 * Author: Jared Counts
 * Author URI: http://www.houstonpublicmedia.org/staff/jared-counts/
 * License: GPL2
 */
define( 'HPM_PODCAST_PLUGIN_DIR', plugin_dir_path(__FILE__) );

register_activation_hook( HPM_PODCAST_PLUGIN_DIR . 'main.php', 'hpm_podcast_activation' );
register_deactivation_hook( HPM_PODCAST_PLUGIN_DIR . 'main.php', 'hpm_podcast_deactivation' );

function hpm_podcast_activation() {
	$pods = get_option( 'hpm_podcasts' );
	if ( empty( $pods ) ) :
		$pods = array(
			'owner' => array(
				'name' => '',
				'email' => ''
			),
			'recurrence' => '',
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
			'https' => '',
			'last_updated' => ''
		);
		add_option( 'hpm_podcasts', $pods );
	endif;
}

function hpm_podcast_deactivation() {
	wp_clear_scheduled_hook( 'hpm_podcast_update' );
	$pods =  get_option( 'hpm_podcasts' );
	if ( !empty( $pods ) ) :
		delete_option( 'hpm_podcasts' );
	endif;
}

add_filter( 'cron_schedules', 'hpm_cron_sched' );
function hpm_cron_sched( $schedules ) {
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

/**
 * Register WP_AJAX functions for uploads and feed refresh
 *
 */
add_action('init', function () {

	add_action( 'wp_ajax_hpm_podcasts_upload', 'hpm_podcast_upload_handler' );
	add_action( 'wp_ajax_hpm_podcasts_refresh', 'hpm_podcast_refresh_handler' );
	add_filter( 'pre_update_option_hpm_podcasts', 'hpm_podcast_option_strip', 10, 2 );

	function hpm_podcast_upload_handler() {
		if ( empty ( $_POST['action'] ) || $_POST['action'] !== 'hpm_podcasts_upload' ) :
			if ( !empty ( $fail_message ) ) :
				wp_send_json_error( array(
					'message' => "Sorry, you don't have permission to do that."
				) );
			endif;
		endif;

		$id = $_POST['id'];
		$feed = $_POST['feed'];

		$output = hpm_podcast_media_upload( $id, $feed );
		if ( $output['state'] == 'success' ) :
			wp_send_json_success(array(
				'action' => $_POST['action'],
				'message' => $output['message'],
				'feed' => $feed,
				'ID' => $id,
				'URL' => $output['url']
			));
		elseif ( $output['state'] == 'error' ) :
			wp_send_json_error(array(
				'action' => $_POST['action'],
				'message' => $output['message'],
				'feed' => $feed,
				'ID' => $id
			));
		endif;
	}

	function hpm_podcast_refresh_handler() {
		if ( empty ( $_POST['action'] ) || $_POST['action'] !== 'hpm_podcasts_refresh' ) :
			if ( !empty ( $fail_message ) ) :
				wp_send_json_error( array(
					'message' => "Sorry, you don't have permission to do that."
				) );
			endif;
		endif;

		$output = hpm_podcast_generate();

		if ( $output['state'] == 'success' ) :
			wp_send_json_success(array(
				'action' => $_POST['action'],
				'message' => $output['message'],
				'date' => $output['date'],
				'timestamp' => $output['timestamp']
			));
		elseif ( $output['state'] == 'error' ) :
			wp_send_json_error(array(
				'action' => $_POST['action'],
				'message' => $output['message']
			));
		endif;

	}

	function hpm_podcast_option_strip( $new_value, $old_value ) {
		$find = array( '{/$}', '{^/}' );
		$replace = array( '', '' );
		foreach ( $new_value['credentials'] as $credk => $credv ) :
			foreach ( $credv as $k => $v ) :
				if ( !empty( $v ) && ( $k != 'key' || $k != 'secret' || $k != 'password' ) ) :
					$new_value['credentials'][$credk][$k] = preg_replace( $find, $replace, $v );
				endif;
			endforeach;
		endforeach;
		return $new_value;
	}
});

add_action( 'hpm_podcast_update', 'hpm_podcast_generate' );
add_action('update_option_hpm_podcasts', function( $old_value, $value ) {
	if ( !empty( $value['recurrence'] ) ) :
		$timestamp = wp_next_scheduled( 'hpm_podcast_update' );
		if ( empty( $timestamp ) ) :
			wp_schedule_event( time(), $value['recurrence'], 'hpm_podcast_update' );
		else :
			wp_clear_scheduled_hook( 'hpm_podcast_update' );
			wp_schedule_event( time(), $value['recurrence'], 'hpm_podcast_update' );
		endif;
	endif;
}, 10, 2);

require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/post-type.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/post-editor.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/admin.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/upload-cache.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/templates.php' );