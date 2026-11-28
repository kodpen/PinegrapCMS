<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for My Notes' list order and section labels
 * (assets/js/workspace_note_list.js): the order picked in the bar's "⋯"
 * (its pure part run in Node, skipped where Node cannot run), the rows drawn
 * as .pg-list-row with the chosen one tinted, the sections' group labels,
 * the text keys, and the script in its bundle.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_note_list_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// Runs $body against the script in Node and answers what it printed as JSON;
// null when Node cannot run.
function workspace_note_list_test_node($body)
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

	$script = "var window = {};\n" . workspace_note_list_test_source('assets/js/workspace_note_list.js') . "\n"
		. "var L = window.PGWsNoteList;\n"
		. "function ids(notes) { return notes.map(function (note) { return note.id; }); }\n"
		. "var notes = [\n"
		. "  { id: 1, name: 'beta', updated_at: 300, created_at: 10 },\n"
		. "  { id: 2, name: 'Alpha', updated_at: 100, created_at: 30 },\n"
		. "  { id: 3, name: 'note 10', updated_at: 200, created_at: 20, pinned: 1 },\n"
		. "  { id: 4, name: 'note 9', updated_at: 200, created_at: 40 },\n"
		. "  { id: 5, name: 'alpha', updated_at: 50, created_at: 30 }\n"
		. "];\n"
		. 'console.log(JSON.stringify((function () {' . $body . '}())));';

	$file = @tempnam(sys_get_temp_dir(), 'pglist');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// Pinned notes first, then the order asked; the higher id breaks a tie, an
// unknown order is the server's own, and the list handed in is left as it is.
function test_workspace_note_list_order()
{
	$result = workspace_note_list_test_node('
		var before = ids(notes);

		return {
			changed: ids(L.order(notes, "changed", "en")),
			name: ids(L.order(notes, "name", "en")),
			created: ids(L.order(notes, "created", "en")),
			unknown: ids(L.order(notes, "size", "en")),
			none: ids(L.order(notes)),
			empty: L.order(null, "name", "en"),
			kept: ids(notes).join() === before.join()
		};
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(3, 1, 4, 2, 5), $result['changed'], 'the newest change first, the pinned note above all');
	pg_assert_same(array(3, 5, 2, 1, 4), $result['name'], 'by name, case and numbers read as a person reads them; a tie to the higher id');
	pg_assert_same(array(3, 4, 5, 2, 1), $result['created'], 'the newest note first; a tie to the higher id');
	pg_assert_same($result['changed'], $result['unknown'], 'an unknown order is the last change');
	pg_assert_same($result['changed'], $result['none'], 'no order given is the last change');
	pg_assert_same(array(), $result['empty'], 'no notes, an empty list');
	pg_assert_true($result['kept'], 'the notes handed in keep their order');
}

// The screen opens on the note the address names, else the one last open
// while it is listed, else the first of the list as ordered (My notes, then
// Shared with me); deleting the open note opens the one after it, else the
// one before it.
function test_workspace_note_list_note_to_open()
{
	$result = workspace_note_list_test_node('
		var lists = { mine: notes.slice(0, 3), shared: notes.slice(3) };

		return {
			first: L.firstOpen(lists, 0, 0, "changed", "en"),
			byName: L.firstOpen(lists, 0, 0, "name", "en"),
			kept: L.firstOpen(lists, 0, 4, "changed", "en"),
			keptGone: L.firstOpen(lists, 0, 9, "changed", "en"),
			wanted: L.firstOpen(lists, 9, 4, "changed", "en"),
			wantedText: L.firstOpen(lists, "2", 0, "changed", "en"),
			none: L.firstOpen({ mine: [], shared: [] }, 0, 4, "changed", "en"),
			noLists: L.firstOpen(null, 0, 0),
			next: L.nextOpen(lists, 1, "changed", "en"),
			nextAcross: L.nextOpen(lists, 2, "changed", "en"),
			nextLast: L.nextOpen(lists, 5, "changed", "en"),
			nextByName: L.nextOpen(lists, 2, "name", "en"),
			nextUnknown: L.nextOpen(lists, 9, "changed", "en"),
			nextNone: L.nextOpen({ mine: [notes[0]], shared: [] }, 1, "changed", "en"),
			kept5: ids(lists.mine).concat(ids(lists.shared)).join()
		};
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(3, $result['first'], 'nothing remembered: the first of the list, the pinned note');
	pg_assert_same(3, $result['byName'], 'the pinned note heads every order');
	pg_assert_same(4, $result['kept'], 'the note last open, a shared one');
	pg_assert_same(3, $result['keptGone'], 'the note last open is gone: the first of the list');
	pg_assert_same(9, $result['wanted'], 'the address wins over the note last open, listed or not');
	pg_assert_same(2, $result['wantedText'], 'the address is read as a number');
	pg_assert_same(0, $result['none'], 'no note: nothing to open');
	pg_assert_same(0, $result['noLists'], 'no lists: nothing to open');
	pg_assert_same(2, $result['next'], 'the note after it');
	pg_assert_same(4, $result['nextAcross'], 'the last of My notes: the first shared one');
	pg_assert_same(4, $result['nextLast'], 'the last of the list: the one before it');
	pg_assert_same(1, $result['nextByName'], 'the next in the order picked');
	pg_assert_same(3, $result['nextUnknown'], 'a note not listed: the first of the list');
	pg_assert_same(0, $result['nextNone'], 'the only note: none left');
	pg_assert_same('1,2,3,4,5', $result['kept5'], 'the lists handed in are left as they are');
}

// No "pick a note" place: the screen opens on a note (wide screens only),
// the note last open is kept per person, a deleted open note gives way to
// the next, and with no note one card starts the first.
function test_workspace_note_list_screen_opens_on_a_note()
{
	$main = workspace_note_list_test_source('assets/js/workspace.js');
	$notes = workspace_note_list_test_source('includes/workspace/notes.php');
	$css = workspace_note_list_test_source('assets/css/backend.src.css');

	pg_assert_false(strpos($main, 'placeholder()') !== false, 'no empty pane with its own New note');
	pg_assert_false(strpos($main, 'ws-nb-placeholder') !== false, 'no placeholder class drawn');
	pg_assert_false(strpos($css, '.ws-nb-placeholder') !== false, 'no placeholder rule');
	pg_assert_false(strpos($notes, "'notes_pick'") !== false, 'no "pick a note" text');
	pg_assert_contains("window.matchMedia('(min-width: 1024px)')", $main, 'one card at a time below 1024 px: the list alone');
	pg_assert_contains("window.PGWsNoteList.firstOpen(lists, 0, window.PGWsNoteList.openRead(CFG.me)", $main, 'the note last open, kept per person');
	pg_assert_contains("window.PGWsNoteList.openWrite(CFG.me, noteId);", $main, 'an opened note is kept');
	pg_assert_contains("window.PGWsNoteList.nextOpen(lists, note.id", $main, 'the next note after a delete');
	pg_assert_contains("title: t('notes_first_title')", $main, 'the first note\'s card');
	pg_assert_contains("'notes_first_title'     => lang('Write your first note')", $notes);
	pg_assert_contains("display: none;", workspace_note_list_test_rule($css, '.ws-nb.ws-nb-none > .ws-nb-list-col'), 'no empty list beside the first note\'s card');
	pg_assert_contains("var OPEN_KEY = 'pg_ws_notes_open_';", workspace_note_list_test_source('assets/js/workspace_note_list.js'));
}

// The row is the look's list row; the open note is the chosen row, tinted
// with an accent edge, not a filled box.
function test_workspace_note_list_row_is_a_list_row()
{
	$main = workspace_note_list_test_source('assets/js/workspace.js');
	$css = workspace_note_list_test_source('assets/css/backend.src.css');

	pg_assert_contains("el('div', 'pg-list-row ws-nb-item' + (open ? ' is-chosen' : '')", $main);
	pg_assert_contains("el('span', 'pg-list-row-quick')", $main, 'the ⋯ sits in the quick actions');
	pg_assert_contains("el('div', 'pg-list-row-text ws-nb-item-excerpt', note.excerpt)", $main, 'the two-line excerpt');
	pg_assert_contains("el('span', 'pg-unread-dot ws-nb-item-dot')", $main, 'the unread dot');
	pg_assert_false(strpos($main, "'ws-nb-item' + (open ? ' active'") !== false, 'no filled box for the open note');

	$chosen = '.ws-nb-list .ws-nb-item.is-chosen {';
	$at = strpos($css, $chosen);

	pg_assert_true($at !== false, 'the chosen row has its rule');

	$rule = substr($css, $at, (int) strpos($css, '}', (int) $at) - $at);

	pg_assert_contains('var(--pg-tint-select)', $rule, 'tinted with the select strength');
	pg_assert_contains('box-shadow: inset 3px 0 0 var(--pg-accent);', $rule, 'the 3 px accent edge');
	pg_assert_false(strpos($css, '.ws-nb-item.active') !== false, 'no rule for the old filled box');
	pg_assert_same(1, substr_count($css, "\n.pg-list-row {"), 'one definition of the list row');
}

// My notes, Shared with me and the folders stand under group labels that
// fold; the sections' state is kept in this browser, inside try/catch.
function test_workspace_note_list_sections_fold()
{
	$list = workspace_note_list_test_source('assets/js/workspace_note_list.js');

	pg_assert_contains("kit.el('button', 'pg-group-label ws-nb-fold')", $list);
	pg_assert_contains("kit.el('span', 'pg-group-label-count', String(count))", $list);
	pg_assert_contains("toggle.setAttribute('aria-expanded'", $list);
	pg_assert_contains("var FOLD_KEY = 'pg_ws_notes_folded';", $list);
	pg_assert_contains("var ORDER_KEY = 'pg_ws_notes_order';", $list);
	pg_assert_same(6, substr_count($list, 'try {'), 'every read and write of the browser store is guarded');

	foreach (array('assets/js/workspace_note_groups.js', 'assets/js/workspace_note_group_shares.js') as $path) {
		$source = workspace_note_list_test_source($path);

		pg_assert_contains("kit.el('button', 'pg-group-label ws-nb-fold ws-nb-group-toggle')", $source, $path . ': the folder is a group label');
		pg_assert_contains('ctx.section(', $source, $path . ': the section folds');
		pg_assert_false(strpos($source, 'ws-nb-count') !== false, $path . ': no old count');
	}
}

// The order's words are Workspace text keys, and the script is bundled
// before the folders that draw with its section().
function test_workspace_note_list_in_the_bundle()
{
	$notes = workspace_note_list_test_source('includes/workspace/notes.php');

	foreach (array('notes_order', 'notes_order_changed', 'notes_order_name', 'notes_order_created') as $key) {
		pg_assert_contains("'" . $key . "'", $notes, 'the text key ' . $key);
	}

	$bundles = pg_js_bundles();
	$sources = $bundles['assets/js/workspace.min.js'];
	$at = array_search('assets/js/workspace_note_list.js', $sources, true);

	pg_assert_true($at !== false, 'the list script is bundled');
	pg_assert_true($at < array_search('assets/js/workspace_note_groups.js', $sources, true), 'before the folders');
	pg_assert_true($at < array_search('assets/js/workspace.js', $sources, true), 'before workspace.js');
}

// The list is a card of its own with a head (Notes, the count, New group,
// the order) beside the note's card: 372 px from 1200 px up with 20 px
// between them, 300 px and 16 px below; the list card sticky with its own
// scroll; each row with a hairline under it, an unread one with a heavier
// title.
function test_workspace_note_list_is_a_card_beside_the_note()
{
	$main = workspace_note_list_test_source('assets/js/workspace.js');
	$groups = workspace_note_list_test_source('assets/js/workspace_note_groups.js');
	$css = workspace_note_list_test_source('assets/css/backend.src.css');

	pg_assert_contains("el('section', 'pg-card ws-nb-list-card')", $main, 'the list is the look\'s card');
	pg_assert_contains("el('h2', 'ws-nb-list-title', t('notes_list_head'))", $main, 'the head names it');
	pg_assert_contains("window.PGWsNoteGroups.addButton(groups)", $main, 'New group stands in the head');
	pg_assert_contains("'bi-sort-down', t('notes_order')", $main, 'the order stands in the head');
	pg_assert_contains("el('div', 'card pg-card ws-nb-card ws-nb-page')", $main, 'the note is the look\'s card');
	pg_assert_false(strpos($main, "'row g-7 ws-nb'") !== false, 'no grid row of columns');
	pg_assert_false(strpos($groups, 'parts.head.appendChild(add)') !== false, 'New group left the My notes band');
	pg_assert_contains("'notes_list_head'", workspace_note_list_test_source('includes/workspace/notes.php'), 'the head\'s text key');

	pg_assert_contains("grid-template-columns: 300px minmax(0, 1fr);", $css, '300 px below 1200 px');
	pg_assert_contains("grid-template-columns: 372px minmax(0, 1fr);\n        gap: 20px;", $css, '372 px and 20 px from 1200 px up');
	pg_assert_contains("position: sticky;\n        top: calc(var(--pg-navbar-h) + 16px);", $css, 'the list card is sticky');
	pg_assert_contains("overflow-y: auto;", workspace_note_list_test_rule($css, '.ws-nb-list-card > .ws-nb-list'), 'its body scrolls');
	pg_assert_contains('min-height: 46px;', workspace_note_list_test_rule($css, '.ws-nb-list-head'), 'the list head is 46 px');
	pg_assert_contains('min-height: 46px;', workspace_note_list_test_rule($css, '.ws-nb-page > .ws-nb-head'), 'the note head is 46 px');
	pg_assert_contains('border-bottom: 1px solid', workspace_note_list_test_rule($css, '.ws-nb-item'), 'a hairline under each row');
	pg_assert_contains('font-weight: var(--pg-weight-title);', workspace_note_list_test_rule($css, '.ws-nb-item-unread .ws-nb-item-name'), 'unread: a heavier title');
}

// The body of the first rule whose line starts with the selector, indented
// or not.
function workspace_note_list_test_rule($css, $selector)
{
	$found = preg_match('/\n[ ]*' . preg_quote($selector, '/') . ' \{/', $css, $match, PREG_OFFSET_CAPTURE);

	pg_assert_true($found === 1, 'the rule ' . $selector);

	$at = $found ? (int) $match[0][1] : 0;

	return substr($css, $at, (int) strpos($css, '}', $at) - $at);
}
