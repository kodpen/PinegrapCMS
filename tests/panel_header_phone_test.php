<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the panel top bar at phone width (#header in
 * output_header() in includes/fn/output.php, and assets/css/backend.src.css):
 * below 768px, where the full logo no longer fits, the mascot stays as the
 * square logo and the wordmark and the page heading are hidden; below 390px the mascot gives way too, so the controls keep the
 * row; from 768px up nothing hides them.
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

function test_panel_header_phone_keeps_the_mascot()
{
	$css = file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$narrow = false;
	$tiny = false;

	foreach (panel_header_phone_test_media_bodies($css, '(max-width: 767.98px)') as $body) {
		if (preg_match('/#header \.pinegrap-logo-word,\s*#header \.pg-navhead\s*\{\s*display: none;\s*\}/', $body)) {
			$narrow = true;
		}

		pg_assert_false((bool) preg_match('/\.pg-brand-item\s*\{[^}]*display:\s*none/', $body), 'below md the logo item itself stays');
	}

	foreach (panel_header_phone_test_media_bodies($css, '(max-width: 389.98px)') as $body) {
		if (preg_match('/#header \.pg-brand-item\s*\{\s*display: none;\s*\}/', $body)) {
			$tiny = true;
		}
	}

	pg_assert_true($narrow, 'below md the wordmark and the page heading are hidden, the mascot stays');
	pg_assert_true($tiny, 'below 390px the logo item gives way to the controls');

	$outside = preg_replace('/@media \(max-width: (767|389)\.98px\) \{.*?\n\}/s', '', $css);
	pg_assert_false((bool) preg_match('/\.pg-brand-item[^{]*\{[^}]*display:\s*none/', $outside), 'nothing hides the logo item from 390px up');
	pg_assert_false((bool) preg_match('/\.pinegrap-logo-(mark|word)[^{]*\{[^}]*display:\s*none/', $outside), 'nothing hides the mascot or the wordmark from md up');
	pg_assert_false((bool) preg_match('/pgLogoGradientIcon/', $css), 'the icon-only crop of the old logo is gone');
}

function test_panel_header_phone_brand_item_markup()
{
	$php = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/output.php');

	pg_assert_true((bool) preg_match('/<li class="nav-item pg-brand-item">\s*<a href="[^"]*welcome\.php"/', $php), 'the logo link sits in li.pg-brand-item');
}

function test_panel_header_nothing_over_the_logo()
{
	$php = file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/output.php');
	$css = file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');

	pg_assert_false(strpos($php, 'pinegrap-version') !== false, 'no version pill in the header');
	pg_assert_false(strpos($css, '.pinegrap-version') !== false, 'no rule left for it');
}
