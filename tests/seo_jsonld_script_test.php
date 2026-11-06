<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_jsonld_script_json() and pg_custom_jsonld_block() in
 * includes/fn/seo.php: JSON-LD printed inside <script type=
 * "application/ld+json"> cannot open a comment or a nested script in the
 * HTML tokenizer, so a visitor's question or a record title holding
 * "<!--<script>" does not swallow the rest of the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// No < or > is left in the text, & is written as an escape too, and the
// value decodes back unchanged.
function test_jsonld_script_json_hides_markup()
{
	$data = array('name' => '<!--<script>x</script> & "Çay"', 'url' => 'https://example.com/a/b');
	$json = pg_jsonld_script_json($data);

	pg_assert_same(false, strpos($json, '<'), 'no <');
	pg_assert_same(false, strpos($json, '>'), 'no >');
	pg_assert_same(false, strpos($json, '&'), 'no &');
	pg_assert_true(strpos($json, 'Çay') !== false, 'unicode kept');
	pg_assert_true(strpos($json, 'https://example.com/a/b') !== false, 'slashes kept');
	pg_assert_same($data, json_decode($json, true));
}

// The page's own block: a value holding a comment opener and a script tag
// leaves exactly one <script and one </script> in the printed block.
function test_custom_jsonld_block_keeps_one_script_element()
{
	$block = pg_custom_jsonld_block('{"@type":"Event","name":"<!--<script>alert(1)</script>"}');

	pg_assert_same(1, substr_count(strtolower($block), '<script'), 'one opening tag');
	pg_assert_same(1, substr_count(strtolower($block), '</script'), 'one closing tag');
	pg_assert_same(false, strpos($block, '<!--<'), 'no comment opener inside');
}
