<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the All Tax Zones list's quick edit
 * (includes/fn/tax_zone_list_edit.php): the name and the rate are fields,
 * Last Modified stays text, the value each field opens with, the screen's
 * checks on a change and the answer's cells.
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
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/tax_zone_list_edit.php');

foreach (array('PATH' => '/', 'OUTPUT_PATH' => '/', 'OUTPUT_SOFTWARE_DIRECTORY' => 'pinegrap', 'DATE_FORMAT' => 'day_month', 'TIME_FORMAT' => 'twenty_four_hours') as $tax_zone_edit_test_name => $tax_zone_edit_test_value) {
	if (!defined($tax_zone_edit_test_name)) {
		define($tax_zone_edit_test_name, $tax_zone_edit_test_value);
	}
}

function pg_test_tax_zone_quick_row(array $over = array())
{
	return array_merge(array(
		'id' => '4', 'name' => 'EU <VAT>', 'tax_rate' => '18.500',
		'last_modified_timestamp' => '1700000000', 'last_modified_username' => 'admin',
	), $over);
}

// Every value the tax zone screen changes that the list shows is an
// editable column with a cell field; Last Modified stays text. A new column
// fails here until it is one or the other.
function test_tax_zone_quick_every_screen_value_is_a_field()
{
	$text = array('last_modified');
	$fields = pg_tax_zone_quick_edit_columns();
	$cells = pg_tax_zone_list_cells(pg_test_tax_zone_quick_row(), $fields);

	foreach (pg_tax_zone_list_columns(true) as $key => $column) {
		if (in_array($key, $text, true)) {
			pg_assert_false(isset($column['editable']), $key . ' is text');
			pg_assert_false(isset($cells[$key]['attributes']['data-pg-edit']), $key . ' cell is text');
			continue;
		}

		pg_assert_true(isset($column['editable']), $key . ' is editable');
		pg_assert_true(in_array($column['editable'], pg_list_table_edit_kinds(), true), $key . ' is a driver kind');
		pg_assert_true(isset($fields[$key]), $key . ' has a field');
		pg_assert_same($key, $cells[$key]['attributes']['data-pg-edit'] ?? '', $key . ' cell is a field');
	}

	foreach (pg_tax_zone_list_columns(false) as $key => $column) {
		pg_assert_false(isset($column['editable']), $key . ' is text without quick edit');
	}

	pg_assert_same(50, $fields['name']['max'], 'the name is the screen\'s 50 characters');
	pg_assert_true(!empty($fields['name']['required']), 'the name is required');
}

function test_tax_zone_quick_values_and_cells()
{
	$row = pg_test_tax_zone_quick_row();

	pg_assert_same('EU <VAT>', pg_tax_zone_quick_value('name', $row), 'name as stored');
	pg_assert_same('18.5', pg_tax_zone_quick_value('tax_rate', $row), 'rate trimmed');
	pg_assert_same('18,5', pg_tax_zone_quick_value('tax_rate', $row, ','), 'rate in the panel\'s separator');
	pg_assert_same('20', pg_tax_zone_quick_value('tax_rate', pg_test_tax_zone_quick_row(array('tax_rate' => '20.000'))), 'whole rate');

	$cells = pg_tax_zone_list_cells($row, pg_tax_zone_quick_edit_columns());
	pg_assert_contains('href="edit_tax_zone.php?id=4" data-pg-open>EU &lt;VAT&gt;</a>', $cells['name']['html'], 'name links to the screen, escaped');
	pg_assert_same('18.5', $cells['tax_rate']['attributes']['data-pg-sort-value'], 'rate sorts by its number');

	$plain = pg_tax_zone_list_cells($row, array());
	pg_assert_false(isset($plain['name']['attributes']['data-pg-edit']), 'no field without quick edit');
}

function test_tax_zone_quick_change_uses_the_screen_checks()
{
	$stored = array('name' => 'EU', 'tax_rate' => '18.500');

	$change = pg_tax_zone_quick_change($stored, 'tax_rate', '20,25');
	pg_assert_true($change['ok'], 'comma rate read');
	pg_assert_same('EU', $change['name'], 'name kept');
	pg_assert_true(abs($change['tax_rate'] - 20.25) < 0.0001, 'rate 20.25');

	$change = pg_tax_zone_quick_change($stored, 'name', '  Turkey  ');
	pg_assert_true($change['ok'], 'name change');
	pg_assert_same('Turkey', $change['name'], 'name trimmed');
	pg_assert_true(abs($change['tax_rate'] - 18.5) < 0.0001, 'stored rate kept');

	pg_assert_false(pg_tax_zone_quick_change($stored, 'name', '  ')['ok'], 'blank name refused');
	pg_assert_false(pg_tax_zone_quick_change($stored, 'tax_rate', '1.2.3')['ok'], 'unreadable rate refused');

	$change = pg_tax_zone_quick_change($stored, 'tax_rate', '150');
	pg_assert_true($change['ok'] && abs($change['tax_rate'] - 100) < 0.0001, 'rate capped at 100 like the screen');
}

function test_tax_zone_quick_saved_redraws_the_row()
{
	$cells = pg_tax_zone_list_cells(pg_test_tax_zone_quick_row(), pg_tax_zone_quick_edit_columns());
	$answer = pg_tax_zone_quick_saved($cells, 'tax_rate');

	pg_assert_true($answer['ok'], 'ok');
	pg_assert_same('18.5', $answer['value'], 'rate value');
	pg_assert_same('18.5', $answer['sort'], 'rate sort');
	pg_assert_same(array('name', 'last_modified'), array_keys($answer['cells']), 'name and Last Modified redrawn');
	pg_assert_same('EU <VAT>', $answer['cells']['name']['value'], 'name keeps its value');
}

function test_tax_zone_list_answers_quick_edit_before_its_access_check()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/view_tax_zones.php');

	$request = strpos($source, 'pg_list_edit_request($quick_edit');
	$gate = strpos($source, 'validate_ecommerce_access($user);');
	pg_assert_true(($request !== false) && ($gate !== false) && ($request < $gate), 'JSON answer before the screen gate');
	pg_assert_contains("return (\$user['role'] < 3) || !empty(\$user['manage_ecommerce']);", $source, 'the screen\'s commerce rights');
	pg_assert_contains("'edit' => array('url' => 'view_tax_zones.php', 'columns' => \$quick_edit)", $source, 'the list offers quick edit');
}
