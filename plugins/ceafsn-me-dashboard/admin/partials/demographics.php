<?php
/**
 * Admin partial: Demographics list, add, and edit view.
 *
 * Variables in scope (set by CEAFSN_MED_Admin::page_demographics()):
 *   @var string      $action  'list' | 'add' | 'edit'
 *   @var int         $id      Row ID when editing
 *   @var object|null $row     DB row when editing
 *   @var array       $items   All demographic group rows
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
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Demographic group saved.', 'ceafsn-med' ); ?></p></div>
	<?php endif; ?>
	<?php if ( ! empty( $_GET['deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Demographic group deleted.', 'ceafsn-med' ); ?></p></div>
	<?php endif; ?>

	<?php if ( $med_is_edit || 'add' === $action ) : ?>

		<a class="ceafsn-back" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_DEMOGRAPHICS ) ); ?>">
			&larr; <?php esc_html_e( 'Back to demographics', 'ceafsn-med' ); ?>
		</a>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'M&E Dashboard', 'ceafsn-med' ); ?></p>
			<h1 class="ceafsn-hero__title">
				<?php
				echo $med_is_edit
					? esc_html__( 'Edit demographic group', 'ceafsn-med' )
					: esc_html__( 'Add demographic group', 'ceafsn-med' ); ?>
			</h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'Record a count or a percentage for one group, with the source and period it came from.', 'ceafsn-med' ); ?>
			</p>
		</section>

		<section class="ceafsn-card">
			<div class="ceafsn-card__body">
				<form class="ceafsn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action"   value="ceafsn_med_save_demo">
					<input type="hidden" name="group_id" value="<?php echo $med_is_edit ? absint( $row->group_id ) : 0; ?>">
					<?php wp_nonce_field( 'ceafsn_med_demo_nonce', 'ceafsn_med_nonce' ); ?>

					<div class="ceafsn-field">
						<label for="med-demo-label">
							<?php esc_html_e( 'Group', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-demo-label" name="label" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->label : '' ); ?>" required aria-required="true">
						<p class="ceafsn-field__hint"><?php esc_html_e( 'The group being described, e.g. "Rural households".', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="med-demo-value">
							<?php esc_html_e( 'Value', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-demo-value" name="value" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->value : '' ); ?>" required aria-required="true">
						<p class="ceafsn-field__hint"><?php esc_html_e( 'A number. Use the value type below to say how it should be read.', 'ceafsn-med' ); ?></p>
					</div>

					<div class="ceafsn-field">
						<label for="med-demo-type"><?php esc_html_e( 'Value type', 'ceafsn-med' ); ?></label>
						<select id="med-demo-type" name="value_type">
							<option value="count" <?php selected( $med_is_edit ? $row->value_type : 'count', 'count' ); ?>>
								<?php esc_html_e( 'Count', 'ceafsn-med' ); ?>
							</option>
							<option value="percentage" <?php selected( $med_is_edit ? $row->value_type : 'count', 'percentage' ); ?>>
								<?php esc_html_e( 'Percentage', 'ceafsn-med' ); ?>
							</option>
						</select>
					</div>

					<div class="ceafsn-field">
						<label for="med-demo-period">
							<?php esc_html_e( 'Reporting period', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-demo-period" name="reporting_period" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->reporting_period : '' ); ?>" required aria-required="true" placeholder="<?php esc_attr_e( 'e.g. Q1 2024', 'ceafsn-med' ); ?>">
					</div>

					<div class="ceafsn-field">
						<label for="med-demo-source">
							<?php esc_html_e( 'Source', 'ceafsn-med' ); ?>
							<span class="required" aria-hidden="true">*</span>
						</label>
						<input id="med-demo-source" name="source" type="text" value="<?php echo esc_attr( $med_is_edit ? $row->source : '' ); ?>" required aria-required="true">
					</div>

					<div class="ceafsn-field">
						<label for="med-demo-visibility"><?php esc_html_e( 'Visibility', 'ceafsn-med' ); ?></label>
						<select id="med-demo-visibility" name="visibility">
							<option value="private" <?php selected( $med_is_edit ? $row->visibility : 'private', 'private' ); ?>>
								<?php esc_html_e( 'Private (admin only)', 'ceafsn-med' ); ?>
							</option>
							<option value="public" <?php selected( $med_is_edit ? $row->visibility : 'private', 'public' ); ?>>
								<?php esc_html_e( 'Public', 'ceafsn-med' ); ?>
							</option>
						</select>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php
							echo $med_is_edit
								? esc_html__( 'Update group', 'ceafsn-med' )
								: esc_html__( 'Add group', 'ceafsn-med' ); ?>
						</button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_DEMOGRAPHICS ) ); ?>">
							<?php esc_html_e( 'Cancel', 'ceafsn-med' ); ?>
						</a>
					</div>
				</form>
			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-hero">
			<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'M&E Dashboard', 'ceafsn-med' ); ?></p>
			<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Demographics', 'ceafsn-med' ); ?></h1>
			<p class="ceafsn-hero__text">
				<?php esc_html_e( 'Who the work reached, broken down by group. Each figure is a count or a percentage tied to a named source.', 'ceafsn-med' ); ?>
			</p>
			<div class="ceafsn-hero__actions">
				<a class="ceafsn-btn ceafsn-btn--gold" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_DEMOGRAPHICS . '&action=add' ) ); ?>">
					<?php esc_html_e( '+ Add group', 'ceafsn-med' ); ?>
				</a>
			</div>
		</section>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title">
						<?php
						printf(
							/* translators: %s: number of demographic groups. */
							esc_html__( 'All groups (%s)', 'ceafsn-med' ),
							esc_html( number_format_i18n( count( $items ) ) )
						);
						?>
					</h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Private groups are visible to admins only.', 'ceafsn-med' ); ?></p>
				</div>
			</div>

			<?php if ( empty( $items ) ) : ?>
				<div class="ceafsn-empty">
					<span class="ceafsn-empty__mark" aria-hidden="true">&#9635;</span>
					<h3 class="ceafsn-empty__title"><?php esc_html_e( 'No demographic groups yet', 'ceafsn-med' ); ?></h3>
					<p class="ceafsn-empty__text">
						<?php esc_html_e( 'No breakdown has been recorded. The public view stays empty until a real, sourced figure is added.', 'ceafsn-med' ); ?>
					</p>
					<div class="ceafsn-empty__action">
						<a class="ceafsn-btn ceafsn-btn--primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_DEMOGRAPHICS . '&action=add' ) ); ?>">
							<?php esc_html_e( 'Add the first group', 'ceafsn-med' ); ?>
						</a>
					</div>
				</div>
			<?php else : ?>
				<div class="ceafsn-table-wrap">
					<table class="ceafsn-table">
						<caption class="ceafsn-sr"><?php esc_html_e( 'Demographic groups list', 'ceafsn-med' ); ?></caption>
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Group', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Value', 'ceafsn-med' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Type', 'ceafsn-med' ); ?></th>
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
									<td class="ceafsn-table__num">
										<?php echo esc_html( $item->value ); ?>
										<?php if ( 'percentage' === $item->value_type ) : ?>
											<span aria-hidden="true">%</span><span class="ceafsn-sr"><?php esc_html_e( 'percent', 'ceafsn-med' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( 'percentage' === $item->value_type ) : ?>
											<span class="ceafsn-badge ceafsn-badge--pending"><?php esc_html_e( 'Percentage', 'ceafsn-med' ); ?></span>
										<?php else : ?>
											<span class="ceafsn-badge ceafsn-badge--private"><?php esc_html_e( 'Count', 'ceafsn-med' ); ?></span>
										<?php endif; ?>
									</td>
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
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_DEMOGRAPHICS . '&action=edit&amp;id=' . absint( $item->group_id ) ) ); ?>">
											<?php esc_html_e( 'Edit', 'ceafsn-med' ); ?>
										</a>
										<a class="ceafsn-delete-link" href="<?php echo esc_url(
											wp_nonce_url(
												admin_url( 'admin-post.php?action=ceafsn_med_delete_demo&id=' . absint( $item->group_id ) ),
												'ceafsn_med_delete_demo_' . absint( $item->group_id )
											)
										); ?>"
											data-confirm-message="<?php echo esc_attr( __( 'Delete this demographic group? This cannot be undone.', 'ceafsn-med' ) ); ?>"
											onclick="return confirm('<?php echo esc_js( __( 'Delete this group? This cannot be undone.', 'ceafsn-med' ) ); ?>');">
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