<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menu form shared by the create and the edit screens
 * (includes/forms/menu_form.php): the menu name rule, and that both
 * screens use the shared form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');

function test_menu_form_name_rule_on_create()
{
	$message = lang('Please only enter letters, numbers, and dashes for the name.');

	pg_assert_same('', pg_menu_form_name_error('Main-Menu-2', null), 'letters, numbers and dashes pass');
	pg_assert_same($message, pg_menu_form_name_error('main_menu', null), 'an underscore is refused');
	pg_assert_same($message, pg_menu_form_name_error('main menu', null), 'a space is refused');
	pg_assert_same($message, pg_menu_form_name_error('menü', null), 'a non-ASCII letter is refused');
}

function test_menu_form_name_rule_on_edit()
{
	$plain = lang('Please only enter letters, numbers, or dashes for the name.');
	$underscore = lang('Please only enter letters, numbers, dashes, and underscores for the name.');

	pg_assert_same('', pg_menu_form_name_error('top-menu', 'top-menu'), 'a valid name passes');
	pg_assert_same($plain, pg_menu_form_name_error('top_menu', 'top-menu'), 'no underscore when the stored name had none');
	pg_assert_same('', pg_menu_form_name_error('top_menu_2', 'top_menu'), 'underscores stay when the stored name had one');
	pg_assert_same($underscore, pg_menu_form_name_error('top menu', 'top_menu'), 'the underscore rule still refuses a space');
	pg_assert_same($plain, pg_menu_form_name_error('a.b', ''), 'a missing stored name uses the plain rule');
}

function test_menu_form_screens_use_shared_form()
{
	foreach (array('add_menu.php', 'edit_menu.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/menu_form.php', $source, $file . ' loads the form');
		pg_assert_contains('pg_menu_form_card($liveform)', $source, $file . ' prints the shared card');
		pg_assert_contains('pg_menu_form_validate_name($liveform, ', $source, $file . ' checks the name the shared way');
		pg_assert_contains('pg_menu_form_values($liveform)', $source, $file . ' saves the shared values');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'Menu Options') !== false, $file . ' keeps no copy of the card');
	}

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/menu_form.php');
	pg_assert_contains("PG_FUNCTIONS_DIR", $form, 'the form has the gate');
	pg_assert_contains('SELECT id FROM menus WHERE (name = ?)', $form, 'the uniqueness check binds the name');
}
