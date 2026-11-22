<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_request_refuse_lists() (includes/fn/core.php) and the create
 * and edit screens of ads, ad regions, auto dialogs, contact groups,
 * containers, key codes, login regions, menus and product attributes. Their
 * forms draw no list; a field posted as one (name[]=x) logged "Array to
 * string conversion" and saved "Array", so the save is now refused before
 * the fields reach the session.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

/**
 * The record screens that refuse a list, with the form file they share.
 *
 * @return array screen => form file, both below the software directory
 */
function pg_test_refuse_lists_screens()
{
	$screens = array();

	foreach (array('ad', 'ad_region', 'auto_dialog', 'contact_group', 'container', 'key_code', 'login_region', 'menu', 'product_attribute') as $record) {
		foreach (array('add_', 'edit_') as $prefix) {
			$screens[$prefix . $record . '.php'] = 'includes/forms/' . $record . '_form.php';
		}
	}

	return $screens;
}

// With no list posted the helper returns and touches nothing.
function test_request_refuse_lists_returns_when_every_field_fits()
{
	$saved_post = $_POST;
	$_POST = array('name' => 'Header', 'description' => '', 'id' => '5');

	$touched = new class {
		public $errors = array();

		public function add_error($message = '')
		{
			$this->errors[] = $message;
		}
	};

	pg_request_refuse_lists($touched, 'add_menu.php');
	pg_assert_same(array(), $touched->errors, 'no error');

	$_POST = array('choices' => array('a', 'b'));
	pg_request_refuse_lists($touched, 'add_menu.php', array('choices' => 1));
	pg_assert_same(array(), $touched->errors, 'a list the form draws fits');

	$_POST = $saved_post;
}

// Each screen checks right after the token, before the fields reach the
// session or a field is read, and goes back to itself.
function test_request_refuse_lists_record_screens()
{
	foreach (pg_test_refuse_lists_screens() as $screen => $form) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		$token = strpos($source, 'validate_token_field();');
		$call = strpos($source, "pg_request_refuse_lists(\$liveform, '" . $screen, (int) $token);

		pg_assert_true(($token !== false) && ($call !== false), $screen . ' refuses a list');

		if (($token === false) || ($call === false)) {
			continue;
		}

		foreach (array('add_fields_to_session(', '$_POST[', 'get_field_value(', 'field_in_session(', 'db(', 'db_q(') as $read) {
			$first = strpos($source, $read, $token);
			pg_assert_true(($first === false) || ($first > $call), $screen . ' checks before ' . $read);
		}
	}
}

// The forms draw no list, so any list is refused. A list field added to one
// of them needs its name in the screen's call, or every save is refused.
function test_request_refuse_lists_forms_draw_no_list()
{
	foreach (pg_test_refuse_lists_screens() as $screen => $form) {
		foreach (array($screen, $form) as $file) {
			$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

			pg_assert_same(0, preg_match('/name\s*=\s*\\\\?["\'][A-Za-z0-9_]+\[|[\'"]name[\'"]\s*=>\s*[\'"][A-Za-z0-9_]+\[/', $source), $file . ' draws no list');
		}
	}
}
