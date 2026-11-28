<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the screens of a person's own status
 * (includes/workspace/person_status_screen.php, assets/js/workspace.js): the
 * look of each kind, the script's settings and texts, the account menu's
 * row, and under Node the pure parts of the script: whether a status holds,
 * whether it is drawn beside a name, its words and the composer's lines.
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

function person_status_screens_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
}

// One "function name(" of workspace.js up to the closing brace at its own
// indent, or ''.
function person_status_screens_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', person_status_screens_test_source(), $match) ? $match[0] : '';
}

// Every kind has an icon and a tone of the look, offered in the rule's
// order, each with the end the rule gives it.
function test_person_status_screens_kinds()
{
	$kinds = ws_person_status_kinds_js();
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_same(array_keys(pg_person_status_kinds()), array_column($kinds, 'kind'), 'every kind, in its order');
	pg_assert_same(array_keys(pg_person_status_kinds()), array_keys(ws_person_status_looks()), 'a look for every kind');

	foreach ($kinds as $kind) {
		pg_assert_true(strpos($kind['icon'], 'bi-') === 0, $kind['kind'] . ' has an icon');
		pg_assert_contains('.pg-tone-' . $kind['tone'] . ' {', $css, $kind['kind'] . ' has a tone of the look');
		pg_assert_same(pg_person_status_default_end($kind['kind']), $kind['end'], $kind['kind'] . ' offers its own end');
	}
}

// "Clear after" offers every end the rule reads, so a kind's default is
// always one of its options.
function test_person_status_screens_ends()
{
	$values = array_column(ws_person_status_end_options(), 0);

	sort($values);
	$ends = pg_person_status_ends();
	sort($ends);
	pg_assert_same($ends, $values, 'one option for each end');
	pg_assert_same('at', ws_person_status_end_options()[6][0], 'Choose a time… comes last');
}

// Nothing for anyone who is not a team member.
function test_person_status_screens_config_for_members_only()
{
	pg_assert_same(null, ws_person_status_js_config(array('id' => 4, 'member' => false)), 'a guest gets nothing');
	pg_assert_same(null, ws_person_status_js_config(array()), 'nobody gets nothing');
}

// The account menu's row: "Set a status" with none, the status with one,
// a link to the channel screen that opens the dialog.
function test_person_status_screens_menu_row()
{
	$none = ws_person_status_menu_markup(null, '/pg/workspace.php?panel=status');

	pg_assert_contains('href="/pg/workspace.php?panel=status"', $none, 'leads to the dialog');
	pg_assert_contains('data-pg-person-status', $none, 'opened in place where the script runs');
	pg_assert_contains(h(lang('Set a status')), $none, 'with none');

	$set = ws_person_status_menu_markup(array('kind' => 'out', 'label' => 'Out of office', 'until_words' => 'until 18:00 today', 'note' => '<b>x</b>'), '/w', ' target="_parent"');

	pg_assert_contains('bi-door-open', $set, 'the kind\'s icon');
	pg_assert_contains('pg-tone-amber', $set, 'in its tone');
	pg_assert_contains('Out of office until 18:00 today', $set, 'the label and its end');
	pg_assert_true(strpos($set, '<b>') === false, 'the note is not in the menu');
	pg_assert_contains(' target="_parent"', $set, 'the row\'s own attributes');
	pg_assert_contains(h(lang('Set a status')), ws_person_status_menu_markup(array('kind' => 'gone'), '/w'), 'an unknown kind reads as none');
	pg_assert_same('', ws_person_status_menu_row(null, 1, '/pg'), 'nothing outside the team');
	pg_assert_contains('ws_person_status_menu_row(', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/output.php'), 'the account menu draws it');
}

function test_person_status_screens_texts()
{
	$texts = ws_person_status_js_strings();

	foreach (array('ps_title', 'ps_kinds', 'ps_note', 'ps_note_hint', 'ps_clear_after', 'ps_at', 'ps_clear', 'ps_cancel', 'ps_save', 'ps_set', 'ps_change', 'ps_saved', 'ps_cleared', 'ps_more') as $key) {
		pg_assert_true(isset($texts[$key]), $key . ' is given');
	}

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_contains('ws_person_status_js_strings()', $screen, 'the screen gives them');
	pg_assert_contains("'person_status' => ws_person_status_js_config(\$viewer)", $screen, 'and the settings');
}

// The script's wiring: the mark beside a message's sender, in Channel info,
// in a discussion's people and in the @ list; the card's line; the
// composer's lines; the dialog's save through the person's own action.
function test_person_status_screens_wiring()
{
	$source = person_status_screens_test_source();
	$dialog = person_status_screens_test_function('personStatusDialog');
	$mark = person_status_screens_test_function('personStatusMark');

	pg_assert_true($dialog !== '' && $mark !== '', 'the dialog and the mark are in workspace.js');
	pg_assert_contains("api('ws_person_status_save', values)", $dialog, 'saved by the person\'s own action');
	pg_assert_contains("send({ kind: '' }, 'ps_cleared')", $dialog, 'Clear status sends no kind');
	pg_assert_contains("'pg-start pg-start-choice ws-person-status-kinds'", $dialog, 'the kinds are the choice cards');
	pg_assert_contains("radio.type = 'radio'", $dialog, 'a radio group, walked by the arrow keys');
	pg_assert_contains("pgDateField(el('input', 'pg-input'), 'datetime-local')", $dialog, 'the chosen time on the panel\'s calendar');
	pg_assert_contains("el('span', 'visually-hidden'", $mark, 'the icon is never the only teller');
	pg_assert_contains('mark.title = words', $mark, 'its words on hover');
	pg_assert_true(substr_count($source, 'personStatusMark(') >= 5, 'a message, Channel info, a discussion, the @ list');
	pg_assert_contains('personStatusCardLine(data)', person_status_screens_test_function('personCardBody'), 'the card\'s line');
	pg_assert_contains('self.drawStatusLines();', $source, 'the composer\'s lines follow the words');
	pg_assert_contains("query.panel === 'status'", $source, 'the account menu\'s row opens the dialog');
}

// The pure parts, run under Node.
function test_person_status_screens_pure_js()
{
	$code = '';

	foreach (array('personStatusActive', 'personStatusShown', 'personStatusWords', 'personStatusComposerLines') as $name) {
		$part = person_status_screens_test_function($name);
		pg_assert_true($part !== '', $name . '() is in workspace.js');
		$code .= $part;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgpstatus');
	file_put_contents($file, $code . '
var now = 1000000 * 1000;
var out = { label: "Out of office", kind: "out", until: 1000000 + 60, until_words: "until 18:00 today", note: "only following this channel" };
var ended = { label: "Busy", kind: "busy", until: 1000000 - 1 };
var free = { label: "Available to talk", kind: "available", until: 0, note: "" };
var freeNote = { label: "Available to talk", kind: "available", until: 0, note: "ask away" };
var sleeping = { label: "Sleeping", kind: "sleeping", until: 0, until_words: "", note: "" };
var people = [
	{ name: "A", status: out }, { name: "B", status: free }, { name: "C", status: sleeping },
	{ name: "D", status: ended }, { name: "E", status: out }, { name: "F", status: out }, null
];
console.log(JSON.stringify([
	personStatusActive(out, now), personStatusActive(ended, now), personStatusActive(null, now), personStatusActive(free, now),
	personStatusShown(free, now), personStatusShown(freeNote, now), personStatusShown(out, now),
	personStatusWords(out, false), personStatusWords(out, true), personStatusWords(sleeping, false), personStatusWords(null, false),
	personStatusComposerLines(people, now, function (n) { return "and " + n + " more"; }),
	personStatusComposerLines([{ name: "B", status: free }], now, function () { return "x"; })
]));');
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $status, $printed);
	pg_assert_same(array(
		true, false, false, true,
		false, true, true,
		'Out of office until 18:00 today: only following this channel',
		'Out of office until 18:00 today · “only following this channel”',
		'Sleeping',
		'',
		array('A: Out of office until 18:00 today: only following this channel', 'C: Sleeping', 'and 2 more'),
		array(),
	), json_decode($printed, true));
}
