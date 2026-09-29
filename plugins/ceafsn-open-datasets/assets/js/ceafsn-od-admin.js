/**
 * CE-AFSN Open Datasets — Admin JavaScript
 *
 * Two jobs:
 *   1. Media library picker for the dataset file field. The picker filters to
 *      the dataset file types and re-checks the chosen attachment's MIME type
 *      against the type declared in the form, so a mislabelled file is rejected
 *      with a message rather than silently stored.
 *   2. Confirmation before deleting a record.
 *
 * No external libraries beyond the jQuery instance WordPress already ships,
 * which wp.media requires.
 */

( function ( $ ) {
  'use strict';

  var BUTTON_ID = 'ceafsn-od-media-button';
  var ID_FIELD = 'ceafsn-od-file-id';
  var NAME_FIELD = 'ceafsn-od-file-field';
  var CLEAR_ID = 'ceafsn-od-media-clear';
  var TYPE_FIELD = 'ceafsn-od-file-type';

  var ALLOWED_MIMES = {
    csv: [ 'text/csv', 'text/plain', 'application/csv', 'text/comma-separated-values', 'application/vnd.ms-excel' ],
    zip: [ 'application/zip', 'application/x-zip-compressed', 'application/octet-stream', 'multipart/x-zip' ],
    xlsx: [
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/zip',
      'application/octet-stream'
    ]
  };

  var EXTENSION_FALLBACK = {
    csv: [ 'csv' ],
    zip: [ 'zip' ],
    xlsx: [ 'xlsx' ]
  };

  function t( text ) {
    return window.wp && window.wp.i18n ? window.wp.i18n.__( text, 'ceafsn-od' ) : text;
  }

  function selectedType() {
    return $( '#' + TYPE_FIELD ).val() || 'csv';
  }

  function messageFor( type ) {
    if ( 'other' === type ) {
      return t( 'The "Other" file type is not enabled in Settings.' );
    }
    if ( 'csv' === type ) {
      return t( 'That file is not a CSV. Choose a CSV file or switch the file type.' );
    }
    if ( 'zip' === type ) {
      return t( 'That file is not a ZIP archive. Choose a ZIP file or switch the file type.' );
    }
    return t( 'That file is not an XLSX workbook. Choose an XLSX file or switch the file type.' );
  }

  /**
   * Whether an attachment is acceptable for the type declared in the form.
   *
   * The "other" type only reaches this function when an administrator has
   * enabled it — the server omits the option otherwise — so any file is fine.
   */
  function isAcceptable( attachment, type ) {
    if ( 'other' === type ) {
      return true;
    }

    var allowed = ALLOWED_MIMES[ type ] || [];
    var mime = attachment.mime || attachment.mime_type || '';

    if ( allowed.indexOf( mime ) !== -1 ) {
      return true;
    }

    // WordPress sometimes reports a generic or empty MIME for these types, so
    // fall back to the file extension before rejecting.
    var filename = attachment.filename || attachment.title || '';
    var lower = filename.toLowerCase();
    var extensions = EXTENSION_FALLBACK[ type ] || [];

    for ( var i = 0; i < extensions.length; i++ ) {
      if ( lower.slice( -1 * ( extensions[ i ].length + 1 ) ) === '.' + extensions[ i ] ) {
        return true;
      }
    }

    return false;
  }

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
        window.alert( t( 'The media library is unavailable.' ) );
        return;
      }

      if ( ! frame ) {
        frame = window.wp.media( {
          title: t( 'Select the dataset file' ),
          button: { text: t( 'Use this file' ) },
          multiple: false
        } );

        frame.on( 'select', function () {
          var attachment = frame.state().get( 'selection' ).first().toJSON();
          var type = selectedType();

          if ( ! isAcceptable( attachment, type ) ) {
            window.alert( messageFor( type ) );
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

    // Changing the declared type invalidates a selection made for the old type.
    $( document ).on( 'change', '#' + TYPE_FIELD, function () {
      var id = $( '#' + ID_FIELD ).val();
      if ( ! id ) {
        return;
      }
      $( '#' + ID_FIELD ).val( '' );
      $( '#' + NAME_FIELD ).val( '' );
      syncClearButton();
    } );

    // Confirm before deleting.
    $( document ).on( 'click', '.ceafsn-od-delete-link', function ( event ) {
      var message = t( 'Delete this dataset record? The file stays in the media library.' );

      if ( ! window.confirm( message ) ) {
        event.preventDefault();
      }
    } );

    syncClearButton();
  } );
} )( window.jQuery );
