<?php
/**
 * Request parameter access.
 *
 * Every read of $_GET or $_POST in this plugin goes through this class, so
 * there is exactly one place where wp_unslash() and a sanitiser are applied.
 * A raw superglobal read anywhere else is a review finding, not a style nit.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_OD_Request
 */
class CEAFSN_OD_Request {

	/**
	 * Is a parameter present in the given superglobal?
	 *
	 * Presence only; the value is not read. Used for checkbox flags, where a
	 * missing key means "unchecked" rather than "empty".
	 *
	 * @param string $key    Parameter name.
	 * @param string $source Either 'GET' or 'POST'.
	 * @return bool
	 */
	public static function has( string $key, string $source = 'GET' ): bool {
		return 'POST' === strtoupper( $source )
			? isset( $_POST[ $key ] )
			: isset( $_GET[ $key ] );
	}

	/**
	 * Read a parameter as a sanitised slug, for routing and enums.
	 *
	 * @param string $key     Parameter name.
	 * @param string $default Value when the parameter is absent.
	 * @param string $source  Either 'GET' or 'POST'.
	 * @return string
	 */
	public static function key( string $key, string $default = '', string $source = 'GET' ): string {
		$value = self::raw( $key, $source );
		return '' === $value ? $default : sanitize_key( $value );
	}

	/**
	 * Read a parameter as a non-negative integer.
	 *
	 * @param string $key     Parameter name.
	 * @param int    $default Value when the parameter is absent.
	 * @param string $source  Either 'GET' or 'POST'.
	 * @return int
	 */
	public static function int( string $key, int $default = 0, string $source = 'GET' ): int {
		$value = self::raw( $key, $source );
		return '' === $value ? $default : absint( $value );
	}

	/**
	 * Read a parameter as a single-line sanitised string.
	 *
	 * @param string $key     Parameter name.
	 * @param string $default Value when the parameter is absent.
	 * @param string $source  Either 'GET' or 'POST'.
	 * @return string
	 */
	public static function text( string $key, string $default = '', string $source = 'GET' ): string {
		$value = self::raw( $key, $source );
		return '' === $value ? $default : sanitize_text_field( $value );
	}

	/**
	 * Read a parameter as a multi-line sanitised string.
	 *
	 * @param string $key     Parameter name.
	 * @param string $default Value when the parameter is absent.
	 * @param string $source  Either 'GET' or 'POST'.
	 * @return string
	 */
	public static function textarea( string $key, string $default = '', string $source = 'GET' ): string {
		$value = self::raw( $key, $source );
		return '' === $value ? $default : sanitize_textarea_field( $value );
	}

	/**
	 * Read a parameter as a URL, rejecting any scheme WordPress disallows.
	 *
	 * @param string $key     Parameter name.
	 * @param string $default Value when the parameter is absent.
	 * @param string $source  Either 'GET' or 'POST'.
	 * @return string
	 */
	public static function url( string $key, string $default = '', string $source = 'GET' ): string {
		$value = self::raw( $key, $source );
		return '' === $value ? $default : esc_url_raw( $value );
	}

	/**
	 * Read a raw, unslashed parameter value.
	 *
	 * Private on purpose: callers get one of the typed accessors above, each of
	 * which sanitises. The only exception is the URL-safe accessor used for the
	 * redirect error notice, which needs its value intact until it is escaped.
	 *
	 * @param string $key    Parameter name.
	 * @param string $source Either 'GET' or 'POST'.
	 * @return string
	 */
	private static function raw( string $key, string $source ): string {
		$value = 'POST' === strtoupper( $source )
			? ( $_POST[ $key ] ?? '' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify their own nonce.
			: ( $_GET[ $key ] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only front-end input.

		if ( is_array( $value ) ) {
			return '';
		}

		return (string) wp_unslash( $value );
	}
}
