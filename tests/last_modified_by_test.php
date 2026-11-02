<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Pins the Last Modified cell of the Countries, Form Fields and Files lists:
 * each prints "by {name}" through pg_list_when(), which leaves the phrase out
 * when the editor's user row is missing, so no cell ends in a dangling "by".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The list screens whose Last Modified cell names the editor.
function last_modified_by_files()
{
	return array(
		'view_countries.php' => "pg_list_when(\$timestamp, \$username)",
		'view_fields.php' => "pg_list_when(\$timestamp, \$username)",
	);
}

function test_last_modified_by_lists_use_the_guarded_cell()
{
	foreach (last_modified_by_files() as $file => $call) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_same(false, strpos($source, "'by {var:1}'"), $file . ' prints "by {var:1}" without a guard');
		pg_assert_contains($call, $source, $file);
	}
}

function test_last_modified_by_unknown_user_has_no_by()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_paging.php');
	if (!defined('TIME_FORMAT')) {
		define('TIME_FORMAT', 'twenty_four_hours');
	}
	if (!defined('DATE_FORMAT')) {
		define('DATE_FORMAT', 'day_month');
	}
	$when = get_relative_time(array('timestamp' => 1760000000));
	pg_assert_same($when, pg_list_when(1760000000, null), 'a missing user leaves the time alone');
	pg_assert_same($when, pg_list_when(1760000000, ''), 'an empty name leaves the time alone');
}
