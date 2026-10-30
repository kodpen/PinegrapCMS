<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the back arrow of panel screens (pg_page_shell 'cancel'): it
 * goes where the screen's Save goes, send_to first, not to a list that
 * answers 404, draws another filter or belongs to another area.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The 'cancel' line of a screen, or '' when it prints no arrow.
function back_arrow_targets_cancel_line($file)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

	return preg_match("/'cancel'\\s*=>[^\\n]*/", $source, $match) ? $match[0] : '';
}

function test_back_arrow_menu_items_go_to_their_menu()
{
	foreach (array('add_menu_item.php', 'edit_menu_item.php') as $file) {
		$line = back_arrow_targets_cancel_line($file);

		pg_assert_contains("'view_menu_items.php?id=' . (int)\$menu_id . '&from=' . urlencode(", $line, $file . ' goes back to its menu, not to view_menu_items.php without an id (404)');
		pg_assert_contains("'&send_to=' . urlencode(", $line, $file . ' keeps send_to like its Save');
	}
}

function test_back_arrow_region_kinds_go_to_their_filter()
{
	$screens = array(
		'add_ad_region.php' => array('ad', false),
		'edit_ad_region.php' => array('ad', true),
		'add_login_region.php' => array('login', false),
		'edit_login_region.php' => array('login', false),
		'add_dynamic_region.php' => array('dynamic', false),
		'edit_dynamic_region.php' => array('dynamic', false),
		'add_designer_region.php' => array('designer', false),
		'edit_designer_region.php' => array('designer', true),
	);

	foreach ($screens as $file => $screen) {
		$line = back_arrow_targets_cancel_line($file);
		$url = "'view_regions.php?filter=all_" . $screen[0] . "_regions'";

		pg_assert_contains($screen[1] ? 'pg_send_to_url(' . $url . ')' : $url, $line, $file . ' goes back to its own region list');
	}
}

function test_back_arrow_form_views_go_to_their_page()
{
	foreach (array('edit_form_list_view.php', 'edit_form_item_view.php') as $file) {
		$line = back_arrow_targets_cancel_line($file);

		pg_assert_contains("pg_send_to_url('edit_page.php?id=' . (int)\$page['id'])", $line, $file . ' goes back to the page it was opened from');
		pg_assert_same(false, strpos($line, 'view_submitted_forms.php'), $file . ' no longer goes to the submitted forms list');
	}
}

function test_back_arrow_calendar_events_go_to_the_calendar()
{
	pg_assert_contains("'url'=>'calendars.php'", back_arrow_targets_cancel_line('add_calendar_event.php'), 'Create Event goes back to the calendar view');
	pg_assert_contains("pg_send_to_url('calendars.php')", back_arrow_targets_cancel_line('edit_calendar_event.php'), 'Edit Event goes back to send_to or the calendar view');
}

function test_back_arrow_referral_sources_has_none()
{
	pg_assert_same('', back_arrow_targets_cancel_line('view_referral_sources.php'), 'a Site Settings list prints no arrow, like All Currencies');
}
