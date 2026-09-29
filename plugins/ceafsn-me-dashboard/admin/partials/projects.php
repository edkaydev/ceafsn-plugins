<?php
/**
 * Admin partial: Projects list, add, and edit view.
 *
 * Variables in scope:
 *   @var string        $action  'list' | 'add' | 'edit'
 *   @var int           $id
 *   @var object|null   $row
 *   @var array         $items   All project rows
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
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Project saved.', 'ceafsn-med' ); ?></p></div>
<?php endif; ?>
<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Project deleted.', 'ceafsn-med' ); ?></p></div>
<?php endif; ?>

<div class="wrap ceafsn-med-wrap">

<?php if ( 'add' === $action || 'edit' === $action ) : ?>

	<?php
	$is_edit  = 'edit' === $action && $row instanceof stdClass;
	$form_url = admin_url( 'admin-post.php' );

	$statuses = array(
		'active'    => __( 'Active', 'ceafsn-med' ),
		'completed' => __( 'Completed', 'ceafsn-med' ),
		'suspended' => __( 'Suspended', 'ceafsn-med' ),
	);
	$verifs = array(
		'verified'   => __( 'Verified', 'ceafsn-med' ),
		'pending'    => __( 'Pending', 'ceafsn-med' ),
		'unverified' => __( 'Unverified', 'ceafsn-med' ),
	);
	?>
	<h1><?php echo $is_edit ? esc_html__( 'Edit Project', 'ceafsn-med' ) : esc_html__( 'Add Project', 'ceafsn-med' ); ?></h1>
	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-projects' ) ); ?>">
			&larr; <?php esc_html_e( 'Back to Projects', 'ceafsn-med' ); ?>
		</a>
	</p>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<input type="hidden" name="action"     value="ceafsn_med_save_project">
		<input type="hidden" name="project_id" value="<?php echo $is_edit ? absint( $row->project_id ) : 0; ?>">
		<?php wp_nonce_field( 'ceafsn_med_project_nonce', 'ceafsn_med_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="med-p-title"><?php esc_html_e( 'Title', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-p-title" name="title" type="text" class="large-text"
						value="<?php echo esc_attr( $is_edit ? $row->title : '' ); ?>"
						required aria-required="true">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-p-pi"><?php esc_html_e( 'Principal Investigator', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-p-pi" name="principal_investigator" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->principal_investigator : '' ); ?>"
						required aria-required="true">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-p-region"><?php esc_html_e( 'Target Region', 'ceafsn-med' ); ?></label></th>
				<td>
					<input id="med-p-region" name="target_region" type="text" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->target_region : '' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-p-status"><?php esc_html_e( 'Status', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<select id="med-p-status" name="status">
						<?php foreach ( $statuses as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"
								<?php selected( $is_edit ? $row->status : 'active', $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-p-verif"><?php esc_html_e( 'Verification Status', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<select id="med-p-verif" name="verification_status">
						<?php foreach ( $verifs as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>"
								<?php selected( $is_edit ? $row->verification_status : 'pending', $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-p-date"><?php esc_html_e( 'Last Updated', 'ceafsn-med' ); ?> <span aria-hidden="true">*</span></label></th>
				<td>
					<input id="med-p-date" name="last_updated" type="date"
						value="<?php echo esc_attr( $is_edit ? $row->last_updated : gmdate( 'Y-m-d' ) ); ?>"
						required aria-required="true">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="med-p-url"><?php esc_html_e( 'Source URL', 'ceafsn-med' ); ?></label></th>
				<td>
					<input id="med-p-url" name="source_url" type="url" class="regular-text"
						value="<?php echo esc_attr( $is_edit ? $row->source_url : '' ); ?>">
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Project', 'ceafsn-med' ) : __( 'Add Project', 'ceafsn-med' ) ); ?>
	</form>

<?php else : ?>

	<h1 class="wp-heading-inline"><?php esc_html_e( 'Projects', 'ceafsn-med' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-projects&action=add' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add New', 'ceafsn-med' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php
	$status_labels = array(
		'active'    => __( 'Active', 'ceafsn-med' ),
		'completed' => __( 'Completed', 'ceafsn-med' ),
		'suspended' => __( 'Suspended', 'ceafsn-med' ),
	);
	$verif_labels = array(
		'verified'   => __( 'Verified', 'ceafsn-med' ),
		'pending'    => __( 'Pending', 'ceafsn-med' ),
		'unverified' => __( 'Unverified', 'ceafsn-med' ),
	);
	?>

	<?php if ( empty( $items ) ) : ?>
		<p><?php esc_html_e( 'No projects have been added yet.', 'ceafsn-med' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped" aria-label="<?php esc_attr_e( 'Projects list', 'ceafsn-med' ); ?>">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'PI', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Region', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Verification', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last Updated', 'ceafsn-med' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'ceafsn-med' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $item ) : ?>
				<tr>
					<td>
						<?php echo esc_html( $item->title ); ?>
						<?php if ( ! empty( $item->source_url ) ) : ?>
							<br><a href="<?php echo esc_url( $item->source_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Source', 'ceafsn-med' ); ?> ↗
							</a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $item->principal_investigator ); ?></td>
					<td><?php echo esc_html( $item->target_region ?: '—' ); ?></td>
					<td>
						<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( $item->status ); ?>">
							<?php echo esc_html( $status_labels[ $item->status ] ?? ucfirst( $item->status ) ); ?>
						</span>
					</td>
					<td>
						<span class="ceafsn-badge ceafsn-badge--verif-<?php echo esc_attr( $item->verification_status ); ?>">
							<?php echo esc_html( $verif_labels[ $item->verification_status ] ?? ucfirst( $item->verification_status ) ); ?>
						</span>
					</td>
					<td><?php echo esc_html( $item->last_updated ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-projects&action=edit&id=' . absint( $item->project_id ) ) ); ?>">
							<?php esc_html_e( 'Edit', 'ceafsn-med' ); ?>
						</a>
						|
						<a href="<?php echo esc_url(
							wp_nonce_url(
								admin_url( 'admin-post.php?action=ceafsn_med_delete_project&id=' . absint( $item->project_id ) ),
								'ceafsn_med_delete_project_' . absint( $item->project_id )
							)
						); ?>"
							class="ceafsn-delete-link"
							onclick="return confirm('<?php esc_attr_e( 'Delete this project? This cannot be undone.', 'ceafsn-med' ); ?>');">
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
