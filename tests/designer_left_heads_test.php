<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the heads of the visual editor's left column: the
 * palette's tab holder is its head (no title row above it), its tabs are
 * words with the rail's icon kept for a narrow column, the Overview's name
 * and its Tree / Notes pair share one row, and nothing there is written in
 * capitals.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_left_heads_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The CSS block that starts with $selector, up to its closing brace.
function designer_left_heads_rule($css, $selector)
{
	$at = strpos($css, $selector . ' {');

	if ($at === false) {
		return '';
	}

	return substr($css, $at, strpos($css, '}', $at) - $at + 1);
}

function test_designer_left_heads_palette_head_is_the_tab_holder()
{
	$js = designer_left_heads_source('assets/js/style_designer.src.js');

	pg_assert_true(strpos($js, "'<div class=\"sd-panel-header\"><span class=\"bi bi-puzzle me-1\"></span>' + esc(_sdT('Components')) + '</div>'") === false, 'the title row Components is gone');
	pg_assert_contains("'<div class=\"sd-panel-header sd-pal-head\">' +\n                '<div class=\"sd-pal-tabs\" id=\"sd-panel-tabs\">'", $js, 'the tab holder is the palette head, so the dock button joins it');
	pg_assert_contains("_sdPalTabHTML('components', _sdT('Components'), true)", $js, 'Components tab, open');
	pg_assert_contains("_sdPalTabHTML('shared', _sdT('Shared'), false)", $js, 'Shared tab');
	pg_assert_contains("_sdPalTabHTML('system', _sdT('System'), false)", $js, 'System tab');
	pg_assert_contains("(_SD_RAIL_ICONS[key] || 'bi-grid') + ' sd-pal-tab-ic\" aria-hidden=\"true\"></span>'", $js, 'a tab carries the rail icon for a narrow column');
	pg_assert_contains("'\" title=\"' + esc(label) + '\">'", $js, 'the word stays in the tip');
	pg_assert_contains("_sdAddDockToggle(leftPanel ? leftPanel.querySelector('.sd-panel-header') : null, 'left');", $js, 'the dock button goes to the first head, the tab holder');
}

function test_designer_left_heads_palette_tabs_css()
{
	$css = designer_left_heads_source('assets/css/style_designer.css');

	$head = designer_left_heads_rule($css, '.sd-panel-header.sd-pal-head');
	pg_assert_contains('min-height: 40px;', $head, 'a 40 px row');
	pg_assert_contains('text-transform: none;', $head, 'no capitals');
	pg_assert_contains('container-type: inline-size;', $head, 'the head answers the column width');
	pg_assert_contains('height: 32px;', designer_left_heads_rule($css, '.sd-pal-tabs'), 'the holder is 32 px');
	pg_assert_contains('font-size: 12.5px;', designer_left_heads_rule($css, '#sd-panel-tabs .sd-panel-tab'), 'tabs are 12.5 px');
	pg_assert_contains("@container (max-width: 229.98px) {\n    #sd-panel-tabs .sd-panel-tab:not(.active) { flex: none; width: 30px; padding: 0; }", $css, 'under 230 px a tab not open is its icon');
}

function test_designer_left_heads_overview_one_row()
{
	$js = designer_left_heads_source('assets/js/style_designer.src.js');
	$css = designer_left_heads_source('assets/css/style_designer.css');

	pg_assert_contains("'<button type=\"button\" class=\"sd-ov-name\" id=\"sd-ov-name\" aria-expanded=\"true\" aria-controls=\"sd-ov-body\"", $js, 'the name is a button that shuts and opens the Overview');
	pg_assert_true(strpos($js, "'<div class=\"sd-panel-tabs sd-ov-subtabs\" id=\"sd-ov-subtabs\">'") === false, 'Tree / Notes is no row of its own');
	pg_assert_true(strpos($js, '<span class="sd-ov-name-txt" id="sd-overview-header-title">') < strpos($js, '<div class="sd-ov-subtabs" id="sd-ov-subtabs"'), 'the pair stands in the head row after the name');
	pg_assert_contains("if (e.target.closest && e.target.closest('#sd-ov-subtabs')) return;", $js, 'a press on Tree or Notes does not shut the Overview');
	pg_assert_contains("nameBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');", $js, 'the name tells whether the Overview is open');
	pg_assert_true(strpos($js, "hdrTitle.textContent = (sub === 'notes')") === false, 'the name stays Overview while Notes is open');

	$head = designer_left_heads_rule($css, '.sd-panel-header.sd-overview-header');
	pg_assert_contains('min-height: 40px;', $head, 'a 40 px row');
	pg_assert_contains('text-transform: none;', $head, 'no capitals');
	pg_assert_contains("@container (max-width: 229.98px) {\n    .sd-ov-name-txt { display: none; }", $css, 'under 230 px the name is its icon');
	pg_assert_true(strpos(designer_left_heads_rule($css, '.sd-ov-subtabs .sd-ov-subtab'), 'uppercase') === false, 'Tree and Notes in sentence case');
}

function test_designer_left_heads_no_capitals_in_groups()
{
	$css = designer_left_heads_source('assets/css/style_designer.css');

	pg_assert_true(strpos(designer_left_heads_rule($css, '.sd-category-header'), 'uppercase') === false, 'a palette group head in sentence case');
	pg_assert_true(strpos(designer_left_heads_rule($css, '.sd-system-header'), 'uppercase') === false, 'a System list head in sentence case');
	pg_assert_contains('color: var(--sd-text-muted);', designer_left_heads_rule($css, '.sd-pal-label'), 'Recently used is a quiet label');
}
