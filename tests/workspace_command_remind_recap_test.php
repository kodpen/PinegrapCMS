<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for /remind (/hatırlat) and /recap (/özet): what /remind reads, the
 * plan it hands to ws_personal_plan_save() with the form's reminder, the
 * New plan form opened filled without a time, the answered message's link
 * in the note, its live line; /recap offered only where Pinegrap AI may sum
 * up the channel, and both answers for the writer alone
 * (includes/workspace/commands.php, command_registry.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/commands.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/personal_plans.php');

function workspace_command_remind_test_now()
{
	// Tuesday 17 November 2026, 08:00.
	return strtotime('2026-11-17 08:00:00');
}

function workspace_command_remind_test_channel($kind = 'public')
{
	return array('id' => 7, 'kind' => $kind, 'name' => 'general', 'archived_at' => 0);
}

// Both commands have their entry, names in Turkish and English, and their
// group.
function test_workspace_command_remind_recap_entries()
{
	$registry = ws_command_registry();

	pg_assert_same('plan', $registry['remind']['group'], '/remind is a plan command');
	pg_assert_same('channel', $registry['recap']['group'], '/recap is the channel\'s');
	pg_assert_same('remind', ws_command_table()['hatırlat'], '/hatırlat');
	pg_assert_same('remind', ws_command_table()['hatirlat'], '/hatirlat');
	pg_assert_same('recap', ws_command_table()['özet'], '/özet');
	pg_assert_same('recap', ws_command_table()['ozet'], '/ozet');
	pg_assert_same('ws_command_live_remind', $registry['remind']['live'], '/remind has a live line');
}

// "/hatırlat yarın 10:00 Teklifi gönder": the day, the time and the title.
function test_workspace_command_remind_reads_day_time_and_title()
{
	$now = workspace_command_remind_test_now();

	pg_assert_same(array('date' => '2026-11-18', 'time' => '10:00', 'title' => 'Teklifi gönder'), ws_command_remind_data('yarın 10:00 Teklifi gönder', $now));
	pg_assert_same(array('date' => '2026-11-18', 'time' => '10:00', 'title' => 'Teklifi gönder'), ws_command_remind_data('10:00 yarın Teklifi gönder', $now), 'either order');
	pg_assert_same(array('date' => '2026-11-20', 'time' => '14:30', 'title' => 'Call Selin'), ws_command_remind_data('friday at 14:30 Call Selin', $now), 'at before the time');
	pg_assert_same(array('date' => '2026-11-17', 'time' => '16:00', 'title' => 'Ara'), ws_command_remind_data('16:00 Ara', $now), 'a time alone is today\'s');
	pg_assert_same(array('date' => '2026-11-18', 'time' => '', 'title' => 'Teklifi gönder'), ws_command_remind_data('yarın Teklifi gönder', $now), 'a day without a time');
	pg_assert_same(array('date' => '', 'time' => '', 'title' => 'Teklifi gönder'), ws_command_remind_data('Teklifi gönder', $now), 'no day, no time');
	pg_assert_same("Line one\nline two", ws_command_remind_data("yarın 10:00 Line one\nline two", $now)['title'], 'the title as written');
}

// The plan /remind saves: its day, time, title and the form's reminder at
// the start, which the form's own cleaning reads as a reminder at 10:00.
function test_workspace_command_remind_plan_has_the_forms_reminder()
{
	$now = workspace_command_remind_test_now();
	$fill = ws_command_remind_plan(ws_command_remind_data('yarın 10:00 Teklifi gönder', $now), 'Teklifi gönder', '', $now);

	pg_assert_same('2026-11-18', $fill['start'], 'the day');
	pg_assert_same('2026-11-18', $fill['end']);
	pg_assert_same('10:00', $fill['start_time'], 'the time');
	pg_assert_same('Teklifi gönder', $fill['title'], 'the title');
	pg_assert_same(0, $fill['all_day']);

	$options = ws_personal_plan_js_strings()['remind_options'];

	pg_assert_same('0', $fill['remind'], 'a reminder at the start');
	pg_assert_same(lang('At the start'), $options[$fill['remind']], 'one of the form\'s reminders');

	$clean = ws_personal_plan_clean($fill);

	pg_assert_true(!isset($clean['error']), 'the form\'s cleaning takes it');
	pg_assert_same(strtotime('2026-11-18 10:00:00'), $clean['starts_at'], 'starts at 10:00');
	pg_assert_same(strtotime('2026-11-18 11:00:00'), $clean['ends_at'], 'an hour, as the form');
	pg_assert_same($clean['starts_at'], $clean['remind_at'], 'reminds at its start');
}

// Without a time the New plan form opens with what was typed; nothing is
// written.
function test_workspace_command_remind_without_a_time_opens_the_form()
{
	$answer = ws_command_run_remind(array('id' => 3, 'member' => true), workspace_command_remind_test_channel(), 'yarın Teklifi gönder', array());

	pg_assert_same('plan_form', $answer['type'], 'the form opens');
	pg_assert_same('Teklifi gönder', $answer['fill']['title'], 'with the title');
	pg_assert_same(date('Y-m-d', strtotime('+1 day', strtotime(date('Y-m-d') . ' 12:00:00'))), $answer['fill']['start'], 'on the day');
	pg_assert_same('', $answer['fill']['start_time'], 'no time');
	pg_assert_same('0', $answer['fill']['remind'], 'with the reminder chosen');
	pg_assert_same(lang('New plan'), $answer['strings']['new_plan'], 'the form\'s texts go along');
	pg_assert_true(in_array('plan_form', ws_api_command_preview_types(), true), 'the API shows it back');
	pg_assert_same('preview', ws_api_command_kind('/hatırlat yarın Teklifi gönder', array()), 'a dry run can show it');
	pg_assert_same('run', ws_api_command_kind('/hatırlat yarın 10:00 Teklifi gönder', array()), 'with a time it writes');
}

// Typed as a reply, the note carries the answered message's link.
function test_workspace_command_remind_reply_note_holds_the_link()
{
	$link = ws_command_message_link(workspace_command_remind_test_channel(), array('id' => 512));

	pg_assert_true(substr($link, -strlen('/workspace.php?channel=7&message=512')) === '/workspace.php?channel=7&message=512', 'the message\'s address: ' . $link);

	$fill = ws_command_remind_plan(ws_command_remind_data('yarın 10:00 Bak', workspace_command_remind_test_now()), 'Bak', $link, workspace_command_remind_test_now());

	pg_assert_same($link, $fill['note'], 'in the plan\'s note');
	pg_assert_same($link, ws_personal_plan_clean($fill)['note'], 'kept by the form\'s cleaning');
}

// The live line says what will be reminded and when.
function test_workspace_command_remind_live_line()
{
	$now = workspace_command_remind_test_now();
	$channel = workspace_command_remind_test_channel();
	$live = ws_command_live_remind(null, $channel, 'yarın 10:00 Teklifi gönder', array(), $now);

	pg_assert_true(strpos($live['line'], '“Teklifi gönder”') !== false, 'the title: ' . $live['line']);
	pg_assert_true(strpos($live['line'], '10:00') !== false, 'the time');
	pg_assert_same(0, strpos($live['line'], lang(array('string' => 'Will remind you: {var:1}', 'vars' => ''))), 'what Enter does');
	pg_assert_same(array(), $live['warnings']);

	$form = ws_command_live_remind(null, $channel, 'yarın Teklifi gönder', array(), $now);

	pg_assert_same(0, strpos($form['line'], lang(array('string' => 'Will open New plan: {var:1}', 'vars' => ''))), 'no time: the form');

	$empty = ws_command_live_remind(null, $channel, 'yarın 10:00', array(), $now);

	pg_assert_same(array(lang('Write what to remind after the time.')), $empty['warnings'], 'no title');

	$gone = ws_command_live_remind(null, $channel, '07:00 Ara', array(), $now);

	pg_assert_same(array(lang('That time has already gone by.')), $gone['warnings'], 'a time gone by');
	pg_assert_same(array('line' => '', 'warnings' => array()), ws_command_live_remind(null, $channel, '', array(), $now), 'nothing typed');
}

// /recap is not offered where the catch-up line has no "Sum up what I
// missed" (no Pinegrap AI here), and is refused there as the button is.
function test_workspace_command_recap_not_offered_where_the_button_is_not()
{
	$channel = workspace_command_remind_test_channel();

	pg_assert_true(!ws_command_offered(null, 'recap', $channel), 'not offered without Pinegrap AI');
	pg_assert_true(ws_command_offered(null, 'recap'), 'listed without a channel');
	pg_assert_true(!in_array('recap', array_column(ws_commands(null, false, $channel), 'key'), true), 'left out of the channel\'s list');
	pg_assert_true(strpos(ws_command_run(null, $channel, '/help')['html'], '/recap') === false, 'left out of the channel\'s /help');

	$refused = ws_command_run(array('id' => 3), $channel, '/özet');

	pg_assert_same('error', $refused['type'], 'refused');
	pg_assert_same(lang('Pinegrap AI cannot be asked in this channel.'), $refused['error'], 'with the button\'s refusal');
}

// Both answers are the writer's alone.
function test_workspace_command_remind_recap_answers_are_ephemeral()
{
	$now = workspace_command_remind_test_now();
	$fill = ws_command_remind_plan(ws_command_remind_data('yarın 10:00 Teklifi gönder', $now), 'Teklifi gönder', '', $now);
	$answer = ws_command_remind_answer($fill, 41);

	pg_assert_same('ephemeral', $answer['type'], '/remind to the writer alone');
	pg_assert_true(strpos($answer['html'], 'workspace_my_calendar.php?plan=41') !== false, 'opens the plan');
	pg_assert_true(strpos($answer['html'], '“Teklifi gönder”') !== false, 'says what');

	$html = ws_command_recap_html("Two <b>things</b>\nhappened.");

	pg_assert_true(strpos($html, h(lang('Summed up by Pinegrap AI'))) !== false, 'says who summed it up');
	pg_assert_true(strpos($html, '&lt;b&gt;') !== false, 'the summary escaped');
	pg_assert_same('ws_command_run_recap', ws_command_registry()['recap']['runner']);
}

// The composer's "/" list leaves /recap out where the catch-up line has no
// "Sum up what I missed", and keeps it where it has one.
function test_workspace_command_recap_left_out_of_the_list_where_the_button_is_not()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js'));
	$functions = '';

	foreach (array('foldText', 'commandPickerRows') as $name) {
		pg_assert_true((bool) preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', $source, $match), $name . '() is in workspace.js');
		$functions .= $match[0] ?? '';
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if (!function_exists('exec') || ($code !== 0)) {
		return;
	}

	$file = (string) @tempnam(sys_get_temp_dir(), 'pgrecapjs');
	file_put_contents($file, $functions . '
var COMMANDS = [
	{ key: "poll", command: "Poll", name: "/poll", names: ["/poll", "/oylama"], where: "any" },
	{ key: "recap", command: "Recap", name: "/recap", names: ["/recap", "/özet"], where: "any" }
];
function names(rows) { return rows.map(function (row) { return row.label; }); }
console.log(JSON.stringify({
	button: names(commandPickerRows(COMMANDS, "", false, true)),
	none: names(commandPickerRows(COMMANDS, "", false, false)),
	typed: names(commandPickerRows(COMMANDS, "öz", false, false)),
	untold: names(commandPickerRows(COMMANDS, "", false))
}));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$result = json_decode(implode("\n", $out), true);

	pg_assert_same(array('/poll', '/recap'), $result['button'] ?? null, 'offered where the button is');
	pg_assert_same(array('/poll'), $result['none'] ?? null, 'left out where it is not');
	pg_assert_same(array(), $result['typed'] ?? null, 'not found by its Turkish name there either');
	pg_assert_same(array('/poll', '/recap'), $result['untold'] ?? null, 'a caller that does not say keeps it');
}
