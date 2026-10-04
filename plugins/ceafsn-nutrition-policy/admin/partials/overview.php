<?php
/**
 * Admin partial: Overview dashboard (the sidebar landing page).
 *
 * Layout: WordPress admin menu on the left, a main canvas in the middle, and a
 * narrower right rail for calls to action and publishing guidance.
 *
 * Variables in scope (set by CEAFSN_NP_Admin::page_overview()):
 *   @var array $items            All policy rows (up to 200)
 *   @var int   $published        Count of rows with status = published
 *   @var array $missing_pdf      Rows with no PDF attached
 *   @var array $shared_documents One entry per attachment used by >1 record,
 *                                each with attachment_id, filename, records
 *   @var array $topics           Distinct topics in use
 *
 * Every figure is counted from stored rows. Nothing is estimated, and no step
 * in the stepper is marked complete unless the underlying count proves it.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

$np_total    = count( $items );
$np_drafts   = max( 0, $np_total - $published );
$np_shared   = count( $shared_documents );
$np_attached = $np_total - count( $missing_pdf );

$np_policies_url = admin_url( 'admin.php?page=' . CEAFSN_NP_Admin::PAGE_POLICIES );
$np_add_url      = add_query_arg( 'action', 'add', $np_policies_url );
$np_settings_url = admin_url( 'admin.php?page=' . CEAFSN_NP_Admin::PAGE_SETTINGS );
$np_export_url   = add_query_arg( 'tab', 'export', $np_settings_url );

// Rows that need a human decision, flattened for the attention table.
$np_attention = array();
foreach ( $shared_documents as $np_doc ) {
	foreach ( $np_doc['records'] as $np_record ) {
		$np_attention[] = array(
			'policy_id' => (int) $np_record->policy_id,
			'title'     => (string) $np_record->title,
			'status'    => (string) $np_record->status,
			'reason'    => 'shared',
			'detail'    => (string) ( '' !== $np_doc['filename'] ? $np_doc['filename'] : __( 'unnamed document', 'ceafsn-np' ) ),
		);
	}
}
foreach ( $missing_pdf as $np_record ) {
	$np_attention[] = array(
		'policy_id' => (int) $np_record->policy_id,
		'title'     => (string) $np_record->title,
		'status'    => (string) $np_record->status,
		'reason'    => 'missing',
		'detail'    => __( 'No PDF attached — cannot be published', 'ceafsn-np' ),
	);
}
$np_attention_count = count( $np_attention );

// Publish workflow. The first step that is not yet met is the current one.
$np_steps = array(
	array(
		'label' => __( 'Record added', 'ceafsn-np' ),
		'meta'  => sprintf( /* translators: %s: number of records. */ __( '%s in total', 'ceafsn-np' ), number_format_i18n( $np_total ) ),
		'count' => $np_total,
	),
	array(
		'label' => __( 'PDF attached', 'ceafsn-np' ),
		'meta'  => sprintf( /* translators: 1: attached count, 2: total. */ __( '%1$s of %2$s', 'ceafsn-np' ), number_format_i18n( $np_attached ), number_format_i18n( $np_total ) ),
		'count' => $np_attached,
	),
	array(
		'label' => __( 'Published', 'ceafsn-np' ),
		'meta'  => sprintf( /* translators: %s: number of published records. */ __( '%s live on the page', 'ceafsn-np' ), number_format_i18n( $published ) ),
		'count' => $published,
	),
);

// A step is done when its count is non-zero. The first step still at zero is
// the current one; anything after it is todo.
$np_current_step = null;
foreach ( $np_steps as $np_index => $np_step ) {
	if ( $np_step['count'] > 0 ) {
		$np_steps[ $np_index ]['state'] = 'done';
	} elseif ( null === $np_current_step ) {
		$np_steps[ $np_index ]['state'] = 'current';
		$np_current_step                = $np_index;
	} else {
		$np_steps[ $np_index ]['state'] = 'todo';
	}
}
?>

<div class="wrap ceafsn-np-wrap">

	<a class="ceafsn-sr" href="#ceafsn-np-main"><?php esc_html_e( 'Skip to dashboard content', 'ceafsn-np' ); ?></a>

	<?php if ( ! empty( $_GET['ceafsn_np_error'] ) ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
				<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title"><?php esc_html_e( 'That change was not saved', 'ceafsn-np' ); ?></p>
					<p class="ceafsn-alert__text"><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_np_error'] ) ) ) ); ?></p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="ceafsn-app" id="ceafsn-np-main">

		<div class="ceafsn-app__main">

			<?php if ( 0 === $np_total ) : ?>

				<div class="ceafsn-alerts">
					<div class="ceafsn-alert ceafsn-alert--warn">
						<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
						<div class="ceafsn-alert__body">
							<p class="ceafsn-alert__title"><?php esc_html_e( 'This repository is empty', 'ceafsn-np' ); ?></p>
							<p class="ceafsn-alert__text">
								<?php esc_html_e( 'No policy records exist yet, so nothing is shown on the public page.', 'ceafsn-np' ); ?>
								<a class="ceafsn-alert__action" href="<?php echo esc_url( $np_add_url ); ?>"><?php esc_html_e( 'Add the first record', 'ceafsn-np' ); ?></a>
							</p>
						</div>
					</div>
				</div>

			<?php else : ?>

				<?php if ( $np_attention_count > 0 ) : ?>
					<div class="ceafsn-alerts">
						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title">
									<?php
									printf(
										/* translators: %s: number of records needing attention. */
										esc_html__( '%s record(s) need a document before they can be published', 'ceafsn-np' ),
										esc_html( number_format_i18n( $np_attention_count ) )
									);
									?>
								</p>
								<p class="ceafsn-alert__text">
									<?php
									printf(
										/* translators: 1: records with no PDF, 2: documents shared by several records. */
										esc_html__( '%1$s without a PDF, %2$s sharing a document with another record.', 'ceafsn-np' ),
										esc_html( number_format_i18n( count( $missing_pdf ) ) ),
										esc_html( number_format_i18n( $np_shared ) )
									);
									?>
									<a class="ceafsn-alert__action" href="<?php echo esc_url( $np_policies_url ); ?>"><?php esc_html_e( 'Review records', 'ceafsn-np' ); ?></a>
								</p>
							</div>
						</div>
					</div>
				<?php endif; ?>

			<?php endif; ?>

			<section class="ceafsn-hero">
				<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-np' ); ?></p>
				<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Nutrition Policy', 'ceafsn-np' ); ?></h1>
				<p class="ceafsn-hero__text">
					<?php esc_html_e( 'A record stays invisible on the public page until its own PDF passes validation. Nothing is listed without a real, readable document.', 'ceafsn-np' ); ?>
				</p>
				<div class="ceafsn-hero__actions">
					<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $np_add_url ); ?>">
						<?php esc_html_e( '+ Add policy record', 'ceafsn-np' ); ?>
					</a>
					<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $np_policies_url ); ?>">
						<?php esc_html_e( 'All records', 'ceafsn-np' ); ?>
					</a>
				</div>
				<p class="ceafsn-hero__meta">
					<span>
						<?php
						printf(
							/* translators: %s: total record count. */
							wp_kses_post( __( '<strong>%s</strong> records', 'ceafsn-np'  ) ),
							esc_html( number_format_i18n( $np_total ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: published count. */
							wp_kses_post( __( '<strong>%s</strong> published', 'ceafsn-np'  ) ),
							esc_html( number_format_i18n( $published ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: draft count. */
							wp_kses_post( __( '<strong>%s</strong> draft', 'ceafsn-np'  ) ),
							esc_html( number_format_i18n( $np_drafts ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: topic count. */
							wp_kses_post( __( '<strong>%s</strong> topics', 'ceafsn-np'  ) ),
							esc_html( number_format_i18n( count( $topics ) ) )
						);
						?>
					</span>
				</p>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display on a page', 'ceafsn-np' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Nothing is visible to visitors until this shortcode is on a page.', 'ceafsn-np' ); ?></p>
					</div>
					<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( add_query_arg( 'tab', 'display', $np_settings_url ) ); ?>">
						<?php esc_html_e( 'Attributes', 'ceafsn-np' ); ?>
					</a>
				</div>
				<div class="ceafsn-card__body">
					<div class="ceafsn-embed">
						<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste into the page that should list policies', 'ceafsn-np' ); ?></p>
						<code class="ceafsn-embed__code">[ceafsn_policy_table]</code>
					</div>
					<p class="ceafsn-card__hint">
						<?php esc_html_e( 'It takes optional per_page and topic attributes — open Display in settings for the full list.', 'ceafsn-np' ); ?>
					</p>
				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Publishing workflow', 'ceafsn-np' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Counted from your records. A step is only complete when the numbers prove it.', 'ceafsn-np' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">
					<ol class="ceafsn-stepper">
						<?php foreach ( $np_steps as $np_index => $np_step ) : ?>
							<li class="ceafsn-step is-<?php echo esc_attr( $np_step['state'] ); ?>">
								<span class="ceafsn-step__dot" aria-hidden="true">
									<?php
									if ( 'done' === $np_step['state'] ) {
										echo '&#10003;';
									} else {
										echo esc_html( (string) ( $np_index + 1 ) );
									}
									?>
								</span>
								<span class="ceafsn-step__label"><?php echo esc_html( $np_step['label'] ); ?></span>
								<span class="ceafsn-step__meta"><?php echo esc_html( $np_step['meta'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
					<p class="ceafsn-card__hint">
						<?php esc_html_e( 'Current step:', 'ceafsn-np' ); ?>
						<strong>
							<?php
							echo esc_html(
								null !== $np_current_step
									? $np_steps[ $np_current_step ]['label']
									: __( 'All steps complete', 'ceafsn-np' )
							);
							?>
						</strong>
					</p>
				</div>
			</section>

			<div class="ceafsn-kpi-row">
				<div class="ceafsn-kpi ceafsn-kpi--accent">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--green" aria-hidden="true"></span>
						<?php esc_html_e( 'Records', 'ceafsn-np' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $np_total ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						printf(
							/* translators: 1: published count, 2: draft count. */
							esc_html__( '%1$s published · %2$s draft', 'ceafsn-np' ),
							esc_html( number_format_i18n( $published ) ),
							esc_html( number_format_i18n( $np_drafts ) )
						);
						?>
					</span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--gold" aria-hidden="true"></span>
						<?php esc_html_e( 'With a PDF', 'ceafsn-np' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $np_attached ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						printf(
							/* translators: %s: number of records with no PDF. */
							esc_html__( '%s still missing one', 'ceafsn-np' ),
							esc_html( number_format_i18n( count( $missing_pdf ) ) )
						);
						?>
					</span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--grey" aria-hidden="true"></span>
						<?php esc_html_e( 'Topics covered', 'ceafsn-np' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( count( $topics ) ) ); ?></span>
					<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Distinct domains in use', 'ceafsn-np' ); ?></span>
				</div>

				<div class="ceafsn-kpi<?php echo $np_attention_count > 0 ? ' ceafsn-kpi--alert' : ''; ?>">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot <?php echo $np_attention_count > 0 ? 'ceafsn-dot--danger' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
						<?php esc_html_e( 'Needs attention', 'ceafsn-np' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $np_attention_count ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						if ( 0 === $np_attention_count ) {
							esc_html_e( 'Every record has its own document', 'ceafsn-np' );
						} else {
							printf(
								/* translators: 1: records with no PDF, 2: documents shared by several records. */
								esc_html__( '%1$s no PDF · %2$s shared', 'ceafsn-np' ),
								esc_html( number_format_i18n( count( $missing_pdf ) ) ),
								esc_html( number_format_i18n( $np_shared ) )
							);
						}
						?>
					</span>
				</div>
			</div>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Needs your attention', 'ceafsn-np' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Records with no PDF, or sharing a document with another record.', 'ceafsn-np' ); ?></p>
					</div>
					<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $np_policies_url ); ?>">
						<?php esc_html_e( 'All records', 'ceafsn-np' ); ?>
					</a>
				</div>

				<?php if ( empty( $np_attention ) ) : ?>
					<div class="ceafsn-empty">
						<span class="ceafsn-empty__mark" aria-hidden="true">&#10003;</span>
						<h3 class="ceafsn-empty__title"><?php esc_html_e( 'Every record has its own document', 'ceafsn-np' ); ?></h3>
						<p class="ceafsn-empty__text">
							<?php esc_html_e( 'No record is missing a PDF or sharing one with another record. Nothing to review here.', 'ceafsn-np' ); ?>
						</p>
					</div>
				<?php else : ?>
					<div class="ceafsn-table-wrap">
						<table class="ceafsn-table">
							<caption class="ceafsn-sr"><?php esc_html_e( 'Records needing attention', 'ceafsn-np' ); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Record', 'ceafsn-np' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-np' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Issue', 'ceafsn-np' ); ?></th>
									<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-np' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( $np_attention, 0, 10 ) as $np_item ) : ?>
									<tr>
										<td>
											<span class="ceafsn-table__title">
												<span class="ceafsn-dot <?php echo 'missing' === $np_item['reason'] ? 'ceafsn-dot--danger' : 'ceafsn-dot--gold'; ?>" aria-hidden="true"></span>
												<?php echo esc_html( $np_item['title'] ); ?>
											</span>
										</td>
										<td>
											<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( $np_item['status'] ); ?>">
												<?php echo esc_html( ucfirst( $np_item['status'] ) ); ?>
											</span>
										</td>
										<td><?php echo esc_html( $np_item['detail'] ); ?></td>
										<td class="ceafsn-table__actions">
											<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => $np_item['policy_id'] ), $np_policies_url ) ); ?>">
												<?php esc_html_e( 'Review', 'ceafsn-np' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if ( $np_attention_count > 10 ) : ?>
						<div class="ceafsn-card__body ceafsn-card__body--tight">
							<p class="ceafsn-card__hint">
								<?php
								printf(
									/* translators: %s: number of remaining records. */
									esc_html__( 'and %s more — open All records to review the rest.', 'ceafsn-np' ),
									esc_html( number_format_i18n( $np_attention_count - 10 ) )
								);
								?>
							</p>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Topics in use', 'ceafsn-np' ); ?></h2>
					<span class="ceafsn-pill ceafsn-pill--green"><?php echo esc_html( number_format_i18n( count( $topics ) ) ); ?></span>
				</div>
				<div class="ceafsn-card__body">
					<?php if ( empty( $topics ) ) : ?>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'No topic recorded yet.', 'ceafsn-np' ); ?></p>
					<?php else : ?>
						<ul class="ceafsn-ticks">
							<?php foreach ( $topics as $np_topic ) : ?>
								<li><?php echo esc_html( $np_topic ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</section>

		</div><!-- .ceafsn-app__main -->

		<aside class="ceafsn-app__rail">

			<div class="ceafsn-cta">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Get started', 'ceafsn-np' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add a policy record', 'ceafsn-np' ); ?></h2>
				<p class="ceafsn-cta__text">
					<?php esc_html_e( 'One record per document. Attach the PDF from the media library and publish when it validates.', 'ceafsn-np' ); ?>
				</p>
				<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( $np_add_url ); ?>">
					<?php esc_html_e( '+ Add policy record', 'ceafsn-np' ); ?>
				</a>
			</div>

			<div class="ceafsn-cta" id="ceafsn-np-help">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Before you publish', 'ceafsn-np' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Publishing rules', 'ceafsn-np' ); ?></h2>
				<ul class="ceafsn-ticks">
					<li><?php esc_html_e( 'Each record needs its own PDF.', 'ceafsn-np' ); ?></li>
					<li><?php esc_html_e( 'The PDF must be readable and have a readable page count.', 'ceafsn-np' ); ?></li>
					<li><?php esc_html_e( 'Known placeholder file names can never be published.', 'ceafsn-np' ); ?></li>
					<li><?php esc_html_e( 'Saving with an invalid PDF downgrades the record to draft.', 'ceafsn-np' ); ?></li>
				</ul>
			</div>

			<div class="ceafsn-cta">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Keep a copy', 'ceafsn-np' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Export records', 'ceafsn-np' ); ?></h2>
				<p class="ceafsn-cta__text">
					<?php esc_html_e( 'Download every record as JSON before bulk changes or removing the plugin.', 'ceafsn-np' ); ?>
				</p>
				<a class="ceafsn-btn ceafsn-btn--ghost ceafsn-btn--block" href="<?php echo esc_url( $np_export_url ); ?>">
					<?php esc_html_e( 'Open export', 'ceafsn-np' ); ?>
				</a>
			</div>

		</aside>

	</div><!-- .ceafsn-app -->

	<a class="ceafsn-help" href="#ceafsn-np-help" aria-label="<?php esc_attr_e( 'Jump to publishing rules', 'ceafsn-np' ); ?>">?</a>

</div><!-- .ceafsn-np-wrap -->