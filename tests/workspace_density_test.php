<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the Workspace density switch (comfortable / compact):
 * the browser key and its two values in assets/js/workspace.js, the class
 * set before the first draw, the compact block of backend.src.css that
 * changes only the spacing tokens under that one class, and the switch's
 * words in the Turkish shards.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_density_test_js()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/js/workspace.js');
}

// The rule bodies of backend.src.css whose selector names .ws-compact.
function workspace_density_test_compact_rules()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');

	preg_match_all('/([^{}]*\.ws-compact[^{}]*)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

	return $matches;
}

function test_workspace_density_key_and_values()
{
	$js = workspace_density_test_js();

	pg_assert_contains("var DENSITY_KEY = 'ws-density';", $js, 'the density is kept under ws-density');
	pg_assert_contains("=== 'compact' ? 'compact' : 'comfortable'", $js, 'compact or comfortable, comfortable by default');
	pg_assert_contains("classList.toggle('ws-compact', value === 'compact')", $js, 'compact sets the one class on the root');
}

function test_workspace_density_applied_before_the_first_draw()
{
	$js = workspace_density_test_js();
	$apply = strpos($js, "\n    applyDensity(readDensity());\n");
	$start = strpos($js, "\n    function start() {\n");

	pg_assert_true($apply !== false, 'the density is applied at the top level of the script');
	pg_assert_true(($apply !== false) && ($start !== false) && ($apply < $start), 'before start() draws anything');
}

function test_workspace_density_switch_on_the_overview()
{
	$js = workspace_density_test_js();

	pg_assert_true((bool) preg_match('/showHome: function \(asked\) \{.*?densityButton\(\).*?\n        \},\n/s', $js), 'the overview header carries the switch');
	pg_assert_contains("writeDensity('compact')", $js, 'the switch writes compact');
	pg_assert_contains("writeDensity('comfortable')", $js, 'the switch writes comfortable');
}

function test_workspace_density_compact_block_narrows_tokens_only()
{
	$rules = workspace_density_test_compact_rules();

	pg_assert_same(1, count($rules), 'one compact block');

	if (!$rules) {
		return;
	}

	foreach (array_filter(array_map('trim', explode(',', $rules[0][1]))) as $selector) {
		pg_assert_true(strpos($selector, 'html.ws-compact ') === 0, 'compact rule scoped under html.ws-compact: ' . $selector);
	}

	$declarations = array_filter(array_map('trim', explode(';', $rules[0][2])));
	$names = array();

	foreach ($declarations as $declaration) {
		$names[] = trim(strtok($declaration, ':'));
	}

	pg_assert_true(count($names) > 0, 'the block declares something');

	foreach ($names as $name) {
		pg_assert_true(strpos($name, '--ws-') === 0, 'only --ws-* tokens change: ' . $name);
	}

	foreach (array('--ws-space-1', '--ws-space-2', '--ws-space-3', '--ws-space-4') as $token) {
		pg_assert_true(in_array($token, $names, true), 'compact narrows ' . $token);
	}
}

function test_workspace_density_words_are_translated()
{
	$shards = glob(dirname(__DIR__) . '/pinegrap/includes/local/tr/*.json');
	$keys = array();

	foreach ((array) $shards as $file) {
		$keys += (array) json_decode((string) file_get_contents($file), true);
	}

	foreach (array('Density', 'Comfortable', 'Compact') as $english) {
		pg_assert_true(isset($keys[$english]), 'Turkish shard has ' . $english);
	}
}
