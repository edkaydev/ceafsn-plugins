<?php
/**
 * WordPress hooks that enforce the ceafsn_staff experience.
 *
 * A CE-AFSN Staff member can do full CRUD across all six plugins but should not
 * see any standard WordPress admin menus (Posts, Pages, Appearance, Plugins,
 * Users, Settings, etc.). When they log in they land directly on the M&E
 * Dashboard page rather than the WordPress dashboard home.
 *
 * This file only attaches hooks — it defines no classes or functions of its
 * own — so requiring it always has side-effects. It is loaded conditionally
 * from ceafsn-shared-load.php only when a request involves an admin context.
 *
 * @package CEAFSN_Shared
 */

defined( 'ABSPATH' ) || exit;

/**
 * After login, send ceafsn_staff users to the M&E Dashboard admin screen
 * instead of the default wp-admin dashboard.
 *
 * WordPress fires login_redirect with three arguments; the filter signature
 * must accept all three so the fallback $redirect_to passes through unchanged
 * for every other role.
 *
 * @param string  $redirect_to           Requested redirect URL.
 * @param string  $requested_redirect_to Original requested URL before login.
 * @param WP_User $user                  The user logging in.
 * @return string URL to redirect to.
 */
add_filter(
	'login_redirect',
	static function ( string $redirect_to, string $requested_redirect_to, $user ): string {
		if ( ! ( $user instanceof WP_User ) ) {
			return $redirect_to;
		}

		if ( ! in_array( CEAFSN_Caps::STAFF_ROLE, (array) $user->roles, true ) ) {
			return $redirect_to;
		}

		// Land on the M&E Dashboard; fall back to a generic admin URL if the
		// plugin is not active (it will still be better than /wp-admin/).
		return admin_url( 'admin.php?page=ceafsn-med' );
	},
	10,
	3
);

/**
 * Remove standard WordPress admin menu items for ceafsn_staff users.
 *
 * The hook fires after all menus have been registered, so every top-level item
 * and every sub-menu item that we want gone is already in the global arrays at
 * this point.
 *
 * Items are removed, not hidden with CSS: a determined user could otherwise
 * reach them directly via URL. The approach here is defence-in-depth — the
 * capability checks on the individual screens are the real gate; this just
 * keeps the interface clean.
 */
add_action(
	'admin_menu',
	static function (): void {
		if ( ! current_user_can( CEAFSN_Caps::EDIT ) ) {
			return;
		}

		// Only apply the restrictions to the staff role, not to admins or
		// editors who also happen to have ceafsn_edit.
		$user = wp_get_current_user();
		if ( ! in_array( CEAFSN_Caps::STAFF_ROLE, (array) $user->roles, true ) ) {
			return;
		}

		// Top-level menu slugs to remove.
		$remove_top = array(
			'index.php',          // Dashboard home
			'edit.php',           // Posts
			'upload.php',         // Media
			'edit.php?post_type=page', // Pages
			'edit-comments.php',  // Comments
			'themes.php',         // Appearance
			'plugins.php',        // Plugins
			'users.php',          // Users
			'tools.php',          // Tools
			'options-general.php', // Settings
		);

		foreach ( $remove_top as $slug ) {
			remove_menu_page( $slug );
		}

		// Sub-menu items whose parent we are keeping (e.g. Dashboard > Updates).
		remove_submenu_page( 'index.php', 'update-core.php' );
	},
	// Priority 999 ensures we run after every plugin has added its own menus.
	999
);

/**
 * Redirect ceafsn_staff away from the dashboard home (/wp-admin/) if they
 * navigate there directly after logging in.
 *
 * The login_redirect filter covers the post-login flow, but a staff member
 * could bookmark /wp-admin/ directly. This catches that case.
 */
add_action(
	'load-index.php',
	static function (): void {
		$user = wp_get_current_user();
		if ( ! in_array( CEAFSN_Caps::STAFF_ROLE, (array) $user->roles, true ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ceafsn-med' ) );
		exit;
	}
);
