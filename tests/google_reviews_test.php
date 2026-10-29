<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Google reviews widget (includes/fn/google_reviews.php,
 * includes/fn/widgets_google_reviews.php): the Place Details request, reading
 * Google's answer, the cache window, the rows and tokens, escaping, and that
 * nothing is drawn while the widget has nothing to show.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A Place Details (New) answer with six reviews, one of them hostile.
function _grev_test_body()
{
	$reviews = array();
	for ($i = 1; $i <= 6; $i++) {
		$reviews[] = array(
			'name' => 'places/ChIJ_test/reviews/r' . $i,
			'relativePublishTimeDescription' => $i . ' weeks ago',
			'rating' => ($i === 2) ? 3 : 5,
			'text' => array('text' => 'Review ' . $i, 'languageCode' => 'en'),
			'originalText' => array('text' => 'Original ' . $i, 'languageCode' => 'tr'),
			'authorAttribution' => array(
				'displayName' => ($i === 1) ? '<script>alert(1)</script>' : 'Author ' . $i,
				'uri' => 'https://www.google.com/maps/contrib/' . $i,
				'photoUri' => ($i === 3) ? '' : 'https://lh3.googleusercontent.com/a/p' . $i,
			),
			'publishTime' => '2026-10-0' . $i . 'T10:00:00Z',
			'googleMapsUri' => 'https://www.google.com/maps/reviews/data=' . $i,
		);
	}
	$reviews[3]['authorAttribution']['photoUri'] = 'javascript:alert(1)';

	return json_encode(array(
		'id' => 'ChIJ_test',
		'displayName' => array('text' => 'Kodpen Café', 'languageCode' => 'tr'),
		'rating' => 4.6,
		'userRatingCount' => 128,
		'googleMapsUri' => 'https://maps.google.com/?cid=123',
		'reviews' => $reviews,
	));
}

function test_google_reviews_request()
{
	$request = pg_grev_request('AIzaKey', 'ChIJN1t_tDeuEmsRUsoyG83frY4', 'tr');
	pg_assert_same('https://places.googleapis.com/v1/places/ChIJN1t_tDeuEmsRUsoyG83frY4?languageCode=tr', $request['url'], 'Place Details (New), in the language asked');
	pg_assert_true(in_array('X-Goog-Api-Key: AIzaKey', $request['headers'], true), 'the key goes in a header, never the address');
	pg_assert_true(in_array('X-Goog-FieldMask: id,displayName,rating,userRatingCount,reviews,googleMapsUri', $request['headers'], true), 'the field mask');
	pg_assert_false(strpos($request['url'], 'AIzaKey'), 'no key in the address');

	$plain = pg_grev_request('AIzaKey', 'ChIJN1t_tDeuEmsRUsoyG83frY4', '');
	pg_assert_same('https://places.googleapis.com/v1/places/ChIJN1t_tDeuEmsRUsoyG83frY4', $plain['url'], 'no language: Google picks');
	pg_assert_same(null, pg_grev_request('', 'ChIJN1t_tDeuEmsRUsoyG83frY4', 'en'), 'no key, no request');
	pg_assert_same(null, pg_grev_request('AIzaKey', '', 'en'), 'no place, no request');
	pg_assert_same(null, pg_grev_request('AIzaKey', '../places/x?y', 'en'), 'a Place ID is letters, digits, _ and - only');
	pg_assert_same(null, pg_grev_request("AIza\r\nX: y", 'ChIJN1t_tDeuEmsRUsoyG83frY4', 'en'), 'no header injection through the key');
	$odd = pg_grev_request('AIzaKey', 'ChIJN1t_tDeuEmsRUsoyG83frY4', 'en"x');
	pg_assert_same('https://places.googleapis.com/v1/places/ChIJN1t_tDeuEmsRUsoyG83frY4', $odd['url'], 'a malformed language is left out');
}

function test_google_reviews_parse()
{
	$place = pg_grev_parse(_grev_test_body());
	pg_assert_same('Kodpen Café', $place['name'], 'the place name');
	pg_assert_same(4.6, $place['rating'], 'the rating');
	pg_assert_same(128, $place['count'], 'the rating count');
	pg_assert_same('https://maps.google.com/?cid=123', $place['maps_url'], 'the place on Google Maps');
	pg_assert_same(5, count($place['reviews']), 'five reviews at most');

	$first = $place['reviews'][0];
	pg_assert_same('<script>alert(1)</script>', $first['author'], 'kept as text, escaped where drawn');
	pg_assert_same('https://www.google.com/maps/contrib/1', $first['author_url'], 'the author attribution link');
	pg_assert_same('https://lh3.googleusercontent.com/a/p1', $first['photo'], 'the author photo');
	pg_assert_same('Review 1', $first['text'], 'the text in the language asked');
	pg_assert_same('1 weeks ago', $first['relative'], 'Google\'s relative time');
	pg_assert_same(5, $first['rating'], 'the review rating');
	pg_assert_same('https://www.google.com/maps/reviews/data=1', $first['url'], 'the review on Google Maps');
	pg_assert_same('2026-10-01T10:00:00Z', $first['published'], 'the publish time');
	pg_assert_same('', $place['reviews'][2]['photo'], 'no photo given');
	pg_assert_same('', $place['reviews'][3]['photo'], 'only an https photo address is kept');

	pg_assert_same(null, pg_grev_parse('not json'), 'not an answer');
	pg_assert_same(null, pg_grev_parse('{"error":{"code":403}}'), 'an error answer');
	$bare = pg_grev_parse('{"id":"x","displayName":{"text":"Shop"}}');
	pg_assert_same(array(), $bare['reviews'], 'a place without reviews');
	pg_assert_same(0, $bare['count'], 'and no ratings');
}

function test_google_reviews_cache_window()
{
	$now = 1800000000;
	pg_assert_true(pg_grev_due(null, $now), 'nothing kept: read');
	pg_assert_false(pg_grev_due(array('fetched_at' => $now - 60, 'tried_at' => $now - 60), $now), 'fresh: no read');
	pg_assert_true(pg_grev_due(array('fetched_at' => $now - PG_GREV_FRESH - 1, 'tried_at' => $now - PG_GREV_FRESH - 1), $now), 'stale: read');
	pg_assert_false(pg_grev_due(array('fetched_at' => $now - PG_GREV_FRESH - 1, 'tried_at' => $now - 60), $now), 'a failed read waits before the next try');

	pg_assert_true(pg_grev_usable(array('fetched_at' => $now - PG_GREV_FRESH - 1, 'place' => array('name' => 'x')), $now), 'a stale copy still shows after a failure');
	pg_assert_false(pg_grev_usable(array('fetched_at' => $now - PG_GREV_KEEP - 1, 'place' => array('name' => 'x')), $now), 'never past the ceiling');
	pg_assert_false(pg_grev_usable(null, $now), 'nothing kept');
	pg_assert_true(PG_GREV_KEEP <= 86400, 'Places content is kept a day at most');
}

function test_google_reviews_rows_and_tokens()
{
	$place = pg_grev_parse(_grev_test_body());
	$rows = pg_grev_rows($place);
	pg_assert_same(5, count($rows), 'one row per review');
	pg_assert_same('Author 2', $rows[1]['__review_author_name'], 'author name');
	pg_assert_same('3', $rows[1]['__review_rating'], 'rating as text');
	pg_assert_same('2 weeks ago', $rows[1]['__review_relative_time'], 'relative time');

	$tokens = pg_grev_static_tokens($place);
	pg_assert_same('Kodpen Café', $tokens['__place_name'], 'place name');
	pg_assert_same('4.6', $tokens['__place_rating'], 'rating with one decimal');
	pg_assert_same('128', $tokens['__place_review_count'], 'count');
	pg_assert_same('https://maps.google.com/?cid=123', $tokens['__place_maps_url'], 'Maps link');
	pg_assert_same('Google Maps', $tokens['__google_attribution'], 'Google\'s attribution, untranslated');
}

function test_google_reviews_render()
{
	$tree = json_encode(array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'h3', 'text' => 'x', '_bindings' => array('text' => '__place_name')), 'children' => array()),
		array('type' => 'loop_area', 'props' => array(), 'children' => array(
			array('type' => 'semantic', 'props' => array('tag' => 'p', 'text' => 'x', '_bindings' => array('text' => '__review_author_name')), 'children' => array()),
		)),
	)));
	$place = pg_grev_parse(_grev_test_body());

	$html = _render_system_widget_google_reviews($tree, 7, array(), 'preview', $place);
	pg_assert_contains('Kodpen Café', $html, 'the place name is drawn');
	pg_assert_contains('&lt;script&gt;alert(1)&lt;/script&gt;', $html, 'an author name is escaped');
	pg_assert_false(strpos($html, '<script>alert'), 'never as markup');
	pg_assert_contains('Author 5', $html, 'the fifth review');
	pg_assert_false(strpos($html, 'Author 6'), 'never a sixth');

	pg_assert_same('', _render_system_widget_google_reviews($tree, 7, array(), 'preview', null), 'not configured or nothing read: nothing drawn');
	pg_assert_same('', _render_system_widget_google_reviews('', 7, array(), 'preview', $place), 'no layout: nothing drawn');
}

function test_google_reviews_registered()
{
	$labels = pg_sw_type_labels();
	pg_assert_true(isset($labels['google_reviews']), 'panel label');
	$designer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_contains("_render_system_widget_google_reviews(", $designer, 'dispatched by _expand_system_widgets()');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("{ type: 'google_reviews'", $js, 'in SW_TYPES');
	pg_assert_contains("case 'google_reviews': {", $js, 'has a starter tree');
	pg_assert_contains("google_reviews:    SW_GOOGLE_REVIEWS_TOKEN_GROUPS", $js, 'has a token palette');
	pg_assert_contains("tag_cloud_region: 1, google_reviews: 1 };", $js, 'draws no Messages block');
}
