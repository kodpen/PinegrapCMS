<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the visual editor's palette lists from the keyboard: every
 * Components, Blocks, Recently Used, Shared and System row is a focusable
 * button that Enter or Space inserts, the foldable headers carry a real
 * toggle button with aria-expanded, the search boxes and their clear buttons
 * are named, the bulk-select box is a reachable Bootstrap 6 check, the
 * description card follows focus and focused controls show a ring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_palette_keyboard_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

// The body of a top-level `function <name>(` in style_designer.js, up to the
// next four-space-indented closing brace.
function designer_palette_keyboard_fn($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	if ($start === false) {
		return '';
	}
	$end = strpos($source, "\n    }\n", $start);
	return (string) substr($source, $start, $end === false ? 4000 : $end - $start);
}

function test_designer_palette_keyboard_rows_are_focusable_buttons()
{
	$source = designer_palette_keyboard_source('assets/js/style_designer.js');

	pg_assert_contains('<div class="sd-drag-item\' + (extraClass ? \' \' + extraClass : \'\') + \'" draggable="true" tabindex="0" role="button"', $source, 'palette rows (components, blocks, recents)');
	pg_assert_contains('<div class="sd-drag-item sd-shared-drag-item" draggable="true" tabindex="0" role="button"', $source, 'shared rows');
	pg_assert_true(substr_count($source, '<div class="sd-drag-item sd-system-drag-item sd-system-new-item" draggable="true" tabindex="0" role="button"') === 2, 'both kinds of System New rows');
	pg_assert_contains('<div class="sd-drag-item sd-system-drag-item sd-system-existing-item" draggable="true" tabindex="0" role="button"', $source, 'existing System rows');
	pg_assert_true(strpos($source, 'class="sd-drag-item') === false || substr_count($source, 'class="sd-drag-item') === substr_count($source, 'tabindex="0" role="button"'), 'no palette row is left without tabindex and role');
}

function test_designer_palette_keyboard_enter_space_insert()
{
	$source = designer_palette_keyboard_source('assets/js/style_designer.js');
	$fn = designer_palette_keyboard_fn($source, '_sdWirePaletteKeys');

	pg_assert_true($fn !== '', '_sdWirePaletteKeys exists');
	pg_assert_contains("e.key === 'Enter' || e.key === ' '", $fn, 'Enter and Space');
	pg_assert_contains("new MouseEvent('dblclick'", $fn, 'the row\'s own double-click insert runs');
	pg_assert_contains("new MouseEvent('click'", $fn, 'select mode picks the row');
	pg_assert_contains('e.stopPropagation()', $fn, 'a focused row keeps Delete and N away from the canvas shortcuts');
	pg_assert_contains("_sdWirePaletteKeys(panel)", $source, 'wired on the left panel');
}

function test_designer_palette_keyboard_headers_are_toggle_buttons()
{
	$source = designer_palette_keyboard_source('assets/js/style_designer.js');

	pg_assert_contains('<div class="sd-category-header"><button type="button" class="sd-category-toggle" aria-expanded="true">', $source, 'Components and Blocks category headers');
	pg_assert_contains("'<div class=\"sd-category-header\"><button type=\"button\" class=\"sd-category-toggle\" aria-expanded=\"true\"><span class=\"bi bi-chevron-down\"></span>' + esc(_sdT('Recently Used'))", $source, 'Recently Used header');
	pg_assert_true((bool) preg_match('/sd-pal-group-toggle"[^\n]*\n[^\n]*<button type="button" class="sd-category-toggle" aria-expanded="\' \+ \(open \? \'true\' : \'false\'\)/', $source), 'Site-wide toggle');
	pg_assert_contains("function _sdPalSyncExpanded(cat)", $source, 'aria-expanded follows the fold');
	pg_assert_true(substr_count($source, '_sdPalSyncExpanded(') >= 3, 'synced on click and on search');
	pg_assert_contains('_paletteToggleFocus', $source, 'focus returns to the Site-wide toggle after it redraws');
}

function test_designer_palette_keyboard_search_named()
{
	$source = designer_palette_keyboard_source('assets/js/style_designer.js');

	pg_assert_contains("id=\"sd-search\" placeholder=\"' + esc(_sdT('Search...')) + '\" aria-label=\"' + esc(_sdT('Search components')) + '\"", $source, 'components search');
	pg_assert_contains("id=\"sd-blocks-search\" placeholder=\"' + esc(_sdT('Search...')) + '\" aria-label=\"' + esc(_sdT('Search blocks')) + '\"", $source, 'blocks search');
	pg_assert_contains("id=\"sd-search-clear\" aria-label=\"' + esc(_sdT('Clear search')) + '\"", $source, 'components clear');
	pg_assert_contains("id=\"sd-blocks-search-clear\" aria-label=\"' + esc(_sdT('Clear search')) + '\"", $source, 'blocks clear');
	pg_assert_contains("class=\"sd-recent-clear-btn\" title=\"' + esc(_sdT('Clear')) + '\" aria-label=\"' + esc(_sdT('Clear')) + '\"", $source, 'Recently Used clear');
}

function test_designer_palette_keyboard_bulk_check_is_v6_and_reachable()
{
	$source = designer_palette_keyboard_source('assets/js/style_designer.js');

	pg_assert_true(strpos($source, "'form-check-input sd-bulk-check'") === false, 'no v5 form-check-input');
	pg_assert_contains("box.className = 'check sd-bulk-check';", $source, 'v6 check class');
	pg_assert_true(strpos($source, 'box.tabIndex = -1;') === false, 'the box is reachable');
}

function test_designer_palette_keyboard_tip_on_focus()
{
	$source = designer_palette_keyboard_source('assets/js/style_designer.js');
	$fn = designer_palette_keyboard_fn($source, '_sdPalTipWire');

	pg_assert_contains("root.addEventListener('focusin'", $fn, 'card on focus');
	pg_assert_contains("root.addEventListener('focusout'", $fn, 'card hides on blur');
}

function test_designer_palette_keyboard_focus_ring()
{
	$css = designer_palette_keyboard_source('assets/css/style_designer.css');

	pg_assert_contains('.sd-drag-item:focus-visible', $css, 'row ring');
	pg_assert_contains('.sd-category-toggle:focus-visible', $css, 'header ring');
	pg_assert_contains('.sd-search-clear:focus-visible', $css, 'clear ring');
}
