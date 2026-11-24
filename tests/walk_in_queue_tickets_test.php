<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for taking a walk-in queue number and the person's page
 * (includes/fn/walk_in_queue_tickets.php, queue.php): the join form read
 * back, the ways a number is taken, the notices' texts, channels and bits,
 * what the person's page says, and the wiring of the page, the firewall
 * and the SMS kind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function walk_in_queue_tickets_test_place($settings = array())
{
	return array(
		'id'       => 4,
		'name'     => 'Town hall',
		'status'   => 'open',
		'services' => array(
			array('key' => 's1', 'name' => 'Teller', 'letter' => 'A', 'minutes' => 6),
			array('key' => 's2', 'name' => 'Loans', 'letter' => 'B', 'minutes' => 20),
		),
		'settings' => pg_waiting_place_settings($settings),
	);
}

function test_walk_in_queue_tickets_join_input_reads_the_form()
{
	$place = walk_in_queue_tickets_test_place();
	$read = pg_waiting_join_input(array('service' => 's2', 'name' => '  Ayşe  ', 'phone' => '0532 123 45 67', 'email' => ''), $place);

	pg_assert_same('', $read['error']);
	pg_assert_same(array('service' => 's2', 'name' => 'Ayşe', 'phone' => '905321234567', 'email' => '', 'party_size' => 0, 'priority' => '', 'booking' => false), $read['values']);

	// Name, phone and e-mail are optional.
	pg_assert_same('', pg_waiting_join_input(array('service' => 's1'), $place)['error']);
}

function test_walk_in_queue_tickets_join_input_refuses_what_it_cannot_read()
{
	$place = walk_in_queue_tickets_test_place();

	pg_assert_same(lang('Choose a service.'), pg_waiting_join_input(array('service' => 's9'), $place)['error'], 'a service of another place');
	pg_assert_same(lang('This phone number cannot be read.'), pg_waiting_join_input(array('service' => 's1', 'phone' => '12'), $place)['error']);
	pg_assert_same(lang('This e-mail address cannot be read.'), pg_waiting_join_input(array('service' => 's1', 'email' => 'not-an-address'), $place)['error']);

	// A place of one service needs no choice.
	$one = $place;
	$one['services'] = array($place['services'][0]);
	pg_assert_same('s1', pg_waiting_join_input(array(), $one)['values']['service']);
}

function test_walk_in_queue_tickets_party_size_only_when_asked()
{
	$read = pg_waiting_join_input(array('service' => 's1', 'party_size' => '4'), walk_in_queue_tickets_test_place());
	pg_assert_same(0, $read['values']['party_size'], 'not asked: not kept');

	$asked = walk_in_queue_tickets_test_place(array('party_size' => 1));
	pg_assert_same(4, pg_waiting_join_input(array('service' => 's1', 'party_size' => '4'), $asked)['values']['party_size']);
	pg_assert_same(1, pg_waiting_join_input(array('service' => 's1', 'party_size' => ''), $asked)['values']['party_size'], 'one by default');
	pg_assert_same(PG_WAITING_PARTY_MAX, pg_waiting_join_input(array('service' => 's1', 'party_size' => '500'), $asked)['values']['party_size']);
}

function test_walk_in_queue_tickets_way_follows_the_state_and_the_switches()
{
	$place = walk_in_queue_tickets_test_place(array('ways' => array('qr' => 1, 'site' => 0, 'kiosk' => 0)));

	pg_assert_true(pg_waiting_way_open($place, 'qr'));
	pg_assert_false(pg_waiting_way_open($place, 'site'), 'the site page is off');
	pg_assert_true(pg_waiting_way_open($place, 'staff'), 'staff add a number whatever the switches');
	pg_assert_false(pg_waiting_way_open($place, 'other'));

	$place['status'] = 'paused';
	pg_assert_false(pg_waiting_way_open($place, 'qr'));
	pg_assert_false(pg_waiting_way_open($place, 'staff'), 'not while paused or closed');
}

function test_walk_in_queue_tickets_notice_texts_fill_their_places()
{
	$values = array('place' => 'Town hall', 'number' => 'A-012', 'desk' => 'Desk 2', 'link' => 'https://example.com/q', 'ahead' => 3);

	pg_assert_same('A-012 to Desk 2, see https://example.com/q', pg_waiting_notice_text('called', '{number} to {desk}, see {link}', $values));

	foreach (array('taken', 'ahead', 'called') as $kind) {
		$text = pg_waiting_notice_text($kind, '', $values);
		pg_assert_contains('A-012', $text, $kind);
		pg_assert_false(strpos($text, '{') !== false, $kind . ': every place filled');
	}

	pg_assert_contains('https://example.com/q', pg_waiting_notice_text('taken', '', $values), 'the taken text carries the link');
	pg_assert_contains('Desk 2', pg_waiting_notice_text('called', '', $values), 'the call names the desk');
}

function test_walk_in_queue_tickets_notice_bits_allow_three_messages()
{
	pg_assert_same(array(1, 2, 4), array(pg_waiting_notice_bit('taken'), pg_waiting_notice_bit('ahead'), pg_waiting_notice_bit('called')));
	pg_assert_same(0, pg_waiting_notice_bit('other'));
}

function test_walk_in_queue_tickets_notice_channel()
{
	$notices = pg_waiting_place_settings(null)['notices'];

	pg_assert_same('phone', pg_waiting_notice_channel($notices, '905321234567', 'a@example.com'));
	pg_assert_same('email', pg_waiting_notice_channel($notices, '', 'a@example.com'), 'e-mail only when no phone is given');
	pg_assert_same('', pg_waiting_notice_channel($notices, '', ''));

	$notices['sms'] = 0;
	pg_assert_same('', pg_waiting_notice_channel($notices, '905321234567', 'a@example.com'), 'a phone given, no phone message on: no e-mail either');

	$notices['whatsapp'] = 1;
	pg_assert_same('phone', pg_waiting_notice_channel($notices, '905321234567', ''));

	$notices['email'] = 0;
	pg_assert_same('', pg_waiting_notice_channel($notices, '', 'a@example.com'));
}

function test_walk_in_queue_tickets_ahead_notice_is_due_once_within_the_setting()
{
	pg_assert_true(pg_waiting_ahead_due(3, 3, 1));
	pg_assert_true(pg_waiting_ahead_due(0, 3, 1));
	pg_assert_false(pg_waiting_ahead_due(4, 3, 1), 'more ahead than the setting');
	pg_assert_false(pg_waiting_ahead_due(2, 3, 1 | 2), 'sent already');
	pg_assert_false(pg_waiting_ahead_due(null, 3, 0), 'not waiting');
}

function test_walk_in_queue_tickets_status_view()
{
	$waiting = pg_waiting_status_view('waiting', 4, 15, '');
	pg_assert_false($waiting['final']);
	pg_assert_same(lang(array('string' => '{var:1} ahead of you', 'vars' => array(4))), $waiting['headline']);
	pg_assert_same(lang(array('string' => 'About {var:1} minutes', 'vars' => array(15))), $waiting['line']);

	pg_assert_same(lang('You are next'), pg_waiting_status_view('waiting', 0, 0, '')['headline']);
	pg_assert_same('', pg_waiting_status_view('waiting', 2, null, '')['line'], 'no estimate: only the people ahead');

	$called = pg_waiting_status_view('called', null, null, 'Desk 2');
	pg_assert_false($called['final'], 'still read: the call may be repeated');
	pg_assert_contains('Desk 2', $called['line']);

	foreach (array('served', 'left', 'no_show') as $state) {
		pg_assert_true(pg_waiting_status_view($state, null, null, '')['final'], $state);
	}
}

function test_walk_in_queue_tickets_link()
{
	pg_assert_same('https://example.com/pinegrap/queue.php?n=12&t=' . str_repeat('a', 64), pg_waiting_ticket_link('https://example.com/pinegrap/queue.php', 12, str_repeat('a', 64)));
}

function test_walk_in_queue_tickets_wiring()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue.php');
	$waf = (string) file_get_contents(PG_FUNCTIONS_DIR . '/waf.php');
	$start = strpos($waf, 'function waf_sensitive_scripts()');

	pg_assert_true(strpos(substr($waf, $start, strpos($waf, "\n}\n", $start) - $start), "'queue.php'") !== false, 'counted on the sensitive allowance');
	pg_assert_contains('validate_token_field()', $page, 'a write carries the form token');
	pg_assert_contains("header('X-Robots-Tag: noindex, nofollow')", $page);
	pg_assert_contains('Pinegrap - Enterprise Website Platform', $page);
	pg_assert_same(false, pg_sms_kinds()['queue'], 'a queue notice is a service message');
	pg_assert_true(isset(pg_sms_queue_list_kinds()['queue']), 'the Sends list names the kind');

	foreach (array('queue_taken', 'queue_ahead', 'queue_called') as $event) {
		pg_assert_same('queue', pg_whatsapp_message_events()[$event]['kind'], $event);
	}
}

// Runs assets/js/queue_page.js on a stub page drawn for a waiting number,
// answers its 15-second reads one by one with $statuses and gives back what
// the page shows after the last; null without Node.
function walk_in_queue_tickets_test_page_after($statuses)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$stub = "var nodes = {};\n"
		. "['number', 'headline', 'line', 'note'].forEach(function (n) { nodes['[data-pg-queue-field=\"' + n + '\"]'] = {textContent: 'old ' + n, hidden: false}; });\n"
		. "nodes['[data-pg-queue-leave]'] = {hidden: false, getAttribute: function () { return ''; }, addEventListener: function () {}};\n"
		. "nodes['[data-pg-queue-on-way]'] = {hidden: false};\n"
		. "nodes['[data-pg-queue-read]'] = {getAttribute: function () { return 'read'; }};\n"
		. "var tick = null, answers = " . json_encode(array_values($statuses)) . ", at = 0;\n"
		. "global.document = {hidden: false, querySelector: function (s) { return nodes[s] || null; }, addEventListener: function () {}, removeEventListener: function () {}};\n"
		. "global.window = {setInterval: function (f) { tick = f; return 1; }, clearInterval: function () {}, confirm: function () { return true;},\n"
		. "  fetch: function () { var a = answers[at++]; return Promise.resolve({ok: true, json: function () { return a; }}); }};\n"
		. "require(" . json_encode(PG_FUNCTIONS_DIR . '/assets/js/queue_page.js') . ");\n"
		. "function step() { if (at >= answers.length) { return done(); } tick(); setTimeout(step, 10); }\n"
		. "function done() { console.log(JSON.stringify({number: nodes['[data-pg-queue-field=\"number\"]'].textContent, leave: !nodes['[data-pg-queue-leave]'].hidden, on_way: !nodes['[data-pg-queue-on-way]'].hidden})); }\n"
		. "step();\n";
	$file = tempnam(sys_get_temp_dir(), 'pgqueue') . '.js';
	file_put_contents($file, $stub);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

function test_walk_in_queue_tickets_page_shows_the_new_number_after_a_transfer()
{
	// "Send to another service" gives the number the other service's letter
	// and count; the page kept showing the old one until reloaded, while the
	// lobby screen called the new one.
	$page = walk_in_queue_tickets_test_page_after(array(array('ok' => true, 'number' => 'B-004', 'state' => 'waiting', 'headline' => '2 ahead of you', 'line' => '', 'note' => '', 'on_way' => false, 'final' => false)));

	if ($page === null) {
		return;
	}

	pg_assert_same('B-004', $page['number']);
	pg_assert_contains('data-pg-queue-field="number"', (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue.php'), 'the number is a field the read fills');
}

function test_walk_in_queue_tickets_page_offers_leave_again_when_the_number_waits_again()
{
	$called = array('ok' => true, 'number' => 'A-012', 'state' => 'called', 'headline' => 'It is your turn', 'line' => '', 'note' => '', 'on_way' => false, 'final' => false);
	$page = walk_in_queue_tickets_test_page_after(array($called));

	if ($page === null) {
		return;
	}

	pg_assert_false($page['leave'], 'a called number cannot leave');

	// Not here moved it back, or it was sent to another service: it waits
	// again and may leave again, and say it is on its way.
	$page = walk_in_queue_tickets_test_page_after(array($called, array('state' => 'waiting', 'on_way' => true) + $called));
	pg_assert_true($page['leave'], 'a waiting number may leave');
	pg_assert_true($page['on_way'], 'I\'m on my way is shown while offered');
}

function test_walk_in_queue_tickets_a_second_press_gets_the_number_just_taken()
{
	// Two presses of Take a number (or a resent form) each took a number:
	// the page showed the second, and the first waited until a desk called
	// it as not here. The browser's number of the same service, taken a
	// moment ago and still waiting, is given again.
	$now = 1764000000;
	$kept = array('id' => 8, 'service' => 's1', 'state' => 'waiting', 'day' => pg_waiting_today($now), 'joined_at' => $now - 5);

	pg_assert_true(pg_waiting_take_repeated($kept, 's1', $now));
	pg_assert_false(pg_waiting_take_repeated($kept, 's2', $now), 'another service is a new number');
	pg_assert_false(pg_waiting_take_repeated(array('state' => 'left') + $kept, 's1', $now), 'it left the queue');
	pg_assert_false(pg_waiting_take_repeated($kept, 's1', $now + PG_WAITING_REPEAT_SECONDS + 10), 'taken a while ago: a new number on purpose');
	pg_assert_false(pg_waiting_take_repeated(null, 's1', $now));

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue.php');
	$kiosk = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue_kiosk.php');
	pg_assert_contains('pg_waiting_take_repeated(', $page);
	pg_assert_contains('pg_waiting_take_repeated(', $kiosk);
}
