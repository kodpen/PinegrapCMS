<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a product's texts in the language of the page that asks for them:
 * the variant chooser's refreshes (assets/js/pg_civ_variants.js) carry the
 * page's language, api.php's get_product answers with pg_tr_product_fields(),
 * and a variant's rich-text value in the page's variant data is translated
 * by its blocks (pg_tr_render_json_strings()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// In the source language (no translation context) the row comes back as it is.
function test_translate_product_fields_unchanged_without_a_language()
{
	$row = array('id' => '7', 'short_description' => 'Deneme ürünü', 'full_description' => '<p>Bir.</p>', 'price' => 1000);

	pg_assert_same($row, pg_tr_product_fields($row));
	pg_assert_same('x', pg_tr_product_fields('x'), 'not a row');
}

// Both refreshes post to an address that carries <html data-pg-lang>, the
// answer goes through the translation, and a value with blocks in the
// variant data is translated block by block.
function test_translate_product_answer_wiring()
{
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_civ_variants.js');
	$panel = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/commerce.php');
	$content = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/content.php');

	pg_assert_false(strpos($script, 'fetch(data.api_url') !== false, 'no refresh posts without the language');
	pg_assert_same(2, substr_count($script, 'fetch(apiUrl(), {'), 'cross-sell and product refresh');
	pg_assert_contains("getAttribute('data-pg-lang')", $script, 'the language the page says it is in');

	$from = strpos($panel, 'function pg_panel_get_product(');
	$section = substr($panel, $from, strpos($panel, 'function pg_panel_get_shipping_methods(') - $from);
	pg_assert_contains('$product = pg_tr_product_fields($product);', $section, 'get_product answers in the language');

	$from = strpos($content, 'function pg_tr_render_json_strings(');
	$json = substr($content, $from, 1600);
	pg_assert_contains('pg_tr_render_html_value($decoded)', $json, 'a rich-text value by its blocks');
}
