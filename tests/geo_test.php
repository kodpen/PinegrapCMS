<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the visitor location layer: the codes includes/geo.php accepts,
 * the lines of the DB-IP list, the records includes/fn/geo.php builds from
 * them, and a lookup in a database built from a list made up for the test.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// includes/geo.php is loaded by the firewall, as on a request.
require_once(PG_FUNCTIONS_DIR . '/waf.php');

// Two letters, upper-cased; the codes that mean nowhere are no country.
function test_geo_normalize_code()
{
	pg_assert_same('TR', pg_geo_normalize_code(' tr '), 'case and space');
	pg_assert_same('', pg_geo_normalize_code('XX'), 'Cloudflare unknown');
	pg_assert_same('', pg_geo_normalize_code('ZZ'), 'reserved');
	pg_assert_same('', pg_geo_normalize_code('--'), 'database none');
	pg_assert_same('', pg_geo_normalize_code('TUR'), 'three letters');
}

// Private, loopback and reserved addresses have no country.
function test_geo_ip_is_public()
{
	pg_assert_true(pg_geo_ip_is_public('8.8.8.8'), 'public IPv4');
	pg_assert_true(pg_geo_ip_is_public('2a00:1450:4001::1'), 'public IPv6');
	pg_assert_false(pg_geo_ip_is_public('127.0.0.1'), 'loopback');
	pg_assert_false(pg_geo_ip_is_public('192.168.1.10'), 'private');
	pg_assert_false(pg_geo_ip_is_public('fd00::1'), 'unique local');
	pg_assert_false(pg_geo_ip_is_public('not an ip'), 'junk');
}

// This month's list and the one before it, across the turn of a year.
function test_geo_source_urls_cover_the_month_before()
{
	$urls = pg_geo_db_source_urls(gmmktime(12, 0, 0, 1, 2, 2027));

	pg_assert_same(array('2027-01', '2026-12'), array_keys($urls));
	pg_assert_same('https://download.db-ip.com/free/dbip-country-lite-2026-12.csv.gz', $urls['2026-12']);
}

// A line gives the family, the keys and the country; anything else is skipped.
function test_geo_parse_line()
{
	pg_assert_same(
		array('family' => 4, 'start' => "\x01\x00\x00\x00", 'end' => "\x01\x00\x00\xff", 'code' => 'AU'),
		pg_geo_db_parse_line("1.0.0.0,1.0.0.255,AU\n"), 'IPv4');

	pg_assert_same(
		array('family' => 6, 'start' => "\x20\x01\x02\x00\x00\x00\x00\x00", 'end' => "\x20\x01\x02\x00\xff\xff\xff\xff", 'code' => 'JP'),
		pg_geo_db_parse_line('"2001:200::","2001:200:ffff:ffff:ffff:ffff:ffff:ffff","JP"'), 'IPv6, quoted');

	pg_assert_same('--', pg_geo_db_parse_line('10.0.0.0,10.255.255.255,ZZ')['code'], 'no country');
	pg_assert_same(null, pg_geo_db_parse_line('ip_start,ip_end,country'), 'header line');
	pg_assert_same(null, pg_geo_db_parse_line('1.0.0.0,2001::,AU'), 'mixed families');
	pg_assert_same(null, pg_geo_db_parse_line(''), 'empty');
}

// Plus one as a big-endian number; the last key has none after it.
function test_geo_bin_increment()
{
	pg_assert_same("\x01\x00\x00\x01", pg_geo_bin_increment("\x01\x00\x00\x00"));
	pg_assert_same("\x01\x00\x01\x00", pg_geo_bin_increment("\x01\x00\x00\xff"));
	pg_assert_same(null, pg_geo_bin_increment("\xff\xff\xff\xff"));
}

// Neighbours of one country merge, holes and the ends are no country, overlaps and disorder are handled.
function test_geo_records_merge_and_fill_gaps()
{
	$range = function ($start, $end, $code) {
		return array('family' => 4, 'start' => inet_pton($start), 'end' => inet_pton($end), 'code' => $code);
	};

	$state = array();
	$out = array();

	foreach (array(
		$range('1.0.0.0', '1.0.0.255', 'AU'),
		$range('1.0.1.0', '1.0.1.255', 'AU'),      // merges with the one before
		$range('1.0.1.10', '1.0.1.20', 'CN'),      // inside: dropped
		$range('1.0.4.0', '1.0.4.255', 'CN'),      // a hole before it
		$range('1.0.4.128', '1.0.5.255', 'JP'),    // overlaps: starts after CN
	) as $item) {
		$out = array_merge($out, pg_geo_db_records($state, $item));
	}

	$out = array_merge($out, pg_geo_db_records($state, null));

	$expected = array(
		inet_pton('0.0.0.0') . '--',
		inet_pton('1.0.0.0') . 'AU',
		inet_pton('1.0.2.0') . '--',
		inet_pton('1.0.4.0') . 'CN',
		inet_pton('1.0.5.0') . 'JP',
		inet_pton('1.0.6.0') . '--',
	);

	pg_assert_same(array_map('bin2hex', $expected), array_map('bin2hex', $out));
	pg_assert_same(array(), $state, 'state cleared at the end');

	$state = array();
	pg_geo_db_records($state, $range('2.0.0.0', '2.0.0.255', 'FR'));
	pg_assert_same(false, pg_geo_db_records($state, $range('1.0.0.0', '1.0.0.255', 'AU')), 'out of order');
}

// A built database answers each address with its range, and a hole or the end with nothing.
function test_geo_build_and_search()
{
	$dir = sys_get_temp_dir() . '/pg_geo_test_' . bin2hex(random_bytes(4));
	mkdir($dir);

	$lines = array("ip_start,ip_end,country");

	// 1200 /24s from 1.0.0.0, alternating countries, the 500th left out.
	for ($i = 0; $i < 1200; $i++) {
		if ($i === 500) {
			continue;
		}

		$start = 0x01000000 + ($i * 256);
		$lines[] = long2ip($start) . ',' . long2ip($start + 255) . ',' . (($i % 2) ? 'JP' : 'AU');
	}

	// 1200 /32s from 2001::, alternating countries.
	for ($i = 0; $i < 1200; $i++) {
		$lines[] = sprintf('2001:%x::,2001:%x:ffff:ffff:ffff:ffff:ffff:ffff,%s', $i, $i, ($i % 2) ? 'FR' : 'DE');
	}

	$source = $dir . '/list.csv';
	file_put_contents($source, implode("\n", $lines) . "\n");

	$built = pg_geo_db_build($source, $dir, '2026-10');

	pg_assert_true($built['ok'], 'built: ' . $built['reason']);

	if ($built['ok']) {
		$meta = json_decode(file_get_contents($dir . '/meta.json'), true);

		pg_assert_same('2026-10', $meta['version'], 'version');
		pg_assert_same(1202, $meta['rows4'], 'IPv4 records: 1199 ranges, the hole, the edges');
		pg_assert_same(1202, $meta['rows6'], 'IPv6 records: 1200 ranges, the edges');

		$file4 = $dir . '/' . $meta['file4'];
		$file6 = $dir . '/' . $meta['file6'];

		pg_assert_same('AU', pg_geo_db_search($file4, inet_pton('1.0.0.0')), 'first address');
		pg_assert_same('JP', pg_geo_db_search($file4, inet_pton('1.0.1.200')), 'inside a range');
		pg_assert_same('--', pg_geo_db_search($file4, inet_pton('1.1.244.7')), 'the hole');
		pg_assert_same('JP', pg_geo_db_search($file4, inet_pton('1.1.245.0')), 'after the hole');
		pg_assert_same('--', pg_geo_db_search($file4, inet_pton('0.255.255.255')), 'before the first range');
		pg_assert_same('--', pg_geo_db_search($file4, inet_pton('200.0.0.1')), 'after the last range');

		pg_assert_same('DE', pg_geo_db_search($file6, substr(inet_pton('2001:0:1234::1'), 0, 8)), 'IPv6');
		pg_assert_same('FR', pg_geo_db_search($file6, substr(inet_pton('2001:4af::'), 0, 8)), 'IPv6, last range');
		pg_assert_same('--', pg_geo_db_search($file6, substr(inet_pton('2a00::1'), 0, 8)), 'IPv6, after');

		pg_assert_same('', pg_geo_db_search($file6, inet_pton('1.0.0.0')), 'key of the wrong length');
	}

	pg_assert_false(pg_geo_db_build($dir . '/missing.csv', $dir, '2026-10')['ok'], 'no list');

	file_put_contents($source, "1.0.0.0,1.0.0.255,AU\n");
	pg_assert_false(pg_geo_db_build($source, $dir, '2026-11')['ok'], 'a list cut short');

	$left = glob($dir . '/*');
	pg_assert_same(4, count($left), 'a failed build leaves the database it had: ' . implode(', ', array_map('basename', $left)));

	foreach ($left as $file) {
		unlink($file);
	}

	rmdir($dir);
}
