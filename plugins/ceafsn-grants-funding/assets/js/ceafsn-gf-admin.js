/**
 * CE-AFSN Grants & Funding — Admin JavaScript
 *
 * Three jobs:
 *   1. Media library picker for the Official Call PDF. The frame is locked to
 *      application/pdf and `multiple` is off, so the picker cannot be used to
 *      attach something that is not a call document.
 *   2. Confirmation before deleting a record, so a stray click cannot remove a
 *      grant listing.
 *   3. Keeping the Clear button's visibility honest: it is shown only while a
 *      file is actually attached, which matters on the edit form where a saved
 *      record arrives with a PDF already chosen.
 *
 * Elements are addressed by ID rather than by walking up to the nearest table
 * cell, so the same markup works whether the picker sits in a form card or in
 * a settings table.
 *
 * No external libraries beyond the jQuery instance WordPress already ships,
 * which wp.media requires.
 */

( function ( $ ) {
	'use strict';

	var ID_FIELD = 'ceafsn-gf-pdf-id';
	var NAME_FIELD = 'ceafsn-gf-pdf-field';
	var BUTTON_ID = 'ceafsn-gf-media-button';
	var CLEAR_ID = 'ceafsn-gf-media-clear';
	var MEDIA_BUTTON_SELECTOR = '.ceafsn-gf-media-button';
	var MEDIA_CLEAR_SELECTOR = '.ceafsn-gf-media-clear';
	var DELETE_SELECTOR = '.ceafsn-gf-delete-link';

	/**
	 * Translate a string, falling back to the source text when the script
	 * translation API is unavailable.
	 *
	 * @param {string} text Source string.
	 * @return {string} Translated string.
	 */
	function t( text ) {
		return window.wp && window.wp.i18n ? window.wp.i18n.__( text, 'ceafsn-gf' ) : text;
	}

	/**
	 * Show the Clear button only while an attachment ID is present.
	 */
	function syncClearButton() {
		$( '#' + CLEAR_ID ).prop( 'hidden', ! $( '#' + ID_FIELD ).val() );
	}

	$( function () {
		var frame = null;

		$( document ).on( 'click', MEDIA_BUTTON_SELECTOR + ', #' + BUTTON_ID, function ( event ) {
			event.preventDefault();

			if ( ! window.wp || ! window.wp.media ) {
				window.alert( t( 'The media library is unavailable.' ) );
				return;
			}

			if ( ! frame ) {
				var cfg = window.ceafsnGfAdmin || {};

				frame = window.wp.media( {
					title: cfg.frameTitle || t( 'Select the Official Call PDF' ),
					button: { text: cfg.frameButton || t( 'Use this document' ) },
					library: { type: cfg.mimeType || 'application/pdf' },
					multiple: false
				} );

				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();

					// The library filter is only a default; a user can still
					// reach other files through the Media modal's own filters,
					// so the type is checked before it is stored.
					if ( 'application/pdf' !== attachment.mime && 'application/pdf' !== attachment.mime_type ) {
						window.alert( t( 'That file is not a PDF. Choose the Official Call PDF.' ) );
						return;
					}

					$( '#' + ID_FIELD ).val( attachment.id );
					// The filename comes from the attachment itself rather than
					// from what the button happens to be labelled.
					$( '#' + NAME_FIELD ).val( attachment.filename || attachment.title || '' );
					$( '#' + NAME_FIELD ).trigger( 'change' );
					syncClearButton();
				} );
			}

			frame.open();
		} );

		$( document ).on( 'click', MEDIA_CLEAR_SELECTOR + ', #' + CLEAR_ID, function ( event ) {
			event.preventDefault();

			$( '#' + ID_FIELD ).val( '' );
			$( '#' + NAME_FIELD ).val( '' );
			$( '#' + NAME_FIELD ).trigger( 'change' );
			syncClearButton();
		} );

		// A record that arrived with a PDF attached must not show a stale
		// Clear button, and a fresh form must not show one at all.
		$( '#' + ID_FIELD ).on( 'change', syncClearButton );

		// Drop any legacy inline handler, then confirm on the delegated click.
		$( document ).find( DELETE_SELECTOR ).each( function () {
			$( this ).removeAttr( 'onclick' );
		} );

		$( document ).on( 'click', DELETE_SELECTOR, function ( event ) {
			if ( ! window.confirm( t( 'Delete this grant record? The call PDF stays in the media library.' ) ) ) {
				event.preventDefault();
			}
		} );

		syncClearButton();
	} );
} )( window.jQuery );