<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Recycle Bin on the external API deletes of pages and files:
 * a rehearsal stops before the bin folder is created, the real delete makes
 * it in the name of the key's owner, and the bin helper takes that owner
 * instead of reading the panel session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one function, from its declaration to the next one.
function api_recycle_test_body($file, $function)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
	$start = strpos($source, 'function ' . $function . '(');

	pg_assert_true($start !== false, $function . ' found');

	$end = strpos($source, "\nfunction ", $start + 10);

	return substr($source, $start, ($end === false) ? null : ($end - $start));
}

// The only call that may create the bin sits below the stop, and it names
// the key's owner; above the stop the bin is only looked up.
function test_api_deletes_create_the_bin_after_the_dry_run_stop()
{
	$handlers = array(
		'includes/api/resources/pages.php' => array('api_pages_delete', "api_dry_run_stop('recycled', 'page'"),
		'includes/api/resources/files.php' => array('api_files_delete', "api_dry_run_stop('recycled', 'file'"),
	);

	foreach ($handlers as $file => $handler) {
		$body = api_recycle_test_body($file, $handler[0]);
		$stop = strpos($body, $handler[1]);
		$create = strpos($body, 'pg_recycle_folder_id(true');

		pg_assert_true($stop !== false, $handler[0] . ' stops for a rehearsal');
		pg_assert_true($create !== false, $handler[0] . ' creates the bin');
		pg_assert_true($create > $stop, $handler[0] . ' creates the bin after the stop');
		pg_assert_same(1, substr_count($body, 'pg_recycle_folder_id(true'), $handler[0] . ' creates it in one place');
		pg_assert_contains("pg_recycle_folder_id(true, array('id' => (int)\$app['owner']['id'], 'username' => (string)\$app['owner']['username']))", $body, $handler[0] . ' names the owner');
		pg_assert_contains("'folder_id' => (\$bin_id > 0) ? \$bin_id : null", $body, $handler[0] . ' reports no bin yet as null');

		// A site without the bin schema is refused before the stop, so a
		// rehearsal meets the same 409 as the real call; the refusal after
		// the creation stays for a failed insert.
		$ready = strpos($body, "if (!pg_recycle_ready()) {\n");
		$refusals = array();
		$offset = 0;

		while (($at = strpos($body, "api_fail(409, 'recycle_bin_unavailable'", $offset)) !== false) {
			$refusals[] = $at;
			$offset = $at + 1;
		}

		pg_assert_true(($ready !== false) && ($ready < $stop), $handler[0] . ' checks the bin schema before the stop');
		pg_assert_same(2, count($refusals), $handler[0] . ' refuses without a bin twice');
		pg_assert_true(($refusals[0] > $ready) && ($refusals[0] < $stop), $handler[0] . ' refuses a rehearsal without the schema');
		pg_assert_true($refusals[1] > $create, $handler[0] . ' refuses a failed creation');
	}
}

// The helper logs and owns the folder as the given user; only without one
// does it fall back to the panel session, and never reads a missing key.
function test_recycle_folder_id_takes_the_creating_user()
{
	$body = api_recycle_test_body('includes/explorer/recycle_bin.php', 'pg_recycle_folder_id');

	pg_assert_contains('function pg_recycle_folder_id($create = false, $user = null)', $body, 'signature');
	pg_assert_contains("isset(\$_SESSION['sessionusername']) ? (string) \$_SESSION['sessionusername'] : ''", $body, 'session read guarded');
	pg_assert_contains("defined('USER_ID') ? USER_ID : 0", $body, 'constant read guarded');
	pg_assert_contains("(string) \$user['username']);", $body, 'logs the given user');
	pg_assert_contains("e((int) \$user['id'])", $body, 'folder owned by the given user');
	pg_assert_false(strpos($body, "e(USER_ID)") !== false, 'no bare USER_ID');
	pg_assert_same(1, substr_count($body, "\$_SESSION['sessionusername']") - substr_count($body, "isset(\$_SESSION['sessionusername'])"), 'session read once, behind isset');
}
