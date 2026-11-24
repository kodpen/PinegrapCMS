<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the walk-in queue's places as staff set them up
 * (includes/fn/walk_in_queue_places.php): services and their keys and
 * letters, the settings' defaults and bounds, the Set-up form read back,
 * what keeps a place from opening and the desk's drawer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_walk_in_queue_places_service_keys_stay_and_new_rows_get_the_next()
{
	$services = pg_waiting_services_clean(array(
		array('key' => 's3', 'name' => 'Teller', 'letter' => 'a', 'minutes' => '6'),
		array('key' => '', 'name' => 'Loans', 'letter' => 'B', 'minutes' => ''),
		array('key' => 's3', 'name' => 'Copy', 'letter' => 'C', 'minutes' => '999'),
		array('key' => 's9', 'name' => '  ', 'letter' => 'D'),
	));

	// A row without a name goes; its key still counts, so a new row never
	// takes a key that numbers may name.
	pg_assert_same(3, count($services));
	pg_assert_same(array('key' => 's3', 'name' => 'Teller', 'letter' => 'A', 'minutes' => 6), $services[0]);
	pg_assert_same(array('key' => 's10', 'name' => 'Loans', 'letter' => 'B', 'minutes' => 10), $services[1]);
	pg_assert_same('s11', $services[2]['key'], 'a taken key is given anew');
	pg_assert_same(240, $services[2]['minutes'], 'minutes are capped');
	pg_assert_same(array(), pg_waiting_services_clean('not json'));
}

function test_walk_in_queue_places_free_letter_is_the_first_not_taken()
{
	pg_assert_same('A', pg_waiting_free_letter(array()));
	pg_assert_same('C', pg_waiting_free_letter(array(array('letter' => 'A'), array('letter' => 'B'), array('letter' => 'D'))));

	$all = array();

	foreach (range('A', 'Z') as $letter) {
		$all[] = array('letter' => $letter);
	}

	pg_assert_same('', pg_waiting_free_letter($all));
}

function test_walk_in_queue_places_settings_fall_back_to_defaults_within_bounds()
{
	$defaults = pg_waiting_place_settings(null);

	pg_assert_same(pg_waiting_place_defaults(), $defaults);
	pg_assert_same(array('qr' => 1, 'site' => 1, 'kiosk' => 0), $defaults['ways']);
	pg_assert_same(3, $defaults['notices']['ahead']);
	pg_assert_same(30, $defaults['keep_days']);

	$settings = pg_waiting_place_settings(json_encode(array(
		'cap' => 50000, 'ways' => array('qr' => 0), 'notices' => array('ahead' => 0, 'texts' => array('taken' => str_repeat('x', 600))),
		'grace_minutes' => 'soon', 'not_here_minutes' => 0, 'keep_days' => 900, 'staff' => array(7, 9),
		'booking_staff' => array('4', 4, 0, 'x'),
	)));

	pg_assert_same(9999, $settings['cap']);
	pg_assert_same(array('qr' => 0, 'site' => 1, 'kiosk' => 0), $settings['ways'], 'a way not given keeps its default');
	pg_assert_same(1, $settings['notices']['ahead']);
	pg_assert_same(500, mb_strlen($settings['notices']['texts']['taken']));
	pg_assert_same(10, $settings['grace_minutes']);
	pg_assert_same(1, $settings['not_here_minutes']);
	pg_assert_same(365, $settings['keep_days']);
	pg_assert_same(array(7, 9), $settings['staff'], 'the staff list is kept as stored');
	pg_assert_same(array(4), $settings['booking_staff']);
}

function test_walk_in_queue_places_set_up_form_is_read_back()
{
	$post = array(
		'name' => ' Main branch ', 'address' => 'Main street 1',
		'service_key' => array('s1', ''), 'service_name' => array('Teller', 'Loans'), 'service_letter' => array('A', 'b'), 'service_minutes' => array('5', '20'),
		'hours' => array('h1a' => '09:00', 'h1b' => '17:00'),
		'x_from' => array('2030-01-01'), 'x_to' => array(''), 'x_open' => array(''), 'x_close' => array(''),
		'way_qr' => '1', 'way_kiosk' => '1', 'cap' => '', 'notice_email' => '1', 'notice_ahead' => '4',
		'notice_text' => array('called' => 'Go to {desk}'),
		'priority_key' => array('p2', ''), 'priority_name' => array('Elderly', 'Veterans'),
		'grace_minutes' => '15', 'not_here_minutes' => '5', 'move_back' => '1', 'keep_days' => '60',
	);
	$read = pg_waiting_place_input($post, array('settings' => array('staff' => array(3))), '2029-12-01');
	$place = $read['place'];

	pg_assert_same(array(), $read['errors']);
	pg_assert_same('Main branch', $place['name']);
	pg_assert_same(array('s1', 's2'), array_column($place['services'], 'key'));
	pg_assert_same('B', $place['services'][1]['letter']);
	pg_assert_same(array(1 => array(array('09:00', '17:00'))), $place['hours']);
	pg_assert_same(array('2030-01-01' => array()), $place['exceptions'], 'a row without hours is a day off');
	pg_assert_same(array('qr' => 1, 'site' => 0, 'kiosk' => 1), $place['settings']['ways'], 'a switch left off is off');
	pg_assert_same(0, $place['settings']['cap']);
	pg_assert_same(array('sms' => 0, 'whatsapp' => 0, 'email' => 1), array_slice($place['settings']['notices'], 0, 3));
	pg_assert_same('Go to {desk}', $place['settings']['notices']['texts']['called']);
	pg_assert_same(array(array('key' => 'p2', 'name' => 'Elderly'), array('key' => 'p3', 'name' => 'Veterans')), $place['settings']['priority']);
	pg_assert_same(array(3), $place['settings']['staff']);
	pg_assert_same(60, $place['settings']['keep_days']);
}

function test_walk_in_queue_places_set_up_form_refuses_letters_hours_and_no_name()
{
	$read = pg_waiting_place_input(array(
		'name' => '',
		'service_name' => array('Teller', 'Loans', 'Cards'), 'service_letter' => array('A', 'a', '7'),
		'hours' => array('h2a' => '18:00', 'h2b' => '09:00'),
	), array(), '2029-12-01');

	// The same letter twice, a letter that is no letter, a range ending
	// before it starts, no name.
	pg_assert_same(4, count($read['errors']));
}

function test_walk_in_queue_places_missing_lists_what_keeps_a_place_closed()
{
	pg_assert_same(array('services', 'hours', 'desks'), pg_waiting_place_missing(array('services' => array(), 'hours' => array(), 'settings' => null), 0));

	$ready = array(
		'services' => array(array('key' => 's1', 'name' => 'Teller', 'letter' => 'A', 'minutes' => 5)),
		'hours'    => array(1 => array(array('09:00', '17:00'))),
		'settings' => array('ways' => array('qr' => 0, 'site' => 0, 'kiosk' => 0)),
	);

	pg_assert_same(array('ways'), pg_waiting_place_missing($ready, 2));

	$ready['settings'] = null;
	pg_assert_same(array(), pg_waiting_place_missing($ready, 1));
}

function test_walk_in_queue_places_desk_needs_a_name_and_a_known_service()
{
	$services = array(array('key' => 's1'), array('key' => 's2'));

	pg_assert_same(array('name' => 'Desk 1', 'services' => array('s2'), 'error' => ''), pg_waiting_desk_input(array('desk_name' => 'Desk 1', 'desk_services' => array('s2', 's9')), $services));
	pg_assert_true(pg_waiting_desk_input(array('desk_name' => 'Desk 1', 'desk_services' => array('s9')), $services)['error'] !== '');
	pg_assert_true(pg_waiting_desk_input(array('desk_name' => ' ', 'desk_services' => array('s1')), $services)['error'] !== '');
}

function test_walk_in_queue_places_only_a_closed_desk_is_removed()
{
	// A paused desk still has its staff member and may have a number called
	// to it: removed, that number stayed called to a desk that no longer
	// exists, and nobody could finish it.
	pg_assert_true(pg_waiting_desk_removable('closed'));
	pg_assert_false(pg_waiting_desk_removable('paused'));
	pg_assert_false(pg_waiting_desk_removable('open'));

	$module = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/walk_in_queue_places.php');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_queue_place.php');
	pg_assert_contains("AND place_id = ? AND state = 'closed'", $module, 'the delete keeps the desk unless closed');
	pg_assert_contains("pg_waiting_desk_removable(\$desk['state'])", $screen, 'Remove is offered only on a closed desk');
}
