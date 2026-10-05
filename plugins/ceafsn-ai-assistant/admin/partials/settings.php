<?php
/**
 * Admin settings page partial for CE-AFSN AI Assistant.
 *
 * Variables available from CEAFSN_AI_Admin::render_settings_page():
 *
 * @var string                             $active_key     Active provider key.
 * @var array<string,string>               $providers      All provider keys => labels.
 * @var int                                $chunk_count    Total indexed chunks.
 * @var int                                $embedded_count Chunks with embeddings.
 * @var string                             $last_indexed   ISO datetime or ''.
 * @var CEAFSN_AI_Provider|null            $embed_provider Active embeddings provider.
 * @var array<string,string>               $keys_masked    Masked key display values.
 * @var array<string,string>               $notices        Admin notices.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

$no_embed = ( null === $embed_provider );
$action   = admin_url( 'admin-post.php' );
?>
<div class="wrap ceafsn-ai-admin">
	<h1><?php esc_html_e( 'CE-AFSN AI Assistant', 'ceafsn-ai' ); ?></h1>

	<?php foreach ( $notices as $notice_msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice_msg ); ?></p></div>
	<?php endforeach; ?>

	<?php if ( $no_embed ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'No embeddings provider is configured. The AI assistant cannot answer questions until you add an OpenAI or Gemini API key below.', 'ceafsn-ai' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<!-- ===== Settings ===== -->
	<h2><?php esc_html_e( 'Settings', 'ceafsn-ai' ); ?></h2>
	<form method="post" action="<?php echo esc_url( $action ); ?>">
		<input type="hidden" name="action" value="ceafsn_ai_save_settings">
		<?php wp_nonce_field( 'ceafsn_ai_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="ceafsn_ai_provider"><?php esc_html_e( 'Active AI provider', 'ceafsn-ai' ); ?></label>
				</th>
				<td>
					<select id="ceafsn_ai_provider" name="ceafsn_ai_provider">
						<?php foreach ( $providers as $pk => $plabel ) : ?>
							<option value="<?php echo esc_attr( $pk ); ?>"<?php selected( $active_key, $pk ); ?>>
								<?php echo esc_html( $plabel ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Grok and Claude do not have an embeddings API. When selected, embeddings fall back to OpenAI (if a key is set), then Gemini.', 'ceafsn-ai' ); ?>
					</p>
				</td>
			</tr>

			<?php
			$key_labels = array(
				'openai' => 'OpenAI API key (sk-…)',
				'gemini' => 'Google Gemini API key (AIza…)',
				'grok'   => 'xAI Grok API key (xai-…)',
				'claude' => 'Anthropic Claude API key (sk-ant-…)',
			);
			foreach ( $providers as $pk => $plabel ) :
				$field_id = 'ceafsn_ai_key_' . $pk;
			?>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( $field_id ); ?>">
						<?php echo esc_html( $key_labels[ $pk ] ?? $plabel . ' API key' ); ?>
					</label>
				</th>
				<td>
					<input
						type="password"
						id="<?php echo esc_attr( $field_id ); ?>"
						name="<?php echo esc_attr( $field_id ); ?>"
						value="<?php echo esc_attr( $keys_masked[ $pk ] ?? '' ); ?>"
						class="regular-text"
						autocomplete="new-password"
						placeholder="<?php esc_attr_e( 'Paste API key here', 'ceafsn-ai' ); ?>"
					>
					<?php if ( '' !== ( $keys_masked[ $pk ] ?? '' ) ) : ?>
						<span class="ceafsn-ai-key-set">&#10003; <?php esc_html_e( 'Key is set', 'ceafsn-ai' ); ?></span>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Leave blank to remove. Only enter a new value to change it.', 'ceafsn-ai' ); ?>
					</p>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<?php submit_button( __( 'Save settings', 'ceafsn-ai' ) ); ?>
	</form>

	<hr>

	<!-- ===== Index status ===== -->
	<h2><?php esc_html_e( 'Knowledge Base', 'ceafsn-ai' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Total chunks', 'ceafsn-ai' ); ?></th>
			<td><?php echo esc_html( number_format_i18n( $chunk_count ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Chunks with embeddings', 'ceafsn-ai' ); ?></th>
			<td><?php echo esc_html( number_format_i18n( $embedded_count ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Last indexed', 'ceafsn-ai' ); ?></th>
			<td>
				<?php
				if ( '' !== $last_indexed ) {
					echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_indexed ) ) );
				} else {
					esc_html_e( 'Never', 'ceafsn-ai' );
				}
				?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Embeddings via', 'ceafsn-ai' ); ?></th>
			<td>
				<?php
				echo $embed_provider
					? esc_html( $embed_provider->name() )
					: '<span style="color:#b32d2e">' . esc_html__( 'Not configured', 'ceafsn-ai' ) . '</span>';
				?>
			</td>
		</tr>
	</table>

	<!-- Run index -->
	<form method="post" action="<?php echo esc_url( $action ); ?>" style="margin-top:1em">
		<input type="hidden" name="action" value="ceafsn_ai_run_index">
		<?php wp_nonce_field( 'ceafsn_ai_index' ); ?>
		<label>
			<input type="checkbox" name="ceafsn_ai_full_rebuild" value="1">
			<?php esc_html_e( 'Full rebuild (delete existing index first)', 'ceafsn-ai' ); ?>
		</label>
		<br><br>
		<?php
		submit_button(
			__( 'Run indexing now', 'ceafsn-ai' ),
			$no_embed ? 'secondary' : 'primary',
			'ceafsn_ai_index_submit',
			false,
			$no_embed ? array( 'disabled' => 'disabled' ) : array()
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Reads all published CE-AFSN records plus WordPress pages/posts, splits them into chunks, and generates embedding vectors via the API. This may take a minute on the first run.', 'ceafsn-ai' ); ?>
		</p>
	</form>

	<!-- Clear index -->
	<form method="post" action="<?php echo esc_url( $action ); ?>" style="margin-top:0.5em"
		  onsubmit="return confirm('<?php esc_attr_e( 'Delete the entire AI index? This cannot be undone.', 'ceafsn-ai' ); ?>')">
		<input type="hidden" name="action" value="ceafsn_ai_clear_index">
		<?php wp_nonce_field( 'ceafsn_ai_clear' ); ?>
		<?php submit_button( __( 'Clear index', 'ceafsn-ai' ), 'delete', 'ceafsn_ai_clear_submit', false ); ?>
	</form>
</div>
