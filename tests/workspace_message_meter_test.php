<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the length meter of the Workspace writing box
 * (assets/js/workspace.js). Run under Node: messageMeter() counts the text as
 * it is posted the way the server counts it (trimmed like trim(), code points
 * like mb_strlen()), shows nothing below METER_FROM of WS_MESSAGE_MAX, warns
 * past the limit and stops the send there unless a command reads the text
 * first. The rest holds the wiring: the limit comes from the screen's config,
 * the meter is drawn from the box and send() refuses with the server's words.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_message_meter_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One top-level function or "var NAME = ...;" line of workspace.js; '' when
// it is not found.
function workspace_message_meter_test_part($kind, $name)
{
	$source = workspace_message_meter_test_source();
	$start = strpos($source, "\n    " . $kind . ' ' . $name . (($kind === 'var') ? ' = ' : '('));

	if ($start === false) {
		return '';
	}

	if ($kind === 'var') {
		$end = strpos($source, "\n", (int) $start + 1);

		return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n";
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One method of the channel screen ("        name: function (" up to "        },").
function workspace_message_meter_test_method($name)
{
	$source = workspace_message_meter_test_source();
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

// Runs messageMeter() under Node on each [text, max, commands] and answers
// what it gave; null when Node is not installed.
function workspace_message_meter_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n";
	$var = workspace_message_meter_test_part('var', 'METER_FROM');
	$function = workspace_message_meter_test_part('function', 'messageMeter');
	pg_assert_true($var !== '', 'METER_FROM is found');
	pg_assert_true($function !== '', 'messageMeter is found');
	$script .= $var . $function
		. "\nvar cases = " . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) { return messageMeter(c[0], c[1], c[2]); })));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsmeter');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// Characters as mb_strlen() counts them: an emoji is one, a Turkish letter
// is one, the blanks trim() takes off are none.
function test_workspace_message_meter_counts_code_points()
{
	$texts = array('😀😀😀', 'çğıöşüİ', "  \n\tabc \r\n", "👩‍💻", 'a' . "\xC2\xA0");
	$cases = array();

	foreach ($texts as $text) {
		$cases[] = array($text, 4000, true);
	}

	$answer = workspace_message_meter_test_run($cases);

	if ($answer === null) {
		return;
	}

	foreach ($texts as $index => $text) {
		pg_assert_same(mb_strlen(trim($text)), $answer[$index]['count'], 'counted as the server counts ' . json_encode($text));
	}

	pg_assert_same(3, $answer[0]['count'], 'three emoji are three characters');
	pg_assert_same(7, $answer[1]['count'], 'seven Turkish letters are seven');
}

// Nothing below 90 %; the count from there; over past the limit, and only
// then the send is stopped.
function test_workspace_message_meter_thresholds()
{
	$answer = workspace_message_meter_test_run(array(
		array('', 4000, true),
		array(str_repeat('a', 3599), 4000, true),
		array(str_repeat('a', 3600), 4000, true),
		array(str_repeat('a', 4000), 4000, true),
		array(str_repeat('a', 4001), 4000, true),
		array(str_repeat('😀', 4001), 4000, true),
		array(str_repeat('a', 9), 10, true),
		array(str_repeat('a', 50), 0, true),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same('none', $answer[0]['state'], 'an empty box shows nothing');
	pg_assert_same('none', $answer[1]['state'], 'below 90 % shows nothing');
	pg_assert_same('near', $answer[2]['state'], 'from 90 % the count shows');
	pg_assert_same('near', $answer[3]['state'], 'the limit itself may be sent');
	pg_assert_false($answer[3]['block'], 'the limit itself is not stopped');
	pg_assert_same('over', $answer[4]['state'], 'one past the limit is over');
	pg_assert_true($answer[4]['block'], 'one past the limit is stopped');
	pg_assert_same(4001, $answer[5]['count'], 'emoji past the limit count once each');
	pg_assert_true($answer[5]['block'], 'emoji past the limit are stopped');
	pg_assert_same(array(4001, 4000), array($answer[4]['count'], $answer[4]['max']), 'count and limit answered');
	pg_assert_same('near', $answer[6]['state'], 'a small limit rounds 90 % up');
	pg_assert_same('none', $answer[7]['state'], 'no limit known shows nothing');
	pg_assert_false($answer[7]['block'], 'no limit known stops nothing');
}

// A command reads the text before the limit (ws_command_run() in ws_send),
// so the box warns but leaves the answer to the server; "//" posts the text
// and is a message. A changed message never runs a command.
function test_workspace_message_meter_commands()
{
	$long = str_repeat('a', 4001);
	$answer = workspace_message_meter_test_run(array(
		array('/task ' . $long, 4000, true),
		array('  /task ' . $long, 4000, true),
		array('//task ' . $long, 4000, true),
		array('/task ' . $long, 4000, false),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same('over', $answer[0]['state'], 'a long command still shows it is over');
	pg_assert_false($answer[0]['block'], 'a command is left to the server');
	pg_assert_false($answer[1]['block'], 'a command after blanks is left to the server');
	pg_assert_true($answer[2]['block'], '"//" is a message and is stopped');
	pg_assert_true($answer[3]['block'], 'a changed message is stopped');
}

// The wiring the browser runs.
function test_workspace_message_meter_wiring()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');
	pg_assert_true(strpos($screen, "'message_max'  => WS_MESSAGE_MAX") !== false, 'the limit is sent in the screen config');
	pg_assert_true(strpos($screen, "'meter_near'") !== false && strpos($screen, "'meter_over'") !== false && strpos($screen, "'msg_too_long'") !== false, 'the meter labels are sent');

	$composer = workspace_message_meter_test_method('composer');
	pg_assert_true(strpos($composer, 'ws-composer-meter') !== false, 'the composer draws the meter');
	pg_assert_true(strpos($composer, "setAttribute('aria-live', 'polite')") !== false, 'the meter has a polite live region');

	$draw = workspace_message_meter_test_method('drawMeter');
	pg_assert_true(strpos($draw, 'messageMeter(this.tokens(this.inputText()), CFG.message_max') !== false, 'the meter counts the text as posted against the config limit');
	pg_assert_true(strpos($draw, 'meter.state !== this.meterState') !== false, 'the live region speaks only when the state changes');

	$autosize = workspace_message_meter_test_method('autosize');
	pg_assert_true(strpos($autosize, 'this.drawMeter()') !== false, 'every change of the box redraws the meter');

	$refuses = workspace_message_meter_test_method('meterRefuses');
	pg_assert_true(strpos($refuses, "t('msg_too_long', CFG.message_max)") !== false, 'past the limit the box refuses with the server\'s words');
	pg_assert_true(strpos(workspace_message_meter_test_method('send'), 'self.meterRefuses()') !== false, 'send refuses past the limit');
	pg_assert_true(strpos(workspace_message_meter_test_method('scheduleSend'), 'self.meterRefuses()') !== false, 'a scheduled send refuses past the limit');
}
