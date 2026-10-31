<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the Contacts and Users lists keep the view (filter) they were
 * opened on: Export, Delete All Contacts and Merge submit it, the Advanced
 * Filters links and form carry it, and Create/Import pass the whole list
 * address in an encoded send_to.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function contact_user_list_view_kept_test_source($file)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

/**
 * The markup of one <form id="…"> up to its closing tag.
 */
function contact_user_list_view_kept_test_form($source, $id)
{
	$start = strpos($source, 'id="' . $id . '"');
	pg_assert_true($start !== false, $id . ' exists');
	$end = strpos($source, '</form>', $start);

	return substr($source, $start, $end - $start);
}

function test_contact_list_export_form_submits_the_view()
{
	$form = contact_user_list_view_kept_test_form(contact_user_list_view_kept_test_source('view_contacts.php'), 'export_form');

	// A GET form replaces the query string, so Export, Merge and Delete All
	// would run on the default view without this field.
	pg_assert_true(strpos($form, '<input type="hidden" name="filter" value="\' . h($filter) . \'">') !== false, 'Export, Merge and Delete All submit the view');
}

function test_contact_list_advanced_filters_keep_the_view()
{
	$source = contact_user_list_view_kept_test_source('view_contacts.php');
	$form = contact_user_list_view_kept_test_form($source, 'search_advanced');

	pg_assert_true(strpos($form, '<input type="hidden" name="filter" value="\' . h($filter) . \'">') !== false, 'Update keeps the view');
	pg_assert_same(2, substr_count($source, 'href="view_contacts.php?advanced_filters=\' . $output_advanced_filters_value . \'&amp;filter=\' . h(urlencode($filter)) . \'"'), 'the toggle and the close button keep the view');
	pg_assert_same(0, substr_count($source, 'href="view_contacts.php?advanced_filters=\' . $output_advanced_filters_value . \'"'), 'no Advanced Filters link drops the view');
}

function test_contact_list_delete_all_returns_to_the_view()
{
	$source = contact_user_list_view_kept_test_source('view_contacts.php');

	pg_assert_true(strpos($source, "SOFTWARE_DIRECTORY . '/view_contacts.php');") === false, 'Delete All Contacts returns to the view it deleted from');
}

function test_contact_and_user_lists_encode_send_to()
{
	$checks = array(
		'view_contacts.php' => array('add_contact.php', 'import_contacts.php'),
		'view_users.php'    => array('add_user.php', 'import_users.php'),
	);

	foreach ($checks as $file => $targets) {
		$source = contact_user_list_view_kept_test_source($file);

		foreach ($targets as $target) {
			// ?filter=a&filter=b or ?sort=…&filter=… is cut at the first & otherwise.
			pg_assert_true(strpos($source, 'href="' . $target . '?send_to=\' . h(urlencode(REQUEST_URL)) . \'"') !== false, $file . ' passes the whole address to ' . $target);
			pg_assert_true(strpos($source, 'href="' . $target . '?send_to=\' . h(REQUEST_URL) . \'"') === false, $file . ' sends ' . $target . ' an unencoded address');
		}
	}
}
