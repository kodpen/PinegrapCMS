<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the menu form shared by the create and the edit screens
 * (includes/forms/menu_form.php): both screens use the shared form.
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
