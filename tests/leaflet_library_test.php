<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the vendored Leaflet, the map of the "Map of places"
 * block (assets/js/pg_map.js): the release files sit unmodified under
 * assets/lib/leaflet/ with their licence and the marker pictures the
 * stylesheet points at, and pg_map.js loads them itself, never by a tag of
 * the page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function leaflet_library_test_source($path)
{
	$file = PG_FUNCTIONS_DIR . '/' . $path;

	return is_file($file) ? (string) file_get_contents($file) : '';
}

function test_leaflet_library_is_the_release_file()
{
	$library = leaflet_library_test_source('assets/lib/leaflet/leaflet.js');

	pg_assert_contains(' * Leaflet 1.9.4, a JS library for interactive maps.', substr($library, 0, 200), 'leaflet.js opens with its own banner');
	// An update copies dist/leaflet.js, dist/leaflet.css, dist/images and
	// LICENSE from the npm package and changes these hashes and the version.
	pg_assert_same('db49d009c841f5ca34a888c96511ae936fd9f5533e90d8b2c4d57596f4e5641a', hash('sha256', $library), 'leaflet.js is the npm release file byte for byte');
	pg_assert_same('a7837102824184820dfa198d1ebcd109ff6d0ff9a2672a074b9a1b4d147d04c6', hash('sha256', leaflet_library_test_source('assets/lib/leaflet/leaflet.css')), 'leaflet.css too');
}

function test_leaflet_library_ships_its_licence()
{
	$licence = leaflet_library_test_source('assets/lib/leaflet/LICENSE');

	pg_assert_same('53e8dc25862014e4324741ca18fbe3611e11d42ef69f59f86ea8c5389647d4cb', hash('sha256', $licence), 'the licence as the package ships it');
	pg_assert_contains('BSD 2-Clause License', $licence);
	pg_assert_contains('Volodymyr Agafonkin', $licence);
}

function test_leaflet_library_has_the_pictures_its_stylesheet_names()
{
	$css = leaflet_library_test_source('assets/lib/leaflet/leaflet.css');

	preg_match_all('~url\(images/([a-z0-9-]+\.png)\)~', $css, $named);
	pg_assert_true(count($named[1]) > 0, 'the stylesheet names pictures');

	foreach (array_unique(array_merge($named[1], array('marker-icon.png', 'marker-icon-2x.png', 'marker-shadow.png'))) as $picture) {
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/assets/lib/leaflet/images/' . $picture), $picture . ' is there');
	}
}

function test_leaflet_library_loaded_only_by_the_map_script()
{
	$script = leaflet_library_test_source('assets/js/pg_map.js');

	pg_assert_same(1, substr_count($script, "createElement('script')"), 'pg_map.js adds the library\'s tag once');
	pg_assert_same(0, substr_count(leaflet_library_test_source('includes/fn/place_list.php'), 'leaflet.js'), 'the page prints no tag of its own');
	pg_assert_contains("\$lib . '/leaflet.js'", leaflet_library_test_source('includes/fn/place_tiles.php'), 'the address comes from the page\'s settings');
}
