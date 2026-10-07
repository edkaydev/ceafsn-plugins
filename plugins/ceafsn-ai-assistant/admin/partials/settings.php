<?php
/**
 * Admin settings partial for CE-AFSN AI Assistant.
 *
 * Three tabs, each posting its own `ceafsn_ai_settings_scope` so that saving
 * one tab cannot clear options that belong to another one. Display is
 * documentation only and posts nothing.
 *
 * Variables in scope (set by CEAFSN_AI_Admin::render_settings_page()):
 *   @var string                   $active_key     Active provider key
 *   @var array<string,string>     $providers      Provider keys => labels
 *   @var CEAFSN_AI_Provider|null  $embed_provider Active embeddings provider
 *   @var array<string,string>     $keys_masked    Masked key display values
 *   @var array<string,array{message:string,type:string}> $notices Saved notices
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

$ai_active_tab = sanitize_key( $_GET['tab'] ?? 'display' );
if ( ! array_key_exists( $ai_active_tab, CEAFSN_AI_Admin::SETTINGS_TABS ) ) {
	$ai_active_tab = 'display';
}

$ai_form_url = admin_url( 'admin-post.php' );
$ai_set_url  = admin_url( 'admin.php?page=' . CEAFSN_AI_Admin::PAGE_SETTINGS );

$ai_is_providers = ( 'providers' === $ai_active_tab );
$ai_is_uninstall = ( 'uninstall' === $ai_active_tab );

$ai_defaults = CEAFSN_AI_Providers::DEFAULT_MODELS;

// Saved overrides, kept separate from the defaults so an empty field can mean
// "use whatever the plugin ships with" rather than "no model at all".
$ai_chat_override   = array();
$ai_embed_override  = array();
foreach ( array_keys( $providers ) as $ai_pk ) {
	$ai_chat_override[ $ai_pk ]  = (string) get_option( CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . $ai_pk, '' );
	$ai_embed_override[ $ai_pk ] = (string) get_option( CEAFSN_AI_Providers::OPTION_EMBED_MODEL_PREFIX . $ai_pk, '' );
}

$ai_uninstall_flag = (bool) get_option( 'ceafsn_ai_uninstall_delete_data', false );

$ai_key_prefixes = array(
	'openai' => 'sk-…',
	'gemini' => 'AIza…',
	'grok'   => 'xai-…',
	'claude' => 'sk-ant-…',
);
?>

<div class="wrap ceafsn-ai-wrap">

	<?php if ( ! empty( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ceafsn-ai' ); ?></p></div>
	<?php endif; ?>

	<?php foreach ( $notices as $ai_notice ) : ?>
		<div class="notice notice-<?php echo 'warning' === $ai_notice['type'] ? 'warning' : ( 'error' === $ai_notice['type'] ? 'error' : 'success' ); ?> is-dismissible">
			<p><?php echo esc_html( $ai_notice['message'] ); ?></p>
		</div>
	<?php endforeach; ?>

	<?php if ( null === $embed_provider ) : ?>
		<div class="ceafsn-alerts">
			<div class="ceafsn-alert ceafsn-alert--warn" role="status">
				<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
				<div class="ceafsn-alert__body">
					<p class="ceafsn-alert__text">
						<?php esc_html_e( 'No embeddings provider is configured. The assistant cannot look anything up until an OpenAI or Gemini API key is set below.', 'ceafsn-ai' ); ?>
					</p>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<section class="ceafsn-hero">
		<p class="ceafsn-hero__eyebrow"><?php esc_html_e( 'CE-AFSN · AI Assistant', 'ceafsn-ai' ); ?></p>
		<h1 class="ceafsn-hero__title"><?php esc_html_e( 'Settings', 'ceafsn-ai' ); ?></h1>
		<p class="ceafsn-hero__text">
			<?php esc_html_e( 'Where the assistant appears, which model answers, and what happens to the index when the plugin is removed.', 'ceafsn-ai' ); ?>
		</p>
	</section>

	<nav class="ceafsn-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'ceafsn-ai' ); ?>">
		<?php foreach ( CEAFSN_AI_Admin::SETTINGS_TABS as $ai_tab_key => $ai_tab_label ) : ?>
			<a class="ceafsn-tabs__tab <?php echo $ai_active_tab === $ai_tab_key ? 'ceafsn-tabs__tab--active' : ''; ?>"
				href="<?php echo esc_url( add_query_arg( 'tab', $ai_tab_key, $ai_set_url ) ); ?>"
				<?php echo $ai_active_tab === $ai_tab_key ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $ai_tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php if ( 'display' === $ai_active_tab ) : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Display the assistant', 'ceafsn-ai' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Nothing appears to visitors until this shortcode is on a published page, and the assistant cannot answer until the index has been built.', 'ceafsn-ai' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<div class="ceafsn-embed">
					<p class="ceafsn-embed__label"><?php esc_html_e( 'Paste this shortcode into the page that should offer the assistant', 'ceafsn-ai' ); ?></p>
					<code class="ceafsn-embed__code">[ceafsn_ai_assistant]</code>
				</div>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'It goes in the page content, in a Code / Preformatted block, or anywhere the block editor accepts HTML.', 'ceafsn-ai' ); ?>
				</p>

				<table class="ceafsn-embed__table">
					<caption class="ceafsn-embed__caption"><?php esc_html_e( 'Optional attributes', 'ceafsn-ai' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Attribute', 'ceafsn-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Default', 'ceafsn-ai' ); ?></th>
							<th scope="col"><?php esc_html_e( 'What it does', 'ceafsn-ai' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>placeholder_en</code></td>
							<td><code><?php echo esc_html( __( 'Ask a question about CE-AFSN research, fellowships, grants…', 'ceafsn-ai' ) ); ?></code></td>
							<td><?php esc_html_e( 'Placeholder shown before the visitor has typed anything, while the text still reads as English.', 'ceafsn-ai' ); ?></td>
						</tr>
						<tr>
							<td><code>placeholder_pt</code></td>
							<td><code><?php echo esc_html( __( 'Faça uma pergunta sobre pesquisa, bolsas, financiamentos da CE-AFSN…', 'ceafsn-ai' ) ); ?></code></td>
							<td><?php esc_html_e( 'Placeholder used once the visitor starts typing in Portuguese.', 'ceafsn-ai' ); ?></td>
						</tr>
					</tbody>
				</table>

				<p class="ceafsn-field__hint">
					<?php esc_html_e( 'Example: [ceafsn_ai_assistant placeholder_en="Ask about fellowships" placeholder_pt="Pergunte sobre bolsas"]', 'ceafsn-ai' ); ?>
				</p>

				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'The placeholder switches as the visitor types', 'ceafsn-ai' ); ?></p>
						<p class="ceafsn-alert__text">
							<?php esc_html_e( 'Language is guessed from the words already typed, so the choice is made on the visitor’s machine and no page reload happens. Both attributes should therefore be filled in whenever the site serves readers in the two languages.', 'ceafsn-ai' ); ?>
						</p>
					</div>
				</div>

			</div>
		</section>

	<?php elseif ( 'uninstall' === $ai_active_tab ) : ?>

		<section class="ceafsn-card ceafsn-danger">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Data removal on uninstall', 'ceafsn-ai' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'Deleting the plugin is the only thing that can remove the index, and only when you ask for it here.', 'ceafsn-ai' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $ai_form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_ai_settings' ); ?>
					<input type="hidden" name="action" value="ceafsn_ai_save_settings" />
					<input type="hidden" name="ceafsn_ai_settings_scope" value="uninstall" />

					<p>
						<?php esc_html_e( 'Deactivating the plugin never deletes anything. Tick the box below only if you intend to delete the plugin and permanently remove the knowledge base, the stored API keys, and the model overrides.', 'ceafsn-ai' ); ?>
					</p>

					<div class="ceafsn-check">
						<input type="checkbox" id="ceafsn-ai-uninstall-delete" name="ceafsn_ai_uninstall_delete_data" value="1"
							<?php checked( $ai_uninstall_flag ); ?> />
						<div class="ceafsn-check__body">
							<label class="ceafsn-check__title" for="ceafsn-ai-uninstall-delete">
								<?php esc_html_e( 'Delete the knowledge base, stored API keys, and model overrides when the plugin is deleted', 'ceafsn-ai' ); ?>
							</label>
							<span class="ceafsn-check__hint">
								<?php esc_html_e( 'Off by default, so removing the plugin never destroys a month of indexing — or a key another tool still uses — by surprise. A few bookkeeping options are always removed either way, and your own pages and posts are never touched.', 'ceafsn-ai' ); ?>
							</span>
						</div>
					</div>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php esc_html_e( 'Save settings', 'ceafsn-ai' ); ?>
						</button>
					</div>
				</form>

			</div>
		</section>

	<?php else : ?>

		<section class="ceafsn-card">
			<div class="ceafsn-card__head">
				<div>
					<h2 class="ceafsn-card__title"><?php esc_html_e( 'Providers and models', 'ceafsn-ai' ); ?></h2>
					<p class="ceafsn-card__hint"><?php esc_html_e( 'One provider answers the question, a second one builds the vectors. OpenAI and Gemini do both on a single key.', 'ceafsn-ai' ); ?></p>
				</div>
			</div>
			<div class="ceafsn-card__body">

				<form class="ceafsn-form" method="post" action="<?php echo esc_url( $ai_form_url ); ?>">
					<?php wp_nonce_field( 'ceafsn_ai_settings' ); ?>
					<input type="hidden" name="action" value="ceafsn_ai_save_settings" />
					<input type="hidden" name="ceafsn_ai_settings_scope" value="providers" />

					<div class="ceafsn-field">
						<label for="ceafsn_ai_provider"><?php esc_html_e( 'Active AI provider', 'ceafsn-ai' ); ?></label>
						<select id="ceafsn_ai_provider" name="ceafsn_ai_provider">
							<?php foreach ( $providers as $ai_pk => $ai_plabel ) : ?>
								<option value="<?php echo esc_attr( $ai_pk ); ?>"<?php selected( $active_key, $ai_pk ); ?>>
									<?php echo esc_html( $ai_plabel ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="ceafsn-field__hint">
							<?php esc_html_e( 'Grok and Claude have no embeddings API. When either is selected, vectors are built by OpenAI if a key is set, then Gemini.', 'ceafsn-ai' ); ?>
						</p>
					</div>

					<?php foreach ( $providers as $ai_pk => $ai_plabel ) : ?>
						<?php
						$ai_key_field   = 'ceafsn_ai_key_' . $ai_pk;
						$ai_chat_field  = CEAFSN_AI_Providers::OPTION_MODEL_PREFIX . $ai_pk;
						$ai_embed_field = CEAFSN_AI_Providers::OPTION_EMBED_MODEL_PREFIX . $ai_pk;
						$ai_chat_def    = (string) ( $ai_defaults[ $ai_pk ]['chat'] ?? '' );
						$ai_embed_def   = (string) ( $ai_defaults[ $ai_pk ]['embed'] ?? '' );
						$ai_key_set     = ( '' !== ( $keys_masked[ $ai_pk ] ?? '' ) );
						?>

						<h3>
							<?php echo esc_html( $ai_plabel ); ?>
							<span class="ceafsn-badge <?php echo $ai_key_set ? 'ceafsn-badge--key-set' : 'ceafsn-badge--key-missing'; ?>">
								<?php echo $ai_key_set ? esc_html__( 'Key set', 'ceafsn-ai' ) : esc_html__( 'No key', 'ceafsn-ai' ); ?>
							</span>
						</h3>

						<div class="ceafsn-field">
							<label for="<?php echo esc_attr( $ai_key_field ); ?>"><?php esc_html_e( 'API key', 'ceafsn-ai' ); ?></label>
							<input
								type="password"
								id="<?php echo esc_attr( $ai_key_field ); ?>"
								name="<?php echo esc_attr( $ai_key_field ); ?>"
								value="<?php echo esc_attr( $keys_masked[ $ai_pk ] ?? '' ); ?>"
								autocomplete="new-password"
								placeholder="<?php echo esc_attr( $ai_key_prefixes[ $ai_pk ] ?? '' ); ?>"
							>
							<p class="ceafsn-field__hint">
								<?php esc_html_e( 'Leave blank to keep the key already stored. Enter a new value to replace it.', 'ceafsn-ai' ); ?>
							</p>
						</div>

						<div class="ceafsn-field">
							<label for="<?php echo esc_attr( $ai_chat_field ); ?>"><?php esc_html_e( 'Chat model', 'ceafsn-ai' ); ?></label>
							<input
								type="text"
								id="<?php echo esc_attr( $ai_chat_field ); ?>"
								name="<?php echo esc_attr( $ai_chat_field ); ?>"
								value="<?php echo esc_attr( $ai_chat_override[ $ai_pk ] ); ?>"
								class="ceafsn-field__mono"
								placeholder="<?php echo esc_attr( $ai_chat_def ); ?>"
							>
							<p class="ceafsn-field__hint">
								<?php
								printf(
									/* translators: 1: default chat model identifier, 2: what filling the field does. */
									esc_html__( 'Leave empty to use the built-in default, %s. Fill this in when a vendor retires it and the assistant stops responding.', 'ceafsn-ai' ),
									$ai_chat_def
								);
								?>
							</p>
						</div>

						<?php if ( '' !== $ai_embed_def ) : ?>
							<div class="ceafsn-field">
								<label for="<?php echo esc_attr( $ai_embed_field ); ?>"><?php esc_html_e( 'Embeddings model', 'ceafsn-ai' ); ?></label>
								<input
									type="text"
									id="<?php echo esc_attr( $ai_embed_field ); ?>"
									name="<?php echo esc_attr( $ai_embed_field ); ?>"
									value="<?php echo esc_attr( $ai_embed_override[ $ai_pk ] ); ?>"
									class="ceafsn-field__mono"
									placeholder="<?php echo esc_attr( $ai_embed_def ); ?>"
								>
								<p class="ceafsn-field__hint">
									<?php
									printf(
										/* translators: %s: default embeddings model identifier. */
										esc_html__( 'Leave empty to use the built-in default, %s. Changing this invalidates the existing index — run a full rebuild afterwards.', 'ceafsn-ai' ),
										$ai_embed_def
									);
									?>
								</p>
							</div>
						<?php endif; ?>

					<?php endforeach; ?>

					<div class="ceafsn-form__actions">
						<button type="submit" class="ceafsn-btn ceafsn-btn--primary">
							<?php esc_html_e( 'Save settings', 'ceafsn-ai' ); ?>
						</button>
					</div>
				</form>

				<div class="ceafsn-alert ceafsn-alert--warn">
					<span class="ceafsn-alert__icon" aria-hidden="true">i</span>
					<div class="ceafsn-alert__body">
						<p class="ceafsn-alert__title"><?php esc_html_e( 'Changing the embeddings model empties the index in practice', 'ceafsn-ai' ); ?></p>
						<p class="ceafsn-alert__text">
							<?php esc_html_e( 'Vectors from one model cannot be compared with vectors from another, so the assistant will report a provider mismatch until you run a full rebuild from the Overview.', 'ceafsn-ai' ); ?>
						</p>
					</div>
				</div>

			</div>
		</section>

	<?php endif; ?>

</div><!-- .ceafsn-ai-wrap -->
