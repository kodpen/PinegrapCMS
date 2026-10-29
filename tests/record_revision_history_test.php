<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the History section of a collection item
 * (edit_submitted_form.php, includes/fn/record_revisions.php): a stored
 * revision read back, the field-by-field comparison with the current
 * values, what a restore brings back per field (a file whose record is gone
 * is not restored), the values handed to the edit form and the text shown
 * for a value.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _record_revision_history_fields()
{
	return array(
		array('id' => '3', 'label' => 'Title', 'type' => 'text box'),
		array('id' => '4', 'label' => 'Tags', 'type' => 'check box'),
		array('id' => '5', 'label' => 'Note', 'type' => 'information'),
		array('id' => '7', 'label' => 'Image', 'type' => 'file upload'),
		array('id' => '8', 'label' => 'Signed', 'type' => 'signature'),
		array('id' => '9', 'label' => 'Body', 'type' => 'text area'),
	);
}

// A stored row reads back as the values it was written from; anything else
// reads as no values.
function test_record_revision_history_decode()
{
	$values = array('3' => array('Title'), '7' => array(array('file_id' => 55)), '12' => array('b', 'a'));

	pg_assert_same($values, pg_record_revision_decode(pg_record_revision_json($values)));
	pg_assert_same(array(), pg_record_revision_decode('{}'), 'empty');
	pg_assert_same(array(), pg_record_revision_decode('not json'), 'broken');
	pg_assert_same(array(), pg_record_revision_decode('"text"'), 'not an object');
	pg_assert_same(array('3' => array('x')), pg_record_revision_decode('{"3":"x","0":["y"],"a":["z"]}'), 'a lone value becomes a list, bad ids go');
}

// One row per field in form order, information fields left out, changed
// fields marked.
function test_record_revision_history_compare()
{
	$revision = array('3' => array('Old title'), '4' => array('a', 'b'), '7' => array(array('file_id' => 55)));
	$current  = array('3' => array('New title'), '4' => array('a', 'b'), '9' => array('<p>Text</p>'), '7' => array(array('file_id' => 55)));

	$rows = pg_record_revision_compare(_record_revision_history_fields(), $revision, $current);

	pg_assert_same(array('3', '4', '7', '8', '9'), array_map(function ($row) { return $row['field_id']; }, $rows));
	pg_assert_same(array(true, false, false, false, true), array_map(function ($row) { return $row['changed']; }, $rows));
	pg_assert_same(array('Old title'), $rows[0]['revision']);
	pg_assert_same(array('New title'), $rows[0]['current']);
	pg_assert_same(array(), $rows[4]['revision'], 'no value in the revision');
	pg_assert_same('Body', $rows[4]['label']);
}

// What a restore does with each field: values come back, a file comes back
// only when its record still exists, a signature is never redrawn.
function test_record_revision_history_restore_plan()
{
	$revision = array('3' => array('Old'), '7' => array(array('file_id' => 55)), '8' => array(array('file_id' => 60)));
	$current  = array('3' => array('New'), '7' => array(array('file_id' => 70)), '8' => array(array('file_id' => 61)));
	$fields   = _record_revision_history_fields();

	pg_assert_same(
		array('3' => 'value', '4' => 'value', '7' => 'file', '8' => 'kept', '9' => 'value'),
		pg_record_revision_restore_plan($fields, $revision, $current, array(55, 70)));

	pg_assert_same('gone', pg_record_revision_restore_plan($fields, $revision, $current, array(70))['7'], 'file deleted since');

	$same = array('7' => array(array('file_id' => 55)), '8' => array(array('file_id' => 60)));
	pg_assert_same('unchanged', pg_record_revision_restore_plan($fields, $same, $same, array(55))['7'], 'same file');
	pg_assert_same('unchanged', pg_record_revision_restore_plan($fields, $same, $same, array())['8'], 'same signature');

	pg_assert_same('kept', pg_record_revision_restore_plan($fields, array(), $current, array(70))['7'], 'no file in the revision: the current one stays');
	pg_assert_same('unchanged', pg_record_revision_restore_plan($fields, array(), array(), array())['7'], 'no file on either side');
}

// The edit form takes one value as text and several as a list; a field the
// revision has no value for is emptied.
function test_record_revision_history_form_value()
{
	pg_assert_same('', pg_record_revision_form_value(array()));
	pg_assert_same('x', pg_record_revision_form_value(array('x')));
	pg_assert_same(array('a', 'b'), pg_record_revision_form_value(array('a', 'b')));
	pg_assert_same('', pg_record_revision_form_value(array(array('file_id' => 5))), 'a file is no form value');
}

// The text shown for a value: tags dropped, several values joined, a file
// by its name, long text cut.
function test_record_revision_history_value_text()
{
	pg_assert_same('', pg_record_revision_value_text(array(), array()));
	pg_assert_same('Hello world', pg_record_revision_value_text(array("<p>Hello</p>\n<p>world</p>"), array()));
	pg_assert_same('a, b', pg_record_revision_value_text(array('a', 'b'), array()));
	pg_assert_same('photo.jpg', pg_record_revision_value_text(array(array('file_id' => 55)), array(55 => 'photo.jpg')));
	pg_assert_same('#56', pg_record_revision_value_text(array(array('file_id' => 56)), array(55 => 'photo.jpg')), 'file gone');

	$text = pg_record_revision_value_text(array(str_repeat('ç', 400)), array());
	pg_assert_same(301, mb_strlen($text), 'cut at 300 characters and an ellipsis');
}
