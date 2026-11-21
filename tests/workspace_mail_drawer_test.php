<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a mailbox's drawer in Site Settings › Mailboxes
 * (includes/workspace/mail_settings.php): the names it posts, which the
 * category's save module and the settings search read, stay the same.
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

/**
 * The distinct names a drawer's markup posts, in order of first use.
 *
 * @param string $html
 * @return string[]
 */
function workspace_mail_drawer_test_names($html)
{
	preg_match_all('/ name="([^"]+)"/', (string) $html, $m);

	return array_values(array_unique($m[1]));
}

function test_workspace_mail_drawer_posted_names()
{
	$box = array('id' => 3, 'name' => 'Support', 'address' => 'support@example.com', 'enabled' => 1, 'channel_id' => 4, 'folder' => 'INBOX', 'after_fetch' => 'seen', 'reply_via' => 'box',
		'settings' => json_encode(array('provider' => 'other', 'host' => 'imap.example.com', 'port' => 993, 'routes' => array(array('match' => '+billing', 'channel_id' => 4)))));
	$html = ws_mail_box_form('', $box, array(4 => array('id' => 4, 'name' => 'support')), true, 'pg_settings_modal_form');
	$names = workspace_mail_drawer_test_names($html);
	$fields = array('name', 'address', 'kept_in', 'channel_id', 'auth_server', 'route_match][', 'route_channel][', 'provider', 'host', 'port', 'google_way', 'user', 'password',
		'connection_id', 'folder', 'after_fetch', 'move_folder', 'reply_via', 'smtp_host', 'smtp_port', 'sent_copy', 'sent_folder', 'read_sent', 'signature', 'support',
		'first_reply_hours', 'auto_reply', 'auto_reply_text', 'ai_triage', 'reads_bounces', 'enabled');

	foreach ($fields as $field) {
		pg_assert_true(in_array('ws_mail[3][' . $field . ']', $names, true), 'the drawer posts ws_mail[3][' . $field . ']');
	}

	foreach ($names as $name) {
		$own = (strpos($name, 'ws_mail[3][') === 0) && in_array(substr($name, 11, -1), $fields, true);
		pg_assert_true($own || in_array($name, array('ws_mail_box_save', 'ws_mail_test', 'ws_mail_remove'), true), 'no other name is posted: ' . $name);
	}

	pg_assert_contains('name="ws_mail_box_save" value="3" form="pg_settings_modal_form"', $html, 'Save names the box');
	pg_assert_same(false, strpos($html, ' required'), 'nothing in the drawer blocks the pane\'s Save');

	// The ids the settings search and the refused-save marks find.
	foreach (array('name', 'address', 'provider', 'host', 'port', 'user', 'password', 'connection', 'folder', 'after', 'reply_via', 'smtp_host', 'signature', 'auth', 'reads_bounces', 'enabled') as $id) {
		pg_assert_contains('id="ws_mail_3_' . $id . '"', $html, 'the id ws_mail_3_' . $id . ' stays');
	}
}
