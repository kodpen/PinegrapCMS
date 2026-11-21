<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Site Settings › Mailboxes as folded rows: the pane's rows and
 * their order, a box's row with its state pill, the accounts as connect
 * rows, Bring in an .eml file as the last row, and a mailbox's Remove (who
 * may, what goes, that its conversations stay). No database.
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

function settings_mailboxes_tidy_test_source($relative)
{
	return str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative));
}

function test_settings_mailboxes_remove_check()
{
	$box = array('id' => 4, 'name' => 'Support', 'transport' => 'imap');

	pg_assert_true(ws_mail_box_remove_check(array('settings' => true), $box)['ok'], 'the settings right removes a box');
	pg_assert_false(ws_mail_box_remove_check(array('settings' => false), $box)['ok'], 'without the right, nothing');
	pg_assert_false(ws_mail_box_remove_check(array('settings' => true), null)['ok'], 'a box that is gone');
	pg_assert_false(ws_mail_box_remove_check(array('settings' => true), array('id' => 5, 'name' => 'WhatsApp', 'transport' => 'whatsapp'))['ok'], 'a WhatsApp box is kept by its own card');
}

function test_settings_mailboxes_remove_keeps_the_conversations()
{
	$sql = implode("\n", ws_mail_box_remove_queries(4));

	// The box's row holds its settings: the sealed password, the rules and
	// the routes go with it.
	pg_assert_contains("DELETE FROM ws_mail_boxes WHERE id = '4'", $sql, 'the box and its settings go');
	pg_assert_contains("DELETE FROM ws_mail_blocks WHERE box_id = '4'", $sql, 'its own blocked senders go');

	foreach (array('ws_mail_conversations', 'ws_mail_items', 'ws_messages', 'ws_channels', 'ws_mail_reads', 'ws_mail_reminders') as $table) {
		pg_assert_false(strpos($sql, $table) !== false, $table . ' is never touched: the conversations stay where they are');
	}

	pg_assert_same(2, count(ws_mail_box_remove_queries('4 OR 1=1')), 'an id is a number');
	pg_assert_contains("id = '4'", implode('', ws_mail_box_remove_queries('4 OR 1=1')), 'nothing else in the id');
}

function test_settings_mailboxes_remove_password_and_rules_live_in_the_row()
{
	// The rules and the sealed password are kept in ws_mail_boxes.settings,
	// so the row's delete takes them: no other table holds either.
	$rules = settings_mailboxes_tidy_test_source('includes/workspace/mail_rules.php');
	pg_assert_false(strpos($rules, 'INSERT INTO ws_mail_rules') !== false, 'no table of rules');
	pg_assert_contains('settings', $rules, 'the rules are in the box\'s settings');
}

function test_settings_mailboxes_remove_confirm_says_what_happens()
{
	$said = ws_mail_box_remove_confirm(array('name' => 'Support'));

	foreach (array('Support', 'Reading stops', 'stored password is deleted', 'rules go', 'conversations stay where they are') as $words) {
		pg_assert_contains($words, $said, 'the confirm says: ' . $words);
	}
}

function test_settings_mailboxes_remove_wired_into_the_save()
{
	$save = settings_mailboxes_tidy_test_source('includes/settings/mailboxes.save.php');

	pg_assert_contains("post_value('ws_mail_remove')", $save, 'Remove is posted as ws_mail_remove');
	pg_assert_true(strpos($save, 'ws_mail_box_remove_check(') < strpos($save, 'ws_mail_box_remove_write('), 'checked before it is written');
	pg_assert_true(strpos($save, 'ws_mail_box_save_write(') < strpos($save, "post_value('ws_mail_remove')"), 'after the drawer\'s save, as the other buttons');
}

function settings_mailboxes_tidy_test_box($overrides = array())
{
	return $overrides + array('id' => 4, 'name' => 'Support', 'address' => 'support@example.com', 'enabled' => 1, 'transport' => 'imap',
		'settings' => json_encode(array('host' => 'imap.example.com')), 'checked_at' => mktime(14, 5, 0, 11, 20, 2026), 'last_error' => '');
}

function test_settings_mailboxes_box_state_pill()
{
	pg_assert_same(array('reading', 'pg-tone-teal'), array_values(array_intersect_key(ws_mail_box_state(settings_mailboxes_tidy_test_box()), array('key' => 1, 'tone' => 1))), 'a box read well is Reading, teal');
	pg_assert_same('pg-tone-gray', ws_mail_box_state(settings_mailboxes_tidy_test_box(array('enabled' => 0)))['tone'], 'a box switched off is Off, gray');

	$auth = ws_mail_box_state(settings_mailboxes_tidy_test_box(array('last_error' => 'auth|LOGIN failed')));
	pg_assert_same(array('sign_in', 'pg-tone-amber'), array($auth['key'], $auth['tone']), 'a refused password waits on the person: Sign-in needed, amber');
	pg_assert_same('sign_in', ws_mail_box_state(settings_mailboxes_tidy_test_box(array('last_error' => 'no_account|')))['key'], 'a gone connected account too');

	$error = ws_mail_box_state(settings_mailboxes_tidy_test_box(array('last_error' => 'connect|refused')));
	pg_assert_same(array('error', 'pg-tone-rose'), array($error['key'], $error['tone']), 'a server not reached is Error, rose');
	pg_assert_same(lang('The server could not be reached on its port.'), $error['error'], 'with its first words, not the server\'s');

	pg_assert_same('none', ws_mail_box_state(settings_mailboxes_tidy_test_box(array('settings' => '{}')))['key'], 'a box with no server has no pill');
}

function test_settings_mailboxes_box_row()
{
	$html = ws_mail_settings_box_row(settings_mailboxes_tidy_test_box(array('last_error' => 'connect|')), '<a href="#">#support</a>', 'f');

	pg_assert_contains('<span class="pg-icon-tile ws-agset-face" aria-hidden="true"><i class="bi bi-inbox"></i></span>', $html, 'the tile');
	pg_assert_contains('<strong class="pg-list-row-title">Support</strong><span class="pg-text-meta">support@example.com</span>', $html, 'name and address');
	pg_assert_contains('<a href="#">#support</a> · ', $html, 'where it is kept');
	pg_assert_contains(lang(array('string' => 'Checked {var:1}', 'vars' => '20.11.2026 14:05')) . ': ' . h(lang('The server could not be reached on its port.')), $html, 'the last check with the error\'s first words');
	pg_assert_contains('<span class="pg-pill ws-agset-state pg-tone-rose">' . h(lang('Error')) . '</span>', $html, 'the state pill');
	pg_assert_contains('name="ws_mail_test" value="4" form="f" class="pg-button pg-button-quiet pg-button-sm"', $html, 'Test connection, quiet and small, its posted name kept');
	pg_assert_contains('data-bs-target="#pgset-ws-mail-4"', $html, 'Edit opens the box\'s drawer');
	pg_assert_false(strpos($html, 'pg-button-fill') !== false, 'no filled button: the window\'s Save is the one');

	pg_assert_false(strpos(ws_mail_settings_box_row(settings_mailboxes_tidy_test_box(array('enabled' => 0)), '', 'f'), 'ws_mail_test') !== false, 'a box switched off is not tested');
}

function test_settings_mailboxes_row_face()
{
	pg_assert_same(lang('No mailbox yet.'), ws_mail_settings_boxes_face(array())['line'], 'no box');

	$face = ws_mail_settings_boxes_face(array(settings_mailboxes_tidy_test_box(), settings_mailboxes_tidy_test_box(array('last_error' => 'auth|'))));
	pg_assert_same(array(lang('Sign-in needed'), 'pg-tone-amber'), $face['pill'], 'the state that needs a look');
	pg_assert_true($face['attention'], 'so the row arrives open');

	$face = ws_mail_settings_boxes_face(array(settings_mailboxes_tidy_test_box()));
	pg_assert_same(lang('1 mailbox'), $face['line'], 'one box');
	pg_assert_same(array(lang('Reading'), 'pg-tone-teal'), $face['pill'], 'reading');
	pg_assert_false($face['attention'], 'nothing to look at');
}

function test_settings_mailboxes_account_rows()
{
	$manager = array('role' => 2);
	$none = ws_mail_account_row($manager, 'google', array('count' => 0, 'other' => array()), true);

	pg_assert_contains('<span class="pg-pill pg-tone-gray">' . h(lang('Not connected')) . '</span><span class="pg-button pg-button-sm pg-button-quiet">' . h(lang('Connect')) . '</span>', $none, 'Not connected, gray, Connect beside it');
	pg_assert_contains('href="' . h(ws_mail_connections_url('google')) . '"', $none, 'leading to Google\'s page on Connections');

	$on = ws_mail_account_row($manager, 'microsoft', array('count' => 1, 'other' => array()), true);
	pg_assert_contains('<span class="pg-pill pg-tone-teal">' . h(lang('Connected')) . '</span>', $on, 'Connected, teal, as Connections says it');

	$other = ws_mail_account_row($manager, 'google', array('count' => 0, 'other' => array('mail')), true);
	pg_assert_contains('pg-tone-amber">' . h(lang('Connected for another purpose')), $other, 'connected for sending only');

	$editor = ws_mail_account_row(array('role' => 3), 'google', array('count' => 0, 'other' => array()), true);
	pg_assert_false(strpos($editor, '<a ') !== false, 'below manager: Connections does not open');
	pg_assert_false(strpos($editor, 'pg-button') !== false, 'and no button');
}

function test_settings_mailboxes_eml_row()
{
	$html = ws_mail_eml_pane(array(settings_mailboxes_tidy_test_box()));

	pg_assert_contains('name="ws_mail_eml" form="pgset-ws-mail-eml-form"', $html, 'the box\'s id under its posted name');
	pg_assert_contains('name="ws_mail_eml_file" accept=".eml,message/rfc822" form="pgset-ws-mail-eml-form"', $html, 'the file under its posted name');
	pg_assert_contains('<option value="4">Support · support@example.com</option>', $html, 'each box');
	pg_assert_same('<p class="pg-text-meta">' . h(lang('Add a mailbox first.')) . '</p>', ws_mail_eml_pane(array()), 'no box, nothing to bring into');
}

function test_settings_mailboxes_pane_wiring()
{
	$card = settings_mailboxes_tidy_test_source('includes/workspace/mail_settings.php');
	$from = strpos($card, 'function ws_mail_settings_rows(');
	$rows = substr($card, (int) $from, (int) strpos($card, "\n}\n", (int) $from) - (int) $from);
	$at = array();

	foreach (array('ws-mail', 'ws-mail-accounts', 'ws-mail-rules', 'ws-mail-blocks', 'ws-mail-eml') as $key) {
		$at[$key] = strpos($rows, "'key' => '" . $key . "'");
		pg_assert_true($at[$key] !== false, 'a row ' . $key);
	}

	$order = array_values($at);
	$sorted = $order;
	sort($sorted);
	pg_assert_same($sorted, $order, 'Mailboxes, Accounts, rules, blocked senders, then Bring in an .eml file last');
	pg_assert_contains('<div id="pgset-ws-mail" class="pg-set-card">', $rows, 'the card keeps its id for the anchors and the search');
	pg_assert_contains('class="pg-button pg-button-quiet pg-button-sm" data-bs-toggle="drawer" data-bs-target="#pgset-ws-mail-0"', $rows, 'Add a mailbox is quiet');
	pg_assert_contains('bi-plus-lg', $rows, 'with a plus');

	$pane = settings_mailboxes_tidy_test_source('includes/settings/mailboxes.php');
	pg_assert_contains("require_once(PG_FUNCTIONS_DIR . '/includes/settings/fold.php');", $pane, 'the folded rows are loaded');
	pg_assert_contains('$pg_settings_modals[] = ws_mail_eml_pane_form();', $pane, 'the .eml form outside the settings form');
	pg_assert_false(strpos($card, "ws_mail_eml_form(pg_settings_url('mailboxes')") !== false, 'the drawer no longer holds the .eml upload');
}

function test_settings_mailboxes_row_menu()
{
	$html = ws_mail_settings_box_row(settings_mailboxes_tidy_test_box(), '#support', 'f');
	$menu = substr($html, (int) strpos($html, '<span class="pg-menu-wrap">'));

	pg_assert_contains('<span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button><ul class="menu pg-menu-end">', $html, 'the Design examples\' row menu');
	pg_assert_true(strpos($html, 'data-bs-target="#pgset-ws-mail-4"') < strpos($html, 'pg-menu-wrap'), 'last in the row\'s actions, after Edit');
	pg_assert_contains('name="ws_mail_switch" value="4:off" form="f" class="menu-item"', $menu, 'Turn off for a box that is on');
	pg_assert_true(strpos($menu, 'ws_mail_switch') < strpos($menu, '<hr class="menu-divider">') && strpos($menu, '<hr class="menu-divider">') < strpos($menu, 'ws_mail_remove'), 'Turn off, a divider, then Remove');
	pg_assert_contains('name="ws_mail_remove" value="4" form="f" class="menu-item fg-emphasis-danger" data-ws-mail-confirm="' . h(ws_mail_box_remove_confirm(settings_mailboxes_tidy_test_box())) . '"', $menu, 'Remove in danger, with its confirm');
	pg_assert_contains('value="4:on"', ws_mail_settings_box_row(settings_mailboxes_tidy_test_box(array('enabled' => 0)), '', 'f'), 'Turn on for a box that is off');
	pg_assert_contains("closest(\\'[data-ws-mail-confirm]\\')", settings_mailboxes_tidy_test_source('includes/workspace/mail_settings.php'), 'the confirm is asked before the submit');
}

function test_settings_mailboxes_switch_value()
{
	pg_assert_same(array('id' => 4, 'enabled' => 0), ws_mail_box_switch_value('4:off'), 'off');
	pg_assert_same(array('id' => 4, 'enabled' => 1), ws_mail_box_switch_value('4:on'), 'on');

	foreach (array(null, '', '4', '0:on', '4:maybe', '4:on OR 1=1', array('4:on')) as $bad) {
		pg_assert_same(null, ws_mail_box_switch_value($bad), 'refused: ' . json_encode($bad));
	}

	$save = settings_mailboxes_tidy_test_source('includes/settings/mailboxes.save.php');
	pg_assert_contains("ws_mail_box_switch_value(post_value('ws_mail_switch'))", $save, 'posted as ws_mail_switch');
	pg_assert_true(strpos($save, '$ws_switching = ws_mail_box_remove_check(') < strpos($save, 'ws_mail_box_switch_write('), 'checked before it is written');
}
