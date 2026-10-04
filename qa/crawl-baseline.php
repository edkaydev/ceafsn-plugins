<?php
/**
 * Baseline crawler for the live CE-AFSN site (Phase 0).
 *
 * Read-only. Fetches the target routes and a bounded set of internal links,
 * then writes three artifacts:
 *
 *     qa/baseline-crawl.json    per-route status, redirect chain, title, generator
 *     qa/baseline-links.csv     every internal link, PDF, CSV, image, form, iframe
 *     qa/baseline-content.md    human-readable summary of the above
 *
 * Nothing is written to the site. Run it with:
 *
 *     php qa/crawl-baseline.php
 *
 * @package CEAFSN_QA
 */

declare( strict_types=1 );

if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( 1 );
}

const BASE        = 'https://ceafsn.duckdns.org';
const USER_AGENT  = 'CE-AFSN-QA-Baseline/1.0 (read-only audit)';
const ROUTE_DELAY_MS = 300; // milliseconds between requests

/**
 * Routes to measure, mirroring qa/routes.csv.
 */
$routes = array(
	'/'                                              => 'Homepage',
	'/appy'                                          => 'Must resolve to configured application destination',
	'/privacy-policy-2/'                             => 'Must redirect permanently',
	'/publications/'                                 => 'Publications',
	'/me-dashboard/'                                 => 'M&E Dashboard',
	'/nutrition-policy-modeling/'                    => 'Nutrition Policy',
	'/open-datasets/'                                => 'Open Datasets',
	'/research-fellowships/'                         => 'Research Fellowships',
	'/scholarships-grants/'                          => 'Grants and Funding',
	'/contact/'                                      => 'Check for placeholder contact data',
	'/volunteer/'                                    => 'Check for Alumin Network title',
	'/our-team/'                                     => 'Check for template staff names',
	'/hello-world/'                                  => 'Decision pending owner approval',
	'/lobortis-elementum-nibhtellus-molestie-adipiscing/' => 'Should be noindexed or removed',
	'/duis-tristique-sollicitudin-nibh-sit-amet-commodo-nulla/' => 'Should be noindexed or removed',
	'/aenean-tortor-atisus-viverra-adipiscing/'       => 'Latin demo post found in post-sitemap.xml',
	'/mauris-cursus-mattis-molestie-aaculis-oterat-pellentesque/' => 'Latin demo post found in post-sitemap.xml',
	'/author/edward_admin/'                          => 'Should be noindexed unless approved',
);

/**
 * Extra pages crawled for the link inventory only.
 */
$extra = array( '/sitemap.xml', '/wp-sitemap.xml', '/robots.txt', '/feed/' );

$UA       = USER_AGENT;
$timeout  = 25;
$pages    = array();
$failures = 0;

/**
 * Perform one request without following redirects, so the redirect chain is
 * recorded rather than hidden.
 */
function ceafsn_qa_get( string $url ) {
	global $UA, $timeout, $failures;

	$ch = curl_init( $url );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_HEADER         => true,
			CURLOPT_USERAGENT      => $UA,
			CURLOPT_TIMEOUT        => $timeout,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => true,
		)
	);

	$raw = curl_exec( $ch );
	if ( false === $raw ) {
		++$failures;
		$error = curl_error( $ch );
		curl_close( $ch );
		return array(
			'ok'      => false,
			'error'   => $error,
			'status'  => 0,
			'headers' => array(),
			'body'    => '',
		);
	}

	$status      = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$header_size = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
	curl_close( $ch );

	$head = substr( $raw, 0, $header_size );
	$body = substr( $raw, $header_size );

	$headers = array();
	foreach ( preg_split( '/\r?\n/', $head ) as $line ) {
		if ( false !== strpos( $line, ':' ) ) {
			list( $k, $v )              = explode( ':', $line, 2 );
			$headers[ strtolower( trim( $k ) ) ] = trim( $v );
		}
	}

	return array(
		'ok'      => true,
		'status'  => $status,
		'headers' => $headers,
		'body'    => $body,
	);
}

/**
 * Walk a redirect chain, up to a sane limit.
 */
function ceafsn_qa_follow( string $url ) {
	$chain = array();
	$seen  = array();
	for ( $i = 0; $i < 6; $i++ ) {
		if ( isset( $seen[ $url ] ) ) {
			$chain[] = array(
				'url'    => $url,
				'status' => 0,
				'loop'   => true,
			);
			break;
		}
		$seen[ $url ] = true;

		$res          = ceafsn_qa_get( $url );
		$chain[]      = array(
			'url'     => $url,
			'status'  => $res['status'],
			'location' => $res['headers']['location'] ?? '',
			'error'   => $res['error'] ?? '',
		);

		if ( ! $res['ok'] ) {
			break;
		}
		if ( $res['status'] < 300 || $res['status'] > 399 ) {
			break;
		}
		$location = $res['headers']['location'] ?? '';
		if ( '' === $location ) {
			break;
		}
		$url = 0 === strpos( $location, 'http' ) ? $location : rtrim( BASE, '/' ) . $location;
		usleep( ROUTE_DELAY_MS * 1000 );
	}

	return $chain;
}

function ceafsn_qa_title( string $html ): string {
	if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $html, $m ) ) {
		return trim( html_entity_decode( wp_strip_tags_fallback( $m[1] ), ENT_QUOTES ) );
	}
	return '';
}

function wp_strip_tags_fallback( string $s ): string {
	return trim( preg_replace( '/\s+/', ' ', strip_tags( $s ) ) );
}

function ceafsn_qa_absolute( string $href ): string {
	if ( '' === $href ) {
		return '';
	}
	if ( 0 === strpos( $href, '//' ) ) {
		return 'https:' . $href;
	}
	if ( preg_match( '#^https?://#i', $href ) ) {
		return $href;
	}
	return rtrim( BASE, '/' ) . '/' . ltrim( $href, '/' );
}

/**
 * Inventory links and embedded assets in one page of HTML.
 */
function ceafsn_qa_inventory( string $html ): array {
	$out = array(
		'links'   => array(),
		'assets'  => array(),
		'iframes' => array(),
		'forms'   => array(),
	);

	if ( preg_match_all( '/<a\b[^>]*href=["\']([^"\']+)["\']/i', $html, $m ) ) {
		foreach ( $m[1] as $href ) {
			$out['links'][] = array( 'href' => $href, 'absolute' => ceafsn_qa_absolute( $href ) );
		}
	}
	foreach ( array( 'img', 'script', 'link' ) as $tag ) {
		$attr = 'img' === $tag ? 'src' : ( 'script' === $tag ? 'src' : 'href' );
		if ( preg_match_all( '/<' . $tag . '\b[^>]*' . $attr . '=["\']([^"\']+)["\']/i', $html, $m ) ) {
			foreach ( $m[1] as $href ) {
				$out['assets'][] = array(
					'tag'      => $tag,
					'url'      => $href,
					'absolute' => ceafsn_qa_absolute( $href ),
				);
			}
		}
	}
	if ( preg_match_all( '/<iframe\b[^>]*src=["\']([^"\']+)["\']/i', $html, $m ) ) {
		foreach ( $m[1] as $src ) {
			$out['iframes'][] = ceafsn_qa_absolute( $src );
		}
	}
	if ( preg_match_all( '/<form\b([^>]*)>/i', $html, $m ) ) {
		foreach ( $m[1] as $attrs ) {
			$action = '';
			$method = 'get';
			if ( preg_match( '/action=["\']([^"\']*)["\']/i', $attrs, $a ) ) {
				$action = $a[1];
			}
			if ( preg_match( '/method=["\']([^"\']*)["\']/i', $attrs, $a ) ) {
				$method = strtolower( $a[1] );
			}
			$out['forms'][] = array(
				'action'  => ceafsn_qa_absolute( $action ),
				'method'  => $method,
			);
		}
	}

	return $out;
}

/**
 * Strings that indicate demo or placeholder content still visible to visitors.
 */
function ceafsn_qa_content_flags( string $html ): array {
	$flags = array();
	$checks = array(
		'lorem_ipsum'      => '/lorem\s+ipsum|dolor\s+sit\s+amet,\s+consectetur/i',
		'latin_demo_slug'  => '/lobortis\s+elementum|duis\s+tristique\s+sollicitudin|Nullam\s+gravida\s+orci/i',
		'placeholder_prompt' => '/\[placeholder[^\]]*\]|placeholder\s+(text|image|content)/i',
		'hello_world'      => '/hello\s*world/i',
		'alumin_network'   => '/Alumin\s+Network/i',
		'wordpress_commenter' => '/A\s+WordPress\s+Commenter|WordPress\s+Commenter/i',
		'wp_generator'     => '/name=["\']generator["\']\s+content=["\']WordPress\s+([0-9][^"\']*)/i',
		'wp_noindex'       => '/<meta[^>]+name=["\']robots["\'][^>]*noindex/i',
		'wp_nofollow'      => '/<meta[^>]+name=["\']robots["\'][^>]*nofollow/i',
	);

	foreach ( $checks as $name => $pattern ) {
		if ( preg_match( $pattern, $html, $m ) ) {
			$flags[ $name ] = isset( $m[1] ) && '' !== $m[1] ? trim( $m[1] ) : 'present';
		}
	}

	return $flags;
}

$inventory = array();
$crawl     = array(
	'base'         => BASE,
	'crawled_at'   => gmdate( 'c' ),
	'user_agent'   => USER_AGENT,
	'tool'         => 'qa/crawl-baseline.php',
	'routes'       => array(),
	'extras'       => array(),
	'environment'  => array(),
);

foreach ( $routes as $path => $label ) {
	$url         = rtrim( BASE, '/' ) . $path;
	$chain       = ceafsn_qa_follow( $url );
	$last        = end( $chain );
	$final       = $chain[ count( $chain ) - 1 ]['url'];
	$res         = ceafsn_qa_get( $final );
	$body        = $res['ok'] ? $res['body'] : '';
	$flags       = ceafsn_qa_content_flags( $body );
	$generator   = $flags['wp_generator'] ?? '';
	unset( $flags['wp_generator'] );

	$crawl['routes'][ $path ] = array(
		'label'          => $label,
		'requested'      => $url,
		'chain'          => $chain,
		'final_status'   => $last['status'],
		'final_url'      => $final,
		'redirect_count' => count( $chain ) - 1,
		'title'          => ceafsn_qa_title( $body ),
		'bytes'          => strlen( $body ),
		'generator'      => $generator,
		'content_flags'  => $flags,
		'error'          => $res['error'] ?? '',
	);

	$inventory[ $path ] = ceafsn_qa_inventory( $body );

	printf( "%-58s %s\n", $path, $last['status'] . ( $final !== $url ? ' -> ' . $final : '' ) );
	usleep( ROUTE_DELAY_MS * 1000 );
}

foreach ( $extra as $path ) {
	$url   = rtrim( BASE, '/' ) . $path;
	$res   = ceafsn_qa_get( $url );
	$ctype = $res['headers']['content-type'] ?? '';
	$crawl['extras'][ $path ] = array(
		'status' => $res['status'],
		'type'   => $ctype,
		'bytes'  => strlen( $res['body'] ),
		'error'  => $res['error'] ?? '',
	);
	if ( 'sitemap' === $path && $res['ok'] && $res['status'] === 200 ) {
		if ( preg_match_all( '#<loc>\s*([^<\s]+)\s*</loc>#i', $res['body'], $m ) ) {
			$crawl['extras'][ $path ]['urls'] = array_values( array_unique( $m[1] ) );
		}
	}
	printf( "%-58s %s\n", $path, $res['status'] );
	usleep( ROUTE_DELAY_MS * 1000 );
}

// Environment fingerprint, gathered from the homepage only.
$homepage = $inventory['/']['links'] ?? array();
$plugin_assets = array();
$theme_assets  = array();
foreach ( ( $inventory['/']['assets'] ?? array() ) as $asset ) {
	if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $asset['url'], $m ) ) {
		$plugin_assets[ $m[1] ] = true;
	}
	if ( preg_match( '#/wp-content/themes/([^/]+)/#', $asset['url'], $m ) ) {
		$theme_assets[ $m[1] ] = true;
	}
}
$crawl['environment'] = array(
	'generator'          => $crawl['routes']['/']['generator'] ?? '',
	'plugin_asset_paths' => array_keys( $plugin_assets ),
	'theme_asset_paths'  => array_keys( $theme_assets ),
	'note'               => 'Active plugins are inferred from asset URLs on the homepage only. Confirm in wp-admin before changing anything.',
);

ksort( $inventory );

$qa_dir = __DIR__;
file_put_contents(
	$qa_dir . '/baseline-crawl.json',
	json_encode( $crawl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
);

// --- baseline-links.csv -----------------------------------------------------
$rows = array( array( 'source_page', 'kind', 'url', 'absolute_url', 'in_scope' ) );
foreach ( $inventory as $page => $sets ) {
	foreach ( $sets['links'] as $link ) {
		$abs = $link['absolute'];
		$kind = 'link';
		if ( preg_match( '/\.pdf($|\?)/i', $abs ) ) {
			$kind = 'pdf';
		} elseif ( preg_match( '/\.csv($|\?)/i', $abs ) ) {
			$kind = 'csv';
		} elseif ( preg_match( '/\.(zip|xlsx?|json)($|\?)/i', $abs ) ) {
			$kind = 'download';
		} elseif ( preg_match( '#^https?://(?!ceafsn\.duckdns\.org)#i', $abs ) ) {
			$kind = 'external';
		}
		$rows[] = array( $page, $kind, $link['href'], $abs, 'yes' );
	}
	foreach ( $sets['assets'] as $asset ) {
		$kind = 'asset';
		if ( preg_match( '/\.pdf($|\?)/i', $asset['absolute'] ) ) {
			$kind = 'pdf';
		} elseif ( preg_match( '/\.(csv|zip|xlsx?)($|\?)/i', $asset['absolute'] ) ) {
			$kind = 'download';
		}
		$rows[] = array( $page, $kind, $asset['url'], $asset['absolute'], 'yes' );
	}
	foreach ( $sets['iframes'] as $src ) {
		$rows[] = array( $page, 'iframe', $src, $src, 'yes' );
	}
	foreach ( $sets['forms'] as $form ) {
		$rows[] = array( $page, 'form:' . $form['method'], $form['action'], $form['action'], 'yes' );
	}
}

$fh = fopen( $qa_dir . '/baseline-links.csv', 'w' );
foreach ( $rows as $row ) {
	fputcsv( $fh, $row );
}
fclose( $fh );

// --- baseline-content.md ----------------------------------------------------
$md  = "# CE-AFSN Baseline Content Snapshot\n\n";
$md .= 'Generated by `qa/crawl-baseline.php` on ' . gmdate( 'Y-m-d H:i:s', strtotime( $crawl['crawled_at'] ) ) . " UTC against `" . BASE . "`.\n";
$md .= "Read-only: no request in this crawl writes to the site.\n\n";

$md .= "## Environment\n\n";
$md .= "- WordPress generator: " . ( '' !== $crawl['environment']['generator'] ? $crawl['environment']['generator'] : 'not exposed' ) . "\n";
$md .= '- Plugin asset paths seen on the homepage: ' . ( $plugin_assets ? implode( ', ', array_keys( $plugin_assets ) ) : 'none' ) . "\n";
$md .= '- Theme asset paths seen on the homepage: ' . ( $theme_assets ? implode( ', ', array_keys( $theme_assets ) ) : 'none' ) . "\n";
$md .= "- {$failures} request(s) failed outright.\n\n";

$md .= "## Routes\n\n";
$md .= "| Route | Final status | Redirects | Final URL | Title | Content flags |\n";
$md .= "| --- | --- | --- | --- | --- | --- |\n";
foreach ( $crawl['routes'] as $path => $r ) {
	$flags = $r['content_flags'] ? implode( ', ', array_keys( $r['content_flags'] ) ) : '—';
	$title = $r['title'] ? $r['title'] : '—';
	$md   .= sprintf(
		"| `%s` | %d | %d | `%s` | %s | %s |\n",
		$path,
		$r['final_status'],
		$r['redirect_count'],
		$r['final_url'],
		$title,
		$flags
	);
}

$md .= "\n## Redirect Chains\n\n";
foreach ( $crawl['routes'] as $path => $r ) {
	if ( $r['redirect_count'] > 0 ) {
		$md .= "- `$path`:\n";
		foreach ( $r['chain'] as $hop ) {
			$md .= sprintf( "  - %d `%s`%s\n", $hop['status'], $hop['url'], $hop['location'] ? ' → Location: ' . $hop['location'] : '' );
		}
	}
}

$md .= "\n## Content Flags Detected\n\n";
$any = false;
foreach ( $crawl['routes'] as $path => $r ) {
	if ( $r['content_flags'] ) {
		$any = true;
		foreach ( $r['content_flags'] as $name => $detail ) {
			$md .= "- `$path` — **$name**: $detail\n";
		}
	}
}
if ( ! $any ) {
	$md .= "- None of the checked patterns were found.\n";
}

$md .= "\n## Counts\n\n";
$by_kind = array();
foreach ( array_slice( $rows, 1 ) as $row ) {
	$by_kind[ $row[1] ] = ( $by_kind[ $row[1] ] ?? 0 ) + 1;
}
ksort( $by_kind );
foreach ( $by_kind as $kind => $n ) {
	$md .= "- $kind: $n\n";
}
$md .= "\nFull per-page inventory in `qa/baseline-links.csv`; raw measurements in `qa/baseline-crawl.json`.\n";

file_put_contents( $qa_dir . '/baseline-content.md', $md );

printf(
	"\nWrote qa/baseline-crawl.json, qa/baseline-links.csv (%d rows), qa/baseline-content.md\n",
	count( $rows ) - 1
);