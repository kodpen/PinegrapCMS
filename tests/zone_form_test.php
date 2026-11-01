<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the shipping zone form shared by add_zone.php and edit_zone.php
 * (includes/forms/zone_form.php): a posted rate in minor units, the order
 * and selection of the country and state options, and both screens drawing
 * the one set of cards.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/zone_form.php');

function test_zone_form_rate_in_minor_units()
{
	$decimal = pg_number_separators()['decimal'];

	pg_assert_same(1250, pg_zone_form_rate('12' . $decimal . '50'), 'a rate with cents');
	pg_assert_same(7, pg_zone_form_rate('0' . $decimal . '07'), 'rounded, not truncated');
	pg_assert_same(500, pg_zone_form_rate(' 5 '), 'spaces are dropped');
	pg_assert_same(0, pg_zone_form_rate('0'), 'zero');
}

function test_zone_form_rate_not_a_number_is_zero()
{
	pg_assert_same(0, pg_zone_form_rate(''), 'empty');
	pg_assert_same(0, pg_zone_form_rate('abc'), 'text');
	pg_assert_same(0, pg_zone_form_rate(array('1')), 'an array');
}

function test_zone_form_option_list_puts_the_chosen_first()
{
	$items = array(
		array('id' => '1', 'label' => 'Austria'),
		array('id' => '2', 'label' => 'Belgium'),
		array('id' => '3', 'label' => 'Chile & Peru'),
	);

	pg_assert_same(
		'<option value="1">Austria</option><option value="2">Belgium</option><option value="3">Chile &amp; Peru</option>',
		pg_zone_form_option_list($items, array()),
		'nothing chosen: the given order, labels escaped');

	pg_assert_same(
		'<option selected="selected" value="3">Chile &amp; Peru</option><option value="1">Austria</option><option value="2">Belgium</option>',
		pg_zone_form_option_list($items, array(3)),
		'the chosen one first and selected; ids compared as text');
}

function test_zone_form_both_screens_draw_the_shared_cards()
{
	foreach (array('add_zone.php', 'edit_zone.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_zone_form_cards(', $source, $file . ' draws the shared cards');
		pg_assert_contains('pg_zone_form_rate(', $source, $file . ' reads the rates through the shared helper');
	}
}

function test_zone_form_post_values()
{
	$values = pg_zone_form_post_values(array('name' => '  Europe ', 'base_rate' => '1,50', 'allowed_countries' => array('3', '4'), 'allowed_states' => 'x'));
	pg_assert_same('Europe', $values['name'], 'the name is trimmed');
	pg_assert_same('1,50', $values['base_rate'], 'a rate is kept as typed');
	pg_assert_same('', $values['item_rate'], 'a missing rate is empty');
	pg_assert_same(array('3', '4'), $values['allowed_countries'], 'the chosen countries');
	pg_assert_same(array(), $values['allowed_states'], 'a non-list is no choice');
}

function test_zone_form_name_error()
{
	pg_assert_same('', pg_zone_form_name_error('Europe'), 'a name passes');
	pg_assert_same(lang('Enter a name.'), pg_zone_form_name_error(''), 'a blank name is refused');
}

function test_zone_form_screens_refuse_a_blank_name()
{
	foreach (array('add_zone.php', 'edit_zone.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);
		pg_assert_contains('pg_zone_form_name_error(', $source, $file . ' checks the name');
	}

	$html = pg_zone_form_cards(array('name' => '', 'base_rate' => '0', 'primary_weight_rate' => '0', 'secondary_weight_rate' => '0', 'item_rate' => '0'), '', '', array('name' => 'Enter a name.'));
	pg_assert_contains('<div id="name-error" class="invalid-feedback d-block">Enter a name.</div>', $html, 'the message under the name');
	pg_assert_contains(' required', $html, 'the name box is required');
}
