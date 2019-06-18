<?php
/**
 * The template for displaying archive pages
 *
 * Used to display archive-type pages if nothing more specific matches a query.
 * For example, puts together date-based pages if no date.php file exists.
 *
 * If you'd like to further customize these archive views, you may create a
 * new template file for each one. For example, tag.php (Tag archives),
 * category.php (Category archives), author.php (Author archives), etc.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */

get_header(); ?>
	<section id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

		<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<h1 class="page-title">Local Shows</h1>
				<?php
					the_archive_description( '<div class="taxonomy-description">', '</div>' );
				?>
			</header><!-- .page-header -->
			<section id="search-results">
			<?php
			// Start the loop.
			while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<?php
						if ( has_post_thumbnail() ) : ?>
					<div class="thumbnail-wrap" style="background-image: url(<?php the_post_thumbnail_url(); ?>)">
						<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true"></a>
					</div>
					<div class="search-result-content">
					<?php
						else : ?>
					<div class="search-result-content-full">
					<?php
						endif; ?>
						<header class="entry-header">
						<h3>Show</h3>
						<?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
						</header><!-- .entry-header -->
						<div class="entry-summary">
							<p>
						<?php
							$social = get_post_meta( get_the_ID(), 'hpm_show_social', true );
							$show = get_post_meta( get_the_ID(), 'hpm_show_meta', true );
							if ( !empty( $show['times'] ) && !empty( $show['hosts'] ) ) :
								echo $show['times']." with ".$show['hosts'];
							elseif ( empty( $show['times'] ) && !empty( $show['hosts'] ) ) :
								echo "With ".$show['hosts'];
							elseif ( !empty( $show['times'] ) && empty( $show['hosts'] ) ) :
								echo $show['times'];
							endif;
							?>
						
							</p>
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
			the_posts_pagination( array(
				'prev_text' => __( '&lt;', 'hpmv2' ),
				'next_text' => __( '&gt;', 'hpmv2' ),
				'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'hpmv2' ) . ' </span>',
			) );

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