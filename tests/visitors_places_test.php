<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the places and devices section of the Visitors overview
 * (includes/visitors/places.php): the top lists and their shares, the split
 * into named parts and an unknown rest, the split bar and the country label.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_VISITORS_SCREEN')) {
	define('PG_VISITORS_SCREEN', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/visitors/shell.php');
require_once(PG_FUNCTIONS_DIR . '/includes/visitors/places.php');

function test_visitors_places_top_skips_empty_values_and_keeps_the_limit()
{
	$rows = array(
		array('value' => '', 'visits' => 500),
		array('value' => 'Istanbul', 'visits' => 300),
		array('value' => 'Ankara', 'visits' => 150),
		array('value' => 'İzmir', 'visits' => 50),
	);

	$top = pg_visitors_places_top($rows, 1000, 2);

	pg_assert_same(2, count($top));
	pg_assert_same('Istanbul', $top[0]['label']);
	pg_assert_same(300, $top[0]['value']);
	pg_assert_same(pg_format_number(300, 0) . ' · ' . pg_visitors_percent(30, 0), $top[0]['text'], 'share of all visits, not of the known ones');
	pg_assert_same('Ankara', $top[1]['label']);

	pg_assert_same(array(), pg_visitors_places_top(array(array('value' => '', 'visits' => 9)), 9, 10), 'only the empty value: no row');
}

function test_visitors_places_top_runs_the_label_callback()
{
	$top = pg_visitors_places_top(array(array('value' => 'tr', 'visits' => 4)), 4, 10, 'strtoupper');

	pg_assert_same('TR', $top[0]['label']);
}

function test_visitors_places_known_counts_visits_with_a_value()
{
	pg_assert_same(7, pg_visitors_places_known(array(
		array('value' => '', 'visits' => 10),
		array('value' => 'TR', 'visits' => 5),
		array('value' => ' ', 'visits' => 3),
		array('value' => 'DE', 'visits' => 2),
	)));
}

function test_visitors_places_split_names_parts_and_puts_the_rest_in_unknown()
{
	$parts = pg_visitors_places_split(array(
		array('value' => 'mobile', 'visits' => 60),
		array('value' => 'Desktop', 'visits' => 30),
		array('value' => '', 'visits' => 5),
		array('value' => 'tv', 'visits' => 1),
	), 100, array('desktop' => 'Desktop', 'mobile' => 'Mobile', 'tablet' => 'Tablet'));

	pg_assert_same(array('desktop', 'mobile', 'tablet', 'unknown'), array_column($parts, 'key'));
	pg_assert_same(array(30, 60, 0, 10), array_column($parts, 'visits'), 'empty, unnamed and unlisted visits are unknown');
	pg_assert_same(array(30.0, 60.0, 0.0, 10.0), array_column($parts, 'share'));
}

function test_visitors_places_split_leaves_out_an_empty_unknown_part()
{
	$parts = pg_visitors_places_split(array(
		array('value' => '1', 'visits' => 3),
		array('value' => '0', 'visits' => 1),
	), 4, array('1' => 'First', '0' => 'Return'));

	pg_assert_same(array('1', '0'), array_column($parts, 'key'));
	pg_assert_same(array(75.0, 25.0), array_column($parts, 'share'));

	$over = pg_visitors_places_split(array(array('value' => '1', 'visits' => 5)), 3, array('1' => 'First'));
	pg_assert_same(100.0, $over[0]['share'], 'a total under the named visits never gives a share above 100');
}

function test_visitors_places_share_text_rounds_by_size()
{
	pg_assert_same(pg_visitors_percent(33, 0), pg_visitors_places_share_text(1, 3));
	pg_assert_same(pg_visitors_percent(2.5, 1), pg_visitors_places_share_text(1, 40));
	pg_assert_same('< ' . pg_visitors_percent(0.1, 1), pg_visitors_places_share_text(1, 5000), 'a tiny part is not shown as 0');
	pg_assert_same(pg_visitors_percent(0, 1), pg_visitors_places_share_text(0, 10));
	pg_assert_same(pg_visitors_percent(0, 1), pg_visitors_places_share_text(0, 0));
}

function test_visitors_places_split_bar_draws_segments_and_escapes_labels()
{
	$html = pg_visitors_places_split_bar(array(
		array('key' => 'desktop', 'label' => '<b>Desk</b>', 'visits' => 3, 'share' => 75.0),
		array('key' => 'tablet', 'label' => 'Tablet', 'visits' => 0, 'share' => 0.0),
		array('key' => 'unknown', 'label' => 'Unknown', 'visits' => 1, 'share' => 25.0),
	), 'Devices');

	pg_assert_contains('&lt;b&gt;Desk&lt;/b&gt;', $html);
	pg_assert_true(strpos($html, '<b>Desk') === false, 'label escaped');
	pg_assert_contains('style="width:75%"', $html);
	pg_assert_contains('style="width:25%"', $html);
	pg_assert_same(2, substr_count($html, 'style="width:'), 'no segment for a part without visits');
	pg_assert_contains('is-unknown', $html);
	pg_assert_contains('aria-label="Devices"', $html);

	pg_assert_contains('pg-visitors-empty', pg_visitors_places_split_bar(array(), 'Devices'));
}

function test_visitors_places_country_label_names_codes_and_keeps_names()
{
	pg_assert_same(pg_geo_country_name('TR'), pg_visitors_places_country_label('tr'));
	pg_assert_same('Narnia', pg_visitors_places_country_label('Narnia'));
}
