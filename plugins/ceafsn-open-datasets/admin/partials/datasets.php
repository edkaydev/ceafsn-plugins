<?php
/**
 * Admin partial: Datasets list, add, and edit view.
 *
 * Layout: WordPress admin menu on the left, a main canvas in the middle, and a
 * narrower right rail for calls to action and publishing guidance.
 *
 * Variables in scope (set by CEAFSN_OD_Admin::page_datasets()):
 *   @var string            $action     'list' | 'add' | 'edit'
 *   @var int               $id         Row ID when editing
 *   @var object|null       $row        DB row when editing
 *   @var array<int,object> $items      All dataset rows
 *   @var array<int,string> $categories Distinct published categories
 *   @var array|null        $validation Validator result for the download target
 *
 * Element IDs ceafsn-od-file-id, ceafsn-od-file-field, ceafsn-od-file-type,
 * ceafsn-od-media-button, ceafsn-od-media-clear, and the class
 * ceafsn-od-delete-link are a contract with assets/js/ceafsn-od-admin.js.
 * Do not rename them.
 *
 * Every count below is counted from stored rows. Nothing is estimated or
 * filled in with placeholder data, and a workflow step is only marked
 * complete when the underlying count proves it.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

// Notices. The error text arrives query-encoded so it survives a redirect;
// it is decoded once here and escaped on output.
$notice_error = CEAFSN_OD_Request::has( 'ceafsn_od_error' )
	? sanitize_text_field( urldecode( CEAFSN_OD_Request::text( 'ceafsn_od_error' ) ) )
	: '';
$notice_saved   = CEAFSN_OD_Request::has( 'saved' );
$notice_deleted = CEAFSN_OD_Request::has( 'deleted' );

$base_url   = admin_url( 'admin.php?page=' . CEAFSN_OD_Admin::MENU_SLUG );
$form_url   = admin_url( 'admin-post.php' );
$statuses   = CEAFSN_OD_DB::statuses();
$file_types = CEAFSN_OD_DB::file_type_labels();
$allow_other = CEAFSN_OD_Admin::other_files_allowed();
$is_list    = 'list' === $action;
$add_url    = add_query_arg( array( 'action' => 'add' ), $base_url );
?>

<div class="wrap ceafsn-od-wrap">

	<a class="ceafsn-sr" href="#ceafsn-od-main"><?php esc_html_e( 'Skip to dataset content', 'ceafsn-od' ); ?></a>

	<div id="ceafsn-od-main">

	<?php if ( '' !== $notice_error ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
				<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title"><?php esc_html_e( 'That change was not saved', 'ceafsn-od' ); ?></p>
					<p class="ceafsn-alert__text"><?php echo esc_html( $notice_error ); ?></p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $notice_saved || $notice_deleted ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--ok" role="status">
				<span class="ceafsn-alert__icon" aria-hidden="true">&#10003;</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title">
						<?php
						echo esc_html(
							$notice_deleted
								? __( 'Dataset record deleted.', 'ceafsn-od' )
								: __( 'Dataset record saved.', 'ceafsn-od' )
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
		$od_total      = count( $items );
		$od_published  = 0;
		$od_with_file  = 0;
		$od_without    = 0;
		foreach ( $items as $od_row ) {
			if ( 'published' === (string) $od_row->status ) {
				++$od_published;
			}
			if ( (int) $od_row->file_attachment_id > 0 || '' !== (string) $od_row->download_url ) {
				++$od_with_file;
			} else {
				++$od_without;
			}
		}

		// Publishing workflow. The first step still at zero is the current one.
		$od_steps = array(
			array(
				'label' => __( 'Records added', 'ceafsn-od' ),
				'meta'  => sprintf( /* translators: %s: number of records. */ __( '%s datasets', 'ceafsn-od' ), number_format_i18n( $od_total ) ),
				'count' => $od_total,
			),
			array(
				'label' => __( 'Download attached', 'ceafsn-od' ),
				'meta'  => sprintf( /* translators: %s: number of records with a download target. */ __( '%s with a file or URL', 'ceafsn-od' ), number_format_i18n( $od_with_file ) ),
				'count' => $od_with_file,
			),
			array(
				'label' => __( 'Published', 'ceafsn-od' ),
				'meta'  => sprintf( /* translators: %s: number of published records. */ __( '%s live on the public page', 'ceafsn-od' ), number_format_i18n( $od_published ) ),
				'count' => $od_published,
			),
		);

		$od_current_step = null;
		foreach ( $od_steps as $od_index => $od_step ) {
			if ( $od_step['count'] > 0 ) {
				$od_steps[ $od_index ]['state'] = 'done';
			} elseif ( null === $od_current_step ) {
				$od_steps[ $od_index ]['state'] = 'current';
				$od_current_step                = $od_index;
			} else {
				$od_steps[ $od_index ]['state'] = 'todo';
			}
		}
		?>

		<div class="ceafsn-app">

			<div class="ceafsn-app__main">

				<?php if ( 0 === $od_total ) : ?>

					<div class="ceafsn-alerts">
						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title"><?php esc_html_e( 'No dataset records yet', 'ceafsn-od' ); ?></p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'Nothing is displayed on the public page until a record is published with a working download target.', 'ceafsn-od' ); ?>
									<a class="ceafsn-alert__action" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add the first dataset', 'ceafsn-od' ); ?></a>
								</p>
							</div>
						</div>
					</div>

				<?php elseif ( $od_without > 0 ) : ?>

					<div class="ceafsn-alerts">
						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title">
									<?php
									printf(
										/* translators: %s: number of records with no download target. */
										esc_html__( '%s record(s) have no download target', 'ceafsn-od' ),
										esc_html( number_format_i18n( $od_without ) )
									);
									?>
								</p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'A record can only be published once its file or URL resolves.', 'ceafsn-od' ); ?>
								</p>
							</div>
						</div>
					</div>

				<?php endif; ?>

				<section class="ceafsn-hero">
					<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'Open Datasets', 'ceafsn-od' ); ?></p>
					<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Dataset records', 'ceafsn-od' ); ?></h1>
					<p class="ceafsn-hero__text">
						<?php esc_html_e( 'Every record carries its own file, source, and license, so a published download can always be traced back to where it came from.', 'ceafsn-od' ); ?>
					</p>
					<div class="ceafsn-hero__actions">
						<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $add_url ); ?>">
							<?php esc_html_e( '+ Add dataset', 'ceafsn-od' ); ?>
						</a>
						<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_OD_Admin::SETTINGS_SLUG . '&tab=export' ) ); ?>">
							<?php esc_html_e( 'Export', 'ceafsn-od' ); ?>
						</a>
					</div>
					<p class="ceafsn-hero__meta">
						<span>
							<?php
							printf(
								/* translators: %s: total record count. */
								wp_kses_post( __( '<strong>%s</strong> records', 'ceafsn-od' ) ),
								esc_html( number_format_i18n( $od_total ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: published record count. */
								wp_kses_post( __( '<strong>%s</strong> published', 'ceafsn-od' ) ),
								esc_html( number_format_i18n( $od_published ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: number of distinct categories. */
								wp_kses_post( __( '<strong>%s</strong> categories', 'ceafsn-od' ) ),
								esc_html( number_format_i18n( count( $categories ) ) )
							);
							?>
						</span>
					</p>
				</section>

				<?php if ( $od_total > 0 ) : ?>

					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<div>
								<h2 class="ceafsn-card__title"><?php esc_html_e( 'Publishing workflow', 'ceafsn-od' ); ?></h2>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'Counted from your records. A step is only complete when the numbers prove it.', 'ceafsn-od' ); ?></p>
							</div>
						</div>
						<div class="ceafsn-card__body">
							<ol class="ceafsn-stepper">
								<?php foreach ( $od_steps as $od_index => $od_step ) : ?>
									<li class="ceafsn-step is-<?php echo esc_attr( $od_step['state'] ); ?>">
										<span class="ceafsn-step__dot" aria-hidden="true">
											<?php
											if ( 'done' === $od_step['state'] ) {
												echo '&#10003;';
											} else {
												echo esc_html( (string) ( $od_index + 1 ) );
											}
											?>
										</span>
										<span class="ceafsn-step__label"><?php echo esc_html( $od_step['label'] ); ?></span>
										<span class="ceafsn-step__meta"><?php echo esc_html( $od_step['meta'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ol>
							<p class="ceafsn-card__hint">
								<?php esc_html_e( 'Current step:', 'ceafsn-od' ); ?>
								<strong>
									<?php
									echo esc_html(
										null !== $od_current_step
											? $od_steps[ $od_current_step ]['label']
											: __( 'All steps complete', 'ceafsn-od' )
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
								<?php esc_html_e( 'Records', 'ceafsn-od' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $od_total ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'In this plugin', 'ceafsn-od' ); ?></span>
						</div>

						<div class="ceafsn-kpi">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot ceafsn-dot--green" aria-hidden="true"></span>
								<?php esc_html_e( 'Published', 'ceafsn-od' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $od_published ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Visible to visitors', 'ceafsn-od' ); ?></span>
						</div>

						<div class="ceafsn-kpi">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot ceafsn-dot--green" aria-hidden="true"></span>
								<?php esc_html_e( 'With download', 'ceafsn-od' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $od_with_file ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'File or URL', 'ceafsn-od' ); ?></span>
						</div>

						<div class="ceafsn-kpi<?php echo $od_without > 0 ? 'ceafsn-kpi--alert' : ''; ?>">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot <?php echo $od_without > 0 ? 'ceafsn-dot--gold' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
								<?php esc_html_e( 'No download', 'ceafsn-od' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $od_without ) ); ?></span>
							<span class="ceafsn-kpi__meta">
								<?php
								if ( $od_without > 0 ) {
									esc_html_e( 'Cannot be published', 'ceafsn-od' );
								} else {
									esc_html_e( 'Every record resolves', 'ceafsn-od' );
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
								<?php esc_html_e( 'All datasets', 'ceafsn-od' ); ?>
								<?php if ( $od_total > 0 ) : ?>
									<span class="ceafsn-pill ceafsn-pill--green"><?php echo esc_html( number_format_i18n( $od_total ) ); ?></span>
								<?php endif; ?>
							</h2>
							<?php if ( $od_total > 0 ) : ?>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'Draft and archived records are visible to admins only.', 'ceafsn-od' ); ?></p>
							<?php endif; ?>
						</div>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $add_url ); ?>">
							<?php esc_html_e( '+ Add dataset', 'ceafsn-od' ); ?>
						</a>
					</div>

					<?php if ( 0 === $od_total ) : ?>

						<div class="ceafsn-empty">
							<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
							<h3 class="ceafsn-empty__title"><?php esc_html_e( 'Nothing to show yet', 'ceafsn-od' ); ?></h3>
							<p class="ceafsn-empty__text">
								<?php esc_html_e( 'Records stay invisible on the public page until they are published with a working download target.', 'ceafsn-od' ); ?>
							</p>
							<div class="ceafsn-empty__action">
								<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $add_url ); ?>">
									<?php esc_html_e( 'Add the first dataset', 'ceafsn-od' ); ?>
								</a>
							</div>
						</div>

					<?php else : ?>

						<div class="ceafsn-table-wrap">
							<table class="ceafsn-table">
								<caption class="ceafsn-sr"><?php esc_html_e( 'Dataset records', 'ceafsn-od' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Dataset', 'ceafsn-od' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Category', 'ceafsn-od' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Coverage', 'ceafsn-od' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Updated', 'ceafsn-od' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Type', 'ceafsn-od' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Download', 'ceafsn-od' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-od' ); ?></th>
										<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-od' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ( $items as $item ) :
										$item_status   = (string) $item->status;
										$item_type     = (string) $item->file_type;
										$item_attached = (int) $item->file_attachment_id;
										$item_url      = (string) $item->download_url;
										$has_download  = $item_attached > 0 || '' !== $item_url;
										?>
										<tr>
											<td>
												<span class="ceafsn-table__title">
													<span class="ceafsn-dot <?php echo 'published' === $item_status ? 'ceafsn-dot--green' : 'ceafsn-dot--grey'; ?>" aria-hidden="true"></span>
													<?php echo esc_html( (string) $item->name ); ?>
													<span class="ceafsn-table__meta">
														<?php echo esc_html( CEAFSN_OD_Validator::format_size( (int) $item->file_size ) ); ?>
													</span>
												</span>
											</td>
											<td><?php echo esc_html( (string) $item->category ); ?></td>
											<td><?php echo esc_html( (string) $item->coverage_area ); ?></td>
											<td><?php echo esc_html( (string) $item->last_updated ); ?></td>
											<td><?php echo esc_html( (string) ( $file_types[ $item_type ] ?? strtoupper( $item_type ) ) ); ?></td>
											<td>
												<?php if ( $has_download ) : ?>
													<span class="ceafsn-badge ceafsn-badge--ok">
														<?php echo esc_html( $item_attached > 0 ? __( 'File', 'ceafsn-od' ) : __( 'URL', 'ceafsn-od' ) ); ?>
													</span>
												<?php else : ?>
													<span class="ceafsn-badge ceafsn-badge--warn"><?php esc_html_e( 'No download', 'ceafsn-od' ); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( $item_status ); ?>">
													<?php echo esc_html( (string) ( CEAFSN_OD_DB::status_labels()[ $item_status ] ?? ucfirst( $item_status ) ) ); ?>
												</span>
											</td>
											<td class="ceafsn-table__actions">
												<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->dataset_id ), $base_url ) ); ?>">
													<?php esc_html_e( 'Edit', 'ceafsn-od' ); ?>
												</a>
												<a class="ceafsn-od-delete-link"
													href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_od_delete_dataset&id=' . (int) $item->dataset_id ), 'ceafsn_od_delete_dataset_' . (int) $item->dataset_id ) ); ?>">
													<?php esc_html_e( 'Delete', 'ceafsn-od' ); ?>
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
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Get started', 'ceafsn-od' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add a dataset', 'ceafsn-od' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'Provide one download route per record — a file from the media library, or a URL that returns a file.', 'ceafsn-od' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( $add_url ); ?>">
						<?php esc_html_e( '+ Add dataset', 'ceafsn-od' ); ?>
					</a>
				</div>

				<div class="ceafsn-cta" id="ceafsn-od-help">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Before you publish', 'ceafsn-od' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Download rules', 'ceafsn-od' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'A record needs a file or a URL before it can be published.', 'ceafsn-od' ); ?></li>
						<li><?php esc_html_e( 'The declared file type is checked against the file itself.', 'ceafsn-od' ); ?></li>
						<li><?php esc_html_e( 'Every record needs a license, a coverage area, and a last-updated date.', 'ceafsn-od' ); ?></li>
						<li><?php esc_html_e( 'Files stay in the media library even if the record is deleted.', 'ceafsn-od' ); ?></li>
					</ul>
				</div>

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Keep a copy', 'ceafsn-od' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Export records', 'ceafsn-od' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'Download every dataset record as JSON before bulk changes or removing the plugin.', 'ceafsn-od' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--ghost ceafsn-btn--block" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_OD_Admin::SETTINGS_SLUG . '&tab=export' ) ); ?>">
						<?php esc_html_e( 'Open export', 'ceafsn-od' ); ?>
					</a>
				</div>

			</aside>

		</div><!-- .ceafsn-app -->

	<?php else : ?>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'Open Datasets', 'ceafsn-od' ); ?></p>
			<h1 class="ceafsn-hero__title">
				<?php
				echo esc_html(
					$row
						? __( 'Edit dataset', 'ceafsn-od' )
						: __( 'Add dataset', 'ceafsn-od' )
				);
				?>
			</h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'The file name and size are read from the attachment itself, never from this form.', 'ceafsn-od' ); ?>
			</p>
		</section>

		<?php if ( $row && $validation && ! $validation['valid'] ) : ?>
			<div class="ceafsn-alerts">
				<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
					<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title">
							<?php
							echo esc_html(
								'published' === $row->status
									? __( 'This published record no longer has a working download:', 'ceafsn-od' )
									: __( 'This record has no working download:', 'ceafsn-od' )
							);
							?>
						</p>
						<ul class="ceafsn-alert__list">
							<?php foreach ( $validation['errors'] as $error ) : ?>
								<li><?php echo esc_html( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<form class="ceafsn-form ceafsn-form__spaced" method="post" action="<?php echo esc_url( $form_url ); ?>">
			<?php wp_nonce_field( 'ceafsn_od_dataset_nonce', 'ceafsn_od_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_od_save_dataset" />
			<input type="hidden" name="dataset_id" value="<?php echo esc_attr( (string) ( $id > 0 ? $id : 0 ) ); ?>" />

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Dataset details', 'ceafsn-od' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Fields marked with an asterisk are required.', 'ceafsn-od' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<label for="ceafsn-od-name">
							<?php esc_html_e( 'Dataset name', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-od-name" name="name" required
							value="<?php echo esc_attr( (string) ( $row->name ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-description">
							<?php esc_html_e( 'Description', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<textarea id="ceafsn-od-description" name="description" rows="5" required><?php echo esc_textarea( (string) ( $row->description ?? '' ) ); ?></textarea>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'What the dataset contains, how it was collected, and any known limitations.', 'ceafsn-od' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-category">
							<?php esc_html_e( 'Category / sector', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-od-category" name="category" list="ceafsn-od-category-list" required
							value="<?php echo esc_attr( (string) ( $row->category ?? '' ) ); ?>" />
						<datalist id="ceafsn-od-category-list">
							<?php foreach ( $categories as $category ) : ?>
								<option value="<?php echo esc_attr( $category ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-coverage">
							<?php esc_html_e( 'Coverage area', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-od-coverage" name="coverage_area" required
							value="<?php echo esc_attr( (string) ( $row->coverage_area ?? '' ) ); ?>" />
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Geographic or administrative scope, e.g. "All 15 counties" or "Northern Province".', 'ceafsn-od' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-last-updated">
							<?php esc_html_e( 'Last updated', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="date" id="ceafsn-od-last-updated" name="last_updated" required
							value="<?php echo esc_attr( (string) ( $row->last_updated ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-license">
							<?php esc_html_e( 'Data license', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-od-license" name="data_license" list="ceafsn-od-license-list" required
							value="<?php echo esc_attr( (string) ( $row->data_license ?? '' ) ); ?>" />
						<datalist id="ceafsn-od-license-list">
							<option value="CC BY 4.0"></option>
							<option value="CC BY-SA 4.0"></option>
							<option value="CC0 1.0"></option>
							<option value="ODbL"></option>
						</datalist>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-contact">
							<?php esc_html_e( 'Contact / owner', 'ceafsn-od' ); ?>
						</label>
						<input type="text" id="ceafsn-od-contact" name="contact_owner"
							value="<?php echo esc_attr( (string) ( $row->contact_owner ?? '' ) ); ?>" />
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Kept private unless "Show contact on the public page" is enabled in Settings.', 'ceafsn-od' ); ?></p>
					</div>

				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Download target', 'ceafsn-od' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Provide one route, not both.', 'ceafsn-od' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<label for="ceafsn-od-file-type">
							<?php esc_html_e( 'File type', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<select id="ceafsn-od-file-type" name="file_type" data-ceafsn-od="file-type">
							<?php foreach ( $file_types as $type_value => $type_label ) : ?>
								<?php if ( 'other' === $type_value && ! $allow_other ) : ?>
									<?php continue; ?>
								<?php endif; ?>
								<option value="<?php echo esc_attr( (string) $type_value ); ?>"
									<?php selected( $type_value, (string) ( $row->file_type ?? 'csv' ) ); ?>>
									<?php echo esc_html( (string) $type_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'The type is used to validate the file. Pick the real format, not a guess.', 'ceafsn-od' ); ?></p>
						<?php if ( ! $allow_other ) : ?>
							<p class="ceafsn-field__hint">
								<?php esc_html_e( 'The "other" file type is not enabled. Turn it on in Settings if you need a format this plugin cannot inspect.', 'ceafsn-od' ); ?>
							</p>
						<?php endif; ?>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-file-field">
							<?php esc_html_e( 'Dataset file', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<?php
						// The displayed file name is derived from the attachment on
						// disk, never from a submitted value, so a crafted form
						// cannot show a misleading name.
						$selected_filename = '';
						$selected_id       = (int) ( $row->file_attachment_id ?? 0 );
						if ( $selected_id > 0 ) {
							$selected_path = get_attached_file( $selected_id );
							if ( $selected_path ) {
								$selected_filename = basename( (string) wp_basename( $selected_path ) );
							}
						}
						?>
						<div class="ceafsn-od-media">
							<input type="hidden" id="ceafsn-od-file-id" name="file_attachment_id"
								value="<?php echo esc_attr( (string) $selected_id ); ?>" />
							<input type="text" id="ceafsn-od-file-field" class="ceafsn-od-media__name" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-od' ); ?>"
								value="<?php echo esc_attr( $selected_filename ); ?>" />
							<button type="button" class="ceafsn-btn ceafsn-btn--quiet" id="ceafsn-od-media-button">
								<?php esc_html_e( 'Select file', 'ceafsn-od' ); ?>
							</button>
							<button type="button" class="ceafsn-od-media__clear" id="ceafsn-od-media-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-od' ); ?>
							</button>
						</div>
						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'The file name and size are read from the attachment itself, never from this form. A record can only be published when the file is non-empty and passes the checks for its declared type.', 'ceafsn-od' ); ?>
						</p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-download-url">
							<?php esc_html_e( 'Download URL', 'ceafsn-od' ); ?>
						</label>
						<input type="url" id="ceafsn-od-download-url" name="download_url"
							value="<?php echo esc_attr( (string) ( $row->download_url ?? '' ) ); ?>" />
						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'Use this instead of a file for datasets hosted elsewhere. The URL must return HTTP 200 and report a file size.', 'ceafsn-od' ); ?>
						</p>
					</div>

					<?php if ( $row && $validation ) : ?>
						<p class="ceafsn-od-validation ceafsn-od-validation--<?php echo esc_attr( $validation['valid'] ? 'ok' : 'fail' ); ?>">
							<?php
							if ( $validation['valid'] ) {
								printf(
									/* translators: %d: number of entries or rows. */
									esc_html__( 'Download verified: readable, %d item(s).', 'ceafsn-od' ),
									(int) $validation['entries']
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
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Visibility', 'ceafsn-od' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Only published records with a working download appear on the public page.', 'ceafsn-od' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<label for="ceafsn-od-status">
							<?php esc_html_e( 'Status', 'ceafsn-od' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<select id="ceafsn-od-status" name="status">
							<?php foreach ( $statuses as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>"
									<?php selected( $status, (string) ( $row->status ?? 'draft' ) ); ?>>
									<?php echo esc_html( (string) ( CEAFSN_OD_DB::status_labels()[ $status ] ?? ucfirst( $status ) ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'An invalid download downgrades the record to draft when you save.', 'ceafsn-od' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-od-methodology">
							<?php esc_html_e( 'Methodology / data dictionary URL', 'ceafsn-od' ); ?>
						</label>
						<input type="url" id="ceafsn-od-methodology" name="methodology_url"
							value="<?php echo esc_attr( (string) ( $row->methodology_url ?? '' ) ); ?>" />
					</div>

				</div>
			</section>

			<div class="ceafsn-form__actions">
				<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Save dataset', 'ceafsn-od' ); ?></button>
				<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-od' ); ?></a>
			</div>
		</form>

	<?php endif; ?>

	</div><!-- #ceafsn-od-main -->

	<a class="ceafsn-help" href="#ceafsn-od-help" aria-label="<?php esc_attr_e( 'Jump to download rules', 'ceafsn-od' ); ?>">?</a>

</div><!-- .ceafsn-od-wrap -->