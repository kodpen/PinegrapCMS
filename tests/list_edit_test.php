<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for quick edit on list screens in includes/fn/output.php: how a
 * typed value is read for each kind of column (pg_list_edit_parse()), and
 * the attributes the table and its editable cells carry
 * (pg_list_edit_table(), pg_list_edit_cell()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function list_edit_columns()
{
	return array(
		'title' => array('type' => 'text', 'label' => 'Title', 'max' => 5),
		'note'  => array('type' => 'text', 'label' => 'Note', 'required' => true),
		'stock' => array('type' => 'integer', 'label' => 'Stock', 'min' => 0, 'max' => 100),
		'floor' => array('type' => 'integer', 'label' => 'Floor', 'min' => 0, 'max' => 9, 'empty' => true),
		'price' => array('type' => 'money', 'label' => 'Price'),
		'state' => array('type' => 'select', 'label' => 'State', 'options' => array('1' => 'On', '0' => 'Off')),
	);
}

function test_list_edit_text_is_trimmed_and_kept_on_one_line()
{
	$columns = list_edit_columns();

	$read = pg_list_edit_parse($columns['note'], "  Blue\r\nshirt \t");
	pg_assert_true($read['ok'], 'accepted');
	pg_assert_same('Blue shirt', $read['value'], 'line break and tab became one space, ends trimmed');
}

function test_list_edit_text_longer_than_its_column_is_refused()
{
	$columns = list_edit_columns();

	pg_assert_true(pg_list_edit_parse($columns['title'], 'Şüphe')['ok'], 'five letters fit, counted as characters, not bytes');
	pg_assert_false(pg_list_edit_parse($columns['title'], 'Şüpheli')['ok'], 'seven do not');
}

function test_list_edit_required_text_cannot_be_emptied()
{
	$columns = list_edit_columns();

	pg_assert_false(pg_list_edit_parse($columns['note'], '   ')['ok'], 'spaces only');
	pg_assert_same('', pg_list_edit_parse($columns['title'], '')['value'], 'a text that is not required can be emptied');
}

function test_list_edit_integer_reads_whole_numbers_inside_the_range()
{
	$columns = list_edit_columns();

	pg_assert_same(42, pg_list_edit_parse($columns['stock'], ' 42 ')['value'], 'trimmed and made an int');
	pg_assert_same(0, pg_list_edit_parse($columns['stock'], '0')['value'], 'the lowest');
	pg_assert_same(100, pg_list_edit_parse($columns['stock'], '100')['value'], 'the highest');

	foreach (array('-1', '101', '4.5', '4,5', '1e3', 'ten', '', '0x10') as $typed) {
		pg_assert_false(pg_list_edit_parse($columns['stock'], $typed)['ok'], $typed);
	}
}

function test_list_edit_integer_may_be_emptied_only_when_the_column_says_so()
{
	$columns = list_edit_columns();

	$read = pg_list_edit_parse($columns['floor'], '  ');
	pg_assert_true($read['ok'], 'empty allowed');
	pg_assert_same('', $read['value'], 'empty reads as an empty string, not 0');
	pg_assert_false(pg_list_edit_parse($columns['stock'], '')['ok'], 'empty refused when not allowed');
}

function test_list_edit_money_keeps_the_typed_figure_for_the_screen_to_read()
{
	$columns = list_edit_columns();

	pg_assert_same('12,50', pg_list_edit_parse($columns['price'], ' 12,50 ')['value'], 'decimal comma');
	pg_assert_same('1.234,56', pg_list_edit_parse($columns['price'], '1.234,56')['value'], 'grouped');
	pg_assert_same('1234.56', pg_list_edit_parse($columns['price'], "1\u{00A0}234.56")['value'], 'a no-break space taken out');
	pg_assert_same('0', pg_list_edit_parse($columns['price'], '0')['value'], 'zero');
}

function test_list_edit_money_refuses_anything_but_a_figure()
{
	$columns = list_edit_columns();

	// A minus sign or a symbol would otherwise be dropped by the reader and
	// the figure stored as typed without it.
	foreach (array('-5', '₺12', '12,', '.5', '12..5', 'abc', '') as $typed) {
		pg_assert_false(pg_list_edit_parse($columns['price'], $typed)['ok'], $typed);
	}
}

function test_list_edit_select_takes_only_an_offered_value()
{
	$columns = list_edit_columns();

	pg_assert_same('0', pg_list_edit_parse($columns['state'], '0')['value'], 'offered, read as a string');
	pg_assert_same('1', pg_list_edit_parse($columns['state'], '1')['value'], 'offered');
	pg_assert_false(pg_list_edit_parse($columns['state'], '2')['ok'], 'not offered');
	pg_assert_false(pg_list_edit_parse($columns['state'], '')['ok'], 'nothing picked');
}

function test_list_edit_unknown_type_and_non_scalar_values_are_refused()
{
	pg_assert_false(pg_list_edit_parse(array('type' => 'html', 'label' => 'X'), 'a')['ok'], 'unknown type');
	pg_assert_false(pg_list_edit_parse(array('type' => 'text', 'label' => 'X', 'required' => true), array('a'))['ok'], 'an array reads as empty');
	pg_assert_false(pg_list_edit_parse(array('type' => 'text', 'label' => 'X'), "\xC3\x28")['ok'], 'broken UTF-8');
}

function test_list_edit_table_carries_the_definitions_in_order()
{
	$attributes = pg_list_edit_table('view_products.php', list_edit_columns());

	pg_assert_contains(' data-pg-edit-url="view_products.php"', $attributes, 'address');
	pg_assert_true(preg_match('/data-pg-edit-columns="([^"]*)"/', $attributes, $match) === 1, 'definitions present');

	$client = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);

	pg_assert_same(array(array('1', 'On'), array('0', 'Off')), $client['state']['options'], 'options as pairs, in the screen\'s order');
	pg_assert_same(array('type' => 'integer', 'label' => 'Stock', 'min' => 0, 'max' => 100), $client['stock'], 'what the script needs, nothing more');
	pg_assert_same(false, isset($client['note']['required']), 'server-side rules stay on the server');
}

function test_list_edit_table_and_cell_are_plain_without_columns()
{
	pg_assert_same('', pg_list_edit_table('view_products.php', array()), 'no columns, no attributes');
	pg_assert_same('', pg_list_edit_cell(array(), 'stock', 5, '3'), 'field not offered');
	pg_assert_same('', pg_list_edit_cell(list_edit_columns(), 'stock', 0, '3'), 'no record');
}

function test_list_edit_cell_escapes_its_value()
{
	$cell = pg_list_edit_cell(list_edit_columns(), 'title', 7, '"><b>');

	pg_assert_contains(' data-pg-edit="title" data-pg-edit-id="7"', $cell, 'field and record');
	pg_assert_contains(' data-pg-edit-value="&quot;&gt;&lt;b&gt;"', $cell, 'value escaped');
	pg_assert_contains(' tabindex="0"', $cell, 'reachable by keyboard');
}
