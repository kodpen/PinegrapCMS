<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for reading the audit trail back (includes/fn/audit_view.php): the
 * screen's filters, the WHERE clause they become, how each stored form of a
 * value is shown, and the CSV line of a row.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A value that does not fit its field is dropped; a reversed range is turned round.
function test_audit_view_filters_drop_bad_values()
{
	$filters = pg_audit_view_filters(array(
		'from'    => '2026-10-30',
		'to'      => '2026-10-01',
		'source'  => 'cron',
		'user'    => ' ayse ',
		'action'  => "update' OR 1",
		'type'    => 'product',
		'id'      => '-4',
		'q'       => array('x'),
		'request' => 'abc 123',
		'page'    => '0',
	));

	pg_assert_same('2026-10-01', $filters['from'], 'from turned round');
	pg_assert_same('2026-10-30', $filters['to'], 'to turned round');
	pg_assert_same('', $filters['source'], 'unknown source');
	pg_assert_same('ayse', $filters['user'], 'user trimmed');
	pg_assert_same('', $filters['action'], 'action not a word');
	pg_assert_same('product', $filters['type'], 'type');
	pg_assert_same(0, $filters['id'], 'negative id');
	pg_assert_same('', $filters['q'], 'array');
	pg_assert_same('', $filters['request'], 'request with a space');
	pg_assert_same(1, $filters['page'], 'page 0');

	$filters = pg_audit_view_filters(array('from' => '2026-02-30', 'to' => '26.10.2026', 'id' => '17', 'page' => '3', 'source' => 'api', 'request' => 'req_9f.A-1'));

	pg_assert_same('', $filters['from'], 'no such day');
	pg_assert_same('', $filters['to'], 'not ISO');
	pg_assert_same(17, $filters['id'], 'id');
	pg_assert_same(3, $filters['page'], 'page');
	pg_assert_same('api', $filters['source'], 'source');
	pg_assert_same('req_9f.A-1', $filters['request'], 'request id');
}

// Only the filters that are set go into a link, and never the page number.
function test_audit_view_query_keeps_set_filters()
{
	$filters = pg_audit_view_filters(array('type' => 'page', 'id' => '5', 'page' => '4'));

	pg_assert_same(array('type' => 'page', 'id' => 5), pg_audit_view_query($filters), 'query');
}

// Each filter becomes its condition; text is escaped and LIKE wildcards are literal.
function test_audit_view_where()
{
	pg_assert_same('1', pg_audit_view_where(pg_audit_view_filters(array()), 'addslashes'), 'nothing filtered');

	$where = pg_audit_view_where(pg_audit_view_filters(array(
		'from'   => '2026-10-01',
		'to'     => '2026-10-01',
		'source' => 'panel',
		'type'   => 'order',
		'id'     => '12',
		'user'   => "o'neil",
		'q'      => '50%_off',
	)), 'addslashes');

	$from = strtotime('2026-10-01 00:00:00');
	$to = strtotime('2026-10-02 00:00:00');

	pg_assert_same(
		"(created_at >= '" . $from . "') AND (created_at < '" . $to . "') AND (source = 'panel') AND (entity_type = 'order') AND (entity_id = '12') AND (username LIKE '%o\\'neil%') AND (label LIKE '%50\\\\%\\\\_off%')",
		$where,
		'all filters'
	);

	pg_assert_same("(user_id = '7')", pg_audit_view_where(pg_audit_view_filters(array('user' => '7')), 'addslashes'), 'user id');
}

// Each stored form of a value is shown as its kind.
function test_audit_view_value_kinds()
{
	pg_assert_same('none', pg_audit_view_value(null)['kind'], 'null');
	pg_assert_same('text', pg_audit_view_value('')['kind'], 'empty text is a value');
	pg_assert_same('secret', pg_audit_view_value('***')['kind'], 'secret');
	pg_assert_same('Kalem', pg_audit_view_value('Kalem')['text'], 'text');

	$cut = pg_audit_view_value(array('truncated' => true, 'length' => 5000, 'sha1' => 'abc', 'start' => 'Başlangıç'));
	pg_assert_same(array('kind' => 'cut', 'text' => 'Başlangıç', 'length' => 5000, 'sha1' => 'abc'), $cut, 'compacted text');

	$json = pg_audit_view_value(array('feeds' => array(array('token' => '***', 'ad' => 'Ürün'))));
	pg_assert_same('json', $json['kind'], 'object');
	pg_assert_true(strpos($json['text'], '"ad": "Ürün"') !== false, 'readable Unicode, indented');

	pg_assert_same('json', pg_audit_view_value(array())['kind'], 'empty object comes back as a list');
}

// Fields come from both sides; a side cut to its names keeps the names without values.
function test_audit_view_changes()
{
	$changes = pg_audit_view_changes('{"price":"1250","note":null}', '{"price":"1300","stock":"4"}');

	pg_assert_same(array('price', 'stock', 'note'), array_column($changes, 'field'), 'fields');
	pg_assert_same('1250', $changes[0]['before']['text'], 'before');
	pg_assert_same('1300', $changes[0]['after']['text'], 'after');
	pg_assert_same('none', $changes[1]['before']['kind'], 'only after');
	pg_assert_same('none', $changes[2]['after']['kind'], 'only before');

	$changes = pg_audit_view_changes('{"truncated":true,"fields":["page_tree_json","page_name"]}', null);

	pg_assert_same(array('page_tree_json', 'page_name'), array_column($changes, 'field'), 'names only');
	pg_assert_true($changes[0]['names_only'], 'marked');
	pg_assert_same('none', $changes[0]['before']['kind'], 'no value');

	pg_assert_same(array(), pg_audit_view_changes(null, ''), 'nothing stored');
	pg_assert_same(array(), pg_audit_view_changes('not json', '[1'), 'unreadable');

	// A real field named "truncated" next to others is a field, not the cut form.
	pg_assert_same(array('truncated', 'name'), array_column(pg_audit_view_changes(null, '{"truncated":"1","name":"x"}'), 'field'), 'field named truncated');
}

// A CSV line holds every column in order, the sides as stored.
function test_audit_view_csv_line()
{
	$row = array(
		'id' => '9', 'created_at' => (string) strtotime('2026-10-26 14:05:00'), 'request_id' => 'r1', 'source' => 'panel',
		'user_id' => '3', 'username' => 'ayse', 'ip' => '10.0.0.1', 'action' => 'update', 'entity_type' => 'product',
		'entity_id' => '44', 'label' => 'Kalem', 'before_json' => '{"price":"1250"}', 'after_json' => '{"price":"1300","name":"Kalem"}',
	);

	$line = pg_audit_view_csv_line($row);

	pg_assert_same(count(pg_audit_view_csv_columns()), count($line), 'one value per column');
	pg_assert_same(array('9', '2026-10-26 14:05:00', 'panel', '3', 'ayse', '10.0.0.1', 'update', 'product', '44', 'Kalem', 'r1', 'price, name', '{"price":"1250"}', '{"price":"1300","name":"Kalem"}'), $line, 'line');
}

// Known types open their screen; others have no link.
function test_audit_view_record_url()
{
	pg_assert_same('edit_product.php?id=4', pg_audit_view_record_url('product', 4), 'product');
	pg_assert_same('view_order.php?id=8', pg_audit_view_record_url('order', '8'), 'order');
	pg_assert_same('', pg_audit_view_record_url('product', 0), 'no id');
	pg_assert_same('api_settings.php', pg_audit_view_record_url('api_key', 2), 'API keys');
	pg_assert_same('', pg_audit_view_record_url('settings', 0), 'settings');
	pg_assert_same('', pg_audit_view_record_url('person', 5), 'person');
}
