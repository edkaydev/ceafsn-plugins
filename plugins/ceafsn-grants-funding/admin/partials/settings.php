<?php
/**
 * Admin partial: settings (public display, visibility defaults, export, data removal).
 *
 * Each editable tab posts its own `ceafsn_gf_settings_scope` so that saving one
 * tab cannot clear the checkboxes that belong to another one.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;

$gf_delete_flag = get_option( 'ceafsn_gf_uninstall_delete_data', false );
$gf_active_tab = sanitize_key( $_GET['tab'] ?? 'display' );
$gf_form_url   = admin_url( 'admin-post.php' );
$gf_set_url    = admin_url( 'admin.php?page=' . CEAFSN_GF_Admin::PAGE_SETTINGS );

// Only the General and Uninstall tabs have anything to save; the rest are
// read-only instructions.
$gf_tabs = array(
	'display'   => __( 'Display', 'ceafsn-gf' ),
	'general'   => __( 'General', 'ceafsn-gf' ),
	'export'    => __( 'Export', 'ceafsn-gf' ),
	'uninstall' => __( 'Uninstall', 'ceafsn-gf' ),
);

if ( ! isset( $gf_tabs[ $gf_active_tab ] ) ) {
	$gf_active_tab = 'display';
}

$gf_is_general   = 'general' === $gf_active_tab;
$gf_is_uninstall = 'uninstall' === $gf_active_tab;
?>

<div class="wrap ceafsn-gf-wrap">

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-gf' ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $_GET['ceafsn_gf_error'] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['ceafsn_gf_error'] ) ) ) ); ?></p>
		</div>
	<?php endif; ?>

	<section class="ceafsn-hero">
		<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · Grants & Funding', 'ceafsn-gf' ); ?></p>
		<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Settings', 'ceafsn-gf' ); ?></h1>
		<p class="ceafsn-hero__text">
			<?php esc_html_e( 'How grants leave the site, which statuses stay listed, and what happens to the records when the plugin is removed.', 'ceafsn-gf' ); ?>
		</p>
	</section>

	<nav class="ceafsn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-gf' ); ?>">
		<?php foreach ( $gf_tabs as $gf_tab_key => $gf_tab_label ) : ?>
			<a class="ceafsn-tabs__tab <?php echo $gf_active_tab === $gf_tab_key ? 'ceafsn-tabs__tab--active' : ''; ?>"
				href="<?php echo esc_url( add_query_arg( 'tab', $gf_tab_key, $gf_set_url ) ); ?>"
				<?php echo $gf_active_tab === $gf_tab_key ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $gf_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'display' === $gf_active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the grants', 'ceafsn-gf' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Grants stay invisible to visitors until this shortcode is on a page and the records themselves are published.', 'ceafsn-gf' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<div class="ceafsn-embed">
					<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste this shortcode into the page that should list grants', 'ceafsn-gf' ); ?></p>
					<code class="ceafsn-embed__code">[ceafsn_grants]</code>
				</div>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'It goes in the page content, in a Code / Preformatted block, or anywhere the block editor accepts HTML.', 'ceafsn-gf' ); ?>
				</p>

				<table class="ceafsn-embed__table">
					<caption class="ceafsn-embed__caption"><?php esc_html_e( 'Optional attributes', 'ceafsn-gf' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Attribute', 'ceafsn-gf' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Default', 'ceafsn-gf' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What it does', 'ceafsn-gf' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>per_page</code></td>
							<td><code>12</code></td>
							<td><?php esc_html_e( 'Grants per page. Values are clamped between 1 and 48.', 'ceafsn-gf' ); ?></td>
						</tr>
						<tr>
							<td><code>funding_institution</code></td>
							<td><code>""</code></td>
							<td><?php esc_html_e( 'Show one funder only. Spell it exactly as it is on the record. Leave empty for every funder.', 'ceafsn-gf' ); ?></td>
						</tr>
						<tr>
							<td><code>status</code></td>
							<td><code>""</code></td>
							<td>
								<?php esc_html_e( 'Show one opportunity status only:', 'ceafsn-gf' ); ?>
								<code><?php echo esc_html( implode( '</code>, <code>', CEAFSN_GF_DB::grant_statuses() ) ); ?></code>.
								<?php esc_html_e( 'Leave empty for every status.', 'ceafsn-gf' ); ?>
							</td>
						</tr>
						<tr>
							<td><code>view</code></td>
							<td><code>""</code></td>
							<td><?php esc_html_e( 'Start the list as cards or as a table. Empty uses the visitor’s choice, or cards.', 'ceafsn-gf' ); ?></td>
						</tr>
					</tbody>
				</table>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'Example: [ceafsn_grants per_page="24" status="open" view="table"]', 'ceafsn-gf' ); ?>
				</p>

				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'A visitor’s link wins over the shortcode', 'ceafsn-gf' ); ?></p>
						<p class="ceafsn-alert__text">
							<?php esc_html_e( 'Filtering, search, sorting, view switching, and pagination come from the URL. That is intentional: a shared link shows the same results for everyone who opens it, including a visitor who has JS turned off.', 'ceafsn-gf' ); ?>
						</p>
					</div>
				</div>

			</div>
		</section>

	<?php elseif ( 'export' === $gf_active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Export records', 'ceafsn-gf' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'A full backup before a bulk edit, a site migration, or removing the plugin.', 'ceafsn-gf' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<p>
					<?php esc_html_e( 'Download every grant record as JSON. The file contains record text and attachment IDs; it does not copy the PDF files themselves, which stay in the Media Library.', 'ceafsn-gf' ); ?>
				</p>

				<p class="ceafsn-form__actions">
					<a class="ceafsn-btn ceafsn-btn--primary"
						href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ceafsn_gf_export' ), 'ceafsn_gf_export_nonce', 'ceafsn_gf_nonce' ) ); ?>">
						<?php esc_html_e( 'Download all grants (JSON)', 'ceafsn-gf' ); ?>
					</a>
				</p>

			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-card<?php echo $gf_is_uninstall ? ' ceafsn-danger' : ''; ?>">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title">
						<?php
						echo esc_html(
							$gf_is_uninstall
								? __( 'Data removal on uninstall', 'ceafsn-gf' )
								: __( 'Public visibility defaults', 'ceafsn-gf' )
						);
						?>
					</h2>
					<p class="ceafsn-card__hint">
						<?php
						echo esc_html(
							$gf_is_uninstall
								? __( 'Deleting the plugin is the only thing that can remove records, and only when you ask for it here.', 'ceafsn-gf' )
								: __( 'Which statuses stay in the public list when a visitor has not asked for anything specific.', 'ceafsn-gf' )
						);
						?>
					</p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $gf_form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_gf_settings_nonce', 'ceafsn_gf_nonce' ); ?>
					<input type="hidden" name="action" value="ceafsn_gf_save_settings" />
					<input type="hidden" name="ceafsn_gf_settings_scope"
						value="<?php echo esc_attr( $gf_is_general ? 'general' : 'uninstall' ); ?>" />

					<?php if ( $gf_is_general ) : ?>

						<div class="ceafsn-check">
							<input type="checkbox" id="ceafsn-gf-show-closed" name="ceafsn_gf_show_closed" value="1"
								<?php checked( CEAFSN_GF_Public::show_closed_by_default() ); ?> />
							<div class="ceafsn-check__body">
								<label class="ceafsn-check__title" for="ceafsn-gf-show-closed">
									<?php esc_html_e( 'Keep listing grants whose status is Closed', 'ceafsn-gf' ); ?>
								</label>
								<span class="ceafsn-check__hint">
									<?php esc_html_e( 'On by default. A closed grant still shows its status and its documents, and its Apply button is inactive rather than dead. Unticking hides closed grants unless a visitor filters for them explicitly.', 'ceafsn-gf' ); ?>
								</span>
							</div>
						</div>

						<div class="ceafsn-alert ceafsn-alert--warn">
							<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
							<div class="ceafsn-alert__body">
								<p class="ceafsn-alert__title"><?php esc_html_e( 'Nothing here is inferred from the calendar', 'ceafsn-gf' ); ?></p>
								<p class="ceafsn-alert__text">
									<?php esc_html_e( 'A passed deadline never closes a grant on its own, and it never hides one either. The status field is yours to set, so a visitor is never told a grant is open when you have not said so.', 'ceafsn-gf' ); ?>
								</p>
							</div>
						</div>

					<?php else : ?>

						<p>
							<?php esc_html_e( 'Deactivating the plugin never deletes anything. Tick the box below only if you intend to delete the plugin and permanently remove every grant record and setting.', 'ceafsn-gf' ); ?>
						</p>

						<div class="ceafsn-check">
							<input type="checkbox" id="ceafsn-gf-uninstall-delete" name="ceafsn_gf_uninstall_delete_data" value="1"
								<?php checked( (bool) $gf_delete_flag ); ?> />
							<div class="ceafsn-check__body">
								<label class="ceafsn-check__title" for="ceafsn-gf-uninstall-delete">
									<?php esc_html_e( 'Delete all grant records and plugin options when the plugin is deleted', 'ceafsn-gf' ); ?>
								</label>
								<span class="ceafsn-check__hint">
									<?php esc_html_e( 'Off by default, so removing the plugin never destroys content by surprise. Attached PDFs stay in the Media Library either way — other pages may still link to them.', 'ceafsn-gf' ); ?>
								</span>
							</div>
						</div>

					<?php endif; ?>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php esc_html_e( 'Save settings', 'ceafsn-gf' ); ?>
						</button>
					</div>
				</form>

			</div>
		</section>

	<?php endif; ?>

</div><!-- .ceafsn-gf-wrap -->