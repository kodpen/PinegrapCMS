<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Visual Page Editor's right column and bottom panel
 * (assets/js/style_designer.src.js): whether the bottom panel opens from
 * the state this browser kept (a browser that never chose gets the closed
 * strip), and which right column width a stored layout brings back (only a
 * width the user dragged; the old defaults saved by every layout write are
 * not one). The readers are cut out of the source and run under Node; on a
 * machine without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_right_column_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
}

// The source of one function of the editor, from its header to the first
// line that closes a block at the function's indent; '' when it is not there.
function designer_right_column_test_function($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$to = strpos($source, "\n    }\n", $from);

	return ($to === false) ? '' : substr($source, $from, $to + 6 - $from);
}

// Runs $code after the two readers; its `out` comes back decoded. Null
// without Node.
function designer_right_column_test_run($code)
{
	$source = designer_right_column_test_source();
	$fns = '';

	foreach (array('_sdBpOpenRead', '_sdLayoutRightWidth') as $name) {
		$fn = designer_right_column_test_function($source, $name);
		pg_assert_true($fn !== '', 'the editor has ' . $name . '()');

		if ($fn === '') {
			return null;
		}

		$fns .= $fn . "\n";
	}

	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$file = tempnam(sys_get_temp_dir(), 'pgrcol');
	file_put_contents($file, $fns . "var out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	unlink($file);

	pg_assert_same(0, $status, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The bottom panel opens only where this browser last left it open ('0' in
// pinegrap_designer_bp_collapsed); nothing stored, '1' or anything else
// gives the closed strip.
function test_designer_bottom_panel_open_state()
{
	$answer = designer_right_column_test_run(
		"out = [null, undefined, '', '0', ' 0 ', '1', 'true', 0].map(_sdBpOpenRead);"
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(false, false, false, true, true, false, false, false), $answer);
}

// A width saved by a drag comes back; a layout saved before (no version)
// that holds one of the two old defaults (260, 240 at 1024 px and below) was
// written by a save that did not drag the column, so the default applies;
// a value out of the drag's bounds or not a number is none.
function test_designer_right_column_stored_width()
{
	$answer = designer_right_column_test_run(<<<'JS'
out = [
	null,
	{},
	{ rightW: 260 },
	{ rightW: 240 },
	{ rightW: 312 },
	{ rightW: 260, v: 2 },
	{ rightW: 240, v: 2 },
	{ rightW: 180 },
	{ rightW: 900, v: 2 },
	{ rightW: 'wide' },
	{ leftW: 300, bottomH: 220, v: 2 }
].map(_sdLayoutRightWidth);
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(0, 0, 0, 0, 312, 260, 240, 0, 0, 0, 0), $answer);
}
