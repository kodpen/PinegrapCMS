<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Menus list's quick edit (includes/fn/menu_list_edit.php):
 * every value the menu screen changes is a field, the value each field
 * opens with, and the form values the menu screen's check gets for a
 * change.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/menu_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $menu_edit_test_name => $menu_edit_test_value) {
	if (!defined($menu_edit_test_name)) {
		define($menu_edit_test_name, $menu_edit_test_value);
	}
}

function pg_test_menu_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '7', 'name' => 'main-menu', 'effect' => 'Pop-up', 'first_level_popup_position' => 'Bottom',
		'second_level_popup_position' => 'Right', 'class' => 'nav big', 'active_item_class' => 'on',
		'created_username' => 'admin', 'created_timestamp' => '1700000000',
		'last_modified_username' => 'admin', 'last_modified_timestamp' => '1700000000',
	), $over);
}

// Every value the menu screen changes is an editable column of the list
// with a cell field; the rest are computed. A new column fails here until
// it is one or the other.
function test_menu_quick_every_screen_value_is_a_field()
{
	$computed = array('created', 'last_modified');
	$fields = pg_menu_quick_edit_columns();
	$cells = pg_menu_list_cells(pg_test_menu_quick_row(), $fields);

	foreach (pg_menu_list_columns(true) as $key => $column) {
		if (in_array($key, $computed, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is computed');
			continue;
		}

		pg_assert_true(isset($column['editable'], $fields[$key]), $key . ' is a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell carries its field');
	}

	foreach (array_keys($fields) as $field) {
		pg_assert_true(isset(pg_menu_list_columns(true)[$field]), $field . ' has a column');
	}

	// The screen's save writes these, in this order.
	pg_assert_same(array('name', 'effect', 'first_level_popup_position', 'second_level_popup_position', 'class', 'active_item_class'), array_keys($fields));

	foreach (pg_menu_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}
}

function test_menu_quick_cells_without_edit_have_no_field()
{
	foreach (pg_menu_list_cells(pg_test_menu_quick_row(), array()) as $key => $cell) {
		pg_assert_false(isset($cell['attributes']['data-pg-edit']), $key . ' is text');
	}
}

function test_menu_quick_selects_offer_the_screen_choices()
{
	$fields = pg_menu_quick_edit_columns();

	pg_assert_same(array('Pop-up', 'Accordion', ''), array_map('strval', array_keys($fields['effect']['options'])));
	pg_assert_same(array('Top', 'Bottom', 'Left', 'Right'), array_keys($fields['first_level_popup_position']['options']));
	pg_assert_same(array('Top', 'Bottom', 'Left', 'Right'), array_keys($fields['second_level_popup_position']['options']));

	// None is the empty effect, which the driver's select takes.
	$parsed = pg_list_edit_parse($fields['effect'], '');
	pg_assert_true($parsed['ok']);
	pg_assert_same('', $parsed['value']);
	pg_assert_false(pg_list_edit_parse($fields['effect'], 'Slide')['ok']);
	pg_assert_false(pg_list_edit_parse($fields['first_level_popup_position'], 'Middle')['ok']);
}

function test_menu_quick_value_and_cells()
{
	$row = pg_test_menu_quick_row();
	$cells = pg_menu_list_cells($row, pg_menu_quick_edit_columns(), '/pinegrap/view_menus.php');

	pg_assert_same('main-menu', pg_menu_quick_value('name', $row));
	pg_assert_same('Pop-up', pg_menu_quick_value('effect', $row));
	pg_assert_same('', pg_menu_quick_value('effect', pg_test_menu_quick_row(array('effect' => ''))));
	pg_assert_same('nav big', $cells['class']['attributes']['data-pg-edit-value']);

	// The name opens the menu's items, coming back to the list.
	pg_assert_contains('view_menu_items.php?id=7', $cells['name']['html']);
	pg_assert_contains(h(urlencode('/pinegrap/view_menus.php')), $cells['name']['html']);

	// The effect and the positions show their labels; the positions are
	// muted for an effect other than Pop-up.
	pg_assert_same(h(lang('Bottom')), $cells['first_level_popup_position']['html']);
	pg_assert_false(strpos($cells['first_level_popup_position']['class'], 'fg-secondary') !== false);
	pg_assert_contains('fg-secondary', pg_menu_list_cells(pg_test_menu_quick_row(array('effect' => 'Accordion')), array())['second_level_popup_position']['class']);
	pg_assert_same(h(lang('None')), pg_menu_list_cells(pg_test_menu_quick_row(array('effect' => '')), array())['effect']['html']);

	// Stored text is escaped.
	pg_assert_same('a&lt;b', pg_menu_list_cells(pg_test_menu_quick_row(array('class' => 'a<b')), array())['class']['html']);
}

function test_menu_quick_form_values_change_one_field()
{
	$row = pg_test_menu_quick_row();
	$values = pg_menu_quick_form_values($row, 'effect', 'Accordion');

	pg_assert_same('Accordion', $values['effect']);
	pg_assert_same('main-menu', $values['name']);
	pg_assert_same('Bottom', $values['first_level_popup_position']);
	pg_assert_same('Right', $values['second_level_popup_position']);
	pg_assert_same('nav big', $values['class']);
	pg_assert_same('on', $values['active_item_class']);

	// Classes reach the check as the chips post them: one space, each once.
	pg_assert_same('a b c', pg_menu_quick_form_values($row, 'class', '  a   b a  c ')['class']);
	pg_assert_same('', pg_menu_quick_form_values($row, 'active_item_class', '   ')['active_item_class']);

	// An unknown field changes nothing.
	pg_assert_same(pg_menu_quick_form_values($row, 'name', 'main-menu'), pg_menu_quick_form_values($row, 'created', 'x'));
}

function test_menu_quick_saved_draws_the_whole_row()
{
	$fields = pg_menu_quick_edit_columns();
	$answer = pg_menu_quick_saved(pg_menu_list_cells(pg_test_menu_quick_row(array('effect' => 'Accordion')), $fields), 'effect');

	pg_assert_true($answer['ok']);
	pg_assert_same('Accordion', $answer['value']);
	pg_assert_contains('fg-secondary', $answer['cells']['first_level_popup_position']['class']);
	pg_assert_same('Bottom', $answer['cells']['first_level_popup_position']['value']);
	pg_assert_true(isset($answer['cells']['last_modified']));
	pg_assert_false(isset($answer['cells']['effect']));
}
