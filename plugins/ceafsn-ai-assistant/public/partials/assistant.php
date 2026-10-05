<?php
/**
 * Front-end assistant UI partial.
 *
 * Variables available from CEAFSN_AI_Public::render_shortcode():
 *
 * @var string $placeholder_en English placeholder text.
 * @var string $placeholder_pt Portuguese placeholder text.
 * @var string $rest_url        REST endpoint URL.
 * @var string $nonce           wp_rest nonce.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ceafsn-ai-assistant"
	data-rest-url="<?php echo esc_url( $rest_url ); ?>"
	data-nonce="<?php echo esc_attr( $nonce ); ?>"
	data-placeholder-en="<?php echo esc_attr( $placeholder_en ); ?>"
	data-placeholder-pt="<?php echo esc_attr( $placeholder_pt ); ?>"
	role="region"
	aria-label="<?php esc_attr_e( 'CE-AFSN AI Assistant', 'ceafsn-ai' ); ?>">

	<div class="ceafsn-ai-assistant__header">
		<span class="ceafsn-ai-assistant__icon" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="24" height="24">
				<circle cx="12" cy="12" r="10"/>
				<path d="M12 8v4l3 3"/>
				<path d="M8.5 14.5A5 5 0 0 0 12 17a5 5 0 0 0 3.5-2.5"/>
			</svg>
		</span>
		<div>
			<h2 class="ceafsn-ai-assistant__title">
				<?php esc_html_e( 'CE-AFSN Knowledge Assistant', 'ceafsn-ai' ); ?>
			</h2>
			<p class="ceafsn-ai-assistant__subtitle">
				<?php esc_html_e( 'Ask anything about our research, fellowships, grants, datasets and publications — in English or Portuguese.', 'ceafsn-ai' ); ?>
				<br>
				<span lang="pt"><?php esc_html_e( 'Faça perguntas em inglês ou português sobre pesquisa, bolsas, financiamentos e publicações.', 'ceafsn-ai' ); ?></span>
			</p>
		</div>
	</div>

	<form class="ceafsn-ai-assistant__form" novalidate>
		<label for="ceafsn-ai-question" class="screen-reader-text">
			<?php esc_html_e( 'Your question', 'ceafsn-ai' ); ?>
		</label>
		<div class="ceafsn-ai-assistant__input-row">
			<textarea
				id="ceafsn-ai-question"
				class="ceafsn-ai-assistant__input"
				name="question"
				rows="2"
				placeholder="<?php echo esc_attr( $placeholder_en ); ?>"
				maxlength="500"
				aria-required="true"
				aria-describedby="ceafsn-ai-hint"
			></textarea>
			<button type="submit" class="ceafsn-ai-assistant__submit" aria-label="<?php esc_attr_e( 'Ask', 'ceafsn-ai' ); ?>">
				<span class="ceafsn-ai-assistant__submit-label" aria-hidden="true">
					<?php esc_html_e( 'Ask', 'ceafsn-ai' ); ?> &#8594;
				</span>
				<span class="ceafsn-ai-assistant__spinner" aria-hidden="true" hidden></span>
			</button>
		</div>
		<p id="ceafsn-ai-hint" class="ceafsn-ai-assistant__hint">
			<?php esc_html_e( 'Answers are generated from CE-AFSN\'s published content only.', 'ceafsn-ai' ); ?>
		</p>
	</form>

	<div class="ceafsn-ai-assistant__response" aria-live="polite" aria-atomic="true" hidden>
		<div class="ceafsn-ai-assistant__answer"></div>
		<div class="ceafsn-ai-assistant__sources" hidden>
			<p class="ceafsn-ai-assistant__sources-label">
				<?php esc_html_e( 'Sources', 'ceafsn-ai' ); ?>
			</p>
			<ul class="ceafsn-ai-assistant__sources-list"></ul>
		</div>
	</div>

	<div class="ceafsn-ai-assistant__error" role="alert" aria-live="assertive" hidden></div>

</div>
