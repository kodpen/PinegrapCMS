<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for focus rings drawn by a screen's own styles: the API
 * application rows, the dashboard card lists, the guided tour and the File
 * Manager draw the panel's 2px --pg-primary-ink ring, never a pastel
 * --pg-primary or 35% ring that reads about 1.3-2.2:1 on white.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function faint_focus_rings_source($path)
{
	return preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path));
}

// The application row keeps its tint and gets an inset ring: the scroll box
// around the rows clips anything drawn outside them.
function test_faint_focus_rings_api_rows()
{
	$source = faint_focus_rings_source('api_settings.php');

	pg_assert_contains('.api-row:focus-visible { outline: 2px solid var(--pg-primary-ink); outline-offset: -2px; }', $source);
	pg_assert_same(0, preg_match('/\.api-row:focus-visible[^{]*\{[^}]*outline:\s*0/', $source), 'no row ring removed');
}

// The card clips outer rings, so the scroll region's ring is inset.
function test_faint_focus_rings_dashboard_scroll_regions()
{
	$source = faint_focus_rings_source('welcome.php');

	pg_assert_same(1, preg_match('/\[data-pg-focus-key\^="scroll-"\]:focus-visible\s*\{\s*outline:\s*2px solid var\(--pg-primary-ink\);\s*outline-offset:\s*-2px;\s*\}/', $source), 'inset ink ring');
	pg_assert_same(0, preg_match('/inset 0 0 0 var\(--pg-focus-ring-width\) var\(--pg-focus-ring-color\)/', $source), 'no pastel band');
}

function test_faint_focus_rings_tour_and_file_manager()
{
	foreach (array('assets/css/pg_tour.css', 'view_folders.php') as $path) {
		$source = faint_focus_rings_source($path);

		pg_assert_same(0, preg_match('/:focus-visible[^{]*\{[^}]*outline:\s*2px solid var\(--pg-primary\)/', $source), $path . ': light ring');
	}

	$tour = faint_focus_rings_source('assets/css/pg_tour.css');

	pg_assert_contains('.pg-tour-body:focus-visible { outline: 2px solid var(--pg-primary-ink); outline-offset: 2px; }', $tour);
	pg_assert_contains('.pg-tour-btn:focus-visible { outline: 2px solid var(--pg-primary-ink); outline-offset: 2px; }', $tour);

	$folders = faint_focus_rings_source('view_folders.php');

	pg_assert_contains('#explorer_tree_pane .tree-toggle:focus-visible { outline: 2px solid var(--pg-primary-ink); outline-offset: -2px; }', $folders);
	pg_assert_same(1, preg_match('/#explorer_list_table th \.col-grip:focus-visible\s*\{\s*outline:\s*2px solid var\(--pg-primary-ink\);/', $folders), 'list heading ring');
}
