<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the picker's list of everything (includes/fn/picker_all.php):
 * the request without a folder, the sorts and their keys, the cursor and the
 * keyset condition it becomes, the access rule shared with search, the
 * tree's total.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A list that names no folder is the list of everything; folder 0 stays the
// top, which the dialog asks for today and which holds no items.
function test_picker_all_request()
{
	$all = pg_picker_request(array('kind' => 'image', 'request' => 'list'));

	pg_assert_same('', $all['error']);
	pg_assert_true($all['all']);
	pg_assert_same('modified', $all['sort'], 'newest first by default');
	pg_assert_same('desc', $all['order']);
	pg_assert_same(null, $all['after'], 'the first page has no cursor');
	pg_assert_true(pg_picker_request(array('kind' => 'image', 'request' => 'list', 'folder' => ''))['all']);

	pg_assert_same(false, pg_picker_request(array('kind' => 'image', 'request' => 'list', 'folder' => '0'))['all']);
	pg_assert_same(false, pg_picker_request(array('kind' => 'image', 'request' => 'list', 'folder' => '7'))['all']);
	pg_assert_same(false, pg_picker_request(array('kind' => 'image', 'request' => 'search', 'q' => 'a'))['all'], 'only a list');

	$by_name = pg_picker_request(array('kind' => 'file', 'request' => 'list', 'sort' => 'name'));
	pg_assert_same(array('name', 'asc'), array($by_name['sort'], $by_name['order']));

	$by_size = pg_picker_request(array('kind' => 'file', 'request' => 'list', 'sort' => 'size', 'order' => 'asc'));
	pg_assert_same(array('size', 'asc'), array($by_size['sort'], $by_size['order']));
}

function test_picker_all_sort()
{
	pg_assert_same(array('sort' => 'modified', 'order' => 'desc'), pg_picker_all_sort('files', null, null));
	pg_assert_same(array('sort' => 'name', 'order' => 'asc'), pg_picker_all_sort('files', 'name', 'sideways'), 'an unknown order falls back');
	pg_assert_same(array('sort' => 'size', 'order' => 'desc'), pg_picker_all_sort('files', 'size', null), 'largest first');
	pg_assert_same(array('sort' => 'type', 'order' => 'desc'), pg_picker_all_sort('files', 'type', 'desc'));
	pg_assert_same(array('sort' => 'modified', 'order' => 'desc'), pg_picker_all_sort('files', 'id; DROP', null));
	pg_assert_same(array('sort' => 'modified', 'order' => 'asc'), pg_picker_all_sort('pages', 'size', 'asc'), 'a page has no size');
	pg_assert_same(array('sort' => 'modified', 'order' => 'desc'), pg_picker_all_sort('files', array('name'), array('asc')));
}

// Every sort ends on the id, so no two rows tie; a type ties by name.
function test_picker_all_columns()
{
	$keys = function ($columns) {
		return array_map(function ($column) {
			return $column['key'];
		}, $columns);
	};

	pg_assert_same(array('timestamp', 'id'), $keys(pg_picker_all_columns('files', 'modified')));
	pg_assert_same(array('name', 'id'), $keys(pg_picker_all_columns('files', 'name')));
	pg_assert_same(array('size', 'id'), $keys(pg_picker_all_columns('files', 'size')));
	pg_assert_same(array('type', 'name', 'id'), $keys(pg_picker_all_columns('files', 'type')));
	pg_assert_same(array('page_timestamp', 'page_id'), $keys(pg_picker_all_columns('pages', 'modified')));
	pg_assert_same(array('page_type', 'page_name', 'page_id'), $keys(pg_picker_all_columns('pages', 'type')));
	pg_assert_same(array('page_timestamp', 'page_id'), $keys(pg_picker_all_columns('pages', 'size')), 'no size key for pages');

	pg_assert_same('timestamp DESC, id DESC', pg_picker_all_order_by(pg_picker_all_columns('files', 'modified'), 'desc'), 'the bare column, so its index serves the order');
	pg_assert_same('type ASC, name ASC, id ASC', pg_picker_all_order_by(pg_picker_all_columns('files', 'type'), 'asc'));
	pg_assert_same('page.page_name ASC, page.page_id ASC', pg_picker_all_order_by(pg_picker_all_columns('pages', 'name'), 'asc'));
}

function test_picker_all_row_keys()
{
	$row = array('id' => '42', 'name' => 'şube.jpg', 'type' => null, 'timestamp' => null, 'size' => '1200');

	pg_assert_same(array(null, 42), pg_picker_all_row_keys($row, pg_picker_all_columns('files', 'modified')), 'a NULL time stays NULL');
	pg_assert_same(array(null, 'şube.jpg', 42), pg_picker_all_row_keys($row, pg_picker_all_columns('files', 'type')));
	pg_assert_same(array(1200, 42), pg_picker_all_row_keys($row, pg_picker_all_columns('files', 'size')));
}

// A cursor comes back as it went out; one of another sort, order or shape is
// refused, and the request with it is an error, not a first page again.
function test_picker_cursor_round_trip()
{
	$type = pg_picker_all_columns('files', 'type');
	$cursor = pg_picker_cursor_encode('type', 'asc', array('jpg', 'İstanbul köprü.jpg', 17));

	pg_assert_true((bool) preg_match('/^[A-Za-z0-9_-]+$/', $cursor), 'URL-safe');
	pg_assert_same(array('jpg', 'İstanbul köprü.jpg', 17), pg_picker_cursor_decode($cursor, 'type', 'asc', $type));
	pg_assert_same(null, pg_picker_cursor_decode($cursor, 'type', 'desc', $type), 'another order');
	pg_assert_same(null, pg_picker_cursor_decode($cursor, 'name', 'asc', pg_picker_all_columns('files', 'name')), 'another sort');
	pg_assert_same(null, pg_picker_cursor_decode($cursor . '!', 'type', 'asc', $type));
	pg_assert_same(null, pg_picker_cursor_decode('', 'type', 'asc', $type));
	pg_assert_same(null, pg_picker_cursor_decode(array($cursor), 'type', 'asc', $type));
	pg_assert_same(null, pg_picker_cursor_decode(substr($cursor, 0, 10), 'type', 'asc', $type), 'a cut cursor');

	$modified = pg_picker_all_columns('files', 'modified');
	pg_assert_same(null, pg_picker_cursor_decode(pg_picker_cursor_encode('modified', 'desc', array('1700000000', 3)), 'modified', 'desc', $modified), 'a number as text');
	pg_assert_same(null, pg_picker_cursor_decode(pg_picker_cursor_encode('modified', 'desc', array(-1, 3)), 'modified', 'desc', $modified));
	pg_assert_same(array(null, 3), pg_picker_cursor_decode(pg_picker_cursor_encode('modified', 'desc', array(null, 3)), 'modified', 'desc', $modified), 'a NULL time');
	pg_assert_same(null, pg_picker_cursor_decode(pg_picker_cursor_encode('modified', 'desc', array(5, null)), 'modified', 'desc', $modified), 'the id is never NULL');
	pg_assert_same(null, pg_picker_cursor_decode(pg_picker_cursor_encode('modified', 'desc', array(5)), 'modified', 'desc', $modified), 'a key missing');
	pg_assert_same(null, pg_picker_cursor_decode(pg_picker_cursor_encode('name', 'asc', array(str_repeat('a', 256), 3)), 'name', 'asc', pg_picker_all_columns('files', 'name')), 'a name over 255');

	$request = pg_picker_request(array('kind' => 'file', 'request' => 'list', 'cursor' => pg_picker_cursor_encode('modified', 'desc', array(1700000000, 9))));
	pg_assert_same('', $request['error']);
	pg_assert_same(array(1700000000, 9), $request['after']);

	pg_assert_same('request', pg_picker_request(array('kind' => 'file', 'request' => 'list', 'sort' => 'name', 'cursor' => pg_picker_cursor_encode('modified', 'desc', array(1, 9))))['error'], 'a cursor of another sort');
	pg_assert_same('request', pg_picker_request(array('kind' => 'file', 'request' => 'list', 'cursor' => 'not-a-cursor'))['error']);
	pg_assert_same(null, pg_picker_request(array('kind' => 'file', 'request' => 'list', 'folder' => '3', 'cursor' => 'x'))['after'], 'a folder list pages by number');
}

function test_picker_keyset_where()
{
	pg_assert_same(
		'((((timestamp < 1700000000) OR (timestamp IS NULL))) OR ((timestamp = 1700000000) AND (id < 9)))',
		pg_picker_keyset_where(pg_picker_all_columns('files', 'modified'), array(1700000000, 9), 'desc'),
		'newest first: the NULL times come last');

	pg_assert_same(
		'(((timestamp IS NULL) AND (id < 9)))',
		pg_picker_keyset_where(pg_picker_all_columns('files', 'modified'), array(null, 9), 'desc'),
		'after a NULL time going down only NULLs remain');

	pg_assert_same(
		'(((timestamp IS NOT NULL)) OR ((timestamp IS NULL) AND (id > 9)))',
		pg_picker_keyset_where(pg_picker_all_columns('files', 'modified'), array(null, 9), 'asc'),
		'oldest first: the NULL times come first');

	pg_assert_same(
		"(((type > 'jpg')) OR ((type = 'jpg') AND (name > 'a.jpg')) OR ((type = 'jpg') AND (name = 'a.jpg') AND (id > 4)))",
		pg_picker_keyset_where(pg_picker_all_columns('files', 'type'), array('jpg', 'a.jpg', 4), 'asc'));

	$where = pg_picker_keyset_where(pg_picker_all_columns('pages', 'name'), array("o'neil", 2), 'asc');
	pg_assert_true(strpos($where, "o\\'neil") !== false, 'a quote in a name is escaped');
}

// The list of everything is read under the search's condition: the folder
// rule of a role 3 user and the Recycle Bin left out, no folder named.
function test_picker_all_same_access_as_search()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/picker_all.php');

	pg_assert_true(strpos($source, '$where = pg_picker_where_for($kind, $user);') !== false);
	pg_assert_true(strpos((string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/picker.php'), "pg_picker_select(\$kind, pg_picker_where_for(\$kind, \$user))\n") !== false, 'search reads the same condition');
	pg_assert_true(strpos($source, "'path'") === false && strpos($source, 'pg_picker_file_details($kind, array_map(') !== false, 'items carry path through pg_picker_file_details');
}

function test_picker_total()
{
	pg_assert_same(9, pg_picker_total(array(1 => 3, 4 => 5, 7 => 1)));
	pg_assert_same(0, pg_picker_total(array()));
	pg_assert_same(5, pg_picker_total(array(1 => '5')));
}
