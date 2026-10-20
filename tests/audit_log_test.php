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
	foreach (array('password', 'smtp_password', 'Password_Confirm', 'api_key', 'apikey', 'google_maps_api_key', 'recaptcha_secret_key', 'client_secret', 'mfa_secret', 'token', 'auth_token', 'parasut_credentials_enc', 'backup_remote_credentials', 'password_hash', 'user_pass', 'card_number', 'card_verification_number', 'cvv', 'pin', 'paypal_api_signature', 'key') as $secret) {
		pg_assert_true(pg_audit_secret_key($secret), $secret);
	}

	foreach (array('name', 'key_code', 'monkey', 'compass', 'bypass', 'passkeys', 'tokens_used', 'keywords', 'sort_order', 'email_address', 'encoding', 'hashtag') as $plain) {
		pg_assert_false(pg_audit_secret_key($plain), $plain);
	}
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
