<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the subscription e-mails: the step that widens
 * email_campaigns.action with the three subscription actions (11.0.129),
 * run twice against an in-memory schema.
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
// real runner helpers, a fake db layer): twice on the enum as 11.0.42 left
// it, once on a site without the table. Null when PHP cannot be started again.
function subscription_mails_test_run_step()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgsubmail');

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

$GLOBALS['fake_schema'] = array('email_campaigns' => array('action' => "enum('','calendar_event_reserved','order_shipped','order_delivered')"));

foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_subscription_campaigns();
	$out['runs'][] = $GLOBALS['fake_writes'];
}

$out['schema'] = $GLOBALS['fake_schema'];

$GLOBALS['fake_schema'] = array();
$GLOBALS['fake_writes'] = array();
upgrade_2026_11_0_subscription_campaigns();
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

	if (($code !== 0) || !is_array($result) || !isset($result['runs'], $result['schema'], $result['missing'])) {
		return array('error' => implode("\n", $lines));
	}

	return $result;
}

// The first run appends the three actions at the end and keeps the default;
// the second, and a run on a site without the table, write nothing.
function test_subscription_mails_step_run_twice_changes_nothing_the_second_time()
{
	$result = subscription_mails_test_run_step();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(array("ALTER TABLE `email_campaigns` MODIFY `action` ENUM('','calendar_event_reserved','order_shipped','order_delivered','subscription_renewed','subscription_failed','subscription_cancelled') NOT NULL DEFAULT ''"), $first, 'appended once, at the end');
	pg_assert_same(array(), $second, 'the second run writes nothing');
	pg_assert_same(array(), $result['missing'], 'nothing written where the table is missing');
}

// Called once, under its label.
function test_subscription_mails_step_is_called_under_its_label()
{
	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($migration, "\tupgrade_2026_11_0_subscription_campaigns();"), 'called once from upgrade_to_2026_11_0()');
	pg_assert_true((bool) preg_match('~\tupgrade_2026_11_0_subscription_campaigns\(\); +// 11\.0\.129\n~', $migration), 'under 11.0.129');
}

// A campaign keeps the action of the profile it was made from, so the
// widened enum is what stores it.
function test_subscription_mails_campaign_row_copies_the_profile_action()
{
	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');

	pg_assert_contains("AND (email_campaign_profiles.action = '\$action')", $mail, 'the profiles of the action');
	pg_assert_true((bool) preg_match("~INSERT INTO email_campaigns \\(.*?action,.*?VALUES \\(\\s*'automatic',\\s*'\" \\. \\\$email_campaign_profile\\['id'\\] \\. \"',\\s*'\" \\. \\\$action \\. \"'~s", $mail), 'the action copied into the campaign row');

	foreach (array('subscription_renewed', 'subscription_failed', 'subscription_cancelled') as $action) {
		pg_assert_contains("case '" . $action . "':", $mail, $action . ' named in the log');
		pg_assert_true(in_array($action, pg_email_campaign_profile_commerce_actions(), true), $action . ' is a commerce action');

		$values = pg_email_campaign_profile_action_values($action, array());
		pg_assert_same(array('0', 'after', 'action'), array($values['action_item_id'], $values['schedule_period'], $values['schedule_base']), $action . ': sent after the action, tied to no record');
		pg_assert_same('show', pg_email_campaign_profile_action_rows($action)['subscription_fields'], $action . ': the fields are listed');
	}

	pg_assert_same('', pg_email_campaign_profile_action_rows('order_shipped')['subscription_fields']);
	pg_assert_same(array_keys(pg_email_campaign_profile_subscription_fields()), array_map(function ($field) {
		return $field['name'];
	}, pg_subscription_mail_fields(array(), array())), 'the form lists the fields the mail fills in');
}

// The Link dialog's http:// comes off the two link fields.
function test_subscription_mails_link_fields_keep_their_own_scheme()
{
	pg_assert_same('<a href="^^subscription_card_url^^">Card</a>', pg_mail_merge_address_hrefs('<a href="http://^^subscription_card_url^^">Card</a>'));
	pg_assert_same('<a href="^^subscription_confirm_url^^">Confirm</a>', pg_mail_merge_address_hrefs('<a href="http://^^subscription_confirm_url^^">Confirm</a>'));
}
