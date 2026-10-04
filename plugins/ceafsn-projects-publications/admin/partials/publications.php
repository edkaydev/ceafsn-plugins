<?php
/**
 * Admin partial: publications list, add, and edit view.
 *
 * Layout: WordPress admin menu on the left, a main canvas in the middle, and a
 * narrower right rail for calls to action and publishing guidance.
 *
 * Variables in scope (set by CEAFSN_PP_Admin::page_publications()):
 *   @var string            $action         'list' | 'add' | 'edit'
 *   @var int               $id             Row ID when editing
 *   @var object|null       $row            DB row when editing
 *   @var array<int,object> $items          All publication rows
 *   @var array|null        $validation     Validator result for the attached PDF
 *   @var int               $duplicate_count Other records sharing the same PDF
 *   @var array<int,object> $shared_with     The other records sharing the PDF
 *
 * Element IDs ceafsn-pp-cover-id, ceafsn-pp-cover-field, ceafsn-pp-cover-button,
 * ceafsn-pp-cover-clear, ceafsn-pp-pdf-id, ceafsn-pp-pdf-field,
 * ceafsn-pp-pdf-button, ceafsn-pp-pdf-clear, and the class
 * ceafsn-pp-delete-link are a contract with assets/js/ceafsn-pp-admin.js.
 * Do not rename them.
 *
 * Every count below is counted from stored rows. Nothing is estimated or
 * filled in with placeholder data, and a workflow step is only marked
 * complete when the underlying count proves it.
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

// Notices.
$pp_notice_error = ! empty( $_GET['ceafsn_pp_error'] )
	? urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_pp_error'] ) ) )
	: '';
$pp_notice_saved   = ! empty( $_GET['saved'] );
$pp_notice_deleted = ! empty( $_GET['deleted'] );

$base_url = admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::MENU_SLUG );
$form_url = admin_url( 'admin-post.php' );
$is_list  = 'list' === $action;
$statuses = CEAFSN_PP_DB::statuses();
$types    = CEAFSN_PP_DB::content_types();
$projects = CEAFSN_PP_DB::project_statuses();
$access   = CEAFSN_PP_DB::access_levels();
$add_url  = add_query_arg( array( 'action' => 'add' ), $base_url );

// The pickers show which file is attached, so the name has to come from the
// attachment itself. A record whose cover has been deleted from the media
// library falls back to the ID rather than pretending the field is empty.
$cover_image_id   = (int) ( $row->cover_image_id ?? 0 );
$cover_image_name = '';
if ( $cover_image_id > 0 ) {
	$cover_image_path = get_attached_file( $cover_image_id );
	$cover_image_name = $cover_image_path
		? basename( (string) $cover_image_path )
		: sprintf( '#%d', $cover_image_id );
}
$pdf_attachment_id = (int) ( $row->pdf_attachment_id ?? 0 );
?>

<div class="wrap ceafsn-pp-wrap">

	<a class="ceafsn-sr" href="#ceafsn-pp-main"><?php esc_html_e( 'Skip to record content', 'ceafsn-pp' ); ?></a>

	<div id="ceafsn-pp-main">

	<?php if ( '' !== $pp_notice_error ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
				<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title"><?php esc_html_e( 'That change was not saved', 'ceafsn-pp' ); ?></p>
					<p class="ceafsn-alert__text"><?php echo esc_html( $pp_notice_error ); ?></p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $pp_notice_saved || $pp_notice_deleted ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--ok" role="status">
				<span class="ceafsn-alert__icon" aria-hidden="true">&#10003;</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title">
						<?php
						echo esc_html(
							$pp_notice_deleted
								? __( 'Record deleted.', 'ceafsn-pp' )
								: __( 'Record saved.', 'ceafsn-pp' )
						);
						?>
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $is_list ) : ?>

		<?php
		// Counted from the stored rows, never estimated.
		$pp_total       = count( $items );
		$pp_published   = 0;
		$pp_drafts      = 0;
		$pp_with_pdf    = 0;
		$pp_no_pdf      = 0;
		$pp_shared_docs = 0;
		$pp_members     = 0;
		foreach ( $items as $pp_row ) {
			$pp_row_status = (string) $pp_row->status;
			if ( 'published' === $pp_row_status ) {
				++$pp_published;
			} elseif ( 'draft' === $pp_row_status ) {
				++$pp_drafts;
			}
			if ( (int) $pp_row->pdf_attachment_id > 0 ) {
				++$pp_with_pdf;
			} else {
				++$pp_no_pdf;
			}
			if ( (int) $pp_row->duplicate_ok ) {
				++$pp_shared_docs;
			}
			if ( 'members_only' === (string) $pp_row->access_level ) {
				++$pp_members;
			}
		}

		// Publishing workflow. The first step still at zero is the current one.
		$pp_steps = array(
			array(
				'label' => __( 'Records added', 'ceafsn-pp' ),
				'meta'  => sprintf( /* translators: %s: number of records. */ __( '%s records', 'ceafsn-pp' ), number_format_i18n( $pp_total ) ),
				'count' => $pp_total,
			),
			array(
				'label' => __( 'Document attached', 'ceafsn-pp' ),
				'meta'  => sprintf( /* translators: %s: number of records with a PDF. */ __( '%s with a PDF', 'ceafsn-pp' ), number_format_i18n( $pp_with_pdf ) ),
				'count' => $pp_with_pdf,
			),
			array(
				'label' => __( 'Published', 'ceafsn-pp' ),
				'meta'  => sprintf( /* translators: %s: number of published records. */ __( '%s visible to visitors', 'ceafsn-pp' ), number_format_i18n( $pp_published ) ),
				'count' => $pp_published,
			),
		);

		$pp_current_step = null;
		foreach ( $pp_steps as $pp_index => $pp_step ) {
			if ( $pp_step['count'] > 0 ) {
				$pp_steps[ $pp_index ]['state'] = 'done';
			} elseif ( null === $pp_current_step ) {
				$pp_steps[ $pp_index ]['state'] = 'current';
				$pp_current_step                = $pp_index;
			} else {
				$pp_steps[ $pp_index ]['state'] = 'todo';
			}
		}
		?>

		<div class="ceafsn-app">

			<div class="ceafsn-app__main">

				<?php if ( 0 === $pp_total ) : ?>

					<div class="ceafsn-alerts">
						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title"><?php esc_html_e( 'No records yet', 'ceafsn-pp' ); ?></p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'Nothing is displayed on the public page until a record is published with its own validated PDF.', 'ceafsn-pp' ); ?>
									<a class="ceafsn-alert__action" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add the first record', 'ceafsn-pp' ); ?></a>
								</p>
							</div>
						</div>
					</div>

				<?php elseif ( $pp_no_pdf > 0 ) : ?>

					<div class="ceafsn-alerts">
						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title">
									<?php
									printf(
										/* translators: %s: number of records with no PDF. */
										esc_html__( '%s record(s) have no document attached', 'ceafsn-pp' ),
										esc_html( number_format_i18n( $pp_no_pdf ) )
									);
									?>
								</p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'A record can only be published once its own PDF passes every check.', 'ceafsn-pp' ); ?>
								</p>
							</div>
						</div>
					</div>

				<?php endif; ?>

				<section class="ceafsn-hero">
					<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-pp' ); ?></p>
					<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Projects & Publications', 'ceafsn-pp' ); ?></h1>
					<p class="ceafsn-hero__text">
						<?php esc_html_e( 'Reports, policy briefs, working papers, and project records. Every publication carries its own validated document.', 'ceafsn-pp' ); ?>
					</p>
					<div class="ceafsn-hero__actions">
						<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $add_url ); ?>">
							<?php esc_html_e( '+ Add record', 'ceafsn-pp' ); ?>
						</a>
						<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::SETTINGS_SLUG . '&tab=export' ) ); ?>">
							<?php esc_html_e( 'Export', 'ceafsn-pp' ); ?>
						</a>
					</div>
					<p class="ceafsn-hero__meta">
						<span>
							<?php
							printf(
								/* translators: %s: total record count. */
								wp_kses_post( __( '<strong>%s</strong> records', 'ceafsn-pp' ) ),
								esc_html( number_format_i18n( $pp_total ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: published record count. */
								wp_kses_post( __( '<strong>%s</strong> published', 'ceafsn-pp' ) ),
								esc_html( number_format_i18n( $pp_published ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: number of draft records. */
								wp_kses_post( __( '<strong>%s</strong> drafts', 'ceafsn-pp' ) ),
								esc_html( number_format_i18n( $pp_drafts ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: number of members-only records. */
								wp_kses_post( __( '<strong>%s</strong> members-only', 'ceafsn-pp' ) ),
								esc_html( number_format_i18n( $pp_members ) )
							);
							?>
						</span>
					</p>
				</section>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display on a page', 'ceafsn-pp' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Nothing is visible to visitors until this shortcode is on a page.', 'ceafsn-pp' ); ?></p>
						</div>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::SETTINGS_SLUG . '&tab=display' ) ); ?>">
							<?php esc_html_e( 'Attributes', 'ceafsn-pp' ); ?>
						</a>
					</div>
					<div class="ceafsn-card__body">
						<div class="ceafsn-embed">
							<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste into the page that should list records', 'ceafsn-pp' ); ?></p>
							<code class="ceafsn-embed__code">[ceafsn_projects_pubs]</code>
						</div>
						<p class="ceafsn-card__hint">
							<?php esc_html_e( 'It takes optional per_page, content_type, and view attributes — open Display in settings for the full list.', 'ceafsn-pp' ); ?>
						</p>
					</div>
				</section>

				<?php if ( $pp_total > 0 ) : ?>

					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<div>
								<h2 class="ceafsn-card__title"><?php esc_html_e( 'Publishing workflow', 'ceafsn-pp' ); ?></h2>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'Counted from your records. A step is only complete when the numbers prove it.', 'ceafsn-pp' ); ?></p>
							</div>
						</div>
						<div class="ceafsn-card__body">
							<ol class="ceafsn-stepper">
								<?php foreach ( $pp_steps as $pp_index => $pp_step ) : ?>
									<li class="ceafsn-step is-<?php echo esc_attr( $pp_step['state'] ); ?>">
										<span class="ceafsn-step__dot" aria-hidden="true">
											<?php
											if ( 'done' === $pp_step['state'] ) {
												echo '&#10003;';
											} else {
												echo esc_html( (string) ( $pp_index + 1 ) );
											}
											?>
										</span>
										<span class="ceafsn-step__label"><?php echo esc_html( $pp_step['label'] ); ?></span>
										<span class="ceafsn-step__meta"><?php echo esc_html( $pp_step['meta'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ol>
							<p class="ceafsn-card__hint">
								<?php esc_html_e( 'Current step:', 'ceafsn-pp' ); ?>
								<strong>
									<?php
									echo esc_html(
										null !== $pp_current_step
											? $pp_steps[ $pp_current_step ]['label']
											: __( 'All steps complete', 'ceafsn-pp' )
									);
									?>
								</strong>
							</p>
						</div>
					</section>

					<div class="ceafsn-kpi-row">
						<div class="ceafsn-kpi ceafsn-kpi--accent">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot ceafsn-dot--grey" aria-hidden="true"></span>
								<?php esc_html_e( 'Records', 'ceafsn-pp' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $pp_total ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'In this plugin', 'ceafsn-pp' ); ?></span>
						</div>

						<div class="ceafsn-kpi">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot ceafsn-dot--green" aria-hidden="true"></span>
								<?php esc_html_e( 'Published', 'ceafsn-pp' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $pp_published ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Visible to visitors', 'ceafsn-pp' ); ?></span>
						</div>

						<div class="ceafsn-kpi">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot ceafsn-dot--gold" aria-hidden="true"></span>
								<?php esc_html_e( 'Drafts', 'ceafsn-pp' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $pp_drafts ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Not yet public', 'ceafsn-pp' ); ?></span>
						</div>

						<div class="ceafsn-kpi <?php echo $pp_no_pdf > 0 ? 'ceafsn-kpi--alert' : ''; ?>">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot <?php echo $pp_no_pdf > 0 ? 'ceafsn-dot--gold' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
								<?php esc_html_e( 'No document', 'ceafsn-pp' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $pp_no_pdf ) ); ?></span>
							<span class="ceafsn-kpi__meta">
								<?php
								if ( $pp_no_pdf > 0 ) {
									esc_html_e( 'Cannot be published', 'ceafsn-pp' );
								} else {
									esc_html_e( 'Every record has one', 'ceafsn-pp' );
								}
								?>
							</span>
						</div>
					</div>

				<?php endif; ?>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title">
								<?php esc_html_e( 'All records', 'ceafsn-pp' ); ?>
								<?php if ( $pp_total > 0 ) : ?>
									<span class="ceafsn-pill ceafsn-pill--green"><?php echo esc_html( number_format_i18n( $pp_total ) ); ?></span>
								<?php endif; ?>
							</h2>
							<?php if ( $pp_total > 0 ) : ?>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'Draft, archived, and members-only records are visible to admins only.', 'ceafsn-pp' ); ?></p>
							<?php endif; ?>
						</div>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $add_url ); ?>">
							<?php esc_html_e( '+ Add record', 'ceafsn-pp' ); ?>
						</a>
					</div>

					<?php if ( 0 === $pp_total ) : ?>

						<div class="ceafsn-empty">
							<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
							<h3 class="ceafsn-empty__title"><?php esc_html_e( 'Nothing to show yet', 'ceafsn-pp' ); ?></h3>
							<p class="ceafsn-empty__text">
								<?php esc_html_e( 'Records stay invisible on the public page until they are published with their own validated PDF.', 'ceafsn-pp' ); ?>
							</p>
							<div class="ceafsn-empty__action">
								<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $add_url ); ?>">
									<?php esc_html_e( 'Add the first record', 'ceafsn-pp' ); ?>
								</a>
							</div>
						</div>

					<?php else : ?>

						<div class="ceafsn-table-wrap">
							<table class="ceafsn-table">
								<caption class="ceafsn-sr"><?php esc_html_e( 'Publication and project records', 'ceafsn-pp' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-pp' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Type', 'ceafsn-pp' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Author / institution', 'ceafsn-pp' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Date', 'ceafsn-pp' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Project status', 'ceafsn-pp' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Document', 'ceafsn-pp' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Access', 'ceafsn-pp' ); ?></th>
										<th scope="col"><?php esc_html_e( 'State', 'ceafsn-pp' ); ?></th>
										<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-pp' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ( $items as $item ) :
										$item_status    = (string) $item->status;
										$item_project   = (string) $item->project_status;
										$item_pdf       = (int) $item->pdf_attachment_id;
										$item_dup       = (int) $item->duplicate_ok;
										$item_members   = 'members_only' === (string) $item->access_level;
										$item_status_mod = str_replace( '_', '-', $item_project );
										?>
										<tr>
											<td>
												<span class="ceafsn-table__title">
													<span class="ceafsn-dot <?php echo 'published' === $item_status ? 'ceafsn-dot--green' : 'ceafsn-dot--grey'; ?>" aria-hidden="true"></span>
													<?php echo esc_html( (string) $item->title ); ?>
<span class="ceafsn-table__meta">
														<?php
														// A record with no document yet has no page count worth showing.
														echo esc_html(
															$item_pdf > 0
																? sprintf(
																	/* translators: 1: page count, 2: record date. */
																	__( '%1$s · %2$s', 'ceafsn-pp' ),
																	sprintf(
																		/* translators: %s: page count. */
																		_n( '%s page', '%s pages', max( 0, (int) $item->page_count ), 'ceafsn-pp' ),
																		number_format_i18n( max( 0, (int) $item->page_count ) )
																	),
																	(string) $item->publication_date
																)
																: sprintf(
																	/* translators: %s: record date. */
																	__( 'No document yet · %s', 'ceafsn-pp' ),
																	(string) $item->publication_date
																)
														);
														?>
													</span>
												</span>
											</td>
											<td><?php echo esc_html( CEAFSN_PP_Public::type_label( (string) $item->content_type ) ); ?></td>
											<td><?php echo esc_html( (string) $item->author_institution ); ?></td>
											<td><?php echo esc_html( (string) $item->publication_date ); ?></td>
											<td>
												<span class="ceafsn-badge ceafsn-badge--<?php echo esc_attr( $item_status_mod ); ?>">
													<?php echo esc_html( CEAFSN_PP_Public::status_label( $item_project ) ); ?>
												</span>
											</td>
											<td>
												<?php if ( $item_pdf > 0 ) : ?>
													<span class="ceafsn-badge ceafsn-badge--ok">
														<?php esc_html_e( 'PDF', 'ceafsn-pp' ); ?>
														<?php if ( $item_dup ) : ?>
															<?php esc_html_e( '(shared)', 'ceafsn-pp' ); ?>
														<?php endif; ?>
													</span>
												<?php else : ?>
													<span class="ceafsn-badge ceafsn-badge--warn"><?php esc_html_e( 'No PDF', 'ceafsn-pp' ); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( $item_members ) : ?>
													<span class="ceafsn-badge ceafsn-badge--members"><?php esc_html_e( 'Members', 'ceafsn-pp' ); ?></span>
												<?php else : ?>
													<span class="ceafsn-badge ceafsn-badge--public"><?php esc_html_e( 'Public', 'ceafsn-pp' ); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( $item_status ); ?>">
													<?php echo esc_html( ucfirst( $item_status ) ); ?>
												</span>
											</td>
											<td class="ceafsn-table__actions">
												<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->publication_id ), $base_url ) ); ?>">
													<?php esc_html_e( 'Edit', 'ceafsn-pp' ); ?>
												</a>
												<a class="ceafsn-pp-delete-link"
													href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_pp_delete_publication&id=' . (int) $item->publication_id ), 'ceafsn_pp_delete_publication_' . (int) $item->publication_id ) ); ?>">
													<?php esc_html_e( 'Delete', 'ceafsn-pp' ); ?>
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

			<aside class="ceafsn-app__rail">

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Get started', 'ceafsn-pp' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add a record', 'ceafsn-pp' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'Each publication needs its own document. Two records should not share one PDF unless it is a known placeholder.', 'ceafsn-pp' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( $add_url ); ?>">
						<?php esc_html_e( '+ Add record', 'ceafsn-pp' ); ?>
					</a>
				</div>

				<div class="ceafsn-cta" id="ceafsn-pp-help">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Before you publish', 'ceafsn-pp' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Document rules', 'ceafsn-pp' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'The PDF must be readable and belong to this record alone.', 'ceafsn-pp' ); ?></li>
						<li><?php esc_html_e( 'A page count that disagrees with the file blocks publishing.', 'ceafsn-pp' ); ?></li>
						<li><?php esc_html_e( 'Scanned documents need the scanned flag ticked.', 'ceafsn-pp' ); ?></li>
						<li><?php esc_html_e( 'Project status is tracked separately from the published state.', 'ceafsn-pp' ); ?></li>
						<li><?php esc_html_e( 'Files stay in the media library even if the record is deleted.', 'ceafsn-pp' ); ?></li>
					</ul>
				</div>

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Keep a copy', 'ceafsn-pp' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Export records', 'ceafsn-pp' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'Download every publication and project record as JSON before bulk changes or removing the plugin.', 'ceafsn-pp' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--ghost ceafsn-btn--block" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::SETTINGS_SLUG . '&tab=export' ) ); ?>">
						<?php esc_html_e( 'Open export', 'ceafsn-pp' ); ?>
					</a>
				</div>

			</aside>

		</div><!-- .ceafsn-app -->

	<?php else : ?>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-pp' ); ?></p>
			<h1 class="ceafsn-hero__title">
				<?php
				echo esc_html(
					$row
						? __( 'Edit record', 'ceafsn-pp' )
						: __( 'Add record', 'ceafsn-pp' )
				);
				?>
			</h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'A record can only be published when its own PDF is readable and its page count matches the file.', 'ceafsn-pp' ); ?>
			</p>
		</section>

		<?php if ( $row && $validation && ! $validation['valid'] && 'published' === $row->status ) : ?>
			<div class="ceafsn-alerts">
				<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
					<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'This published record no longer passes PDF validation:', 'ceafsn-pp' ); ?></p>
						<ul class="ceafsn-alert__list">
							<?php foreach ( $validation['errors'] as $error ) : ?>
								<li><?php echo esc_html( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $row && $duplicate_count > 0 ) : ?>
			<div class="ceafsn-alerts">
				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title">
							<?php
							printf(
								/* translators: %d: number of other records. */
								esc_html( _n(
									'The same document is attached to %d other record.',
									'The same document is attached to %d other records.',
									$duplicate_count,
									'ceafsn-pp'
								) ),
								(int) $duplicate_count
							);
							?>
						</p>
						<?php esc_html_e( 'Each publication should have its own document.', 'ceafsn-pp' ); ?>
						<?php if ( ! empty( $shared_with ) ) : ?>
							<ul class="ceafsn-pp-shared-list">
								<?php foreach ( $shared_with as $other ) : ?>
									<li>
										<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $other->publication_id ), $base_url ) ); ?>">
											<?php echo esc_html( (string) $other->title ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<form class="ceafsn-form ceafsn-form__spaced" method="post" action="<?php echo esc_url( $form_url ); ?>">
			<?php wp_nonce_field( 'ceafsn_pp_publication_nonce', 'ceafsn_pp_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_pp_save_publication" />
			<input type="hidden" name="publication_id" value="<?php echo esc_attr( (string) ( $id > 0 ? $id : 0 ) ); ?>" />

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Record details', 'ceafsn-pp' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Fields marked with an asterisk are required.', 'ceafsn-pp' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<label for="ceafsn-pp-title">
							<?php esc_html_e( 'Title', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-pp-title" name="title" required
							value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-content-type">
							<?php esc_html_e( 'Content type', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<select id="ceafsn-pp-content-type" name="content_type" required>
							<?php foreach ( $types as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>"
									<?php selected( $type, (string) ( $row->content_type ?? 'report' ) ); ?>>
									<?php echo esc_html( CEAFSN_PP_Public::type_label( $type ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-summary">
							<?php esc_html_e( 'Executive summary', 'ceafsn-pp' ); ?>
						</label>
						<textarea id="ceafsn-pp-summary" name="executive_summary" rows="4"><?php echo esc_textarea( (string) ( $row->executive_summary ?? '' ) ); ?></textarea>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Shown on the record card. Leave empty to hide the summary rather than repeat the title.', 'ceafsn-pp' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-author">
							<?php esc_html_e( 'Author / institution', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-pp-author" name="author_institution" required
							value="<?php echo esc_attr( (string) ( $row->author_institution ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-date">
							<?php esc_html_e( 'Publication date', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="date" id="ceafsn-pp-date" name="publication_date" required
							value="<?php echo esc_attr( (string) ( $row->publication_date ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-project-status">
							<?php esc_html_e( 'Project status', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<select id="ceafsn-pp-project-status" name="project_status" required>
							<?php foreach ( $projects as $status_key ) : ?>
								<option value="<?php echo esc_attr( $status_key ); ?>"
									<?php selected( $status_key, (string) ( $row->project_status ?? 'in_progress' ) ); ?>>
									<?php echo esc_html( CEAFSN_PP_Public::status_label( $status_key ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Document', 'ceafsn-pp' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'One PDF per record, validated on save.', 'ceafsn-pp' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<label for="ceafsn-pp-pdf-field">
							<?php esc_html_e( 'PDF attachment', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<div class="ceafsn-pp-media">
							<input type="hidden" id="ceafsn-pp-pdf-id" name="pdf_attachment_id"
								value="<?php echo esc_attr( (string) $pdf_attachment_id ); ?>" />
							<input type="text" id="ceafsn-pp-pdf-field" class="ceafsn-pp-media__name" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-pp' ); ?>"
								value="<?php echo esc_attr( $pdf_attachment_id > 0 ? __( 'Document attached', 'ceafsn-pp' ) : '' ); ?>" />
							<button type="button" class="ceafsn-btn ceafsn-btn--quiet" id="ceafsn-pp-pdf-button"
								data-target="ceafsn-pp-pdf-id">
								<?php esc_html_e( 'Select PDF', 'ceafsn-pp' ); ?>
							</button>
							<button type="button" class="ceafsn-pp-media__clear" id="ceafsn-pp-pdf-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-pp' ); ?>
							</button>
						</div>
						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'A record can only be published when this PDF is readable, belongs to this record alone, and its page count matches the field below.', 'ceafsn-pp' ); ?>
						</p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-page-count">
							<?php esc_html_e( 'Page count', 'ceafsn-pp' ); ?>
						</label>
						<input type="number" id="ceafsn-pp-page-count" name="page_count" min="0" step="1"
							value="<?php echo esc_attr( (string) ( $row->page_count ?? 0 ) ); ?>" />
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Leave at 0 if you do not know. A value that disagrees with the document blocks publishing.', 'ceafsn-pp' ); ?></p>
					</div>

					<?php if ( $row && $validation ) : ?>
						<p class="ceafsn-pp-validation ceafsn-pp-validation--<?php echo esc_attr( $validation['valid'] ? 'ok' : 'fail' ); ?>">
							<?php
							if ( $validation['valid'] ) {
								printf(
									/* translators: %d: page count. */
									esc_html__( 'PDF verified: readable, %d page(s).', 'ceafsn-pp' ),
									(int) $validation['pages']
								);
							} else {
								echo esc_html( implode( ' ', $validation['errors'] ) );
							}
							?>
						</p>
					<?php endif; ?>

				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Cover image', 'ceafsn-pp' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Optional. A record without a cover image is shown as a text-only card.', 'ceafsn-pp' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<span class="ceafsn-field__label" id="ceafsn-pp-cover-label"><?php esc_html_e( 'Image', 'ceafsn-pp' ); ?></span>
						<div class="ceafsn-pp-media">
							<input type="hidden" id="ceafsn-pp-cover-id" name="cover_image_id"
								value="<?php echo esc_attr( (string) $cover_image_id ); ?>" />
							<input type="text" id="ceafsn-pp-cover-field" class="ceafsn-pp-media__name" readonly
								aria-labelledby="ceafsn-pp-cover-label"
								placeholder="<?php esc_attr_e( 'No image selected', 'ceafsn-pp' ); ?>"
								value="<?php echo esc_attr( $cover_image_name ); ?>" />
							<button type="button" class="ceafsn-btn ceafsn-btn--quiet" id="ceafsn-pp-cover-button"
								data-target="ceafsn-pp-cover-id">
								<?php esc_html_e( 'Select image', 'ceafsn-pp' ); ?>
							</button>
							<button type="button" class="ceafsn-pp-media__clear" id="ceafsn-pp-cover-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-pp' ); ?>
							</button>
						</div>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-cover-alt">
							<?php esc_html_e( 'Cover image alt text', 'ceafsn-pp' ); ?>
						</label>
						<input type="text" id="ceafsn-pp-cover-alt" name="cover_image_alt"
							aria-describedby="ceafsn-pp-cover-alt-help"
							value="<?php echo esc_attr( (string) ( $row->cover_image_alt ?? '' ) ); ?>" />
						<p class="ceafsn-field__hint" id="ceafsn-pp-cover-alt-help">
							<?php esc_html_e( 'Required when a cover image is set. Describe what the image shows.', 'ceafsn-pp' ); ?>
						</p>
					</div>

				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Visibility and sharing', 'ceafsn-pp' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Only a record whose document passes every check can be Published.', 'ceafsn-pp' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<label for="ceafsn-pp-status">
							<?php esc_html_e( 'Published state', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<select id="ceafsn-pp-status" name="status" required>
							<?php foreach ( $statuses as $status_value ) : ?>
								<option value="<?php echo esc_attr( $status_value ); ?>"
									<?php selected( $status_value, (string) ( $row->status ?? 'draft' ) ); ?>>
									<?php echo esc_html( ucfirst( $status_value ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-access">
							<?php esc_html_e( 'Access level', 'ceafsn-pp' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<select id="ceafsn-pp-access" name="access_level" required>
							<?php foreach ( $access as $access_value ) : ?>
								<option value="<?php echo esc_attr( $access_value ); ?>"
									<?php selected( $access_value, (string) ( $row->access_level ?? 'public' ) ); ?>>
									<?php
									echo esc_html(
										'members_only' === $access_value
											? __( 'Members Only', 'ceafsn-pp' )
											: __( 'Public', 'ceafsn-pp' )
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Members-only records are hidden from logged-out visitors and cannot be linked to directly.', 'ceafsn-pp' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-doi">
							<?php esc_html_e( 'DOI / citation URL', 'ceafsn-pp' ); ?>
						</label>
						<input type="url" id="ceafsn-pp-doi" name="doi_citation"
							value="<?php echo esc_attr( (string) ( $row->doi_citation ?? '' ) ); ?>" />
					</div>

				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Document flags', 'ceafsn-pp' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Only tick these when they are true — they suppress validation warnings.', 'ceafsn-pp' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-check">
						<input type="checkbox" name="scanned" value="1" id="ceafsn-pp-scanned"
							<?php checked( true, 1 === (int) ( $row->scanned ?? 0 ) ); ?>>
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-pp-scanned">
								<?php esc_html_e( 'This is a scanned document (no extractable text is expected)', 'ceafsn-pp' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Use this for photocopies and scans so a missing text layer is not treated as a broken file.', 'ceafsn-pp' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-check">
						<input type="checkbox" name="duplicate_ok" value="1" id="ceafsn-pp-duplicate-ok"
							<?php checked( true, 1 === (int) ( $row->duplicate_ok ?? 0 ) ); ?>>
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-pp-duplicate-ok">
								<?php esc_html_e( 'This document is intentionally shared, or is a known placeholder', 'ceafsn-pp' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Add a note below explaining why this document is used more than once.', 'ceafsn-pp' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-pp-duplicate-note">
							<?php esc_html_e( 'Sharing / placeholder note', 'ceafsn-pp' ); ?>
						</label>
						<textarea id="ceafsn-pp-duplicate-note" name="duplicate_note" rows="3"
							aria-describedby="ceafsn-pp-duplicate-note-help"><?php echo esc_textarea( (string) ( $row->duplicate_note ?? '' ) ); ?></textarea>
						<p class="ceafsn-field__hint" id="ceafsn-pp-duplicate-note-help">
							<?php esc_html_e( 'Required when the box above is ticked. Explain why this document is used more than once.', 'ceafsn-pp' ); ?>
						</p>
					</div>

				</div>
			</section>

			<div class="ceafsn-form__actions">
				<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Save record', 'ceafsn-pp' ); ?></button>
				<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-pp' ); ?></a>
			</div>
		</form>

	<?php endif; ?>

	</div><!-- #ceafsn-pp-main -->

	<a class="ceafsn-help" href="#ceafsn-pp-help" aria-label="<?php esc_attr_e( 'Jump to document rules', 'ceafsn-pp' ); ?>">?</a>

</div><!-- .ceafsn-pp-wrap -->