<?php
/**
 * Floating launcher and panel for the site-wide AI assistant.
 *
 * Variables available from CEAFSN_AI_Public::render_floater():
 *
 * @var string $assistant_html Pre-rendered assistant widget markup.
 * @var string $position       Bottom corner: right or left.
 * @var string $open_label     Accessible label for the launcher when closed.
 * @var string $close_label    Accessible label for the launcher when open.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ceafsn-ai-floater"
	data-position="<?php echo esc_attr( $position ); ?>"
	data-open="false"
	data-label-open="<?php echo esc_attr( $open_label ); ?>"
	data-label-close="<?php echo esc_attr( $close_label ); ?>">

	<div id="ceafsn-ai-floater-panel"
		class="ceafsn-ai-floater__panel"
		role="dialog"
		aria-modal="false"
		aria-label="<?php esc_attr_e( 'CE-AFSN AI Assistant', 'ceafsn-ai' ); ?>"
		hidden>

		<button type="button" class="ceafsn-ai-floater__close" aria-label="<?php echo esc_attr( $close_label ); ?>">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true">
				<path d="M18 6 6 18"/>
				<path d="m6 6 12 12"/>
			</svg>
		</button>

		<div class="ceafsn-ai-floater__panel-body">
			<?php echo $assistant_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-rendered, already escaped by the partial. ?>
		</div>
	</div>

	<button type="button"
		class="ceafsn-ai-floater__launcher"
		aria-expanded="false"
		aria-controls="ceafsn-ai-floater-panel"
		aria-label="<?php echo esc_attr( $open_label ); ?>">
		<span class="ceafsn-ai-floater__icon ceafsn-ai-floater__icon--open" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="28" height="28">
				<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
			</svg>
		</span>
		<span class="ceafsn-ai-floater__icon ceafsn-ai-floater__icon--close" aria-hidden="true">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="28" height="28">
				<path d="M18 6 6 18"/>
				<path d="m6 6 12 12"/>
			</svg>
		</span>
	</button>
</div>
