<?php
/**
 * Admin partial: publications list, add, and edit view.
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
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

// Notices.
if ( ! empty( $_GET['ceafsn_pp_error'] ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_pp_error'] ) ) ) ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Record saved.', 'ceafsn-pp' ); ?></p></div>
<?php endif; ?>

<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Record deleted.', 'ceafsn-pp' ); ?></p></div>
<?php endif; ?>

<?php
$base_url   = admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::MENU_SLUG );
$form_url   = admin_url( 'admin-post.php' );
$is_list    = 'list' === $action;
$statuses   = CEAFSN_PP_DB::statuses();
$types      = CEAFSN_PP_DB::content_types();
$projects   = CEAFSN_PP_DB::project_statuses();
$access     = CEAFSN_PP_DB::access_levels();

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

	<?php if ( $is_list ) : ?>

		<h1 class="wp-heading-inline"><?php esc_html_e( 'Projects & Publications', 'ceafsn-pp' ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add' ), $base_url ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'ceafsn-pp' ); ?>
		</a>
		<hr class="wp-header-end" />

		<?php if ( empty( $items ) ) : ?>
			<div class="ceafsn-pp-empty">
				<p><?php esc_html_e( 'No records yet. Add the first one using "Add New".', 'ceafsn-pp' ); ?></p>
				<p class="ceafsn-pp-empty__hint">
					<?php esc_html_e( 'Records stay invisible on the public page until they are published with their own validated PDF.', 'ceafsn-pp' ); ?>
				</p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped ceafsn-pp-admin-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Author / institution', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Date', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Project status', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Document', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Access', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Published', 'ceafsn-pp' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-pp' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$item_status = (string) $item->status;
						$item_pdf    = (int) $item->pdf_attachment_id;
						$item_dup    = (int) $item->duplicate_ok;
						?>
						<tr>
							<td><strong><?php echo esc_html( (string) $item->title ); ?></strong></td>
							<td><?php echo esc_html( CEAFSN_PP_Public::type_label( (string) $item->content_type ) ); ?></td>
							<td><?php echo esc_html( (string) $item->author_institution ); ?></td>
							<td><?php echo esc_html( (string) $item->publication_date ); ?></td>
							<td>
								<span class="ceafsn-pp-badge ceafsn-pp-badge--status-<?php echo esc_attr( (string) $item->project_status ); ?>">
									<?php echo esc_html( CEAFSN_PP_Public::status_label( (string) $item->project_status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( $item_pdf > 0 ) : ?>
									<span class="ceafsn-pp-badge ceafsn-pp-badge--ok">
										<?php esc_html_e( 'PDF', 'ceafsn-pp' ); ?>
										<?php if ( $item_dup ) : ?>
											<?php esc_html_e( '(shared)', 'ceafsn-pp' ); ?>
										<?php endif; ?>
									</span>
								<?php else : ?>
									<span class="ceafsn-pp-badge ceafsn-pp-badge--warn">
										<?php esc_html_e( 'No PDF', 'ceafsn-pp' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 'members_only' === (string) $item->access_level ) : ?>
									<span class="ceafsn-pp-badge ceafsn-pp-badge--members">
										<?php esc_html_e( 'Members', 'ceafsn-pp' ); ?>
									</span>
								<?php else : ?>
									<?php esc_html_e( 'Public', 'ceafsn-pp' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<span class="ceafsn-pp-badge ceafsn-pp-badge--status-<?php echo esc_attr( $item_status ); ?>">
									<?php echo esc_html( ucfirst( $item_status ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->publication_id ), $base_url ) ); ?>">
									<?php esc_html_e( 'Edit', 'ceafsn-pp' ); ?>
								</a>
								|
								<a class="ceafsn-pp-delete-link"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_pp_delete_publication&id=' . (int) $item->publication_id ), 'ceafsn_pp_delete_publication_' . (int) $item->publication_id ) ); ?>">
									<?php esc_html_e( 'Delete', 'ceafsn-pp' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

	<?php else : ?>

		<h1>
			<?php
			echo esc_html(
				$row
					? __( 'Edit Record', 'ceafsn-pp' )
					: __( 'Add New Record', 'ceafsn-pp' )
			);
			?>
		</h1>

		<?php if ( $row && $validation && ! $validation['valid'] && 'published' === $row->status ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'This published record no longer passes PDF validation:', 'ceafsn-pp' ); ?></strong></p>
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
						/* translators: %d: number of other records. */
						esc_html( _n(
							'The same document is attached to %d other record. Each publication should have its own document.',
							'The same document is attached to %d other records. Each publication should have its own document.',
							$duplicate_count,
							'ceafsn-pp'
						) ),
						(int) $duplicate_count
					);
					?>
				</p>
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
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="ceafsn-pp-form">
			<?php wp_nonce_field( 'ceafsn_pp_publication_nonce', 'ceafsn_pp_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_pp_save_publication" />
			<input type="hidden" name="publication_id" value="<?php echo esc_attr( (string) ( $id > 0 ? $id : 0 ) ); ?>" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-title"><?php esc_html_e( 'Title', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-pp-title" name="title" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-content-type"><?php esc_html_e( 'Content type', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<select id="ceafsn-pp-content-type" name="content_type" required>
								<?php foreach ( $types as $type ) : ?>
									<option value="<?php echo esc_attr( $type ); ?>"
										<?php selected( $type, (string) ( $row->content_type ?? 'report' ) ); ?>>
										<?php echo esc_html( CEAFSN_PP_Public::type_label( $type ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-summary"><?php esc_html_e( 'Executive summary', 'ceafsn-pp' ); ?></label>
						</th>
						<td>
							<textarea id="ceafsn-pp-summary" name="executive_summary" rows="4" class="large-text"><?php echo esc_textarea( (string) ( $row->executive_summary ?? '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown on the record card. Leave empty to hide the summary rather than repeat the title.', 'ceafsn-pp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-author"><?php esc_html_e( 'Author / institution', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-pp-author" name="author_institution" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->author_institution ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-date"><?php esc_html_e( 'Publication date', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<input type="date" id="ceafsn-pp-date" name="publication_date" required
								value="<?php echo esc_attr( (string) ( $row->publication_date ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-project-status"><?php esc_html_e( 'Project status', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<select id="ceafsn-pp-project-status" name="project_status" required>
								<?php foreach ( $projects as $status_key ) : ?>
									<option value="<?php echo esc_attr( $status_key ); ?>"
										<?php selected( $status_key, (string) ( $row->project_status ?? 'in_progress' ) ); ?>>
										<?php echo esc_html( CEAFSN_PP_Public::status_label( $status_key ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<span><?php esc_html_e( 'Cover image', 'ceafsn-pp' ); ?></span>
						</th>
						<td>
							<input type="hidden" id="ceafsn-pp-cover-id" name="cover_image_id"
								value="<?php echo esc_attr( (string) $cover_image_id ); ?>" />
							<input type="text" id="ceafsn-pp-cover-field" class="regular-text" readonly
								placeholder="<?php esc_attr_e( 'No image selected', 'ceafsn-pp' ); ?>"
								value="<?php echo esc_attr( $cover_image_name ); ?>" />
							<button type="button" class="button ceafsn-pp-media-button" id="ceafsn-pp-cover-button"
								data-target="ceafsn-pp-cover-id">
								<?php esc_html_e( 'Select image', 'ceafsn-pp' ); ?>
							</button>
							<button type="button" class="button-link ceafsn-pp-media-clear" id="ceafsn-pp-cover-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-pp' ); ?>
							</button>
							<p class="description"><?php esc_html_e( 'Optional. A record without a cover image is shown as a text-only card.', 'ceafsn-pp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-cover-alt"><?php esc_html_e( 'Cover image alt text', 'ceafsn-pp' ); ?></label>
						</th>
						<td>
							<input type="text" id="ceafsn-pp-cover-alt" name="cover_image_alt" class="regular-text"
								aria-describedby="ceafsn-pp-cover-alt-help"
								value="<?php echo esc_attr( (string) ( $row->cover_image_alt ?? '' ) ); ?>" />
							<p id="ceafsn-pp-cover-alt-help" class="description">
								<?php esc_html_e( 'Required when a cover image is set. Describe what the image shows.', 'ceafsn-pp' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-pdf-field"><?php esc_html_e( 'PDF attachment', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<input type="hidden" id="ceafsn-pp-pdf-id" name="pdf_attachment_id"
								value="<?php echo esc_attr( (string) $pdf_attachment_id ); ?>" />
							<input type="text" id="ceafsn-pp-pdf-field" class="regular-text" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-pp' ); ?>"
								value="<?php echo esc_attr( $pdf_attachment_id > 0 ? __( 'Document attached', 'ceafsn-pp' ) : '' ); ?>" />
							<button type="button" class="button ceafsn-pp-media-button" id="ceafsn-pp-pdf-button"
								data-target="ceafsn-pp-pdf-id">
								<?php esc_html_e( 'Select PDF', 'ceafsn-pp' ); ?>
							</button>
							<button type="button" class="button-link ceafsn-pp-media-clear" id="ceafsn-pp-pdf-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-pp' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'A record can only be published when this PDF is readable, belongs to this record alone, and its page count matches the field below.', 'ceafsn-pp' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-page-count"><?php esc_html_e( 'Page count', 'ceafsn-pp' ); ?></label>
						</th>
						<td>
							<input type="number" id="ceafsn-pp-page-count" name="page_count" min="0" step="1"
								class="small-text"
								value="<?php echo esc_attr( (string) ( $row->page_count ?? 0 ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Leave at 0 if you do not know. A value that disagrees with the document blocks publishing.', 'ceafsn-pp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-doi"><?php esc_html_e( 'DOI / citation URL', 'ceafsn-pp' ); ?></label>
						</th>
						<td>
							<input type="url" id="ceafsn-pp-doi" name="doi_citation" class="regular-text"
								value="<?php echo esc_attr( (string) ( $row->doi_citation ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-access"><?php esc_html_e( 'Access level', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<select id="ceafsn-pp-access" name="access_level" required>
								<option value="public" <?php selected( 'public', (string) ( $row->access_level ?? 'public' ) ); ?>>
									<?php esc_html_e( 'Public', 'ceafsn-pp' ); ?>
								</option>
								<option value="members_only" <?php selected( 'members_only', (string) ( $row->access_level ?? 'public' ) ); ?>>
									<?php esc_html_e( 'Members Only', 'ceafsn-pp' ); ?>
								</option>
							</select>
							<p class="description"><?php esc_html_e( 'Members-only records are hidden from logged-out visitors and cannot be linked to directly.', 'ceafsn-pp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<span><?php esc_html_e( 'Document flags', 'ceafsn-pp' ); ?></span>
						</th>
						<td>
							<label for="ceafsn-pp-scanned">
								<input type="checkbox" id="ceafsn-pp-scanned" name="scanned" value="1"
									<?php checked( 1 === (int) ( $row->scanned ?? 0 ) ); ?> />
								<?php esc_html_e( 'This is a scanned document (no extractable text is expected)', 'ceafsn-pp' ); ?>
							</label>
							<br />
							<label for="ceafsn-pp-duplicate-ok">
								<input type="checkbox" id="ceafsn-pp-duplicate-ok" name="duplicate_ok" value="1"
									<?php checked( 1 === (int) ( $row->duplicate_ok ?? 0 ) ); ?> />
								<?php esc_html_e( 'This document is intentionally shared, or is a known placeholder', 'ceafsn-pp' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-duplicate-note"><?php esc_html_e( 'Sharing / placeholder note', 'ceafsn-pp' ); ?></label>
						</th>
						<td>
							<textarea id="ceafsn-pp-duplicate-note" name="duplicate_note" rows="3" class="large-text"
								aria-describedby="ceafsn-pp-duplicate-note-help"><?php echo esc_textarea( (string) ( $row->duplicate_note ?? '' ) ); ?></textarea>
							<p id="ceafsn-pp-duplicate-note-help" class="description">
								<?php esc_html_e( 'Required when the box above is ticked. Explain why this document is used more than once.', 'ceafsn-pp' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-pp-status"><?php esc_html_e( 'Published state', 'ceafsn-pp' ); ?> *</label>
						</th>
						<td>
							<select id="ceafsn-pp-status" name="status" required>
								<?php foreach ( $statuses as $status_value ) : ?>
									<option value="<?php echo esc_attr( $status_value ); ?>"
										<?php selected( $status_value, (string) ( $row->status ?? 'draft' ) ); ?>>
										<?php echo esc_html( ucfirst( $status_value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Only a record whose document passes every check can be Published.', 'ceafsn-pp' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

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

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save record', 'ceafsn-pp' ); ?>
				</button>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'ceafsn-pp' ); ?>
				</a>
			</p>
		</form>

	<?php endif; ?>

</div>
