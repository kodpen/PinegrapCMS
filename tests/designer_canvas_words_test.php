<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the words the visual editor draws on the canvas of the Express
 * Order, Shopping Cart and Catalog Detail widgets: the English sources
 * name no table, column or file, and their Turkish wording keeps none of
 * the English terms the rest of the panel translates.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// The English sources of the Express Order section labels (eo_*: …) and
// the saved cart link value.
function _dcw_sources()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	preg_match_all("/^\s+eo_[a-z_]+:\s+_sdT\('((?:[^'\\\\]|\\\\.)*)'\)/m", $js, $m);
	$keys = array_map('stripslashes', $m[1]);
	pg_assert_true(count($keys) > 20, 'the section labels are found');
	foreach (array('Variant Attribute', 'Variant Attribute Template', 'Saved Cart Link — alert box (HTML; empty when there is no order)', 'Add an Attribute', 'Attribute filter') as $k) {
		pg_assert_true(strpos($js, "_sdT('" . $k . "')") !== false, 'used: ' . $k);
		$keys[] = $k;
	}
	return $keys;
}

function test_designer_canvas_words_sources_name_no_table_or_file()
{
	foreach (_dcw_sources() as $key) {
		pg_assert_same(0, preg_match('/\.php\b|\b[a-z]+_id\b|\borders\./', $key), 'no table, column or file name: ' . $key);
	}
}

function test_designer_canvas_words_turkish_has_no_english_terms()
{
	$tr = pg_test_language_map('tr');
	foreach (_dcw_sources() as $key) {
		pg_assert_true(isset($tr[$key]), 'translated: ' . $key);
		pg_assert_same(0, preg_match('/\b(Attribute|alert|visitor|Upsell|LEGACY|tree|checkbox|modal|radyolar)\b|\.php\b/u', $tr[$key]), 'Turkish wording: ' . $tr[$key]);
	}
}

// Every key the visual editor translates: its Turkish wording keeps none of
// the English terms the panel translates elsewhere and names no file.
function test_designer_canvas_words_all_editor_keys_turkish()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	preg_match_all("/_sdT\('((?:[^'\\\\]|\\\\.)*)'/", $js, $m);
	$keys = array_unique(array_map('stripslashes', $m[1]));
	pg_assert_true(count($keys) > 1000, 'the editor keys are found');

	$tr = pg_test_language_map('tr');
	foreach ($keys as $key) {
		if (!isset($tr[$key])) {
			continue;
		}
		pg_assert_same(0, preg_match('/\b(legacy|Legacy|LEGACY|checkbox|alert|Attribute|Breadcrumbs?)\b|\.php\b/u', $tr[$key]), 'Turkish wording: ' . $tr[$key]);
	}
}

// The two legacy e-mail controls name no contact column in Turkish.
function test_designer_canvas_words_legacy_email_names_no_column()
{
	$tr = pg_test_language_map('tr');
	foreach (array('Email (legacy name; contact: email_address)', 'Email again (legacy name; optional, must match when filled in)') as $key) {
		pg_assert_true(isset($tr[$key]), 'translated: ' . $key);
		pg_assert_false(isset($tr[$key]) && (strpos($tr[$key], '_') !== false), 'no column name: ' . $key);
	}
}

// The Breadcrumb component's canvas placeholder title is translated.
function test_designer_canvas_words_breadcrumb_placeholder_translated()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_false(strpos($js, "'<strong>Breadcrumb</strong>'") !== false, 'the placeholder title is not translated');
}
