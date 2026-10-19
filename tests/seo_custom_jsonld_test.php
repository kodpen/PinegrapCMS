<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_page_custom_jsonld_check() in includes/fn/seo.php: what a
 * page's own JSON-LD must be before it is stored (page.custom_jsonld), and
 * the reason given when it is not.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Nothing, or only white space, clears the page's block.
function test_page_jsonld_empty_is_kept_as_none()
{
	pg_assert_same(array('ok' => true, 'value' => '', 'error' => ''), pg_page_custom_jsonld_check(''));
	pg_assert_same(array('ok' => true, 'value' => '', 'error' => ''), pg_page_custom_jsonld_check("  \n\t "));
	pg_assert_same(array('ok' => true, 'value' => '', 'error' => ''), pg_page_custom_jsonld_check(null));
}

// A typed object is kept as written, trimmed, line breaks and all.
function test_page_jsonld_typed_object_is_kept_as_written()
{
	$raw = "{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"FAQPage\",\n  \"name\": \"Sık sorulanlar\"\n}";
	$r = pg_page_custom_jsonld_check("\n" . $raw . "\n\n");

	pg_assert_true($r['ok']);
	pg_assert_same($raw, $r['value']);
	pg_assert_same('', $r['error']);
}

// A list of typed objects, a type given as a list, and an @graph pass.
function test_page_jsonld_lists_type_lists_and_graphs_pass()
{
	pg_assert_true(pg_page_custom_jsonld_check('[{"@type":"Event"},{"@type":"Place"}]')['ok'], 'list');
	pg_assert_true(pg_page_custom_jsonld_check('{"@type":["LocalBusiness","Bakery"]}')['ok'], 'type list');
	pg_assert_true(pg_page_custom_jsonld_check('{"@context":"https://schema.org","@graph":[{"@type":"WebSite"},{"@type":"Organization"}]}')['ok'], 'graph');
}

// Text that does not decode is refused as such.
function test_page_jsonld_broken_json_is_refused()
{
	pg_assert_same('json', pg_page_custom_jsonld_check('{"@type": "Event",}')['error'], 'trailing comma');
	pg_assert_same('json', pg_page_custom_jsonld_check("{'@type': 'Event'}")['error'], 'single quotes');
	pg_assert_same('json', pg_page_custom_jsonld_check('<script type="application/ld+json">{}</script>')['error'], 'script tag');
	pg_assert_false(pg_page_custom_jsonld_check('{"@type": "Event"')['ok'], 'unclosed');
}

// A value that is not an object or a list of objects has no shape to read.
function test_page_jsonld_scalars_and_mixed_lists_are_refused()
{
	pg_assert_same('shape', pg_page_custom_jsonld_check('"FAQPage"')['error'], 'string');
	pg_assert_same('shape', pg_page_custom_jsonld_check('42')['error'], 'number');
	pg_assert_same('shape', pg_page_custom_jsonld_check('null')['error'], 'null');
	pg_assert_same('shape', pg_page_custom_jsonld_check('true')['error'], 'boolean');
	pg_assert_same('shape', pg_page_custom_jsonld_check('[{"@type":"Event"}, "x"]')['error'], 'mixed list');
}

// Every item says what it describes.
function test_page_jsonld_untyped_items_are_refused()
{
	pg_assert_same('type', pg_page_custom_jsonld_check('{}')['error'], 'empty object');
	pg_assert_same('type', pg_page_custom_jsonld_check('[]')['error'], 'empty list');
	pg_assert_same('type', pg_page_custom_jsonld_check('{"@context":"https://schema.org","name":"x"}')['error'], 'no type');
	pg_assert_same('type', pg_page_custom_jsonld_check('{"@type":"  "}')['error'], 'blank type');
	pg_assert_same('type', pg_page_custom_jsonld_check('{"@type":[]}')['error'], 'empty type list');
	pg_assert_same('type', pg_page_custom_jsonld_check('{"@type":["Event", 3]}')['error'], 'type list with a number');
	pg_assert_same('type', pg_page_custom_jsonld_check('[{"@type":"Event"},{"name":"x"}]')['error'], 'one untyped in a list');
	pg_assert_same('type', pg_page_custom_jsonld_check('{"@graph":[{"@type":"WebSite"},{"name":"x"}]}')['error'], 'untyped in the graph');
	pg_assert_same('type', pg_page_custom_jsonld_check('{"@graph":[]}')['error'], 'empty graph');
	pg_assert_same('type', pg_page_custom_jsonld_check('{"@graph":[{"@graph":[{"@type":"Thing"}]}]}')['error'], 'graph inside a graph');
}

// A block past 64 KB is refused before it is decoded.
function test_page_jsonld_too_long_is_refused()
{
	$raw = '{"@type":"Thing","text":"' . str_repeat('a', 65536) . '"}';

	pg_assert_same(array('ok' => false, 'value' => '', 'error' => 'size'), pg_page_custom_jsonld_check($raw));
}

// The reason names the page, and every reason has its own sentence.
function test_page_jsonld_message_names_the_page()
{
	pg_assert_contains('contact', pg_page_custom_jsonld_message('json', 'contact'));

	$sentences = array();
	foreach (array('json', 'shape', 'type', 'size') as $error) {
		$sentences[] = pg_page_custom_jsonld_message($error, 'contact');
	}

	pg_assert_same(4, count(array_unique($sentences)));
}
