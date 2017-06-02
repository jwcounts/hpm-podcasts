<?php
/**
 * Create and configure a custom post type for our podcast feeds
 */
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

/**
 * Add capabilities to the selected roles (default is admin only)
 */
add_action( 'admin_init', 'hpm_podcast_add_role_caps', 999 );
function hpm_podcast_add_role_caps() {
	$pods = get_option( 'hpm_podcasts' );
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

/**
 * Set up metadata for this feed: iTunes categories, episode archive link, iTunes link, Google Play link, number of
 * episodes in the feed, feed-specific analytics, etc.
 *
 * @param $object
 * @param $box
 */
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
			$hpm_podcast_link = array('page' => '', 'limit' => 0, 'itunes' => '', 'gplay' => '', 'stitcher' => '', 'analytics' => '', 'categories' => array( 'first' => '', 'second' => '', 'third' => '') );
		else :
			if ( empty( $hpm_podcast_link['categories'] ) ) :
				$hpm_podcast_link['categories'] = array(
					'first' => $hpm_podcast_link['cat-prime'],
					'second' => $hpm_podcast_link['cat-second'],
					'third' => $hpm_podcast_link['cat-third']
				);
				unset( $hpm_podcast_link['cat-prime'] );
				unset( $hpm_podcast_link['cat-second'] );
				unset( $hpm_podcast_link['cat-third'] );
			endif;
		endif;
	else :
		$hpm_podcast_link = array('page' => '', 'limit' => 0, 'itunes' => '', 'gplay' => '', 'stitcher' => '', 'analytics' => '', 'categories' => array( 'first' => '', 'second' => '', 'third' => '') );
	endif; ?>
<h3><?PHP _e( "Category and Page", 'hpm_podcasts' ); ?></h3>
<p><?PHP _e( "Select the post category for this podcast:", 'hpm_podcasts' );
	wp_dropdown_categories(array(
		'show_option_all' => __("Select One"),
		'taxonomy'		=> 'category',
		'name'			=> 'hpm-podcast-cat',
		'orderby'		 => 'name',
		'selected'		=> $hpm_podcast_cat,
		'hierarchical'	=> true,
		'depth'		   => 3,
		'show_count'	  => false,
		'hide_empty'	  => false,
	)); ?></p>
<p><strong><?PHP _e( "Enter the page URL for this podcast (show page or otherwise)", 'hpm_podcasts' ); ?></strong><br />
<label for="hpm-podcast-link"><?php _e( "URL:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link" name="hpm-podcast-link" value="<?PHP echo $hpm_podcast_link['page']; ?>" placeholder="http://example.com/law-blog-with-bob-loblaw/" style="width: 60%;" /></p>
<p><strong><?PHP _e( "How many episodes do you want to show in the feed? (Enter a 0 to display all)", 'hpm_podcasts' ); ?></strong><br />
<label for="hpm-podcast-limit"><?php _e( "Number of Eps:", 'hpm_podcasts' ); ?></label> <input type="number" id="hpm-podcast-limit" name="hpm-podcast-limit" value="<?PHP echo $hpm_podcast_link['limit']; ?>" placeholder="0" style="width: 30%;" /></p>
<p>&nbsp;</p>
<h3><?PHP _e( "iTunes Categories", 'hpm_podcasts' ); ?></h3>
<p><?PHP _e( "iTunes allows you to select up to 3 category/subcategory combinations.  **The primary category is required, and is what will display in iTunes.**", 'hpm_podcasts' ); ?></p>
<ul>
<?php
	foreach ( $hpm_podcast_link['categories'] as $pos => $cat ) : ?>
	<li>
		<label for="hpm-podcast-icat-<?php echo $pos; ?>"><?php _e( ucwords( $pos )." Category:", 'hpm_podcasts' );
		?></label>
		<select name="hpm-podcast-icat-<?php echo $pos; ?>" id="hpm-podcast-icat-<?php echo $pos; ?>">
			<option value=""<?PHP selected( $cat, '', TRUE ); ?>><?PHP _e( "Select One", 'hpm_podcasts' ); ?></option>
<?php
		foreach ( $itunes_cats as $it_cat => $it_sub ) : ?>
			<option value="<?PHP echo $it_cat; ?>"<?PHP selected( $cat, $it_cat, TRUE ); ?>><?PHP _e( $it_cat, 'hpm_podcasts' ); ?></option>
<?PHP
			if ( !empty( $it_sub ) ) :
				foreach ( $it_sub as $sub ) :
				$cat_sub = $it_cat.'||'.$sub; ?>
				<option value="<?PHP echo $cat_sub; ?>"<?PHP selected( $cat, $cat_sub, TRUE ); ?>><?PHP _e( $it_cat." > ".$sub, 'hpm_podcasts' ); ?></option>
<?php
				endforeach;
			endif;
		endforeach;
?>
		</select>
	</li>
<?php
	endforeach; ?>
</ul>
<p>&nbsp;</p>
<h3><?PHP _e( "External Services", 'hpm_podcasts' ); ?></h3>
<p><label for="hpm-podcast-link-itunes"><?php _e( "iTunes:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-itunes" name="hpm-podcast-link-itunes" value="<?PHP echo $hpm_podcast_link['itunes']; ?>" placeholder="https://itunes.apple.com/us/podcast/law-blog-with-bob-loblaw/id123456789?mt=2" style="width: 60%;" /></p>
<p><label for="hpm-podcast-link-gplay"><?php _e( "Google Play:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-gplay" name="hpm-podcast-link-gplay" value="<?PHP echo $hpm_podcast_link['gplay']; ?>" placeholder="http://play.google.com/blahblahblah" style="width: 60%;" /></p>
<p><label for="hpm-podcast-link-stitcher"><?php _e( "Stitcher:", 'hpm_podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-stitcher" name="hpm-podcast-link-stitcher" value="<?PHP echo $hpm_podcast_link['stitcher']; ?>" placeholder="http://stitcher.com/blah" style="width: 60%;" /></p>
<p>&nbsp;</p>
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
<?php 
}

/**
 * Save the above metadata in postmeta
 *
 * @param $post_id
 * @param $post
 *
 * @return mixed
 */
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
			'stitcher' => ( isset( $_POST['hpm-podcast-link-stitcher'] ) ? sanitize_text_field( $_POST['hpm-podcast-link-stitcher'] ) : '' ),
			'analytics' => ( isset( $_POST['hpm-podcast-analytics'] ) ? sanitize_text_field( $_POST['hpm-podcast-analytics'] ) : '' ),
			'limit' => ( isset( $_POST['hpm-podcast-limit'] ) ? sanitize_text_field( $_POST['hpm-podcast-limit'] ) : 0 ),
			'categories' => array(
				'first' => $_POST['hpm-podcast-icat-first'],
				'second' => $_POST['hpm-podcast-icat-second'],
				'third' => $_POST['hpm-podcast-icat-third']
			)
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