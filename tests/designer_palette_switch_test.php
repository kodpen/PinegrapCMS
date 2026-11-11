<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the visual editor's Components tab: one tab title holds
 * the Components and Blocks lists behind a two-option switch drawn by the
 * rules of the panel's view switch, with tab roles, arrow keys, a roving
 * tab stop and the list last chosen remembered.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_palette_switch_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of a top-level `function <name>(` in style_designer.js, up to the
// next four-space-indented closing brace.
function designer_palette_switch_fn($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    }\n", $start);
	return (string) substr($source, $start, $end === false ? 4000 : $end - $start);
}

function test_designer_palette_switch_blocks_live_in_the_components_tab()
{
	$source = designer_palette_switch_source('assets/js/style_designer.src.js');

	pg_assert_true(strpos($source, 'data-tab="blocks"') === false, 'the tab strip has no Blocks tab');
	pg_assert_true(strpos($source, "tab === 'blocks'") === false, 'the tab switcher no longer shows a Blocks pane');
	pg_assert_contains("blocks: 'bi-magic'", designer_palette_switch_fn($source, '_sdPalSwitchHTML'), 'the Blocks option keeps its icon');
	pg_assert_true(strpos($source, "var _SD_RAIL_ICONS = { components: 'bi-puzzle', shared: 'bi-puzzle-fill', system: 'bi-cpu' };") !== false, 'the rail has no Blocks button');

	$markup = substr($source, (int) strpos($source, '<div id="sd-tab-components" class="sd-tab-pane">'), 3000);
	pg_assert_contains("_sdPalSwitchHTML()", $markup, 'the switch heads the Components tab');
	pg_assert_contains('<div id="sd-pal-components" class="sd-pal-list" role="tabpanel" aria-labelledby="sd-pal-sw-components">', $markup, 'components list panel');
	pg_assert_contains('<div id="sd-pal-blocks" class="sd-pal-list" role="tabpanel" aria-labelledby="sd-pal-sw-blocks" hidden>', $markup, 'blocks list panel, hidden at first');
}

function test_designer_palette_switch_is_an_accessible_tab_list()
{
	$source = designer_palette_switch_source('assets/js/style_designer.src.js');
	$html = designer_palette_switch_fn($source, '_sdPalSwitchHTML');
	$wire = designer_palette_switch_fn($source, '_sdPalSwitchWire');

	pg_assert_contains('class="sd-pal-switch" role="tablist"', $html, 'tab list role');
	pg_assert_contains("aria-label=\"' + esc(_sdT('Palette list')) + '\"", $html, 'the list is named');
	pg_assert_contains('role="tab"', $html, 'options are tabs');
	pg_assert_contains("aria-controls=\"sd-pal-' + k + '\"", $html, 'each option names its list');
	pg_assert_contains("_sdT('Components')", $html, 'Components option');
	pg_assert_contains("_sdT('Blocks')", $html, 'Blocks option');

	pg_assert_true($wire !== '', '_sdPalSwitchWire exists');
	foreach (array("'ArrowLeft'", "'ArrowRight'", "'Home'", "'End'") as $key) {
		pg_assert_contains($key, $wire, 'key ' . $key);
	}
	$show = designer_palette_switch_fn($source, '_sdPalSwitchShow');
	pg_assert_contains("setAttribute('aria-selected'", $show, 'aria-selected follows the choice');
	pg_assert_contains("setAttribute('tabindex', on ? '0' : '-1')", $show, 'roving tab stop');
	pg_assert_contains('.hidden = !on', $show, 'only the chosen list shows');
}

function test_designer_palette_switch_remembers_the_list()
{
	$source = designer_palette_switch_source('assets/js/style_designer.src.js');
	$show = designer_palette_switch_fn($source, '_sdPalSwitchShow');
	$wire = designer_palette_switch_fn($source, '_sdPalSwitchWire');

	pg_assert_contains("localStorage.setItem(_SD_PAL_LIST_KEY, which)", $show, 'the choice is stored');
	pg_assert_contains("localStorage.getItem(_SD_PAL_LIST_KEY)", $wire, 'the choice is read back');
	pg_assert_contains("var _SD_PAL_LIST_KEY = 'pinegrap_designer_palette_list';", $source, 'storage key');
}

function test_designer_palette_switch_follows_the_panel_switch_rules()
{
	$css = designer_palette_switch_source('assets/css/style_designer.css');

	pg_assert_contains('.sd-pal-switch {', $css, 'switch frame rule');
	pg_assert_contains('.sd-pal-switch-btn[aria-selected="true"] {', $css, 'chosen option rule');
	pg_assert_contains('@media (forced-colors: active)', $css, 'forced colours keep the chosen option visible');
	pg_assert_contains('.sd-pal-switch-btn:focus-visible', $css, 'focus ring');
}
