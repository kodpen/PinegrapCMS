<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a volunteer's own Pause and Leave
 * (includes/fn/volunteer_signup.php, includes/fn/volunteer_page_view.php):
 * which state each press moves to, a second press, the sign-ups given up,
 * the one e-mail to the coordinators, the buttons with their questions, and
 * that the move goes through the coordinator's pg_volunteer_move().
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

function _volunteer_self_move_test_rows()
{
	return array(
		array('shift_id' => 1, 'state' => 'signed', 'start' => '2026-12-10 09:00:00'),
		array('shift_id' => 2, 'state' => 'waiting', 'start' => '2026-12-11 09:00:00'),
		array('shift_id' => 3, 'state' => 'signed', 'start' => '2026-12-01 09:00:00'),
		array('shift_id' => 4, 'state' => 'cancelled', 'start' => '2026-12-12 09:00:00'),
	);
}

function test_volunteer_pause_gives_up_two_coming_sign_ups()
{
	pg_assert_same('paused', pg_volunteer_self_move_check('active', 'pause'));

	$given = pg_volunteer_give_up_rows(_volunteer_self_move_test_rows(), '2026-12-05 12:00:00');
	pg_assert_same(array(1, 2), array_column($given, 'shift_id'), 'the signed and the waiting row to come; not a started or cancelled one');

	$shifts = array(
		array('start' => '2026-12-10 09:00:00', 'end' => '2026-12-10 13:00:00', 'place' => 'Depot'),
		array('start' => '2026-12-11 09:00:00', 'end' => '2026-12-11 13:00:00', 'place' => 'Kitchen'),
	);
	$mail = pg_volunteer_self_move_mail(array('name' => 'Food bank'), 'Ada Lovelace', 'paused', $shifts);
	pg_assert_same('Food bank: a volunteer paused', $mail['subject']);
	pg_assert_contains('Ada Lovelace paused their volunteering.', $mail['body']);
	pg_assert_contains('Depot', $mail['body'], 'one message names every shift');
	pg_assert_contains('Kitchen', $mail['body']);
	pg_assert_same(true, pg_volunteer_answer_text('paused')[1]);
}

function test_volunteer_leave()
{
	pg_assert_same('left', pg_volunteer_self_move_check('active', 'leave'));
	pg_assert_same('left', pg_volunteer_self_move_check('paused', 'leave'), 'a paused volunteer can leave');

	$mail = pg_volunteer_self_move_mail(array('name' => 'Food bank'), 'Ada', 'left', array());
	pg_assert_same('Food bank: a volunteer left', $mail['subject']);
	pg_assert_contains('No coming shifts were given up.', $mail['body']);
	pg_assert_same(true, pg_volunteer_answer_text('left')[1]);
}

function test_volunteer_second_press_and_moved_volunteers()
{
	pg_assert_same('already_paused', pg_volunteer_self_move_check('paused', 'pause'), 'a second press of Pause');
	pg_assert_same(false, pg_volunteer_answer_text('already_paused')[1]);
	pg_assert_same('refused', pg_volunteer_self_move_check('left', 'leave'), 'a second press of Leave');
	pg_assert_same('refused', pg_volunteer_self_move_check('left', 'pause'), 'a volunteer who left');
	pg_assert_same('refused', pg_volunteer_self_move_check('applied', 'pause'), 'an applicant');
	pg_assert_same('refused', pg_volunteer_self_move_check('applied', 'leave'));
	pg_assert_same('refused', pg_volunteer_self_move_check('active', 'resume'), 'coming back stays the coordinator\'s');
}

function test_volunteer_self_move_buttons()
{
	$form = array('action' => '/volunteer.php', 'hidden' => '');
	$c = pg_volunteer_page_secure_classes();
	$active = pg_volunteer_self_move_html($form, $c, array('id' => 5, 'state' => 'active'));
	pg_assert_contains('Pause my volunteering', $active);
	pg_assert_contains('Leave the programme', $active);
	pg_assert_contains('Only the coordinator can make your volunteering active again.', $active, 'the pause question says who brings them back');
	pg_assert_contains('Yes, pause', $active);

	$paused = pg_volunteer_self_move_html($form, $c, array('id' => 5, 'state' => 'paused'));
	pg_assert_false(strpos($paused, 'Pause my volunteering') !== false, 'no second pause');
	pg_assert_contains('Yes, leave', $paused);
	pg_assert_same('', pg_volunteer_self_move_html($form, $c, array('id' => 5, 'state' => 'applied')));
}

function test_volunteer_self_move_shares_the_coordinators_path()
{
	pg_assert_contains("_pg_volunteer_self_move(\$volunteer_id, \$action)", pg_test_function_source('pg_volunteer_self_act'));
	pg_assert_contains('pg_volunteer_move($programme, $volunteer, $to, 0)', pg_test_function_source('_pg_volunteer_self_move'));
	pg_assert_contains('_pg_volunteer_signup_give_up(', pg_test_function_source('_pg_volunteer_self_move'), 'given up as a cancel gives one up');
	pg_assert_contains('log_activity(', pg_test_function_source('pg_volunteer_move'), 'one log line for both');
	pg_assert_false(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_volunteer_programme.php'), 'was moved to') !== false, 'the screen logs no second line');
}
