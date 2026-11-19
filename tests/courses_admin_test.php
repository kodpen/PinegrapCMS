<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Courses screen's side (includes/fn/courses_admin.php): who
 * may open it, a posted course and its lessons read and checked, the
 * problems a course is named with, a lesson outside the folder gating
 * nothing, a learner's row and the learners' CSV rows. The runner has no
 * connection, so only the pure functions are called.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/courses_admin.php');

function _courses_admin_test_post(array $extra = array())
{
	return array_merge(array(
		'name'              => '  Photography   basics ',
		'folder_id'         => '7',
		'classroom_page_id' => '40',
		'in_order'          => '1',
		'lesson_id'         => array('3', '0', '0'),
		'lesson_page'       => array('41', '0', '42'),
		'lesson_days'       => array('0', '5', '7'),
		'lesson_quiz'       => array('0', '0', '90'),
		'lesson_score'      => array('', '', '70'),
	), $extra);
}

function test_courses_admin_staff_allowed()
{
	pg_assert_true(pg_course_staff_allowed(array('role' => 0)), 'an administrator');
	pg_assert_true(pg_course_staff_allowed(array('role' => 2)), 'a manager');
	pg_assert_false(pg_course_staff_allowed(array('role' => 3)), 'a user');
	pg_assert_false(pg_course_staff_allowed(array()), 'no role');
}

function test_courses_admin_input_reads_course_and_lessons()
{
	$input = pg_course_input(_courses_admin_test_post());

	pg_assert_same(array(), $input['errors'], 'no errors');
	pg_assert_same('Photography basics', $input['course']['name'], 'the name is trimmed and its spaces folded');
	pg_assert_same(7, $input['course']['folder_id'], 'the folder');
	pg_assert_same(1, $input['course']['in_order'], 'lessons in order');
	pg_assert_same(0, $input['course']['certificate'], 'no certificate when not posted');
	pg_assert_same(2, count($input['lessons']), 'a row without a page is skipped');
	pg_assert_same(array(1, 2), array_column($input['lessons'], 'sort_order'), 'numbered in the order shown');
	pg_assert_same(3, $input['lessons'][0]['id'], 'a kept lesson keeps its id');
	pg_assert_same(0, $input['lessons'][0]['quiz_pass_score'], 'no quiz: score 0');
	pg_assert_same(90, $input['lessons'][1]['quiz_page_id'], 'the quiz page');
	pg_assert_same(70, $input['lessons'][1]['quiz_pass_score'], 'the score needed');
	pg_assert_same(7, $input['lessons'][1]['open_days'], 'the days of the third row, not of the skipped one');
}

function test_courses_admin_input_refuses_bad_values()
{
	$input = pg_course_input(_courses_admin_test_post(array('name' => ' ', 'folder_id' => '0')));
	pg_assert_true(isset($input['errors']['name']), 'a name is needed');
	pg_assert_true(isset($input['errors']['folder_id']), 'a folder is needed');

	$input = pg_course_input(_courses_admin_test_post(array('lesson_page' => array('41', '0', '41'))));
	pg_assert_true(isset($input['errors']['lessons']), 'a page twice is refused');

	$input = pg_course_input(_courses_admin_test_post(array('lesson_score' => array('', '', '0'))));
	pg_assert_true(isset($input['errors']['lessons']), 'a quiz lesson with score 0 is refused');

	$input = pg_course_input(_courses_admin_test_post(array('lesson_score' => array('', '', '101'))));
	pg_assert_true(isset($input['errors']['lessons']), 'a score over 100 is refused');

	$input = pg_course_input(_courses_admin_test_post(array('lesson_days' => array('-1', '0', '0'))));
	pg_assert_true(isset($input['errors']['lessons']), 'negative days are refused');

	$input = pg_course_input(_courses_admin_test_post(array('lesson_days' => array((string) (PG_COURSE_OPEN_DAYS_MAX + 1), '0', '0'))));
	pg_assert_true(isset($input['errors']['lessons']), 'too many days are refused');

	$input = pg_course_input(_courses_admin_test_post(array('lesson_quiz' => array('0', '0', '0'), 'lesson_score' => array('', '', '55'))));
	pg_assert_same(array(), $input['errors'], 'a score without a quiz is not asked');
	pg_assert_same(0, $input['lessons'][1]['quiz_pass_score'], 'and is not kept');
}

function test_courses_admin_lesson_in_folder()
{
	pg_assert_true(pg_course_lesson_in_folder(7, 7), 'right in the folder');
	pg_assert_false(pg_course_lesson_in_folder(8, 7), 'in another folder');
	pg_assert_false(pg_course_lesson_in_folder(0, 7), 'a page that is gone');
	pg_assert_false(pg_course_lesson_in_folder(0, 0), 'a course without a folder');
}

function test_courses_admin_problems()
{
	$course = array('folder_id' => 7, 'classroom_page_id' => 40);
	$lessons = array(array('id' => 1, 'page_id' => 41), array('id' => 2, 'page_id' => 42));
	$folders = array(40 => 7, 41 => 7, 42 => 7);

	pg_assert_same(array(), pg_course_problems($course, 'private', $lessons, $folders), 'a working course has none');

	$codes = array_column(pg_course_problems($course, 'public', $lessons, $folders), 'code');
	pg_assert_same(array('folder_not_private'), $codes, 'a public folder');
	pg_assert_true(pg_course_problem_blocks(array('code' => 'folder_not_private')), 'which closes nothing');

	$codes = array_column(pg_course_problems($course, '', $lessons, $folders), 'code');
	pg_assert_same(array('folder_gone'), $codes, 'a deleted folder');

	$problems = pg_course_problems($course, 'private', $lessons, array(40 => 7, 41 => 9));
	pg_assert_same(array('lesson_outside', 'lesson_outside'), array_column($problems, 'code'), 'a page moved away and a page deleted');
	pg_assert_same(array(41, 42), array_column($problems, 'page_id'), 'each names its page');
	pg_assert_false(pg_course_problem_blocks($problems[0]), 'one lesson does not stop the course');

	$codes = array_column(pg_course_problems(array('folder_id' => 7, 'classroom_page_id' => 0), 'private', array(), array()), 'code');
	pg_assert_same(array('no_lessons', 'no_classroom'), $codes, 'no lessons and no classroom');

	$codes = array_column(pg_course_problems($course, 'private', $lessons, array(40 => 3, 41 => 7, 42 => 7)), 'code');
	pg_assert_same(array('classroom_outside'), $codes, 'a classroom page in another folder');

	foreach (array('folder_gone', 'folder_not_private', 'no_lessons', 'no_classroom', 'classroom_outside', 'lesson_outside') as $code) {
		pg_assert_true(pg_course_problem_text(array('code' => $code, 'page_id' => 41), 'Week 1') !== '', $code . ' is said in a sentence');
	}

	pg_assert_contains('Week 1', pg_course_problem_text(array('code' => 'lesson_outside', 'page_id' => 41), 'Week 1'), 'the sentence names the page');
}

function test_courses_admin_learner_row()
{
	$member = array('user_id' => '5', 'username' => 'ayse', 'email' => 'ayse@example.com');
	$order_at = strtotime('2026-11-02 15:00:00');

	$row = pg_course_learner_row($member, array('done' => '3', 'first_open_at' => '0', 'completed_at' => '0', 'last_at' => '1700000000'), $order_at, 4);
	pg_assert_same('2026-11-02', $row['enrolled'], 'enrolled on the first order\'s day');
	pg_assert_same(3, $row['done'], 'three done');
	pg_assert_same(4, $row['lessons'], 'of four');

	$row = pg_course_learner_row($member, array('done' => '9', 'first_open_at' => (string) strtotime('2026-11-05 10:00:00')), 0, 4);
	pg_assert_same('2026-11-05', $row['enrolled'], 'given by hand: the first open\'s day');
	pg_assert_same(4, $row['done'], 'never more done than lessons');

	$row = pg_course_learner_row($member, array(), 0, 4);
	pg_assert_same('', $row['enrolled'], 'not enrolled yet');
	pg_assert_same(0, $row['done'], 'nothing done');
}

function test_courses_admin_learners_csv_rows()
{
	$rows = pg_course_learners_csv_rows(array(array(
		'user_id' => 5, 'name' => '=HYPERLINK("x")', 'email' => 'a@example.com', 'enrolled' => '2026-11-02',
		'done' => 2, 'lessons' => 4, 'last_at' => 0, 'completed_at' => 0,
	)));

	pg_assert_same(2, count($rows), 'a heading row and one learner');
	pg_assert_same(7, count($rows[0]), 'seven columns');
	pg_assert_same('=HYPERLINK("x")', $rows[1][0], 'the cell is left for csv_cell() to guard');
	pg_assert_same(array('2', '4', '', ''), array_slice($rows[1], 3), 'figures, and no times for none');
	pg_assert_same('\'=HYPERLINK(""x"")', csv_cell($rows[1][0]), 'the writer guards a formula');
}

// A browser leaves a disabled field out of the post. Every lesson row must
// post one value to each of the five parallel lists, or a quiz lesson after
// a lesson without one reads another row's score (or none, and the save is
// refused).
function test_courses_admin_lesson_row_posts_every_list()
{
	$plain = pg_course_lesson_row_html(array('id' => 3, 'page_id' => 41, 'open_days' => 0, 'quiz_page_id' => 0, 'quiz_pass_score' => 0), '<option value="41">A</option>', '<option value="0">No quiz</option>');
	$quiz = pg_course_lesson_row_html(array('id' => 4, 'page_id' => 42, 'open_days' => 7, 'quiz_page_id' => 90, 'quiz_pass_score' => 70), '<option value="42">B</option>', '<option value="90">Quiz</option>');

	foreach (array('lesson_id', 'lesson_page', 'lesson_days', 'lesson_quiz', 'lesson_score') as $list) {
		pg_assert_same(1, substr_count($plain, 'name="' . $list . '[]"'), 'a lesson without a quiz posts ' . $list);
		pg_assert_same(1, substr_count($quiz, 'name="' . $list . '[]"'), 'a quiz lesson posts ' . $list);
	}

	pg_assert_false((bool) preg_match('/<(input|select)\b[^>]*\bname="lesson_[a-z]+\[\]"[^>]*\sdisabled\b/', $plain), 'no posted field of a lesson without a quiz is disabled');
	pg_assert_contains('readonly', $plain, 'its score field cannot be typed in');
	pg_assert_contains('value="70"', $quiz, 'a quiz lesson shows its score');
	pg_assert_false(strpos($quiz, 'readonly') !== false, 'and can change it');

	// What the browser then posts for the two rows reads back right.
	$input = pg_course_input(array(
		'name' => 'Course', 'folder_id' => '7',
		'lesson_id' => array('3', '4'), 'lesson_page' => array('41', '42'), 'lesson_days' => array('0', '7'),
		'lesson_quiz' => array('0', '90'), 'lesson_score' => array('', '70'),
	));
	pg_assert_same(array(), $input['errors'], 'the save is not refused');
	pg_assert_same(70, $input['lessons'][1]['quiz_pass_score'], 'the quiz lesson keeps its own score');
}

// output_header() prints its heading as markup, so a course's name, typed by
// staff, is escaped before it goes there; the title and the breadcrumb escape
// their own text.
function test_courses_admin_editor_heading_escapes_the_name()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_course.php');

	pg_assert_contains('\'heading\' => h($course_title)', $source, 'the heading is the escaped name');
	pg_assert_false((bool) preg_match('/\'heading\'\s*=>\s*\$course_title/', $source), 'never the raw name');
}
