<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the dashboard Activity card (includes/dashboard/widgets/widget_7.php):
 * each kind's "N today" count and bars come from the same condition as the
 * rows it lists, so a contributor's Forms and Contacts tiles count only what
 * their lists show, and comments on pages in the Recycle Bin are left out of
 * both the Comments count and its list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function dashboard_activity_counts_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/dashboard/widgets/widget_7.php');
}

// The body of one function in the widget, from its signature to the next one.
function dashboard_activity_counts_test_function($source, $name)
{
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . '() exists');
	$end = strpos($source, "\nfunction ", $start + 1);

	return ($end === false) ? substr($source, $start) : substr($source, $start, $end - $start);
}

// Forms: the series and the list take the one condition worked out for the
// reader; with no form page granted, the tile is a zero series.
function test_dashboard_activity_counts_forms_share_condition()
{
	$source = dashboard_activity_counts_test_source();
	$kinds = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_kinds');

	pg_assert_contains('$form_where = pg_dashboard_activity_forms_where($user);', $kinds);
	pg_assert_contains("pg_dashboard_activity_forms(\$form_where, \$limit),", $kinds);
	pg_assert_contains("pg_activity_daily('forms', 'forms.submitted_timestamp', ' AND ' . \$form_where)", $kinds);
	pg_assert_contains('array_fill(0, 8, 0)', $kinds);
	pg_assert_true(strpos($kinds, "pg_activity_daily('forms', 'forms.submitted_timestamp', \" AND (forms.complete = '1')\")") === false, 'not: ' . "pg_activity_daily('forms', 'forms.submitted_timestamp', \" AND (forms.complete = '1')\")");

	$where = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_forms_where');
	pg_assert_contains("(forms.complete = '1')", $where);
	pg_assert_contains('forms.page_id IN (', $where);
	pg_assert_contains('return null;', $where);

	$list = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_forms');
	pg_assert_contains('WHERE " . $where . "', $list);
	pg_assert_true(strpos($list, 'get_folders_that_user_has_access_to') === false, 'not: ' . 'get_folders_that_user_has_access_to');
}

// Contacts: the group scope is one IN (subquery) condition used by both.
function test_dashboard_activity_counts_contacts_share_condition()
{
	$source = dashboard_activity_counts_test_source();
	$kinds = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_kinds');

	pg_assert_contains('$contact_where = pg_dashboard_activity_contacts_where($user);', $kinds);
	pg_assert_contains('pg_dashboard_activity_contacts($contact_where, $limit),', $kinds);
	pg_assert_contains("pg_activity_daily('contacts', 'contacts.timestamp', \$contact_where)", $kinds);

	$where = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_contacts_where');
	pg_assert_contains('contacts.id IN (SELECT contact_id FROM contacts_contact_groups_xref WHERE contact_group_id IN (', $where);
	pg_assert_contains("get_items_user_can_edit('contact_groups', \$user['id'])", $where);
	pg_assert_contains('return null;', $where);

	$list = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_contacts');
	pg_assert_contains('WHERE 1 = 1" . $where . "', $list);
	pg_assert_true(strpos($list, 'get_items_user_can_edit') === false, 'not: ' . 'get_items_user_can_edit');
}

// Comments: the series joins page and both leave out binned pages.
function test_dashboard_activity_counts_comments_leave_out_recycle_bin()
{
	$source = dashboard_activity_counts_test_source();
	$kinds = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_kinds');

	pg_assert_contains('$comment_where = pg_comment_live_page_sql(pg_recycle_bin_folder_ids());', $kinds);
	pg_assert_contains('pg_dashboard_activity_comments($comment_where, $limit),', $kinds);
	pg_assert_contains("pg_activity_daily('comments LEFT JOIN page ON comments.page_id = page.page_id', 'comments.created_timestamp', \$comment_where)", $kinds);

	$list = dashboard_activity_counts_test_function($source, 'pg_dashboard_activity_comments');
	pg_assert_contains('LEFT JOIN page ON comments.page_id = page.page_id', $list);
	pg_assert_contains('WHERE 1 = 1" . $where . "', $list);
}
