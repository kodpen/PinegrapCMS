<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the unsent draft of the Workspace composer
 * (assets/js/workspace.js). Run under Node: draftKey() names the slot per
 * site, viewer, channel and discussion, draftFresh() reads a kept value and
 * refuses an expired or broken one, draftPrune() picks what goes so the
 * browser keeps at most DRAFT_KEEP drafts. The rest holds the wiring the
 * browser runs: saved while typing, restored into an empty box, cleared on
 * send, never from the box that changes a sent message.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_unsent_draft_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One top-level function of workspace.js, from its line to the closing brace
// at its own indent; '' when it is not found.
function workspace_unsent_draft_test_function($name)
{
	$source = workspace_unsent_draft_test_source();
	$start = strpos($source, "\n    function " . $name . '(');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n    }\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n    }\n";
}

// One method of the channel screen ("        name: function (" up to "        },").
function workspace_unsent_draft_test_method($name)
{
	$source = workspace_unsent_draft_test_source();
	$start = strpos($source, "\n        " . $name . ': function (');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", (int) $start);

	return (string) substr($source, (int) $start, (int) $end - (int) $start);
}

// One "var NAME = ...;" line of the file.
function workspace_unsent_draft_test_var($name)
{
	$source = workspace_unsent_draft_test_source();
	$start = strpos($source, "\n    var " . $name . ' = ');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n", (int) $start + 1);

	return (string) substr($source, (int) $start, (int) $end - (int) $start) . "\n";
}

// Runs the pure helpers under Node on the steps given and answers what each
// gave; null when Node is not installed.
function workspace_unsent_draft_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$script = "'use strict';\n";

	foreach (array('DRAFT_PREFIX', 'DRAFT_DAYS', 'DRAFT_KEEP') as $name) {
		$part = workspace_unsent_draft_test_var($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	foreach (array('draftKey', 'draftFresh', 'draftPrune') as $name) {
		$part = workspace_unsent_draft_test_function($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	$script .= "\nvar steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (step) {\n"
		. "  if (step[0] === 'key') { return draftKey(step[1], step[2], step[3], step[4]); }\n"
		. "  if (step[0] === 'fresh') { return draftFresh(step[1], step[2]); }\n"
		. "  if (step[0] === 'prune') { return draftPrune(step[1], step[2]); }\n"
		. "  if (step[0] === 'limits') { return [DRAFT_DAYS, DRAFT_KEEP]; }\n"
		. "  return null;\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwsdraft');
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

// One slot per site, viewer, channel and discussion: two people on one
// browser, two sites on one host, a channel and a discussion beside it never
// share one.
function test_workspace_unsent_draft_key_per_viewer_and_channel()
{
	$answer = workspace_unsent_draft_test_run(array(
		array('key', '/pg/api.php', 7, 12, 0),
		array('key', '/pg/api.php', 8, 12, 0),
		array('key', '/pg/api.php', 7, 13, 0),
		array('key', '/shop/api.php', 7, 12, 0),
		array('key', '/pg/api.php', 7, 12, 40),
		array('key', '/pg/api.php', 7, 12, 41),
		array('key', '/pg/api.php', '7', '12', null),
	));

	if ($answer === null) {
		return;
	}

	foreach ($answer as $key) {
		pg_assert_true(is_string($key) && strpos($key, 'ws-draft:') === 0, 'every slot starts with ws-draft: ' . json_encode($key));
	}

	$distinct = array_unique(array_slice($answer, 0, 6));
	pg_assert_same(6, count($distinct), 'viewer, channel, site and discussion each give their own slot');
	pg_assert_same($answer[0], $answer[6], 'ids as strings and no discussion name the same slot');
}

// A kept value is restored only while it is young and well formed.
function test_workspace_unsent_draft_expiry()
{
	$now = 1800000000000;
	$day = 86400000;
	$value = function ($at, $raw = 'hello') {
		return json_encode(array('raw' => $raw, 'labels' => array('<@user:3>' => 'Ayşe'), 'at' => $at));
	};
	$answer = workspace_unsent_draft_test_run(array(
		array('limits'),
		array('fresh', $value($now - $day), $now),
		array('fresh', $value($now - 13 * $day), $now),
		array('fresh', $value($now - 15 * $day), $now),
		array('fresh', $value($now, ''), $now),
		array('fresh', $value($now, '   '), $now),
		array('fresh', 'not json', $now),
		array('fresh', null, $now),
		array('fresh', json_encode(array('raw' => 'x')), $now),
		array('fresh', json_encode(array('raw' => 'x', 'at' => $now, 'labels' => 'bad')), $now),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(14, 50), $answer[0], 'kept 14 days, 50 drafts at most');
	pg_assert_same('hello', $answer[1]['raw'], 'a day old is restored');
	pg_assert_same(array('<@user:3>' => 'Ayşe'), $answer[1]['labels'], 'the chips\' labels come back');
	pg_assert_same('hello', $answer[2]['raw'], 'thirteen days old is restored');
	pg_assert_same(null, $answer[3], 'fifteen days old is dropped');
	pg_assert_same(null, $answer[4], 'an empty text is no draft');
	pg_assert_same(null, $answer[5], 'blanks are no draft');
	pg_assert_same(null, $answer[6], 'broken JSON is no draft');
	pg_assert_same(null, $answer[7], 'nothing kept is no draft');
	pg_assert_same(null, $answer[8], 'no time is no draft');
	pg_assert_same(array(), $answer[9]['labels'], 'labels that are not a map are left out');
}

// Expired drafts go, then the oldest beyond the cap.
function test_workspace_unsent_draft_prune_to_cap()
{
	$now = 1800000000000;
	$day = 86400000;
	$entries = array();

	for ($i = 0; $i < 55; $i++) {
		$entries[] = array('key' => 'ws-draft:k' . $i, 'at' => $now - $i * 60000);
	}

	$entries[] = array('key' => 'ws-draft:old', 'at' => $now - 20 * $day);
	$entries[] = array('key' => 'ws-draft:broken', 'at' => 0);

	$answer = workspace_unsent_draft_test_run(array(
		array('prune', $entries, $now),
		array('prune', array_slice($entries, 0, 3), $now),
		array('prune', array(), $now),
	));

	if ($answer === null) {
		return;
	}

	$dropped = $answer[0];
	sort($dropped);
	$expected = array('ws-draft:broken', 'ws-draft:k50', 'ws-draft:k51', 'ws-draft:k52', 'ws-draft:k53', 'ws-draft:k54', 'ws-draft:old');
	sort($expected);
	pg_assert_same($expected, $dropped, 'the expired, the broken and the five oldest beyond 50 go');
	pg_assert_same(array(), $answer[1], 'three young drafts all stay');
	pg_assert_same(array(), $answer[2], 'nothing kept, nothing to drop');
}

// The browser's side: storage only in try/catch, saved while typing,
// restored into an empty box, cleared on send, the edit box left alone.
function test_workspace_unsent_draft_wiring()
{
	$source = workspace_unsent_draft_test_source();

	foreach (array('draftRead', 'draftWrite') as $name) {
		$body = workspace_unsent_draft_test_function($name);
		pg_assert_true($body !== '', $name . ' is found');
		pg_assert_true(strpos($body, 'try {') !== false && strpos($body, 'catch (error)') !== false, $name . ' keeps storage in try/catch');
	}

	$composer = workspace_unsent_draft_test_method('composer');
	pg_assert_true(strpos($composer, 'self.keepDraft(') !== false, 'the composer saves the draft while typing');
	pg_assert_true(strpos($composer, 'self.restoreDraft(') !== false, 'the composer restores the draft');

	$keep = workspace_unsent_draft_test_method('keepDraft');
	pg_assert_true(strpos($keep, 'editing') !== false && strpos($keep, 'editingScheduled') !== false, 'changing a sent or scheduled message keeps no draft');
	pg_assert_true(strpos($keep, 'setTimeout') !== false, 'saving waits for a pause in typing');

	$restore = workspace_unsent_draft_test_method('restoreDraft');
	pg_assert_true(strpos($restore, 'inputEmpty()') !== false, 'only an empty box is filled');

	$clear = workspace_unsent_draft_test_method('clearInput');
	pg_assert_true(strpos($clear, 'dropDraft(') !== false, 'an emptied box after send drops the draft');

	pg_assert_true(strpos(workspace_unsent_draft_test_method('dropDraft'), 'editing') !== false, 'the edit box never drops the channel\'s draft');
	pg_assert_true(strpos(workspace_unsent_draft_test_method('draftSlot'), 'CFG.me') !== false, 'the slot names the viewer');
	pg_assert_same(1, substr_count($source, "\n    var DRAFT_PREFIX = "), 'one draft prefix');
}
