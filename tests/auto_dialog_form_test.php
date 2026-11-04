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
		pg_assert_contains('pg_auto_dialog_save(' . (($id === 'null') ? '0' : '$id') . ', pg_auto_dialog_form_values($liveform), USER_ID)', $source, $file . ' saves the shared values through pg_auto_dialog_save()');
		pg_assert_contains("validate_area_access(\$user, 'manager')", $source, $file . ' is for managers');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_false(strpos($source, 'already in use') !== false, $file . ' keeps no copy of the name check');
		pg_assert_false(strpos($source, 'mb_substr') !== false, $file . ' keeps no copy of the page clean-up');
	}
}

function test_auto_dialog_form_whole_number_reads_blank_plain_and_grouped_numbers()
{
	pg_assert_same('', pg_auto_dialog_form_whole_number('', 9999), 'blank stays blank');
	pg_assert_same('', pg_auto_dialog_form_whole_number('  ', 9999), 'spaces only are blank');
	pg_assert_same('', pg_auto_dialog_form_whole_number(null, 9999), 'no value is blank');
	pg_assert_same('0', pg_auto_dialog_form_whole_number('0', 9999), 'zero');
	pg_assert_same('600', pg_auto_dialog_form_whole_number(' 600 ', 9999), 'trimmed');
	pg_assert_same('2400', pg_auto_dialog_form_whole_number('2.400', 9999), 'Turkish thousands separator');
	pg_assert_same('2400', pg_auto_dialog_form_whole_number('2,400', 9999), 'English thousands separator');
	pg_assert_same('1200000', pg_auto_dialog_form_whole_number('1.200.000', 2147483), 'several groups');
	pg_assert_same('9999', pg_auto_dialog_form_whole_number('9999', 9999), 'the limit itself');
}

function test_auto_dialog_form_whole_number_refuses_negative_decimal_and_huge_values()
{
	foreach (array('-5', '-24', '12,5', '12.50', '1.5', '1e3', 'abc', '5px', '10000', '99999999999', '1.2.3', ',400') as $raw) {
		pg_assert_same(null, pg_auto_dialog_form_whole_number($raw, 9999), 'refused: ' . $raw);
	}
}

function test_auto_dialog_form_limits_keep_the_delay_inside_settimeout()
{
	$limits = pg_auto_dialog_form_number_limits();

	pg_assert_same(array('width', 'height', 'delay', 'frequency'), array_keys($limits), 'the four number fields');
	pg_assert_true($limits['delay'] * 1000 <= 2147483647, 'the delay in milliseconds fits setTimeout');

	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_auto_dialog.php');
	pg_assert_false(strpos($template, "'data-inputmask-alias'=>'decimal'") !== false, 'no decimal mask on the number fields');
	pg_assert_contains("'maxlength' => '100'", $template, 'the name is as long as its column');
}

function test_auto_dialog_list_enabled_cell_is_read_as_a_word()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_auto_dialogs.php');

	// The shared check cell: a mark with role img and the label Yes, sorted by 1/0.
	pg_assert_contains("'enabled' => pg_list_table_check_cell(!empty(\$auto_dialog['enabled']))", $screen, 'an enabled dialog reads Yes');
}
