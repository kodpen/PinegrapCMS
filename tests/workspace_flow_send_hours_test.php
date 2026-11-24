<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the sending hours of a flow's sending boxes
 * (includes/workspace/flow_send_hours.php): the setting as kept on post,
 * email, sms and whatsapp, what it still lacks, the next moment the hours
 * are open in a time zone, its wait key, and, read from the source, that a
 * run waits or settles before the box begins and Try says when it sends.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

// Off at first, Monday to Friday, 09:00 to 20:00, waiting for the next
// hour that fits; days kept once and in order, clocks as HH:MM.
function test_workspace_flow_send_hours_normalize()
{
	$first = array('on' => false, 'days' => array(1, 2, 3, 4, 5), 'from' => '09:00', 'to' => '20:00', 'outside' => 'wait');

	pg_assert_same($first, ws_flow_hours_normalize(null));
	pg_assert_same($first, ws_flow_hours_normalize('on'));
	pg_assert_same(
		array('on' => true, 'days' => array(1, 6, 7), 'from' => '08:30', 'to' => '18:00', 'outside' => 'skip'),
		ws_flow_hours_normalize(array('on' => 1, 'days' => array(7, '6', 6, 1, 9, 0, true, 'x'), 'from' => '8:30', 'to' => '18:00', 'outside' => 'skip'))
	);
	pg_assert_same(array(), ws_flow_hours_normalize(array('on' => true, 'days' => array()))['days']);
	pg_assert_same('09:00', ws_flow_hours_normalize(array('from' => '25:00'))['from']);
	pg_assert_same('wait', ws_flow_hours_normalize(array('outside' => 'later'))['outside']);
}

// The four sending boxes keep the setting; other boxes do not.
function test_workspace_flow_send_hours_on_the_sending_boxes()
{
	pg_assert_same(array('post', 'email', 'sms', 'whatsapp'), ws_flow_hours_kinds());

	foreach (ws_flow_hours_kinds() as $kind) {
		$config = ws_step_config_normalize($kind, array('hours' => array('on' => true, 'days' => array(2))));

		pg_assert_same(true, $config['hours']['on'], $kind);
		pg_assert_same(array(2), $config['hours']['days'], $kind);
		pg_assert_same(false, ws_step_config_normalize($kind, array())['hours']['on'], $kind);
	}

	pg_assert_true(!array_key_exists('hours', ws_step_config_normalize('notify', array('hours' => array('on' => true)))));
}

// On with no day, or an end not after the start, keeps it from publishing.
function test_workspace_flow_send_hours_problems()
{
	pg_assert_same(array(), ws_flow_hours_problems(array(), 'Mail'));
	pg_assert_same(array(), ws_flow_hours_problems(array('hours' => array('on' => false, 'days' => array())), 'Mail'));
	pg_assert_same(array(), ws_flow_hours_problems(array('hours' => array('on' => true)), 'Mail'));
	pg_assert_same(1, count(ws_flow_hours_problems(array('hours' => array('on' => true, 'days' => array())), 'Mail')));
	pg_assert_same(1, count(ws_flow_hours_problems(array('hours' => array('on' => true, 'from' => '20:00', 'to' => '09:00')), 'Mail')));
	pg_assert_same(1, count(ws_flow_hours_problems(array('hours' => array('on' => true, 'from' => '10:00', 'to' => '10:00')), 'Mail')));
}

// The next open moment in the zone: now inside the hours, else the start
// of the next day's hours that is listed; off gives now; none gives 0.
function test_workspace_flow_send_hours_next()
{
	$zone = 'Europe/Istanbul';
	$at = function ($text) use ($zone) {
		return (new DateTime($text, new DateTimeZone($zone)))->getTimestamp();
	};
	$hours = array('on' => true, 'days' => array(1, 2, 3, 4, 5), 'from' => '09:00', 'to' => '20:00');

	// 2026-11-23 is a Monday.
	pg_assert_same($at('2026-11-23 10:15'), ws_flow_hours_next($hours, $at('2026-11-23 10:15'), $zone));
	pg_assert_same($at('2026-11-23 09:00'), ws_flow_hours_next($hours, $at('2026-11-23 09:00'), $zone));
	pg_assert_same($at('2026-11-23 09:00'), ws_flow_hours_next($hours, $at('2026-11-23 06:40'), $zone));
	pg_assert_same($at('2026-11-24 09:00'), ws_flow_hours_next($hours, $at('2026-11-23 20:00'), $zone));
	pg_assert_same($at('2026-11-30 09:00'), ws_flow_hours_next($hours, $at('2026-11-27 21:00'), $zone));
	pg_assert_same($at('2026-11-30 09:00'), ws_flow_hours_next($hours, $at('2026-11-28 12:00'), $zone));

	// One day a week: the same day next week once today's hours are gone.
	$sunday = array('on' => true, 'days' => array(7), 'from' => '11:00', 'to' => '12:00');
	pg_assert_same($at('2026-12-06 11:00'), ws_flow_hours_next($sunday, $at('2026-11-29 12:30'), $zone));

	// In UTC the same moment falls in other hours.
	pg_assert_same($at('2026-11-23 12:00'), ws_flow_hours_next(array('on' => true, 'from' => '09:00', 'to' => '20:00'), $at('2026-11-23 10:00'), 'UTC'));

	pg_assert_same(1000, ws_flow_hours_next(array('on' => false), 1000, $zone));
	pg_assert_same(0, ws_flow_hours_next(array('on' => true, 'days' => array()), 1000, $zone));
	pg_assert_same(0, ws_flow_hours_next(array('on' => true, 'from' => '20:00', 'to' => '09:00'), 1000, $zone));
}

// The key a box waiting for its hours keeps, read back; others give 0.
function test_workspace_flow_send_hours_key()
{
	pg_assert_same('hours:1800000000', ws_flow_hours_key(1800000000));
	pg_assert_same(1800000000, ws_flow_hours_key_until('hours:1800000000'));
	pg_assert_same(0, ws_flow_hours_key_until('delay:1800000000'));
	pg_assert_same(0, ws_flow_hours_key_until('hours:0'));
}

// Read from the source: the run looks at the hours before the box begins,
// a waiting box is made ready again when they open, the run view says what
// it waits for, and Try says when it would send.
function test_workspace_flow_send_hours_wired()
{
	$steps = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/steps.php');
	$runs = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flow_runs.php');
	$events = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/flow_run_events.php');

	$hold = strpos($steps, 'ws_flow_hours_hold(');
	$began = strpos($steps, "SET effect = '\" . e(WS_STEP_BEGAN . \$kind)");
	pg_assert_true(($hold !== false) && ($began !== false) && ($hold < $began), 'the hours are looked at before the box begins');
	pg_assert_true(strpos($steps, 'ws_flow_hours_try(') !== false, 'Try says when it would send');
	pg_assert_true(strpos($runs, 'ws_flow_hours_key_until($step[\'wait_key\'])') !== false, 'a waiting box is looked at again');
	pg_assert_true(strpos($events, "'hours'") !== false, 'the run event names the wait');
}
