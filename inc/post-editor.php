<?php
/**
 * Creating and setting up the metadata boxes in the post editor
 */
add_action( 'load-post.php', 'hpm_podcast_description_setup' );
add_action( 'load-post-new.php', 'hpm_podcast_description_setup' );
function hpm_podcast_description_setup() {
	add_action( 'add_meta_boxes', 'hpm_podcast_add_description' );
	add_action( 'save_post', 'hpm_podcast_save_description', 10, 2 );
}

function hpm_podcast_add_description() {
	add_meta_box(
		'hpm-podcast-meta-class',
		esc_html__( 'Podcast Feed Information', 'hpm-podcasts' ),
		'hpm_podcast_description_box',
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
function hpm_podcast_description_box( $object, $box ) {
	$pods = get_option( 'hpm_podcast_settings' );
	global $post;
	$post_old = $post;
	wp_nonce_field( basename( __FILE__ ), 'hpm_podcast_class_nonce' );
	$hpm_pod_desc = get_post_meta( $object->ID, 'hpm_podcast_ep_meta', true );
	if ( empty( $hpm_pod_desc ) ) :
		$hpm_pod_desc = array( 'feed' => '', 'description' => '' );
	endif; ?>
<h3><?PHP _e( "Feed-Specific Excerpt", 'hpm-podcasts' ); ?></h3>
<p><?PHP _e( "If this post is part of a podcast, and you would like something other than the content of this post to appear in iTunes, put your content here. <br /><br /><i><b>**NOTE**</b>: Any HTML formatting will have to be entered manually, so be careful.</i>", 'hpm-podcasts' ); ?></p>
<p>
	<label for="hpm-podcast-description"><?php _e( "Feed-Specific Description:", 'hpm-podcasts' ); ?></label><br />
	<textarea style="width: 100%; height: 200px;" name="hpm-podcast-description" id="hpm-podcast-description"><?php echo $hpm_pod_desc['description']; ?></textarea>
</p>
<?php
	if ( !empty( $pods['upload-media'] ) ) :
		$hpm_pod_sg = get_post_meta( $object->ID, 'hpm_podcast_sg_file', true );
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
				endif;
				wp_reset_query();
				$post = $post_old; ?>
		</select>&nbsp;&nbsp;&nbsp;<a href="#" class="button button-secondary" id="hpm-pods-upload">Upload Media File</a></p>
	<h3><?PHP _e( "External URL", 'hpm-podcasts' ); ?></h3>
	<p><?PHP _e( "If you want to upload your audio file manually, you can paste the URL here:", 'hpm-podcasts' );
	?><br />
		<label for="hpm-podcast-sg-file"><?php _e( "URL:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-sg-file" name="hpm-podcast-sg-file" value="<?PHP echo $hpm_pod_sg; ?>" placeholder="https://ondemand.example.com/blah/blah.mp3" style="width: 75%;" /></p>
<?php
	endif; ?>
<script>
	jQuery(document).ready(function($){
		$("#hpm-podcast-meta-class .handlediv").after("<div style=\"position:absolute;top:12px;right:34px;color:#666;\"><small>Excerpt length: </small><span id=\"excerpt_counter\"></span><span style=\"font-weight:bold; padding-left:7px;\">/ 4000</span><small><span style=\"font-weight:bold; padding-left:7px;\">character(s).</span></small></div>");
		$("span#excerpt_counter").text($("#hpm-podcast-description").val().length);
		$("#hpm-podcast-description").keyup( function() {
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
</script><?php
}

/**
 * Saving the media file feed and episode-specific description in postmeta.
 *
 * If your media files are being uploaded to another service, this function will also kick off a cron job to handle
 * the upload.
 */
function hpm_podcast_save_description( $post_id, $post ) {
	if ( empty( $_POST['hpm_podcast_class_nonce'] ) || !wp_verify_nonce( $_POST['hpm_podcast_class_nonce'], basename( __FILE__ ) ) ) :
		return $post_id;
	endif;
	$pods = get_option( 'hpm_podcast_settings' );

	$post_type = get_post_type_object( $post->post_type );

	if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) :
		return $post_id;
	endif;

	$hpm_podcast_description = array(
		'feed' => ( !empty( $_POST['hpm-podcast-ep-feed'] ) ? $_POST['hpm-podcast-ep-feed'] : '' ),
		'description' => balanceTags( $_POST['hpm-podcast-description'], true )
	);
	$sg_url = ( isset( $_POST['hpm-podcast-sg-file'] ) ? sanitize_text_field( $_POST['hpm-podcast-sg-file'] ) : '' );

	$hpm_pod_desc_exists = metadata_exists( 'post', $post_id, 'hpm_podcast_ep_meta' );
	if ( $hpm_pod_desc_exists ) :
		update_post_meta( $post_id, 'hpm_podcast_ep_meta', $hpm_podcast_description );
	else :
		add_post_meta( $post_id, 'hpm_podcast_ep_meta', $hpm_podcast_description, true );
	endif;

	$hpm_pod_sg_file = metadata_exists( 'post', $post_id, 'hpm_podcast_sg_file' );
	if ( !empty( $pods['upload-media'] ) ) :
		if ( $hpm_pod_sg_file ) :
			update_post_meta( $post_id, 'hpm_podcast_sg_file', $sg_url );
		else :
			add_post_meta( $post_id, 'hpm_podcast_sg_file', $sg_url, true );
		endif;
	else :
		if ( $hpm_pod_sg_file ) :
			delete_post_meta( $post_id, 'hpm_podcast_sg_file', $sg_url );
		endif;
	endif;
}