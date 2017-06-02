<?php
/**
 * Create options page in Podcasts menu in Admin Dashboard
 */
add_action('admin_menu', 'hpm_podcast_create_menu');
function hpm_podcast_create_menu() {
	add_submenu_page( 'edit.php?post_type=podcasts', 'HPM Podcast Settings', 'Settings', 'manage_options', 'hpm-podcast-settings', 'hpm_podcast_settings_page' );
	add_action( 'admin_init', 'register_hpm_podcast_settings' );
}

function register_hpm_podcast_settings() {
	register_setting( 'hpm-podcast-settings-group', 'hpm_podcasts' );
}

/**
 * Build out options page
 */
function hpm_podcast_settings_page() {
	$pods = get_option( 'hpm_podcasts' );
	$pods_last = get_option( 'hpm_podcasts_last_update' );
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
		$date_format = get_option( 'date_format' );
		$time_format = get_option( 'time_format' );
		$last_refresh = date( $date_format.' @ '.$time_format, $pods_last );
	else :
		$last_refresh = 'Never';
	endif; ?>
<div class="wrap">
	<h1><?php _e('Podcast Administration', 'hpm_podcasts' ); ?></h1>
	<?php settings_errors(); ?>
	<p><?php _e('Hello, and thank you for installing our plugin.  The following sections will walk you through all of the data we need to gather to properly set up your podcast feeds.', 'hpm_podcasts' ); ?></p>
	<p><em>Feeds last refreshed: <span class="hpm-last-refresh-time"><?php echo $last_refresh; ?></span></em></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'hpm-podcast-settings-group' ); ?>
		<?php do_settings_sections( 'hpm-podcast-settings-group' ); ?>
		<div id="poststuff">
			<div id="post-body" class="metabox-holder columns-2">
				<div id="post-body-content">
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Ownership Information', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('iTunes and other podcasting directories ask for you to give a name and email address of the "owner" of the podcast, which can be a single person or an organization.', 'hpm_podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[owner][name]"><?php _e('Owner Name', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[owner][name]" value="<?php echo $pods['owner']['name']; ?>" class="regular-text" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[owner][email]"><?php _e('Owner Email', 'hpm_podcasts' ); ?></label></th>
										<td><input type="email" name="hpm_podcasts[owner][email]" value="<?php echo $pods['owner']['email']; ?>" class="regular-text" /></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('User Roles', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('Select all of the user roles that you would like to be able to manage your podcast feeds.  Anyone 
									who can create new posts can create an episode of a podcast, but only the roles selected here can 
									create, alter, or delete podcast feeds.', 'hpm_podcasts' ); ?></p>
								<p><?php _e('To select more than one, hold down Ctrl (on Windows) or Command (on Mac) and click the roles you want included.', 'hpm_podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[roles]"><?php _e('Select Your Roles', 'hpm_podcasts' );
										?></label></th>
										<td>
											<select name="hpm_podcasts[roles][ ]" multiple class="regular-text">
											<?php foreach ( get_editable_roles() as $role_name => $role_info ) : ?>
												<option value="<?php echo $role_name; ?>"<?php echo ( in_array( $role_name, $pods['roles'] ) ? " selected" : '' ); ?>><?php echo $role_name; ?></option>
											<?php endforeach; ?>
											</select>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Background Tasks', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('To save server resources, we use a cron job to generate a flat XML file.  Use the options below to choose how often you want to run that job.', 'hpm_podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[recurrence]"><?php _e('Select Your Time Period', 'hpm_podcasts' );
												?></label></th>
										<td>
											<select name="hpm_podcasts[recurrence]" class="regular-text">
												<option value="hourly" <?php selected( $pods['recurrence'], 'hourly', TRUE );
												?>>Hourly</option>
												<option value="hpm_30min" <?php selected( $pods['recurrence'], 'hpm_30min', TRUE ); ?>>Every 30 Minutes</option>
												<option value="hpm_15min" <?php selected( $pods['recurrence'], 'hpm_15min', TRUE ); ?>>Every 15 Minutes</option>
												<option value="hpm_5min" <?php selected( $pods['recurrence'], 'hpm_5min', TRUE ); ?>>Every 5 Minutes</option>
											</select>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Upload Options', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('By default, this plugin will store the flat XML files in a folder in your "uploads" directory, and the media files will be stored with the rest of your attachments.  However, if you want to store your files elsewhere, select one of the options below.', 'hpm_podcasts' );
									?></p>
								<p><?php _e('**NOTE**: If you go the S3 route, it is recommended that you create a new IAM user in Amazon Web Services that only has access to your S3 buckets.  Please refer to Amazon\'s documentation for how to manage your user settings.', 'hpm_podcasts' );
									?></p>
								<ul>
									<li><a href="http://docs.aws.amazon.com/AmazonS3/latest/dev/walkthrough1.html" target="_blank">Amazon IAM User Documentation</a></li>
								</ul>
								<p><?php _e('**ALSO NOTE**: Please do not include any leading or trailing slashes in your domains, URLs, folder names, etc. You can include slashes within them (e.g. you might store your files in the "files/podcasts" folder, but the public URL is "http://example.com/podcasts").',
										'hpm_podcasts' );
									?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[upload-flats]"><?php _e('Flat XML File Upload?', 'hpm_podcasts' );
												?></label></th>
										<td>
											<select name="hpm_podcasts[upload-flats]" class="regular-text" id="hpm-flats">
												<option value="">Local</option>
												<option value="s3" <?php selected( $pods['upload-flats'], 's3', TRUE); ?>>Amazon
													S3</option>
												<option value="ftp" <?php selected( $pods['upload-flats'], 'ftp', TRUE); ?>>FTP</option>
												<option value="sftp" <?php selected( $pods['upload-flats'], 'sftp', TRUE);
												?>>SFTP</option>
											</select>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[upload-media]"><?php _e('Media File Upload?', 'hpm_podcasts' );
												?></label></th>
										<td>
											<select name="hpm_podcasts[upload-media]" class="regular-text" id="hpm-media">
												<option value="">Local</option>
												<option value="s3" <?php selected( $pods['upload-media'], 's3', TRUE); ?>>Amazon
													S3</option>
												<option value="ftp" <?php selected( $pods['upload-flats'], 'ftp', TRUE); ?>>FTP</option>
												<option value="sftp" <?php selected( $pods['upload-flats'], 'sftp', TRUE);
												?>>SFTP</option>
											</select>
										</td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div id="hpm-s3"class="meta-box-sortables ui-sortable hpm-uploads<?php echo $upload_s3; ?>">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Amazon S3 Credentials', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][s3][key]"><?php _e('Amazon S3 Access Key', 'hpm_podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][s3][key]" value="<?php echo $pods['credentials']['s3']['key']; ?>"
												   class="regular-text" placeholder="" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][s3][secret]"><?php _e('Amazon S3 Secret Key', 'hpm_podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][s3][secret]" value="<?php echo $pods['credentials']['s3']['secret']; ?>" class="regular-text" placeholder="" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][s3][region]"><?php _e('Amazon S3 Region', 'hpm_podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][s3][region]" value="<?php echo $pods['credentials']['s3']['region']; ?>" class="regular-text" placeholder="us-west-2"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][s3][bucket]"><?php _e('Amazon S3 Bucket', 'hpm_podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][s3][bucket]" value="<?php echo $pods['credentials']['s3']['bucket']; ?>" class="regular-text" placeholder="mybucket"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][s3][folder]"><?php _e('Amazon S3 Folder Path', 'hpm_podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][s3][folder]" value="<?php echo $pods['credentials']['s3']['folder']; ?>" class="regular-text" placeholder="podcasts"
											/></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div id="hpm-ftp" class="meta-box-sortables ui-sortable hpm-uploads<?php echo $upload_ftp; ?>">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('FTP Credentials', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][ftp][host]"><?php _e('FTP Host', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][ftp][host]" value="<?php echo $pods['credentials']['ftp']['host']; ?>" class="regular-text" placeholder="URL or IP Address" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][ftp][url]"><?php _e('FTP Public URL', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][ftp][url]" value="<?php echo $pods['credentials']['ftp']['url']; ?>" class="regular-text" placeholder="http://ondemand.example.com" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][ftp][username]"><?php _e('FTP Username', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][ftp][username]" value="<?php echo
											$pods['credentials']['ftp']['username']; ?>" class="regular-text" placeholder="thisguy"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][ftp][password]"><?php _e('FTP Host', 'hpm_podcasts' ); ?></label></th>
										<td><input type="password" name="hpm_podcasts[credentials][ftp][password]" value="<?php echo $pods['credentials']['ftp']['password']; ?>" class="regular-text" placeholder="P@assw0rd"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][ftp][folder]"><?php _e('FTP Folder', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][ftp][folder]" value="<?php echo $pods['credentials']['ftp']['folder']; ?>" class="regular-text" placeholder="folder" /></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div id="hpm-sftp" class="meta-box-sortables ui-sortable hpm-uploads<?php echo $upload_sftp; ?>">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('SFTP Credentials', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][sftp][host]"><?php _e('SFTP Host', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][sftp][host]" value="<?php echo $pods['credentials']['sftp']['host']; ?>" class="regular-text" placeholder="URL or IP
										Address" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][sftp][url]"><?php _e('SFTP Public URL', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][sftp][url]" value="<?php echo $pods['credentials']['sftp']['url']; ?>" class="regular-text" placeholder="http://ondemand.example.com" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][sftp][username]"><?php _e('SFTP Username', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][sftp][username]" value="<?php echo
											$pods['credentials']['sftp']['username']; ?>" class="regular-text" placeholder="thisguy"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][sftp][password]"><?php _e('SFTP Host', 'hpm_podcasts' ); ?></label></th>
										<td><input type="password" name="hpm_podcasts[credentials][sftp][password]" value="<?php echo $pods['credentials']['sftp']['password']; ?>" class="regular-text" placeholder="P@assw0rd" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[credentials][sftp][folder]"><?php _e('SFTP Folder', 'hpm_podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcasts[credentials][sftp][folder]" value="<?php echo $pods['credentials']['sftp']['folder']; ?>" class="regular-text" placeholder="folder" /></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Force HTTPS?', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('Apple Podcasts/iTunes, as well as other podcasting directories, are starting to favor or even require HTTPS for your feeds and media enclosures.  If you\'re already using HTTPS, or if you want to force your feeds and the associated background processes to use HTTPS, then check the box below.', 'hpm_podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcasts[https]"><?php _e('Force HTTPS in Feed?', 'hpm_podcasts' );
												?></label></th>
										<td><input type="checkbox" name="hpm_podcasts[https]" value="force-https" class="regular-text" <?php echo ( !empty( $pods['https'] ) ? " selected" : '' ); ?> /></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Feed Refresh', 'hpm_podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e("Made some changes to your podcast feeds and don't want to wait for the cron job to fire? Click the button below to force a refresh.", 'hpm_podcasts'	); ?></p>
								<p><em>Feeds last refreshed: <span class="hpm-last-refresh-time"><?php echo $last_refresh; ?></span></em></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label><?php _e('Force Feed Refresh?', 'hpm_podcasts' );
												?></label></th>
										<td><a href="#" class="button button-secondary" id="hpm-pods-refresh">Refresh Feeds</a></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<br class="clear" />
		<?php submit_button(); ?>
	</form>
	<script>
		jQuery(document).ready(function($){
			$('#hpm-media,#hpm-flats').change(function(){
				var hpmFlats = $('#hpm-flats').val();
				var hpmMedia = $('#hpm-media').val();
				$('.hpm-uploads').hide();
				$('#hpm-'+hpmFlats+',#hpm-'+hpmMedia).show();
			});
			$('#hpm-pods-refresh').click(function(e){
				e.preventDefault();
				data = { action: 'hpm_podcasts_refresh' };
				$(this).after( ' <img id="hpm-refresh-spinner" src="/wp-includes/images/spinner.gif">' );
				$.ajax({
					type: 'POST',
					url: ajaxurl,
					data: data,
					success: function (response) {
						if (response.success) {
							var status = 'success';
							$('.hpm-last-refresh-time').html(response.data.date);
						} else {
							var status = 'error';
						}
						$('#hpm-refresh-spinner').remove();
						$( '<div class="notice notice-'+status+' is-dismissible"><p>'+response.data.message+'</p></div>' ).insertBefore( $('#hpm-pods-refresh').closest('table.form-table') );
					}
				});
			});
		});
	</script>
</div>
<?php
}