<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the recycle bin name helpers (includes/explorer/recycle_names.php):
 * reading back the name a parked row had, and where the explorer loads them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

// The helpers live in their own file, loaded by the explorer's functions.
function test_recycle_bin_names_live_in_their_own_file()
{
	$names = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_names.php');

	foreach (array('pg_recycle_parked_original', 'pg_recycle_drop_home', 'pg_recycle_park_name', 'pg_recycle_release_name', 'pg_recycle_purge_if_due') as $function) {
		pg_assert_contains('function ' . $function . '(', $names, $function);
	}

	pg_assert_contains("if (!defined('PG_FUNCTIONS_DIR')) {", $names, 'gate');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_names.php');", (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php'), 'loaded by the explorer');
}

// A parked name gives back the original only for its own row.
function test_recycle_bin_names_parked_original()
{
	pg_assert_same('pgbin_', PG_RECYCLE_PARK_PREFIX, 'prefix');
	pg_assert_same('index.html', pg_recycle_parked_original('pgbin_12_index.html', 12), 'parked');
	pg_assert_same('a_b.png', pg_recycle_parked_original('pgbin_7_a_b.png', '7'), 'underscore in the name, id as string');
	pg_assert_same('', pg_recycle_parked_original('pgbin_12_index.html', 13), 'another row');
	pg_assert_same('', pg_recycle_parked_original('index.html', 12), 'not parked');
	pg_assert_same('', pg_recycle_parked_original(null, 12), 'no name');
}
