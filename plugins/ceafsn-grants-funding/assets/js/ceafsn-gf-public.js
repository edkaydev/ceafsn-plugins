/**
 * Public scripts for CE-AFSN Grants & Funding.
 *
 * Filtering and sorting are plain links and a GET form, so this file only
 * handles two small conveniences: keeping a long eligibility read in a
 * clamped block, and letting a keyboard user jump from the filter bar
 * straight to the results.
 */
( function () {
	'use strict';

	/**
	 * A result set that would need scrolling is worth a skip link.
	 *
	 * @param {HTMLElement} root Shortcode container.
	 */
	function addSkipLink( root ) {
		var target = root.querySelector( '.ceafsn-gf-cards, .ceafsn-gf-table' );

		if ( ! target || root.querySelector( '.ceafsn-gf-skip' ) ) {
			return;
		}

		if ( target.id === '' ) {
			target.id = 'ceafsn-gf-results';
		}

		var link = document.createElement( 'a' );
		link.className = 'ceafsn-gf-skip screen-reader-text';
		link.href = '#' + target.id;
		link.textContent = root.dataset.skipLabel || 'Skip to results';

		root.insertBefore( link, root.firstChild );
	}

	/**
	 * Truncate long eligibility text, keeping the full copy available.
	 *
	 * The whole text stays in the DOM and in the accessibility tree; only the
	 * visual height is capped, so nothing is hidden from a screen reader.
	 *
	 * @param {HTMLElement} root Shortcode container.
	 */
	function clampEligibility( root ) {
		var blocks = root.querySelectorAll( '.ceafsn-gf-card__eligibility p' );

		Array.prototype.forEach.call( blocks, function ( block ) {
			if ( block.textContent.length < 420 ) {
				return;
			}

			block.classList.add( 'is-clamped' );

			if ( ! block.querySelector( '.ceafsn-gf-expand' ) ) {
				var toggle = document.createElement( 'button' );
				toggle.type = 'button';
				toggle.className = 'ceafsn-gf-expand';
				toggle.setAttribute( 'aria-expanded', 'false' );
				toggle.textContent = block.dataset.expandLabel || 'Read more';

				toggle.addEventListener( 'click', function () {
					var expanded = 'true' === toggle.getAttribute( 'aria-expanded' );

					toggle.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );
					block.classList.toggle( 'is-clamped', expanded );

					if ( expanded ) {
						toggle.textContent = block.dataset.expandLabel || 'Read more';
					} else {
						toggle.textContent = block.dataset.collapseLabel || 'Show less';
					}
				} );

				block.parentNode.appendChild( toggle );
			}
		} );
	}

	/**
	 * Wire up one shortcode instance.
	 *
	 * @param {HTMLElement} root Shortcode container.
	 */
	function init( root ) {
		if ( root.dataset.ceafsnGfReady === 'yes' ) {
			return;
		}

		root.dataset.ceafsnGfReady = 'yes';
		addSkipLink( root );
		clampEligibility( root );
	}

	function initAll() {
		var roots = document.querySelectorAll( '.ceafsn-gf' );

		Array.prototype.forEach.call( roots, init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
}() );
