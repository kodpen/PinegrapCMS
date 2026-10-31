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

	// A name a spreadsheet would run as a formula is kept as text.
	$row['label'] = '=HYPERLINK("x")';
	$row['username'] = '@app';
	$line = pg_audit_view_csv_line($row);
	pg_assert_same('\'=HYPERLINK("x")', $line[9], 'formula label');
	pg_assert_same('\'@app', $line[4], 'formula name');
}

// Known types open their screen; others have no link.
function test_audit_view_record_url()
{
	pg_assert_same('edit_product.php?id=4', pg_audit_view_record_url('product', 4), 'product');
	pg_assert_same('view_order.php?id=8', pg_audit_view_record_url('order', '8'), 'order');
	pg_assert_same('edit_contact.php?id=12', pg_audit_view_record_url('contact', 12), 'contact');
	pg_assert_same('', pg_audit_view_record_url('product', 0), 'no id');
	pg_assert_same('api_settings.php', pg_audit_view_record_url('api_key', 2), 'API keys');
	pg_assert_same('', pg_audit_view_record_url('settings', 0), 'settings');
	pg_assert_same('', pg_audit_view_record_url('person', 5), 'person');
}

function test_audit_view_record_ids_grouped_by_type()
{
	$rows = array(
		array('entity_type' => 'page', 'entity_id' => '1116'),
		array('entity_type' => 'page', 'entity_id' => 1116),
		array('entity_type' => 'page', 'entity_id' => 7),
		array('entity_type' => 'file', 'entity_id' => 7070),
		array('entity_type' => 'settings', 'entity_id' => 0),
		array('entity_type' => 'api_key', 'entity_id' => 2),
		array('entity_type' => 'product', 'entity_id' => 0),
	);

	pg_assert_same(array('page' => array(1116, 7), 'file' => array(7070)), pg_audit_view_record_ids($rows), 'one list per type with an edit screen, each id once');
	pg_assert_same(array(), pg_audit_view_record_ids(array()), 'an empty page');

	// Every type with an edit screen link is looked up, so a deleted one loses its "Open" link.
	foreach (array_keys(pg_audit_view_record_tables()) as $type) {
		pg_assert_true(pg_audit_view_record_url($type, 5) !== '', $type . ' has an edit screen');
	}

	pg_assert_contains('pg_audit_view_live_records(', file_get_contents(dirname(__DIR__) . '/pinegrap/includes/fn/audit_list.php'), 'each page asks which records still exist');
}

// The Open icon follows the target screen's own gate: edit_user.php admits
// a strictly lower role (administrators everyone), edit_file.php refuses a
// design file below Designer.
function test_audit_view_can_open_role_matrix()
{
	$user = function ($target) {
		return array('user_role' => $target);
	};

	foreach (array(0, 1, 2, 3) as $target) {
		pg_assert_true(pg_audit_view_can_open('user', $user($target), 0), 'an administrator opens role ' . $target);
	}

	pg_assert_false(pg_audit_view_can_open('user', $user(0), 1), 'a designer does not open an administrator');
	pg_assert_false(pg_audit_view_can_open('user', $user(1), 1), 'a designer does not open a designer, their own account included');
	pg_assert_true(pg_audit_view_can_open('user', $user(2), 1), 'a designer opens a manager');
	pg_assert_false(pg_audit_view_can_open('user', $user(2), 2), 'a manager does not open a manager, their own account included');
	pg_assert_true(pg_audit_view_can_open('user', $user(3), 2), 'a manager opens a user');
	pg_assert_false(pg_audit_view_can_open('user', true, 0), 'an unknown role opens nothing');

	pg_assert_true(pg_audit_view_can_open('file', array('design' => 0), 2), 'a manager opens a file');
	pg_assert_false(pg_audit_view_can_open('file', array('design' => '1'), 2), 'a manager does not open a design file');
	pg_assert_true(pg_audit_view_can_open('file', array('design' => '1'), 1), 'a designer opens a design file');
	pg_assert_true(pg_audit_view_can_open('file', array('design' => 1), 0), 'an administrator opens a design file');
	pg_assert_false(pg_audit_view_can_open('file', true, 2), 'a file of unknown kind is treated as a design file');
	pg_assert_true(pg_audit_view_can_open('file', true, 1), 'which a designer opens');

	foreach (array('product', 'page', 'order', 'offer', 'contact') as $type) {
		pg_assert_true(pg_audit_view_can_open($type, true, 2), $type . ' opens for a manager');
	}

	pg_assert_same(array('user' => 'user_role', 'file' => 'design'), pg_audit_view_record_columns(), 'the gates read these columns');

	foreach (pg_audit_view_record_columns() as $type => $column) {
		pg_assert_true(isset(pg_audit_view_record_tables()[$type]), $type . ' is a looked-up type');
	}
}

// The condition binds every value; a ? in a value stays a value.
function test_audit_view_condition()
{
	pg_assert_same(array('sql' => '1', 'params' => array()), pg_audit_view_condition(pg_audit_view_filters(array())), 'nothing filtered');

	$condition = pg_audit_view_condition(pg_audit_view_filters(array('action' => 'update', 'user' => 'who?', 'q' => 'a_b')));

	pg_assert_same('(action = ?) AND (username LIKE ?) AND (label LIKE ?)', $condition['sql']);
	pg_assert_same(array('update', '%who?%', '%a\\_b%'), $condition['params']);
	pg_assert_same("(action = 'update') AND (username LIKE '%who?%') AND (label LIKE '%a\\\\_b%')", pg_audit_view_where(pg_audit_view_filters(array('action' => 'update', 'user' => 'who?', 'q' => 'a_b')), 'addslashes'));
}

function test_audit_view_labels()
{
	pg_assert_same(lang('Record created'), pg_audit_view_labels('action')['create']);
	pg_assert_same(array(), pg_audit_view_labels('other'));
	pg_assert_same('later_word', pg_audit_view_label('action', 'later_word'), 'a word without a label is shown as stored');
}

// The search finds a settings line by the category name the screen shows,
// in any case and with the Turkish dotted and dotless i alike; the stored key
// still matches through the label.
function test_audit_view_settings_keys_named()
{
	$names = array('general' => 'Genel', 'maintenance' => 'Güncellemeler ve Bakım', 'commerce' => 'E-Ticaret', 'api' => 'Entegrasyonlar', 'seo' => 'SEO');

	pg_assert_same(array('general'), pg_audit_view_settings_keys_named('Genel', $names), 'the shown name');
	pg_assert_same(array('general'), pg_audit_view_settings_keys_named('GENEL', $names), 'any case');
	pg_assert_same(array('maintenance'), pg_audit_view_settings_keys_named('BAKIM', $names), 'dotless i typed as I');
	pg_assert_same(array('commerce', 'api'), pg_audit_view_settings_keys_named('e', array_intersect_key($names, array('api' => 1, 'commerce' => 1))), 'every match');
	pg_assert_same(array(), pg_audit_view_settings_keys_named('general', $names), 'the key is matched by the label, not here');
	pg_assert_same(array(), pg_audit_view_settings_keys_named('  ', $names), 'blank');
}

function test_audit_view_condition_finds_settings_by_shown_name()
{
	$names = array('general' => 'Genel', 'maintenance' => 'Güncellemeler ve Bakım');

	$condition = pg_audit_view_condition(pg_audit_view_filters(array('q' => 'Genel')), $names);
	pg_assert_same("(label LIKE ? OR (entity_type = 'settings' AND label IN (?)))", $condition['sql']);
	pg_assert_same(array('%Genel%', 'general'), $condition['params']);

	$condition = pg_audit_view_condition(pg_audit_view_filters(array('type' => 'settings', 'q' => 'bakım')), $names);
	pg_assert_same("(entity_type = ?) AND (label LIKE ? OR (entity_type = 'settings' AND label IN (?)))", $condition['sql']);
	pg_assert_same(array('settings', '%bakım%', 'maintenance'), $condition['params']);

	$condition = pg_audit_view_condition(pg_audit_view_filters(array('type' => 'page', 'q' => 'Genel')), $names);
	pg_assert_same('(entity_type = ?) AND (label LIKE ?)', $condition['sql'], 'another type searches the label only');

	$condition = pg_audit_view_condition(pg_audit_view_filters(array('q' => 'general')), $names);
	pg_assert_same('(label LIKE ?)', $condition['sql'], 'the key alone matches through the label');
}
