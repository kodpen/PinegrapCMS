<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_geo_state_option_labels() in includes/fn/geo.php: the state
 * pick list's labels with the country named in the current language, in the
 * order of those names, shared by the verified shipping address form and
 * the verified shipping address list's state filter.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// With states of other countries, each label carries the shown country name
// and the rows follow that name; states of one country keep their order.
function test_geo_state_option_labels_with_foreign_states()
{
	$states = array(
		array('id' => '3', 'name' => 'Adana', 'country_name' => 'Zeta Land', 'country_code' => ''),
		array('id' => '4', 'name' => 'Bursa', 'country_name' => 'Zeta Land', 'country_code' => ''),
		array('id' => '7', 'name' => 'Texas', 'country_name' => 'Alpha Land', 'country_code' => ''),
		array('id' => '9', 'name' => 'Turkish', 'country_name' => 'Turkey', 'country_code' => 'TR'),
	);

	$options = pg_geo_state_option_labels($states, true);

	pg_assert_same(4, count($options));
	pg_assert_same(array('id' => '7', 'label' => 'Alpha Land: Texas'), $options[0]);
	$labels = array_column($options, 'label', 'id');
	pg_assert_same(pg_geo_country_label('Turkey', 'TR') . ': Turkish', $labels['9'], 'the country in the current language');

	$ids = array_column($options, 'id');
	pg_assert_same(array_search('3', $ids) + 1, array_search('4', $ids), 'states of one country stay together and in order');
}

// Without foreign states the label is the state name alone, in the given order.
function test_geo_state_option_labels_without_foreign_states()
{
	$states = array(
		array('id' => '2', 'name' => 'Zonguldak', 'country_name' => 'Turkey', 'country_code' => 'TR'),
		array('id' => '1', 'name' => 'Adana', 'country_name' => 'Turkey', 'country_code' => 'TR'),
	);

	pg_assert_same(
		array(array('id' => '2', 'label' => 'Zonguldak'), array('id' => '1', 'label' => 'Adana')),
		pg_geo_state_option_labels($states, false));
	pg_assert_same(array(), pg_geo_state_option_labels(array(), true));
}

// A state whose country row is gone keeps its name and comes first.
function test_geo_state_option_labels_missing_country()
{
	$states = array(
		array('id' => '5', 'name' => 'Ohio', 'country_name' => 'Alpha Land', 'country_code' => ''),
		array('id' => '6', 'name' => 'Lost', 'country_name' => null, 'country_code' => null),
	);

	$options = pg_geo_state_option_labels($states, true);

	pg_assert_same(array('id' => '6', 'label' => 'Lost'), $options[0]);
	pg_assert_same('Alpha Land: Ohio', $options[1]['label']);
}
