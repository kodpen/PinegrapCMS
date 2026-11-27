<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The script that changes a Data table's cells in its grid
 * (assets/js/workspace_wtable_grid.js) in Node: whether a field still
 * holds the value its cell opened with, and the cell Tab and Shift+Tab go
 * to. Skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Loads the script as a module and answers what $script printed as JSON;
// null without Node.
function workspace_wtable_grid_js_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgwtgridjs');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'var G = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/workspace_wtable_grid.js') . ");\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	return ($code === 0) ? json_decode(implode("\n", $out), true) : null;
}

// A field unchanged sends nothing: spaces around a value do not count, a
// box counts as ticked or not.
function test_workspace_wtable_grid_js_same_value()
{
	$result = workspace_wtable_grid_js_test_node('console.log(JSON.stringify([
	G.sameValue("text", "abc", " abc "),
	G.sameValue("text", "abc", "abd"),
	G.sameValue("text", "", null),
	G.sameValue("number", "1,5", "1,50"),
	G.sameValue("checkbox", "1", "1"),
	G.sameValue("checkbox", "", "0"),
	G.sameValue("checkbox", "", "1"),
	G.sameValue("status", "s1", "")
]));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, false, true, false, true, true, false, false), $result);
}

// Tab goes to the next cell, Shift+Tab to the one before; none past the ends
// or from a cell that is not in the list.
function test_workspace_wtable_grid_js_step()
{
	$result = workspace_wtable_grid_js_test_node('console.log(JSON.stringify([
	G.step(3, 0, 1), G.step(3, 2, 1), G.step(3, 2, -1), G.step(3, 0, -1), G.step(3, -1, 1), G.step(0, 0, 1)
]));');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(1, -1, 1, -1, -1, -1), $result);
}
