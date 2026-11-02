<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's Security and Team cards (includes/dashboard/
 * v2/cards/firewall.php, team.php, mytasks.php and includes/dashboard/v2/
 * team.php): the bars and the marked ones, the names of what was blocked, a
 * person as card data, the days of a week, task tones and the line under a
 * teammate.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/run.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/team.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/firewall.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/team.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards/mytasks.php');

// Grouped rows land in their slot; slots outside the range are dropped and
// an empty slot is zero.
function test_dashboard_v2_slot_bars()
{
	pg_assert_same(array(3, 0, 7, 0), pg_dashboard_v2_slot_bars(array(
		array('slot' => '0', 'hits' => '3'),
		array('slot' => 2, 'hits' => 7),
		array('slot' => 4, 'hits' => 9),
		array('slot' => -1, 'hits' => 9),
	), 4));
}

// A spike well above the busy bars is marked; an even or a quiet series,
// and a lone bar, mark nothing.
function test_dashboard_v2_hot_bars()
{
	pg_assert_same(array(false, false, true, false), pg_dashboard_v2_hot_bars(array(2, 3, 40, 3)));
	pg_assert_same(array(false, false, false), pg_dashboard_v2_hot_bars(array(10, 12, 11)));
	pg_assert_same(array(false, false, false), pg_dashboard_v2_hot_bars(array(0, 1, 4)), 'below five');
	pg_assert_same(array(false, false), pg_dashboard_v2_hot_bars(array(0, 90)), 'one bar alone');
	pg_assert_same(array(), pg_dashboard_v2_hot_bars(array()));
}

// A bot keeps its own name (title-cased when written in lower case); a rule
// category is named in words; an unknown one is shown as it is.
function test_dashboard_v2_waf_source()
{
	pg_assert_same('Gptbot', pg_dashboard_v2_waf_source('gptbot', 'bot'));
	pg_assert_same('ClaudeBot', pg_dashboard_v2_waf_source('ClaudeBot', 'bot'));
	pg_assert_same(lang('SQL Injection'), pg_dashboard_v2_waf_source('sqli', 'sqli'));
	pg_assert_same(lang('Sign-in Lockouts'), pg_dashboard_v2_waf_source('login', 'login'));
	pg_assert_same('custom', pg_dashboard_v2_waf_source('custom', 'custom'));
}

// A person carries letters, a presence the card knows and a photo only
// when it is one.
function test_dashboard_v2_person()
{
	$person = pg_dashboard_v2_person(array('name' => 'Ayşe Demir'));
	pg_assert_same('AD', $person['initials']);
	pg_assert_same('offline', $person['presence']);
	pg_assert_same('', $person['photo']);

	$person = pg_dashboard_v2_person(array('name' => 'Can', 'initials' => 'CY', 'tone' => 2, 'presence' => 'away',
		'avatar' => 'https://example.com/a.jpg', 'avatar_kind' => 'photo'));
	pg_assert_same('CY', $person['initials']);
	pg_assert_same('away', $person['presence']);
	pg_assert_same('https://example.com/a.jpg', $person['photo']);

	$person = pg_dashboard_v2_person(array('name' => 'Elif', 'presence' => 'busy',
		'avatar' => 'data:image/svg+xml,x', 'avatar_kind' => 'photo'));
	pg_assert_same('offline', $person['presence'], 'unknown presence');
	pg_assert_same('', $person['photo'], 'letters picture');
	pg_assert_same('?', pg_dashboard_v2_person(array())['initials']);
}

// The week starts on Monday at midnight, whatever the day and hour.
function test_dashboard_v2_week_start()
{
	pg_assert_same(mktime(0, 0, 0, 10, 26, 2026), pg_dashboard_v2_week_start(mktime(15, 30, 0, 10, 30, 2026)), 'Friday');
	pg_assert_same(mktime(0, 0, 0, 10, 26, 2026), pg_dashboard_v2_week_start(mktime(0, 0, 0, 10, 26, 2026)), 'Monday');
	pg_assert_same(mktime(0, 0, 0, 10, 26, 2026), pg_dashboard_v2_week_start(mktime(23, 59, 0, 11, 1, 2026)), 'Sunday');
}

// Days run on across a month end, today is marked and items are counted.
function test_dashboard_v2_days()
{
	$days = pg_dashboard_v2_days(mktime(0, 0, 0, 10, 26, 2026), 35, array('2026-10-30' => 2, '2026-11-02' => 1), mktime(10, 0, 0, 10, 30, 2026));

	pg_assert_same(35, count($days));
	pg_assert_same('2026-10-26', $days[0]['date']);
	pg_assert_same(1, $days[6]['day'], 'November 1st');
	pg_assert_same('2026-11-29', $days[34]['date']);
	pg_assert_same(true, $days[4]['today']);
	pg_assert_same(false, $days[5]['today']);
	pg_assert_same(2, $days[4]['items']);
	pg_assert_same(1, $days[7]['items']);
	pg_assert_same(0, $days[8]['items']);
	pg_assert_true($days[4]['label'] !== $days[7]['label']);
}

// Late is bad; due today or urgent is a warning; anything else has no tone.
function test_dashboard_v2_task_tone()
{
	pg_assert_same('bad', pg_dashboard_v2_task_tone(array('overdue' => true, 'due_date' => '2026-10-01', 'priority' => 'normal'), '2026-11-01'));
	pg_assert_same('warn', pg_dashboard_v2_task_tone(array('overdue' => false, 'due_date' => '2026-11-01', 'priority' => 'normal'), '2026-11-01'));
	pg_assert_same('warn', pg_dashboard_v2_task_tone(array('overdue' => false, 'due_date' => null, 'priority' => 'urgent'), '2026-11-01'));
	pg_assert_same('', pg_dashboard_v2_task_tone(array('overdue' => false, 'due_date' => '2026-11-04', 'priority' => 'high'), '2026-11-01'));
}

// The line under a teammate: where they are when out, how long a timer has
// run, else their presence; always with their first name.
function test_dashboard_v2_team_note()
{
	$person = array('name' => 'Ayşe Demir', 'presence' => 'online');
	$now = 1000000;

	pg_assert_same('Ayşe · ' . lang('On leave'), pg_dashboard_v2_team_note(array('away' => lang('On leave'), 'since' => 0), $person, $now));
	pg_assert_same('Ayşe · ' . lang(array('string' => 'working for {var:1} min', 'vars' => '20')), pg_dashboard_v2_team_note(array('away' => '', 'since' => $now - 1200), $person, $now));
	pg_assert_same('Ayşe · ' . lang(array('string' => 'working for {var:1} h', 'vars' => '2')), pg_dashboard_v2_team_note(array('away' => '', 'since' => $now - 7300), $person, $now));
	pg_assert_same('Ayşe · ' . lang('online'), pg_dashboard_v2_team_note(array('away' => '', 'since' => 0), $person, $now));
	pg_assert_same('Ayşe · ' . lang('away'), pg_dashboard_v2_team_note(array('away' => '', 'since' => 0), array('name' => 'Ayşe Demir', 'presence' => 'away'), $now));
}
