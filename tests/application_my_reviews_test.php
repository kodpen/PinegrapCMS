<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a reviewer's own work on application programmes that need no
 * database (includes/fn/application_my_reviews.php): what blind review
 * hides, the scores kept as typed and their weighted total, the days left,
 * the reminders three days and one day before a round's end, the queue's
 * Previous and Next; and the gates of my_reviews.php and its menu row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/application_my_reviews.php');

function test_application_blind_review_hides_contact_mail_and_named_fields()
{
	$name = array('name' => 'first_name', 'type' => 'text box', 'contact_field' => 'first_name');
	$mail = array('name' => 'mail', 'type' => 'email address', 'contact_field' => '');
	$school = array('name' => 'school', 'type' => 'text box', 'contact_field' => '');
	$essay = array('name' => 'essay', 'type' => 'text area', 'contact_field' => '');

	pg_assert_true(pg_application_blind_hides($name, true, array()), 'a contact field');
	pg_assert_true(pg_application_blind_hides($mail, true, array()), 'an e-mail field');
	pg_assert_true(pg_application_blind_hides($school, true, array('school')), 'a field the programme names');
	pg_assert_false(pg_application_blind_hides($essay, true, array('school')), 'the rest shows');

	foreach (array($name, $mail, $school, $essay) as $field) {
		pg_assert_false(pg_application_blind_hides($field, false, array('school')), 'blind review off hides nothing');
	}
}

function test_application_review_scores_kept_as_typed()
{
	$criteria = array(array('id' => 'c1', 'weight' => 60), array('id' => 'c2', 'weight' => 40));

	$kept = pg_application_review_scores_input($criteria, array('c1' => '8', 'c2' => '11', 'c9' => '5'), array('c1' => '  Strong ', 'c2' => array('x')));
	pg_assert_same(array('c1' => array('score' => 8, 'note' => 'Strong'), 'c2' => array('score' => null, 'note' => '')), $kept, 'out of range and unknown dropped');
	pg_assert_same(null, pg_application_weighted_sum($criteria, pg_application_review_score_map($kept)), 'no total while one is unscored');

	$kept = pg_application_review_scores_input($criteria, array('c1' => '8', 'c2' => '10'), null);
	pg_assert_same(8.8, pg_application_weighted_sum($criteria, pg_application_review_score_map($kept)), '8 x 60 + 10 x 40 over 100');

	foreach (array('0', '1.5', ' 7', 'ten', '') as $bad) {
		$kept = pg_application_review_scores_input($criteria, array('c1' => $bad), null);
		pg_assert_same(null, $kept['c1']['score'], 'refused: ' . $bad);
	}

	// Stored and read back the same.
	$kept = pg_application_review_scores_input($criteria, array('c1' => '3'), array('c2' => 'Unclear'));
	pg_assert_same($kept, pg_application_review_scores_stored(json_encode($kept)));
	pg_assert_same(array(), pg_application_review_scores_stored(null));
}

function test_application_days_left_counts_up()
{
	$now = 1000000;

	pg_assert_same(null, pg_application_days_left(0, $now), 'no end');
	pg_assert_same(1, pg_application_days_left($now + 3600, $now), 'an hour is a day');
	pg_assert_same(3, pg_application_days_left($now + 3 * 86400, $now));
	pg_assert_same(0, pg_application_days_left($now - 1, $now), 'ended');
}

function test_application_reminders_three_days_and_one_day_each_once()
{
	$end = 2000000;
	$day = 86400;

	pg_assert_same(0, pg_application_reminder_stage($end, 0, $end - 4 * $day), 'too early');
	pg_assert_same(3, pg_application_reminder_stage($end, 0, $end - 3 * $day), 'three days before');
	pg_assert_same(0, pg_application_reminder_stage($end, $end - 3 * $day + 10, $end - 2 * $day), 'the three days\' sent');
	pg_assert_same(1, pg_application_reminder_stage($end, $end - 3 * $day + 10, $end - $day + 5), 'then the last day');
	pg_assert_same(0, pg_application_reminder_stage($end, $end - $day + 5, $end - 60), 'the last day\'s sent');
	pg_assert_same(1, pg_application_reminder_stage($end, 0, $end - 3600), 'the last day even when the three days\' was missed');
	pg_assert_same(0, pg_application_reminder_stage($end, 0, $end), 'nothing once the round has ended');
	pg_assert_same(0, pg_application_reminder_stage(0, 0, $end), 'nothing for a round without an end');
}

function test_application_queue_previous_and_next()
{
	pg_assert_same(array(0, 12), pg_application_queue_neighbours(array(11, 12, 13), 11));
	pg_assert_same(array(11, 13), pg_application_queue_neighbours(array('11', '12', '13'), 12));
	pg_assert_same(array(12, 0), pg_application_queue_neighbours(array(11, 12, 13), 13));
	pg_assert_same(array(0, 0), pg_application_queue_neighbours(array(11), 99), 'not in the queue');
}

function test_my_reviews_opens_by_membership_and_reads_only_own_reviews()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/my_reviews.php');

	// No Forms right asked; the review is looked up with the signed-in user.
	pg_assert_false(strpos($source, 'validate_forms_access') !== false, 'no Forms gate');
	pg_assert_contains("pg_application_my_review(\$mr_id, (int) \$user['id'])", $source, 'own review only');
	pg_assert_true(strpos($source, 'pg_application_my_review(') < strpos($source, "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n    if (!\$mr_autosave)"), 'checked before any write');
	pg_assert_contains("output_error(lang('You are not a reviewer of any application programme.'), 403)", $source, 'the list refuses a non-member');

	// The ownership check is in the query itself.
	$read = pg_test_function_source('pg_application_my_review');
	pg_assert_contains('application_reviewers.user_id = ? AND application_reviewers.revoked_at = 0', $read, 'by user and not removed');
	pg_assert_contains('application_reviews.status IN (0, 1, 2)', $read, 'a conflict is not opened');

	// One fill: Send the review.
	pg_assert_same(1, substr_count($source, 'pg-button-fill'), 'one fill');
	pg_assert_contains("h(lang('Send the review'))", $source);
}

function test_my_reviews_menu_row_follows_membership()
{
	$menu = pg_test_function_source('output_menu');
	pg_assert_contains("if (pg_application_review_member((int) \$user['id'])) {\n        \$menu_items[28]['id'] = 28;", $menu, 'slot 28 by membership');
	pg_assert_contains("case 'my_reviews.php':\n            \$active_menu = 28;", $menu, 'the screen lights its row');
}
