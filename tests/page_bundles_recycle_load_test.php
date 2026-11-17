<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Release bundles reach the Recycle Bin from their own module: a published
 * bundle bins its used replacements (pg_recycle_page()), and an add that lost
 * a race deletes the copy it made (pg_delete_page_record()). Those functions
 * and what they call live in the explorer's files, which the panel's screens
 * load through view_folder_and_files_f.php. The module loads them itself
 * (pg_page_bundle_load_recycle()); this test loads them the same way and
 * checks every function those paths reach is there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

function test_page_bundle_recycle_functions_loaded()
{
	pg_page_bundle_load_recycle();

	foreach (array('pg_recycle_page', 'pg_delete_page_record', 'pg_recycle_ready', 'pg_recycle_folder_id', 'pg_explorer_root_folder_id') as $function) {
		pg_assert_true(function_exists($function), $function . ' is loaded');
	}
}

// Every function of the module that bins or deletes a page loads the bin
// first, so no caller has to know.
function test_page_bundle_recycle_callers_load_it()
{
	foreach (array('pg_page_bundle_publish_locked', 'pg_page_bundle_add_copy') as $function) {
		$code = pg_test_function_source($function);
		$load = strpos($code, 'pg_page_bundle_load_recycle()');
		$call = max((int) strpos($code, 'pg_recycle_page('), (int) strpos($code, 'pg_delete_page_record('));

		pg_assert_true($call > 0, $function . ' reaches the bin');
		pg_assert_true(($load !== false) && ($load < $call), $function . ' loads the bin before it');
	}

	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/page_bundles.php'), "/includes/explorer/recycle_bin.php'") === false,
		'the module never loads recycle_bin.php alone');
}
