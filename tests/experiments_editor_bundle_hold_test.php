<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a tab page's held state in the visual editor
 * (pg_experiment_editor_page_state() in includes/fn/experiments_sheet.php):
 * the Publish sheet's row for a draft an open release bundle holds, for an
 * open experiment's variant, and for a page neither holds.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A bundled draft is held; its label and text name the bundle, never an
// experiment.
function test_experiment_editor_page_state_bundle()
{
	$got = pg_experiment_editor_page_state(null, array('bundle_id' => 7, 'bundle_name' => 'Spring sale', 'status' => 'open'), 'Spring home');

	pg_assert_same('bundle', $got['role']);
	pg_assert_true($got['held'], 'held');
	pg_assert_same(7, $got['bundle_id']);
	pg_assert_same('Spring sale', $got['bundle_name']);
	pg_assert_false(isset($got['status']), 'no experiment status, so no experiment chip');
	pg_assert_contains('Spring sale', $got['label'], 'the row label names the bundle');
	pg_assert_same(pg_page_bundle_hold_text('Spring home', 'Spring sale'), $got['hold_text'], 'the refusal text');
	pg_assert_true(stripos($got['label'] . $got['hold_text'], 'experiment') === false, 'no experiment in the words');
}

// An open experiment's variant keeps its own state and comes first; the
// sheet draws it with its own words.
function test_experiment_editor_page_state_variant()
{
	$variant = array('role' => 'variant', 'status' => 'running', 'live_page_id' => 3, 'live_page_name' => 'Home');
	$got = pg_experiment_editor_page_state($variant, array('bundle_id' => 7, 'bundle_name' => 'Spring sale', 'status' => 'open'), 'Variant');

	pg_assert_same('variant', $got['role']);
	pg_assert_true($got['held'], 'held while running');
	pg_assert_false(isset($got['label']), 'the sheet\'s own label');

	$live = pg_experiment_editor_page_state(array('role' => 'live', 'status' => 'running', 'live_page_id' => 3, 'live_page_name' => 'Home'), null, 'Home');
	pg_assert_false($live['held'], 'the live page is not held');
}

// A page neither holds has no state.
function test_experiment_editor_page_state_neither()
{
	pg_assert_same(null, pg_experiment_editor_page_state(null, null, 'About'));
}

// The sheet draws a held row's own label and note; the editor hands them on.
function test_experiment_editor_page_state_sheet_wiring()
{
	$sheet = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_designer_publish.js');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');

	pg_assert_contains("state.textContent = r.label || text('state-' + r.state);", $sheet, 'the row label');
	pg_assert_contains("label: (hold && hold.label) || '',", $js, 'the editor passes the label');
	pg_assert_contains("note: (hold && hold.hold_text) || '',", $js, 'the editor passes the text');
}
