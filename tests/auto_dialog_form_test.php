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

function test_auto_dialog_template_lays_out_form_status_and_save_bar()
{
	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_auto_dialog.php');

	$form = strpos($template, 'class="col-12 lg:col-8 2xl:col-9 order-1"');
	$status = strpos($template, 'class="col-12 lg:col-4 2xl:col-3 order-2"');
	$bar = strpos($template, 'class="col-12 order-3 position-sticky"');
	pg_assert_true($form !== false && $status !== false && $bar !== false, 'form column, status column and save bar');
	pg_assert_true($form < $status && $status < $bar, 'the status column follows the form column, the bar comes last');
	pg_assert_contains('<div class="position-sticky" style="top:3.3rem;">', $template, 'the status box sticks under the top bar');
	pg_assert_true(strpos($template, "'id' => 'enabled'") > $status, 'the Enabled switch sits in the status column');

	foreach (array('page', 'width', 'height', 'delay', 'frequency') as $field) {
		pg_assert_contains('auto_dialog_summary_<?=$summary_row[0]?>', $template, 'the Summary cells carry an id');
		pg_assert_contains("array('" . $field . "', lang(", $template, $field . ' has a Summary row');
	}
	pg_assert_contains('<?=h(($summary_value === \'\')', $template, 'the Summary value is escaped');
}

function test_auto_dialog_edit_loads_the_last_change()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_auto_dialog.php');

	pg_assert_contains('LEFT JOIN user ON auto_dialogs.last_modified_user_id = user.user_id', $source, 'the user of the last change');
	pg_assert_contains('auto_dialogs.last_modified_timestamp', $source, 'the time of the last change');
	pg_assert_contains("WHERE auto_dialogs.id = '", $source, 'the id is read from the auto dialog table');
}

// A liveform stand-in for pg_auto_dialog_form_validate(): the posted fields
// and the errors it marks. The name is left blank, so the check that asks
// the database whether the name is taken does not run.
function auto_dialog_form_test_validate($url)
{
	$liveform = new class(array('name' => '', 'url' => $url)) {
		public $fields;
		public $errors = array();

		public function __construct($fields)
		{
			$this->fields = $fields;
		}

		public function get_field_value($name)
		{
			return isset($this->fields[$name]) ? $this->fields[$name] : '';
		}

		public function validate_required_field($name, $message)
		{
			if (trim((string) $this->get_field_value($name)) === '') {
				$this->mark_error($name, $message);
			}
		}

		public function check_field_error($name)
		{
			return isset($this->errors[$name]);
		}

		public function mark_error($name, $message)
		{
			$this->errors[$name] = $message;
		}
	};

	pg_auto_dialog_form_validate($liveform, null);

	return isset($liveform->errors['url']) ? $liveform->errors['url'] : '';
}

function test_auto_dialog_form_url_must_be_a_web_address_or_a_site_path()
{
	foreach (array('https://example.com/newsletter', 'http://example.com', '/newsletter', 'newsletter?popup=1') as $url) {
		pg_assert_same('', auto_dialog_form_test_validate($url), $url . ' is saved');
		pg_assert_same('', pg_auto_dialog_url_problem($url), $url . ' is a web address or a path');
	}

	foreach (array('javascript:alert(1)', 'JavaScript:void(0)', "java\tscript:alert(1)", 'data:text/html,x', '//example.com/embed', '/\\example.com') as $url) {
		pg_assert_true(auto_dialog_form_test_validate($url) !== '', json_encode($url) . ' is refused on the URL field');
		pg_assert_same(pg_web_address_problem($url), pg_auto_dialog_url_problem($url), json_encode($url) . ': the short link destination\'s rule');
	}

	pg_assert_same(lang(array('string' => '{var:1} is required.', 'vars' => lang('URL'))), auto_dialog_form_test_validate(''), 'a blank URL keeps its own message');
	pg_assert_same(lang(array('string' => '{var:1} can be at most {var:2} characters long.', 'vars' => array(lang('URL'), 255))), auto_dialog_form_test_validate('javascript:' . str_repeat('x', 300)), 'a URL over the column keeps the length message');
}

function test_auto_dialog_page_leaves_out_an_address_that_runs_script()
{
	$rows = array(
		array('id' => '1', 'url' => '/newsletter'),
		array('id' => '2', 'url' => 'javascript:alert(1)'),
		array('id' => '3', 'url' => 'https://example.com/form'),
		array('id' => '4', 'url' => " java\tscript:alert(1)"),
		array('id' => '5', 'url' => 'data:text/html,x'),
		array('id' => '6', 'url' => '//example.com/embed'),
	);

	pg_assert_same(array('1', '3', '6'), array_column(pg_auto_dialogs_for_page($rows), 'id'), 'web addresses and paths stay');
	pg_assert_same(array(0, 1, 2), array_keys(pg_auto_dialogs_for_page($rows)), 'a JSON list, not an object');
	pg_assert_same(array(), pg_auto_dialogs_for_page(array()), 'no dialogs');
	pg_assert_same(array(), pg_auto_dialogs_for_page(false), 'a failed query');
}

function test_auto_dialog_page_and_api_use_the_address_checks()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$start = strpos($page, 'if (AUTO_DIALOGS) {');
	$block = substr($page, $start, strpos($page, '// If there is JS to output', $start) - $start);

	pg_assert_same(2, substr_count($block, '$auto_dialogs = pg_auto_dialogs_for_page($auto_dialogs);'), 'the preview and the enabled dialogs both pass the guard');
	pg_assert_same(2, substr_count($block, 'software.init_auto_dialogs('), 'both lists reach the page');

	foreach (array('$auto_dialogs = pg_auto_dialogs_for_page($auto_dialogs);' => 'software.init_auto_dialogs(') as $guard => $print) {
		$at = 0;

		while (($at = strpos($block, $print, $at + 1)) !== false) {
			$before = substr($block, 0, $at);
			pg_assert_true(strrpos($before, $guard) > strrpos($before, 'FROM auto_dialogs'), 'the rows are guarded between the query and the print');
		}
	}

	$api = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/api/resources/design_dialogs_regions.php');
	$prepare = substr($api, strpos($api, 'function api_design_auto_dialog_prepare('), 3000);

	pg_assert_contains("pg_auto_dialog_url_problem(\$values['url'])", $prepare, 'the API asks the screen\'s address check');
	pg_assert_contains("api_fail_validation(\$problem, 'url')", $prepare, 'a refused address is 422 on url');
	pg_assert_contains("((\$row === null) || array_key_exists('url', \$params))", $prepare, 'on a create and whenever a URL is sent');
}
