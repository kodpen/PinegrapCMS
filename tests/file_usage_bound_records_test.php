<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the records the file manager's "Where used" reads by file id
 * (submitted form attachments, comment attachments, contact pictures) and
 * for the attachment state Edit Submitted Form draws from the file row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

// Rows the viewer may not open are left out before the count, so neither
// the rows nor their number reach a person without the right; the cap
// applies to what is left.
function test_file_usage_bound_group_keeps_only_openable_rows()
{
	$rows = array(
		array('id' => 1, 'ok' => true),
		array('id' => 2, 'ok' => false),
		array('id' => 3, 'ok' => true),
		array('id' => 4, 'ok' => true));
	$item = function ($row) {
		return array('name' => 'R' . $row['id'], 'note' => '', 'url' => 'x.php?id=' . $row['id']);
	};
	$open = function ($row) {
		return $row['ok'];
	};

	$out = pg_explorer_file_usage_bound_group('Label', $rows, $item, $open, 40);
	pg_assert_same('Label', $out['group']['label'], 'label');
	pg_assert_same(array('R1', 'R3', 'R4'), array_column($out['group']['items'], 'name'), 'the closed row is left out');
	pg_assert_false($out['truncated'], 'not truncated');

	$out = pg_explorer_file_usage_bound_group('Label', $rows, $item, $open, 2);
	pg_assert_same(array('R1', 'R3'), array_column($out['group']['items'], 'name'), 'capped after the access check');
	pg_assert_true($out['truncated'], 'truncated');

	$none = function ($row) {
		return false;
	};
	$out = pg_explorer_file_usage_bound_group('Label', $rows, $item, $none, 40);
	pg_assert_same(null, $out['group'], 'no group when nothing may be opened');
	pg_assert_false($out['truncated'], 'and no truncation that would leak a count');

	$out = pg_explorer_file_usage_bound_group('Label', false, $item, $open, 40);
	pg_assert_same(null, $out['group'], 'a failed query gives no group');
}

// The items: a submitted form by its form name and reference code, a
// comment by its author and page, a contact by name or e-mail address.
function test_file_usage_bound_items()
{
	pg_assert_same(
		array('name' => 'Contact Us', 'note' => 'ABC123', 'url' => 'edit_submitted_form.php?id=1005'),
		pg_explorer_file_usage_form_item(array('id' => '1005', 'form_name' => 'Contact Us', 'reference_code' => 'ABC123')),
		'submitted form');

	pg_assert_same('#7', pg_explorer_file_usage_form_item(array('id' => 7, 'form_name' => null, 'reference_code' => ''))['name'], 'a form without a name');

	pg_assert_same(
		array('name' => 'Ayşe', 'note' => 'blog', 'url' => 'edit_comment.php?id=12'),
		pg_explorer_file_usage_comment_item(array('id' => 12, 'name' => 'Ayşe', 'page_id' => 4, 'page_name' => 'pgbin_4_blog')),
		'comment, the page label without its bin prefix');

	pg_assert_same(
		array('name' => 'Ada Lovelace', 'note' => 'ada@example.com', 'url' => 'edit_contact.php?id=9'),
		pg_explorer_file_usage_contact_item(array('id' => 9, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email_address' => 'ada@example.com')),
		'contact');

	pg_assert_same('ada@example.com', pg_explorer_file_usage_contact_item(array('id' => 9, 'first_name' => '', 'last_name' => null, 'email_address' => 'ada@example.com'))['name'], 'a contact without a name');
}

// The submitted form screen opens for a Manager and above, or for a user
// with the forms right who edits the form page's folder.
function test_file_usage_form_open()
{
	pg_assert_true(pg_explorer_file_usage_form_open(0, false, false), 'administrator');
	pg_assert_true(pg_explorer_file_usage_form_open(2, false, false), 'manager');
	pg_assert_false(pg_explorer_file_usage_form_open(3, false, true), 'user without the forms right');
	pg_assert_false(pg_explorer_file_usage_form_open(3, true, false), 'user without the folder');
	pg_assert_true(pg_explorer_file_usage_form_open(3, true, true), 'user with both');
}

// The handler reads the three id-bound sources and asks each screen's
// access check.
function test_file_usage_reads_bound_sources()
{
	$handler = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_file_usage.php');

	pg_assert_contains('form_data.file_id = ?', $handler, 'form attachments by id');
	pg_assert_contains('comments.file_id = ?', $handler, 'comment attachments by id');
	pg_assert_contains('contacts.file_id = ?', $handler, 'contact pictures by id');
	pg_assert_contains('check_edit_access(', $handler, 'folder check');
	pg_assert_contains('validate_contact_access($user, ', $handler, 'contact check');
	pg_assert_contains('validate_contacts_access($user, true)', $handler, 'contacts area check without an error page');
}

// The attachment state Edit Submitted Form draws: none, present, in the
// bin (parked name or a folder in the bin) or deleted (the files row gone).
function test_submitted_form_attachment_state()
{
	pg_assert_same(array('state' => 'none', 'name' => ''), pg_submitted_form_attachment_state(null, array()), 'no row');
	pg_assert_same(array('state' => 'none', 'name' => ''), pg_submitted_form_attachment_state(array('file_id' => '0', 'found_id' => null, 'name' => null, 'folder' => null), array()), 'no file');
	pg_assert_same(array('state' => 'deleted', 'name' => ''), pg_submitted_form_attachment_state(array('file_id' => '7124', 'found_id' => null, 'name' => null, 'folder' => null), array()), 'files row gone');
	pg_assert_same(array('state' => 'ok', 'name' => 'a.txt'), pg_submitted_form_attachment_state(array('file_id' => '5', 'found_id' => '5', 'name' => 'a.txt', 'folder' => '3'), array(9)), 'present');
	pg_assert_same(array('state' => 'binned', 'name' => 'pg-test-ek-3.txt'), pg_submitted_form_attachment_state(array('file_id' => '7130', 'found_id' => '7130', 'name' => 'pgbin_7130_pg-test-ek-3.txt', 'folder' => '3'), array(9)), 'parked name');
	pg_assert_same(array('state' => 'binned', 'name' => 'a.txt'), pg_submitted_form_attachment_state(array('file_id' => '5', 'found_id' => '5', 'name' => 'a.txt', 'folder' => '9'), array(9)), 'inside a binned folder');
	pg_assert_same(array('state' => 'ok', 'name' => 'pgbin_8_x.txt'), pg_submitted_form_attachment_state(array('file_id' => '5', 'found_id' => '5', 'name' => 'pgbin_8_x.txt', 'folder' => '3'), array()), 'another id\'s prefix is a plain name');
}
