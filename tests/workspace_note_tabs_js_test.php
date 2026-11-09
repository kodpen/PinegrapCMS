<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the note tabs of the My Notes screen
 * (assets/js/workspace_note_tabs.js, window.PGWsNoteTabs.pure): which notes
 * a group's tabs show and in which order, where an arrow key moves, where a
 * dragged tab lands and the position sent to ws_note_place, and how a long
 * title is cut. Runs in Node; skipped where Node cannot run. The bundle test
 * keeps the script in workspace.min.js beside the groups script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $body against the script in Node and answers what it printed as JSON;
// null when Node cannot run.
function workspace_note_tabs_js_test_node($body)
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

	$script = "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_note_tabs.js') . "\n"
		. "var P = window.PGWsNoteTabs.pure;\n"
		. 'console.log(JSON.stringify((function () {' . $body . '}())));';

	$file = @tempnam(sys_get_temp_dir(), 'pgtabs');

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

function test_workspace_note_tabs_follow_the_group_order()
{
	$result = workspace_note_tabs_js_test_node('
		var notes = [
			{ id: 5, group_id: 2, group_sort: 3, pinned: true },
			{ id: 7, group_id: 2, group_sort: 1 },
			{ id: 9, group_id: 3, group_sort: 0 },
			{ id: 4, group_id: 2, group_sort: 1 },
			{ id: 6, group_id: 0 },
			{ id: 8, group_id: "2", group_sort: "2" }
		];
		return {
			two: P.order(notes, 2).map(function (n) { return n.id; }),
			none: P.order(notes, 0).length,
			missing: P.order(notes, 11).length
		};
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(4, 7, 8, 5), $result['two'], 'the group order alone, pinned or not; a tie by id');
	pg_assert_same(0, $result['none'], 'a note in no group has no tabs');
	pg_assert_same(0, $result['missing'], 'an unknown group has none');
}

function test_workspace_note_tabs_arrow_keys()
{
	$result = workspace_note_tabs_js_test_node('
		return [
			P.step("ArrowRight", 0, 3), P.step("ArrowRight", 2, 3), P.step("ArrowLeft", 0, 3),
			P.step("ArrowLeft", 2, 3), P.step("Home", 2, 3), P.step("End", 0, 3),
			P.step("ArrowDown", 0, 3), P.step("Enter", 1, 3), P.step("ArrowRight", 0, 0),
			P.step("ArrowRight", 0, 3, true), P.step("ArrowLeft", 0, 3, true)
		];
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(1, 0, 2, 1, 0, 2, -1, -1, -1, 2, 1), $result, 'right and left wrap, Home and End, others none; right-to-left swaps the arrows');
}

function test_workspace_note_tabs_drop_position()
{
	$result = workspace_note_tabs_js_test_node('
		var centers = [50, 150, 250, 350];
		return {
			stay: P.dropIndex(centers, 1, 160),
			back: P.dropIndex(centers, 1, 20),
			end: P.dropIndex(centers, 1, 900),
			midway: P.dropIndex(centers, 0, 260),
			lastToFirst: P.dropIndex(centers, 3, 10),
			rtl: P.dropIndex([350, 250, 150, 50], 0, 120, true)
		};
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(1, $result['stay'], 'dropped where it was: the same position');
	pg_assert_same(0, $result['back'], 'before the first tab: position 0');
	pg_assert_same(3, $result['end'], 'past the last: the end of the others');
	pg_assert_same(2, $result['midway'], 'counted among the other tabs, not itself');
	pg_assert_same(0, $result['lastToFirst'], 'the last brought to the front');
	pg_assert_same(2, $result['rtl'], 'right to left counts from the right');
}

function test_workspace_note_tabs_short_title()
{
	$result = workspace_note_tabs_js_test_node('
		return [P.short("Short", 24), P.short("  A   long \n title  ", 24), P.short("abcdefghijklmnopqrstuvwxyz", 10), P.short("", 24), P.short("şşşşşşşşşşşş", 5)];
	');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('Short', 'A long title', 'abcdefghi…', '', 'şşşş…'), $result, 'spaces run together, a long title cut with an ellipsis');
}

function test_workspace_note_tabs_in_the_workspace_bundle()
{
	$bundles = pg_js_bundles();
	$list = $bundles['assets/js/workspace.min.js'];
	$tabs = array_search('assets/js/workspace_note_tabs.js', $list, true);
	$groups = array_search('assets/js/workspace_note_groups.js', $list, true);

	pg_assert_true($tabs !== false, 'the tabs script is bundled');
	pg_assert_true(($groups !== false) && ($tabs > $groups) && ($tabs < array_search('assets/js/workspace.js', $list, true)), 'after the groups script, before workspace.js');
}
