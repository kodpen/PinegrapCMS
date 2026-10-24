<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_json_duplicate_keys() (includes/fn/core.php), which
 * tools/check_lang.php uses to refuse a tr.json that holds a key twice:
 * json_decode() keeps the last copy without a word, so a second copy with
 * another value hides a translation from every check that reads the decoded
 * file.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A file in the layout of tr.json: one key per line, no duplicate.
function test_lang_file_duplicates_none_in_clean_file()
{
	$json = "{\n\t\"Cart\": \"Sepet\",\n\t\"Order\": \"Sipariş\",\n    \"Save\": \"Kaydet\"\n}\n";

	pg_assert_same(array(), pg_json_duplicate_keys($json));
}

// The same key twice with the same value: the key and both lines, values equal.
function test_lang_file_duplicates_same_value_reports_both_lines()
{
	$json = "{\n"
		. "\t\"Cart\": \"Sepet\",\n"
		. "\t\"This page is not available.\": \"Bu sayfa kullanılamıyor.\",\n"
		. "\t\"Order\": \"Sipariş\",\n"
		. "\t\"This page is not available.\": \"Bu sayfa kullanılamıyor.\"\n"
		. "}\n";

	pg_assert_same(
		array(array('key' => 'This page is not available.', 'lines' => array(3, 5), 'values_differ' => false)),
		pg_json_duplicate_keys($json));
}

// The copies disagree: json_decode() would keep "Sepetim" and hide "Sepet".
function test_lang_file_duplicates_different_values_are_flagged()
{
	$json = "{\n\t\"Cart\": \"Sepet\",\n\t\"Order\": \"Sipariş\",\n\t\"Cart\": \"Sepetim\"\n}";

	pg_assert_same(
		array(array('key' => 'Cart', 'lines' => array(2, 4), 'values_differ' => true)),
		pg_json_duplicate_keys($json));
}

// A key written three times is one finding with every line.
function test_lang_file_duplicates_three_copies_one_finding()
{
	$json = "{\n\"A\": \"1\",\n\"A\": \"1\",\n\"B\": \"2\",\n\"A\": \"1\"\n}";

	pg_assert_same(
		array(array('key' => 'A', 'lines' => array(2, 3, 5), 'values_differ' => false)),
		pg_json_duplicate_keys($json));
}

// Two duplicates are reported in the order their first copies appear, not in
// the order their second copies do.
function test_lang_file_duplicates_ordered_by_first_copy()
{
	$json = "{\n\"First\": \"1\",\n\"Second\": \"2\",\n\"Second\": \"2\",\n\"First\": \"1\"\n}";

	$found = pg_json_duplicate_keys($json);

	pg_assert_same(2, count($found));
	pg_assert_same('First', isset($found[0]['key']) ? $found[0]['key'] : null);
	pg_assert_same(array(2, 5), isset($found[0]['lines']) ? $found[0]['lines'] : null);
	pg_assert_same('Second', isset($found[1]['key']) ? $found[1]['key'] : null);
	pg_assert_same(array(3, 4), isset($found[1]['lines']) ? $found[1]['lines'] : null);
}

// json_decode() reads "\u00e7" and "ç" as the same key, so they clash; values
// that only differ in how a character is escaped are the same value.
function test_lang_file_duplicates_escaped_key_is_the_same_key()
{
	$json = "{\n\"Se\\u00e7\": \"Se\\u00e7im\",\n\"Seç\": \"Seçim\"\n}";

	pg_assert_same(
		array(array('key' => 'Seç', 'lines' => array(2, 3), 'values_differ' => false)),
		pg_json_duplicate_keys($json));
}

// An outer key whose first copy comes before a nested object's duplicate is
// reported first, although the nested object closes before the outer one.
function test_lang_file_duplicates_nested_ordered_by_first_copy()
{
	$found = pg_json_duplicate_keys('{"a":1,"o":{"x":1,"x":2},"a":2}');

	pg_assert_same(
		array(
			array('key' => 'a', 'lines' => array(1, 1), 'values_differ' => true),
			array('key' => 'x', 'lines' => array(1, 1), 'values_differ' => true),
		),
		$found);
}

// A string of a million escapes is read to its end, so the duplicate after
// it is still found.
function test_lang_file_duplicates_long_escaped_string()
{
	$json = "{\n\"A\": \"" . str_repeat('\\"', 1000000) . "\",\n\"A\": \"x\"\n}";

	pg_assert_same(
		array(array('key' => 'A', 'lines' => array(2, 3), 'values_differ' => true)),
		pg_json_duplicate_keys($json));
}

// Quotes, braces, colons and commas inside strings are text, not structure.
function test_lang_file_duplicates_punctuation_inside_strings()
{
	$json = "{\n"
		. "\"Say \\\"hi\\\", {var:1}: ok\": \"\\\"Selam\\\" de, {var:1}: tamam\",\n"
		. "\"Path \\\\ \\\"x\\\"\": \"}{][,:\",\n"
		. "\"Say \\\"hi\\\", {var:1}: ok\": \"\\\"Merhaba\\\" de, {var:1}: tamam\"\n"
		. "}";

	pg_assert_same(
		array(array('key' => 'Say "hi", {var:1}: ok', 'lines' => array(2, 4), 'values_differ' => true)),
		pg_json_duplicate_keys($json));
}

// A key is only a duplicate inside one object: the same name in two sibling
// objects, or in the objects of an array, is not one.
function test_lang_file_duplicates_scoped_to_one_object()
{
	$json = "{\n"
		. "\"one\": {\"name\": \"a\", \"size\": 1},\n"
		. "\"two\": {\"name\": \"b\"},\n"
		. "\"list\": [{\"name\": \"c\"}, {\"name\": \"d\"}]\n"
		. "}";

	pg_assert_same(array(), pg_json_duplicate_keys($json));
}

// A duplicate inside a nested object is found, and a nested object or array
// as a value is compared as a whole.
function test_lang_file_duplicates_nested_values_compared_whole()
{
	$json = "{\n"
		. "\"outer\": {\n"
		. "  \"name\": \"a\",\n"
		. "  \"name\": \"a\"\n"
		. "},\n"
		. "\"block\": {\"x\": [1, 2]},\n"
		. "\"block\": {\"x\": [1, 3]},\n"
		. "\"same\": [true, null],\n"
		. "\"same\": [ true , null ]\n"
		. "}";

	pg_assert_same(
		array(
			array('key' => 'name', 'lines' => array(3, 4), 'values_differ' => false),
			array('key' => 'block', 'lines' => array(6, 7), 'values_differ' => true),
			array('key' => 'same', 'lines' => array(8, 9), 'values_differ' => false),
		),
		pg_json_duplicate_keys($json));
}

// Minified JSON on one line: both copies on line 1; numbers, booleans and
// null compare by their decoded values.
function test_lang_file_duplicates_single_line_and_literals()
{
	pg_assert_same(
		array(
			array('key' => 'n', 'lines' => array(1, 1), 'values_differ' => true),
			array('key' => 'b', 'lines' => array(1, 1), 'values_differ' => false),
		),
		pg_json_duplicate_keys('{"n":1,"b":true,"n":2,"b":true,"z":null}'));
}

// Windows line endings count one line each, and an empty key is a key.
function test_lang_file_duplicates_crlf_and_empty_key()
{
	$json = "{\r\n\"\": \"x\",\r\n\"k\": \"v\",\r\n\"\": \"y\"\r\n}\r\n";

	pg_assert_same(
		array(array('key' => '', 'lines' => array(2, 4), 'values_differ' => true)),
		pg_json_duplicate_keys($json));
}

// Not an object at the top, or empty: nothing to report, and no warning.
function test_lang_file_duplicates_non_object_input()
{
	pg_assert_same(array(), pg_json_duplicate_keys(''));
	pg_assert_same(array(), pg_json_duplicate_keys('{}'));
	pg_assert_same(array(), pg_json_duplicate_keys('["a", "a"]'));
	pg_assert_same(array(), pg_json_duplicate_keys('"a"'));
}

// Text that is not valid JSON cannot be read to its end: null, not a clean
// result that would let a broken file pass.
function test_lang_file_duplicates_invalid_json_is_null()
{
	pg_assert_same(null, pg_json_duplicate_keys('{"a": "1", "a": "2"'));
	pg_assert_same(null, pg_json_duplicate_keys('{"a": "unterminated}'));
	pg_assert_same(null, pg_json_duplicate_keys('{"a": 1}}'));
	pg_assert_same(null, pg_json_duplicate_keys('{"a": [1, 2}'));
}
