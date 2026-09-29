<?php
/**
 * Admin partial: settings, export, and uninstall.
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="wrap ceafsn-rf-wrap ceafsn-rf-settings">

	<h1><?php esc_html_e( 'Research Fellowships Settings', 'ceafsn-rf' ); ?></h1>

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-rf' ); ?></p></div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'ceafsn_rf_settings_nonce', 'ceafsn_rf_nonce' ); ?>
		<input type="hidden" name="action" value="ceafsn_rf_save_settings" />

		<h2><?php esc_html_e( 'Public display', 'ceafsn-rf' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Closed opportunities', 'ceafsn-rf' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ceafsn_rf_show_closed" value="1"
								<?php checked( CEAFSN_RF_Public::show_closed_by_default() ); ?> />
							<?php esc_html_e( 'Keep listing opportunities whose closing date has passed', 'ceafsn-rf' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Kept on by default: a closed call still shows its status and its documents, and the Apply button is inactive rather than dead. Untick to show only opportunities that are open or upcoming.', 'ceafsn-rf' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Export', 'ceafsn-rf' ); ?></h2>
		<p>
			<a class="button"
				href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_rf_export' ), 'ceafsn_rf_export_nonce' ) ); ?>">
				<?php esc_html_e( 'Download all opportunities (JSON)', 'ceafsn-rf' ); ?>
			</a>
		</p>
		<p class="description">
			<?php esc_html_e( 'A full backup of the fellowship records, including the text of each description. Keep it somewhere safe before a site migration.', 'ceafsn-rf' ); ?>
		</p>

		<h2><?php esc_html_e( 'Uninstall', 'ceafsn-rf' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Data on uninstall', 'ceafsn-rf' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="ceafsn_rf_uninstall_delete_data" value="1"
								<?php checked( (bool) get_option( 'ceafsn_rf_uninstall_delete_data', false ) ); ?> />
							<?php esc_html_e( 'Permanently delete all fellowship records and settings when the plugin is deleted', 'ceafsn-rf' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Off by default, so removing the plugin never destroys content by surprise. Deactivating the plugin never deletes anything either. Attached PDFs stay in the Media Library either way.', 'ceafsn-rf' ); ?>
						</p>
					</td>
				</tr>
			</tbody>
		</table>

		<?php submit_button(); ?>
	</form>
</div>
