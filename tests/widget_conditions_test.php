<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the value conditions of system widget nodes
 * (includes/fn/widgets_conditions.php): the operators, how a value is read
 * as text and as a number (amounts as the site writes them), the case fold,
 * and which conditions are valid. The shared vectors in
 * tests/fixtures/widget_condition_vectors.json are the contract the
 * editor's twin of pg_sw_cond_eval() is held to as well.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _widget_conditions_vectors()
{
	$rows = json_decode((string) file_get_contents(dirname(__FILE__) . '/fixtures/widget_condition_vectors.json'), true);
	return is_array($rows) ? $rows : array();
}

// Every shared vector gives its expected answer.
function test_widget_conditions_vectors()
{
	$rows = _widget_conditions_vectors();
	pg_assert_true(count($rows) >= 60, 'the vectors file holds its rows');

	foreach ($rows as $i => $row) {
		pg_assert_same($row['expect'], pg_sw_cond_eval($row['op'], $row['value'], $row['operand']),
			'#' . $i . ' ' . $row['op'] . ' ' . json_encode($row['value'], JSON_UNESCAPED_UNICODE) . ' ' . json_encode($row['operand'], JSON_UNESCAPED_UNICODE));
	}
}

// The vectors leave out a single separator with three digits after it:
// the site language decides those, and the editor's twin cannot know it.
function test_widget_conditions_vectors_language_free()
{
	$ops = pg_sw_cond_ops();
	foreach (_widget_conditions_vectors() as $i => $row) {
		foreach (array($row['value'], $row['operand']) as $text) {
			$language_bound = (preg_match_all('/[.,]/', (string) $text) === 1)
				&& preg_match('/[.,][0-9]{3}(?![0-9])/', (string) $text);
			pg_assert_false((bool) $language_bound, '#' . $i . ' ' . json_encode($text, JSON_UNESCAPED_UNICODE));
		}
		pg_assert_true(isset($ops[$row['op']]) || ($row['op'] === 'bogus'), '#' . $i . ' names a known operator');
	}
}

// The operators, in the editor's order, with what each needs.
function test_widget_conditions_ops()
{
	pg_assert_same(
		array('not_empty', 'empty', 'eq', 'neq', 'contains', 'not_contains', 'starts', 'gt', 'gte', 'lt', 'lte', 'multiple_of'),
		array_keys(pg_sw_cond_ops()));
	$ops = pg_sw_cond_ops();
	pg_assert_same(array('operand' => false, 'numeric' => false), $ops['not_empty']);
	pg_assert_same(array('operand' => true, 'numeric' => false), $ops['eq']);
	pg_assert_same(array('operand' => true, 'numeric' => true), $ops['multiple_of']);
}

// Both ways of grouping a figure read the same number.
function test_widget_conditions_turkish_and_english_figures()
{
	pg_assert_same(1234.56, pg_sw_cond_number('1.234,56'), 'Turkish');
	pg_assert_same(1234.56, pg_sw_cond_number('1,234.56'), 'English');
	pg_assert_same(1234567.0, pg_sw_cond_number('1.234.567'), 'thousands only');
	pg_assert_true(pg_sw_cond_eval('eq', '1.234,56', '1,234.56'));
}

// A single separator with three digits follows the site language, as the
// amount reader does everywhere.
function test_widget_conditions_three_digits_follow_the_site()
{
	pg_assert_same(pg_parse_amount('1.500'), pg_sw_cond_number('1.500'));
	pg_assert_same(pg_parse_amount('1,500'), pg_sw_cond_number('1,500'));
}

// A zero price shows something, but it is not above zero.
function test_widget_conditions_zero_price()
{
	pg_assert_true(pg_sw_cond_eval('not_empty', '₺0,00', ''));
	pg_assert_false(pg_sw_cond_eval('gt', '₺0,00', '0'));
	pg_assert_true(pg_sw_cond_eval('eq', '₺0,00', '0'));
}

// The amounts the site writes: the minus before the symbol, the code after
// the figure, a percentage.
function test_widget_conditions_money_formats()
{
	pg_assert_same(-12.5, pg_sw_cond_number('-₺12,50'));
	pg_assert_true(pg_sw_cond_eval('lt', '-₺12,50', '0'));
	pg_assert_same(12.5, pg_sw_cond_number('€12,50 EUR'));
	pg_assert_true(pg_sw_cond_eval('eq', '€12,50 EUR', '12.5'));
	pg_assert_same(10.0, pg_sw_cond_number('10%'));
	pg_assert_true(pg_sw_cond_eval('eq', '10%', '10'));
	pg_assert_same(12.0, pg_sw_cond_number('US$12'));
	pg_assert_same(12.5, pg_sw_cond_number('12,50 TL'));
	pg_assert_same(12.5, pg_sw_cond_number('₺ 12,50'));
	pg_assert_same(12.5, pg_sw_cond_number('TRY 12,50'));
	pg_assert_same(12.0, pg_sw_cond_number('12 USD'));
	pg_assert_same(1234567.0, pg_sw_cond_number('1 234 567'));
	pg_assert_same(1234567.89, pg_sw_cond_number('1.234.567,89'));
}

// Only a currency sign, an all-capital three-letter code, TL or a code
// written with a dollar sign stands beside a figure; a word does not.
function test_widget_conditions_words_are_not_codes()
{
	foreach (array('Kat 2', 'Oda 2', 'No 5', 'Room 2', '12 tl', 'try 12', 'Kapı 3', '5 adet', 'Ab$5') as $text) {
		pg_assert_same(null, pg_sw_cond_number($text), json_encode($text, JSON_UNESCAPED_UNICODE));
	}
	pg_assert_false(pg_sw_cond_eval('eq', 'Kat 2', 'Oda 2'));
	pg_assert_true(pg_sw_cond_eval('neq', 'Kat 2', 'Oda 2'));
	pg_assert_false(pg_sw_cond_eval('eq', 'No 5', '5'));
}

// A figure that repeats a separator is grouped in threes or it is no number:
// a dotted date is text, so eq compares it as written and gt, lt are false.
function test_widget_conditions_dotted_dates_are_text()
{
	foreach (array('24.10.2026', '1.11.2026', '01.01.2026', '11.1.2026', '2026.10.24', '1.2,5', '2 5', '1,2,3', '1 234.567,89') as $text) {
		pg_assert_same(null, pg_sw_cond_number($text), $text);
	}
	pg_assert_false(pg_sw_cond_eval('eq', '1.11.2026', '11.1.2026'));
	pg_assert_true(pg_sw_cond_eval('eq', '24.10.2026', '24.10.2026'));
	pg_assert_false(pg_sw_cond_eval('gt', '15.03.2025', '01.01.2026'));
	pg_assert_false(pg_sw_cond_eval('lt', '15.03.2025', '01.01.2026'));
	pg_assert_same(1.1, pg_sw_cond_number('1.10'), 'one separator is a decimal point');
}

// White space is one explicit set, the same in the editor's twin: U+FEFF
// and U+0085 are spaces, a zero-width space is not.
function test_widget_conditions_white_space_set()
{
	pg_assert_same('a b c', pg_sw_cond_plain("\u{FEFF}a\u{85}b\u{2028}\u{3000}c\u{FEFF}"));
	pg_assert_same("a\u{200B}b", pg_sw_cond_plain("a\u{200B}b"));
	pg_assert_true(pg_sw_cond_eval('empty', "\u{FEFF}", ''));
}

// Text is not a number.
function test_widget_conditions_not_numbers()
{
	foreach (array('', 'abc', 'Room 12', '12 apples', '1e5', '%10', '--5', '₺', '٣') as $text) {
		pg_assert_same(null, pg_sw_cond_number($text), json_encode($text, JSON_UNESCAPED_UNICODE));
		pg_assert_false(pg_sw_cond_is_number($text), json_encode($text, JSON_UNESCAPED_UNICODE));
	}
	pg_assert_true(pg_sw_cond_is_number('0'));
}

// The carrier's markup and entities become the text a visitor reads.
function test_widget_conditions_text_of_markup()
{
	pg_assert_same('A & B', pg_sw_cond_text('<b>A &amp; B</b>'));
	pg_assert_same('Hall A', pg_sw_cond_text("&nbsp; Hall\xC2\xA0\n  A "));
	pg_assert_same('', pg_sw_cond_text('<span class="x"></span>&nbsp;'));
	pg_assert_same('x', pg_sw_cond_text('<!--[if gte mso 9]><xml></xml><![endif]-->x'));
	pg_assert_same('€12,50 EUR', pg_sw_cond_text('&euro;12,50&nbsp;EUR'));
	pg_assert_true(pg_sw_cond_eval('empty', pg_sw_cond_text('&nbsp;<br>'), ''));
	pg_assert_true(pg_sw_cond_eval('eq', pg_sw_cond_text('&#8378;12,50'), '12.5'));
}

// A capital İ folds to i, as its small form loses the dot above.
function test_widget_conditions_fold()
{
	pg_assert_same('istanbul', pg_sw_cond_fold('İstanbul'));
	pg_assert_same('istanbul', pg_sw_cond_fold('ISTANBUL'));
	pg_assert_true(pg_sw_cond_eval('eq', 'İSTANBUL', 'istanbul'));
	pg_assert_true(pg_sw_cond_eval('contains', 'Büyük İndirim', 'indirim'));
	pg_assert_same('irmak', pg_sw_cond_fold('ırmak'), 'a dotless ı folds to i');
	pg_assert_true(pg_sw_cond_eval('eq', 'IRMAK', 'ırmak'));
}

// Comparing text with gt or lt is false whichever way.
function test_widget_conditions_order_needs_numbers()
{
	foreach (array('gt', 'gte', 'lt', 'lte') as $op) {
		pg_assert_false(pg_sw_cond_eval($op, 'b', 'a'), $op . ' text');
		pg_assert_false(pg_sw_cond_eval($op, '5', 'a'), $op . ' text operand');
		pg_assert_false(pg_sw_cond_eval($op, '', '5'), $op . ' empty');
	}
}

// multiple_of: whole numbers, an operand above zero.
function test_widget_conditions_multiple_of()
{
	pg_assert_false(pg_sw_cond_eval('multiple_of', '10', '0'), 'by zero');
	pg_assert_false(pg_sw_cond_eval('multiple_of', '0', '0'), 'zero by zero');
	pg_assert_false(pg_sw_cond_eval('multiple_of', '7,5', '2,5'), 'decimals');
	pg_assert_false(pg_sw_cond_eval('multiple_of', '10', '2,5'), 'decimal operand');
	pg_assert_true(pg_sw_cond_eval('multiple_of', '9', '3'));
	pg_assert_false(pg_sw_cond_eval('multiple_of', '9', '2'));
}

// The condition read from a node's props.
function test_widget_conditions_spec()
{
	pg_assert_same(null, pg_sw_cond_spec(array('text' => 'x')), 'no bindings');
	pg_assert_same(null, pg_sw_cond_spec(array('_bindings' => array('text' => 'title'))), 'no show_if');
	pg_assert_same(null, pg_sw_cond_spec(array('_bindings' => array('show_if' => ''))), 'an empty binding');

	pg_assert_same(array('token' => '__discount_percent', 'op' => 'not_empty', 'operand' => ''),
		pg_sw_cond_spec(array('_bindings' => array('show_if' => '__discount_percent'))), 'no params: not_empty');
	pg_assert_same(array('token' => '__discount_percent', 'op' => 'not_empty', 'operand' => ''),
		pg_sw_cond_spec(array('_bindings' => array('show_if' => '__discount_percent'), '_bindParams' => array('show_if' => array()))), 'no op: not_empty');
	pg_assert_same(array('token' => '__price', 'op' => 'gt', 'operand' => '0'),
		pg_sw_cond_spec(array('_bindings' => array('show_if' => '__price'), '_bindParams' => array('show_if' => array('op' => 'gt', 'value' => ' 0 ')))), 'gt 0');
	pg_assert_same(array('token' => 'publish-date', 'op' => 'empty', 'operand' => ''),
		pg_sw_cond_spec(array('_bindings' => array('show_if' => 'publish-date"><'), '_bindParams' => array('show_if' => array('op' => 'empty', 'value' => 'ignored')))), 'sanitised like a binding, no operand kept');
	pg_assert_same(array('token' => '__qty', 'op' => 'eq', 'operand' => '0'),
		pg_sw_cond_spec(array('_bindings' => array('show_if' => '__qty'), '_bindParams' => array('show_if' => array('op' => 'eq', 'value' => 0)))), 'a numeric operand');

	pg_assert_false(pg_sw_cond_spec(array('_bindings' => array('show_if' => '__price'), '_bindParams' => array('show_if' => array('op' => 'between')))), 'unknown op');
	pg_assert_false(pg_sw_cond_spec(array('_bindings' => array('show_if' => '__price'), '_bindParams' => array('show_if' => array('op' => array('gt'))))), 'op not text');
	pg_assert_false(pg_sw_cond_spec(array('_bindings' => array('show_if' => '__price'), '_bindParams' => array('show_if' => array('op' => 'gt')))), 'gt without operand');
	pg_assert_false(pg_sw_cond_spec(array('_bindings' => array('show_if' => '__price'), '_bindParams' => array('show_if' => array('op' => 'eq', 'value' => '  ')))), 'blank operand');
	pg_assert_false(pg_sw_cond_spec(array('_bindings' => array('show_if' => '"<>'))), 'nothing left after sanitising');
	pg_assert_false(pg_sw_cond_spec(array('_bindings' => array('show_if' => array('x')))), 'not text');
}

// Tokens a renderer looks for, builds as a block or rewrites per node.
function test_widget_conditions_denied_tokens()
{
	foreach (array('__variant_picker', '__filter_chips', '__filters_button', '__filters_offcanvas', '__clear_filters_button',
		'__edit_url', '__comment_edit_url', '__empty_message', '__page_link', '__page_link_0', '__link_label', '__link_label_3',
		'__item_form_data_html', '__breadcrumb_html', '__price_block_html', '__comment_message_html', '__EDIT_URL') as $token) {
		pg_assert_false(pg_sw_cond_token_allowed($token), $token);
		pg_assert_false(pg_sw_cond_spec(array('_bindings' => array('show_if' => $token))), 'spec ' . $token);
	}
	// The names a renderer looks for in the saved tree stay allowed: it reads
	// the tree through pg_sw_cond_tree_json().
	foreach (array('__discount_percent', '__event_location', '__price_formatted', 'title', 'publish-date', '__page_links', '__link_label_x',
		'__legal_preinfo_url', '__legal_contract_url', '__withdrawal_deadline', '__return_form', '__returns', '__not_found_message') as $token) {
		pg_assert_true(pg_sw_cond_token_allowed($token), $token);
	}
	foreach (pg_sw_cond_denied_tokens() as $key => $token) {
		pg_assert_true(is_int($key) && is_string($token), 'a plain list: ' . $token);
	}
}

// A condition names a token without placing it: the tree a renderer
// searches for a placed name has every condition taken out, at any depth.
function test_widget_conditions_tree_json_without_conditions()
{
	$tree = array('type' => 'root', 'props' => array(), 'children' => array(
		array('type' => 'semantic', 'props' => array('tag' => 'h5', 'text' => 'Legal documents',
			'_bindings' => array('show_if' => '__legal_preinfo_url'),
			'_bindParams' => array('show_if' => array('op' => 'contains', 'value' => '__returns'), 'href' => array('page_id' => 4))),
			'children' => array(
				array('type' => 'content', 'props' => array('_bindings' => array('text' => '__order_no', 'show_if' => '__not_found_message')), 'children' => array()),
				array('type' => 'semantic', 'props' => array('_bindings' => array('eo_visible_if' => 'has_items', 'show_if' => 'not_logged_in')), 'children' => array()),
			)),
	));
	$json = pg_sw_cond_tree_json($tree);
	foreach (array('__legal_preinfo_url', '__returns', '__not_found_message', 'not_logged_in', 'show_if') as $name) {
		pg_assert_same(false, strpos($json, $name), $name);
	}
	pg_assert_contains('"has_items"', $json, 'a placed flag stays');
	pg_assert_contains('__order_no', $json, 'a placed token stays');
	pg_assert_contains('"page_id":4', $json, 'other parameters stay');

	$encoded = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	pg_assert_same($json, pg_sw_cond_tree_json($encoded), 'the saved JSON reads the same');
	$plain = '{"type":"root","props":{"_bindings":{"text":"__legal_preinfo_url"}},"children":[]}';
	pg_assert_same($plain, pg_sw_cond_tree_json($plain), 'JSON without a condition is returned as it is');
	pg_assert_same('not json show_if', pg_sw_cond_tree_json('not json show_if'));
}

// The order view, the cart and the account widget ask whether the design
// placed a name through pg_sw_cond_tree_json(), never through the raw tree.
function test_widget_conditions_placement_checks_skip_conditions()
{
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	$cart    = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_cart.php');
	pg_assert_contains("strpos(pg_sw_cond_tree_json(\$tree_decoded), 'not_logged_in')", $widgets);
	pg_assert_contains("strpos(pg_sw_cond_tree_json(\$tree_decoded), '__not_found_message')", $widgets);
	pg_assert_contains('$placed_json = pg_sw_cond_tree_json((string)$tree_json);', $widgets);
	foreach (array('__legal_preinfo_url', '__legal_contract_url', '__withdrawal_deadline', '__return_form', '__returns') as $name) {
		pg_assert_contains("strpos(\$placed_json, '" . $name . "')", $widgets, $name);
	}
	pg_assert_contains("strpos(pg_sw_cond_tree_json((string)\$tree_json), '\"has_items\"')", $cart);
	foreach (array($widgets, $cart) as $i => $source) {
		pg_assert_same(0, preg_match("/strpos\\(\\(string\\)(\\\$tree_json|json_encode\\(\\\$tree_decoded\\)), '(__legal|__withdrawal|__return|__not_found|not_logged_in|\"has_items)/", $source), 'no raw check left in file ' . $i);
	}
}

// The module carries no quoted '__token' map keys: tools/check_bindings.php
// would count each one as a token a renderer fills.
function test_widget_conditions_no_token_map_keys()
{
	foreach (array('widgets_conditions.php', 'widgets_finish.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/' . $file);
		pg_assert_same(0, preg_match("/'(__[a-z0-9_]+)'\s*=>/", $source), $file);
		pg_assert_same(0, preg_match("/\\\$[A-Za-z_][A-Za-z0-9_]*\['(__[a-z0-9_]+)'\]\s*=[^=>]/", $source), $file);
	}
}
