<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the panel's editors in the dark theme: the code editor's
 * comment colour on its background and active line, and the rich text
 * editor's dialogs left as one light surface (no dark title bar alone).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function editor_dark_contrast_ratio($a, $b)
{
	$lum = function ($hex) {
		$sum = array();

		foreach (array(1, 3, 5) as $i) {
			$c = hexdec(substr($hex, $i, 2)) / 255;
			$sum[] = ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		}

		return 0.2126 * $sum[0] + 0.7152 * $sum[1] + 0.0722 * $sum[2];
	};
	$la = $lum($a);
	$lb = $lum($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// Dark comments read 4.5:1 on the editor and on the active line (#212327).
function test_editor_dark_code_comment()
{
	$js = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/pg_code_editor.js');
	pg_assert_true(preg_match("/var c = dark \\? \\{\\s*bg: '(#[0-9a-f]{6})'.*?comment: '(#[0-9a-f]{6})'/s", $js, $dark) === 1, 'the dark colours are found');

	foreach (array($dark[1], '#212327') as $ground) {
		$ratio = editor_dark_contrast_ratio($dark[2], $ground);
		pg_assert_true($ratio >= 4.5, sprintf('dark comments read %.2f:1 on %s', $ratio, $ground));
	}
}

// A dark title bar over the light dialog skin read as two surfaces.
function test_editor_dark_rich_text_dialog_one_surface()
{
	foreach (array('backend.src.css', 'backend.min.css') as $file) {
		$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/' . $file);
		pg_assert_false(strpos($css, '.cke_dialog_title') !== false, $file . ' leaves the rich text dialog title in its skin');
	}
}
