<?php
/**
 * Admin partial: Settings page (privacy, export, uninstall).
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

$delete_flag  = get_option( CEAFSN_OD_DB::UNINSTALL_OPTION, false );
$show_contact = (bool) get_option( 'ceafsn_od_show_contact', 0 );
$allow_other  = (bool) get_option( 'ceafsn_od_allow_other_files', 0 );
$active_tab   = CEAFSN_OD_Request::key( 'tab', 'display' );
$form_url     = admin_url( 'admin-post.php' );
$settings_url = admin_url( 'admin.php?page=' . CEAFSN_OD_Admin::SETTINGS_SLUG );
$notice_saved = CEAFSN_OD_Request::has( 'saved' );

$od_tabs = array(
	'display'   => __( 'Display', 'ceafsn-od' ),
	'general'   => __( 'General', 'ceafsn-od' ),
	'export'    => __( 'Export', 'ceafsn-od' ),
	'uninstall' => __( 'Uninstall', 'ceafsn-od' ),
);

$od_tab_url = static function ( string $tab ) use ( $settings_url ): string {
	return add_query_arg( 'tab', $tab, $settings_url );
};
?>

<div class="wrap ceafsn-od-wrap">

	<a class="ceafsn-sr" href="#ceafsn-od-main"><?php esc_html_e( 'Skip to settings content', 'ceafsn-od' ); ?></a>

	<div id="ceafsn-od-main">

	<?php if ( $notice_saved ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--ok" role="status">
				<span class="ceafsn-alert__icon" aria-hidden="true">&#10003;</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title"><?php esc_html_e( 'Settings saved.', 'ceafsn-od' ); ?></p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<section class="ceafsn-hero">
		<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'Open Datasets', 'ceafsn-od' ); ?></p>
		<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Settings', 'ceafsn-od' ); ?></h1>
		<p class="ceafsn-hero__text">
			<?php esc_html_e( 'Privacy, file type checks, data export, and what happens to your records if the plugin is removed.', 'ceafsn-od' ); ?>
		</p>
	</section>

	<nav class="ceafsn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-od' ); ?>">
		<?php foreach ( $od_tabs as $od_tab_key => $od_tab_label ) : ?>
			<a class="ceafsn-tabs__tab<?php echo $od_tab_key === $active_tab ? ' ceafsn-tabs__tab--active' : ''; ?>"
				href="<?php echo esc_url( $od_tab_url( $od_tab_key ) ); ?>"
				<?php echo $od_tab_key === $active_tab ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $od_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'display' === $active_tab ) : ?>

		<div class="ceafsn-app">
			<div class="ceafsn-app__main">

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the datasets', 'ceafsn-od' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Datasets stay invisible to visitors until this shortcode is on a page and the records themselves are published.', 'ceafsn-od' ); ?></p>
						</div>
					</div>
					<div class="ceafsn-card__body">

						<div class="ceafsn-embed">
							<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste this shortcode into the page that should list datasets', 'ceafsn-od' ); ?></p>
							<code class="ceafsn-embed__code">[ceafsn_open_datasets]</code>
						</div>

						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'It goes in the page content, in a Code / Preformatted block, or anywhere the block editor accepts HTML.', 'ceafsn-od' ); ?>
						</p>

						<table class="ceafsn-embed__table">
							<caption class="ceafsn-embed__caption"><?php esc_html_e( 'Optional attributes', 'ceafsn-od' ); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Attribute', 'ceafsn-od' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Default', 'ceafsn-od' ); ?></th>
									<th scope="col"><?php esc_html_e( 'What it does', 'ceafsn-od' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><code>per_page</code></td>
									<td><code>20</code></td>
									<td><?php esc_html_e( 'Rows per page. Values are clamped between 1 and 100.', 'ceafsn-od' ); ?></td>
								</tr>
								<tr>
									<td><code>category</code></td>
									<td><code>""</code></td>
									<td>
										<?php esc_html_e( 'Show one category only. Spell it exactly as it is on the record, for example:', 'ceafsn-od' ); ?>
										<code><?php echo esc_html( 'Health' ); ?></code>.
										<?php esc_html_e( 'Leave empty for every category.', 'ceafsn-od' ); ?>
									</td>
								</tr>
								<tr>
									<td><code>file_type</code></td>
									<td><code>""</code></td>
									<td>
										<?php esc_html_e( 'Show one file type only:', 'ceafsn-od' ); ?>
										<code>csv</code>, <code>zip</code>, <code>xlsx</code>.
										<?php esc_html_e( 'Leave empty for every type.', 'ceafsn-od' ); ?>
									</td>
								</tr>
							</tbody>
						</table>

						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'Example: [ceafsn_open_datasets per_page="12" file_type="csv"]', 'ceafsn-od' ); ?>
						</p>

						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title"><?php esc_html_e( 'Visitors can still narrow the list', 'ceafsn-od' ); ?></p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'Sorting, category and file-type filtering, search, and pagination come from the visitor’s link. That is intentional: a filtered link shows the same results for everyone who opens it, including a visitor who has JS turned off.', 'ceafsn-od' ); ?>
								</p>
							</div>
						</div>

					</div>
				</section>

			</div>

			<aside class="ceafsn-app__rail">

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'What visitors see', 'ceafsn-od' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Only published datasets', 'ceafsn-od' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'Drafts and archived datasets never reach the page.', 'ceafsn-od' ); ?></li>
						<li><?php esc_html_e( 'A dataset with no file attached cannot be published at all.', 'ceafsn-od' ); ?></li>
						<li><?php esc_html_e( 'External links are shown only when they are allowed.', 'ceafsn-od' ); ?></li>
					</ul>
				</div>

				<div class="ceafsn-cta" id="ceafsn-od-help">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Manage datasets', 'ceafsn-od' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add and publish', 'ceafsn-od' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'The shortcode is only the container. Add datasets and set each one to Published.', 'ceafsn-od' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_OD_Admin::MENU_SLUG ) ); ?>">
						<?php esc_html_e( 'Go to datasets', 'ceafsn-od' ); ?>
					</a>
				</div>

			</aside>
		</div>

	<?php elseif ( 'general' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Privacy and file types', 'ceafsn-od' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Both options are off by default.', 'ceafsn-od' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">
				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_od_settings_nonce', 'ceafsn_od_nonce' ); ?>
					<input type="hidden" name="action" value="ceafsn_od_save_settings" />

					<div class="ceafsn-check">
						<input type="checkbox" name="ceafsn_od_show_contact" value="1" id="ceafsn-od-show-contact" <?php checked( true, $show_contact ); ?>>
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-od-show-contact">
								<?php esc_html_e( 'Show the contact / owner on the public page', 'ceafsn-od' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Off by default. Contact details stay in the database and are only rendered publicly when this is enabled.', 'ceafsn-od' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-check">
						<input type="checkbox" name="ceafsn_od_allow_other_files" value="1" id="ceafsn-od-allow-other" <?php checked( true, $allow_other ); ?>>
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-od-allow-other">
								<?php esc_html_e( 'Allow the "Other" file type in the media picker', 'ceafsn-od' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Off by default. When enabled, files that are not CSV, ZIP, or XLSX can be attached. "Other" files are checked for a non-zero size but their contents are not inspected.', 'ceafsn-od' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Save settings', 'ceafsn-od' ); ?></button>
					</div>
				</form>
			</div>
		</section>

	<?php elseif ( 'export' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Export records', 'ceafsn-od' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'A plain JSON copy of everything this plugin stores.', 'ceafsn-od' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">
				<p class="ceafsn-lead">
					<?php esc_html_e( 'Download every dataset record as JSON before making bulk changes or uninstalling the plugin.', 'ceafsn-od' ); ?>
				</p>
				<a class="ceafsn-btn ceafsn-btn--primary"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_od_export' ), 'ceafsn_od_export_nonce', 'ceafsn_od_nonce' ) ); ?>">
					<?php esc_html_e( 'Download export', 'ceafsn-od' ); ?>
				</a>
			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-card ceafsn-danger">
			<div class="ceafsn-card__head">
				<h2 class="ceafsn-card__title"><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-od' ); ?></h2>
			</div>
			<div class="ceafsn-card__body">
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Warning:', 'ceafsn-od' ); ?></strong>
						<?php esc_html_e( 'Enabling the option below means that all dataset records and plugin options will be permanently deleted when you delete this plugin via Plugins → Delete. This cannot be undone. Export your data first.', 'ceafsn-od' ); ?>
					</p>
				</div>

				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_od_settings_nonce', 'ceafsn_od_nonce' ); ?>
					<input type="hidden" name="action" value="ceafsn_od_save_settings" />

					<div class="ceafsn-check">
						<input type="checkbox" name="ceafsn_od_uninstall_delete_data" value="1" id="ceafsn-od-uninstall-delete" <?php checked( true, (bool) $delete_flag ); ?>>
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-od-uninstall-delete">
								<?php esc_html_e( 'Delete all dataset records and plugin options when the plugin is deleted', 'ceafsn-od' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Leave this off to keep your records if the plugin is ever removed or reinstalled. Files in the media library are never removed — other pages may still link to them.', 'ceafsn-od' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--danger"><?php esc_html_e( 'Save uninstall settings', 'ceafsn-od' ); ?></button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $od_tab_url( 'export' ) ); ?>">
							<?php esc_html_e( 'Export first', 'ceafsn-od' ); ?>
						</a>
					</div>
				</form>
			</div>
		</section>

	<?php endif; ?>

	</div><!-- #ceafsn-od-main -->

</div><!-- .ceafsn-od-wrap -->