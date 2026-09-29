<?php
/**
 * Admin partial: Policies list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_NP_Admin::page_policies()):
 *   @var string          $action         'list' | 'add' | 'edit'
 *   @var int             $id             Row ID when editing
 *   @var object|null     $row            DB row when editing
 *   @var array<int,object> $items       All policy rows
 *   @var array<int,string> $topics      Distinct published topics
 *   @var array|null      $validation     Validator result for the attached PDF
 *   @var int             $duplicate_count Other records sharing the same PDF
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

// Notices.
if ( ! empty( $_GET['ceafsn_np_error'] ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_np_error'] ) ) ) ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Policy record saved.', 'ceafsn-np' ); ?></p></div>
<?php endif; ?>

<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Policy record deleted.', 'ceafsn-np' ); ?></p></div>
<?php endif; ?>

<?php
$base_url  = admin_url( 'admin.php?page=' . CEAFSN_NP_Admin::MENU_SLUG );
$form_url  = admin_url( 'admin-post.php' );
$statuses  = CEAFSN_NP_DB::statuses();
$is_list   = 'list' === $action;
$row_title = $row ? (string) $row->title : '';
?>

<div class="wrap ceafsn-np-wrap">

	<?php if ( $is_list ) : ?>

		<h1 class="wp-heading-inline"><?php esc_html_e( 'Nutrition Policy Records', 'ceafsn-np' ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add' ), $base_url ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New Policy', 'ceafsn-np' ); ?>
		</a>
		<hr class="wp-header-end" />

		<?php if ( empty( $items ) ) : ?>
			<div class="ceafsn-np-empty">
				<p><?php esc_html_e( 'No policy records yet. Add the first one using "Add New Policy".', 'ceafsn-np' ); ?></p>
				<p class="ceafsn-np-empty__hint">
					<?php esc_html_e( 'Records stay invisible on the public page until they are published with a validated PDF.', 'ceafsn-np' ); ?>
				</p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped ceafsn-np-admin-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-np' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Topic', 'ceafsn-np' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Institution', 'ceafsn-np' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Published', 'ceafsn-np' ); ?></th>
						<th scope="col"><?php esc_html_e( 'PDF', 'ceafsn-np' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-np' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-np' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$item_status = (string) $item->status;
						$item_pdf    = (int) $item->pdf_attachment_id;
						?>
						<tr>
							<td><strong><?php echo esc_html( (string) $item->title ); ?></strong></td>
							<td><?php echo esc_html( (string) $item->topic ); ?></td>
							<td><?php echo esc_html( (string) $item->authoring_institution ); ?></td>
							<td><?php echo esc_html( (string) $item->publication_date ); ?></td>
							<td>
								<?php if ( $item_pdf > 0 ) : ?>
									<span class="ceafsn-np-badge ceafsn-np-badge--ok">
										<?php echo esc_html( (string) ( $item->pdf_filename ?: __( 'Attached', 'ceafsn-np' ) ) ); ?>
									</span>
								<?php else : ?>
									<span class="ceafsn-np-badge ceafsn-np-badge--warn">
										<?php esc_html_e( 'No PDF', 'ceafsn-np' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<span class="ceafsn-np-badge ceafsn-np-badge--status-<?php echo esc_attr( $item_status ); ?>">
									<?php echo esc_html( ucfirst( $item_status ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->policy_id ), $base_url ) ); ?>">
									<?php esc_html_e( 'Edit', 'ceafsn-np' ); ?>
								</a>
								|
								<a class="ceafsn-np-delete-link"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_np_delete_policy&id=' . (int) $item->policy_id ), 'ceafsn_np_delete_policy_' . (int) $item->policy_id ) ); ?>">
									<?php esc_html_e( 'Delete', 'ceafsn-np' ); ?>
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
					? __( 'Edit Policy', 'ceafsn-np' )
					: __( 'Add New Policy', 'ceafsn-np' )
			);
			?>
		</h1>

		<?php if ( $row && $validation && ! $validation['valid'] && 'published' === $row->status ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'This published record no longer passes PDF validation:', 'ceafsn-np' ); ?></strong></p>
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
						esc_html__( 'The same PDF is attached to %d other record(s). Each policy should have its own document.', 'ceafsn-np' ),
						(int) $duplicate_count
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="ceafsn-np-form">
			<?php wp_nonce_field( 'ceafsn_np_policy_nonce', 'ceafsn_np_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_np_save_policy" />
			<input type="hidden" name="policy_id" value="<?php echo esc_attr( (string) ( $id > 0 ? $id : 0 ) ); ?>" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-title"><?php esc_html_e( 'Policy title', 'ceafsn-np' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-np-title" name="title" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-description"><?php esc_html_e( 'Description', 'ceafsn-np' ); ?> *</label>
						</th>
						<td>
							<textarea id="ceafsn-np-description" name="description" rows="5" class="large-text" required><?php echo esc_textarea( (string) ( $row->description ?? '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'A plain-language summary of what the policy covers.', 'ceafsn-np' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-publication-date"><?php esc_html_e( 'Publication date', 'ceafsn-np' ); ?> *</label>
						</th>
						<td>
							<input type="date" id="ceafsn-np-publication-date" name="publication_date" required
								value="<?php echo esc_attr( (string) ( $row->publication_date ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-topic"><?php esc_html_e( 'Topic / domain', 'ceafsn-np' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-np-topic" name="topic" class="regular-text" list="ceafsn-np-topic-list" required
								value="<?php echo esc_attr( (string) ( $row->topic ?? '' ) ); ?>" />
							<datalist id="ceafsn-np-topic-list">
								<?php foreach ( $topics as $topic ) : ?>
									<option value="<?php echo esc_attr( $topic ); ?>"></option>
								<?php endforeach; ?>
							</datalist>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-institution"><?php esc_html_e( 'Authoring institution', 'ceafsn-np' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-np-institution" name="authoring_institution" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->authoring_institution ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-pdf-field"><?php esc_html_e( 'PDF attachment', 'ceafsn-np' ); ?> *</label>
						</th>
						<td>
							<input type="hidden" id="ceafsn-np-pdf-id" name="pdf_attachment_id"
								value="<?php echo esc_attr( (string) ( $row->pdf_attachment_id ?? 0 ) ); ?>" />
							<input type="text" id="ceafsn-np-pdf-field" class="regular-text" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-np' ); ?>"
								value="<?php echo esc_attr( (string) ( $row->pdf_filename ?? '' ) ); ?>" />
							<button type="button" class="button ceafsn-np-media-button" id="ceafsn-np-media-button">
								<?php esc_html_e( 'Select PDF', 'ceafsn-np' ); ?>
							</button>
							<button type="button" class="button-link ceafsn-np-media-clear" id="ceafsn-np-media-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-np' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'The file name is read from the attachment itself. A record can only be published when this PDF is readable, is not a known placeholder, and has a readable page count.', 'ceafsn-np' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-source-url"><?php esc_html_e( 'Source / citation URL', 'ceafsn-np' ); ?></label>
						</th>
						<td>
							<input type="url" id="ceafsn-np-source-url" name="source_url" class="regular-text"
								value="<?php echo esc_attr( (string) ( $row->source_url ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-np-status"><?php esc_html_e( 'Status', 'ceafsn-np' ); ?> *</label>
						</th>
						<td>
							<select id="ceafsn-np-status" name="status">
								<?php foreach ( $statuses as $status ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>"
										<?php selected( $status, (string) ( $row->status ?? 'draft' ) ); ?>>
										<?php echo esc_html( ucfirst( $status ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( $row && $validation ) : ?>
				<p class="ceafsn-np-validation ceafsn-np-validation--<?php echo esc_attr( $validation['valid'] ? 'ok' : 'fail' ); ?>">
					<?php
					if ( $validation['valid'] ) {
						printf(
							/* translators: %d: page count. */
							esc_html__( 'PDF verified: readable, %d page(s).', 'ceafsn-np' ),
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
					<?php esc_html_e( 'Save policy', 'ceafsn-np' ); ?>
				</button>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'ceafsn-np' ); ?>
				</a>
			</p>
		</form>

	<?php endif; ?>

</div>
