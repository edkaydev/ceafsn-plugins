<?php
/**
 * Admin partial: Settings page (preview mode, export, uninstall).
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

$preview_mode  = get_option( 'ceafsn_med_preview_mode', '0' );
$delete_flag   = get_option( 'ceafsn_med_uninstall_delete_data', false );
$active_tab    = sanitize_key( $_GET['tab'] ?? 'general' );
?>

<?php if ( ! empty( $_GET['saved'] ) ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-med' ); ?></p></div>
<?php endif; ?>

<div class="wrap ceafsn-med-wrap">
	<h1><?php esc_html_e( 'M&E Dashboard — Settings', 'ceafsn-med' ); ?></h1>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings tabs', 'ceafsn-med' ); ?>">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-settings&tab=general' ) ); ?>"
			class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"
			aria-current="<?php echo 'general' === $active_tab ? 'page' : 'false'; ?>">
			<?php esc_html_e( 'General', 'ceafsn-med' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-settings&tab=export' ) ); ?>"
			class="nav-tab <?php echo 'export' === $active_tab ? 'nav-tab-active' : ''; ?>"
			aria-current="<?php echo 'export' === $active_tab ? 'page' : 'false'; ?>">
			<?php esc_html_e( 'Export', 'ceafsn-med' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=ceafsn-med-settings&tab=uninstall' ) ); ?>"
			class="nav-tab <?php echo 'uninstall' === $active_tab ? 'nav-tab-active' : ''; ?>"
			aria-current="<?php echo 'uninstall' === $active_tab ? 'page' : 'false'; ?>">
			<?php esc_html_e( 'Uninstall', 'ceafsn-med' ); ?>
		</a>
	</nav>

	<!-- General tab -->
	<?php if ( 'general' === $active_tab ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ceafsn_med_save_settings">
		<?php wp_nonce_field( 'ceafsn_med_settings_nonce', 'ceafsn_med_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Preview Mode', 'ceafsn-med' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ceafsn_med_preview_mode" value="1"
							<?php checked( '1', $preview_mode ); ?>>
						<?php esc_html_e( 'Enable Preview Mode', 'ceafsn-med' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When enabled, a clearly visible "Preview" badge is shown on the public dashboard. Use this when the site is not yet showing live institutional data.', 'ceafsn-med' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'ceafsn-med' ) ); ?>
	</form>
	<?php endif; ?>

	<!-- Export tab -->
	<?php if ( 'export' === $active_tab ) : ?>
	<h2><?php esc_html_e( 'Export Data', 'ceafsn-med' ); ?></h2>
	<p><?php esc_html_e( 'Download a full JSON export of all metrics, demographic groups, and projects. Save this before making bulk changes or before uninstalling the plugin.', 'ceafsn-med' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ceafsn_med_export">
		<?php wp_nonce_field( 'ceafsn_med_export_nonce', 'ceafsn_med_nonce' ); ?>
		<?php submit_button( __( 'Download JSON Export', 'ceafsn-med' ), 'secondary' ); ?>
	</form>
	<?php endif; ?>

	<!-- Uninstall tab -->
	<?php if ( 'uninstall' === $active_tab ) : ?>
	<h2><?php esc_html_e( 'Uninstall', 'ceafsn-med' ); ?></h2>
	<div class="notice notice-warning inline">
		<p>
			<strong><?php esc_html_e( 'Warning:', 'ceafsn-med' ); ?></strong>
			<?php esc_html_e( 'Enabling the option below means that all plugin data (metrics, demographics, projects) will be permanently deleted when you delete this plugin via Plugins → Delete. This cannot be undone. Export your data first.', 'ceafsn-med' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ceafsn_med_save_settings">
		<?php wp_nonce_field( 'ceafsn_med_settings_nonce', 'ceafsn_med_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Delete Data on Uninstall', 'ceafsn-med' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="ceafsn_med_uninstall_delete_data" value="1"
							<?php checked( true, (bool) $delete_flag ); ?>>
						<?php esc_html_e( 'Permanently delete all plugin data when this plugin is deleted', 'ceafsn-med' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Uninstall Settings', 'ceafsn-med' ), 'secondary' ); ?>
	</form>
	<?php endif; ?>

</div><!-- .ceafsn-med-wrap -->
