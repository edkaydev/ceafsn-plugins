<?php
/**
 * Admin partial: settings, export, and uninstall.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap ceafsn-gf-wrap ceafsn-gf-settings">

	<h1><?php esc_html_e( 'Grants & Funding Settings', 'ceafsn-gf' ); ?></h1>

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-gf' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ceafsn_gf_settings_nonce', 'ceafsn_gf_nonce' ); ?>
		<input type="hidden" name="action" value="ceafsn_gf_save_settings" />

		<h2><?php esc_html_e( 'Public display', 'ceafsn-gf' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Closed opportunities', 'ceafsn-gf' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ceafsn_gf_show_closed" value="1"
								<?php checked( CEAFSN_GF_Public::show_closed_by_default() ); ?> />
							<?php esc_html_e( 'Keep listing grants whose status is Closed', 'ceafsn-gf' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Kept on by default: a closed grant still shows its status and its documents, and the Apply button is inactive rather than dead. Untick to hide closed grants unless a visitor filters for them.', 'ceafsn-gf' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Export', 'ceafsn-gf' ); ?></h2>
		<p>
			<a class="button"
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_gf_export' ), 'ceafsn_gf_export_nonce' ) ); ?>">
				<?php esc_html_e( 'Download all grants (JSON)', 'ceafsn-gf' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'A full backup of the grant records. Keep it somewhere safe before a site migration.', 'ceafsn-gf' ); ?>
		</p>

		<h2><?php esc_html_e( 'Uninstall', 'ceafsn-gf' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Data on uninstall', 'ceafsn-gf' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ceafsn_gf_uninstall_delete_data" value="1"
								<?php checked( (bool) get_option( 'ceafsn_gf_uninstall_delete_data', false ) ); ?> />
							<?php esc_html_e( 'Permanently delete all grant records and settings when the plugin is deleted', 'ceafsn-gf' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Off by default, so removing the plugin never destroys content by surprise. Deactivating the plugin never deletes anything either. Attached PDFs stay in the Media Library either way.', 'ceafsn-gf' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
