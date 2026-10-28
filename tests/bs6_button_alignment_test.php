<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel shell rule that aligns .btn content with
 * text-start/text-end: Bootstrap 6 buttons centre their content with
 * flexbox, so text-align alone no longer moves it. The rule must sit inside
 * backend.src.css's @layer custom, below the utilities layer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of backend.src.css's @layer custom block, braces matched.
function bs6_button_alignment_custom_layer()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$start = strpos($css, '@layer custom {');
	if ($start === false) {
		return '';
	}
	$open = $start + strlen('@layer custom {');
	$depth = 1;
	$length = strlen($css);
	for ($i = $open; $i < $length; $i++) {
		if ($css[$i] === '{') {
			$depth++;
		} elseif ($css[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($css, $open, $i - $open);
			}
		}
	}

	return '';
}

function test_bs6_button_text_start_aligns_content()
{
	$layer = bs6_button_alignment_custom_layer();
	pg_assert_same(1, preg_match('/\.btn:where\(\.text-start\)\s*\{\s*justify-content:\s*flex-start;\s*\}/', $layer), '.btn.text-start starts its content in @layer custom');
	pg_assert_same(1, preg_match('/\.btn:where\(\.text-end\)\s*\{\s*justify-content:\s*flex-end;\s*\}/', $layer), '.btn.text-end ends its content in @layer custom');
}
