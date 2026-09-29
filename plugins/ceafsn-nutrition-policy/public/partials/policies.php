<?php
/**
 * Public partial: policy table front-end template.
 *
 * Variables in scope (extracted by CEAFSN_NP_Public::render_shortcode()):
 *   @var array $rows          Row models: row, pdf_url, has_pdf
 *   @var int   $total         Total matching records
 *   @var array $topics        Distinct published topics
 *   @var array $sortable      Allow-listed sortable columns
 *   @var int   $per_page      Records per page
 *   @var int   $current_page  Current page number
 *   @var string $orderby      Active sort column
 *   @var string $order        'asc' or 'desc'
 *   @var string $filter_topic Active topic filter
 *   @var string $filter_search Active search term
 * @var string $base_url     Current page URL
 *
 * Sorting and URL helpers live on CEAFSN_NP_Public as static methods so the
 * template stays include-safe: rendering the shortcode twice on one page must
 * not redeclare anything.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

$column_labels = array(
	'title'                 => __( 'Policy', 'ceafsn-np' ),
	'publication_date'      => __( 'Published', 'ceafsn-np' ),
	'topic'                 => __( 'Topic', 'ceafsn-np' ),
	'authoring_institution' => __( 'Institution', 'ceafsn-np' ),
);
?>

<div class="ceafsn-np" id="ceafsn-np-app">

	<form class="ceafsn-np-filters" method="get" action="<?php echo esc_url( $base_url ); ?>" role="search">
		<input type="hidden" name="np_sort" value="<?php echo esc_attr( $orderby ); ?>" />
		<input type="hidden" name="np_order" value="<?php echo esc_attr( $order ); ?>" />
		<input type="hidden" name="np_page" value="1" />

		<div class="ceafsn-np-filters__field">
			<label for="ceafsn-np-search"><?php esc_html_e( 'Search policies', 'ceafsn-np' ); ?></label>
			<input type="search" id="ceafsn-np-search" name="np_search" value="<?php echo esc_attr( $filter_search ); ?>"
				placeholder="<?php esc_attr_e( 'Title, description, or institution', 'ceafsn-np' ); ?>" />
		</div>

		<div class="ceafsn-np-filters__field">
			<label for="ceafsn-np-topic"><?php esc_html_e( 'Filter by topic', 'ceafsn-np' ); ?></label>
			<select id="ceafsn-np-topic" name="np_topic">
				<option value=""><?php esc_html_e( 'All topics', 'ceafsn-np' ); ?></option>
				<?php foreach ( $topics as $topic ) : ?>
					<option value="<?php echo esc_attr( $topic ); ?>" <?php selected( $topic, $filter_topic ); ?>>
						<?php echo esc_html( $topic ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ceafsn-np-filters__actions">
			<button type="submit" class="ceafsn-np-btn"><?php esc_html_e( 'Apply filters', 'ceafsn-np' ); ?></button>
			<?php if ( '' !== $filter_search || '' !== $filter_topic ) : ?>
				<a class="ceafsn-np-btn ceafsn-np-btn--ghost"
					href="<?php echo esc_url( CEAFSN_NP_Public::page_url( $base_url, array( 'np_search' => '', 'np_topic' => '', 'np_page' => 1 ) ) ); ?>">
					<?php esc_html_e( 'Clear filters', 'ceafsn-np' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>

	<div class="ceafsn-np-results" id="ceafsn-np-results" tabindex="-1">

		<p class="ceafsn-np-count" role="status">
			<?php
			printf(
				/* translators: %d: number of policy records. */
				esc_html( _n( '%d policy record', '%d policy records', $total, 'ceafsn-np' ) ),
				(int) $total
			);
			?>
		</p>

		<?php if ( empty( $rows ) ) : ?>

			<p class="ceafsn-np-empty">
				<?php esc_html_e( 'No policies have been published yet.', 'ceafsn-np' ); ?>
			</p>

		<?php else : ?>

			<div class="ceafsn-np-table-wrap" tabindex="0" role="region"
				aria-labelledby="ceafsn-np-table-caption">

				<table class="ceafsn-np-table">
					<caption id="ceafsn-np-table-caption" class="screen-reader-text">
						<?php esc_html_e( 'Published nutrition and food security policy documents', 'ceafsn-np' ); ?>
					</caption>
					<thead>
						<tr>
							<?php foreach ( $sortable as $column ) : ?>
								<?php
								$link = CEAFSN_NP_Public::sort_link( $base_url, $column, $orderby, $order );
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
							<th scope="col"><?php esc_html_e( 'Document', 'ceafsn-np' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $model ) : ?>
							<?php $row = $model['row']; ?>
							<tr>
								<th scope="row" class="ceafsn-np-table__title">
									<?php echo esc_html( (string) $row->title ); ?>
									<p class="ceafsn-np-table__description"><?php echo esc_html( (string) $row->description ); ?></p>
								</th>
								<td data-label="<?php esc_attr_e( 'Published', 'ceafsn-np' ); ?>">
									<time datetime="<?php echo esc_attr( (string) $row->publication_date ); ?>">
										<?php echo esc_html( (string) $row->publication_date ); ?>
									</time>
								</td>
								<td data-label="<?php esc_attr_e( 'Topic', 'ceafsn-np' ); ?>"><?php echo esc_html( (string) $row->topic ); ?></td>
								<td data-label="<?php esc_attr_e( 'Institution', 'ceafsn-np' ); ?>"><?php echo esc_html( (string) $row->authoring_institution ); ?></td>
								<td class="ceafsn-np-table__doc">
									<?php if ( $model['has_pdf'] ) : ?>
										<a class="ceafsn-np-btn ceafsn-np-btn--small"
											href="<?php echo esc_url( $model['pdf_url'] ); ?>"
											target="_blank" rel="noopener noreferrer">
											<?php esc_html_e( 'Read Policy', 'ceafsn-np' ); ?>
											<span class="screen-reader-text">
												<?php
												printf(
													/* translators: %s: policy title. */
													esc_html__( '(opens %s in a new tab)', 'ceafsn-np' ),
													esc_html( (string) $row->title )
												);
												?>
											</span>
										</a>
										<?php if ( ! empty( $row->source_url ) ) : ?>
											<a class="ceafsn-np-source"
												href="<?php echo esc_url( (string) $row->source_url ); ?>"
												target="_blank" rel="noopener noreferrer">
												<?php esc_html_e( 'Source', 'ceafsn-np' ); ?>
											</a>
										<?php endif; ?>
									<?php else : ?>
										<span class="ceafsn-np-missing">
											<?php esc_html_e( 'Document currently unavailable. Please contact the institution.', 'ceafsn-np' ); ?>
										</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php
			$total_pages = (int) ceil( $total / max( 1, $per_page ) );
			if ( $total_pages > 1 ) :
				?>
				<nav class="ceafsn-np-pagination" aria-label="<?php esc_attr_e( 'Policy record pages', 'ceafsn-np' ); ?>">
					<?php if ( $current_page > 1 ) : ?>
						<a class="ceafsn-np-btn ceafsn-np-btn--ghost"
							href="<?php echo esc_url( CEAFSN_NP_Public::page_url( $base_url, array( 'np_page' => $current_page - 1 ) ) ); ?>"
							rel="prev">
							<?php esc_html_e( 'Previous', 'ceafsn-np' ); ?>
						</a>
					<?php endif; ?>

					<span class="ceafsn-np-pagination__status">
						<?php
						printf(
							/* translators: 1: current page, 2: total pages. */
							esc_html__( 'Page %1$d of %2$d', 'ceafsn-np' ),
							(int) $current_page,
							(int) $total_pages
						);
						?>
					</span>

					<?php if ( $current_page < $total_pages ) : ?>
						<a class="ceafsn-np-btn ceafsn-np-btn--ghost"
							href="<?php echo esc_url( CEAFSN_NP_Public::page_url( $base_url, array( 'np_page' => $current_page + 1 ) ) ); ?>"
							rel="next">
							<?php esc_html_e( 'Next', 'ceafsn-np' ); ?>
						</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

		<?php endif; ?>

	</div>
</div>
