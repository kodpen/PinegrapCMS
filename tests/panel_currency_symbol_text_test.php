<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The currencies table keeps a symbol as HTML for the pages that print it
 * (&#8378;, &euro;). Where the panel escapes it or writes it as text - h(),
 * jQuery .text(), a Chart.js canvas - it is decoded first (pg_money_text(),
 * html_entity_decode()); escaped as stored, a screen reads "&#8378;0,00".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/source_reader.php');

// The code of a product file, comments left out.
function panel_currency_symbol_text_code($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

	return implode('', array_map('pg_test_source_text', pg_test_source_tokens($source)));
}

// h() around money written with a stored symbol, or around the stored
// symbol itself, escapes its entity a second time.
function test_panel_currency_symbol_is_never_escaped_as_stored()
{
	$root = PG_FUNCTIONS_DIR;
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	$pattern = '/\bh\(\s*(?:(?:pg_format_money|pg_visitor_money|prepare_price_for_output|prepare_amount)\s*\(|(?:\(string\)\s*)?(?:BASE|VISITOR)_CURRENCY_SYMBOL\s*\))/';
	$scanned = 0;
	$found = array();

	foreach ($iterator as $file) {
		if ($file->getExtension() !== 'php') {
			continue;
		}

		$relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

		if ((strpos($relative, 'data/') === 0) || (strpos($relative, 'includes/local/') === 0)) {
			continue;
		}

		$scanned++;

		if (!preg_match($pattern, (string) file_get_contents($file->getPathname()))) {
			continue;
		}

		if (preg_match_all($pattern, panel_currency_symbol_text_code($relative), $matches)) {
			foreach ($matches[0] as $match) {
				$found[] = $relative . ': ' . $match;
			}
		}
	}

	pg_assert_true($scanned > 500, 'the product tree was read');
	pg_assert_same(array(), $found, 'no h() of money text or of a symbol as stored');
}

// The container form's Summary writes the cost with jQuery .text(), which
// shows an entity as it is: data-symbol carries the character.
function test_panel_currency_symbol_container_summary()
{
	$code = panel_currency_symbol_text_code('includes/forms/container_form.php');

	pg_assert_contains("data-symbol=\"' . h(html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '\"", $code, 'decoded, then escaped for the attribute');
	pg_assert_contains('$cost.text(cost === "" ? "" : $cost.attr("data-symbol") + cost);', $code, 'written as text');
}

// The orders list's spark chart draws its tooltip on the canvas, where an
// entity is not read; its data is printed into a script, so a tag in it is
// escaped.
function test_panel_currency_symbol_orders_chart_tooltip()
{
	$code = panel_currency_symbol_text_code('view_orders.php');
	$data = pg_test_source_array_in($code, '$orders_chart_data');

	pg_assert_true(isset($data['currency']) && ($data['currency'] instanceof PgTestSourceExpression), 'the chart data has a computed symbol');
	pg_assert_same("html_entity_decode(BASE_CURRENCY_SYMBOL, ENT_QUOTES | ENT_HTML5, 'UTF-8')", isset($data['currency']->code) ? $data['currency']->code : null, 'the character');
	pg_assert_contains('var data = \' . json_encode($orders_chart_data, JSON_HEX_TAG | JSON_HEX_AMP) . \';', $code, 'no tag in the data can end the script');
	pg_assert_contains('return data.currency + Number(value)', $code, 'the tooltip writes it before the figure');
}
