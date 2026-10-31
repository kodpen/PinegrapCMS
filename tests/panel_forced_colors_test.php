<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel's forced-colours block (Windows High Contrast):
 * one @media (forced-colors: active) block inside @layer custom of
 * backend.src.css, drawing in system colours the states that the panel and
 * Bootstrap 6 show only by a fill, a ::before background or mask, or a
 * shadow ring.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// backend.src.css with the comments taken out.
function panel_forced_colors_test_css()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	return preg_replace('~/\*.*?\*/~s', '', $css);
}

// The body of the block that opens at $offset, up to its matching brace.
function panel_forced_colors_test_body($css, $offset)
{
	$start = strpos($css, '{', $offset);
	if ($start === false) {
		return '';
	}
	$depth = 0;
	$length = strlen($css);
	for ($i = $start; $i < $length; $i++) {
		if ($css[$i] === '{') {
			$depth++;
		} elseif ($css[$i] === '}') {
			$depth--;
			if ($depth === 0) {
				return substr($css, $start + 1, $i - $start - 1);
			}
		}
	}
	return '';
}

function panel_forced_colors_test_block()
{
	$css = panel_forced_colors_test_css();
	$at = strpos($css, '@media (forced-colors: active)');
	return $at === false ? '' : panel_forced_colors_test_body($css, $at);
}

function test_panel_forced_colors_one_block_in_custom_layer()
{
	$css = panel_forced_colors_test_css();

	pg_assert_same(1, substr_count($css, '@media (forced-colors: active)'), 'one forced-colours block');

	$layer = strpos($css, '@layer custom {');
	pg_assert_true($layer !== false, 'the custom layer opens');
	$custom = panel_forced_colors_test_body($css, $layer);
	pg_assert_true(strpos($custom, '@media (forced-colors: active)') !== false, 'the block sits inside @layer custom');
}

function test_panel_forced_colors_controls_draw_their_state()
{
	$block = panel_forced_colors_test_block();

	$expect = array(
		'tick, dot and knob' => '/\.check::before,\s*\.radio::before,\s*\.switch::before\s*\{[^}]*background-color:\s*CanvasText;/',
		'checked switch track' => '/\.switch:has\(input:checked\)\s*\{[^}]*background-color:\s*Highlight;/',
		'checked switch knob' => '/\.switch:has\(input:checked\)::before\s*\{[^}]*background-color:\s*HighlightText;/',
		'close button X' => '/\.btn-close\s*\{[^}]*background-color:\s*ButtonText;/',
		'progress track frame' => '/\.progress,\s*\.progress-stacked\s*\{[^}]*border:\s*1px solid CanvasText;/',
		'progress bar fill' => '/\.progress-bar\s*\{[^}]*background-color:\s*Highlight;/',
		'stepper disc frame' => '/\.stepper-item::before\s*\{[^}]*border:\s*1px solid CanvasText;/',
		'reached step fill' => '/\.stepper-item\.active::before\s*\{[^}]*background-color:\s*Highlight;/',
		'current step ring' => '/\.stepper-item\[aria-current="step"\]::before\s*\{[^}]*outline:/',
		'role card and chips focus' => '/\.btn-check:focus-visible \+ \.pg-role-card,\s*\.pg-chips:focus-within\s*\{[^}]*outline:\s*2px solid Highlight;/',
	);
	foreach ($expect as $what => $pattern) {
		pg_assert_same(1, preg_match($pattern, $block), $what);
	}
}

function test_panel_forced_colors_on_states_take_highlight()
{
	$block = panel_forced_colors_test_block();

	pg_assert_same(1, preg_match('/:is\(([^)]*(?:\([^)]*\)[^)]*)*)\)\s*\{([^}]*)\}/', $block, $match), 'the on-state rule');
	pg_assert_true(strpos($match[2], 'forced-color-adjust: none;') !== false, 'on states keep their colours');
	pg_assert_true(strpos($match[2], 'background-color: Highlight;') !== false, 'on states fill with Highlight');
	pg_assert_true(strpos($match[2], 'color: HighlightText;') !== false, 'on states write in HighlightText');

	// Each screen rule that draws a current or pressed item by fill alone.
	$selectors = array(
		'.nav-link.active',
		'.active > .page-link',
		'.list-group-item.active',
		'.menu-item.active',
		'input.btn-check:checked + .btn',
		'.btn-text.theme-secondary.active',
		'#header .pg-um-themebtn.active',
		'.pg-filter[aria-pressed="true"]',
		'button.pg-vs-cell.is-active',
		'.pg-sale-switch .btn.active',
		'.pg-sale-method[aria-pressed="true"]',
		'.ws-rich-tools .btn-text.theme-secondary[aria-pressed="true"]',
		'.ws-format-tool.active',
		'.btn-check:checked + .pg-role-card',
	);
	foreach ($selectors as $selector) {
		pg_assert_true(strpos($match[1], $selector) !== false, $selector . ' is in the on-state list');
	}
}
