<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Return in an Edit User field saves the user: the main form
 * opens with a hidden nameless submit button, so Create Contact, printed
 * further down inside the same form, is never the default button.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function edit_user_return_key_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_user.php');
}

function test_edit_user_return_key_form_starts_with_a_nameless_submit()
{
	$source = edit_user_return_key_test_source();
	$start = strpos($source, '<form name="form" action="edit_user.php"');
	pg_assert_true($start !== false, 'main form found');
	$end = strpos($source, '$output_contact_card .', $start);
	pg_assert_true($end !== false, 'contact card printed inside the main form');
	$slice = substr($source, $start, $end - $start);

	pg_assert_true((bool) preg_match('/<button\b[^>]*type="submit"[^>]*>/', $slice, $match), 'a submit button opens the form');
	$button = $match[0];
	pg_assert_true(strpos($button, 'name=') === false, 'the first submit button has no name');
	pg_assert_true(strpos($button, 'visually-hidden') !== false, 'the first submit button is visually hidden');
	pg_assert_true(strpos($button, 'tabindex="-1"') !== false, 'the first submit button is out of the tab order');
	pg_assert_true(strpos($button, 'aria-hidden="true"') !== false, 'the first submit button is hidden from screen readers');
}

function test_edit_user_return_key_save_branch_reads_no_button_name()
{
	$source = edit_user_return_key_test_source();
	pg_assert_true(strpos($source, "\$_POST['submit_save']") === false, 'the save branch does not need submit_save');
	pg_assert_true(strpos($source, 'name="submit_create_contact"') !== false, 'Create Contact stays');
}
