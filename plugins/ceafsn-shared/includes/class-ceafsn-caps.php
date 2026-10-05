<?php
/**
 * Capability registry for the CE-AFSN plugin suite.
 *
 * Every CE-AFSN admin screen used to be gated behind `manage_options`, which is
 * a site-administration capability: an editor who maintains the research data
 * but must not install plugins or edit user accounts had no way to do their job
 * without being handed site administration. These three capabilities separate
 * the work.
 *
 *   ceafsn_manage   Settings, exports, uninstall, and anything else that can
 *                   affect the whole installation.
 *   ceafsn_edit     Creating, editing, and deleting research records, and
 *                   viewing the admin screens.
 *   ceafsn_approve  Promoting a record out of draft, and verifying a claim.
 *
 * `manage_options` is kept as a fallback everywhere, so an installation that has
 * not run the installer yet still behaves exactly as it did before and no
 * administrator is locked out of their own data.
 *
 * @package CEAFSN_Shared
 */

defined( 'ABSPATH' ) || exit;

/**
 * Capability registry and installer.
 */
final class CEAFSN_Caps {

	/**
	 * Full-site settings, exports, and uninstall.
	 */
	public const MANAGE = 'ceafsn_manage';

	/**
	 * Reading and writing research records.
	 */
	public const EDIT = 'ceafsn_edit';

	/**
	 * Publishing and verification.
	 */
	public const APPROVE = 'ceafsn_approve';

	/**
	 * Slug of the research editor role added by {@see self::install()}.
	 */
	public const EDITOR_ROLE = 'ceafsn_research_editor';

	/**
	 * Roles that receive every CE-AFSN capability.
	 *
	 * Administrators are included so an existing installation keeps working
	 * without anyone having to touch a role screen.
	 *
	 * @var string[]
	 */
	private const PRIVILEGED_ROLES = array( 'administrator' );

	/**
	 * All CE-AFSN capabilities, in increasing order of privilege.
	 *
	 * @return string[] Capability names.
	 */
	public static function all(): array {
		return array( self::EDIT, self::APPROVE, self::MANAGE );
	}

	/**
	 * Capabilities granted to the research editor role.
	 *
	 * An editor may prepare and publish research records but not change site
	 * settings, which is the separation that `manage_options` never provided.
	 *
	 * @return string[] Capability names.
	 */
	public static function editor_capabilities(): array {
		return array( self::EDIT, self::APPROVE );
	}

	/**
	 * Register the capabilities and the research editor role.
	 *
	 * Idempotent, because each of the six plugins may call this from its own
	 * activation hook and several may be activated in the same request.
	 */
	public static function install(): void {
		foreach ( self::PRIVILEGED_ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::all() as $cap ) {
				$role->add_cap( $cap );
			}
		}

		$editor = get_role( self::EDITOR_ROLE );
		if ( null === $editor ) {
			$editor = add_role( self::EDITOR_ROLE, __( 'CE-AFSN Research Editor', 'ceafsn' ), array( 'read' => true ) );
		}
		if ( null === $editor ) {
			return;
		}
		foreach ( self::editor_capabilities() as $cap ) {
			$editor->add_cap( $cap );
		}
	}

	/**
	 * Remove the CE-AFSN capabilities from every role.
	 *
	 * Called on uninstall. Missing roles are skipped rather than treated as an
	 * error, so uninstalling on a site where the role was already removed by
	 * hand still completes.
	 */
	public static function uninstall(): void {
		foreach ( self::PRIVILEGED_ROLES as $role_name ) {
			$role = get_role( $role_name );
			if ( null === $role ) {
				continue;
			}
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		$editor = get_role( self::EDITOR_ROLE );
		if ( null !== $editor ) {
			foreach ( self::editor_capabilities() as $cap ) {
				$editor->remove_cap( $cap );
			}
			remove_role( self::EDITOR_ROLE );
		}
	}

	/**
	 * Whether the current user holds a CE-AFSN capability.
	 *
	 * `manage_options` is accepted as a fallback so that a site which has not
	 * re-activated a plugin since the capabilities were introduced keeps the
	 * access its administrators already had.
	 *
	 * @param string $cap Capability name.
	 * @return bool True when allowed.
	 */
	public static function can( string $cap ): bool {
		if ( in_array( $cap, self::all(), true ) && current_user_can( $cap ) ) {
			return true;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Whether the current user may write research records.
	 *
	 * @return bool True when allowed.
	 */
	public static function can_edit(): bool {
		return self::can( self::EDIT );
	}

	/**
	 * Whether the current user may publish or verify.
	 *
	 * @return bool True when allowed.
	 */
	public static function can_approve(): bool {
		return self::can( self::APPROVE );
	}

	/**
	 * Whether the current user may change site-wide settings.
	 *
	 * @return bool True when allowed.
	 */
	public static function can_manage(): bool {
		return self::can( self::MANAGE );
	}

	/**
	 * The capability a menu item should be gated behind.
	 *
	 * Admin screens are read-and-edit surfaces, so they use the edit capability
	 * rather than the more restrictive manage capability.
	 *
	 * @return string Capability name.
	 */
	public static function menu_capability(): string {
		return self::EDIT;
	}
}