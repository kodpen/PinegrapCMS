<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the list of minified scripts (pg_js_bundles(),
 * includes/fn/output.php) and the choice between a .min.js and its sources
 * (pg_js_bundle_files()). Whether a .min.js matches its sources is
 * tools/build_js.php --check's job, not this file's.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every bundle is a .min.js under assets/js/ that is in the tree, built from
// sources that are in the tree and are not themselves minified.
function test_js_bundles_name_files_that_exist()
{
	foreach (pg_js_bundles() as $bundle => $sources) {
		pg_assert_true((bool) preg_match('#^assets/js/[a-z_]+\.min\.js$#', $bundle), $bundle . ' is a .min.js under assets/js/');
		pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $bundle), $bundle . ' is built');
		pg_assert_true(is_array($sources) && (count($sources) > 0), $bundle . ' has sources');

		foreach ($sources as $source) {
			pg_assert_true(is_file(PG_FUNCTIONS_DIR . '/' . $source), $bundle . ': ' . $source . ' exists');
			pg_assert_false((bool) preg_match('#\.min\.js$#', $source), $bundle . ': ' . $source . ' is a source');
		}
	}
}

// A source belongs to one bundle: in two, a screen that loads both would run
// it twice.
function test_js_bundles_list_each_source_once()
{
	$seen = array();

	foreach (pg_js_bundles() as $bundle => $sources) {
		foreach ($sources as $source) {
			pg_assert_false(isset($seen[$source]), $source . ' is in ' . $bundle . (isset($seen[$source]) ? ' and ' . $seen[$source] : ''));
			$seen[$source] = $bundle;
		}
	}
}

// The workspace's scripts are all served through a bundle: a new one written
// into a <script> tag of its own would be served unminified, and the source
// would drift from what the bundles hold without CI noticing.
function test_js_bundles_cover_every_workspace_script()
{
	$listed = array();

	foreach (pg_js_bundles() as $sources) {
		$listed = array_merge($listed, $sources);
	}

	foreach (glob(PG_FUNCTIONS_DIR . '/assets/js/workspace*.js') as $file) {
		$name = 'assets/js/' . basename($file);

		if (preg_match('#\.min\.js$#', $name)) {
			continue;
		}

		pg_assert_contains($name, $listed, 'a bundle lists ' . $name);
	}
}

// The workspace bundle runs the scripts workspace.js leans on before it, as
// the page used to load them; workspace.js itself comes last.
function test_js_bundles_workspace_runs_last()
{
	$bundles = pg_js_bundles();
	$sources = $bundles['assets/js/workspace.min.js'];

	pg_assert_same('assets/js/workspace.js', end($sources));
	pg_assert_same('assets/js/workspace_editor.js', $sources[0], 'the writing box first');
}

// The Workspace list of the main menu is loaded on every panel page of the
// team, so it is a small file of its own and never part of the workspace's
// set (workspace.min.js, 655 KB), which only the workspace's screens load.
function test_js_bundles_side_list_stands_alone()
{
	$bundles = pg_js_bundles();

	pg_assert_same(array('assets/js/workspace_side.js'), $bundles['assets/js/workspace_side.min.js'] ?? null);
	pg_assert_false(in_array('assets/js/workspace_side.js', $bundles['assets/js/workspace.min.js'], true), 'not in the workspace set');
}

// Running minified (the default when ENVIRONMENT_SUFFIX is not 'src') the
// page loads the .min.js alone; a name that is not a bundle loads nothing.
function test_js_bundle_files_pick_the_minified_file()
{
	if (defined('ENVIRONMENT_SUFFIX') && (ENVIRONMENT_SUFFIX !== 'min')) {
		return;
	}

	pg_assert_same(array('assets/js/workspace.min.js'), pg_js_bundle_files('assets/js/workspace.min.js'));
	pg_assert_same(array('assets/js/workspace_tour.min.js'), pg_js_bundle_files('assets/js/workspace_tour.min.js'));
	pg_assert_same(array(), pg_js_bundle_files('assets/js/nothing.min.js'));
}
