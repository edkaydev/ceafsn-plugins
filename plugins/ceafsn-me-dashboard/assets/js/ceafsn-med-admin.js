/**
 * CE-AFSN M&E Dashboard — Admin JavaScript
 *
 * Handles confirm-on-delete for rows with class .ceafsn-delete-link.
 * Native confirm() is already in the inline onclick; this file provides
 * a progressive enhancement that avoids duplicate confirm prompts by
 * removing the inline onclick and replacing it with an event listener.
 *
 * No external libraries required.
 */

/* global jQuery */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		const deleteLinks = document.querySelectorAll( '.ceafsn-delete-link' );

		deleteLinks.forEach( function ( link ) {
			// Remove the inline onclick attribute set in the PHP partial
			// so we don't fire two confirms.
			link.removeAttribute( 'onclick' );

			link.addEventListener( 'click', function ( event ) {
				const message =
					link.dataset.confirmMessage ||
					'Are you sure you want to delete this item? This cannot be undone.';

				if ( ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
}() );
