<?php
/**
 * Public partial: projects and publications front-end template.
 *
 * Renders a card grid or a sortable table depending on the active view. Both
 * layouts are produced by the same server-side query, so the view toggle is a
 * presentation choice and never changes which records a visitor can see.
 *
 * Variables in scope (extracted by CEAFSN_PP_Public::render_shortcode()):
 *   @var array $rows          Card models
 *   @var int   $total         Total matching records
 *   @var array $years         Published years, newest first
 *   @var array $content_types Content type allow list
 *   @var array $statuses      Project status allow list
 *   @var array $sortable      Allow-listed sortable columns
 *   @var int   $per_page      Records per page
 *   @var int   $current_page  Current page number
 *   @var string $orderby      Active sort column
 *   @var string $order        'asc' or 'desc'
 *   @var string $view         'grid' or 'list'
 *   @var string $filter_type  Active content type filter
 *   @var string $filter_status Active project status filter
 *   @var int    $filter_year  Active year filter
 *   @var string $filter_search Active search term
 *   @var bool   $is_member    Whether the viewer may see members-only records
 *   @var string $base_url     Current page URL
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

$column_labels = array(
	'title'            => __( 'Title', 'ceafsn-pp' ),
	'publication_date' => __( 'Date', 'ceafsn-pp' ),
	'content_type'     => __( 'Type', 'ceafsn-pp' ),
	'project_status'   => __( 'Status', 'ceafsn-pp' ),
);
?>

<div class="ceafsn-pp ceafsn-pp--<?php echo esc_attr( $view ); ?>" id="ceafsn-pp-app">

	<form class="ceafsn-pp-filters" method="get" action="<?php echo esc_url( $base_url ); ?>" role="search">
		<input type="hidden" name="pp_view" value="<?php echo esc_attr( $view ); ?>" />
		<input type="hidden" name="pp_sort" value="<?php echo esc_attr( $orderby ); ?>" />
		<input type="hidden" name="pp_order" value="<?php echo esc_attr( $order ); ?>" />
		<input type="hidden" name="pp_page" value="1" />

		<div class="ceafsn-pp-filters__field">
			<label for="ceafsn-pp-search"><?php esc_html_e( 'Search records', 'ceafsn-pp' ); ?></label>
			<input type="search" id="ceafsn-pp-search" name="pp_search"
				value="<?php echo esc_attr( $filter_search ); ?>"
				placeholder="<?php esc_attr_e( 'Title, summary, or institution', 'ceafsn-pp' ); ?>" />
		</div>

		<div class="ceafsn-pp-filters__field">
			<label for="ceafsn-pp-type"><?php esc_html_e( 'Filter by type', 'ceafsn-pp' ); ?></label>
			<select id="ceafsn-pp-type" name="pp_type">
				<option value=""><?php esc_html_e( 'All types', 'ceafsn-pp' ); ?></option>
				<?php foreach ( $content_types as $type ) : ?>
					<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $type, $filter_type ); ?>>
						<?php echo esc_html( CEAFSN_PP_Public::type_label( $type ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ceafsn-pp-filters__field">
			<label for="ceafsn-pp-status"><?php esc_html_e( 'Filter by status', 'ceafsn-pp' ); ?></label>
			<select id="ceafsn-pp-status" name="pp_status">
				<option value=""><?php esc_html_e( 'All statuses', 'ceafsn-pp' ); ?></option>
				<?php foreach ( $statuses as $status ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status, $filter_status ); ?>>
						<?php echo esc_html( CEAFSN_PP_Public::status_label( $status ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ceafsn-pp-filters__field">
			<label for="ceafsn-pp-year"><?php esc_html_e( 'Filter by year', 'ceafsn-pp' ); ?></label>
			<select id="ceafsn-pp-year" name="pp_year">
				<option value=""><?php esc_html_e( 'All years', 'ceafsn-pp' ); ?></option>
				<?php foreach ( $years as $year ) : ?>
					<option value="<?php echo esc_attr( (string) $year ); ?>" <?php selected( (string) $year, (string) $filter_year ); ?>>
						<?php echo esc_html( (string) $year ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ceafsn-pp-filters__actions">
			<button type="submit" class="ceafsn-pp-btn"><?php esc_html_e( 'Apply filters', 'ceafsn-pp' ); ?></button>
			<?php if ( '' !== $filter_search || '' !== $filter_type || '' !== $filter_status || $filter_year > 0 ) : ?>
				<a class="ceafsn-pp-btn ceafsn-pp-btn--ghost"
					href="<?php echo esc_url( CEAFSN_PP_Public::page_url( $base_url, array( 'pp_search' => '', 'pp_type' => '', 'pp_status' => '', 'pp_year' => '', 'pp_page' => 1 ) ) ); ?>">
					<?php esc_html_e( 'Clear filters', 'ceafsn-pp' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>

	<div class="ceafsn-pp-toolbar">
		<p class="ceafsn-pp-count" role="status">
			<?php
			printf(
				/* translators: %d: number of publication records. */
				esc_html( _n( '%d record', '%d records', $total, 'ceafsn-pp' ) ),
				(int) $total
			);
			?>
		</p>

		<div class="ceafsn-pp-view" role="group" aria-label="<?php esc_attr_e( 'Layout', 'ceafsn-pp' ); ?>">
			<a class="ceafsn-pp-view__btn <?php echo 'grid' === $view ? 'is-active' : ''; ?>"
				href="<?php echo esc_url( CEAFSN_PP_Public::page_url( $base_url, array( 'pp_view' => 'grid' ) ) ); ?>"
				<?php echo 'grid' === $view ? 'aria-current="true"' : ''; ?>>
				<?php esc_html_e( 'Grid', 'ceafsn-pp' ); ?>
			</a>
			<a class="ceafsn-pp-view__btn <?php echo 'list' === $view ? 'is-active' : ''; ?>"
				href="<?php echo esc_url( CEAFSN_PP_Public::page_url( $base_url, array( 'pp_view' => 'list' ) ) ); ?>"
				<?php echo 'list' === $view ? 'aria-current="true"' : ''; ?>>
				<?php esc_html_e( 'List', 'ceafsn-pp' ); ?>
			</a>
		</div>
	</div>

	<div class="ceafsn-pp-results" id="ceafsn-pp-results" tabindex="-1">

		<?php if ( empty( $rows ) ) : ?>

			<p class="ceafsn-pp-empty">
				<?php esc_html_e( 'No publications or projects have been published yet.', 'ceafsn-pp' ); ?>
			</p>

		<?php elseif ( 'list' === $view ) : ?>

			<div class="ceafsn-pp-table-wrap" tabindex="0" role="region"
				aria-labelledby="ceafsn-pp-table-caption">

				<table class="ceafsn-pp-table">
					<caption id="ceafsn-pp-table-caption" class="screen-reader-text">
						<?php esc_html_e( 'Published CE-AFSN projects and publications', 'ceafsn-pp' ); ?>
					</caption>
					<thead>
						<tr>
							<?php foreach ( $sortable as $column ) : ?>
								<?php
								$link = CEAFSN_PP_Public::sort_link( $base_url, $column, $orderby, $order );
								$sort_indicator = ( $column === $orderby )
									? ( 'asc' === $order ? ' ↑' : ' ↓' )
									: '';
								?>
								<th scope="col" aria-sort="<?php echo esc_attr( $link['aria_sort'] ); ?>">
									<a href="<?php echo esc_url( $link['url'] ); ?>">
										<?php echo esc_html( (string) ( $column_labels[ $column ] ?? $column ) ); ?><?php echo esc_html( $sort_indicator ); ?>
									</a>
								</th>
							<?php endforeach; ?>
							<th scope="col"><?php esc_html_e( 'Author / institution', 'ceafsn-pp' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Document', 'ceafsn-pp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $card ) : ?>
							<?php $row = $card['row']; ?>
							<tr>
								<th scope="row" class="ceafsn-pp-table__title">
									<?php echo esc_html( (string) $row->title ); ?>
									<?php if ( '' !== (string) $row->executive_summary ) : ?>
										<p class="ceafsn-pp-table__summary"><?php echo esc_html( (string) $row->executive_summary ); ?></p>
									<?php endif; ?>
									<?php if ( $card['members_only'] ) : ?>
										<span class="ceafsn-pp-badge ceafsn-pp-badge--members">
											<?php esc_html_e( 'Members only', 'ceafsn-pp' ); ?>
										</span>
									<?php endif; ?>
								</th>
								<td data-label="<?php esc_attr_e( 'Date', 'ceafsn-pp' ); ?>">
									<time datetime="<?php echo esc_attr( (string) $row->publication_date ); ?>">
										<?php echo esc_html( (string) $row->publication_date ); ?>
									</time>
								</td>
								<td data-label="<?php esc_attr_e( 'Type', 'ceafsn-pp' ); ?>">
									<?php echo esc_html( CEAFSN_PP_Public::type_label( (string) $row->content_type ) ); ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Status', 'ceafsn-pp' ); ?>">
									<span class="ceafsn-pp-badge ceafsn-pp-badge--status-<?php echo esc_attr( (string) $row->project_status ); ?>">
										<?php echo esc_html( CEAFSN_PP_Public::status_label( (string) $row->project_status ) ); ?>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Author / institution', 'ceafsn-pp' ); ?>">
									<?php echo esc_html( (string) $row->author_institution ); ?>
								</td>
								<td class="ceafsn-pp-table__doc">
									<?php if ( $card['locked'] ) : ?>
										<span class="ceafsn-pp-locked">
											<?php esc_html_e( 'Sign in to view this document.', 'ceafsn-pp' ); ?>
										</span>
									<?php elseif ( $card['has_pdf'] ) : ?>
										<a class="ceafsn-pp-btn ceafsn-pp-btn--small"
											href="<?php echo esc_url( $card['pdf_url'] ); ?>"
											target="_blank" rel="noopener noreferrer"
											aria-label="<?php echo esc_attr( CEAFSN_PP_Public::document_label( $row, $card ) ); ?>">
											<?php esc_html_e( 'View Document', 'ceafsn-pp' ); ?>
										</a>
									<?php else : ?>
										<span class="ceafsn-pp-missing">
											<?php esc_html_e( 'Document currently unavailable.', 'ceafsn-pp' ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

		<?php else : ?>

			<ul class="ceafsn-pp-grid" id="ceafsn-pp-grid">
				<?php foreach ( $rows as $card ) : ?>
					<?php $row = $card['row']; ?>
					<li class="ceafsn-pp-card">
						<?php if ( $card['has_cover'] ) : ?>
							<img class="ceafsn-pp-card__cover"
								src="<?php echo esc_url( $card['cover_url'] ); ?>"
								alt="<?php echo esc_attr( $card['cover_alt'] ); ?>"
								loading="lazy" />
						<?php endif; ?>

						<div class="ceafsn-pp-card__body">
							<p class="ceafsn-pp-card__meta">
								<span class="ceafsn-pp-card__type"><?php echo esc_html( CEAFSN_PP_Public::type_label( (string) $row->content_type ) ); ?></span>
								<span class="ceafsn-pp-badge ceafsn-pp-badge--status-<?php echo esc_attr( (string) $row->project_status ); ?>">
									<?php echo esc_html( CEAFSN_PP_Public::status_label( (string) $row->project_status ) ); ?>
								</span>
								<?php if ( $card['members_only'] ) : ?>
									<span class="ceafsn-pp-badge ceafsn-pp-badge--members">
										<?php esc_html_e( 'Members only', 'ceafsn-pp' ); ?>
									</span>
								<?php endif; ?>
							</p>

							<h3 class="ceafsn-pp-card__title"><?php echo esc_html( (string) $row->title ); ?></h3>

							<?php if ( '' !== (string) $row->executive_summary ) : ?>
								<p class="ceafsn-pp-card__summary"><?php echo esc_html( (string) $row->executive_summary ); ?></p>
							<?php endif; ?>

							<dl class="ceafsn-pp-card__details">
								<div>
									<dt><?php esc_html_e( 'Author / institution', 'ceafsn-pp' ); ?></dt>
									<dd><?php echo esc_html( (string) $row->author_institution ); ?></dd>
								</div>
								<div>
									<dt><?php esc_html_e( 'Published', 'ceafsn-pp' ); ?></dt>
									<dd>
										<time datetime="<?php echo esc_attr( (string) $row->publication_date ); ?>">
											<?php echo esc_html( (string) $row->publication_date ); ?>
										</time>
									</dd>
								</div>
								<?php if ( '' !== $card['file_size'] ) : ?>
									<div>
										<dt><?php esc_html_e( 'File size', 'ceafsn-pp' ); ?></dt>
										<dd><?php echo esc_html( $card['file_size'] ); ?></dd>
									</div>
								<?php endif; ?>
								<?php if ( '' !== $card['page_label'] ) : ?>
									<div>
										<dt><?php esc_html_e( 'Length', 'ceafsn-pp' ); ?></dt>
										<dd><?php echo esc_html( $card['page_label'] ); ?></dd>
									</div>
								<?php endif; ?>
							</dl>

							<?php if ( $card['is_duplicate'] ) : ?>
								<p class="ceafsn-pp-card__note">
									<?php esc_html_e( 'This document is shared with another record.', 'ceafsn-pp' ); ?>
								</p>
							<?php endif; ?>

							<?php if ( $card['locked'] ) : ?>
								<span class="ceafsn-pp-locked">
									<?php esc_html_e( 'Sign in to view this document.', 'ceafsn-pp' ); ?>
								</span>
							<?php elseif ( $card['has_pdf'] ) : ?>
								<a class="ceafsn-pp-btn ceafsn-pp-btn--small"
									href="<?php echo esc_url( $card['pdf_url'] ); ?>"
									target="_blank" rel="noopener noreferrer"
									aria-label="<?php echo esc_attr( CEAFSN_PP_Public::document_label( $row, $card ) ); ?>">
									<?php esc_html_e( 'View Document', 'ceafsn-pp' ); ?>
								</a>
							<?php else : ?>
								<span class="ceafsn-pp-missing">
									<?php esc_html_e( 'Document currently unavailable.', 'ceafsn-pp' ); ?>
								</span>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php endif; ?>

		<?php
		$total_pages = (int) ceil( $total / max( 1, $per_page ) );
		if ( $total_pages > 1 ) :
			?>
			<nav class="ceafsn-pp-pagination" aria-label="<?php esc_attr_e( 'Publication record pages', 'ceafsn-pp' ); ?>">
				<?php if ( $current_page > 1 ) : ?>
					<a class="ceafsn-pp-btn ceafsn-pp-btn--ghost"
						href="<?php echo esc_url( CEAFSN_PP_Public::page_url( $base_url, array( 'pp_page' => $current_page - 1 ) ) ); ?>"
						rel="prev">
						<?php esc_html_e( 'Previous', 'ceafsn-pp' ); ?>
					</a>
				<?php endif; ?>

				<span class="ceafsn-pp-pagination__status">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages. */
						esc_html__( 'Page %1$d of %2$d', 'ceafsn-pp' ),
						(int) $current_page,
						(int) $total_pages
					);
					?>
				</span>

				<?php if ( $current_page < $total_pages ) : ?>
					<a class="ceafsn-pp-btn ceafsn-pp-btn--ghost"
						href="<?php echo esc_url( CEAFSN_PP_Public::page_url( $base_url, array( 'pp_page' => $current_page + 1 ) ) ); ?>"
						rel="next">
						<?php esc_html_e( 'Next', 'ceafsn-pp' ); ?>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>

	</div>
</div>
