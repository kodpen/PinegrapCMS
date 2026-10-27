<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the jQuery UI timepicker add-on leaving the panel: no
 * panel screen calls .datetimepicker() or .timepicker() since the date and
 * time fields moved to pg_fields.js, so the converted screens no longer load
 * the add-on, while they still print the picker options pg_fields.js reads.
 * The site branch of get_form_content() keeps loading it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function bs6_timepicker_addon_test_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

function test_bs6_timepicker_addon_left_the_panel_screens()
{
	$files = array(
		'add_arrival_date.php',
		'edit_arrival_date.php',
		'add_email_campaign.php',
		'edit_email_campaign.php',
		'add_email_campaign_profile.php',
		'edit_email_campaign_profile.php',
		'add_shipping_method.php',
		'edit_shipping_method.php',
		'add_user.php',
		'edit_user.php',
		'edit_comment.php',
		'import_users.php',
	);

	foreach ($files as $path) {
		$source = bs6_timepicker_addon_test_source($path);
		pg_assert_false(strpos($source, 'timepicker-addon') !== false, $path . ' does not load the timepicker add-on');
		pg_assert_false((bool) preg_match('/\.(date)?timepicker\(|\$\.timepicker/', $source), $path . ' calls no timepicker');
	}

	// The picker options stay: pg_fields.js reads window.datetimepicker_options.
	pg_assert_contains('get_date_time_picker_format()', bs6_timepicker_addon_test_source('edit_comment.php'), 'edit_comment.php keeps the date and time options');
	pg_assert_contains('get_time_picker_format()', bs6_timepicker_addon_test_source('add_shipping_method.php'), 'add_shipping_method.php keeps the time options');
}

function test_bs6_timepicker_addon_form_content_branches()
{
	$source = bs6_timepicker_addon_test_source('includes/fn/content.php');

	// The panel branch: the scripts printed once after a backend form.
	$start = strpos($source, "\$form_info['picker_scripts'] = '';");
	pg_assert_true($start !== false, 'content.php builds picker_scripts');
	$end = strpos($source, 'return $form_info;', (int) $start);
	$panel = substr($source, (int) $start, (int) $end - (int) $start);
	pg_assert_false(strpos($panel, 'timepicker-addon') !== false, 'the backend picker_scripts do not load the add-on');
	pg_assert_contains('get_date_time_picker_format()', $panel, 'the backend picker_scripts keep the date and time options');

	// The site branch of a date and time field keeps the jQuery UI add-on.
	$found = preg_match("/case 'date and time':\\s*if \\(\\\$interface == 'backend'\\)/", $source, $match, PREG_OFFSET_CAPTURE);
	pg_assert_true($found === 1, 'content.php draws the date and time field per interface');
	$site = substr($source, $found === 1 ? $match[0][1] : 0, 1200);
	pg_assert_contains('timepicker-addon', $site, 'the site date and time field still loads the add-on');
	pg_assert_contains('.datetimepicker(datetimepicker_options)', $site, 'the site date and time field still calls datetimepicker');
}
