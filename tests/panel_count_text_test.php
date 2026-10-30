<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel counts read right at one: a count key has its English singular
 * ('{var:1} day{suffix:1}', never a plural-only '{var:1} days'), a verb
 * that agrees with the count is chosen from two literal keys, and no
 * count is glued to a noun outside lang(). These tests pin the ERP due
 * badges, the Create Order cart, the Users list lock, the API application
 * counts, the delete and connection notices and the File and Catalog
 * Manager answers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

/**
 * The source of a product file.
 *
 * @param string $path relative to pinegrap/
 * @return string
 */
function panel_count_text_source($path)
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/' . $path);
}

/**
 * The plural-only keys each file used, and the keys that replace them.
 *
 * @return array path => array('gone' => keys, 'used' => keys)
 */
function panel_count_text_pins()
{
	return array(
		'erp_invoices.php' => array(
			'gone' => array("'{var:1} days overdue'", "'due in {var:1} days'"),
			'used' => array("'{var:1} day{suffix:1} overdue'", "'due in {var:1} day{suffix:1}'"),
		),
		'erp_dashboard.php' => array(
			'gone' => array("'{var:1} days'"),
			'used' => array("'{var:1} day{suffix:1}'"),
		),
		'add_order.php' => array(
			'gone' => array("'{var:1} lines · {var:2} units'"),
			'used' => array('local_sale_count_text('),
		),
		'includes/local_sale.php' => array(
			'gone' => array("'{var:1} lines · {var:2} units'", "'{var:1} · {var:2} lines'", "'{var:1} days'"),
			'used' => array("'{var:1} line{suffix:1} · {var:2} unit{suffix:2}'", "'{var:1} · {var:2} line{suffix:1}'", "'{var:1} day{suffix:1}'"),
		),
		'includes/fn/user_list.php' => array(
			'gone' => array("'Clears in {var:1} minutes.'"),
			'used' => array("'Clears in {var:1} minute{suffix:1}.'"),
		),
		'api_settings.php' => array(
			'gone' => array("lang('connections')", "lang('devices')", "lang('requests')", "lang('errors')"),
			'used' => array("'{var:1} connection{suffix:1}'", "'{var:1} device{suffix:1}'", "'{var:1} request{suffix:1}'", "'{var:1} error{suffix:1}'"),
		),
		'delete_users.php' => array(
			'gone' => array(),
			'used' => array("'{var:1} user was deleted'", "'{var:1} users were deleted'"),
		),
		'connected_apps.php' => array(
			'gone' => array(),
			'used' => array("'{var:1} connection was ended.'", "'{var:1} connections were ended.'"),
		),
		'includes/explorer/handle_files.php' => array(
			'gone' => array(),
			'used' => array("'{var:1} file was updated'", "'{var:1} files were updated'"),
		),
		'includes/explorer/handle_bulk_edit.php' => array(
			'gone' => array(),
			'used' => array("'{var:1} file was updated'", "'{var:1} files were updated'", "'{var:1} page was updated'", "'{var:1} pages were updated'"),
		),
		'includes/explorer/handle_zip.php' => array(
			'gone' => array(),
			'used' => array("'{var:1} file was compressed'", "'{var:1} files were compressed'", "'{var:1} file was extracted'", "'{var:1} files were extracted'"),
		),
		'includes/explorer/handle_backups.php' => array(
			'gone' => array(),
			'used' => array("'{var:1} file was compressed'", "'{var:1} files were compressed'", "'{var:1} file was extracted'", "'{var:1} files were extracted'"),
		),
		'includes/explorer/handle_products_bulk.php' => array(
			'gone' => array(),
			'used' => array("'{var:1} product was updated ({var:2}).'", "'{var:1} products were updated ({var:2}).'", "'{var:1} product was updated'", "'{var:1} products were updated'"),
		),
		'includes/explorer/handle_items.php' => array(
			'gone' => array("lang('item(s)')", "'were moved to {var:1}'", "'{var:1} item(s) were moved.'"),
			'used' => array("'{var:1} item was moved.'", "'{var:1} items were moved.'", "'{var:1} item was moved to the folder {var:2}'", "'{var:1} items were moved to the folder {var:2}'"),
		),
	);
}

function test_panel_count_text_keys_have_a_singular()
{
	foreach (panel_count_text_pins() as $path => $pins) {
		$source = panel_count_text_source($path);

		foreach ($pins['gone'] as $gone) {
			pg_assert_true(strpos($source, $gone) === false, $path . ': ' . $gone . ' is gone');
		}

		foreach ($pins['used'] as $used) {
			pg_assert_true(strpos($source, $used) !== false, $path . ': ' . $used . ' is used');
		}
	}
}

function test_panel_count_text_no_count_glued_outside_lang()
{
	$api_settings = panel_count_text_source('api_settings.php');

	pg_assert_true(strpos($api_settings, "\$signed_in_count . ' ' . lang(") === false, 'api_settings.php: the signed-in count goes through lang()');
	pg_assert_true(!preg_match("/pg_format_number\([^;]*?, 0\) \. ' ' \. lang\('(connections|requests|errors)'\)/", $api_settings), 'api_settings.php: no formatted count glued to a noun');
	pg_assert_true(strpos(panel_count_text_source('includes/explorer/handle_items.php'), "\$moved . ' ' . lang(") === false, 'handle_items.php: the moved count goes through lang()');
}

function test_panel_count_text_cart_units_are_formatted()
{
	$source = panel_count_text_source('includes/local_sale.php');

	pg_assert_true(strpos($source, "pg_format_number(\$totals['units'], 0)") !== false, 'local_sale.php: the cart units go through pg_format_number()');
	pg_assert_true(substr_count($source, 'local_sale_count_text($totals)') >= 2, 'local_sale.php: the summary card and the Ajax count text share one helper');
}

function test_panel_count_text_new_keys_are_translated()
{
	$map = pg_test_language_map('tr');

	$keys = array(
		'{var:1} day{suffix:1} overdue' => null,
		'due in {var:1} day{suffix:1}' => null,
		'{var:1} day{suffix:1}' => null,
		'{var:1} line{suffix:1} · {var:2} unit{suffix:2}' => null,
		'{var:1} · {var:2} line{suffix:1}' => null,
		'Clears in {var:1} minute{suffix:1}.' => null,
		'{var:1} connection{suffix:1}' => null,
		'{var:1} device{suffix:1}' => null,
		'{var:1} request{suffix:1}' => null,
		'{var:1} error{suffix:1}' => null,
		'{var:1} user was deleted' => null,
		'{var:1} connection was ended.' => null,
		'{var:1} file was updated' => null,
		'{var:1} page was updated' => null,
		'{var:1} file was compressed' => null,
		'{var:1} file was extracted' => null,
		'{var:1} product was updated' => null,
		'{var:1} item was moved.' => null,
		'{var:1} items were moved.' => null,
		'{var:1} item was moved to the folder {var:2}' => '{var:1} öğe {var:2} klasörüne taşındı',
		'{var:1} items were moved to the folder {var:2}' => '{var:1} öğe {var:2} klasörüne taşındı',
	);

	foreach ($keys as $key => $value) {
		pg_assert_true(isset($map[$key]), 'no Turkish value for "' . $key . '"');

		if (isset($map[$key]) && ($value !== null)) {
			pg_assert_same($value, $map[$key], 'Turkish value of "' . $key . '"');
		}
	}
}
