<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a person's own status (includes/fn/person_status.php): the
 * kinds, the input check, the decode that drops an ended status, each kind's
 * default end, the words of an end in the person's zone, the save being the
 * person's own, and the API routes and panel action that write it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/person_status.php');

// A local moment in a zone, as a timestamp.
function person_status_test_at($local, $zone)
{
	$date = new DateTime($local, new DateTimeZone($zone));

	return $date->getTimestamp();
}

function test_person_status_kinds_in_their_order()
{
	pg_assert_same(array('available', 'busy', 'meeting', 'out', 'sleeping', 'leave'), array_keys(pg_person_status_kinds()));
}

function test_person_status_each_kind_offers_its_end()
{
	pg_assert_same('none', pg_person_status_default_end('available'));
	pg_assert_same('1h', pg_person_status_default_end('busy'));
	pg_assert_same('1h', pg_person_status_default_end('meeting'));
	pg_assert_same('4h', pg_person_status_default_end('out'));
	pg_assert_same('morning', pg_person_status_default_end('sleeping'));
	pg_assert_same('today', pg_person_status_default_end('leave'));
	pg_assert_same('none', pg_person_status_default_end('nonsense'));
}

function test_person_status_end_times_in_the_persons_zone()
{
	$zone = 'Europe/Istanbul';
	$now = person_status_test_at('2026-11-28 14:10', $zone);

	pg_assert_same(0, pg_person_status_end_time('none', $now, $zone));
	pg_assert_same($now + 1800, pg_person_status_end_time('30m', $now, $zone));
	pg_assert_same($now + 3600, pg_person_status_end_time('1h', $now, $zone));
	pg_assert_same($now + 14400, pg_person_status_end_time('4h', $now, $zone));
	pg_assert_same(person_status_test_at('2026-11-29 00:00', $zone), pg_person_status_end_time('today', $now, $zone), 'today ends at the coming midnight');
	pg_assert_same(person_status_test_at('2026-11-29 08:00', $zone), pg_person_status_end_time('morning', $now, $zone), 'without quiet hours the morning is 08:00');
	pg_assert_same(person_status_test_at('2026-11-29 07:30', $zone), pg_person_status_end_time('morning', $now, $zone, '07:30'), 'the end of the quiet hours');
	pg_assert_same(person_status_test_at('2026-11-28 07:30', $zone), pg_person_status_end_time('morning', person_status_test_at('2026-11-28 02:00', $zone), $zone, '07:30'), 'after midnight the same morning');
	pg_assert_same(-1, pg_person_status_end_time('at', $now, $zone));
}

function test_person_status_decode_drops_an_ended_status()
{
	$now = 1764331200;
	$value = json_encode(array('kind' => 'out', 'note' => 'At the bank', 'until' => $now + 60, 'at' => $now - 600));

	pg_assert_same(array('kind' => 'out', 'note' => 'At the bank', 'until' => $now + 60, 'at' => $now - 600), pg_person_status_decode($value, $now));
	pg_assert_same(null, pg_person_status_decode($value, $now + 60), 'the end itself is past');
	pg_assert_same(null, pg_person_status_decode($value, $now + 3600));
	pg_assert_same('busy', pg_person_status_decode('{"kind":"busy","until":0}', $now + 999999)['kind'], 'no end stays');
}

function test_person_status_a_value_that_is_no_status()
{
	foreach (array(null, '', 'nonsense', '[]', '{"kind":"dancing"}', '{"note":"x"}', '{"kind":1}') as $value) {
		pg_assert_same(null, pg_person_status_decode($value, 1764331200), var_export($value, true));
	}
}

function test_person_status_input_takes_the_kinds_default_end()
{
	$zone = 'UTC';
	$now = person_status_test_at('2026-11-28 10:00', $zone);
	$input = pg_person_status_input(array('kind' => 'busy', 'note' => ' On a call '), $now, $zone);

	pg_assert_true($input['ok']);
	pg_assert_same(array('kind' => 'busy', 'note' => 'On a call', 'until' => $now + 3600, 'at' => $now), $input['value']);
	pg_assert_same(0, pg_person_status_input(array('kind' => 'busy', 'end' => 'none'), $now, $zone)['value']['until'], 'none keeps it');
	pg_assert_same(0, pg_person_status_input(array('kind' => 'available'), $now, $zone)['value']['until']);
	pg_assert_same(person_status_test_at('2026-11-29 06:00', $zone), pg_person_status_input(array('kind' => 'sleeping'), $now, $zone, '06:00')['value']['until']);
}

function test_person_status_input_clears_with_no_kind()
{
	pg_assert_same(array('ok' => true, 'value' => null, 'error' => '', 'field' => ''), pg_person_status_input(array('kind' => '', 'note' => 'x'), 1764331200, 'UTC'));
	pg_assert_same(null, pg_person_status_input(array(), 1764331200, 'UTC')['value']);
}

function test_person_status_input_keeps_the_note_to_one_line()
{
	$input = pg_person_status_input(array('kind' => 'out', 'note' => "Back soon\nask\tAyşe", 'end' => 'none'), 1764331200, 'UTC');

	pg_assert_same('Back soon ask Ayşe', $input['value']['note']);
	pg_assert_true(pg_person_status_input(array('kind' => 'out', 'note' => str_repeat('ş', 80)), 1764331200, 'UTC')['ok'], '80 characters, not bytes');

	$long = pg_person_status_input(array('kind' => 'out', 'note' => str_repeat('a', 81)), 1764331200, 'UTC');

	pg_assert_false($long['ok']);
	pg_assert_same('note', $long['field']);
}

function test_person_status_input_refuses_a_bad_field()
{
	$now = person_status_test_at('2026-11-28 10:00', 'UTC');

	pg_assert_same('kind', pg_person_status_input(array('kind' => 'dancing'), $now, 'UTC')['field']);
	pg_assert_same('end', pg_person_status_input(array('kind' => 'out', 'end' => '2h'), $now, 'UTC')['field']);
	pg_assert_same('until', pg_person_status_input(array('kind' => 'out', 'end' => 'at'), $now, 'UTC')['field'], 'at needs until');
	pg_assert_same('until', pg_person_status_input(array('kind' => 'out', 'end' => 'at', 'until' => $now - 1), $now, 'UTC')['field'], 'a past time');
	pg_assert_same('until', pg_person_status_input(array('kind' => 'out', 'end' => 'at', 'until' => $now + 400 * 86400), $now, 'UTC')['field'], 'beyond a year');
}

function test_person_status_input_reads_a_chosen_time()
{
	$zone = 'Europe/Istanbul';
	$now = person_status_test_at('2026-11-28 10:00', $zone);

	pg_assert_same(person_status_test_at('2026-11-28 18:00', $zone), pg_person_status_input(array('kind' => 'out', 'end' => 'at', 'until' => '2026-11-28 18:00'), $now, $zone)['value']['until'], 'without an offset in the person\'s zone');
	pg_assert_same(person_status_test_at('2026-11-28 18:00', 'UTC'), pg_person_status_input(array('kind' => 'out', 'end' => 'at', 'until' => '2026-11-28T18:00:00Z'), $now, $zone)['value']['until'], 'an offset is kept');
	pg_assert_same($now + 600, pg_person_status_input(array('kind' => 'out', 'end' => 'at', 'until' => (string) ($now + 600)), $now, $zone)['value']['until'], 'a Unix time');
}

function test_person_status_until_words_in_the_persons_zone()
{
	$zone = 'Europe/Istanbul';
	$now = person_status_test_at('2026-11-28 10:00', $zone);

	pg_assert_same('', pg_person_status_until_words(0, $now, $zone));
	pg_assert_same('until 18:00 today', pg_person_status_until_words(person_status_test_at('2026-11-28 18:00', $zone), $now, $zone));
	pg_assert_same('until 18:00 today', pg_person_status_until_words(person_status_test_at('2026-11-28 15:00', 'UTC'), $now, $zone), 'told on the person\'s clock');
	pg_assert_same('until the end of today', pg_person_status_until_words(person_status_test_at('2026-11-29 00:00', $zone), $now, $zone));
	pg_assert_same('until tomorrow 08:00', pg_person_status_until_words(person_status_test_at('2026-11-29 08:00', $zone), $now, $zone));
	pg_assert_same('until Wednesday 09:30', pg_person_status_until_words(person_status_test_at('2026-12-02 09:30', $zone), $now, $zone));
	pg_assert_same('until 2026-12-24 18:00', pg_person_status_until_words(person_status_test_at('2026-12-24 18:00', $zone), $now, $zone));
}

function test_person_status_present_carries_label_and_words()
{
	$now = person_status_test_at('2026-11-28 10:00', 'UTC');
	$shown = pg_person_status_present(array('kind' => 'meeting', 'note' => '', 'until' => $now + 3600, 'at' => $now), $now, 'UTC');

	pg_assert_same(array('kind' => 'meeting', 'label' => 'In a meeting', 'note' => '', 'until' => $now + 3600, 'until_words' => 'until 11:00 today', 'at' => $now), $shown);
	pg_assert_same(null, pg_person_status_present(null, $now, 'UTC'));
}

function test_person_status_save_check_is_the_persons_own()
{
	$now = 1764331200;
	$member = array('id' => 7, 'member' => true);

	pg_assert_true(pg_person_status_save_check($member, array('kind' => 'out'), $now, 'UTC')['ok']);
	pg_assert_same(7, pg_person_status_save_check($member, array('kind' => 'out', 'user_id' => 7), $now, 'UTC')['user_id']);

	$other = pg_person_status_save_check($member, array('kind' => 'out', 'user_id' => 8), $now, 'UTC');

	pg_assert_false($other['ok'], 'never for somebody else');
	pg_assert_same('', $other['field']);
	pg_assert_false(pg_person_status_save_check(array('id' => 7, 'member' => false), array('kind' => 'out'), $now, 'UTC')['ok'], 'not a team member');
	pg_assert_false(pg_person_status_save_check(array(), array('kind' => 'out'), $now, 'UTC')['ok']);
}

function test_person_status_routes_are_the_persons_own()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api_person_status.php');
	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

	pg_assert_true(strpos($api, 'ws_person_status_api_routes()') !== false, 'merged into the routes');

	foreach (array('ws_api_person_status_read', 'ws_api_person_status_set') as $handler) {
		pg_assert_true(preg_match('/function ' . $handler . '\(\$params\)\s*\{\s*ws_api_person_only\(/', $source) === 1, $handler . ' refuses a key first');
	}

	// An MCP tool for the person's own connection: no 'mcp' => false.
	pg_assert_true(strpos($source, "'mcp'") === false, 'both routes are MCP tools');
	pg_assert_true(strpos($source, "'dry_run'     => true") !== false, 'the write can be rehearsed');

	// Check, then the dry run, then the write.
	$check = strpos($source, 'ws_person_status_save_check($viewer');
	$stop = strpos($source, 'api_dry_run_stop(');
	$write = strpos($source, 'ws_person_status_save($viewer');

	pg_assert_true(($check !== false) && ($stop > $check) && ($write > $stop), 'check, dry run, write');
}

function test_person_status_writers_are_the_panel_action_and_the_route()
{
	$writers = array();

	foreach (array('includes/workspace/actions.php', 'includes/workspace/api_person_status.php', 'includes/workspace/people.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		if (preg_match_all('/(?<!function )ws_person_status_save\(\$viewer/', $source) > 0) {
			$writers[] = $file;
		}
	}

	pg_assert_same(array('includes/workspace/actions.php', 'includes/workspace/api_person_status.php'), $writers);

	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');

	pg_assert_true(strpos($panel, "'ws_person_status_save'") !== false, 'the panel action is allowed');
}

function test_person_status_people_query_waits_for_the_column()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/people.php');

	pg_assert_true(strpos($source, '(ws_person_status_ready() ? ",' . "\n" . '            p.status AS status" : "")') !== false, 'p.status is selected only once the column exists');
	pg_assert_true(strpos($source, "'status'   => ws_person_status_brief(\$row)") !== false, 'the brief carries status');
}
