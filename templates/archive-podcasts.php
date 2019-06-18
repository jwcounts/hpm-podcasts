<?php
/**
 * The template for displaying the Podcasts archive
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package HPM_Podcasts
 */

get_header(); ?>
	<section id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<h1 class="page-title">Podcasts</h1>
				<?php
					the_archive_description( '<div class="taxonomy-description">', '</div>' );
				?>
			</header><!-- .page-header -->
			<section id="search-results" class="page-content">
			<?php
			// Start the loop.
			while ( have_posts() ) : the_post();
				$pod_link = get_post_meta( get_the_ID(), 'hpm_pod_link', true ); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<?php
						if ( has_post_thumbnail() ) : ?>
					<div class="thumbnail-wrap" style="background-image: url(<?php the_post_thumbnail_url(); ?>)">
						<a class="post-thumbnail" href="<?php echo $pod_link['page']; ?>" aria-hidden="true"></a>
					</div>
					<div class="search-result-content">
					<?php
						else : ?>
					<div class="search-result-content-full">
					<?php
						endif; ?>
						<header class="entry-header">
							<h3>Podcast</h3>
							<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', $pod_link['page'] ), '</a></h2>' ); ?>
							<div class="screen-reader-text"><?PHP
								if ( function_exists( 'coauthors_posts_links' ) ) :
									coauthors_posts_links( ' / ', ' / ', '<address class="vcard author">', '</address>',	true );
								else :
									$byline = sprintf(
									/* translators: %s: post author */
										__( 'by %s', 'hpm-podcasts' ),
										'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . get_the_author() . '</a></span>'
									);

									// Finally, let's write all of this to the page.
									echo '<span class="byline"> ' . $byline . '</span>';
								endif;
								?> </div>
						</header><!-- .entry-header -->
						<div class="entry-summary">
							<p><?php echo get_the_excerpt(); ?></p>
							<ul>
								<li><a href="<?php echo $pod_link['page']; ?>">Episode Archive</a></li>
						<?php
							if ( !empty( $pod_link['rss-override'] ) ) : ?>
								<li><a href="<?php echo $pod_link['rss-override']; ?>">RSS Feed</a></li>
						<?php
							else : ?>
								<li><a href="<?php the_permalink(); ?>">RSS Feed</a></li>
						<?php
							endif; ?>
							</ul>
							<div class="podcast-episode-info">
								<h2>Available on:</h2>
								<?php echo HPM_Podcasts::show_social( get_the_ID(), false, '' ); ?>
						</div><!-- .entry-summary -->

						<?php if ( 'post' == get_post_type() ) : ?>

							<footer class="entry-footer">
								<?php 
									$tags_list = get_the_tag_list( '', _x( ' ', 'Used between list items, there is a space after the comma.', 'hpmv2' ) );
									if ( $tags_list ) :
										printf( '<p><span class="tags-links"><span class="screen-reader-text">%1$s </span>%2$s</span></p>',
											_x( 'Tags', 'Used before tag names.', 'hpmv2' ),
											$tags_list
										);
									endif;
									edit_post_link( __( 'Edit', 'hpmv2' ), '<span class="edit-link">', '</span>' ); ?>
							</footer><!-- .entry-footer -->

						<?php else : ?>

							<?php edit_post_link( __( 'Edit', 'hpmv2' ), '<footer class="entry-footer"><span class="edit-link">', '</span></footer><!-- .entry-footer -->' ); ?>

						<?php endif; ?>
					</div>
				</article><!-- #post-## -->
			<?php
			endwhile;

			// Previous/next page navigation.
			the_posts_pagination( [
				'prev_text' => __( '&lt;', 'hpm-podcasts' ),
				'next_text' => __( '&gt;', 'hpm-podcasts' ),
				'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'hpm-podcasts' ) . ' </span>',
			 ] );

		// If no content, include the "No posts found" template.
		else :
			get_template_part( 'content', 'none' );

		endif;
		?>
			</section>
			<aside class="column-right">
			   <?php get_template_part( 'sidebar', 'none' ); ?>
			</aside>
		</main><!-- .site-main -->
	</section><!-- .content-area -->

<?php get_footer(); ?>