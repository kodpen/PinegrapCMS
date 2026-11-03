<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the send_to of edit_system_style.php and
 * view_system_style_source.php. Both put it in the form's session and later
 * hand it to urlencode() (a failed design save, the Save button of the head
 * content); a send_to[]= in the address made that a TypeError on PHP 8. It
 * is taken as one value before the form stores it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_system_style_send_to_is_one_value()
{
	$files = array(
		'edit_system_style.php'        => "\$liveform = new liveform('edit_system_style'",
		'view_system_style_source.php' => "\$liveform = new liveform('view_system_style_source'",
	);

	foreach ($files as $file => $form) {
		$code = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		$request = "pg_request_single_values(array('send_to'));";
		$at = strpos($code, $request);

		pg_assert_true($at !== false, $file . ': send_to is one value');
		pg_assert_true($at !== false && $at < strpos($code, $form), $file . ': before the form stores it');
	}
}

function test_system_style_array_send_to_is_dropped()
{
	$get = $_GET;
	$post = $_POST;
	$request = $_REQUEST;

	$_GET = array('send_to' => array('/x'));
	$_POST = array();
	$_REQUEST = $_GET;
	pg_request_single_values(array('send_to'));
	pg_assert_same('', urlencode($_REQUEST['send_to'] ?? ''), 'an array send_to reads as none');

	$_GET = $get;
	$_POST = $post;
	$_REQUEST = $request;
}
