<?php
/**
 * Admin partial: Demographics list, add, and edit view.
 *
 * Variables in scope:
 *   @var string        $action  'list' | 'add' | 'edit'
 *   @var int           $id
 *   @var object|null   $row
 *   @var array         $items
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

if ( ! empty( $_GET['ceafsn_med_error'] ) ) : ?>
<div class="notice notice-error is-dismissible">
	<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_med_error'] ) ) ) ); ?></p>
</div>
<?php endif; ?>
<?php if ( ! empty( $_GET['saved'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Demographic group saved.', 'ceafsn-med' ); ?></p></div>
<?php endif; ?>
<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Demographic group deleted.', 'ceafsn-med' ); ?></p></div>
<?php endif; ?>

<div class="wrap ceafsn-med-wrap">

<?php if ( 'add' === $action || 'edit' === $action ) : ?>

	<?php
	$is_edit  = 'edit' === $action && $row instanceof stdClass;
	$form_url = admin_url( 'admin-post.php' );
	?>
	<h1><?php echo $is_edit ? esc_html__( 'Edit Demographic Group', 'ceafsn-med' ) : esc_html__( 'Add Demographic Group', 'ceafsn-med' ); ?></h1>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-demographics' ) ); ?>">
			&larr; <?php esc_html_e( 'Back to Demographics', 'ceafsn-med' ); ?>
		</a>
	</p>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<input type="hidden" name="action"   value="ceafsn_med_save_demo">
		<input type="hidden" name="group_id" value="<?php echo $is_edit ? absint( $row->group_id ) : 0; ?>">
		<?php wp_nonce_field( 'ceafsn_med_demo_nonce', 'ceafsn_med_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="med-d-label"><?php esc_html_e( 'Label', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-d-label" name="label" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->label : '' ); ?>"
						required aria-required="true">
					<p class="description"><?php esc_html_e( 'Name of the demographic group, e.g. "Women 15–49".', 'ceafsn-med' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-d-value"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-d-value" name="value" type="number" step="0.01" class="small-text"
						value="<?php echo esc_attr( $is_edit ? $row->value : '' ); ?>"
						required aria-required="true">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-d-type"><?php esc_html_e( 'Value Type', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<select id="med-d-type" name="value_type">
						<option value="count" <?php selected( $is_edit ? $row->value_type : 'count', 'count' ); ?>>
							<?php esc_html_e( 'Count', 'ceafsn-med' ); ?>
						</option>
						<option value="percentage" <?php selected( $is_edit ? $row->value_type : 'count', 'percentage' ); ?>>
							<?php esc_html_e( 'Percentage', 'ceafsn-med' ); ?>
						</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-d-period"><?php esc_html_e( 'Reporting Period', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-d-period" name="reporting_period" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->reporting_period : '' ); ?>"
						required aria-required="true"
						placeholder="<?php esc_attr_e( 'e.g. 2023 Annual Survey', 'ceafsn-med' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-d-source"><?php esc_html_e( 'Source', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-d-source" name="source" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->source : '' ); ?>"
						required aria-required="true">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-d-vis"><?php esc_html_e( 'Visibility', 'ceafsn-med' ); ?></label></th>
				<td>
					<select id="med-d-vis" name="visibility">
						<option value="private" <?php selected( $is_edit ? $row->visibility : 'private', 'private' ); ?>>
							<?php esc_html_e( 'Private', 'ceafsn-med' ); ?>
						</option>
						<option value="public" <?php selected( $is_edit ? $row->visibility : 'private', 'public' ); ?>>
							<?php esc_html_e( 'Public', 'ceafsn-med' ); ?>
						</option>
					</select>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Group', 'ceafsn-med' ) : __( 'Add Group', 'ceafsn-med' ) ); ?>
	</form>

<?php else : ?>

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Demographics', 'ceafsn-med' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-demographics&action=add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'ceafsn-med' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'No demographic groups have been added yet.', 'ceafsn-med' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" aria-label="<?php esc_attr_e( 'Demographics list', 'ceafsn-med' ); ?>">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Label', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Type', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Period', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Source', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Visibility', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-med' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td><?php echo esc_html( $item->label ); ?></td>
					<td><?php echo esc_html( number_format( (float) $item->value, 2 ) ); ?></td>
					<td><?php echo esc_html( ucfirst( $item->value_type ) ); ?></td>
					<td><?php echo esc_html( $item->reporting_period ); ?></td>
					<td><?php echo esc_html( $item->source ); ?></td>
					<td>
						<?php if ( 'public' === $item->visibility ) : ?>
							<span class="ceafsn-badge ceafsn-badge--public"><?php esc_html_e( 'Public', 'ceafsn-med' ); ?></span>
						<?php else : ?>
							<span class="ceafsn-badge ceafsn-badge--private"><?php esc_html_e( 'Private', 'ceafsn-med' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-demographics&action=edit&id=' . absint( $item->group_id ) ) ); ?>">
							<?php esc_html_e( 'Edit', 'ceafsn-med' ); ?>
						</a>
						|
						<a href="<?php echo esc_url(
							wp_nonce_url(
								admin_url( 'admin-post.php?action=ceafsn_med_delete_demo&id=' . absint( $item->group_id ) ),
								'ceafsn_med_delete_demo_' . absint( $item->group_id )
							)
						); ?>"
							class="ceafsn-delete-link"
							onclick="return confirm('<?php esc_attr_e( 'Delete this group? This cannot be undone.', 'ceafsn-med' ); ?>');">
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
