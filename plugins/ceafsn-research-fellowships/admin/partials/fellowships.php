<?php
/**
 * Admin partial: fellowships list, add, and edit view.
 *
 * Layout: WordPress admin menu on the left, a main canvas in the middle, and a
 * narrower right rail for calls to action and publishing guidance.
 *
 * Variables in scope (set by CEAFSN_RF_Admin::page_fellowships()):
 *   @var string             $action      'list' | 'add' | 'edit'
 *   @var int                $id          Row ID when editing
 *   @var object|null        $row         DB row when editing
 *   @var array<int,object>  $items       All fellowship rows
 *   @var array|null         $derived     Derived status for the edited row
 *   @var array|null         $validation  Call PDF validation result
 *
 * The media picker contract with assets/js/ceafsn-rf-admin.js: the hidden
 * input, the readonly filename box, and the clear button all live inside the
 * same <td>, and the select button carries data-target="ceafsn-rf-pdf-id".
 * The field rows therefore stay a two-column table. Do not restructure them.
 *
 * The status a visitor sees is derived from the dates on every page load, so
 * every count below is counted from stored rows and nothing is estimated.
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

$base_url    = admin_url( 'admin.php?page=' . CEAFSN_RF_Admin::MENU_SLUG );
$is_list     = 'list' === $action;
$statuses    = CEAFSN_RF_DB::statuses();
$manuals     = CEAFSN_RF_Status::manual_values();
$add_url     = add_query_arg( array( 'action' => 'add' ), $base_url );
$export_url  = admin_url( 'admin.php?page=' . CEAFSN_RF_Admin::SETTINGS_SLUG . '&tab=export' );
$display_url = admin_url( 'admin.php?page=' . CEAFSN_RF_Admin::SETTINGS_SLUG . '&tab=display' );

// The pickers show which file is attached, so the name has to come from the
// attachment itself. A record whose call PDF has been deleted from the media
// library falls back to the ID rather than pretending the field is empty.
$call_pdf_id   = (int) ( $row->call_pdf_id ?? 0 );
$call_pdf_name = '';
if ( $call_pdf_id > 0 ) {
	$call_pdf_path = get_attached_file( $call_pdf_id );
	$call_pdf_name = $call_pdf_path
		? basename( (string) $call_pdf_path )
		: sprintf( '#%d', $call_pdf_id );
}

// One derived status per row, worked out with the same code the front end
// uses, so the list and the public page can never disagree.
$today            = CEAFSN_RF_Status::today();
$rf_row_statuses  = array();
$rf_total         = count( $items );
$rf_open          = 0;
$rf_upcoming      = 0;
$rf_closed        = 0;
$rf_unconfirmed   = 0;
$rf_with_pdf      = 0;
$rf_no_pdf        = 0;
$rf_overrides     = 0;
$rf_published     = 0;
$rf_drafts        = 0;
$rf_no_closing    = 0;

foreach ( $items as $rf_item ) {
	$rf_status = CEAFSN_RF_Status::derive( $rf_item, $today );
	$rf_row_statuses[ (int) $rf_item->fellowship_id ] = $rf_status;

	switch ( (string) $rf_status['status'] ) {
		case CEAFSN_RF_Status::OPEN:
			++$rf_open;
			break;
		case CEAFSN_RF_Status::UPCOMING:
			++$rf_upcoming;
			break;
		case CEAFSN_RF_Status::CLOSED:
			++$rf_closed;
			break;
		default:
			++$rf_unconfirmed;
			break;
	}

	if ( (int) $rf_item->call_pdf_id > 0 ) {
		++$rf_with_pdf;
	} else {
		++$rf_no_pdf;
	}
	if ( ! empty( $rf_item->status_override ) ) {
		++$rf_overrides;
	}
	if ( 'no_closing_date' === (string) $rf_status['reason'] ) {
		++$rf_no_closing;
	}
	if ( 'published' === (string) $rf_item->status ) {
		++$rf_published;
	} elseif ( 'draft' === (string) $rf_item->status ) {
		++$rf_drafts;
	}
}
?>

<div class="wrap ceafsn-rf-wrap">

	<a class="ceafsn-sr" href="#ceafsn-rf-main"><?php esc_html_e( 'Skip to record content', 'ceafsn-rf' ); ?></a>

	<div id="ceafsn-rf-main">

	<?php if ( ! empty( $_GET['ceafsn_rf_error'] ) ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
				<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title"><?php esc_html_e( 'That change was not saved', 'ceafsn-rf' ); ?></p>
					<p class="ceafsn-alert__text">
						<?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_rf_error'] ) ) ) ); ?>
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['saved'] ) || ! empty( $_GET['deleted'] ) ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--ok" role="status">
				<span class="ceafsn-alert__icon" aria-hidden="true">&#10003;</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title">
						<?php
						echo esc_html(
							! empty( $_GET['deleted'] )
								? __( 'Opportunity deleted.', 'ceafsn-rf' )
								: __( 'Opportunity saved.', 'ceafsn-rf' )
						);
						?>
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( $is_list ) : ?>

		<div class="ceafsn-app">

			<div class="ceafsn-app__main">

				<?php if ( 0 === $rf_total ) : ?>

					<div class="ceafsn-alerts">
						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title"><?php esc_html_e( 'No opportunities yet', 'ceafsn-rf' ); ?></p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'Nothing appears on the public page until an opportunity is published with a real closing date, or with a documented status override.', 'ceafsn-rf' ); ?>
									<a class="ceafsn-alert__action" href="<?php echo esc_url( $add_url ); ?>"><?php esc_html_e( 'Add the first opportunity', 'ceafsn-rf' ); ?></a>
								</p>
							</div>
						</div>
					</div>

				<?php elseif ( $rf_unconfirmed > 0 ) : ?>

					<div class="ceafsn-alerts">
						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title">
									<?php
									printf(
										/* translators: %s: number of opportunities whose status is unconfirmed. */
										esc_html( _n(
											'%s opportunity has no confirmed status',
											'%s opportunities have no confirmed status',
											$rf_unconfirmed,
											'ceafsn-rf'
										) ),
										esc_html( number_format_i18n( $rf_unconfirmed ) )
									);
									?>
								</p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'A visitor cannot apply to an opportunity that will not say whether applications are open. Add a closing date, or set the status yourself.', 'ceafsn-rf' ); ?>
								</p>
							</div>
						</div>
					</div>

				<?php endif; ?>

				<section class="ceafsn-hero">
					<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-rf' ); ?></p>
					<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Research Fellowships', 'ceafsn-rf' ); ?></h1>
					<p class="ceafsn-hero__text">
						<?php esc_html_e( 'Calls for applications. The status a visitor sees is worked out from the opening and closing dates every time the page loads, so a deadline that passes closes the listing on its own.', 'ceafsn-rf' ); ?>
					</p>
					<div class="ceafsn-hero__actions">
						<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $add_url ); ?>">
							<?php esc_html_e( '+ Add opportunity', 'ceafsn-rf' ); ?>
						</a>
						<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $export_url ); ?>">
							<?php esc_html_e( 'Export', 'ceafsn-rf' ); ?>
						</a>
					</div>
					<p class="ceafsn-hero__meta">
						<span>
							<?php
							printf(
								/* translators: %s: total opportunity count. */
								wp_kses_post( __( '<strong>%s</strong> opportunities', 'ceafsn-rf' ) ),
								esc_html( number_format_i18n( $rf_total ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: count of open opportunities. */
								wp_kses_post( __( '<strong>%s</strong> open', 'ceafsn-rf' ) ),
								esc_html( number_format_i18n( $rf_open ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: count of upcoming opportunities. */
								wp_kses_post( __( '<strong>%s</strong> upcoming', 'ceafsn-rf' ) ),
								esc_html( number_format_i18n( $rf_upcoming ) )
							);
							?>
						</span>
						<span>
							<?php
							printf(
								/* translators: %s: count of published opportunities. */
								wp_kses_post( __( '<strong>%s</strong> published', 'ceafsn-rf' ) ),
								esc_html( number_format_i18n( $rf_published ) )
							);
							?>
						</span>
					</p>
				</section>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display on a page', 'ceafsn-rf' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Nothing is visible to visitors until this shortcode is on a page.', 'ceafsn-rf' ); ?></p>
						</div>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $display_url ); ?>">
							<?php esc_html_e( 'Attributes', 'ceafsn-rf' ); ?>
						</a>
					</div>
					<div class="ceafsn-card__body">
						<div class="ceafsn-embed">
							<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste into the page that should list opportunities', 'ceafsn-rf' ); ?></p>
							<code class="ceafsn-embed__code">[ceafsn_fellowships]</code>
						</div>
						<p class="ceafsn-card__hint">
							<?php esc_html_e( 'It takes optional per_page, track_domain, status, and view attributes — open Display in settings for the full list.', 'ceafsn-rf' ); ?>
						</p>
					</div>
				</section>

				<?php if ( $rf_total > 0 ) : ?>

					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<div>
								<h2 class="ceafsn-card__title"><?php esc_html_e( 'Publishing workflow', 'ceafsn-rf' ); ?></h2>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'Counted from your opportunities. A step is only complete when the numbers prove it.', 'ceafsn-rf' ); ?></p>
							</div>
						</div>
						<div class="ceafsn-card__body">
							<?php
							// The first step still at zero is the current one.
							$rf_steps = array(
								array(
									'label' => __( 'Opportunities added', 'ceafsn-rf' ),
									'meta'  => sprintf( /* translators: %s: number of opportunities. */ __( '%s records', 'ceafsn-rf' ), number_format_i18n( $rf_total ) ),
									'count' => $rf_total,
								),
								array(
									'label' => __( 'Dates confirmed', 'ceafsn-rf' ),
									'meta'  => sprintf( /* translators: 1: open or upcoming count, 2: total. */ __( '%1$s of %2$s show a real status', 'ceafsn-rf' ), number_format_i18n( $rf_open + $rf_upcoming ), number_format_i18n( $rf_total ) ),
									'count' => $rf_open + $rf_upcoming,
								),
								array(
									'label' => __( 'Published', 'ceafsn-rf' ),
									'meta'  => sprintf( /* translators: %s: number of published opportunities. */ __( '%s visible to visitors', 'ceafsn-rf' ), number_format_i18n( $rf_published ) ),
									'count' => $rf_published,
								),
							);

							$rf_current_step = null;
							foreach ( $rf_steps as $rf_index => $rf_step ) {
								if ( $rf_step['count'] > 0 ) {
									$rf_steps[ $rf_index ]['state'] = 'done';
								} elseif ( null === $rf_current_step ) {
									$rf_steps[ $rf_index ]['state'] = 'current';
									$rf_current_step                = $rf_index;
								} else {
									$rf_steps[ $rf_index ]['state'] = 'todo';
								}
							}
							?>
							<ol class="ceafsn-stepper">
								<?php foreach ( $rf_steps as $rf_index => $rf_step ) : ?>
									<li class="ceafsn-step is-<?php echo esc_attr( $rf_step['state'] ); ?>">
										<span class="ceafsn-step__dot" aria-hidden="true">
											<?php
											if ( 'done' === $rf_step['state'] ) {
												echo '&#10003;';
											} else {
												echo esc_html( (string) ( $rf_index + 1 ) );
											}
											?>
										</span>
										<span class="ceafsn-step__label"><?php echo esc_html( $rf_step['label'] ); ?></span>
										<span class="ceafsn-step__meta"><?php echo esc_html( $rf_step['meta'] ); ?></span>
									</li>
								<?php endforeach; ?>
							</ol>
							<p class="ceafsn-card__hint">
								<?php esc_html_e( 'Current step:', 'ceafsn-rf' ); ?>
								<strong>
									<?php
									echo esc_html(
										null !== $rf_current_step
											? $rf_steps[ $rf_current_step ]['label']
											: __( 'All steps complete', 'ceafsn-rf' )
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
								<?php esc_html_e( 'Opportunities', 'ceafsn-rf' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $rf_total ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'In this plugin', 'ceafsn-rf' ); ?></span>
						</div>

						<div class="ceafsn-kpi">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot ceafsn-dot--green" aria-hidden="true"></span>
								<?php esc_html_e( 'Open', 'ceafsn-rf' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $rf_open ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Apply button active', 'ceafsn-rf' ); ?></span>
						</div>

						<div class="ceafsn-kpi <?php echo $rf_unconfirmed > 0 ? 'ceafsn-kpi--alert' : ''; ?>">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot <?php echo $rf_unconfirmed > 0 ? 'ceafsn-dot--gold' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
								<?php esc_html_e( 'Status not confirmed', 'ceafsn-rf' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $rf_unconfirmed ) ); ?></span>
							<span class="ceafsn-kpi__meta">
								<?php
								if ( $rf_unconfirmed > 0 ) {
									esc_html_e( 'Visitors see a warning', 'ceafsn-rf' );
								} else {
									esc_html_e( 'Every record has a status', 'ceafsn-rf' );
								}
								?>
							</span>
						</div>

						<div class="ceafsn-kpi">
							<span class="ceafsn-kpi__label">
								<span class="ceafsn-dot <?php echo $rf_no_pdf > 0 ? 'ceafsn-dot--gold' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
								<?php esc_html_e( 'No call PDF', 'ceafsn-rf' ); ?>
							</span>
							<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $rf_no_pdf ) ); ?></span>
							<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Optional, not blocking', 'ceafsn-rf' ); ?></span>
						</div>
					</div>

				<?php endif; ?>

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title">
								<?php esc_html_e( 'All opportunities', 'ceafsn-rf' ); ?>
								<?php if ( $rf_total > 0 ) : ?>
									<span class="ceafsn-pill ceafsn-pill--green"><?php echo esc_html( number_format_i18n( $rf_total ) ); ?></span>
								<?php endif; ?>
							</h2>
							<?php if ( $rf_total > 0 ) : ?>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'The shown status is recalculated from the dates every time this page loads.', 'ceafsn-rf' ); ?></p>
							<?php endif; ?>
						</div>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $add_url ); ?>">
							<?php esc_html_e( '+ Add opportunity', 'ceafsn-rf' ); ?>
						</a>
					</div>

					<?php if ( 0 === $rf_total ) : ?>

						<div class="ceafsn-empty">
							<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
							<h3 class="ceafsn-empty__title"><?php esc_html_e( 'Nothing to show yet', 'ceafsn-rf' ); ?></h3>
							<p class="ceafsn-empty__text">
								<?php esc_html_e( 'Opportunities stay invisible on the public page until they are published with a real closing date or a documented status override.', 'ceafsn-rf' ); ?>
							</p>
							<div class="ceafsn-empty__action">
								<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $add_url ); ?>">
									<?php esc_html_e( 'Add the first opportunity', 'ceafsn-rf' ); ?>
								</a>
							</div>
						</div>

					<?php else : ?>

						<div class="ceafsn-table-wrap">
							<table class="ceafsn-table">
								<caption class="ceafsn-sr"><?php esc_html_e( 'Research fellowship opportunities', 'ceafsn-rf' ); ?></caption>
								<thead>
									<tr>
										<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-rf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Track / domain', 'ceafsn-rf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Opening', 'ceafsn-rf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Closing', 'ceafsn-rf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Shown as', 'ceafsn-rf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Call PDF', 'ceafsn-rf' ); ?></th>
										<th scope="col"><?php esc_html_e( 'Record state', 'ceafsn-rf' ); ?></th>
										<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-rf' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									foreach ( $items as $item ) :
										$item_status    = $rf_row_statuses[ (int) $item->fellowship_id ];
										$item_open      = CEAFSN_RF_Status::OPEN === (string) $item_status['status'];
										$item_track     = (string) $item->track_domain;
										$item_opening   = CEAFSN_RF_Public::format_date( (string) ( $item->opening_date ?? '' ), false );
										$item_closing   = CEAFSN_RF_Public::format_date( (string) ( $item->closing_date ?? '' ), false );
										?>
										<tr>
											<td>
												<span class="ceafsn-table__title">
													<span class="ceafsn-dot <?php echo $item_open ? 'ceafsn-dot--green' : 'ceafsn-dot--grey'; ?>" aria-hidden="true"></span>
													<?php echo esc_html( (string) $item->title ); ?>
													<?php if ( ! empty( $item->duration ) ) : ?>
														<span class="ceafsn-table__meta"><?php echo esc_html( (string) $item->duration ); ?></span>
													<?php endif; ?>
												</span>
											</td>
											<td>
												<?php if ( '' !== $item_track ) : ?>
													<?php echo esc_html( $item_track ); ?>
												<?php else : ?>
													<span class="ceafsn-badge"><?php esc_html_e( 'All tracks', 'ceafsn-rf' ); ?></span>
												<?php endif; ?>
											</td>
											<td><?php echo esc_html( $item_opening ?: '—' ); ?></td>
											<td><?php echo esc_html( $item_closing ?: '—' ); ?></td>
											<td>
												<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( (string) $item_status['status'] ); ?>">
													<?php echo esc_html( CEAFSN_RF_Status::label( (string) $item_status['status'] ) ); ?>
												</span>
												<?php if ( ! empty( $item->status_override ) ) : ?>
													<span class="ceafsn-badge ceafsn-badge--override">
														<?php esc_html_e( 'Override', 'ceafsn-rf' ); ?>
													</span>
												<?php endif; ?>
											</td>
											<td>
												<?php if ( (int) $item->call_pdf_id > 0 ) : ?>
													<span class="ceafsn-badge ceafsn-badge--ok"><?php esc_html_e( 'PDF', 'ceafsn-rf' ); ?></span>
												<?php else : ?>
													<span class="ceafsn-badge ceafsn-badge--warn"><?php esc_html_e( 'None', 'ceafsn-rf' ); ?></span>
												<?php endif; ?>
											</td>
											<td>
												<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( (string) $item->status ); ?>">
													<?php echo esc_html( ucfirst( (string) $item->status ) ); ?>
												</span>
											</td>
											<td class="ceafsn-table__actions">
												<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'edit', 'id' => (int) $item->fellowship_id ), $base_url ) ); ?>">
													<?php esc_html_e( 'Edit', 'ceafsn-rf' ); ?>
												</a>
												<a class="ceafsn-rf-delete-link"
													href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_rf_delete_fellowship&id=' . (int) $item->fellowship_id ), 'ceafsn_rf_delete_fellowship_' . (int) $item->fellowship_id ) ); ?>">
													<?php esc_html_e( 'Delete', 'ceafsn-rf' ); ?>
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
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Get started', 'ceafsn-rf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add an opportunity', 'ceafsn-rf' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'Applicants need three things to be able to apply: eligibility criteria, a closing date, and somewhere to send them.', 'ceafsn-rf' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( $add_url ); ?>">
						<?php esc_html_e( '+ Add opportunity', 'ceafsn-rf' ); ?>
					</a>
				</div>

				<div class="ceafsn-cta" id="ceafsn-rf-help">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'How status works', 'ceafsn-rf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Dates decide the status', 'ceafsn-rf' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'A future opening date makes it Upcoming.', 'ceafsn-rf' ); ?></li>
						<li><?php esc_html_e( 'A closing date that has not passed makes it Open.', 'ceafsn-rf' ); ?></li>
						<li><?php esc_html_e( 'A closing date in the past makes it Closed, on its own.', 'ceafsn-rf' ); ?></li>
						<li><?php esc_html_e( 'An opening date with no closing date cannot be confirmed, and the Apply button is inactive.', 'ceafsn-rf' ); ?></li>
						<li><?php esc_html_e( 'The override exists for real deadline extensions, and needs a note.', 'ceafsn-rf' ); ?></li>
					</ul>
				</div>

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Before you publish', 'ceafsn-rf' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Check these', 'ceafsn-rf' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'The application URL is a full http or https address, or the button is shown as inactive rather than broken.', 'ceafsn-rf' ); ?></li>
						<li><?php esc_html_e( 'Stipend and contact details stay hidden until you approve them for display.', 'ceafsn-rf' ); ?></li>
						<li><?php esc_html_e( 'A call PDF is optional, but an unreadable one is flagged.', 'ceafsn-rf' ); ?></li>
						<li><?php esc_html_e( 'Files stay in the media library even if the record is deleted.', 'ceafsn-rf' ); ?></li>
					</ul>
				</div>

			</aside>

		</div><!-- .ceafsn-app -->

	<?php else : ?>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-rf' ); ?></p>
			<h1 class="ceafsn-hero__title">
				<?php
				echo esc_html(
					$row
						? __( 'Edit opportunity', 'ceafsn-rf' )
						: __( 'Add opportunity', 'ceafsn-rf' )
				);
				?>
			</h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'An applicant should be able to read this page and know whether they can apply, until when, and what to send.', 'ceafsn-rf' ); ?>
			</p>
		</section>

		<?php if ( $row && $derived ) : ?>
			<div class="ceafsn-alerts">
				<div class="ceafsn-alert <?php echo CEAFSN_RF_Status::OPEN === (string) $derived['status'] ? 'ceafsn-alert--ok' : 'ceafsn-alert--warn'; ?>">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title">
							<?php esc_html_e( 'This opportunity will be shown to visitors as:', 'ceafsn-rf' ); ?>
							<strong><?php echo esc_html( CEAFSN_RF_Status::label( (string) $derived['status'] ) ); ?></strong>
						</p>
						<?php if ( CEAFSN_RF_Status::UNCONFIRMED === (string) $derived['status'] ) : ?>
							<p class="ceafsn-alert__text">
								<?php esc_html_e( 'The opening date has passed but no closing date is recorded, so the listing cannot say applications are open. Add a closing date, set the status to Upcoming or Closed, or tick the override and explain why.', 'ceafsn-rf' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $row && $validation && ! $validation['valid'] && $call_pdf_id > 0 ) : ?>
			<div class="ceafsn-alerts">
				<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
					<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'The attached call document is not usable:', 'ceafsn-rf' ); ?></p>
						<ul class="ceafsn-alert__list">
							<?php foreach ( $validation['errors'] as $error ) : ?>
								<li><?php echo esc_html( $error ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<form class="ceafsn-form ceafsn-form__spaced" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'ceafsn_rf_fellowship_nonce', 'ceafsn_rf_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_rf_save_fellowship" />
			<input type="hidden" name="fellowship_id" value="<?php echo esc_attr( (string) ( $row->fellowship_id ?? 0 ) ); ?>" />

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'The opportunity', 'ceafsn-rf' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Fields marked with an asterisk are required.', 'ceafsn-rf' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">
					<table class="ceafsn-rows" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-title">
										<?php esc_html_e( 'Fellowship title', 'ceafsn-rf' ); ?>
										<span class="required" aria-hidden="true">*</span>
									</label>
								</th>
								<td>
									<input type="text" id="ceafsn-rf-title" name="title" required
										value="<?php echo esc_attr( (string) ( $row->title ?? '' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-track"><?php esc_html_e( 'Track / domain', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<input type="text" id="ceafsn-rf-track" name="track_domain"
										value="<?php echo esc_attr( (string) ( $row->track_domain ?? '' ) ); ?>" />
									<p class="ceafsn-field__hint"><?php esc_html_e( 'Optional. Used by the public filter.', 'ceafsn-rf' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-duration"><?php esc_html_e( 'Duration', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<input type="text" id="ceafsn-rf-duration" name="duration"
										placeholder="<?php esc_attr_e( 'e.g. 12 months', 'ceafsn-rf' ); ?>"
										value="<?php echo esc_attr( (string) ( $row->duration ?? '' ) ); ?>" />
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-eligibility">
										<?php esc_html_e( 'Eligibility criteria', 'ceafsn-rf' ); ?>
										<span class="required" aria-hidden="true">*</span>
									</label>
								</th>
								<td>
									<textarea id="ceafsn-rf-eligibility" name="eligibility" rows="5" required
										aria-describedby="ceafsn-rf-eligibility-help"><?php echo esc_textarea( (string) ( $row->eligibility ?? '' ) ); ?></textarea>
									<p class="ceafsn-field__hint" id="ceafsn-rf-eligibility-help">
										<?php esc_html_e( 'Required. A visitor who cannot tell whether they are eligible will not apply.', 'ceafsn-rf' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-host"><?php esc_html_e( 'Host / supervisor', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<input type="text" id="ceafsn-rf-host" name="host_supervisor"
										value="<?php echo esc_attr( (string) ( $row->host_supervisor ?? '' ) ); ?>" />
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Dates and status', 'ceafsn-rf' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'The status a visitor sees is worked out from these dates every time the page loads.', 'ceafsn-rf' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">
					<table class="ceafsn-rows" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-opening"><?php esc_html_e( 'Opening date', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<input type="date" id="ceafsn-rf-opening" name="opening_date"
										value="<?php echo esc_attr( CEAFSN_RF_Status::readable_date( $row->opening_date ?? '' ) ); ?>" />
									<p class="ceafsn-field__hint"><?php esc_html_e( 'Optional. A future date makes the opportunity Upcoming.', 'ceafsn-rf' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-closing"><?php esc_html_e( 'Closing date', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<input type="date" id="ceafsn-rf-closing" name="closing_date"
										value="<?php echo esc_attr( CEAFSN_RF_Status::readable_date( $row->closing_date ?? '' ) ); ?>" />
									<p class="ceafsn-field__hint" id="ceafsn-rf-closing-help">
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
									<p class="ceafsn-field__hint">
										<?php esc_html_e( 'Only used when neither date is set. "Open" is still refused on publish without a closing date or an override.', 'ceafsn-rf' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<span class="ceafsn-field__label"><?php esc_html_e( 'Status override', 'ceafsn-rf' ); ?></span>
								</th>
								<td>
									<div class="ceafsn-check">
										<input type="checkbox" id="ceafsn-rf-override" name="status_override" value="1"
											<?php checked( ! empty( $row->status_override ) ); ?>
											aria-describedby="ceafsn-rf-override-help" />
										<div class="ceafsn-check__body">
											<label class="ceafsn-check__title" for="ceafsn-rf-override">
												<?php esc_html_e( 'Ignore the dates and show this as Open', 'ceafsn-rf' ); ?>
											</label>
											<span class="ceafsn-check__hint" id="ceafsn-rf-override-help">
												<?php esc_html_e( 'Use this only when applications really are open past the closing date, for example after a deadline extension agreed with the funder.', 'ceafsn-rf' ); ?>
											</span>
										</div>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-override-note"><?php esc_html_e( 'Override note', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<textarea id="ceafsn-rf-override-note" name="override_note" rows="3"
										aria-describedby="ceafsn-rf-override-note-help"><?php echo esc_textarea( (string) ( $row->override_note ?? '' ) ); ?></textarea>
									<p class="ceafsn-field__hint" id="ceafsn-rf-override-note-help">
										<?php esc_html_e( 'Required whenever the override is ticked. This is the audit record of why the dates were set aside.', 'ceafsn-rf' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Applying', 'ceafsn-rf' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Where an applicant sends everything, and what they need to know first.', 'ceafsn-rf' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">
					<table class="ceafsn-rows" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-app-url"><?php esc_html_e( 'Application URL', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<input type="url" id="ceafsn-rf-app-url" name="application_url" placeholder="https://"
										aria-describedby="ceafsn-rf-app-help"
										value="<?php echo esc_attr( (string) ( $row->application_url ?? '' ) ); ?>" />
									<p class="ceafsn-field__hint" id="ceafsn-rf-app-help">
										<?php esc_html_e( 'A full http or https address. Without one the Apply button is shown as inactive rather than as a broken link.', 'ceafsn-rf' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<span class="ceafsn-field__label"><?php esc_html_e( 'Stipend / funding info', 'ceafsn-rf' ); ?></span>
								</th>
								<td>
									<textarea id="ceafsn-rf-stipend" name="stipend_info" rows="3"
										aria-describedby="ceafsn-rf-stipend-help"><?php echo esc_textarea( (string) ( $row->stipend_info ?? '' ) ); ?></textarea>
									<p class="ceafsn-field__hint" id="ceafsn-rf-stipend-help">
										<?php esc_html_e( 'Stored but never shown until it is approved below.', 'ceafsn-rf' ); ?>
									</p>
									<div class="ceafsn-check">
										<input type="checkbox" id="ceafsn-rf-show-stipend" name="show_stipend" value="1"
											<?php checked( ! empty( $row->show_stipend ) ); ?> />
										<div class="ceafsn-check__body">
											<label class="ceafsn-check__title" for="ceafsn-rf-show-stipend">
												<?php esc_html_e( 'Approved for public display', 'ceafsn-rf' ); ?>
											</label>
										</div>
									</div>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<span class="ceafsn-field__label"><?php esc_html_e( 'Contact email', 'ceafsn-rf' ); ?></span>
								</th>
								<td>
									<input type="email" id="ceafsn-rf-email" name="contact_email"
										aria-describedby="ceafsn-rf-email-help"
										value="<?php echo esc_attr( (string) ( $row->contact_email ?? '' ) ); ?>" />
									<div class="ceafsn-check">
										<input type="checkbox" id="ceafsn-rf-show-contact" name="show_contact" value="1"
											<?php checked( ! empty( $row->show_contact ) ); ?> />
										<div class="ceafsn-check__body">
											<label class="ceafsn-check__title" for="ceafsn-rf-show-contact">
												<?php esc_html_e( 'Approved for public display', 'ceafsn-rf' ); ?>
											</label>
										</div>
									</div>
									<p class="ceafsn-field__hint" id="ceafsn-rf-email-help">
										<?php esc_html_e( 'Stored but never shown until it is approved. Leave the address blank if there is no verified contact.', 'ceafsn-rf' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Call document', 'ceafsn-rf' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'The official call, attached from the Media Library.', 'ceafsn-rf' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">
					<table class="ceafsn-rows" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="ceafsn-rf-pdf-field"><?php esc_html_e( 'Call PDF', 'ceafsn-rf' ); ?></label>
								</th>
								<td>
									<?php // The picker script reads these three inputs from the same <td>. ?>
									<div class="ceafsn-rf-media">
										<input type="hidden" id="ceafsn-rf-pdf-id" name="call_pdf_id" value="<?php echo esc_attr( (string) $call_pdf_id ); ?>" />
										<input type="text" id="ceafsn-rf-pdf-field" readonly
											placeholder="<?php esc_attr_e( 'No file selected', 'ceafsn-rf' ); ?>"
											value="<?php echo esc_attr( $call_pdf_name ); ?>" />
										<button type="button" class="ceafsn-btn ceafsn-btn--quiet ceafsn-rf-media-button" id="ceafsn-rf-pdf-button"
											data-target="ceafsn-rf-pdf-id">
											<?php esc_html_e( 'Select PDF', 'ceafsn-rf' ); ?>
										</button>
										<button type="button" class="ceafsn-rf-media-clear" id="ceafsn-rf-pdf-clear" hidden>
											<?php esc_html_e( 'Clear', 'ceafsn-rf' ); ?>
										</button>
									</div>
									<p class="ceafsn-field__hint">
										<?php esc_html_e( 'Optional. An unreadable or empty file is flagged on the record rather than silently ignored.', 'ceafsn-rf' ); ?>
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
									<p class="ceafsn-field__hint">
										<?php esc_html_e( 'Only Published opportunities appear on the public page.', 'ceafsn-rf' ); ?>
									</p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</section>

			<div class="ceafsn-form__actions">
				<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Save opportunity', 'ceafsn-rf' ); ?></button>
				<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-rf' ); ?></a>
			</div>
		</form>

	<?php endif; ?>

	</div><!-- #ceafsn-rf-main -->

	<a class="ceafsn-help" href="#ceafsn-rf-help" aria-label="<?php esc_attr_e( 'Jump to how status works', 'ceafsn-rf' ); ?>">?</a>

</div><!-- .ceafsn-rf-wrap -->