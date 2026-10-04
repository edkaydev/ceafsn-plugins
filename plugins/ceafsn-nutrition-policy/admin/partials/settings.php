<?php
/**
 * Admin partial: Settings (placeholder file names, export, data removal).
 *
 * Variables in scope (set by CEAFSN_NP_Admin::page_settings()):
 *   @var array<int,string> $placeholders Configured placeholder file names
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

$delete_flag = get_option( 'ceafsn_np_uninstall_delete_data', false );
$placeholders = CEAFSN_NP_Validator::placeholder_filenames();
$active_tab   = sanitize_key( $_GET['tab'] ?? 'display' );
$form_url     = admin_url( 'admin-post.php' );
$settings_url = admin_url( 'admin.php?page=' . CEAFSN_NP_Admin::PAGE_SETTINGS );

// Only the general tab edits settings; the others are read-only or export.
$np_is_general = 'general' === $active_tab;
?>

<div class="wrap ceafsn-np-wrap">

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-np' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['ceafsn_np_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_np_error'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<section class="ceafsn-hero">
		<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · Nutrition Policy', 'ceafsn-np' ); ?></p>
		<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Settings', 'ceafsn-np' ); ?></h1>
		<p class="ceafsn-hero__text">
			<?php esc_html_e( 'Controls which files count as placeholders, how records leave the site, and what happens when the plugin is removed.', 'ceafsn-np' ); ?>
		</p>
	</section>

	<nav class="ceafsn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-np' ); ?>">
		<a class="ceafsn-tabs__tab <?php echo 'display' === $active_tab ? 'ceafsn-tabs__tab--active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'display', $settings_url ) ); ?>"
			<?php echo 'display' === $active_tab ? 'aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Display', 'ceafsn-np' ); ?>
		</a>
		<a class="ceafsn-tabs__tab <?php echo $np_is_general ? 'ceafsn-tabs__tab--active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'general', $settings_url ) ); ?>"
			<?php echo $np_is_general ? 'aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'General', 'ceafsn-np' ); ?>
		</a>
		<a class="ceafsn-tabs__tab <?php echo 'export' === $active_tab ? 'ceafsn-tabs__tab--active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'export', $settings_url ) ); ?>"
			<?php echo 'export' === $active_tab ? 'aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Export', 'ceafsn-np' ); ?>
		</a>
		<a class="ceafsn-tabs__tab <?php echo 'uninstall' === $active_tab ? 'ceafsn-tabs__tab--active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'uninstall', $settings_url ) ); ?>"
			<?php echo 'uninstall' === $active_tab ? 'aria-current="page"' : ''; ?>>
			<?php esc_html_e( 'Uninstall', 'ceafsn-np' ); ?>
		</a>
	</nav>

	<?php if ( 'display' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the policies', 'ceafsn-np' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Policies stay invisible to visitors until this shortcode is on a page and the records themselves are published.', 'ceafsn-np' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<div class="ceafsn-embed">
					<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste this shortcode into the page that should list policies', 'ceafsn-np' ); ?></p>
					<code class="ceafsn-embed__code">[ceafsn_policy_table]</code>
				</div>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'It goes in the page content, in a Code / Preformatted block, or anywhere the block editor accepts HTML.', 'ceafsn-np' ); ?>
				</p>

				<table class="ceafsn-embed__table">
					<caption class="ceafsn-embed__caption"><?php esc_html_e( 'Optional attributes', 'ceafsn-np' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Attribute', 'ceafsn-np' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Default', 'ceafsn-np' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What it does', 'ceafsn-np' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>per_page</code></td>
							<td><code>20</code></td>
							<td><?php esc_html_e( 'Rows per page. Values are clamped between 1 and 100.', 'ceafsn-np' ); ?></td>
						</tr>
						<tr>
							<td><code>topic</code></td>
							<td><code>""</code></td>
							<td>
								<?php esc_html_e( 'Show one topic only. Spell it exactly as it is on the record, for example:', 'ceafsn-np' ); ?>
								<code><?php echo esc_html( 'Maternal Health' ); ?></code>.
								<?php esc_html_e( 'Leave empty for every topic.', 'ceafsn-np' ); ?>
							</td>
						</tr>
					</tbody>
				</table>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'Example: [ceafsn_policy_table per_page="10" topic="Maternal Health"]', 'ceafsn-np' ); ?>
				</p>

				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'Visitors can still narrow the list', 'ceafsn-np' ); ?></p>
						<p class="ceafsn-alert__text">
							<?php esc_html_e( 'Sorting, topic filtering, search, and pagination come from the visitor’s link. That is intentional: a filtered link shows the same results for everyone who opens it, including a visitor who has JS turned off.', 'ceafsn-np' ); ?>
						</p>
					</div>
				</div>

			</div>
		</section>

	<?php else : ?>

	<section class="ceafsn-card<?php echo 'uninstall' === $active_tab ? ' ceafsn-danger' : ''; ?>">
		<?php if ( 'uninstall' === $active_tab ) : ?>
			<div class="ceafsn-card__head">
				<h2 class="ceafsn-card__title"><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-np' ); ?></h2>
			</div>
		<?php endif; ?>
		<div class="ceafsn-card__body">

			<form class="ceafsn-form" method="post" action="<?php echo esc_url( $form_url ); ?>">
				<?php wp_nonce_field( 'ceafsn_np_settings_nonce', 'ceafsn_np_nonce' ); ?>
				<input type="hidden" name="action" value="ceafsn_np_save_settings" />

				<?php if ( $np_is_general ) : ?>

					<div class="ceafsn-field">
						<label for="ceafsn-np-placeholder-files"><?php esc_html_e( 'Known placeholder files', 'ceafsn-np' ); ?></label>
						<textarea id="ceafsn-np-placeholder-files" name="ceafsn_np_placeholder_files" rows="6"
							class="ceafsn-field__mono" aria-describedby="ceafsn-np-placeholder-help"><?php echo esc_textarea( implode( "\n", $placeholders ) ); ?></textarea>
						<p class="ceafsn-field__hint" id="ceafsn-np-placeholder-help">
							<?php esc_html_e( 'One file name per line. A policy record can never be published while its PDF matches one of these names. ceafsn.pdf is always included and cannot be removed from this list.', 'ceafsn-np' ); ?>
						</p>
					</div>

				<?php elseif ( 'export' === $active_tab ) : ?>

					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Export records', 'ceafsn-np' ); ?></h2>
					<p>
						<?php esc_html_e( 'Download every policy record as JSON before making bulk changes or removing the plugin. The export contains record text and attachment IDs; it does not copy the PDF files themselves.', 'ceafsn-np' ); ?>
					</p>
					<p class="ceafsn-form__actions">
						<a class="ceafsn-btn ceafsn-btn--primary"
							href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_np_export' ), 'ceafsn_np_export_nonce', 'ceafsn_np_nonce' ) ); ?>">
							<?php esc_html_e( 'Download export', 'ceafsn-np' ); ?>
						</a>
					</p>

				<?php else : ?>

					<p>
						<?php esc_html_e( 'Deactivating the plugin never deletes anything. Tick the box below only if you intend to delete the plugin and permanently remove the policy table.', 'ceafsn-np' ); ?>
					</p>

					<div class="ceafsn-check">
						<input type="checkbox" id="ceafsn-np-uninstall-delete" name="ceafsn_np_uninstall_delete_data" value="1"
							<?php checked( (bool) $delete_flag ); ?> />
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-np-uninstall-delete">
								<?php esc_html_e( 'Delete all policy records and plugin options when the plugin is deleted', 'ceafsn-np' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Files in the Media Library are never removed — other pages may still link to them.', 'ceafsn-np' ); ?>
							</span>
						</div>
					</div>

				<?php endif; ?>

				<?php if ( $np_is_general || 'uninstall' === $active_tab ) : ?>
					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php esc_html_e( 'Save settings', 'ceafsn-np' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</form>

		</div>
	</section>

	<?php endif; ?>

</div><!-- .ceafsn-np-wrap -->