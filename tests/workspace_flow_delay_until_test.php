<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the delay box's until mode (includes/workspace/flow_runs.php):
 * it waits until a date read from a value or written, less a time before
 * it; a date that has passed goes on at once; at most 90 days ahead; a value
 * that is no date fails the box with its reason.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/templates.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flows.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_values.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/flow_catalogue.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/scheduled.php');

function test_workspace_flow_delay_until_config()
{
	pg_assert_same(
		array('mode' => 'until', 'at' => '{{trigger.start}}', 'amount' => 2, 'unit' => 'hours'),
		ws_flow_config_normalize('delay', array('mode' => 'until', 'at' => " {{trigger.start}}\n", 'amount' => '2', 'unit' => 'hours'))
	);
	pg_assert_same('for', ws_flow_delay_normalize(array('mode' => 'later'))['mode'], 'an unknown mode waits for a time');
	pg_assert_same('', ws_flow_delay_normalize(array('at' => array('x')))['at'], 'at only a text');
}

// A written date in the site's zone, an ISO time with its zone, a Unix time.
function test_workspace_flow_delay_until_moment()
{
	pg_assert_same(gmmktime(9, 0, 0, 11, 20, 2026), ws_flow_delay_moment('2026-11-20T09:00:00Z'), 'ISO in UTC');
	pg_assert_same(gmmktime(6, 0, 0, 11, 20, 2026), ws_flow_delay_moment('2026-11-20T09:00:00+03:00'), 'ISO with an offset');
	pg_assert_same(gmmktime(14, 30, 0, 11, 20, 2026), ws_flow_delay_moment('2026-11-20T09:00-0530'), 'ISO with a negative offset');
	pg_assert_same(mktime(9, 0, 0, 11, 20, 2026), ws_flow_delay_moment('2026-11-20 09:00'), 'the site\'s zone');
	pg_assert_same(mktime(0, 0, 0, 11, 20, 2026), ws_flow_delay_moment('2026-11-20'), 'a day alone: its start');
	pg_assert_same(1795000000, ws_flow_delay_moment('1795000000'), 'a Unix time');
	pg_assert_same(0, ws_flow_delay_moment('next friday'), 'words are no date');
	pg_assert_same(0, ws_flow_delay_moment('2026-02-30 10:00'), 'no such day');
	pg_assert_same(0, ws_flow_delay_moment(''), 'nothing');
}

function test_workspace_flow_delay_until_date()
{
	$now = gmmktime(12, 0, 0, 11, 6, 2026);
	$until = function ($at, $amount = '', $unit = 'hours') use ($now) {
		return ws_flow_delay_until_date(ws_flow_delay_normalize(array('mode' => 'until', 'at' => $at, 'amount' => $amount, 'unit' => $unit)), $at, $now);
	};

	pg_assert_same(gmmktime(9, 0, 0, 11, 20, 2026), $until('2026-11-20T09:00:00Z')['until'], 'the date');
	pg_assert_same(gmmktime(7, 0, 0, 11, 20, 2026), $until('2026-11-20T09:00:00Z', 2)['until'], 'two hours before it');
	pg_assert_same($now, $until('2026-11-01T09:00:00Z')['until'], 'a date that has passed: at once');
	pg_assert_same($now, $until('2026-11-06T13:00:00Z', 1, 'days')['until'], 'its time before has passed: at once');
	pg_assert_same(0, $until('2027-03-01T09:00:00Z')['until'], 'more than 90 days ahead');
	pg_assert_true($until('2027-03-01T09:00:00Z')['error'] !== '', 'and why');
	pg_assert_same(gmmktime(9, 0, 0, 2, 3, 2027), $until('2027-02-03T09:00:00Z')['until'], 'within 90 days');
	pg_assert_same(0, $until('soon')['until'], 'no date');
	pg_assert_contains('soon', $until('soon')['error'], 'the value named');
}

function test_workspace_flow_delay_until_problems()
{
	$problems = function ($at, $amount = '') {
		return ws_flow_delay_problems(array('mode' => 'until', 'at' => $at, 'amount' => $amount, 'unit' => 'hours'), 'Wait');
	};

	pg_assert_same(1, count($problems('')), 'a date is needed');
	pg_assert_same(1, count($problems('tomorrow')), 'a written date must read');
	pg_assert_same(array(), $problems('2026-12-01 10:00'), 'a written date');
	pg_assert_same(array(), $problems('{{trigger.start}}'), 'a value is read at run time');
	pg_assert_same(array(), $problems('{{trigger.start}}', 0), 'nothing before it');
	pg_assert_same(1, count($problems('{{trigger.start}}', 2161)), 'more than 90 days before it');
}

// The graph check reads the date's value only in until mode.
function test_workspace_flow_delay_until_reads_its_value()
{
	$node = array('id' => 'wait', 'kind' => 'delay', 'config' => array('mode' => 'until', 'at' => '{{trigger.start}}', 'amount' => '', 'unit' => 'hours'));

	pg_assert_same(array('{{trigger.start}}'), ws_flow_node_texts($node), 'until reads at');
	$node['config']['mode'] = 'for';
	pg_assert_same(array(), ws_flow_node_texts($node), 'for reads nothing');

	pg_assert_same(array('for', 'until'), ws_flow_setting_values()['delay.mode'] ?? null, 'the catalogue names the modes');
}
