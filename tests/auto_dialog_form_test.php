<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the auto dialog save shared by the create and the edit screens
 * (includes/forms/auto_dialog_form.php): the page clean-up, the values a
 * save writes, and that both screens use the shared helpers.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php');

function test_auto_dialog_form_page_drops_one_leading_slash()
{
	pg_assert_same('home', pg_auto_dialog_form_page('/home'), 'a URL typed instead of a page name');
	pg_assert_same('home', pg_auto_dialog_form_page('home'), 'a page name stays');
	pg_assert_same('/home', pg_auto_dialog_form_page('//home'), 'only the first slash goes');
	pg_assert_same('', pg_auto_dialog_form_page(null), 'no page');
	pg_assert_same('sayfa-ğ', pg_auto_dialog_form_page('/sayfa-ğ'), 'multibyte names');
}

function test_auto_dialog_form_values_keep_the_insert_and_update_order()
{
	$fields = array(
		'name' => 'welcome',
		'enabled' => 1,
		'url' => '/welcome-dialog',
		'width' => '600',
		'height' => '',
		'delay' => '5',
		'frequency' => '24',
		'page' => '/home',
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
		array('welcome', '1', '/welcome-dialog', '600', '', '5', '24', 'home'),
		pg_auto_dialog_form_values($liveform),
		'name, enabled, url, width, height, delay, frequency, page');
}

function test_auto_dialog_form_screens_use_shared_helpers()
{
	foreach (array('add_auto_dialog.php' => 'null', 'edit_auto_dialog.php' => '(string) $id') as $file => $id) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_contains('includes/forms/auto_dialog_form.php', $source, $file . ' loads the helpers');
		pg_assert_contains('pg_auto_dialog_form_validate($liveform, ' . $id . ')', $source, $file . ' checks the shared way');
		pg_assert_contains('array_merge(pg_auto_dialog_form_values($liveform), ', $source, $file . ' saves the shared values');
		pg_assert_contains("validate_area_access(\$user, 'manager')", $source, $file . ' is for managers');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'already in use') !== false, $file . ' keeps no copy of the name check');
		pg_assert_false(strpos($source, 'mb_substr') !== false, $file . ' keeps no copy of the page clean-up');
	}
}
