<h3><?PHP _e( "Feed-Specific Excerpt", 'hpm-podcasts' ); ?></h3>
<p><?PHP _e( "If this post is part of a podcast, and you would like something other than the content of this post to appear in iTunes, put your content here. <br /><br /><i><b>**NOTE**</b>: Any HTML formatting will have to be entered manually, so be careful.</i>", 'hpm-podcasts' ); ?></p>
<p>
	<label for="hpm-podcast-description"><?php _e( "Feed-Specific Description:", 'hpm-podcasts' ); ?></label><br />
	<textarea style="width: 100%; height: 200px;" name="hpm-podcast-description" id="hpm-podcast-description"><?php echo $hpm_pod_desc['description']; ?></textarea>
</p>
<?php
if ( !empty( $pods['upload-media'] ) ) :
	$hpm_pod_sg = get_post_meta( $object->ID, 'hpm_podcast_enclosure', true );
	$sg_url = '';
	if ( !empty( $hpm_pod_sg ) ) :
		$sg_url = $hpm_pod_sg['url'];
	endif;
	$podcasts = new WP_Query(
		array(
			'post_type' => 'podcasts',
			'post_status' => 'publish',
			'orderby' => 'name',
			'order' => 'ASC'
		)
	); ?>
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
		</select>&nbsp;&nbsp;&nbsp;<a href="#" class="button button-secondary" id="hpm-pods-upload">Upload Media File</a></p>
	<h3><?PHP _e( "External URL", 'hpm-podcasts' ); ?></h3>
	<p><?PHP _e( "If you want to upload your audio file manually, you can paste the URL here:", 'hpm-podcasts' );
		?><br />
		<label for="hpm-podcast-sg-file"><?php _e( "URL:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-sg-file" name="hpm-podcast-sg-file" value="<?PHP echo $sg_url; ?>" placeholder="https://ondemand.example
		.com/blah/blah.mp3" style="width: 75%;" /></p>
	<?php
endif; ?>
<script>
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
			var id = $('#post_ID').val();
			var feed = $('#hpm-podcast-ep-feed').val();
			$(this).after( ' <img id="hpm-upload-spinner" src="/wp-includes/images/spinner.gif">' );
			$.ajax({
				type: 'GET',
				url: '/wp-json/hpm-podcast/v1/upload/'+feed+'/'+id,
				data: '',
				success: function (response) {
					$('#hpm-upload-spinner').remove();
					$('#hpm-podcast-sg-file').val(response.data.url).addClass('refresh').removeClass('refresh');
					$( '<div class="notice notice-success is-dismissible"><p>'+response.message+'</p></div>' ).insertBefore( $('#hpm-pods-upload') );
				},
				error: function (response) {
					$('#hpm-upload-spinner').remove();
					if (typeof response.responseJSON.message !== 'undefined') {
						$('<div class="notice notice-error is-dismissible"><p>' + response.responseJSON.message + '</p></div>').insertBefore($('#hpm-pods-upload'));
					} else {
						console.log(response);
						$('<div class="notice notice-error is-dismissible">There was an error while performing this function. Please consult your javascript console for more information.</div>').insertBefore($('#hpm-pods-upload'));
					}
				}
			});
		});
	});
</script>