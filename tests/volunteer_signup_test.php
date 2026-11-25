<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of a volunteer's own shifts
 * (includes/fn/volunteer_signup.php, includes/fn/volunteer_page_view.php):
 * what a sign-up offers (Cancel, Offer my place, Keep my place), which
 * shifts a volunteer is offered (Sign up, Join the waitlist, Take this
 * place), the swaps waiting for a coordinator, the words of each state and
 * outcome; and that the signed page is a sensitive script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_volunteering.php');

function _volunteer_signup_test_shift(array $roles = array('r1' => 2))
{
	return array('id' => 7, 'start' => '2026-12-10 09:00:00', 'end' => '2026-12-10 13:00:00', 'roles' => $roles);
}

function _volunteer_signup_test_volunteer(array $skills = array('Driving'))
{
	return array('id' => 1, 'state' => 'active', 'skills' => $skills, 'trainings' => array(), 'documents' => array(), 'hours' => array(), 'shifts' => array());
}

function _volunteer_signup_test_roles()
{
	return array('r1' => array('name' => 'Driver', 'skills' => array('Driving')), 'r2' => array('name' => 'Packer'));
}

function _volunteer_signup_test_row($id, $volunteer, $state, $role = 'r1', $offered = 0, $swap = 0)
{
	return array('id' => $id, 'volunteer_id' => $volunteer, 'role' => $role, 'state' => $state, 'offered_at' => $offered, 'swap_from' => $swap);
}

function test_volunteer_signup_actions_follow_the_cut_off()
{
	$shift = _volunteer_signup_test_shift();
	$signed = _volunteer_signup_test_row(1, 1, 'signed');

	pg_assert_same(array('cancel'), pg_volunteer_signup_actions($signed, $shift, '2026-12-09 08:00:00', 24), '25 hours before: cancel');
	pg_assert_same(array('offer'), pg_volunteer_signup_actions($signed, $shift, '2026-12-09 10:00:00', 24), '23 hours before: offer the place');
	pg_assert_same(array('cancel'), pg_volunteer_signup_actions($signed, $shift, '2026-12-09 10:00:00', 12), 'the programme\'s own cut-off');
	pg_assert_same(array('keep'), pg_volunteer_signup_actions(array('offered_at' => 5) + $signed, $shift, '2026-12-09 10:00:00', 24), 'an offer can be taken back');
	pg_assert_same(array(), pg_volunteer_signup_actions(array('offered_at' => 5) + $signed, $shift, '2026-12-09 10:00:00', 24, true), 'not once someone asked for it');
	pg_assert_same(array('cancel'), pg_volunteer_signup_actions(_volunteer_signup_test_row(2, 1, 'waiting'), $shift, '2026-12-10 08:59:00', 24), 'the waitlist is left until the start');
	pg_assert_same(array(), pg_volunteer_signup_actions($signed, $shift, '2026-12-10 09:00:00', 24), 'nothing once it started');
	pg_assert_same(array(), pg_volunteer_signup_actions(_volunteer_signup_test_row(3, 1, 'cancelled'), $shift, '2026-12-01 09:00:00', 24));
}

function test_volunteer_shift_choices_sign_up_waitlist_and_take()
{
	$now = '2026-12-01 10:00:00';
	$roles = _volunteer_signup_test_roles();
	$shift = _volunteer_signup_test_shift(array('r1' => 2, 'r2' => 1));
	$volunteer = _volunteer_signup_test_volunteer();

	pg_assert_same(array(
		array('role' => 'r1', 'action' => 'sign_up', 'signup' => 0),
		array('role' => 'r2', 'action' => 'sign_up', 'signup' => 0),
	), pg_volunteer_shift_choices($volunteer, $roles, $shift, array(), $now), 'both roles free');

	pg_assert_same(array(array('role' => 'r2', 'action' => 'sign_up', 'signup' => 0)),
		pg_volunteer_shift_choices(_volunteer_signup_test_volunteer(array()), $roles, $shift, array(), $now), 'a role needing a skill they lack is not offered');

	$full = array(_volunteer_signup_test_row(10, 2, 'signed'), _volunteer_signup_test_row(11, 3, 'signed'), _volunteer_signup_test_row(12, 4, 'signed', 'r2'));
	pg_assert_same(array(
		array('role' => 'r1', 'action' => 'waitlist', 'signup' => 0),
		array('role' => 'r2', 'action' => 'waitlist', 'signup' => 0),
	), pg_volunteer_shift_choices($volunteer, $roles, $shift, $full, $now), 'full roles offer the waitlist');

	$full[1]['offered_at'] = 9;
	pg_assert_same(array('role' => 'r1', 'action' => 'take', 'signup' => 11), pg_volunteer_shift_choices($volunteer, $roles, $shift, $full, $now)[0], 'an offered place is taken');

	$full[] = _volunteer_signup_test_row(13, 5, 'waiting', 'r1', 0, 11);
	pg_assert_same('waitlist', pg_volunteer_shift_choices($volunteer, $roles, $shift, $full, $now)[0]['action'], 'an offer someone asked for is not offered again');

	$full[] = _volunteer_signup_test_row(14, 1, 'waiting', 'r2');
	pg_assert_same(array(), pg_volunteer_shift_choices($volunteer, $roles, $shift, $full, $now), 'a volunteer with a place or a waitlist row is offered nothing');
	pg_assert_same(array(), pg_volunteer_shift_choices($volunteer, $roles, $shift, array(), '2026-12-10 09:00:00'), 'nothing once it started');
	pg_assert_same(array(), pg_volunteer_shift_choices(array('state' => 'paused') + $volunteer, $roles, $shift, array(), $now), 'a paused volunteer fits nothing');
}

function test_volunteer_swaps_pending()
{
	pg_assert_same(array(11 => 13), pg_volunteer_swaps_pending(array(
		_volunteer_signup_test_row(11, 3, 'signed', 'r1', 9),
		_volunteer_signup_test_row(12, 4, 'waiting'),
		_volunteer_signup_test_row(13, 5, 'waiting', 'r1', 0, 11),
		_volunteer_signup_test_row(14, 6, 'signed', 'r1', 0, 8),
	)), 'only a waiting row with swap_from asks; a finished swap does not');
}

function test_volunteer_signup_words()
{
	pg_assert_same(array('Signed up', 'ok'), pg_volunteer_signup_label(_volunteer_signup_test_row(1, 1, 'signed')));
	pg_assert_same(array('On the waitlist', 'wait'), pg_volunteer_signup_label(_volunteer_signup_test_row(1, 1, 'waiting')));
	pg_assert_same(array('Waiting for approval', 'info'), pg_volunteer_signup_label(_volunteer_signup_test_row(1, 1, 'waiting', 'r1', 0, 4)));
	pg_assert_same(array('Place offered', 'wait'), pg_volunteer_signup_label(_volunteer_signup_test_row(1, 1, 'signed', 'r1', 5)));
	pg_assert_same(array('Someone asked to take it', 'info'), pg_volunteer_signup_label(array('asked' => true) + _volunteer_signup_test_row(1, 1, 'signed', 'r1', 5)));
	pg_assert_same(true, pg_volunteer_answer_text('signed')[1]);
	pg_assert_same(false, pg_volunteer_answer_text('refused')[1]);
	pg_assert_same(false, pg_volunteer_answer_text('<script>')[1], 'an unknown outcome is the refusal');
}

function test_volunteer_page_is_sensitive()
{
	pg_assert_contains("'volunteer.php'", pg_test_function_source('waf_sensitive_scripts'), 'the signed page on the sensitive allowance');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/volunteer.php');
	pg_assert_contains("header('Cache-Control: no-store, private');", $page);
	pg_assert_true(strpos($page, 'validate_token_field();') < strpos($page, 'pg_volunteer_self_act('), 'every write checks the form token first');
	pg_assert_contains('in_array($vo_profile, $vo_ids, true)', $page, 'a member acts on their own profiles only');
}

function test_volunteer_left_frees_coming_places()
{
	$rows = array(
		array('id' => 1, 'shift_id' => 5, 'state' => 'signed', 'start' => '2026-12-10 09:00:00'),
		array('id' => 2, 'shift_id' => 6, 'state' => 'waiting', 'start' => '2026-12-11 09:00:00'),
		array('id' => 3, 'shift_id' => 7, 'state' => 'signed', 'start' => '2026-12-01 08:00:00'),
		array('id' => 4, 'shift_id' => 8, 'state' => 'cancelled', 'start' => '2026-12-12 09:00:00'),
	);

	pg_assert_same(array(1, 2), array_column(pg_volunteer_give_up_rows($rows, '2026-12-01 10:00:00'), 'id'), 'signed and waiting places of shifts to come; a started shift and a cancel stay');

	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_volunteer_programme.php');
	pg_assert_contains('pg_volunteer_give_up_coming($vp, (int) $vp_person[\'id\'])', $page, 'Mark as left gives the places up');
	pg_assert_contains('pg_volunteer_give_up_coming(', pg_test_function_source('_pg_volunteer_self_move'), 'the volunteer\'s own Pause or Leave goes through the same helper');
	pg_assert_contains('_pg_volunteer_signup_give_up(', pg_test_function_source('pg_volunteer_give_up_coming'), 'each place is given up as Cancel gives one up');
	pg_assert_contains('_pg_volunteer_signup_give_up(', pg_test_function_source('pg_volunteer_self_act'));
}

function test_volunteer_swap_approve_checks_the_taker_again()
{
	$source = pg_test_function_source('pg_volunteer_swap_decide');

	pg_assert_contains('pg_volunteer_call_people($programme, $shift)', $source, 'the taker is read again as the waitlist pass reads them');
	pg_assert_contains('pg_volunteer_fits(', $source, 'an approved swap goes only to a volunteer who still fits the role');
	pg_assert_true(strpos($source, 'pg_volunteer_fits(') < strpos($source, '_pg_volunteer_swap_finish('), 'checked before the place is handed over');
}

function test_volunteer_swap_refuse_needs_no_offered_place()
{
	$source = pg_test_function_source('pg_volunteer_swap_decide');

	pg_assert_contains('$done = $approve', $source, 'Approve and Refuse are decided apart');
	pg_assert_contains(': (($asking !== null) && db_q(', $source, 'Refuse clears a request whose offered place is gone (its volunteer erased), which else waits for approval for good');
}
