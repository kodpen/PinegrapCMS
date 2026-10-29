<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source pins for controls that act as buttons and for names a screen reader
 * reads: report Edit and Add Filter are buttons that keep focus visible,
 * Unlock Sign-in submits its form, Create an User is a real link, the
 * Optimize, Shipment Tracking and order view Print buttons carry their
 * action's name, the edit-file previews add no unnamed tab stop, and Download
 * keeps the file's own name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function panel_link_roles_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

// A link with href="#" or javascript:void(0) is read as a link and ignores
// Space; the report Edit button moves focus into the form it opens, and
// Cancel Edit brings it back to Edit.
function test_report_edit_and_add_filter_are_buttons()
{
	foreach (array('view_order_report.php', 'view_visitor_report.php') as $file) {
		$source = panel_link_roles_source($file);

		pg_assert_false(strpos($source, 'href="#" id="edit_button"') !== false, $file . ': Edit is a link');
		pg_assert_true(strpos($source, '<button type="button" class="btn btn-solid theme-primary" id="edit_button"') !== false, $file . ': Edit is a button');
		pg_assert_true(strpos($source, "document.getElementById(\\'edit_button\\').focus();") !== false, $file . ': Cancel Edit returns focus to Edit');
		pg_assert_true(strpos($source, "querySelector(\\'input:not([type=hidden]), select, textarea\\')") !== false, $file . ': Edit moves focus into the form');
	}

	foreach (array('view_order_report.php', 'view_visitor_report.php', 'edit_form_list_view.php') as $file) {
		$source = panel_link_roles_source($file);

		pg_assert_false(strpos($source, 'href="javascript:void(0)" onclick="create_filter()"') !== false, $file . ': Add Filter is a link');
		pg_assert_true(strpos($source, '<button type="button" onclick="create_filter()"') !== false, $file . ': Add Filter is a button');
	}
}

function test_unlock_sign_in_is_a_submit_button()
{
	$source = panel_link_roles_source('edit_user.php');

	pg_assert_false(strpos($source, 'document.unlock_sign_in_form.submit()') !== false, 'Unlock Sign-in submits from a script');
	pg_assert_true(strpos($source, '<button type="submit" form="unlock_sign_in_form"') !== false, 'Unlock Sign-in is a submit button');
	pg_assert_true(strpos($source, '<form id="unlock_sign_in_form" name="unlock_sign_in_form"') !== false, 'unlock form has its id');
}

function test_create_user_is_a_real_link()
{
	$source = panel_link_roles_source('edit_contact.php');

	pg_assert_false(strpos($source, 'href="#!"') !== false, 'Create an User navigates from a script');
	pg_assert_true(strpos($source, "'<a class=\"alert-link\" href=\"' . h(PATH . SOFTWARE_DIRECTORY . '/add_user.php?contact_id=' . \$_GET['id']) . '\">'") !== false, 'Create an User has a real href');
}

// A button whose only text is a figure is named by that figure; a hidden
// action word in front of it gives the whole name.
function test_figure_buttons_carry_their_action_name()
{
	foreach (array('view_files.php', 'view_design_files.php') as $file) {
		$source = panel_link_roles_source($file);
		pg_assert_true(strpos($source, '<i class="bi bi-fast-forward-circle" aria-hidden="true"></i><span class="visually-hidden">\' . h(lang(\'Optimize this image\')) . \'</span>\' . $optimize_percent_html') !== false, $file . ': Optimize named');
	}

	$source = panel_link_roles_source('view_orders.php');
	pg_assert_true(strpos($source, '<span class="visually-hidden">\' . h(lang(\'Shipment Tracking\')) . \'</span>') !== false, 'Shipment Tracking named');
}

function test_order_view_print_buttons_are_named()
{
	$source = panel_link_roles_source('view_order.php');

	pg_assert_false(strpos($source, 'return false;""') !== false, 'stray quote after onclick');
	pg_assert_false((bool) preg_match('/<button\b[^>]*print_[a-z_]+\.php[^>]*>.*?<\/a>/', $source), 'print button closed with </a>');
	pg_assert_true(strpos($source, 'aria-label="\' . h(lang(\'Print Packing Slip\')) . \'"') !== false, 'packing slip button named');
	pg_assert_true(strpos($source, 'aria-label="\' . h(lang(\'Print Order\')) . \'"') !== false, 'print order button named');
}

// The preview link repeats Open in New Tab, so it stays out of keyboard and
// screen-reader reach; Download saves under the file's own name.
function test_edit_file_previews_and_download_names()
{
	foreach (array('edit_file.php', 'edit_design_file.php') as $file) {
		$source = panel_link_roles_source($file);

		pg_assert_false(strpos($source, 'download="image"') !== false, $file . ': every file downloaded as image');
		pg_assert_true(strpos($source, 'download="\' . h($file_name) . \'"') !== false, $file . ': download keeps the file name');
		pg_assert_true(strpos($source, '" target="_blank" tabindex="-1" aria-hidden="true"') !== false, $file . ': preview link out of reach');
		pg_assert_false((bool) preg_match('/<img(?![^>]*\balt=)[^>]*\blazy\b/', $source), $file . ': preview image without alt');
	}
}
