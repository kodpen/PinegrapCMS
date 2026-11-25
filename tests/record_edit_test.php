<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a collection item's fields corrected in place from the Page
 * Design Studio's Preview (includes/fn/record_edit.php): the control each
 * field is edited with, who may write, when a change waits for approval,
 * the sent changes, the changed-meanwhile plan, the stored value read, the
 * writer's order of writes and the panel actions.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A text, a formatted text, a number, a date and a picture are edited in place; the rest stays on the item's screen.
function test_record_edit_control()
{
	pg_assert_same('text', pg_rec_edit_control(array('type' => 'text box')));
	pg_assert_same('text', pg_rec_edit_control(array('type' => 'email address')));
	pg_assert_same('text', pg_rec_edit_control(array('type' => 'text area', 'wysiwyg' => '0')));
	pg_assert_same('rich', pg_rec_edit_control(array('type' => 'text area', 'wysiwyg' => '1')));
	pg_assert_same('number', pg_rec_edit_control(array('type' => 'text box'), 'number'));
	pg_assert_same('text', pg_rec_edit_control(array('type' => 'text box'), 'slug'), 'a slug is a line of text');
	pg_assert_same('date', pg_rec_edit_control(array('type' => 'date')));
	pg_assert_same('picture', pg_rec_edit_control(array('type' => 'file upload'), 'image'));

	pg_assert_same('', pg_rec_edit_control(array('type' => 'file upload')), 'a file that is not a picture');
	pg_assert_same('', pg_rec_edit_control(array('type' => 'text box'), 'reference'));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'text box'), 'rows'));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'pick list'), 'product'));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'pick list')));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'check box')));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'radio button')));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'date and time')));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'time')));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'signature')));
	pg_assert_same('', pg_rec_edit_control(array('type' => 'information')));
	pg_assert_same('', pg_rec_edit_control(array()));
}

// Whoever the item's edit screen lets save it as the forms' manager.
function test_record_edit_may()
{
	pg_assert_true(pg_rec_edit_may(array('role' => '0'), false), 'administrator');
	pg_assert_true(pg_rec_edit_may(array('role' => '1'), false), 'designer');
	pg_assert_true(pg_rec_edit_may(array('role' => '2'), false), 'manager');
	pg_assert_true(pg_rec_edit_may(array('role' => '3', 'manage_forms' => true), true), 'user with Manage Forms and the folder');
	pg_assert_false(pg_rec_edit_may(array('role' => '3', 'manage_forms' => true), false), 'Manage Forms without the folder');
	pg_assert_false(pg_rec_edit_may(array('role' => '3', 'manage_forms' => false), true), 'the folder without Manage Forms');
	pg_assert_false(pg_rec_edit_may(null, true), 'nobody');
	pg_assert_false(pg_rec_edit_may(array(), true), 'no role');
}

// A content level's change waits while the approval switch is on; it is then not written in place.
function test_record_edit_waits()
{
	pg_assert_false(pg_rec_edit_waits(array('role' => '2'), false), 'switch off');
	pg_assert_true(pg_rec_edit_waits(array('role' => '2'), true), 'manager');
	pg_assert_true(pg_rec_edit_waits(array('role' => '3'), true), 'user');
	pg_assert_false(pg_rec_edit_waits(array('role' => '1'), true), 'designer');
	pg_assert_false(pg_rec_edit_waits(array('role' => '0'), true), 'administrator');
}

// The sent changes: field id => value and base as strings, or why they are refused.
function test_record_edit_changes()
{
	pg_assert_same(array(
		12 => array('value' => 'Blue Mountain', 'base' => 'Blue Mountian'),
		7  => array('value' => '4.5', 'base' => ''),
	), pg_rec_edit_changes(array(
		array('field_id' => '12', 'value' => 'Blue Mountain', 'base' => 'Blue Mountian'),
		array('field_id' => 7, 'value' => 4.5, 'base' => null),
	)));

	pg_assert_true(is_string(pg_rec_edit_changes(array())), 'nothing sent');
	pg_assert_true(is_string(pg_rec_edit_changes('x')), 'not a list');
	pg_assert_true(is_string(pg_rec_edit_changes(array(array('field_id' => 0, 'value' => 'a', 'base' => '')))), 'no field');
	pg_assert_true(is_string(pg_rec_edit_changes(array(array('field_id' => 3, 'value' => 'a')))), 'no base');
	pg_assert_true(is_string(pg_rec_edit_changes(array(array('field_id' => 3, 'value' => array('a'), 'base' => '')))), 'a list value');
	pg_assert_true(is_string(pg_rec_edit_changes(array(array('field_id' => array(3), 'value' => 'a', 'base' => '')))), 'a list id');
	pg_assert_true(is_string(pg_rec_edit_changes(array_fill(0, PG_REC_EDIT_MAX_FIELDS + 1, array('field_id' => 3, 'value' => 'a', 'base' => '')))), 'too many');
}

// Written when the stored value is still the base; left when it is already the new one; a conflict otherwise.
function test_record_edit_plan()
{
	$changes = array(
		1 => array('value' => 'new', 'base' => 'old'),
		2 => array('value' => 'same', 'base' => 'old'),
		3 => array('value' => 'mine', 'base' => 'old'),
		4 => array('value' => 'filled', 'base' => ''),
	);

	$plan = pg_rec_edit_plan($changes, array(1 => 'old', 2 => 'same', 3 => 'theirs'));

	pg_assert_same(array(1 => 'new', 4 => 'filled'), $plan['write'], 'a field with no stored value reads as empty');
	pg_assert_same(array(2), $plan['same']);
	pg_assert_same(array(3 => array('mine' => 'mine', 'theirs' => 'theirs')), $plan['conflicts']);

	// Choosing their value or mine after a conflict sends the stored value as the base.
	$again = pg_rec_edit_plan(array(3 => array('value' => 'mine', 'base' => 'theirs')), array(3 => 'theirs'));
	pg_assert_same(array(3 => 'mine'), $again['write']);
	pg_assert_same(array(), $again['conflicts']);

	// Compared byte for byte: a changed case is a change.
	$case = pg_rec_edit_plan(array(5 => array('value' => 'Coffee', 'base' => 'coffee')), array(5 => 'COFFEE'));
	pg_assert_same(array(5), array_keys($case['conflicts']));
}

// A picture is its file id, anything else the data of its first row.
function test_record_edit_stored()
{
	pg_assert_same('', pg_rec_edit_stored('text', array()));
	pg_assert_same('Blue', pg_rec_edit_stored('text', array(array('data' => 'Blue', 'file_id' => '0'), array('data' => 'Green', 'file_id' => '0'))));
	pg_assert_same('42', pg_rec_edit_stored('picture', array(array('data' => '', 'file_id' => '42'))));
	pg_assert_same('', pg_rec_edit_stored('picture', array(array('data' => '', 'file_id' => '0'))), 'a blank picture row');
	pg_assert_same('', pg_rec_edit_stored('picture', array(array('data' => '', 'file_id' => null))));
}

// The foot bar names the item by its Title, else by its code.
function test_record_edit_title()
{
	$record = array('reference_code' => 'ABC123');

	pg_assert_same('Blue Mountain Coffee', pg_rec_edit_title($record, array(
		array('rss_field' => '', 'value' => 'Body'),
		array('rss_field' => 'title', 'value' => ' Blue Mountain <b>Coffee</b> '),
	)));
	pg_assert_same('ABC123', pg_rec_edit_title($record, array(array('rss_field' => 'title', 'value' => ' '))));
	pg_assert_same('ABC123', pg_rec_edit_title($record, array()));
}

// Each refusal has its own status code.
function test_record_edit_http_status()
{
	if (!defined('PG_PANEL_ACTIONS')) {
		define('PG_PANEL_ACTIONS', true);
	}

	require_once(PG_FUNCTIONS_DIR . '/includes/panel/record_edit.php');

	pg_assert_same(200, pg_panel_record_edit_http('saved'));
	pg_assert_same(200, pg_panel_record_edit_http('unchanged'));
	pg_assert_same(200, pg_panel_record_edit_http('ok'));
	pg_assert_same(404, pg_panel_record_edit_http('missing'));
	pg_assert_same(403, pg_panel_record_edit_http('denied'));
	pg_assert_same(409, pg_panel_record_edit_http('conflict'));
	pg_assert_same(409, pg_panel_record_edit_http('approval'));
	pg_assert_same(422, pg_panel_record_edit_http('invalid'));
	pg_assert_same(422, pg_panel_record_edit_http('refused'));
}

// The save checks rights, approval and conflicts before it writes, and writes in the edit screen's order.
function test_record_edit_save_order()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/record_edit.php');

	$save = substr($source, strpos($source, 'function pg_rec_edit_save('));
	$save = substr($save, 0, strpos($save, "\n}\n"));
	$order = array('pg_rec_edit_rights(', "['waits']", 'pg_rec_edit_changes(', 'pg_rec_edit_plan(', "['conflicts']", 'pg_rec_edit_check(', 'pg_rec_edit_write(');
	$last = -1;

	foreach ($order as $call) {
		$at = strpos($save, $call);
		pg_assert_true(($at !== false) && ($at > $last), 'the save calls ' . $call . ' in its place');
		$last = (int) $at;
	}

	$write = substr($source, strpos($source, 'function pg_rec_edit_write('));
	$order = array('pg_record_revision_before(', 'pg_submitted_form_save_value(', 'last_modified_user_id', 'update_submitted_form_address_name(', "pg_record_revision_after(\$id, \$revision_before, 'designer'", 'log_activity(');
	$last = -1;

	foreach ($order as $call) {
		$at = strpos($write, $call);
		pg_assert_true(($at !== false) && ($at > $last), 'the writer calls ' . $call . ' in its place');
		$last = (int) $at;
	}

	pg_assert_false(strpos($source, 'page_tree') !== false || strpos($source, 'pg_designer_save_page') !== false, 'a record save never writes a page');
}

// Both actions reach every role past the session check; the handler admits by the item's rule and checks the token.
function test_record_edit_panel_actions()
{
	$actions = pg_panel_actions();

	foreach (array('record_edit_read' => false, 'record_edit_save' => true) as $name => $write) {
		pg_assert_true(isset($actions[$name]), $name . ' is listed');
		pg_assert_same('record_edit.php', $actions[$name]['file']);
		pg_assert_true(!empty($actions[$name]['any_role']) && empty($actions[$name]['exempt']), $name . ' asks for the session only');
		pg_assert_true($actions[$name]['token'], $name . ' checks the token');
		pg_assert_same($write, $actions[$name]['write']);
	}
}

// The read names each field's RSS role, so the Preview finds the bound post title and picture (PGRecordEditSpots).
function test_record_edit_fields_out()
{
	$out = pg_rec_edit_fields_out(array(
		5 => array('id' => '5', 'name' => 'cover', 'label' => 'Cover', 'control' => 'picture', 'required' => '1', 'rss_field' => 'media', 'value' => '12', 'file_name' => 'a.jpg'),
		6 => array('id' => '6', 'name' => 'note', 'label' => 'Note', 'control' => 'text', 'required' => '0', 'rss_field' => null, 'value' => 'x', 'file_name' => ''),
	));

	pg_assert_same(array(
		array('id' => 5, 'name' => 'cover', 'label' => 'Cover', 'control' => 'picture', 'required' => true, 'rss' => 'media', 'value' => '12', 'file_name' => 'a.jpg'),
		array('id' => 6, 'name' => 'note', 'label' => 'Note', 'control' => 'text', 'required' => false, 'rss' => '', 'value' => 'x', 'file_name' => ''),
	), $out);
}
