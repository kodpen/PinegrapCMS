<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The last step of an upgrade resets the update check in config. Its
 * columns are integers: writing '' into one logs MariaDB warning 1366 under
 * the session's non-strict sql_mode and fails under a strict one.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_upgrade_end_config_reset_writes_no_empty_string_into_integer_columns()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/runner.php');

	pg_assert_true($source !== '', 'runner.php is read');

	foreach (array('last_software_update_check_timestamp', 'software_update_available') as $column) {
		pg_assert_same(
			0,
			preg_match('/\b' . $column . "\\s*=\\s*''/", $source),
			$column . " is never set to ''"
		);
	}

	pg_assert_true(
		strpos($source, "db(\"UPDATE config SET last_software_update_check_timestamp = 0\")") !== false,
		'the update check time is reset to 0'
	);
}
