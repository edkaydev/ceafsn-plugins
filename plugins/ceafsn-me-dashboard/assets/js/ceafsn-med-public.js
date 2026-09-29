/**
 * CE-AFSN M&E Dashboard — Public JavaScript
 *
 * Implements the ARIA tabs pattern (WAI-ARIA 1.1) for the dashboard panels.
 *
 * Keyboard behaviour:
 *   - Left / Right arrows: move focus between tabs
 *   - Home: focus first tab
 *   - End:  focus last tab
 *   - Enter / Space: activate focused tab (also triggered by click)
 *
 * No external libraries required.
 */

( function () {
	'use strict';

	/**
	 * Initialise all tab widgets on the page.
	 */
	function initTabs() {
		const tabLists = document.querySelectorAll( '.ceafsn-med-tabs' );

		tabLists.forEach( function ( tabList ) {
			const tabs   = Array.from( tabList.querySelectorAll( '[role="tab"]' ) );
			const panels = tabs.map( function ( tab ) {
				return document.getElementById( tab.getAttribute( 'aria-controls' ) );
			} );

			// Activate the tab that has aria-selected="true" on load.
			const initialActive = tabs.findIndex( ( t ) => t.getAttribute( 'aria-selected' ) === 'true' );
			activateTab( tabs, panels, initialActive >= 0 ? initialActive : 0 );

			// Click handler.
			tabs.forEach( function ( tab, index ) {
				tab.addEventListener( 'click', function () {
					activateTab( tabs, panels, index );
				} );
			} );

			// Keyboard handler on the tablist.
			tabList.addEventListener( 'keydown', function ( event ) {
				const currentIndex = tabs.indexOf( document.activeElement );
				if ( currentIndex === -1 ) return;

				let targetIndex = currentIndex;

				switch ( event.key ) {
					case 'ArrowRight':
						targetIndex = ( currentIndex + 1 ) % tabs.length;
						break;
					case 'ArrowLeft':
						targetIndex = ( currentIndex - 1 + tabs.length ) % tabs.length;
						break;
					case 'Home':
						targetIndex = 0;
						break;
					case 'End':
						targetIndex = tabs.length - 1;
						break;
					case 'Enter':
					case ' ':
						activateTab( tabs, panels, currentIndex );
						return; // don't prevent default for other keys
					default:
						return;
				}

				event.preventDefault();
				tabs[ targetIndex ].focus();
				activateTab( tabs, panels, targetIndex );
			} );
		} );
	}

	/**
	 * Activate a tab by index and show its panel.
	 *
	 * @param {HTMLElement[]} tabs
	 * @param {HTMLElement[]} panels
	 * @param {number}        index
	 */
	function activateTab( tabs, panels, index ) {
		tabs.forEach( function ( tab, i ) {
			const isActive = i === index;
			tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			tab.setAttribute( 'tabindex', isActive ? '0' : '-1' );
			tab.classList.toggle( 'ceafsn-med-tab--active', isActive );
		} );

		panels.forEach( function ( panel, i ) {
			if ( ! panel ) return;
			if ( i === index ) {
				panel.removeAttribute( 'hidden' );
				panel.classList.add( 'ceafsn-med-panel--active' );
			} else {
				panel.setAttribute( 'hidden', '' );
				panel.classList.remove( 'ceafsn-med-panel--active' );
			}
		} );
	}

	// Initialise after DOM is ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initTabs );
	} else {
		initTabs();
	}
}() );
