<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the copies DataTables and FixedHeader make of a panel list's
 * heading (table.chart, scrollX and fixedHeader in backend.src.js). The
 * sizing copy in the body table keeps its text for screen readers, which take
 * the column headers from it, but its links and boxes leave the Tab order;
 * while the heading floats, the floating table is no longer aria-hidden and
 * the placeholder left in place is inert. The vendored files stay as shipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_heading_copies_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$from = strpos($source, 'function pgListHeadingCopies(api) {');
	$to = ($from === false) ? false : strpos($source, "\n}\n", $from);

	return ($to === false) ? '' : substr($source, $from, $to - $from);
}

function test_list_heading_copies_leave_the_tab_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$body = list_heading_copies_body();

	pg_assert_true($body !== '', 'pgListHeadingCopies() found');
	pg_assert_contains("datatable.on('draw.dt column-sizing.dt fixedheader-mode.dt', function (e) {", $source, 'it runs after each draw, column sizing and heading mode change');
	pg_assert_contains('pgListHeadingCopies(e.dt);', $source, 'per table, through the event');
	pg_assert_contains("$(api.table().node()).children('thead').not(header)", $body, 'the body table\'s sizing copy');
	pg_assert_contains(".find('a[href], button, input, select, textarea, [tabindex]').attr('tabindex', '-1');", $body, 'its focusable parts leave the Tab order');
	pg_assert_contains(".find('button, input, select, textarea').attr('aria-hidden', 'true');", $body, 'its boxes are hidden from screen readers');
	pg_assert_same(false, strpos($body, "copy.attr('inert'"), 'the sizing copy itself is not inert');
	pg_assert_same(false, strpos($body, "copy.attr('aria-hidden'"), 'the sizing copy itself stays readable');
}

function test_list_heading_copies_floating_heading_is_perceivable()
{
	$body = list_heading_copies_body();

	pg_assert_contains("var holder = $(header).closest('table');", $body, 'the table that holds the real heading');
	pg_assert_contains("holder.removeAttr('aria-hidden');", $body, 'is not aria-hidden while it floats');
	pg_assert_contains("$(api.table().container()).find('div.dt-scroll-head thead').not(header)", $body, 'the placeholder left in place');
	pg_assert_contains(".attr('inert', '')", $body, 'is inert');
}

function test_list_heading_copies_vendored_files_unchanged()
{
	$dir = PG_FUNCTIONS_DIR . '/assets/lib/DataTables/';

	pg_assert_same('119ae2bf2e007ccd696b4c86efc502932806c52d', sha1_file($dir . 'datatables.js'), 'datatables.js is the shipped DataTables 2.3.7 / FixedHeader 4.0.6 build');
}
