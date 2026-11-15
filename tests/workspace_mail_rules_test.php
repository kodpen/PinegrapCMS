<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mailbox's incoming mail rules (includes/workspace/mail_rules.php):
 * a kept rule cleaned, the Add rule form checked, the first rule that fits,
 * the line a rule is told in, the Site Log lines, the list and form on the
 * Mailboxes card, and the wiring into the ingest, the card and its save.
 * No database.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/mail_parse.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

function workspace_mail_rules_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function workspace_mail_rules_test_box($rules, $support = true, $transport = 'imap')
{
	return array('id' => 3, 'name' => 'Support', 'transport' => $transport, 'settings' => json_encode(array('support' => $support, 'rules' => $rules)));
}

function test_workspace_mail_rules_clean_keeps_only_whole_rules()
{
	pg_assert_same(
		array('id' => 'ab12cd34', 'field' => 'domain', 'value' => 'shop.test', 'action' => 'close', 'user_id' => 0),
		ws_mail_rule_clean(array('id' => 'ab12cd34', 'field' => 'domain', 'value' => '@Shop.TEST', 'action' => 'close', 'user_id' => 9)),
		'a domain kept in small letters, no person on a closing rule'
	);
	pg_assert_same('a@b.test', ws_mail_rule_clean(array('id' => 'ab12cd34', 'field' => 'address', 'value' => ' A@B.test ', 'action' => 'assign', 'user_id' => 4))['value'], 'an address');
	pg_assert_same('invoice due', ws_mail_rule_clean(array('id' => 'ab12cd34', 'field' => 'subject', 'value' => "Invoice   DUE", 'action' => 'close'))['value'], 'words, spaces tidied');
	pg_assert_same(null, ws_mail_rule_clean(array('id' => 'ab12cd34', 'field' => 'address', 'value' => 'a@b.test', 'action' => 'assign', 'user_id' => 0)), 'giving it to nobody');
	pg_assert_same(null, ws_mail_rule_clean(array('id' => 'nope', 'field' => 'address', 'value' => 'a@b.test', 'action' => 'close')), 'no id of its own');
	pg_assert_same(null, ws_mail_rule_clean(array('id' => 'ab12cd34', 'field' => 'body', 'value' => 'x', 'action' => 'close')), 'an unknown field');
	pg_assert_same(null, ws_mail_rule_clean(array('id' => 'ab12cd34', 'field' => 'address', 'value' => 'not an address', 'action' => 'close')), 'not an address');
	pg_assert_same(null, ws_mail_rule_clean(array('id' => 'ab12cd34', 'field' => 'subject', 'value' => str_repeat('x', WS_MAIL_RULE_WORDS_MAX + 1), 'action' => 'close')), 'words too long');
	pg_assert_same('iade talebi', ws_mail_rule_value('subject', '  İADE   Talebi '), 'a capital İ folds to a plain i');
	pg_assert_same(null, ws_mail_rule_clean('x'), 'not an array');
}

function test_workspace_mail_rules_read_from_the_box_settings_at_most_the_limit()
{
	$rules = array();

	for ($i = 0; $i < WS_MAIL_RULES_MAX + 3; $i++) {
		$rules[] = array('id' => sprintf('%08x', $i), 'field' => 'subject', 'value' => 'w' . $i, 'action' => 'close');
	}

	$rules[] = array('id' => 'zz', 'field' => 'subject', 'value' => 'bad', 'action' => 'close');

	pg_assert_same(WS_MAIL_RULES_MAX, count(ws_mail_rules(workspace_mail_rules_test_box($rules))), 'at most the limit');
	pg_assert_same(array(), ws_mail_rules(null), 'no box, no rules');
	pg_assert_same('w0', ws_mail_rules(workspace_mail_rules_test_box($rules))[0]['value'], 'in their order');
}

function test_workspace_mail_rules_input()
{
	$ok = ws_mail_rule_input(array('box_id' => 3, 'field' => 'domain', 'value' => '@News.Test', 'action' => 'close'), array(4));
	pg_assert_true($ok['ok'], 'a closing rule');
	pg_assert_same(array('field' => 'domain', 'value' => 'news.test', 'action' => 'close', 'user_id' => 0), $ok['rule'], 'its shape');

	$ok = ws_mail_rule_input(array('box_id' => 3, 'field' => 'address', 'value' => 'a@b.test', 'action' => 'assign:4'), array(4));
	pg_assert_same(4, $ok['rule']['user_id'], 'given to a team member');

	pg_assert_same('box_id', ws_mail_rule_input(array('field' => 'address', 'value' => 'a@b.test', 'action' => 'close'), array())['field'], 'no box');
	pg_assert_same('field', ws_mail_rule_input(array('box_id' => 3, 'field' => 'body', 'value' => 'x', 'action' => 'close'), array())['field'], 'an unknown field');
	pg_assert_same('value', ws_mail_rule_input(array('box_id' => 3, 'field' => 'address', 'value' => 'x', 'action' => 'close'), array())['field'], 'a bad address');
	pg_assert_same('value', ws_mail_rule_input(array('box_id' => 3, 'field' => 'subject', 'value' => '  ', 'action' => 'close'), array())['field'], 'no words');
	pg_assert_same('action', ws_mail_rule_input(array('box_id' => 3, 'field' => 'address', 'value' => 'a@b.test', 'action' => 'assign:5'), array(4))['field'], 'somebody off the team');
	pg_assert_same('action', ws_mail_rule_input(array('box_id' => 3, 'field' => 'address', 'value' => 'a@b.test', 'action' => 'delete'), array(4))['field'], 'an unknown action');
	pg_assert_false(ws_mail_rule_input('x', array())['ok'], 'not an array');
}

function test_workspace_mail_rules_match_the_first_that_fits()
{
	$rules = array(
		array('id' => '00000001', 'field' => 'subject', 'value' => 'invoice', 'action' => 'close', 'user_id' => 0),
		array('id' => '00000002', 'field' => 'domain', 'value' => 'shop.test', 'action' => 'assign', 'user_id' => 4),
		array('id' => '00000003', 'field' => 'address', 'value' => 'ali@shop.test', 'action' => 'assign', 'user_id' => 5),
	);

	pg_assert_same('00000002', ws_mail_rule_match($rules, 'Ali@mail.Shop.test', 'Hello')['id'], 'a subdomain falls under its domain');
	pg_assert_same('00000002', ws_mail_rule_match($rules, 'ali@shop.test', 'Hello')['id'], 'the first that fits wins over a later address rule');
	pg_assert_same('00000001', ws_mail_rule_match($rules, 'ali@shop.test', 'Your  INVOICE is ready')['id'], 'words anywhere, any capitals');
	pg_assert_same(null, ws_mail_rule_match($rules, 'ali@othershop.test', 'Hello'), 'a look-alike domain does not fit');
	pg_assert_same(null, ws_mail_rule_match($rules, '', 'Hello'), 'no sender, no address or domain rule');
	pg_assert_same('00000004', ws_mail_rule_match(array(array('id' => '00000004', 'field' => 'subject', 'value' => ws_mail_rule_value('subject', 'İade Talebi'), 'action' => 'close', 'user_id' => 0)), 'a@b.test', 'Yeni iade talebi')['id'], 'a capital İ matches a small i');
	pg_assert_same(null, ws_mail_rule_match(array(), 'a@b.test', 'invoice'), 'no rules');
}

function test_workspace_mail_rules_text_and_log_lines()
{
	$close = array('field' => 'domain', 'value' => 'news.test', 'action' => 'close', 'user_id' => 0);
	$give = array('field' => 'subject', 'value' => 'invoice', 'action' => 'assign', 'user_id' => 4);

	pg_assert_same('When the sender is at @news.test → close it', ws_mail_rule_text($close, ''), 'a closing rule');
	pg_assert_same('When the subject has "invoice" → give it to Ayşe', ws_mail_rule_text($give, 'Ayşe'), 'a giving rule');
	pg_assert_same('When the sender is a@b.test → give it to somebody who left the team', ws_mail_rule_text(array('field' => 'address', 'value' => 'a@b.test', 'action' => 'assign', 'user_id' => 9), ''), 'a person gone');
	pg_assert_same('an incoming mail rule was added to the mailbox Support: When the sender is at @news.test → close it', ws_mail_rule_log_line(true, $close, 'Support', ''), 'added');
	pg_assert_same('an incoming mail rule was deleted from the mailbox Support: When the sender is at @news.test → close it', ws_mail_rule_log_line(false, $close, 'Support', ''), 'deleted');
}

function test_workspace_mail_rules_only_a_support_email_box_keeps_them()
{
	pg_assert_true(ws_mail_rule_box_ok(workspace_mail_rules_test_box(array())), 'a support box');
	pg_assert_false(ws_mail_rule_box_ok(workspace_mail_rules_test_box(array(), false)), 'a plain box has no holder or status');
	pg_assert_false(ws_mail_rule_box_ok(workspace_mail_rules_test_box(array(), true, 'whatsapp')), 'a WhatsApp box');
	pg_assert_false(ws_mail_rule_box_ok(null), 'no box');
}

function test_workspace_mail_rules_card_html()
{
	pg_assert_same('', ws_mail_rules_html(array(workspace_mail_rules_test_box(array(), false)), array(4 => 'Ayşe'), 'f'), 'no support box, nothing drawn');

	$box = workspace_mail_rules_test_box(array(
		array('id' => 'ab12cd34', 'field' => 'address', 'value' => 'a@b.test', 'action' => 'assign', 'user_id' => 4),
		array('id' => 'ab12cd35', 'field' => 'subject', 'value' => '<b>sale</b>', 'action' => 'close'),
	));
	$html = ws_mail_rules_html(array($box), array(4 => 'Ayşe <A>'), 'pg_settings_page_form');

	pg_assert_contains('Incoming mail rules', $html, 'its title');
	pg_assert_contains('give it to Ayşe &lt;A&gt;', $html, 'a giving rule, escaped');
	pg_assert_contains('&quot;&lt;b&gt;sale&lt;/b&gt;&quot;', $html, 'words escaped');
	pg_assert_contains('name="ws_mail_rule_delete" value="3:ab12cd34" form="pg_settings_page_form"', $html, 'Delete submits the pane form');
	pg_assert_contains('name="ws_mail_rule_add" value="1" form="pg_settings_page_form"', $html, 'Add rule submits the pane form');
	pg_assert_contains('name="ws_mail_rule_value"', $html, 'flat field names: the settings screen refuses a list');
	pg_assert_true(strpos($html, 'name="ws_mail_rule[') === false, 'no field posted as a list');
	pg_assert_contains('<option value="assign:4">Give it to Ayşe &lt;A&gt;</option>', $html, 'one choice per person');
	pg_assert_contains('<option value="close">Close it</option>', $html, 'close');
	pg_assert_contains('<option value="3">Support</option>', $html, 'the box to choose');
	pg_assert_contains('class="pg-list-row"', $html, 'look A: rows');
	pg_assert_contains('class="pg-input"', $html, 'look A: fields');
	pg_assert_contains('class="ws-mail-rule-add mt-5"', $html, 'the form\'s own class');
	pg_assert_true(strpos($html, ' required') === false, 'no required field blocks the card\'s other saves');

	$html = ws_mail_rules_html(array(workspace_mail_rules_test_box(array())), array(), '');
	pg_assert_contains('No rules yet.', $html, 'an empty list says so');
	pg_assert_true(strpos($html, 'ws_mail_rule_add') === false, 'no form without the pane form');
}

function test_workspace_mail_rules_wiring()
{
	$ingest = workspace_mail_rules_test_source('includes/workspace/mail.php');
	$block = strpos($ingest, 'ws_mail_block_find($box_id, $from_email)');
	$opened = strpos($ingest, "db(\"INSERT INTO ws_mail_conversations");
	$rules = strpos($ingest, 'ws_mail_rules_run($box, $conversation_id, $channel, $message_id, $from_email, $subject, $now)');
	$customer = strpos($ingest, "ws_notify((int) \$conversation['assignee_id'], 'mail_customer'");
	$event = strpos($ingest, "ws_event('workspace.mail.received'");
	$auto = strpos($ingest, "if (\$opened && (\$ruled !== 'close') && function_exists('ws_mail_auto_reply'))");

	pg_assert_true($block !== false && $opened !== false && $rules !== false && $event !== false && $auto !== false, 'every part is there');
	pg_assert_true(($block < $opened) && ($opened < $rules), 'after the block check and the conversation\'s write');
	pg_assert_true(($rules < $customer) && ($rules < $event) && ($rules < $auto), 'before the notices, the event and the automatic answer');
	pg_assert_contains('$ruled = ($opened && function_exists(\'ws_mail_rules_run\'))', $ingest, 'only for a conversation the mail opened');

	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/workspace/mail_rules.php');", workspace_mail_rules_test_source('includes/workspace/bootstrap.php'), 'loaded with the module');
	pg_assert_contains('ws_mail_rules_html($boxes, ws_mail_rule_people(), $form)', workspace_mail_rules_test_source('includes/workspace/mail_settings.php'), 'drawn on the Mailboxes card');

	$save = workspace_mail_rules_test_source('includes/settings/mailboxes.save.php');
	pg_assert_contains("ws_mail_rule_add(\$ws_viewer,", $save, 'Add rule is saved');
	pg_assert_contains("ws_mail_rule_delete(\$ws_viewer,", $save, 'Delete is saved');

	$module = workspace_mail_rules_test_source('includes/workspace/mail_rules.php');
	pg_assert_contains("if (empty(\$viewer['settings'])) {", $module, 'behind the Workspace settings right');
	pg_assert_true(substr_count($module, 'log_activity(ws_mail_rule_log_line(') === 2, 'each change is a Site Log line');
	pg_assert_contains("'mail_assigned', array('channel_id' => (int) \$channel['id'], 'message_id' => (int) \$message_id, 'actor_id' => 0)", $module, 'the person is told');
	pg_assert_contains('!ws_mail_can_answer($person, $channel)', $module, 'only somebody who may answer there');
}
