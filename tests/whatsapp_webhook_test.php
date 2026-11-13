<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for what a WhatsApp webhook post says: the events read from Meta's
 * body, a delivery state on a queued row, a template's change, a customer's
 * message and its bell notice and history line, and the wiring of the
 * receiver, the job and the migration step. No database and no call to Meta.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp_webhook.php');

function whatsapp_webhook_test_body($field, $value)
{
	return array('object' => 'whatsapp_business_account', 'entry' => array(array('id' => '102290129340398', 'changes' => array(array('field' => $field, 'value' => $value)))));
}

function test_whatsapp_webhook_statuses()
{
	$body = whatsapp_webhook_test_body('messages', array(
		'messaging_product' => 'whatsapp',
		'metadata' => array('display_phone_number' => '15550000000', 'phone_number_id' => '106540352242922'),
		'statuses' => array(
			array('id' => 'wamid.HBgLMTU1NTAwMDAwMDAVAgARGBI=', 'status' => 'delivered', 'timestamp' => '1763000000', 'recipient_id' => '905321234567'),
			array('id' => 'wamid.B', 'status' => 'failed', 'timestamp' => '1763000001', 'errors' => array(array('code' => 131026, 'title' => 'Message undeliverable', 'error_data' => array('details' => 'Not a WhatsApp number')))),
			array('id' => 'wamid.C', 'status' => 'played', 'timestamp' => '1763000002'),
			array('id' => 'wamid.D', 'status' => 'deleted'),
			array('id' => "bad id'", 'status' => 'sent'),
		),
	));

	$events = pg_whatsapp_webhook_events($body, '106540352242922');
	pg_assert_same(3, count($events), 'unknown states and bad ids skipped');
	pg_assert_same(array('type' => 'status', 'wamid' => 'wamid.HBgLMTU1NTAwMDAwMDAVAgARGBI=', 'status' => 'delivered', 'at' => 1763000000, 'error_code' => '', 'error' => ''), $events[0], 'delivered');
	pg_assert_same('131026', $events[1]['error_code'], 'error code');
	pg_assert_same('Message undeliverable: Not a WhatsApp number', $events[1]['error'], 'error text');
	pg_assert_same('read', $events[2]['status'], 'a played voice message counts as read');

	pg_assert_same(array(), pg_whatsapp_webhook_events($body, '999'), 'another number of the app is not ours');
	pg_assert_same(array(), pg_whatsapp_webhook_events($body, ''), 'no number stored');
	pg_assert_same(array(), pg_whatsapp_webhook_events(array('object' => 'page') + $body, '106540352242922'), 'another object');
	pg_assert_same(array(), pg_whatsapp_webhook_events(null, '106540352242922'), 'not JSON');
}

function test_whatsapp_webhook_messages()
{
	$body = whatsapp_webhook_test_body('messages', array(
		'metadata' => array('phone_number_id' => '106540352242922'),
		'contacts' => array(array('profile' => array('name' => 'Ayşe Yılmaz'), 'wa_id' => '905321234567'), array('profile' => array('name' => 'Can'), 'user_id' => 'TR.1349')),
		'messages' => array(
			array('from' => '905321234567', 'id' => 'wamid.M1', 'timestamp' => '1763000100', 'type' => 'text', 'text' => array('body' => ' Siparişim nerede? '), 'context' => array('id' => 'wamid.OURS')),
			array('from_user_id' => 'TR.1349', 'id' => 'wamid.M2', 'timestamp' => '1763000101', 'type' => 'image', 'image' => array('id' => '1234567', 'mime_type' => 'image/jpeg', 'caption' => 'Kutu')),
			array('from' => '905321234567', 'id' => 'wamid.M3', 'type' => 'interactive', 'interactive' => array('type' => 'button_reply', 'button_reply' => array('id' => 'x', 'title' => 'Evet'))),
			array('from' => '905321234567', 'id' => 'wamid.M4', 'type' => 'document', 'document' => array('id' => '77', 'mime_type' => 'application/pdf', 'filename' => 'fatura.pdf')),
			array('from' => 'abc', 'id' => 'wamid.M5', 'type' => 'text', 'text' => array('body' => 'no sender')),
		),
	));

	$events = pg_whatsapp_webhook_events($body, '106540352242922');
	pg_assert_same(4, count($events), 'a message without a sender is skipped');
	pg_assert_same(array(
		'type' => 'message', 'wamid' => 'wamid.M1', 'at' => 1763000100, 'from' => '905321234567', 'from_user_id' => '',
		'profile_name' => 'Ayşe Yılmaz', 'kind' => 'text', 'body' => 'Siparişim nerede?', 'media_id' => '', 'media_mime' => '',
		'context_wamid' => 'wamid.OURS',
	), $events[0], 'a text message');
	pg_assert_same(array('', 'TR.1349', 'Can', 'image', 'Kutu', '1234567', 'image/jpeg'), array($events[1]['from'], $events[1]['from_user_id'], $events[1]['profile_name'], $events[1]['kind'], $events[1]['body'], $events[1]['media_id'], $events[1]['media_mime']), 'a username without a number');
	pg_assert_same('Evet', $events[2]['body'], 'a button reply\'s title');
	pg_assert_same('fatura.pdf', $events[3]['body'], 'a document without a caption by its name');

	$long = pg_whatsapp_webhook_message_content(array('type' => 'text', 'text' => array('body' => str_repeat('ş', 5000))));
	pg_assert_same(PG_WHATSAPP_INBOX_BODY_MAX, function_exists('mb_strlen') ? mb_strlen($long['body'], 'UTF-8') : PG_WHATSAPP_INBOX_BODY_MAX, 'cut at the limit');
	pg_assert_true(preg_match('//u', $long['body']) === 1, 'cut between characters');

	$odd = pg_whatsapp_webhook_message_content(array('type' => 'unsupported'));
	pg_assert_same(array('kind' => 'unsupported', 'body' => '', 'media_id' => '', 'media_mime' => ''), $odd, 'a type not read');
}

function test_whatsapp_webhook_template_events()
{
	$events = pg_whatsapp_webhook_events(whatsapp_webhook_test_body('message_template_status_update', array(
		'event' => 'REJECTED', 'message_template_id' => 594425479261596, 'message_template_name' => 'order_shipped',
		'message_template_language' => 'tr', 'reason' => 'INVALID_FORMAT', 'other_info' => array('title' => 'Format', 'description' => 'Variables at the end'),
	)), '');
	pg_assert_same(array(array('meta_id' => '594425479261596', 'name' => 'order_shipped', 'language' => 'tr', 'type' => 'template', 'event' => 'REJECTED', 'reason' => 'INVALID_FORMAT: Variables at the end')), $events, 'a rejection, read without a phone number id');

	$events = pg_whatsapp_webhook_events(whatsapp_webhook_test_body('message_template_status_update', array('event' => 'APPROVED', 'message_template_id' => '5944', 'reason' => 'NONE')), '');
	pg_assert_same('', $events[0]['reason'], 'NONE is no reason');

	$events = pg_whatsapp_webhook_events(whatsapp_webhook_test_body('template_category_update', array('message_template_id' => '5944', 'previous_category' => 'UTILITY', 'new_category' => 'MARKETING')), '');
	pg_assert_same('category', $events[0]['type'], 'category change');
	pg_assert_same('MARKETING', $events[0]['category'], 'new category');

	pg_assert_same(array(), pg_whatsapp_webhook_events(whatsapp_webhook_test_body('template_category_update', array('message_template_id' => '5944', 'new_category' => 'OTHER')), ''), 'unknown category');
	pg_assert_same(array(), pg_whatsapp_webhook_events(whatsapp_webhook_test_body('message_template_status_update', array('event' => 'APPROVED', 'message_template_id' => 'x')), ''), 'no id');

	pg_assert_same(array('action' => 'update', 'set' => array('status' => 'REJECTED', 'reason' => 'INVALID_FORMAT')), pg_whatsapp_template_change(array('type' => 'template', 'event' => 'REJECTED', 'reason' => 'INVALID_FORMAT')), 'rejected keeps its reason');
	pg_assert_same(array('action' => 'update', 'set' => array('status' => 'APPROVED', 'reason' => '')), pg_whatsapp_template_change(array('type' => 'template', 'event' => 'APPROVED', 'reason' => 'X')), 'approved clears it');
	pg_assert_same('delete', pg_whatsapp_template_change(array('type' => 'template', 'event' => 'DELETED', 'reason' => ''))['action'], 'deleted');
	pg_assert_same(array('action' => 'update', 'set' => array('category' => 'MARKETING')), pg_whatsapp_template_change(array('type' => 'category', 'category' => 'MARKETING')), 'category');
}

function test_whatsapp_delivery_change()
{
	$fresh = array('delivery' => '', 'delivered_at' => 0, 'read_at' => 0);

	pg_assert_same(array('delivery' => 'sent'), pg_whatsapp_delivery_change($fresh, array('status' => 'sent', 'at' => 10), 99), 'sent');
	pg_assert_same(array('delivery' => 'delivered', 'delivered_at' => 11), pg_whatsapp_delivery_change($fresh, array('status' => 'delivered', 'at' => 11), 99), 'delivered');
	pg_assert_same(array('delivery' => 'read', 'delivered_at' => 99, 'read_at' => 99), pg_whatsapp_delivery_change($fresh, array('status' => 'read', 'at' => 0), 99), 'read before delivered, at now');

	$read = array('delivery' => 'read', 'delivered_at' => 11, 'read_at' => 12);
	pg_assert_same(array(), pg_whatsapp_delivery_change($read, array('status' => 'delivered', 'at' => 13), 99), 'never back');
	pg_assert_same(array(), pg_whatsapp_delivery_change($read, array('status' => 'read', 'at' => 13), 99), 'twice');
	pg_assert_same(array(), pg_whatsapp_delivery_change(array('delivery' => 'delivered', 'delivered_at' => 11, 'read_at' => 0), array('status' => 'failed', 'at' => 13, 'error_code' => '131026', 'error' => 'x'), 99), 'a delivered message did not fail');

	pg_assert_same(array('delivery' => 'failed', 'error_code' => '131026', 'last_error' => 'Message undeliverable'),
		pg_whatsapp_delivery_change(array('delivery' => 'sent', 'delivered_at' => 0, 'read_at' => 0), array('status' => 'failed', 'at' => 13, 'error_code' => '131026', 'error' => 'Message undeliverable'), 99), 'failed');
	pg_assert_same(array(), pg_whatsapp_delivery_change($fresh, array('status' => 'deleted'), 99), 'unknown state');
}

function test_whatsapp_inbox_notice()
{
	$event = array('from' => '905321234567', 'from_user_id' => '', 'profile_name' => 'Ayşe', 'kind' => 'text', 'body' => "Merhaba\n  nasılsınız");

	pg_assert_same(array('action' => 'whatsapp_message', 'type' => 'info', 'title' => 'Merhaba nasılsınız', 'order_total' => 'Ayşe', 'form_id' => 7, 'user' => 'system'), pg_whatsapp_inbox_notice(7, $event), 'notice');
	pg_assert_same('+905321234567', pg_whatsapp_inbox_notice(7, array('profile_name' => '') + $event)['order_total'], 'by number without a profile');
	pg_assert_same('[image]', pg_whatsapp_inbox_notice(7, array('kind' => 'image', 'body' => '') + $event)['title'], 'a picture without a caption');
}

function test_whatsapp_inbox_bell_and_history()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/notifications.php');

	$row = array('action' => 'whatsapp_message', 'target_user_id' => 0);
	pg_assert_true(pg_notification_visible($row, array('id' => 1, 'role' => 2, 'manage_contacts' => false)), 'a manager');

	// The user role: only a contact filed in one of their contact groups
	// (user 4 holds contact 9 through a group, not contact 10).
	$asked = array();
	$groups = function ($user_id, $contact_id) use (&$asked) {
		$asked[] = array($user_id, $contact_id);

		return ($user_id === 4) && ($contact_id === 9);
	};
	$user = array('id' => 4, 'role' => 3, 'manage_contacts' => true);

	pg_assert_true(pg_notification_whatsapp_visible($user, 9, $groups), 'a user, the contact in their group');
	pg_assert_false(pg_notification_whatsapp_visible($user, 10, $groups), 'a user, the contact outside their groups');
	pg_assert_false(pg_notification_whatsapp_visible($user, 0, $groups), 'a user, a number tied to no contact');
	pg_assert_false(pg_notification_whatsapp_visible(array('manage_contacts' => false) + $user, 9, $groups), 'a user without the contacts right');
	pg_assert_true(pg_notification_whatsapp_visible(array('id' => 5, 'role' => 2), 0, $groups), 'a manager, a number tied to no contact');
	pg_assert_same(array(array(4, 9), array(4, 10)), $asked, 'groups asked only for a user with the right and a contact');

	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/customer_history.php');

	$rights = array('whatsapp' => true);
	$data = array('whatsapp' => array(array('id' => 5, 'received_at' => 1763000100, 'contact_id' => 9, 'from_number' => '905321234567', 'profile_name' => '', 'type' => 'text', 'body' => 'Siparişim nerede?')));
	$page = ws_customer_history_build($rights, $data, function () { return ''; }, '/pg/', 20);
	pg_assert_same(1, count($page['entries']), 'one line');
	pg_assert_same('whatsapp', $page['entries'][0]['kind'], 'kind');
	pg_assert_same('+905321234567: Siparişim nerede?', $page['entries'][0]['detail'], 'sender and text');
	pg_assert_same('/pg/edit_contact.php?id=9', $page['entries'][0]['url'], 'opens the contact');

	$page = ws_customer_history_build(array('whatsapp' => false), $data, function () { return ''; }, '/pg/', 20);
	pg_assert_same(array(), $page['entries'], 'not without the contacts right');

	// Which contact's messages the history reads: a user-role member opens
	// only contacts of their groups (user 4 holds contact 9, not 10).
	$open = function ($viewer, $ids) {
		return ((int) $viewer['role'] < 3) ? $ids : array_values(array_intersect($ids, ((int) $viewer['id'] === 4) ? array(9) : array()));
	};
	$user = array('id' => 4, 'role' => 3, 'contacts' => true);

	pg_assert_same(9, ws_customer_history_whatsapp_contact($user, array('contact_id' => 9), $open), 'a user, the contact in their group');
	pg_assert_same(0, ws_customer_history_whatsapp_contact($user, array('contact_id' => 10), $open), 'a user, the contact outside their groups');
	pg_assert_same(0, ws_customer_history_whatsapp_contact(array('contacts' => false) + $user, array('contact_id' => 9), $open), 'no contacts right');
	pg_assert_same(10, ws_customer_history_whatsapp_contact(array('id' => 5, 'role' => 2, 'contacts' => true), array('contact_id' => 10), $open), 'a manager');
	pg_assert_same(0, ws_customer_history_whatsapp_contact($user, array('contact_id' => 0), $open), 'a channel about no contact');
}

function test_whatsapp_webhook_wiring()
{
	$receiver = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/whatsapp.php');
	pg_assert_contains('pg_whatsapp_webhook_apply(pg_whatsapp_webhook_events(json_decode($body, true), $app[\'phone_number_id\']), $app);', $receiver, 'a signed post is read');

	$job = (string) file_get_contents(PG_FUNCTIONS_DIR . '/job.php');
	pg_assert_contains('pg_whatsapp_inbox_sweep(time());', $job, 'two years');

	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_whatsapp_inbox();          // 11.0.198', $migration, 'step');
	pg_assert_contains('UNIQUE KEY uq_wamid (wamid)', $migration, 'kept once');

	$install = (string) file_get_contents(PG_FUNCTIONS_DIR . '/install/index.php');
	pg_assert_contains("'whatsapp_inbox',", $install, 'get_tables()');
}
