<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The browser side of the clicks and scroll count: the cookie notice rule
 * of the anonymous counts in frontend.src.js against the server's
 * (pg_consent_statistics_not_refused_by()) on the same examples, the
 * script's naming and fold of an element (assets/js/pg_clicks.src.js)
 * against the server's on the server test's fixtures, and who gets the
 * script. The browser parts run in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/consent.php');

// Runs `$code` in Node after `$prelude`; its `out` comes back decoded. Null
// when Node cannot run.
function page_clicks_script_test_node($prelude, $code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgclicks');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, $prelude . "\nvar out = null;\n" . $code . "\nconsole.log(JSON.stringify(out));");
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return ($status === 0) ? json_decode(implode("\n", $out), true) : null;
}

// The pure rule of frontend.src.js as a function of Node.
function page_clicks_script_test_consent_rule()
{
	$source = str_replace("\r\n", "\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js'));
	$start = strpos($source, 'statistics_not_refused_by: function(enabled, raw) {');
	$end = ($start === false) ? false : strpos($source, "\n    },", $start);

	pg_assert_true(($start !== false) && ($end !== false), 'the browser rule is there');

	return 'var rule = function(enabled, raw) {' . substr($source, $start + strlen('statistics_not_refused_by: function(enabled, raw) {'), $end - $start - strlen('statistics_not_refused_by: function(enabled, raw) {')) . "\n};";
}

// The cookie notice examples both sides are held to: notice off, a1, a0,
// not answered, a value that cannot be read.
function page_clicks_script_test_consent_cases()
{
	return array(
		array(false, '', true, 'notice off'),
		array(false, '1.a0.m0', true, 'notice off, whatever the cookie says'),
		array(true, '1.a1.m0', true, 'a1'),
		array(true, '1.a0.m1', false, 'a0 refused'),
		array(true, '1.a0', false, 'a0 alone'),
		array(true, '', true, 'not answered'),
		array(true, '1.m1', true, 'statistics not named yet'),
		array(true, 'x.y', true, 'a broken value'),
		array(true, '2.a0', true, 'an unknown version'),
		array(true, '1.a1.a0', false, 'the last a part wins'),
		array(true, '1.aa.a0', false, 'an unknown part is skipped'),
	);
}

function test_page_clicks_consent_rule_is_one_on_both_sides()
{
	$cases = page_clicks_script_test_consent_cases();

	foreach ($cases as $case) {
		pg_assert_same($case[2], pg_consent_statistics_not_refused_by($case[0], pg_consent_parse($case[1])), 'server: ' . $case[3]);
	}

	$out = page_clicks_script_test_node(page_clicks_script_test_consent_rule(), 'out = ' . json_encode($cases) . '.map(function (c) { return rule(c[0], c[1]); });');

	if ($out === null) {
		return;
	}

	foreach ($cases as $index => $case) {
		pg_assert_same($case[2], $out[$index], 'browser: ' . $case[3]);
	}

	// The served twin, hand-patched in frontend.min.js, on the same examples.
	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');
	$at = strpos($min, 'statistics_not_refused_by:function(e,t){');
	$end = ($at === false) ? false : strpos($min, 'return!o}', $at);
	pg_assert_true(($at !== false) && ($end !== false), 'the served rule is there');
	$served = page_clicks_script_test_node('var rule = function(e,t){' . substr($min, $at + 40, $end + 9 - $at - 40) . ';', 'out = ' . json_encode($cases) . '.map(function (c) { return rule(c[0], c[1]); });');

	foreach ($cases as $index => $case) {
		pg_assert_same($case[2], $served[$index], 'served: ' . $case[3]);
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	pg_assert_true(strpos($source, 'statistics_allowed') === false, 'no "allowed" check left: the anonymous counts ask "not refused"');
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js'), 'statistics_allowed') === false, 'nor in the served script');
}
