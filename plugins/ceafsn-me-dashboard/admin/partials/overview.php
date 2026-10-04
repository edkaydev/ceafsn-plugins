<?php
/**
 * Admin partial: Overview dashboard (the sidebar landing page).
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
 * sampled, or filled in with placeholder data.
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

$med_metric_count    = count( $metrics );
$med_demo_count      = count( $demographics );
$med_project_count   = count( $project_rows );
$med_total_records   = $med_metric_count + $med_demo_count + $med_project_count;
$med_attention_count = count( $needs_verification );

$med_url = static function ( string $page, string $extra = '' ): string {
	return admin_url( 'admin.php?page=' . $page . $extra );
};
?>

<div class="wrap ceafsn-med-wrap">

	<?php if ( ! empty( $_GET['ceafsn_med_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_med_error'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<a class="ceafsn-sr" href="#ceafsn-med-main"><?php esc_html_e( 'Skip to dashboard content', 'ceafsn-med' ); ?></a>

	<header class="ceafsn-header">
		<p class="ceafsn-header__eyebrow"><?php esc_html_e( 'CE-AFSN', 'ceafsn-med' ); ?></p>
		<h1 class="ceafsn-header__title"><?php esc_html_e( 'Monitoring &amp; Evaluation', 'ceafsn-med' ); ?></h1>
		<p class="ceafsn-header__subtitle">
			<?php esc_html_e( 'Institutional metrics, demographic groups, and the project registry. Only verified records should be marked public.', 'ceafsn-med' ); ?>
		</p>
		<div class="ceafsn-header__actions">
			<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_METRICS, '&action=add' ) ); ?>">
				<?php esc_html_e( '+ Add metric', 'ceafsn-med' ); ?>
			</a>
			<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_DEMOGRAPHICS, '&action=add' ) ); ?>">
				<?php esc_html_e( '+ Add demographic', 'ceafsn-med' ); ?>
			</a>
			<a class="ceafsn-btn ceafsn-btn--ghost" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_PROJECTS, '&action=add' ) ); ?>">
				<?php esc_html_e( '+ Add project', 'ceafsn-med' ); ?>
			</a>
		</div>
	</header>

	<div id="ceafsn-med-main">

		<?php if ( 0 === $med_total_records ) : ?>

			<div class="ceafsn-card">
				<div class="ceafsn-empty">
					<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
					<h2 class="ceafsn-empty__title"><?php esc_html_e( 'No records yet', 'ceafsn-med' ); ?></h2>
					<p class="ceafsn-empty__text">
						<?php esc_html_e( 'This dashboard is empty on purpose. Nothing is displayed publicly until you add real, sourced records below. Add your first metric, demographic group, or project to get started.', 'ceafsn-med' ); ?>
					</p>
					<div class="ceafsn-empty__action">
						<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_METRICS, '&action=add' ) ); ?>">
							<?php esc_html_e( 'Add the first metric', 'ceafsn-med' ); ?>
						</a>
					</div>
				</div>
			</div>

		<?php else : ?>

			<div class="ceafsn-kpi-row">
				<div class="ceafsn-kpi ceafsn-kpi--accent">
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Metrics', 'ceafsn-med' ); ?></span>
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
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Demographic groups', 'ceafsn-med' ); ?></span>
					<span class="ceafsn-kpi__value"><?php echo esc_html( number_format_i18n( $med_demo_count ) ); ?></span>
					<span class="ceafsn-kpi__meta"><?php esc_html_e( 'Breakdowns tracked', 'ceafsn-med' ); ?></span>
				</div>

				<div class="ceafsn-kpi">
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Projects', 'ceafsn-med' ); ?></span>
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
					<span class="ceafsn-kpi__label"><?php esc_html_e( 'Needs verification', 'ceafsn-med' ); ?></span>
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

			<div class="ceafsn-split">

				<div>
					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<div>
								<h2 class="ceafsn-card__title"><?php esc_html_e( 'Needs your attention', 'ceafsn-med' ); ?></h2>
								<p class="ceafsn-card__hint">
									<?php esc_html_e( 'Projects that are not marked verified yet.', 'ceafsn-med' ); ?>
								</p>
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
							<ul class="ceafsn-list">
								<?php foreach ( array_slice( $needs_verification, 0, 8 ) as $med_project ) : ?>
									<li class="ceafsn-list__item">
										<div class="ceafsn-list__main">
											<p class="ceafsn-list__title"><?php echo esc_html( $med_project->title ); ?></p>
											<p class="ceafsn-list__meta">
												<?php
												echo esc_html(
													sprintf(
														/* translators: 1: principal investigator, 2: last updated date. */
														__( '%1$s · updated %2$s', 'ceafsn-med' ),
														$med_project->principal_investigator,
														$med_project->last_updated
													)
												);
												?>
											</p>
										</div>
										<div class="ceafsn-list__actions">
											<?php if ( 'unverified' === $med_project->verification_status ) : ?>
												<span class="ceafsn-badge ceafsn-badge--unverified"><?php esc_html_e( 'Unverified', 'ceafsn-med' ); ?></span>
											<?php else : ?>
												<span class="ceafsn-badge ceafsn-badge--pending"><?php esc_html_e( 'Pending', 'ceafsn-med' ); ?></span>
											<?php endif; ?>
											<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_PROJECTS, '&action=edit&id=' . absint( $med_project->project_id ) ) ); ?>">
												<?php esc_html_e( 'Review', 'ceafsn-med' ); ?>
											</a>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $med_attention_count > 8 ) : ?>
								<div class="ceafsn-card__body">
									<p class="ceafsn-card__hint">
										<?php
										printf(
											/* translators: %s: number of remaining projects. */
											esc_html__( 'and %s more — open All projects to review the rest.', 'ceafsn-med' ),
											esc_html( number_format_i18n( $med_attention_count - 8 ) )
										);
										?>
									</p>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</section>
				</div>

				<div>
					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Reporting coverage', 'ceafsn-med' ); ?></h2>
						</div>
						<div class="ceafsn-card__body">
							<?php if ( empty( $periods ) ) : ?>
								<p class="ceafsn-card__hint"><?php esc_html_e( 'No reporting period recorded yet.', 'ceafsn-med' ); ?></p>
							<?php else : ?>
								<ul class="ceafsn-list">
									<?php foreach ( $periods as $med_period => $med_period_count ) : ?>
										<li class="ceafsn-list__item">
											<div class="ceafsn-list__main">
												<p class="ceafsn-list__title"><?php echo esc_html( $med_period ); ?></p>
												<p class="ceafsn-list__meta">
													<?php
													printf(
														/* translators: %s: number of metrics. */
														esc_html__( '%s metrics', 'ceafsn-med' ),
														esc_html( number_format_i18n( $med_period_count ) )
													);
													?>
												</p>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</section>

					<section class="ceafsn-card">
						<div class="ceafsn-card__head">
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Before you publish', 'ceafsn-med' ); ?></h2>
						</div>
						<div class="ceafsn-card__body">
							<ul class="ceafsn-list">
								<li class="ceafsn-list__item">
									<div class="ceafsn-list__main">
										<p class="ceafsn-list__meta">
											<?php esc_html_e( 'Every metric needs a source and a reporting period before it can be marked public.', 'ceafsn-med' ); ?>
										</p>
									</div>
								</li>
								<li class="ceafsn-list__item">
									<div class="ceafsn-list__main">
										<p class="ceafsn-list__meta">
											<?php esc_html_e( 'Leave a record private until the value is verified and approved for public display.', 'ceafsn-med' ); ?>
										</p>
									</div>
								</li>
							</ul>
							<div class="ceafsn-card__body ceafsn-card__body--tight">
								<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( $med_url( CEAFSN_MED_Admin::PAGE_METRICS ) ); ?>">
									<?php esc_html_e( 'Review all metrics', 'ceafsn-med' ); ?>
								</a>
							</div>
						</div>
					</section>
				</div>

			</div>

		<?php endif; ?>

	</div>
</div><!-- .ceafsn-med-wrap -->