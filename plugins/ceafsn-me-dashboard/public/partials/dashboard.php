<?php
/**
 * Public partial: M&E Dashboard front-end template.
 *
 * Variables in scope (extracted by CEAFSN_MED_Public::render_shortcode()):
 *   @var array   $metrics        Public metric rows
 *   @var array   $demographics   Public demographic rows
 *   @var array   $projects       Project rows for current page
 *   @var int     $total          Total project count (for pagination)
 *   @var int     $per_page
 *   @var int     $current_page
 *   @var bool    $preview_mode
 *   @var string  $filter_status
 *   @var string  $filter_search
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

$has_metrics      = ! empty( $metrics );
$has_demographics = ! empty( $demographics );
$has_projects     = ! empty( $projects );

// Single source of truth for these labels is the DB class, so the filter
// dropdown and the table cells can never drift apart or drift out of sync with
// the .pot catalogue.
$status_labels = CEAFSN_MED_DB::project_status_labels();
$verif_labels  = CEAFSN_MED_DB::verification_labels();

$total_pages  = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;
$current_url  = get_permalink();
?>

<div class="ceafsn-med-dashboard" role="main" aria-label="<?php esc_attr_e( 'M&E Dashboard', 'ceafsn-med' ); ?>">

	<?php if ( $preview_mode ) : ?>
	<div class="ceafsn-med-preview-banner" role="note" aria-live="polite">
		<strong><?php esc_html_e( 'Preview', 'ceafsn-med' ); ?></strong>
		<?php esc_html_e( 'This dashboard is in preview mode. The values displayed are for demonstration purposes and are not live institutional data.', 'ceafsn-med' ); ?>
	</div>
	<?php endif; ?>

	<!-- Tab navigation -->
	<nav class="ceafsn-med-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Dashboard sections', 'ceafsn-med' ); ?>">
		<button role="tab" id="ceafsn-tab-overview"      aria-controls="ceafsn-panel-overview"      aria-selected="true"  class="ceafsn-med-tab ceafsn-med-tab--active" tabindex="0">
			<?php esc_html_e( 'Overview', 'ceafsn-med' ); ?>
		</button>
		<button role="tab" id="ceafsn-tab-demographics"  aria-controls="ceafsn-panel-demographics"  aria-selected="false" class="ceafsn-med-tab" tabindex="-1">
			<?php esc_html_e( 'Demographics', 'ceafsn-med' ); ?>
		</button>
		<button role="tab" id="ceafsn-tab-projects"      aria-controls="ceafsn-panel-projects"      aria-selected="false" class="ceafsn-med-tab" tabindex="-1">
			<?php esc_html_e( 'Project Registry', 'ceafsn-med' ); ?>
		</button>
	</nav>

	<!-- ============================
	     Panel 1: Overview (Metrics)
	     ============================ -->
	<section id="ceafsn-panel-overview"
		role="tabpanel"
		aria-labelledby="ceafsn-tab-overview"
		class="ceafsn-med-panel ceafsn-med-panel--active">

		<?php if ( ! $has_metrics ) : ?>
			<p class="ceafsn-med-empty">
				<?php esc_html_e( 'No public metrics have been published yet.', 'ceafsn-med' ); ?>
			</p>
		<?php else : ?>
			<!-- Stat cards -->
			<ul class="ceafsn-med-cards" aria-label="<?php esc_attr_e( 'Key metrics', 'ceafsn-med' ); ?>">
				<?php foreach ( $metrics as $metric ) : ?>
				<li class="ceafsn-med-card">
					<span class="ceafsn-med-card__label"><?php echo esc_html( $metric->label ); ?></span>
					<span class="ceafsn-med-card__value" aria-label="<?php echo esc_attr( $metric->label . ': ' . $metric->value . ' ' . $metric->unit ); ?>">
						<?php echo esc_html( $metric->value ); ?>
						<?php if ( $metric->unit ) : ?>
							<span class="ceafsn-med-card__unit"><?php echo esc_html( $metric->unit ); ?></span>
						<?php endif; ?>
					</span>
					<?php if ( $metric->definition ) : ?>
						<span class="ceafsn-med-card__definition"><?php echo esc_html( $metric->definition ); ?></span>
					<?php endif; ?>
					<span class="ceafsn-med-card__meta">
						<?php esc_html_e( 'Source:', 'ceafsn-med' ); ?> <?php echo esc_html( $metric->source ); ?>
						&middot;
						<?php esc_html_e( 'Period:', 'ceafsn-med' ); ?> <?php echo esc_html( $metric->reporting_period ); ?>
					</span>
				</li>
				<?php endforeach; ?>
			</ul>

			<!-- Accessible data table backing the visual stat cards -->
			<details class="ceafsn-med-table-details">
				<summary><?php esc_html_e( 'View as table', 'ceafsn-med' ); ?></summary>
				<table class="ceafsn-med-table">
					<caption><?php esc_html_e( 'Key metrics data table', 'ceafsn-med' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Metric', 'ceafsn-med' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Unit', 'ceafsn-med' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Reporting Period', 'ceafsn-med' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Source', 'ceafsn-med' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $metrics as $metric ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $metric->label ); ?></th>
							<td><?php echo esc_html( $metric->value ); ?></td>
							<td><?php echo esc_html( $metric->unit ?: '—' ); ?></td>
							<td><?php echo esc_html( $metric->reporting_period ); ?></td>
							<td><?php echo esc_html( $metric->source ); ?></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</details>
		<?php endif; ?>
	</section>

	<!-- ============================
	     Panel 2: Demographics
	     ============================ -->
	<section id="ceafsn-panel-demographics"
		role="tabpanel"
		aria-labelledby="ceafsn-tab-demographics"
		class="ceafsn-med-panel"
		hidden>

		<?php if ( ! $has_demographics ) : ?>
			<p class="ceafsn-med-empty">
				<?php esc_html_e( 'No public demographic data has been published yet.', 'ceafsn-med' ); ?>
			</p>
		<?php else : ?>
			<!-- Visual bar chart (CSS-only, progressively enhanced by JS) -->
			<div class="ceafsn-med-chart" aria-hidden="true">
				<?php
				$max_value = max( array_map( fn( $d ) => (float) $d->value, $demographics ) );
				$max_value = $max_value ?: 1;
				foreach ( $demographics as $demo ) :
					$bar_pct = round( ( (float) $demo->value / $max_value ) * 100, 1 );
				?>
				<div class="ceafsn-med-chart__bar-row">
					<span class="ceafsn-med-chart__bar-label"><?php echo esc_html( $demo->label ); ?></span>
					<span class="ceafsn-med-chart__bar" style="width:<?php echo esc_attr( $bar_pct ); ?>%" role="presentation"></span>
					<span class="ceafsn-med-chart__bar-value">
						<?php echo esc_html( number_format( (float) $demo->value, 2 ) ); ?>
						<?php echo 'percentage' === $demo->value_type ? '%' : ''; ?>
					</span>
				</div>
				<?php endforeach; ?>
			</div>

			<!-- Accessible data table (always present, chart is enhancement) -->
			<table class="ceafsn-med-table">
				<caption><?php esc_html_e( 'Demographic groups data table', 'ceafsn-med' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Group', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Reporting Period', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Source', 'ceafsn-med' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $demographics as $demo ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $demo->label ); ?></th>
						<td>
							<?php echo esc_html( number_format( (float) $demo->value, 2 ) ); ?>
							<?php echo 'percentage' === $demo->value_type ? '%' : ''; ?>
						</td>
						<td><?php echo esc_html( CEAFSN_MED_DB::label( CEAFSN_MED_DB::value_type_labels(), (string) $demo->value_type ) ); ?></td>
						<td><?php echo esc_html( $demo->reporting_period ); ?></td>
						<td><?php echo esc_html( $demo->source ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</section>

	<!-- ============================
	     Panel 3: Project Registry
	     ============================ -->
	<section id="ceafsn-panel-projects"
		role="tabpanel"
		aria-labelledby="ceafsn-tab-projects"
		class="ceafsn-med-panel"
		hidden>

		<!-- Filter form -->
		<form class="ceafsn-med-filter" method="get" action="<?php echo esc_url( $current_url ); ?>">
			<label for="ceafsn-med-search" class="screen-reader-text"><?php esc_html_e( 'Search projects', 'ceafsn-med' ); ?></label>
			<input id="ceafsn-med-search" type="search" name="med_search"
				value="<?php echo esc_attr( $filter_search ); ?>"
				placeholder="<?php esc_attr_e( 'Search by title or PI…', 'ceafsn-med' ); ?>"
				class="ceafsn-med-filter__search">

			<label for="ceafsn-med-status" class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'ceafsn-med' ); ?></label>
			<select id="ceafsn-med-status" name="med_status" class="ceafsn-med-filter__select">
				<option value=""><?php esc_html_e( 'All statuses', 'ceafsn-med' ); ?></option>
				<?php foreach ( $status_labels as $val => $label ) : ?>
					<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filter_status, $val ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button type="submit" class="ceafsn-med-filter__submit">
				<?php esc_html_e( 'Filter', 'ceafsn-med' ); ?>
			</button>

			<?php if ( $filter_status || $filter_search ) : ?>
			<a href="<?php echo esc_url( $current_url ); ?>" class="ceafsn-med-filter__reset">
				<?php esc_html_e( 'Clear filters', 'ceafsn-med' ); ?>
			</a>
			<?php endif; ?>
		</form>

		<?php if ( ! $has_projects ) : ?>
			<p class="ceafsn-med-empty">
				<?php esc_html_e( 'No projects match the current filters.', 'ceafsn-med' ); ?>
			</p>
		<?php else : ?>
			<table class="ceafsn-med-table ceafsn-med-table--projects">
				<caption>
					<?php
					printf(
						/* translators: %d: total projects */
						esc_html( _n( '%d project', '%d projects', $total, 'ceafsn-med' ) ),
						absint( $total )
					);
					?>
				</caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Principal Investigator', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Region', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Verification', 'ceafsn-med' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Updated', 'ceafsn-med' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $projects as $project ) : ?>
					<tr>
						<th scope="row">
							<?php echo esc_html( $project->title ); ?>
							<?php if ( ! empty( $project->source_url ) ) : ?>
								<br>
								<a href="<?php echo esc_url( $project->source_url ); ?>" target="_blank" rel="noopener noreferrer" class="ceafsn-med-source-link">
									<?php esc_html_e( 'Source', 'ceafsn-med' ); ?> ↗
								</a>
							<?php endif; ?>
						</th>
						<td><?php echo esc_html( $project->principal_investigator ); ?></td>
						<td><?php echo esc_html( $project->target_region ?: '—' ); ?></td>
						<td>
							<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( $project->status ); ?>"
								aria-label="<?php echo esc_attr( __( 'Status:', 'ceafsn-med' ) . ' ' . ( $status_labels[ $project->status ] ?? $project->status ) ); ?>">
								<?php echo esc_html( CEAFSN_MED_DB::label( CEAFSN_MED_DB::project_status_labels(), (string) $project->status ) ); ?>
							</span>
						</td>
						<td>
							<span class="ceafsn-badge ceafsn-badge--verif-<?php echo esc_attr( $project->verification_status ); ?>"
								aria-label="<?php echo esc_attr( __( 'Verification:', 'ceafsn-med' ) . ' ' . ( $verif_labels[ $project->verification_status ] ?? $project->verification_status ) ); ?>">
								<?php echo esc_html( CEAFSN_MED_DB::label( CEAFSN_MED_DB::verification_labels(), (string) $project->verification_status ) ); ?>
							</span>
						</td>
						<td>
							<time datetime="<?php echo esc_attr( $project->last_updated ); ?>">
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $project->last_updated ) ) ); ?>
							</time>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) : ?>
			<nav class="ceafsn-med-pagination" aria-label="<?php esc_attr_e( 'Project registry pages', 'ceafsn-med' ); ?>">
				<?php
				$base_args = array_filter( array(
					'med_status' => $filter_status,
					'med_search' => $filter_search,
				) );

				for ( $p = 1; $p <= $total_pages; $p++ ) :
					$page_url = add_query_arg( array_merge( $base_args, array( 'med_page' => $p ) ), $current_url );
					$is_current = $p === $current_page;
				?>
				<a href="<?php echo esc_url( $page_url ); ?>"
					class="ceafsn-med-pagination__link <?php echo $is_current ? 'ceafsn-med-pagination__link--current' : ''; ?>"
					<?php echo $is_current ? 'aria-current="page"' : ''; ?>>
					<?php echo absint( $p ); ?>
				</a>
				<?php endfor; ?>
			</nav>
			<?php endif; ?>

		<?php endif; ?>
	</section>

</div><!-- .ceafsn-med-dashboard -->
