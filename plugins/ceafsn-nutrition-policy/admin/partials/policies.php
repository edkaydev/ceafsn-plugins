<?php
/**
 * Admin partial: Policies list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_NP_Admin::page_policies()):
 *   @var string            $action         'list' | 'add' | 'edit'
 *   @var int               $id             Row ID when editing
 *   @var object|null       $row            DB row when editing
 *   @var array<int,object> $items          All policy rows
 *   @var array<int,string> $topics         Distinct published topics
 *   @var array|null        $validation     Validator result for the attached PDF
 *   @var int               $duplicate_count Other records sharing the same PDF
 *
 * Element IDs ceafsn-np-pdf-id, ceafsn-np-pdf-field, ceafsn-np-media-button,
 * ceafsn-np-media-clear, and the class ceafsn-np-delete-link are a contract with
 * assets/js/ceafsn-np-admin.js. Do not rename them.
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
$np_base_url = admin_url( 'admin.php?page=' . CEAFSN_NP_Admin::PAGE_POLICIES );
$np_form_url = admin_url( 'admin-post.php' );
$np_statuses = CEAFSN_NP_DB::statuses();
$np_is_list  = 'list' === $action;
?>

<div class="wrap ceafsn-np-wrap">

	<?php if ( $np_is_list ) : ?>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · Nutrition Policy', 'ceafsn-np' ); ?></p>
			<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Policy records', 'ceafsn-np' ); ?></h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'Each record needs its own PDF. A record only reaches the public page once the document is readable, is not a known placeholder, and has a readable page count.', 'ceafsn-np' ); ?>
			</p>
			<div class="ceafsn-hero__actions">
				<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( add_query_arg( 'action', 'add', $np_base_url ) ); ?>">
					<?php esc_html_e( '+ Add policy record', 'ceafsn-np' ); ?>
				</a>
				<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_NP_Admin::MENU_SLUG ) ); ?>">
					<?php esc_html_e( 'Back to overview', 'ceafsn-np' ); ?>
				</a>
			</div>
			<p class="ceafsn-hero__meta">
				<span>
					<?php
					printf(
						/* translators: %s: total record count. */
						esc_html__( '<strong>%s</strong> records', 'ceafsn-np' ),
						esc_html( number_format_i18n( count( $items ) ) )
					);
					?>
				</span>
				<span><?php esc_html_e( 'Drafts stay hidden from visitors until published', 'ceafsn-np' ); ?></span>
			</p>
		</section>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title">
						<?php
						printf(
							/* translators: %s: number of policy records. */
							esc_html__( 'All records (%s)', 'ceafsn-np' ),
							esc_html( number_format_i18n( count( $items ) ) )
						);
						?>
					</h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Drafts stay hidden from visitors until published.', 'ceafsn-np' ); ?></p>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="ceafsn-empty">
					<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
					<h3 class="ceafsn-empty__title"><?php esc_html_e( 'No policy records yet', 'ceafsn-np' ); ?></h3>
					<p class="ceafsn-empty__text">
						<?php esc_html_e( 'Nothing has been recorded. Records stay invisible on the public page until they are published with a validated PDF.', 'ceafsn-np' ); ?>
					</p>
					<div class="ceafsn-empty__action">
						<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( add_query_arg( 'action', 'add', $np_base_url ) ); ?>">
							<?php esc_html_e( 'Add the first record', 'ceafsn-np' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<div class="ceafsn-table-wrap">
					<table class="ceafsn-table">
						<caption class="ceafsn-sr"><?php esc_html_e( 'Policy records list', 'ceafsn-np' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Title & date', 'ceafsn-np' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Topic', 'ceafsn-np' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Institution', 'ceafsn-np' ); ?></th>
								<th scope="col"><?php esc_html_e( 'PDF', 'ceafsn-np' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-np' ); ?></th>
								<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-np' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<?php
								$np_item_status = (string) $item->status;
								$np_item_pdf    = (int) $item->pdf_attachment_id;
								?>
								<tr>
									<td>
										<span class="ceafsn-table__title">
											<span class="ceafsn-dot <?php echo $np_item_pdf > 0 ? 'ceafsn-dot--green' : 'ceafsn-dot--danger'; ?>" aria-hidden="true"></span>
											<?php echo esc_html( (string) $item->title ); ?>
											<span class="ceafsn-table__meta"><?php echo esc_html( (string) $item->publication_date ); ?></span>
										</span>
									</td>
									<td><?php echo esc_html( (string) $item->topic ); ?></td>
									<td><?php echo esc_html( (string) $item->authoring_institution ); ?></td>
									<td>
										<?php if ( $np_item_pdf > 0 ) : ?>
											<span class="ceafsn-badge ceafsn-badge--verified">
												<?php echo esc_html( (string) ( $item->pdf_filename ?: __( 'Attached', 'ceafsn-np' ) ) ); ?>
											</span>
										<?php else : ?>
											<span class="ceafsn-badge ceafsn-badge--unverified"><?php esc_html_e( 'No PDF', 'ceafsn-np' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( $np_item_status ); ?>">
											<?php echo esc_html( ucfirst( $np_item_status ) ); ?>
										</span>
									</td>
									<td class="ceafsn-table__actions">
										<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->policy_id ), $np_base_url ) ); ?>">
											<?php esc_html_e( 'Edit', 'ceafsn-np' ); ?>
										</a>
										<a class="ceafsn-np-delete-link"
											href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_np_delete_policy&id=' . (int) $item->policy_id ), 'ceafsn_np_delete_policy_' . (int) $item->policy_id ) ); ?>">
											<?php esc_html_e( 'Delete', 'ceafsn-np' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>

	<?php else : ?>

		<a class="ceafsn-back" href="<?php echo esc_url( $np_base_url ); ?>">
			&larr; <?php esc_html_e( 'Back to records', 'ceafsn-np' ); ?>
		</a>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · Nutrition Policy', 'ceafsn-np' ); ?></p>
			<h1 class="ceafsn-hero__title">
				<?php
				echo esc_html(
					$row
						? __( 'Edit policy record', 'ceafsn-np' )
						: __( 'Add policy record', 'ceafsn-np' )
				);
				?>
			</h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'A title, description, topic, institution, date, and PDF are all required. Publishing is blocked until the PDF validates.', 'ceafsn-np' ); ?>
			</p>
		</section>

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

		<section class="ceafsn-card">
			<div class="ceafsn-card__body">
				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $np_form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_np_policy_nonce', 'ceafsn_np_nonce' ); ?>
					<input type="hidden" name="action" value="ceafsn_np_save_policy" />
					<input type="hidden" name="policy_id" value="<?php echo esc_attr( (string) ( $id > 0 ? $id : 0 ) ); ?>" />

					<div class="ceafsn-field">
						<label for="ceafsn-np-title">
							<?php esc_html_e( 'Policy title', 'ceafsn-np' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-np-title" name="title" required
							value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-np-description">
							<?php esc_html_e( 'Description', 'ceafsn-np' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<textarea id="ceafsn-np-description" name="description" rows="5" required><?php echo esc_textarea( (string) ( $row->description ?? '' ) ); ?></textarea>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'A plain-language summary of what the policy covers.', 'ceafsn-np' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-np-publication-date">
							<?php esc_html_e( 'Publication date', 'ceafsn-np' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="date" id="ceafsn-np-publication-date" name="publication_date" required
							value="<?php echo esc_attr( (string) ( $row->publication_date ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-np-topic">
							<?php esc_html_e( 'Topic / domain', 'ceafsn-np' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-np-topic" name="topic" list="ceafsn-np-topic-list" required
							value="<?php echo esc_attr( (string) ( $row->topic ?? '' ) ); ?>" />
						<datalist id="ceafsn-np-topic-list">
							<?php foreach ( $topics as $topic ) : ?>
								<option value="<?php echo esc_attr( $topic ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-np-institution">
							<?php esc_html_e( 'Authoring institution', 'ceafsn-np' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input type="text" id="ceafsn-np-institution" name="authoring_institution" required
							value="<?php echo esc_attr( (string) ( $row->authoring_institution ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-np-pdf-field">
							<?php esc_html_e( 'PDF attachment', 'ceafsn-np' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<div class="ceafsn-np-media">
							<input type="hidden" id="ceafsn-np-pdf-id" name="pdf_attachment_id"
								value="<?php echo esc_attr( (string) ( $row->pdf_attachment_id ?? 0 ) ); ?>" />
							<input type="text" id="ceafsn-np-pdf-field" class="ceafsn-np-media__name" readonly
								placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-np' ); ?>"
								value="<?php echo esc_attr( (string) ( $row->pdf_filename ?? '' ) ); ?>" />
							<button type="button" class="ceafsn-btn ceafsn-btn--quiet" id="ceafsn-np-media-button">
								<?php esc_html_e( 'Select PDF', 'ceafsn-np' ); ?>
							</button>
							<button type="button" class="ceafsn-np-media__clear" id="ceafsn-np-media-clear" hidden>
								<?php esc_html_e( 'Clear', 'ceafsn-np' ); ?>
							</button>
						</div>
						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'The file name is read from the attachment itself. A record can only be published when this PDF is readable, is not a known placeholder, and has a readable page count.', 'ceafsn-np' ); ?>
						</p>
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-np-source-url"><?php esc_html_e( 'Source / citation URL', 'ceafsn-np' ); ?></label>
						<input type="url" id="ceafsn-np-source-url" name="source_url"
							value="<?php echo esc_attr( (string) ( $row->source_url ?? '' ) ); ?>" />
					</div>

					<div class="ceafsn-field">
						<label for="ceafsn-np-status">
							<?php esc_html_e( 'Status', 'ceafsn-np' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<select id="ceafsn-np-status" name="status">
							<?php foreach ( $np_statuses as $status ) : ?>
								<option value="<?php echo esc_attr( $status ); ?>"
									<?php selected( $status, (string) ( $row->status ?? 'draft' ) ); ?>>
									<?php echo esc_html( ucfirst( $status ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Choosing Published is only honoured when the PDF passes validation.', 'ceafsn-np' ); ?></p>
					</div>

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

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php esc_html_e( 'Save policy', 'ceafsn-np' ); ?>
						</button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $np_base_url ); ?>">
							<?php esc_html_e( 'Cancel', 'ceafsn-np' ); ?>
						</a>
					</div>
				</form>
			</div>
		</section>

	<?php endif; ?>

</div><!-- .ceafsn-np-wrap -->