/**
 * CE-AFSN AI Assistant — public script.
 *
 * Handles the async question/answer cycle:
 *   1. Read the question from the textarea
 *   2. POST to the REST endpoint
 *   3. Render the answer and source links
 *   4. Handle errors gracefully in both EN and PT
 *
 * No dependencies — plain ES2017+ (supported by all browsers since 2017).
 */
( function () {
	'use strict';

	/**
	 * Strings shown to the user when the network itself fails (before the
	 * server even responds). The API may return its own translated error.
	 */
	const NETWORK_ERROR = {
		en: 'A network error occurred. Please check your connection and try again.',
		pt: 'Ocorreu um erro de rede. Verifique a sua ligação e tente novamente.',
	};

	/**
	 * Detect whether a string looks more like Portuguese than English.
	 * Heuristic: check for common PT function words.
	 *
	 * @param {string} text
	 * @returns {boolean}
	 */
	function looksPortuguese( text ) {
		const ptWords = /\b(que|uma?|para|com|por|como|sobre|qual|quais|quando|onde|quem|bolsas|pesquisa|financiamento|publicações|conjuntos|dados)\b/i;
		return ptWords.test( text );
	}

	/**
	 * Escape a string for safe insertion as text content.
	 * We use textContent for most things, but this guards any innerHTML paths.
	 *
	 * @param {string} str
	 * @returns {string}
	 */
	function esc( str ) {
		const d = document.createElement( 'div' );
		d.textContent = str;
		return d.innerHTML;
	}

	/**
	 * Convert plain-text answer lines into minimal HTML.
	 * The server may return newlines; wrap each non-empty paragraph in <p>.
	 *
	 * @param {string} text
	 * @returns {string} Safe HTML.
	 */
	function textToHtml( text ) {
		// The server already passes the answer through wp_kses, so it may
		// contain allowed tags (p, ul, li, strong, em). If it looks like it
		// already has HTML tags, return as-is.
		if ( /<[a-z][\s\S]*>/i.test( text ) ) {
			return text;
		}

		// Plain text: split on double newlines → paragraphs.
		return text
			.split( /\n{2,}/ )
			.map( line => line.trim() )
			.filter( line => line.length > 0 )
			.map( line => '<p>' + esc( line ) + '</p>' )
			.join( '' );
	}

	/**
	 * Initialise one assistant widget.
	 *
	 * @param {HTMLElement} widget
	 */
	function init( widget ) {
		const form        = widget.querySelector( '.ceafsn-ai-assistant__form' );
		const textarea    = widget.querySelector( '.ceafsn-ai-assistant__input' );
		const submitBtn   = widget.querySelector( '.ceafsn-ai-assistant__submit' );
		const submitLabel = widget.querySelector( '.ceafsn-ai-assistant__submit-label' );
		const spinner     = widget.querySelector( '.ceafsn-ai-assistant__spinner' );
		const responseEl  = widget.querySelector( '.ceafsn-ai-assistant__response' );
		const answerEl    = widget.querySelector( '.ceafsn-ai-assistant__answer' );
		const sourcesWrap = widget.querySelector( '.ceafsn-ai-assistant__sources' );
		const sourcesList = widget.querySelector( '.ceafsn-ai-assistant__sources-list' );
		const errorEl     = widget.querySelector( '.ceafsn-ai-assistant__error' );

		const restUrl      = widget.dataset.restUrl  || '';
		const nonce        = widget.dataset.nonce    || '';
		const placeholderEn = widget.dataset.placeholderEn || '';
		const placeholderPt = widget.dataset.placeholderPt || '';

		if ( ! form || ! textarea || ! restUrl ) {
			return;
		}

		// Switch placeholder language as the user types.
		textarea.addEventListener( 'input', function () {
			const pt = looksPortuguese( textarea.value );
			textarea.placeholder = pt ? placeholderPt : placeholderEn;
		} );

		form.addEventListener( 'submit', async function ( e ) {
			e.preventDefault();

			const question = textarea.value.trim();
			if ( ! question ) {
				textarea.focus();
				return;
			}

			// Detect language for fallback error messages.
			const isPt = looksPortuguese( question );

			// --- Loading state ---
			submitBtn.disabled   = true;
			submitLabel.hidden   = true;
			spinner.hidden       = false;
			responseEl.hidden    = true;
			errorEl.hidden       = true;
			answerEl.innerHTML   = '';
			sourcesList.innerHTML = '';
			sourcesWrap.hidden   = true;

			try {
				const response = await fetch( restUrl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce':   nonce,
					},
					body: JSON.stringify( { question } ),
				} );

				const data = await response.json();

				if ( data.error && data.error !== '' ) {
					showError( data.error );
					return;
				}

				// --- Render answer ---
				answerEl.innerHTML = textToHtml( data.answer || '' );
				responseEl.hidden  = false;
				responseEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );

				// --- Render sources ---
				const sources = Array.isArray( data.sources ) ? data.sources : [];
				if ( sources.length > 0 ) {
					sourcesList.innerHTML = sources
						.map( s => {
							const label = esc( s.label || s.url );
							const url   = esc( s.url || '' );
							return url
								? `<li><a href="${url}" target="_blank" rel="noopener noreferrer">${label}</a></li>`
								: `<li>${label}</li>`;
						} )
						.join( '' );
					sourcesWrap.hidden = false;
				}

			} catch ( err ) {
				showError( isPt ? NETWORK_ERROR.pt : NETWORK_ERROR.en );
			} finally {
				submitBtn.disabled = false;
				submitLabel.hidden = false;
				spinner.hidden     = true;
			}
		} );

		/**
		 * Display an error message.
		 *
		 * @param {string} message
		 */
		function showError( message ) {
			errorEl.textContent = message;
			errorEl.hidden      = false;
			errorEl.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}
	}

	// Boot all widgets on the page.
	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.ceafsn-ai-assistant' ).forEach( init );
	} );

} )();
