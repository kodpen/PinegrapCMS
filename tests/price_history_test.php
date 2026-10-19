<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the pure parts of includes/fn/price_history.php: which products
 * get a history row after a write, the product list a writer hands over, the
 * source label and the statements the recorder runs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A product with no history yet gets its first row, whatever the price.
function test_price_history_changes_first_row()
{
	pg_assert_same(array(7 => 1999), pg_price_history_changes(array(7 => 1999), array(), 'TRY'));
	pg_assert_same(array(7 => 0), pg_price_history_changes(array(7 => 0), array(), 'TRY'), 'a free product is a price too');
}

// The same price again writes nothing: a save that left the price alone, or a
// writer that cannot tell whether it changed it.
function test_price_history_changes_same_price_writes_nothing()
{
	$newest = array(7 => array('price' => 1999, 'currency' => 'TRY'));

	pg_assert_same(array(), pg_price_history_changes(array(7 => 1999), $newest, 'TRY'));

	// Values read from the database arrive as strings.
	$stored = array(7 => array('price' => '1999', 'currency' => 'TRY'));

	pg_assert_same(array(), pg_price_history_changes(array(7 => '1999'), $stored, 'TRY'), 'strings from the database');
}

// A different price is a row, in either direction.
function test_price_history_changes_new_price()
{
	$newest = array(7 => array('price' => 1999, 'currency' => 'TRY'));

	pg_assert_same(array(7 => 1499), pg_price_history_changes(array(7 => 1499), $newest, 'TRY'), 'lower');
	pg_assert_same(array(7 => 2499), pg_price_history_changes(array(7 => 2499), $newest, 'TRY'), 'higher');
	pg_assert_same(array(7 => 1999), pg_price_history_changes(array(7 => 1999), array(7 => array('price' => 2499, 'currency' => 'TRY')), 'TRY'), 'set back to an earlier price');
}

// The base currency changing is a new price even when the number is the same:
// 100 lira and 100 dollars are not one price.
function test_price_history_changes_currency_change()
{
	$newest = array(7 => array('price' => 1999, 'currency' => 'USD'));

	pg_assert_same(array(7 => 1999), pg_price_history_changes(array(7 => 1999), $newest, 'TRY'));
}

// Only the products that changed are returned, in the order they were read,
// and a product the writer named but that is not in the table (deleted under
// it) is left out.
function test_price_history_changes_mixed_list()
{
	$current = array(3 => 500, 5 => 700, 9 => 900);
	$newest = array(
		3 => array('price' => 500, 'currency' => 'TRY'),
		5 => array('price' => 650, 'currency' => 'TRY'),
		11 => array('price' => 100, 'currency' => 'TRY'),
	);

	pg_assert_same(array(5 => 700, 9 => 900), pg_price_history_changes($current, $newest, 'TRY'));
}

// A writer hands over one id or a list, as integers or as the strings a form
// posted; zero, negatives, repeats and anything that is not a whole number
// are dropped.
function test_price_history_ids()
{
	pg_assert_same(array(7), pg_price_history_ids(7));
	pg_assert_same(array(7), pg_price_history_ids('7'));
	pg_assert_same(array(3, 5, 9), pg_price_history_ids(array(3, '5', 3, 9, '5')));
	pg_assert_same(array(4), pg_price_history_ids(array(0, -2, '', null, '1.5', 'x', ' 4 ', array(8))));
	pg_assert_same(array(), pg_price_history_ids(null));
	pg_assert_same(array(), pg_price_history_ids(array()));
}

// The labels the history keeps; anything else is kept as a panel change
// rather than lost, since a missing row is worse than a vague label.
function test_price_history_source()
{
	foreach (array('initial', 'panel', 'import', 'api', 'offer', 'erp') as $source) {
		pg_assert_same($source, pg_price_history_source($source), $source);
	}

	pg_assert_same('api', pg_price_history_source(' API '), 'case and spaces');
	pg_assert_same('panel', pg_price_history_source('workspace'), 'unknown');
	pg_assert_same('panel', pg_price_history_source(''), 'empty');
	pg_assert_same('panel', pg_price_history_source(array('api')), 'a list');
}

// Channel names go into a statement as they are, so only the known shapes
// pass.
function test_price_history_channel()
{
	pg_assert_same('web', pg_price_history_channel('web'));
	pg_assert_same('pos', pg_price_history_channel('pos'));
	pg_assert_same('marketplace:n11', pg_price_history_channel('marketplace:n11'));
	pg_assert_same('marketplace:trendyol_tr', pg_price_history_channel('marketplace:trendyol_tr'));

	pg_assert_same(null, pg_price_history_channel('Web'), 'capitals');
	pg_assert_same(null, pg_price_history_channel('marketplace:'), 'no code');
	pg_assert_same(null, pg_price_history_channel("web' OR '1"), 'a quote');
	pg_assert_same(null, pg_price_history_channel('marketplace:' . str_repeat('a', 41)), 'too long for the column');
	pg_assert_same(null, pg_price_history_channel(array('web')), 'a list');
}

// A currency code keeps up to three capital letters and nothing else.
function test_price_history_currency_code()
{
	pg_assert_same('TRY', pg_price_history_currency_code('TRY'));
	pg_assert_same('EUR', pg_price_history_currency_code(' eur '));
	pg_assert_same('USD', pg_price_history_currency_code("US'D"));
	pg_assert_same('TL', pg_price_history_currency_code('TL'));
	pg_assert_same('', pg_price_history_currency_code(null));
}

// The newest row of each product is found by its id, within one channel.
function test_price_history_newest_sql()
{
	$sql = pg_price_history_newest_sql(array(3, '5', 3), 'web');

	pg_assert_contains('product_id IN (3,5)', $sql);
	pg_assert_contains("channel = 'web'", $sql);
	pg_assert_contains('MAX(id)', $sql);

	pg_assert_same('', pg_price_history_newest_sql(array(), 'web'), 'no product');
	pg_assert_same('', pg_price_history_newest_sql(array(3), "web'"), 'not a channel');
}

// One INSERT for the products that changed, every value quoted.
function test_price_history_insert_sql()
{
	$sql = pg_price_history_insert_sql(array(5 => 700, 9 => -100), 'web', 'TRY', 'import', 1760000000);

	pg_assert_contains('INSERT INTO product_price_history (product_id, channel, price, currency, changed_at, source) VALUES', $sql);
	pg_assert_contains("('5', 'web', '700', 'TRY', '1760000000', 'import')", $sql);
	pg_assert_contains("('9', 'web', '-100', 'TRY', '1760000000', 'import')", $sql);

	pg_assert_same('', pg_price_history_insert_sql(array(), 'web', 'TRY', 'panel', 1760000000), 'nothing to write');
	pg_assert_same('', pg_price_history_insert_sql(array(5 => 700), 'shop', 'TRY', 'panel', 1760000000), 'not a channel');
	pg_assert_same('', pg_price_history_insert_sql(array(5 => 700), 'web', 'TRY', 'workspace', 1760000000), 'not a label the history keeps');
	pg_assert_contains("'5', 'web', '700', 'TRY'", pg_price_history_insert_sql(array(5 => 700), 'web', "t'ry", 'panel', 1760000000), 'currency cleaned');
}

// A bulk writer's queue keeps each product once per source label, and a flush
// empties it.
function test_price_history_queue_and_flush()
{
	pg_price_history_flush();

	pg_price_history_queue(array(3, '5'), 'import');
	pg_price_history_queue(5, 'IMPORT');
	pg_price_history_queue(array(7, 0, 'x'), 'api');

	$state = pg_price_history_queue_state();

	pg_assert_same(array(3, 5), array_keys($state['ids']['import']), 'one entry per product');
	pg_assert_same(array(7), array_keys($state['ids']['api']), 'per source');
	pg_assert_true($state['shutdown'], 'flushed when the request ends');

	pg_price_history_flush();

	$state = pg_price_history_queue_state();

	pg_assert_same(array(), $state['ids'], 'empty after a flush');
}

// A full chunk is written at once rather than held until the end.
function test_price_history_queue_flushes_a_full_chunk()
{
	pg_price_history_flush();

	pg_price_history_queue(range(1, PG_PRICE_HISTORY_CHUNK - 1), 'import');

	$state = pg_price_history_queue_state();

	pg_assert_same(PG_PRICE_HISTORY_CHUNK - 1, count($state['ids']['import']), 'one short of a chunk is held');

	pg_price_history_queue(PG_PRICE_HISTORY_CHUNK, 'import');

	$state = pg_price_history_queue_state();

	pg_assert_same(array(), $state['ids'], 'a full chunk is written');
}
