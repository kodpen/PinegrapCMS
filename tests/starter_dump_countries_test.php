<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The countries of the two starter site dumps: every code once, no country
 * ISO 3166 no longer has, current names, and every shipping zone and tax
 * zone row pointing at a country that is there.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function starter_dump_countries_line($dump, $table)
{
	$sql = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/data/backups/' . $dump . '/sql.sql');
	$start = strpos($sql, 'INSERT INTO `' . $table . '` VALUES ');

	if ($start === false) {
		return '';
	}

	return substr($sql, $start, strpos($sql, "\n", $start) - $start);
}

// id => array(name, code)
function starter_dump_countries($dump)
{
	preg_match_all("/\((\d+),'((?:[^'\\\\]|\\\\.)*)','([A-Z]*)',/", starter_dump_countries_line($dump, 'countries'), $rows, PREG_SET_ORDER);

	$countries = array();

	foreach ($rows as $row) {
		$countries[$row[1]] = array($row[2], $row[3]);
	}

	return $countries;
}

function test_starter_dump_countries_codes_are_unique_and_current()
{
	foreach (array('turkish_default', 'english_default') as $dump) {
		$countries = starter_dump_countries($dump);
		$codes = array();

		foreach ($countries as $country) {
			$codes[] = $country[1];
		}

		pg_assert_same(240, count($countries), $dump . ': country rows');
		pg_assert_same(count($codes), count(array_unique($codes)), $dump . ': every code once');

		foreach (array('AN', 'CS') as $gone) {
			pg_assert_false(in_array($gone, $codes, true), $dump . ': ' . $gone . ' is no longer a country');
		}

		foreach (array('RS', 'ME', 'EH', 'WS', 'CD', 'VA') as $code) {
			pg_assert_true(in_array($code, $codes, true), $dump . ': ' . $code . ' is there');
		}

		foreach ($countries as $country) {
			pg_assert_false(in_array($country[0], array('Western Samoa', 'Zaire', 'Swaziland', 'Macedonia', 'Azerbeycan', 'Holy See (Vatican City)'), true), $dump . ': outdated name ' . $country[0]);
		}
	}
}

function test_starter_dump_countries_zones_point_at_countries()
{
	foreach (array('turkish_default', 'english_default') as $dump) {
		$countries = starter_dump_countries($dump);

		foreach (array('zones_countries_xref', 'tax_zones_countries_xref') as $table) {
			preg_match_all('/\((\d+),(\d+)\)/', starter_dump_countries_line($dump, $table), $pairs, PREG_SET_ORDER);

			foreach ($pairs as $pair) {
				pg_assert_true(isset($countries[$pair[2]]), $dump . ': ' . $table . ' points at country ' . $pair[2]);
			}
		}

		$international = array();
		preg_match_all('/\(3,(\d+)\)/', starter_dump_countries_line($dump, 'zones_countries_xref'), $ids);

		foreach ($ids[1] as $id) {
			$international[] = $countries[$id][1] ?? '';
		}

		foreach (array('RS', 'ME', 'CD', 'VA', 'EH') as $code) {
			pg_assert_true(in_array($code, $international, true), $dump . ': ' . $code . ' ships in the International zone');
		}
	}
}
