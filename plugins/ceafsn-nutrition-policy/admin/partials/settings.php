<?php
/**
 * Admin partial: Settings page (placeholder list, export, uninstall).
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

$delete_flag = get_option( 'ceafsn_np_uninstall_delete_data', false );
$placeholders = CEAFSN_NP_Validator::placeholder_filenames();
$active_tab   = sanitize_key( $_GET['tab'] ?? 'general' );
$form_url     = admin_url( 'admin-post.php' );
$settings_url = admin_url( 'admin.php?page=ceafsn-np-settings' );
?>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-np' ); ?></p></div>
<?php endif; ?>

<div class="wrap ceafsn-np-wrap">
	<h1><?php esc_html_e( 'Nutrition Policy — Settings', 'ceafsn-np' ); ?></h1>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-np' ); ?>">
		<a class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'general', $settings_url ) ); ?>">
			<?php esc_html_e( 'General', 'ceafsn-np' ); ?>
		</a>
		<a class="nav-tab <?php echo 'export' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'export', $settings_url ) ); ?>">
			<?php esc_html_e( 'Export', 'ceafsn-np' ); ?>
		</a>
		<a class="nav-tab <?php echo 'uninstall' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'uninstall', $settings_url ) ); ?>">
			<?php esc_html_e( 'Uninstall', 'ceafsn-np' ); ?>
		</a>
	</nav>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<?php wp_nonce_field( 'ceafsn_np_settings_nonce', 'ceafsn_np_nonce' ); ?>
		<input type="hidden" name="action" value="ceafsn_np_save_settings" />

		<?php if ( 'general' === $active_tab ) : ?>
			<h2><?php esc_html_e( 'Known placeholder files', 'ceafsn-np' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'One file name per line. A policy record can never be published while its PDF matches one of these names.', 'ceafsn-np' ); ?>
			</p>
			<textarea name="ceafsn_np_placeholder_files" rows="6" class="large-text code"
				aria-describedby="ceafsn-np-placeholder-help"><?php echo esc_textarea( implode( "\n", $placeholders ) ); ?></textarea>
			<p id="ceafsn-np-placeholder-help" class="description">
				<?php esc_html_e( 'ceafsn.pdf is always included and cannot be removed from this list.', 'ceafsn-np' ); ?>
			</p>
		<?php elseif ( 'export' === $active_tab ) : ?>
			<h2><?php esc_html_e( 'Export records', 'ceafsn-np' ); ?></h2>
			<p><?php esc_html_e( 'Download every policy record as JSON before making bulk changes or uninstalling the plugin.', 'ceafsn-np' ); ?></p>
			<p>
				<a class="button button-primary"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_np_export' ), 'ceafsn_np_export_nonce', 'ceafsn_np_nonce' ) ); ?>">
					<?php esc_html_e( 'Download export', 'ceafsn-np' ); ?>
				</a>
			</p>
		<?php else : ?>
			<h2><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-np' ); ?></h2>
			<p><?php esc_html_e( 'Deactivating the plugin never deletes anything. Tick the box below only if you intend to delete the plugin and permanently remove the policy table.', 'ceafsn-np' ); ?></p>
			<p>
				<label for="ceafsn-np-uninstall-delete">
					<input type="checkbox" id="ceafsn-np-uninstall-delete" name="ceafsn_np_uninstall_delete_data" value="1"
						<?php checked( (bool) $delete_flag ); ?> />
					<?php esc_html_e( 'Delete all policy records and plugin options when the plugin is deleted', 'ceafsn-np' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'Files in the Media Library are never removed — other pages may still link to them.', 'ceafsn-np' ); ?>
			</p>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'ceafsn-np' ); ?></button>
		</p>
	</form>
</div>
