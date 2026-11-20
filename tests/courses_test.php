<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for courses (includes/fn/courses.php): the rule that says whether a
 * lesson is open (its days since the enrolment day, the lesson before it
 * with "Lessons in order", a done lesson staying open, an editor passing),
 * the enrolment day read from the orders and from the first open, the
 * first-open, done and completion rows written once, and the certificate's
 * signed code. The runner has no connection, so the pure rules are called
 * and the writers' SQL is read.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _courses_test_lessons()
{
	return array(
		array('id' => 11, 'sort_order' => 1, 'open_days' => 0),
		array('id' => 12, 'sort_order' => 2, 'open_days' => 7),
		array('id' => 13, 'sort_order' => 3, 'open_days' => 14),
	);
}

function test_courses_lesson_opens_by_days()
{
	$lessons = _courses_test_lessons();

	$state = pg_course_lesson_state($lessons, 12, array(), '2026-11-01', false, '2026-11-07', false);
	pg_assert_false($state['open'], 'day 6 of 7: closed');
	pg_assert_same('days', $state['reason'], 'closed by its days');
	pg_assert_same('2026-11-08', $state['opens_on'], 'opens on enrolment + 7');

	$state = pg_course_lesson_state($lessons, 12, array(), '2026-11-01', false, '2026-11-08', false);
	pg_assert_true($state['open'], 'day 7 of 7: open');
	pg_assert_same('', $state['reason'], 'no reason when open');

	$state = pg_course_lesson_state($lessons, 11, array(), '2026-11-01', false, '2026-11-01', false);
	pg_assert_true($state['open'], '0 days: open at once');
}

function test_courses_lesson_opens_by_order()
{
	$lessons = _courses_test_lessons();

	$state = pg_course_lesson_state($lessons, 12, array(), '2026-10-01', true, '2026-11-20', false);
	pg_assert_false($state['open'], 'the lesson before is not done');
	pg_assert_same('order', $state['reason'], 'closed by the order');
	pg_assert_same(11, $state['before_id'], 'names the lesson to finish first');

	$state = pg_course_lesson_state($lessons, 12, array(11), '2026-10-01', true, '2026-11-20', false);
	pg_assert_true($state['open'], 'the lesson before is done');

	$state = pg_course_lesson_state($lessons, 12, array(), '2026-10-01', false, '2026-11-20', false);
	pg_assert_true($state['open'], 'without "Lessons in order" the one before does not matter');

	// Days are checked before the order, so the notice names the later of the two.
	$state = pg_course_lesson_state($lessons, 13, array(11), '2026-11-10', true, '2026-11-12', false);
	pg_assert_same('days', $state['reason'], 'days first');
}

function test_courses_done_lesson_stays_open()
{
	$lessons = _courses_test_lessons();

	$state = pg_course_lesson_state($lessons, 13, array(13), '2026-11-10', true, '2026-11-11', false);
	pg_assert_true($state['open'], 'a done lesson stays open');
}

function test_courses_editor_passes()
{
	$lessons = _courses_test_lessons();

	$state = pg_course_lesson_state($lessons, 13, array(), '2026-11-10', true, '2026-11-11', true);
	pg_assert_true($state['open'], 'whoever may edit the page passes');
}

function test_courses_unknown_lesson_is_not_gated()
{
	$state = pg_course_lesson_state(_courses_test_lessons(), 99, array(), '2026-11-10', true, '2026-11-11', false);
	pg_assert_true($state['open'], 'a page that is no lesson of the course is not closed');
}

function test_courses_no_enrolment_day_counts_from_today()
{
	$lessons = _courses_test_lessons();

	$state = pg_course_lesson_state($lessons, 12, array(), '', false, '2026-11-11', false);
	pg_assert_false($state['open'], 'no day yet: the first open is today');
	pg_assert_same('2026-11-18', $state['opens_on'], 'counted from today');
}

function test_courses_enrolment_day_from_first_counting_order()
{
	$orders = array(
		array('status' => 'complete', 'order_date' => mktime(10, 0, 0, 11, 5, 2026)),
		array('status' => 'exported', 'order_date' => mktime(9, 0, 0, 11, 3, 2026)),
		array('status' => 'complete', 'order_date' => mktime(9, 0, 0, 11, 9, 2026)),
	);

	pg_assert_same('2026-11-03', pg_course_enrolment_day($orders, 0), 'the first counting order');
	pg_assert_same('2026-11-03', pg_course_enrolment_day($orders, mktime(8, 0, 0, 11, 1, 2026)), 'an order wins over a first open');
}

function test_courses_enrolment_day_cancelled_order_does_not_count()
{
	$orders = array(
		array('status' => 'cancelled', 'order_date' => mktime(10, 0, 0, 10, 1, 2026)),
		array('status' => 'incomplete', 'order_date' => mktime(10, 0, 0, 10, 2, 2026)),
		array('status' => 'complete', 'order_date' => mktime(10, 0, 0, 11, 5, 2026)),
	);

	pg_assert_same('2026-11-05', pg_course_enrolment_day($orders, 0), 'cancelled and incomplete orders do not count');
	pg_assert_true(pg_course_order_counts('exported'), 'an exported order counts');
	pg_assert_false(pg_course_order_counts('cancelled'), 'a cancelled order does not count');
}

function test_courses_enrolment_day_from_first_open()
{
	$orders = array(array('status' => 'cancelled', 'order_date' => mktime(10, 0, 0, 10, 1, 2026)));

	pg_assert_same('2026-11-02', pg_course_enrolment_day($orders, mktime(15, 0, 0, 11, 2, 2026)), 'given by hand: the first open');
	pg_assert_same('', pg_course_enrolment_day(array(), 0), 'neither: no day');
}

function test_courses_folder_chain_walks_the_parents()
{
	// 10 Academy at the root, 11 Course A below it, 12 a lesson group below that.
	$parents = array(10 => 0, 11 => 10, 12 => 11, 20 => 0);

	pg_assert_same(array(10), pg_course_folder_chain($parents, 10), 'a root folder: itself only');
	pg_assert_same(array(11, 10), pg_course_folder_chain($parents, 11), 'a parent grant opens the folder');
	pg_assert_same(array(12, 11, 10), pg_course_folder_chain($parents, 12), 'a grandparent grant opens it too');
	pg_assert_same(array(), pg_course_folder_chain($parents, 99), 'a folder that does not exist: none');
	pg_assert_same(array(), pg_course_folder_chain($parents, 0), 'no folder: none');
}

function test_courses_folder_chain_stops_on_a_broken_chain()
{
	pg_assert_same(array(5, 6), pg_course_folder_chain(array(5 => 6, 6 => 5), 5), 'a loop stops at the folder seen');
	pg_assert_same(array(7), pg_course_folder_chain(array(7 => 7), 7), 'a folder that is its own parent');
	pg_assert_same(array(3, 4), pg_course_folder_chain(array(3 => 4, 4 => 8), 3), 'a parent that names no folder stops the walk');

	$deep = array(1 => 0);
	for ($i = 2; $i <= 200; $i++) {
		$deep[$i] = $i - 1;
	}
	pg_assert_same(PG_COURSE_FOLDER_DEPTH, count(pg_course_folder_chain($deep, 200)), 'the walk is bounded');
}

function test_courses_grant_folder_sql_matches_the_chain()
{
	pg_assert_same("(products.private_folder IN ('11', '10'))", pg_course_grant_folder_sql(array(11, 10)), 'the folder and its parents');
	pg_assert_same('(1 = 0)', pg_course_grant_folder_sql(array()), 'no folder matches nothing');
	pg_assert_same("(products.private_folder IN ('3'))", pg_course_grant_folder_sql(array('3 OR 1', 0)), 'ids are integers');

	// The gate, the course blocks and the learners list read the same chain.
	$fn = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/courses.php');
	$admin = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/courses_admin.php');
	pg_assert_contains('pg_course_grant_folder_sql(pg_course_folder_ids($folder_id))', $fn, 'pg_course_member_orders() walks the parents');
	pg_assert_contains('pg_course_grant_folder_sql(pg_course_folder_ids($folder_id))', $admin, 'the learners list walks the parents');
	pg_assert_false(strpos($fn . $admin, "products.private_folder = '") !== false, 'no reader matches the course folder alone');
}

function test_courses_enrolment_day_from_a_parent_folder_grant()
{
	// Bought on day 1 a product opening "Academy", the parent of the course
	// folder; first open on day 10. The order counts: lesson 2 at 7 days
	// opens on day 8.
	$chain = pg_course_folder_chain(array(10 => 0, 11 => 10), 11);
	pg_assert_true(in_array(10, $chain, true), 'the parent grant opens the course folder');

	$orders = array(array('status' => 'complete', 'order_date' => mktime(10, 0, 0, 11, 1, 2026)));
	$day = pg_course_enrolment_day($orders, mktime(9, 0, 0, 11, 10, 2026));
	pg_assert_same('2026-11-01', $day, 'the order day, not the first open');

	$state = pg_course_lesson_state(_courses_test_lessons(), 12, array(), $day, false, '2026-11-10', false);
	pg_assert_true($state['open'], 'lesson 2 is open on day 10');
	$state = pg_course_lesson_state(_courses_test_lessons(), 12, array(), $day, false, '2026-11-07', false);
	pg_assert_same('2026-11-08', $state['opens_on'], 'lesson 2 opens on day 8');
}

function test_courses_first_open_row_only_for_member_with_folder()
{
	$now = mktime(12, 0, 0, 11, 2, 2026);

	pg_assert_same(null, pg_course_first_open_row(0, 5, true, $now), 'never for a visitor');
	pg_assert_same(null, pg_course_first_open_row(7, 5, false, $now), 'never without the folder');

	$row = pg_course_first_open_row(7, 5, true, $now);
	pg_assert_same(array('user_id' => 7, 'course_id' => 5, 'lesson_id' => 0, 'kind' => pg_course_kind('first_open'), 'created_at' => $now), $row, 'the row');
}

function test_courses_rows_are_written_once()
{
	$sql = pg_course_progress_insert_sql();

	pg_assert_contains('INSERT IGNORE INTO course_progress', $sql, 'a second write meets the key and is ignored');

	$kinds = array(pg_course_kind('done'), pg_course_kind('first_open'), pg_course_kind('complete'));
	pg_assert_same(3, count(array_unique($kinds)), 'three kinds');
	pg_assert_same(0, pg_course_kind('nothing'), 'an unknown kind');

	$schema = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/migrations/2026.11.0.php');
	pg_assert_contains('PRIMARY KEY (user_id, course_id, kind, lesson_id)', $schema, 'the key the write meets');
	pg_assert_contains('UNIQUE KEY idx_page (page_id)', $schema, 'a page is a lesson of one course only');
	pg_assert_contains('quiz_pass_score', $schema, 'the needed score is named apart from forms.quiz_score');

	pg_assert_same(PG_COURSE_KIND_DONE, pg_course_kind('done'), 'done constant');
	pg_assert_same(PG_COURSE_KIND_FIRST_OPEN, pg_course_kind('first_open'), 'first open constant');
	pg_assert_same(PG_COURSE_KIND_COMPLETE, pg_course_kind('complete'), 'complete constant');
}

function test_courses_done_row()
{
	$now = mktime(12, 0, 0, 11, 2, 2026);

	pg_assert_same(null, pg_course_done_row(0, 5, 11, $now), 'never for a visitor');
	pg_assert_same(null, pg_course_done_row(7, 5, 0, $now), 'never without a lesson');
	pg_assert_same(array('user_id' => 7, 'course_id' => 5, 'lesson_id' => 11, 'kind' => pg_course_kind('done'), 'created_at' => $now), pg_course_done_row(7, 5, 11, $now), 'the row');
}

function test_courses_completion_written_once()
{
	$now = mktime(12, 0, 0, 11, 2, 2026);

	pg_assert_same(null, pg_course_completion_row(7, 5, array(11, 12, 13), array(11, 12), false, $now), 'a lesson left');
	pg_assert_same(null, pg_course_completion_row(7, 5, array(11, 12, 13), array(11, 12, 13), true, $now), 'already complete: not again');
	pg_assert_same(null, pg_course_completion_row(7, 5, array(), array(), false, $now), 'a course without lessons is never complete');

	$row = pg_course_completion_row(7, 5, array(11, 12, 13), array(13, 12, 11, 99), false, $now);
	pg_assert_same(array('user_id' => 7, 'course_id' => 5, 'lesson_id' => 0, 'kind' => pg_course_kind('complete'), 'created_at' => $now), $row, 'the last lesson done completes');
}

function test_courses_certificate_code_round_trip()
{
	$key = 'site-secret';
	$at = mktime(12, 0, 0, 11, 2, 2026);
	$code = pg_course_certificate_code(7, 5, $at, $key);

	pg_assert_true(preg_match('/^[0-9A-Z]+-[0-9A-Z]+-[0-9A-Z]+-[0-9A-Z]{12}$/', $code) === 1, 'the code\'s shape: ' . $code);
	pg_assert_same(array('user_id' => 7, 'course_id' => 5, 'completed_at' => $at), pg_course_certificate_parse($code, $key), 'read back');
	pg_assert_same(array('user_id' => 7, 'course_id' => 5, 'completed_at' => $at), pg_course_certificate_parse(' ' . strtolower($code) . ' ', $key), 'typed in small letters');
	pg_assert_same('', pg_course_certificate_code(7, 5, $at, ''), 'no key: no code');
}

function test_courses_certificate_changed_code_refused()
{
	$key = 'site-secret';
	$at = mktime(12, 0, 0, 11, 2, 2026);
	$code = pg_course_certificate_code(7, 5, $at, $key);
	$parts = explode('-', $code);

	$other = $parts;
	$other[1] = strtoupper(base_convert('8', 10, 36));
	pg_assert_same(null, pg_course_certificate_parse(implode('-', $other), $key), 'another member');

	$other = $parts;
	$other[0] = strtoupper(base_convert('6', 10, 36));
	pg_assert_same(null, pg_course_certificate_parse(implode('-', $other), $key), 'another course');

	$other = $parts;
	$other[3] = ($other[3][0] === 'A' ? 'B' : 'A') . substr($other[3], 1);
	pg_assert_same(null, pg_course_certificate_parse(implode('-', $other), $key), 'a changed signature');

	pg_assert_same(null, pg_course_certificate_parse($code, 'another-secret'), 'another site\'s secret');
	pg_assert_same(null, pg_course_certificate_parse('nonsense', $key), 'not a code');
}

function _courses_test_gate_lesson($lesson_id, $page_id)
{
	return array(
		'id'      => $lesson_id,
		'page_id' => $page_id,
		'course'  => array('id' => 5, 'in_order' => '1', 'classroom_page_id' => 40),
	);
}

function test_courses_gate_closed_by_days()
{
	$answer = pg_course_gate_answer(true, 'private', _courses_test_gate_lesson(12, 52), _courses_test_lessons(), array(11), '2026-11-01', '2026-11-07', false);

	pg_assert_true($answer['closed'], 'day 6 of 7: closed');
	pg_assert_same('days', $answer['reason'], 'closed by its days');
	pg_assert_same('2026-11-08', $answer['opens_on'], 'names the day it opens');
	pg_assert_same(40, $answer['classroom_page_id'], 'sends the member to the classroom');
}

function test_courses_gate_closed_by_order()
{
	$answer = pg_course_gate_answer(true, 'private', _courses_test_gate_lesson(12, 52), _courses_test_lessons(), array(), '2026-11-01', '2026-11-20', false);

	pg_assert_true($answer['closed'], 'lesson before not done: closed');
	pg_assert_same('order', $answer['reason'], 'closed by the order');
	pg_assert_same(11, $answer['before_id'], 'names the lesson to finish first');
}

function test_courses_gate_open_lesson_passes()
{
	$answer = pg_course_gate_answer(true, 'private', _courses_test_gate_lesson(12, 52), _courses_test_lessons(), array(11), '2026-11-01', '2026-11-20', false);

	pg_assert_false($answer['closed'], 'days passed and the lesson before done');
}

function test_courses_gate_public_lesson_never_closed()
{
	foreach (array('public', 'registration', 'guest', 'membership', '') as $type) {
		$answer = pg_course_gate_answer(true, $type, _courses_test_gate_lesson(12, 52), _courses_test_lessons(), array(), '2026-11-01', '2026-11-02', false);
		pg_assert_false($answer['closed'], 'a lesson in a ' . ($type === '' ? 'blank' : $type) . ' folder is a free preview');
	}
}

function test_courses_gate_editor_passes()
{
	$answer = pg_course_gate_answer(true, 'private', _courses_test_gate_lesson(12, 52), _courses_test_lessons(), array(), '2026-11-01', '2026-11-02', true);

	pg_assert_false($answer['closed'], 'whoever may edit the page passes');
}

function test_courses_gate_page_that_is_no_lesson()
{
	$answer = pg_course_gate_answer(true, 'private', null, _courses_test_lessons(), array(), '2026-11-01', '2026-11-02', false);
	pg_assert_false($answer['closed'], 'a page that is no lesson');

	$answer = pg_course_gate_answer(true, 'private', _courses_test_gate_lesson(99, 59), _courses_test_lessons(), array(), '2026-11-01', '2026-11-02', false);
	pg_assert_false($answer['closed'], 'a lesson row missing from the course\'s list');
}

function test_courses_gate_switch_off()
{
	$answer = pg_course_gate_answer(false, 'private', _courses_test_gate_lesson(12, 52), _courses_test_lessons(), array(), '2026-11-01', '2026-11-02', false);

	pg_assert_false($answer['closed'], 'with the switch off no lesson is closed');
}

function test_courses_gate_classroom_never_sends_to_itself()
{
	$lesson = _courses_test_gate_lesson(12, 40);
	$answer = pg_course_gate_answer(true, 'private', $lesson, _courses_test_lessons(), array(), '2026-11-01', '2026-11-02', false);
	pg_assert_true($answer['closed'], 'the classroom page as a closed lesson is still closed');
	pg_assert_same(0, $answer['classroom_page_id'], 'but it is not sent to itself');

	$lesson['course']['classroom_page_id'] = 0;
	$answer = pg_course_gate_answer(true, 'private', $lesson, _courses_test_lessons(), array(), '2026-11-01', '2026-11-02', false);
	pg_assert_same(0, $answer['classroom_page_id'], 'a course without a classroom page');
}

function test_courses_gate_notice_markup()
{
	$html = pg_course_notice_html('Finish <b>"Week 1"</b> first.');

	pg_assert_contains('role="status"', $html, 'announced to a screen reader');
	pg_assert_contains('Finish &lt;b&gt;', $html, 'the text is escaped');
	pg_assert_same('', pg_course_notice_html(''), 'no text: nothing printed');
}

function test_courses_gate_notice_inject_after_body()
{
	$page = '<html><body class="x"><p>Classroom</p></body></html>';
	$out = pg_course_notice_insert($page, '<div id="n">$1</div>');

	pg_assert_contains('<body class="x"><div id="n">$1</div><p>', $out, 'right after the body tag, a dollar sign kept');
	pg_assert_same('<p>x</p>', pg_course_notice_insert('<p>x</p>', ''), 'nothing to insert');
	pg_assert_contains('<div id="n">', pg_course_notice_insert('<p>x</p>', '<div id="n"></div>'), 'a fragment without a body gets it first');
}

function test_courses_gate_notice_names_the_lessons()
{
	$name = 'Light <b>&</b> "exposure"';
	$days = array('closed' => true, 'reason' => 'days', 'opens_on' => '2026-11-24', 'before_id' => 0, 'classroom_page_id' => 40);
	$order = array('closed' => true, 'reason' => 'order', 'opens_on' => '', 'before_id' => 11, 'classroom_page_id' => 40);

	$text = pg_course_gate_notice_text($days, $name, '', '24 November 2026');
	pg_assert_contains($name, $text, 'days: names the closed lesson');
	pg_assert_contains('24 November 2026', $text, 'days: names the day it opens');

	$text = pg_course_gate_notice_text($order, $name, 'Week 1 <i>start</i>', '');
	pg_assert_contains('Week 1 <i>start</i>', $text, 'order: names the lesson to finish first');
	pg_assert_contains($name, $text, 'order: names the lesson it opens');

	$text = pg_course_gate_notice_text($order, $name, '', '');
	pg_assert_contains($name, $text, 'order without the lesson before\'s name: still names the closed lesson');

	$html = pg_course_notice_html(pg_course_gate_notice_text($days, $name, '', '24 November 2026'));
	pg_assert_contains('Light &lt;b&gt;&amp;&lt;/b&gt; &quot;exposure&quot;', $html, 'the name is escaped on the page');
	pg_assert_false(strpos($html, '<b>') !== false, 'no markup from the name');
}

// A deleted account's progress goes with it: a new account given the same
// id (MySQL 5.7 hands the highest id out again after a restart) would
// inherit its lessons done and its completion, and the Courses list would
// keep counting it as a learner and a completion.
function test_courses_progress_goes_with_a_deleted_account()
{
	pg_assert_true(function_exists('pg_course_forget_user'), 'the progress has a forget step');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/user_write.php');
	$forget = strpos($source, 'pg_course_forget_user(');
	$delete = strpos($source, 'DELETE FROM user WHERE');

	pg_assert_true($forget !== false, 'pg_user_delete() removes the course progress');
	pg_assert_true(($forget !== false) && ($delete !== false) && ($forget < $delete), 'before the account row goes');
}

function test_courses_lesson_outside_folder_does_not_count()
{
	// Lesson 12's page left the course's folder (40); lesson 13's page was
	// deleted (no folder). Neither gates or counts (Z17).
	$lessons = array(
		array('id' => 11, 'page_id' => 101, 'sort_order' => 1, 'open_days' => 0),
		array('id' => 12, 'page_id' => 102, 'sort_order' => 2, 'open_days' => 0),
		array('id' => 13, 'page_id' => 103, 'sort_order' => 3, 'open_days' => 0),
		array('id' => 14, 'page_id' => 104, 'sort_order' => 4, 'open_days' => 0),
	);
	$counted = pg_course_lessons_counted($lessons, array(101 => 40, 102 => 41, 104 => 40), 40);

	pg_assert_same(array(11, 14), array_map('intval', array_column($counted, 'id')), 'only the lessons in the folder count');
	pg_assert_same(array(), pg_course_lessons_counted($lessons, array(101 => 40), 0), 'a course without a folder counts none');

	$state = pg_course_lesson_state($counted, 14, array(11), '2026-11-01', true, '2026-11-20', false);
	pg_assert_true($state['open'], 'in order: the stray lessons before do not hold lesson 14 back');

	$now = mktime(12, 0, 0, 11, 2, 2026);
	$row = pg_course_completion_row(7, 5, array_column($counted, 'id'), array(11, 14), false, $now);
	pg_assert_same(pg_course_kind('complete'), $row['kind'] ?? 0, 'the counted lessons done complete the course');
}

function test_courses_student_side_reads_counted_lessons()
{
	$files = array(
		'course_action.php'            => file_get_contents(PG_FUNCTIONS_DIR . '/course_action.php'),
		'includes/fn/course_blocks.php' => file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/course_blocks.php'),
	);

	foreach ($files as $name => $source) {
		pg_assert_false(strpos($source, 'pg_course_lessons(') !== false, $name . ' reads the counted lessons, not every lesson row');
		pg_assert_true(strpos($source, 'pg_course_member_lessons(') !== false, $name . ' reads pg_course_member_lessons()');
	}

	$courses = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/courses.php');
	foreach (array('pg_course_mark_done', 'pg_course_gate') as $fn) {
		$at = strpos($courses, 'function ' . $fn . '(');
		$body = substr($courses, $at, strpos($courses, "\n}\n", $at) - $at);
		pg_assert_false(strpos($body, 'pg_course_lessons(') !== false, $fn . ' reads the counted lessons');
	}
}
