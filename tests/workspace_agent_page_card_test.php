<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the card of a live page (includes/workspace/page_card.php,
 * assets/js/workspace_page_card.js): the card's state from the versions,
 * the frame's sandbox, and what the script keeps to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/page_card.php');

function ws_test_page_card_version_row($no, $handle, $source = 'agent')
{
	return array(
		'version_id' => $no * 10, 'version_no' => $no, 'source' => $source, 'size' => 10, 'user_id' => 0, 'created_at' => 0,
		'doc_json' => ws_page_version_doc(array('id' => $no, 'kind' => 'app', 'handle' => $handle)),
	);
}

function test_workspace_agent_page_card_state()
{
	pg_assert_same(null, ws_page_card_state(array(), true), 'no versions, no page');

	$one = ws_page_card_state(array(ws_test_page_card_version_row(1, 'reports')), true);
	pg_assert_same('reports', $one['by'], 'the maker is the first version\'s agent');
	pg_assert_same('', $one['edited_by'], 'the first version is no edit');
	pg_assert_same(false, $one['can_undo'], 'nothing to undo');
	pg_assert_same(1, $one['version']);

	$edited = ws_page_card_state(array(
		ws_test_page_card_version_row(1, 'reports'),
		ws_test_page_card_version_row(2, 'charts'),
	), true);
	pg_assert_same('reports', $edited['by']);
	pg_assert_same('charts', $edited['edited_by'], 'the newest agent version after the first is an edit');
	pg_assert_same(true, $edited['can_undo']);
	pg_assert_same(2, $edited['version']);

	$reader = ws_page_card_state(array(
		ws_test_page_card_version_row(1, 'reports'),
		ws_test_page_card_version_row(2, 'charts'),
	), false);
	pg_assert_same(false, $reader['can_undo'], 'a reader who may not write cannot undo');
	pg_assert_same('charts', $reader['edited_by'], 'but sees who edited');

	$undone = ws_page_card_state(array(
		ws_test_page_card_version_row(1, 'reports'),
		ws_test_page_card_version_row(2, 'charts'),
		ws_test_page_card_version_row(3, 'reports', 'restore'),
	), true);
	pg_assert_same('', $undone['edited_by'], 'an Undo ends the mark');
	pg_assert_same(false, $undone['can_undo']);
	pg_assert_same(3, $undone['version']);
}

function test_workspace_agent_page_card_frame_and_script()
{
	pg_assert_same('allow-scripts', pg_guarded_frame_panel_sandbox('script'), 'the card frames the shell without allow-same-origin');

	$js = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_page_card.js');
	pg_assert_true(strpos($js, 'allow-same-origin') === false, 'the script never widens the sandbox');
	pg_assert_true(strpos($js, "setAttribute('sandbox', page.sandbox)") !== false, 'the sandbox comes from the server');
	pg_assert_true(strpos($js, 'innerHTML') === false, 'the source is shown as text, never as markup');
	pg_assert_true(strpos($js, 'frameOf(event.source)') !== false, 'a height is taken only from a frame the card made');

	$bundle = pg_js_bundles();
	pg_assert_true(in_array('assets/js/workspace_page_card.js', $bundle['assets/js/workspace.min.js'], true), 'served in the workspace bundle');

	$strings = ws_page_card_js_strings();
	pg_assert_true(strpos($strings['page_by'], '{1}') !== false, 'the maker\'s line names the agent');
}
