<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the card of a note in a channel and its windows
 * (assets/js/workspace_channel_notes.js): the fold's state and the key it
 * is kept under, the words of the card's foot, which menu items a reader
 * gets, and the hooks in workspace.js, the bundle list and the strings. The
 * Node parts are skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The source of a file under pinegrap/, '' when it is not there.
function workspace_channel_notes_js_test_source($name = 'assets/js/workspace_channel_notes.js')
{
	$file = PG_FUNCTIONS_DIR . '/' . $name;

	return is_file($file) ? str_replace("\r\n", "\n", (string) file_get_contents($file)) : '';
}

// One "function name(" of the card's script up to the closing brace at its
// own indent; '' when it is not there.
function workspace_channel_notes_js_test_function($name)
{
	return preg_match('/\n    function ' . $name . '\([^)]*\) \{\n.*?\n    \}\n/s', workspace_channel_notes_js_test_source(), $match) ? $match[0] : '';
}

// Runs a script with Node and answers what it printed as JSON; null without
// Node.
function workspace_channel_notes_js_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcnjs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

// A t() that names the key and its values, as the tests read the words.
function workspace_channel_notes_js_test_t()
{
	return 'function t(key) { var rest = Array.prototype.slice.call(arguments, 1); return rest.length ? key + "(" + rest.join(",") + ")" : key; }' . "\n";
}

function test_workspace_channel_notes_js_fold()
{
	$state = workspace_channel_notes_js_test_function('foldState');
	$key = workspace_channel_notes_js_test_function('foldKey');

	pg_assert_true($state !== '', 'foldState() is in the card\'s script');
	pg_assert_true($key !== '', 'foldKey() is in the card\'s script');

	$result = workspace_channel_notes_js_test_node($state . $key . '
console.log(JSON.stringify({
	fits: foldState(false, false),
	fitsOpened: foldState(true, false),
	folded: foldState(false, true),
	open: foldState(true, true),
	key: foldKey({ id: 12, channel_id: 3 }),
	keyOther: foldKey({ id: 12, channel_id: 4 }),
	keyNone: foldKey(null)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same('whole', $result['fits'], 'a note that fits is drawn whole, with no toggle');
	pg_assert_same('whole', $result['fitsOpened'], 'and stays whole when it was opened before it got shorter');
	pg_assert_same('folded', $result['folded'], 'a longer note is folded until opened');
	pg_assert_same('open', $result['open'], 'an opened card stays open');
	pg_assert_same('12:3', $result['key'], 'the fold is kept by note and channel');
	pg_assert_true($result['key'] !== $result['keyOther'], 'the same note in another channel keeps its own fold');
	pg_assert_same('0:0', $result['keyNone'], 'no card, an empty key');
}

function test_workspace_channel_notes_js_foot_words()
{
	$changed = workspace_channel_notes_js_test_function('changedWords');
	$foot = workspace_channel_notes_js_test_function('footWords');

	pg_assert_true($changed !== '' && $foot !== '', 'changedWords() and footWords() are in the card\'s script');

	$result = workspace_channel_notes_js_test_node(workspace_channel_notes_js_test_t() . $changed . $foot . '
console.log(JSON.stringify({
	both: footWords({ owner: { name: "Ayşe" }, updated_by: { name: "Mehmet" }, updated: "5 min" }, t),
	ownerOnly: footWords({ owner: { name: "Ayşe" }, updated_by: null }, t),
	none: footWords({}, t),
	nothing: footWords(null, t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('notes_owner(Ayşe)', 'cn_changed(Mehmet,5 min)'), $result['both'], 'the owner, then who changed it last and when');
	pg_assert_same(array('notes_owner(Ayşe)'), $result['ownerOnly'], 'nobody named as the changer, no change line');
	pg_assert_same(array(), $result['none'], 'nothing known, no words');
	pg_assert_same(array(), $result['nothing'], 'no card, no words');
}

function test_workspace_channel_notes_js_menu_items()
{
	$may = workspace_channel_notes_js_test_function('mayEdit');
	$items = workspace_channel_notes_js_test_function('menuItems');

	pg_assert_true($may !== '' && $items !== '', 'mayEdit() and menuItems() are in the card\'s script');

	$result = workspace_channel_notes_js_test_node($may . $items . '
console.log(JSON.stringify({
	owner: menuItems({ access: "owner", channel_id: 3 }),
	ownerGroup: menuItems({ access: "owner", channel_id: 3, via_group: true }),
	ownerNoChannel: menuItems({ access: "owner", channel_id: 0 }),
	editor: menuItems({ access: "edit", channel_id: 3 }),
	reader: menuItems({ access: "view", channel_id: 3 }),
	nobody: menuItems({ access: "", channel_id: 3 })
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('read', 'edit', 'versions', 'open', 'channel_edit'), $result['owner'], 'the owner gets every item and the channel\'s edit switch');
	pg_assert_same(array('read', 'edit', 'versions', 'open'), $result['ownerGroup'], 'a note group\'s share has no switch: it is the group\'s');
	pg_assert_same(array('read', 'edit', 'versions', 'open'), $result['ownerNoChannel'], 'no channel share, no switch');
	pg_assert_same(array('read', 'edit', 'versions', 'open'), $result['editor'], 'who may edit gets Edit, not the switch');
	pg_assert_same(array('read', 'versions', 'open'), $result['reader'], 'a reader reads, sees the versions and opens it on the notes screen');
	pg_assert_same(array('read', 'versions', 'open'), $result['nobody'], 'an unknown access is a reader\'s');
}

function test_workspace_channel_notes_js_hooks()
{
	$script = workspace_channel_notes_js_test_source();
	$workspace = workspace_channel_notes_js_test_source('assets/js/workspace.js');
	$output = workspace_channel_notes_js_test_source('includes/fn/output.php');
	$notes = workspace_channel_notes_js_test_source('includes/workspace/notes.php');
	$css = workspace_channel_notes_js_test_source('assets/css/backend.src.css');

	pg_assert_contains('window.PGWsChannelNotes = {', $script, 'the script lends card() and write()');
	pg_assert_contains("'assets/js/workspace_channel_notes.js',", $output, 'the script is in the workspace bundle (pg_js_bundles())');
	pg_assert_true(strpos($output, "'assets/js/workspace_channel_notes.js'") < strpos($output, "'assets/js/workspace.js',"), 'and printed before workspace.js');
	pg_assert_contains('window.PGWsChannelNotes.card(window.PGWsKit, card, this.noteHooks())', $workspace, 'noteCardNode() draws the card through the script');
	pg_assert_contains("label: t('cn_write')", $workspace, 'the attach menu offers Write a note');
	pg_assert_contains('!self.isThread && !self.era && !!self.channel && !!self.channel.can_post', $workspace, 'only to who may post in the channel, not in a thread');
	pg_assert_contains("versions: 1 }", $script, 'the Versions drawer reads the list the notes screen reads');
	pg_assert_contains("'ws_note_channel_write'", $script, 'Send writes the note and its card in one move');
	pg_assert_contains("'drawer drawer-end ws-cn-versions'", $script, 'Versions is a drawer from the end');
	pg_assert_contains("classList.add('drawer-bottom')", $script, 'and a sheet from the bottom on a phone');
	pg_assert_contains("'versions'     => \$kept[(int) \$note['id']] ?? 0,", $notes, 'the card map gives the count of versions');
	pg_assert_contains(".ws-nb-msg-card-text.ws-cn-fold.is-folded {\n    max-height: 18rem;", $css, 'the stylesheet folds at the script\'s height');
	pg_assert_contains('var FOLD_REM = 18;', $script, 'the script measures the same fold');

	foreach (array('cn_write', 'cn_channel_edit', 'cn_channel_edit_on', 'cn_channel_edit_off', 'cn_show_all', 'cn_show_less', 'cn_changed', 'cn_versions', 'cn_menu', 'cn_open_notes', 'cn_show', 'cn_put_back_done') as $key) {
		pg_assert_contains("'" . $key . "'", $notes, 'the string ' . $key . ' is sent to the screen');
	}
}
