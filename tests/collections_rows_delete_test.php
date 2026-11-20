<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a collection record's rows on delete and in its History
 * (includes/fn/collection_rows.php): the rows a deleted record lists go
 * with it, the delete confirmations name how many rows go, and a loaded
 * revision keeps only the rows that still exist.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _collections_rows_delete_data($type, $kind, $page_id, $data)
{
	return array('type' => $type, 'collection_kind' => $kind, 'reference_page_id' => $page_id, 'data' => $data);
}

// The codes a record lists in its rows fields, by child collection; any
// other value of the record lists nothing.
function test_collections_rows_delete_held_codes()
{
	$held = pg_coll_rows_held_codes(array(
		_collections_rows_delete_data('text box', 'rows', 40, 'a1, b2'),
		_collections_rows_delete_data('text box', 'rows', 41, 'c3'),
		_collections_rows_delete_data('text box', 'reference', 42, 'd4'),
		_collections_rows_delete_data('text area', 'rows', 43, 'e5'),
		_collections_rows_delete_data('text box', 'rows', 0, 'f6'),
		_collections_rows_delete_data('text box', 'rows', 40, 'b2, g7'),
		_collections_rows_delete_data('text box', 'rows', 44, ''),
	));
	pg_assert_same(array(40 => array('a1', 'b2', 'g7'), 41 => array('c3')), $held);
	pg_assert_same(array(), pg_coll_rows_held_codes(array()));
}

// A record's rows are deleted with it, each through the record delete, and
// their files counted with the record's; a row that cannot be deleted
// stops nothing.
function test_collections_rows_delete_rows_go_with_the_record()
{
	$deleted = array();
	$files = pg_coll_rows_delete_rows(array(7, 8, 9), function ($id) use (&$deleted) {
		$deleted[] = $id;
		if ($id === 8) return false;
		return ($id === 9) ? array('step.jpg') : array();
	});
	pg_assert_same(array(7, 8, 9), $deleted);
	pg_assert_same(array('step.jpg'), $files);
	pg_assert_same(array(), pg_coll_rows_delete_rows(array(), function ($id) { return array('never'); }));
}

// The record delete reads the rows before the record goes and deletes them
// after it.
function test_collections_rows_delete_order_in_the_record_delete()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_writes.php');
	$read = strpos($source, 'pg_coll_rows_held_ids(array($form_id))');
	$gone = strpos($source, 'DELETE FROM forms WHERE id = ?');
	$rows = strpos($source, "pg_coll_rows_delete_rows(\$row_ids, 'pg_submitted_form_delete')");
	pg_assert_true($read !== false && $gone !== false && $rows !== false, 'the record delete takes its rows');
	pg_assert_true($read < $gone && $gone < $rows, 'rows read before the record goes, deleted after');
}

// The confirmations name how many rows go; with none the question stays.
function test_collections_rows_delete_count_in_the_question()
{
	$one = 'WARNING: This submitted form will be permanently deleted.';
	pg_assert_same($one, pg_coll_rows_delete_question($one, 0, false));
	pg_assert_same($one . ' Its 1 row of a Record rows field is deleted with it.', pg_coll_rows_delete_question($one, 1, false));
	pg_assert_same($one . ' Its 3 rows of Record rows fields are deleted with it.', pg_coll_rows_delete_question($one, 3, false));

	$many = 'WARNING: Selected submitted form(s) will be permanently deleted.';
	pg_assert_same($many, pg_coll_rows_delete_question($many, 0, true));
	pg_assert_same($many . ' 1 row of their Record rows fields is deleted with them.', pg_coll_rows_delete_question($many, 1, true));
	pg_assert_same($many . ' 12 rows of their Record rows fields are deleted with them.', pg_coll_rows_delete_question($many, 12, true));
}

// Every delete confirmation of the record list and the record screen asks
// with the rows' count.
function test_collections_rows_delete_confirmations_ask_with_the_count()
{
	$screen = file_get_contents(PG_FUNCTIONS_DIR . '/edit_submitted_form.php');
	pg_assert_true(strpos($screen, 'pg_coll_rows_delete_question(') !== false, 'the record screen names the rows');

	$list = file_get_contents(PG_FUNCTIONS_DIR . '/view_submitted_forms.php');
	pg_assert_true(substr_count($list, 'pg_coll_rows_delete_question(') === 1, 'Delete All Forms names the rows');
	pg_assert_true(strpos($list, 'data-pg-rows-count') !== false, 'Delete Selected asks for its count');

	$delete = file_get_contents(PG_FUNCTIONS_DIR . '/delete_submitted_forms.php');
	pg_assert_true(strpos($delete, "\$_POST['rows_count']") < strpos($delete, 'pg_submitted_form_delete('), 'the count is answered before anything is deleted');
}

function _collections_rows_delete_row($code)
{
	return array('id' => 0, 'reference_code' => $code, 'values' => array(71 => 'Step ' . $code));
}

// A loaded revision keeps the rows that still exist, in its own order, and
// counts the ones deleted since.
function test_collections_rows_delete_revision_keeps_rows_that_exist()
{
	$current = array(_collections_rows_delete_row('a1'), _collections_rows_delete_row('b2'), _collections_rows_delete_row('n9'));

	$keep = pg_coll_rows_revision_keep('b2, x5, a1, y6', $current);
	pg_assert_same(array('b2', 'a1'), array_map(function ($r) { return $r['reference_code']; }, $keep['rows']));
	pg_assert_same(2, $keep['lost']);
	pg_assert_same('Step b2', $keep['rows'][0]['values'][71], 'a kept row brings its values of now');

	$none = pg_coll_rows_revision_keep('', $current);
	pg_assert_same(array(), $none['rows'], 'a revision without rows lists none');
	pg_assert_same(0, $none['lost']);

	$gone = pg_coll_rows_revision_keep('x5', array());
	pg_assert_same(array(), $gone['rows']);
	pg_assert_same(1, $gone['lost']);
}

// The kept rows go to the editor as it posts them: code and the values of
// the fields the table takes.
function test_collections_rows_delete_revision_posted_list()
{
	$spec = array('ok' => true, 'page_id' => 40, 'fields' => array(array('id' => 71, 'type' => 'text box')));
	$rows = array(array('reference_code' => 'b2', 'values' => array(71 => 'Mix', 73 => 'photo.jpg')));
	pg_assert_same(array(array('code' => 'b2', 'values' => array('71' => 'Mix'))), pg_coll_rows_as_posted($spec, $rows));
	pg_assert_same(array(), pg_coll_rows_as_posted($spec, array()));

	$posted = pg_coll_rows_posted(json_encode(pg_coll_rows_as_posted($spec, $rows)));
	pg_assert_same('b2', $posted[0]['code'], 'the writer reads the list back');
}

// Loading a revision fills the rows editors, and the save without the form
// writes the rows field itself, from the list stored before.
function test_collections_rows_delete_history_load_and_restore()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/record_revisions.php');
	pg_assert_true(strpos($source, 'pg_coll_rows_revision_load($liveform, $form_id, $page_id, $values, $fill)') !== false, 'Load fills the rows editors');
	pg_assert_true(strpos($source, 'pg_coll_rows_revision_restore($form_id, $page_id, $values)') !== false, 'the restore writes the rows');
}
