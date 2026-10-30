<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the login region form shared by the create and the edit
 * screens (includes/forms/login_region_form.php): the values a save
 * writes, the default a new region starts with, what a refused create
 * comes back with, and that both screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!class_exists('liveform', false)) {
	require_once(PG_FUNCTIONS_DIR . '/liveform.class.php');
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/login_region_form.php');

function test_login_region_form_values_keep_the_insert_and_update_order()
{
	$fields = array(
		'name' => 'members',
		'not_logged_in_header' => '<p>Hello</p>',
		'login_form' => 1,
		'not_logged_in_footer' => '<p>Join</p>',
		'logged_in_header' => '<p>Welcome</p>',
		'logged_in_footer' => '<p>Bye</p>',
	);
	$liveform = new class($fields) {
		private $fields;

		public function __construct($fields)
		{
			$this->fields = $fields;
		}

		public function get_field_value($name)
		{
			return isset($this->fields[$name]) ? $this->fields[$name] : '';
		}
	};

	pg_assert_same(
		array('members', '<p>Hello</p>', '1', '<p>Join</p>', '<p>Welcome</p>', '<p>Bye</p>'),
		pg_login_region_form_values($liveform),
		'name, not logged in header, login form, not logged in footer, logged in header, logged in footer');
}

function test_login_region_form_default_only_on_create()
{
	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/login_region_form.php');

	pg_assert_contains('pg_login_region_form_card($liveform, $liveform->prefill())', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_login_region.php'), 'create checks Show Login Form only on a first draw');
	pg_assert_contains('pg_login_region_form_card($liveform)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_login_region.php'), 'edit shows the stored value');
	pg_assert_same(1, substr_count($form, '$checked + array('), 'only the login form switch has a default');
}

function test_login_region_form_screens_use_shared_form()
{
	foreach (array('add_login_region.php' => 'null', 'edit_login_region.php' => "(string) (\$_POST['id'] ?? '')") as $file => $id) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/login_region_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_login_region_form_validate_name($liveform, ' . $id . ')', $source, $file . ' checks the name the shared way');
		pg_assert_contains('array_merge(pg_login_region_form_values($liveform), ', $source, $file . ' saves the shared values');
		pg_assert_contains("validate_area_access(\$user, 'designer')", $source, $file . ' is for designers');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Show Login Form') !== false, $file . ' keeps no copy of the card');
	}
}

// Returns the card drawn the way the create screen draws it, after
// $request was posted and refused (no post: a first draw).
function _test_login_region_form_create_card($request)
{
	$saved = isset($_SESSION) ? $_SESSION : null;
	$saved_request = $_REQUEST;
	$_SESSION = array();

	$liveform = new liveform('login_region_form_test');

	if ($request !== null) {
		$_REQUEST = $request;
		$liveform->add_fields_to_session();
		$_REQUEST = $saved_request;
	}

	$card = pg_login_region_form_card($liveform, $liveform->prefill());

	if ($saved === null) {
		unset($_SESSION);
	} else {
		$_SESSION = $saved;
	}

	return $card;
}

function test_login_region_form_refused_create_keeps_the_login_form_switch()
{
	pg_assert_true((bool) preg_match('/id="login_form"[^>]*checked="checked"/', _test_login_region_form_create_card(null)), 'a new region shows the login form');
	pg_assert_false((bool) preg_match('/id="login_form"[^>]*checked/', _test_login_region_form_create_card(array('name' => 'members'))), 'a switch turned off stays off');
	pg_assert_true((bool) preg_match('/id="login_form"[^>]*checked="checked"/', _test_login_region_form_create_card(array('name' => 'members', 'login_form' => '1'))), 'a switch left on stays on');
}
