/**
 * CE-AFSN Projects & Publications — Admin JavaScript
 *
 * Two jobs:
 *   1. Media library pickers for the cover image and the PDF. Each picker is
 *      locked to its own media type, and a wrong-type selection is rejected
 *      with a message rather than silently stored.
 *   2. Confirmation before deleting a record.
 *
 * No external libraries beyond the jQuery instance WordPress already ships,
 * which wp.media requires.
 */

( function ( $ ) {
  'use strict';

  var IMAGE_BUTTON = 'ceafsn-pp-cover-button';
  var IMAGE_CLEAR = 'ceafsn-pp-cover-clear';
  var IMAGE_ID_FIELD = 'ceafsn-pp-cover-id';
  var IMAGE_NAME_FIELD = 'ceafsn-pp-cover-field';

  var PDF_BUTTON = 'ceafsn-pp-pdf-button';
  var PDF_CLEAR = 'ceafsn-pp-pdf-clear';
  var PDF_ID_FIELD = 'ceafsn-pp-pdf-id';
  var PDF_NAME_FIELD = 'ceafsn-pp-pdf-field';

  var frames = {};

  /**
   * Translate a string when wp.i18n is available.
   *
   * @param {string} text Original text.
   * @return {string} Translated text.
   */
  function t( text ) {
    return window.wp && window.wp.i18n ? window.wp.i18n.__( text, 'ceafsn-pp' ) : text;
  }

  /**
   * Show or hide a Clear button based on whether its field holds a value.
   *
   * @param {string} idField   ID of the hidden value field.
   * @param {string} clearId   ID of the Clear button.
   */
  function syncClearButton( idField, clearId ) {
    $( '#' + clearId ).prop( 'hidden', ! $( '#' + idField ).val() );
  }

  /**
   * Open a media frame, creating it once per picker.
   *
   * @param {Object} config wp.media configuration.
   * @param {string} key    Cache key.
   * @param {Function} onSelect Called with the selected attachment.
   */
  function openFrame( config, key, onSelect ) {
    if ( ! window.wp || ! window.wp.media ) {
      window.alert( t( 'The media library is unavailable.' ) );
      return;
    }

    if ( ! frames[ key ] ) {
      frames[ key ] = window.wp.media( config );

      frames[ key ].on( 'select', function () {
        onSelect( frames[ key ].state().get( 'selection' ).first().toJSON() );
      } );
    }

    frames[ key ].open();
  }

  $( function () {
    $( document ).on( 'click', '#' + IMAGE_BUTTON, function ( event ) {
      event.preventDefault();

      openFrame(
        {
          title: t( 'Select the cover image' ),
          button: { text: t( 'Use this image' ) },
          library: { type: 'image' },
          multiple: false
        },
        'image',
        function ( attachment ) {
          if ( 'image' !== attachment.mime && 'image/jpeg' !== attachment.mime ) {
            window.alert( t( 'That file is not an image.' ) );
            return;
          }

          $( '#' + IMAGE_ID_FIELD ).val( attachment.id );
          $( '#' + IMAGE_NAME_FIELD ).val( attachment.alt || attachment.filename || '' );
          syncClearButton( IMAGE_ID_FIELD, IMAGE_CLEAR );
        }
      );
    } );

    $( document ).on( 'click', '#' + IMAGE_CLEAR, function ( event ) {
      event.preventDefault();
      $( '#' + IMAGE_ID_FIELD ).val( '' );
      $( '#' + IMAGE_NAME_FIELD ).val( '' );
      syncClearButton( IMAGE_ID_FIELD, IMAGE_CLEAR );
    } );

    $( document ).on( 'click', '#' + PDF_BUTTON, function ( event ) {
      event.preventDefault();

      openFrame(
        {
          title: t( 'Select the document PDF' ),
          button: { text: t( 'Use this PDF' ) },
          library: { type: 'application/pdf' },
          multiple: false
        },
        'pdf',
        function ( attachment ) {
          var isPdf =
            'application/pdf' === attachment.mime || 'application/pdf' === attachment.mime_type;

          if ( ! isPdf ) {
            window.alert( t( 'That file is not a PDF. Choose a PDF document.' ) );
            return;
          }

          $( '#' + PDF_ID_FIELD ).val( attachment.id );
          $( '#' + PDF_NAME_FIELD ).val( attachment.filename || '' );
          syncClearButton( PDF_ID_FIELD, PDF_CLEAR );
        }
      );
    } );

    $( document ).on( 'click', '#' + PDF_CLEAR, function ( event ) {
      event.preventDefault();
      $( '#' + PDF_ID_FIELD ).val( '' );
      $( '#' + PDF_NAME_FIELD ).val( '' );
      syncClearButton( PDF_ID_FIELD, PDF_CLEAR );
    } );

    // Confirm before deleting: strip any inline handler, add a delegated one.
    $( document ).find( '.ceafsn-pp-delete-link' ).each( function () {
      $( this ).removeAttr( 'onclick' );
    } );

    $( document ).on( 'click', '.ceafsn-pp-delete-link', function ( event ) {
      if ( ! window.confirm( t( 'Delete this record? The PDF stays in the media library.' ) ) ) {
        event.preventDefault();
      }
    } );

    syncClearButton( IMAGE_ID_FIELD, IMAGE_CLEAR );
    syncClearButton( PDF_ID_FIELD, PDF_CLEAR );
  } );
} )( window.jQuery );
