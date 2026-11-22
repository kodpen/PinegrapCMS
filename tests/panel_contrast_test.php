<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of panel content in backend.src.css and api_settings.php: icons
 * at 3:1 on their card or row ground, secondary or "off" states on the
 * secondary text colour instead of an opacity.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_contrast_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function panel_contrast_luminance($hex)
{
	$hex = ltrim($hex, '#');
	$sum = 0;

	foreach (array(0 => 0.2126, 2 => 0.7152, 4 => 0.0722) as $offset => $weight) {
		$c = hexdec(substr($hex, $offset, 2)) / 255;
		$c = ($c <= 0.03928) ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		$sum += $weight * $c;
	}

	return $sum;
}

function panel_contrast_ratio($a, $b)
{
	$la = panel_contrast_luminance($a);
	$lb = panel_contrast_luminance($b);

	return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

// Every light dashboard card's header icon on that card's tinted ground.
function test_panel_contrast_dashboard_header_icons()
{
	$css = panel_contrast_source('assets/css/backend.src.css');

	preg_match_all('/^\.widget\[widget-id="(\d+)"\]\s*\{[^}]*background: (#[0-9a-f]{6})/m', $css, $grounds, PREG_SET_ORDER);
	preg_match_all('/^\.widget\[widget-id="(\d+)"\]\s*\.card-header i \{ color: (#[0-9a-f]{6})/m', $css, $icons, PREG_SET_ORDER);

	$ground = array();

	foreach ($grounds as $match) {
		$ground[$match[1]] = $match[2];
	}

	pg_assert_true(count($icons) > 20, 'the card colour table is found');

	foreach ($icons as $match) {
		pg_assert_true(isset($ground[$match[1]]), 'card ' . $match[1] . ' has a ground');
		pg_assert_true(panel_contrast_ratio($match[2], $ground[$match[1]]) >= 3,
			'card ' . $match[1] . ' header icon ' . $match[2] . ' reads 3:1 on ' . $ground[$match[1]]);
	}
}

// The Workspace sidebar's part icons on the active row's tint.
function test_panel_contrast_workspace_nav_icons()
{
	$css = panel_contrast_source('assets/css/backend.src.css');

	// Each place's row (Today, More's rows) has its own tone.
	preg_match_all('/^\.ws-channel-link\[data-ws-nav="([a-z_]+)"\] > \.bi \{ color: (#[0-9a-f]{6}); \}/m', $css, $icons, PREG_SET_ORDER);

	pg_assert_true(count($icons) > 8, 'the sidebar icon tones are found');

	foreach ($icons as $match) {
		pg_assert_true(panel_contrast_ratio($match[2], '#ded6ef') >= 3,
			'the ' . $match[1] . ' icon reads 3:1 on the active row');
	}

	pg_assert_true(strpos($css, 'color: color-mix(in srgb, var(--ws-group, var(--pg-fg-2)) 60%, var(--pg-fg-emphasis));') !== false,
		'a group name is its tone pulled toward the text colour');
}

// Off and secondary states keep their colour solid.
function test_panel_contrast_states_without_opacity()
{
	$css = panel_contrast_source('assets/css/backend.src.css');
	$api = panel_contrast_source('api_settings.php');

	pg_assert_true(preg_match('/\.pg-perm\.is-off[^{]*\{[^}]*opacity/', $css) !== 1, 'switched-off permission rows are not faded');
	pg_assert_true(strpos($css, ".pg-perm.is-off .pg-perm-text b {\n  color: var(--pg-fg-2);") !== false, 'the title of an off row is secondary text');
	pg_assert_true(preg_match('/\.pg-vs-unit \{[^}]*opacity/', $css) !== 1, 'the visitor unit is not faded');
	pg_assert_true(preg_match('/\.api-chip[^{]*\{[^}]*opacity/', $api) !== 1, 'API scope chips are not faded');
	pg_assert_true(strpos($api, '.api-chip.w { border-color: var(--pg-primary); color: var(--pg-primary-fg-emphasis); }') !== false,
		'a write scope chip reads the primary ink');
}

// Coloured figures on tinted grounds use the theme's emphasis inks.
function test_panel_contrast_dashboard_inks()
{
	$css = panel_contrast_source('assets/css/backend.src.css');

	pg_assert_true(strpos($css, ".pg-brief-ws-item.is-late .pg-brief-ws-label {\n  color: var(--pg-danger-fg-emphasis);") !== false, 'Overdue reads the danger ink');
	pg_assert_true(strpos($css, ".pg-lanes .pg-filter-count.is-clear {\n  background: none;\n  color: var(--pg-success-fg-emphasis);") !== false, 'a clear lane count reads the success ink');
	pg_assert_true(strpos($css, 'background: #0a1413; --pg-accent: #14b8a6; --pg-accent-ink: #18181b; }') !== false, 'dark teal tiles carry dark ink');
}

// The two values of a light-dark() colour token of backend.src.css.
function panel_contrast_light_dark($css, $token)
{
	preg_match('/' . preg_quote($token, '/') . ': light-dark\((#[0-9a-f]{6}), (#[0-9a-f]{6})\);/', $css, $found);

	return array($found[1] ?? '', $found[2] ?? '');
}

// A over B at a share of A, both #rrggbb.
function panel_contrast_mix($a, $share, $b)
{
	$out = '#';

	foreach (array(1, 3, 5) as $offset) {
		$out .= sprintf('%02x', (int) round(hexdec(substr($a, $offset, 2)) * $share + hexdec(substr($b, $offset, 2)) * (1 - $share)));
	}

	return $out;
}

// The Workspace list's rows: a mention's pill in the accent reads 4.5:1 in
// both themes; a channel's tile keeps its icon at 3:1 on its tinted ground
// over the menu, for every colour a channel can take.
function test_panel_contrast_workspace_list_pills_and_tiles()
{
	$css = panel_contrast_source('assets/css/backend.src.css');

	pg_assert_true(strpos($css, ".ws-menu-side .ws-count.ws-count-mention {\n  background: var(--pg-accent);\n  color: var(--pg-on-accent-fill);") !== false, 'the mention pill reads the look\'s accent and its ink');

	$accent = panel_contrast_light_dark($css, '--pg-accent');
	$ink = panel_contrast_light_dark($css, '--pg-on-accent-fill');

	foreach (array(0 => 'light', 1 => 'dark') as $index => $theme) {
		pg_assert_true(panel_contrast_ratio($ink[$index], $accent[$index]) >= 4.5, 'the ' . $theme . ' mention pill reads 4.5:1');
	}

	pg_assert_true(strpos($css, '.ws-channel-link[data-ws-colored] .ws-ch-icon {' . "\n" . '  color: color-mix(in srgb, var(--ws-ch) 75%, var(--pg-fg-emphasis));') !== false, 'the tile\'s icon is the colour pulled toward the text');
	pg_assert_true(strpos($css, '.ws-menu-side .ws-channel-link[data-ws-colored] .ws-side-ch-tile {' . "\n" . '  --pg-tone: var(--ws-ch);') !== false, 'the tile\'s ground is the channel\'s colour');

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/groups.php');

	// The menu's ground, the strongest text colour and the tile's strongest
	// tint (--pg-tint-from) of each theme.
	$themes = array(
		'light' => array('#f8f8f8', '#000000', 0.17),
		'dark'  => array('#0e0e0e', '#ffffff', 0.26),
	);

	foreach (ws_palette_hexes() as $hex) {
		foreach ($themes as $theme => $values) {
			$ground = panel_contrast_mix($hex, $values[2], $values[0]);
			$icon = panel_contrast_mix($hex, 0.75, $values[1]);

			pg_assert_true(panel_contrast_ratio($icon, $ground) >= 3, $hex . ' tile reads 3:1 in the ' . $theme . ' theme');
		}
	}
}
