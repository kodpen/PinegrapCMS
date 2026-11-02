<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tax zone form shared by add_tax_zone.php and
 * edit_tax_zone.php (includes/forms/tax_zone_form.php): the country and
 * state options, the posted link ids, the name field of each screen, both
 * screens drawing the one set of cards, a blank name refused with the form
 * drawn again, and the states listed under their country's shown name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/tax_zone_form.php');

function test_tax_zone_form_option_list_keeps_the_order()
{
	$items = array(
		array('id' => '1', 'label' => 'Austria'),
		array('id' => '2', 'label' => 'Belgium'),
		array('id' => '3', 'label' => 'Chile & Peru'),
	);

	pg_assert_same(
		'<option value="1">Austria</option><option value="2">Belgium</option><option value="3">Chile &amp; Peru</option>',
		pg_tax_zone_form_option_list($items, array()),
		'nothing chosen: the given order, labels escaped');

	pg_assert_same(
		'<option value="1">Austria</option><option value="2">Belgium</option><option value="3" selected="selected">Chile &amp; Peru</option>',
		pg_tax_zone_form_option_list($items, array(3)),
		'the chosen one selected in its place; ids compared as text');
}

function test_tax_zone_form_link_ids()
{
	pg_assert_same(array('4', '7'), pg_tax_zone_form_link_ids(array('4', '', '0', 7)), 'empty and zero values are dropped, the rest as text');
	pg_assert_same(array('2'), pg_tax_zone_form_link_ids(array(array('1'), '2')), 'a nested value is skipped');
	pg_assert_same(array(), pg_tax_zone_form_link_ids(null), 'nothing posted');
	pg_assert_same(array(), pg_tax_zone_form_link_ids('5'), 'not a list');
}

function test_tax_zone_form_cards_name_field()
{
	$create = pg_tax_zone_form_cards(null, '', '');
	pg_assert_contains('maxlength="50" value="" placeholder="Tax Zone Name" class="form-control" required />', $create, 'create shows a placeholder, the name required');
	pg_assert_contains('id="tax_rate" required class="form-control"', $create, 'create leaves the rate empty');

	$edit = pg_tax_zone_form_cards(array('name' => 'EU "VAT"', 'tax_rate' => '20'), '<option value="1">A</option>', '');
	pg_assert_contains('maxlength="50" value="EU &quot;VAT&quot;" placeholder="Tax Zone Name" class="form-control" required />', $edit, 'edit shows the stored name, escaped');
	pg_assert_contains('id="tax_rate" required value="20" class="form-control"', $edit, 'edit shows the stored rate');
	pg_assert_contains('multiple="multiple"><option value="1">A</option></select>', $edit, 'the options go into the country list');

	$zero = pg_tax_zone_form_cards(array('name' => 'Exempt', 'tax_rate' => '0.000'), '', '');
	pg_assert_contains('id="tax_rate" required value="0" class="form-control"', $zero, 'a zero rate stays 0, not an empty box');
}

function test_tax_zone_form_both_screens_draw_the_shared_cards()
{
	foreach (array('add_tax_zone.php', 'edit_tax_zone.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_tax_zone_form_cards(', $source, $file . ' draws the shared cards');
		pg_assert_contains('pg_tax_zone_form_save_links(', $source, $file . ' writes the links through the shared helper');
		pg_assert_contains('validate_token_field()', $source, $file . ' checks the token');
		pg_assert_contains('validate_ecommerce_access($user)', $source, $file . ' keeps its gate');
	}
}

function test_tax_zone_form_post_values()
{
	$values = pg_tax_zone_form_post_values(array('name' => '  EU VAT ', 'tax_rate' => '8,25', 'allowed_countries' => array('3', '', '4'), 'allowed_states' => 'x'));
	pg_assert_same('EU VAT', $values['name'], 'the name is trimmed');
	pg_assert_same('8,25', $values['tax_rate'], 'the rate is kept as typed');
	pg_assert_same(array('3', '4'), $values['allowed_countries'], 'the chosen countries');
	pg_assert_same(array(), $values['allowed_states'], 'a non-list is no choice');

	$missing = pg_tax_zone_form_post_values(array('name' => array('x')));
	pg_assert_same('', $missing['name'], 'a non-scalar name is empty');
	pg_assert_same('', $missing['tax_rate'], 'a missing rate is empty');
}

function test_tax_zone_form_name_error()
{
	pg_assert_same('', pg_tax_zone_form_name_error('EU VAT'), 'a name passes');
	pg_assert_same(lang('Enter a name.'), pg_tax_zone_form_name_error(''), 'a blank name is refused');
	pg_assert_same(lang('Enter a name.'), pg_tax_zone_form_name_error("  \t"), 'spaces only are refused');
}

// A blank name was stored and listed as an empty row of All Tax Zones; both
// screens now draw the form again with what was typed and the message.
function test_tax_zone_form_screens_refuse_a_blank_name()
{
	foreach (array('add_tax_zone.php', 'edit_tax_zone.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_tax_zone_form_name_error($values[\'name\'])', $source, $file . ' checks the name');
		pg_assert_contains('pg_tax_zone_form_cards($values, $choices[\'countries\'], $choices[\'states\'], $errors)', $source, $file . ' draws the message');
		pg_assert_same(0, substr_count($source, "(string) (\$_POST['name'] ?? ''), \$tax_rate"), $file . ' writes the trimmed name, not the posted one');
	}

	$edit = file_get_contents(PG_FUNCTIONS_DIR . '/edit_tax_zone.php');
	pg_assert_contains("if ((\$_POST['submit_delete'] ?? '') != 'Delete') {\n        \$values = pg_tax_zone_form_post_values(\$_POST);", $edit, 'delete is never held back by the name');

	$html = pg_tax_zone_form_cards(array('name' => '', 'tax_rate' => '18'), '', '', array('name' => 'Enter a name.'));
	pg_assert_contains('class="form-control is-invalid" required aria-invalid="true" aria-describedby="name-error" />', $html, 'the name box is marked');
	pg_assert_contains('<div id="name-error" class="invalid-feedback d-block">Enter a name.</div>', $html, 'the message under the name');
	pg_assert_contains('id="tax_rate" required value="18" class="form-control"', $html, 'the typed rate is kept');
}

// The States or Provinces list printed the stored English country name and
// ordered by it ("Turkey: Adana") while the shipping zone form says
// "Türkiye: Adana": states now go under the country's shown name, ordered by
// it, the state name order kept within each country.
function test_tax_zone_form_state_items()
{
	$rows = array(
		array('id' => '7', 'name' => 'Alabama', 'country_name' => 'Zed Land', 'country_code' => ''),
		array('id' => '3', 'name' => 'Bavaria', 'country_name' => 'Alpha Land', 'country_code' => ''),
		array('id' => '9', 'name' => 'Ohio', 'country_name' => 'Zed Land', 'country_code' => ''),
		array('id' => '5', 'name' => 'Saxony', 'country_name' => 'Alpha Land', 'country_code' => ''),
		array('id' => '8', 'name' => 'Nowhere', 'country_name' => null, 'country_code' => null),
	);

	$labels = array();

	foreach (pg_tax_zone_form_state_items($rows) as $item) {
		$labels[$item['id']] = $item['label'];
	}

	pg_assert_same(array('8' => ': Nowhere', '3' => 'Alpha Land: Bavaria', '5' => 'Alpha Land: Saxony', '7' => 'Zed Land: Alabama', '9' => 'Zed Land: Ohio'), $labels, 'by country, then by the given state order');

	if (class_exists('Locale')) {
		$items = pg_tax_zone_form_state_items(array(array('id' => '1', 'name' => 'Bayern', 'country_name' => '', 'country_code' => 'DE')));
		pg_assert_same(pg_geo_country_label('', 'DE') . ': Bayern', $items[0]['label'], 'the country is named through pg_geo_country_label()');
	}

	$source = file_get_contents(PG_FUNCTIONS_DIR . '/includes/forms/tax_zone_form.php');
	pg_assert_contains('$states = pg_tax_zone_form_state_items(', $source, 'the form lists its states through it');
	pg_assert_same(0, substr_count($source, 'ORDER BY countries.name'), 'not ordered by the stored country name');
}
