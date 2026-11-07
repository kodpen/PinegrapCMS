<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visual designer's timing probe (assets/js/
 * style_designer.src.js): it is on only for an address carrying
 * `sd_perf=1`, sums its samples as count, median and worst, writes one line
 * per measure, and hooks the opening, the first draw, an edit, a tab switch
 * and a save. Also runs tests/js/designer_render_timing.js on the big
 * fixture page. The JavaScript runs under Node; on a machine without Node
 * those runs are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_perf_probe_test_source()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	}
	return $js;
}

// The source of one function declaration, braces matched; '' when missing.
function designer_perf_probe_test_function($name)
{
	$source = designer_perf_probe_test_source();
	$from = strpos($source, 'function ' . $name . '(');

	if ($from === false) {
		return '';
	}

	$depth = 0;

	for ($i = strpos($source, '{', $from); $i < strlen($source); $i++) {
		if ($source[$i] === '{') {
			$depth++;
		} elseif ($source[$i] === '}') {
			$depth--;

			if ($depth === 0) {
				return substr($source, $from, $i - $from + 1);
			}
		}
	}

	return '';
}

function designer_perf_probe_test_has_node()
{
	if (!function_exists('exec')) {
		return false;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	return $code === 0;
}

// Runs `$script` after the named designer functions; the decoded JSON of
// what it prints, or null without Node.
function designer_perf_probe_test_run(array $names, $script)
{
	if (!designer_perf_probe_test_has_node()) {
		return null;
	}

	$code_js = '';

	foreach ($names as $name) {
		$found = designer_perf_probe_test_function($name);
		pg_assert_true($found !== '', $name . ' is in the designer');
		$code_js .= $found . "\n";
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgsdperf');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $code_js . "\n" . $script);
	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// Only `sd_perf=1` in the query string turns the probe on.
function test_designer_perf_probe_is_behind_the_address()
{
	$got = designer_perf_probe_test_run(array('_sdProbeEnabled'),
		'console.log(JSON.stringify(["?sd_perf=1", "?page=3&sd_perf=1", "", "?sd_perf=0", "?xsd_perf=1", "?sd_perf=10", "?sd_perf=1#x"].map(_sdProbeEnabled)));');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(true, true, false, false, false, false, true), $got, 'sd_perf=1 and nothing else');
}

// Count, median and worst of a list of milliseconds.
function test_designer_perf_probe_stats()
{
	$got = designer_perf_probe_test_run(array('_sdProbeStats'),
		'console.log(JSON.stringify([_sdProbeStats([5, 1, 3]), _sdProbeStats([4, 1, 2, 3]), _sdProbeStats([])]));');

	if ($got === null) {
		return;
	}

	pg_assert_same(array(
		array('n' => 3, 'median' => 3, 'worst' => 5),
		array('n' => 4, 'median' => 2.5, 'worst' => 4),
		array('n' => 0, 'median' => 0, 'worst' => 0),
	), $got, 'an odd list, an even list, no samples');
}

// One console line per measure: its name, milliseconds to one decimal and
// the page's node count.
function test_designer_perf_probe_line()
{
	$got = designer_perf_probe_test_run(array('_sdProbeLine'),
		'console.log(JSON.stringify([_sdProbeLine("first draw", 312.449, 1539), _sdProbeLine("edit", 4, 0)]));');

	if ($got === null) {
		return;
	}

	pg_assert_same(array('[sd-perf] first draw 312.4 ms (1539 nodes)', '[sd-perf] edit 4.0 ms (0 nodes)'), $got, 'the console line');
}

// The five measures are hooked, and the probe costs nothing when it is off.
function test_designer_perf_probe_hooks()
{
	$source = designer_perf_probe_test_source();

	pg_assert_contains("var _sdProbe = { on: _sdProbeEnabled(window.location.search),", $source, 'the probe reads the address once');
	foreach (array("'open'", "'first draw'", "'edit'", "'tab switch'", "'save'") as $measure) {
		pg_assert_contains('_sdProbeRecord(' . $measure, $source, $measure . ' is recorded');
	}
	pg_assert_contains("_pgTabsSwitch     = _sdProbeWrapTabSwitch(_pgTabsSwitch);", $source, 'the tab switch is timed');
	pg_assert_contains("saveAjax          = _sdProbeWrapSave(saveAjax);", $source, 'the save is timed');
	pg_assert_contains("badge.id = 'sd-perf-badge';", $source, 'the badge');

	$record = designer_perf_probe_test_function('_sdProbeRecord');
	pg_assert_true(strpos($record, 'if (!_sdProbe.on) return;') !== false, 'an off probe records nothing');
}

// The pure parts of render() on the big fixture page: every measure is a
// number and the walk finds the last node.
function test_designer_render_timing_on_big_fixture()
{
	if (!designer_perf_probe_test_has_node()) {
		return;
	}

	$out = array();
	$code = 1;
	@exec('node ' . escapeshellarg(dirname(__DIR__) . '/tests/js/designer_render_timing.js') . ' --json 2>&1', $out, $code);
	pg_assert_same(0, $code, implode("\n", $out));

	$got = json_decode(implode("\n", $out), true);
	pg_assert_same(1539, $got['nodes'], 'the fixture page has 1539 nodes');
	pg_assert_true($got['found'], 'findNodeById() reaches the last node');
	pg_assert_same(array('undo_snapshot', 'page_baseline', 'shared_snapshot', 'undo_restore', 'find_last_node'), array_keys($got['phases']), 'the measures');

	foreach ($got['phases'] as $name => $ms) {
		pg_assert_true(is_numeric($ms) && $ms >= 0, $name . ' is a time');
	}
}
