<?php
/**
 * Admin partial: grants list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_GF_Admin::page_grants()):
 *   @var string             $action          'list' | 'add' | 'edit'
 *   @var int                $id              Row ID when editing
 *   @var object|null        $row             DB row when editing
 *   @var array<int,object>  $items           All grant rows
 *   @var array|null         $validation      Call PDF validation result
 *   @var int                $duplicate_count Other records sharing the same PDF
 *   @var array<int,object>  $shared_with     The other records sharing the PDF
 *
 * Element IDs ceafsn-gf-pdf-id, ceafsn-gf-pdf-field, ceafsn-gf-media-button,
 * ceafsn-gf-media-clear, and the class ceafsn-gf-media are a contract with
 * assets/js/ceafsn-gf-admin.js. Do not rename them.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;

// Notices.
if ( ! empty( $_GET['ceafsn_gf_error'] ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_gf_error'] ) ) ) ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Grant saved.', 'ceafsn-gf' ); ?></p></div>
<?php endif; ?>

<?php if ( ! empty( $_GET['published_blocked'] ) ) : ?>
	<div class="notice notice-warning">
		<p><?php esc_html_e( "Grant saved as a draft: you do not have permission to publish. Ask an administrator to review and publish it.", 'ceafsn-gf' ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Grant deleted.', 'ceafsn-gf' ); ?></p></div>
<?php endif; ?>

<?php
$gf_base_url   = admin_url( 'admin.php?page=' . CEAFSN_GF_Admin::MENU_SLUG );
$gf_form_url   = admin_url( 'admin-post.php' );
$gf_add_url    = add_query_arg( array( 'action' => 'add' ), $gf_base_url );
$gf_set_url= admin_url( 'admin.php?page=' . CEAFSN_GF_Admin::PAGE_SETTINGS );
$gf_is_list    = 'list' === $action;
$statuses      = CEAFSN_GF_DB::statuses();
$grants        = CEAFSN_GF_DB::grant_statuses();
$gf_now_utc    = gmdate( 'Y-m-d H:i:s' );

// Counts for the KPI tiles and the workflow stepper. They describe the records
// this screen is actually showing, so an administrator is never told "all
// records have a PDF" when the list below them says otherwise.
$gf_total     = count( $items );
$gf_open      = 0;
$gf_upcoming  = 0;
$gf_closed    = 0;
$gf_published = 0;
$gf_with_pdf  = 0;
$gf_due       = 0;

foreach ( $items as $gf_item ) {
	$gf_grant_state = (string) $gf_item->grant_status;

	if ( 'open' === $gf_grant_state ) {
		++$gf_open;
	} elseif ( 'upcoming' === $gf_grant_state ) {
		++$gf_upcoming;
	} elseif ( 'closed' === $gf_grant_state ) {
		++$gf_closed;
	}

	if ( 'published' === (string) $gf_item->status ) {
		++$gf_published;
	}

	if ( (int) $gf_item->call_pdf_id > 0 ) {
		++$gf_with_pdf;
	}

	if ( 'open' === $gf_grant_state
		&& '' !== (string) ( $gf_item->deadline ?? '' )
		&& (string) $gf_item->deadline < $gf_now_utc ) {
		++$gf_due;
	}
}

// The picker shows which file is attached, so the name has to come from the
// attachment itself. A record whose call PDF has been deleted falls back to
// the ID rather than pretending the field is empty.
$call_pdf_id   = (int) ( $row->call_pdf_id ?? 0 );
$call_pdf_name = '';
if ( $call_pdf_id > 0 ) {
	$call_pdf_path = get_attached_file( $call_pdf_id );
	$call_pdf_name = $call_pdf_path
		? basename( (string) $call_pdf_path )
		: sprintf( '#%d', $call_pdf_id );
}

$deadline_input = CEAFSN_GF_DB::utc_to_local_input( (string) ( $row->deadline ?? '' ) );
?>

<div class="wrap ceafsn-gf-wrap">

	<?php if ( $gf_is_list ) : ?>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · Grants & Funding', 'ceafsn-gf' ); ?></p>
			<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Grant & scholarship records', 'ceafsn-gf' ); ?></h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'Nothing here is invented. A record cannot be published without a real deadline and a validated, non-duplicated Official Call PDF.', 'ceafsn-gf' ); ?>
			</p>
			<div class="ceafsn-hero__actions">
				<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $gf_add_url ); ?>">
					<?php esc_html_e( '+ Add grant', 'ceafsn-gf' ); ?>
				</a>
				<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $gf_set_url ); ?>">
					<?php esc_html_e( 'Settings', 'ceafsn-gf' ); ?>
				</a>
			</div>
			<p class="ceafsn-hero__meta">
				<span>
					<?php
					printf(
						/* translators: %s: total record count. */
						wp_kses_post( __( '<strong>%s</strong> records', 'ceafsn-gf' ) ),
						esc_html( number_format_i18n( $gf_total ) )
					);
					?>
				</span>
				<span><?php esc_html_e( 'Drafts stay hidden from visitors until published', 'ceafsn-gf' ); ?></span>
			</p>
		</section>

		<div class="ceafsn-app">

			<div class="ceafsn-app__main">

				<div class="ceafsn-kpi-row">
					<div class="ceafsn-kpi ceafsn-kpi--accent">
						<span class="ceafsn-kpi__label"><?php esc_html_e( 'Records', 'ceafsn-gf' ); ?></span>
						<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $gf_total ) ); ?></span>
						<span class="ceafsn-kpi__meta">
							<?php
							printf(
								/* translators: %s: number of published records. */
								esc_html__( '%s published', 'ceafsn-gf' ),
								esc_html( number_format_i18n( $gf_published ) )
							);
							?>
						</span>
					</div>
					<div class="ceafsn-kpi">
						<span class="ceafsn-kpi__label">
							<span class="ceafsn-dot ceafsn-dot--green" aria-hidden="true"></span>
							<?php esc_html_e( 'Open', 'ceafsn-gf' ); ?>
						</span>
						<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $gf_open ) ); ?></span>
						<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Accepting applications', 'ceafsn-gf' ); ?></span>
					</div>
					<div class="ceafsn-kpi">
						<span class="ceafsn-kpi__label">
							<span class="ceafsn-dot ceafsn-dot--gold" aria-hidden="true"></span>
							<?php esc_html_e( 'Upcoming', 'ceafsn-gf' ); ?>
						</span>
						<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $gf_upcoming ) ); ?></span>
						<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Not yet accepting', 'ceafsn-gf' ); ?></span>
					</div>
					<div class="ceafsn-kpi<?php echo $gf_due > 0 ? ' ceafsn-kpi--alert' : ''; ?>">
						<span class="ceafsn-kpi__label">
							<span class="ceafsn-dot ceafsn-dot--danger" aria-hidden="true"></span>
							<?php esc_html_e( 'Deadline passed', 'ceafsn-gf' ); ?>
						</span>
						<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $gf_due ) ); ?></span>
						<span class="ceafsn-kpi__meta">
							<?php
							if ( $gf_due > 0 ) {
								esc_html_e( 'Still marked Open — check these', 'ceafsn-gf' );
							} else {
								esc_html_e( 'No stale Open records', 'ceafsn-gf' );
							}
							?>
						</span>
					</div>
				</div>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title">
								<?php
								printf(
									/* translators: %s: number of grant records. */
									esc_html__( 'All records (%s)', 'ceafsn-gf' ),
									esc_html( number_format_i18n( $gf_total ) )
								);
								?>
							</h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Drafts stay hidden from visitors until published.', 'ceafsn-gf' ); ?></p>
						</div>
					</div>

					<div class="ceafsn-card__body">
						<ol class="ceafsn-stepper">
							<?php
							$gf_steps = array(
								array(
									'label' => __( 'Recorded', 'ceafsn-gf' ),
									'meta'  => sprintf(
										/* translators: %s: number of records. */
										__( '%s records', 'ceafsn-gf' ),
										number_format_i18n( $gf_total )
									),
									'state' => $gf_total > 0 ? 'done' : 'current',
								),
								array(
									'label' => __( 'Call PDF attached', 'ceafsn-gf' ),
									'meta'  => sprintf(
										/* translators: 1: number of records with a PDF, 2: total records. */
										__( '%1$s of %2$s', 'ceafsn-gf' ),
										number_format_i18n( $gf_with_pdf ),
										number_format_i18n( $gf_total )
									),
									'state' => ( $gf_total > 0 && $gf_with_pdf === $gf_total ) ? 'done' : 'current',
								),
								array(
									'label' => __( 'Published', 'ceafsn-gf' ),
									'meta'  => sprintf(
										/* translators: %s: number of published records. */
										__( '%s live', 'ceafsn-gf' ),
										number_format_i18n( $gf_published )
									),
									'state' => ( $gf_total > 0 && $gf_published === $gf_total ) ? 'done' : 'current',
								),
							);

							foreach ( $gf_steps as $gf_index => $gf_step ) :
								?>
								<li class="ceafsn-step is-<?php echo esc_attr( $gf_step['state'] ); ?>">
									<span class="ceafsn-step__dot" aria-hidden="true">
										<?php
										if ( 'done' === $gf_step['state'] ) {
											echo '&#10003;';
										} else {
											echo esc_html( (string) ( $gf_index + 1 ) );
										}
										?>
									</span>
									<span class="ceafsn-step__label"><?php echo esc_html( $gf_step['label'] ); ?></span>
									<span class="ceafsn-step__meta"><?php echo esc_html( $gf_step['meta'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ol>
					</div>

					<?php if ( empty( $items ) ) : ?>
						<div class="ceafsn-empty">
							<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
							<h3 class="ceafsn-empty__title"><?php esc_html_e( 'No grant records yet', 'ceafsn-gf' ); ?></h3>
							<p class="ceafsn-empty__text">
								<?php esc_html_e( 'Nothing has been recorded. Grants stay invisible on the public page until they are published with a validated call PDF.', 'ceafsn-gf' ); ?>
							</p>
							<div class="ceafsn-empty__action">
								<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $gf_add_url ); ?>">
									<?php esc_html_e( 'Add the first grant', 'ceafsn-gf' ); ?>
								</a>
							</div>
						</div>
					<?php else : ?>
						<div class="ceafsn-table-wrap">
							<table class="ceafsn-table">
								<caption class="ceafsn-sr"><?php esc_html_e( 'Grant records list', 'ceafsn-gf' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Title & deadline', 'ceafsn-gf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Institution', 'ceafsn-gf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Opportunity', 'ceafsn-gf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Call PDF', 'ceafsn-gf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Record state', 'ceafsn-gf' ); ?></th>
										<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-gf' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ( $items as $item ) :
										$gf_item_pdf       = (int) $item->call_pdf_id;
										$gf_item_deadline  = CEAFSN_GF_Public::format_deadline( (string) ( $item->deadline ?? '' ), false );
										$gf_item_overdue   = 'open' === (string) $item->grant_status
											&& '' !== (string) ( $item->deadline ?? '' )
											&& (string) $item->deadline < $gf_now_utc;
										?>
										<tr>
											<td>
												<span class="ceafsn-table__title">
													<span class="ceafsn-dot <?php echo $gf_item_pdf > 0 ? 'ceafsn-dot--green' : 'ceafsn-dot--danger'; ?>" aria-hidden="true"></span>
													<?php echo esc_html( (string) $item->title ); ?>
												</span>
												<span class="ceafsn-table__meta">
													<?php
													if ( '' !== $gf_item_deadline ) {
														echo esc_html( $gf_item_deadline );
													} else {
														esc_html_e( 'No deadline', 'ceafsn-gf' );
													}
													?>
												</span>
											</td>
											<td>
												<?php
												echo esc_html(
													'' !== (string) $item->funding_institution
														? (string) $item->funding_institution
														: '—'
												);
												?>
											</td>
											<td>
												<span class="ceafsn-badge ceafsn-badge--grant-<?php echo esc_attr( (string) $item->grant_status ); ?>">
													<?php echo esc_html( CEAFSN_GF_Public::status_label( (string) $item->grant_status ) ); ?>
												</span>
												<?php if ( $gf_item_overdue ) : ?>
													<span class="ceafsn-gf-flag">
														<?php esc_html_e( 'Deadline passed, still Open', 'ceafsn-gf' ); ?>
													</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $gf_item_pdf > 0 ) : ?>
													<span class="ceafsn-badge ceafsn-badge--ok"><?php esc_html_e( 'PDF', 'ceafsn-gf' ); ?></span>
													<?php if ( (int) $item->duplicate_ok === 1 ) : ?>
														<span class="ceafsn-badge ceafsn-badge--shared"><?php esc_html_e( 'Shared', 'ceafsn-gf' ); ?></span>
													<?php endif; ?>
												<?php else : ?>
													<span class="ceafsn-badge ceafsn-badge--warn"><?php esc_html_e( 'Missing', 'ceafsn-gf' ); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<span class="ceafsn-badge ceafsn-badge--state-<?php echo esc_attr( (string) $item->status ); ?>">
													<?php echo esc_html( CEAFSN_GF_DB::label( CEAFSN_GF_DB::status_labels(), (string) $item->status ) ); ?>
												</span>
											</td>
											<td class="ceafsn-table__actions">
												<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->grant_id ), $gf_base_url ) ); ?>">
													<?php esc_html_e( 'Edit', 'ceafsn-gf' ); ?>
												</a>
												<a class="ceafsn-gf-delete-link"
													href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_gf_delete_grant&id=' . (int) $item->grant_id ), 'ceafsn_gf_delete_grant_' . (int) $item->grant_id ) ); ?>">
													<?php esc_html_e( 'Delete', 'ceafsn-gf' ); ?>
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</section>

			</div><!-- .ceafsn-app__main -->

			<div class="ceafsn-app__rail">

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Get started', 'ceafsn-gf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add a grant', 'ceafsn-gf' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'One record per call document. Attach the PDF from the media library and publish when it validates.', 'ceafsn-gf' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( $gf_add_url ); ?>">
						<?php esc_html_e( '+ Add grant', 'ceafsn-gf' ); ?>
					</a>
				</div>

				<div class="ceafsn-cta" id="ceafsn-gf-help">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Before you publish', 'ceafsn-gf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Publishing rules', 'ceafsn-gf' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'A real deadline is required; nothing is guessed from the file name.', 'ceafsn-gf' ); ?></li>
						<li><?php esc_html_e( 'The Official Call PDF must be readable.', 'ceafsn-gf' ); ?></li>
						<li><?php esc_html_e( 'Each grant should have its own call document.', 'ceafsn-gf' ); ?></li>
						<li><?php esc_html_e( 'Sharing one PDF across records has to be confirmed with a note.', 'ceafsn-gf' ); ?></li>
						<li><?php esc_html_e( 'Saving with an invalid PDF downgrades the record to draft.', 'ceafsn-gf' ); ?></li>
					</ul>
				</div>

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Keep a copy', 'ceafsn-gf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Display & export', 'ceafsn-gf' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'The shortcode that lists these grants, and a JSON backup of every record, both live on the settings screen.', 'ceafsn-gf' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--quiet ceafsn-btn--block" href="<?php echo esc_url( $gf_set_url ); ?>">
						<?php esc_html_e( 'Open settings', 'ceafsn-gf' ); ?>
					</a>
				</div>

			</div><!-- .ceafsn-app__rail -->

		</div><!-- .ceafsn-app -->

		<a class="ceafsn-help" href="#ceafsn-gf-help" aria-label="<?php esc_attr_e( 'Jump to publishing rules', 'ceafsn-gf' ); ?>">?</a>

	<?php else : ?>

		<a class="ceafsn-back" href="<?php echo esc_url( $gf_base_url ); ?>">
			&larr; <?php esc_html_e( 'Back to records', 'ceafsn-gf' ); ?>
		</a>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · Grants & Funding', 'ceafsn-gf' ); ?></p>
			<h1 class="ceafsn-hero__title">
				<?php
				echo esc_html(
					$row
						? __( 'Edit grant', 'ceafsn-gf' )
						: __( 'Add new grant', 'ceafsn-gf' )
				);
				?>
			</h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'A title, award range, institution, deadline, eligibility, and call PDF are all required. Publishing is blocked until the PDF validates.', 'ceafsn-gf' ); ?>
			</p>
		</section>

		<?php if ( $row && $validation && ! $validation['valid'] && 'published' === $row->status ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'This published record no longer passes PDF validation:', 'ceafsn-gf' ); ?></strong></p>
				<ul>
					<?php foreach ( $validation['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $row && $duplicate_count > 0 ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						esc_html(
							/* translators: %d: number of other records. */
							_n(
								'The same call PDF is attached to %d other record. Each grant should have its own document.',
								'The same call PDF is attached to %d other records. Each grant should have its own document.',
								$duplicate_count,
								'ceafsn-gf'
							)
						),
						(int) $duplicate_count
					);
					?>
				</p>
				<?php if ( ! empty( $shared_with ) ) : ?>
					<ul class="ceafsn-gf-shared-list">
						<?php foreach ( $shared_with as $other ) : ?>
							<li>
								<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $other->grant_id ), $gf_base_url ) ); ?>">
									<?php echo esc_html( (string) $other->title ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="ceafsn-app">
			<div class="ceafsn-app__main">

				<form class="ceafsn-form ceafsn-form__spaced" method="post" action="<?php echo esc_url( $gf_form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_gf_grant_nonce', 'ceafsn_gf_nonce' ); ?>
					<input type="hidden" name="action" value="ceafsn_gf_save_grant" />
					<input type="hidden" name="grant_id" value="<?php echo esc_attr( (string) (int) ( $row->grant_id ?? 0 ) ); ?>" />

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Opportunity details', 'ceafsn-gf' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Fields marked with an asterisk are required.', 'ceafsn-gf' ); ?></p>
						</div>
					</div>
					<div class="ceafsn-card__body">

							<div class="ceafsn-field">
								<label for="ceafsn-gf-title">
									<?php esc_html_e( 'Grant / scholarship title', 'ceafsn-gf' ); ?>
									<span class="required" aria-hidden="true">*</span>
								</label>
								<input type="text" id="ceafsn-gf-title" name="title" required
									value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
							</div>

							<div class="ceafsn-field">
								<label for="ceafsn-gf-award">
									<?php esc_html_e( 'Award range', 'ceafsn-gf' ); ?>
									<span class="required" aria-hidden="true">*</span>
								</label>
								<input type="text" id="ceafsn-gf-award" name="award_range" required
									placeholder="<?php esc_attr_e( 'e.g. USD 5,000-10,000, or "Not disclosed"', 'ceafsn-gf' ); ?>"
									value="<?php echo esc_attr( (string) ( $row->award_range ?? '' ) ); ?>" />
								<p class="ceafsn-field__hint"><?php esc_html_e( 'Use "Not disclosed" rather than leaving this blank if the amount is unknown.', 'ceafsn-gf' ); ?></p>
							</div>

							<div class="ceafsn-field">
								<label for="ceafsn-gf-institution">
									<?php esc_html_e( 'Funding institution', 'ceafsn-gf' ); ?>
									<span class="required" aria-hidden="true">*</span>
								</label>
								<input type="text" id="ceafsn-gf-institution" name="funding_institution" required
									value="<?php echo esc_attr( (string) ( $row->funding_institution ?? '' ) ); ?>" />
							</div>

							<div class="ceafsn-field">
								<label for="ceafsn-gf-deadline">
									<?php esc_html_e( 'Deadline', 'ceafsn-gf' ); ?>
									<span class="required" aria-hidden="true">*</span>
								</label>
								<input type="datetime-local" id="ceafsn-gf-deadline" name="deadline"
									aria-describedby="ceafsn-gf-deadline-help"
									value="<?php echo esc_attr( $deadline_input ); ?>" />
								<p class="ceafsn-field__hint" id="ceafsn-gf-deadline-help">
									<?php
									printf(
										/* translators: %s: site timezone name. */
										esc_html__( 'Entered and displayed in the site timezone (%s), stored as UTC.', 'ceafsn-gf' ),
										esc_html( (string) wp_timezone_string() )
									);
									?>
								</p>
							</div>

							<div class="ceafsn-field">
								<label for="ceafsn-gf-beneficiaries"><?php esc_html_e( 'Target beneficiaries', 'ceafsn-gf' ); ?></label>
								<textarea id="ceafsn-gf-beneficiaries" name="target_beneficiaries" rows="3"><?php echo esc_textarea( (string) ( $row->target_beneficiaries ?? '' ) ); ?></textarea>
								<p class="ceafsn-field__hint"><?php esc_html_e( 'Optional. Who this grant is meant for.', 'ceafsn-gf' ); ?></p>
							</div>

							<div class="ceafsn-field">
								<label for="ceafsn-gf-eligibility">
									<?php esc_html_e( 'Eligibility', 'ceafsn-gf' ); ?>
									<span class="required" aria-hidden="true">*</span>
								</label>
								<textarea id="ceafsn-gf-eligibility" name="eligibility" rows="5" required
									aria-describedby="ceafsn-gf-eligibility-help"><?php echo esc_textarea( (string) ( $row->eligibility ?? '' ) ); ?></textarea>
								<p class="ceafsn-field__hint" id="ceafsn-gf-eligibility-help">
									<?php esc_html_e( 'Required. A visitor who cannot tell whether they are eligible will not apply.', 'ceafsn-gf' ); ?>
								</p>
							</div>

					</div>
				</section>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Call document', 'ceafsn-gf' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Validated on save. One document per grant unless sharing is confirmed.', 'ceafsn-gf' ); ?></p>
						</div>
					</div>
					<div class="ceafsn-card__body">

						<div class="ceafsn-field">
							<label for="ceafsn-gf-pdf-field">
								<?php esc_html_e( 'Official Call PDF', 'ceafsn-gf' ); ?>
								<span class="required" aria-hidden="true">*</span>
							</label>
							<div class="ceafsn-gf-media">
								<input type="hidden" id="ceafsn-gf-pdf-id" name="call_pdf_id" value="<?php echo esc_attr( (string) $call_pdf_id ); ?>" />
								<input type="text" id="ceafsn-gf-pdf-field" class="ceafsn-gf-media__name" readonly
									placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-gf' ); ?>"
									value="<?php echo esc_attr( $call_pdf_name ); ?>" />
								<button type="button" class="ceafsn-btn ceafsn-btn--quiet" id="ceafsn-gf-media-button">
									<?php esc_html_e( 'Select PDF', 'ceafsn-gf' ); ?>
								</button>
								<button type="button" class="ceafsn-gf-media__clear" id="ceafsn-gf-media-clear" hidden>
									<?php esc_html_e( 'Clear', 'ceafsn-gf' ); ?>
								</button>
							</div>
							<p class="ceafsn-field__hint">
								<?php esc_html_e( 'Required to publish. The file name is read from the attachment itself, and a record whose document has been deleted shows its ID rather than an empty box.', 'ceafsn-gf' ); ?>
							</p>
						</div>

						<?php if ( $row && $validation ) : ?>
							<p class="ceafsn-gf-validation ceafsn-gf-validation--<?php echo esc_attr( $validation['valid'] ? 'ok' : 'fail' ); ?>">
								<?php
								if ( $validation['valid'] ) {
									printf(
										/* translators: %d: page count. */
										esc_html__( 'Call PDF verified: readable, %d page(s).', 'ceafsn-gf' ),
										(int) $validation['pages']
									);
								} else {
									echo esc_html( implode( ' ', $validation['errors'] ) );
								}
								?>
							</p>
						<?php endif; ?>

						<div class="ceafsn-field">
							<label class="ceafsn-check__title" for="ceafsn-gf-duplicate-ok">
								<?php esc_html_e( 'Shared document', 'ceafsn-gf' ); ?>
							</label>
							<div class="ceafsn-check">
								<input type="checkbox" id="ceafsn-gf-duplicate-ok" name="duplicate_ok" value="1"
									<?php checked( ! empty( $row->duplicate_ok ) ); ?> />
								<div class="ceafsn-check__body">
									<label class="ceafsn-check__title" for="ceafsn-gf-duplicate-ok">
										<?php esc_html_e( 'This document is intentionally shared with another record', 'ceafsn-gf' ); ?>
									</label>
									<span class="ceafsn-check__hint">
										<?php esc_html_e( 'Only tick this when two grant listings genuinely point at the same call PDF. This is shown to visitors on both records.', 'ceafsn-gf' ); ?>
									</span>
								</div>
							</div>
						</div>

						<div class="ceafsn-field">
							<label for="ceafsn-gf-duplicate-note"><?php esc_html_e( 'Sharing note', 'ceafsn-gf' ); ?></label>
							<textarea id="ceafsn-gf-duplicate-note" name="duplicate_note" rows="2"
								aria-describedby="ceafsn-gf-duplicate-note-help"><?php echo esc_textarea( (string) ( $row->duplicate_note ?? '' ) ); ?></textarea>
							<p class="ceafsn-field__hint" id="ceafsn-gf-duplicate-note-help">
								<?php esc_html_e( 'Required whenever the box above is ticked.', 'ceafsn-gf' ); ?>
							</p>
						</div>

					</div>
				</section>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'How visitors find and apply', 'ceafsn-gf' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'An invalid application link is never rendered as a working button.', 'ceafsn-gf' ); ?></p>
						</div>
					</div>
					<div class="ceafsn-card__body">

						<div class="ceafsn-field">
							<label for="ceafsn-gf-app-url"><?php esc_html_e( 'Application URL', 'ceafsn-gf' ); ?></label>
							<input type="url" id="ceafsn-gf-app-url" name="application_url" placeholder="https://"
								aria-describedby="ceafsn-gf-app-help"
								value="<?php echo esc_attr( (string) ( $row->application_url ?? '' ) ); ?>" />
							<p class="ceafsn-field__hint" id="ceafsn-gf-app-help">
								<?php esc_html_e( 'A full http or https address. Without one the Apply button is shown as inactive rather than as a broken link.', 'ceafsn-gf' ); ?>
							</p>
						</div>

						<div class="ceafsn-field">
							<label for="ceafsn-gf-contact"><?php esc_html_e( 'Contact', 'ceafsn-gf' ); ?></label>
							<input type="text" id="ceafsn-gf-contact" name="contact"
								aria-describedby="ceafsn-gf-contact-help"
								value="<?php echo esc_attr( (string) ( $row->contact ?? '' ) ); ?>" />
							<div class="ceafsn-check">
								<input type="checkbox" id="ceafsn-gf-show-contact" name="show_contact" value="1"
									<?php checked( ! empty( $row->show_contact ) ); ?> />
								<div class="ceafsn-check__body">
									<label class="ceafsn-check__title" for="ceafsn-gf-show-contact">
										<?php esc_html_e( 'Approved for public display', 'ceafsn-gf' ); ?>
									</label>
									<span class="ceafsn-check__hint">
										<?php esc_html_e( 'Stored but never shown until it is approved. Leave blank if there is no verified contact.', 'ceafsn-gf' ); ?>
									</span>
								</div>
							</div>
							<p class="ceafsn-field__hint" id="ceafsn-gf-contact-help">
								<?php esc_html_e( 'A contact is only ever published when it has been approved here.', 'ceafsn-gf' ); ?>
							</p>
						</div>

					</div>
				</section>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Visibility', 'ceafsn-gf' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Opportunity status and record state are separate decisions.', 'ceafsn-gf' ); ?></p>
						</div>
					</div>
					<div class="ceafsn-card__body">

						<div class="ceafsn-field">
							<label for="ceafsn-gf-grant-status"><?php esc_html_e( 'Opportunity status', 'ceafsn-gf' ); ?></label>
							<select id="ceafsn-gf-grant-status" name="grant_status">
								<?php foreach ( $grants as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $value, (string) ( $row->grant_status ?? 'upcoming' ) ); ?>>
										<?php echo esc_html( CEAFSN_GF_Public::status_label( $value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="ceafsn-field__hint">
								<?php esc_html_e( 'Set directly by the administrator. Keep this in step with the deadline above; the plugin does not change it automatically.', 'ceafsn-gf' ); ?>
							</p>
							<?php if ( $row && 'open' === (string) ( $row->grant_status ?? '' ) && '' !== (string) ( $row->deadline ?? '' ) && (string) $row->deadline < $gf_now_utc ) : ?>
								<p class="ceafsn-gf-flag">
									<?php esc_html_e( 'The deadline above has already passed, but the status is still set to Open. Check this is correct.', 'ceafsn-gf' ); ?>
								</p>
							<?php endif; ?>
						</div>

						<div class="ceafsn-field">
							<label for="ceafsn-gf-status"><?php esc_html_e( 'Record state', 'ceafsn-gf' ); ?></label>
							<select id="ceafsn-gf-status" name="status">
								<?php foreach ( $statuses as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $value, (string) ( $row->status ?? 'draft' ) ); ?>>
										<?php echo esc_html( CEAFSN_GF_DB::label( CEAFSN_GF_DB::status_labels(), (string) $value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="ceafsn-field__hint">
								<?php esc_html_e( 'Only Published grants appear on the public page.', 'ceafsn-gf' ); ?>
							</p>
						</div>

					</div>
				</section>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php esc_html_e( 'Save grant', 'ceafsn-gf' ); ?>
						</button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $gf_base_url ); ?>">
							<?php esc_html_e( 'Cancel', 'ceafsn-gf' ); ?>
						</a>
					</div>
				</form>

			</div><!-- .ceafsn-app__main -->

			<div class="ceafsn-app__rail">

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Before you publish', 'ceafsn-gf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Publishing rules', 'ceafsn-gf' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'A real deadline is required.', 'ceafsn-gf' ); ?></li>
						<li><?php esc_html_e( 'The Official Call PDF must be readable.', 'ceafsn-gf' ); ?></li>
						<li><?php esc_html_e( 'Sharing one PDF across records has to be confirmed with a note.', 'ceafsn-gf' ); ?></li>
						<li><?php esc_html_e( 'Saving with an invalid PDF downgrades the record to draft.', 'ceafsn-gf' ); ?></li>
					</ul>
				</div>

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Nothing invented', 'ceafsn-gf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Honest empty states', 'ceafsn-gf' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'A grant with no deadline shows "No deadline". One with no application link shows an inactive Apply button rather than a dead link. Neither is filled in for you.', 'ceafsn-gf' ); ?>
					</p>
				</div>

			</div><!-- .ceafsn-app__rail -->

		</div><!-- .ceafsn-app -->

	<?php endif; ?>

</div><!-- .ceafsn-gf-wrap -->