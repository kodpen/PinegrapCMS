<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the screens that send the operator to a setting of Site
 * Settings › Ecommerce or › Accounting: their links reach the pane and card
 * that hold the setting, and their texts name the row it is on now that the
 * old ERP card was cut into rows (Documents, Sales, Web orders, Reminders
 * and notices).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function settings_moved_rows_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_settings_moved_rows_stock_history_links_the_stock_locations_card()
{
	$src = settings_moved_rows_source('view_stock_moves.php');

	// settings.php is no screen: the dialog read it as a link without a pane
	// and opened the category last seen instead of Ecommerce.
	pg_assert_false(strpos($src, "'settings.php#") !== false, 'view_stock_moves.php: no link to the settings.php screen, which does not exist');
	pg_assert_true(strpos($src, "pg_settings_link('commerce', 'pgset-stock-locations')") !== false, 'view_stock_moves.php: Add a stock location opens Ecommerce on Stock locations');
	pg_assert_false(file_exists(PG_FUNCTIONS_DIR . '/settings.php'), 'there is still no settings.php screen');
}

function test_settings_moved_rows_texts_name_the_row()
{
	$files = array(
		'edit_erp_invoice.php',
		'erp_settings.php',
		'view_order.php',
		'includes/erp/account_form.php',
		'includes/erp/notify.php',
		'includes/erp/order_flow.php',
	);

	foreach ($files as $file) {
		$src = settings_moved_rows_source($file);

		pg_assert_false(strpos($src, 'ERP settings card') !== false, $file . ': no text sends the operator to the ERP settings card, which is gone');
		pg_assert_false(strpos($src, 'on the ERP card of the accounting settings') !== false, $file . ': no text names the ERP card of the accounting settings');
	}

	$rows = array(
		'edit_erp_invoice.php' => array('Reminders and notices'),
		'includes/erp/account_form.php' => array('Reminders and notices', 'Web orders'),
		'includes/erp/notify.php' => array('Reminders and notices'),
		'includes/erp/order_flow.php' => array('Sales'),
		'view_order.php' => array('Sales'),
	);

	foreach ($rows as $file => $names) {
		$src = settings_moved_rows_source($file);

		foreach ($names as $name) {
			pg_assert_true(strpos($src, 'Site Settings › Accounting › ' . $name) !== false, $file . ': the text names Site Settings › Accounting › ' . $name);
		}
	}

	// The seller note of ERP Settings names Documents, the row holding the
	// address the sale was made at.
	$erp_settings = settings_moved_rows_source('erp_settings.php');
	pg_assert_true(strpos($erp_settings, "lang('Accounting') . ' &rsaquo; ' . lang('Documents')") !== false, 'erp_settings.php: the place of the sale\'s address ends on Documents');
	pg_assert_false(strpos($erp_settings, "lang('Accounting') . ' &rsaquo; ' . lang('ERP')") !== false, 'erp_settings.php: no place ends on an ERP card');
}
