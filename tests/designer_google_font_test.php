<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_google_font_hrefs() (includes/fn/designer.php): the Google
 * Fonts links printed for a design's font, the regular face linked beside
 * the weighted request so a family lacking a ticked weight still loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_google_font_hrefs_link_regular_face_beside_weights()
{
	pg_assert_same(array(
		'https://fonts.googleapis.com/css2?family=Lobster&display=swap',
		'https://fonts.googleapis.com/css2?family=Lobster:wght@400;700&display=swap',
	), pg_google_font_hrefs(array('family' => 'Lobster', 'weights' => array('400', '700'))));
}

function test_google_font_hrefs_regular_only_is_one_link()
{
	pg_assert_same(array('https://fonts.googleapis.com/css2?family=Open%20Sans&display=swap'), pg_google_font_hrefs(array('family' => 'Open Sans')));
	pg_assert_same(array('https://fonts.googleapis.com/css2?family=Open%20Sans&display=swap'), pg_google_font_hrefs(array('family' => 'Open Sans', 'weights' => array(400))));
}

function test_google_font_hrefs_sort_and_clean_weights()
{
	pg_assert_same(array(
		'https://fonts.googleapis.com/css2?family=Roboto&display=swap',
		'https://fonts.googleapis.com/css2?family=Roboto:wght@300;700&display=swap',
	), pg_google_font_hrefs(array('family' => 'Roboto', 'weights' => array('700', 'bold', '300', '700', '9999'))));
}

function test_google_font_hrefs_skip_disabled_and_nameless()
{
	pg_assert_same(array(), pg_google_font_hrefs(array('family' => 'Lobster', 'enabled' => false)));
	pg_assert_same(array(), pg_google_font_hrefs(array('family' => '')));
	pg_assert_same(array(), pg_google_font_hrefs('Lobster'));
}
