<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the name length refusal: a folder, page, product or product
 * group name longer than its varchar column is refused by the File and
 * Catalog Manager, the folder screens and the Pages bulk Find & Replace,
 * instead of being cut by the non-strict session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/folder_form.php');

function _test_name_length_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_name_length_counts_characters_not_bytes()
{
	pg_assert_same(100, pg_name_max_length(), 'the name columns are varchar(100)');
	pg_assert_false(pg_name_too_long(str_repeat('a', 100)), '100 characters fit');
	pg_assert_true(pg_name_too_long(str_repeat('a', 101)), '101 characters do not');
	pg_assert_false(pg_name_too_long(str_repeat('ş', 100)), '100 Turkish letters fit (200 bytes)');
	pg_assert_true(pg_name_too_long(str_repeat('ğ', 101)), '101 Turkish letters do not');
	pg_assert_false(pg_name_too_long(str_repeat('a', 255), 255), 'a wider limit');
	pg_assert_true(pg_name_too_long(str_repeat('a', 256), 255), 'past a wider limit');
	pg_assert_false(pg_name_too_long(null), 'null is empty');
	pg_assert_false(pg_name_too_long(''), 'empty fits');
}

function test_name_length_explorer_handlers_refuse_before_writing()
{
	$items = _test_name_length_source('includes/explorer/handle_items.php');

	$create = substr($items, (int) strpos($items, 'function pg_explorer_handle_create_folder('));
	pg_assert_true(strpos($create, 'pg_name_too_long($name)') < strpos($create, '$name = get_unique_name('), 'folder create refuses before the unique name');

	$rename = substr($items, (int) strpos($items, 'function pg_explorer_handle_rename('));
	pg_assert_true(strpos($rename, 'pg_name_too_long($name)') < strpos($rename, 'switch ($item_kind)'), 'rename refuses before any kind');
	pg_assert_true(strpos($rename, 'pg_name_too_long($name)') < strpos($rename, 'check_name_availability('), 'page rename refuses before the address check');

	$catalog = _test_name_length_source('includes/explorer/handle_catalog.php');
	$group = substr($catalog, (int) strpos($catalog, 'function pg_explorer_handle_catalog_create_group('));
	pg_assert_true(strpos($group, 'pg_name_too_long($name)') < strpos($group, 'INSERT INTO product_groups'), 'group create refuses before the insert');
	$crename = substr($catalog, (int) strpos($catalog, 'function pg_explorer_handle_catalog_rename('));
	pg_assert_true(strpos($crename, 'pg_name_too_long($name)') < strpos($crename, 'UPDATE product_groups'), 'catalog rename refuses before the update');
}

function test_name_length_folder_screens()
{
	$html = pg_folder_form_markup(array('name' => 'Docs'));
	pg_assert_contains('maxlength="100"', $html, 'edit name box stops at 100');

	pg_assert_contains("'maxlength'=>'100'", _test_name_length_source('add_folder.php'), 'create name box stops at 100');
	pg_assert_contains('pg_name_too_long($name)', _test_name_length_source('add_folder.php'), 'create refuses a long name');
	pg_assert_contains('pg_name_too_long(trim((string) $_POST[\'name\']))', _test_name_length_source('edit_folder.php'), 'edit refuses a long name');
}

function test_name_length_mass_edit()
{
	$source = _test_name_length_source('mass_edit.php');

	pg_assert_true((bool) preg_match('/name="page_title_\' \. \$row\[\'page_id\'\] \. \'" value="[^\n]*maxlength="255"/', $source), 'Web Browser Title stops at 255');
	pg_assert_contains("pg_name_too_long(\$new_val, (\$field === 'page_name') ? pg_name_max_length() : 255)", $source, 'Find & Replace skips a value past its column');
	pg_assert_true(strpos($source, 'pg_name_too_long($new_val') < strpos($source, 'check_name_availability(array(\'name\' => $new_val'), 'the length is checked on the value the name check sees');
}
