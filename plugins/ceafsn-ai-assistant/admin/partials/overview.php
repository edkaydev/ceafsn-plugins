<?php
/**
 * Admin partial: Overview dashboard for CE-AFSN AI Assistant.
 *
 * Layout: WordPress admin menu on the left, a main canvas in the middle, and a
 * narrower right rail for setup guidance.
 *
 * Variables in scope (set by CEAFSN_AI_Admin::render_overview_page()):
 *   @var CEAFSN_AI_Provider|null $ai_provider     Active chat provider
 *   @var CEAFSN_AI_Provider|null $embed_provider  Active embeddings provider
 *   @var int                     $chunk_count     Rows in the knowledge base
 *   @var int                     $embedded_count  Rows that carry a vector
 *   @var string                  $last_indexed    ISO datetime, or ''
 *   @var array<string,int>       $breakdown       Rows grouped by provider
 *   @var array<string,array{message:string,type:string}> $notices Saved notices
 *   @var int                     $unembedded      Chunks with no vector yet
 *   @var int                     $mine            Rows matching the live provider
 *   @var int                     $foreign         Rows from another provider
 *   @var int                     $shortcode_pages Published items using the shortcode
 *
 * Every figure is counted from stored rows or from published content. Nothing
 * is inferred, and no setup step is marked complete unless a count proves it.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

$ai_no_chat   = ( null === $ai_provider );
$ai_no_embed  = ( null === $embed_provider );
$ai_empty     = ( 0 === $chunk_count );
$ai_mismatch  = ( ! $ai_empty && $foreign > 0 );
$ai_no_vector = ( ! $ai_empty && 0 === $embedded_count );

// A problem blocks answering outright; a shortfall does not. The distinction
// decides whether a card is a red alert or a gold note, so it is made once here
// rather than re-derived at each call site below.
$ai_blocking = array();
if ( $ai_no_chat ) {
	$ai_blocking[] = __( 'No chat provider is configured, so no question can be answered.', 'ceafsn-ai' );
}
if ( $ai_no_embed ) {
	$ai_blocking[] = __( 'No embeddings provider is configured, so nothing can be looked up.', 'ceafsn-ai' );
}
if ( $ai_empty ) {
	$ai_blocking[] = __( 'The knowledge base is empty, so there is nothing to look up.', 'ceafsn-ai' );
}
if ( $ai_mismatch ) {
	$ai_blocking[] = sprintf(
		/* translators: %s: number of chunks. */
		_n( '%s chunk was built with a different embeddings provider and will never match a new question.', '%s chunks were built with a different embeddings provider and will never match a new question.', $foreign, 'ceafsn-ai' ),
		number_format_i18n( $foreign )
	);
}
if ( $ai_no_vector ) {
	$ai_blocking[] = __( 'Every indexed chunk is missing its vector, so none of them can be found.', 'ceafsn-ai' );
}

$ai_ready        = ( 0 === count( $ai_blocking ) );
$ai_block_count  = count( $ai_blocking );
$ai_partial      = ( $ai_ready && $unembedded > 0 );

// The status pill text is built here so the markup below can emit it in one
// line: the pill reads "Ready" only when nothing blocks answering.
$ai_pill_text = $ai_ready
	? __( 'Ready', 'ceafsn-ai' )
	: sprintf(
		/* translators: %s: number of blocking issues. */
		_n( '%s issue', '%s issues', $ai_block_count, 'ceafsn-ai' ),
		number_format_i18n( $ai_block_count )
	);

$ai_settings_url  = admin_url( 'admin.php?page=' . CEAFSN_AI_Admin::PAGE_SETTINGS );
$ai_providers_url = add_query_arg( 'tab', 'providers', $ai_settings_url );
$ai_display_url   = add_query_arg( 'tab', 'display', $ai_settings_url );
$ai_index_anchor  = '#ceafsn-ai-index';

// Setup progress. A step is only complete when the count behind it says so.
$ai_steps = array(
	array(
		'label' => __( 'Provider configured', 'ceafsn-ai' ),
		'meta'  => $ai_no_chat || $ai_no_embed
			? __( 'An API key is needed for both answering and looking up', 'ceafsn-ai' )
			: sprintf(
				/* translators: %s: name of the active provider. */
				__( 'Answering with %s', 'ceafsn-ai' ),
				$ai_provider->name()
			),
		'count' => ( $ai_no_chat || $ai_no_embed ) ? 0 : 1,
	),
	array(
		'label' => __( 'Knowledge base built', 'ceafsn-ai' ),
		'meta'  => $ai_empty
			? __( 'Nothing indexed yet', 'ceafsn-ai' )
			: sprintf(
				/* translators: 1: indexed chunks, 2: chunks with vectors. */
				__( '%1$s chunks, %2$s with vectors', 'ceafsn-ai' ),
				number_format_i18n( $chunk_count ),
				number_format_i18n( $embedded_count )
			),
		// The step is only proved once every stored chunk carries a vector;
		// a shortfall leaves it current so the gap stays visible.
		'count' => ( $ai_empty || $unembedded > 0 ) ? 0 : 1,
	),
	array(
		'label' => __( 'Shown on a page', 'ceafsn-ai' ),
		'meta'  => 0 === $shortcode_pages
			? __( 'The shortcode is not on any published page', 'ceafsn-ai' )
			: sprintf(
				/* translators: %s: number of published items using the shortcode. */
				_n( 'Live on %s published item', 'Live on %s published items', $shortcode_pages, 'ceafsn-ai' ),
				number_format_i18n( $shortcode_pages )
			),
		'count' => $shortcode_pages,
	),
);

$ai_current_step = null;
foreach ( $ai_steps as $ai_index => $ai_step ) {
	if ( $ai_step['count'] > 0 ) {
		$ai_steps[ $ai_index ]['state'] = 'done';
	} elseif ( null === $ai_current_step ) {
		$ai_steps[ $ai_index ]['state'] = 'current';
		$ai_current_step               = $ai_index;
	} else {
		$ai_steps[ $ai_index ]['state'] = 'todo';
	}
}
?>

<div class="wrap ceafsn-ai-wrap">

	<a class="ceafsn-sr" href="#ceafsn-ai-main"><?php esc_html_e( 'Skip to dashboard content', 'ceafsn-ai' ); ?></a>

	<?php if ( ! empty( $notices ) ) : ?>
		<div class="ceafsn-alerts">
			<?php foreach ( $notices as $ai_notice ) : ?>
				<div class="ceafsn-alert ceafsn-alert--<?php echo esc_attr( 'warning' === $ai_notice['type'] ? 'warn' : ( 'error' === $ai_notice['type'] ? 'danger' : 'ok' ) ); ?>" role="status">
					<span class="ceafsn-alert__icon" aria-hidden="true"><?php echo 'ok' === $ai_notice['type'] ? '&#10003;' : '!'; ?></span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__text"><?php echo esc_html( $ai_notice['message'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="ceafsn-app" id="ceafsn-ai-main">

		<div class="ceafsn-app__main">

			<?php if ( ! $ai_ready ) : ?>
				<div class="ceafsn-alerts">
					<?php foreach ( $ai_blocking as $ai_issue ) : ?>
						<div class="ceafsn-alert ceafsn-alert--danger">
							<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__text"><?php echo esc_html( $ai_issue ); ?></p>
							</div>
						</div>
					<?php endforeach; ?>
					<div class="ceafsn-alert ceafsn-alert--warn">
						<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
						<div class="ceafsn-alert__body">
							<p class="ceafsn-alert__title"><?php esc_html_e( 'The assistant answers only when all three steps are done', 'ceafsn-ai' ); ?></p>
							<p class="ceafsn-alert__text">
								<?php esc_html_e( 'Add an API key, build the index, then put the shortcode on a page. Until then every question is refused rather than answered from a half-built index.', 'ceafsn-ai' ); ?>
								<a class="ceafsn-alert__action" href="<?php echo esc_url( $ai_providers_url ); ?>"><?php esc_html_e( 'Open provider settings', 'ceafsn-ai' ); ?></a>
							</p>
						</div>
					</div>
				</div>
			<?php elseif ( $ai_partial ) : ?>
				<div class="ceafsn-alerts">
					<div class="ceafsn-alert ceafsn-alert--warn">
						<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
						<div class="ceafsn-alert__body">
							<p class="ceafsn-alert__title"><?php esc_html_e( 'Some indexed chunks are missing their vector', 'ceafsn-ai' ); ?></p>
							<p class="ceafsn-alert__text">
								<?php
								printf(
									/* translators: 1: chunks without a vector, 2: total chunks. */
									esc_html__( '%1$s of %2$s chunks cannot be found until they are embedded. Run indexing again to fill the gaps.', 'ceafsn-ai' ),
									esc_html( number_format_i18n( $unembedded ) ),
									esc_html( number_format_i18n( $chunk_count ) )
								);
								?>
								<a class="ceafsn-alert__action" href="<?php echo esc_url( $ai_index_anchor ); ?>"><?php esc_html_e( 'Run indexing', 'ceafsn-ai' ); ?></a>
							</p>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<section class="ceafsn-hero">
				<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-ai' ); ?></p>
				<h1 class="ceafsn-hero__title"><?php esc_html_e( 'AI Assistant', 'ceafsn-ai' ); ?></h1>
				<p class="ceafsn-hero__text">
					<?php esc_html_e( 'A question from a visitor is embedded, matched against the indexed CE-AFSN content, and answered only from what those chunks actually say.', 'ceafsn-ai' ); ?>
				</p>
				<div class="ceafsn-hero__actions">
					<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $ai_index_anchor ); ?>">
						<?php esc_html_e( 'Run indexing', 'ceafsn-ai' ); ?>
					</a>
					<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $ai_providers_url ); ?>">
						<?php esc_html_e( 'Providers', 'ceafsn-ai' ); ?>
					</a>
				</div>
				<p class="ceafsn-hero__meta">
					<span>
						<?php
						printf(
							/* translators: %s: number of indexed chunks. */
							wp_kses_post( __( '<strong>%s</strong> chunks', 'ceafsn-ai' ) ),
							esc_html( number_format_i18n( $chunk_count ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: number of chunks with vectors. */
							wp_kses_post( __( '<strong>%s</strong> embedded', 'ceafsn-ai' ) ),
							esc_html( number_format_i18n( $embedded_count ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: name of the chat provider, or Not configured. */
							wp_kses_post( __( '<strong>%s</strong> answering', 'ceafsn-ai' ) ),
							esc_html( $ai_no_chat ? __( 'Not configured', 'ceafsn-ai' ) : $ai_provider->name() )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: number of published items using the shortcode. */
							wp_kses_post( __( '<strong>%s</strong> on the site', 'ceafsn-ai' ) ),
							esc_html( number_format_i18n( $shortcode_pages ) )
						);
						?>
					</span>
				</p>
			</section>

			<div class="ceafsn-kpi-row">
				<div class="ceafsn-kpi ceafsn-kpi--accent">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot <?php echo $ai_empty ? 'ceafsn-dot--grey' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
						<?php esc_html_e( 'Chunks', 'ceafsn-ai' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $chunk_count ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						printf(
							/* translators: 1: chunks with vectors, 2: chunks without. */
							esc_html__( '%1$s with a vector · %2$s without', 'ceafsn-ai' ),
							esc_html( number_format_i18n( $embedded_count ) ),
							esc_html( number_format_i18n( $unembedded ) )
						);
						?>
					</span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--gold" aria-hidden="true"></span>
						<?php esc_html_e( 'Last indexed', 'ceafsn-ai' ); ?>
					</span>
					<span class="ceafsn-kpi__value">
						<?php
						if ( '' !== $last_indexed ) {
							echo esc_html( date_i18n( get_option( 'date_format' ), (int) strtotime( $last_indexed ) ) );
						} else {
							esc_html_e( 'Never', 'ceafsn-ai' );
						}
						?>
					</span>
					<span class="ceafsn-kpi__meta">
						<?php
						echo $ai_no_embed
							? esc_html__( 'No embeddings provider yet', 'ceafsn-ai' )
							: esc_html(
								sprintf(
									/* translators: %s: name of the embeddings provider. */
									__( 'Vectors from %s', 'ceafsn-ai' ),
									$embed_provider->name()
								)
							);
						?>
					</span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--grey" aria-hidden="true"></span>
						<?php esc_html_e( 'On the site', 'ceafsn-ai' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $shortcode_pages ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php esc_html_e( 'Published pages and posts with the shortcode', 'ceafsn-ai' ); ?>
					</span>
				</div>

				<div class="ceafsn-kpi<?php echo $ai_block_count > 0 ? ' ceafsn-kpi--alert' : ''; ?>">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot <?php echo $ai_block_count > 0 ? 'ceafsn-dot--danger' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
						<?php esc_html_e( 'Blocking issues', 'ceafsn-ai' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $ai_block_count ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						if ( $ai_block_count > 0 ) {
							esc_html_e( 'Questions are refused until these are resolved', 'ceafsn-ai' );
						} else {
							esc_html_e( 'The assistant can answer a question now', 'ceafsn-ai' );
						}
						?>
					</span>
				</div>
			</div>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the assistant', 'ceafsn-ai' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Nothing appears to visitors until this shortcode is on a published page.', 'ceafsn-ai' ); ?></p>
					</div>
					<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $ai_display_url ); ?>">
						<?php esc_html_e( 'Attributes', 'ceafsn-ai' ); ?>
					</a>
				</div>
				<div class="ceafsn-card__body">
					<div class="ceafsn-embed">
						<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste into the page that should offer the assistant', 'ceafsn-ai' ); ?></p>
						<code class="ceafsn-embed__code">[ceafsn_ai_assistant]</code>
					</div>
					<p class="ceafsn-card__hint">
						<?php esc_html_e( 'It takes optional placeholder_en and placeholder_pt attributes — open Display in settings for the full list.', 'ceafsn-ai' ); ?>
					</p>
				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Setup progress', 'ceafsn-ai' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Counted from your configuration and your published content. A step is only complete when the numbers prove it.', 'ceafsn-ai' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">
					<ol class="ceafsn-stepper">
						<?php foreach ( $ai_steps as $ai_index => $ai_step ) : ?>
							<li class="ceafsn-step is-<?php echo esc_attr( $ai_step['state'] ); ?>">
								<span class="ceafsn-step__dot" aria-hidden="true">
									<?php
									if ( 'done' === $ai_step['state'] ) {
										echo '&#10003;';
									} else {
										echo esc_html( (string) ( $ai_index + 1 ) );
									}
									?>
								</span>
								<span class="ceafsn-step__label"><?php echo esc_html( $ai_step['label'] ); ?></span>
								<span class="ceafsn-step__meta"><?php echo esc_html( $ai_step['meta'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
					<p class="ceafsn-card__hint">
						<?php esc_html_e( 'Current step:', 'ceafsn-ai' ); ?>
						<strong>
							<?php
							echo esc_html(
								null !== $ai_current_step
									? $ai_steps[ $ai_current_step ]['label']
									: __( 'All steps complete', 'ceafsn-ai' )
							);
							?>
						</strong>
					</p>
				</div>
			</section>

			<section class="ceafsn-card" id="ceafsn-ai-index">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Knowledge base', 'ceafsn-ai' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Reads the published CE-AFSN records plus WordPress pages and posts, splits them into chunks, and stores a vector for each one.', 'ceafsn-ai' ); ?></p>
					</div>
				<span class="ceafsn-pill <?php echo $ai_ready ? 'ceafsn-pill--green' : ''; ?>"><?php echo esc_html( $ai_pill_text ); ?></span>
				</div>
				<div class="ceafsn-card__body">

					<table class="ceafsn-embed__table">
						<caption class="ceafsn-sr"><?php esc_html_e( 'Index status', 'ceafsn-ai' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Measure', 'ceafsn-ai' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Value', 'ceafsn-ai' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php esc_html_e( 'Total chunks', 'ceafsn-ai' ); ?></td>
								<td><span class="ceafsn-badge ceafsn-badge--<?php echo $ai_empty ? 'empty' : 'ready'; ?>"><?php echo esc_html( number_format_i18n( $chunk_count ) ); ?></span></td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Chunks with vectors', 'ceafsn-ai' ); ?></td>
								<td>
									<span class="ceafsn-badge ceafsn-badge--<?php echo $ai_no_vector ? 'empty' : 'ready'; ?>"><?php echo esc_html( number_format_i18n( $embedded_count ) ); ?></span>
									<?php if ( $ai_mismatch ) : ?>
										<span class="ceafsn-ai-flag">
											<?php
											printf(
												/* translators: %s: number of chunks from another provider. */
												esc_html__( '%s of them belong to a different provider', 'ceafsn-ai' ),
												esc_html( number_format_i18n( $foreign ) )
											);
											?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Last indexed', 'ceafsn-ai' ); ?></td>
								<td>
									<?php
									if ( '' !== $last_indexed ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) strtotime( $last_indexed ) ) );
									} else {
										esc_html_e( 'Never', 'ceafsn-ai' );
									}
									?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Embeddings via', 'ceafsn-ai' ); ?></td>
								<td>
									<?php
									echo $ai_no_embed
										? '<span class="ceafsn-badge ceafsn-badge--warn">' . esc_html__( 'Not configured', 'ceafsn-ai' ) . '</span>'
										: '<span class="ceafsn-badge ceafsn-badge--ready">' . esc_html( $embed_provider->name() ) . '</span>';
									?>
								</td>
							</tr>
							<tr>
								<td><?php esc_html_e( 'Answering via', 'ceafsn-ai' ); ?></td>
								<td>
									<?php
									echo $ai_no_chat
										? '<span class="ceafsn-badge ceafsn-badge--warn">' . esc_html__( 'Not configured', 'ceafsn-ai' ) . '</span>'
										: '<span class="ceafsn-badge ceafsn-badge--ready">' . esc_html( $ai_provider->name() ) . '</span>';
									?>
								</td>
							</tr>
						</tbody>
					</table>

				</div>

				<div class="ceafsn-card__body">
					<form class="ceafsn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ceafsn_ai_run_index" />
						<?php wp_nonce_field( 'ceafsn_ai_index' ); ?>

						<div class="ceafsn-check">
							<input type="checkbox" id="ceafsn-ai-full-rebuild" name="ceafsn_ai_full_rebuild" value="1" />
							<div class="ceafsn-check__body">
								<label class="ceafsn-check__title" for="ceafsn-ai-full-rebuild">
									<?php esc_html_e( 'Full rebuild (delete the existing index first)', 'ceafsn-ai' ); ?>
								</label>
								<span class="ceafsn-check__hint">
									<?php esc_html_e( 'Use this after switching embeddings provider, or when a chunk was edited and did not pick up the change.', 'ceafsn-ai' ); ?>
								</span>
							</div>
						</div>

						<div class="ceafsn-form__actions">
							<button type="submit" class="ceafsn-btn ceafsn-btn--primary" <?php disabled( $ai_no_embed ); ?>>
								<?php esc_html_e( 'Run indexing now', 'ceafsn-ai' ); ?>
							</button>
						</div>

						<p class="ceafsn-card__hint">
							<?php esc_html_e( 'Each content item costs one API call, so the first run can take a few minutes. A run that reaches its time budget stops cleanly and can be continued by pressing the button again.', 'ceafsn-ai' ); ?>
						</p>
						<?php if ( $ai_no_embed ) : ?>
							<p class="ceafsn-card__hint">
								<?php esc_html_e( 'Indexing is disabled until an OpenAI or Gemini API key is added.', 'ceafsn-ai' ); ?>
							</p>
						<?php endif; ?>
					</form>
				</div>

				<?php if ( ! $ai_empty ) : ?>
					<div class="ceafsn-card__body">
						<form class="ceafsn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
							onsubmit="return confirm('<?php echo esc_js( __( 'Delete the entire AI index? This cannot be undone.', 'ceafsn-ai' ) ); ?>');">
							<input type="hidden" name="action" value="ceafsn_ai_clear_index" />
							<?php wp_nonce_field( 'ceafsn_ai_clear' ); ?>
							<div class="ceafsn-form__actions">
								<button type="submit" class="ceafsn-btn ceafsn-btn--danger">
									<?php esc_html_e( 'Clear index', 'ceafsn-ai' ); ?>
								</button>
							</div>
						</form>
					</div>
				<?php endif; ?>
			</section>

		</div><!-- .ceafsn-app__main -->

		<aside class="ceafsn-app__rail">

			<div class="ceafsn-cta">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Get started', 'ceafsn-ai' ); ?></p>
				<h2 class="ceafsn-cta__title">
					<?php
					echo esc_html(
						$ai_no_chat || $ai_no_embed
							? __( 'Add an API key', 'ceafsn-ai' )
							: __( 'Review providers', 'ceafsn-ai' )
					);
					?>
				</h2>
				<p class="ceafsn-cta__text">
					<?php
					echo esc_html(
						$ai_no_chat || $ai_no_embed
							? __( 'One key answers the question, a second one builds the vectors. OpenAI and Gemini can do both on a single key.', 'ceafsn-ai' )
							: __( 'Change which model answers, or pin a newer one when a vendor retires the default.', 'ceafsn-ai' )
					);
					?>
				</p>
				<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( $ai_providers_url ); ?>">
					<?php echo esc_html( $ai_no_chat || $ai_no_embed ? __( '+ Add an API key', 'ceafsn-ai' ) : __( 'Open providers', 'ceafsn-ai' ) ); ?>
				</a>
			</div>

			<div class="ceafsn-cta" id="ceafsn-ai-help">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'How it answers', 'ceafsn-ai' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Rules the assistant keeps', 'ceafsn-ai' ); ?></h2>
				<ul class="ceafsn-ticks">
					<li><?php esc_html_e( 'Only indexed content is used — never the open web.', 'ceafsn-ai' ); ?></li>
					<li><?php esc_html_e( 'A question with no close match is refused, not guessed.', 'ceafsn-ai' ); ?></li>
					<li><?php esc_html_e( 'Sources are shown with every answer so it can be checked.', 'ceafsn-ai' ); ?></li>
					<li><?php esc_html_e( 'API keys are never printed to visitors or to page source.', 'ceafsn-ai' ); ?></li>
					<li><?php esc_html_e( 'Twenty questions per visitor per minute, then a short wait.', 'ceafsn-ai' ); ?></li>
				</ul>
			</div>

			<div class="ceafsn-cta">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Placement', 'ceafsn-ai' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Put it on a page', 'ceafsn-ai' ); ?></h2>
				<p class="ceafsn-cta__text">
					<?php esc_html_e( 'Drop the shortcode into any page or post. The assistant loads its script only on pages that carry it.', 'ceafsn-ai' ); ?>
				</p>
				<a class="ceafsn-btn ceafsn-btn--ghost ceafsn-btn--block" href="<?php echo esc_url( $ai_display_url ); ?>">
					<?php esc_html_e( 'Open Display', 'ceafsn-ai' ); ?>
				</a>
			</div>

		</aside>

	</div><!-- .ceafsn-app -->

	<a class="ceafsn-help" href="#ceafsn-ai-help" aria-label="<?php esc_attr_e( 'Jump to how the assistant answers', 'ceafsn-ai' ); ?>">?</a>

</div><!-- .ceafsn-ai-wrap -->
