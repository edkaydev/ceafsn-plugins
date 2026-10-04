<?php
/**
 * Admin partial: Projects list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_MED_Admin::page_projects()):
 *   @var string      $action  'list' | 'add' | 'edit'
 *   @var int         $id      Row ID when editing
 *   @var object|null $row     DB row when editing
 *   @var array       $items   All project rows
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
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Project saved.', 'ceafsn-med' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Project deleted.', 'ceafsn-med' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $med_is_edit || 'add' === $action ) : ?>

		<a class="ceafsn-back" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_PROJECTS ) ); ?>">
			&larr; <?php esc_html_e( 'Back to projects', 'ceafsn-med' ); ?>
		</a>

		<header class="ceafsn-header">
			<p class="ceafsn-header__eyebrow"><?php esc_html_e( 'M&E Dashboard', 'ceafsn-med' ); ?></p>
			<h1 class="ceafsn-header__title">
				<?php
				echo $med_is_edit
					? esc_html__( 'Edit project', 'ceafsn-med' )
					: esc_html__( 'Add project', 'ceafsn-med' ); ?>
			</h1>
			<p class="ceafsn-header__subtitle">
				<?php esc_html_e( 'Project status and verification are tracked separately — a project can be active while still awaiting verification.', 'ceafsn-med' ); ?>
			</p>
		</header>

		<section class="ceafsn-card">
			<div class="ceafsn-card__body">
				<form class="ceafsn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action"     value="ceafsn_med_save_project">
					<input type="hidden" name="project_id" value="<?php echo $med_is_edit ? absint( $row->project_id ) : 0; ?>">
					<?php wp_nonce_field( 'ceafsn_med_project_nonce', 'ceafsn_med_nonce' ); ?>

					<div class="ceafsn-field">
						<label for="med-project-title">
							<?php esc_html_e( 'Title', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-project-title" name="title" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->title : '' ); ?>" required aria-required="true">
					</div>

					<div class="ceafsn-field">
						<label for="med-project-pi">
							<?php esc_html_e( 'Principal investigator', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-project-pi" name="principal_investigator" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->principal_investigator : '' ); ?>" required aria-required="true">
					</div>

					<div class="ceafsn-field">
						<label for="med-project-region"><?php esc_html_e( 'Target region', 'ceafsn-med' ); ?></label>
						<input id="med-project-region" name="target_region" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->target_region : '' ); ?>">
					</div>

					<div class="ceafsn-field">
						<label for="med-project-status"><?php esc_html_e( 'Status', 'ceafsn-med' ); ?></label>
						<select id="med-project-status" name="status">
							<option value="active" <?php selected( $med_is_edit ? $row->status : 'active', 'active' ); ?>>
								<?php esc_html_e( 'Active', 'ceafsn-med' ); ?>
							</option>
							<option value="completed" <?php selected( $med_is_edit ? $row->status : 'active', 'completed' ); ?>>
								<?php esc_html_e( 'Completed', 'ceafsn-med' ); ?>
							</option>
							<option value="suspended" <?php selected( $med_is_edit ? $row->status : 'active', 'suspended' ); ?>>
								<?php esc_html_e( 'Suspended', 'ceafsn-med' ); ?>
							</option>
						</select>
					</div>

					<div class="ceafsn-field">
						<label for="med-project-verify"><?php esc_html_e( 'Verification status', 'ceafsn-med' ); ?></label>
						<select id="med-project-verify" name="verification_status">
							<option value="pending" <?php selected( $med_is_edit ? $row->verification_status : 'pending', 'pending' ); ?>>
								<?php esc_html_e( 'Pending', 'ceafsn-med' ); ?>
							</option>
							<option value="verified" <?php selected( $med_is_edit ? $row->verification_status : 'pending', 'verified' ); ?>>
								<?php esc_html_e( 'Verified', 'ceafsn-med' ); ?>
							</option>
							<option value="unverified" <?php selected( $med_is_edit ? $row->verification_status : 'pending', 'unverified' ); ?>>
								<?php esc_html_e( 'Unverified', 'ceafsn-med' ); ?>
							</option>
						</select>
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Independent of status. Only mark verified once someone has checked the record.', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="med-project-updated">
							<?php esc_html_e( 'Last updated', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-project-updated" name="last_updated" type="date" value="<?php echo esc_attr( $med_is_edit ? $row->last_updated : '' ); ?>" required aria-required="true">
					</div>

					<div class="ceafsn-field">
						<label for="med-project-url"><?php esc_html_e( 'Source URL', 'ceafsn-med' ); ?></label>
						<input id="med-project-url" name="source_url" type="url" value="<?php echo esc_attr( $med_is_edit ? $row->source_url : '' ); ?>">
						<p class="ceafsn-field__hint"><?php esc_html_e( 'Optional link to where this project is described.', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php
							echo $med_is_edit
								? esc_html__( 'Update project', 'ceafsn-med' )
								: esc_html__( 'Add project', 'ceafsn-med' ); ?>
						</button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_PROJECTS ) ); ?>">
							<?php esc_html_e( 'Cancel', 'ceafsn-med' ); ?>
						</a>
					</div>
				</form>
			</div>
		</section>

	<?php else : ?>

		<header class="ceafsn-header">
			<p class="ceafsn-header__eyebrow"><?php esc_html_e( 'M&E Dashboard', 'ceafsn-med' ); ?></p>
			<h1 class="ceafsn-header__title"><?php esc_html_e( 'Projects', 'ceafsn-med' ); ?></h1>
			<p class="ceafsn-header__subtitle">
				<?php esc_html_e( 'The project registry. Verification is tracked separately from status so an active project can still be awaiting a check.', 'ceafsn-med' ); ?>
			</p>
			<div class="ceafsn-header__actions">
				<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_PROJECTS . '&action=add' ) ); ?>">
					<?php esc_html_e( '+ Add project', 'ceafsn-med' ); ?>
				</a>
			</div>
		</header>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title">
						<?php
						printf(
							/* translators: %s: number of projects. */
							esc_html__( 'Registry (%s)', 'ceafsn-med' ),
							esc_html( number_format_i18n( count( $items ) ) )
						);
						?>
					</h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Sorted with the entries needing a decision first.', 'ceafsn-med' ); ?></p>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="ceafsn-empty">
					<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
					<h3 class="ceafsn-empty__title"><?php esc_html_e( 'No projects registered', 'ceafsn-med' ); ?></h3>
					<p class="ceafsn-empty__text">
						<?php esc_html_e( 'The registry is empty. Add a project with its investigator and a last-updated date so the record can be verified later.', 'ceafsn-med' ); ?>
					</p>
					<div class="ceafsn-empty__action">
						<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_PROJECTS . '&action=add' ) ); ?>">
							<?php esc_html_e( 'Add the first project', 'ceafsn-med' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<div class="ceafsn-table-wrap">
					<table class="ceafsn-table">
						<caption class="ceafsn-sr"><?php esc_html_e( 'Projects list', 'ceafsn-med' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Title', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Investigator', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Region', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Verification', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Updated', 'ceafsn-med' ); ?></th>
								<th scope="col" class="ceafsn-table__actions"><?php esc_html_e( 'Actions', 'ceafsn-med' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $item->title ); ?></strong></td>
									<td><?php echo esc_html( $item->principal_investigator ); ?></td>
									<td><?php echo esc_html( $item->target_region ); ?></td>
									<td>
										<span class="ceafsn-badge ceafsn-badge--status-<?php echo esc_attr( $item->status ); ?>">
											<?php echo esc_html( ucfirst( $item->status ) ); ?>
										</span>
									</td>
									<td>
										<?php if ( 'verified' === $item->verification_status ) : ?>
											<span class="ceafsn-badge ceafsn-badge--verified"><?php esc_html_e( 'Verified', 'ceafsn-med' ); ?></span>
										<?php elseif ( 'unverified' === $item->verification_status ) : ?>
											<span class="ceafsn-badge ceafsn-badge--unverified"><?php esc_html_e( 'Unverified', 'ceafsn-med' ); ?></span>
										<?php else : ?>
											<span class="ceafsn-badge ceafsn-badge--pending"><?php esc_html_e( 'Pending', 'ceafsn-med' ); ?></span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $item->last_updated ); ?></td>
									<td class="ceafsn-table__actions">
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_PROJECTS . '&action=edit&amp;id=' . absint( $item->project_id ) ) ); ?>">
											<?php esc_html_e( 'Edit', 'ceafsn-med' ); ?>
										</a>
										<a class="ceafsn-delete-link" href="<?php echo esc_url(
											wp_nonce_url(
												admin_url( 'admin-post.php?action=ceafsn_med_delete_project&id=' . absint( $item->project_id ) ),
												'ceafsn_med_delete_project_' . absint( $item->project_id )
											)
										); ?>"
											onclick="return confirm('<?php echo esc_js( __( 'Delete this project? This cannot be undone.', 'ceafsn-med' ) ); ?>');">
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