<?php
/**
 * Admin partial: fellowships list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_RF_Admin::page_fellowships()):
 *   @var string             $action      'list' | 'add' | 'edit'
 *   @var int                $id          Row ID when editing
 *   @var object|null        $row         DB row when editing
 *   @var array<int,object>  $items       All fellowship rows
 *   @var array|null         $derived     Derived status for the edited row
 *   @var array|null         $validation  Call PDF validation result
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

// Notices.
if ( ! empty( $_GET['ceafsn_rf_error'] ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_rf_error'] ) ) ) ); ?></p>
	</div>
<?php endif; ?>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Opportunity saved.', 'ceafsn-rf' ); ?></p></div>
<?php endif; ?>

<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Opportunity deleted.', 'ceafsn-rf' ); ?></p></div>
<?php endif; ?>

<?php
$base_url = admin_url( 'admin.php?page=' . CEAFSN_RF_Admin::MENU_SLUG );
$is_list  = 'list' === $action;
$statuses = CEAFSN_RF_DB::statuses();
$manuals  = CEAFSN_RF_Status::manual_values();

// The pickers show which file is attached, so the name has to come from the
// attachment itself. A record whose call PDF has been deleted falls back to the
// ID rather than pretending the field is empty.
$call_pdf_id   = (int) ( $row->call_pdf_id ?? 0 );
$call_pdf_name = '';
if ( $call_pdf_id > 0 ) {
	$call_pdf_path = get_attached_file( $call_pdf_id );
	$call_pdf_name = $call_pdf_path
		? basename( (string) $call_pdf_path )
		: sprintf( '#%d', $call_pdf_id );
}
?>

<div class="wrap ceafsn-rf-wrap">

	<?php if ( $is_list ) : ?>

		<h1 class="wp-heading-inline"><?php esc_html_e( 'Research Fellowships', 'ceafsn-rf' ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add' ), $base_url ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'ceafsn-rf' ); ?>
		</a>
		<hr class="wp-header-end" />

		<p class="description">
			<?php esc_html_e( 'The status a visitor sees is worked out from the opening and closing dates every time the page loads. A deadline that passes closes the listing on its own.', 'ceafsn-rf' ); ?>
		</p>

		<?php if ( empty( $items ) ) : ?>
			<div class="ceafsn-rf-empty">
				<p><?php esc_html_e( 'No opportunities yet. Add the first one using "Add New".', 'ceafsn-rf' ); ?></p>
				<p class="ceafsn-rf-empty__hint">
					<?php esc_html_e( 'Opportunities stay invisible on the public page until they are published with a real closing date or a documented status override.', 'ceafsn-rf' ); ?>
				</p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped ceafsn-rf-admin-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-rf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Track / domain', 'ceafsn-rf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Opening', 'ceafsn-rf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Closing', 'ceafsn-rf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Shown as', 'ceafsn-rf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Call PDF', 'ceafsn-rf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Record state', 'ceafsn-rf' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-rf' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$today = CEAFSN_RF_Status::today();
					foreach ( $items as $item ) :
						$item_status = CEAFSN_RF_Status::derive( $item, $today );
						?>
						<tr>
							<td><strong><?php echo esc_html( (string) $item->title ); ?></strong></td>
							<td><?php echo esc_html( '' !== (string) $item->track_domain ? (string) $item->track_domain : '—' ); ?></td>
							<td><?php echo esc_html( CEAFSN_RF_Public::format_date( (string) ( $item->opening_date ?? '' ), false ) ?: '—' ); ?></td>
							<td><?php echo esc_html( CEAFSN_RF_Public::format_date( (string) ( $item->closing_date ?? '' ), false ) ?: '—' ); ?></td>
							<td>
								<span class="ceafsn-rf-badge ceafsn-rf-badge--status-<?php echo esc_attr( (string) $item_status['status'] ); ?>">
									<?php echo esc_html( CEAFSN_RF_Status::label( (string) $item_status['status'] ) ); ?>
								</span>
								<?php if ( ! empty( $item->status_override ) ) : ?>
									<span class="ceafsn-rf-badge ceafsn-rf-badge--override">
										<?php esc_html_e( 'Override', 'ceafsn-rf' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( (int) $item->call_pdf_id > 0 ) : ?>
									<span class="ceafsn-rf-badge ceafsn-rf-badge--ok"><?php esc_html_e( 'PDF', 'ceafsn-rf' ); ?></span>
								<?php else : ?>
									<span class="ceafsn-rf-badge ceafsn-rf-badge--warn"><?php esc_html_e( 'None', 'ceafsn-rf' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<span class="ceafsn-rf-badge ceafsn-rf-badge--state-<?php echo esc_attr( (string) $item->status ); ?>">
									<?php echo esc_html( ucfirst( (string) $item->status ) ); ?>
								</span>
							</td>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->fellowship_id ), $base_url ) ); ?>">
									<?php esc_html_e( 'Edit', 'ceafsn-rf' ); ?>
								</a>
								|
								<a class="ceafsn-rf-delete-link"
									href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_rf_delete_fellowship&id=' . (int) $item->fellowship_id ), 'ceafsn_rf_delete_fellowship_' . (int) $item->fellowship_id ) ); ?>">
									<?php esc_html_e( 'Delete', 'ceafsn-rf' ); ?>
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
					? __( 'Edit Opportunity', 'ceafsn-rf' )
					: __( 'Add New Opportunity', 'ceafsn-rf' )
			);
			?>
		</h1>

		<?php if ( $row && $derived ) : ?>
			<div class="notice notice-info inline">
				<p>
					<?php
					printf(
						/* translators: %s: the status a visitor will see. */
						esc_html__( 'This opportunity will be shown to visitors as: %s', 'ceafsn-rf' ),
						'<strong>' . esc_html( CEAFSN_RF_Status::label( (string) $derived['status'] ) ) . '</strong>'
					);
					?>
				</p>
				<?php if ( CEAFSN_RF_Status::UNCONFIRMED === (string) $derived['status'] ) : ?>
					<p class="description">
						<?php esc_html_e( 'The opening date has passed but no closing date is recorded, so the listing cannot say applications are open. Add a closing date, set the status to Upcoming or Closed, or tick the override and explain why.', 'ceafsn-rf' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $row && $validation && ! $validation['valid'] && (int) $row->call_pdf_id > 0 ) : ?>
			<div class="notice notice-warning inline">
				<p><strong><?php esc_html_e( 'The attached call document is not usable:', 'ceafsn-rf' ); ?></strong></p>
				<ul>
					<?php foreach ( $validation['errors'] as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ceafsn-rf-form">
			<?php wp_nonce_field( 'ceafsn_rf_fellowship_nonce', 'ceafsn_rf_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_rf_save_fellowship" />
			<input type="hidden" name="fellowship_id" value="<?php echo esc_attr( (string) (int) ( $row->fellowship_id ?? 0 ) ); ?>" />

			<table class="form-table ceafsn-rf-fields" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-title"><?php esc_html_e( 'Fellowship title', 'ceafsn-rf' ); ?> *</label>
						</th>
						<td>
							<input type="text" id="ceafsn-rf-title" name="title" class="regular-text" required
								value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-track"><?php esc_html_e( 'Track / domain', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="text" id="ceafsn-rf-track" name="track_domain" class="regular-text"
								value="<?php echo esc_attr( (string) ( $row->track_domain ?? '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. Used by the public filter.', 'ceafsn-rf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-duration"><?php esc_html_e( 'Duration', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="text" id="ceafsn-rf-duration" name="duration" class="regular-text"
								placeholder="<?php esc_attr_e( 'e.g. 12 months', 'ceafsn-rf' ); ?>"
								value="<?php echo esc_attr( (string) ( $row->duration ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-eligibility"><?php esc_html_e( 'Eligibility criteria', 'ceafsn-rf' ); ?> *</label>
						</th>
						<td>
							<textarea id="ceafsn-rf-eligibility" name="eligibility" rows="5" class="large-text" required
								aria-describedby="ceafsn-rf-eligibility-help"><?php echo esc_textarea( (string) ( $row->eligibility ?? '' ) ); ?></textarea>
							<p id="ceafsn-rf-eligibility-help" class="description">
								<?php esc_html_e( 'Required. A visitor who cannot tell whether they are eligible will not apply.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-host"><?php esc_html_e( 'Host / supervisor', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="text" id="ceafsn-rf-host" name="host_supervisor" class="regular-text"
								value="<?php echo esc_attr( (string) ( $row->host_supervisor ?? '' ) ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-stipend"><?php esc_html_e( 'Stipend / funding info', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<textarea id="ceafsn-rf-stipend" name="stipend_info" rows="3" class="large-text"
								aria-describedby="ceafsn-rf-stipend-help"><?php echo esc_textarea( (string) ( $row->stipend_info ?? '' ) ); ?></textarea>
							<p id="ceafsn-rf-stipend-help" class="description">
								<?php esc_html_e( 'Stored but never shown until it is approved below.', 'ceafsn-rf' ); ?>
							</p>
							<label for="ceafsn-rf-show-stipend">
								<input type="checkbox" id="ceafsn-rf-show-stipend" name="show_stipend" value="1"
									<?php checked( ! empty( $row->show_stipend ) ); ?> />
								<?php esc_html_e( 'Approved for public display', 'ceafsn-rf' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-opening"><?php esc_html_e( 'Opening date', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="date" id="ceafsn-rf-opening" name="opening_date"
								value="<?php echo esc_attr( CEAFSN_RF_Status::readable_date( $row->opening_date ?? '' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Optional. A future date makes the opportunity Upcoming.', 'ceafsn-rf' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-closing"><?php esc_html_e( 'Closing date', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="date" id="ceafsn-rf-closing" name="closing_date"
								value="<?php echo esc_attr( CEAFSN_RF_Status::readable_date( $row->closing_date ?? '' ) ); ?>" />
							<p id="ceafsn-rf-closing-help" class="description">
								<?php esc_html_e( 'Required for an opportunity to be shown as Open. Compared against today in the site timezone.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-manual"><?php esc_html_e( 'Status when no dates are set', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<select id="ceafsn-rf-manual" name="status_manual">
								<?php foreach ( $manuals as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $value, (string) ( $row->status_manual ?? 'upcoming' ) ); ?>>
										<?php echo esc_html( CEAFSN_RF_Status::label( $value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Only used when neither date is set. "Open" is still refused on publish without a closing date or an override.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<span><?php esc_html_e( 'Status override', 'ceafsn-rf' ); ?></span>
						</th>
						<td>
							<label for="ceafsn-rf-override">
								<input type="checkbox" id="ceafsn-rf-override" name="status_override" value="1"
									<?php checked( ! empty( $row->status_override ) ); ?> />
								<?php esc_html_e( 'Ignore the dates and show this as Open', 'ceafsn-rf' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Use this only when applications really are open past the closing date, for example after a deadline extension agreed with the funder.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-override-note"><?php esc_html_e( 'Override note', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<textarea id="ceafsn-rf-override-note" name="override_note" rows="3" class="large-text"
								aria-describedby="ceafsn-rf-override-help"><?php echo esc_textarea( (string) ( $row->override_note ?? '' ) ); ?></textarea>
							<p id="ceafsn-rf-override-help" class="description">
								<?php esc_html_e( 'Required whenever the override is ticked. This is the audit record of why the dates were set aside.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-app-url"><?php esc_html_e( 'Application URL', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="url" id="ceafsn-rf-app-url" name="application_url" class="regular-text"
								placeholder="https://"
								aria-describedby="ceafsn-rf-app-help"
								value="<?php echo esc_attr( (string) ( $row->application_url ?? '' ) ); ?>" />
							<p id="ceafsn-rf-app-help" class="description">
								<?php esc_html_e( 'A full http or https address. Without one the Apply button is shown as inactive rather than as a broken link.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-pdf-field"><?php esc_html_e( 'Call PDF', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="hidden" id="ceafsn-rf-pdf-id" name="call_pdf_id" value="<?php echo esc_attr( (string) $call_pdf_id ); ?>" />
							<input type="text" id="ceafsn-rf-pdf-field" class="regular-text" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-rf' ); ?>"
								value="<?php echo esc_attr( $call_pdf_name ); ?>" />
							<button type="button" class="button ceafsn-rf-media-button" id="ceafsn-rf-pdf-button"
								data-target="ceafsn-rf-pdf-id">
								<?php esc_html_e( 'Select PDF', 'ceafsn-rf' ); ?>
							</button>
							<button type="button" class="button-link ceafsn-rf-media-clear" id="ceafsn-rf-pdf-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-rf' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Optional. The official call document, attached from the Media Library.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-email"><?php esc_html_e( 'Contact email', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<input type="email" id="ceafsn-rf-email" name="contact_email" class="regular-text"
								aria-describedby="ceafsn-rf-email-help"
								value="<?php echo esc_attr( (string) ( $row->contact_email ?? '' ) ); ?>" />
							<label for="ceafsn-rf-show-contact">
								<input type="checkbox" id="ceafsn-rf-show-contact" name="show_contact" value="1"
									<?php checked( ! empty( $row->show_contact ) ); ?> />
								<?php esc_html_e( 'Approved for public display', 'ceafsn-rf' ); ?>
							</label>
							<p id="ceafsn-rf-email-help" class="description">
								<?php esc_html_e( 'Stored but never shown until it is approved. Leave the address blank if there is no verified contact.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ceafsn-rf-status"><?php esc_html_e( 'Record state', 'ceafsn-rf' ); ?></label>
						</th>
						<td>
							<select id="ceafsn-rf-status" name="status">
								<?php foreach ( $statuses as $value ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>"
										<?php selected( $value, (string) ( $row->status ?? 'draft' ) ); ?>>
										<?php echo esc_html( ucfirst( $value ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Only Published opportunities appear on the public page.', 'ceafsn-rf' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save opportunity', 'ceafsn-rf' ); ?></button>
				<a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-rf' ); ?></a>
			</p>
		</form>

	<?php endif; ?>
</div>
