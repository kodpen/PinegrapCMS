<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests: the pure part of a Data table's row drag
 * (assets/js/workspace_wtable_order.js), run in Node when it is there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A row dropped on another takes its place, the line above it when it
// moves up and under it when it moves down; on itself it stays.
function test_workspace_wtable_order_js_drop_place()
{
	if (!function_exists('exec')) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgwtorderjs');

	if ($file === false) {
		return;
	}

	file_put_contents($file, 'var O = require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/workspace_wtable_order.js') . ");\n"
		. 'console.log(JSON.stringify([O.dropPlace(5, 2), O.dropPlace("2", "7"), O.dropPlace(3, 3), O.dropPlace(0, 2)]));');
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));
	pg_assert_same(array(
		array('position' => 2, 'side' => 'before'),
		array('position' => 7, 'side' => 'after'),
		null,
		null,
	), json_decode(implode("\n", $out), true));
}
