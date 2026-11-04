<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the store credit mails and expiry
 * (includes/fn/store_credit_mails.php): the migration step run twice, the
 * fields, when the expiring notice is due, the pass window and the profile
 * form's handling of the two actions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/email_campaign_profile_form.php');

// Runs the step against an in-memory schema in a PHP process of its own (the
// real runner helpers, a fake db layer): twice on the lists, once on a site
// without the tables. Null when PHP cannot be started again.
function store_credit_mails_test_run_step()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcrmail');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_schema'] = array();
$GLOBALS['fake_writes'] = array();

function e($value) { return addslashes((string) $value); }

function lang($text)
{
	if (is_array($text)) {
		return str_replace('{var:1}', (string) $text['vars'], $text['string']);
	}
	return $text;
}

function db_value($sql)
{
	if (preg_match("/TABLE_NAME = '([^']+)'/", $sql, $m)) {
		return isset($GLOBALS['fake_schema'][$m[1]]) ? 1 : 0;
	}
	throw new Exception('unexpected db_value: ' . $sql);
}

function db_item($sql)
{
	if (preg_match("/^SHOW COLUMNS FROM `([^`]+)` WHERE Field = '([^']+)'$/", $sql, $m)) {
		if (isset($GLOBALS['fake_schema'][$m[1]][$m[2]])) {
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]], 'Null' => 'NO', 'Default' => '');
		}
		return array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match('/^ALTER TABLE `([^`]+)` MODIFY `([^`]+)` (ENUM\(.*\)) NOT NULL/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[2]] = strtolower($m[3]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$out = array('runs' => array());

$GLOBALS['fake_schema'] = array(
	'email_campaign_profiles' => array('action' => "enum('calendar_event_reserved','contact_group_joined')"),
	'email_campaigns' => array('action' => "enum('','calendar_event_reserved','contact_group_joined')"),
);

foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_credit_campaigns();
	$out['runs'][] = $GLOBALS['fake_writes'];
}

$GLOBALS['fake_schema'] = array();
$GLOBALS['fake_writes'] = array();
upgrade_2026_11_0_credit_campaigns();
$out['missing'] = $GLOBALS['fake_writes'];

echo json_encode($out);
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. $script . "\n");

	$lines = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $lines, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $lines), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'], $result['missing'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The two actions are appended to both lists once; the second run and a
// site without the tables write nothing.
function test_store_credit_mails_step_run_twice_changes_nothing_the_second_time()
{
	$result = store_credit_mails_test_run_step();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(array(
		"ALTER TABLE `email_campaign_profiles` MODIFY `action` ENUM('calendar_event_reserved','contact_group_joined','credit_issued','credit_expiring') NOT NULL DEFAULT ''",
		"ALTER TABLE `email_campaigns` MODIFY `action` ENUM('','calendar_event_reserved','contact_group_joined','credit_issued','credit_expiring') NOT NULL DEFAULT ''",
	), $first, 'appended once, at the end of each list');
	pg_assert_same(array(), $second, 'the second run writes nothing');
	pg_assert_same(array(), $result['missing'], 'nothing written where the tables are missing');

	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_same(1, substr_count($migration, "\tupgrade_2026_11_0_credit_campaigns();"), 'called once');
	pg_assert_true((bool) preg_match('~\tupgrade_2026_11_0_credit_campaigns\(\); +// 11\.0\.171\n~', $migration), 'under 11.0.171');
}

function test_store_credit_mails_fields_follow_the_meanings()
{
	$fields = pg_credit_mail_fields(array('amount' => '₺50,00', 'left' => '₺20,00', 'name' => 'Ayşe', 'account_url' => 'https://example.com/account'));
	$names = array_column($fields, 'name');

	pg_assert_same(array_keys(pg_credit_mail_field_meanings()), $names, 'every listed field, in order');

	$by_name = array_column($fields, 'data', 'name');
	pg_assert_same('₺50,00', $by_name['credit_amount'], 'amount');
	pg_assert_same('₺20,00', $by_name['credit_left'], 'left');
	pg_assert_same('', $by_name['credit_reason'], 'missing value is empty');
	pg_assert_same('https://example.com/account', $by_name['credit_account_url'], 'link');

	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');
	pg_assert_contains("'credit_account_url'", $mail, 'the account link loses the editor\'s http://');
}

function test_store_credit_mails_expiring_notice_is_due_once_in_its_window()
{
	$now = mktime(12, 0, 0, 11, 4, 2026);
	$expires = $now + 14 * 86400 - 60;
	$card = array('balance' => 500, 'expires_at' => $expires, 'created_timestamp' => $now - 30 * 86400);

	pg_assert_same($expires - 14 * 86400, pg_credit_expiring_moment($expires), 'fourteen days before the end');
	pg_assert_same(0, pg_credit_expiring_moment(0), 'never ending: no notice');

	pg_assert_true(pg_credit_expiring_due($card, $now - 900, $now), 'moment fell in this pass');
	pg_assert_false(pg_credit_expiring_due($card, $now, $now + 900), 'the next pass does not send it again');
	pg_assert_false(pg_credit_expiring_due($card, $now - 900, $now - 120), 'not yet');
	pg_assert_false(pg_credit_expiring_due(array('balance' => 0) + $card, $now - 900, $now), 'nothing left');
	pg_assert_false(pg_credit_expiring_due(array('created_timestamp' => $now - 30) + $card, $now - 900, $now), 'written after the moment');
	pg_assert_false(pg_credit_expiring_due(array('expires_at' => 0) + $card, $now - 900, $now), 'never ends');
}

function test_store_credit_mails_window_start()
{
	$now = 1800000000;

	pg_assert_same($now - 900, pg_credit_mail_window_start('', $now), 'first pass looks back one tick');
	pg_assert_same($now - 600, pg_credit_mail_window_start((string) ($now - 600), $now), 'from the last pass');
	pg_assert_same($now - 2 * 86400, pg_credit_mail_window_start((string) ($now - 10 * 86400), $now), 'no further than two days');
}

function test_store_credit_mails_profile_actions()
{
	pg_assert_same(pg_credit_mail_actions(), pg_email_campaign_profile_credit_actions(), 'the form lists the module\'s actions');

	foreach (pg_credit_mail_actions() as $action) {
		$values = pg_email_campaign_profile_action_values($action, array());
		pg_assert_same(array('0', 'after', 'action'), array($values['action_item_id'], $values['schedule_period'], $values['schedule_base']), $action . ': after the action, no record');

		$rows = pg_email_campaign_profile_action_rows($action);
		pg_assert_same('show', $rows['credit_fields'], $action . ' lists the fields');
		pg_assert_same('', $rows['booking_fields']);
	}

	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');

	foreach (pg_credit_mail_actions() as $action) {
		pg_assert_contains("case '" . $action . "':", $mail, $action . ' named in the log');
	}
}
