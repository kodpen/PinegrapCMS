<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that saving an existing submitted form leaves its signature fields
 * alone: the signature's form_data row points at the drawn file and is
 * written only when the form is created (includes/forms/submitted_form_form.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/submitted_form_form.php');

function test_submitted_form_edit_skips_signature_fields()
{
	pg_assert_same(false, pg_submitted_form_edit_writes_field(array('type' => 'signature')), 'a signature is never rewritten by an edit');
	pg_assert_same(false, pg_submitted_form_edit_writes_field(array('type' => 'information')), 'an information field holds no answer');
	pg_assert_same(true, pg_submitted_form_edit_writes_field(array('type' => 'text box')), 'a text box is saved');
	pg_assert_same(true, pg_submitted_form_edit_writes_field(array('type' => 'file upload')), 'a file upload is saved');
	pg_assert_same(true, pg_submitted_form_edit_writes_field(array('type' => 'check box')), 'a check box is saved');
}

function test_submitted_form_edit_screen_uses_the_signature_guard()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/edit_submitted_form.php');
	pg_assert_contains('pg_submitted_form_edit_writes_field($field)', $source, 'the save loop asks which fields an edit writes');
}
