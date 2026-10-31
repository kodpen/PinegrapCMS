<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The toolbar of the content screens sits straight under the title: it is
 * drawn by pg_screen_toolbar(), never inside a .row > .col-* md:text-start
 * column beside a second tool column, and View Pages keeps at most four
 * buttons in the bar itself, the rest in one menu.
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
 * The content screens whose bar left its column.
 */
function button_bar_placement_test_screens()
{
	return array(
		'view_pages.php', 'view_ads.php', 'editor_select_page_or_file.php', 'view_log.php',
		'view_regions.php', 'view_menus.php', 'view_styles.php', 'view_themes.php',
		'view_comments.php', 'view_fields.php', 'view_calendars.php', 'view_calendar_event_locations.php',
		'view_short_links.php', 'edit_short_link.php', 'view_containers.php', 'edit_design_file.php',
		'edit_file.php', 'includes/templates/edit_auto_dialog.php', 'includes/templates/view_auto_dialogs.php',
		'includes/templates/view_key_codes.php', 'view_contact_groups.php', 'view_email_campaigns.php',
		'view_email_campaign_profiles.php', 'view_email_campaign_history.php', 'view_sms_campaigns.php',
	);
}

function test_button_bar_placement_drawn_by_the_helper_outside_columns()
{
	foreach (button_bar_placement_test_screens() as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_true($source !== false, $file . ': is read');

		pg_assert_true(strpos($source, 'pg_screen_toolbar(') !== false, $file . ': draws its bar through pg_screen_toolbar()');
		pg_assert_same(false, strpos($source, '<nav id="button_bar"'), $file . ': no hand-written #button_bar is left');
		pg_assert_same(0, preg_match('/class="col-[^"]*md:text-start/', $source), $file . ': no md:text-start tool column is opened');
	}
}

function test_button_bar_placement_view_pages_four_buttons_and_one_menu()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_pages.php');
	$start = strpos($source, '$output_button_bar = \'\';');
	$end = strpos($source, '$output_button_bar = pg_screen_toolbar(');
	pg_assert_true($start !== false && $end !== false && $start < $end, 'view_pages.php: the toolbar is built in one place');

	$block = substr($source, $start, $end - $start);

	// A bar button is a btn outside the menu's rows; the menu toggle counts.
	$buttons = 0;
	foreach (explode("\n", $block) as $line) {
		if (strpos($line, 'menu-item') !== false) {
			continue;
		}
		$buttons += preg_match_all('/class="btn /', $line);
	}
	pg_assert_true($buttons >= 1 && $buttons <= 4, 'view_pages.php: one to four buttons in the bar (' . $buttons . ')');

	foreach (array('Add Page (Legacy)', 'Update Search Index', 'Refresh SEO Scores', 'Analyze HTML Structure', 'Accessibility report') as $label) {
		$found = false;
		foreach (explode("\n", $block) as $line) {
			if ((strpos($line, $label) !== false) && (strpos(ltrim($line), '//') !== 0)) {
				$found = true;
				pg_assert_contains('menu-item', $line, 'view_pages.php: ' . $label . ' is a menu row');
			}
		}
		pg_assert_true($found, 'view_pages.php: ' . $label . ' is still offered');
	}
}
