<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the login region form shared by the create and the edit
 * screens (includes/forms/login_region_form.php): the values a save
 * writes, the default a new region starts with, and that both screens use
 * the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
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

	pg_assert_contains('pg_login_region_form_card($liveform, true)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/add_login_region.php'), 'create checks Show Login Form');
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
