<?php
/**
 * Public partial: the shortcode output.
 *
 * Variables in scope (set by CEAFSN_RF_Public::render_shortcode()):
 *   @var array<int,array<string,mixed>> $rows         Card models for the current page
 *   @var int                          $total        Rows matching the filters, across all pages
 *   @var int                          $total_pages  Total pages, at least 1
 *   @var array<int,string>            $tracks       Known track/domain values
 *   @var array<int,string>            $present      Statuses present in the results
 *   @var array<int,string>            $sortable     Sortable column keys
 *   @var int                          $per_page     Page size
 *   @var int                          $current_page Current page number
 *   @var string                       $orderby      Active sort column
 *   @var string                       $order        Active sort direction
 *   @var string                       $view         'cards' or 'table'
 *   @var string                       $filter_track Active track filter
 *   @var string                       $filter_status Active status filter
 *   @var string                       $filter_search Active search term
 *   @var string                       $today        Today in the site timezone
 *   @var string                       $base_url     Base URL for links
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

$rf_status_labels = array();
foreach ( $present as $rf_status ) {
	$rf_status_labels[ $rf_status ] = CEAFSN_RF_Status::label( (string) $rf_status );
}
?>

<div class="ceafsn-rf"
	data-view="<?php echo esc_attr( $view ); ?>"
	data-skip-label="<?php esc_attr_e( 'Skip to the opportunity list', 'ceafsn-rf' ); ?>"
	data-expand-label="<?php esc_attr_e( 'Read the full eligibility criteria', 'ceafsn-rf' ); ?>"
	data-collapse-label="<?php esc_attr_e( 'Show less', 'ceafsn-rf' ); ?>">

	<?php if ( '' !== $filter_search || '' !== $filter_track || '' !== $filter_status ) : ?>
		<p class="ceafsn-rf-summary">
			<?php
			printf(
				/* translators: 1: number of opportunities, 2: shortcode attribute, e.g. [ceafsn_fellowships]. */
				esc_html( _n( '%1$s opportunity shown.', '%1$s opportunities shown.', $total, 'ceafsn-rf' ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
			<a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear filters', 'ceafsn-rf' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $tracks ) ) : ?>
	<form class="ceafsn-rf-filters" method="get" action="<?php echo esc_url( $base_url ); ?>">
		<?php
		// Keep unrelated query args on the page URL so the form does not strip
		// the WordPress page permalink arguments the server relies on.
		$rf_hidden = CEAFSN_RF_Public::passthrough_args(
			array( 'rf_track', 'rf_status', 'rf_search', 'rf_view', 'rf_page', 'rf_sort', 'rf_order' )
		);
		foreach ( $rf_hidden as $rf_k => $rf_v ) :
			?>
			<input type="hidden" name="<?php echo esc_attr( $rf_k ); ?>" value="<?php echo esc_attr( $rf_v ); ?>" />
		<?php endforeach; ?>

		<p class="ceafsn-rf-filters__row">
			<label class="screen-reader-text" for="ceafsn-rf-search"><?php esc_html_e( 'Search opportunities', 'ceafsn-rf' ); ?></label>
			<input type="search" id="ceafsn-rf-search" name="rf_search"
				value="<?php echo esc_attr( $filter_search ); ?>"
				placeholder="<?php esc_attr_e( 'Search titles and eligibility', 'ceafsn-rf' ); ?>" />

			<label class="screen-reader-text" for="ceafsn-rf-filter-track"><?php esc_html_e( 'Filter by track or domain', 'ceafsn-rf' ); ?></label>
			<select id="ceafsn-rf-filter-track" name="rf_track">
				<option value=""><?php esc_html_e( 'All tracks and domains', 'ceafsn-rf' ); ?></option>
				<?php foreach ( $tracks as $rf_track ) : ?>
					<option value="<?php echo esc_attr( $rf_track ); ?>" <?php selected( $rf_track, $filter_track ); ?>>
						<?php echo esc_html( $rf_track ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="ceafsn-rf-filter-status"><?php esc_html_e( 'Filter by status', 'ceafsn-rf' ); ?></label>
			<select id="ceafsn-rf-filter-status" name="rf_status">
				<option value=""><?php esc_html_e( 'All statuses', 'ceafsn-rf' ); ?></option>
				<?php foreach ( $rf_status_labels as $rf_value => $rf_label ) : ?>
					<option value="<?php echo esc_attr( $rf_value ); ?>" <?php selected( (string) $rf_value, $filter_status ); ?>>
						<?php echo esc_html( $rf_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php if ( 'table' === $view ) : ?>
				<input type="hidden" name="rf_view" value="table" />
			<?php endif; ?>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'ceafsn-rf' ); ?></button>
		</p>
	</form>
	<?php endif; ?>

	<?php if ( empty( $rows ) ) : ?>

		<p class="ceafsn-rf-empty">
			<?php esc_html_e( 'There are no opportunities to show right now. Please check back soon.', 'ceafsn-rf' ); ?>
		</p>

	<?php elseif ( 'table' === $view ) : ?>

		<div class="ceafsn-rf-table-wrap" tabindex="0" role="region"
			aria-labelledby="ceafsn-rf-table-caption">
		<table class="ceafsn-rf-table">
			<caption id="ceafsn-rf-table-caption" class="screen-reader-text"><?php esc_html_e( 'Research fellowship opportunities', 'ceafsn-rf' ); ?></caption>
			<thead>
				<tr>
					<?php
					$rf_headers = array(
						'title'         => __( 'Opportunity', 'ceafsn-rf' ),
						'track_domain'  => __( 'Track', 'ceafsn-rf' ),
						'opening_date'  => __( 'Opens', 'ceafsn-rf' ),
						'closing_date'  => __( 'Closes', 'ceafsn-rf' ),
						'duration'      => __( 'Duration', 'ceafsn-rf' ),
					);

					foreach ( $rf_headers as $rf_key => $rf_label ) :
						$rf_sort = CEAFSN_RF_Public::sort_link( $base_url, $rf_key, $orderby, $order );
						?>
						<th scope="col" aria-sort="<?php echo esc_attr( $rf_sort['aria_sort'] ); ?>">
							<a href="<?php echo esc_url( $rf_sort['url'] ); ?>">
								<?php echo esc_html( $rf_label ); ?>
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										'asc' === $rf_sort['next']
											? __( '(sort ascending)', 'ceafsn-rf' )
											: __( '(sort descending)', 'ceafsn-rf' )
									);
									?>
								</span>
							</a>
						</th>
					<?php endforeach; ?>
					<th scope="col"><?php esc_html_e( 'Apply', 'ceafsn-rf' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $rf_card ) : ?>
					<tr>
						<th scope="row">
							<span class="ceafsn-rf-badge ceafsn-rf-badge--status-<?php echo esc_attr( $rf_card['status'] ); ?>">
								<?php echo esc_html( $rf_card['status_label'] ); ?>
							</span>
							<?php echo esc_html( (string) $rf_card['row']->title ); ?>
						</th>
						<td data-label="<?php esc_attr_e( 'Track', 'ceafsn-rf' ); ?>"><?php echo esc_html( '' !== (string) $rf_card['row']->track_domain ? (string) $rf_card['row']->track_domain : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Opens', 'ceafsn-rf' ); ?>"><?php echo esc_html( '' !== $rf_card['opening_date'] ? CEAFSN_RF_Public::format_date( (string) $rf_card['row']->opening_date ) : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Closes', 'ceafsn-rf' ); ?>"><?php echo esc_html( '' !== $rf_card['closing_date'] ? CEAFSN_RF_Public::format_date( (string) $rf_card['row']->closing_date ) : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Duration', 'ceafsn-rf' ); ?>"><?php echo esc_html( '' !== (string) $rf_card['row']->duration ? (string) $rf_card['row']->duration : '—' ); ?></td>
						<td data-label="<?php esc_attr_e( 'Apply', 'ceafsn-rf' ); ?>"><?php CEAFSN_RF_Public::render_apply( $rf_card ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>

	<?php else : ?>

		<ul class="ceafsn-rf-cards">
			<?php foreach ( $rows as $rf_card ) : ?>
				<li class="ceafsn-rf-card ceafsn-rf-card--<?php echo esc_attr( $rf_card['status'] ); ?>">

					<p class="ceafsn-rf-card__status">
						<span class="ceafsn-rf-badge ceafsn-rf-badge--status-<?php echo esc_attr( $rf_card['status'] ); ?>">
							<?php echo esc_html( $rf_card['status_label'] ); ?>
						</span>
						<?php if ( $rf_card['is_override'] ) : ?>
							<span class="ceafsn-rf-badge ceafsn-rf-badge--override" title="<?php echo esc_attr( (string) $rf_card['row']->override_note ); ?>">
								<?php esc_html_e( 'Override', 'ceafsn-rf' ); ?>
							</span>
						<?php endif; ?>
					</p>

					<h3 class="ceafsn-rf-card__title"><?php echo esc_html( (string) $rf_card['row']->title ); ?></h3>

					<dl class="ceafsn-rf-card__meta">
						<?php if ( '' !== (string) $rf_card['row']->track_domain ) : ?>
							<div>
								<dt><?php esc_html_e( 'Track', 'ceafsn-rf' ); ?></dt>
								<dd><?php echo esc_html( (string) $rf_card['row']->track_domain ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( '' !== (string) $rf_card['row']->duration ) : ?>
							<div>
								<dt><?php esc_html_e( 'Duration', 'ceafsn-rf' ); ?></dt>
								<dd><?php echo esc_html( (string) $rf_card['row']->duration ); ?></dd>
							</div>
						<?php endif; ?>

						<?php if ( '' !== (string) $rf_card['row']->host_supervisor ) : ?>
							<div>
								<dt><?php esc_html_e( 'Host', 'ceafsn-rf' ); ?></dt>
								<dd><?php echo esc_html( (string) $rf_card['row']->host_supervisor ); ?></dd>
							</div>
						<?php endif; ?>

						<div>
							<dt><?php esc_html_e( 'Closes', 'ceafsn-rf' ); ?></dt>
							<dd>
								<?php if ( '' !== $rf_card['closing_date'] ) : ?>
									<time datetime="<?php echo esc_attr( $rf_card['closing_date'] ); ?>">
										<?php echo esc_html( CEAFSN_RF_Public::format_date( (string) $rf_card['row']->closing_date ) ); ?>
									</time>
								<?php else : ?>
									<span class="ceafsn-rf-card__nodate"><?php esc_html_e( 'No closing date recorded', 'ceafsn-rf' ); ?></span>
								<?php endif; ?>
							</dd>
						</div>
					</dl>

					<div class="ceafsn-rf-card__eligibility">
						<h4><?php esc_html_e( 'Who can apply', 'ceafsn-rf' ); ?></h4>
						<p><?php echo esc_html( (string) $rf_card['row']->eligibility ); ?></p>
					</div>

					<?php if ( '' !== $rf_card['stipend'] ) : ?>
						<div class="ceafsn-rf-card__stipend">
							<h4><?php esc_html_e( 'Funding', 'ceafsn-rf' ); ?></h4>
							<p><?php echo esc_html( $rf_card['stipend'] ); ?></p>
						</div>
					<?php endif; ?>

					<p class="ceafsn-rf-card__actions">
						<?php CEAFSN_RF_Public::render_apply( $rf_card ); ?>
						<?php if ( $rf_card['has_pdf'] ) : ?>
							<a class="ceafsn-rf-card__doc" href="<?php echo esc_url( $rf_card['pdf_url'] ); ?>">
								<?php echo esc_html( $rf_card['pdf_label'] ); ?>
							</a>
						<?php endif; ?>
					</p>

					<?php if ( '' !== $rf_card['contact_email'] ) : ?>
						<p class="ceafsn-rf-card__contact">
							<?php
							printf(
								/* translators: %s: contact email address. */
								esc_html__( 'Questions? Email %s', 'ceafsn-rf' ),
								'<a href="' . esc_url( 'mailto:' . $rf_card['contact_email'] ) . '">' . esc_html( $rf_card['contact_email'] ) . '</a>'
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( ! $rf_card['is_open'] && '' !== $rf_card['status_reason'] ) : ?>
						<p class="ceafsn-rf-card__reason"><?php echo esc_html( $rf_card['status_reason'] ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>

	<?php
	// Rendered for both the card grid and the table view: a visitor who filtered
	// to a single track still needs a way to reach the rest of that track.
	echo CEAFSN_RF_Public::render_pagination( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_pagination().
		(int) $current_page,
		(int) $total_pages,
		(string) $base_url,
		'rf_page',
		__( 'Research fellowship pages', 'ceafsn-rf' )
	);
	?>
</div>
