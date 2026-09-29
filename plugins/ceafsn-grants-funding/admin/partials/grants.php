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

<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Grant deleted.', 'ceafsn-gf' ); ?></p></div>
<?php endif; ?>

<?php
$base_url = admin_url( 'admin.php?page=' . CEAFSN_GF_Admin::MENU_SLUG );
$is_list  = 'list' === $action;
$statuses = CEAFSN_GF_DB::statuses();
$grants   = CEAFSN_GF_DB::grant_statuses();

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

	<?php if ( $is_list ) : ?>

		<h1 class="wp-heading-inline"><?php esc_html_e( 'Grants & Funding', 'ceafsn-gf' ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add' ), $base_url ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'ceafsn-gf' ); ?>
		</a>
		<hr class="wp-header-end" />

		<p class="description">
			<?php esc_html_e( 'Nothing here is invented: a record cannot be published without a real deadline and a validated, non-duplicated Official Call PDF.', 'ceafsn-gf' ); ?>
		</p>

		<?php if ( empty( $items ) ) : ?>
			<div class="ceafsn-gf-empty">
				<p><?php esc_html_e( 'No grants yet. Add the first one using "Add New".', 'ceafsn-gf' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped ceafsn-gf-admin-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-gf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Institution', 'ceafsn-gf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Deadline', 'ceafsn-gf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-gf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Call PDF', 'ceafsn-gf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Record state', 'ceafsn-gf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-gf' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td><strong><?php echo esc_html( (string) $item->title ); ?></strong></td>
							<td><?php echo esc_html( '' !== (string) $item->funding_institution ? (string) $item->funding_institution : '—' ); ?></td>
							<td>
								<?php
								$item_deadline = CEAFSN_GF_Public::format_deadline( (string) ( $item->deadline ?? '' ), false );
								echo esc_html( '' !== $item_deadline ? $item_deadline : '—' );
								?>
							</td>
							<td>
								<span class="ceafsn-gf-badge ceafsn-gf-badge--status-<?php echo esc_attr( (string) $item->grant_status ); ?>">
									<?php echo esc_html( CEAFSN_GF_Public::status_label( (string) $item->grant_status ) ); ?>
								</span>
							</td>
							<td>
								<?php if ( (int) $item->call_pdf_id > 0 ) : ?>
									<span class="ceafsn-gf-badge ceafsn-gf-badge--ok"><?php esc_html_e( 'PDF', 'ceafsn-gf' ); ?></span>
									<?php if ( (int) $item->duplicate_ok === 1 ) : ?>
										<span class="ceafsn-gf-badge ceafsn-gf-badge--shared"><?php esc_html_e( 'Shared', 'ceafsn-gf' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="ceafsn-gf-badge ceafsn-gf-badge--warn"><?php esc_html_e( 'Missing', 'ceafsn-gf' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<span class="ceafsn-gf-badge ceafsn-gf-badge--state-<?php echo esc_attr( (string) $item->status ); ?>">
									<?php echo esc_html( ucfirst( (string) $item->status ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->grant_id ), $base_url ) ); ?>">
									<?php esc_html_e( 'Edit', 'ceafsn-gf' ); ?>
								</a>
								|
								<a class="ceafsn-gf-delete-link"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_gf_delete_grant&id=' . (int) $item->grant_id ), 'ceafsn_gf_delete_grant_' . (int) $item->grant_id ) ); ?>">
									<?php esc_html_e( 'Delete', 'ceafsn-gf' ); ?>
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
					? __( 'Edit Grant', 'ceafsn-gf' )
					: __( 'Add New Grant', 'ceafsn-gf' )
			);
			?>
		</h1>

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
								<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $other->grant_id ), $base_url ) ); ?>">
									<?php echo esc_html( (string) $other->title ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ceafsn-gf-form">
			<?php wp_nonce_field( 'ceafsn_gf_grant_nonce', 'ceafsn_gf_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_gf_save_grant" />
			<input type="hidden" name="grant_id" value="<?php echo esc_attr( (string) (int) ( $row->grant_id ?? 0 ) ); ?>" />

			<table class="form-table ceafsn-gf-fields" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-title"><?php esc_html_e( 'Grant / scholarship title', 'ceafsn-gf' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-gf-title" name="title" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-award"><?php esc_html_e( 'Award range', 'ceafsn-gf' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-gf-award" name="award_range" class="regular-text" required
								placeholder="<?php esc_attr_e( 'e.g. USD 5,000-10,000, or "Not disclosed"', 'ceafsn-gf' ); ?>"
								value="<?php echo esc_attr( (string) ( $row->award_range ?? '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Use "Not disclosed" rather than leaving this blank if the amount is unknown.', 'ceafsn-gf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-institution"><?php esc_html_e( 'Funding institution', 'ceafsn-gf' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-gf-institution" name="funding_institution" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->funding_institution ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-deadline"><?php esc_html_e( 'Deadline', 'ceafsn-gf' ); ?> *</label>
						</th>
						<td>
							<input type="datetime-local" id="ceafsn-gf-deadline" name="deadline"
								aria-describedby="ceafsn-gf-deadline-help"
								value="<?php echo esc_attr( $deadline_input ); ?>" />
							<p id="ceafsn-gf-deadline-help" class="description">
								<?php
								printf(
									/* translators: %s: site timezone name. */
									esc_html__( 'Entered and displayed in the site timezone (%s), stored as UTC.', 'ceafsn-gf' ),
									esc_html( (string) wp_timezone_string() )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-beneficiaries"><?php esc_html_e( 'Target beneficiaries', 'ceafsn-gf' ); ?></label>
						</th>
						<td>
							<textarea id="ceafsn-gf-beneficiaries" name="target_beneficiaries" rows="3" class="large-text"><?php echo esc_textarea( (string) ( $row->target_beneficiaries ?? '' ) ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Optional. Who this grant is meant for.', 'ceafsn-gf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-eligibility"><?php esc_html_e( 'Eligibility', 'ceafsn-gf' ); ?> *</label>
						</th>
						<td>
							<textarea id="ceafsn-gf-eligibility" name="eligibility" rows="5" class="large-text" required
								aria-describedby="ceafsn-gf-eligibility-help"><?php echo esc_textarea( (string) ( $row->eligibility ?? '' ) ); ?></textarea>
							<p id="ceafsn-gf-eligibility-help" class="description">
								<?php esc_html_e( 'Required. A visitor who cannot tell whether they are eligible will not apply.', 'ceafsn-gf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-pdf-field"><?php esc_html_e( 'Official Call PDF', 'ceafsn-gf' ); ?> *</label>
						</th>
						<td>
							<input type="hidden" id="ceafsn-gf-pdf-id" name="call_pdf_id" value="<?php echo esc_attr( (string) $call_pdf_id ); ?>" />
							<input type="text" id="ceafsn-gf-pdf-field" class="regular-text" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-gf' ); ?>"
								value="<?php echo esc_attr( $call_pdf_name ); ?>" />
							<button type="button" class="button ceafsn-gf-media-button" id="ceafsn-gf-pdf-button"
								data-target="ceafsn-gf-pdf-id">
								<?php esc_html_e( 'Select PDF', 'ceafsn-gf' ); ?>
							</button>
							<button type="button" class="button-link ceafsn-gf-media-clear" id="ceafsn-gf-pdf-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-gf' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Required to publish. Validated on save: must be a real, readable PDF, and unique to this record unless sharing is confirmed below.', 'ceafsn-gf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-duplicate-ok"><?php esc_html_e( 'Shared document', 'ceafsn-gf' ); ?></label>
						</th>
						<td>
							<label for="ceafsn-gf-duplicate-ok">
								<input type="checkbox" id="ceafsn-gf-duplicate-ok" name="duplicate_ok" value="1"
									<?php checked( ! empty( $row->duplicate_ok ) ); ?> />
								<?php esc_html_e( 'This document is intentionally shared with another record', 'ceafsn-gf' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Only tick this when two grant listings genuinely point at the same call PDF. This is shown to visitors on both records.', 'ceafsn-gf' ); ?>
							</p>
							<label for="ceafsn-gf-duplicate-note"><?php esc_html_e( 'Sharing note', 'ceafsn-gf' ); ?></label>
							<textarea id="ceafsn-gf-duplicate-note" name="duplicate_note" rows="2" class="large-text"
								aria-describedby="ceafsn-gf-duplicate-note-help"><?php echo esc_textarea( (string) ( $row->duplicate_note ?? '' ) ); ?></textarea>
							<p id="ceafsn-gf-duplicate-note-help" class="description">
								<?php esc_html_e( 'Required whenever the box above is ticked.', 'ceafsn-gf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-app-url"><?php esc_html_e( 'Application URL', 'ceafsn-gf' ); ?></label>
						</th>
						<td>
							<input type="url" id="ceafsn-gf-app-url" name="application_url" class="regular-text"
								placeholder="https://"
								aria-describedby="ceafsn-gf-app-help"
								value="<?php echo esc_attr( (string) ( $row->application_url ?? '' ) ); ?>" />
							<p id="ceafsn-gf-app-help" class="description">
								<?php esc_html_e( 'A full http or https address. Without one the Apply button is shown as inactive rather than as a broken link.', 'ceafsn-gf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-contact"><?php esc_html_e( 'Contact', 'ceafsn-gf' ); ?></label>
						</th>
						<td>
							<input type="text" id="ceafsn-gf-contact" name="contact" class="regular-text"
								aria-describedby="ceafsn-gf-contact-help"
								value="<?php echo esc_attr( (string) ( $row->contact ?? '' ) ); ?>" />
							<label for="ceafsn-gf-show-contact">
								<input type="checkbox" id="ceafsn-gf-show-contact" name="show_contact" value="1"
									<?php checked( ! empty( $row->show_contact ) ); ?> />
								<?php esc_html_e( 'Approved for public display', 'ceafsn-gf' ); ?>
							</label>
							<p id="ceafsn-gf-contact-help" class="description">
								<?php esc_html_e( 'Stored but never shown until it is approved. Leave blank if there is no verified contact.', 'ceafsn-gf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-grant-status"><?php esc_html_e( 'Status', 'ceafsn-gf' ); ?></label>
						</th>
						<td>
							<select id="ceafsn-gf-grant-status" name="grant_status">
								<?php foreach ( $grants as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $value, (string) ( $row->grant_status ?? 'upcoming' ) ); ?>>
										<?php echo esc_html( CEAFSN_GF_Public::status_label( $value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Set directly by the administrator. Keep this in step with the deadline above; the plugin does not change it automatically.', 'ceafsn-gf' ); ?>
							</p>
							<?php if ( $row && 'open' === (string) ( $row->grant_status ?? '' ) && '' !== (string) ( $row->deadline ?? '' ) && (string) $row->deadline < gmdate( 'Y-m-d H:i:s' ) ) : ?>
								<p class="description ceafsn-gf-notice-inline">
									<?php esc_html_e( 'The deadline above has already passed, but the status is still set to Open. Check this is correct.', 'ceafsn-gf' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-gf-status"><?php esc_html_e( 'Record state', 'ceafsn-gf' ); ?></label>
						</th>
						<td>
							<select id="ceafsn-gf-status" name="status">
								<?php foreach ( $statuses as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $value, (string) ( $row->status ?? 'draft' ) ); ?>>
										<?php echo esc_html( ucfirst( $value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Only Published grants appear on the public page.', 'ceafsn-gf' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save grant', 'ceafsn-gf' ); ?></button>
				<a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-gf' ); ?></a>
			</p>
		</form>

	<?php endif; ?>
</div>
