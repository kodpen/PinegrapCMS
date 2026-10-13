<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for telling a form filled in for somebody else from one about the
 * signed-in person (pg_cf_email_is_someone_else(), includes/fn/custom_form.php):
 * custom_form.php leaves the signed-in person's contact alone when the
 * form's contact e-mail is not theirs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Staff booking for a patient: another address than any of theirs.
function test_custom_form_contact_other_address_is_someone_else()
{
	pg_assert_true(pg_cf_email_is_someone_else('patient@example.com', array('staff@example.com', 'staff.contact@example.com')));
}

// The person's own address, whichever of theirs it is, in any case and with
// spaces around it.
function test_custom_form_contact_own_address_is_not_someone_else()
{
	pg_assert_false(pg_cf_email_is_someone_else('staff@example.com', array('staff@example.com', '')), 'account address');
	pg_assert_false(pg_cf_email_is_someone_else(' Staff.Contact@Example.com ', array('staff@example.com', 'staff.contact@example.com')), 'contact address, case and spaces');
}

// Nothing to compare: no contact e-mail field, or no address on file.
function test_custom_form_contact_nothing_to_compare_is_not_someone_else()
{
	pg_assert_false(pg_cf_email_is_someone_else('', array('staff@example.com')), 'no e-mail field');
	pg_assert_false(pg_cf_email_is_someone_else('patient@example.com', array('', ' ')), 'no address on file');
	pg_assert_false(pg_cf_email_is_someone_else('patient@example.com', array()), 'no addresses given');
}

// Not signed in (the runner has no session): the anonymous path is unchanged.
function test_custom_form_contact_anonymous_is_not_someone_else()
{
	pg_assert_false(pg_cf_contact_is_someone_else('patient@example.com', '', 'first_name = \'A\', '));
}
