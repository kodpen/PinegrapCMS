<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/merchant_center.php: the data
 * source request body built from the feed address, finding the data source
 * that already fetches the feed, the issue summary of a Merchant API answer,
 * the cache window, the stored state and the System Status rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// An accounts.aggregateProductStatuses.list answer: one product listed for
// two reporting contexts, so its issue appears twice.
function merchant_center_test_statuses()
{
	return array(
		array(
			'name'             => 'accounts/123/aggregateProductStatuses/SHOPPING_ADS~TR',
			'reportingContext' => 'SHOPPING_ADS',
			'country'          => 'TR',
			'stats'            => array('activeCount' => '40', 'pendingCount' => '2', 'disapprovedCount' => '3', 'expiringCount' => '0'),
			'itemLevelIssues'  => array(
				array('code' => 'image_link_broken', 'severity' => 'DISAPPROVED', 'resolution' => 'MERCHANT_ACTION', 'attribute' => 'image link',
					'description' => 'Invalid image [image link]', 'documentationUri' => 'https://support.google.com/merchants/answer/6098289', 'productCount' => '3'),
				array('code' => 'missing_gtin', 'severity' => 'DEMOTED', 'description' => 'Missing <b>GTIN</b>', 'productCount' => '12',
					'documentationUri' => 'javascript:alert(1)'),
				array('code' => 'low_image_quality', 'severity' => 'NOT_IMPACTED', 'description' => "Low image\nquality", 'productCount' => '7'),
			),
		),
		array(
			'name'             => 'accounts/123/aggregateProductStatuses/FREE_LISTINGS~TR',
			'reportingContext' => 'FREE_LISTINGS',
			'country'          => 'TR',
			'stats'            => array('activeCount' => '42', 'pendingCount' => '1', 'disapprovedCount' => '1', 'expiringCount' => '5'),
			'itemLevelIssues'  => array(
				array('code' => 'missing_gtin', 'severity' => 'DISAPPROVED', 'description' => 'Missing <b>GTIN</b>', 'productCount' => '4'),
				array('code' => 'price_mismatch', 'severity' => 'SEVERITY_UNSPECIFIED', 'description' => 'Price mismatch', 'productCount' => '1'),
			),
		),
	);
}

function test_merchant_center_data_source_body()
{
	$body = pg_merchant_data_source_body('https://shop.example/pinegrap/feed.php?f=abc', 'tr-TR', 'TR', 'Europe/Istanbul', 'Website product feed');

	pg_assert_same('Website product feed', $body['displayName']);
	pg_assert_same(array('contentLanguage' => 'tr', 'feedLabel' => 'TR', 'countries' => array('TR')), $body['primaryProductDataSource'], 'language cut to two letters, the country is the feed label');
	pg_assert_same(array(
		'enabled'   => true,
		'fetchUri'  => 'https://shop.example/pinegrap/feed.php?f=abc',
		'frequency' => 'FREQUENCY_DAILY',
		'timeOfDay' => array('hours' => PG_MERCHANT_FETCH_HOUR),
		'timeZone'  => 'Europe/Istanbul',
	), $body['fileInput']['fetchSettings']);
	pg_assert_false(isset($body['fileInput']['fileName']), 'a fetched file has no upload name');

	$plain = pg_merchant_data_source_body('https://shop.example/feed.php?f=x', '', 'us', '', 'Feed');
	pg_assert_same('en', $plain['primaryProductDataSource']['contentLanguage'], 'no language falls back to English');
	pg_assert_same('US', $plain['primaryProductDataSource']['feedLabel']);
	pg_assert_false(isset($plain['fileInput']['fetchSettings']['timeZone']), 'no time zone leaves Google\'s default');
	pg_assert_same('{"enabled":true', substr(json_encode($plain['fileInput']['fetchSettings']), 0, 15), 'enabled is a JSON boolean');
}

function test_merchant_center_find_source_by_url()
{
	$url = 'https://shop.example/pinegrap/feed.php?f=new';
	$sources = array(
		array('name' => 'accounts/123/dataSources/1', 'displayName' => 'API', 'input' => 'API'),
		array('name' => 'accounts/123/dataSources/2', 'fileInput' => array('fetchSettings' => array('fetchUri' => 'https://shop.example/pinegrap/feed.php?f=old'))),
		array('name' => 'accounts/123/dataSources/3', 'fileInput' => array('fetchSettings' => array('fetchUri' => $url))),
	);

	pg_assert_same(array('name' => 'accounts/123/dataSources/3', 'same_url' => true), pg_merchant_find_source($sources, $url), 'same address is kept');
	pg_assert_same(array('name' => 'accounts/123/dataSources/3', 'same_url' => true), pg_merchant_find_source($sources, $url, 'accounts/123/dataSources/2'), 'an exact address wins over the stored name');
	pg_assert_same(array('name' => 'accounts/123/dataSources/2', 'same_url' => false), pg_merchant_find_source(array_slice($sources, 0, 2), $url, 'accounts/123/dataSources/2'), 'the source registered before is moved to the new address');
	pg_assert_same(array('name' => '', 'same_url' => false), pg_merchant_find_source(array_slice($sources, 0, 2), $url), 'another address and no stored name: create');
	pg_assert_same(array('name' => '', 'same_url' => false), pg_merchant_find_source(array('x', array('fileInput' => array())), $url), 'malformed entries are skipped');
}

function test_merchant_center_issue_summary()
{
	$summary = pg_merchant_issue_summary(merchant_center_test_statuses());

	pg_assert_same(array('DISAPPROVED' => 2, 'DEMOTED' => 0, 'NOT_IMPACTED' => 2), $summary['counts'], 'issue kinds per worst severity; an unknown severity does not impact');
	pg_assert_same(array('active' => 42, 'pending' => 2, 'disapproved' => 3, 'expiring' => 5), $summary['products'], 'the largest figure among the contexts');
	pg_assert_same(4, count($summary['top']));

	pg_assert_same('missing_gtin', $summary['top'][0]['code'], 'worst severity first, then most products');
	pg_assert_same('DISAPPROVED', $summary['top'][0]['severity'], 'demoted in one context, disapproved in another: disapproved');
	pg_assert_same(12, $summary['top'][0]['products'], 'a product in two contexts is not counted twice');
	pg_assert_same('Missing <b>GTIN</b>', $summary['top'][0]['description'], 'kept raw; the tile escapes it');
	pg_assert_same('', $summary['top'][0]['help'], 'a script address is dropped');

	pg_assert_same('image_link_broken', $summary['top'][1]['code']);
	pg_assert_same('https://support.google.com/merchants/answer/6098289', $summary['top'][1]['help']);
	pg_assert_same('image link', $summary['top'][1]['attribute']);
	pg_assert_same('Low image quality', $summary['top'][2]['description'], 'line breaks become spaces');

	pg_assert_same(2, count(pg_merchant_issue_summary(merchant_center_test_statuses(), 2)['top']), 'only the top issues are kept');
	pg_assert_same(array('DISAPPROVED' => 0, 'DEMOTED' => 0, 'NOT_IMPACTED' => 0), pg_merchant_issue_summary(array('x', array('itemLevelIssues' => 'y')))['counts'], 'malformed answer');
}

function test_merchant_center_issue_detail_rows()
{
	$rows = pg_merchant_issue_detail(pg_merchant_issue_summary(merchant_center_test_statuses()));

	pg_assert_same('Missing <b>GTIN</b>', $rows[0]['label'], 'raw text: the widget escapes the row with h()');
	pg_assert_same('fail', $rows[0]['state']);
	pg_assert_same('Invalid image [image link]', $rows[1]['label'], 'an attribute the description names is not repeated');
	$summary = pg_merchant_issue_summary(array(array('itemLevelIssues' => array(array('code' => 'c', 'severity' => 'DEMOTED', 'attribute' => 'gtin', 'description' => 'Missing value', 'productCount' => '2')))));
	pg_assert_same('Missing value [gtin]', pg_merchant_issue_detail($summary)[0]['label'], 'the attribute follows the description');
	pg_assert_same('info', $rows[2]['state']);
	pg_assert_contains('7', $rows[2]['when']);
}

function test_merchant_center_help_url()
{
	pg_assert_same('https://support.google.com/merchants/answer/1', pg_merchant_help_url('https://support.google.com/merchants/answer/1'));
	pg_assert_same('', pg_merchant_help_url('http://support.google.com/x'), 'plain http');
	pg_assert_same('', pg_merchant_help_url('https://evil.example/google.com'), 'another host');
	pg_assert_same('', pg_merchant_help_url('https://notgoogle.com/x'), 'a host that only ends in google.com');
	pg_assert_same('', pg_merchant_help_url('https://support.google.com/a"onmouseover="x'), 'a quote');
	pg_assert_same('', pg_merchant_help_url(array('x')));
}

function test_merchant_center_cache_window()
{
	$now = 1800000000;

	pg_assert_true(pg_merchant_issues_due(0, $now), 'never read');
	pg_assert_false(pg_merchant_issues_due($now - 60, $now), 'read a minute ago');
	pg_assert_false(pg_merchant_issues_due($now - PG_MERCHANT_ISSUES_TTL + 1, $now), 'just inside the window');
	pg_assert_true(pg_merchant_issues_due($now - PG_MERCHANT_ISSUES_TTL, $now), 'the window has passed');
	pg_assert_true(pg_merchant_issues_due($now + 3600, $now), 'a time ahead of the clock');
	pg_assert_true(PG_MERCHANT_ISSUES_TTL < 86400, 'the daily job always finds a reading due');
}

function test_merchant_center_state_parse()
{
	$state = pg_merchant_state_parse(array(
		'connection_id' => '7',
		'account'       => '123456',
		'account_name'  => "Shop\x00 TR",
		'country'       => 'TR',
		'data_source'   => 'accounts/123456/dataSources/99',
		'registered_at' => '-5',
		'issues'        => array('counts' => array('DISAPPROVED' => '2'), 'top' => array(
			array('code' => 'x', 'severity' => 'BAD', 'description' => 'X', 'products' => '3', 'help' => 'javascript:1'),
			'junk',
		)),
		'unknown'       => 'dropped',
	));

	pg_assert_same(7, $state['connection_id']);
	pg_assert_same('123456', $state['account']);
	pg_assert_same('Shop  TR', $state['account_name'], 'control characters become spaces');
	pg_assert_same(0, $state['registered_at']);
	pg_assert_same(2, $state['issues']['counts']['DISAPPROVED']);
	pg_assert_same(1, count($state['issues']['top']));
	pg_assert_same('NOT_IMPACTED', $state['issues']['top'][0]['severity']);
	pg_assert_same('', $state['issues']['top'][0]['help']);
	pg_assert_false(isset($state['unknown']));

	$bad = pg_merchant_state_parse(array('account' => '12a', 'country' => 'tur', 'data_source' => 'accounts/1/other/2'));
	pg_assert_same('', $bad['account']);
	pg_assert_same('', $bad['country']);
	pg_assert_same('', $bad['data_source']);
	pg_assert_same(pg_merchant_state_parse(null), pg_merchant_state_parse('nope'), 'not an array: defaults');
}

function test_merchant_center_health()
{
	$state = pg_merchant_state_parse(null);
	pg_assert_same('none', pg_merchant_health($state), 'no account: no tile');

	$state['account'] = '1';
	pg_assert_same('warn', pg_merchant_health($state), 'not registered yet');

	$state['data_source'] = 'accounts/1/dataSources/2';
	pg_assert_same('ok', pg_merchant_health($state));

	$state['issues']['counts']['DEMOTED'] = 1;
	pg_assert_same('warn', pg_merchant_health($state));

	$state['issues']['counts']['DISAPPROVED'] = 1;
	pg_assert_same('fail', pg_merchant_health($state));
}

function test_merchant_center_accounts_and_country()
{
	pg_assert_same(array('123' => 'Shop', '456' => '456'), pg_merchant_accounts_from_api(array('accounts' => array(
		array('name' => 'accounts/123', 'accountId' => '123', 'accountName' => 'Shop'),
		array('name' => 'accounts/456'),
		array('name' => 'accounts/x'),
		'junk',
	))));
	pg_assert_same(array(), pg_merchant_accounts_from_api(null));

	pg_assert_same('DE', pg_merchant_default_country('de', 'EUR'));
	pg_assert_same('TR', pg_merchant_default_country('Türkiye', 'TRY'));
	pg_assert_same('', pg_merchant_default_country('', 'EUR'), 'the euro names no country');
	pg_assert_same('https://merchants.google.com/mc/products/diagnostics?a=123', pg_merchant_center_url('123'));
}
