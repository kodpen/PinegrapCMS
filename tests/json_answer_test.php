<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the JSON encoder of the external API's and the MCP server's
 * answers (pg_json_encode_answer() in includes/fn/core.php) and the walk that
 * replaces broken UTF-8 bytes where json_encode() cannot (PHP 7.1).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every string, keys included, that is not UTF-8 gets U+FFFD in place of its
// broken bytes; valid strings and every other value stay as they were.
function test_json_utf8_substitute_replaces_keys_and_values()
{
	$value = array(
		"k\xFF"  => "x\xFFy",
		'ok'     => 'Çağla & <b> \'a\' "b" &amp; &#039;',
		'n'      => 1,
		'f'      => 1.5,
		'inf'    => INF,
		'b'      => true,
		'none'   => null,
		'list'   => array("\xC4", 'a'),
		'nested' => array('deep' => array("d\xFE" => "Sat\xC4"))
	);

	pg_assert_same(array(
		"k\u{FFFD}" => "x\u{FFFD}y",
		'ok'        => 'Çağla & <b> \'a\' "b" &amp; &#039;',
		'n'         => 1,
		'f'         => 1.5,
		'inf'       => INF,
		'b'         => true,
		'none'      => null,
		'list'      => array("\u{FFFD}", 'a'),
		'nested'    => array('deep' => array("d\u{FFFD}" => "Sat\u{FFFD}"))
	), pg_json_utf8_substitute($value));

	pg_assert_same('x &amp; <b> \'a\' "b" &#039;' . "\u{FFFD}", pg_json_utf8_substitute('x &amp; <b> \'a\' "b" &#039;' . "\xFF"), 'entities and quotes in a broken string come back as written');
}

// An object is walked through its public properties and stays an object, an
// empty one included, so it is still written as {} and never as [].
function test_json_utf8_substitute_keeps_objects()
{
	$object = new stdClass();
	$object->{"p\xFE"} = "q\xFF";
	$object->list = array("\xC4");
	$object->empty = new stdClass();

	pg_assert_same('{"p' . "\u{FFFD}" . '":"q' . "\u{FFFD}" . '","list":["' . "\u{FFFD}" . '"],"empty":{}}', json_encode(pg_json_utf8_substitute($object), JSON_UNESCAPED_UNICODE));
	pg_assert_same('{"a":[],"o":{}}', json_encode(pg_json_utf8_substitute(array('a' => array(), 'o' => new stdClass()))), 'an empty array stays a list, an empty object an object');
}

// A key, not only a value, that is not UTF-8 is written as U+FFFD on every
// PHP version; before, PHP 7.1 wrote it as a bare null, which is not JSON.
function test_json_encode_answer_substitutes_a_broken_key()
{
	pg_assert_same('{"k' . "\u{FFFD}" . '":"v","ok":1}', pg_json_encode_answer(array("k\xFF" => 'v', 'ok' => 1)));
	pg_assert_same('{"result":{"structuredContent":{"k' . "\u{FFFD}" . '":"v"}}}', pg_json_encode_answer(array('result' => (object)array('structuredContent' => array("k\xFF" => 'v')))), 'inside an object');
	pg_assert_same('{"note":"x' . "\u{FFFD}" . 'y","name":"Çağla/Ünal"}', pg_json_encode_answer(array('note' => "x\xFFy", 'name' => 'Çağla/Ünal')), 'a broken value, unescaped unicode and slashes');
}

// A broken byte never hides what JSON cannot hold at all: INF, NAN or depth
// past 512 next to it is still null, in either order, nested too.
function test_json_encode_answer_broken_byte_hides_no_other_error()
{
	pg_assert_same(null, pg_json_encode_answer(array('a' => "x\xFFy", 'n' => INF)), 'a broken byte before INF');
	pg_assert_same(null, pg_json_encode_answer(array('n' => INF, 'a' => "x\xFFy")), 'INF before a broken byte');
	pg_assert_same(null, pg_json_encode_answer(array('a' => "x\xFFy", 'r' => array('n' => NAN))), 'a nested NAN');
	pg_assert_same(null, pg_json_encode_answer(array("k\xFF" => 'v', 'n' => -INF)), 'a broken key next to -INF');

	$deep = "x\xFFy";

	for ($level = 0; $level < 600; $level++) {
		$deep = array($deep);
	}

	pg_assert_same(null, pg_json_encode_answer($deep), 'deeper than 512 with a broken leaf');
}
