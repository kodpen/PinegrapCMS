<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the code editor helpers of a panel screen
 * (includes/fn/editor.php): the language a code type maps to and the JSON
 * printed into an inline script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The names get_codemirror_javascript() takes, and CodeMirror 5's MIME
// names, land on the four grammars the facade carries.
function test_code_editor_language_maps_the_code_types()
{
	pg_assert_same('css', pg_code_editor_language('css'), 'css');
	pg_assert_same('css', pg_code_editor_language('text/css'), 'text/css');
	pg_assert_same('javascript', pg_code_editor_language('javascript'), 'javascript');
	pg_assert_same('javascript', pg_code_editor_language('js'), 'js');
	pg_assert_same('javascript', pg_code_editor_language('text/javascript'), 'text/javascript');
	pg_assert_same('json', pg_code_editor_language('application/json'), 'application/json');
	pg_assert_same('json', pg_code_editor_language('json'), 'json');
	pg_assert_same('html', pg_code_editor_language('mixed'), 'mixed');
	pg_assert_same('html', pg_code_editor_language('php'), 'php');
	pg_assert_same('html', pg_code_editor_language('xml'), 'xml');
	pg_assert_same('html', pg_code_editor_language('svg'), 'svg');
	pg_assert_same('html', pg_code_editor_language('text/plain'), 'text/plain');
}

// Case and surrounding space do not change the answer; an unknown or empty
// type is shown as HTML, which is what CodeMirror 5 fell back to.
function test_code_editor_language_tolerates_case_space_and_unknown_types()
{
	pg_assert_same('css', pg_code_editor_language(' CSS '), 'upper case with spaces');
	pg_assert_same('javascript', pg_code_editor_language('JavaScript'), 'mixed case');
	pg_assert_same('html', pg_code_editor_language(''), 'empty');
	pg_assert_same('html', pg_code_editor_language(null), 'null');
	pg_assert_same('html', pg_code_editor_language('clike.java'), 'a type the facade does not carry');
}

// A translated phrase printed into an inline <script> can never end the
// script element or start a tag.
function test_code_editor_json_escapes_markup()
{
	$json = pg_code_editor_json(array('phrases' => array('close' => '</script><b>&')));

	pg_assert_false(strpos($json, '</script>') !== false, 'no closing script tag');
	pg_assert_false(strpos($json, '<b>') !== false, 'no tag');
	pg_assert_same(array('phrases' => array('close' => '</script><b>&')), json_decode($json, true), 'the value survives the round trip');
	pg_assert_same('"ağaç"', pg_code_editor_json('ağaç'), 'UTF-8 stays readable');
}

// Plain text keeps the HTML colours but not the HTML lint: a stray < in a
// list of options is not an error.
function test_code_editor_is_plain_names_the_text_types()
{
	pg_assert_true(pg_code_editor_is_plain('plain'), 'plain');
	pg_assert_true(pg_code_editor_is_plain('text'), 'text');
	pg_assert_true(pg_code_editor_is_plain('text.txt'), 'text.txt');
	pg_assert_true(pg_code_editor_is_plain('text/plain'), 'text/plain');
	pg_assert_false(pg_code_editor_is_plain('mixed'), 'mixed');
	pg_assert_false(pg_code_editor_is_plain('php'), 'php');
	pg_assert_false(pg_code_editor_is_plain(''), 'empty');
}

// The source of the facade's syntax linter, up to the next function of the
// facade.
function code_editor_test_linter_source()
{
	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, 'function _syntaxLinter(');

	if ($from === false) {
		return '';
	}

	$next = strpos($facade, "\n    function ", $from + 10);

	return ($next !== false) ? substr($facade, $from, $next - $from) : substr($facade, $from);
}

// An empty field is not a syntax error: the HTML parser leaves a zero-length
// error node at the start of an empty document and the JSON parser reports
// an unexpected end, so the linter checks nothing until there is text (for
// JSON, more than white space).
function test_code_editor_lint_skips_an_empty_document()
{
	$linter = code_editor_test_linter_source();

	pg_assert_true($linter !== '', 'the linter is found');

	$empty = strpos($linter, 'if (view.state.doc.length === 0) return out;');
	$tree = strpos($linter, 'cm.language.syntaxTree(view.state)');

	pg_assert_true(($empty !== false) && ($tree !== false) && ($empty < $tree), 'an empty document is not parsed for errors');

	$blank = strpos($linter, '/\S/.test(view.state.doc.toString()) ? json(view) : []');

	pg_assert_true($blank !== false, 'a blank JSON document is not handed to the JSON parser');
}
