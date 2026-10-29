<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the names screen readers get on the form list view, the design
 * file editor and the ad region, calendar event and short link forms: every
 * code field has a name, radio and checkbox groups sit under a legend and
 * Material Icons words are not read aloud.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function content_form_group_names_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_form_list_view_advanced_search_code_field_is_named()
{
	$src = content_form_group_names_source('edit_form_list_view.php');

	pg_assert_contains('<div class="form-text text-start" id="search_advanced_layout_label">', $src, 'the advanced search text has an id');
	pg_assert_contains('<textarea id="search_advanced_layout" name="search_advanced_layout" aria-labelledby="search_advanced_layout_label">', $src, 'the advanced search code field points at its text');
}

function test_form_list_view_hints_buttons_hide_the_icon_word()
{
	$src = content_form_group_names_source('edit_form_list_view.php');

	pg_assert_true(strpos($src, '<span class="material-icons me-1">info</span>') === false, 'no Hints button reads "info"');
	pg_assert_true(substr_count($src, '<span class="material-icons me-1" aria-hidden="true">info</span>') === 2, 'both Hints icons are hidden');
}

function test_design_file_editor_label_names_the_code_field()
{
	$src = content_form_group_names_source('editor_edit_file.php');

	pg_assert_contains('<label class="form-label" for="code">', $src, 'the file label points at the code field');
}

function test_ad_region_radio_groups_have_a_legend()
{
	$src = content_form_group_names_source('includes/forms/ad_region_form.php');

	pg_assert_contains('<legend class="form-label fs-md">\' . lang(\'Display Type\') . \'</legend>', $src, 'Display Type is a legend');
	pg_assert_contains('<legend class="form-label fs-md">\' . lang(\'Transition Type\') . \'</legend>', $src, 'Transition Type is a legend');
	pg_assert_true(strpos($src, '<label class="form-label">\' . lang(\'Display Type\')') === false, 'no orphan Display Type label');
	pg_assert_true(strpos($src, '<label class="form-label">\' . lang(\'Transition Type\')') === false, 'no orphan Transition Type label');
	pg_assert_contains("'data-bs-target'=>'#display_type_dynamic_row'", $src, 'the dynamic radio still opens its row');
}

function test_calendar_event_recurrence_groups_have_a_legend()
{
	$src = content_form_group_names_source('includes/forms/calendar_event_form.php');

	pg_assert_contains('<legend class="form-label fs-md">\' . lang(\'On\') . \'</legend>', $src, 'the weekdays group is named On');
	pg_assert_contains('<legend class="form-label fs-md">\' . lang(\'By\') . \'</legend>', $src, 'the month type group is named By');
	pg_assert_true(strpos($src, '<h5 class="fg-secondary">\' . lang(\'On\')') === false, 'no On heading');
	pg_assert_true(strpos($src, '<h5 class="fg-secondary">\' . lang(\'By\')') === false, 'no By heading');
	pg_assert_contains('id="recurrence_days_of_the_week_row"', $src, 'the weekdays row keeps its id');
	pg_assert_contains('id="recurrence_month_type_row"', $src, 'the month type row keeps its id');
}

function test_short_link_name_icon_is_silent_and_pattern_is_visible()
{
	$src = content_form_group_names_source('includes/forms/short_link_form.php');

	pg_assert_contains('<span class="input-group-text material-icons" aria-hidden="true">public</span>', $src, 'the public icon is hidden');
	pg_assert_true(strpos($src, 'data-bs-content="\' . URL_SCHEME') === false, 'the address pattern is not in a hover popover');
	pg_assert_contains('<div class="form-text">\' . lang(\'This option determines the url address of the short link.\') . \' <code>\' . h(URL_SCHEME . HOSTNAME . OUTPUT_PATH . \'{\' . lang(\'Short Link Name\') . \'}\') . \'</code></div>', $src, 'the address pattern is shown under the field');
}
