<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel top bar at phone width (#header in
 * output_header() in includes/fn/output.php, and assets/css/backend.src.css):
 * below 768px, where the full logo no longer fits, the logo item and the
 * page heading are hidden; from 768px up nothing hides them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of every @media block whose query is exactly $query.
function panel_header_phone_test_media_bodies($css, $query)
{
	$bodies = array();
	$offset = 0;
	$needle = '@media ' . $query . ' {';

	while (($start = strpos($css, $needle, $offset)) !== false) {
		$open = $start + strlen($needle);
		$depth = 1;
		$i = $open;
		$length = strlen($css);
		while ($i < $length && $depth > 0) {
			if ($css[$i] === '{') {
				$depth++;
			} elseif ($css[$i] === '}') {
				$depth--;
			}
			$i++;
		}
		$bodies[] = substr($css, $open, $i - $open - 1);
		$offset = $i;
	}

	return $bodies;
}

function test_panel_header_phone_hides_logo_and_heading()
{
	$css = file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$found = false;

	foreach (panel_header_phone_test_media_bodies($css, '(max-width: 767.98px)') as $body) {
		if (preg_match('/#header \.pg-brand-item,\s*#header \.pg-navhead\s*\{\s*display: none;\s*\}/', $body)) {
			$found = true;
		}
	}

	pg_assert_true($found, 'below md the logo item and the page heading are hidden');
	pg_assert_false((bool) preg_match('/\.pg-brand-item[^{]*\{[^}]*display:\s*none/', preg_replace('/@media \(max-width: 767\.98px\) \{.*?\n\}/s', '', $css)), 'nothing hides the logo item from md up');
	pg_assert_false((bool) preg_match('/pgLogoGradientIcon/', $css), 'the icon-only crop of the logo is gone');
}

function test_panel_header_phone_brand_item_markup()
{
	$php = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/output.php');

	pg_assert_true((bool) preg_match('/<li class="nav-item pg-brand-item">\s*<a href="[^"]*welcome\.php"/', $php), 'the logo link sits in li.pg-brand-item');
}
