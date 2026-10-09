<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shared components and system widgets panel of the Visual
 * Page Editor's design list: the PHP names of the widget kinds
 * (pg_sw_type_labels()) mirror the editor's SW_TYPES list, kind for kind and
 * key for key, so a row reads the same on both screens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// kind => English key of SW_TYPES in style_designer.js.
function _test_components_js_types()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$start = strpos($js, 'var SW_TYPES = [');
	$end = $start === false ? false : strpos($js, '];', $start);
	if ($start === false || $end === false) {
		return array();
	}
	preg_match_all("/\\{\\s*type:\\s*'([a-z_]+)',\\s*label:\\s*_sdT\\('((?:[^'\\\\]|\\\\.)*)'\\)/", substr($js, $start, $end - $start), $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $row) {
		$out[$row[1]] = $row[2];
	}
	return $out;
}

// kind => English key of pg_sw_type_labels() in includes/fn/widgets.php.
function _test_components_php_types()
{
	$php = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$start = strpos($php, 'function pg_sw_type_labels()');
	$end = $start === false ? false : strpos($php, "\n}", $start);
	if ($start === false || $end === false) {
		return array();
	}
	preg_match_all("/'([a-z_]+)'\\s*=>\\s*lang\\('((?:[^'\\\\]|\\\\.)*)'\\)/", substr($php, $start, $end - $start), $m, PREG_SET_ORDER);
	$out = array();
	foreach ($m as $row) {
		$out[$row[1]] = $row[2];
	}
	return $out;
}

function test_designer_components_type_labels_cover_every_kind()
{
	$js = _test_components_js_types();
	pg_assert_true(count($js) > 0, 'SW_TYPES is found in style_designer.js');
	pg_assert_same(array_keys($js), array_keys(pg_sw_type_labels()), 'same kinds, same order');
}

function test_designer_components_type_labels_use_the_editor_keys()
{
	$js = _test_components_js_types();
	pg_assert_same($js, _test_components_php_types(), 'each kind is named with the key the editor uses');
}
