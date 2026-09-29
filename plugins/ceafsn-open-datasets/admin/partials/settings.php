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
$active_tab   = CEAFSN_OD_Request::key( 'tab', 'general' );
$form_url     = admin_url( 'admin-post.php' );
$settings_url = admin_url( 'admin.php?page=' . CEAFSN_OD_Admin::SETTINGS_SLUG );
$notice_saved = CEAFSN_OD_Request::has( 'saved' );
?>

<?php if ( $notice_saved ) : ?>
	<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-od' ); ?></p></div>
<?php endif; ?>

<div class="wrap ceafsn-od-wrap">
	<h1><?php esc_html_e( 'Open Datasets — Settings', 'ceafsn-od' ); ?></h1>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-od' ); ?>">
		<a class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'general', $settings_url ) ); ?>">
			<?php esc_html_e( 'General', 'ceafsn-od' ); ?>
		</a>
		<a class="nav-tab <?php echo 'export' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'export', $settings_url ) ); ?>">
			<?php esc_html_e( 'Export', 'ceafsn-od' ); ?>
		</a>
		<a class="nav-tab <?php echo 'uninstall' === $active_tab ? 'nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( add_query_arg( 'tab', 'uninstall', $settings_url ) ); ?>">
			<?php esc_html_e( 'Uninstall', 'ceafsn-od' ); ?>
		</a>
	</nav>

	<form method="post" action="<?php echo esc_url( $form_url ); ?>">
		<?php wp_nonce_field( 'ceafsn_od_settings_nonce', 'ceafsn_od_nonce' ); ?>
		<input type="hidden" name="action" value="ceafsn_od_save_settings" />

		<?php if ( 'general' === $active_tab ) : ?>
			<h2><?php esc_html_e( 'Privacy and file types', 'ceafsn-od' ); ?></h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Contact visibility', 'ceafsn-od' ); ?></th>
						<td>
							<label for="ceafsn-od-show-contact">
								<input type="checkbox" id="ceafsn-od-show-contact" name="ceafsn_od_show_contact" value="1"
									<?php checked( $show_contact ); ?> />
								<?php esc_html_e( 'Show the contact / owner on the public page', 'ceafsn-od' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. Contact details stay in the database and are only rendered publicly when this is enabled.', 'ceafsn-od' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Other file types', 'ceafsn-od' ); ?></th>
						<td>
							<label for="ceafsn-od-allow-other">
								<input type="checkbox" id="ceafsn-od-allow-other" name="ceafsn_od_allow_other_files" value="1"
									<?php checked( $allow_other ); ?> />
								<?php esc_html_e( 'Allow the "Other" file type in the media picker', 'ceafsn-od' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Off by default. When enabled, files that are not CSV, ZIP, or XLSX can be attached. "Other" files are checked for a non-zero size but their contents are not inspected.', 'ceafsn-od' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		<?php elseif ( 'export' === $active_tab ) : ?>
			<h2><?php esc_html_e( 'Export records', 'ceafsn-od' ); ?></h2>
			<p><?php esc_html_e( 'Download every dataset record as JSON before making bulk changes or uninstalling the plugin.', 'ceafsn-od' ); ?></p>
			<p>
				<a class="button button-primary"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_od_export' ), 'ceafsn_od_export_nonce', 'ceafsn_od_nonce' ) ); ?>">
					<?php esc_html_e( 'Download export', 'ceafsn-od' ); ?>
				</a>
			</p>
		<?php else : ?>
			<h2><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-od' ); ?></h2>
			<p><?php esc_html_e( 'Deactivating the plugin never deletes anything. Tick the box below only if you intend to delete the plugin and permanently remove the dataset table.', 'ceafsn-od' ); ?></p>
			<p>
				<label for="ceafsn-od-uninstall-delete">
					<input type="checkbox" id="ceafsn-od-uninstall-delete" name="ceafsn_od_uninstall_delete_data" value="1"
						<?php checked( (bool) $delete_flag ); ?> />
					<?php esc_html_e( 'Delete all dataset records and plugin options when the plugin is deleted', 'ceafsn-od' ); ?>
				</label>
			</p>
			<p class="description">
				<?php esc_html_e( 'Files in the Media Library are never removed — other pages may still link to them.', 'ceafsn-od' ); ?>
			</p>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'ceafsn-od' ); ?></button>
		</p>
	</form>
</div>
