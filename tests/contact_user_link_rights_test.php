<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins: the submitted forms list, the cart quote list, the privacy
 * export and the Workspace user tag link a contact or user name only when
 * pg_can_open_contact() / pg_can_open_user() say that edit screen admits the
 * viewer, and print the plain name otherwise.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * Every line of $file that links $screen has $helper within the $before
 * lines above it (or on the line itself); returns how many links were found.
 */
function _contact_user_link_rights_pin($file, $screen, $helper, $before)
{
	$lines = file(PG_FUNCTIONS_DIR . '/' . $file);
	$found = 0;

	foreach ($lines as $i => $line) {
		if (strpos($line, $screen . '?id=') === false) {
			continue;
		}

		$found++;
		$context = implode('', array_slice($lines, max(0, $i - $before), $before + 1));
		pg_assert_true(strpos($context, $helper) !== false, $file . ':' . ($i + 1) . ' links ' . $screen . ' without ' . $helper . ')');
	}

	return $found;
}

function test_contact_user_link_rights_submitted_form_list_asks_per_row()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/submitted_form_list.php');

	pg_assert_true(strpos($source, 'pg_submitted_form_list_row($row, $contacts_access && pg_can_open_contact($user, $row[\'contact_id\'], $contacts_access))') !== false, 'each row asks whether its contact opens for the viewer');
	pg_assert_true(_contact_user_link_rights_pin('includes/fn/submitted_form_list.php', 'edit_contact.php', '$contacts_access', 1) === 1);
}

function test_contact_user_link_rights_cart_quotes_ask_the_contact_screen()
{
	pg_assert_true(_contact_user_link_rights_pin('view_cart_quotes.php', 'edit_contact.php', 'pg_can_open_contact(', 2) === 1);
	pg_assert_true(strpos(file_get_contents(PG_FUNCTIONS_DIR . '/view_cart_quotes.php'), '$contacts_access = (validate_contacts_access($user, true) == true);') !== false, 'contacts access is computed once for the list');
}

function test_contact_user_link_rights_privacy_export_asks_the_user_screen()
{
	pg_assert_true(_contact_user_link_rights_pin('privacy_export.php', 'edit_user.php', 'pg_can_open_user(', 2) === 1);
}

function test_contact_user_link_rights_workspace_user_tag_asks_the_user_screen()
{
	pg_assert_true(_contact_user_link_rights_pin('includes/workspace/refs.php', 'edit_user.php', 'pg_can_open_user(', 3) === 1);
}
