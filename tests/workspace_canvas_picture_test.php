<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the picture of an agent's canvas drawn by a member's browser
 * (includes/workspace/canvas.php): which versions wait for it, the address
 * the conversation shows it under, and the read's pending flag.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api_canvas.php');

// Only an agent's version marked pending waits for a browser: a person's
// save carrying the mark along drew its picture itself.
function test_workspace_canvas_doc_pending()
{
	$doc = array('v' => 1, 'artboard' => array('width' => 10, 'height' => 10), 'picture' => 'pending');

	pg_assert_true(ws_canvas_doc_pending($doc, 'agent'), 'agent, pending');
	pg_assert_true(ws_canvas_doc_pending(json_decode(json_encode($doc)), 'agent'), 'object decode');
	pg_assert_false(ws_canvas_doc_pending($doc, 'new'), 'a person\'s save');
	pg_assert_false(ws_canvas_doc_pending($doc, 'saved'), 'a person\'s save over it');
	unset($doc['picture']);
	pg_assert_false(ws_canvas_doc_pending($doc, 'agent'), 'drawn');
	pg_assert_false(ws_canvas_doc_pending(null, 'agent'), 'no document');
}

// The address carries the version, and a pending mark, so a browser that
// kept the blank picture asks again once it is drawn.
function test_workspace_canvas_picture_url()
{
	pg_assert_same('/f/a.png', ws_canvas_picture_url('/f/a.png', null), 'no canvas');
	pg_assert_same('/f/a.png', ws_canvas_picture_url('/f/a.png', array('version_no' => 0, 'pending' => false)), 'no version');
	pg_assert_same('/f/a.png?v=3p', ws_canvas_picture_url('/f/a.png', array('version_no' => 3, 'pending' => true)), 'pending');
	pg_assert_same('/f/a.png?v=3', ws_canvas_picture_url('/f/a.png', array('version_no' => 3, 'pending' => false)), 'drawn');
	pg_assert_same('/f/a.png?x=1&v=4', ws_canvas_picture_url('/f/a.png?x=1', array('version_no' => 4, 'pending' => false)), 'a query already');
}

// The read says pending only while the newest version is an agent's.
function test_workspace_canvas_read_pending_source()
{
	$doc = array('v' => 1, 'artboard' => array('width' => 10, 'height' => 10), 'picture' => 'pending', 'layers' => array());
	$row = array('version_no' => 1, 'source' => 'agent', 'user_id' => 1, 'created_at' => 5);

	pg_assert_same('pending', ws_canvas_read($doc, array($row))['picture'], 'agent\'s version');
	pg_assert_same('pending', ws_canvas_read($doc, array())['picture'], 'just made');
	$row2 = array('version_no' => 2, 'source' => 'new', 'user_id' => 1, 'created_at' => 6);
	pg_assert_same('drawn', ws_canvas_read($doc, array($row, $row2))['picture'], 'a person saved it since');
}
