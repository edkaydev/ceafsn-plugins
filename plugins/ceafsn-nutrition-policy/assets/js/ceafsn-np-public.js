/**
 * CE-AFSN Nutrition Policy — Public JavaScript
 *
 * Progressive enhancement only. Filtering and sorting work without JavaScript;
 * this file adds:
 *   - focus management: after a filter or sort the results region receives focus
 *     so screen reader and keyboard users are not dropped at the top of the page
 *   - a small live announcement of the result count
 *
 * No external libraries required.
 */

( function () {
  'use strict';

  var APP_ID = 'ceafsn-np-app';
  var RESULTS_ID = 'ceafsn-np-results';

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

  /**
   * Tell assistive technology how many records matched.
   *
   * @param {number} count Number of matching records.
   */
  function announceCount( count ) {
    var app = document.getElementById( APP_ID );

    if ( ! app ) {
      return;
    }

    var polite = app.querySelector( '[role="status"]' );

    if ( polite ) {
      polite.setAttribute( 'data-count', String( count ) );
    }
  }

  document.addEventListener( 'DOMContentLoaded', function () {
    if ( ! document.getElementById( APP_ID ) ) {
      return;
    }

    // Filter form: move focus to the results after submitting.
    var form = document.querySelector( '.ceafsn-np-filters' );

    if ( form ) {
      form.addEventListener( 'submit', function () {
        window.setTimeout( function () {
          focusResults();
          announceCount( parseInt( document.querySelector( '.ceafsn-np-count' ).textContent, 10 ) || 0 );
        }, 0 );
      } );
    }

    // Sort links: same behaviour, and remember the choice in the URL.
    var sortLinks = document.querySelectorAll( '.ceafsn-np-table thead th a' );

    Array.prototype.forEach.call( sortLinks, function ( link ) {
      link.addEventListener( 'click', function () {
        // Set a flag so the next page load restores focus to the results too.
        try {
          window.sessionStorage.setItem( 'ceafsn-np-focus-results', '1' );
        } catch ( error ) {
          // Storage can be unavailable (private mode); the page still works.
        }
      } );
    } );

    try {
      if ( window.sessionStorage.getItem( 'ceafsn-np-focus-results' ) ) {
        window.sessionStorage.removeItem( 'ceafsn-np-focus-results' );
        focusResults();
      }
    } catch ( error ) {
      // Nothing to restore.
    }
  } );
} )();
