<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Settings > Bookings (includes/fn/booking_settings.php): the
 * policies and the widget's texts kept in config_text, a service's posted
 * row, the weekly hours grid, the dated exceptions entered as ranges of days,
 * the price box, the order of the week, the folded rows' lines, a staff
 * member's line, a new member's hours and the drawer a refused box sits in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Nothing stored reads as the defaults; every value is bounded.
function test_booking_settings_policy_defaults_and_bounds()
{
	$defaults = pg_booking_settings_policy_clean(array());

	pg_assert_same(120, $defaults['lead_minutes']);
	pg_assert_same(60, $defaults['horizon_days']);
	pg_assert_same(24, $defaults['cancel_hours']);
	pg_assert_same(true, $defaults['reschedule']);
	pg_assert_same(10, $defaults['hold_minutes']);
	pg_assert_same(array(24, 2), $defaults['reminder_hours']);
	pg_assert_same(true, $defaults['auto_confirm']);
	pg_assert_same(0, $defaults['step_minutes']);

	$policy = pg_booking_settings_policy_clean(array(
		'lead_minutes'   => '-5',
		'horizon_days'   => '5000',
		'cancel_hours'   => 'abc',
		'reschedule'     => '0',
		'hold_minutes'   => '1',
		'reminder_hours' => '2, 24, 24, 0, 500, x',
		'auto_confirm'   => '',
		'step_minutes'   => '7',
	));

	pg_assert_same(0, $policy['lead_minutes'], 'no negative lead');
	pg_assert_same(730, $policy['horizon_days'], 'horizon capped');
	pg_assert_same(0, $policy['cancel_hours'], 'unreadable is zero');
	pg_assert_same(false, $policy['reschedule']);
	pg_assert_same(2, $policy['hold_minutes'], 'hold at least two minutes');
	pg_assert_same(array(24, 2), $policy['reminder_hours'], 'repeats, zero and out of range dropped, latest first');
	pg_assert_same(false, $policy['auto_confirm']);
	pg_assert_same(5, $policy['step_minutes'], 'step on five minutes');

	pg_assert_same(array(), pg_booking_settings_policy_clean(array('reminder_hours' => ''))['reminder_hours'], 'no reminder');
	pg_assert_same(array(48, 24, 2), pg_booking_settings_policy_clean(array('reminder_hours' => array(2, 24, 48, 72)))['reminder_hours'], 'three at most, the nearest kept');

	// The stored JSON reads back the same; broken JSON is the defaults.
	pg_assert_same($policy, pg_booking_settings_policy_clean(json_decode(json_encode($policy), true)));
	pg_assert_same($defaults, pg_booking_settings_policy_read('{broken'));
	pg_assert_same(30, pg_booking_settings_policy_read('{"lead_minutes":30}')['lead_minutes']);
}

// The widget's texts: known keys only, no markup, bounded; empty means the
// widget's own wording.
function test_booking_settings_texts_clean()
{
	$texts = pg_booking_settings_texts_clean(array(
		'title' => '  <b>Book</b> a visit ',
		'intro' => str_repeat('a', 900),
		'done'  => '',
		'other' => 'x',
		'taken' => array('x'),
	));

	pg_assert_same(array('title' => 'Book a visit', 'intro' => str_repeat('a', 500)), $texts);
	pg_assert_same(array('title', 'intro', 'done', 'taken'), array_keys(pg_booking_settings_text_fields()));
}

// A price box reads both ways of writing a decimal and stores minor units.
function test_booking_settings_price()
{
	pg_assert_same(1250, pg_booking_settings_price_cents('12,50'));
	pg_assert_same(1250, pg_booking_settings_price_cents('12.50'));
	pg_assert_same(123456, pg_booking_settings_price_cents('1.234,56'));
	pg_assert_same(123456, pg_booking_settings_price_cents('1,234.56'));
	pg_assert_same(123400, pg_booking_settings_price_cents('1.234'));
	pg_assert_same(0, pg_booking_settings_price_cents(''));
	pg_assert_same(0, pg_booking_settings_price_cents('-'));
	pg_assert_same(null, pg_booking_settings_price_cents('99999999999'), 'above the column');

	pg_assert_same('1.234,56', pg_booking_settings_price_text(123456, array('decimal' => ',', 'thousands' => '.')));
	pg_assert_same('0.00', pg_booking_settings_price_text(0, array('decimal' => '.', 'thousands' => ',')));
}

// A service row: a name and a length are needed, the rest is bounded.
function test_booking_settings_service_clean()
{
	$answer = pg_booking_settings_service_clean(array(
		'name' => '  Haircut  ', 'description' => 'Wash and cut', 'length' => '45',
		'before' => '5', 'after' => '900', 'price' => '350,00', 'enabled' => '1', 'order' => '3',
	));

	pg_assert_same('', $answer['error']);
	pg_assert_same(array(
		'name' => 'Haircut', 'description' => 'Wash and cut', 'length_minutes' => 45,
		'buffer_before' => 5, 'buffer_after' => 480, 'price' => 35000, 'enabled' => 1, 'sort_order' => 3,
	), $answer['values']);

	pg_assert_same('name', pg_booking_settings_service_clean(array('name' => ' ', 'length' => '30'))['error']);
	pg_assert_same('length', pg_booking_settings_service_clean(array('name' => 'A', 'length' => '0'))['error']);
	pg_assert_same('length', pg_booking_settings_service_clean(array('name' => 'A', 'length' => '2000'))['error']);
	pg_assert_same('price', pg_booking_settings_service_clean(array('name' => 'A', 'length' => '30', 'price' => '99999999999'))['error']);
	pg_assert_same(0, pg_booking_settings_service_clean(array('name' => 'A', 'length' => '30'))['values']['enabled'], 'an unticked switch posts nothing');

	// A new row left empty is no service and no mistake.
	pg_assert_true(pg_booking_settings_service_blank(array('name' => '', 'length' => '30', 'price' => '')));
	pg_assert_false(pg_booking_settings_service_blank(array('name' => 'A')));
}

// The weekly grid: two ranges a day, an empty day closed, a range that ends
// before it starts named.
function test_booking_settings_hours_grid()
{
	$answer = pg_booking_settings_hours_from_fields(array(
		'h1a' => '09:00', 'h1b' => '13:00', 'h1c' => '14:00', 'h1d' => '18:00',
		'h2a' => '10:00', 'h2b' => '16:00',
		'h3a' => '12:00', 'h3b' => '11:00',
		'h4a' => '09:00',
		'h5a' => '9:00 AM', 'h5b' => '17:00',
	));

	pg_assert_same(array(
		'1' => array(array('09:00', '13:00'), array('14:00', '18:00')),
		'2' => array(array('10:00', '16:00')),
	), $answer['hours']);
	pg_assert_same(array('h3b', 'h4b', 'h5a'), $answer['bad']);

	$fields = pg_booking_settings_hours_fields(array('1' => array(array('09:00', '13:00'), array('14:00', '18:00')), '6' => array(array('10:00', '12:00'))));

	pg_assert_same(array('09:00', '13:00', '14:00', '18:00'), $fields[1]);
	pg_assert_same(array('10:00', '12:00', '', ''), $fields[6]);
	pg_assert_same(array('', '', '', ''), $fields[7]);
	pg_assert_same(7, count($fields));
}

// A time box cannot hold 24:00 (it ends at 23:59), so an end of 00:00 is the
// day's end: hours until midnight are saved as 24:00 and shown as 00:00, and
// the grid posts them back unchanged.
function test_booking_settings_hours_until_midnight()
{
	$answer = pg_booking_settings_hours_from_fields(array(
		'h1a' => '18:00', 'h1b' => '00:00',
		'h2a' => '00:00', 'h2b' => '00:00',
		'h3a' => '20:00', 'h3b' => '0:00',
	));

	pg_assert_same(array(
		'1' => array(array('18:00', '24:00')),
		'2' => array(array('00:00', '24:00')),
		'3' => array(array('20:00', '24:00')),
	), $answer['hours']);
	pg_assert_same(array(), $answer['bad']);

	$fields = pg_booking_settings_hours_fields(array('1' => array(array('09:00', '13:00'), array('18:00', '24:00'))));

	pg_assert_same(array('09:00', '13:00', '18:00', '00:00'), $fields[1]);

	$posted = array();

	foreach ($fields as $day => $boxes) {
		foreach (array('a', 'b', 'c', 'd') as $position => $box) {
			$posted['h' . $day . $box] = $boxes[$position];
		}
	}

	$again = pg_booking_settings_hours_from_fields($posted);

	pg_assert_same(array('1' => array(array('09:00', '13:00'), array('18:00', '24:00'))), $again['hours']);
	pg_assert_same(array(), $again['bad']);
}

// An exception row's hours until midnight round-trip the same way.
function test_booking_settings_exception_until_midnight()
{
	$answer = pg_booking_settings_exceptions_from_rows(array('2026-12-31'), array(''), array('20:00'), array('00:00'), '2026-11-03');

	pg_assert_same(array('2026-12-31' => array(array('20:00', '24:00'))), $answer['exceptions']);
	pg_assert_same(array(), $answer['bad']);

	pg_assert_same(array(
		array('from' => '2026-12-31', 'to' => '', 'open' => '20:00', 'close' => '00:00'),
	), pg_booking_settings_exception_rows($answer['exceptions'], '2026-11-03'));
}

// Exceptions are typed as ranges of days with one range of hours (none: day
// off), stored day by day, and read back as the same ranges.
function test_booking_settings_exceptions_round_trip()
{
	$answer = pg_booking_settings_exceptions_from_rows(
		array('2026-12-24', '2026-12-28', '2026-11-01', 'bad', '2027-01-10', ''),
		array('', '2026-12-31', '', '', '2027-01-09', ''),
		array('09:00', '', '', '', '', ''),
		array('12:00', '', '', '', '', ''),
		'2026-11-03'
	);

	pg_assert_same(array(
		'2026-12-24' => array(array('09:00', '12:00')),
		'2026-12-28' => array(), '2026-12-29' => array(), '2026-12-30' => array(), '2026-12-31' => array(),
	), $answer['exceptions']);
	pg_assert_same(array(3, 4), $answer['bad'], 'a bad date and an end before the start');

	$rows = pg_booking_settings_exception_rows($answer['exceptions'], '2026-11-03');

	pg_assert_same(array(
		array('from' => '2026-12-24', 'to' => '', 'open' => '09:00', 'close' => '12:00'),
		array('from' => '2026-12-28', 'to' => '2026-12-31', 'open' => '', 'close' => ''),
	), $rows);

	// A past day is dropped from the list; a long range is cut at a year.
	pg_assert_same(array(), pg_booking_settings_exception_rows(array('2026-10-01' => array()), '2026-11-03'));
	$long = pg_booking_settings_exceptions_from_rows(array('2027-01-01'), array('2029-01-01'), array(''), array(''), '2026-11-03');
	pg_assert_same(366, count($long['exceptions']));
}

// The grid follows the week start of the panel language.
function test_booking_settings_week_order()
{
	pg_assert_same(array(1, 2, 3, 4, 5, 6, 7), pg_booking_settings_week_days(1));
	pg_assert_same(array(7, 1, 2, 3, 4, 5, 6), pg_booking_settings_week_days(0));
}

// A staff member's services are ids of known services only.
function test_booking_settings_service_ids()
{
	pg_assert_same(array(2, 5), pg_booking_settings_service_ids(array('5', '2', '5', '9', 'x', array()), array(2, 5, 7)));
	pg_assert_same(array(), pg_booking_settings_service_ids('5', array(5)));
}

// The category is wired where every category is.
function test_booking_settings_category_files()
{
	$root = PG_FUNCTIONS_DIR;

	pg_assert_true(is_file($root . '/settings_bookings.php'));
	pg_assert_true(is_file($root . '/includes/settings/bookings.php'));
	pg_assert_true(is_file($root . '/includes/settings/bookings.save.php'));
	pg_assert_contains("'settings_bookings'", (string) file_get_contents($root . '/get_help_url.php'));

	$registry = (string) file_get_contents($root . '/includes/settings/registry.php');

	pg_assert_contains("'bookings' => array(", $registry);
	pg_assert_contains("'booking_service' => 2,", $registry);
	pg_assert_contains("'booking_staff'   => 3,", $registry);
}

// The pane's labels use booking keys, not the shared 'Services' (shuttle
// sense in Turkish), 'Order' (a purchase) or 'Length'; the Reminders box
// takes commas, so it asks for no numeric keypad.
function test_booking_settings_pane_labels_and_reminder_keyboard()
{
	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/bookings.php');

	foreach (array("lang('Services')", "lang('Order')", "lang('Length')") as $key) {
		pg_assert_same(false, strpos($pane, $key), 'not used: ' . $key);
	}

	pg_assert_contains("lang('Bookable services')", $pane);
	pg_assert_contains("lang('Duration')", $pane);
	pg_assert_true(preg_match('/id="booking_reminder_hours"[^>]*inputmode="numeric"/', $pane) === 0, 'no numeric keypad for a list');
}

// A refused box opens the drawer that holds it: a service's boxes sit in its
// drawer, a new service's in the add drawer, a staff member's boxes in the
// member's drawer, the user to add and the new member's hours in the add
// drawer. The order is no box of a drawer.
function test_booking_settings_error_drawer()
{
	foreach (array('name', 'length', 'before', 'after', 'price', 'deposit', 'deposit_product', 'description', 'enabled') as $box) {
		pg_assert_same('pgset-booking-service-12', pg_booking_settings_error_drawer('booking_service', '12', $box), $box);
	}

	pg_assert_same('pgset-booking-service-new', pg_booking_settings_error_drawer('booking_service', 'new', 'name'));
	pg_assert_same('pgset-booking-service-7', pg_booking_settings_error_drawer('booking_service', 7, 'price'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_service', '12', 'order'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_service', '12', 'colour'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_service', '0', 'name'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_service', '1x', 'name'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_service', array(), 'name'));

	foreach (array('h1a', 'h7d', 'x_from', 'enabled', 'services') as $box) {
		pg_assert_same('pgset-booking-member-4', pg_booking_settings_error_drawer('booking_staff', '4', $box), $box);
	}

	pg_assert_same('pgset-booking-member-new', pg_booking_settings_error_drawer('booking_staff', 'new', 'h3b'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_staff', '4', 'h8a'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_staff', '4', 'seen'));
	pg_assert_same('', pg_booking_settings_error_drawer('booking_staff', '0', 'h1a'));
	pg_assert_same('pgset-booking-member-new', pg_booking_settings_error_drawer('booking_staff_add', '', ''));
}

// A member added to the staff starts with their own working days in Team and
// working hours, else the company's working week, else Monday to Friday;
// each day from 09:00 to 17:00.
function test_booking_settings_new_member_hours()
{
	$day = array(array('09:00', '17:00'));

	pg_assert_same(array(1 => $day, 3 => $day, 6 => $day), pg_booking_settings_new_member_hours(1 + 4 + 32, 31), 'own days: Mon, Wed, Sat');
	pg_assert_same(array(1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day, 6 => $day), pg_booking_settings_new_member_hours(0, 63), 'company week Mon-Sat');
	pg_assert_same(array(1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day), pg_booking_settings_new_member_hours(0, 0), 'Monday to Friday');
	pg_assert_same(array(1 => $day, 2 => $day, 3 => $day, 4 => $day, 5 => $day), pg_booking_settings_new_member_hours(null, null), 'nothing known');
	pg_assert_same(array(7 => $day), pg_booking_settings_new_member_hours(64 + 128, 31), 'bits past Sunday ignored');
	pg_assert_same(array(2 => $day), pg_booking_settings_new_member_hours(128, 2), 'no day of the week in the own mask: the company week');
	pg_assert_same(pg_booking_hours_clean(pg_booking_settings_new_member_hours(1, 31)), pg_booking_settings_new_member_hours(1, 31), 'stored shape');
}

// A staff member's row says whether they take bookings, how many services
// and which days they work.
function test_booking_settings_staff_line()
{
	$member = array('enabled' => 1, 'service_ids' => '[3,5,9]', 'hours' => '{"1":[["09:00","17:00"]],"2":[["09:00","17:00"]],"3":[["09:00","12:00"],["13:00","17:00"]],"4":[["09:00","17:00"]],"5":[["09:00","17:00"]]}');

	pg_assert_same('Takes bookings · 3 services · Mon–Fri', pg_booking_settings_staff_line($member, array(3, 5, 9)));
	pg_assert_same('Takes bookings · 2 services · Mon–Fri', pg_booking_settings_staff_line($member, array(3, 9, 11)), 'only known services');
	pg_assert_same('Takes no bookings · 1 service · Mon, Wed', pg_booking_settings_staff_line(array('enabled' => 0, 'service_ids' => '[3]', 'hours' => '{"1":[["09:00","17:00"]],"3":[["09:00","17:00"]]}'), array(3)));
	pg_assert_same('Takes bookings · No service · Mon–Wed, Sat, Sun', pg_booking_settings_staff_line(array('enabled' => 1, 'service_ids' => '[]', 'hours' => '{"1":[["09:00","17:00"]],"2":[["09:00","17:00"]],"3":[["09:00","17:00"]],"6":[["10:00","14:00"]],"7":[["10:00","14:00"]]}'), array(3)));
	pg_assert_same('Takes bookings · 1 service · No weekly hours', pg_booking_settings_staff_line(array('enabled' => 1, 'service_ids' => '[3]', 'hours' => '{}'), array(3)));
}

// A service's row says its length, its price and whether it can be booked.
function test_booking_settings_service_line()
{
	$separators = array('decimal' => ',', 'thousands' => '.');
	$service = array('length_minutes' => 30, 'price' => 25000, 'enabled' => 1);

	pg_assert_same('30 min · 250,00 TRY · Can be booked', pg_booking_settings_service_line($service, $separators, 'TRY'));
	pg_assert_same('45 min · Can be booked', pg_booking_settings_service_line(array('price' => 0, 'length_minutes' => 45) + $service, $separators, 'TRY'), 'no price, no amount');
	pg_assert_same('Switched off', pg_booking_settings_service_line(array('enabled' => 0) + $service, $separators, 'TRY'));
}

// Each folded row's line of state.
function test_booking_settings_fold_lines()
{
	$services = array(
		3 => array('enabled' => 1), 5 => array('enabled' => 0), 9 => array('enabled' => 1),
	);
	$staff = array(
		2 => array('enabled' => 1), 4 => array('enabled' => 0),
	);
	$policy = array('horizon_days' => 60, 'auto_confirm' => true);

	$lines = pg_booking_settings_fold_lines($services, $staff, $policy, array('title' => 'Book a table'));

	pg_assert_same('3 services, 2 can be booked', $lines['services']);
	pg_assert_same('1 takes bookings', $lines['staff']);
	pg_assert_same('Up to 60 days ahead · confirmed at once', $lines['policy']);
	pg_assert_same('1 of 4 texts set', $lines['texts']);

	$lines = pg_booking_settings_fold_lines(array(1 => array('enabled' => 1)), array(2 => array('enabled' => 1), 4 => array('enabled' => 1)),
		array('horizon_days' => 1, 'auto_confirm' => false), array());

	pg_assert_same('1 service, 1 can be booked', $lines['services']);
	pg_assert_same('2 take bookings', $lines['staff']);
	pg_assert_same('Up to 1 day ahead · staff confirm each booking', $lines['policy']);
	pg_assert_same('The widget\'s own wording', $lines['texts']);

	$lines = pg_booking_settings_fold_lines(array(), array(), $policy, array());

	pg_assert_same('No service yet', $lines['services']);
	pg_assert_same('Nobody takes bookings yet', $lines['staff']);
}

// The pane: folded rows, the services in drawers, every box under its old
// name, the card ids kept, look A's switch, no Display order box.
function test_booking_settings_pane_rows_and_drawers()
{
	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/bookings.php');

	pg_assert_contains('pg_settings_fold(', $pane);
	pg_assert_contains('pg_settings_drawer(', $pane);
	pg_assert_contains("lang('Add a service')", $pane);
	pg_assert_contains("lang('Move up')", $pane);
	pg_assert_contains("lang('Move down')", $pane);

	foreach (array('pgset-booking-services', 'pgset-booking-staff', 'pgset-booking-policy', 'pgset-booking-texts') as $card) {
		pg_assert_contains("'" . $card . "'", $pane, 'card id kept: ' . $card);
	}

	foreach (array('name', 'length', 'before', 'after', 'price', 'deposit', 'deposit_product', 'description', 'enabled', 'order') as $box) {
		pg_assert_contains("\$name . '[" . $box . "]", $pane, 'posts as booking_service[..][' . $box . ']');
	}

	foreach (array("'booking_reschedule'", "'booking_auto_confirm'", 'name="booking_reminder_hours"', 'name="booking_staff_add"', 'name="booking_text_') as $box) {
		pg_assert_contains($box, $pane);
	}

	pg_assert_same(false, strpos($pane, '<div class="switch">'), 'look A switch');
	pg_assert_same(false, strpos($pane, "lang('Display order')"), 'the order is moved, not typed');
	pg_assert_same(false, strpos($pane, "lang('New service')"), 'no empty block');

	// The save names the drawer of a refused box and changes nothing else.
	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/bookings.save.php');

	pg_assert_contains('pg_settings_drawer_refused(pg_booking_settings_error_drawer(', $save);
}

// The staff: list rows, each member in a drawer posting under the names it
// always had; the add drawer's picker and grid, whose hours the save checks
// as a member's and stores as posted.
function test_booking_settings_staff_rows_and_drawers()
{
	$pane = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/bookings.php');

	pg_assert_contains("lang('Add a staff member')", $pane);
	pg_assert_contains('pg_settings_drawer($drawer, h($member[\'person\'])', $pane);
	pg_assert_contains('pg_booking_settings_member_drawer(\'new\')', $pane);
	pg_assert_contains('pg_booking_settings_staff_line($member, $booking_service_ids)', $pane);
	pg_assert_contains('href="#settings/workspace_team/pgset-ws-calendar"', $pane);
	pg_assert_contains("\$booking_hours_grid('booking_staff[new]'", $pane);
	pg_assert_contains('data-pg-booking-hours="', $pane);
	pg_assert_same(false, strpos($pane, '<fieldset'), 'no open block per member');

	foreach (array("[seen]", "[enabled]", "[services][]", "[h' . \$day . \$box . ']", "[x_from][]", "[x_to][]", "[x_open][]", "[x_close][]") as $box) {
		pg_assert_contains("\$name . '" . $box, $pane, 'posts as booking_staff[..]' . $box);
	}

	$save = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/settings/bookings.save.php');

	pg_assert_contains("pg_booking_settings_hours_from_fields(\$booking_add_grid)", $save);
	pg_assert_contains("\$liveform->mark_error('booking_staff[new][' . \$box . ']'", $save);
	pg_assert_contains('pg_booking_settings_save_staff($booking_staff_rows, $booking_add, $booking_enabled_services, $booking_now, $booking_add_hours);', $save);
}
