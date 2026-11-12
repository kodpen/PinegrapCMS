<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that no script source declares one function name twice in a scope
 * (tests/helpers/js_function_names.php): the scanner on a fixture and on
 * the tricky parts of a script, then every script of the product tree that
 * is not a built bundle or a library.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/js_function_names.php');

// The names of what the scanner found, as "name line/again".
function js_function_names_test_found($source)
{
	return array_map(function ($found) {
		return $found['name'] . ' ' . $found['line'] . '/' . $found['again'];
	}, pg_test_js_duplicate_functions($source));
}

function test_js_function_names_finds_the_twin_of_the_fixture()
{
	$source = (string) file_get_contents(__DIR__ . '/fixtures/js_function_names/twin.js');

	pg_assert_same(array('newPost 18/34'), js_function_names_test_found($source),
		'the later newPost replaces the earlier; a named expression, a block and the text of strings, comments, a regex and a template are other scopes or no code');
}

function test_js_function_names_scopes()
{
	pg_assert_same(array(), js_function_names_test_found("function a() {}\nfunction b() { function a() {} }"), 'a nested function is another scope');
	pg_assert_same(array(), js_function_names_test_found("var x = { a: function a() {} };\nfunction a() {}"), 'a property\'s function is an expression');
	pg_assert_same(array(), js_function_names_test_found("call(function a() {}, function a() {});"), 'arguments are expressions');
	pg_assert_same(array(), js_function_names_test_found("var b = cond ? function a() {} : function a() {};"), 'both sides of a condition are expressions');
	pg_assert_same(array(), js_function_names_test_found("function f() { return function a() {}; }\nfunction a() {}"), 'a returned function is an expression');
	pg_assert_same(array('a 1/2'), js_function_names_test_found("function a() {}\nfunction a() {}"), 'two at the top');
	pg_assert_same(array('a 1/3'), js_function_names_test_found("async function a() {}\nvar x = 1\nfunction a() {}"), 'after async, and after a line without a semicolon');
	pg_assert_same(array('g 1/2'), js_function_names_test_found("function* g() {}\nfunction g() {}"), 'a generator');
}

function test_js_function_names_steps_over_text()
{
	pg_assert_same(array(), js_function_names_test_found("var s = 'function a() {}';\nfunction a() {}"), 'a string');
	pg_assert_same(array(), js_function_names_test_found("var s = \"it's { function a() {}\";\nfunction a() {}"), 'a quote and a brace in a string');
	pg_assert_same(array(), js_function_names_test_found("var r = /{function a\\(/;\nfunction a() {}"), 'a regular expression with a brace');
	pg_assert_same(array(), js_function_names_test_found("var r = /[/{]/g;\nfunction a() {}"), 'a slash and a brace in a class');
	pg_assert_same(array(), js_function_names_test_found('var t = `{ ${ x ? \'}\' : `${ y }` } function a() {}`;' . "\nfunction a() {}"), 'nested templates');
	pg_assert_same(array(), js_function_names_test_found("// function a() {}\n/* { */\nfunction a() {}"), 'comments');
	pg_assert_same(array('b 2/3'), js_function_names_test_found('var half = total / 2; var t = `${ { a: 1 }.a }`;' . "\nfunction b() {}\nfunction b() {}"),
		'a division is no regex and an object in a template closes');
}

function test_js_function_names_in_every_script()
{
	// The visual editor's two findNodeById copies are the same function, so
	// the later one changes nothing; any other twin fails here.
	$known = array('style_designer.src.js' => array('findNodeById'));
	$files = array_merge((array) glob(PG_FUNCTIONS_DIR . '/assets/js/*.js'), (array) glob(PG_FUNCTIONS_DIR . '/*.js'));
	$checked = 0;
	$twins = array();

	foreach ($files as $file) {
		if (substr($file, -7) === '.min.js') {
			continue;
		}

		$checked++;

		foreach (pg_test_js_duplicate_functions((string) file_get_contents($file)) as $found) {
			if (!in_array($found['name'], $known[basename($file)] ?? array(), true)) {
				$twins[] = basename($file) . ': ' . $found['name'] . ' at lines ' . $found['line'] . ' and ' . $found['again'];
			}
		}
	}

	pg_assert_true($checked > 100, 'the scripts were found');
	pg_assert_same(array(), $twins, 'no script declares one function name twice in a scope');
}
