<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a customer's WhatsApp message as a Workspace conversation
 * (includes/workspace/mail_phone.php): the 2026.11.0 step 11.0.199 run twice
 * against an in-memory schema, the WhatsApp box's columns, the card's
 * Channel field and what it accepts, the card of a WhatsApp item, the
 * offers a phone conversation is not given, the known customer's line, and
 * the wiring from the webhook and the settings card. No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');

function test_workspace_mail_phone_step_is_called_once_under_a_label_of_its_own()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_ws_mail_phone();           // 11.0.199\n"), 'called once under 11.0.199');

	preg_match('/function upgrade_to_2026_11_0\(\) \{(.*?)\n\}\n/s', $source, $entry);
	preg_match_all('#// (11\.0\.\d+)\n#', isset($entry[1]) ? $entry[1] : '', $labels);

	pg_assert_same(count($labels[1]), count(array_unique($labels[1])), 'every label of the call list is used once');
}

// Runs the step twice in a child process: the fake db layer answers the
// runner's probes from an array, applies MODIFY, ADD and ADD INDEX to it and
// logs every write. Null when PHP cannot be started again.
function workspace_mail_phone_test_run_twice($schema)
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgmailphone');

	if ($file === false) {
		return null;
	}

	$script = <<<'PHP'
define('INSTALL_OR_UPDATE', true);

$GLOBALS['fake_writes'] = array();
$GLOBALS['fake_indexes'] = array();

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
			return array('Field' => $m[2], 'Type' => $GLOBALS['fake_schema'][$m[1]][$m[2]]);
		}
		return array();
	}
	if (preg_match("/^SHOW INDEX FROM `([^`]+)` WHERE Key_name = '([^']+)'$/", $sql, $m)) {
		return isset($GLOBALS['fake_indexes'][$m[1]][$m[2]]) ? array('Key_name' => $m[2]) : array();
	}
	throw new Exception('unexpected db_item: ' . $sql);
}

function db($sql)
{
	$GLOBALS['fake_writes'][] = $sql;
	if (preg_match('/^ALTER TABLE `([^`]+)` ADD INDEX ([a-z_]+) /', $sql, $m)) {
		$GLOBALS['fake_indexes'][$m[1]][$m[2]] = true;
		return true;
	}
	if (preg_match('/^ALTER TABLE `([^`]+)` (ADD|MODIFY) `([^`]+)` ([A-Z]+(?:\([^)]*\))?)/', $sql, $m)) {
		$GLOBALS['fake_schema'][$m[1]][$m[3]] = strtolower($m[4]);
		return true;
	}
	throw new Exception('unexpected db: ' . $sql);
}

require $pg_dir . '/includes/migrations/runner.php';
require $pg_dir . '/includes/migrations/2026.11.0.php';

$runs = array();
foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_ws_mail_phone();
	$runs[] = $GLOBALS['fake_writes'];
}

echo json_encode(array('runs' => $runs, 'schema' => $GLOBALS['fake_schema'], 'indexes' => $GLOBALS['fake_indexes']));
PHP;

	file_put_contents($file, '<?php' . "\n"
		. '$pg_dir = ' . var_export(PG_FUNCTIONS_DIR, true) . ";\n"
		. '$GLOBALS[\'fake_schema\'] = ' . var_export($schema, true) . ";\n"
		. $script . "\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=stderr -d error_reporting=-1 ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	if (($code !== 0) || !is_array($result) || !isset($result['runs'])) {
		return array('error' => implode("\n", $out));
	}

	return $result;
}

function test_workspace_mail_phone_step_run_twice_changes_nothing_the_second_time()
{
	$result = workspace_mail_phone_test_run_twice(array(
		'ws_mail_boxes'         => array('id' => 'int', 'transport' => "enum('imap','graph','gmail','push')"),
		'ws_mail_conversations' => array('id' => 'int', 'box_id' => 'int'),
	));

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same(3, count($result['runs'][0]), 'the first run widens, adds the column and the index');
	pg_assert_same(array(), $result['runs'][1], 'the second run writes nothing');
	pg_assert_contains("'whatsapp','sms'", $result['schema']['ws_mail_boxes']['transport'], 'the transports');
	pg_assert_same('varchar(20)', $result['schema']['ws_mail_conversations']['customer_phone'], 'the number');
	pg_assert_true(!empty($result['indexes']['ws_mail_conversations']['idx_box_phone']), 'the index');

	$none = workspace_mail_phone_test_run_twice(array());
	pg_assert_true(!isset($none['error']), 'without the mail tables it runs');
	pg_assert_same(array(array(), array()), isset($none['runs']) ? $none['runs'] : null, 'and writes nothing');
}

function test_workspace_mail_phone_box_values()
{
	$app = array('display_phone_number' => '+90 532 123 45 67', 'phone_number_id' => '106540352242922');

	$values = ws_mail_phone_box_values($app, 7);
	pg_assert_same('WhatsApp +90 532 123 45 67', $values['name'], 'name');
	pg_assert_same('+90 532 123 45 67', $values['address'], 'the number as the address');
	pg_assert_same(array('app' => 'whatsapp', 'phone_number_id' => '106540352242922'), json_decode($values['settings'], true), 'settings name the account');
	pg_assert_same(array(7, 1), array($values['channel_id'], $values['enabled']), 'on in its channel');

	$values = ws_mail_phone_box_values(array(), 0);
	pg_assert_same(array('WhatsApp', 0, 0), array($values['name'], $values['channel_id'], $values['enabled']), 'no channel: off');
}

function test_workspace_mail_phone_channel_decide()
{
	$channels = array(4 => array('id' => 4, 'name' => 'support'), 9 => array('id' => 9, 'name' => 'sales'));

	pg_assert_same(array('ok' => true, 'channel_id' => 9), ws_mail_phone_channel_decide('9', $channels, 4), 'a channel the person sees');
	pg_assert_same(array('ok' => true, 'channel_id' => 0), ws_mail_phone_channel_decide('0', $channels, 4), 'none');
	pg_assert_same(array('ok' => true, 'channel_id' => 12), ws_mail_phone_channel_decide('12', $channels, 12), 'the kept channel, unseen, stays');
	pg_assert_same(array('ok' => false, 'channel_id' => 4), ws_mail_phone_channel_decide('12', $channels, 4), 'a channel the person does not see');
	pg_assert_same(false, ws_mail_phone_channel_decide('x', $channels, 4)['ok'], 'not a number');
	pg_assert_same(false, ws_mail_phone_channel_decide(array('9'), $channels, 4)['ok'], 'not text');

	$html = ws_mail_phone_channel_html($channels, 12);
	pg_assert_contains('name="whatsapp_channel"', $html, 'the field');
	pg_assert_contains('<option value="12" selected>', $html, 'the kept, unseen channel');
	pg_assert_contains('<option value="9"># sales</option>', $html, 'a channel');

	$html = ws_mail_phone_channel_html(array(5 => array('id' => 5, 'name' => '<b>x</b>')), 5);
	pg_assert_contains('<option value="5" selected># &lt;b&gt;x&lt;/b&gt;</option>', $html, 'the name escaped, the kept one chosen');
}

function test_workspace_mail_phone_items()
{
	pg_assert_true(ws_mail_item_is_phone(array('transport' => 'whatsapp')), 'whatsapp');
	pg_assert_true(ws_mail_item_is_phone(array('transport' => 'sms')), 'sms');
	pg_assert_false(ws_mail_item_is_phone(array('transport' => 'imap')), 'mail');
	pg_assert_false(ws_mail_item_is_phone(null), 'none');

	pg_assert_same('[' . lang('A picture') . ']', ws_mail_phone_kind_text('image'), 'a picture without a caption');
	pg_assert_same('[' . lang('A message WhatsApp did not pass on') . ']', ws_mail_phone_kind_text('unsupported'), 'a type WhatsApp does not pass on');

	$conversation = array('id' => 3, 'message_id' => 40, 'box_id' => 2, 'status' => 'open', 'assignee_id' => 0, 'first_in_at' => 0, 'first_reply_at' => 0);
	$item = array('id' => 8, 'message_id' => 40, 'direction' => 'in', 'auth' => 'pass', 'from_email' => '+905321234567', 'from_name' => 'Ayşe',
		'subject' => '', 'body_text' => 'Merhaba', 'refused_files' => '', 'to_list' => '', 'conversation' => $conversation, 'support' => false,
		'first_reply_hours' => 0, 'transport' => 'whatsapp', 'send_state' => '');
	$row = array('id' => 40, 'file_id' => 0, 'body' => 'Merhaba');

	$html = ws_mail_card_html($row, $item);
	pg_assert_contains('bi-whatsapp', $html, 'the WhatsApp badge');
	pg_assert_contains('<span class="ms-1">+905321234567</span>', $html, 'the number in place of the address');
	pg_assert_false(strpos($html, lang('Sender not verified')) !== false, 'Meta vouches for the number');

	pg_assert_same(0, ws_mail_forward_offer($row, $item, array('member' => true)), 'no Forward as e-mail');
	pg_assert_same(8, ws_mail_forward_offer($row, array('transport' => 'imap') + $item, array('member' => true)), 'a mail is still offered it');
}

function test_workspace_mail_phone_customer_line()
{
	$text = ws_mail_customer_line_text('Ayşe', 4, 'Siparişim nerede?', '', 'whatsapp');
	pg_assert_same(lang(array('string' => 'A WhatsApp message from {var:1} came into {var:2}: {var:3}', 'vars' => array('Ayşe', '<#channel:4>', 'Siparişim nerede?'))), $text, 'the WhatsApp line');

	$text = ws_mail_customer_line_text('Ayşe', 4, 'Order', '');
	pg_assert_same(lang(array('string' => 'An e-mail from {var:1} came into {var:2}: {var:3}', 'vars' => array('Ayşe', '<#channel:4>', 'Order'))), $text, 'the e-mail line as before');
}

function test_workspace_mail_phone_wiring()
{
	$read = function ($path) {
		return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
	};

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_phone.php');", $read('includes/workspace/bootstrap.php'), 'loaded with the Workspace');
	pg_assert_contains('pg_whatsapp_inbox_to_channel($event, $contact_id, $at, $now);', $read('includes/fn/whatsapp_webhook.php'), 'a kept message goes to the channel');
	pg_assert_contains('$whatsapp_channel_post = pg_whatsapp_channel_check($_POST, USER_ID, $liveform);', $read('includes/settings/contact.save.php'), 'the card checks the channel');
	pg_assert_contains('pg_whatsapp_channel_save($whatsapp_channel_post, USER_ID);', $read('includes/settings/contact.save.php'), 'and saves it after the account');
	pg_assert_contains("'channel_html' => pg_whatsapp_channel_field(", $read('includes/fn/whatsapp.php'), 'the card draws the field');

	$messages = $read('includes/workspace/messages.php');
	pg_assert_contains("!(function_exists('ws_mail_item_is_phone') && ws_mail_item_is_phone(", $messages, 'no Reply by e-mail on a WhatsApp card');

	$html = pg_whatsapp_settings_html(array(
		'enabled' => false, 'secrets_unreadable' => false, 'app_secret' => '', 'token' => '', 'app_id' => '', 'phone_number_id' => '',
		'account_id' => '', 'verify_token' => '', 'checked' => 0, 'display_phone_number' => '', 'verified_name' => '', 'quality_rating' => '',
	), array('refusal' => '', 'webhook_url' => 'https://shop.test/whatsapp_webhook.php', 'test_to' => '', 'channel_html' => '<i id="channel-field"></i>'));
	pg_assert_contains('6. ' . h(lang('Conversations')), $html, 'step 6');
	pg_assert_contains('<i id="channel-field"></i>', $html, 'the field under it');
}

function test_workspace_mail_phone_box_stays_out_of_the_mailbox_settings()
{
	pg_assert_true(ws_mail_box_is_phone(array('transport' => 'whatsapp')), 'a WhatsApp box');
	pg_assert_false(ws_mail_box_is_phone(array('transport' => 'imap')), 'a mailbox');
	pg_assert_false(ws_mail_box_is_phone(null), 'none');

	$settings = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mail_settings.php');
	pg_assert_contains("return !ws_mail_box_is_phone(\$box);", $settings, 'left out of the list');
	pg_assert_contains("\$box = ws_mail_box_is_phone(\$box) ? null : \$box;", $settings, 'not edited or fed an .eml by id');
	pg_assert_contains("\$ws_box = ws_mail_box_is_phone(\$ws_box) ? null : \$ws_box;", (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/mailboxes.save.php'), 'not saved by id');
}
