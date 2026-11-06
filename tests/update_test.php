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

// Refused with checks off, without a version and with an older one; the same
// version repairs, a newer one repairs and updates.
function test_update_repair_decision()
{
	$off = pg_update_repair_decision('2026.4.8', '2026.4.8', false);

	pg_assert_false($off['ok'], 'checks off');
	pg_assert_contains('SOFTWARE_UPDATE_CHECK', $off['message']);

	$none = pg_update_repair_decision('', '2026.4.8', true);

	pg_assert_false($none['ok'], 'no version');

	$garbage = pg_update_repair_decision('<html>', '2026.4.8', true);

	pg_assert_false($garbage['ok'], 'not a version');

	$older = pg_update_repair_decision('2026.4.7', '2026.4.8', true);

	pg_assert_false($older['ok'], 'older');
	pg_assert_contains('2026.4.7', $older['message']);
	pg_assert_contains('2026.4.8', $older['message']);

	$same = pg_update_repair_decision(' 2026.4.8 ', '2026.4.8', true);

	pg_assert_true($same['ok'], 'same');
	pg_assert_false($same['newer'], 'same is not newer');
	pg_assert_contains('2026.4.8', $same['message']);

	$newer = pg_update_repair_decision('2026.4.10', '2026.4.9', true);

	pg_assert_true($newer['ok'], 'newer');
	pg_assert_true($newer['newer'], 'newer is newer');
	pg_assert_contains('2026.4.10', $newer['message']);

	// A string comparison would put 2026.4.10 after 2026.11.0.
	$calver = pg_update_repair_decision('2026.11.0', '2026.4.10', true);

	pg_assert_true($calver['newer'], '2026.11.0 is newer than 2026.4.10');

	$behind = pg_update_repair_decision('2026.4.10', '2026.11.0', true);

	pg_assert_false($behind['ok'], '2026.4.10 is older than 2026.11.0');
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
	$request = strpos($source, 'pg_curl_run(');

	pg_assert_true($off !== false && $request !== false && $off < $request, 'checks_off before the request');
	pg_assert_contains("\$answer['ok']", $source, 'the status is read');
	pg_assert_false(strpos($source, 'daily software update check') !== false, 'no "daily" wording');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/software_update.php');

	pg_assert_false(strpos($screen, 'log_activity(lang(\'software update check') !== false, 'the screen does not log the check a second time');
	pg_assert_contains('h($new_version)', $screen, 'the server version is escaped');
}

// Whether the update server's version is newer than the installed one. A
// version of fewer than three parts (the 2026 release, a cut-short answer)
// is compared as well, without a warning; anything that is not digits and
// dots offers nothing.
function test_update_version_newer()
{
	pg_assert_true(pg_update_version_newer('2026.11.1', '2026.11.0'), 'patch');
	pg_assert_true(pg_update_version_newer(' 2026.11.0 ', '2026.4.10'), 'month 11 after month 4');
	pg_assert_false(pg_update_version_newer('2026.4.10', '2026.11.0'), 'older');
	pg_assert_false(pg_update_version_newer('2026.11.0', '2026.11.0'), 'same');
	pg_assert_true(pg_update_version_newer('2026.4.8', '2026'), 'from the 2026 release');
	pg_assert_false(pg_update_version_newer('2026', '2026.4.8'), 'a two-digit answer is older');
	pg_assert_false(pg_update_version_newer('', '2026.4.8'), 'no version');
	pg_assert_false(pg_update_version_newer('<html>', '2026.4.8'), 'not a version');
	pg_assert_false(pg_update_version_newer('2026.11.0-beta1', '2026.4.8'), 'a tag is not a version');
	pg_assert_false(pg_update_version_newer(array('2027.1.0'), '2026.4.8'), 'a list is not a version');
}

// The update steps' check refuses before asking the server while update
// checks are off in data/config.php, on the api2 path as on the Kodpen API
// path, and both version checks compare through pg_update_version_newer().
function test_update_check_step_source()
{
	$steps = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/software.php');
	$checks_off = strpos($steps, "pg_update_check_error_text(array('error' => 'checks_off'))");
	$api2_ask = strpos($steps, '$server = pg_update_server_version();');

	pg_assert_true($checks_off !== false, 'the check step names the checks-off reason');
	pg_assert_true(($api2_ask !== false) && ($checks_off < $api2_ask), 'refused before the api2 request');
	pg_assert_contains('pg_update_version_newer($server[\'version\'], VERSION)', $steps, 'the check step compares with the helper');
	pg_assert_false(strpos($steps, '$new_version_parts') !== false, 'no hand-made comparison in the steps');

	$daily = (string) file_get_contents(PG_FUNCTIONS_DIR . '/software_update_check.php');

	pg_assert_contains('pg_update_version_newer(', $daily, 'the daily check compares with the helper');
	pg_assert_false(strpos($daily, '$new_version_parts') !== false, 'no hand-made comparison in the daily check');
}
