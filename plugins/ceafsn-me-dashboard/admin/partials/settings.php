<?php
/**
 * Admin partial: Settings page (preview mode, export, uninstall).
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

$preview_mode = get_option( 'ceafsn_med_preview_mode', '0' );
$delete_flag  = get_option( 'ceafsn_med_uninstall_delete_data', false );
$active_tab   = sanitize_key( $_GET['tab'] ?? 'display' );

$med_settings_url = static function ( string $tab ): string {
	return admin_url( 'admin.php?page=' . CEAFSN_MED_Admin::PAGE_SETTINGS . '&tab=' . $tab );
};

$med_tabs = array(
	'display'   => __( 'Display', 'ceafsn-med' ),
	'general'   => __( 'General', 'ceafsn-med' ),
	'export'    => __( 'Export', 'ceafsn-med' ),
	'uninstall' => __( 'Uninstall', 'ceafsn-med' ),
);
?>

<div class="wrap ceafsn-med-wrap">

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-med' ); ?></p></div>
	<?php endif; ?>

	<section class="ceafsn-hero">
		<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'M&E Dashboard', 'ceafsn-med' ); ?></p>
		<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Settings', 'ceafsn-med' ); ?></h1>
		<p class="ceafsn-hero__text">
			<?php esc_html_e( 'Preview mode, data export, and what happens to your records if the plugin is removed.', 'ceafsn-med' ); ?>
		</p>
	</section>

	<nav class="ceafsn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-med' ); ?>">
		<?php foreach ( $med_tabs as $med_tab_key => $med_tab_label ) : ?>
			<a class="ceafsn-tabs__tab<?php echo $med_tab_key === $active_tab ? ' ceafsn-tabs__tab--active' : ''; ?>"
				href="<?php echo esc_url( $med_settings_url( $med_tab_key ) ); ?>"
				<?php echo $med_tab_key === $active_tab ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $med_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'display' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the dashboard', 'ceafsn-med' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'The dashboard only appears where this shortcode is placed.', 'ceafsn-med' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<div class="ceafsn-embed">
					<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste this shortcode into the page that should show the dashboard', 'ceafsn-med' ); ?></p>
					<code class="ceafsn-embed__code">[ceafsn_me_dashboard]</code>
				</div>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'It goes in the page content, in a Code / Preformatted block, or anywhere the block editor accepts HTML.', 'ceafsn-med' ); ?>
				</p>

				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'This shortcode takes no attributes', 'ceafsn-med' ); ?></p>
						<p class="ceafsn-alert__text">
							<?php esc_html_e( 'The dashboard decides for itself what to show from the published records. Anything written inside the shortcode brackets is ignored, so paste it exactly as shown.', 'ceafsn-med' ); ?>
						</p>
					</div>
				</div>

				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'A page with the shortcode can still look empty', 'ceafsn-med' ); ?></p>
						<p class="ceafsn-alert__text">
							<?php esc_html_e( 'The dashboard renders nothing until a record is published, so check that the records are published before sending the page link out.', 'ceafsn-med' ); ?>
						</p>
					</div>
				</div>

			</div>
		</section>

	<?php elseif ( 'general' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Public display', 'ceafsn-med' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'How the dashboard presents itself to visitors.', 'ceafsn-med' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">
				<form class="ceafsn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ceafsn_med_save_settings">
					<?php wp_nonce_field( 'ceafsn_med_settings_nonce', 'ceafsn_med_nonce' ); ?>

					<div class="ceafsn-check">
						<input type="checkbox" name="ceafsn_med_preview_mode" value="1" id="ceafsn-med-preview" <?php checked( '1', $preview_mode ); ?>>
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-med-preview">
								<?php esc_html_e( 'Enable Preview Mode', 'ceafsn-med' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'When enabled, a clearly visible "Preview" badge is shown on the public dashboard. Use this when the site is not yet showing live institutional data.', 'ceafsn-med' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Save settings', 'ceafsn-med' ); ?></button>
					</div>
				</form>
			</div>
		</section>

	<?php elseif ( 'export' === $active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Export data', 'ceafsn-med' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'A complete copy of everything this plugin stores.', 'ceafsn-med' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">
				<p class="ceafsn-field__hint ceafsn-lead">
					<?php esc_html_e( 'Download a full JSON export of all metrics, demographic groups, and projects. Save this before making bulk changes or before uninstalling the plugin.', 'ceafsn-med' ); ?>
				</p>
				<form class="ceafsn-form__spaced" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ceafsn_med_export">
					<?php wp_nonce_field( 'ceafsn_med_export_nonce', 'ceafsn_med_nonce' ); ?>
					<button type="submit" class="ceafsn-btn ceafsn-btn--primary"><?php esc_html_e( 'Download JSON export', 'ceafsn-med' ); ?></button>
				</form>
			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-card ceafsn-danger">
			<div class="ceafsn-card__head">
				<h2 class="ceafsn-card__title"><?php esc_html_e( 'Uninstall', 'ceafsn-med' ); ?></h2>
			</div>
			<div class="ceafsn-card__body">
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'Warning:', 'ceafsn-med' ); ?></strong>
						<?php esc_html_e( 'Enabling the option below means that all plugin data (metrics, demographics, projects) will be permanently deleted when you delete this plugin via Plugins → Delete. This cannot be undone. Export your data first.', 'ceafsn-med' ); ?>
					</p>
				</div>

				<form class="ceafsn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ceafsn_med_save_settings">
					<?php wp_nonce_field( 'ceafsn_med_settings_nonce', 'ceafsn_med_nonce' ); ?>

					<div class="ceafsn-check">
						<input type="checkbox" name="ceafsn_med_uninstall_delete_data" value="1" id="ceafsn-med-delete-data" <?php checked( true, (bool) $delete_flag ); ?>>
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-med-delete-data">
								<?php esc_html_e( 'Permanently delete all plugin data when this plugin is deleted', 'ceafsn-med' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Leave this off to keep your records if the plugin is ever removed or reinstalled.', 'ceafsn-med' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--danger"><?php esc_html_e( 'Save uninstall settings', 'ceafsn-med' ); ?></button>
						<a class="ceafsn-btn ceafsn-btn--quiet" href="<?php echo esc_url( $med_settings_url( 'export' ) ); ?>">
							<?php esc_html_e( 'Export first', 'ceafsn-med' ); ?>
						</a>
					</div>
				</form>
			</div>
		</section>

	<?php endif; ?>

</div><!-- .ceafsn-med-wrap -->