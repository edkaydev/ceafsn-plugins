<?php
/**
 * Public partial: dataset repository table front-end template.
 *
 * Variables in scope (extracted by CEAFSN_OD_Public::render_shortcode()):
 *   @var array $rows          Row models: row, download, has_file, size_label
 *   @var int   $total         Total matching records
 *   @var array $categories    Distinct published categories
 *   @var array $file_types    File type labels
 *   @var array $sortable      Allow-listed sortable columns
 *   @var int   $per_page      Records per page
 *   @var int   $current_page  Current page number
 *   @var string $orderby      Active sort column
 *   @var string $order        'asc' or 'desc'
 *   @var string $filter_cat   Active category filter
 *   @var string $filter_type  Active file type filter
 *   @var string $filter_search Active search term
 *   @var bool   $show_contact Whether the contact owner may be shown
 * @var string $base_url     Current page URL
 *
 * Sorting and URL helpers live on CEAFSN_OD_Public as static methods so the
 * template stays include-safe: rendering the shortcode twice on one page must
 * not redeclare anything.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

$column_labels = array(
	'name'         => __( 'Dataset', 'ceafsn-od' ),
	'category'     => __( 'Category', 'ceafsn-od' ),
	'last_updated' => __( 'Last updated', 'ceafsn-od' ),
	'file_type'    => __( 'File type / size', 'ceafsn-od' ),
);
?>

<div class="ceafsn-od" id="ceafsn-od-app">

	<form class="ceafsn-od-filters" method="get" action="<?php echo esc_url( $base_url ); ?>" role="search">
		<input type="hidden" name="od_sort" value="<?php echo esc_attr( $orderby ); ?>" />
		<input type="hidden" name="od_order" value="<?php echo esc_attr( $order ); ?>" />
		<input type="hidden" name="od_page" value="1" />

		<div class="ceafsn-od-filters__field">
			<label for="ceafsn-od-search"><?php esc_html_e( 'Search datasets', 'ceafsn-od' ); ?></label>
			<input type="search" id="ceafsn-od-search" name="od_search" value="<?php echo esc_attr( $filter_search ); ?>"
				placeholder="<?php esc_attr_e( 'Name, description, or area', 'ceafsn-od' ); ?>" />
		</div>

		<div class="ceafsn-od-filters__field">
			<label for="ceafsn-od-category"><?php esc_html_e( 'Filter by category', 'ceafsn-od' ); ?></label>
			<select id="ceafsn-od-category" name="od_category">
				<option value=""><?php esc_html_e( 'All categories', 'ceafsn-od' ); ?></option>
				<?php foreach ( $categories as $category ) : ?>
					<option value="<?php echo esc_attr( $category ); ?>" <?php selected( $category, $filter_cat ); ?>>
						<?php echo esc_html( $category ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ceafsn-od-filters__field">
			<label for="ceafsn-od-file-type"><?php esc_html_e( 'Filter by file type', 'ceafsn-od' ); ?></label>
			<select id="ceafsn-od-file-type" name="od_file_type">
				<option value=""><?php esc_html_e( 'All file types', 'ceafsn-od' ); ?></option>
				<?php foreach ( $file_types as $type_value => $type_label ) : ?>
					<option value="<?php echo esc_attr( (string) $type_value ); ?>" <?php selected( (string) $type_value, $filter_type ); ?>>
						<?php echo esc_html( (string) $type_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="ceafsn-od-filters__actions">
			<button type="submit" class="ceafsn-od-btn"><?php esc_html_e( 'Apply filters', 'ceafsn-od' ); ?></button>
			<?php if ( '' !== $filter_search || '' !== $filter_cat || '' !== $filter_type ) : ?>
				<a class="ceafsn-od-btn ceafsn-od-btn--ghost"
					href="<?php echo esc_url( CEAFSN_OD_Public::page_url( $base_url, array( 'od_search' => '', 'od_category' => '', 'od_file_type' => '', 'od_page' => 1 ) ) ); ?>">
					<?php esc_html_e( 'Clear filters', 'ceafsn-od' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</form>

	<div class="ceafsn-od-results" id="ceafsn-od-results" tabindex="-1">

		<p class="ceafsn-od-count" role="status">
			<?php
			printf(
				/* translators: %d: number of dataset records. */
				esc_html( _n( '%d dataset', '%d datasets', $total, 'ceafsn-od' ) ),
				(int) $total
			);
			?>
		</p>

		<?php if ( empty( $rows ) ) : ?>

			<p class="ceafsn-od-empty">
				<?php esc_html_e( 'No public dataset is currently available for download.', 'ceafsn-od' ); ?>
			</p>

		<?php else : ?>

			<div class="ceafsn-od-table-wrap" tabindex="0" role="region"
				aria-labelledby="ceafsn-od-table-caption">

				<table class="ceafsn-od-table">
					<caption id="ceafsn-od-table-caption" class="screen-reader-text">
						<?php esc_html_e( 'Published open food security and nutrition datasets', 'ceafsn-od' ); ?>
					</caption>
					<thead>
						<tr>
							<?php foreach ( $sortable as $column ) : ?>
								<?php
								$link           = CEAFSN_OD_Public::sort_link( $base_url, $column, $orderby, $order );
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
							<th scope="col"><?php esc_html_e( 'Coverage area', 'ceafsn-od' ); ?></th>
							<th scope="col"><?php esc_html_e( 'License', 'ceafsn-od' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Download', 'ceafsn-od' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $model ) : ?>
							<?php
							$row           = $model['row'];
							$row_type      = (string) $row->file_type;
							$type_label    = (string) ( $file_types[ $row_type ] ?? strtoupper( $row_type ) );
							$methodology   = (string) $row->methodology_url;
							$contact_owner = (string) $row->contact_owner;
							$size_label    = (string) $model['size_label'];
							?>
							<tr>
								<th scope="row" class="ceafsn-od-table__title">
									<?php echo esc_html( (string) $row->name ); ?>
									<p class="ceafsn-od-table__description"><?php echo esc_html( (string) $row->description ); ?></p>
									<?php if ( '' !== $methodology ) : ?>
										<p class="ceafsn-od-table__meta">
											<a class="ceafsn-od-source"
												href="<?php echo esc_url( $methodology ); ?>"
												target="_blank" rel="noopener noreferrer">
												<?php esc_html_e( 'Data dictionary', 'ceafsn-od' ); ?>
												<span class="screen-reader-text">
													<?php
													printf(
														/* translators: %s: dataset name. */
														esc_html__( '(opens the data dictionary for %s in a new tab)', 'ceafsn-od' ),
														esc_html( (string) $row->name )
													);
													?>
												</span>
											</a>
										</p>
									<?php endif; ?>
									<?php if ( $show_contact && '' !== $contact_owner ) : ?>
										<p class="ceafsn-od-table__meta">
											<?php
											printf(
												/* translators: %s: contact owner. */
												esc_html__( 'Contact: %s', 'ceafsn-od' ),
												esc_html( $contact_owner )
											);
											?>
										</p>
									<?php endif; ?>
								</th>
								<td data-label="<?php esc_attr_e( 'Category', 'ceafsn-od' ); ?>"><?php echo esc_html( (string) $row->category ); ?></td>
								<td data-label="<?php esc_attr_e( 'Last updated', 'ceafsn-od' ); ?>">
									<time datetime="<?php echo esc_attr( (string) $row->last_updated ); ?>">
										<?php echo esc_html( (string) $row->last_updated ); ?>
									</time>
								</td>
								<td data-label="<?php esc_attr_e( 'File type / size', 'ceafsn-od' ); ?>">
									<?php echo esc_html( $type_label ); ?>
									<span class="ceafsn-od-table__size"><?php echo esc_html( $size_label ); ?></span>
								</td>
								<td data-label="<?php esc_attr_e( 'Coverage area', 'ceafsn-od' ); ?>"><?php echo esc_html( (string) $row->coverage_area ); ?></td>
								<td data-label="<?php esc_attr_e( 'License', 'ceafsn-od' ); ?>"><?php echo esc_html( (string) $row->data_license ); ?></td>
								<td class="ceafsn-od-table__download">
									<?php if ( $model['has_file'] ) : ?>
										<?php
										$link_label = 'other' === $row_type
											/* translators: 1: dataset name, 2: file size. */
											? sprintf( __( 'Download %1$s, %2$s', 'ceafsn-od' ), (string) $row->name, $size_label )
											/* translators: 1: dataset name, 2: file type, 3: file size. */
											: sprintf( __( 'Download %1$s as %2$s, %3$s', 'ceafsn-od' ), (string) $row->name, $type_label, $size_label );
										?>
										<a class="ceafsn-od-btn ceafsn-od-btn--small"
											href="<?php echo esc_url( (string) $model['download'] ); ?>"
											target="_blank" rel="noopener noreferrer"
											aria-label="<?php echo esc_attr( $link_label ); ?>">
											<?php
											printf(
												/* translators: %s: file type. */
												esc_html__( 'Download %s', 'ceafsn-od' ),
												esc_html( $type_label )
											);
											?>
											<span class="screen-reader-text">
												<?php
												printf(
													/* translators: %s: dataset name. */
													esc_html__( '(opens %s in a new tab)', 'ceafsn-od' ),
													esc_html( (string) $row->name )
												);
												?>
											</span>
										</a>
									<?php else : ?>
										<span class="ceafsn-od-missing">
											<?php esc_html_e( 'Download currently unavailable. Please contact the publisher.', 'ceafsn-od' ); ?>
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
				<nav class="ceafsn-od-pagination" aria-label="<?php esc_attr_e( 'Dataset record pages', 'ceafsn-od' ); ?>">
					<?php if ( $current_page > 1 ) : ?>
						<a class="ceafsn-od-btn ceafsn-od-btn--ghost"
							href="<?php echo esc_url( CEAFSN_OD_Public::page_url( $base_url, array( 'od_page' => $current_page - 1 ) ) ); ?>"
							rel="prev">
							<?php esc_html_e( 'Previous', 'ceafsn-od' ); ?>
						</a>
					<?php endif; ?>

					<span class="ceafsn-od-pagination__status">
						<?php
						printf(
							/* translators: 1: current page, 2: total pages. */
							esc_html__( 'Page %1$d of %2$d', 'ceafsn-od' ),
							(int) $current_page,
							(int) $total_pages
						);
						?>
					</span>

					<?php if ( $current_page < $total_pages ) : ?>
						<a class="ceafsn-od-btn ceafsn-od-btn--ghost"
							href="<?php echo esc_url( CEAFSN_OD_Public::page_url( $base_url, array( 'od_page' => $current_page + 1 ) ) ); ?>"
							rel="next">
							<?php esc_html_e( 'Next', 'ceafsn-od' ); ?>
						</a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

		<?php endif; ?>

	</div>
</div>
