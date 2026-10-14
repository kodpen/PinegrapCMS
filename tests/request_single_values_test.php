<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_request_single_values() (includes/fn/core.php), which init.php
 * calls for send_to and from: a parameter that carries one value counts as
 * not given when it arrives as an array (?send_to[]=x).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $check with the request set to $get and $post ($_REQUEST being both,
// the post winning as with request_order "GP"), and puts the real request
// back afterwards.
function _test_request_single_values_with($get, $post, $check)
{
	$saved = array($_GET, $_POST, $_REQUEST);
	$_GET = $get;
	$_POST = $post;
	$_REQUEST = array_merge($get, $post);

	try {
		return $check();
	} finally {
		list($_GET, $_POST, $_REQUEST) = $saved;
	}
}

// An array is removed from $_GET and $_REQUEST; strings and other parameters
// are left exactly as they came.
function test_request_single_values_removes_arrays()
{
	_test_request_single_values_with(
		array('send_to' => array('x'), 'from' => '/pinegrap/view_menus.php', 'tags' => array('a')),
		array(),
		function () {
			pg_request_single_values(array('send_to', 'from'));

			pg_assert_false(isset($_GET['send_to']), 'array removed from $_GET');
			pg_assert_false(isset($_REQUEST['send_to']), 'array removed from $_REQUEST');
			pg_assert_same('/pinegrap/view_menus.php', $_GET['from'], 'string kept');
			pg_assert_same('/pinegrap/view_menus.php', $_REQUEST['from'], 'string kept in $_REQUEST');
			pg_assert_same(array('a'), $_GET['tags'], 'unlisted array kept');
		}
	);
}

// A posted array is removed too, and an empty string is still a string.
function test_request_single_values_posted_array()
{
	_test_request_single_values_with(
		array(),
		array('send_to' => array('/a', '/b'), 'from' => ''),
		function () {
			pg_request_single_values(array('send_to', 'from'));

			pg_assert_false(isset($_POST['send_to']), 'array removed from $_POST');
			pg_assert_false(isset($_REQUEST['send_to']), 'array removed from $_REQUEST');
			pg_assert_same('', $_POST['from'], 'empty string kept');
		}
	);
}

// Each copy is checked on its own: a string in the address stays when the
// post carries the array that $_REQUEST took over.
function test_request_single_values_each_copy_on_its_own()
{
	_test_request_single_values_with(
		array('send_to' => '/pinegrap/view_pages.php'),
		array('send_to' => array('x')),
		function () {
			pg_request_single_values(array('send_to'));

			pg_assert_same('/pinegrap/view_pages.php', $_GET['send_to'], '$_GET string kept');
			pg_assert_false(isset($_POST['send_to']), '$_POST array removed');
			pg_assert_false(isset($_REQUEST['send_to']), '$_REQUEST array removed');
		}
	);
}
