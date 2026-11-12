<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A picture drawn by a file's bare address carries the file's change time
 * (pg_url_change_stamp(), includes/fn/files.php): get_file.php lets a
 * browser or an edge cache keep the answer for a week, so a picture saved
 * again under the same name needs a new address to be fetched again. The
 * source checks pin the panel's places that print a contact's picture.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function picture_change_stamp_test_source($file)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
}

function test_picture_change_stamp_appended()
{
	pg_assert_same('/face.gif?v=1700000000', pg_url_change_stamp('/face.gif', 1700000000), 'bare address');
	pg_assert_same('/face.gif?w=80&v=1700000000', pg_url_change_stamp('/face.gif?w=80', 1700000000), 'address with a query');
	pg_assert_same('/face.gif?v=12#top', pg_url_change_stamp('/face.gif#top', 12), 'fragment stays last');
}

function test_picture_change_stamp_none_without_time()
{
	pg_assert_same('/face.gif', pg_url_change_stamp('/face.gif', 0), 'no time');
	pg_assert_same('/face.gif', pg_url_change_stamp('/face.gif', false), 'filemtime failed');
	pg_assert_same('', pg_url_change_stamp('', 1700000000), 'no address');
}

function test_picture_change_stamp_file_url()
{
	$name = 'pg-stamp-test-' . getmypid() . '.gif';
	$path = FILE_DIRECTORY_PATH . '/' . $name;

	if (!@file_put_contents($path, 'GIF89a')) {
		pg_assert_same(PATH . $name, pg_file_fresh_url($name), 'no file: bare address');
		return;
	}

	@touch($path, 1700000000);
	clearstatcache(true, $path);
	pg_assert_same(PATH . $name . '?v=1700000000', pg_file_fresh_url($name), 'stamped by its change time');
	@unlink($path);

	pg_assert_same(PATH . $name, pg_file_fresh_url($name), 'missing file: bare address');
	pg_assert_same('', pg_file_fresh_url(''), 'no name');
}

function test_picture_change_stamp_panel_faces()
{
	$places = array(
		'includes/workspace/people.php' => 'ws_avatar_url',
		'includes/fn/output.php'        => 'pg_account_menu',
		'chat.php'                      => 'pg_chat_avatar_src',
		'includes/fn/contact_list.php'  => 'contact list',
		'edit_contact.php'              => 'Edit Contact',
		'edit_user.php'                 => 'Edit User',
		'view_order.php'                => 'View Order',
	);

	foreach ($places as $file => $label) {
		pg_assert_true(strpos(picture_change_stamp_test_source($file), 'pg_file_fresh_url(') !== false, $label . ' prints the face with its change stamp');
	}

	pg_assert_false((bool) preg_match('/PATH \. \$row\[\'image_file_name\'\]/', picture_change_stamp_test_source('includes/workspace/people.php')), 'ws_avatar_url() bare address');
	pg_assert_false((bool) preg_match('/PATH \. \$person\[\'file_name\'\]/', picture_change_stamp_test_source('includes/fn/output.php')), 'account menu bare address');
}
