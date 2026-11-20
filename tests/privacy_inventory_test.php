<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The personal data inventory (includes/fn/privacy_inventory.php): every
 * table the erasure reaches is in the catalogue, every catalogue line is
 * whole and its person column is one the erasure knows, every row of the
 * registry of outside services names a country and the data it receives,
 * and the transfers list keeps only what is switched on, marking what goes
 * abroad.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The tables pg_privacy_erase_tables() names, by their real name.
function privacy_inventory_test_erase_tables()
{
	$tables = array();

	foreach (pg_privacy_erase_tables() as $key => $entry) {
		$tables[isset($entry['table']) ? $entry['table'] : $key] = true;
	}

	return array_keys($tables);
}

function test_privacy_inventory_names_every_table_the_erasure_reaches()
{
	$catalogue = pg_privacy_inventory();

	foreach (privacy_inventory_test_erase_tables() as $table) {
		pg_assert_true(isset($catalogue[$table]), $table . ': the erasure reaches it, so the inventory names it');
	}
}

function test_privacy_inventory_every_line_is_whole()
{
	pg_assert_true(count(pg_privacy_inventory()) > 0, 'the inventory has lines');

	foreach (pg_privacy_inventory() as $table => $line) {
		pg_assert_same(array(), pg_privacy_inventory_line_problems($line), $table . ': the line is whole');
	}
}

function test_privacy_inventory_line_problems_names_what_is_missing()
{
	pg_assert_same(array('line'), pg_privacy_inventory_line_problems('x'), 'a line that is no array');

	$whole = array(
		'categories' => array('identity', 'contact'),
		'subjects'   => array('customer'),
		'purpose'    => 'Orders and their delivery',
		'ground'     => 'contract',
		'retention'  => 'orders',
		'person'     => array('billing_email_address', 'text'),
	);

	pg_assert_same(array(), pg_privacy_inventory_line_problems($whole), 'a whole line');

	$bad = $whole;
	$bad['categories'] = array('identity', 'shoe size');
	$bad['subjects'] = array();
	$bad['purpose'] = ' ';
	$bad['ground'] = 'because';
	$bad['retention'] = 'forever';
	$bad['person'] = array('email', 'date');

	pg_assert_same(array('categories', 'subjects', 'purpose', 'ground', 'retention', 'person'), pg_privacy_inventory_line_problems($bad), 'each key at fault is named');

	$none = $whole;
	unset($none['person']);
	pg_assert_same(array(), pg_privacy_inventory_line_problems($none), 'a line may count records only');
}

// The person column is one the erasure finds or replaces in that table, so
// it exists wherever the erasure runs.
function test_privacy_inventory_person_columns_are_known_to_the_erasure()
{
	$columns = array();

	foreach (pg_privacy_erase_tables() as $key => $entry) {
		$table = isset($entry['table']) ? $entry['table'] : $key;

		foreach (array_merge(array_keys($entry['find']), array_keys($entry['fields'])) as $column) {
			$columns[$table][$column] = true;
		}
	}

	foreach (pg_privacy_inventory() as $table => $line) {
		if (empty($line['person'])) {
			continue;
		}

		pg_assert_true(isset($columns[$table][$line['person'][0]]), $table . ': the person column ' . $line['person'][0] . ' is one the erasure knows');
	}
}

function test_privacy_inventory_count_sql_skips_empty_people()
{
	$sql = pg_privacy_inventory_count_sql('orders', array('person' => array('billing_email_address', 'text')));
	pg_assert_same("SELECT COUNT(*) AS records, COUNT(DISTINCT NULLIF(`billing_email_address`, '')) AS people FROM `orders`", $sql, 'a text person column skips the empty value');

	$sql = pg_privacy_inventory_count_sql('ws_events', array('person' => array('contact_id', 'int')));
	pg_assert_same("SELECT COUNT(*) AS records, COUNT(DISTINCT NULLIF(`contact_id`, 0)) AS people FROM `ws_events` WHERE `contact_id` > 0", $sql, 'a linking id counts only the linked rows');

	$sql = pg_privacy_inventory_count_sql('ship_tos', array());
	pg_assert_same('SELECT COUNT(*) AS records FROM `ship_tos`', $sql, 'no person column: records only');
}

function test_privacy_inventory_registry_rows_name_country_and_data()
{
	foreach (pg_connections_registry() as $id => $row) {
		pg_assert_true(isset($row['country']) && is_string($row['country']) && (preg_match('/^([A-Z]{2}|\*)$/', $row['country']) === 1), $id . ': the row names a country');
		pg_assert_true(!empty($row['data']) && is_array($row['data']), $id . ': the row names the data it receives');
	}
}

function test_privacy_inventory_registry_row_problems_names_country_and_data()
{
	$row = pg_connections_registry();
	$row = $row['stripe'];

	pg_assert_same(array(), pg_connections_row_problems($row), 'a whole row');

	$bad = $row;
	unset($bad['country']);
	$bad['data'] = array();
	pg_assert_same(array('country', 'data'), pg_connections_row_problems($bad), 'a row with no country and no data');

	$bad = $row;
	$bad['country'] = 'Turkey';
	$bad['data'] = array('identity', 'none');
	pg_assert_same(array('country', 'data'), pg_connections_row_problems($bad), 'a country that is no code, and none beside a category');

	$bad['data'] = array('shoe size');
	pg_assert_same(array('country', 'data'), pg_connections_row_problems($bad), 'an unknown category');
}

function test_privacy_inventory_transfers_keep_what_is_switched_on()
{
	$registry = array(
		'iyzico' => array('name' => 'iyzico', 'group' => 'payments', 'uses' => 'Card payments', 'country' => 'TR', 'data' => array('identity', 'order_payment')),
		'stripe' => array('name' => 'Stripe', 'group' => 'payments', 'uses' => 'Card payments', 'country' => 'US', 'data' => array('identity', 'order_payment')),
		'slack'  => array('name' => 'Slack', 'group' => 'messaging', 'uses' => 'Alerts', 'country' => 'US', 'data' => array('messages')),
		'dbip'   => array('name' => 'DB-IP', 'group' => 'maps', 'uses' => 'Countries', 'country' => '*', 'data' => array('none')),
		'gone'   => array('name' => 'Gone', 'group' => 'other', 'uses' => 'Nothing', 'country' => 'US', 'data' => array('contact')),
	);
	$states = array('iyzico' => 'connected', 'stripe' => 'not_connected', 'slack' => 'attention', 'dbip' => 'none', 'gone' => 'off');

	$list = pg_privacy_transfers($registry, $states, 'TR');

	pg_assert_same(array('iyzico', 'slack', 'dbip'), array_keys($list), 'connected, needing attention and the stateless are kept; not connected and off are not');
	pg_assert_false($list['iyzico']['abroad'], 'a service in the site\'s country is not abroad');
	pg_assert_true($list['slack']['abroad'], 'a service in another country is abroad');
	pg_assert_true($list['slack']['personal'], 'messages are personal data');
	pg_assert_false($list['dbip']['personal'], 'none is no personal data');
	pg_assert_false($list['dbip']['abroad'], 'what receives no personal data is no transfer abroad');
	pg_assert_true($list['dbip']['unknown'], 'a stateless row is kept as may be in use');
	pg_assert_same('Alerts', $list['slack']['uses'], 'why it receives the data');
}

function test_privacy_inventory_categories_cover_the_registry()
{
	$categories = pg_privacy_inventory_categories();

	foreach (pg_connections_registry() as $id => $row) {
		foreach ((array) (isset($row['data']) ? $row['data'] : array()) as $key) {
			pg_assert_true(($key === 'none') || isset($categories[$key]), $id . ': ' . $key . ' is a category');
		}
	}
}
