<?php
/**
 * Admin partial: Datasets list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_OD_Admin::page_datasets()):
 *   @var string            $action     'list' | 'add' | 'edit'
 *   @var int               $id         Row ID when editing
 *   @var object|null       $row        DB row when editing
 *   @var array<int,object> $items      All dataset rows
 *   @var array<int,string> $categories Distinct published categories
 *   @var array|null        $validation Validator result for the download target
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
?>

<?php if ( '' !== $notice_error ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( $notice_error ); ?></p>
	</div>
<?php endif; ?>

<?php if ( $notice_saved ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Dataset record saved.', 'ceafsn-od' ); ?></p></div>
<?php endif; ?>

<?php if ( $notice_deleted ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Dataset record deleted.', 'ceafsn-od' ); ?></p></div>
<?php endif; ?>

<?php
$base_url  = admin_url( 'admin.php?page=' . CEAFSN_OD_Admin::MENU_SLUG );
$form_url  = admin_url( 'admin-post.php' );
$statuses  = CEAFSN_OD_DB::statuses();
$file_types = CEAFSN_OD_DB::file_type_labels();
$allow_other = CEAFSN_OD_Admin::other_files_allowed();
$is_list   = 'list' === $action;
?>

<div class="wrap ceafsn-od-wrap">

	<?php if ( $is_list ) : ?>

		<h1 class="wp-heading-inline"><?php esc_html_e( 'Open Dataset Records', 'ceafsn-od' ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add' ), $base_url ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New Dataset', 'ceafsn-od' ); ?>
		</a>
		<hr class="wp-header-end" />

		<?php if ( empty( $items ) ) : ?>
			<div class="ceafsn-od-empty">
				<p><?php esc_html_e( 'No dataset records yet. Add the first one using "Add New Dataset".', 'ceafsn-od' ); ?></p>
				<p class="ceafsn-od-empty__hint">
					<?php esc_html_e( 'Records stay invisible on the public page until they are published with a working download target.', 'ceafsn-od' ); ?>
				</p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped ceafsn-od-admin-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Dataset', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Category', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Coverage', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Updated', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Size', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Download', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-od' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-od' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<?php
						$item_status    = (string) $item->status;
						$item_type      = (string) $item->file_type;
						$item_size      = (int) $item->file_size;
						$item_attached  = (int) $item->file_attachment_id;
						$item_url       = (string) $item->download_url;
						$has_download   = $item_attached > 0 || '' !== $item_url;
						?>
						<tr>
							<td><strong><?php echo esc_html( (string) $item->name ); ?></strong></td>
							<td><?php echo esc_html( (string) $item->category ); ?></td>
							<td><?php echo esc_html( (string) $item->coverage_area ); ?></td>
							<td><?php echo esc_html( (string) $item->last_updated ); ?></td>
							<td><?php echo esc_html( (string) ( $file_types[ $item_type ] ?? strtoupper( $item_type ) ) ); ?></td>
							<td>
								<?php echo esc_html( CEAFSN_OD_Validator::format_size( $item_size ) ); ?>
							</td>
							<td>
								<?php if ( $has_download ) : ?>
									<span class="ceafsn-od-badge ceafsn-od-badge--ok">
										<?php echo esc_html( $item_attached > 0 ? __( 'File', 'ceafsn-od' ) : __( 'URL', 'ceafsn-od' ) ); ?>
									</span>
								<?php else : ?>
									<span class="ceafsn-od-badge ceafsn-od-badge--warn">
										<?php esc_html_e( 'No download', 'ceafsn-od' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<span class="ceafsn-od-badge ceafsn-od-badge--status-<?php echo esc_attr( $item_status ); ?>">
									<?php echo esc_html( (string) ( CEAFSN_OD_DB::status_labels()[ $item_status ] ?? ucfirst( $item_status ) ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->dataset_id ), $base_url ) ); ?>">
									<?php esc_html_e( 'Edit', 'ceafsn-od' ); ?>
								</a>
								|
								<a class="ceafsn-od-delete-link"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_od_delete_dataset&id=' . (int) $item->dataset_id ), 'ceafsn_od_delete_dataset_' . (int) $item->dataset_id ) ); ?>">
									<?php esc_html_e( 'Delete', 'ceafsn-od' ); ?>
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
					? __( 'Edit Dataset', 'ceafsn-od' )
					: __( 'Add New Dataset', 'ceafsn-od' )
			);
			?>
		</h1>

		<?php if ( $row && $validation && ! $validation['valid'] ) : ?>
			<div class="notice notice-error">
				<p>
					<strong>
						<?php
						echo esc_html(
							'published' === $row->status
								? __( 'This published record no longer has a working download:', 'ceafsn-od' )
								: __( 'This record has no working download:', 'ceafsn-od' )
						);
						?>
					</strong>
				</p>
				<ul>
					<?php foreach ( $validation['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $form_url ); ?>" class="ceafsn-od-form">
			<?php wp_nonce_field( 'ceafsn_od_dataset_nonce', 'ceafsn_od_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_od_save_dataset" />
			<input type="hidden" name="dataset_id" value="<?php echo esc_attr( (string) ( $id > 0 ? $id : 0 ) ); ?>" />

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-name"><?php esc_html_e( 'Dataset name', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-od-name" name="name" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->name ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-description"><?php esc_html_e( 'Description', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
							<textarea id="ceafsn-od-description" name="description" rows="5" class="large-text" required><?php echo esc_textarea( (string) ( $row->description ?? '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'What the dataset contains, how it was collected, and any known limitations.', 'ceafsn-od' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-category"><?php esc_html_e( 'Category / sector', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-od-category" name="category" class="regular-text" list="ceafsn-od-category-list" required
								value="<?php echo esc_attr( (string) ( $row->category ?? '' ) ); ?>" />
							<datalist id="ceafsn-od-category-list">
								<?php foreach ( $categories as $category ) : ?>
									<option value="<?php echo esc_attr( $category ); ?>"></option>
								<?php endforeach; ?>
							</datalist>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-coverage"><?php esc_html_e( 'Coverage area', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-od-coverage" name="coverage_area" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->coverage_area ?? '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Geographic or administrative scope, e.g. "All 15 counties" or "Northern Province".', 'ceafsn-od' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-last-updated"><?php esc_html_e( 'Last updated', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
							<input type="date" id="ceafsn-od-last-updated" name="last_updated" required
								value="<?php echo esc_attr( (string) ( $row->last_updated ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-file-type"><?php esc_html_e( 'File type', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
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
						<p class="description"><?php esc_html_e( 'The type is used to validate the file. Pick the real format, not a guess.', 'ceafsn-od' ); ?></p>
						<?php if ( ! $allow_other ) : ?>
							<p class="description">
								<?php esc_html_e( 'The "other" file type is not enabled. Turn it on in Settings if you need a format this plugin cannot inspect.', 'ceafsn-od' ); ?>
							</p>
						<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-file-field"><?php esc_html_e( 'Dataset file', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
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
							<input type="hidden" id="ceafsn-od-file-id" name="file_attachment_id"
								value="<?php echo esc_attr( (string) $selected_id ); ?>" />
							<input type="text" id="ceafsn-od-file-field" class="regular-text" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-od' ); ?>"
								value="<?php echo esc_attr( $selected_filename ); ?>" />
							<button type="button" class="button ceafsn-od-media-button" id="ceafsn-od-media-button">
								<?php esc_html_e( 'Select file', 'ceafsn-od' ); ?>
							</button>
							<button type="button" class="button-link ceafsn-od-media-clear" id="ceafsn-od-media-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-od' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'The file name and size are read from the attachment itself, never from this form. A record can only be published when the file is non-empty and passes the checks for its declared type.', 'ceafsn-od' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-download-url"><?php esc_html_e( 'Download URL', 'ceafsn-od' ); ?></label>
						</th>
						<td>
							<input type="url" id="ceafsn-od-download-url" name="download_url" class="regular-text"
								value="<?php echo esc_attr( (string) ( $row->download_url ?? '' ) ); ?>" />
							<p class="description">
								<?php esc_html_e( 'Use this instead of a file for datasets hosted elsewhere. The URL must return HTTP 200 and report a file size. Provide one route, not both.', 'ceafsn-od' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-license"><?php esc_html_e( 'Data license', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-od-license" name="data_license" class="regular-text" list="ceafsn-od-license-list" required
								value="<?php echo esc_attr( (string) ( $row->data_license ?? '' ) ); ?>" />
							<datalist id="ceafsn-od-license-list">
								<option value="CC BY 4.0"></option>
								<option value="CC BY-SA 4.0"></option>
								<option value="CC0 1.0"></option>
								<option value="ODbL"></option>
							</datalist>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-methodology"><?php esc_html_e( 'Methodology / data dictionary URL', 'ceafsn-od' ); ?></label>
						</th>
						<td>
							<input type="url" id="ceafsn-od-methodology" name="methodology_url" class="regular-text"
								value="<?php echo esc_attr( (string) ( $row->methodology_url ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-contact"><?php esc_html_e( 'Contact / owner', 'ceafsn-od' ); ?></label>
						</th>
						<td>
							<input type="text" id="ceafsn-od-contact" name="contact_owner" class="regular-text"
								value="<?php echo esc_attr( (string) ( $row->contact_owner ?? '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Kept private unless "Show contact on the public page" is enabled in Settings.', 'ceafsn-od' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-od-status"><?php esc_html_e( 'Status', 'ceafsn-od' ); ?> *</label>
						</th>
						<td>
							<select id="ceafsn-od-status" name="status">
								<?php foreach ( $statuses as $status ) : ?>
									<option value="<?php echo esc_attr( $status ); ?>"
										<?php selected( $status, (string) ( $row->status ?? 'draft' ) ); ?>>
										<?php echo esc_html( (string) ( CEAFSN_OD_DB::status_labels()[ $status ] ?? ucfirst( $status ) ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</tbody>
			</table>

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

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save dataset', 'ceafsn-od' ); ?>
				</button>
				<a href="<?php echo esc_url( $base_url ); ?>" class="button">
					<?php esc_html_e( 'Cancel', 'ceafsn-od' ); ?>
				</a>
			</p>
		</form>

	<?php endif; ?>

</div>
