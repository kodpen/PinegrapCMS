<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of the one-product push to Google Merchant Center
 * (includes/fn/merchant_center.php): the money and availability values, the
 * product input sent to the supplemental data source, the supplemental source
 * body and its link into the feed source's default rule, the product's issues
 * and the stored supplemental source name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function merchant_push_test_item()
{
	return array(
		'id'           => '15',
		'title'        => 'Mug',
		'price'        => 15990,
		'sale_price'   => null,
		'currency'     => 'try',
		'availability' => 'in_stock',
	);
}

function test_merchant_push_values()
{
	pg_assert_same(array('amountMicros' => '159900000', 'currencyCode' => 'TRY'), pg_merchant_amount(15990, 'try'), 'minor units to micros, as a string');
	pg_assert_same(array('amountMicros' => '0', 'currencyCode' => 'EUR'), pg_merchant_amount(0, 'EUR'));
	pg_assert_same('1000000000000000', pg_merchant_amount(100000000000, 'USD')['amountMicros'], 'no float or 32-bit overflow');

	pg_assert_same('IN_STOCK', pg_merchant_availability('in_stock'));
	pg_assert_same('IN_STOCK', pg_merchant_availability('backorder'), 'as in the feed: back orders keep selling');
	pg_assert_same('OUT_OF_STOCK', pg_merchant_availability('out_of_stock'));

	pg_assert_same('tr', pg_merchant_content_language('tr-TR'));
	pg_assert_same('en', pg_merchant_content_language(''));
	pg_assert_same('tr~TR~15', pg_merchant_product_key('tr', 'TR', '15'));
	pg_assert_same('', pg_merchant_product_key('tr', 'TR', 'a/b'), 'an id the name cannot carry');
}

function test_merchant_push_product_input()
{
	$item = merchant_push_test_item();

	pg_assert_same(array(
		'offerId'           => '15',
		'contentLanguage'   => 'tr',
		'feedLabel'         => 'TR',
		'productAttributes' => array(
			'price'        => array('amountMicros' => '159900000', 'currencyCode' => 'TRY'),
			'availability' => 'IN_STOCK',
		),
	), pg_merchant_product_input($item, 'tr', 'TR'));

	$item['sale_price'] = 12990;
	$item['availability'] = 'out_of_stock';
	$input = pg_merchant_product_input($item, 'tr', 'TR');
	pg_assert_same(array('amountMicros' => '129900000', 'currencyCode' => 'TRY'), $input['productAttributes']['salePrice']);
	pg_assert_same('OUT_OF_STOCK', $input['productAttributes']['availability']);
}

function test_merchant_push_supplement()
{
	pg_assert_same(array(
		'displayName'                   => 'Website price and stock updates',
		'supplementalProductDataSource' => array('contentLanguage' => 'tr', 'feedLabel' => 'TR'),
	), pg_merchant_supplement_body('tr', 'TR', 'Website price and stock updates'));

	$supplement = 'accounts/1/dataSources/9';

	pg_assert_same(array(array('self' => true), array('supplementalDataSourceName' => $supplement)),
		pg_merchant_rule_with_supplement(array('name' => 'accounts/1/dataSources/2'), $supplement), 'no rule yet: the source itself, then the updates');

	$primary = array('primaryProductDataSource' => array('defaultRule' => array('takeFromDataSources' => array(
		array('self' => true),
		array('supplementalDataSourceName' => 'accounts/1/dataSources/5'),
	))));
	pg_assert_same(array(
		array('self' => true),
		array('supplementalDataSourceName' => 'accounts/1/dataSources/5'),
		array('supplementalDataSourceName' => $supplement),
	), pg_merchant_rule_with_supplement($primary, $supplement), 'the shop\'s own links are kept');

	$primary['primaryProductDataSource']['defaultRule']['takeFromDataSources'][] = array('supplementalDataSourceName' => $supplement);
	pg_assert_same(null, pg_merchant_rule_with_supplement($primary, $supplement), 'already linked');
}

function test_merchant_push_product_issues()
{
	pg_assert_same(array('found' => false, 'issues' => array()), pg_merchant_product_issues(null));

	$answer = array(
		'name'          => 'accounts/1/products/tr~TR~15',
		'productStatus' => array('itemLevelIssues' => array(
			array('code' => 'missing_gtin', 'severity' => 'DEMOTED', 'description' => 'Missing GTIN', 'attribute' => 'gtin'),
			array('code' => 'image_link_broken', 'severity' => 'DISAPPROVED', 'description' => "Invalid\nimage", 'documentation' => 'https://support.google.com/merchants/answer/1'),
			array('code' => 'missing_gtin', 'severity' => 'DEMOTED', 'description' => 'Missing GTIN', 'attribute' => 'gtin'),
			array('code' => 'x', 'severity' => 'SOMETHING_NEW', 'description' => 'Note', 'documentation' => 'http://evil.example/'),
			'junk',
		)),
	);

	pg_assert_same(array('found' => true, 'issues' => array(
		array('severity' => 'DISAPPROVED', 'description' => 'Invalid image', 'attribute' => '', 'help' => 'https://support.google.com/merchants/answer/1'),
		array('severity' => 'DEMOTED', 'description' => 'Missing GTIN', 'attribute' => 'gtin', 'help' => ''),
		array('severity' => 'NOT_IMPACTED', 'description' => 'Note', 'attribute' => '', 'help' => ''),
	)), pg_merchant_product_issues($answer), 'worst first, one line per issue, help only on a Google host');
}

function test_merchant_push_state()
{
	pg_assert_same('accounts/1/dataSources/9', pg_merchant_state_parse(array('supplement' => 'accounts/1/dataSources/9'))['supplement']);
	pg_assert_same('', pg_merchant_state_parse(array('supplement' => 'accounts/1/dataSources/x/../2'))['supplement']);
	pg_assert_same('', pg_merchant_state_parse(null)['supplement']);
}
