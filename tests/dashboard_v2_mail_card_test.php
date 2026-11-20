<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Incoming e-mail card
 * (includes/dashboard/v2/cards/mail.php, assets/js/pg_dash_cards_mail.js):
 * who is offered it, the rows it hands its renderer from the list of
 * Communication › Incoming e-mail, how a row names its conversation as the
 * screen does, and the wiring. Reading the conversations needs the database
 * and is not run here.
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
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/gallery.php');

if (is_file(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/mail.php')) {
	require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/mail.php');
}

// A row of ws_mail_list().
function dashboard_v2_mail_test_row($changes = array())
{
	return array_merge(array('id' => 4, 'channel_id' => 3, 'subject' => 'Order <late>', 'customer_name' => 'Ayşe', 'customer_email' => 'ayse@example.com',
		'customer_phone' => '', 'status' => 'open', 'assignee_id' => 0, 'last_in_at' => 100, 'last_out_at' => 50, 'transport' => 'email',
		'box_name' => 'Support', 'channel_name' => 'support', 'mails' => 2, 'snippet' => 'Where is it?', 'unread' => true, 'unsent' => ''), $changes);
}

// The card is in the list, built, narrow, without a period, in the gallery,
// and behind its own gate.
function test_dashboard_v2_mail_card_registered()
{
	$cards = pg_dashboard_v2_cards();

	pg_assert_true(isset($cards['mail']), 'the card');
	pg_assert_true($cards['mail']['built'], 'built');
	pg_assert_same('mail', $cards['mail']['gate'], 'its gate');
	pg_assert_same('S', $cards['mail']['width'], 'narrow');
	pg_assert_same(null, $cards['mail']['period'], 'no period');
	pg_assert_same('bi-envelope', $cards['mail']['icon'], 'the tab\'s icon');
	pg_assert_true(in_array('mail', pg_dashboard_v2_gates(), true), 'a known gate');
	pg_assert_true(isset(pg_dashboard_v2_gallery_cards()['mail']), 'in the gallery');
	pg_assert_true(function_exists('pg_dashboard_v2_card_mail'), 'its function');
	pg_assert_true(in_array('mail', pg_dashboard_v2_card_timing()['refresh'], true), 'asked again at the refresh time');
}

// Offered to whoever may open the tab: the tab's own gate, while the
// unread marks' table is there (the screen refuses without it).
function test_dashboard_v2_mail_gate()
{
	pg_assert_true(function_exists('pg_dashboard_v2_mail_gate'), 'the gate');

	pg_assert_true(pg_dashboard_v2_mail_gate(true, true), 'reads a mailbox');
	pg_assert_false(pg_dashboard_v2_mail_gate(false, true), 'reads no mailbox');
	pg_assert_false(pg_dashboard_v2_mail_gate(true, false), 'marks not installed');
	pg_assert_false(pg_dashboard_v2_gate('mail', array('id' => 0, 'role' => 0)), 'nobody signed in');
}

// A row names its conversation as the screen's row does.
function test_workspace_mail_list_row_names()
{
	pg_assert_same(array('who' => 'Ayşe', 'subject' => 'Order <late>', 'transport' => 'email', 'last' => 100), ws_mail_list_row_names(dashboard_v2_mail_test_row()));

	$bare = ws_mail_list_row_names(dashboard_v2_mail_test_row(array('customer_name' => ' ', 'subject' => ' ', 'last_out_at' => 300)));
	pg_assert_same('ayse@example.com', $bare['who'], 'the address without a name');
	pg_assert_same(lang('(no subject)'), $bare['subject']);
	pg_assert_same(300, $bare['last'], 'the newest mail either way');

	$phone = ws_mail_list_row_names(dashboard_v2_mail_test_row(array('customer_name' => '', 'customer_email' => '', 'customer_phone' => '905551112233', 'subject' => '', 'transport' => 'whatsapp')));
	pg_assert_same('+905551112233', $phone['who'], 'the number');
	pg_assert_same('', $phone['subject'], 'a WhatsApp conversation has no subject');
	pg_assert_same('whatsapp', $phone['transport']);
}

// The card's rows: who, subject, the time as the screen prints it, unread,
// and the screen's address with the conversation; at most the card's count.
function test_dashboard_v2_mail_rows()
{
	pg_assert_true(function_exists('pg_dashboard_v2_mail_rows'), 'the rows');

	$rows = pg_dashboard_v2_mail_rows(array(dashboard_v2_mail_test_row()), '/pg/');

	pg_assert_same(array(array(
		'id'      => 4,
		'who'     => 'Ayşe',
		'subject' => 'Order <late>',
		'kind'    => '',
		'time'    => ws_time_label(100),
		'unread'  => true,
		'url'     => '/pg/messages_mail.php?conversation=4',
	)), $rows, 'text as it is: the renderer writes it with textContent');

	$read = pg_dashboard_v2_mail_rows(array(dashboard_v2_mail_test_row(array('id' => 9, 'unread' => false))), '/pg/');
	pg_assert_false($read[0]['unread'], 'read');
	pg_assert_same('/pg/messages_mail.php?conversation=9', $read[0]['url']);

	$sms = pg_dashboard_v2_mail_rows(array(dashboard_v2_mail_test_row(array('subject' => '', 'transport' => 'sms'))), '/pg/');
	pg_assert_same('', $sms[0]['subject'], 'no subject');
	pg_assert_same(lang('SMS'), $sms[0]['kind'], 'the kind in its place');

	$many = array();
	for ($i = 1; $i <= 12; $i++) {
		$many[] = dashboard_v2_mail_test_row(array('id' => $i));
	}
	$cut = pg_dashboard_v2_mail_rows($many, '/pg/');
	pg_assert_same(PG_DASHBOARD_V2_MAIL_SHOWN, count($cut), 'the newest eight');
	pg_assert_same(8, PG_DASHBOARD_V2_MAIL_SHOWN);
	pg_assert_same(array(1, 2, 3, 4, 5, 6, 7, 8), array_column($cut, 'id'), 'in the list\'s order');
	pg_assert_same(array(), pg_dashboard_v2_mail_rows(array(), '/pg/'), 'none');
}

// The card reads the list the screen draws, Incoming by its defaults, and
// the count the menu's Communication row shows; the renderer draws texts,
// never markup.
function test_dashboard_v2_mail_card_wiring()
{
	$file = PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/mail.php';
	$card = is_file($file) ? (string) file_get_contents($file) : '';

	pg_assert_contains('ws_mail_list($viewer, $filters, time(), PG_DASHBOARD_V2_MAIL_SHOWN)', $card, 'the screen\'s list');
	pg_assert_contains('$filters = ws_mail_list_filters(array());', $card, 'the Incoming view as the tab opens');
	pg_assert_contains('pg_messages_mail_unread($user)', $card, 'the menu\'s unread count');
	pg_assert_contains("lang('No e-mail waiting')", $card, 'the empty line');

	$js_file = PG_FUNCTIONS_DIR . '/assets/js/pg_dash_cards_mail.js';
	$js = is_file($js_file) ? (string) file_get_contents($js_file) : '';

	pg_assert_contains('pgDash.renderers.mail = function', $js, 'the renderer');
	pg_assert_same(false, strpos($js, 'innerHTML'), 'no markup from the mail');
	pg_assert_true(in_array('assets/js/pg_dash_cards_mail.js', pg_js_bundles()['assets/js/pg_dash.min.js'], true), 'in the bundle');
}
