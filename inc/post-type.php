<h3><?PHP _e( "Featured Podcast", 'hpm-podcasts' ); ?></h3>
<p><strong><?PHP _e( "Is this podcast being produced internally, or featured from an external source?", 'hpm-podcasts' ); ?></strong><br />
	<label for="hpm-podcast-prod"><?php _e( "Production:", 'hpm-podcasts' ); ?></label> <select name="hpm-podcast-prod" id="hpm-podcast-prod">
		<option value="internal"<?PHP selected( $hpm_podcast_prod, 'internal', TRUE ); ?>><?PHP _e( "Internal", 'hpm-podcasts' ); ?></option>
		<option value="external"<?PHP selected( $hpm_podcast_prod, 'external', TRUE ); ?>><?PHP _e( "External", 'hpm-podcasts' ); ?></option>
	</select>
</p>
<p><strong><?PHP _e( "If externally produced/hosted, enter the RSS feed link below", 'hpm-podcasts' );
?></strong><br />
<label for="hpm-podcast-rss-override"><?php _e( "URL:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-rss-override" name="hpm-podcast-rss-override" value="<?PHP echo $hpm_podcast_link['rss-override']; ?>" placeholder="http://example.com/law-blog-with-bob-loblaw/" style="width: 60%;" /></p>
<p>&nbsp;</p>
<?php
	$itunes_cats = [
		'Arts' => [
			'Design',
			'Fashion & Beauty',
			'Food',
			'Literature',
			'Performing Arts',
			'Visual Arts'
		],
		'Business' => [
			'Business News',
			'Careers',
			'Investing',
			'Management & Marketing',
			'Shopping'
		],
		'Comedy' => [],
		'Education' => [
			'Educational Technology',
			'Higher Education',
			'K-12',
			'Language Courses',
			'Training'
		],
		'Games & Hobbies' => [
			'Automotive',
			'Aviation',
			'Hobbies',
			'Other Games',
			'Video Games'
		],
		'Government & Organizations' => [
			'Local',
			'National',
			'Non-Profit',
			'Regional'
		],
		'Health' => [
			'Alternative Health',
			'Fitness & Nutrition',
			'Self-Help',
			'Sexuality'
		],
		'Kids & Family' => [],
		'Music' => [],
		'News & Politics' => [],
		'Religion & Spirituality' => [
			'Buddhism',
			'Christianity',
			'Hinduism',
			'Islam',
			'Judaism',
			'Other',
			'Spirituality'
		],
		'Science & Medicine' => [
			'Medicine',
			'Natural Sciences',
			'Social Sciences'
		],
		'Society & Culture' => [
			'History',
			'Personal Journals',
			'Philosophy',
			'Places & Travel'
		],
		'Sports & Recreation' => [
			'Amateur',
			'College & High School',
			'Outdoor',
			'Professional'
		],
		'Technology' => [
			'Gadgets',
			'Tech News',
			'Podcasting',
			'Software How-To'
		],
		'TV & Film' => []
	]; ?>
<h3><?PHP _e( "Category and Page", 'hpm-podcasts' ); ?></h3>
<p><?PHP _e( "Select the post category for this podcast:", 'hpm-podcasts' );
	wp_dropdown_categories([
		'show_option_all' => __("Select One"),
		'taxonomy'		=> 'category',
		'name'			=> 'hpm-podcast-cat',
		'orderby'		 => 'name',
		'selected'		=> $hpm_podcast_cat,
		'hierarchical'	=> true,
		'depth'		   => 3,
		'show_count'	  => false,
		'hide_empty'	  => false,
	]); ?></p>
<p><strong><?PHP _e( "Enter the page URL for this podcast (show page or otherwise)", 'hpm-podcasts' );
?></strong><br />
<label for="hpm-podcast-link"><?php _e( "URL:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-link" name="hpm-podcast-link" value="<?PHP echo $hpm_podcast_link['page']; ?>" placeholder="http://example.com/law-blog-with-bob-loblaw/" style="width: 60%;" /></p>

<p><strong><?PHP _e( "How many episodes do you want to show in the feed? (Enter a 0 to display all)", 'hpm-podcasts' ); ?></strong><br />
<label for="hpm-podcast-limit"><?php _e( "Number of Eps:", 'hpm-podcasts' ); ?></label> <input type="number" id="hpm-podcast-limit" name="hpm-podcast-limit" value="<?PHP echo $hpm_podcast_link['limit']; ?>" placeholder="0" style="width: 30%;" /></p>

<p><strong><?PHP _e( "Is it an episodic podcast or a serialized one?", 'hpm-podcasts' ); ?></strong><br />
	<label for="hpm-podcast-type"><?php _e( "Podcast Type:", 'hpm-podcasts' ); ?></label> <select name="hpm-podcast-type" id="hpm-podcast-type">
		<option value="episodic"<?PHP selected( $hpm_podcast_link['type'], 'episodic', TRUE ); ?>><?PHP _e( "Episodic", 'hpm-podcasts' ); ?></option>
		<option value="serial"<?PHP selected( $hpm_podcast_link['type'], 'serial', TRUE ); ?>><?PHP _e( "Serialized", 'hpm-podcasts' ); ?></option>
	</select>
</p>
<p>&nbsp;</p>

<h3><?PHP _e( "iTunes Categories", 'hpm-podcasts' ); ?></h3>
<p><?PHP _e( "iTunes allows you to select up to 3 category/subcategory combinations.  **The primary category is required, and is what will display in iTunes.**", 'hpm-podcasts' ); ?></p>
<ul>
<?php
	foreach ( $hpm_podcast_link['categories'] as $pos => $cat ) : ?>
	<li>
		<label for="hpm-podcast-icat-<?php echo $pos; ?>"><?php _e( ucwords( $pos )." Category:", 'hpm-podcasts' );
		?></label>
		<select name="hpm-podcast-icat-<?php echo $pos; ?>" id="hpm-podcast-icat-<?php echo $pos; ?>">
			<option value=""<?PHP selected( $cat, '', TRUE ); ?>><?PHP _e( "Select One", 'hpm-podcasts' ); ?></option>
<?php
		foreach ( $itunes_cats as $it_cat => $it_sub ) : ?>
			<option value="<?PHP echo $it_cat; ?>"<?PHP selected( $cat, $it_cat, TRUE ); ?>><?PHP _e( $it_cat, 'hpm-podcasts' ); ?></option>
<?PHP
			if ( !empty( $it_sub ) ) :
				foreach ( $it_sub as $sub ) :
				$cat_sub = $it_cat.'||'.$sub; ?>
				<option value="<?PHP echo $cat_sub; ?>"<?PHP selected( $cat, $cat_sub, TRUE ); ?>><?PHP _e( $it_cat." > ".$sub, 'hpm-podcasts' ); ?></option>
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
<h3><?PHP _e( "External Services", 'hpm-podcasts' ); ?></h3>
<p><label for="hpm-podcast-link-itunes"><?php _e( "iTunes:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-itunes" name="hpm-podcast-link-itunes" value="<?PHP echo $hpm_podcast_link['itunes']; ?>" placeholder="https://itunes.apple.com/us/podcast/law-blog-with-bob-loblaw/id123456789?mt=2" style="width: 60%;" /></p>
<p><label for="hpm-podcast-link-gplay"><?php _e( "Google Play:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-gplay" name="hpm-podcast-link-gplay" value="<?PHP echo $hpm_podcast_link['gplay']; ?>" placeholder="http://play.google.com/blahblahblah" style="width: 60%;" /></p>
<p><label for="hpm-podcast-link-stitcher"><?php _e( "Stitcher:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-stitcher" name="hpm-podcast-link-stitcher" value="<?PHP echo $hpm_podcast_link['stitcher']; ?>" placeholder="http://stitcher.com/blah" style="width: 60%;" /></p>
<p><label for="hpm-podcast-link-radiopublic"><?php _e( "RadioPublic:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-radiopublic" name="hpm-podcast-link-radiopublic" value="<?PHP echo $hpm_podcast_link['radiopublic']; ?>" placeholder="http://radiopublic.com/blah" style="width: 60%;" /></p>
<p><label for="hpm-podcast-link-pcast"><?php _e( "Pocket Casts:", 'hpm-podcasts' ); ?></label> <input type="text" id="hpm-podcast-link-pcast" name="hpm-podcast-link-pcast" value="<?PHP echo $hpm_podcast_link['pcast']; ?>" placeholder="https://pca.st/blah" style="width: 60%;" /></p>
<script>
	jQuery(document).ready(function($){
		var excerpt = $('#postexcerpt');
		var imageDiv = $('#postimagediv');
		excerpt.find("button .screen-reader-text").text("Toggle panel: iTunes Subtitle");
		excerpt.find("h2 span").text("iTunes Subtitle");
		excerpt.find(".inside p").remove();
		imageDiv.find("button .screen-reader-text").text("Toggle panel: Podcast Logo");
		imageDiv.find("h2 span").text("Podcast Logo");
		imageDiv.find(".inside").prepend('<p class="hide-in-no-js howto">Minimum logo resolution for iTunes etc. is 1400px x 1400px.  Maximum is 3000px x 3000px.</p>');
		$("#postdivrich").prepend('<h1>Podcast Description</h1>');
	});
</script>