<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's canvas guides part (assets/js/style_designer_guides.js, the
 * part 'guides'): a bundle of its own after the pure geometry, handed to
 * every editor, run the first time Alt is pressed with one element selected;
 * the core binds its key listener only while one element is selected, and
 * the part draws in a layer of the editor's own, never in the canvas' page.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_designer_guides_is_a_part_after_its_geometry()
{
	$parts = pg_designer_parts();
	pg_assert_same('assets/js/style_designer_guides.min.js', isset($parts['guides']) ? $parts['guides'] : null, 'the part guides');

	$bundles = pg_js_bundles();
	pg_assert_same(array('assets/js/pg_canvas_guides.js', 'assets/js/style_designer_guides.js'), $bundles['assets/js/style_designer_guides.min.js'], 'the geometry runs first');
	pg_assert_false(in_array('assets/js/style_designer_guides.js', $bundles['assets/js/style_designer.min.js'], true), 'not in the core bundle');

	pg_assert_same('always', pg_designer_part_files()['guides']['when'], 'handed to every editor');
}

function test_designer_guides_key_bound_only_with_one_selection()
{
	$core = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($core, '    // ── Canvas guides: Measure');
	pg_assert_true($from !== false, 'the core has the Measure hook');
	$hook = substr($core, (int) $from, 3000);

	pg_assert_true(strpos($hook, 'selectedNodes.length <= 1') !== false, 'one element selected');
	pg_assert_true(strpos($hook, "_sdPartCall('guides', 'measure'") !== false, 'Alt runs the part');
	pg_assert_true(strpos($hook, "removeEventListener('keydown', _sdGuidesKey, true)") !== false, 'the listener is dropped again');
	pg_assert_same(2, substr_count($core, "        _sdGuidesSync();\n"), 'render() and renderSelectionOnly() keep the listener in step');
}

function test_designer_guides_part_draws_outside_the_page()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_guides.js');

	pg_assert_true(strpos($js, "StyleDesigner.definePart('guides', function (K) {") !== false, 'registers the part');
	pg_assert_true(strpos($js, "closest('.sd-canvas-wrapper')") !== false, 'the layer goes into the canvas wrapper of the editor');
	pg_assert_true(strpos($js, 'document.createElement(') !== false, 'built in the editor\'s document');
	pg_assert_false((bool) preg_match('/\bdoc\.(createElement|body\.appendChild)/', $js), 'nothing is put into the canvas\' page');
	pg_assert_true(strpos($js, "doc[fn]('mousemove', onMove, true)") !== false && strpos($js, 'listen(false)') !== false, 'the pointer is listened to only while measuring');
}
