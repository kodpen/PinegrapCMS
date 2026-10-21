<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the audit trail helpers in includes/fn/audit.php: which fields a
 * change keeps, how secrets and long texts are stored, the JSON of each side,
 * the row a change becomes, and that writing never stops the caller.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A database row and a posted form agree when only the type differs; null and '' do not.
function test_audit_value_compares_by_text()
{
	pg_assert_same('5', pg_audit_value(5), 'int');
	pg_assert_same('1', pg_audit_value(true), 'true');
	pg_assert_same('0', pg_audit_value(false), 'false');
	pg_assert_same(null, pg_audit_value(null), 'null');
	pg_assert_same(array('a' => '1', 'b' => array('c' => null)), pg_audit_value(array('a' => 1, 'b' => array('c' => null))), 'nested');
}

// Only the fields that differ are kept, each side with its value; one-sided fields are null on the other.
function test_audit_diff_keeps_changed_fields_only()
{
	list($old, $new) = pg_audit_diff(
		array('name' => 'Kalem', 'price' => '1250', 'enabled' => '1', 'note' => null),
		array('name' => 'Kalem', 'price' => 1300, 'enabled' => true, 'note' => '', 'stock' => 4)
	);

	pg_assert_same(array('price' => '1250', 'note' => null, 'stock' => null), $old, 'before');
	pg_assert_same(array('price' => '1300', 'note' => '', 'stock' => '4'), $new, 'after');

	pg_assert_same(array(array(), array()), pg_audit_diff(array('a' => 1), array('a' => '1')), 'nothing changed');
	pg_assert_same(array(array('gone' => 'x'), array('gone' => null)), pg_audit_diff(array('gone' => 'x'), array()), 'field removed');
}

// Secrets are recognised by name; ordinary fields that only look alike are not.
function test_audit_secret_key_names()
{
	foreach (array('password', 'smtp_password', 'Password_Confirm', 'api_key', 'apikey', 'google_maps_api_key', 'recaptcha_secret_key', 'client_secret', 'mfa_secret', 'token', 'auth_token', 'parasut_credentials_enc', 'backup_remote_credentials', 'password_hash', 'user_pass', 'card_number', 'card_verification_number', 'cvv', 'pin', 'paypal_api_signature', 'key', 'user_devpasspin', 'checkout_pin') as $secret) {
		pg_assert_true(pg_audit_secret_key($secret), $secret);
	}

	foreach (array('name', 'key_code', 'monkey', 'compass', 'bypass', 'passkeys', 'tokens_used', 'keywords', 'sort_order', 'email_address', 'encoding', 'hashtag', 'spin', 'shipping') as $plain) {
		pg_assert_false(pg_audit_secret_key($plain), $plain);
	}
}

// Security settings and password bookkeeping named after passwords hold no
// secret: on/off flags, a hashing scheme, a time, a job cursor. The trail
// shows their values; the user's own hint text stays masked.
function test_audit_password_setting_flags_are_plain()
{
	foreach (array('strong_password', 'password_hint', 'forgot_password_link', 'user_password_algo', 'user_password_changed_at', 'password_wrap_cursor', 'password_wrap_done') as $plain) {
		pg_assert_false(pg_audit_secret_key($plain), $plain);
	}

	foreach (array('user_password', 'user_password_hint', 'ups_password', 'signature_tsa_password') as $secret) {
		pg_assert_true(pg_audit_secret_key($secret), $secret);
	}

	$row = pg_audit_row(
		'update',
		'settings',
		0,
		array('strong_password' => '1', 'password_hint' => '1', 'forgot_password_link' => '1', 'smtp_password' => 'old'),
		array('strong_password' => '0', 'password_hint' => '', 'forgot_password_link' => '0', 'smtp_password' => 'new'),
		'security',
		1
	);

	pg_assert_same('{"strong_password":"1","password_hint":"1","forgot_password_link":"1","smtp_password":"***"}', $row['before_json'], 'before: flags readable, secret masked');
	pg_assert_same('{"strong_password":"0","password_hint":"","forgot_password_link":"0","smtp_password":"***"}', $row['after_json'], 'after: flags readable, secret masked');
}

// A secret's value is never kept, only that it is set or cleared; nested fields too.
function test_audit_redact_masks_secrets()
{
	pg_assert_same(
		array('smtp_password' => '***', 'api_key' => '', 'token' => null, 'name' => 'Mağaza', 'gateway' => array('secret' => '***', 'mode' => 'live')),
		pg_audit_redact(array('smtp_password' => 'hunter2', 'api_key' => '', 'token' => null, 'name' => 'Mağaza', 'gateway' => array('secret' => 'sk_live_1', 'mode' => 'live')))
	);
}

// A long text becomes its length, hash and opening, cut on a UTF-8 boundary.
function test_audit_compact_long_text()
{
	$limits = pg_audit_limits();
	$short = str_repeat('a', $limits['value']);
	$long = 'x' . str_repeat('ş', $limits['value']);

	pg_assert_same($short, pg_audit_compact($short), 'at the limit stays');
	pg_assert_same(42, pg_audit_compact(42), 'not a string');

	$compact = pg_audit_compact($long);

	pg_assert_true($compact['truncated'], 'marked');
	pg_assert_same(strlen($long), $compact['length'], 'length in bytes');
	pg_assert_same(sha1($long), $compact['sha1'], 'hash of the whole');
	pg_assert_true(strlen($compact['start']) <= $limits['start'], 'opening within its limit');
	pg_assert_true(mb_check_encoding($compact['start'], 'UTF-8'), 'opening is valid UTF-8');
	pg_assert_same(0, strpos($long, $compact['start']), 'opening is the start');
}

// Each side's JSON: null when empty, Unicode kept, the field names alone past the limit, bad UTF-8 survives.
function test_audit_json_sides()
{
	$limits = pg_audit_limits();

	pg_assert_same(null, pg_audit_json(array()), 'empty');
	pg_assert_same('{"name":"Çanta","url":"/a/b"}', pg_audit_json(array('name' => 'Çanta', 'url' => '/a/b')), 'unescaped');

	$wide = array();

	for ($i = 0; $i < 60; $i++) {
		$wide['field_' . $i] = str_repeat('y', $limits['value']);
	}

	pg_assert_same('{"truncated":true,"fields":["field_0","field_1"', substr(pg_audit_json($wide), 0, 47), 'names only past the limit');
	pg_assert_true(is_string(pg_audit_json(array('bad' => "a\xC3\x28b"))), 'invalid UTF-8 still encodes');
}

// An update keeps only what changed, secrets masked; a change that changed nothing is no row.
function test_audit_row_for_update()
{
	$row = pg_audit_row(
		'update',
		'settings',
		0,
		array('smtp_host' => 'mail.example.com', 'smtp_password' => 'old', 'smtp_port' => '25'),
		array('smtp_host' => 'mail.example.com', 'smtp_password' => 'new', 'smtp_port' => 587),
		"  E-mail\r\nsettings ",
		1760000000
	);

	pg_assert_same(array(
		'created_at'  => 1760000000,
		'action'      => 'update',
		'entity_type' => 'settings',
		'entity_id'   => 0,
		'label'       => 'E-mail settings',
		'before_json' => '{"smtp_password":"***","smtp_port":"25"}',
		'after_json'  => '{"smtp_password":"***","smtp_port":"587"}',
	), $row);

	pg_assert_same(null, pg_audit_row('update', 'product', 3, array('price' => 100), array('price' => '100')), 'no change');
}

// Create and delete keep one side; an event with no fields is still written.
function test_audit_row_for_create_delete_and_events()
{
	$create = pg_audit_row('create', 'page', 12, array(), array('name' => 'hakkimizda'), 'hakkimizda', 1);

	pg_assert_same(null, $create['before_json'], 'nothing before a create');
	pg_assert_same('{"name":"hakkimizda"}', $create['after_json'], 'after a create');

	$removed = pg_audit_row('delete', 'product', 9, array('name' => 'Kalem', 'price' => '1250'), array(), 'Kalem', 1);

	pg_assert_same('{"name":"Kalem","price":"1250"}', $removed['before_json'], 'before a delete');
	pg_assert_same(null, $removed['after_json'], 'nothing after a delete');

	$delete = pg_audit_row('delete', 'api_key', 4, array(), array(), 'Muhasebe', 1);

	pg_assert_same(array('created_at' => 1, 'action' => 'delete', 'entity_type' => 'api_key', 'entity_id' => 4, 'label' => 'Muhasebe', 'before_json' => null, 'after_json' => null), $delete, 'event without fields');
	pg_assert_same(0, pg_audit_row('delete', 'file', -8, array(), array())['entity_id'], 'negative id');
	pg_assert_same(255, mb_strlen(pg_audit_row('update', 'product', 1, array(), array(), str_repeat('ü', 300))['label'], 'UTF-8'), 'label cut to the column');
}

// Action and type are lower-case identifiers; anything else writes nothing.
function test_audit_row_refuses_bad_names()
{
	foreach (array(array('', 'product'), array('Update', 'product'), array('update', 'Product'), array('product.update', 'product'), array('update', 'pro duct'), array('1update', 'product'), array(str_repeat('a', 65), 'product'), array('update', str_repeat('b', 33))) as $pair) {
		pg_assert_same(null, pg_audit_row($pair[0], $pair[1], 1, array(), array()), implode(' / ', $pair));
	}
}

// Without the table (no connection here) nothing is written and nothing is thrown.
function test_audit_write_without_table()
{
	pg_assert_false(pg_audit_ready(), 'not ready without a database');
	pg_assert_false(pg_audit('update', 'product', 1, array('price' => 1), array('price' => 2), 'Kalem'), 'nothing written');
}

// One id per request; from the command line the actor is a job.
function test_audit_request_id_and_actor()
{
	$id = pg_audit_request_id();

	pg_assert_true(preg_match('/^[0-9a-f]{16,32}$/', $id) === 1, 'hex id');
	pg_assert_same($id, pg_audit_request_id(), 'same within the request');
	pg_assert_same(array('source' => 'job', 'user_id' => 0, 'username' => ''), pg_audit_actor(), 'command line');
}

// A save that changed nothing reads the same twice: the editor, the stamps and
// the derived SEO score are left out, every other column stays.
function test_audit_volatile_columns_left_out()
{
	$product = array('id' => '7', 'name' => 'SKU-7', 'price' => '1250', 'user' => '3', 'timestamp' => '1760000000', 'out_of_stock_timestamp' => '0', 'seo_score' => '82', 'seo_analysis' => '{}', 'enabled' => '1');

	pg_assert_same(array('id' => '7', 'name' => 'SKU-7', 'price' => '1250', 'enabled' => '1'), pg_audit_volatile('products', $product), 'product');
	pg_assert_same(array('page_id' => '4', 'page_name' => 'hakkimizda'), pg_audit_volatile('page', array('page_id' => '4', 'page_name' => 'hakkimizda', 'page_user' => '1', 'page_timestamp' => '9', 'layout_modified' => '1')), 'page');
	pg_assert_same(array('title' => 'Mağaza', 'smtp_password' => 'x'), pg_audit_volatile('config', array('title' => 'Mağaza', 'smtp_password' => 'x', 'last_modified_user_id' => '1', 'last_modified_timestamp' => '9', 'software_update_available' => '1')), 'config');
	pg_assert_same(array('user_id' => '2', 'user_role' => '2', 'user_password' => 'h'), pg_audit_volatile('user', array('user_id' => '2', 'user_role' => '2', 'user_password' => 'h', 'user_user' => '1', 'user_timestamp' => '9', 'token' => 't', 'user_online_timestamp' => '9')), 'user');
	pg_assert_same(array('id' => '1', 'status' => 'active'), pg_audit_volatile('api_apps', array('id' => '1', 'status' => 'active', 'last_used_ip' => '10.0.0.1', 'last_used_timestamp' => '9', 'updated_timestamp' => '9')), 'api app');
	pg_assert_same(array('timestamp_label' => 'x'), pg_audit_volatile('files', array('timestamp_label' => 'x', 'timestamp' => '9')), 'only a column that ends in timestamp');
	pg_assert_same(array('page_title' => 'Hakkımızda'), pg_audit_volatile('page', array('page_title' => 'Hakkımızda', 'seo_flags' => '34', 'seo_checked_at' => '9', 'seo_meta_score' => '62', 'seo_depth' => '2')), 'derived page SEO columns');
	pg_assert_same(array('price' => '100'), pg_audit_volatile('products', array('price' => '100', 'seo_struct_score' => '40', 'seo_link_score' => '10')), 'derived product SEO columns');
	pg_assert_same(array('seo_flags' => '1'), pg_audit_volatile('files', array('seo_flags' => '1')), 'seo_ prefix only where it is derived');
}

// Every mapped table names a record type the trail accepts, and the label is the
// first non-empty name column.
function test_audit_tables_and_labels()
{
	foreach (pg_audit_tables() as $table => $spec) {
		pg_assert_true(pg_audit_row('update', $spec['type'], 1, array(), array()) !== null, $table . ' type is an identifier');
	}

	pg_assert_same('Deri Kalem Kılıfı', pg_audit_label_of('products', array('name' => '543', 'short_description' => 'Deri Kalem Kılıfı')), 'product by its description');
	pg_assert_same('543', pg_audit_label_of('products', array('name' => '543', 'short_description' => ' ')), 'product code when there is no description');
	pg_assert_same('1172', pg_audit_label_of('orders', array('order_number' => '1172', 'reference_code' => 'ABC')), 'order number');
	pg_assert_same('', pg_audit_label_of('config', array('title' => 'Mağaza')), 'settings have no label column');
	pg_assert_same('', pg_audit_label_of('nope', array('name' => 'x')), 'unknown table');
}

// Without a connection nothing is read and nothing is recorded.
function test_audit_record_without_table()
{
	pg_assert_same(array(), pg_audit_fetch('products', 1), 'no row');
	pg_assert_same(array(), pg_audit_fetch_many('page', array(1, 2)), 'no rows');
	pg_assert_same(array(), pg_audit_fetch('nope', 1), 'unknown table');
	pg_assert_false(pg_audit_record('update', 'products', 1, array('price' => '1')), 'nothing recorded');
	pg_assert_same(0, pg_audit_record_many('update', 'files', array(3 => array('folder' => '1'), 4 => array('folder' => '2'))), 'nothing recorded for many');
	pg_assert_same(0, pg_audit_record_many('update', 'files', array()), 'no rows');
}

// A column that holds JSON text is masked inside: a feed token in
// config.product_feeds must not reach the trail as part of a string.
function test_audit_redact_json_text()
{
	$feeds = '{"seller_name":"Mağaza","formats":{"openai_jsonl":{"enabled":1,"token_hash":"ab12","token_secret":"s3cr3t"}}}';

	pg_assert_same(
		array('product_feeds' => array('seller_name' => 'Mağaza', 'formats' => array('openai_jsonl' => array('enabled' => 1, 'token_hash' => '***', 'token_secret' => '***')))),
		pg_audit_redact(array('product_feeds' => $feeds)),
		'secrets inside JSON text'
	);
	pg_assert_same(array('list' => array('a', 'b')), pg_audit_redact(array('list' => '["a","b"]')), 'a JSON list');
	pg_assert_same(array('note' => '{not json'), pg_audit_redact(array('note' => '{not json')), 'text that only looks like JSON');
	pg_assert_same(array('n' => '12'), pg_audit_redact(array('n' => '12')), 'a number stays text');
}

// A page deleted with a large designer tree keeps its name, folder and title:
// the tree is kept as its length, hash and opening, never decoded into a side
// that passes the JSON limit.
function test_audit_large_json_text_is_compacted()
{
	$limits = pg_audit_limits();
	$nodes = array();

	for ($i = 0; strlen(json_encode($nodes)) < 100000; $i++) {
		$nodes[] = array('id' => 'n' . $i, 'tag' => 'div', 'text' => 'Bölüm ' . $i);
	}

	$tree = json_encode(array('type' => 'root', 'children' => $nodes));

	pg_assert_same(null, pg_audit_json_text($tree), 'over the JSON limit it is not decoded');
	pg_assert_true(strlen($tree) > $limits['json'], 'the fixture is past the limit');

	$row = pg_audit_row('delete', 'page', 7, array('page_name' => 'hakkimizda', 'page_folder' => '3', 'page_title' => 'Hakkımızda', 'page_tree_json' => $tree), array(), 'hakkimizda', 1);
	$before = json_decode($row['before_json'], true);

	pg_assert_same('hakkimizda', $before['page_name'] ?? null, '100 KB tree: name kept');
	pg_assert_same('Hakkımızda', $before['page_title'] ?? null, '100 KB tree: title kept');
	pg_assert_same(true, $before['page_tree_json']['truncated'] ?? null, '100 KB tree: compacted');
	pg_assert_same(sha1($tree), $before['page_tree_json']['sha1'] ?? null, '100 KB tree: hash of the text');

	// Under the JSON limit but over the value limit: decoded to be masked,
	// then kept compacted as the masked text.
	$medium = json_encode(array('token_secret' => 's3cr3t', 'children' => array_slice($nodes, 0, 400)));
	pg_assert_true((strlen($medium) > $limits['value']) && (strlen($medium) < $limits['json']), 'medium fixture size');

	$row = pg_audit_row('delete', 'page', 8, array('page_name' => 'iletisim', 'page_tree_json' => $medium), array(), 'iletisim', 1);
	$before = json_decode($row['before_json'], true);

	pg_assert_same('iletisim', $before['page_name'] ?? null, '40 KB tree: name kept');
	pg_assert_same(true, $before['page_tree_json']['truncated'] ?? null, '40 KB tree: compacted');
	pg_assert_true(strpos($row['before_json'], 's3cr3t') === false, '40 KB tree: the secret is not in the opening');
	pg_assert_same(0, strpos($before['page_tree_json']['start'] ?? '', '{"token_secret":"***"'), '40 KB tree: the opening is the masked text');
}

// Encrypted blobs and keys named private or license are secrets; columns that
// only contain those words are not.
function test_audit_private_and_license_names()
{
	foreach (array('push_vapid_private', 'ws_ai_license', 'license') as $secret) {
		pg_assert_true(pg_audit_secret_key($secret), $secret);
	}

	foreach (array('private_label', 'private_folder', 'grant_private_access', 'private_days', 'license_check') as $plain) {
		pg_assert_false(pg_audit_secret_key($plain), $plain);
	}

	pg_assert_same(array('title' => 'x'), pg_audit_volatile('config', array('title' => 'x', 'backup_remote_settings' => 'cipher:iv', 'parasut_credentials_enc' => 'cipher')), 'config ciphertext re-encrypted on every save');
}
