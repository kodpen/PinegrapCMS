<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the visual editor's dark chrome (style_designer.css): page
 * tabs, Publish, tree id labels, lock icons, the "off" and "Legacy" badges
 * and "No selection" reach 4.5:1 for text and 3:1 for icons, computed from
 * the editor's own --sd-* tokens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_chrome_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function designer_chrome_tokens()
{
	preg_match_all('/^\s*--sd-([a-z-]+):\s*(#[0-9a-f]{6});/m', designer_chrome_source('assets/css/style_designer.css'), $m, PREG_SET_ORDER);
	$tokens = array();

	foreach ($m as $match) {
		$tokens[$match[1]] = $match[2];
	}

	return $tokens;
}

function designer_chrome_rgb($hex)
{
	$hex = ltrim($hex, '#');

	return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

// color-mix(in srgb, a share, b), and an alpha layer over a ground.
function designer_chrome_mix($a, $b, $share)
{
	$out = array();

	for ($i = 0; $i < 3; $i++) {
		$out[] = $a[$i] * $share + $b[$i] * (1 - $share);
	}

	return $out;
}

function designer_chrome_ratio($a, $b)
{
	$lum = function ($c) {
		$sum = 0;

		foreach (array(0.2126, 0.7152, 0.0722) as $i => $weight) {
			$v = $c[$i] / 255;
			$v = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
			$sum += $weight * $v;
		}

		return $sum;
	};
	$la = $lum($a);
	$lb = $lum($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

function test_designer_chrome_contrast()
{
	$css = designer_chrome_source('assets/css/style_designer.css');
	$t = designer_chrome_tokens();

	pg_assert_true(isset($t['panel-bg'], $t['panel-border'], $t['accent'], $t['text'], $t['text-muted']), 'the editor tokens are found');

	$panel = designer_chrome_rgb($t['panel-bg']);
	$accent = designer_chrome_rgb($t['accent']);
	$text = designer_chrome_rgb($t['text']);
	$muted = designer_chrome_rgb($t['text-muted']);
	$white = array(255, 255, 255);
	$active_tab = designer_chrome_mix($white, $panel, 0.05);
	$selected_row = designer_chrome_mix($accent, $panel, 0.18);

	pg_assert_contains('color: color-mix(in srgb, var(--sd-text-muted) 70%, var(--sd-text));', $css, 'page tabs and the Legacy badge use the softened muted text');
	$soft = designer_chrome_mix($muted, $text, 0.7);
	pg_assert_true(designer_chrome_ratio($soft, $active_tab) >= 4.5, 'a tab name reads 4.5:1 on a lit tab');

	pg_assert_contains('background: color-mix(in srgb, var(--sd-accent) 85%, var(--sd-panel-border));', $css, 'Publish sits on the deepened accent');
	$publish = designer_chrome_mix($accent, designer_chrome_rgb($t['panel-border']), 0.85);
	pg_assert_true(designer_chrome_ratio($white, $publish) >= 4.5, 'the Publish label reads 4.5:1');

	pg_assert_contains('color: color-mix(in srgb, var(--sd-accent) 55%, var(--sd-text));', $css, 'tree ids mix the accent with the text colour');
	pg_assert_true(designer_chrome_ratio(designer_chrome_mix($accent, $text, 0.55), $selected_row) >= 4.5, 'a tree id reads 4.5:1 on a selected row');

	pg_assert_true(designer_chrome_ratio($accent, $panel) >= 3, 'an accent lock icon reads 3:1 on the panel');
	pg_assert_true(designer_chrome_ratio($muted, $panel) >= 4.5, 'muted text ("No selection", an off file) reads 4.5:1 on the panel');

	foreach (array('.sd-tree-id', '.sd-sys-attr-name .bi-lock-fill', '.sd-ht-lock-icon', '.sd-as-item.sd-as-item-disabled') as $selector) {
		pg_assert_true(preg_match('/^' . preg_quote($selector, '/') . ' \{[^}]*opacity/m', $css) !== 1, $selector . ' is not faded');
	}
}

function test_designer_chrome_markup_without_fades()
{
	$js = designer_chrome_source('assets/js/style_designer.js');

	pg_assert_true(strpos($js, 'color:var(--sd-accent);opacity:') === false, 'inline lock icons are not faded');
	pg_assert_contains('<span class="sd-breadcrumb-empty">', $js, '"No selection" carries its class, not an opacity');
	pg_assert_contains('<span class="sd-legacy-badge">', $js, 'the Legacy badge is styled by its class');
}
