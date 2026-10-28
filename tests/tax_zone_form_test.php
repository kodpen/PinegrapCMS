<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the tax zone form shared by add_tax_zone.php and
 * edit_tax_zone.php (includes/forms/tax_zone_form.php): the country and
 * state options, the posted link ids, the name field of each screen and
 * both screens drawing the one set of cards.
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
	pg_assert_contains('maxlength="50" placeholder="Tax Zone Name" class="form-control"', $create, 'create shows a placeholder');
	pg_assert_contains('id="tax_rate" maxlength="10" class="form-control"', $create, 'create leaves the rate empty');

	$edit = pg_tax_zone_form_cards(array('name' => 'EU "VAT"', 'tax_rate' => '20'), '<option value="1">A</option>', '');
	pg_assert_contains('maxlength="50" value="EU &quot;VAT&quot;" class="form-control"', $edit, 'edit shows the stored name, escaped');
	pg_assert_contains('maxlength="10" value="20" class="form-control"', $edit, 'edit shows the stored rate');
	pg_assert_contains('multiple="multiple"><option value="1">A</option></select>', $edit, 'the options go into the country list');
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
