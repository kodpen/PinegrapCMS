<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's quiet hours (includes/fn/quiet_hours.php): a range
 * over midnight, the days the clock changes, a start equal to the end, the
 * days off, and a value that is no setting.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/quiet_hours.php');

// A local moment in a zone, as a timestamp.
function quiet_hours_test_at($local, $zone)
{
	$date = new DateTime($local, new DateTimeZone($zone));

	return $date->getTimestamp();
}

function test_quiet_hours_range_over_midnight()
{
	$setting = '{"start":"22:00","end":"07:00","off_days":0}';
	$zone = 'Europe/Istanbul';

	pg_assert_same(8 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-11 23:00', $zone)), 'Wednesday 23:00 waits until 07:00');
	pg_assert_same(1800, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 06:30', $zone)));
	pg_assert_same(0, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 07:00', $zone)), 'the end is no longer quiet');
	pg_assert_same(0, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 12:00', $zone)));
	pg_assert_same(9 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-11-12 22:00', $zone)), 'the start is quiet');
}

function test_quiet_hours_range_within_a_day()
{
	$setting = array('start' => '12:00', 'end' => '13:30');

	pg_assert_same(3600, pg_quiet_hours_left($setting, 'UTC', 31, quiet_hours_test_at('2026-11-12 12:30', 'UTC')));
	pg_assert_same(0, pg_quiet_hours_left($setting, 'UTC', 31, quiet_hours_test_at('2026-11-12 14:00', 'UTC')));
}

function test_quiet_hours_keep_their_clock_times_when_the_clock_changes()
{
	$setting = '{"start":"22:00","end":"07:00"}';
	$zone = 'Europe/Berlin';

	// 29 March 2026: the clocks go forward an hour, so the night is 7 hours.
	pg_assert_same(7 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-03-28 23:00', $zone)));
	// 25 October 2026: the clocks go back an hour, so the night is 9 hours.
	pg_assert_same(9 * 3600, pg_quiet_hours_left($setting, $zone, 31, quiet_hours_test_at('2026-10-24 23:00', $zone)));
}

function test_quiet_hours_equal_start_and_end_are_off()
{
	pg_assert_same(null, pg_quiet_hours_decode('{"start":"09:00","end":"09:00","off_days":1}'));
	pg_assert_same(0, pg_quiet_hours_left('{"start":"09:00","end":"09:00","off_days":1}', 'UTC', 31, quiet_hours_test_at('2026-11-14 09:00', 'UTC')), 'not even a day off is quiet');
}

function test_quiet_hours_days_off()
{
	$zone = 'Europe/Istanbul';
	$off = '{"start":"22:00","end":"07:00","off_days":1}';

	// 14 November 2026 is a Saturday; Monday to Friday are the working days.
	pg_assert_same(43 * 3600, pg_quiet_hours_left($off, $zone, 31, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'Saturday noon waits until Monday 07:00');
	pg_assert_same(56 * 3600, pg_quiet_hours_left($off, $zone, 31, quiet_hours_test_at('2026-11-13 23:00', $zone)), 'Friday night runs into the weekend');
	pg_assert_same(0, pg_quiet_hours_left('{"start":"22:00","end":"07:00","off_days":0}', $zone, 31, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'without off_days a Saturday noon is not quiet');
	pg_assert_same(0, pg_quiet_hours_left($off, $zone, 127, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'a person who works every day has no day off');
	pg_assert_same(0, pg_quiet_hours_left($off, $zone, 0, quiet_hours_test_at('2026-11-14 12:00', $zone)), 'no working day known: none is off');
}

function test_quiet_hours_a_value_that_is_no_setting()
{
	foreach (array(null, '', 'nope', '{"start":"25:00","end":"07:00"}', '{"start":"22:00"}', '{"start":"7:00","end":"08:00"}') as $value) {
		pg_assert_same(null, pg_quiet_hours_decode($value), var_export($value, true));
		pg_assert_same(0, pg_quiet_hours_left($value, 'UTC', 31, 1763200000), var_export($value, true));
	}

	pg_assert_same(array('start' => '22:00', 'end' => '07:00', 'off_days' => 1), pg_quiet_hours_decode('{"start":"22:00","end":"07:00","off_days":"1"}'));
	pg_assert_same(8 * 3600, pg_quiet_hours_left('{"start":"22:00","end":"07:00"}', 'Not/AZone', 31, quiet_hours_test_at('2026-11-11 23:00', 'UTC')), 'an unknown zone counts as UTC');
}

function test_quiet_hours_input_reads_the_two_times_and_the_switch()
{
	$input = pg_quiet_hours_input('22:00', '07:30', '1');

	pg_assert_true($input['ok']);
	pg_assert_same(array('start' => '22:00', 'end' => '07:30', 'off_days' => 1), $input['value']);
	pg_assert_same(0, pg_quiet_hours_input(' 12:00 ', '13:00', 0)['value']['off_days']);
	pg_assert_same('12:00', pg_quiet_hours_input(' 12:00 ', '13:00', 0)['value']['start'], 'spaces around a time are dropped');
}

function test_quiet_hours_input_clears_with_no_range()
{
	pg_assert_same(array('ok' => true, 'value' => null, 'error' => '', 'field' => ''), pg_quiet_hours_input('', '', 1), 'both empty clears');
	pg_assert_true(pg_quiet_hours_input('09:00', '09:00', 1)['ok'], 'equal times are accepted');
	pg_assert_same(null, pg_quiet_hours_input('09:00', '09:00', 1)['value'], 'equal times are no quiet range');
}

function test_quiet_hours_input_refuses_a_bad_time()
{
	foreach (array(array('24:00', '07:00', 'start'), array('7:00', '08:00', 'start'), array('22:00', '', 'end'), array('', '07:00', 'start'), array('22:00', '07:60', 'end'), array(array('22:00'), '07:00', 'start')) as $case) {
		$input = pg_quiet_hours_input($case[0], $case[1], 0);

		pg_assert_false($input['ok'], json_encode($case));
		pg_assert_same($case[2], $input['field'], json_encode($case));
		pg_assert_same(null, $input['value']);
	}
}

function test_quiet_hours_save_check_is_the_persons_own()
{
	$me = array('id' => 7, 'member' => true);
	$request = array('start' => '22:00', 'end' => '07:00', 'off_days' => 1);

	$check = pg_quiet_hours_save_check($me, $request);
	pg_assert_true($check['ok']);
	pg_assert_same(7, $check['user_id']);

	pg_assert_true(pg_quiet_hours_save_check($me, $request + array('user_id' => 7))['ok'], 'naming oneself is fine');

	$other = pg_quiet_hours_save_check($me, $request + array('user_id' => 8));
	pg_assert_false($other['ok'], "someone else's profile is refused");
	pg_assert_same(0, $other['user_id']);

	pg_assert_false(pg_quiet_hours_save_check(array('id' => 7, 'member' => false), $request)['ok'], 'a guest or site member is refused');
	pg_assert_false(pg_quiet_hours_save_check(array('id' => 0, 'member' => true), $request)['ok'], 'no signed-in person');

	$bad = pg_quiet_hours_save_check($me, array('start' => '25:00', 'end' => '07:00'));
	pg_assert_false($bad['ok'], 'a bad time is refused');
	pg_assert_same('start', $bad['field']);
	pg_assert_same(0, $bad['user_id']);
}

// The panel action and the team devices route are the only writers; the
// route is no MCP tool and refuses a key and an OAuth connection before it
// rehearses or writes.
function test_quiet_hours_save_is_the_panel_action_and_the_device_route_only()
{
	$actions = pg_panel_actions();

	pg_assert_true(isset($actions['ws_quiet_hours_save']), 'the panel action is there');

	$callers = array();

	foreach (glob(PG_FUNCTIONS_DIR . '/includes/{api,mcp,workspace}/*.php', GLOB_BRACE) as $file) {
		if (!in_array(basename($file), array('actions.php', 'notify.php'), true) && (strpos(file_get_contents($file), 'ws_quiet_hours_save(') !== false)) {
			$callers[] = basename($file);
		}
	}

	pg_assert_same(array('api_quiet_hours.php'), $callers, 'only the quiet hours route writes quiet hours over the API');

	quiet_hours_test_load_api();

	$route = null;

	foreach (ws_api_routes() as $row) {
		if ($row['id'] === 'workspace.quiet_hours.set') {
			$route = $row;
		}
	}

	pg_assert_true(is_array($route), 'the route is declared');
	pg_assert_same('POST', $route['method']);
	pg_assert_same('/workspace/quiet-hours', $route['path']);
	pg_assert_same('workspace:write', $route['scope']);
	pg_assert_false($route['mcp'], 'no MCP tool');
	pg_assert_true($route['dry_run'], 'takes X-Dry-Run');

	$reflection = new ReflectionFunction('ws_api_quiet_hours_set');
	$lines = file($reflection->getFileName());
	$source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

	$gate = strpos($source, 'ws_api_own_words_only(');
	$check = strpos($source, 'pg_quiet_hours_save_check(');
	$stop = strpos($source, 'api_dry_run_stop(');
	$save = strpos($source, 'ws_quiet_hours_save(');

	pg_assert_true(($gate !== false) && ($gate < $check), 'the team devices session is asked for first');
	pg_assert_true(($check !== false) && ($check < $stop), 'a dry run meets the same refusals');
	pg_assert_true(($stop !== false) && ($stop < $save), 'a dry run stops before the write');
}

function test_quiet_hours_api_present()
{
	quiet_hours_test_load_api();

	pg_assert_same(null, ws_api_quiet_hours_present(null), 'no range is null');
	pg_assert_same(array('start' => '22:00', 'end' => '07:00', 'off_days' => 1), ws_api_quiet_hours_present(array('start' => '22:00', 'end' => '07:00', 'off_days' => true)));
	pg_assert_same(array('start' => '13:00', 'end' => '14:00', 'off_days' => 0), ws_api_quiet_hours_present(pg_quiet_hours_decode('{"start":"13:00","end":"14:00","off_days":0}')));
}

// The external API's Workspace routes, loaded as the panel loads them.
function quiet_hours_test_load_api()
{
	if (!defined('PG_API_PANEL')) {
		define('PG_API_PANEL', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
}
