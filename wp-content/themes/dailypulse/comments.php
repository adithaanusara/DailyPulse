<?php
/**
 * Comments. Only shown on stories that have comments open (or already have some).
 *
 * @package DailyPulse
 */

if ( post_password_required() ) {
	return;
}

$dailypulse_count = (int) get_comments_number();
?>
<section id="comments" class="container container--narrow comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments__title">
			<?php
			/* translators: %s: number of comments. */
			echo esc_html( sprintf( _n( '%s comment', '%s comments', $dailypulse_count, 'dailypulse' ), number_format_i18n( $dailypulse_count ) ) );
			?>
		</h2>

		<ol class="comments__list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 44,
				)
			);
			?>
		</ol>

		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && $dailypulse_count ) : ?>
		<p class="comments__closed"><?php esc_html_e( 'Comments are closed.', 'dailypulse' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply_before' => '<h2 id="reply-title" class="comments__title comment-reply-title">',
			'title_reply_after'  => '</h2>',
			'class_submit'       => 'btn',
		)
	);
	?>
</section>
