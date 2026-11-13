<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the joined card of a canvas with a drawing and a page
 * (includes/workspace/canvas_card.php, assets/js/workspace_page_card.js):
 * the one list of versions, the texts and the script's hooks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_card.php');

function test_workspace_canvas_card_versions()
{
	$list = ws_canvas_card_versions(array(
		'drawing' => array(
			array('version_no' => 1, 'source' => 'agent', 'by' => 'stock', 'user_id' => 3, 'created_at' => 100),
			array('version_no' => 2, 'source' => 'save', 'user_id' => 5, 'created_at' => 400),
		),
		'page'    => array(
			array('version_no' => 1, 'source' => 'agent', 'by' => 'stock', 'user_id' => 3, 'created_at' => 200),
			array('version_no' => 2, 'source' => 'restore', 'user_id' => 5, 'created_at' => 300),
		),
	));

	pg_assert_same(array('drawing2', 'page2', 'page1', 'drawing1'), array_map(function ($row) {
		return $row['part'] . $row['version'];
	}, $list), 'newest first over both parts');
	pg_assert_same(array(true, true, false, false), array_map(function ($row) {
		return $row['current'];
	}, $list), 'each part\'s newest is the one shown now');
	pg_assert_same('stock', $list[3]['by'], 'an agent\'s version names it');
	pg_assert_same('', $list[0]['by'], 'a person\'s save names nobody');
	pg_assert_same('restore', $list[1]['source']);
}

function test_workspace_canvas_card_versions_same_second()
{
	$list = ws_canvas_card_versions(array(
		'page'    => array(array('version_no' => 3, 'source' => 'agent', 'user_id' => 0, 'created_at' => 50), array('version_no' => 4, 'source' => 'agent', 'user_id' => 0, 'created_at' => 50)),
		'drawing' => array(array('version_no' => 9, 'source' => 'agent', 'user_id' => 0, 'created_at' => 50)),
	));

	pg_assert_same(array('drawing9', 'page4', 'page3'), array_map(function ($row) {
		return $row['part'] . $row['version'];
	}, $list), 'one second: by part, then newest first');
	pg_assert_same(array(), ws_canvas_card_versions(array()));
}

function test_workspace_canvas_card_texts_and_hooks()
{
	$strings = ws_page_card_js_strings();

	foreach (array('canvas_part_drawing', 'canvas_part_page', 'canvas_part_data', 'canvas_part_version', 'canvas_all', 'canvas_now', 'canvas_show', 'canvas_restore', 'canvas_restore_ask', 'canvas_restored') as $key) {
		pg_assert_true(isset($strings[$key]) && ($strings[$key] !== ''), $key . ' has its words');
	}

	$card = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_page_card.js');
	$app = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$picture = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_canvas_picture.js');

	pg_assert_true(strpos($card, 'joined: joined') !== false, 'the card script offers joined()');
	pg_assert_true(strpos($card, "'ws_canvas_restore'") !== false, 'a version is put back through ws_canvas_restore');
	pg_assert_true(strpos($app, 'PGWsPageCard.joined(') !== false, 'the conversation draws the joined card');
	pg_assert_true(strpos($app, 'file.joined') < strpos($app, 'file.page && window.PGWsPageCard'), 'before a part\'s own card');
	pg_assert_true(strpos($picture, "'&part=drawing'") !== false, 'a drawing part is drawn and posted as the part');
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/live_page.php'), "ws_canvas_part_message(\$message, 'page')") !== false, 'the frame runs the page part');
}
