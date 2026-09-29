/**
 * CE-AFSN Nutrition Policy — Admin JavaScript
 *
 * Two jobs:
 *   1. Media library picker for the PDF attachment field. The picker is locked
 *      to application/pdf, and a non-PDF selection is rejected with a message
 *      rather than silently stored.
 *   2. Confirmation before deleting a record. The inline onclick in the PHP
 *      partial is removed and replaced by a single delegated listener.
 *
 * No external libraries beyond the jQuery instance WordPress already ships,
 * which wp.media requires.
 */

( function ( $ ) {
  'use strict';

  var BUTTON_ID = 'ceafsn-np-media-button';
  var ID_FIELD = 'ceafsn-np-pdf-id';
  var NAME_FIELD = 'ceafsn-np-pdf-field';
  var CLEAR_ID = 'ceafsn-np-media-clear';
  var EMPTY_LABEL = wp.i18n ? wp.i18n.__( 'No file selected', 'ceafsn-np' ) : 'No file selected';
  var WRONG_TYPE = wp.i18n
    ? wp.i18n.__( 'That file is not a PDF. Choose a PDF document.', 'ceafsn-np' )
    : 'That file is not a PDF. Choose a PDF document.';

  /**
   * Toggle the "Clear" button based on whether a file is selected.
   */
  function syncClearButton() {
    var id = $( '#' + ID_FIELD ).val();
    $( '#' + CLEAR_ID ).prop( 'hidden', ! id );
  }

  $( function () {
    var frame;

    $( document ).on( 'click', '#' + BUTTON_ID, function ( event ) {
      event.preventDefault();

      if ( ! window.wp || ! window.wp.media ) {
        window.alert( wp.i18n ? wp.i18n.__( 'The media library is unavailable.', 'ceafsn-np' ) : 'The media library is unavailable.' );
        return;
      }

      if ( ! frame ) {
        frame = window.wp.media( {
          title: wp.i18n ? wp.i18n.__( 'Select the policy PDF', 'ceafsn-np' ) : 'Select the policy PDF',
          button: { text: wp.i18n ? wp.i18n.__( 'Use this PDF', 'ceafsn-np' ) : 'Use this PDF' },
          library: { type: 'application/pdf' },
          multiple: false
        } );

        frame.on( 'select', function () {
          var attachment = frame.state().get( 'selection' ).first().toJSON();
          var isPdf = 'application/pdf' === attachment.mime || 'application/pdf' === attachment.mime_type;

          if ( ! isPdf ) {
            window.alert( WRONG_TYPE );
            return;
          }

          $( '#' + ID_FIELD ).val( attachment.id );
          $( '#' + NAME_FIELD ).val( attachment.filename || '' );
          syncClearButton();
        } );
      }

      frame.open();
    } );

    $( document ).on( 'click', '#' + CLEAR_ID, function ( event ) {
      event.preventDefault();
      $( '#' + ID_FIELD ).val( '' );
      $( '#' + NAME_FIELD ).val( '' );
      syncClearButton();
    } );

    // Confirm before deleting: remove the inline handler, add a delegated one.
    $( document ).find( '.ceafsn-np-delete-link' ).each( function () {
      $( this ).removeAttr( 'onclick' );
    } );

    $( document ).on( 'click', '.ceafsn-np-delete-link', function ( event ) {
      var message = wp.i18n
        ? wp.i18n.__( 'Delete this policy record? The PDF stays in the media library.', 'ceafsn-np' )
        : 'Delete this policy record? The PDF stays in the media library.';

      if ( ! window.confirm( message ) ) {
        event.preventDefault();
      }
    } );

    syncClearButton();
  } );
} )( window.jQuery );
