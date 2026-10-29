<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the jQuery UI timepicker add-on leaving the product: no
 * panel screen calls .datetimepicker() or .timepicker() since the date and
 * time fields moved to pg_fields.js, so the converted screens no longer load
 * the add-on, while they still print the picker options pg_fields.js reads.
 * The site renderers draw theirs on the same control, so no file loads it.
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
		'add_calendar_event.php',
		'edit_calendar_event.php',
		'add_submitted_form.php',
		'edit_submitted_form.php',
		'includes/settings/commerce.php',
		'view_order.php',
	);

	foreach ($files as $path) {
		$source = bs6_timepicker_addon_test_source($path);
		pg_assert_false(strpos($source, 'timepicker-addon') !== false, $path . ' does not load the timepicker add-on');
		pg_assert_false((bool) preg_match('/\.(date)?timepicker\(|\$\.timepicker/', $source), $path . ' calls no timepicker');
	}

	// The picker options stay: pg_fields.js reads window.datetimepicker_options.
	pg_assert_contains('get_date_time_picker_format()', bs6_timepicker_addon_test_source('edit_comment.php'), 'edit_comment.php keeps the date and time options');
	pg_assert_contains('get_time_picker_format()', bs6_timepicker_addon_test_source('add_shipping_method.php'), 'add_shipping_method.php keeps the time options');
	pg_assert_contains('get_time_picker_format()', bs6_timepicker_addon_test_source('includes/settings/commerce.php'), 'the commerce settings keep the time options');
	pg_assert_contains('get_date_picker_format()', bs6_timepicker_addon_test_source('view_order.php'), 'view_order.php keeps the date options');
}

function test_bs6_timepicker_addon_loaded_nowhere()
{
	// Custom forms, form views, comments, the checkout and order pages draw
	// their date and time fields on the date-and-time control: no PHP or
	// JavaScript file outside the vendored libraries loads the add-on.
	$root = dirname(__DIR__) . '/pinegrap/';
	$found = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if (($file->getExtension() !== 'php') && ($file->getExtension() !== 'js')) {
			continue;
		}
		$path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
		if (strpos($path, 'assets/lib/') === 0) {
			continue;
		}
		if (strpos((string) file_get_contents($file->getPathname()), 'jquery-ui-timepicker-addon') !== false) {
			$found[] = $path;
		}
	}

	pg_assert_same(array(), $found, 'no file loads the timepicker add-on');
}

function test_bs6_timepicker_addon_form_content_branches()
{
	$source = bs6_timepicker_addon_test_source('includes/fn/content.php');

	// The panel branch: the scripts printed once after a backend form.
	pg_assert_contains("\$form_info['picker_scripts'] = pg_form_info_picker_format(\$picker_kinds);", $source, 'content.php builds picker_scripts');
	$start = strpos($source, 'function pg_form_info_picker_format(');
	$end = strpos($source, "\n}\n", (int) $start);
	$panel = substr($source, (int) $start, (int) $end - (int) $start);
	pg_assert_false(strpos($panel, 'timepicker-addon') !== false, 'the backend picker_scripts do not load the add-on');
	pg_assert_contains('get_date_time_picker_format()', $panel, 'the backend picker_scripts keep the date and time options');

	// Site and panel rows alike carry the hook; the site page brings the
	// control itself (pg_site_datetime_inject()).
	foreach (array("'data-pg-date' => ''", "'data-pg-datetime' => ''", "'data-pg-time' => ''") as $hook) {
		pg_assert_contains($hook, $source, 'content.php hooks its rows: ' . $hook);
	}
	pg_assert_false((bool) preg_match('/\\.(date)?(time)?picker\\(datetimepicker_options\\)/', $source), 'content.php calls no jQuery UI picker');
}
