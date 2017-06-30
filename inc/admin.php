<div class="wrap">
	<h1><?php _e('Podcast Administration', 'hpm-podcasts' ); ?></h1>
	<?php settings_errors(); ?>
	<p><?php _e('Hello, and thank you for installing our plugin.  The following sections will walk you through all of the data we need to gather to properly set up your podcast feeds.', 'hpm-podcasts' ); ?></p>
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
							<h2 class="hndle"><span><?php _e('Ownership Information', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('iTunes and other podcasting directories ask for you to give a name and email address of the "owner" of the podcast, which can be a single person or an organization.', 'hpm-podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[owner][name]"><?php _e('Owner Name', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[owner][name]" value="<?php echo $pods['owner']['name']; ?>" class="regular-text" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[owner][email]"><?php _e('Owner Email', 'hpm-podcasts' ); ?></label></th>
										<td><input type="email" name="hpm_podcast_settings[owner][email]" value="<?php echo $pods['owner']['email']; ?>" class="regular-text" /></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('User Roles', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('Select all of the user roles that you would like to be able to manage your podcast feeds.  Anyone 
										who can create new posts can create an episode of a podcast, but only the roles selected here can 
										create, alter, or delete podcast feeds.', 'hpm-podcasts' ); ?></p>
								<p><?php _e('To select more than one, hold down Ctrl (on Windows) or Command (on Mac) and click the roles you want included.', 'hpm-podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[roles]"><?php _e('Select Your Roles', 'hpm-podcasts' );
												?></label></th>
										<td>
											<select name="hpm_podcast_settings[roles][ ]" multiple class="regular-text">
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
							<h2 class="hndle"><span><?php _e('Background Tasks', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('To save server resources, we use a cron job to generate a flat XML file.  Use the options below to choose how often you want to run that job.', 'hpm-podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[recurrence]"><?php _e('Select Your Time Period', 'hpm-podcasts' );
												?></label></th>
										<td>
											<select name="hpm_podcast_settings[recurrence]" class="regular-text">
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
							<h2 class="hndle"><span><?php _e('Upload Options', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('By default, this plugin will store the flat XML files in a folder in your "uploads" directory, and the media files will be stored with the rest of your attachments.  However, if you want to store your files elsewhere, select one of the options below.', 'hpm-podcasts' );
									?></p>
								<p><?php _e('**NOTE**: If you go the S3 route, it is recommended that you create a new IAM user in Amazon Web Services that only has access to your S3 buckets.  Please refer to Amazon\'s documentation for how to manage your user settings.', 'hpm-podcasts' );
									?></p>
								<ul>
									<li><a href="http://docs.aws.amazon.com/AmazonS3/latest/dev/walkthrough1.html" target="_blank">Amazon IAM User Documentation</a></li>
								</ul>
								<p><?php _e('**ALSO NOTE**: Please do not include any leading or trailing slashes in your domains, URLs, folder names, etc. You can include slashes within them (e.g. you might store your files in the "files/podcasts" folder, but the public URL is "http://example.com/podcasts").',
										'hpm-podcasts' );
									?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[upload-flats]"><?php _e('Flat XML File Upload?', 'hpm-podcasts' );
												?></label></th>
										<td>
											<select name="hpm_podcast_settings[upload-flats]" class="regular-text" id="hpm-flats">
												<option value="">Local</option>
												<option value="s3" <?php selected( $pods['upload-flats'], 's3', TRUE); ?>>Amazon
													S3</option>
												<option value="ftp" <?php selected( $pods['upload-flats'], 'ftp', TRUE); ?>>FTP</option>
												<option value="sftp" <?php selected( $pods['upload-flats'], 'sftp', TRUE); ?>>SFTP</option>
												<option value="sftp" <?php selected( $pods['upload-flats'], 'database', TRUE); ?>>Database</option>
											</select>
										</td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[upload-media]"><?php _e('Media File Upload?', 'hpm-podcasts' );
												?></label></th>
										<td>
											<select name="hpm_podcast_settings[upload-media]" class="regular-text" id="hpm-media">
												<option value="">Local</option>
												<option value="s3" <?php selected( $pods['upload-media'], 's3', TRUE); ?>>AmazonS3</option>
												<option value="ftp" <?php selected( $pods['upload-media'], 'ftp', TRUE); ?>>FTP</option>
												<option value="sftp" <?php selected( $pods['upload-media'], 'sftp', TRUE); ?>>SFTP</option>
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
							<h2 class="hndle"><span><?php _e('Amazon S3 Credentials', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e("If you aren't comfortable storing your AWS key and secret in your database, you can define them as Wordpress defaults.  Add the following lines to your wp-config.php file:",	'hpm-podcasts' );
									?></p>
								<pre>define('AWS_ACCESS_KEY_ID', 'YOUR_AWS_KEY');
	define('AWS_SECRET_ACCESS_KEY', 'YOUR_AWS_SECRET');</pre>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][s3][key]"><?php
												_e('Amazon S3 Access Key', 'hpm-podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][s3][key]" <?php
											if ( defined( 'AWS_ACCESS_KEY_ID' ) ) :
												echo 'value="Set in wp-config.php" disabled ';
											else :
												echo 'value ="'.$pods['credentials']['s3']['key'].'" ';
											endif;
											?>class="regular-text" placeholder="S3 Key" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label
												for="hpm_podcast_settings[credentials][s3][secret]"><?php _e('Amazon S3 Secret Key', 'hpm-podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][s3][secret]" <?php
											if ( defined( 'AWS_SECRET_ACCESS_KEY' ) ) :
												echo 'value="Set in wp-config.php" disabled ';
											else :
												echo 'value ="'.$pods['credentials']['s3']['secret'].'" ';
											endif; ?>class="regular-text" placeholder="S3 Secret" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label
												for="hpm_podcast_settings[credentials][s3][region]"><?php _e('Amazon S3 Region', 'hpm-podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][s3][region]" value="<?php echo $pods['credentials']['s3']['region']; ?>" class="regular-text" placeholder="us-west-2"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label
												for="hpm_podcast_settings[credentials][s3][bucket]"><?php _e('Amazon S3 Bucket', 'hpm-podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][s3][bucket]" value="<?php echo $pods['credentials']['s3']['bucket']; ?>" class="regular-text" placeholder="mybucket"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label
												for="hpm_podcast_settings[credentials][s3][folder]"><?php _e('Amazon S3 Folder Path', 'hpm-podcasts' );
												?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][s3][folder]" value="<?php echo $pods['credentials']['s3']['folder']; ?>" class="regular-text" placeholder="podcasts"
											/></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div id="hpm-ftp" class="meta-box-sortables ui-sortable hpm-uploads<?php echo $upload_ftp; ?>">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('FTP Credentials', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e("If you aren't comfortable storing your FTP password in your database, you can define it as a Wordpress default.  Add the following line to your wp-config.php file:",	'hpm-podcasts' );
									?></p>
								<pre>define('HPM_FTP_PASSWORD', 'YOUR_FTP_PASSWORD');</pre>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label
												for="hpm_podcast_settings[credentials][ftp][host]"><?php _e('FTP Host', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][ftp][host]" value="<?php echo $pods['credentials']['ftp']['host']; ?>" class="regular-text" placeholder="URL or IP Address" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][ftp][url]"><?php _e('FTP Public URL', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][ftp][url]" value="<?php echo $pods['credentials']['ftp']['url']; ?>" class="regular-text" placeholder="http://ondemand.example.com" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][ftp][username]"><?php _e('FTP Username', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][ftp][username]" value="<?php echo
											$pods['credentials']['ftp']['username']; ?>" class="regular-text" placeholder="thisguy"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][ftp][password]"><?php _e('FTP Host', 'hpm-podcasts' ); ?></label></th>
										<td><input name="hpm_podcast_settings[credentials][ftp][password]" <?php
											if ( defined( 'HPM_FTP_PASSWORD' ) ) :
												echo 'value="Set in wp-config.php" disabled type="text" ';
											else :
												echo 'value ="'.$pods['credentials']['ftp']['password'].'" type="password" ';
											endif; ?>class="regular-text" placeholder="P@assw0rd" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][ftp][folder]"><?php _e('FTP Folder', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][ftp][folder]" value="<?php echo $pods['credentials']['ftp']['folder']; ?>" class="regular-text" placeholder="folder" /></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div id="hpm-sftp" class="meta-box-sortables ui-sortable hpm-uploads<?php echo $upload_sftp; ?>">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('SFTP Credentials', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e("If you aren't comfortable storing your SFTP password in your database, you can define it as a Wordpress default.  Add the following line to your wp-config.php file:",	'hpm-podcasts' );
									?></p>
								<pre>define('HPM_SFTP_PASSWORD', 'YOUR_SFTP_PASSWORD');</pre>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][sftp][host]"><?php _e('SFTP Host', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][sftp][host]" value="<?php echo $pods['credentials']['sftp']['host']; ?>" class="regular-text" placeholder="URL or IP
											Address" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][sftp][url]"><?php _e('SFTP Public URL', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][sftp][url]" value="<?php echo $pods['credentials']['sftp']['url']; ?>" class="regular-text" placeholder="http://ondemand.example.com" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][sftp][username]"><?php _e('SFTP Username', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][sftp][username]" value="<?php echo
											$pods['credentials']['sftp']['username']; ?>" class="regular-text" placeholder="thisguy"
											/></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][sftp][password]"><?php _e('SFTP Password', 'hpm-podcasts' ); ?></label></th>
										<td><input name="hpm_podcast_settings[credentials][sftp][password]" <?php
											if ( defined( 'HPM_SFTP_PASSWORD' ) ) :
												echo 'value="Set in wp-config.php" disabled type="text" ';
											else :
												echo 'value ="'.$pods['credentials']['sftp']['password'].'" type="password" ';
											endif; ?>class="regular-text" placeholder="P@assw0rd" /></td>
									</tr>
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[credentials][sftp][folder]"><?php _e('SFTP Folder', 'hpm-podcasts' ); ?></label></th>
										<td><input type="text" name="hpm_podcast_settings[credentials][sftp][folder]" value="<?php echo $pods['credentials']['sftp']['folder']; ?>" class="regular-text" placeholder="folder" /></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Force HTTPS?', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e('Apple Podcasts/iTunes, as well as other podcasting directories, are starting to favor or even require HTTPS for your feeds and media enclosures.  If you\'re already using HTTPS, or if you want to force your feeds and the associated background processes to use HTTPS, then check the box below.', 'hpm-podcasts' ); ?></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label for="hpm_podcast_settings[https]"><?php _e('Force HTTPS in Feed?', 'hpm-podcasts' );
												?></label></th>
										<td><input type="checkbox" name="hpm_podcast_settings[https]" value="force-https" class="regular-text"<?php
											if ( !empty( $pods['https'] ) ) :
												if ( $pods['https'] == 'force-https' ) :
													echo ' checked';
												endif;
											endif; ?>/></td>
									</tr>
								</table>
							</div>
						</div>
					</div>
					<div class="meta-box-sortables ui-sortable">
						<div class="postbox">
							<div class="handlediv" title="Click to toggle"><br></div>
							<h2 class="hndle"><span><?php _e('Feed Refresh', 'hpm-podcasts' ); ?></span></h2>
							<div class="inside">
								<p><?php _e("Made some changes to your podcast feeds and don't want to wait for the cron job to fire? Click the button below to force a refresh.", 'hpm-podcasts'	); ?></p>
								<p><em>Feeds last refreshed: <span class="hpm-last-refresh-time"><?php echo $last_refresh; ?></span></em></p>
								<table class="form-table">
									<tr valign="top">
										<th scope="row"><label><?php _e('Force Feed Refresh?', 'hpm-podcasts' );
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
				$(this).after( ' <img id="hpm-refresh-spinner" src="/wp-includes/images/spinner.gif">' );
				$.ajax({
					type: 'GET',
					url: '/wp-json/hpm-podcast/v1/refresh',
					data: '',
					success: function (response) {
						$('#hpm-refresh-spinner').remove();
						$('.hpm-last-refresh-time').html(response.data.date);
						$( '<div class="notice notice-success is-dismissible"><p>'+response.message+'</p></div>' ).insertBefore( $('#hpm-pods-refresh').closest('table.form-table') );
					},
					error: function (response) {
						$('#hpm-refresh-spinner').remove();
						if (typeof response.responseJSON.message !== 'undefined') {
							$( '<div class="notice notice-error is-dismissible"><p>'+response.responseJSON.message+'</p></div>' ).insertBefore( $('#hpm-pods-refresh').closest('table.form-table') );
						} else {
							console.log(response);
							$( '<div class="notice notice-error is-dismissible">There was an error while performing this function. Please consult your javascript console for more information.</div>' ).insertBefore( $('#hpm-pods-refresh').closest('table.form-table') );
						}
					}
				});
			});
		});
	</script>
</div>