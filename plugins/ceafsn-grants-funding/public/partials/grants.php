<?php
/**
 * Public partial: the shortcode output.
 *
 * Variables in scope (set by CEAFSN_GF_Public::render_shortcode()):
* @var array<int,array<string,mixed>> $rows               Card models for the current page
 *   @var int                          $total                Rows matching the filters, across all pages
 *   @var int                          $total_pages          Total pages, at least 1
 *   @var array<int,string>            $institutions         Known funding institutions
 *   @var array<int,string>            $statuses             All grant status keys
 *   @var array<int,string>            $sortable             Sortable column keys
 *   @var int                          $per_page             Page size
 *   @var int                          $current_page         Current page number
 *   @var string                       $orderby              Active sort column
 *   @var string                       $order                Active sort direction
 *   @var string                       $view                 'cards' or 'table'
 *   @var string                       $filter_institution   Active institution filter
 *   @var string                       $filter_status        Active status filter
 *   @var string                       $filter_search        Active search term
 *   @var string                       $base_url             Base URL for links
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="ceafsn-gf"
	data-view="<?php echo esc_attr( $view ); ?>"
	data-skip-label="<?php esc_attr_e( 'Skip to the grant list', 'ceafsn-gf' ); ?>"
	data-expand-label="<?php esc_attr_e( 'Read the full eligibility criteria', 'ceafsn-gf' ); ?>"
	data-collapse-label="<?php esc_attr_e( 'Show less', 'ceafsn-gf' ); ?>">

	<?php if ( '' !== $filter_search || '' !== $filter_institution || '' !== $filter_status ) : ?>
		<p class="ceafsn-gf-summary">
			<?php
			printf(
				/* translators: %s: number of grants. */
				esc_html( _n( '%s funding opportunity shown.', '%s funding opportunities shown.', $total, 'ceafsn-gf' ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
			<a href="<?php echo esc_url( $base_url ); ?>"><?php esc_html_e( 'Clear filters', 'ceafsn-gf' ); ?></a>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $institutions ) ) : ?>
	<form class="ceafsn-gf-filters" method="get" action="<?php echo esc_url( $base_url ); ?>">
		<?php
		// Keep unrelated query args on the page URL so the form does not strip
		// the WordPress page permalink arguments the server relies on.
		$gf_hidden = CEAFSN_GF_Public::passthrough_args(
			array( 'gf_institution', 'gf_status', 'gf_search', 'gf_view', 'gf_page', 'gf_sort', 'gf_order' )
		);
		foreach ( $gf_hidden as $gf_k => $gf_v ) :
			?>
			<input type="hidden" name="<?php echo esc_attr( $gf_k ); ?>" value="<?php echo esc_attr( $gf_v ); ?>" />
		<?php endforeach; ?>

		<p class="ceafsn-gf-filters__row">
			<label class="screen-reader-text" for="ceafsn-gf-search"><?php esc_html_e( 'Search grants', 'ceafsn-gf' ); ?></label>
			<input type="search" id="ceafsn-gf-search" name="gf_search"
				value="<?php echo esc_attr( $filter_search ); ?>"
				placeholder="<?php esc_attr_e( 'Search titles and eligibility', 'ceafsn-gf' ); ?>" />

			<label class="screen-reader-text" for="ceafsn-gf-filter-institution"><?php esc_html_e( 'Filter by funding institution', 'ceafsn-gf' ); ?></label>
			<select id="ceafsn-gf-filter-institution" name="gf_institution">
				<option value=""><?php esc_html_e( 'All institutions', 'ceafsn-gf' ); ?></option>
				<?php foreach ( $institutions as $gf_institution ) : ?>
					<option value="<?php echo esc_attr( $gf_institution ); ?>" <?php selected( $gf_institution, $filter_institution ); ?>>
						<?php echo esc_html( $gf_institution ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<label class="screen-reader-text" for="ceafsn-gf-filter-status"><?php esc_html_e( 'Filter by status', 'ceafsn-gf' ); ?></label>
			<select id="ceafsn-gf-filter-status" name="gf_status">
				<option value=""><?php esc_html_e( 'All statuses', 'ceafsn-gf' ); ?></option>
				<?php foreach ( $statuses as $gf_status ) : ?>
					<option value="<?php echo esc_attr( $gf_status ); ?>" <?php selected( $gf_status, $filter_status ); ?>>
						<?php echo esc_html( CEAFSN_GF_Public::status_label( $gf_status ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php if ( 'table' === $view ) : ?>
				<input type="hidden" name="gf_view" value="table" />
			<?php endif; ?>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'ceafsn-gf' ); ?></button>
		</p>
	</form>
	<?php endif; ?>

	<?php if ( empty( $rows ) ) : ?>

		<p class="ceafsn-gf-empty">
			<?php esc_html_e( 'No funding opportunities are currently open.', 'ceafsn-gf' ); ?>
		</p>

	<?php elseif ( 'table' === $view ) : ?>

		<table class="ceafsn-gf-table">
			<caption class="screen-reader-text"><?php esc_html_e( 'Grants and scholarships', 'ceafsn-gf' ); ?></caption>
			<thead>
				<tr>
					<?php
					$gf_headers = array(
						'title'               => __( 'Opportunity', 'ceafsn-gf' ),
						'funding_institution' => __( 'Institution', 'ceafsn-gf' ),
						'deadline'            => __( 'Deadline', 'ceafsn-gf' ),
					);

					foreach ( $gf_headers as $gf_key => $gf_label ) :
						$gf_sort = CEAFSN_GF_Public::sort_link( $base_url, $gf_key, $orderby, $order );
						?>
						<th scope="col" aria-sort="<?php echo esc_attr( $gf_sort['aria_sort'] ); ?>">
							<a href="<?php echo esc_url( $gf_sort['url'] ); ?>">
								<?php echo esc_html( $gf_label ); ?>
								<span class="screen-reader-text">
									<?php
									echo esc_html(
										'asc' === $gf_sort['next']
											? __( '(sort ascending)', 'ceafsn-gf' )
											: __( '(sort descending)', 'ceafsn-gf' )
									);
									?>
								</span>
							</a>
						</th>
					<?php endforeach; ?>
					<th scope="col"><?php esc_html_e( 'Award range', 'ceafsn-gf' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Apply', 'ceafsn-gf' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $gf_card ) : ?>
					<tr>
						<th scope="row">
							<span class="ceafsn-gf-badge ceafsn-gf-badge--status-<?php echo esc_attr( $gf_card['status'] ); ?>">
								<?php echo esc_html( $gf_card['status_label'] ); ?>
							</span>
							<?php echo esc_html( (string) $gf_card['row']->title ); ?>
						</th>
						<td><?php echo esc_html( '' !== (string) $gf_card['row']->funding_institution ? (string) $gf_card['row']->funding_institution : '—' ); ?></td>
						<td>
							<?php if ( '' !== $gf_card['deadline_iso'] ) : ?>
								<time datetime="<?php echo esc_attr( $gf_card['deadline_iso'] ); ?>">
									<?php echo esc_html( CEAFSN_GF_Public::format_deadline( (string) $gf_card['row']->deadline, false ) ); ?>
								</time>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( '' !== (string) $gf_card['row']->award_range ? (string) $gf_card['row']->award_range : '—' ); ?></td>
						<td><?php CEAFSN_GF_Public::render_apply( $gf_card ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php else : ?>

		<ul class="ceafsn-gf-cards">
			<?php foreach ( $rows as $gf_card ) : ?>
				<li class="ceafsn-gf-card ceafsn-gf-card--<?php echo esc_attr( $gf_card['status'] ); ?>">

					<p class="ceafsn-gf-card__status">
						<span class="ceafsn-gf-badge ceafsn-gf-badge--status-<?php echo esc_attr( $gf_card['status'] ); ?>">
							<?php echo esc_html( $gf_card['status_label'] ); ?>
						</span>
						<?php if ( $gf_card['is_shared_document'] ) : ?>
							<span class="ceafsn-gf-badge ceafsn-gf-badge--shared">
								<?php esc_html_e( 'Shared document', 'ceafsn-gf' ); ?>
							</span>
						<?php endif; ?>
					</p>

					<h3 class="ceafsn-gf-card__title"><?php echo esc_html( (string) $gf_card['row']->title ); ?></h3>

					<dl class="ceafsn-gf-card__meta">
						<div>
							<dt><?php esc_html_e( 'Institution', 'ceafsn-gf' ); ?></dt>
							<dd><?php echo esc_html( '' !== (string) $gf_card['row']->funding_institution ? (string) $gf_card['row']->funding_institution : '—' ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Award range', 'ceafsn-gf' ); ?></dt>
							<dd><?php echo esc_html( '' !== (string) $gf_card['row']->award_range ? (string) $gf_card['row']->award_range : '—' ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Deadline', 'ceafsn-gf' ); ?></dt>
							<dd>
								<?php if ( '' !== $gf_card['deadline_iso'] ) : ?>
									<time datetime="<?php echo esc_attr( $gf_card['deadline_iso'] ); ?>">
										<?php echo esc_html( CEAFSN_GF_Public::format_deadline( (string) $gf_card['row']->deadline ) ); ?>
									</time>
								<?php else : ?>
									<span class="ceafsn-gf-card__nodate"><?php esc_html_e( 'No deadline recorded', 'ceafsn-gf' ); ?></span>
								<?php endif; ?>
							</dd>
						</div>
					</dl>

					<?php if ( '' !== (string) $gf_card['row']->target_beneficiaries ) : ?>
						<div class="ceafsn-gf-card__beneficiaries">
							<h4><?php esc_html_e( 'Who this is for', 'ceafsn-gf' ); ?></h4>
							<p><?php echo esc_html( (string) $gf_card['row']->target_beneficiaries ); ?></p>
						</div>
					<?php endif; ?>

					<div class="ceafsn-gf-card__eligibility">
						<h4><?php esc_html_e( 'Eligibility', 'ceafsn-gf' ); ?></h4>
						<p><?php echo esc_html( (string) $gf_card['row']->eligibility ); ?></p>
					</div>

					<p class="ceafsn-gf-card__actions">
						<?php CEAFSN_GF_Public::render_apply( $gf_card ); ?>
						<?php if ( $gf_card['has_pdf'] ) : ?>
							<a class="ceafsn-gf-card__doc" href="<?php echo esc_url( $gf_card['pdf_url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $gf_card['pdf_label'] ); ?>
							</a>
						<?php endif; ?>
					</p>

					<?php if ( '' !== $gf_card['contact'] ) : ?>
						<p class="ceafsn-gf-card__contact">
							<?php
							printf(
								/* translators: %s: contact detail. */
								esc_html__( 'Questions? Contact %s', 'ceafsn-gf' ),
								esc_html( $gf_card['contact'] )
							);
							?>
						</p>
					<?php endif; ?>

					<?php if ( ! $gf_card['is_open'] && $gf_card['deadline_passed'] ) : ?>
						<p class="ceafsn-gf-card__reason"><?php esc_html_e( 'The deadline has passed.', 'ceafsn-gf' ); ?></p>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php endif; ?>

	<?php
	// Rendered for both the card grid and the table view: a visitor who filtered
	// to one funder still needs a way to reach the rest of that funder's calls.
	echo CEAFSN_GF_Public::render_pagination( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in render_pagination().
		(int) $current_page,
		(int) $total_pages,
		(string) $base_url,
		'gf_page',
		__( 'Grant opportunity pages', 'ceafsn-gf' )
	);
	?>
</div>
