/**
 * CE-AFSN Open Datasets — Public JavaScript
 *
 * Progressive enhancement only. Filtering and sorting work without JavaScript;
 * this file adds focus management so screen reader and keyboard users are not
 * dropped at the top of the page after a filter or sort.
 *
 * There is deliberately no client-side check on download links. The target is
 * validated server-side when the record is saved, and a link cannot be
 * intercepted before the browser follows it without delaying every download
 * behind a network round trip. A file that disappears after publishing is
 * reported honestly by the server on the next page load.
 *
 * No external libraries required.
 */

( function () {
  'use strict';

  var APP_ID = 'ceafsn-od-app';
  var RESULTS_ID = 'ceafsn-od-results';
  var FOCUS_KEY = 'ceafsn-od-focus-results';

  /**
   * Move focus to the results region without scrolling past the page heading.
   */
  function focusResults() {
    var results = document.getElementById( RESULTS_ID );

    if ( ! results ) {
      return;
    }

    // preventScroll keeps the viewport where the user left it; the browser
    // default jump to the top of the document is disorienting after filtering.
    if ( 'function' === typeof results.focus ) {
      try {
        results.focus( { preventScroll: true } );
        results.scrollIntoView( { block: 'nearest', behavior: 'smooth' } );
      } catch ( error ) {
        results.focus();
      }
    }
  }

  document.addEventListener( 'DOMContentLoaded', function () {
    if ( ! document.getElementById( APP_ID ) ) {
      return;
    }

    var form = document.querySelector( '.ceafsn-od-filters' );

    if ( form ) {
      form.addEventListener( 'submit', function () {
        window.setTimeout( focusResults, 0 );
      } );
    }

    var sortLinks = document.querySelectorAll( '.ceafsn-od-table thead th a' );

    Array.prototype.forEach.call( sortLinks, function ( link ) {
      link.addEventListener( 'click', function () {
        // Set a flag so the next page load restores focus to the results too.
        try {
          window.sessionStorage.setItem( FOCUS_KEY, '1' );
        } catch ( error ) {
          // Storage can be unavailable (private mode); the page still works.
        }
      } );
    } );

    try {
      if ( window.sessionStorage.getItem( FOCUS_KEY ) ) {
        window.sessionStorage.removeItem( FOCUS_KEY );
        focusResults();
      }
    } catch ( error ) {
      // Nothing to restore.
    }
  } );
} )();
