<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a booking's messages (2026.11.0, 11.0.161;
 * includes/fn/booking_messages.php): the step that widens the two campaign
 * action lists, the .ics file, the times in the site's zone, the mail-merge
 * fields, the reminders' timing, the SMS events and the profile form's
 * booking actions.
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
// real runner helpers, a fake db layer): twice on the lists as 11.0.132 left
// them, once on a site without the tables. Null when PHP cannot be started
// again.
function booking_messages_test_run_step()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgbkmail');

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
	'email_campaign_profiles' => array('action' => "enum('calendar_event_reserved','subscription_price_changed')"),
	'email_campaigns' => array('action' => "enum('','calendar_event_reserved','subscription_price_changed')"),
);

foreach (array(1, 2) as $run) {
	$GLOBALS['fake_writes'] = array();
	upgrade_2026_11_0_booking_campaigns();
	$out['runs'][] = $GLOBALS['fake_writes'];
}

$GLOBALS['fake_schema'] = array();
$GLOBALS['fake_writes'] = array();
upgrade_2026_11_0_booking_campaigns();
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

// The four actions are appended to both lists once; the second run and a
// site without the tables write nothing.
function test_booking_messages_step_run_twice_changes_nothing_the_second_time()
{
	$result = booking_messages_test_run_step();

	if ($result === null) {
		return;
	}

	pg_assert_true(!isset($result['error']), 'the step ran: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	list($first, $second) = $result['runs'];

	pg_assert_same(array(
		"ALTER TABLE `email_campaign_profiles` MODIFY `action` ENUM('calendar_event_reserved','subscription_price_changed','booking_confirmed','booking_reminder','booking_rescheduled','booking_cancelled') NOT NULL DEFAULT ''",
		"ALTER TABLE `email_campaigns` MODIFY `action` ENUM('','calendar_event_reserved','subscription_price_changed','booking_confirmed','booking_reminder','booking_rescheduled','booking_cancelled') NOT NULL DEFAULT ''",
	), $first, 'appended once, at the end of each list');
	pg_assert_same(array(), $second, 'the second run writes nothing');
	pg_assert_same(array(), $result['missing'], 'nothing written where the tables are missing');

	$migration = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');
	pg_assert_same(1, substr_count($migration, "\tupgrade_2026_11_0_booking_campaigns();"), 'called once');
	pg_assert_true((bool) preg_match('~\tupgrade_2026_11_0_booking_campaigns\(\); +// 11\.0\.161\n~', $migration), 'under 11.0.161');
}

function booking_messages_test_row($overrides = array())
{
	return $overrides + array(
		'id'           => 42,
		'service_id'   => 3,
		'staff_id'     => 5,
		'user_id'      => 0,
		'name'         => 'Ayşe Yılmaz',
		'email'        => 'ayse@example.com',
		'phone'        => '05321234567',
		'start_at'     => 1793343600, // 2026-10-30 07:00 UTC, 10:00 in Istanbul
		'end_at'       => 1793346300,
		'status'       => 'confirmed',
		'source'       => 'site',
		'manage_token' => str_repeat('a', 64),
		'created_at'   => 1793000000,
		'updated_at'   => 1793000000,
		'service_name' => 'Haircut, wash; dry',
		'staff_name'   => 'Deniz Kaya',
	);
}

// One VEVENT in UTC with the booking's UID, CRLF endings, text escaped and
// long lines folded.
function test_booking_messages_ics_shape()
{
	$ics = pg_booking_ics(booking_messages_test_row(), array(
		'host'       => 'Shop.Example.com',
		'store'      => 'Salon',
		'place'      => "Main St 1\nIstanbul",
		'manage_url' => 'https://shop.example.com/booking_manage.php?b=42&t=' . str_repeat('b', 64),
	), 1793000100);

	pg_assert_true(strpos($ics, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n") === 0, 'starts with the calendar');
	pg_assert_same("END:VCALENDAR\r\n", substr($ics, -15), 'ends with CRLF');
	pg_assert_same(0, preg_match("/(?<!\r)\n/", $ics), 'no bare LF');
	pg_assert_contains("\r\nMETHOD:PUBLISH\r\n", $ics);
	pg_assert_contains("\r\nUID:booking-42@shop.example.com\r\n", $ics);
	pg_assert_contains("\r\nDTSTAMP:20261026T073500Z\r\n", $ics, 'DTSTAMP in UTC');
	pg_assert_contains("\r\nDTSTART:20261030T070000Z\r\n", $ics);
	pg_assert_contains("\r\nDTEND:20261030T074500Z\r\n", $ics);
	pg_assert_contains("\r\nSEQUENCE:0\r\n", $ics);
	pg_assert_contains("\r\nSTATUS:CONFIRMED\r\n", $ics);
	pg_assert_contains("\r\nSUMMARY:Haircut\\, wash\\; dry - Salon\r\n", $ics, 'comma and semicolon escaped');
	pg_assert_contains("\r\nLOCATION:Main St 1\\nIstanbul\r\n", $ics, 'line break escaped');

	foreach (explode("\r\n", rtrim($ics, "\r\n")) as $line) {
		pg_assert_true(strlen($line) <= 75, 'line within 75 octets: ' . $line);
	}

	// The folded URL reads back whole.
	pg_assert_contains('URL:https://shop.example.com/booking_manage.php?b=42&t=' . str_repeat('b', 64), str_replace("\r\n ", '', $ics));
}

// A moved booking raises SEQUENCE; a cancelled one is CANCELLED, a request
// TENTATIVE.
function test_booking_messages_ics_status_and_sequence()
{
	$context = array('host' => 'shop.example.com', 'store' => 'Salon');

	$ics = pg_booking_ics(booking_messages_test_row(array('status' => 'cancelled', 'updated_at' => 1793000500)), $context, 1793000600);
	pg_assert_contains("\r\nSTATUS:CANCELLED\r\n", $ics);
	pg_assert_contains("\r\nSEQUENCE:500\r\n", $ics);
	pg_assert_false(strpos($ics, 'URL:') !== false, 'no link without one');

	$ics = pg_booking_ics(booking_messages_test_row(array('status' => 'requested')), $context, 1793000600);
	pg_assert_contains("\r\nSTATUS:TENTATIVE\r\n", $ics);
}

// A fold never cuts a UTF-8 character.
function test_booking_messages_ics_fold_keeps_characters_whole()
{
	$line = 'SUMMARY:' . str_repeat('ğ', 60);
	$folded = pg_booking_ics_fold($line);

	foreach (explode("\r\n", $folded) as $part) {
		pg_assert_true(strlen($part) <= 75, 'within 75 octets');
		pg_assert_true(mb_check_encoding($part, 'UTF-8'), 'whole characters');
	}

	pg_assert_same($line, str_replace("\r\n ", '', $folded), 'unfolds to the line');
	pg_assert_same('SHORT', pg_booking_ics_fold('SHORT'));
}

// The day and times in the site's zone and formats, the zone named.
function test_booking_messages_times_in_site_zone()
{
	$times = pg_booking_message_times(1793343600, 1793346300, 'Europe/Istanbul', 'j/n/Y', 'H:i');
	pg_assert_same(array('date' => '30/10/2026', 'time' => '10:00 – 10:45', 'zone' => 'Europe/Istanbul', 'start' => '2026-10-30 10:00:00'), $times);

	// The autumn change in Berlin (2026-10-25 03:00 CEST → 02:00 CET): the
	// same UTC hour reads an hour apart either side.
	$times = pg_booking_message_times(1792886400, 1792890000, 'Europe/Berlin', 'n/j/Y', 'g:i A');
	pg_assert_same('10/25/2026', $times['date']);
	pg_assert_same('2:00 AM – 2:00 AM', $times['time'], 'before and after the change');
}

// Every field the form lists is filled, the start as a date and time.
function test_booking_messages_mail_fields()
{
	$values = pg_booking_message_values(booking_messages_test_row(), array(
		'zone' => 'Europe/Istanbul', 'date_code' => 'j/n/Y', 'time_code' => 'H:i',
		'place' => 'Main St 1, Istanbul', 'manage_url' => 'https://x.example/m',
	));
	$fields = pg_booking_mail_fields($values);
	$names = array();

	foreach ($fields as $field) {
		$names[$field['name']] = $field;
	}

	pg_assert_same(array_keys(pg_booking_mail_field_meanings()), array_keys($names), 'every listed field');
	pg_assert_same('Haircut, wash; dry', $names['booking_service']['data']);
	pg_assert_same('Deniz Kaya', $names['booking_staff']['data']);
	pg_assert_same('10:00 – 10:45', $names['booking_time']['data']);
	pg_assert_same('42', $names['booking_reference']['data']);
	pg_assert_same('https://x.example/m', $names['booking_manage_url']['data']);
	pg_assert_same('date and time', $names['booking_start']['type']);
	pg_assert_same('2026-10-30 10:00:00', $names['booking_start']['data']);
}

// The address on one line, empty parts left out.
function test_booking_messages_place_text()
{
	pg_assert_same('Main St 1, 34000 Istanbul, TR', pg_booking_place_text(array('address_1' => 'Main St 1', 'address_2' => ' ', 'zip_code' => '34000', 'city' => 'Istanbul', 'country' => 'TR')));
	pg_assert_same('', pg_booking_place_text(array()));
}

// A reminder goes at its moment once; after a long stop only the nearest
// one; not for a booking changed after the moment or already started.
function test_booking_messages_reminder_due()
{
	$start = 1793343600;
	$hours = array(24, 2);
	$created = $start - 5 * 86400;

	pg_assert_same(24, pg_booking_reminder_due($start, $created, $hours, $start - 86400 - 300, $start - 86400 + 60), 'the 24 h moment fell in the pass');
	pg_assert_same(0, pg_booking_reminder_due($start, $created, $hours, $start - 86400 + 60, $start - 86400 + 360), 'not again in the next pass');
	pg_assert_same(0, pg_booking_reminder_due($start, $created, $hours, $start - 10 * 3600, $start - 9 * 3600), 'between the two');
	pg_assert_same(2, pg_booking_reminder_due($start, $created, $hours, $start - 7200 - 60, $start - 7200 + 240), 'the 2 h moment');
	pg_assert_same(2, pg_booking_reminder_due($start, $created, array(2, 24), $start - 30 * 3600, $start - 3600), 'after a long stop: the nearest one only');
	pg_assert_same(0, pg_booking_reminder_due($start, $start - 3 * 3600, $hours, $start - 86400 - 300, $start - 86400 + 60), 'booked or moved after the moment');
	pg_assert_same(0, pg_booking_reminder_due($start, $created, $hours, $start - 300, $start + 60), 'already started');
	pg_assert_same(0, pg_booking_reminder_due($start, $created, array(), $start - 86400 - 300, $start - 86400 + 60), 'no reminder hours');
}

// The event carries no manage token; the webhook catalogue knows the four
// events.
function test_booking_messages_event_payload_and_catalogue()
{
	$payload = pg_booking_event_payload(booking_messages_test_row());

	pg_assert_false(isset($payload['manage_token']), 'no token');
	pg_assert_same('2026-10-30T07:00:00Z', $payload['start_at']);
	pg_assert_same(42, $payload['id']);

	$webhooks = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/outbound/webhooks.php');

	foreach (array('created', 'confirmed', 'rescheduled', 'cancelled') as $event) {
		pg_assert_contains("'booking." . $event . "'", $webhooks, 'booking.' . $event . ' can be subscribed to');
	}

	pg_assert_same('booking:42:reminder:1793343600', pg_booking_sms_reference(booking_messages_test_row(), 'reminder'));
}

// The four SMS events: kind booking (not commercial), offered only once the
// booking tables exist, with the fields their texts use.
function test_booking_messages_sms_events()
{
	$events = pg_sms_message_events();
	$kinds = pg_sms_kinds();

	pg_assert_same(false, $kinds['booking'], 'informational');

	foreach (pg_booking_mail_actions() as $event) {
		pg_assert_true(isset($events[$event]), $event . ' is an SMS event');
		pg_assert_same('booking', $events[$event]['kind']);
		pg_assert_false(pg_sms_message_event_shown($events[$event], true, false), 'hidden without the tables');
		pg_assert_true(pg_sms_message_event_shown($events[$event], false, true), 'shown with them');

		foreach (pg_sms_message_template_fields($events[$event]['text']) as $field) {
			pg_assert_true(in_array($field, $events[$event]['fields'], true), $event . ' text uses only its fields: ' . $field);
		}
	}

	pg_assert_true(pg_sms_message_event_shown($events['order_received'], false), 'an order event as before');
}

// The profile form takes the booking actions without commerce access: tied
// to no record, sent after the action, with the field list shown.
function test_booking_messages_profile_actions()
{
	foreach (pg_booking_mail_actions() as $action) {
		pg_assert_true(in_array($action, pg_email_campaign_profile_booking_actions(), true), $action);

		$values = pg_email_campaign_profile_action_values($action, array());
		pg_assert_same(array('0', 'after', 'action'), array($values['action_item_id'], $values['schedule_period'], $values['schedule_base']), $action . ': after the action, no record');

		$rows = pg_email_campaign_profile_action_rows($action);
		pg_assert_same('show', $rows['booking_fields'], $action . ' lists the fields');
		pg_assert_same('', $rows['subscription_fields']);
	}

	$mail = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/mail.php');

	foreach (pg_booking_mail_actions() as $action) {
		pg_assert_contains("case '" . $action . "':", $mail, $action . ' named in the log');
	}

	pg_assert_contains("'booking_manage_url'", $mail, 'the manage link loses the editor\'s http://');
	pg_assert_contains("'" . '" . e($campaign_item_id) . "' . "'", $mail, 'the campaign keeps the booking id');
}

// Only a booking campaign's mail gets an attachment.
function test_booking_messages_attachment_only_for_booking_campaigns()
{
	pg_assert_same(null, pg_booking_ics_attachment(array('action' => 'order_shipped', 'action_item_id' => 42)));
	pg_assert_same(null, pg_booking_ics_attachment(array('action' => 'booking_confirmed', 'action_item_id' => 0)));
}

// A request confirmed and a booking cancelled on the agenda announce
// themselves; other moves, an unchanged status and a hold do not.
function test_booking_messages_status_event()
{
	pg_assert_same('confirmed', pg_booking_status_event('requested', 'confirmed'));
	pg_assert_same('cancelled', pg_booking_status_event('confirmed', 'cancelled'));
	pg_assert_same('cancelled', pg_booking_status_event('requested', 'cancelled'));
	pg_assert_same('', pg_booking_status_event('confirmed', 'done'));
	pg_assert_same('', pg_booking_status_event('confirmed', 'no_show'));
	pg_assert_same('', pg_booking_status_event('confirmed', 'confirmed'));
	pg_assert_same('', pg_booking_status_event('held', 'confirmed'), 'pg_booking_confirm() announces a hold');

	pg_assert_contains('pg_booking_changed($booking_id, $status_event);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_booking.php'), 'the agenda calls it');
}

// The staff name of a booking's customer messages is the contact's name only:
// the row reaches the unauthenticated manage page, the mails and the .ics, so
// a staff member without a contact name never shows the panel login name.
function test_booking_messages_staff_public_name()
{
	pg_assert_same('Deniz Kaya', pg_booking_staff_public_name(' Deniz ', 'Kaya'));
	pg_assert_same('Deniz', pg_booking_staff_public_name('Deniz', null));
	pg_assert_same('', pg_booking_staff_public_name('', ''));
	pg_assert_same('', pg_booking_staff_public_name(null, null));

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/booking_messages.php');
	pg_assert_same(false, strpos($source, 'user_username'), 'the message row reads no login name');
}
