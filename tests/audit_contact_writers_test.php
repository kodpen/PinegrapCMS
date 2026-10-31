<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the contact writers outside a single-row save: the same-address
 * opt-in sync of the contact screens, the CSV import and the İYS sync, and
 * the column read they share (pg_audit_fetch_by()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A read by column needs a traced table keyed by id, a plain column name and a value.
function test_audit_fetch_by_accepts_only_plain_reads()
{
	pg_assert_same(true, pg_audit_by_column_ok('contacts', 'email_address', 'a@example.com'), 'contacts by address');
	pg_assert_same(false, pg_audit_by_column_ok('contacts', 'email_address', ''), 'empty value: no read of every blank address');
	pg_assert_same(false, pg_audit_by_column_ok('contacts', 'email_address', '   '), 'blank value');
	pg_assert_same(false, pg_audit_by_column_ok('contacts', 'email`address', 'a@example.com'), 'quoted column name');
	pg_assert_same(false, pg_audit_by_column_ok('contacts', '', 'a@example.com'), 'no column');
	pg_assert_same(false, pg_audit_by_column_ok('config', 'id', '1'), 'config has no id key');
	pg_assert_same(false, pg_audit_by_column_ok('no_such_table', 'id', '1'), 'untraced table');
	pg_assert_same(array(), pg_audit_fetch_by('contacts', 'email_address', ''), 'refused read gives no rows');
}

// The rows a create line starts from: one empty row per new id, ids that are not numbers above 0 dropped.
function test_audit_created_rows()
{
	pg_assert_same(array(4 => array(), 9 => array()), pg_audit_created_rows(array(4, '9', 0, -2, 'x', 4)), 'ids');
	pg_assert_same(array(), pg_audit_created_rows(array()), 'none');
}

function audit_contact_writers_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

// The same-address opt-in sync reads the other contacts first and records them after.
function test_audit_same_address_opt_in_sync_is_recorded()
{
	// The contact screens sync through their shared form.
	$file = 'includes/forms/contact_form.php';
	$source = audit_contact_writers_test_source($file);
	$read = strpos($source, "pg_audit_fetch_by('contacts', 'email_address'");
	$sync = strpos($source, 'UPDATE contacts SET opt_in = ');
	$record = strpos($source, "pg_audit_record_many('update', 'contacts', \$audit_address)");

	pg_assert_true(($read !== false) && ($sync !== false) && ($record !== false), $file . ': read, sync and record');
	pg_assert_true(($read < $sync) && ($sync < $record), $file . ': read before the sync, record after it');

	foreach (array('edit_contact.php', 'add_contact.php') as $file) {
		pg_assert_contains('pg_contact_form_sync_opt_in(', audit_contact_writers_test_source($file), $file . ': syncs through the shared form');
	}
}

// Every contact the import creates gets a create line, after its opt-out.
function test_audit_import_records_created_contacts()
{
	$source = audit_contact_writers_test_source('import_contacts.php');

	pg_assert_contains("pg_audit_record_many('create', 'contacts', pg_audit_created_rows(", $source, 'batched create lines');
	pg_assert_true(strpos($source, 'UPDATE contacts SET opt_in = 0 WHERE id') < strpos($source, '$audit_created[] = $contact_id'), 'queued after the opt-out');
}

// The İYS writes are recorded, as the job's.
function test_audit_iys_writes_are_recorded_as_job()
{
	$source = audit_contact_writers_test_source('includes/iys/client.php');

	pg_assert_contains('$was = pg_audit_as_job(true);', $source, 'run as the job');
	pg_assert_contains('pg_audit_as_job($was);', $source, 'switch restored');
	pg_assert_same(2, substr_count($source, "pg_audit_fetch_by('contacts', 'email_address'"), 'push and pull read the address first');
	pg_assert_same(2, substr_count($source, "pg_audit_fetch_many('contacts'"), 'the SMS push and pull read the contacts first');
	pg_assert_same(4, substr_count($source, "pg_audit_record_many('update', 'contacts'"), 'push and pull record after, e-mail and SMS');
}

// Delete All Contacts reads each chunk before its deletes and records a delete line per contact after them.
function test_audit_view_contacts_mass_delete_is_recorded()
{
	$source = audit_contact_writers_test_source('view_contacts.php');
	$branch = strpos($source, "== 'Delete Contacts'");
	pg_assert_true($branch !== false, 'Delete Contacts branch');
	$source = substr($source, $branch);
	$chunk = strpos($source, 'array_chunk(');
	$read = strpos($source, "pg_audit_fetch_many('contacts', \$audit_ids)");
	$delete = strpos($source, 'DELETE FROM contacts WHERE id = ?');
	$record = strpos($source, "pg_audit_record_many('delete', 'contacts', \$audit_before)");

	pg_assert_true(($chunk !== false) && ($read !== false) && ($delete !== false) && ($record !== false), 'chunk, read, delete and record');
	pg_assert_true(($chunk < $read) && ($read < $delete) && ($delete < $record), 'read before the deletes, record after them');
}

// Approve Affiliate reads the contact before its update and records the update after it.
function test_audit_approve_affiliate_is_recorded()
{
	$source = audit_contact_writers_test_source('approve_affiliate.php');
	$read = strpos($source, "\$audit_before = pg_audit_fetch('contacts', \$contact_id);");
	$update = strpos($source, "affiliate_approved = '1'");
	$record = strpos($source, "pg_audit_record('update', 'contacts', \$contact_id, \$audit_before);");

	pg_assert_true(($read !== false) && ($update !== false) && ($record !== false), 'read, update and record');
	pg_assert_true(($read < $update) && ($update < $record), 'read before the update, record after it');
}

// The campaign's new recipient contact gets a create line, its id read before the line's own insert.
function test_audit_email_campaign_recipient_contact_is_recorded()
{
	$source = audit_contact_writers_test_source('add_email_campaign.php');
	$insert = strpos($source, 'INSERT INTO contacts');
	$id = strpos($source, '$new_contact_id = (int) mysqli_insert_id(db::$con);');
	$record = strpos($source, "pg_audit_record('create', 'contacts', \$new_contact_id);");

	pg_assert_true(($insert !== false) && ($id !== false) && ($record !== false), 'insert, id and record');
	pg_assert_true(($insert < $id) && ($id < $record), 'id read after the insert, record after it');
}
