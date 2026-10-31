<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the texts of the products list's view picker and of the Mass
 * Change window (edit_products.php): the price change methods show their
 * sample amount and percent in the panel's number format, and the views and
 * messages use readable English keys.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_INIT_LOADED')) {
	define('PG_INIT_LOADED', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/product_list.php');

function test_product_mass_price_methods_sample_in_panel_format()
{
	$options = pg_product_mass_price_method_options('$');
	$amount = pg_format_money(30, '$');
	$percent = lang(array('string' => '{var:1}%', 'vars' => array(pg_format_number(30, 0))));

	pg_assert_same('2', $options[lang(array('string' => 'Increase by an amount (e.g. {var:1})', 'vars' => array('+ ' . $amount)))]);
	pg_assert_same('1', $options[lang(array('string' => 'Decrease by an amount (e.g. {var:1})', 'vars' => array('- ' . $amount)))]);
	pg_assert_same('4', $options[lang(array('string' => 'Increase by a percentage (e.g. {var:1})', 'vars' => array('+ ' . $percent)))]);
	pg_assert_same('3', $options[lang(array('string' => 'Decrease by a percentage (e.g. {var:1})', 'vars' => array('- ' . $percent)))]);
	pg_assert_same('', reset($options), 'the prompt comes first and selects nothing');
	pg_assert_same(5, count($options));
}

function test_product_mass_price_methods_no_fixed_sample()
{
	foreach (array_keys(pg_product_mass_price_method_options('$')) as $label) {
		pg_assert_false(strpos($label, '30.00') !== false && pg_number_separators()['decimal'] !== '.', 'the sample follows the panel separators: ' . $label);
		pg_assert_false(strpos($label, '30%') !== false && lang(array('string' => '{var:1}%', 'vars' => array('30'))) !== '30%', 'the percent follows the panel format: ' . $label);
	}
}

function test_product_list_texts_use_readable_keys()
{
	$filters = pg_product_list_filters();
	pg_assert_same(lang('Actions After an Order'), $filters['all_product_actions']['label']);

	$list = file_get_contents(PG_FUNCTIONS_DIR . '/view_products.php');
	pg_assert_false(strpos($list, 'Content that viewed') !== false, 'the view picker has a readable name');
	pg_assert_true(strpos($list, "lang('Products shown')") !== false);

	$mass = file_get_contents(PG_FUNCTIONS_DIR . '/edit_products.php');
	pg_assert_false(strpos($mass, 'decerease') !== false, 'the price refusal key is readable English');
	pg_assert_false(strpos($mass, 'Quantity(') !== false, 'a space before the sign');
}
