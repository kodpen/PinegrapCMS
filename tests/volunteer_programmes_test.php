<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of volunteer programmes
 * (includes/fn/volunteer_programmes.php): the Set-up form read and cleaned,
 * what a programme still misses, who sees it, an application's answers,
 * the age, the documents pill, the state moves and the messages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _volunteer_programme_test_post($overrides = array())
{
	return $overrides + array(
		'name'             => '  Food bank  ',
		'page_id'          => '40',
		'field_skills'     => 'skills',
		'field_birth_date' => 'birth',
		'field_free_days'  => 'unknown_field',
		'role_row'         => array('r2', 'n1', 'n2'),
		'role_name'        => array('Driver', 'Packer', ''),
		'role_skills'      => array('driving, first aid, driving', '', 'x'),
		'role_documents'   => array('Criminal record certificate', '', ''),
		'role_trainings'   => array('r2' => array('12', '99'), 'n1' => array('13')),
		'cutoff_hours'     => '48',
		'min_age'          => '16',
		'swap_approval'    => '1',
		'call_days'        => '99',
		'call_channels'    => array('sms', 'fax', 'email'),
		'text'             => array('welcome' => array('subject' => 'Welcome, {name}', 'body' => 'Hello')),
		'coordinators'     => array('7', '8', '7', '1000'),
	);
}

function test_volunteer_programme_input_cleans_the_set_up_form()
{
	$read = pg_volunteer_programme_input(_volunteer_programme_test_post(), array(40, 41), array('skills', 'birth', 'days'), array(12, 13), array(7, 8));
	$programme = $read['programme'];

	pg_assert_same(array(), $read['errors']);
	pg_assert_same('Food bank', $programme['name']);
	pg_assert_same(40, $programme['page_id']);
	pg_assert_same(array('skills' => 'skills', 'birth_date' => 'birth', 'free_days' => ''), $programme['form_fields'], 'a field not on the form is dropped');
	pg_assert_same(array('r2', 'r3'), array_keys($programme['roles']), 'the stored key stays, a new row takes the next free one, a row with no name goes');
	pg_assert_same(array('name' => 'Driver', 'skills' => array('driving', 'first aid'), 'trainings' => array(12), 'documents' => array('Criminal record certificate')), $programme['roles']['r2']);
	pg_assert_same(array(13), $programme['roles']['r3']['trainings']);
	pg_assert_same(48, $programme['cutoff_hours']);
	pg_assert_same(16, $programme['min_age']);
	pg_assert_same(1, $programme['swap_approval']);
	pg_assert_same(14, $programme['call_days'], 'held to its top');
	pg_assert_same(array('email', 'sms'), $programme['call_channels']);
	pg_assert_same(array(7, 8), $programme['coordinators'], 'a panel user once, an unknown id dropped');
	pg_assert_same('Welcome, {name}', $programme['texts']['welcome']['subject']);
	pg_assert_same('', $programme['texts']['call']['body']);
}

function test_volunteer_programme_input_refuses_a_missing_name_and_a_foreign_form()
{
	$read = pg_volunteer_programme_input(_volunteer_programme_test_post(array('name' => ' ', 'page_id' => '99')), array(40), array(), array(), array());

	pg_assert_same(2, count($read['errors']));
	pg_assert_same(0, $read['programme']['page_id']);
	pg_assert_same(PG_VOLUNTEER_CUTOFF_HOURS, pg_volunteer_programme_input(array('cutoff_hours' => 'x'), array(), array(), array(), array())['programme']['cutoff_hours']);
}

function test_volunteer_programme_missing_names_what_is_not_set()
{
	pg_assert_same(array('form', 'roles', 'coordinators'), pg_volunteer_programme_missing(array('page_id' => 0, 'roles' => array(), 'coordinators' => array())));
	pg_assert_same(array(), pg_volunteer_programme_missing(array('page_id' => 4, 'roles' => array('r1' => array('name' => 'A')), 'coordinators' => array(3))));
}

function test_volunteer_programme_is_seen_by_staff_and_its_coordinators()
{
	$programme = array('coordinators' => array(7, 8));

	pg_assert_true(pg_volunteer_user_sees($programme, array('id' => 1, 'role' => 2)));
	pg_assert_true(pg_volunteer_user_sees($programme, array('id' => '8', 'role' => 3)));
	pg_assert_false(pg_volunteer_user_sees($programme, array('id' => 9, 'role' => 3)));
}

function test_volunteer_application_values_read_skills_birth_date_and_free_days()
{
	$answers = array('skills' => array('Driving', ' first aid '), 'birth' => '2010-05-20', 'days' => array('Monday', 'sat', '3', 'someday'));
	$values = pg_volunteer_application_values($answers, array('skills' => 'skills', 'birth_date' => 'birth', 'free_days' => 'days'));

	pg_assert_same(array('Driving', 'first aid'), $values['skills']);
	pg_assert_same('2010-05-20', $values['birth_date']);
	pg_assert_same(array(1, 3, 6), array_keys($values['hours']), 'days by name, short name or number');
	pg_assert_same(array(array('00:00', '24:00')), $values['hours'][1], 'a free day is free all day');

	$none = pg_volunteer_application_values(array('birth' => '20 May'), array('birth_date' => 'birth'));

	pg_assert_same(array(), $none['skills']);
	pg_assert_same('', $none['birth_date'], 'an unreadable date is not kept');
	pg_assert_same(array(), $none['hours'], 'no free days given: no limit');
}

function test_volunteer_age_counts_whole_years()
{
	pg_assert_same(16, pg_volunteer_age('2010-05-20', '2026-05-20'));
	pg_assert_same(15, pg_volunteer_age('2010-05-20', '2026-05-19'));
	pg_assert_same(null, pg_volunteer_age('', '2026-05-19'));
}

function test_volunteer_documents_state_for_the_applications_pill()
{
	$kinds = array('Criminal record certificate');
	$today = '2026-11-22';

	pg_assert_same('', pg_volunteer_documents_state(array(), array(), $today), 'no document needed');
	pg_assert_same('missing', pg_volunteer_documents_state(array(), $kinds, $today));
	pg_assert_same('missing', pg_volunteer_documents_state(array(array('kind' => 'Criminal record certificate', 'valid_until' => '2026-11-21')), $kinds, $today), 'ended yesterday');
	pg_assert_same('running_out', pg_volunteer_documents_state(array(array('kind' => 'Criminal record certificate', 'valid_until' => '2026-12-22')), $kinds, $today), 'ends within 30 days');
	pg_assert_same('valid', pg_volunteer_documents_state(array(array('kind' => 'Criminal record certificate', 'valid_until' => '2026-12-23')), $kinds, $today));
}

function test_volunteer_state_moves()
{
	pg_assert_true(pg_volunteer_state_move_allowed('applied', 'active'));
	pg_assert_true(pg_volunteer_state_move_allowed('active', 'paused'));
	pg_assert_true(pg_volunteer_state_move_allowed('paused', 'active'));
	pg_assert_true(pg_volunteer_state_move_allowed('paused', 'left'));
	pg_assert_false(pg_volunteer_state_move_allowed('left', 'active'), 'a volunteer who left applies again');
	pg_assert_false(pg_volunteer_state_move_allowed('applied', 'paused'));
}

function test_volunteer_message_fills_the_names_or_takes_the_standard_text()
{
	$texts = array('welcome' => array('subject' => 'Welcome to {programme}', 'body' => ''));
	$message = pg_volunteer_message($texts, 'welcome', array('name' => 'Ada', 'programme' => 'Food bank'));

	pg_assert_same('Welcome to Food bank', $message['subject']);
	pg_assert_true(strpos($message['body'], 'Ada') !== false, 'the standard text names the volunteer');
}
