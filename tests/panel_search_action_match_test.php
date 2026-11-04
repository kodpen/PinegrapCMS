<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests actionMatches() of the panel search box (backend.src.js), run in
 * Node: a key that starts with the query matches; a query that starts with
 * a key matches only when the key is a whole word of it or four letters or
 * longer, so 'adres' no longer offers Ads (key 'ad') and 'ipek' no IP
 * settings (key 'ip').
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The function's source, cut out of the bundle source.
function panel_search_action_match_source()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/backend.src.js');
	$start = strpos($js, '        function actionMatches(q, keys) {');

	if ($start === false) {
		return '';
	}

	$end = strpos($js, "\n        }\n", $start);

	return ($end === false) ? '' : substr($js, $start, $end - $start + 10);
}

// Every case's answer from Node, or null without Node.
function panel_search_action_match_run($cases)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgam');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "function normalizeTR(s) { return String(s).toLowerCase(); }\n"
		. panel_search_action_match_source() . "\n"
		. 'var cases = ' . json_encode($cases) . ";\n"
		. "console.log(JSON.stringify(cases.map(function (c) { return actionMatches(c[0], c[1]); })));\n");

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	$json = json_decode(implode("\n", $out), true);

	return (($code === 0) && is_array($json)) ? $json : array('error' => implode("\n", $out));
}

function test_panel_search_action_match_source_found()
{
	pg_assert_contains('function actionMatches(q, keys) {', panel_search_action_match_source(), 'the matcher is cut out of the bundle source');
}

function test_panel_search_action_match_short_keys()
{
	$cases = array(
		'adres'        => array('adres', array('reklam', 'ad', 'ads', 'rek')),
		'ad'           => array('ad', array('reklam', 'ad', 'ads', 'rek')),
		'ad soyad'     => array('ad soyad', array('ad')),
		'ipek'         => array('ipek', array('ip')),
		'rek'          => array('rek', array('reklam')),
		'reklamlar'    => array('reklamlar', array('reklam')),
		'kisayol'      => array('kisayol', array('kis')),
		'stil ekle'    => array('stil ekle yeni', array('stil ekle')),
		'empty query'  => array('', array('ad')),
	);

	$expected = array(
		'adres'        => false,
		'ad'           => true,
		'ad soyad'     => true,
		'ipek'         => false,
		'rek'          => true,
		'reklamlar'    => true,
		'kisayol'      => false,
		'stil ekle'    => true,
		'empty query'  => false,
	);

	$result = panel_search_action_match_run(array_values($cases));

	if ($result === null) {
		pg_assert_true(true, 'Node is not available; the matcher is not run');

		return;
	}

	pg_assert_false(isset($result['error']), 'Node ran the matcher: ' . (isset($result['error']) ? $result['error'] : ''));

	if (isset($result['error'])) {
		return;
	}

	pg_assert_same(array_values($expected), $result, 'which chips each query offers');
}
