<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Figma connection's pure parts (includes/fn/figma.php): the
 * token's form, a file link, what a refused answer means, and the frame list
 * of a file read with depth 2.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/figma.php');

function test_figma_token_form()
{
	pg_assert_true(pg_figma_token_valid('figd_' . str_repeat('aB3-_', 8)), 'a personal access token');
	pg_assert_true(pg_figma_token_valid(str_repeat('a1', 20)), 'an older token without the prefix');
	pg_assert_false(pg_figma_token_valid('figd_short'), 'too short');
	pg_assert_false(pg_figma_token_valid('figd_' . str_repeat('a', 30) . ' x'), 'no space');
	pg_assert_false(pg_figma_token_valid(str_repeat('a', 251)), 'too long');
	pg_assert_false(pg_figma_token_valid(null), 'not a string');
}

function test_figma_file_link()
{
	pg_assert_same(array('key' => 'AbC123xyz0QW', 'node' => ''), pg_figma_file_link('https://www.figma.com/design/AbC123xyz0QW/Shop-site'), 'a design link');
	pg_assert_same(array('key' => 'AbC123xyz0QW', 'node' => '12:345'), pg_figma_file_link(' https://www.figma.com/design/AbC123xyz0QW/Shop?node-id=12-345&t=x '), 'node-id with a dash becomes the API\'s colon');
	pg_assert_same('AbC123xyz0QW', pg_figma_file_link('https://figma.com/file/AbC123xyz0QW')['key'], 'an older file link without www');
	pg_assert_same('AbC123xyz0QW', pg_figma_file_link('https://www.figma.com/proto/AbC123xyz0QW/x')['key'], 'a prototype link');
	pg_assert_same(null, pg_figma_file_link('https://www.figma.com/board/AbC123xyz0QW/x'), 'a FigJam board is no design');
	pg_assert_same(null, pg_figma_file_link('https://evil.example/design/AbC123xyz0QW/x'), 'another host');
	pg_assert_same(null, pg_figma_file_link('https://www.figma.com.evil.example/design/AbC123xyz0QW'), 'a look-alike host');
	pg_assert_same(null, pg_figma_file_link('http://www.figma.com/design/AbC123xyz0QW'), 'plain http');
	pg_assert_same('', pg_figma_file_link('https://www.figma.com/design/AbC123xyz0QW/x?node-id=evil')['node'], 'a bad node-id is dropped');
	pg_assert_same(null, pg_figma_file_link(array()), 'not a string');
}

function test_figma_answer_error()
{
	pg_assert_same('token', pg_figma_answer_error(403, '{"status":403,"err":"Invalid token"}')['kind'], 'a refused token');
	pg_assert_same('token', pg_figma_answer_error(403, '{"status":403,"err":"Token expired"}')['kind'], 'an expired token');
	pg_assert_same('scope', pg_figma_answer_error(403, '{"status":403,"err":"Invalid scope(s): file_content:read"}')['kind'], 'a token without the scope');
	pg_assert_same('access', pg_figma_answer_error(403, '{"status":403,"err":"File not shared"}')['kind'], 'a file the account cannot open');
	pg_assert_same('access', pg_figma_answer_error(404, '{"status":404,"err":"Not found"}')['kind'], 'no such file');
	pg_assert_same(array('kind' => 'limit', 'retry' => 3600), pg_figma_answer_error(429, '', array('retry-after' => '3600')), 'the rate limit and its wait');
	pg_assert_same('network', pg_figma_answer_error(0, '')['kind'], 'no answer');
	pg_assert_same('network', pg_figma_answer_error(503, 'down')['kind'], 'Figma down');
	pg_assert_same('other', pg_figma_answer_error(400, '{"err":"Bad"}')['kind'], 'anything else');
}

function test_figma_frame_list()
{
	$frame = function ($id, $name, $w, $h, $type = 'FRAME') {
		return array('id' => $id, 'name' => $name, 'type' => $type, 'absoluteBoundingBox' => array('x' => 0, 'y' => 0, 'width' => $w, 'height' => $h));
	};

	$file = array('name' => 'Shop site', 'document' => array('id' => '0:0', 'type' => 'DOCUMENT', 'children' => array(
		array('id' => '0:1', 'name' => 'Notes', 'type' => 'CANVAS', 'children' => array(
			array('id' => '9:1', 'name' => 'Sticky', 'type' => 'TEXT'),
		)),
		array('id' => '1:1', 'name' => 'Desktop', 'type' => 'CANVAS', 'children' => array(
			$frame('1:2', 'Home', 1440, 3200.4),
			$frame('1:3', 'Phone home', 390, 2400),
			$frame('1:4', 'Card', 1280, 800, 'COMPONENT'),
			array('id' => '1:5', 'name' => 'Group', 'type' => 'GROUP'),
			array_merge($frame('1:6', 'Hidden', 1440, 900), array('visible' => false)),
		)),
		array('id' => '2:1', 'name' => 'More', 'type' => 'CANVAS', 'children' => array(
			$frame('2:2', 'About', 1440, 2000),
		)),
	)));

	$list = pg_figma_frame_list($file, 12);

	pg_assert_same('Shop site', $list['name'], 'the file name');
	pg_assert_same(3, $list['page_count'], 'every page is listed');
	pg_assert_same(4, $list['frame_count'], 'frames and components only, hidden ones left out');
	pg_assert_same(array(), $list['pages'][0]['frames'], 'a page with no frame has an empty list');
	pg_assert_same(array('id' => '1:2', 'name' => 'Home', 'width' => 1440, 'height' => 3200, 'ticked' => true), $list['pages'][1]['frames'][0], 'a frame with its rounded size');
	pg_assert_false($list['pages'][1]['frames'][1]['ticked'], 'a phone-wide frame is not ticked at the start');
	pg_assert_true($list['pages'][1]['frames'][2]['ticked'], 'a desktop-wide component is ticked');
	pg_assert_false($list['pages'][2]['frames'][0]['ticked'], 'only the first page with frames is ticked');
	pg_assert_same(array('1:2', '1:3', '1:4', '2:2'), pg_figma_frame_ids($list), 'the ids in order');

	$many = array();
	for ($i = 0; $i < 15; $i++) {
		$many[] = $frame('3:' . $i, 'F' . $i, 1440, 900);
	}
	$big = pg_figma_frame_list(array('document' => array('children' => array(array('id' => '3:0', 'name' => 'P', 'type' => 'CANVAS', 'children' => $many)))), 12);
	$ticked = 0;
	foreach ($big['pages'][0]['frames'] as $f) {
		$ticked += $f['ticked'] ? 1 : 0;
	}
	pg_assert_same(12, $ticked, 'never more ticked than one import takes');

	pg_assert_same(null, pg_figma_frame_list(array('err' => 'x')), 'an answer that is no file');
}

function test_figma_refusal_says_each_kind()
{
	pg_assert_same('token', pg_figma_refusal(array('kind' => 'token', 'retry' => 0))['code'], 'a refused token');
	pg_assert_same('scope', pg_figma_refusal(array('kind' => 'scope', 'retry' => 0))['code'], 'no file scope');
	pg_assert_same('access', pg_figma_refusal(array('kind' => 'access', 'retry' => 0))['code'], 'a file it cannot open');
	pg_assert_same('not_connected', pg_figma_refusal(array('kind' => 'not_connected', 'retry' => 0))['code'], 'no token');
	pg_assert_same('other', pg_figma_refusal(array('kind' => 'strange', 'retry' => 0))['code'], 'an unknown kind');
	$limit = pg_figma_refusal(array('kind' => 'limit', 'retry' => 150));
	pg_assert_same(array('limit', 150), array($limit['code'], $limit['retry']), 'the limit with its wait');
	pg_assert_true(strpos($limit['detail'], '3') !== false, 'the wait in whole minutes, rounded up');
}

function test_figma_on_connections()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_registry.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/connections_screen.php');

	$row = pg_connections_registry()['figma'];
	pg_assert_same(array('connections.php', 'other'), array($row['place'], $row['group']), 'its page on Connections, group Other');
	pg_assert_same(array('kind' => 'service', 'key' => 'figma'), $row['state'], 'the sealed service entry is its state');
	pg_assert_same(array('status', 'details', 'uses'), pg_connections_screen_page_cards('figma', array('figma' => true)), 'a manager types the token');
	pg_assert_same(array('status', 'uses'), pg_connections_screen_page_cards('figma', array('manager' => true)), 'the details only with the Figma gate');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/connections.php');
	pg_assert_true(strpos($source, "name=\"figma_token\" value=\"\"") !== false, 'the stored token is never drawn back');
	pg_assert_true(strpos($source, 'pg_figma_try_token($figma_token)') < strpos($source, "pg_figma_connection_store(array('token' => \$figma_token, 'name' => \$figma_try['name']"), 'Save and try asks Figma before storing');
}

function test_figma_start_in_the_new_design_window()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	$keys = array_map(function ($k) { return $k['key']; }, pg_designer_start_kinds(true, true));
	pg_assert_same(array('blank', 'template', 'figma', 'canva', 'import', 'paste'), $keys, 'Figma after Template, Canva after it, as the plan orders the starts');
	pg_assert_false(in_array('figma', array_map(function ($k) { return $k['key']; }, pg_designer_start_kinds(true)), true), 'not listed until the import is there');
	pg_assert_same(function_exists('pg_figma_import_start'), pg_designer_figma_start_on(), 'listed once the import exists');

	$empty = pg_designer_figma_pane(array(), true);
	pg_assert_true(strpos($empty, 'connections.php?service=figma') !== false && strpos($empty, 'sd-fg-link') === false, 'not connected: the way to Connections, no field');
	pg_assert_true(strpos(pg_designer_figma_pane(array(), false), 'connections.php') === false, 'who may not open Connections is told to ask');

	$pane = pg_designer_figma_pane(array('token' => 'figd_secret_token_value_123', 'name' => 'Ada', 'state' => 'ok', 'tried' => 1), true);
	pg_assert_true(strpos($pane, 'id="sd-fg-link"') !== false, 'connected: the link field');
	pg_assert_true(strpos($pane, 'figd_secret') === false, 'the token never reaches the browser');
	pg_assert_true(strpos($pane, 'theme-warning') === false, 'no warning while the token works');
	pg_assert_true(strpos(pg_designer_figma_pane(array('token' => 'x', 'name' => '', 'state' => 'refused', 'tried' => 1), true), 'theme-warning') !== false, 'a refused token says so above the field');
}
