<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure helpers of includes/fn/update.php that the Repair
 * Software screen uses: reading the version history from the source of
 * versions.php, the versions the upgrade steps can be run again from, and
 * whether the update server's version allows a repair.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The real versions.php, read as text: oldest first, the 2026 entries in it,
// the open version last.
function test_update_versions_from_real_source()
{
	$versions = pg_upgrade_versions_from_source((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/versions.php'));

	pg_assert_same('2017.2', $versions[0], 'first version');
	pg_assert_contains('2026', $versions, 'the 2026 entry');
	pg_assert_same('2026.11.0', $versions[count($versions) - 1], 'last version');
	pg_assert_same(count($versions), count(array_unique($versions)), 'each version once');
	pg_assert_same($versions, pg_upgrade_versions_list(), 'the list of this installation');
}

// Only the single-quoted numbers of the returned array count: not the ones
// in comments, not a double-quoted one, and the order is kept.
function test_update_versions_from_fake_source()
{
	$source = "<?php\n"
		. "// Legacy versions (2017.2 - 2023.3.1) live in legacy.php; '2019.1' is an example.\n"
		. "if (!defined('INSTALL_OR_UPDATE')) { exit; }\n"
		. "return array(\n"
		. "\t'2020.1',\n"
		. "\t'2026', // 2025.9 was skipped\n"
		. "\t\"2026.0.5\",\n"
		. "\t'2026.1.10',\n"
		. "\t'2026.1.9',\n"
		. ");\n"
		. "// '2030.1' after the array\n";

	pg_assert_same(array('2020.1', '2026', '2026.1.10', '2026.1.9'), pg_upgrade_versions_from_source($source));

	pg_assert_same(array(), pg_upgrade_versions_from_source('<?php echo 1;'), 'no array');
}

// From the first 2026 version up to the one before the installed version,
// later years included.
function test_update_rerun_choices()
{
	$versions = array('2017.2', '2023.3.1', '2026', '2026.1', '2026.4.7', '2026.4.8');

	pg_assert_same(array('2026', '2026.1', '2026.4.7'), pg_upgrade_rerun_choices($versions, '2026.4.8'), 'from 2026.4.8');
	pg_assert_same(array('2026', '2026.1'), pg_upgrade_rerun_choices($versions, '2026.4.7'), 'from 2026.4.7');
	pg_assert_same(array(), pg_upgrade_rerun_choices($versions, '2026'), 'nothing before 2026 is offered');
	pg_assert_same(array(), pg_upgrade_rerun_choices($versions, '2099.1'), 'a version outside the list');

	// A later year stays in the list.
	pg_assert_same(array('2026', '2026.1', '2027.1'), pg_upgrade_rerun_choices(array('2023.3.1', '2026', '2026.1', '2027.1', '2027.2'), '2027.2'), 'from 2027.2');

	$real = pg_upgrade_rerun_choices(null, '2026.4.8');

	pg_assert_same('2026', $real[0], 'the real list starts at 2026');
	pg_assert_same('2026.4.7', $real[count($real) - 1], 'the real list ends before 2026.4.8');
}

// version_compare() orders the numbers the way the repair needs, also where
// a part has two digits and across the move to year.month.patch numbers.
function test_update_version_compare_orders_release_numbers()
{
	pg_assert_true(version_compare('2026.4.10', '2026.4.9', '>'), '2026.4.10 > 2026.4.9');
	pg_assert_true(version_compare('2026.11.0', '2026.4.10', '>'), '2026.11.0 > 2026.4.10');
	pg_assert_true(version_compare('2026.11.1', '2026.11.0', '>'), '2026.11.1 > 2026.11.0');
	pg_assert_true(version_compare('2027.1.0', '2026.12.9', '>'), '2027.1.0 > 2026.12.9');
	pg_assert_true(version_compare('2026.4', '2026.4.1', '<'), '2026.4 < 2026.4.1');
	pg_assert_true(version_compare('2026.4.8', '2026.4.8', '=='), 'equal');
}

// Repair Software is refused while update checks are off in
// data/config.php, with a sentence naming the setting; a release that is
// missing or older is refused by pg_vendor_release_offer().
function test_update_repair_checks_off_message()
{
	pg_assert_contains('SOFTWARE_UPDATE_CHECK', pg_update_repair_checks_off_message());

	$steps = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/software.php');

	pg_assert_contains("'message' => pg_update_repair_checks_off_message()", $steps, 'the check step refuses a repair with it');
}

// One sentence per reason a check has no answer: an error page names its
// HTTP status, a cURL failure its number, and every sentence starts with a
// capital letter.
function test_update_check_error_text()
{
	$http = pg_update_check_error_text(array('error' => 'http_error', 'http_status' => 500));

	pg_assert_contains('HTTP 500', $http, 'the status is named');

	$curl = pg_update_check_error_text(array('error' => 'curl_error', 'curl_errno' => 28, 'curl_error' => 'Operation timed out'));

	pg_assert_contains('28', $curl, 'the cURL number');
	pg_assert_contains('Operation timed out', $curl, 'the cURL message');

	foreach (array('curl_missing', 'curl_error', 'http_error', 'invalid_response', 'checks_off') as $error) {
		$text = pg_update_check_error_text(array('error' => $error));

		pg_assert_true($text !== '', $error . ' has a sentence');
		pg_assert_same(mb_strtoupper(mb_substr($text, 0, 1)), mb_substr($text, 0, 1), $error . ' starts with a capital');
		pg_assert_false(stripos($text, 'daily') !== false, $error . ' does not call a manual check daily');
	}

	pg_assert_contains('SOFTWARE_UPDATE_CHECK', pg_update_check_error_text(array('error' => 'checks_off')));
	pg_assert_same('', pg_update_check_error_text(array('error' => '')), 'no error, no sentence');
}

// With update checks off in data/config.php the manual check returns before
// it builds or sends a request, and an error page is told apart from an
// unreadable answer.
function test_update_check_source_order()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/software_update_check.php');

	$off = strpos($source, "'checks_off'");
	$request = strpos($source, 'pg_update_server_version(');

	pg_assert_true($off !== false && $request !== false && $off < $request, 'checks_off before the request');
	pg_assert_false(strpos($source, 'pg_curl_run(') !== false, 'only the Kodpen API is asked');
	pg_assert_false(strpos($source, 'daily software update check') !== false, 'no "daily" wording');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/software_update.php');

	pg_assert_false(strpos($screen, 'log_activity(lang(\'software update check') !== false, 'the screen does not log the check a second time');
	pg_assert_contains('h($new_version)', $screen, 'the server version is escaped');
}

// The update steps' check refuses before asking the server while update
// checks are off in data/config.php, and offers what the Kodpen API's
// decision offers; neither check compares versions by hand.
function test_update_check_step_source()
{
	$steps = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/software.php');
	$checks_off = strpos($steps, "if (!\$repair && defined('SOFTWARE_UPDATE_CHECK') && (SOFTWARE_UPDATE_CHECK != true)) {");
	$ask = strpos($steps, "\$server = pg_update_server_version(\$repair ? 'repair' : 'update');");

	pg_assert_true($checks_off !== false, 'the check step reads the setting');
	pg_assert_true(($ask !== false) && ($checks_off < $ask), 'refused before the request');
	pg_assert_same(1, substr_count($steps, '$server = pg_update_server_version('), 'one request');
	pg_assert_false(strpos($steps, '$new_version_parts') !== false, 'no hand-made comparison in the steps');

	$daily = (string) file_get_contents(PG_FUNCTIONS_DIR . '/software_update_check.php');

	pg_assert_contains("in_array(\$decision['outcome'], array('update', 'blocked'), true)", $daily, 'the daily check reads the decision');
	pg_assert_false(strpos($daily, '$new_version_parts') !== false, 'no hand-made comparison in the daily check');
}
