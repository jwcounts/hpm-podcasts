<?php
/*
	Plugin Name: HPM Podcasts
	Plugin URI: http://www.houstonpublicmedia.org
	Description: A plugin that allows you to create a podcast feed from any category, either video or audio. It also has the option to periodically cache the feeds as flat XML in Amazon S3 to speed up delivery.
	Version: 1.0
	Author: Jared Counts
	Author URI: http://www.houstonpublicmedia.org/staff/jared-counts/
	License: GPL2
*/
define( 'HPM_PODCAST_PLUGIN_DIR', plugin_dir_path(__FILE__) );

register_activation_hook( HPM_PODCAST_PLUGIN_DIR . 'main.php', 'hpm_podcast_activation' );
register_deactivation_hook( HPM_PODCAST_PLUGIN_DIR . 'main.php', 'hpm_podcast_deactivation' );


function hpm_podcast_activation() {
	$pods =  get_option( 'hpm_podcasts' );
	if ( empty( $pods ) ) :
        $pods = array(
	        'owner' => array(
		        'name' => '',
		        'email' => ''
	        ),
            'recurrence' => '',
            'roles' => array(),
            'upload-flats' => '',
            'upload-media' => '',
            'credentials' => array(
                'ftp' => array(
                    'host' => '',
                    'url' => '',
                    'username' => '',
                    'password' => '',
                    'port' => 21
                ),
                'sftp' => array(
	                'host' => '',
	                'url' => '',
	                'username' => '',
	                'password' => '',
                    'port' => 22
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

add_action( 'init', 'create_hpm_podcasts' );
function create_hpm_podcasts() {
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

add_action( 'admin_init', 'hpm_podcast_add_role_caps', 999 );
function hpm_podcast_add_role_caps() {
    $pods = get_option( 'hpm_podcasts' );
    // Add the roles you'd like to administer the custom post types

    if ( !empty( $pods['roles'] ) ) :
        $roles = $pods['roles'];
    else :
        $roles = array('editor','administrator');
    endif;

    // Loop through each role and assign capabilities
    foreach($roles as $the_role) :
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

add_action( 'load-post.php', 'hpm_podcast_setup' );
add_action( 'load-post-new.php', 'hpm_podcast_setup' );
function hpm_podcast_setup() {
	add_action( 'add_meta_boxes', 'hpm_podcast_add_meta' );
	add_action( 'save_post', 'hpm_podcast_save_meta', 10, 2 );
}

function hpm_podcast_add_meta( ) {
	add_meta_box(
		'hpm-podcast-meta-class',
		esc_html__( 'Podcast Metadata', 'hpm_podcasts' ),
		'hpm_podcast_meta_box',
		'podcasts',
		'normal',
		'core'
	);
}

function hpm_podcast_meta_box( $object, $box ) {
	wp_nonce_field( basename( __FILE__ ), 'hpm_podcast_class_nonce' );
	$exists_cat = metadata_exists( 'post', $object->ID, 'hpm_pod_cat' );
	$exists_link = metadata_exists( 'post', $object->ID, 'hpm_pod_link' );

	$itunes_cats = array(
		'Arts' => array('Design', 'Fashion & Beauty', 'Food', 'Literature', 'Performing Arts', 'Visual Arts'),
		'Business' => array('Business News', 'Careers', 'Investing', 'Management & Marketing', 'Shopping'),
		'Comedy' => array(),
		'Education' => array('Educational Technology', 'Higher Education', 'K-12', 'Language Courses', 'Training'),
		'Games & Hobbies' => array('Automotive', 'Aviation', 'Hobbies', 'Other Games', 'Video Games'),
		'Government & Organizations' => array('Local', 'National', 'Non-Profit', 'Regional'),
		'Health' => array('Alternative Health', 'Fitness & Nutrition', 'Self-Help', 'Sexuality'),
		'Kids & Family' => array(),
		'Music' => array(),
		'News & Politics' => array(),
		'Religion & Spirituality' => array('Buddhism', 'Christianity', 'Hinduism', 'Islam', 'Judaism', 'Other', 'Spirituality'),
		'Science & Medicine' => array('Medicine', 'Natural Sciences', 'Social Sciences'),
		'Society & Culture' => array('History', 'Personal Journals', 'Philosophy', 'Places & Travel'),
		'Sports & Recreation' => array('Amateur', 'College & High School', 'Outdoor', 'Professional'),
		'Technology' => array('Gadgets', 'Tech News', 'Podcasting', 'Software How-To'),
		'TV & Film' => array()
	);

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
			$hpm_podcast_link = array('page' => '', 'limit' => 0, 'itunes' => '', 'gplay' => '', 'cat-prime' => '', 'cat-second' => '', 'cat-third' => '');
		endif;
	else :
		$hpm_podcast_link = array('page' => '', 'itunes' => '', 'gplay' => '', 'blubrry' => '', 'cat-prime' => '', 'cat-second' => '', 'cat-third' => '');
	endif; ?>
	<h3><?PHP _e( "Category and Page", 'hpm_podcasts' ); ?></h3>
	<p><?PHP _e( "Select the post category for this podcast:", 'hpm_podcasts' ); ?>
<?php
	wp_dropdown_categories(array(
		'show_option_all' => __("Select One"),
		'taxonomy'        => 'category',
		'name'            => 'hpm-podcast-cat',
		'orderby'         => 'name',
		'selected'        => $hpm_podcast_cat,
		'hierarchical'    => true,
		'depth'           => 3,
		'show_count'      => false,
		'hide_empty'      => false,
	)); ?></p>
	<p><strong><?PHP _e( "Enter the page URL for this podcast (show page or otherwise)", 'hpm_podcasts' ); ?></strong><br />
	<label for="hpm-podcast-link"><?php _e( "URL:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link" name="hpm-podcast-link" value="<?PHP echo $hpm_podcast_link['page']; ?>" placeholder="http://example.com/law-blog-with-bob-loblaw/" style="width: 60%;" /></p>
	<p><strong><?PHP _e( "How many episodes do you want to show in the feed? (Enter a 0 to display all)", 'hpm_podcasts' ); ?></strong><br />
	<label for="hpm-podcast-limit"><?php _e( "Number of Eps:", 'hpm_podcasts' ); ?></label> <input type="number" id="hpm-podcast-limit" name="hpm-podcast-limit" value="<?PHP echo $hpm_podcast_link['limit']; ?>" placeholder="0" style="width: 30%;" /></p>
	<p>&nbsp;</p>
	<h3><?PHP _e( "iTunes Categories", 'hpm_podcasts' ); ?></h3>
	<p><?PHP _e( "iTunes allows you to select up to 3 category/subcategory combinations.  **The primary category is required, and is what will display in iTunes.**", 'hpm_podcasts' ); ?></p>
	<ul>
		<li>
			<label for="hpm-podcast-icat-prime"><?php _e( "Primary Category:", 'hpm_podcasts' ); ?></label>
			<select name="hpm-podcast-icat-prime" id="hpm-podcast-icat-prime">
				<option value=""<?PHP echo ( empty( $hpm_podcast_link['cat-prime'] ) ? " selected" : "" ); ?>><?PHP _e( "Select One", 'hpm_podcasts' ); ?></option>
<?php
	foreach ( $itunes_cats as $it_cat => $it_sub ) : ?>
				<option value="<?PHP echo $it_cat; ?>"<?PHP if ($it_cat == $hpm_podcast_link['cat-prime']) { echo " selected"; } ?>><?PHP _e( $it_cat, 'hpm_podcasts' ); ?></option>
<?PHP
		if ( !empty( $it_sub ) ) :
			foreach ( $it_sub as $sub ) :
				$cat_sub = $it_cat.'||'.$sub; ?>
					<option value="<?PHP echo $cat_sub; ?>"<?PHP if ($cat_sub == $hpm_podcast_link['cat-prime']) { echo " selected"; } ?>><?PHP _e( $it_cat." > ".$sub, 'hpm_podcasts' ); ?></option>
<?php
			endforeach;
		endif;
	endforeach;
?>
			</select>
		</li>
		<li>
			<label for="hpm-podcast-icat-second"><?php _e( "Secondary Category:", 'hpm_podcasts' ); ?></label>
			<select name="hpm-podcast-icat-second" id="hpm-podcast-icat-second">
				<option value=""<?PHP echo ( empty( $hpm_podcast_link['cat-second'] ) ? " selected" : "" ); ?>><?PHP _e( "Select One", 'hpm_podcasts' ); ?></option>
<?php
	foreach ( $itunes_cats as $it_cat => $it_sub ) : ?>
				<option value="<?PHP echo $it_cat; ?>"<?PHP if ($it_cat == $hpm_podcast_link['cat-second']) { echo " selected"; } ?>><?PHP _e( $it_cat, 'hpm_podcasts' ); ?></option>
<?PHP
		if ( !empty( $it_sub ) ) :
			foreach ( $it_sub as $sub ) :
				$cat_sub = $it_cat.'||'.$sub; ?>
					<option value="<?PHP echo $cat_sub; ?>"<?PHP if ($cat_sub == $hpm_podcast_link['cat-second']) { echo " selected"; } ?>><?PHP _e( $it_cat." > ".$sub, 'hpm_podcasts' ); ?></option>
<?php
			endforeach;
		endif;
	endforeach;
?>
			</select>
		</li>
		<li>
			<label for="hpm-podcast-icat-third"><?php _e( "Tertiary Category:", 'hpm_podcasts' ); ?></label>
			<select name="hpm-podcast-icat-third" id="hpm-podcast-icat-third">
				<option value=""<?PHP echo ( empty( $hpm_podcast_link['cat-third'] ) ? " selected" : "" ); ?>><?PHP _e( "Select One", 'hpm_podcasts' ); ?></option>
<?php
	foreach ( $itunes_cats as $it_cat => $it_sub ) : ?>
				<option value="<?PHP echo $it_cat; ?>"<?PHP if ($it_cat == $hpm_podcast_link['cat-third']) { echo " selected"; } ?>><?PHP _e( $it_cat, 'hpm_podcasts' ); ?></option>
<?PHP
		if ( !empty( $it_sub ) ) :
			foreach ( $it_sub as $sub ) :
				$cat_sub = $it_cat.'||'.$sub; ?>
					<option value="<?PHP echo $cat_sub; ?>"<?PHP if ($cat_sub == $hpm_podcast_link['cat-third']) { echo " selected"; } ?>><?PHP _e( $it_cat." > ".$sub, 'hpm_podcasts' ); ?></option>
<?php
			endforeach;
		endif;
	endforeach;
?>
			</select>
		</li>
	</ul>
	<p>&nbsp;</p>
	<h3><?PHP _e( "External Services", 'hpm_podcasts' ); ?></h3>
	<p><strong><?PHP _e( "Enter the page URL for this podcast on iTunes", 'hpm_podcasts' ); ?></strong><br />
	<label for="hpm-podcast-link-itunes"><?php _e( "URL:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-itunes" name="hpm-podcast-link-itunes" value="<?PHP echo $hpm_podcast_link['itunes']; ?>" placeholder="https://itunes.apple.com/us/podcast/law-blog-with-bob-loblaw/id123456789?mt=2" style="width: 60%;" /></p>
	<p><strong><?PHP _e( "Enter the page URL for this podcast on Google Play", 'hpm_podcasts' ); ?></strong><br />
	<label for="hpm-podcast-link-gplay"><?php _e( "URL:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-gplay" name="hpm-podcast-link-gplay" value="<?PHP echo $hpm_podcast_link['gplay']; ?>" placeholder="http://play.google.com/blahblahblah" style="width: 60%;" /></p>
    <h3><?PHP _e( "Analytics Tracking", 'hpm_podcasts' ); ?></h3>
    <p><strong><?PHP _e( "If you're using an analytics tracking service like Blubrry that appends a tracking link at the beginning of your media URLs, you can enter it here.", 'hpm_podcasts' );
    ?></strong><br />
        <label for="hpm-podcast-analytics"><?php _e( "URL:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-itunes" name="hpm-podcast-analytics" value="<?PHP echo $hpm_podcast_link['analytics']; ?>" placeholder="https://blubrry.com/law-blog/blahblah/" style="width: 60%;" /></p>
    <script>
        jQuery(document).ready(function($){
            $("#postexcerpt button .screen-reader-text").text("Toggle panel: iTunes Subtitle");
            $("#postexcerpt h2 span").text("iTunes Subtitle");
            $("#postexcerpt .inside p").remove();
            $("#postimagediv button .screen-reader-text").text("Toggle panel: Podcast Logo");
            $("#postimagediv h2 span").text("Podcast Logo");
            $("#postimagediv .inside").prepend('<p class="hide-in-no-js howto">Minimum logo resolution for iTunes etc. is 1400px x 1400px.  Maximum is 3000px x 3000px.</p>');
            $("#postdivrich").prepend('<h1>Podcast Description</h1>');
        });
    </script>
<?php }

function hpm_podcast_save_meta( $post_id, $post ) {
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
			'analytics' => ( isset( $_POST['hpm-podcast-analytics'] ) ? sanitize_text_field(
			        $_POST['hpm-podcast-analytics'] ) : '' ),
			'limit' => ( isset( $_POST['hpm-podcast-limit'] ) ? sanitize_text_field( $_POST['hpm-podcast-limit'] ) : 0 ),
			'cat-prime' => $_POST['hpm-podcast-icat-prime'],
			'cat-second' => $_POST['hpm-podcast-icat-second'],
			'cat-third' => $_POST['hpm-podcast-icat-third']
		);

		$exists_cat = metadata_exists( 'post', $post_id, 'hpm_pod_cat' );
		$exists_link = metadata_exists( 'post', $post_id, 'hpm_pod_link' );

		if ( $exists_cat ) :
			update_post_meta( $post_id, 'hpm_pod_cat', $hpm_podcast_cat );
		else :
			add_post_meta( $post_id, 'hpm_pod_cat', $hpm_podcast_cat, true );
		endif;
		if ( $exists_link ) :
			update_post_meta( $post_id, 'hpm_pod_link', $hpm_podcast_link );
		else :
			add_post_meta( $post_id, 'hpm_pod_link', $hpm_podcast_link, true );
		endif;
	endif;
}

add_action( 'load-post.php', 'hpm_podcast_description_setup' );
add_action( 'load-post-new.php', 'hpm_podcast_description_setup' );
function hpm_podcast_description_setup() {
	add_action( 'add_meta_boxes', 'hpm_podcast_add_description' );
	add_action( 'save_post', 'hpm_podcast_save_description', 10, 2 );
}

function hpm_podcast_add_description() {
	add_meta_box(
		'hpm-podcast-meta-class',
		esc_html__( 'Podcast Feed Information', 'hpm_podcasts' ),
		'hpm_podcast_description_box',
		'post',
		'advanced',
		'default'
	);
}

/*
 * Adding Post Type and Priority metadata boxes to the post editor
 */
function hpm_podcast_description_box( $object, $box ) {
	global $wpdb;
	$pods = get_option( 'hpm_podcasts' );
	wp_nonce_field( basename( __FILE__ ), 'hpm_podcast_class_nonce' );
	$hpm_pod_desc = get_post_meta( $object->ID, 'hpm_podcast_ep_meta', true );
	if ( empty( $hpm_pod_desc ) ) :
		$hpm_pod_desc = array( 'feed' => '', 'description' => '' );
		$checked = '';
	else :
		$checked = ' checked';
	endif;
	$hpm_pod_sg = get_post_meta( $object->ID, 'hpm_podcast_sg_file', true );
	$podcasts = $wpdb->get_results( "SELECT post_name,post_title FROM $wpdb->posts WHERE post_type = 'podcasts' && post_status = 'publish' ORDER BY post_name ASC", OBJECT );
	?>
	<p><input type="checkbox"<?php echo $checked; ?> value="1" name="hpm-podcast-ep" id="hpm-podcast-ep" /><label for="hpm-podcast-ep">Is this a podcast episode?</label></p>
	<p id="hpm-podcast-feeds"<?php echo ( $checked == '' ? ' class="hidden"' : '' ); ?>>
		<label for="hpm-podcast-ep-feed"><?php _e( "Podcast Feed:", 'hpm_podcasts' ); ?></label>
		<select name="hpm-podcast-ep-feed" id="hpm-podcast-ep-feed">
			<option value=""<?PHP echo ( empty( $hpm_pod_desc['feed'] ) ? " selected" : "" ); ?>><?PHP _e( "Select One", 'hpm_podcasts' ); ?></option>
<?php
	foreach ( $podcasts as $pod ) : ?>
			<option value="<?PHP echo $pod->post_name; ?>"<?PHP if ($pod->post_name == $hpm_pod_desc['feed']) { echo " selected"; } ?>><?PHP _e( $pod->post_title, 'hpm_podcasts' ); ?></option>
<?php
	endforeach; ?>
		</select>
	</p>
	<p><?PHP _e( "If this post is part of a podcast, and you would like something other than the content of this post to appear in iTunes, put your content here. <br /><br /><i><b>**NOTE**</b>: Any HTML formatting will have to be entered manually, so be careful.</i>", 'hpm_podcasts' ); ?></p>
	<p>
		<label for="hpm-podcast-description"><?php _e( "Alternate Podcast Description:", 'hpm_podcasts' ); ?></label><br />
		<textarea style="width: 100%; height: 250px;" name="hpm-podcast-description" id="hpm-podcast-description"><?php echo $hpm_pod_desc['description']; ?></textarea>
	</p>
<?php
    if ( !empty( $pods['upload'] ) ) : ?>
	<h3><?PHP _e( "StreamGuys URL", 'hpm_podcasts' ); ?></h3>
	<p><strong><?PHP _e( "If you want to upload your audio file manually, you can paste the URL here:", 'hpm_podcasts' ); ?></strong><br />
	<label for="hpm-podcast-sg-file"><?php _e( "URL:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-sg-file" name="hpm-podcast-sg-file" value="<?PHP echo $hpm_pod_sg; ?>" placeholder="https://ondemand.example.com/blah/blah.mp3" style="width: 75%;" /></p>
<?php
    endif;  ?>
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
			$('input#hpm-podcast-ep').on( 'change', function(){
				if( $(this).is(':checked') ) {
					$('#hpm-podcast-feeds').removeClass('hidden');
				} else {
					$('#hpm-podcast-feeds').addClass('hidden');
				}
			});
		});
	</script><?php
}

/*
 * Saving the Post Type and Priority metadata to the database
 */
function hpm_podcast_save_description( $post_id, $post ) {
	if ( empty( $_POST['hpm_podcast_class_nonce'] ) || !wp_verify_nonce( $_POST['hpm_podcast_class_nonce'], basename( __FILE__ ) ) ) :
		return $post_id;
	endif;
	$pods = get_option( 'hpm_podcasts' );

	$post_type = get_post_type_object( $post->post_type );

	if ( !current_user_can( $post_type->cap->edit_post, $post_id ) ) :
		return $post_id;
	endif;
	if ( empty( $_POST['hpm-podcast-ep'] ) || $_POST['hpm-podcast-ep'] == 0 ) :
		return $post_id;
	endif;
	$hpm_podcast_description = array(
		'feed' => $_POST['hpm-podcast-ep-feed'],
		'description' => balanceTags( $_POST['hpm-podcast-description'], true )
	);
	$sg_url = ( isset( $_POST['hpm-podcast-sg-file'] ) ? sanitize_text_field( $_POST['hpm-podcast-sg-file'] ) : '' );

	$hpm_pod_desc_exists = metadata_exists( 'post', $post_id, 'hpm_podcast_ep_meta' );
	if ( $hpm_pod_desc_exists ) :
		update_post_meta( $post_id, 'hpm_podcast_ep_meta', $hpm_podcast_description );
	else :
		add_post_meta( $post_id, 'hpm_podcast_ep_meta', $hpm_podcast_description, true );
	endif;

    if ( !empty( $pods['upload'] ) ) :
        $hpm_pod_sg_file = metadata_exists( 'post', $post_id, 'hpm_podcast_sg_file' );
        if ( $hpm_pod_sg_file ) :
            update_post_meta( $post_id, 'hpm_podcast_sg_file', $sg_url );
        else :
            add_post_meta( $post_id, 'hpm_podcast_sg_file', $sg_url, true );
        endif;
        if ( $post_type->labels->singular_name == 'Post' ) :
            wp_schedule_single_event( time() + 60, 'hpm_podcast_media', array( $post_id, $hpm_podcast_description['feed'] ) );
        else :
            wp_schedule_single_event( time() + 60, 'hpm_podcast_media', array( $post->post_parent, $hpm_podcast_description['feed'] ) );
        endif;
    endif;
}

function hpm_podcast_media_upload( $arg1, $arg2 ) {
    $pods = get_option( 'hpm_podcasts' );
    $path = HPM_PODCAST_PLUGIN_DIR .'phpseclib';
	$dir = wp_upload_dir();
	$save = $dir['basedir'];
	set_include_path(get_include_path() . PATH_SEPARATOR . $path);
	include( 'Net/SFTP.php' );
	$sftp = new Net_SFTP( $pods['upload']['host'] );
	if ( !$sftp->login( $pods['upload']['username'], $pods['upload']['password'] ) ) :
	    exit('Login Failed');
	endif;
	if ( empty( $arg2 ) ) :
		return false;
	else :
		if ( !$sftp->chdir( $arg2 ) ) :
			$sftp->mkdir( $arg2 );
			$sftp->chdir( $arg2 );
		endif;
	endif;
	if ( empty( $arg1 ) ) :
		return false;
	else :
        $media = get_attached_media( 'audio', $arg1 );
	    if ( empty( $media ) ) :
		    $media = get_attached_media( 'video', $arg1 );
        endif;
		$med = reset( $media );
		$url = wp_get_attachment_url( $med->ID );
		$parse = parse_url( $url );
		$path = pathinfo( $parse['path'] );
		$local = $save.$path['basename'];
		file_put_contents( $local, fopen( $url, 'r' ) );
		if ( $sftp->put( $path['basename'], $local, NET_SFTP_LOCAL_FILE ) ) :
			unlink( $local );
			$sg_url = $pods['upload']['upload_url'].$arg2.'/'.$path['basename'];
			$hpm_pod_sg_file = metadata_exists( 'post', $arg1, 'hpm_podcast_sg_file' );
			if ( $hpm_pod_sg_file ) :
				update_post_meta( $arg1, 'hpm_podcast_sg_file', $sg_url );
			else :
				add_post_meta( $arg1, 'hpm_podcast_sg_file', $sg_url, true );
			endif;
			wp_mail( $pods['upload']['email'], 'Podcast Audio Uploaded Successfully', "Audio for this article was uploaded successfully:\n\n".get_site_url()."/wp-admin/post.php?post=".$arg1."&action=edit\n\n".$sg_url );
		else :
			wp_mail( $pods['upload']['email'], 'Error Uploading Podcast Audio', "There was an error uploading audio for this article: ".get_site_url()."/wp-admin/post.php?post=".$arg1."&action=edit" );
		endif;
	endif;
}
add_action( 'hpm_podcast_media', 'hpm_podcast_media_upload', 10, 3 );



/*
 * Generate the podcast feeds and save them as flat files on S3
 */
function hpmv2_podcast_generate() {
	$pods = get_option( 'hpm_podcasts' );
    if ( !empty( $pods['https'] ) ) :
        $protocol = 'https://';
        $_SERVER['HTTPS'] = 'on';
    else :
        $protocol = 'http://';
    endif;
	global $wpdb;
	if ( !empty( $pods['s3'] ) ) :
        require HPM_PODCAST_PLUGIN_DIR . 'aws/aws-autoloader.php';
        $client = new Aws\S3\S3Client([
	        'version' => 'latest',
	        'region'  => $pods['s3']['region'],
	        'credentials' => [
                'key' => AWS_ACCESS_KEY_ID,
                'secret' => AWS_SECRET_ACCESS_KEY
	        ]
        ]);
    endif;
    $error = '';
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
	while ( $podcasts->have_posts() ) :
		$podcasts->the_post();
		$pod_id = get_the_ID();
		$catslug = get_post_meta( $pod_id, 'hpm_pod_cat', true );
		$podlink = get_post_meta( $pod_id, 'hpm_pod_link', true );
		ob_start();
		echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
		do_action( 'rss_tag_pre', 'rss2' ); ?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:atom="http://www.w3.org/2005/Atom" <?php do_action( 'rss2_ns' ); ?>>
<?php
		$main_image = wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' );
		$categories = array(
			'prime' => explode( '||', $podlink['cat-prime'] ),
			'second' => explode( '||', $podlink['cat-second'] ),
			'third' => explode( '||', $podlink['cat-third'] ),
		);
		$podcast_title = $podcasts->post->post_name; ?>
	<channel>
		<title><?php the_title_rss(); ?></title>
		<atom:link href="<?php echo get_the_permalink(); ?>" rel="self" type="application/rss+xml" />
		<link><?php echo $podlink['page']; ?></link>
		<description><![CDATA[<?php the_content_feed(); ?>]]></description>
		<language><?php bloginfo_rss( 'language' ); ?></language>
		<copyright>All Rights Reserved</copyright>
		<webMaster><?php echo $pods['owner']['name']."(".$pods['owner']['email'].")"; ?></webMaster>
		<ttl><?php echo $frequency; ?></ttl>
		<pubDate><?php echo date('r'); ?></pubDate>
		<itunes:summary><![CDATA[<?php the_content_feed(); ?>]]></itunes:summary>
		<itunes:owner>
			<itunes:name><?php echo $pods['owner']['name']; ?></itunes:name>
			<itunes:email><?php echo $pods['owner']['email']; ?></itunes:email>
		</itunes:owner>
		<itunes:keywords><?php echo strip_tags( get_the_tag_list( '', ',', '' ) ); ?></itunes:keywords>
		<itunes:subtitle><?PHP echo get_the_excerpt();  ?></itunes:subtitle>
		<itunes:author><?php
            if ( function_exists( 'coauthors' ) ) :
                coauthors(', ', ', ', '', '', true);
            else :
                echo get_the_author();
            endif; ?></itunes:author>
		<itunes:explicit>no</itunes:explicit>
<?PHP
		foreach ( $categories as $podcat ) :
			if ( count( $podcat ) == 2 ) : ?>
		<itunes:category text="<?PHP echo htmlentities( $podcat[0] ); ?>">
			<itunes:category text="<?PHP echo htmlentities( $podcat[1] ); ?>" />
		</itunes:category>
<?PHP
			else :
				if ( !empty( $podcat[0] ) ) :
?>		<itunes:category text="<?PHP echo htmlentities( $podcat[0] ); ?>" />
<?PHP
				endif;
			endif;
		endforeach;
		if ( !empty( $main_image ) ) :
?>		<itunes:image href="<?PHP echo $main_image[0]; ?>" />
		<image>
			<url><?php echo $main_image[0]; ?></url>
			<title><?PHP the_title_rss(); ?></title>
		</image>
<?php
		endif;
		do_action( 'rss2_head');
		$perpage = "";
		if ( !empty( $podlink['limit'] ) && $podlink['limit'] != 0 && is_numeric( $podlink['limit'] ) ) :
			$perpage = " LIMIT 0,".$podlink['limit'];
		endif;
		$podeps = $wpdb->get_results(
            "SELECT SQL_CALC_FOUND_ROWS $wpdb->posts.*
                FROM $wpdb->posts,wp_term_relationships
                WHERE $wpdb->posts.ID = $wpdb->term_relationships.object_id
                    AND $wpdb->term_relationships.term_taxonomy_id IN ($catslug)
                    AND $wpdb->posts.post_type = 'post'
                    AND $wpdb->posts.post_status = 'publish'
                    AND $wpdb->posts.ID IN (
                        SELECT DISTINCT post_parent
                        FROM $wpdb->posts
                        WHERE post_parent > 0
                            AND post_type = 'attachment'
                            AND (
                                post_mime_type = 'audio/mpeg'
                                OR post_mime_type = 'video/mp4'
                                OR post_mime_type = 'audio/mp4'
                            )
                    )
                GROUP BY $wpdb->posts.ID
                ORDER BY $wpdb->posts.post_date DESC
                $perpage",
            OBJECT
        );
        if ( !empty( $podeps ) ) :
			foreach ( $podeps as $pod ) :
				$epid = $pod->ID;
				$media = $wpdb->get_results(
                    "SELECT ID,post_title
                        FROM $wpdb->posts
                        WHERE post_type = 'attachment'
                            AND (
                                post_mime_type = 'audio/mpeg'
                                OR post_mime_type = 'video/mp4'
                                OR post_mime_type = 'audio/mp4'
                            )
                            AND post_parent = $epid",
                    OBJECT
                );
				$m = reset( $media );
				$url = wp_get_attachment_url( $m->ID );
				$attr = get_post_meta( $m->ID, '_wp_attachment_metadata', true );
				$pod_image = wp_get_attachment_image_src( get_post_thumbnail_id( $epid ), 'full' );
				$tags = wp_get_post_tags( $epid );
				$tag_array = array();
				foreach ( $tags as $t ) :
					$tag_array[] = $t->name;
				endforeach;
				$pod_desc = get_post_meta( $epid, 'hpm_podcast_ep_meta', true );
				$sg_file = get_post_meta( $epid, 'hpm_podcast_sg_file', true );
				if ( empty( $sg_file ) ) :
					$media_file = $protocol.( !empty( $podlink['blubrry'] ) ? "media.blubrry.com/" .$podlink['blubrry']."/" : '' ).$url;
				else :
					$media_file = $sg_file;
				endif;
				$content = "<p>".wp_trim_words( strip_shortcodes( $pod->post_content ), 75, '... <a href="'.get_the_permalink( $epid ).'">Read More</a>' )."</p>"; ?>
		<item>
			<title><?php echo apply_filters('the_title_rss', $pod->post_title ); ?></title>
			<link><?php echo get_the_permalink( $epid ); ?></link>
			<pubDate><?php echo mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true, $epid ), false); ?></pubDate>
			<guid isPermaLink="true"><?php echo get_the_permalink( $epid ); ?></guid>
			<description><![CDATA[<?php echo ( !empty( $pod_desc['description'] ) ? $pod_desc['description'] : $content ); ?>]]></description>
			<itunes:keywords><?php echo implode( ',', $tag_array ); ?></itunes:keywords>
			<itunes:summary><![CDATA[<?php echo ( !empty( $pod_desc['description'] ) ? $pod_desc['description'] : $content ); ?>]]></itunes:summary>
<?php
				if ( !empty( $pod_image ) ) :
?>			<itunes:image href="<?PHP echo $pod_image[0]; ?>"/>
<?php
				endif;
				if ( in_array( 'explicit', $tag_array ) ) : ?>
			<itunes:explicit>yes</itunes:explicit>
<?php
				else : ?>
			<itunes:explicit>no</itunes:explicit>
<?php
				endif; ?>
			<enclosure url="<?PHP echo $media_file; ?>" length="<?PHP echo $attr['filesize']; ?>" type="<?php echo $attr['mime_type']; ?>" />
			<itunes:duration><?PHP echo $attr['length_formatted']; ?></itunes:duration>
<?php do_action( 'rss2_item' ); ?>
		</item>
<?php
			endforeach;
		endif; ?>
	</channel>
</rss>
<?php
		$getContent = ob_get_contents();
		ob_end_clean();
		$getContent_mini = trim( preg_replace( '/\s+/', ' ', $getContent ) );
		if ( !empty( $pods['s3'] ) ) :
            try {
                $result = $client->putObject(array(
                    'Bucket' => $pods['s3']['bucket'],
                    'Key' => $pods['s3']['folder'].'/'.$podcast_title.'.xml',
                    'Body' => $getContent_mini,
                    'ACL' => 'public-read',
                    'ContentType' => 'application/rss+xml'
                ));
            } catch (S3Exception $e) {
                $error .= $podcast_title."\n".$e->getMessage()."\n\n";
            } catch (AwsException $e) {
                $error .= $podcast_title . "\n" . $e->getAwsRequestId() . "\n" . $e->getAwsErrorType() . "\n" . $e->getAwsErrorCode() . "\n\n";
            }
            sleep(30);
		else :
            $uploads = wp_upload_dir();
		    if ( !file_exists( $uploads['basedir'].'/hpm-podcasts' ) ) :
                mkdir( $uploads['basedir'].'/hpm-podcasts' );
            endif;
			$file_write = file_put_contents( $uploads['basedir'].'/hpm-podcasts/'.$podcast_title.'.xml',
                $getContent_mini );
			if ( $file_write === FALSE ) :
				$error .= $podcast_title."\nThere was an error writing your cache file into the Uploads directory.  Please check the error log.\n\n";
            endif;
        endif;
	endwhile;
	if ( !empty( $error ) ) :
		wp_mail( $pods['upload']['email'], 'Error Generating Podcast Feeds', "There was an error generating the podcast feeds.  See below:\n".$error );
	endif;
}

// create custom plugin settings menu
add_action('admin_menu', 'hpm_podcast_create_menu');

function hpm_podcast_create_menu() {

	add_submenu_page( 'edit.php?post_type=podcasts', 'HPM Podcast Settings', 'Settings', 'edit_hpm_podcasts', 'hpm-podcast-settings', 'hpm_podcast_settings_page' );

	//call register settings function
	add_action( 'admin_init', 'register_hpm_podcast_settings' );
}


function register_hpm_podcast_settings() {
	//register our settings
	register_setting( 'hpm-podcast-settings-group', 'hpm_podcasts' );
}
/*
 *
 * @TODO: Finish writing out options for uploads and update other functions accordingly
 * @TODO: Figure out how to consolidate the S3 functions into one?
 *
 */
function hpm_podcast_settings_page() {
	$pods = get_option( 'hpm_podcasts' ); ?>
    <div class="wrap">
        <h1><?php _e('Podcast Administration', 'hpm_podcasts' ); ?></h1>
        <p><?php _e('Hello, and thank you for installing our plugin.  The following sections will walk you through all of the data we need to gather to properly set up your podcast feeds.', 'hpm_podcasts' ); ?></p>
        <p>&nbsp;</p>
        <form method="post" action="options.php">
			<?php settings_fields( 'hpm-podcast-settings-group' ); ?>
			<?php do_settings_sections( 'hpm-podcast-settings-group' ); ?>
            <h2><?php _e('Ownership Information', 'hpm_podcasts' ); ?></h2>
            <p><?php _e('iTunes and other podcasting directories ask for you to give a name and email address of the "owner"
                of the podcast, which can be a single person or an organization.', 'hpm_podcasts' ); ?></p>
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
            <p>&nbsp;</p>
            <h2><?php _e('User Roles', 'hpm_podcasts' ); ?></h2>
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
                        <?php foreach (get_editable_roles() as $role_name => $role_info) : ?>
                            <option value="<?php echo $role_name; ?>"<?php echo ( in_array( $role_name, $pods['roles'] ) ? " selected" : '' ); ?>><?php echo $role_name; ?></option>
                        <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p>&nbsp;</p>
            <h2><?php _e('Background Tasks', 'hpm_podcasts' ); ?></h2>
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
            <p>&nbsp;</p>
            <h2><?php _e('Upload Options', 'hpm_podcasts' ); ?></h2>
            <p><?php _e('By default, this plugin will store the flat XML files in a folder in your "uploads" directory, and the media files will be stored with the rest of your attachments.  However, if you want to store your files elsewhere, select one of the options below.', 'hpm_podcasts' );
		        ?></p>
            <p><?php _e('**NOTE**: If you go the S3 route, it is recommended that you create a new IAM user in Amazon Web Services that only has access to your S3 buckets.  Please refer to Amazon\'s documentation for how to manage your user settings.', 'hpm_podcasts' );
		        ?></p>
            <ul>
                <li><a href="http://docs.aws.amazon.com/AmazonS3/latest/dev/walkthrough1.html" target="_blank">Amazon IAM User Documentation</a></li>
            </ul>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="hpm_podcasts[upload-flats]"><?php _e('Flat XML File Upload?', 'hpm_podcasts' );
					        ?></label></th>
                    <td>
                        <select name="hpm_podcasts[upload-flats]" class="regular-text" id="hpm-flats">
                            <option value=""></option>
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
                            <option value=""></option>
                            <option value="s3" <?php selected( $pods['upload-media'], 's3', TRUE); ?>>Amazon
                                S3</option>
                            <option value="ftp" <?php selected( $pods['upload-flats'], 'ftp', TRUE); ?>>FTP</option>
                            <option value="sftp" <?php selected( $pods['upload-flats'], 'sftp', TRUE);
                            ?>>SFTP</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="hpm_podcasts[email]"><?php _e('Email for Error Notifications', 'hpm_podcasts' );
				            ?></label></th>
                    <td><input type="email" name="hpm_podcasts[email]" value="<?php echo $pods['email']; ?>" class="regular-text" placeholder="bob@loblaw.com" /></td>
                </tr>
            </table>
            <div id="hpm-s3">
                <p>&nbsp;</p>
                <h2><?php _e('Amazon S3 Credentials', 'hpm_podcasts' ); ?></h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][s3][key]"><?php _e('Amazon S3 Access Key', 'hpm_podcasts' );
                                ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][s3][key]" value="<?php echo $pods['credentials']['s3']['key']; ?>"
                                   class="regular-text" placeholder="us-west-2" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][s3][secret]"><?php _e('Amazon S3 Secret Key', 'hpm_podcasts' );
                                ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][s3][secret]" value="<?php echo $pods['credentials']['s3']['secret']; ?>"
                                   class="regular-text" placeholder="us-west-2" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][s3][region]"><?php _e('Amazon S3 Region', 'hpm_podcasts' );
                                ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][s3][region]" value="<?php echo $pods['credentials']['s3']['region']; ?>"
                                   class="regular-text" placeholder="us-west-2" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][s3][bucket]"><?php _e('Amazon S3 Bucket', 'hpm_podcasts' );
                                ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][s3][bucket]" value="<?php echo $pods['credentials']['s3']['bucket']; ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][s3][folder]"><?php _e('Amazon S3 Folder Path', 'hpm_podcasts' );
                                ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][s3][folder]" value="<?php echo $pods['credentials']['s3']['folder']; ?>" class="regular-text" placeholder="blah/blah" /></td>
                    </tr>
                </table>
            </div>
            <div id="hpm-ftp">
                <p>&nbsp;</p>
                <h2><?php _e('FTP Credentials', 'hpm_podcasts' ); ?></h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][ftp][host]"><?php _e('FTP Host', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][ftp][host]" value="<?php echo $pods['credentials']['ftp']['host']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][ftp][url]"><?php _e('FTP Public URL', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][ftp][url]" value="<?php echo $pods['credentials']['ftp']['url']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][ftp][username]"><?php _e('FTP Username', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][ftp][username]" value="<?php echo
                            $pods['credentials']['ftp']['username']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][ftp][password]"><?php _e('FTP Host', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="password" name="hpm_podcasts[credentials][ftp][password]" value="<?php echo $pods['credentials']['ftp']['password']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][ftp][port]"><?php _e('FTP Server Port', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="number" name="hpm_podcasts[credentials][ftp][port]" value="<?php echo $pods['credentials']['ftp']['port']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                </table>
            </div>
            <div id="hpm-sftp">
                <p>&nbsp;</p>
                <h2><?php _e('SFTP Credentials', 'hpm_podcasts' ); ?></h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][sftp][host]"><?php _e('SFTP Host', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][sftp][host]" value="<?php echo $pods['credentials']['sftp']['host']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][sftp][url]"><?php _e('SFTP Public URL', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][sftp][url]" value="<?php echo $pods['credentials']['sftp']['url']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][sftp][username]"><?php _e('SFTP Username', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="text" name="hpm_podcasts[credentials][sftp][username]" value="<?php echo
				            $pods['credentials']['sftp']['username']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][sftp][password]"><?php _e('SFTP Host', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="password" name="hpm_podcasts[credentials][sftp][password]" value="<?php echo $pods['credentials']['sftp']['password']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="hpm_podcasts[credentials][sftp][port]"><?php _e('SFTP Server Port', 'hpm_podcasts' ); ?></label></th>
                        <td><input type="number" name="hpm_podcasts[credentials][sftp][port]" value="<?php echo $pods['credentials']['sftp']['port']; ?>" class="regular-text" placeholder="" /></td>
                    </tr>
                </table>
            </div>
            <p>&nbsp;</p>
            <h2><?php _e('Force HTTPS?', 'hpm_podcasts' ); ?></h2>
            <p><?php _e('Apple Podcasts/iTunes, as well as other podcasting directories, are starting to favor or even require HTTPS for your feeds and media enclosures.  If you\'re already using HTTPS, or if you want to force your feeds and the associated background processes to use HTTPS, then check the box below.', 'hpm_podcasts' );
            ?></p>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="hpm_podcasts[https]"><?php _e('Force HTTPS in Feed?', 'hpm_podcasts' );
                            ?></label></th>
                    <td><input type="checkbox" name="hpm_podcasts[https]" value="force-https" class="regular-text" <?php echo ( !empty( $pods['https'] ) ? " selected" : '' ); ?> /></td>
                </tr>
            </table>
			<?php submit_button(); ?>
        </form>
    </div>
<?php
}
add_filter('single_template', 'hpm_podcasts_single_template');

function hpm_podcasts_single_template( $single ) {
	global $wp_query, $post;

	/* Checks for single template by post type */
	if ( $post->post_type == "podcasts" && file_exists(HPM_PODCAST_PLUGIN_DIR . 'templates/single.php') ) :
        return HPM_PODCAST_PLUGIN_DIR . 'templates/single.php';
	endif;
	return $single;
}

add_filter('archive_template', 'hpm_podcasts_archive_template');

function hpm_podcasts_archive_template( $archive_template ) {
    global $post;

    if ( is_post_type_archive ( 'podcasts' ) && file_exists( HPM_PODCAST_PLUGIN_DIR . 'templates/archive.php' ) ) :
        $archive_template = HPM_PODCAST_PLUGIN_DIR . 'templates/archive.php';
    endif;
    return $archive_template;
}