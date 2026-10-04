<?php
/**
 * Admin partial: Overview dashboard (the sidebar landing page).
 *
 * Layout: WordPress admin menu on the left, a main canvas in the middle, and a
 * narrower right rail for calls to action and publishing guidance.
 *
 * Variables in scope (set by CEAFSN_MED_Admin::page_overview()):
 *   @var array $metrics            All metric rows, public and private
 *   @var array $demographics       All demographic group rows
 *   @var array $project_rows       All project rows
 *   @var int   $public_metrics     Count of metrics with visibility = public
 *   @var int   $private_metrics    Count of metrics with visibility = private
 *   @var int   $active_projects    Count of projects with status = active
 *   @var array $needs_verification Projects not yet marked verified
 *   @var array $periods            reporting_period => metric count, desc by count
 *
 * Every number below is counted from stored rows. Nothing is estimated,
 * sampled, or filled in with placeholder data. A workflow step is only marked
 * complete when the underlying count proves it.
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

$med_metric_count    = count( $metrics );
$med_demo_count      = count( $demographics );
$med_project_count   = count( $project_rows );
$med_total_records   = $med_metric_count + $med_demo_count + $med_project_count;
$med_attention_count = count( $needs_verification );

$med_verified_count = 0;
foreach ( $project_rows as $med_row ) {
	if ( 'verified' === $med_row->verification_status ) {
		++$med_verified_count;
	}
}

$med_url = static function ( string $page, string $extra = '' ): string {
	return admin_url( 'admin.php?page=' . $page . $extra );
};

// Publishing workflow. The first step still at zero is the current one.
$med_steps = array(
	array(
		'label' => __( 'Records added', 'ceafsn-med' ),
		'meta'  => sprintf( /* translators: 1: metric count, 2: demographic count, 3: project count. */ __( '%1$s metrics · %2$s groups · %3$s projects', 'ceafsn-med' ), number_format_i18n( $med_metric_count ), number_format_i18n( $med_demo_count ), number_format_i18n( $med_project_count ) ),
		'count' => $med_total_records,
	),
	array(
		'label' => __( 'Marked public', 'ceafsn-med' ),
		'meta'  => sprintf( /* translators: %s: count of public metrics. */ __( '%s metrics visible', 'ceafsn-med' ), number_format_i18n( $public_metrics ) ),
		'count' => $public_metrics,
	),
	array(
		'label' => __( 'Projects verified', 'ceafsn-med' ),
		'meta'  => sprintf( /* translators: 1: verified count, 2: total projects. */ __( '%1$s of %2$s checked', 'ceafsn-med' ), number_format_i18n( $med_verified_count ), number_format_i18n( $med_project_count ) ),
		'count' => $med_verified_count,
	),
);

$med_current_step = null;
foreach ( $med_steps as $med_index => $med_step ) {
	if ( $med_step['count'] > 0 ) {
		$med_steps[ $med_index ]['state'] = 'done';
	} elseif ( null === $med_current_step ) {
		$med_steps[ $med_index ]['state'] = 'current';
		$med_current_step                = $med_index;
	} else {
		$med_steps[ $med_index ]['state'] = 'todo';
	}
}
?>

<div class="wrap ceafsn-med-wrap">

	<a class="ceafsn-sr" href="#ceafsn-med-main"><?php esc_html_e( 'Skip to dashboard content', 'ceafsn-med' ); ?></a>

	<?php if ( ! empty( $_GET['ceafsn_med_error'] ) ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--danger" role="alert">
				<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title"><?php esc_html_e( 'That change was not saved', 'ceafsn-med' ); ?></p>
					<p class="ceafsn-alert__text"><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_med_error'] ) ) ) ); ?></p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="ceafsn-app" id="ceafsn-med-main">

		<div class="ceafsn-app__main">

			<?php if ( 0 === $med_total_records ) : ?>

				<div class="ceafsn-alerts">
					<div class="ceafsn-alert ceafsn-alert--warn">
						<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
						<div class="ceafsn-alert__body">
							<p class="ceafsn-alert__title"><?php esc_html_e( 'This dashboard is empty', 'ceafsn-med' ); ?></p>
							<p class="ceafsn-alert__text">
								<?php esc_html_e( 'No records exist yet, so nothing is displayed on the public page.', 'ceafsn-med' ); ?>
								<a class="ceafsn-alert__action" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_METRICS, '&action=add' ) ); ?>"><?php esc_html_e( 'Add the first metric', 'ceafsn-med' ); ?></a>
							</p>
						</div>
					</div>
				</div>

			<?php elseif ( $med_attention_count > 0 ) : ?>

				<div class="ceafsn-alerts">
					<div class="ceafsn-alert ceafsn-alert--warn">
						<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
						<div class="ceafsn-alert__body">
							<p class="ceafsn-alert__title">
								<?php
								printf(
									/* translators: %s: number of projects awaiting verification. */
									esc_html__( '%s project(s) are not verified yet', 'ceafsn-med' ),
									esc_html( number_format_i18n( $med_attention_count ) )
								);
								?>
							</p>
							<p class="ceafsn-alert__text">
								<?php esc_html_e( 'Status and verification are tracked separately — an active project can still be awaiting a check.', 'ceafsn-med' ); ?>
								<a class="ceafsn-alert__action" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_PROJECTS ) ); ?>"><?php esc_html_e( 'Review projects', 'ceafsn-med' ); ?></a>
							</p>
						</div>
					</div>
				</div>

			<?php endif; ?>

			<section class="ceafsn-hero">
				<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-med' ); ?></p>
				<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Monitoring & Evaluation', 'ceafsn-med' ); ?></h1>
				<p class="ceafsn-hero__text">
					<?php esc_html_e( 'Institutional metrics, demographic groups, and the project registry. Only verified records should be marked public.', 'ceafsn-med' ); ?>
				</p>
				<div class="ceafsn-hero__actions">
					<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_METRICS, '&action=add' ) ); ?>">
						<?php esc_html_e( '+ Add metric', 'ceafsn-med' ); ?>
					</a>
					<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_PROJECTS, '&action=add' ) ); ?>">
						<?php esc_html_e( '+ Add project', 'ceafsn-med' ); ?>
					</a>
				</div>
				<p class="ceafsn-hero__meta">
					<span>
						<?php
						printf(
							/* translators: %s: total record count. */
							wp_kses_post( __( '<strong>%s</strong> records', 'ceafsn-med'  ) ),
							esc_html( number_format_i18n( $med_total_records ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: public metric count. */
							wp_kses_post( __( '<strong>%s</strong> metrics public', 'ceafsn-med'  ) ),
							esc_html( number_format_i18n( $public_metrics ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: active project count. */
							wp_kses_post( __( '<strong>%s</strong> active projects', 'ceafsn-med'  ) ),
							esc_html( number_format_i18n( $active_projects ) )
						);
						?>
					</span>
					<span>
						<?php
						printf(
							/* translators: %s: number of reporting periods. */
							wp_kses_post( __( '<strong>%s</strong> reporting periods', 'ceafsn-med'  ) ),
							esc_html( number_format_i18n( count( $periods ) ) )
						);
						?>
					</span>
				</p>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display on a page', 'ceafsn-med' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'The dashboard only appears where this shortcode is placed.', 'ceafsn-med' ); ?></p>
					</div>
					<a class="ceafsn-btn ceafsn-btn--quiet"
						href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_SETTINGS, '&tab=display' ) ); ?>">
						<?php esc_html_e( 'How it works', 'ceafsn-med' ); ?>
					</a>
				</div>
				<div class="ceafsn-card__body">
					<div class="ceafsn-embed">
						<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste into the page that should show the dashboard', 'ceafsn-med' ); ?></p>
						<code class="ceafsn-embed__code">[ceafsn_me_dashboard]</code>
					</div>
					<p class="ceafsn-card__hint">
						<?php esc_html_e( 'This shortcode takes no attributes — it decides for itself what to show from the published records.', 'ceafsn-med' ); ?>
					</p>
				</div>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Publishing workflow', 'ceafsn-med' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Counted from your records. A step is only complete when the numbers prove it.', 'ceafsn-med' ); ?></p>
					</div>
				</div>
				<div class="ceafsn-card__body">
					<ol class="ceafsn-stepper">
						<?php foreach ( $med_steps as $med_index => $med_step ) : ?>
							<li class="ceafsn-step is-<?php echo esc_attr( $med_step['state'] ); ?>">
								<span class="ceafsn-step__dot" aria-hidden="true">
									<?php
									if ( 'done' === $med_step['state'] ) {
										echo '&#10003;';
									} else {
										echo esc_html( (string) ( $med_index + 1 ) );
									}
									?>
								</span>
								<span class="ceafsn-step__label"><?php echo esc_html( $med_step['label'] ); ?></span>
								<span class="ceafsn-step__meta"><?php echo esc_html( $med_step['meta'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
					<p class="ceafsn-card__hint">
						<?php esc_html_e( 'Current step:', 'ceafsn-med' ); ?>
						<strong>
							<?php
							echo esc_html(
								null !== $med_current_step
									? $med_steps[ $med_current_step ]['label']
									: __( 'All steps complete', 'ceafsn-med' )
							);
							?>
						</strong>
					</p>
				</div>
			</section>

			<div class="ceafsn-kpi-row">
				<div class="ceafsn-kpi ceafsn-kpi--accent">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--green" aria-hidden="true"></span>
						<?php esc_html_e( 'Metrics', 'ceafsn-med' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $med_metric_count ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						printf(
							/* translators: 1: public metric count, 2: private metric count. */
							esc_html__( '%1$s public · %2$s private', 'ceafsn-med' ),
							esc_html( number_format_i18n( $public_metrics ) ),
							esc_html( number_format_i18n( $private_metrics ) )
						);
						?>
					</span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--grey" aria-hidden="true"></span>
						<?php esc_html_e( 'Demographic groups', 'ceafsn-med' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $med_demo_count ) ); ?></span>
					<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Breakdowns tracked', 'ceafsn-med' ); ?></span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot ceafsn-dot--gold" aria-hidden="true"></span>
						<?php esc_html_e( 'Projects', 'ceafsn-med' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $med_project_count ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						printf(
							/* translators: %s: active project count. */
							esc_html__( '%s active', 'ceafsn-med' ),
							esc_html( number_format_i18n( $active_projects ) )
						);
						?>
					</span>
				</div>

				<div class="ceafsn-kpi<?php echo $med_attention_count > 0 ? ' ceafsn-kpi--alert' : ''; ?>">
					<span class="ceafsn-kpi__label">
						<span class="ceafsn-dot <?php echo $med_attention_count > 0 ? 'ceafsn-dot--danger' : 'ceafsn-dot--green'; ?>" aria-hidden="true"></span>
						<?php esc_html_e( 'Needs verification', 'ceafsn-med' ); ?>
					</span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $med_attention_count ) ); ?></span>
					<span class="ceafsn-kpi__meta">
						<?php
						if ( $med_attention_count > 0 ) {
							esc_html_e( 'Projects not yet verified', 'ceafsn-med' );
						} else {
							esc_html_e( 'All projects verified', 'ceafsn-med' );
						}
						?>
					</span>
				</div>
			</div>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Needs your attention', 'ceafsn-med' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'Projects that are not marked verified yet.', 'ceafsn-med' ); ?></p>
					</div>
					<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_PROJECTS ) ); ?>">
						<?php esc_html_e( 'All projects', 'ceafsn-med' ); ?>
					</a>
				</div>

				<?php if ( empty( $needs_verification ) ) : ?>
					<div class="ceafsn-empty">
						<span class="ceafsn-empty__mark" aria-hidden="true">&#10003;</span>
						<h3 class="ceafsn-empty__title"><?php esc_html_e( 'Everything is verified', 'ceafsn-med' ); ?></h3>
						<p class="ceafsn-empty__text">
							<?php esc_html_e( 'No project is waiting on a verification decision.', 'ceafsn-med' ); ?>
						</p>
					</div>
				<?php else : ?>
					<div class="ceafsn-table-wrap">
						<table class="ceafsn-table">
							<caption class="ceafsn-sr"><?php esc_html_e( 'Projects awaiting verification', 'ceafsn-med' ); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Project', 'ceafsn-med' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-med' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Verification', 'ceafsn-med' ); ?></th>
									<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-med' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( array_slice( $needs_verification, 0, 10 ) as $med_project ) : ?>
									<tr>
										<td>
											<span class="ceafsn-table__title">
												<span class="ceafsn-dot <?php echo 'unverified' === $med_project->verification_status ? 'ceafsn-dot--danger' : 'ceafsn-dot--gold'; ?>" aria-hidden="true"></span>
												<?php echo esc_html( (string) $med_project->title ); ?>
												<span class="ceafsn-table__meta">
													<?php
													echo esc_html(
														sprintf(
															/* translators: 1: principal investigator, 2: last updated date. */
															__( '%1$s · updated %2$s', 'ceafsn-med' ),
															(string) $med_project->principal_investigator,
															(string) $med_project->last_updated
														)
													);
													?>
												</span>
											</span>
										</td>
										<td>
											<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( (string) $med_project->status ); ?>">
												<?php echo esc_html( ucfirst( (string) $med_project->status ) ); ?>
											</span>
										</td>
										<td>
											<?php if ( 'unverified' === $med_project->verification_status ) : ?>
												<span class="ceafsn-badge ceafsn-badge--unverified"><?php esc_html_e( 'Unverified', 'ceafsn-med' ); ?></span>
											<?php else : ?>
												<span class="ceafsn-badge ceafsn-badge--pending"><?php esc_html_e( 'Pending', 'ceafsn-med' ); ?></span>
											<?php endif; ?>
										</td>
										<td class="ceafsn-table__actions">
											<a href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_PROJECTS, '&action=edit&id=' . absint( $med_project->project_id ) ) ); ?>">
												<?php esc_html_e( 'Review', 'ceafsn-med' ); ?>
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php if ( $med_attention_count > 10 ) : ?>
						<div class="ceafsn-card__body ceafsn-card__body--tight">
							<p class="ceafsn-card__hint">
								<?php
								printf(
									/* translators: %s: number of remaining projects. */
									esc_html__( 'and %s more — open All projects to review the rest.', 'ceafsn-med' ),
									esc_html( number_format_i18n( $med_attention_count - 10 ) )
								);
								?>
							</p>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</section>

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Reporting coverage', 'ceafsn-med' ); ?></h2>
					<span class="ceafsn-pill ceafsn-pill--green"><?php echo esc_html( number_format_i18n( count( $periods ) ) ); ?></span>
				</div>
				<div class="ceafsn-card__body">
					<?php if ( empty( $periods ) ) : ?>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'No reporting period recorded yet.', 'ceafsn-med' ); ?></p>
					<?php else : ?>
						<ul class="ceafsn-ticks">
							<?php foreach ( $periods as $med_period => $med_period_count ) : ?>
								<li>
									<?php
									printf(
										/* translators: 1: reporting period, 2: number of metrics. */
										esc_html__( '%1$s — %2$s metrics', 'ceafsn-med' ),
										esc_html( $med_period ),
										esc_html( number_format_i18n( $med_period_count ) )
									);
									?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</section>

		</div><!-- .ceafsn-app__main -->

		<aside class="ceafsn-app__rail">

			<div class="ceafsn-cta">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Get started', 'ceafsn-med' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add a record', 'ceafsn-med' ); ?></h2>
				<p class="ceafsn-cta__text">
					<?php esc_html_e( 'Metrics, demographic groups, and projects are stored separately. Every record needs a source.', 'ceafsn-med' ); ?>
				</p>
				<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_METRICS, '&action=add' ) ); ?>">
					<?php esc_html_e( '+ Add metric', 'ceafsn-med' ); ?>
				</a>
				<a class="ceafsn-btn ceafsn-btn--ghost ceafsn-btn--block" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_DEMOGRAPHICS, '&action=add' ) ); ?>">
					<?php esc_html_e( '+ Add demographic', 'ceafsn-med' ); ?>
				</a>
				<a class="ceafsn-btn ceafsn-btn--ghost ceafsn-btn--block" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_PROJECTS, '&action=add' ) ); ?>">
					<?php esc_html_e( '+ Add project', 'ceafsn-med' ); ?>
				</a>
			</div>

			<div class="ceafsn-cta" id="ceafsn-med-help">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Before you publish', 'ceafsn-med' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Visibility rules', 'ceafsn-med' ); ?></h2>
				<ul class="ceafsn-ticks">
					<li><?php esc_html_e( 'Every metric needs a source and a reporting period.', 'ceafsn-med' ); ?></li>
					<li><?php esc_html_e( 'Leave a record private until the value is checked.', 'ceafsn-med' ); ?></li>
					<li><?php esc_html_e( 'Project verification is independent of project status.', 'ceafsn-med' ); ?></li>
					<li><?php esc_html_e( 'Only public records reach the front-end dashboard.', 'ceafsn-med' ); ?></li>
				</ul>
			</div>

			<div class="ceafsn-cta">
				<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Keep a copy', 'ceafsn-med' ); ?></p>
				<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Export records', 'ceafsn-med' ); ?></h2>
				<p class="ceafsn-cta__text">
					<?php esc_html_e( 'Download every metric, group, and project as JSON before bulk changes or removing the plugin.', 'ceafsn-med' ); ?>
				</p>
				<a class="ceafsn-btn ceafsn-btn--ghost ceafsn-btn--block" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_SETTINGS, '&tab=export' ) ); ?>">
					<?php esc_html_e( 'Open export', 'ceafsn-med' ); ?>
				</a>
			</div>

		</aside>

	</div><!-- .ceafsn-app -->

	<a class="ceafsn-help" href="#ceafsn-med-help" aria-label="<?php esc_attr_e( 'Jump to visibility rules', 'ceafsn-med' ); ?>">?</a>

</div><!-- .ceafsn-med-wrap -->