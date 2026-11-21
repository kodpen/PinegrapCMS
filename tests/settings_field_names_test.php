<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for the field names of Settings › SEO. The structured-data
 * card (includes/settings/prep.php) and the Merchant Center country, once on
 * the Product Feeds card and now on Merchant Center's page of Connections,
 * were posted in one form: PHP keeps the last value of a repeated name, and
 * a repeated id sends a label's for= and getElementById to the first
 * element, so the two share neither.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_field_names_source($path)
{
	$source = file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
	pg_assert_true(is_string($source) && $source !== '', $path . ' readable');
	return (string) $source;
}

// Values of one attribute on the form controls of a source.
function settings_field_names_attr($source, $attr)
{
	preg_match_all('/<(?:input|select|textarea)\b[^>]*\b' . $attr . '="([A-Za-z][\w\[\]-]*)"/', $source, $found);
	return array_values(array_unique($found[1]));
}

// The structured-data card and the Merchant Center page share no name or id,
// and the Product Feeds card posts no Merchant Center field.
function test_settings_field_names_seo_cards_apart()
{
	$prep = settings_field_names_source('includes/settings/prep.php');
	$card = settings_field_names_source('includes/settings/commerce_merchant.php');
	$source = settings_field_names_source('connections.php');
	$start = strpos($source, "\nfunction connections_merchant_page(");
	pg_assert_true($start !== false, 'Merchant Center\'s page');
	$merchant = substr($source, $start, 12000);

	pg_assert_same(array(), settings_field_names_attr($card, 'name'), 'the card posts nothing of Merchant Center');

	foreach (array('name', 'id') as $attr) {
		$shared = array_intersect(settings_field_names_attr($prep, $attr), settings_field_names_attr($merchant, $attr));
		pg_assert_same(array(), array_values($shared), 'no shared ' . $attr);
	}

	pg_assert_true(strpos($merchant, 'name="merchant_center_country" id="merchant_center_country"') !== false, 'Merchant page field');
	pg_assert_true(strpos($merchant, '<label for="merchant_center_country"') !== false, 'Merchant page label');
	pg_assert_true(strpos($prep, 'name="merchant_country" id="merchant_country"') !== false, 'structured-data field');
}

// Each save reads its own field.
function test_settings_field_names_seo_save_reads()
{
	$save = settings_field_names_source('includes/settings/seo.save.php') . settings_field_names_source('includes/settings/commerce.save.php');

	pg_assert_true(preg_match('/\'country\'\s*=>\s*\$merchant_post\(\'merchant_center_country\'\)/', settings_field_names_source('connections.php')) === 1, 'Merchant save reads merchant_center_country');
	pg_assert_true(preg_match("/\\\$merchant_country_input = .*post_value\('merchant_country'\)/", $save) === 1, 'config save reads merchant_country');
}
