<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the learner's course blocks on the published page
 * (includes/fn/course_blocks.php): the press of "Mark as done" refused
 * without access, on a closed lesson, without the token and over the rate;
 * a quiz lesson below and at its needed score; two presses giving one
 * answer over one row; the view the blocks are filled from, for a member
 * and for anyone else; the script added to a page with a block only.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/course_blocks.php');

// A press that passes every check, with $change applied.
function _test_course_press(array $change = array())
{
	return pg_course_press_refusal(array_merge(array(
		'method' => 'POST', 'same_origin' => true, 'on' => true, 'throttled' => false, 'signed_in' => true,
		'token_ok' => true, 'lesson' => true, 'access' => true, 'open' => true, 'quiz_ok' => true,
	), $change));
}

// (Z8) Every check refuses on its own, with its status.
function test_course_press_refused()
{
	pg_assert_same(null, _test_course_press(), 'a press that passes every check writes');
	pg_assert_same(array(403, 'access'), _test_course_press(array('access' => false)), 'without the folder');
	pg_assert_same(array(403, 'closed'), _test_course_press(array('open' => false)), 'on a closed lesson');
	pg_assert_same(array(403, 'token'), _test_course_press(array('token_ok' => false)), 'without the token');
	pg_assert_same(array(429, 'rate'), _test_course_press(array('throttled' => true)), 'over the rate');
	pg_assert_same(array(401, 'sign_in'), _test_course_press(array('signed_in' => false)), 'a visitor');
	pg_assert_same(array(403, 'origin'), _test_course_press(array('same_origin' => false)), 'from another site');
	pg_assert_same(array(405, 'method'), _test_course_press(array('method' => 'GET')), 'a GET');
	pg_assert_same(array(404, 'off'), _test_course_press(array('on' => false, 'token_ok' => false)), 'the switch off answers before the token');
	pg_assert_same(array(404, 'lesson'), _test_course_press(array('lesson' => false)), 'no lesson');
	pg_assert_same(array(429, 'rate'), _test_course_press(array('throttled' => true, 'token_ok' => false)), 'the rate is counted before the token');
}

// (Z9) A quiz lesson is done only at or above its needed score.
function test_course_quiz_score()
{
	pg_assert_true(pg_course_quiz_passed(null, 0), 'no quiz');
	pg_assert_false(pg_course_quiz_passed(null, 70), 'no completed submission');
	pg_assert_false(pg_course_quiz_passed(69, 70), 'below the score');
	pg_assert_true(pg_course_quiz_passed(70, 70), 'at the score');
	pg_assert_true(pg_course_quiz_passed(100, 70), 'above the score');
	pg_assert_same(array(403, 'quiz'), _test_course_press(array('quiz_ok' => pg_course_quiz_passed(69, 70))));
	pg_assert_same(null, _test_course_press(array('quiz_ok' => pg_course_quiz_passed(70, 70))));
}

// (Z8) Two presses: the first writes the row, the second writes nothing
// (INSERT IGNORE on the row's key) and both answer "done".
function test_course_two_presses_one_row()
{
	pg_assert_same(0, strpos(pg_course_progress_insert_sql(), 'INSERT IGNORE INTO course_progress'), 'the writer ignores a second row');
	$first = pg_course_press_answer(array('done' => true, 'completed' => false), array());
	$second = pg_course_press_answer(array('done' => false, 'completed' => false), array());
	pg_assert_same($first, $second, 'the second press gives the same answer');
	pg_assert_same(1, $second['done']);

	$door = (string) file_get_contents(PG_FUNCTIONS_DIR . '/course_action.php');
	pg_assert_contains('pg_course_mark_done(', $door, 'the door writes through the writer that writes once');
	pg_assert_false(strpos($door, 'INSERT') !== false, 'the door writes no row of its own');
	pg_assert_contains('pg_course_press_refusal(', $door);
	pg_assert_contains("pg_course_throttled('press')", $door);
	pg_assert_contains('hash_equals(', $door, 'the token is compared in constant time');
}

function _test_course_lessons()
{
	$out = array();
	foreach (array(array(1, 0, 0), array(2, 0, 70), array(3, 7, 0), array(4, 0, 0)) as $i => $l) {
		$out[] = array('id' => $l[0], 'page_id' => 100 + $l[0], 'sort_order' => $i + 1, 'open_days' => $l[1],
			'quiz_page_id' => $l[2] ? 9 : 0, 'quiz_pass_score' => $l[2], 'name' => 'Lesson ' . $l[0], 'url' => '/lesson-' . $l[0]);
	}
	return $out;
}

// (Z11) A member's view: each lesson's state, the count, what comes next
// and the lesson of the page.
function test_course_blocks_view_member()
{
	$course = array('name' => 'Bread', 'url' => '/classroom', 'in_order' => 1);
	$view = pg_course_blocks_view($course, _test_course_lessons(), true, array(1), '2026-11-10', '2026-11-12', false, 102);
	pg_assert_same(array('done', 'open', 'opens', 'locked'), array_column($view['lessons'], 'state'));
	pg_assert_same('2026-11-17', $view['lessons'][2]['opens_on'], 'the day a timed lesson opens');
	pg_assert_same(array(4, 1, 0), array($view['total'], $view['done'], $view['complete']));
	pg_assert_same('/lesson-2', $view['next'], 'continue with the first open lesson not done');
	pg_assert_same(array('id' => 2, 'done' => 0, 'need' => 70), $view['lesson'], 'the lesson of the page and its quiz');

	$all = pg_course_blocks_view($course, _test_course_lessons(), true, array(1, 2, 3, 4), '2026-11-10', '2026-11-12', false, 0);
	pg_assert_same(array(4, 1, ''), array($all['done'], $all['complete'], $all['next']), 'every lesson done: complete');
	pg_assert_same(null, $all['lesson'], 'a page that is no lesson');

	$editor = pg_course_blocks_view($course, _test_course_lessons(), true, array(), '', '2026-11-12', true, 0);
	pg_assert_same(array('open', 'open', 'open', 'open'), array_column($editor['lessons'], 'state'), 'an editor passes');
}

// (Z12) Anyone else sees the lessons' names only.
function test_course_blocks_view_visitor()
{
	$view = pg_course_blocks_view(array('name' => 'Bread', 'url' => '/classroom', 'in_order' => 1), _test_course_lessons(), false, array(1), '2026-11-10', '2026-11-12', false, 102);
	pg_assert_same(array('name' => 'Lesson 1'), $view['lessons'][0]);
	pg_assert_same(0, $view['member']);
	foreach (array('total', 'done', 'next', 'lesson', 'complete') as $key) {
		pg_assert_false(array_key_exists($key, $view), 'no ' . $key . ' for a visitor');
	}
}

// The script is added once, to a page holding a block only.
function test_course_blocks_inject()
{
	$settings = pg_course_blocks_settings('/course_action.php', 7, array('Done' => 'Bitti'));
	$page = '<body><div data-pg-course="progress" hidden></div></body>';
	$out = pg_course_blocks_inject($page, 7, $settings, '/x/pg_course.js');
	pg_assert_contains('<script src="/x/pg_course.js" defer></script>', $out);
	pg_assert_contains('window.pgCourseConfig = {"door":"\/course_action.php","page":7', $out);
	$once = pg_course_blocks_inject($page, 7, $settings, '/assets/js/pg_course.js');
	pg_assert_same($once, pg_course_blocks_inject($once, 7, $settings, '/assets/js/pg_course.js'), 'once');
	foreach (array('<body><p>data-pg-course</p></body>', '<body><li data-pg-course-item></li></body>') as $none) {
		pg_assert_same($none, pg_course_blocks_inject($none, 7, $settings, '/x/pg_course.js'), 'no block: ' . $none);
	}
	pg_assert_true(pg_course_blocks_on_page('<div data-pg-course>'), 'a bare mark');
}
