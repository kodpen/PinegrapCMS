<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the notice of an affiliate approval: approve_affiliate.php queues
 * it under the form Edit Contact reads, and Edit Contact prints it once and
 * removes the form, so it is shown after the approval and never waits in the
 * session for a site page's messages region.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_approve_affiliate_queues_the_notice_for_edit_contact()
{
	$approve = (string) file_get_contents(PG_FUNCTIONS_DIR . '/approve_affiliate.php');

	pg_assert_contains("new liveform('edit_contact')", $approve, 'the notice is queued for the screen the approval leads to');
	pg_assert_false(strpos($approve, "new liveform('view_contact')") !== false, 'no form nobody reads');
}

function test_edit_contact_prints_and_removes_its_notices()
{
	$edit = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_contact.php');

	pg_assert_contains("\$liveform_edit_contact = new liveform('edit_contact');", $edit, 'Edit Contact reads its own form');
	pg_assert_contains("\$liveform_edit_contact->output_notices()", $edit, 'its notices are printed');
	pg_assert_contains("\$liveform_edit_contact->remove_form();", $edit, 'and removed once the screen is drawn');
}
