<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the new dashboard's gallery and side panel (includes/dashboard/
 * v2/gallery.php, assets/js/pg_dash_panel.js): every card has a gallery
 * line and a sketch the script draws, the Recommended lists are cut to the
 * cards a person sees, the look's Auto matches the old dashboard's, and the
 * script reads only texts the page sends.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/layout.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/cards.php');
require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/gallery.php');

function test_dashboard_v2_gallery_covers_every_card()
{
	$gallery = pg_dashboard_v2_gallery_cards();
	$kinds = pg_dashboard_v2_sketches();

	pg_assert_same(array_keys(pg_dashboard_v2_cards()), array_keys($gallery));

	foreach ($gallery as $id => $entry) {
		pg_assert_true($entry['desc'] !== '', 'line of ' . $id);
		pg_assert_true(in_array($entry['new'], array(0, 1), true), 'new of ' . $id);
		pg_assert_true(in_array($entry['sketch'], $kinds, true), 'sketch of ' . $id);
	}

	// Cards that grew from an old widget are not tagged new.
	pg_assert_same(0, $gallery['visitors']['new']);
	pg_assert_same(1, $gallery['notes']['new']);
}

function test_dashboard_v2_gallery_script_draws_every_sketch()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_panel.js');

	foreach (pg_dashboard_v2_sketches() as $kind) {
		pg_assert_true(preg_match('/^\s{8}' . $kind . ': function \(box\)/m', $source) === 1, 'sketch ' . $kind);
	}
}

function test_dashboard_v2_recommended_cut_to_visible()
{
	$visible = array('notes' => array(), 'ga' => array());

	pg_assert_same(array('notes', 'ga'), pg_dashboard_v2_recommended(array('notes', 'engagement', 'ga', 'files'), $visible));
	pg_assert_same(array(), pg_dashboard_v2_recommended(array(), $visible));
	pg_assert_same(array(), pg_dashboard_v2_recommended(array(5, null), $visible));
}

function test_dashboard_v2_look_choices_follow_appearance()
{
	$choices = pg_dashboard_v2_look_choices();
	$appearance = pg_dashboard_appearance_choices();

	pg_assert_same($appearance['widget_theme'], array_keys($choices['style']));
	pg_assert_same($appearance['panel_backdrop'], array_keys($choices['backdrop']));
	pg_assert_same(array('flat' => 'none', 'neon' => 'dusk', 'glass' => 'mesh', 'aurora' => 'mesh'), $choices['auto']);
}

// The panel's texts come from the page: every S.key the script reads is in
// the strings the config sends, and the script follows the edit mode's in
// the bundle.
function test_dashboard_v2_panel_script_texts_and_bundle()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/dashboard/v2/page_grid.php');

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_panel.js');
	preg_match_all('/\bS(?:\.([a-z_]+)|\["([a-z_]+)"\])/', $source, $found);
	$used = array_unique(array_filter(array_merge($found[1], $found[2])));
	$strings = pg_dashboard_v2_edit_strings() + pg_dashboard_v2_gallery_strings();

	pg_assert_true(count($used) > 15, 'texts found');
	foreach ($used as $key) {
		pg_assert_true(isset($strings[$key]), 'panel text ' . $key);
	}

	foreach (array('since', 'actions', 'tiles', 'status') as $part) {
		pg_assert_true(isset($strings['top_' . $part]), 'top text ' . $part);
	}

	// The gallery's keys must not shadow the grid's own texts.
	pg_assert_same(array(), array_intersect_key(pg_dashboard_v2_gallery_strings(), pg_dashboard_v2_edit_strings()));

	$bundle = pg_js_bundles()['assets/js/pg_dash.min.js'];
	pg_assert_true(array_search('assets/js/pg_dash_panel.js', $bundle, true) > array_search('assets/js/pg_dash_edit.js', $bundle, true), 'bundle order');
}
