<h3><?PHP _e( "Feed-Specific Title", 'hpm-podcasts' ); ?></h3>
<p><?PHP _e( "If you want a different title in the podcast feed than the article, enter it here:", 'hpm-podcasts' );
	?><br />
	<label for="hpm-podcast-title"><?php _e( "Title:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-title" name="hpm-podcast-title" value="<?PHP echo $hpm_pod_desc['title']; ?>" placeholder="The Gang Finds a Pizza" style="width: 75%;" />
</p>
<h3><?PHP _e( "Feed-Specific Excerpt", 'hpm-podcasts' ); ?></h3>
<p><?PHP _e( "If this post is part of a podcast, and you would like something other than the content of this post to appear in iTunes, put your content here.", 'hpm-podcasts' ); ?></p>
<p>
	<label for="hpm-podcast-description"><?php _e( "Feed-Specific Description:", 'hpm-podcasts' ); ?></label><br />
	<?php
		$editor_opts = [
			'editor_height' => 150,
			'media_buttons' => false,
			'teeny' => true
		];
		wp_editor( $hpm_pod_desc['description'], 'hpm-podcast-description', $editor_opts );
	?>
</p>
<h3><?php _e( "Episode Information", 'hpm-podcasts' ); ?></h3>

<p><label for="hpm-podcast-episodetype"><?php _e( "Episode Type:", 'hpm-podcasts' ); ?></label>
<select name="hpm-podcast-episodetype" id="hpm-podcast-episodetype">
	<option value="full" <?PHP selected( 'full', $hpm_pod_desc['episodeType'], TRUE ); ?>><?PHP _e( "Full", 'hpm-podcasts' ); ?></option>
	<option value="trailer" <?PHP selected( 'trailer', $hpm_pod_desc['episodeType'], TRUE ); ?>><?PHP _e( "Trailer", 'hpm-podcasts' ); ?></option>
	<option value="bonus" <?PHP selected( 'bonus', $hpm_pod_desc['episodeType'], TRUE ); ?>><?PHP _e( "Bonus", 'hpm-podcasts' ); ?></option>
</select></p>

<p style="float: left; width: 50%;"><label for="hpm-podcast-episode"><?php _e( "Episode Number:", 'hpm-podcasts' );
?></label>
	<input type="number" id="hpm-podcast-episode" name="hpm-podcast-episode" value="<?PHP echo $hpm_pod_desc['episode']; ?>" placeholder="" style="width: 25%;" /></p>
<p style="float: left; width: 50%;"><label for="hpm-podcast-season"><?php _e( "Season Number:", 'hpm-podcasts' ); ?></label>
	<input type="number" id="hpm-podcast-season" name="hpm-podcast-season" value="<?PHP echo $hpm_pod_desc['season']; ?>" placeholder="" style="width: 25%;" /></p>
<?php
if ( !empty( $pods['upload-media'] ) ) :
	$hpm_pod_sg = get_post_meta( $object->ID, 'hpm_podcast_enclosure', true );
	$sg_url = '';
	if ( !empty( $hpm_pod_sg ) ) :
		if ( !empty( $hpm_pod_desc['feed'] ) ) :
			$sg_url = $hpm_pod_sg['url'];
		endif;
	endif;
	$podcasts = new WP_Query([
		'post_type' => 'podcasts',
		'post_status' => 'publish',
		'orderby' => 'name',
		'order' => 'ASC'
	]); ?>
	<p>&nbsp;</p>
	<h3><?PHP _e( "Podcast Feed", 'hpm-podcasts' ); ?></h3>
	<p id="hpm-podcast-feeds">
		<label for="hpm-podcast-ep-feed"><?php _e( "Podcast Feed:", 'hpm-podcasts' ); ?></label>
		<select name="hpm-podcast-ep-feed" id="hpm-podcast-ep-feed">
			<option value=""<?PHP selected( '', $hpm_pod_desc['feed'], TRUE ); ?>><?PHP _e( "Select One", 'hpm-podcasts' ); ?></option>
			<?php
			if ( $podcasts->have_posts() ) :
				while ( $podcasts->have_posts() ) : $podcasts->the_post(); ?>
					<option value="<?PHP echo $post->post_name; ?>"<?PHP selected( $hpm_pod_desc['feed'], $post->post_name, TRUE );?>><?PHP the_title(); ?></option>
					<?php
				endwhile;
			endif; ?>
		</select>&nbsp;&nbsp;&nbsp;<a href="#" class="button button-secondary" id="hpm-pods-upload">Choose Audio for Podcast</a></p>
	<h3><?PHP _e( "External URL", 'hpm-podcasts' ); ?></h3>
	<p><?PHP _e( "If you want to upload your audio file manually, you can paste the URL here:", 'hpm-podcasts' );
		?><br />
		<label for="hpm-podcast-sg-file"><?php _e( "URL:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-sg-file" name="hpm-podcast-sg-file" value="<?PHP echo $sg_url; ?>" placeholder="https://ondemand.example.com/blah/blah.mp3" style="width: 75%;" /></p>
	<?php
endif; ?>
<script>
	function uploadCheck() {
		var id = jQuery('#post_ID').val();
		jQuery.ajax({
			type: 'GET',
			url: '/wp-json/hpm-podcast/v1/upload/'+id+'/progress',
			data: '',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', '<?php echo wp_create_nonce( 'wp_rest' ); ?>' );
			},
			success: function (response) {
				if ( response.data.current === 'in-progress' ) {
					jQuery("#hpm-upload-message").text(response.message);
					setTimeout( 'uploadCheck()', 5000 );
				} else if ( response.data.current === 'success' ) {
					jQuery('#hpm-upload').remove();
					jQuery('#hpm-podcast-sg-file').val(response.data.url).addClass('refresh').removeClass('refresh');
					jQuery( '<div class="notice notice-success is-dismissible"><p>'+response.message+'</p></div>' )
						.insertBefore( jQuery('#hpm-podcast-feeds') );
				}
			},
			error: function (response) {
				jQuery('#hpm-upload').remove();
				if (typeof response.responseJSON.message !== 'undefined') {
					jQuery('<div class="notice notice-error is-dismissible"><p>' + response.responseJSON.message +
						'</p></div>').insertBefore(jQuery('#hpm-podcast-feeds'));
				} else {
					console.log(response);
					jQuery('<div class="notice notice-error is-dismissible">There was an error while performing this function. Please consult your javascript console for more information.</div>').insertBefore(jQuery('#hpm-podcast-feeds'));
				}
			}
		});
	}

	jQuery(document).ready(function($){
		var metaClass = $('#hpm-podcast-meta-class');
		var desc = $("#hpm-podcast-description");
		metaClass.find(".handlediv").after("<div style=\"position:absolute;top:12px;right:34px;color:#666;\"><small>Excerpt length: </small><span id=\"excerpt_counter\"></span><span style=\"font-weight:bold; padding-left:7px;\">/ 4000</span><small><span style=\"font-weight:bold; padding-left:7px;\">character(s).</span></small></div>");
		$("span#excerpt_counter").text(desc.val().length);
		desc.keyup( function() {
			if($(this).val().length > 4000){
				$(this).val($(this).val().substr(0, 4000));
			}
			$("span#excerpt_counter").text($("#hpm-podcast-description").val().length);
		});
		$('#hpm-pods-upload').click(function(e){
			e.preventDefault();
			var frame = wp.media({
				title: 'Choose Your Audio File',
				library: {type: 'audio/mpeg'},
				multiple: false,
				button: {text: 'Upload to StreamGuys'}
			});
			frame.on('select', function(){
				var attachId = frame.state().get('selection').first().id;
				var id = $('#post_ID').val();
				var feed = $('#hpm-podcast-ep-feed').val();
				console.log('Attach ID: '+attachId);
				$('#hpm-pods-upload').after( '<span id="hpm-upload"><img style="padding: 0 0.5em; vertical-align: middle;" src="<?php echo WP_SITEURL; ?>/wp-includes/images/spinner.gif"><span style="margin-left: 1em;" id="hpm-upload-message"></span></span>' );
				$.ajax({
					type: 'GET',
					url: '/wp-json/hpm-podcast/v1/upload/'+feed+'/'+id+'/'+attachId,
					data: '',
					success: function (response) {
						$('#hpm-upload-message').text(response.message);
						setTimeout( 'uploadCheck()', 3000 );
					},
					error: function (response) {
						$('#hpm-upload').remove();
						if (typeof response.responseJSON.message !== 'undefined') {
							$('<div class="notice notice-error is-dismissible"><p>' + response.responseJSON.message +
								'</p></div>').insertBefore($('#hpm-podcast-feeds'));
						} else {
							console.log(response);
							$('<div class="notice notice-error is-dismissible">There was an error while performing this function. Please consult your javascript console for more information.</div>').insertBefore($('#hpm-podcast-feeds'));
						}
					}
				});
			});
			frame.open();
		});
	});
</script>