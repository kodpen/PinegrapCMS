<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page and offer pickers keeping a stored choice visible:
 * select_page() lets the selected page past the archived-folder rule and
 * marks it, the calendar event Next Page keeps the stored page, and
 * select_offer() marks offers that are not enabled.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function page_pickers_keep_test_source($relative)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $relative);
}

function page_pickers_keep_test_function($relative, $name)
{
	$source = page_pickers_keep_test_source($relative);
	$start = strpos($source, 'function ' . $name . '(');
	pg_assert_true($start !== false, $name . '() exists');
	$end = strpos($source, "\nfunction ", $start + 1);

	return substr($source, $start, ($end === false) ? null : $end - $start);
}

function test_select_page_lets_the_selected_page_past_the_archived_rule()
{
	$body = page_pickers_keep_test_function('includes/fn/forms.php', 'select_page');

	pg_assert_contains('(folder.folder_archived = "0") OR (page.page_id = ?)', $body, 'the selected page is listed whatever its folder');
	pg_assert_contains('folder.folder_archived', $body, 'the folder state is read for the mark');
	pg_assert_contains('pg_page_option_status(', $body, 'the selected page is marked as get_page_options() marks it');
	pg_assert_contains('pg_recycle_bin_folder_ids()', $body, 'a page in the bin is told apart from an archived one');
	pg_assert_contains("lang('IN RECYCLE BIN')", $body, 'bin mark');
	pg_assert_contains("lang('ARCHIVED')", $body, 'archived mark');
	pg_assert_contains('pg_recycle_parked_original(', $body, 'a page in the bin shows its name from before the bin');
	pg_assert_contains('check_folder_access_in_array($page[\'folder_id\'], $folders_that_user_has_access_to)', $body, 'the role-3 folder filter still applies to the selected page');
}

function test_calendar_event_next_page_keeps_the_stored_page()
{
	$form = page_pickers_keep_test_source('includes/forms/calendar_event_form.php');
	$edit = page_pickers_keep_test_source('edit_calendar_event.php');

	pg_assert_contains("get_page_options('', '', 'edit', true, \$keep_next_page_id)", $form, 'the Next Page picker keeps the given page');
	pg_assert_contains("\$stored_next_page_id = (int) db_value(\"SELECT next_page_id FROM calendar_events WHERE id = '\" . \$id . \"'\");", $edit, 'the keep id is the stored next page, not the post');
	pg_assert_contains('pg_calendar_event_form_cards($liveform, $user, $calendars, $calendar_event_locations, $id, $stored_next_page_id)', $edit, 'edit passes the stored page');
	pg_assert_contains("pg_calendar_event_form_cards(\$liveform, \$user, \$choices['calendars'], \$choices['calendar_event_locations'])", page_pickers_keep_test_source('add_calendar_event.php'), 'add keeps nothing');
}

function test_select_offer_marks_offers_that_are_not_enabled()
{
	$body = page_pickers_keep_test_function('includes/fn/forms.php', 'select_offer');

	pg_assert_contains('status', $body, 'the offer status is read');
	pg_assert_contains("if (\$offer['status'] !== 'enabled') {", $body, 'an offer that is not enabled is told apart');
	pg_assert_contains("\$output_label .= ' [' . lang('DISABLED') . ']';", $body, 'and marked disabled');
}
