/**
 * Admin scripts for CE-AFSN Research Fellowships.
 *
 * One Media Library frame, opened from the Select PDF button. The frame's
 * library filter is set to PDFs and `multiple` is off, so the button cannot be
 * used to attach something that is not a call document.
 */
( function ( $ ) {
	'use strict';

	var frame = null;

	/**
	 * Read the localized strings and the attachment type.
	 *
	 * @return {Object|null} Config, or null when the strings are missing.
	 */
	function config() {
		if ( ! window.ceafsnRfAdmin ) {
			return null;
		}

		return window.ceafsnRfAdmin;
	}

	/**
	 * Open (or reuse) the media frame.
	 *
	 * @param {jQuery} $button Button that was clicked.
	 */
	function openFrame( $button ) {
		var cfg = config();

		if ( ! cfg ) {
			return;
		}

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: cfg.frameTitle,
			button: { text: cfg.frameButton },
			library: { type: cfg.mimeType },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var $input = $( '#' + $button.data( 'target' ) );
			var $field = $input.closest( 'td' ).find( 'input[type="text"]' );
			var $clear = $input.closest( 'td' ).find( '.ceafsn-rf-media-clear' );

			$input.val( attachment.id );
			$field.val( attachment.filename || attachment.title || ( '#' + attachment.id ) );
			$field.trigger( 'change' );
			$clear.prop( 'hidden', false );
		} );

		frame.open();
	}

	$( document ).on( 'click', '.ceafsn-rf-media-button', function ( event ) {
		event.preventDefault();
		openFrame( $( this ) );
	} );

	$( document ).on( 'click', '.ceafsn-rf-media-clear', function ( event ) {
		event.preventDefault();

		var $clear = $( this );
		var $cell = $clear.closest( 'td' );

		$cell.find( 'input[type="hidden"]' ).val( '' );
		$cell.find( 'input[type="text"]' ).val( '' );
		$clear.prop( 'hidden', true );
	} );
}( window.jQuery ) );
