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
	both: footWords({ owner: { name: "Ayşe" }, updated_by: { name: "Mehmet" }, changed_by: "Changed by @mehmet", updated: "5 min" }, t),
	agent: footWords({ owner: { name: "Ayşe" }, updated_by: { name: "Ayşe" }, updated_app: { name: "Build bot" }, changed_by: "Changed by Build bot", updated: "now" }, t),
	noTime: footWords({ owner: { name: "Ayşe" }, changed_by: "Changed by @mehmet", updated: "" }, t),
	ownerOnly: footWords({ owner: { name: "Ayşe" }, updated_by: null, changed_by: "" }, t),
	noWords: footWords({ owner: { name: "Ayşe" }, updated_by: { name: "Mehmet" }, updated: "5 min" }, t),
	none: footWords({}, t),
	nothing: footWords(null, t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('notes_owner(Ayşe)', 'Changed by @mehmet · 5 min'), $result['both'], 'the owner, then the server\'s words for who changed it last, and when');
	pg_assert_same(array('notes_owner(Ayşe)', 'Changed by Build bot · now'), $result['agent'], 'an agent\'s change draws the server\'s words naming the application, not the person behind its key');
	pg_assert_same(array('notes_owner(Ayşe)', 'Changed by @mehmet'), $result['noTime'], 'no time known, the words alone');
	pg_assert_same(array('notes_owner(Ayşe)'), $result['ownerOnly'], 'nobody named as the changer, no change line');
	pg_assert_same(array('notes_owner(Ayşe)'), $result['noWords'], 'the script builds no words of its own from updated_by');
	pg_assert_same(array(), $result['none'], 'nothing known, no words');
	pg_assert_same(array(), $result['nothing'], 'no card, no words');
}

// The foot is one line: its words joined by a middle dot, in their order.
function test_workspace_channel_notes_js_foot_line()
{
	$changed = workspace_channel_notes_js_test_function('changedWords');
	$foot = workspace_channel_notes_js_test_function('footWords');
	$line = workspace_channel_notes_js_test_function('footLine');

	pg_assert_true($line !== '', 'footLine() is in the card\'s script');

	$result = workspace_channel_notes_js_test_node(workspace_channel_notes_js_test_t() . $changed . $foot . $line . '
console.log(JSON.stringify({
	both: footLine({ owner: { name: "Ayşe" }, changed_by: "Changed by @mehmet", updated: "5 min" }, t),
	ownerOnly: footLine({ owner: { name: "Ayşe" }, changed_by: "" }, t),
	nothing: footLine(null, t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same('notes_owner(Ayşe) · Changed by @mehmet · 5 min', $result['both'], 'the owner, then who changed it and when, on one line');
	pg_assert_same('notes_owner(Ayşe)', $result['ownerOnly'], 'the owner alone, no dot');
	pg_assert_same('', $result['nothing'], 'no card, an empty line');
}

// The first line "Written up to …" is told from any other first line.
function test_workspace_channel_notes_js_written_up_to()
{
	$written = workspace_channel_notes_js_test_function('writtenUpTo');

	pg_assert_true($written !== '', 'writtenUpTo() is in the card\'s script');

	$result = workspace_channel_notes_js_test_node($written . '
console.log(JSON.stringify([
	writtenUpTo("Written up to #12"),
	writtenUpTo("  written up to <#message:5>"),
	writtenUpTo("Written up to"),
	writtenUpTo("Plan written up to #12"),
	writtenUpTo("Written upto #12"),
	writtenUpTo(""),
	writtenUpTo(null)
]));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, true, true, false, false, false, false), $result, 'only a line that opens with "Written up to" is the meta line');
}

// The card's look: look A's plain card, no coloured edge and no narrow
// width, no "Note" label, a small quiet Show all centred under the note, a
// one-line muted foot, headings measured against the text.
function test_workspace_channel_notes_js_card_look()
{
	$card = workspace_channel_notes_js_test_function('cardNode');
	$fold = workspace_channel_notes_js_test_function('fold');
	$css = workspace_channel_notes_js_test_source('assets/css/backend.src.css');

	pg_assert_contains("kit.el('div', 'pg-card ws-cn-card')", $card, 'the card is look A\'s plain card');
	pg_assert_true(strpos($card, "'ws-nb-msg-card'") === false && strpos($card, 'ws-nb-msg-card-kind') === false, 'no coloured edge, narrow width or "Note" label of the old card');
	pg_assert_true(strpos($card, "notes_card'") === false, 'the "Note" word is not written');
	pg_assert_contains("kit.el('button', 'pg-button pg-button-quiet pg-button-sm')", $card, 'Show all is a small quiet button');
	pg_assert_contains("kit.el('div', 'ws-cn-more d-none')", $card, 'in its own row, hidden while the note fits');
	pg_assert_contains("more.classList.toggle('d-none', state === 'whole')", $fold, 'the fold shows the row only for a longer note');
	pg_assert_contains("metaLine(kit, text);", $card, 'the "Written up to" line is drawn as a meta line');
	pg_assert_contains("tightGaps(kit, text);", $card, 'an empty line between paragraphs is a short gap');
	pg_assert_contains(".ws-cn-gap {\n    display: block;\n    height: 6px;\n}", $css, 'of 6 px');
	pg_assert_contains("kit.el('div', 'ws-cn-foot pg-text-meta')", $card, 'the foot is one muted line');
	pg_assert_contains("footLine(card, kit.t)", $card, 'of the foot\'s words joined');
	pg_assert_contains(".ws-cn-more {\n    margin-top: 6px;\n    text-align: center;\n}", $css, 'the button is centred under the note');
	pg_assert_contains(".ws-cn-card h2.ws-heading {\n    font-size: var(--pg-text-status-title);\n}", $css, 'the first heading is one step above the text');
	pg_assert_true(!preg_match('/\.ws-cn-card[^{]*\{[^}]*(max-width|border-left)/', $css), 'no width cap or coloured edge on the card');
}

function test_workspace_channel_notes_js_version_who()
{
	$who = workspace_channel_notes_js_test_function('versionWho');

	pg_assert_true($who !== '', 'versionWho() is in the card\'s script');

	$result = workspace_channel_notes_js_test_node(workspace_channel_notes_js_test_t() . $who . '
console.log(JSON.stringify({
	person: versionWho({ by: { name: "Mehmet" }, app: null, changed_by: "Changed by @mehmet" }, t),
	agent: versionWho({ by: { name: "Ayşe" }, app: { name: "Build bot" }, changed_by: "Changed by Build bot" }, t),
	unknown: versionWho({ by: null, changed_by: "" }, t),
	nothing: versionWho(null, t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same('Changed by @mehmet', $result['person'], 'a version a person saved: the server\'s words with the handle');
	pg_assert_same('Changed by Build bot', $result['agent'], 'a version an agent saved: the application, not the person behind its key');
	pg_assert_same('notes_person', $result['unknown'], 'nobody known: the plain "Person"');
	pg_assert_same('notes_person', $result['nothing'], 'no version: the plain "Person"');
}

// The notes screen's version list names who saved each version by the
// server's words, as the card's drawer does.
function test_workspace_channel_notes_js_notes_screen_versions()
{
	$source = workspace_channel_notes_js_test_source('assets/js/workspace.js');

	pg_assert_true(strpos($source, "item.appendChild(el('div', 'small fg-secondary text-truncate', version.changed_by));") !== false, 'the notes screen\'s version list draws changed_by');
	pg_assert_true(strpos($source, 'version.by.name') === false, 'and no longer the person behind an agent\'s key');
	pg_assert_same('', preg_match("/'cn_changed'/", workspace_channel_notes_js_test_source('includes/workspace/notes.php')) ? 'kept' : '', 'the script\'s own "Changed by" template is gone');
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

	pg_assert_same(array('read', 'edit', 'versions', 'open', 'channel_edit', 'move'), $result['owner'], 'the owner gets every item, the channel\'s edit switch and Move to this channel');
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
	pg_assert_contains('window.PGWsChannelNotes.card(window.PGWsKit, card, Object.assign(self.noteHooks(), {', $workspace, 'noteCardNode() draws the card through the script');
	pg_assert_contains("label: t('cn_write')", $workspace, 'the attach menu offers Write a note');
	pg_assert_contains('!self.isThread && !self.era && !!self.channel && !!self.channel.can_post', $workspace, 'only to who may post in the channel, not in a thread');
	pg_assert_contains("versions: 1 }", $script, 'the Versions drawer reads the list the notes screen reads');
	pg_assert_contains("'ws_note_channel_write'", $script, 'Send writes the note and its card in one move');
	pg_assert_contains("'drawer drawer-end ws-cn-versions'", $script, 'Versions is a drawer from the end');
	pg_assert_contains("classList.add('drawer-bottom')", $script, 'and a sheet from the bottom on a phone');
	pg_assert_contains("'versions'     => \$kept[(int) \$note['id']] ?? 0,", $notes, 'the card map gives the count of versions');
	pg_assert_contains(".ws-nb-msg-card-text.ws-cn-fold.is-folded {\n    max-height: 18rem;", $css, 'the stylesheet folds at the script\'s height');
	pg_assert_contains('var FOLD_REM = 18;', $script, 'the script measures the same fold');

	foreach (array('cn_write', 'cn_channel_edit', 'cn_channel_edit_on', 'cn_channel_edit_off', 'cn_show_all', 'cn_show_less', 'cn_versions', 'cn_menu', 'cn_open_notes', 'cn_show', 'cn_put_back_done') as $key) {
		pg_assert_contains("'" . $key . "'", $notes, 'the string ' . $key . ' is sent to the screen');
	}
}

// A channel's own note: its foot names who wrote it, and its card does not
// send to the notes screen, where it is not listed.
function test_workspace_channel_notes_js_owned_card()
{
	$changed = workspace_channel_notes_js_test_function('changedWords');
	$foot = workspace_channel_notes_js_test_function('footWords');
	$may = workspace_channel_notes_js_test_function('mayEdit');
	$items = workspace_channel_notes_js_test_function('menuItems');

	$result = workspace_channel_notes_js_test_node(workspace_channel_notes_js_test_t() . $changed . $foot . $may . $items . '
console.log(JSON.stringify({
	foot: footWords({ owned: true, owner: { name: "Ayşe" }, changed_by: "Changed by Build bot", updated: "now" }, t),
	poster: menuItems({ owned: true, access: "edit", channel_id: 3 }),
	reader: menuItems({ owned: true, access: "view", channel_id: 3 })
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('cn_written_by(Ayşe)', 'Changed by Build bot · now'), $result['foot'], '"Written by" the writer, then who changed it');
	pg_assert_same(array('read', 'edit', 'versions', 'copy'), $result['poster'], 'who may post edits it; no notes screen, no switch, no move; Copy to my notes');
	pg_assert_same(array('read', 'versions', 'copy'), $result['reader'], 'a reader reads it and its versions, and copies it to their notes');
}

// The Notes tab: when it shows, a row's menu and its meta line.
function test_workspace_channel_notes_js_tab()
{
	$shown = workspace_channel_notes_js_test_function('tabShown');
	$rows = workspace_channel_notes_js_test_function('rowItems');
	$meta = workspace_channel_notes_js_test_function('rowMeta');

	pg_assert_true($shown !== '' && $rows !== '' && $meta !== '', 'tabShown(), rowItems() and rowMeta() are in the script');

	$result = workspace_channel_notes_js_test_node(workspace_channel_notes_js_test_t() . $shown . $rows . $meta . '
console.log(JSON.stringify({
	shown: [tabShown({ notes: 2 }, "messages"), tabShown({ notes: 0 }, "messages"), tabShown({ notes: 0 }, "notes"), tabShown(null, "files")],
	deleter: rowItems({ can_delete: true }, true),
	reader: rowItems({ can_delete: false }, true),
	noExport: rowItems({ can_delete: false }, false),
	meta: rowMeta({ changed_by: "Changed by @mehmet", updated: "5 min" }, t),
	metaTime: rowMeta({ changed_by: "", updated: "5 min" }, t)
}));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, false, true, false), $result['shown'], 'shown once the channel has a note, or while it is the open tab');
	pg_assert_same(array('open', 'versions', 'copy', 'export', 'delete'), $result['deleter'], 'Open, Versions, Copy to my notes, Export, Delete for who may delete');
	pg_assert_same(array('open', 'versions', 'copy', 'export'), $result['reader'], 'no Delete for anybody else');
	pg_assert_same(array('open', 'versions', 'copy'), $result['noExport'], 'no Export where the screen has no export address');
	pg_assert_same('Changed by @mehmet · 5 min', $result['meta'], 'the server\'s "Changed by <name>" printed once, then the time');
	pg_assert_true(strpos($result['meta'], 'cn_changed_by') === false, 'the label is not wrapped in the label again');
	pg_assert_same('5 min', $result['metaTime'], 'nobody named: the time alone');
}

function test_workspace_channel_notes_js_tab_wiring()
{
	$script = workspace_channel_notes_js_test_source();
	$workspace = workspace_channel_notes_js_test_source('assets/js/workspace.js');
	$notes = workspace_channel_notes_js_test_source('includes/workspace/notes.php');

	pg_assert_contains("kit.api('ws_channel_notes', params)", $script, 'the tab reads the channel\'s notes a page at a time');
	pg_assert_contains("'pg-toolbar ws-pane-toolbar ws-cn-bar'", $script, 'its bar is the panes\' toolbar');
	pg_assert_true(strpos($script, 'cn_channel_edit') === false || strpos(workspace_channel_notes_js_test_function('write'), 'cn_channel_edit') === false, 'Write a note has no edit switch: a channel\'s note is edited by its posters');
	pg_assert_contains("window.PGWsChannelNotes.tab(window.PGWsKit, pane, channel,", $workspace, 'drawCenter() draws the Notes tab through the script');
	pg_assert_contains("window.PGWsChannelNotes.tabShown(channel.tab_counts, self.tab)", $workspace, 'beside Files once the channel has a note');
	pg_assert_contains("['notes', counts.notes, 'cn_tab_count']", $workspace, 'its mark counts the notes');
	pg_assert_contains("'owned'        => ws_note_channel_of(\$note) > 0,", $notes, 'the card map says a note is the channel\'s');

	foreach (array('cn_written_by', 'cn_tab', 'cn_tab_count', 'cn_search', 'cn_open', 'cn_none', 'cn_none_found', 'cn_older', 'cn_delete_confirm') as $key) {
		pg_assert_contains("'" . $key . "'", $notes, 'the string ' . $key . ' is sent to the screen');
	}
}

// Move to this channel and Copy to my notes call their panel actions, which
// the panel's action table routes.
function test_workspace_channel_notes_js_move_copy()
{
	$script = workspace_channel_notes_js_test_source();
	$panel = workspace_channel_notes_js_test_source('includes/panel/actions.php');

	pg_assert_contains("kit.api('ws_note_move', { note_id: card.id, channel_id: card.channel_id })", $script, 'Move to this channel moves the card\'s note into its channel');
	pg_assert_contains("kit.t('cn_move_confirm')", $script, 'after a question');
	pg_assert_contains("kit.api('ws_note_copy_mine', { note_id: noteId })", $script, 'Copy to my notes');

	foreach (array('ws_note_move', 'ws_note_copy_mine', 'ws_channel_notes') as $action) {
		pg_assert_contains("'" . $action . "'", $panel, $action . ' is in the panel\'s action table');
	}
}
