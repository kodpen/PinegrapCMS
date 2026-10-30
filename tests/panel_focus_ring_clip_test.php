<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for focus rings that a clipping box would cut off or that a
 * rule took away: the Word/Excel preview cells, paragraphs and scrolling
 * boxes, the section rail, the breadcrumb bar, the Site Settings chip strip,
 * the account menu's licence row, code fields, chip fields and the Create
 * Order boxes. Where a parent has overflow set the ring is drawn inset, or
 * the scrolling box gets inner room for it. A unit test cannot render CSS,
 * so these pin the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The panel stylesheet with the comments taken out.
function panel_focus_ring_clip_css()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	return preg_replace('~/\*.*?\*/~s', '', $css);
}

// The declarations of every rule whose selector list is exactly $selector,
// joined in source order.
function panel_focus_ring_clip_rule($css, $selector)
{
	$out = '';
	if (preg_match_all('/(^|[}{;])\s*' . preg_quote($selector, '/') . '\s*\{([^}]*)\}/', $css, $m)) {
		$out = implode("\n", $m[2]);
	}
	return $out;
}

// The inset 2px primary-ink ring.
function panel_focus_ring_clip_assert_inset($css, $selector)
{
	$body = panel_focus_ring_clip_rule($css, $selector);
	pg_assert_true(preg_match('/outline:\s*2px solid var\(--pg-primary-ink\);/', $body) === 1, $selector . ' draws the primary-ink ring');
	pg_assert_true(preg_match('/outline-offset:\s*-2px;/', $body) === 1, $selector . ' draws it inset');
}

function test_panel_focus_ring_clip_office_preview()
{
	$css = panel_focus_ring_clip_css();

	pg_assert_same(0, preg_match('/\.office-sheet td:focus\s*\{\s*outline:\s*none;?\s*\}/', $css), 'a focused cell keeps its ring');
	pg_assert_same(0, preg_match('/\.office-doc \[data-p\]:focus\s*\{\s*outline:\s*none;?\s*\}/', $css), 'a focused paragraph keeps its ring');
	panel_focus_ring_clip_assert_inset($css, '.office-view .office-sheet td:focus-visible,
.office-view .office-doc [data-p]:focus-visible,
.office-view .office-pane:focus-visible,
.office-view .office-doc:focus-visible');
	pg_assert_true(strpos(panel_focus_ring_clip_rule($css, '.office-sheet td.office-selected'), 'inset 0 0 0 2px var(--pg-primary-ink)') !== false, 'the selected cell is marked in primary-ink');
}

function test_panel_focus_ring_clip_scrolling_strips_have_room()
{
	$css = panel_focus_ring_clip_css();

	$nav = panel_focus_ring_clip_rule($css, '.pg-settings-nav');
	pg_assert_true(preg_match('/padding:\s*4px;/', $nav) === 1 && preg_match('/margin:\s*-4px;/', $nav) === 1, 'the section rail leaves room for the ring sides');

	$crumbs = panel_focus_ring_clip_rule($css, '.pg-breadcrumb .breadcrumb');
	pg_assert_true(preg_match('/padding:\s*4px;/', $crumbs) === 1 && preg_match('/margin:\s*-4px;/', $crumbs) === 1, 'the breadcrumb strip leaves room for the ring');

	$chips = panel_focus_ring_clip_rule($css, '.pg-toolbar .pg-chips');
	pg_assert_true(preg_match('/padding:\s*4px;/', $chips) === 1 && preg_match('/margin-block:\s*-4px;/', $chips) === 1, 'the chip strip leaves room for the ring');
}

function test_panel_focus_ring_clip_inset_rings()
{
	$css = panel_focus_ring_clip_css();

	panel_focus_ring_clip_assert_inset($css, '#header .pg-lic-row:focus-visible');
	panel_focus_ring_clip_assert_inset($css, '.pg-code-field:focus-within');
	panel_focus_ring_clip_assert_inset($css, '.pg-sale-ig input:focus-visible,
.pg-sale-ig > .btn:focus-visible');
	panel_focus_ring_clip_assert_inset($css, '.pg-sale-step input:focus-visible');
}

function test_panel_focus_ring_clip_chip_field_joins_global_ring()
{
	$css = panel_focus_ring_clip_css();

	pg_assert_true(preg_match('/\.form-adorn:has\(> \.form-ghost:focus-visible\),\s*\.chip-input\.pg-chips:has\(> \.form-ghost:focus-visible\)\s*\{\s*outline:\s*2px solid var\(--pg-primary-ink\);/', $css) === 1, 'a chip field wears the global ring');
}
