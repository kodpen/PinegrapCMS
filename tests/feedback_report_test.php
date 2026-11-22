<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the satisfaction survey results' pure parts
 * (includes/fn/feedback_report.php): the months and their bounds, the
 * survey choice, the grouping's SQL, the fold of counted cells into rows,
 * the staff rule under 5 answers, the frequent words, the band filter and
 * the workbook rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once PG_FUNCTIONS_DIR . '/includes/fn/feedback_report.php';

function test_feedback_report_months_and_bounds()
{
	$now = mktime(12, 0, 0, 3, 15, 2026);

	pg_assert_same('2026-03', pg_feedback_report_month('', $now), 'no month is this month');
	pg_assert_same('2025-11', pg_feedback_report_month('2025-11', $now), 'a month is kept');
	pg_assert_same('2026-03', pg_feedback_report_month('2025-13', $now), 'month 13 is refused');
	pg_assert_same('2026-03', pg_feedback_report_month('2026-04', $now), 'a month ahead is refused');
	pg_assert_same('2026-03', pg_feedback_report_month(array('x'), $now), 'an array is refused');

	pg_assert_same('2025-12', pg_feedback_report_month_step('2026-01', -1), 'back over the year');
	pg_assert_same('2027-01', pg_feedback_report_month_step('2026-12', 1), 'on over the year');

	pg_assert_same(array('2026-03'), pg_feedback_report_months('2026-03', false), 'one month');
	$year = pg_feedback_report_months('2026-03', true);
	pg_assert_same(12, count($year), 'twelve months');
	pg_assert_same('2025-04', $year[0], 'the oldest first');
	pg_assert_same('2026-03', $year[11], 'the chosen one last');

	pg_assert_same(array(mktime(0, 0, 0, 3, 1, 2026), mktime(0, 0, 0, 4, 1, 2026)), pg_feedback_report_bounds(array('2026-03')), 'a month runs to the next one');
	pg_assert_same(array(mktime(0, 0, 0, 4, 1, 2025), mktime(0, 0, 0, 4, 1, 2026)), pg_feedback_report_bounds($year), 'a year from its first month');
}

function test_feedback_report_scope()
{
	$surveys = array(4 => PG_FEEDBACK_CSAT, 7 => PG_FEEDBACK_NPS);

	pg_assert_same(array('kind' => PG_FEEDBACK_CSAT, 'survey' => 4, 'value' => '4'), pg_feedback_report_scope('4', $surveys, PG_FEEDBACK_NPS), 'a survey brings its kind');
	pg_assert_same(array('kind' => PG_FEEDBACK_EFFORT, 'survey' => 0, 'value' => 'k3'), pg_feedback_report_scope('k3', $surveys, PG_FEEDBACK_NPS), 'all surveys of a kind');
	pg_assert_same(array('kind' => PG_FEEDBACK_NPS, 'survey' => 0, 'value' => 'k1'), pg_feedback_report_scope('9', $surveys, PG_FEEDBACK_NPS), 'an unknown survey is the default kind');
	pg_assert_same(array('kind' => PG_FEEDBACK_CSAT, 'survey' => 0, 'value' => 'k2'), pg_feedback_report_scope('k9', $surveys, PG_FEEDBACK_CSAT), 'an unknown kind is the default kind');
}

function test_feedback_report_groupings()
{
	pg_assert_true(isset(pg_feedback_report_groupings(true)['staff']), 'a manager groups by staff member');
	pg_assert_false(isset(pg_feedback_report_groupings(false)['staff']), 'below manager there is no staff grouping');
	pg_assert_same(array('touchpoint', 'service', 'staff', 'course', 'event', 'form', 'month'), array_keys(pg_feedback_report_groupings(true)), 'the order of the choice');
}

function test_feedback_report_group_sql()
{
	$service = pg_feedback_report_group_sql('service', array('2026-03'));
	pg_assert_same('r.subject_id', $service['key'], 'a service is the subject');
	pg_assert_same(" AND r.record_type = 'booking'", $service['where'], 'of bookings only');

	$staff = pg_feedback_report_group_sql('staff', array('2026-03'));
	pg_assert_same(' AND r.staff_user_id <> 0', $staff['where'], 'answers with a staff member only');

	$touchpoint = pg_feedback_report_group_sql('touchpoint', array('2026-03'));
	pg_assert_same('s.touchpoint', $touchpoint['key'], 'what it follows is the survey\'s');

	$month = pg_feedback_report_group_sql('month', array('2026-02', '2026-03'));
	pg_assert_same("CASE WHEN r.sent_at < " . mktime(0, 0, 0, 3, 1, 2026) . " THEN '2026-02' ELSE '2026-03' END", $month['key'], 'months by the send time');
	pg_assert_same('', $month['where'], 'every answer has a month');
}

function test_feedback_report_fold()
{
	// key, score, answered, count
	$cells = array(
		array('k' => '1', 's' => '10', 'a' => '1', 'n' => '3'),
		array('k' => '1', 's' => '3', 'a' => '1', 'n' => '1'),
		array('k' => '1', 's' => '0', 'a' => '0', 'n' => '4'),
		array('k' => '2', 's' => '8', 'a' => '1', 'n' => '2'),
	);
	$fold = pg_feedback_report_fold(PG_FEEDBACK_NPS, $cells);

	pg_assert_same(8, $fold['rows']['1']['sent'], 'answered and not answered are sent');
	pg_assert_same(4, $fold['rows']['1']['answered'], 'four answers');
	pg_assert_same(50, $fold['rows']['1']['rate'], 'half answered');
	pg_assert_same(50, $fold['rows']['1']['sum']['score'], 'three promoters and a detractor of four');
	pg_assert_same(0, $fold['rows']['2']['sum']['score'], 'two passives');
	pg_assert_same(10, $fold['total']['sent'], 'the total sends');
	pg_assert_same(6, $fold['total']['answered'], 'the total answers');
	pg_assert_same(1, $fold['total']['low'], 'one low answer');
	pg_assert_same(33, $fold['total']['sum']['score'], 'the total NPS');

	$empty = pg_feedback_report_fold(PG_FEEDBACK_CSAT, array());
	pg_assert_same(array(), $empty['rows'], 'no cells, no rows');
	pg_assert_same(null, $empty['total']['rate'], 'nothing sent has no rate');
	pg_assert_same('', pg_feedback_report_score_text(PG_FEEDBACK_CSAT, $empty['total']['sum']), 'no answers, no score');
}

function test_feedback_report_score_text()
{
	pg_assert_same('+50', pg_feedback_report_score_text(PG_FEEDBACK_NPS, pg_feedback_sum(PG_FEEDBACK_NPS, array(10, 10, 10, 3))), 'an NPS is signed');
	pg_assert_same('-100', pg_feedback_report_score_text(PG_FEEDBACK_NPS, pg_feedback_sum(PG_FEEDBACK_NPS, array(1))), 'a negative NPS');
	pg_assert_same('0', pg_feedback_report_score_text(PG_FEEDBACK_NPS, pg_feedback_sum(PG_FEEDBACK_NPS, array(7))), 'zero has no sign');
	pg_assert_same(pg_format_number(4.5, 1), pg_feedback_report_score_text(PG_FEEDBACK_CSAT, pg_feedback_sum(PG_FEEDBACK_CSAT, array(4, 5))), 'a CSAT is its mean');
	pg_assert_same(pg_format_number(2.0, 1), pg_feedback_report_score_text(PG_FEEDBACK_EFFORT, pg_feedback_sum(PG_FEEDBACK_EFFORT, array(1, 3))), 'effort is its mean');
}

function test_feedback_report_staff_under_five()
{
	pg_assert_true(pg_feedback_report_few('staff', array('answered' => 4)), 'a staff member with 4 answers is held back');
	pg_assert_false(pg_feedback_report_few('staff', array('answered' => 5)), 'from 5 answers on it shows');
	pg_assert_false(pg_feedback_report_few('service', array('answered' => 1)), 'a service always shows');
}

function test_feedback_report_band_key()
{
	pg_assert_same(PG_FEEDBACK_BAND_LOW, pg_feedback_band_key('low'), 'low');
	pg_assert_same(PG_FEEDBACK_BAND_MIDDLE, pg_feedback_band_key('middle'), 'middle');
	pg_assert_same(PG_FEEDBACK_BAND_HIGH, pg_feedback_band_key('high'), 'high');
	pg_assert_same(0, pg_feedback_band_key('worst'), 'anything else is every band');
	pg_assert_same(0, pg_feedback_band_key(array()), 'an array is every band');
}

function test_feedback_report_words()
{
	$comments = array(
		'The staff was friendly and the room was clean.',
		'Friendly staff, but the waiting was long. Waiting, waiting!',
		'Clean room; friendly people. 2026',
	);
	$words = pg_feedback_words($comments, 'en', 3);

	pg_assert_same(array('friendly' => 3, 'clean' => 2, 'room' => 2), $words, 'counted once a comment, most first, then by word');
	pg_assert_false(isset(pg_feedback_words($comments, 'en', 20)['the']), 'stop words are left out');
	pg_assert_false(isset(pg_feedback_words($comments, 'en', 20)['2026']), 'numbers are left out');
	pg_assert_same(1, pg_feedback_words($comments, 'en', 20)['waiting'], 'a word said three times in one comment counts once');

	$turkish = pg_feedback_words(array('İLGİLİ ve güler yüzlü', 'çok ilgili bir ekip', 'Işık güzeldi, ama çok geç geldi'), 'tr', 20);
	pg_assert_same(2, $turkish['ilgili'], 'a Turkish capital İ folds to i');
	pg_assert_true(isset($turkish['ışık']), 'a Turkish capital I folds to ı');
	pg_assert_false(isset($turkish['çok']) || isset($turkish['ama']) || isset($turkish['bir']), 'Turkish stop words are left out');

	pg_assert_same(array(), pg_feedback_words(array('', null, 'ok'), 'en', 20), 'short words and empty comments give nothing');
}

function test_feedback_report_sheet_rows()
{
	$rows = pg_feedback_report_sheet(array('Name', 'Score'), array(array('Kadıköy', 42), array('=1+1', null), array('Late', 4.5)));

	pg_assert_same(array('t' => 's', 'v' => 'Name'), $rows[1][0], 'the heading is the first row');
	pg_assert_same(array('t' => 'n', 'v' => '42'), $rows[2][1], 'a number is a number');
	pg_assert_same(array('t' => 's', 'v' => '=1+1'), $rows[3][0], 'a formula stays text');
	pg_assert_same(null, $rows[3][1], 'no value is a gap');
	pg_assert_same(array('t' => 'n', 'v' => '4.5'), $rows[4][1], 'a fraction is a number');
}

function test_feedback_report_record_url()
{
	pg_assert_same('edit_booking.php?id=5', pg_feedback_report_record_url('booking', 5), 'a booking opens its booking');
	pg_assert_same('view_order.php?id=8', pg_feedback_report_record_url('order', 8), 'a delivery opens its order');
	pg_assert_same('edit_submitted_form.php?id=3', pg_feedback_report_record_url('form', 3), 'a form opens its submission');
	pg_assert_same('', pg_feedback_report_record_url('periodic', 3), 'a periodic survey has no record');
	pg_assert_same('', pg_feedback_report_record_url('booking', 0), 'no id, no link');
}
