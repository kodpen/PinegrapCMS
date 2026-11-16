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
		pg_assert_contains('pg_auto_dialog_save(' . (($id === 'null') ? '0' : '$id') . ', pg_auto_dialog_form_values($liveform), USER_ID, pg_auto_dialog_form_open_values($liveform))', $source, $file . ' saves the shared values and the open settings through pg_auto_dialog_save()');
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

	// The shared check cell: a mark with role img and the label Yes, sorted by 1/0,
	// carrying on or off for the toolbar's Enabled / Disabled filter.
	pg_assert_contains("'enabled' => pg_auto_dialog_list_enabled_cell(!empty(\$auto_dialog['enabled']))", $screen, 'an enabled dialog reads Yes');
	pg_assert_contains('$cell = pg_list_table_check_cell($on);', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/auto_dialogs.php'), 'on the shared check cell');
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

function auto_dialog_test_lacks($needle, $haystack, $label)
{
	pg_assert_true(strpos($haystack, $needle) === false, $label);
}

function test_auto_dialog_page_rows_are_the_same_for_every_visitor()
{
	$rows = array(
		array('id' => '2', 'url' => '/ebook', 'width' => '400', 'height' => '650', 'delay' => '15', 'frequency' => '24', 'page' => ''),
		array('id' => '3', 'url' => '/ticket', 'width' => '0', 'height' => '0', 'delay' => '0', 'frequency' => '0', 'page' => 'Shop'),
		array('id' => '4', 'url' => '/sayfa', 'width' => '1', 'height' => '1', 'delay' => '1', 'frequency' => '1', 'page' => 'ürün'),
		'not a row',
	);

	$home = pg_auto_dialogs_on_page($rows, 'home');
	pg_assert_same(array(2), array_column($home, 'id'), 'a row without a page belongs everywhere');
	pg_assert_same(array('id' => 2, 'url' => '/ebook', 'width' => 400, 'height' => 650, 'delay' => 15, 'frequency' => 24, 'open_on' => 'delay', 'open_scroll' => 50, 'audience' => 'all', 'place' => 'centre'), $home[0], 'the values the script needs, without the page; a row stored before the open settings opens as before');
	pg_assert_same(array(2, 3), array_column(pg_auto_dialogs_on_page($rows, 'online-shop-sale'), 'id'), 'a page that is part of the name, case-blind');
	pg_assert_same(array(2, 4), array_column(pg_auto_dialogs_on_page($rows, 'ÜRÜN-listesi'), 'id'), 'multibyte page names');
	pg_assert_same(array(), pg_auto_dialogs_on_page(false, 'home'), 'a failed query');

	$saved = $_COOKIE;
	$_COOKIE['software']['auto_dialog_2'] = (string) time();
	pg_assert_same($home, pg_auto_dialogs_on_page($rows, 'home'), 'a dialog already shown is still printed: the script reads the cookie');
	$_COOKIE = $saved;
}

function test_auto_dialog_page_keeps_nothing_in_the_session()
{
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_page_content.php');
	$start = strpos($page, 'if (AUTO_DIALOGS) {');
	$block = substr($page, $start, strpos($page, '// If there is JS to output', $start) - $start);

	auto_dialog_test_lacks('visit_start_timestamp', $block, 'no visit start in the session');
	auto_dialog_test_lacks('$_SESSION', $block, 'nothing in the session');
	auto_dialog_test_lacks('$_COOKIE', $block, 'the cookie is read by the script');
	pg_assert_contains('$auto_dialogs_page = pg_auto_dialogs_on_page($auto_dialogs, $page_name, $auto_dialogs_cart);', $block, 'the page prints the rows for the page');
	pg_assert_contains('encode_json($auto_dialogs_page)', $block, 'and only those');
	pg_assert_contains('$auto_dialogs_cart = pg_auto_dialogs_need_cart($auto_dialogs) && pg_auto_dialog_session_has_cart();', $block, 'the cart is asked only while a row is for visitors with a cart');
	pg_assert_contains("pg_page_cache_personal('popup_cart');", $block, 'a page that prints a cart row is drawn for this visitor');
	auto_dialog_test_lacks('visit_start_timestamp', $page, 'no other place writes it');
}

function test_auto_dialog_script_picks_in_the_browser_and_opens_the_browsers_dialog()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	$from = strpos($src, 'init_auto_dialogs: function(');
	$init = substr($src, $from, strpos($src, '// Used by express order', $from) - $from);
	$open = substr($src, strpos($src, 'open_dialog: function('), 9000);
	$open = substr($open, 0, strpos($open, "\n    },\n"));

	pg_assert_contains("sessionStorage.getItem('pg_visit_start')", $init, 'the delay counts from the visit\'s first page');
	pg_assert_contains("'software[auto_dialog_' + id + ']'", $init, 'the old per-row cookie still gives a dialog\'s last time');
	pg_assert_contains('>= (frequency * 3600)', $init, 'a recurring dialog opens again after its hours');
	pg_assert_contains("disable_auto_dialog_' + auto_dialog.id", $init, 'the body class of one dialog');
	pg_assert_contains("'disable_auto_dialogs'", $init, 'the body class of all dialogs');
	auto_dialog_test_lacks('expires=Tue, 01 Jan 2030', $init, 'the per-row cookie is no longer written');

	pg_assert_contains("document.createElement('dialog')", $open, 'the browser\'s own dialog');
	pg_assert_contains('dialog.showModal();', $open, 'centred and modal by default');
	pg_assert_contains('dialog.show();', $open, 'modal false keeps the page usable');
	pg_assert_contains("addEventListener('pg:consent'", $open, 'it waits while the cookie notice asks');
	auto_dialog_test_lacks('.dialog(', $open, 'no jQuery UI');
	auto_dialog_test_lacks('ui-', $open, 'no ui- classes');
	pg_assert_contains('dialog_style: function(', $src, 'the frame\'s rules');

	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');
	pg_assert_contains('sessionStorage.getItem("pg_visit_start")', $min, 'the served script picks in the browser');
	pg_assert_contains('createElement("dialog")', $min, 'the served script opens the browser\'s dialog');
	pg_assert_contains('"pg-dialog-style"', $min, 'the served script has the frame\'s rules');
}

function test_auto_dialog_open_settings_read_as_today_when_unset()
{
	$defaults = array('open_on' => 'delay', 'open_scroll' => 50, 'audience' => 'all', 'place' => 'centre');

	pg_assert_same($defaults, pg_auto_dialog_open_settings(array('id' => 2, 'delay' => 15)), 'a row stored before the columns');
	pg_assert_same($defaults, pg_auto_dialog_open_settings(array('open_on' => 'hover', 'open_scroll' => '0', 'audience' => 'members', 'place' => 'middle')), 'an unknown value reads as the default');
	pg_assert_same($defaults, pg_auto_dialog_open_settings(array('open_on' => array('scroll'), 'open_scroll' => '101')), 'a list or a share past 100');
	pg_assert_same(array('open_on' => 'scroll', 'open_scroll' => 30, 'audience' => 'cart', 'place' => 'corner'), pg_auto_dialog_open_settings(array('open_on' => ' Scroll ', 'open_scroll' => '30', 'audience' => 'cart', 'place' => 'corner')), 'the stored settings, trimmed and case-blind');
	pg_assert_same(array('delay', 'all', 'centre'), array(pg_auto_dialog_open_choices()['open_on'][0], pg_auto_dialog_open_choices()['audience'][0], pg_auto_dialog_open_choices()['place'][0]), 'each default is listed first, as the form offers it');
}

function test_auto_dialog_cart_audience_rule()
{
	pg_assert_true(pg_auto_dialog_audience_shows('all', false), 'all: a visitor without a cart');
	pg_assert_true(pg_auto_dialog_audience_shows('all', true), 'all: a visitor with a cart');
	pg_assert_false(pg_auto_dialog_audience_shows('cart', false), 'cart: not without a line in the cart');
	pg_assert_true(pg_auto_dialog_audience_shows('cart', true), 'cart: a visitor whose order holds a line');

	$rows = array(
		array('id' => '2', 'url' => '/ebook', 'page' => ''),
		array('id' => '5', 'url' => '/waiting', 'page' => '', 'audience' => 'cart', 'open_on' => 'leave', 'place' => 'bottom'),
	);

	pg_assert_same(array(2), array_column(pg_auto_dialogs_on_page($rows, 'home'), 'id'), 'a cart row is left out by default');
	pg_assert_same(array(2, 5), array_column(pg_auto_dialogs_on_page($rows, 'home', true), 'id'), 'and printed for a visitor with a cart');
	pg_assert_same(array('leave', 'bottom'), array(pg_auto_dialogs_on_page($rows, 'home', true)[1]['open_on'], pg_auto_dialogs_on_page($rows, 'home', true)[1]['place']), 'with its trigger and place');
	pg_assert_false(pg_auto_dialogs_need_cart(array($rows[0])), 'no cart row: the session is not asked');
	pg_assert_true(pg_auto_dialogs_need_cart($rows), 'a cart row: the session is asked');
}

function test_auto_dialog_form_open_values_and_fields()
{
	$liveform = new class {
		public $values = array('open_on' => 'scroll', 'open_scroll' => '075', 'audience' => '', 'place' => 'top');
		public function get_field_value($field) { return $this->values[$field] ?? ''; }
	};

	pg_assert_same(array('open_on' => 'scroll', 'open_scroll' => 75, 'audience' => 'all', 'place' => 'top'), pg_auto_dialog_form_open_values($liveform), 'the sent settings, a blank one as its default');
	pg_assert_same(array('open_on', 'open_scroll', 'audience', 'place'), array_keys(pg_auto_dialog_form_open_fields()), 'the four fields');

	foreach (pg_auto_dialog_form_open_fields() as $field => $rule) {
		if ($rule[1] !== null) {
			pg_assert_same(pg_auto_dialog_open_choices()[$field], array_values($rule[1]), $field . ' offers exactly the values the writer takes, the default first');
		}
	}

	$template = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_auto_dialog.php');
	pg_assert_contains('pg_auto_dialog_form_open_fields()', $template, 'the form draws the fields');
	pg_assert_contains('pg_auto_dialog_form_open_problems($liveform)', (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php'), 'the save checks them');
}

function test_auto_dialog_script_scroll_leave_and_place()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	$from = strpos($src, 'init_auto_dialogs: function(');
	$init = substr($src, $from, strpos($src, '// Used by express order', $from) - $from);

	pg_assert_contains("software.when_scrolled(parseInt(auto_dialog.open_scroll, 10) || 50, show);", $init, 'scroll waits for its share of the page');
	pg_assert_contains("software.when_leaving(show);", $init, 'leave waits for the pointer to leave');
	pg_assert_contains("if ((open_on === 'leave') && software.is_touch_screen()) {", $init, 'a touch screen falls back to the delay');
	pg_assert_contains("var open_on = preview ? 'delay' : auto_dialog.open_on;", $init, 'a preview opens at once');
	pg_assert_contains("setTimeout(show, delay * 1000);", $init, 'the delay as before');
	pg_assert_contains('dialog.modal = false;', $src, 'a bar or a corner card leaves the page usable (show())');
	pg_assert_contains("'dialog.pg-dialog-corner{", $src, 'the corner card\'s rule');

	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');
	foreach (array('show_auto_dialog:function(', 'when_scrolled:function(', 'when_leaving:function(', 'is_touch_screen:function(', 'dialog.pg-dialog-top{', 'software.when_scrolled(parseInt(a.open_scroll,10)||50,d)') as $needle) {
		pg_assert_contains($needle, $min, 'the served script: ' . $needle);
	}
}

function test_auto_dialogs_wait_for_the_cookie_notice_and_keep_one_cookie()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.src.js');
	$from = strpos($src, 'init_auto_dialogs: function(');
	$init = substr($src, $from, strpos($src, '// Used by express order', $from) - $from);

	pg_assert_contains("document.getElementById('pg-cc')", $init, 'it looks for the cookie notice');
	pg_assert_contains("document.addEventListener('pg:consent'", $init, 'with the notice on, the dialogs start on its answer');
	pg_assert_contains('if (preview || !notice) {', $init, 'with the notice off, or for a preview, they start at once');
	pg_assert_contains('!!(choices && choices.marketing)', $init, 'the cookie is kept only with marketing allowed');
	pg_assert_contains("var name = 'pg_popup';", $init, 'one cookie for all pop-ups');
	pg_assert_same(PG_POPUP_COOKIE, 'pg_popup', 'the script and the catalogue name the same cookie');
	pg_assert_contains('Max-Age=31536000', $init, 'kept for a year');
	pg_assert_contains("window.sessionStorage.setItem(name, parts.join('|'))", $init, 'without consent, sessionStorage for the visit');

	$min = (string) file_get_contents(PG_FUNCTIONS_DIR . '/frontend.min.js');
	pg_assert_contains('addEventListener("pg:consent"', $min, 'the served script waits for the notice');
	pg_assert_contains('"pg_popup"', $min, 'the served script keeps the one cookie');
	auto_dialog_test_lacks('expires=Tue, 01 Jan 2030', $min, 'the served script no longer writes the per-row cookie');
}

function test_auto_dialog_screens_in_look_a()
{
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_paging.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
	require_once(PG_FUNCTIONS_DIR . '/includes/fn/auto_dialog_counts.php');

	pg_assert_same(lang('Immediately'), pg_auto_dialog_opens_label(array('delay' => 0)), 'no delay opens at once');
	pg_assert_contains('15', pg_auto_dialog_opens_label(array('delay' => 15)), 'the delay in seconds');
	pg_assert_contains('60', pg_auto_dialog_opens_label(array('open_on' => 'scroll', 'open_scroll' => 60, 'delay' => 15)), 'the scroll share, not the delay');
	pg_assert_same(lang('When the visitor is leaving'), pg_auto_dialog_opens_label(array('open_on' => 'leave', 'delay' => 15)), 'leaving');
	pg_assert_same('400 &times; 650 px', pg_auto_dialog_size_label(400, 650), 'both sides');
	pg_assert_same(h(lang('Auto')) . ' &times; ' . h(lang('Auto')), pg_auto_dialog_size_label(0, 0), 'a pop-up sized by its content');
	pg_assert_same('on', pg_auto_dialog_list_enabled_cell(true)['attributes']['data-pg-filter'], 'an enabled row for the filter');
	pg_assert_same('off', pg_auto_dialog_list_enabled_cell(false)['attributes']['data-pg-filter'], 'a disabled row for the filter');
	pg_assert_same(array('shown' => 43, 'closed' => 31, 'taken' => 1), pg_auto_dialog_count_total(array(2 => array('shown' => 40, 'closed' => 30, 'taken' => 0), 5 => array('shown' => 3, 'closed' => 1, 'taken' => 1))), 'the figure row sums every pop-up');

	$list = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/view_auto_dialogs.php');
	pg_assert_false(strpos($list, '<h2') !== false, 'the list draws no title of its own: the header holds it with the count');
	pg_assert_contains('<div class="pg-figures"', $list, 'the figure row');
	pg_assert_contains("'search'  => array(\$output_filters,", $list, 'the segmented filter in the toolbar');
	pg_assert_contains("pg_list_seg_filters('view_auto_dialogs', 'enabled',", (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_auto_dialogs.php'), 'over the Enabled column');

	$form = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/templates/edit_auto_dialog.php');
	pg_assert_false(strpos($form, 'breadcrumb') !== false, 'the breadcrumb is the shell\'s');
	pg_assert_false(strpos($form, 'material-icons') !== false, 'bi icons on the save bar');
	pg_assert_contains('<div class="col-12 pg-field-span-6 my-3">', $form, 'the page name alone at 6');
	pg_assert_same(4, substr_count($form, '<div class="col-12 md:col-3 my-3">'), 'width, height, delay and frequency at 3');
	pg_assert_contains("<?=(\$open_field === 'open_scroll') ? 'md:col-3' : 'md:col-6'?>", $form, 'a select at 6, the scroll share at 3');
	pg_assert_contains('<div class="pg-figures mb-0" style="--pg-figures: 3;">', $form, 'the 30-day figures in the status column');

	foreach (array('add_auto_dialog.php', 'edit_auto_dialog.php') as $screen) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $screen);
		pg_assert_contains("'breadcrumb' => array(", $source, $screen . ' passes its breadcrumb to the shell');
		pg_assert_contains("'heading_description' =>", $source, $screen . ' and its description');
	}
}
