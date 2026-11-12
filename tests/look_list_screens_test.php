<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the list screens in look A: every root screen whose
 * content is a list on the table driver takes the list page width, the
 * driver's frame is the look's card, its tools are the look's buttons, and
 * its rows, headings, bar and foot read the type and tone tokens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function look_list_screens_test_source($path)
{
	$file = dirname(__DIR__) . '/pinegrap/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

/** The driver's own rules in backend.src.css, from its banner to the next. */
function look_list_screens_test_block()
{
	$css = look_list_screens_test_source('assets/css/backend.src.css');
	$start = strpos($css, '/* List table driver (assets/js/pg_list_table.js');
	$end = ($start === false) ? false : strpos($css, '/* Bookings week', $start);

	return ($start === false || $end === false) ? '' : substr($css, $start, $end - $start);
}

function test_list_screens_take_the_list_width()
{
	$root = dirname(__DIR__) . '/pinegrap/';
	$screens = array_merge(glob($root . 'view_*.php'), glob($root . 'erp_*.php'), array($root . 'mail_queue.php'));
	$checked = 0;

	foreach ($screens as $file) {
		$name = basename($file);
		$source = (string) file_get_contents($file);

		// The form designer prints a list beside its own editor.
		if ($name === 'view_fields.php' || !preg_match('/pg_list_table_(html|printed)\(/', $source)) {
			continue;
		}

		$checked++;
		pg_assert_true((bool) preg_match("/'extra classes'\\s*=>\\s*'[^']*\\bpg-measure-list\\b/", $source), $name . ' takes the list width');
	}

	pg_assert_true($checked > 50, 'the list screens were found');
}

function test_list_frame_and_tools_are_look_parts()
{
	require_once dirname(__DIR__) . '/pinegrap/includes/fn/list_table.php';

	$php = look_list_screens_test_source('includes/fn/list_table.php');
	$js = look_list_screens_test_source('assets/js/pg_list_table.js');

	pg_assert_contains('<div class="pg-list-table pg-card" data-pg-list-table="', $php, 'the frame is the look card');
	pg_assert_contains('class="pg-icon-button pg-icon-button-sm pg-list-table-columns"', $php, 'columns is an icon button');
	pg_assert_contains('class="pg-button pg-button-ghost pg-button-sm pg-list-table-details-switch"', $php, 'Details is a small ghost button');
	pg_assert_contains("'pg-button pg-button-quiet pg-button-sm pg-list-table-more'", $js, 'Show more is a small quiet button');
	pg_assert_contains("'pg-icon-button pg-icon-button-sm pg-list-table-clear'", $js, 'clear is an icon button');
	pg_assert_same(false, strpos($php . $js, 'btn btn-sm btn-text theme-secondary pg-list-table-'), 'no driver tool keeps the old button');
}

function test_list_rows_and_bar_read_the_look_tokens()
{
	$block = look_list_screens_test_block();

	pg_assert_true($block !== '', 'the driver block is found');
	pg_assert_contains("height: 38px;\n  padding: 0 14px;", $block, 'a heading row is 38 px');
	pg_assert_contains("font-size: var(--pg-text-meta);\n  font-weight: var(--pg-weight-strong);\n  color: var(--pg-fg-3);", $block, 'headings are 12 / 550 in the third text colour');
	pg_assert_contains("height: 52px;\n  max-width: var(--pg-list-table-cell-max);\n  padding: 0 14px;", $block, 'a row is 52 px');
	pg_assert_contains('min-height: 48px;', $block, 'the bar is 48 px');
	pg_assert_contains('color-mix(in srgb, var(--pg-accent) var(--pg-tint-select), transparent)', $block, 'a selected row takes the accent');
	pg_assert_contains("width: 32px !important;\n    height: 32px !important;", $block, 'a thumbnail is 32 px');
	pg_assert_same(0, preg_match('/font-size:\s*[0-9.]+(rem|px)|font-weight:\s*[0-9]+/', $block), 'no size or weight literal in the driver block');
}
