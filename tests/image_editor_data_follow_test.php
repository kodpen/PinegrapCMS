<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's canvas card that follows its tables
 * (includes/fn/image_editor_data.php, workspace_canvas_picture.js): the
 * tables a card reads, the reads as JSON keeps them, the mark the
 * conversation carries and the reads the card's drawing is handed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/image_editor_data.php');
require_once(dirname(__FILE__) . '/image_editor_data_menu_test.php');

// The tables of the open scene's data layers, once each, in their order;
// a layer of no table and other layers name none.
function test_image_editor_data_doc_tables()
{
	$doc = array('v' => 1, 'artboard' => array('width' => 10, 'height' => 10), 'layers' => array(
		array('type' => 'data', 'data' => array('table' => 7)),
		array('type' => 'text', 'data' => array('table' => 9)),
		array('type' => 'data', 'data' => array('table' => '3')),
		array('type' => 'data', 'data' => array('table' => 7)),
		array('type' => 'data', 'data' => array('table' => 0)),
		array('type' => 'data', 'data' => array('table' => -2)),
		array('type' => 'data'),
		'x',
	), 'scenes' => array(array('layers' => array(array('type' => 'data', 'data' => array('table' => 11))))));

	pg_assert_same(array(7, 3), pg_image_editor_data_doc_tables($doc), 'the open scene\'s tables, once each');
	pg_assert_same(array(7, 3), pg_image_editor_data_doc_tables(json_decode(json_encode($doc))), 'object decode');
	pg_assert_same(array(), pg_image_editor_data_doc_tables(null), 'no document');
	pg_assert_same(array(), pg_image_editor_data_doc_tables(array('layers' => 'x')), 'no layers');

	$many = array('layers' => array());

	for ($i = 1; $i <= 30; $i++) {
		$many['layers'][] = array('type' => 'data', 'data' => array('table' => $i));
	}

	pg_assert_same(range(1, PG_IMAGE_EDITOR_DATA_CARD_TABLES), pg_image_editor_data_doc_tables($many), 'a bound number of tables');
}

// A read keeps its columns and cells objects in JSON, empty ones too; a
// refusal stays as it is.
function test_image_editor_data_read_json()
{
	$read = pg_image_editor_data_read_json(array('columns' => array(), 'rows' => array(array('cells' => array()))));

	pg_assert_same('{"columns":{},"rows":[{"cells":{}}]}', json_encode($read));
	pg_assert_same(array('state' => 'refused'), pg_image_editor_data_read_json(array('state' => 'refused')));
}

// The conversation marks a canvas whose newest version holds a data layer,
// the card and the joined card's drawing alike, and the save endpoint
// answers the reads.
function test_image_editor_data_follow_wiring()
{
	$canvas = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
	$messages = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	$card = file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_card.php');
	$save = file_get_contents(PG_FUNCTIONS_DIR . '/workspace_file_save.php');
	$ws = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$page = file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_page_card.js');

	pg_assert_contains('doc_json LIKE \'%\"type\":\"data\"%\' AS charted', $canvas, 'one LIKE, no decode');
	pg_assert_contains("'canvas_data'", $messages, 'the card\'s mark');
	pg_assert_contains("'data'    => !empty(\$state['data'])", $card, 'the joined drawing\'s mark');
	pg_assert_contains("=== 'canvas_data')", $save, 'the reads\' action');
	pg_assert_contains('pg_image_editor_data_card_reads($viewer', $save, 'read for this reader');
	pg_assert_contains('PGWsCanvasPicture.follow(message, img', $ws, 'the card follows');
	pg_assert_contains('self.showImage(file.drawn || file.url)', $ws, 'the viewer shows the card\'s drawing');
	pg_assert_contains('PGWsCanvasPicture.follow(', $page, 'the joined card follows');
}

// The reads handed to the card's drawing: every table a data layer names
// has one, so the painter never asks the editor's own action; one not
// answered draws as no rows.
function test_image_editor_data_follow_reads_for()
{
	$r = image_editor_data_menu_node(
		'global.window = global; require(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/workspace_canvas_picture.js') . ');'
		. 'var P = window.PGWsCanvasPicture, read = { columns: { a: { name: "A" } }, rows: [] };'
		. 'var doc = { layers: [{ type: "data", data: { table: 7 } }, { type: "text" }, { type: "data", data: { table: 3 } }, { type: "data" }] };'
		. 'console.log(JSON.stringify({ all: P.readsFor(doc, { 7: read }), none: P.readsFor({ layers: [] }, null), bad: P.readsFor(null, { 7: read }) }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_false(isset($r['error']), isset($r['error']) ? $r['error'] : 'ran');
	// Number-like keys come in their order in JSON.
	pg_assert_same(array('3' => null, '7' => array('columns' => array('a' => array('name' => 'A')), 'rows' => array())), $r['all']);
	pg_assert_same(array(), $r['none']);
	pg_assert_same(array(), $r['bad']);
}
