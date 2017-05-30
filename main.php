<?php
/*
	Plugin Name: HPM Podcasts
	Plugin URI: http://www.houstonpublicmedia.org
	Description: A plugin that allows you to create a podcast feed from any category, either video or audio. It also has the option to periodically cache the feeds as flat XML in Amazon S3 or on a separate FTP to speed up delivery, and to store the media files themselves there as well.
	Version: 1.0
	Author: Jared Counts
	Author URI: http://www.houstonpublicmedia.org/staff/jared-counts/
	License: GPL2
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
						'password' => ''
					),
					'sftp' => array(
						'host' => '',
						'url' => '',
						'username' => '',
						'password' => ''
					),
					's3' => array(
						'folder' => '',
						'bucket' => '',
						'region' => '',
						'key' => '',
						'secret' => ''
					)
				),
				'email' => '',
				'https' => ''
			);
			add_option( 'hpm_podcasts', $pods );
		endif;
		/*
		 * Add new scheduling options to the Cron
		 */
		add_filter( 'cron_schedules', 'hpm_cron_sched' );

		if ( !empty( $pods['recurrence'] ) ) :
			add_action( 'hpm_podcast_update', 'hpm_podcast_generate' );
			$timestamp = wp_next_scheduled( 'hpm_podcast_update' );
			if ( empty( $timestamp ) ) :
				wp_schedule_event( time(), $pods['recurrence'], 'hpm_podcast_update' );
			endif;
		endif;
	}

	function hpm_podcast_deactivation() {
		wp_clear_scheduled_hook( 'hpm_podcast_update' );
		$pods =  get_option( 'hpm_podcasts' );
		if ( !empty( $pods ) ) :
			delete_option( 'hpm_podcasts' );
		endif;
	}

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

	require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/post-type.php' );
	require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/post-editor.php' );
	require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/admin.php' );
	require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/upload-cache.php' );
	require_once( HPM_PODCAST_PLUGIN_DIR . 'inc/templates.php' );