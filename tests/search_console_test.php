<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Google Search Console rules that need no database
 * (includes/fn/search_console.php): the stored setup, which property is this
 * site, the page filter of a domain property, the verification code, the days
 * a download asks for and how Google's rows are kept.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/search_console.php');

function test_gsc_settings_parse_fills_and_types_every_key()
{
	$empty = pg_gsc_settings_parse('');

	pg_assert_same(0, $empty['connection_id'], 'no account');
	pg_assert_same('', $empty['property'], 'no property');
	pg_assert_same('', $empty['synced_to'], 'nothing read');

	$stored = pg_gsc_settings_parse(json_encode(array(
		'connection_id' => '7',
		'property'      => 'sc-domain:example.com',
		'synced_to'     => '2026-10-10',
		'synced_at'     => '1760000000',
		'unknown'       => 'x',
		'permission'    => array('owner'),
	)));

	pg_assert_same(7, $stored['connection_id'], 'id as a number');
	pg_assert_same('sc-domain:example.com', $stored['property'], 'property');
	pg_assert_same('2026-10-10', $stored['synced_to'], 'last day read');
	pg_assert_same(1760000000, $stored['synced_at'], 'time as a number');
	pg_assert_same('', $stored['permission'], 'a list where a word belongs is dropped');
	pg_assert_false(isset($stored['unknown']), 'unknown keys are not carried');

	pg_assert_same('', pg_gsc_settings_parse('{"synced_to":"yesterday"}')['synced_to'], 'a day that is not a date');
	pg_assert_same(0, pg_gsc_settings_parse('not json')['connection_id'], 'not JSON');
}

function test_gsc_permission_reads_both_spellings()
{
	pg_assert_same('owner', pg_gsc_permission('siteOwner'), 'API spelling');
	pg_assert_same('owner', pg_gsc_permission('SITE_OWNER'), 'discovery spelling');
	pg_assert_same('full', pg_gsc_permission('siteFullUser'), 'full');
	pg_assert_same('restricted', pg_gsc_permission('siteRestrictedUser'), 'restricted');
	pg_assert_same('unverified', pg_gsc_permission('siteUnverifiedUser'), 'unverified');
	pg_assert_same('', pg_gsc_permission('admin'), 'unknown');
}

function test_gsc_property_pick_prefers_the_exact_prefix_then_the_closest_domain()
{
	$site = 'https://shop.example.com/';

	$entries = array(
		array('siteUrl' => 'sc-domain:example.com', 'permissionLevel' => 'siteOwner'),
		array('siteUrl' => 'sc-domain:shop.example.com', 'permissionLevel' => 'siteRestrictedUser'),
		array('siteUrl' => 'https://other.example.com/', 'permissionLevel' => 'siteOwner'),
	);

	pg_assert_same(
		array('property' => 'sc-domain:shop.example.com', 'permission' => 'restricted', 'match' => 'domain'),
		pg_gsc_property_pick($entries, $site),
		'the closest domain wins over a parent domain');

	$entries[] = array('siteUrl' => 'HTTPS://Shop.Example.com', 'permissionLevel' => 'siteFullUser');

	pg_assert_same(
		array('property' => 'HTTPS://Shop.Example.com', 'permission' => 'full', 'match' => 'prefix'),
		pg_gsc_property_pick($entries, $site),
		'a prefix property for this address wins, however it is spelled');

	pg_assert_same(null, pg_gsc_property_pick(array(
		array('siteUrl' => 'https://shop.example.com/', 'permissionLevel' => 'siteUnverifiedUser'),
	), $site), 'an unverified property is no use');

	pg_assert_same(null, pg_gsc_property_pick(array(
		array('siteUrl' => 'http://shop.example.com/', 'permissionLevel' => 'siteOwner'),
		array('siteUrl' => 'https://shop.example.com/blog/', 'permissionLevel' => 'siteOwner'),
		array('siteUrl' => 'sc-domain:com', 'permissionLevel' => 'siteOwner'),
	), $site), 'another scheme, a deeper path and a bare top-level domain do not match');

	$pick = pg_gsc_property_pick(array(
		array('siteUrl' => 'https://shop.example.com/', 'permissionLevel' => 'siteRestrictedUser'),
		array('siteUrl' => 'https://shop.example.com', 'permissionLevel' => 'siteOwner'),
	), $site);

	pg_assert_same('owner', $pick['permission'], 'within a kind of match the higher permission wins');

	pg_assert_same(null, pg_gsc_property_pick('nope', $site), 'not a list');
	pg_assert_same(null, pg_gsc_property_pick($entries, 'not an address'), 'not an address');
}

function test_gsc_prefix_key_compares_spellings()
{
	pg_assert_same('https://example.com/', pg_gsc_prefix_key('HTTPS://Example.COM'), 'case and the closing slash');
	pg_assert_same('https://example.com/shop/', pg_gsc_prefix_key('https://example.com/shop'), 'a path');
	pg_assert_same('http://example.com:8080/', pg_gsc_prefix_key('http://example.com:8080/'), 'a port');
	pg_assert_same('', pg_gsc_prefix_key('sc-domain:example.com'), 'a domain property');
	pg_assert_same('', pg_gsc_prefix_key('ftp://example.com/'), 'another scheme');
}

function test_gsc_page_filter_only_for_domain_properties()
{
	pg_assert_same('', pg_gsc_page_filter('https://example.com/', 'https://example.com/'), 'a prefix property needs none');
	pg_assert_same('^https?://shop\.example\.com/', pg_gsc_page_filter('sc-domain:example.com', 'https://Shop.example.com/'), 'host');
	pg_assert_same('^https?://example\.com/tr/', pg_gsc_page_filter('sc-domain:example.com', 'https://example.com/tr/'), 'host and path');
}

function test_gsc_meta_content_takes_the_tag_or_the_code()
{
	$code = 'abcDEF0123_-xyz';

	pg_assert_same($code, pg_gsc_meta_content('<meta name="google-site-verification" content="' . $code . '" />'), 'the tag');
	pg_assert_same($code, pg_gsc_meta_content("<meta content='" . $code . "' name='google-site-verification'>"), 'single quotes');
	pg_assert_same($code, pg_gsc_meta_content('  ' . $code . ' '), 'the code alone');
	pg_assert_same('', pg_gsc_meta_content('<meta content="a&quot;><script>">'), 'markup in the code');
	pg_assert_same('', pg_gsc_meta_content('short'), 'too short');
	pg_assert_same('', pg_gsc_meta_content(''), 'nothing');
}

function test_gsc_sync_range_rereads_the_last_days()
{
	pg_assert_same(array('from' => '2026-07-15', 'to' => '2026-10-12'), pg_gsc_sync_range('2026-10-13', ''), 'first time: the whole window');
	pg_assert_same(array('from' => '2026-10-07', 'to' => '2026-10-12'), pg_gsc_sync_range('2026-10-13', '2026-10-10'), 'three days before the last day read');
	pg_assert_same(array('from' => '2026-07-15', 'to' => '2026-10-12'), pg_gsc_sync_range('2026-10-13', '2025-01-01'), 'never older than the window');
	pg_assert_same(array('from' => '2026-10-12', 'to' => '2026-10-12'), pg_gsc_sync_range('2026-10-13', '2026-10-20'), 'a last day in the future');
	pg_assert_same(array('from' => '2026-10-06', 'to' => '2026-10-12'), pg_gsc_sync_range('2026-10-13', '', 7), 'a shorter window');
}

// A backup carries the last day read but not the rows (the table is dumped
// empty), so an empty table starts again from the whole window.
function test_gsc_resume_day_starts_over_on_an_empty_table()
{
	pg_assert_same('2026-10-10', pg_gsc_resume_day('2026-10-10', true), 'rows: continue');
	pg_assert_same('', pg_gsc_resume_day('2026-10-10', false), 'restored, no rows: the whole window');
	pg_assert_same('', pg_gsc_resume_day('', false), 'never read');
	pg_assert_same(array('from' => '2026-07-15', 'to' => '2026-10-12'), pg_gsc_sync_range('2026-10-13', pg_gsc_resume_day('2026-10-12', false)), 'the window after a restore');
}

function test_gsc_period_and_the_one_before()
{
	pg_assert_same(array(
		'from'          => '2026-09-13',
		'to'            => '2026-10-10',
		'previous_from' => '2026-08-16',
		'previous_to'   => '2026-09-12',
	), pg_gsc_period('2026-10-10', 28), '28 days');

	pg_assert_same(array(
		'from'          => '2026-10-10',
		'to'            => '2026-10-10',
		'previous_from' => '2026-10-09',
		'previous_to'   => '2026-10-09',
	), pg_gsc_period('2026-10-10', 0), 'at least one day');
}

function test_gsc_rows_from_api_keep_the_level()
{
	$rows = array(
		array('keys' => array('2026-10-10', 'pinegrap cms'), 'clicks' => 3, 'impressions' => 40, 'ctr' => 0.075, 'position' => 4.567),
		array('keys' => array('2026-10-10', ''), 'clicks' => 1, 'impressions' => 1, 'position' => 1),
		array('keys' => array('not a day', 'x'), 'clicks' => 1),
		'nope',
	);

	pg_assert_same(array(array(
		'day'         => '2026-10-10',
		'kind'        => 'query',
		'page'        => '',
		'query'       => 'pinegrap cms',
		'clicks'      => 3,
		'impressions' => 40,
		'position'    => 4.57,
	)), pg_gsc_rows_from_api('query', $rows), 'a query row; an empty key and a bad day are dropped');

	$site = pg_gsc_rows_from_api('site', array(array('keys' => array('2026-10-10'), 'clicks' => 5, 'impressions' => 90, 'position' => 7.2)));

	pg_assert_same(1, count($site), 'the day total');
	pg_assert_same('', $site[0]['page'], 'no page');
	pg_assert_same(5, $site[0]['clicks'], 'clicks');

	$page = pg_gsc_rows_from_api('page', array(array('keys' => array('2026-10-10', 'https://example.com/a'), 'clicks' => -2)));

	pg_assert_same('https://example.com/a', $page[0]['page'], 'page');
	pg_assert_same(0, $page[0]['clicks'], 'never below zero');
	pg_assert_same(array(), pg_gsc_rows_from_api('page', 'nope'), 'not a list');
}

function test_gsc_row_key_tells_levels_and_rows_apart()
{
	pg_assert_same(40, strlen(pg_gsc_row_key('site', '', '')), 'hex sha1');
	pg_assert_true(pg_gsc_row_key('page', 'a', '') !== pg_gsc_row_key('query', '', 'a'), 'the same text at two levels');
	pg_assert_true(pg_gsc_row_key('query', '', "a\nb") !== pg_gsc_row_key('query', "a", 'b'), 'the separator is part of the key');
	pg_assert_same(pg_gsc_row_key('query', '', 'x'), pg_gsc_row_key('query', '', 'x'), 'stable');
}

function test_gsc_figures_helpers()
{
	pg_assert_same(0.25, pg_gsc_ctr(1, 4), 'click rate');
	pg_assert_same(0.0, pg_gsc_ctr(3, 0), 'no impressions');

	pg_assert_same(0.5, pg_gsc_change(150, 100), 'up by half');
	pg_assert_same(-0.25, pg_gsc_change(75, 100), 'down by a quarter');
	pg_assert_same(null, pg_gsc_change(10, 0), 'nothing to compare with');

	pg_assert_same('/blog/post?x=1', pg_gsc_page_label('https://example.com/blog/post?x=1', 'https://example.com/'), 'a page of this site');
	pg_assert_same('/post', pg_gsc_page_label('https://example.com/tr/post', 'https://example.com/tr/'), 'under the site path');
	pg_assert_same('/', pg_gsc_page_label('https://example.com', 'https://example.com/'), 'the home page');
	pg_assert_same('shop.example.com/a', pg_gsc_page_label('https://shop.example.com/a', 'https://example.com/'), 'another host of the domain');
}
