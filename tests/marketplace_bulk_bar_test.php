<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the marketplace products bulk bar (assets/js/marketplace_products.js):
 * hiding the bar moves the focus out of it first, and a changed selected
 * count is announced from the bar's own visible text.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function marketplace_bulk_bar_test_update_bulk()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/marketplace_products.js');
	$start = strpos($script, 'function updateBulk(');

	pg_assert_true($start !== false, 'updateBulk() is there');

	return substr($script, $start, (int) strpos($script, "\n    }\n", $start) - $start);
}

function test_marketplace_bulk_bar_hiding_moves_focus_out_first()
{
	$body = marketplace_bulk_bar_test_update_bulk();

	$contains = strpos($body, 'bulk.contains(document.activeElement)');
	$hide = strpos($body, "bulk.classList.toggle('d-none'");

	pg_assert_true($contains !== false, 'updateBulk() checks whether the bar holds the focus');
	pg_assert_true($hide !== false && $contains < $hide, 'the focus leaves the bar before it is hidden');
	pg_assert_true(strpos($body, 'selectAll') !== false && strpos($body, "querySelector('[data-mp-select]')") !== false, 'the focus goes to Select all, else the first row box');
	pg_assert_true(strpos($body, '.focus()') !== false, 'the target takes the focus');
}

function test_marketplace_bulk_bar_announces_a_changed_count()
{
	$body = marketplace_bulk_bar_test_update_bulk();

	pg_assert_true(strpos($body, 'window.pgAnnounce') !== false, 'the count is announced');
	pg_assert_true(strpos($body, 'ids.length !== announcedCount') !== false, 'only when the count changes');
	pg_assert_true(strpos($body, 'clearTimeout(announceTimer)') !== false && strpos($body, 'setTimeout(') !== false, 'behind a debounce');
	pg_assert_true(strpos($body, "bulk.querySelector('#mpp-bulk-n').parentNode.textContent") !== false, "the bar's own visible text, no new string");

	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/marketplace_products.js');

	pg_assert_true(strpos($script, 'var announcedCount = selected().length;') !== false, 'the count on load is the baseline, never announced');
}
