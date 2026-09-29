<?php
/**
 * Admin partial: Metrics list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_MED_Admin::page_metrics()):
 *   @var string        $action  'list' | 'add' | 'edit'
 *   @var int           $id      Row ID when editing
 *   @var object|null   $row     DB row when editing
 *   @var array         $items   All metric rows for list view
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

// Show error or success notices.
if ( ! empty( $_GET['ceafsn_med_error'] ) ) : ?>
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

<div class="wrap ceafsn-med-wrap">

<?php if ( 'add' === $action || 'edit' === $action ) : ?>

	<?php
	$is_edit  = 'edit' === $action && $row instanceof stdClass;
	$form_url = admin_url( 'admin-post.php' );
	?>
	<h1><?php echo $is_edit ? esc_html__( 'Edit Metric', 'ceafsn-med' ) : esc_html__( 'Add Metric', 'ceafsn-med' ); ?></h1>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med' ) ); ?>">
			&larr; <?php esc_html_e( 'Back to Metrics', 'ceafsn-med' ); ?>
		</a>
	</p>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<input type="hidden" name="action"      value="ceafsn_med_save_metric">
		<input type="hidden" name="metric_id"   value="<?php echo $is_edit ? absint( $row->metric_id ) : 0; ?>">
		<?php wp_nonce_field( 'ceafsn_med_metric_nonce', 'ceafsn_med_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="med-label"><?php esc_html_e( 'Label', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-label" name="label" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->label : '' ); ?>"
						required aria-required="true">
					<p class="description"><?php esc_html_e( 'Short name for the metric, e.g. "Households Reached".', 'ceafsn-med' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-value"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-value" name="value" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->value : '' ); ?>"
						required aria-required="true">
					<p class="description"><?php esc_html_e( 'Real, verified value only. Do not enter placeholder or estimated data.', 'ceafsn-med' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-unit"><?php esc_html_e( 'Unit', 'ceafsn-med' ); ?></label></th>
				<td>
					<input id="med-unit" name="unit" type="text" class="small-text"
						value="<?php echo esc_attr( $is_edit ? $row->unit : '' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional, e.g. "%", "households", "kg".', 'ceafsn-med' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-definition"><?php esc_html_e( 'Definition', 'ceafsn-med' ); ?></label></th>
				<td>
					<textarea id="med-definition" name="definition" rows="3" class="large-text"><?php echo esc_textarea( $is_edit ? $row->definition : '' ); ?></textarea>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-source"><?php esc_html_e( 'Source', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-source" name="source" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->source : '' ); ?>"
						required aria-required="true">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-period"><?php esc_html_e( 'Reporting Period', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-period" name="reporting_period" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->reporting_period : '' ); ?>"
						required aria-required="true"
						placeholder="<?php esc_attr_e( 'e.g. Q1 2024', 'ceafsn-med' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-visibility"><?php esc_html_e( 'Visibility', 'ceafsn-med' ); ?></label></th>
				<td>
					<select id="med-visibility" name="visibility">
						<option value="private" <?php selected( $is_edit ? $row->visibility : 'private', 'private' ); ?>>
							<?php esc_html_e( 'Private (admin only)', 'ceafsn-med' ); ?>
						</option>
						<option value="public" <?php selected( $is_edit ? $row->visibility : 'private', 'public' ); ?>>
							<?php esc_html_e( 'Public', 'ceafsn-med' ); ?>
						</option>
					</select>
					<p class="description"><?php esc_html_e( 'Only set to Public when the value is verified and approved for public display.', 'ceafsn-med' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Metric', 'ceafsn-med' ) : __( 'Add Metric', 'ceafsn-med' ) ); ?>
	</form>

<?php else : ?>

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Metrics', 'ceafsn-med' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med&action=add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'ceafsn-med' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'No metrics have been added yet.', 'ceafsn-med' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" aria-label="<?php esc_attr_e( 'Metrics list', 'ceafsn-med' ); ?>">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Label', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Unit', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Source', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Period', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Visibility', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-med' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item->label ); ?></td>
					<td><?php echo esc_html( $item->value ); ?></td>
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
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med&action=edit&id=' . absint( $item->metric_id ) ) ); ?>">
							<?php esc_html_e( 'Edit', 'ceafsn-med' ); ?>
						</a>
						|
						<a href="<?php echo esc_url(
							wp_nonce_url(
								admin_url( 'admin-post.php?action=ceafsn_med_delete_metric&id=' . absint( $item->metric_id ) ),
								'ceafsn_med_delete_metric_' . absint( $item->metric_id )
							)
						); ?>"
							class="ceafsn-delete-link"
							onclick="return confirm('<?php esc_attr_e( 'Delete this metric? This cannot be undone.', 'ceafsn-med' ); ?>');">
							<?php esc_html_e( 'Delete', 'ceafsn-med' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

<?php endif; ?>
</div><!-- .ceafsn-med-wrap -->
