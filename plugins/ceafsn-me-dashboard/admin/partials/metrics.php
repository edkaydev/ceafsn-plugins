<?php
/**
 * Admin partial: Metrics list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_MED_Admin::page_metrics()):
 *   @var string      $action  'list' | 'add' | 'edit'
 *   @var int         $id      Row ID when editing
 *   @var object|null $row     DB row when editing
 *   @var array       $items   All metric rows for list view
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

$med_is_edit = ( 'edit' === $action && $row instanceof stdClass );
?>

<div class="wrap ceafsn-med-wrap">

	<?php if ( ! empty( $_GET['ceafsn_med_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_med_error'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Metric saved.', 'ceafsn-med' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Metric deleted.', 'ceafsn-med' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $med_is_edit || 'add' === $action ) : ?>

		<a class="ceafsn-back" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_METRICS ) ); ?>">
			&larr; <?php esc_html_e( 'Back to metrics', 'ceafsn-med' ); ?>
		</a>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'M&E Dashboard', 'ceafsn-med' ); ?></p>
			<h1 class="ceafsn-hero__title">
				<?php
				echo $med_is_edit
					? esc_html__( 'Edit metric', 'ceafsn-med' )
					: esc_html__( 'Add metric', 'ceafsn-med' );
				?>
			</h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'A source and a reporting period are required. Leave visibility on private until the value is approved for public display.', 'ceafsn-med' ); ?>
			</p>
		</section>

		<section class="ceafsn-card">
			<div class="ceafsn-card__body">
				<form class="ceafsn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action"    value="ceafsn_med_save_metric">
					<input type="hidden" name="metric_id" value="<?php echo $med_is_edit ? absint( $row->metric_id ) : 0; ?>">
					<?php wp_nonce_field( 'ceafsn_med_metric_nonce', 'ceafsn_med_nonce' ); ?>

					<div class="ceafsn-field">
						<label for="med-label">
							<?php esc_html_e( 'Label', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-label" name="label" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->label : '' ); ?>" required aria-required="true">
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Short name for the metric, e.g. "Households Reached".', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="med-value">
							<?php esc_html_e( 'Value', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-value" name="value" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->value : '' ); ?>" required aria-required="true">
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Real, verified value only. Do not enter placeholder or estimated data.', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="med-unit"><?php esc_html_e( 'Unit', 'ceafsn-med' ); ?></label>
						<input id="med-unit" name="unit" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->unit : '' ); ?>">
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Optional, e.g. "%", "households", "kg".', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="med-definition"><?php esc_html_e( 'Definition', 'ceafsn-med' ); ?></label>
						<textarea id="med-definition" name="definition" rows="3"><?php echo esc_textarea( $med_is_edit ? $row->definition : '' ); ?></textarea>
					</div>

					<div class="ceafsn-field">
						<label for="med-source">
							<?php esc_html_e( 'Source', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-source" name="source" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->source : '' ); ?>" required aria-required="true">
					</div>

					<div class="ceafsn-field">
						<label for="med-period">
							<?php esc_html_e( 'Reporting period', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-period" name="reporting_period" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->reporting_period : '' ); ?>" required aria-required="true" placeholder="<?php esc_attr_e( 'e.g. Q1 2024', 'ceafsn-med' ); ?>">
					</div>

					<div class="ceafsn-field">
						<label for="med-visibility"><?php esc_html_e( 'Visibility', 'ceafsn-med' ); ?></label>
						<select id="med-visibility" name="visibility">
							<option value="private" <?php selected( $med_is_edit ? $row->visibility : 'private', 'private' ); ?>>
								<?php esc_html_e( 'Private (admin only)', 'ceafsn-med' ); ?>
							</option>
							<option value="public" <?php selected( $med_is_edit ? $row->visibility : 'private', 'public' ); ?>>
								<?php esc_html_e( 'Public', 'ceafsn-med' ); ?>
							</option>
						</select>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Only set to Public when the value is verified and approved for public display.', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php
							echo $med_is_edit
								? esc_html__( 'Update metric', 'ceafsn-med' )
								: esc_html__( 'Add metric', 'ceafsn-med' );
							?>
						</button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_METRICS ) ); ?>">
							<?php esc_html_e( 'Cancel', 'ceafsn-med' ); ?>
						</a>
					</div>
				</form>
			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'M&E Dashboard', 'ceafsn-med' ); ?></p>
			<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Metrics', 'ceafsn-med' ); ?></h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'Every metric carries its own source and reporting period, so a published number can always be traced back to where it came from.', 'ceafsn-med' ); ?>
			</p>
			<div class="ceafsn-hero__actions">
				<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_METRICS . '&action=add' ) ); ?>">
					<?php esc_html_e( '+ Add metric', 'ceafsn-med' ); ?>
				</a>
			</div>
		</section>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title">
						<?php
						printf(
							/* translators: %s: number of metrics. */
							esc_html__( 'All metrics (%s)', 'ceafsn-med' ),
							esc_html( number_format_i18n( count( $items ) ) )
						);
						?>
					</h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Private records are visible to admins only.', 'ceafsn-med' ); ?></p>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="ceafsn-empty">
					<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
					<h3 class="ceafsn-empty__title"><?php esc_html_e( 'No metrics yet', 'ceafsn-med' ); ?></h3>
					<p class="ceafsn-empty__text">
						<?php esc_html_e( 'Nothing has been recorded. The public dashboard stays empty until you add a real metric with a source.', 'ceafsn-med' ); ?>
					</p>
					<div class="ceafsn-empty__action">
						<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_METRICS . '&action=add' ) ); ?>">
							<?php esc_html_e( 'Add the first metric', 'ceafsn-med' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<div class="ceafsn-table-wrap">
					<table class="ceafsn-table">
						<caption class="ceafsn-sr"><?php esc_html_e( 'Metrics list', 'ceafsn-med' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Label', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Unit', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Source', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Period', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Visibility', 'ceafsn-med' ); ?></th>
								<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-med' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $item->label ); ?></strong></td>
									<td class="ceafsn-table__num"><?php echo esc_html( $item->value ); ?></td>
									<td><?php echo esc_html( $item->unit ); ?></td>
									<td><?php echo esc_html( $item->source ); ?></td>
									<td><?php echo esc_html( $item->reporting_period ); ?></td>
									<td>
										<?php if ( 'public' === $item->visibility ) : ?>
											<span class="ceafsn-badge ceafsn-badge--public"><?php esc_html_e( 'Public', 'ceafsn-med' ); ?></span>
										<?php else : ?>
											<span class="ceafsn-badge ceafsn-badge--private"><?php esc_html_e( 'Private', 'ceafsn-med' ); ?></span>
										<?php endif; ?>
									</td>
									<td class="ceafsn-table__actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_METRICS . '&action=edit&amp;id=' . absint( $item->metric_id ) ) ); ?>">
											<?php esc_html_e( 'Edit', 'ceafsn-med' ); ?>
										</a>
										<a class="ceafsn-delete-link" href="<?php echo esc_url(
											wp_nonce_url(
												admin_url( 'admin-post.php?action=ceafsn_med_delete_metric&id=' . absint( $item->metric_id ) ),
												'ceafsn_med_delete_metric_' . absint( $item->metric_id )
											)
										); ?>"
											data-confirm-message="<?php echo esc_attr( __( 'Delete this metric? This cannot be undone.', 'ceafsn-med' ) ); ?>"
											onclick="return confirm('<?php echo esc_js( __( 'Delete this metric? This cannot be undone.', 'ceafsn-med' ) ); ?>');">
											<?php esc_html_e( 'Delete', 'ceafsn-med' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>

	<?php endif; ?>
</div><!-- .ceafsn-med-wrap -->