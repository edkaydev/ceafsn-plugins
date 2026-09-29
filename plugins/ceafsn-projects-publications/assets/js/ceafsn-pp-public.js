/**
 * CE-AFSN Projects & Publications — Public JavaScript
 *
 * Progressive enhancement only. Filtering, sorting, and the view toggle all
 * work without JavaScript because each control is a real link or form. This
 * file adds:
 *   - focus management: after a filter, sort, or view change the results region
 *     receives focus so screen reader and keyboard users are not dropped at the
 *     top of the page
 *   - keeping the current view when the form is submitted
 *
 * No external libraries required.
 */

( function () {
  'use strict';

  var APP_ID = 'ceafsn-pp-app';
  var RESULTS_ID = 'ceafsn-pp-results';
  var FOCUS_KEY = 'ceafsn-pp-focus-results';
  var VIEW_FIELD = 'pp_view';

  /**
   * Move focus to the results region without jumping past the page heading.
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
   * Remember that a reload should restore focus to the results.
   */
  function flagFocus() {
    try {
      window.sessionStorage.setItem( FOCUS_KEY, '1' );
    } catch ( error ) {
      // Storage can be unavailable (private mode); the page still works.
    }
  }

  /**
   * Restore focus if a previous interaction asked for it.
   */
  function restoreFocus() {
    try {
      if ( window.sessionStorage.getItem( FOCUS_KEY ) ) {
        window.sessionStorage.removeItem( FOCUS_KEY );
        focusResults();
      }
    } catch ( error ) {
      // Nothing to restore.
    }
  }

  document.addEventListener( 'DOMContentLoaded', function () {
    if ( ! document.getElementById( APP_ID ) ) {
      return;
    }

    // The filter form carries a hidden view field, so submitting filters does
    // not silently snap the layout back to the default grid.
    var form = document.querySelector( '.ceafsn-pp-filters' );

    if ( form ) {
      var viewField = form.querySelector( 'input[name="' + VIEW_FIELD + '"]' );
      var view = document.querySelector( '.ceafsn-pp' ).className.match( /ceafsn-pp--(grid|list)/ );

      if ( ! viewField && view ) {
        var hidden = document.createElement( 'input' );
        hidden.type = 'hidden';
        hidden.name = VIEW_FIELD;
        hidden.value = view[ 1 ];
        form.appendChild( hidden );
      }

      form.addEventListener( 'submit', function () {
        flagFocus();
      } );
    }

    // Every link inside the component that changes the query string should
    // restore focus after the reload.
    var links = document.querySelectorAll(
      '.ceafsn-pp-view__btn, .ceafsn-pp-table thead th a, .ceafsn-pp-pagination a, .ceafsn-pp-filters__actions a'
    );

    Array.prototype.forEach.call( links, function ( link ) {
      link.addEventListener( 'click', flagFocus );
    } );

    restoreFocus();
  } );
} )();
