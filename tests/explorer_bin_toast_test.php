<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Delete toasts of the File Manager and the Catalog Manager
 * (view_folders.php): the Undo toast counts what the server moved to the
 * bin, not the selection, and when some items stayed the server's reasons
 * are shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function pg_test_bin_toast_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folders.php');
}

// The helper reads the server's own count and its refusal message.
function test_bin_toast_counts_what_the_server_moved()
{
	$source = pg_test_bin_toast_source();
	$start = strpos($source, 'function binToast(response, selectedCount, entry, catalog) {');
	pg_assert_true($start !== false, 'helper found');
	$body = substr($source, $start, 900);

	pg_assert_contains('var moved = Number(catalog ? response.count : response.binned);', $body);
	pg_assert_contains("(response.status === 'partial') || (Number(response.skipped) > 0)", $body);
	pg_assert_contains('undoToast(response.message, entry);', $body, 'the catalog message is the Undo text');
	pg_assert_contains('undoToast(fmt(L.moved_to_bin_count, [moved]), entry);', $body);
	pg_assert_contains('toast(response.message || L.request_failed, false);', $body, 'the reasons as a danger toast');
}

// No Delete path counts the selection any more: the two File Manager
// requests and the Catalog Manager request all go through the helper.
function test_bin_toast_replaces_the_selection_count()
{
	$source = pg_test_bin_toast_source();

	pg_assert_false(strpos($source, 'fmt(L.moved_to_bin_count, [payload.length])') !== false, 'no payload.length count');
	pg_assert_false(strpos($source, 'fmt(L.moved_to_bin_count, [items.length])') !== false, 'no items.length count');
	pg_assert_same(3, substr_count($source, 'binToast(response, ') - substr_count($source, 'function binToast(response, '), 'three Delete paths');
	pg_assert_same(1, substr_count($source, "redo: { type: 'catalog_recycle', items: payload }\n                }), true);"), 'the catalog path');
}
