<?php
/**
 * Admin partial: Settings page (display, placeholders, routes, export, uninstall).
 *
 * Variables in scope:
 *   @var bool   $delete_flag   Whether data deletion on uninstall is enabled
 *   @var array  $placeholders  Known placeholder file names
 *   @var bool   $redirect_on   Whether the legacy route redirect is enabled
 *   @var string $active_tab    Active tab key
 *
 * The form field names, nonce actions, and export URL are a contract with
 * CEAFSN_PP_Admin::handle_save_settings() and handle_export(). Do not rename them.
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

$delete_flag  = get_option( 'ceafsn_pp_uninstall_delete_data', false );
$placeholders = CEAFSN_PP_Validator::placeholder_filenames();
$redirect_on  = CEAFSN_PP_Activator::redirect_enabled();
$active_tab   = sanitize_key( $_GET['tab'] ?? 'general' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$form_url     = admin_url( 'admin-post.php' );
$settings_url = admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::SETTINGS_SLUG );

$pp_tabs = array(
	'display'   => __( 'Display', 'ceafsn-pp' ),
	'placeholders' => __( 'Placeholders', 'ceafsn-pp' ),
	'routes'    => __( 'Routes', 'ceafsn-pp' ),
	'export'    => __( 'Export', 'ceafsn-pp' ),
	'uninstall' => __( 'Uninstall', 'ceafsn-pp' ),
);

$pp_tab_url = static function ( string $tab ) use ( $settings_url ): string {
	return add_query_arg( 'tab', $tab, $settings_url );
};
?>

<div class="wrap ceafsn-pp-wrap">

	<a class="ceafsn-sr" href="#ceafsn-pp-main"><?php esc_html_e( 'Skip to settings content', 'ceafsn-pp' ); ?></a>

	<div id="ceafsn-pp-main">

	<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--ok" role="status">
				<span class="ceafsn-alert__icon" aria-hidden="true">&#10003;</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__title"><?php esc_html_e( 'Settings saved.', 'ceafsn-pp' ); ?></p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<section class="ceafsn-hero">
		<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'Projects & Publications', 'ceafsn-pp' ); ?></p>
		<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Settings', 'ceafsn-pp' ); ?></h1>
		<p class="ceafsn-hero__text">
			<?php esc_html_e( 'How the shortcode is placed on a page, which file names count as placeholders, legacy route redirects, data export, and what happens to your records if the plugin is removed.', 'ceafsn-pp' ); ?>
		</p>
	</section>

	<nav class="ceafsn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-pp' ); ?>">
		<?php foreach ( $pp_tabs as $pp_tab_key => $pp_tab_label ) : ?>
			<a class="ceafsn-tabs__tab<?php echo $pp_tab_key === $active_tab ? ' ceafsn-tabs__tab--active' : ''; ?>"
				href="<?php echo esc_url( $pp_tab_url( $pp_tab_key ) ); ?>"
				<?php echo $pp_tab_key === $active_tab ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $pp_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'display' === $active_tab ) : ?>

		<div class="ceafsn-app">
			<div class="ceafsn-app__main">

				<section class="ceafsn-card">
					<div class="ceafsn-card__head">
						<div>
							<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the records', 'ceafsn-pp' ); ?></h2>
							<p class="ceafsn-card__hint"><?php esc_html_e( 'Records stay invisible to visitors until this shortcode is on a page and the records themselves are published.', 'ceafsn-pp' ); ?></p>
						</div>
					</div>
					<div class="ceafsn-card__body">

						<div class="ceafsn-embed">
							<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste this shortcode into the page that should list records', 'ceafsn-pp' ); ?></p>
							<code class="ceafsn-embed__code">[ceafsn_projects_pubs]</code>
						</div>

						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'It goes in the page content, in a Code / Preformatted block, or anywhere the block editor accepts HTML.', 'ceafsn-pp' ); ?>
						</p>

						<table class="ceafsn-embed__table">
							<caption class="ceafsn-embed__caption"><?php esc_html_e( 'Optional attributes', 'ceafsn-pp' ); ?></caption>
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Attribute', 'ceafsn-pp' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Default', 'ceafsn-pp' ); ?></th>
									<th scope="col"><?php esc_html_e( 'What it does', 'ceafsn-pp' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><code>per_page</code></td>
									<td><code>12</code></td>
									<td><?php esc_html_e( 'Records per page. Values are clamped between 1 and 48.', 'ceafsn-pp' ); ?></td>
								</tr>
								<tr>
									<td><code>content_type</code></td>
									<td><code>""</code></td>
									<td>
										<?php esc_html_e( 'Limit the list to one type:', 'ceafsn-pp' ); ?>
										<code>report</code>, <code>annual_report</code>, <code>policy_brief</code>, <code>working_paper</code>, <code>strategic_document</code>, <code>project</code>.
										<?php esc_html_e( 'Leave empty for every type.', 'ceafsn-pp' ); ?>
									</td>
								</tr>
								<tr>
									<td><code>view</code></td>
									<td><code>grid</code></td>
									<td><?php esc_html_e( 'Set to list for a table layout instead of cards.', 'ceafsn-pp' ); ?></td>
								</tr>
							</tbody>
						</table>

						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'Example: [ceafsn_projects_pubs per_page="24" view="list"]', 'ceafsn-pp' ); ?>
						</p>

						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title"><?php esc_html_e( 'Visitors can still narrow the list', 'ceafsn-pp' ); ?></p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'Sorting, filtering, search, and pagination come from the visitor’s link. That is intentional: a filtered link shows the same results for everyone who opens it, including a visitor who has JS turned off.', 'ceafsn-pp' ); ?>
								</p>
							</div>
						</div>

					</div>
				</section>

			</div>

			<aside class="ceafsn-app__rail">

				<div class="ceafsn-cta">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'What visitors see', 'ceafsn-pp' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Only published records', 'ceafsn-pp' ); ?></h2>
					<ul class="ceafsn-ticks">
						<li><?php esc_html_e( 'Drafts and archived records never reach the page.', 'ceafsn-pp' ); ?></li>
						<li><?php esc_html_e( 'Members-only records are hidden from logged-out visitors.', 'ceafsn-pp' ); ?></li>
						<li><?php esc_html_e( 'Records with no validated PDF cannot be published at all.', 'ceafsn-pp' ); ?></li>
					</ul>
				</div>

				<div class="ceafsn-cta" id="ceafsn-pp-help">
					<p class="ceafsn-cta__eyebrow"><?php esc_html_e( 'Manage records', 'ceafsn-pp' ); ?></p>
					<h2 class="ceafsn-cta__title"><?php esc_html_e( 'Add and publish', 'ceafsn-pp' ); ?></h2>
					<p class="ceafsn-cta__text">
						<?php esc_html_e( 'The shortcode is only the container. Add records and set each one to Published.', 'ceafsn-pp' ); ?>
					</p>
					<a class="ceafsn-btn ceafsn-btn--primary ceafsn-btn--block" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::MENU_SLUG ) ); ?>">
						<?php esc_html_e( 'Go to records', 'ceafsn-pp' ); ?>
					</a>
				</div>

			</aside>
		</div>

	<?php elseif ( 'placeholders' === $active_tab ) : ?>

		<form class="ceafsn-form ceafsn-form__spaced" method="post" action="<?php echo esc_url( $form_url ); ?>">
			<?php wp_nonce_field( 'ceafsn_pp_settings_nonce', 'ceafsn_pp_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_pp_save_settings" />

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Known placeholder files', 'ceafsn-pp' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'One file name per line.', 'ceafsn-pp' ); ?></p>
					</div>
					<span class="ceafsn-pill ceafsn-pill--green">
						<?php
						printf(
							/* translators: %s: number of placeholder names. */
							esc_html__( '%s known', 'ceafsn-pp' ),
							esc_html( number_format_i18n( count( $placeholders ) ) )
						);
						?>
					</span>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-field">
						<label for="ceafsn-pp-placeholder-files"><?php esc_html_e( 'File names that must never be published', 'ceafsn-pp' ); ?></label>
						<textarea id="ceafsn-pp-placeholder-files" name="ceafsn_pp_placeholder_files" rows="6"
							class="ceafsn-code"><?php echo esc_textarea( implode( "\n", $placeholders ) ); ?></textarea>
						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'A record can never be published while its PDF matches one of these names unless the document is explicitly confirmed as a known placeholder.', 'ceafsn-pp' ); ?>
						</p>
					</div>

					<div class="ceafsn-alert ceafsn-alert--warn">
						<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
						<div class="ceafsn-alert__body">
							<p class="ceafsn-alert__title"><?php esc_html_e( 'ceafsn.pdf is always blocked', 'ceafsn-pp' ); ?></p>
							<p class="ceafsn-alert__text">
								<?php esc_html_e( 'It is added automatically and cannot be removed from this list.', 'ceafsn-pp' ); ?>
							</p>
						</div>
					</div>

				</div>
			</section>

			<div class="ceafsn-form__actions">
				<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Save settings', 'ceafsn-pp' ); ?></button>
				<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::MENU_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-pp' ); ?></a>
			</div>
		</form>

	<?php elseif ( 'routes' === $active_tab ) : ?>

		<form class="ceafsn-form ceafsn-form__spaced" method="post" action="<?php echo esc_url( $form_url ); ?>">
			<?php wp_nonce_field( 'ceafsn_pp_settings_nonce', 'ceafsn_pp_nonce' ); ?>
			<input type="hidden" name="action" value="ceafsn_pp_save_settings" />

			<section class="ceafsn-card">
				<div class="ceafsn-card__head">
					<div>
						<h2 class="ceafsn-card__title"><?php esc_html_e( 'Legacy route redirect', 'ceafsn-pp' ); ?></h2>
						<p class="ceafsn-card__hint"><?php esc_html_e( 'For visitors who already have an old link or bookmark.', 'ceafsn-pp' ); ?></p>
					</div>
					<span class="ceafsn-pill <?php echo $redirect_on ? 'ceafsn-pill--green' : 'ceafsn-pill--grey'; ?>">
						<?php echo $redirect_on ? esc_html__( 'On', 'ceafsn-pp' ) : esc_html__( 'Off', 'ceafsn-pp' ); ?>
					</span>
				</div>
				<div class="ceafsn-card__body">

					<div class="ceafsn-check">
						<input type="checkbox" id="ceafsn-pp-legacy-redirect" name="ceafsn_pp_legacy_redirect" value="1"
							<?php checked( $redirect_on ); ?>
							aria-describedby="ceafsn-pp-legacy-redirect-help" />
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-pp-legacy-redirect">
								<?php
								printf(
									/* translators: 1: legacy route, 2: current route. */
									esc_html__( 'Permanently redirect %1$s to %2$s', 'ceafsn-pp' ),
									'<code>' . esc_html( CEAFSN_PP_Activator::LEGACY_PATH ) . '</code>',
									'<code>' . esc_html( CEAFSN_PP_Activator::TARGET_PATH ) . '</code>'
								);
								?>
							</label>
							<span class="ceafsn-check__hint" id="ceafsn-pp-legacy-redirect-help">
								<?php esc_html_e( 'Leave this on until every old link has been updated. If a real page is later created at the old address, that page takes precedence and the redirect steps aside.', 'ceafsn-pp' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-alert ceafsn-alert--warn">
						<span class="ceafsn-alert__icon" aria-hidden="true">!</span>
						<div class="ceafsn-alert__body">
							<p class="ceafsn-alert__title"><?php esc_html_e( 'The target page must contain the shortcode', 'ceafsn-pp' ); ?></p>
							<p class="ceafsn-alert__text">
								<?php esc_html_e( 'A page that contains [ceafsn_projects_pubs] is required, or the redirect will land visitors on an empty page.', 'ceafsn-pp' ); ?>
							</p>
						</div>
					</div>

				</div>
			</section>

			<div class="ceafsn-form__actions">
				<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Save settings', 'ceafsn-pp' ); ?></button>
				<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::MENU_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-pp' ); ?></a>
			</div>
		</form>

	<?php elseif ( 'export' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Export records', 'ceafsn-pp' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'One file, every record.', 'ceafsn-pp' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<p>
					<?php esc_html_e( 'Download every record as JSON before making bulk changes or uninstalling the plugin. The export includes members-only records, so it is as sensitive as the records themselves.', 'ceafsn-pp' ); ?>
				</p>

				<p class="ceafsn-alert ceafsn-alert--warn">
					<?php esc_html_e( 'The export is a full copy of your records. Treat it as confidential and delete it once it is safely stored.', 'ceafsn-pp' ); ?>
				</p>

				<a class="ceafsn-btn ceafsn-btn--primary"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_pp_export' ), 'ceafsn_pp_export_nonce', 'ceafsn_pp_nonce' ) ); ?>">
					<?php esc_html_e( 'Download export', 'ceafsn-pp' ); ?>
				</a>

			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-card ceafsn-danger">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-pp' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Deactivating the plugin never deletes anything.', 'ceafsn-pp' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_pp_settings_nonce', 'ceafsn_pp_nonce' ); ?>
					<input type="hidden" name="action" value="ceafsn_pp_save_settings" />

					<div class="ceafsn-check">
						<input type="checkbox" id="ceafsn-pp-uninstall-delete" name="ceafsn_pp_uninstall_delete_data" value="1"
							<?php checked( (bool) $delete_flag ); ?>
							aria-describedby="ceafsn-pp-uninstall-delete-help" />
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-pp-uninstall-delete">
								<?php esc_html_e( 'Delete all records and plugin options when the plugin is deleted', 'ceafsn-pp' ); ?>
							</label>
							<span class="ceafsn-check__hint" id="ceafsn-pp-uninstall-delete-help">
								<?php esc_html_e( 'Tick this only if you intend to delete the plugin and permanently remove the records table.', 'ceafsn-pp' ); ?>
							</span>
						</div>
					</div>

					<ul class="ceafsn-alert__list">
						<li><?php esc_html_e( 'Files in the Media Library are never removed — other pages may still link to them.', 'ceafsn-pp' ); ?></li>
						<li><?php esc_html_e( 'Export your records first. This cannot be undone.', 'ceafsn-pp' ); ?></li>
					</ul>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--danger"><?php esc_html_e( 'Save settings', 'ceafsn-pp' ); ?></button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( admin_url( 'admin.php?page=' . CEAFSN_PP_Admin::MENU_SLUG ) ); ?>"><?php esc_html_e( 'Cancel', 'ceafsn-pp' ); ?></a>
					</div>

				</form>

			</div>
		</section>

	<?php endif; ?>

	</div><!-- #ceafsn-pp-main -->

	<a class="ceafsn-help" href="#ceafsn-pp-help" aria-label="<?php esc_attr_e( 'Jump to help', 'ceafsn-pp' ); ?>">?</a>

</div><!-- .ceafsn-pp-wrap -->