<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page speed beacon and its rollup (includes/fn/page_vitals.php):
 * the buckets by Google's thresholds, the validation of the beacon's figures,
 * the row key, the signature, the upsert, when a page carries the beacon and
 * the places that wire it. The runner has no connection, so the call sites
 * are read from the source.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function vitals_beacon_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function test_vitals_bucket_follows_the_thresholds()
{
	pg_assert_same('good', pg_vitals_bucket('lcp', 0));
	pg_assert_same('good', pg_vitals_bucket('lcp', 2500), 'the threshold itself is good');
	pg_assert_same('needs', pg_vitals_bucket('lcp', 2501));
	pg_assert_same('needs', pg_vitals_bucket('lcp', 4000));
	pg_assert_same('poor', pg_vitals_bucket('lcp', 4001));
	pg_assert_same('good', pg_vitals_bucket('inp', 200));
	pg_assert_same('needs', pg_vitals_bucket('inp', 500));
	pg_assert_same('poor', pg_vitals_bucket('inp', 501));
	pg_assert_same('good', pg_vitals_bucket('cls', 100), 'CLS 0.1');
	pg_assert_same('needs', pg_vitals_bucket('cls', 250), 'CLS 0.25');
	pg_assert_same('poor', pg_vitals_bucket('cls', 251));
	pg_assert_same('', pg_vitals_bucket('fid', 10), 'an unknown figure');
}

function test_vitals_parse_reads_a_well_formed_beacon()
{
	pg_assert_same(array('device' => 'mobile', 'lcp' => 1800, 'inp' => 120, 'cls' => 50), pg_vitals_parse('1800,120,50,mobile'));
	pg_assert_same(array('device' => 'desktop', 'lcp' => 2100, 'inp' => null, 'cls' => 0), pg_vitals_parse('2100,,0,desktop'), 'no interaction: no INP');
	pg_assert_same(array('device' => 'tablet', 'lcp' => 60000, 'inp' => 10000, 'cls' => 10000), pg_vitals_parse('60000,10000,10000,tablet'), 'the limits themselves');
}

function test_vitals_parse_drops_what_is_out_of_shape_or_range()
{
	pg_assert_same(null, pg_vitals_parse(',,,desktop'), 'nothing measured');
	pg_assert_same(null, pg_vitals_parse('60001,100,0,desktop'), 'LCP above 60 s');
	pg_assert_same(null, pg_vitals_parse('1000,10001,0,desktop'), 'INP above 10 s');
	pg_assert_same(null, pg_vitals_parse('1000,100,10001,desktop'), 'CLS above 10');
	pg_assert_same(null, pg_vitals_parse('-5,100,0,desktop'), 'a negative figure');
	pg_assert_same(null, pg_vitals_parse('1.5,100,0,desktop'), 'a fraction');
	pg_assert_same(null, pg_vitals_parse('1000,100,0,phone'), 'an unknown width class');
	pg_assert_same(null, pg_vitals_parse('1000,100,0'), 'a part missing');
	pg_assert_same(null, pg_vitals_parse('1000,100,0,desktop,4g'), 'a part too many');
	pg_assert_same(null, pg_vitals_parse(array('1000')), 'an array');
	pg_assert_same(null, pg_vitals_parse(str_repeat('1', 70)), 'too long');
	pg_assert_same(null, pg_vitals_parse('0001000,1,1,desktop'), 'more than six digits');
}

function test_vitals_key_is_the_hour_page_and_device()
{
	pg_assert_same(sha1('2026-11-02|9|42|mobile'), pg_vitals_key('2026-11-02', 9, 42, 'mobile'));
	pg_assert_true(pg_vitals_key('2026-11-02', 9, 42, 'mobile') !== pg_vitals_key('2026-11-02', 9, 42, 'desktop'), 'a device has its own row');
	pg_assert_same(40, strlen(pg_vitals_key('2026-11-02', 23, 1, 'tablet')));
}

function test_vitals_signature_names_one_page_of_one_site()
{
	$signature = pg_vitals_sign(42, 'site-key');

	pg_assert_same(hash_hmac('sha256', 'page-vitals:42', 'site-key'), $signature);
	pg_assert_same(42, pg_vitals_verify('42', $signature, 'site-key'));
	pg_assert_same(0, pg_vitals_verify('43', $signature, 'site-key'), 'another page');
	pg_assert_same(0, pg_vitals_verify('42', $signature, 'other-key'), 'another site');
	pg_assert_same(0, pg_vitals_verify('42', '', 'site-key'), 'no signature');
	pg_assert_same(0, pg_vitals_verify(array('42'), $signature, 'site-key'), 'an array');
	pg_assert_same(0, pg_vitals_verify('4x2', $signature, 'site-key'), 'not a number');
	pg_assert_same('', pg_vitals_sign(42, ''), 'no key, no beacon');
	pg_assert_same('', pg_vitals_sign(0, 'site-key'), 'no page, no beacon');
	pg_assert_same(0, pg_vitals_verify('42', pg_vitals_sign(42, ''), ''), 'no key never verifies');
}

function test_vitals_upsert_raises_sums_and_buckets()
{
	$sql = pg_vitals_upsert_sql(array('device' => 'mobile', 'lcp' => 3000, 'inp' => null, 'cls' => 300));

	pg_assert_contains('INSERT INTO page_vitals_hourly (id, day, hour, page_id, device, views, lcp_sum, lcp_good, lcp_needs, lcp_poor, inp_sum, inp_good, inp_needs, inp_poor, cls_sum, cls_good, cls_needs, cls_poor)', $sql);
	pg_assert_contains('VALUES (?, ?, ?, ?, ?, 1, 3000, 0, 1, 0, 0, 0, 0, 0, 300, 0, 0, 1)', $sql);
	pg_assert_contains('ON DUPLICATE KEY UPDATE views = views + 1, lcp_sum = lcp_sum + 3000, lcp_needs = lcp_needs + 1, cls_sum = cls_sum + 300, cls_poor = cls_poor + 1', $sql);
	pg_assert_false(strpos(substr($sql, strpos($sql, 'UPDATE')), 'inp_'), 'an unmeasured INP is not touched');

	$good = pg_vitals_upsert_sql(array('device' => 'desktop', 'lcp' => 0, 'inp' => 0, 'cls' => 0));
	pg_assert_contains('lcp_good = lcp_good + 1, inp_good = inp_good + 1, cls_good = cls_good + 1', $good, 'zero is a measured, good figure');
}

function test_vitals_beacon_only_on_a_visitors_page()
{
	$visitor = array('rendered_html_page' => true, 'toolbar' => false, 'panel_preview' => false, 'background' => false, 'staff' => false);

	pg_assert_true(pg_vitals_beacon_wanted($visitor));
	pg_assert_false(pg_vitals_beacon_wanted(array_merge($visitor, array('rendered_html_page' => false))), 'a PDF, feed or calendar');
	pg_assert_false(pg_vitals_beacon_wanted(array_merge($visitor, array('toolbar' => true))), 'the panel bar');
	pg_assert_false(pg_vitals_beacon_wanted(array_merge($visitor, array('panel_preview' => true))), 'the File Manager preview');
	pg_assert_false(pg_vitals_beacon_wanted(array_merge($visitor, array('background' => true))), 'a background render');
	pg_assert_false(pg_vitals_beacon_wanted(array_merge($visitor, array('staff' => true))), 'staff with the bar hidden');
}

function test_vitals_beacon_tag_carries_page_and_signature()
{
	$tag = pg_vitals_beacon_tag('/pinegrap/assets/js/pg_vitals.min.js?v=1', '/pinegrap/page_view.php', 42, 'abc');

	pg_assert_same('<script src="/pinegrap/assets/js/pg_vitals.min.js?v=1" defer data-u="/pinegrap/page_view.php" data-p="42" data-s="abc"></script>', $tag);
	pg_assert_same('', pg_vitals_beacon_tag('/x.js', '/p.php', 42, ''), 'no signature');
	pg_assert_same('', pg_vitals_beacon_tag('', '/p.php', 42, 'abc'), 'no script');
	pg_assert_same('', pg_vitals_beacon_tag('/x.js', '/p.php', 0, 'abc'), 'no page');
	pg_assert_contains('data-u="/a&quot;b.php"', pg_vitals_beacon_tag('/x.js', '/a"b.php', 1, 's'), 'escaped');
}

function test_vitals_is_wired_into_the_page_the_endpoint_and_the_schema()
{
	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_vitals.src.js'), $bundles['assets/js/pg_vitals.min.js'] ?? null, 'a bundle of its own');

	$page_view = vitals_beacon_test_source('page_view.php');
	pg_assert_contains("isset(\$_POST['vp'])", $page_view);
	pg_assert_true(strpos($page_view, "isset(\$_POST['vp'])") < strpos($page_view, 'pg_page_cache_beacon_verify('), 'handled before the page view');
	pg_assert_contains('pg_vitals_verify(', $page_view);
	pg_assert_contains('pg_vitals_parse(', $page_view);

	$get_page = vitals_beacon_test_source('get_page.php');
	pg_assert_true(strpos($get_page, 'pg_vitals_beacon_inject(') < strpos($get_page, 'pg_page_cache_store_page($content'), 'the cached copy carries it');

	pg_assert_contains("'page_vitals_hourly',", vitals_beacon_test_source('install/index.php'), 'in get_tables()');
	$migration = vitals_beacon_test_source('includes/migrations/2026.11.0.php');
	pg_assert_contains('upgrade_2026_11_0_page_vitals_hourly();', $migration);
	pg_assert_contains("install_create_table('page_vitals_hourly'", $migration);

	$script = vitals_beacon_test_source('assets/js/pg_vitals.src.js');
	pg_assert_contains('navigator.sendBeacon', $script);
	pg_assert_contains('event.persisted', $script, 'no figures for a page back from the back-forward cache');
}
