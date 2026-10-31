<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pictures whose file was deleted in the File Manager: a contact
 * picture id without its files row gives no file name
 * (pg_contact_picture_name()), and the order view, print order, Edit User,
 * Edit Contact and the Contacts list then draw their no-picture branch, not
 * an <img> pointing at the site's home page. The Out of stock card and the
 * Create Order cart print a product picture only when its file is on disk.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function contact_picture_fallback_source($path)
{
	return file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_contact_picture_name_from_known_names()
{
	$names = array(7 => 'ayse.jpg', 9 => '');

	pg_assert_same('ayse.jpg', pg_contact_picture_name(7, $names), 'a files row gives its name');
	pg_assert_same('ayse.jpg', pg_contact_picture_name('7', $names), 'a string id is read as a number');
	pg_assert_same('', pg_contact_picture_name(8, $names), 'a deleted files row gives no name');
	pg_assert_same('', pg_contact_picture_name(9, $names), 'an empty name is no picture');
	pg_assert_same('', pg_contact_picture_name(0, $names), 'no picture id, no name');
	pg_assert_same('', pg_contact_picture_name('', $names), 'an empty id, no name');
	pg_assert_same('', pg_contact_picture_name(null, $names), 'a missing id, no name');
}

function test_contact_screens_take_the_no_picture_branch_for_a_deleted_file()
{
	$view_order = contact_picture_fallback_source('view_order.php');
	pg_assert_contains('$contact_picture_name = pg_contact_picture_name($contact_file_id);', $view_order, 'the order view asks the helper');
	pg_assert_true(strpos($view_order, "\$file['name']") === false, 'the order view never reads a missing files row');

	$edit_contact = contact_picture_fallback_source('edit_contact.php');
	pg_assert_contains("pg_contact_picture_name(\$row['file_id'])", $edit_contact, 'Edit Contact asks the helper');
	pg_assert_true(strpos($edit_contact, "\$file['name']") === false, 'Edit Contact never reads a missing files row');
	pg_assert_contains('<input type="hidden" name="file_id" id="file_id" value="\' . (int) $row[\'file_id\'] . \'"/>', $edit_contact, 'Edit Contact keeps the stored id so a Save changes nothing');

	$edit_user = contact_picture_fallback_source('edit_user.php');
	pg_assert_contains('pg_contact_picture_name($contact_file_id)', $edit_user, 'Edit User asks the helper');
	pg_assert_true(strpos($edit_user, 'PATH . db_value("SELECT name FROM files') === false, 'Edit User never prints PATH alone');

	$list = contact_picture_fallback_source('includes/fn/contact_list.php');
	pg_assert_contains('pg_contact_picture_name($contact[\'file_id\'], $files)', $list, 'the Contacts list asks the helper');
	pg_assert_contains("(string) \$file_name !== ''", $list, 'a list row prints an <img> only with a file name');

	$print = contact_picture_fallback_source('print_order.php');
	pg_assert_true(strpos($print, '$output_contact_image') === false, 'print order builds no contact picture it never prints');
	pg_assert_true(strpos($print, "\$row['image']") === false, 'print order reads no column its query lacks');
}

function test_product_thumbs_need_their_file_on_disk()
{
	$widget = contact_picture_fallback_source('includes/dashboard/widgets/widget_9.php');
	pg_assert_contains("is_file(FILE_DIRECTORY_PATH . '/' . basename(\$row['image_name']))", $widget, 'the Out of stock card checks the file');

	$sale = contact_picture_fallback_source('includes/local_sale.php');
	pg_assert_contains("is_file(FILE_DIRECTORY_PATH . '/' . basename(\$line['image_name']))", $sale, 'the Create Order cart checks the file');
}
