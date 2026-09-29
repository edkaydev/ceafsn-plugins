<?php
/**
 * Admin partial: Settings page (placeholders, legacy redirect, export, uninstall).
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

$delete_flag   = get_option( 'ceafsn_pp_uninstall_delete_data', false );
$placeholders  = CEAFSN_PP_Validator::placeholder_filenames();
$redirect_on   = CEAFSN_PP_Activator::redirect_enabled();
$active_tab    = sanitize_key( $_GET['tab'] ?? 'general' );
$form_url      = admin_url( 'admin-post.php' );
$settings_url  = admin_url( 'admin.php?page=ceafsn-pp-settings' );
?>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-pp' ); ?></p></div>
<?php endif; ?>

<div class="wrap ceafsn-pp-wrap">
	<h1><?php esc_html_e( 'Projects & Publications — Settings', 'ceafsn-pp' ); ?></h1>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-pp' ); ?>">
		<a class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'general', $settings_url ) ); ?>">
			<?php esc_html_e( 'General', 'ceafsn-pp' ); ?>
		</a>
		<a class="nav-tab <?php echo 'routes' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'routes', $settings_url ) ); ?>">
			<?php esc_html_e( 'Routes', 'ceafsn-pp' ); ?>
		</a>
		<a class="nav-tab <?php echo 'export' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'export', $settings_url ) ); ?>">
			<?php esc_html_e( 'Export', 'ceafsn-pp' ); ?>
		</a>
		<a class="nav-tab <?php echo 'uninstall' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'uninstall', $settings_url ) ); ?>">
			<?php esc_html_e( 'Uninstall', 'ceafsn-pp' ); ?>
		</a>
	</nav>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<?php wp_nonce_field( 'ceafsn_pp_settings_nonce', 'ceafsn_pp_nonce' ); ?>
		<input type="hidden" name="action" value="ceafsn_pp_save_settings" />

		<?php if ( 'general' === $active_tab ) : ?>
			<h2><?php esc_html_e( 'Known placeholder files', 'ceafsn-pp' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'One file name per line. A record can never be published while its PDF matches one of these names unless the document is explicitly confirmed.', 'ceafsn-pp' ); ?>
			</p>
			<textarea name="ceafsn_pp_placeholder_files" rows="6" class="large-text code"
				aria-describedby="ceafsn-pp-placeholder-help"><?php echo esc_textarea( implode( "\n", $placeholders ) ); ?></textarea>
			<p id="ceafsn-pp-placeholder-help" class="description">
				<?php esc_html_e( 'ceafsn.pdf is always included and cannot be removed from this list.', 'ceafsn-pp' ); ?>
			</p>
		<?php elseif ( 'routes' === $active_tab ) : ?>
			<h2><?php esc_html_e( 'Legacy route redirect', 'ceafsn-pp' ); ?></h2>
			<p>
				<label for="ceafsn-pp-legacy-redirect">
					<input type="checkbox" id="ceafsn-pp-legacy-redirect" name="ceafsn_pp_legacy_redirect" value="1"
						<?php checked( $redirect_on ); ?> />
					<?php
					printf(
						/* translators: 1: legacy path, 2: new path. */
						esc_html__( 'Permanently redirect %1$s to %2$s', 'ceafsn-pp' ),
						'<code>' . esc_html( CEAFSN_PP_Activator::LEGACY_PATH ) . '</code>',
						'<code>' . esc_html( CEAFSN_PP_Activator::TARGET_PATH ) . '</code>'
					);
					?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'Leave this on until every old link has been updated. If a real page is later created at the old address, that page takes precedence and the redirect steps aside.', 'ceafsn-pp' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'The target page must exist and contain the [ceafsn_projects_pubs] shortcode, or the redirect will land visitors on a 404.', 'ceafsn-pp' ); ?>
			</p>
		<?php elseif ( 'export' === $active_tab ) : ?>
			<h2><?php esc_html_e( 'Export records', 'ceafsn-pp' ); ?></h2>
			<p><?php esc_html_e( 'Download every record as JSON before making bulk changes or uninstalling the plugin. The export includes members-only records, so it is as sensitive as the records themselves.', 'ceafsn-pp' ); ?></p>
			<p>
				<a class="button button-primary"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_pp_export' ), 'ceafsn_pp_export_nonce', 'ceafsn_pp_nonce' ) ); ?>">
					<?php esc_html_e( 'Download export', 'ceafsn-pp' ); ?>
				</a>
			</p>
		<?php else : ?>
			<h2><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-pp' ); ?></h2>
			<p><?php esc_html_e( 'Deactivating the plugin never deletes anything. Tick the box below only if you intend to delete the plugin and permanently remove the records table.', 'ceafsn-pp' ); ?></p>
			<p>
				<label for="ceafsn-pp-uninstall-delete">
					<input type="checkbox" id="ceafsn-pp-uninstall-delete" name="ceafsn_pp_uninstall_delete_data" value="1"
						<?php checked( (bool) $delete_flag ); ?> />
					<?php esc_html_e( 'Delete all records and plugin options when the plugin is deleted', 'ceafsn-pp' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'Files in the Media Library are never removed — other pages may still link to them.', 'ceafsn-pp' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'Export your records first. This cannot be undone.', 'ceafsn-pp' ); ?>
			</p>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'ceafsn-pp' ); ?></button>
		</p>
	</form>
</div>
