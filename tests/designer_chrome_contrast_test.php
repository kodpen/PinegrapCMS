<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the visual editor's dark chrome (style_designer.css): the
 * measured colour table and its tokens, page
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

	pg_assert_true(preg_match('/^\.sd-icon-btn-primary \{[^}]*background: var\(--sd-accent-strong\);/m', $css) === 1, 'Publish sits on the strong accent');
	$publish = designer_chrome_rgb($t['accent-strong']);
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
	$js = designer_chrome_source('assets/js/style_designer.src.js');

	pg_assert_true(strpos($js, 'color:var(--sd-accent);opacity:') === false, 'inline lock icons are not faded');
	pg_assert_contains('<span class="sd-breadcrumb-empty">', $js, '"No selection" carries its class, not an opacity');
	pg_assert_contains('<span class="sd-legacy-badge">', $js, 'the Legacy badge is styled by its class');
}

// Left panel small texts (#9099): the active palette tab and sub-tab, the
// System tab, the EXT badge, the empty Fonts note and the rule search
// placeholder reach 4.5:1 on their grounds.
function test_designer_chrome_left_panel_small_texts()
{
	$css = designer_chrome_source('assets/css/style_designer.css');
	$js = designer_chrome_source('assets/js/style_designer.src.js');
	$t = designer_chrome_tokens();
	$panel = designer_chrome_rgb($t['panel-bg']);
	$text = designer_chrome_rgb($t['text']);
	$accent = designer_chrome_rgb($t['accent']);
	$muted = designer_chrome_rgb($t['text-muted']);
	$subtab_ground = designer_chrome_rgb('#1a1f27');
	$assets_ground = designer_chrome_rgb('#1c2128');

	pg_assert_contains("color: color-mix(in srgb, var(--sd-accent) 55%, var(--sd-text));\n    border-bottom-color: var(--sd-accent", $css, 'an active tab mixes its label and keeps the accent underline');
	$active = designer_chrome_mix($accent, $text, 0.55);
	pg_assert_true(designer_chrome_ratio($active, $panel) >= 4.5, 'an active palette tab reads 4.5:1');
	pg_assert_true(designer_chrome_ratio($active, $subtab_ground) >= 4.5, 'an active Tree or Design sub-tab reads 4.5:1');

	pg_assert_true(strpos($js, 'data-tab="system" style="color:#6366f1"') === false, 'the System tab has no inline indigo');
	pg_assert_contains('#sd-panel-tabs .sd-panel-tab[data-tab="system"] { color: #818cf8; }', $css, 'the System tab takes the light indigo');
	pg_assert_true(designer_chrome_ratio(designer_chrome_rgb('#818cf8'), $panel) >= 4.5, 'the System tab reads 4.5:1');

	pg_assert_contains('background: rgba(77,142,240,.2); color: #79b8ff;', $css, 'the EXT badge takes the light blue');
	$ext_fill = designer_chrome_mix(designer_chrome_rgb('#4d8ef0'), $assets_ground, 0.2);
	pg_assert_true(designer_chrome_ratio(designer_chrome_rgb('#79b8ff'), $ext_fill) >= 4.5, 'the EXT badge reads 4.5:1 on its tint');

	pg_assert_true(preg_match('/^\.sd-as-empty \{[^}]*color: var\(--sd-text-muted\);/m', $css) === 1, 'the empty Fonts note uses the muted text colour');
	pg_assert_true(designer_chrome_ratio($muted, $assets_ground) >= 4.5, 'the empty Fonts note reads 4.5:1');

	pg_assert_contains('.sd-styles-find::placeholder { color: var(--sd-text-muted); }', $css, 'the rule search placeholder uses the muted text colour');
	pg_assert_true(designer_chrome_ratio($muted, designer_chrome_rgb('#0d1117')) >= 4.5, 'the rule search placeholder reads 4.5:1');
}

// Every --sd-* token, colours and rgba() layers alike, by name.
function designer_chrome_all_tokens()
{
	preg_match('/:root\s*\{([^}]*)\}/', designer_chrome_source('assets/css/style_designer.css'), $root);
	preg_match_all('/--sd-([a-z0-9-]+):\s*([^;]+);/', isset($root[1]) ? $root[1] : '', $m, PREG_SET_ORDER);
	$tokens = array();

	foreach ($m as $match) {
		$tokens[$match[1]] = trim($match[2]);
	}

	return $tokens;
}

// The chrome's colour table: each token holds the value of the measure
// sheet, and each text or icon colour reaches its stated ratio on the panel.
function test_designer_chrome_measures()
{
	$t = designer_chrome_all_tokens();
	$want = array(
		'panel-bg' => '#171b21',
		'raised-bg' => '#1f242c',
		'raised-bg-2' => '#262c36',
		'input-bg' => '#11151a',
		'panel-border' => '#2a313b',
		'input-border' => '#2a313b',
		'border-strong' => '#343c48',
		'text' => '#e8ebf0',
		'label' => '#aab3bf',
		'text-muted' => '#8c96a4',
		'icon-quiet' => '#5d6673',
		'accent' => '#5b9bff',
		'accent-rgb' => '91,155,255',
		'accent-strong' => '#2563eb',
		'on-accent' => '#ffffff',
		'accent-soft' => 'rgba(91,155,255,.14)',
		'accent-line' => 'rgba(91,155,255,.45)',
		'warning' => '#f5b54a',
		'warning-soft' => 'rgba(245,181,74,.14)',
		'success' => '#3ecf8e',
		'danger' => '#ff6b6b',
		'focus-ring' => '0 0 0 3px rgba(91,155,255,.13)',
		'dot-ring' => '0 0 0 3px rgba(91,155,255,.18)',
		'segment-shadow' => '0 3px 8px -3px rgba(37,99,235,.8)',
	);

	foreach ($want as $name => $value) {
		pg_assert_same($value, isset($t[$name]) ? $t[$name] : null, '--sd-' . $name . ' holds the measured value');
	}

	if (count(array_diff_key($want, $t)) > 0) {
		return;
	}

	$panel = designer_chrome_rgb($t['panel-bg']);
	$card = designer_chrome_rgb($t['raised-bg']);
	$pairs = array(
		array('text', $panel, 14.5, 'text on the panel'),
		array('label', $card, 7.4, 'a row label on an open section card'),
		array('text-muted', $panel, 5.8, 'secondary text on the panel'),
	);

	foreach ($pairs as $pair) {
		$ratio = designer_chrome_ratio(designer_chrome_rgb($t[$pair[0]]), $pair[1]);
		pg_assert_same($pair[2], round($ratio, 1), $pair[3] . ' measures ' . $pair[2] . ':1');
	}

	// The quiet icon is pinned at its measured value, under 3:1 on purpose:
	// it only ever draws an icon that says nothing by itself.
	pg_assert_true(designer_chrome_ratio(designer_chrome_rgb($t['icon-quiet']), $panel) >= 2.9, 'a quiet icon measures at least 2.9:1 on the panel');

	// The screen-size badge: its text on its soft ground, over the panel and over a section card.
	$warning = designer_chrome_rgb($t['warning']);
	pg_assert_true(designer_chrome_ratio($warning, designer_chrome_mix($warning, $panel, 0.14)) >= 4.5, 'the screen-size badge reads 4.5:1 on its soft ground over the panel');
	pg_assert_true(designer_chrome_ratio($warning, designer_chrome_mix($warning, $card, 0.14)) >= 4.5, 'the screen-size badge reads 4.5:1 on its soft ground over a section card');

	$white = designer_chrome_rgb($t['on-accent']);
	pg_assert_same(5.2, round(designer_chrome_ratio($white, designer_chrome_rgb($t['accent-strong'])), 1), 'white on the strong accent measures 5.2:1');

	// Text, a label, secondary text, the accent and the three states stay
	// readable on every ground of the chrome.
	foreach (array('panel-bg', 'raised-bg', 'raised-bg-2', 'input-bg') as $ground) {
		foreach (array('text', 'label', 'text-muted', 'accent', 'warning', 'success', 'danger') as $ink) {
			pg_assert_true(designer_chrome_ratio(designer_chrome_rgb($t[$ink]), designer_chrome_rgb($t[$ground])) >= 4.5, '--sd-' . $ink . ' reads 4.5:1 on --sd-' . $ground);
		}
	}
}

// The open Options tab's badge is filled with the accent: its number is
// written in the panel's ground, as white on the accent reads under 3:1.
function test_designer_chrome_open_tab_badge()
{
	$css = designer_chrome_source('assets/css/style_designer.css');
	$t = designer_chrome_tokens();
	$rule = '.sd-otab.active .sd-otab-count { background: var(--sd-accent); color: var(--sd-panel-bg); }';

	pg_assert_true(strpos($css, $rule) !== false, 'the open tab\'s badge: the panel\'s ground on the accent');
	pg_assert_true(designer_chrome_ratio(designer_chrome_rgb($t['panel-bg']), designer_chrome_rgb($t['accent'])) >= 4.5, 'its number reads 4.5:1');
	pg_assert_true(designer_chrome_ratio(array(255, 255, 255), designer_chrome_rgb($t['accent'])) < 3, 'white would not');
}

// The rules of the Options panel, the Overview, the page strip and the
// canvas bar take every colour from a token: no hex, rgb() or named colour.
function test_designer_chrome_areas_read_tokens_only()
{
	$css = designer_chrome_source('assets/css/style_designer.css');
	$css = preg_replace_callback('#/\*.*?\*/#s', function ($m) {
		return preg_replace('/[^\n]/', ' ', $m[0]);
	}, $css);
	$areas = '/\.sd-tabs?(?:-[a-z-]+)?\b|\.sd-bp-bar|\.sd-vb-|\.sd-tree|\.sd-overview|\.sd-ov-|#sd-properties|\.sd-props?-|\.sd-panel-tabs\b|\.sd-assets-tabs|\.sd-sect-|\.sd-sp-|\.sd-cp-(?:label|menu|option|opt-|trigger|grp)|\.sd-bm-|\.sd-align-|\.sd-bcp|\.sd-ctx-wrap|\.sd-quick-layouts|\.sd-tr-|\.sd-notes-/';
	$literal = '/#[0-9a-fA-F]{3,8}\b|\brgba?\(\s*\d|\b(?:white|black)\b(?!-)/';
	$found = array();

	preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

	foreach ($rules as $rule) {
		$selector = trim($rule[1][0]);

		if ($selector === '' || $selector[0] === '@' || strpos($selector, ':root') === 0 || !preg_match($areas, $selector)) {
			continue;
		}

		// A custom property set inside a rule is a token (a tab group's colour).
		$body = preg_replace('/--[a-z0-9-]+\s*:[^;]*;/', '', $rule[2][0]);

		if (preg_match($literal, $body, $hit)) {
			$found[] = 'line ' . (substr_count($css, "\n", 0, $rule[2][1]) + 1) . ' ' . $hit[0];
		}
	}

	pg_assert_same(array(), $found, 'the four areas write no colour of their own');
}
