<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for panel drawers and boxes that load their content: the ERP
 * record drawer, the Create Order account and Recent sales drawers, the
 * Audit Trail changes fold and the Update Search Index page tell a screen
 * reader that they are loading and, where a run ends, that it ended.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function loading_announced_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_loading_announced_drawer_spinners()
{
	// A status spinner carries its own text; aria-hidden would take it out
	// of the accessibility tree.
	$spinner = '<span class="spinner-border fg-secondary" style="font-size:2rem" role="status"><span class="visually-hidden"></span></span>';

	foreach (array('assets/js/add_order.js' => 2, 'assets/js/erp_lists.js' => 1) as $path => $count) {
		$source = loading_announced_source($path);
		pg_assert_same($count, substr_count($source, $spinner), $path);
		pg_assert_true(strpos($source, 'aria-hidden="true"></span></div>') === false, $path . ': hidden spinner');
	}

	pg_assert_contains("'loading' => lang('Loading...'),", loading_announced_source('add_order.php'));
	pg_assert_contains('t.loading', loading_announced_source('assets/js/add_order.js'));
}

function test_loading_announced_erp_drawer_name()
{
	// The drawer is named by its title, so the title reads the loading text
	// (and the dialog is busy) until the record arrives.
	pg_assert_contains('data-erp-drawer-loading="\' . h(lang(\'Loading...\')) . \'"', loading_announced_source('includes/erp/drawer.php'));

	$source = loading_announced_source('assets/js/erp_lists.js');
	pg_assert_contains("title.textContent = loading;", $source);
	pg_assert_contains("panel.setAttribute('aria-busy', 'true');", $source);
	pg_assert_contains("panel.removeAttribute('aria-busy');", $source);

	$order = loading_announced_source('assets/js/add_order.js');
	pg_assert_contains("title.textContent = t.loading;", $order);
	pg_assert_contains("panel.setAttribute('aria-busy', 'true');", $order);
	pg_assert_contains("panel.removeAttribute('aria-busy');", $order);
}

function test_loading_announced_audit_fold()
{
	$source = loading_announced_source('view_audit_log.php');

	pg_assert_true(strpos($source, 'body.textContent = "…";') === false, 'Bare ellipsis while the changes load');
	pg_assert_contains('data-pg-audit-loading="\' . h(lang(\'Loading...\')) . \'"', $source);
	pg_assert_contains('body.setAttribute("aria-busy", "true");', $source);
}

function test_loading_announced_search_index()
{
	$source = loading_announced_source('update_search_index.php');

	// The sentence sits inside the status region, not beside an empty one.
	pg_assert_true(strpos($source, 'flex-shrink-0" role="status"></span>') === false, 'Empty status spinner');
	pg_assert_contains('<span id="start" class="navbar-text d-flex align-items-center gap-3 small" role="status">', $source);

	// The end of the run is written into a live region printed early.
	pg_assert_contains('<span id="finish_status" class="visually-hidden" role="status"></span>', $source);
	pg_assert_contains('$("#finish_status").text(', $source);
}
