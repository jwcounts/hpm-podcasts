<?php
/**
 * Plugin Name: HPM Podcasts
 * Plugin URI: http://www.houstonpublicmedia.org
 * Description: A plugin that allows you to create a podcast feed from any category, either video or audio. It also has the option to periodically cache the feeds as flat XML in Amazon S3 or on a separate FTP to speed up delivery, and to store the media files themselves there as well.
 * Version: 20170608
 * Author: Jared Counts
 * Author URI: http://www.houstonpublicmedia.org/staff/jared-counts/
 * License: GPL2
 * Text Domain: hpm-podcasts
 */
define( 'HPM_PODCAST_PLUGIN_DIR', plugin_dir_path(__FILE__) );

register_activation_hook( HPM_PODCAST_PLUGIN_DIR . 'main.php', 'hpm_podcast_activation' );
register_deactivation_hook( HPM_PODCAST_PLUGIN_DIR . 'main.php', 'hpm_podcast_deactivation' );

require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/post-type.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/post-editor.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/admin.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/templates.php' );
require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/rest.php' );

function hpm_podcast_activation() {
	$pods = get_option( 'hpm_podcast_settings' );
	$pods_last = get_option( 'hpm_podcast_last_update' );
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
		add_option( 'hpm_podcast_settings', $pods );
	endif;
	if ( empty( $pods_last ) ) :
		add_option( 'hpm_podcast_last_update', '' );
	endif;
	create_hpm_podcasts();
	flush_rewrite_rules();
}

function hpm_podcast_deactivation() {
	wp_clear_scheduled_hook( 'hpm_podcast_update' );
	$pods =  get_option( 'hpm_podcast_settings' );
	if ( !empty( $pods ) ) :
		delete_option( 'hpm_podcast_settings' );
	endif;
	$pods_last =  get_option( 'hpm_podcast_settings' );
	if ( !empty( $pods_last ) ) :
		delete_option( 'hpm_podcast_last_update' );
	endif;
	flush_rewrite_rules();
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
	add_filter( 'pre_update_option_hpm_podcast_settings', 'hpm_podcast_option_strip', 10, 2 );

	function hpm_podcast_option_strip( $new_value, $old_value ) {
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
		return $new_value;
	}
});

add_action( 'hpm_podcast_update', 'hpm_podcast_rest_generate' );
add_action( 'update_option_hpm_podcast_settings', function( $old_value, $value ) {
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