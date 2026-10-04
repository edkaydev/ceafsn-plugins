<?php
/**
 * Admin partial: settings (Display, Export, Uninstall).
 *
 * Each editable tab posts its own form, and each form declares which settings
 * it owns with a `ceafsn_rf_settings_scope` hidden field. The handler only
 * writes the options named by that scope, so saving one tab cannot silently
 * reset the checkbox on another.
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

$delete_flag   = (bool) get_option( 'ceafsn_rf_uninstall_delete_data', false );
$active_tab    = sanitize_key( $_GET['tab'] ?? 'display' );
$form_url      = admin_url( 'admin-post.php' );
$settings_url  = admin_url( 'admin.php?page=' . CEAFSN_RF_Admin::SETTINGS_SLUG );
$list_url      = admin_url( 'admin.php?page=' . CEAFSN_RF_Admin::MENU_SLUG );
$rf_is_export  = 'export' === $active_tab;
$rf_is_uninst  = 'uninstall' === $active_tab;
$show_closed   = CEAFSN_RF_Public::show_closed_by_default();
?>

<div class="wrap ceafsn-rf-wrap">

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-rf' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['ceafsn_rf_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_rf_error'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<section class="ceafsn-hero">
		<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · Research Fellowships', 'ceafsn-rf' ); ?></p>
		<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Settings', 'ceafsn-rf' ); ?></h1>
		<p class="ceafsn-hero__text">
			<?php esc_html_e( 'Controls how the public list behaves, how records leave the site, and what happens when the plugin is removed.', 'ceafsn-rf' ); ?>
		</p>
	</section>

	<nav class="ceafsn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-rf' ); ?>">
		<a class="ceafsn-tabs__tab <?php echo 'display' === $active_tab ? 'ceafsn-tabs__tab--active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'display', $settings_url ) ); ?>"
			<?php echo 'display' === $active_tab ? 'aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Display', 'ceafsn-rf' ); ?>
		</a>
		<a class="ceafsn-tabs__tab <?php echo $rf_is_export ? 'ceafsn-tabs__tab--active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'export', $settings_url ) ); ?>"
			<?php echo $rf_is_export ? 'aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Export', 'ceafsn-rf' ); ?>
		</a>
		<a class="ceafsn-tabs__tab <?php echo $rf_is_uninst ? 'ceafsn-tabs__tab--active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'uninstall', $settings_url ) ); ?>"
			<?php echo $rf_is_uninst ? 'aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Uninstall', 'ceafsn-rf' ); ?>
		</a>
	</nav>

	<?php if ( 'display' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the opportunities', 'ceafsn-rf' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Opportunities stay invisible to visitors until this shortcode is on a page and the records themselves are published.', 'ceafsn-rf' ); ?></p>
				</div>
				<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $list_url ); ?>">
					<?php esc_html_e( 'All opportunities', 'ceafsn-rf' ); ?>
				</a>
			</div>
			<div class="ceafsn-card__body">

				<div class="ceafsn-embed">
					<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste this shortcode into the page that should list opportunities', 'ceafsn-rf' ); ?></p>
					<code class="ceafsn-embed__code">[ceafsn_fellowships]</code>
				</div>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'It goes in the page content, in a Code / Preformatted block, or anywhere the block editor accepts HTML.', 'ceafsn-rf' ); ?>
				</p>

				<table class="ceafsn-embed__table">
					<caption class="ceafsn-embed__caption"><?php esc_html_e( 'Optional attributes', 'ceafsn-rf' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Attribute', 'ceafsn-rf' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Default', 'ceafsn-rf' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What it does', 'ceafsn-rf' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>per_page</code></td>
							<td><code>12</code></td>
							<td><?php esc_html_e( 'Cards per page. Values are clamped between 1 and 48.', 'ceafsn-rf' ); ?></td>
						</tr>
						<tr>
							<td><code>track_domain</code></td>
							<td><code>""</code></td>
							<td>
								<?php esc_html_e( 'Show one research track only. Must match the record exactly, for example:', 'ceafsn-rf' ); ?>
								<code><?php echo esc_html( 'Food Systems' ); ?></code>.
								<?php esc_html_e( 'Leave empty for every track.', 'ceafsn-rf' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>status</code></td>
							<td><code>""</code></td>
							<td>
								<?php esc_html_e( 'Show one status only. Use:', 'ceafsn-rf' ); ?>
								<code><?php echo esc_html( 'open' ); ?></code>,
								<code><?php echo esc_html( 'upcoming' ); ?></code>,
								<code><?php echo esc_html( 'closed' ); ?></code>,
								<code><?php echo esc_html( 'archived' ); ?></code>,
								<code><?php echo esc_html( 'unconfirmed' ); ?></code>.
								<?php esc_html_e( 'Leave empty for every status.', 'ceafsn-rf' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>view</code></td>
							<td><code>cards</code></td>
							<td>
								<?php esc_html_e( 'Use', 'ceafsn-rf' ); ?>
								<code><?php echo esc_html( 'cards' ); ?></code>
								<?php esc_html_e( 'for the card layout, or', 'ceafsn-rf' ); ?>
								<code><?php echo esc_html( 'table' ); ?></code>
								<?php esc_html_e( 'for the compact table. Anything else falls back to cards.', 'ceafsn-rf' ); ?>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'Example: [ceafsn_fellowships per_page="6" view="table"]', 'ceafsn-rf' ); ?>
				</p>

				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'The shown status is never stored', 'ceafsn-rf' ); ?></p>
						<p class="ceafsn-alert__text">
							<?php esc_html_e( 'A visitor’s status filter only ever matches opportunities whose dates currently work out that way. The status attribute narrows the list; it cannot mark a closed call as open.', 'ceafsn-rf' ); ?>
						</p>
					</div>
				</div>

			</div>
		</section>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Closed opportunities', 'ceafsn-rf' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'What the public list does with a call whose closing date has passed.', 'ceafsn-rf' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">
				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_rf_settings_nonce', 'ceafsn_rf_nonce' ); ?>
					<input type="hidden" name="action" value="ceafsn_rf_save_settings" />
					<input type="hidden" name="ceafsn_rf_settings_scope" value="display" />

					<div class="ceafsn-check">
						<input type="checkbox" id="ceafsn-rf-show-closed" name="ceafsn_rf_show_closed" value="1"
							<?php checked( $show_closed ); ?> />
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-rf-show-closed">
								<?php esc_html_e( 'Keep listing opportunities whose closing date has passed', 'ceafsn-rf' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'On by default: a closed call still shows its status and its documents, and the Apply button is inactive rather than dead. Untick to show only opportunities that are open or upcoming.', 'ceafsn-rf' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php esc_html_e( 'Save settings', 'ceafsn-rf' ); ?>
						</button>
					</div>
				</form>
			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-card<?php echo $rf_is_uninst ? ' ceafsn-danger' : ''; ?>">
			<?php if ( $rf_is_uninst ) : ?>
				<div class="ceafsn-card__head">
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-rf' ); ?></h2>
				</div>
			<?php endif; ?>
			<div class="ceafsn-card__body">

				<?php if ( $rf_is_export ) : ?>

					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Export records', 'ceafsn-rf' ); ?></h2>
					<p>
						<?php esc_html_e( 'Download every opportunity as JSON before making bulk changes or removing the plugin. The export contains record text and attachment IDs; it does not copy the PDF files themselves.', 'ceafsn-rf' ); ?>
					</p>
					<p class="ceafsn-form__actions">
						<a class="ceafsn-btn ceafsn-btn--primary"
							href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_rf_export' ), 'ceafsn_rf_export_nonce', 'ceafsn_rf_nonce' ) ); ?>">
							<?php esc_html_e( 'Download export', 'ceafsn-rf' ); ?>
						</a>
					</p>

				<?php else : ?>

					<p>
						<?php esc_html_e( 'Deactivating the plugin never deletes anything. Tick the box below only if you intend to delete the plugin and permanently remove the fellowship table.', 'ceafsn-rf' ); ?>
					</p>

					<form class="ceafsn-form" method="post" action="<?php echo esc_url( $form_url ); ?>">
						<?php wp_nonce_field( 'ceafsn_rf_settings_nonce', 'ceafsn_rf_nonce' ); ?>
						<input type="hidden" name="action" value="ceafsn_rf_save_settings" />
						<input type="hidden" name="ceafsn_rf_settings_scope" value="uninstall" />

						<div class="ceafsn-check">
							<input type="checkbox" id="ceafsn-rf-uninstall-delete" name="ceafsn_rf_uninstall_delete_data" value="1"
								<?php checked( $delete_flag ); ?> />
							<div class="ceafsn-check__body">
								<label class="ceafsn-check__title" for="ceafsn-rf-uninstall-delete">
									<?php esc_html_e( 'Delete all opportunity records and plugin options when the plugin is deleted', 'ceafsn-rf' ); ?>
								</label>
								<span class="ceafsn-check__hint">
									<?php esc_html_e( 'Attached PDFs stay in the Media Library either way — other pages may still link to them.', 'ceafsn-rf' ); ?>
								</span>
							</div>
						</div>

						<div class="ceafsn-form__actions">
							<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
								<?php esc_html_e( 'Save settings', 'ceafsn-rf' ); ?>
							</button>
						</div>
					</form>

				<?php endif; ?>

			</div>
		</section>

	<?php endif; ?>

</div><!-- .ceafsn-rf-wrap -->