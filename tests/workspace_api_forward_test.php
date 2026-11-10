<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the forwarded copies a Workspace API message carries (forwards):
 * one item per frozen copy, the source channel left out when the reader
 * cannot read it, none on a deleted message, and the schema that declares
 * them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/scopes.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');

// One ws_message_forwards row.
function ws_api_forward_test_row($sender_kind = 'user')
{
	return array(
		'message_id'         => 7001,
		'position'           => 0,
		'source_message_id'  => 6990,
		'source_channel_id'  => 12,
		'source_sender_kind' => $sender_kind,
		'source_sender_id'   => 4,
		'source_created_at'  => 1767225600,
		'body'               => 'Book the <@user:4> venue',
		'file_name'          => 'plan.pdf',
	);
}

// A copy from a channel the reader reads names the channel and its writer.
function test_ws_api_forward_item_from_a_readable_channel()
{
	$item = ws_api_message_forward_item(ws_api_forward_test_row(), array('id' => 12, 'name' => 'sales'), true, array('name' => 'Ada'), 'Book the Ada venue');

	pg_assert_same(array(
		'source_message_id'   => 6990,
		'source_channel_id'   => 12,
		'source_channel_name' => 'sales',
		'can_open'            => true,
		'sender'              => array('user_id' => 4, 'name' => 'Ada'),
		'text'                => 'Book the Ada venue',
		'file_name'           => 'plan.pdf',
		'created_at'          => '2026-01-01T00:00:00Z',
	), $item, 'the copy');
}

// A channel the reader cannot read is not named: the frozen words still show.
function test_ws_api_forward_item_hides_an_unreadable_channel()
{
	$item = ws_api_message_forward_item(ws_api_forward_test_row(), null, true, array('name' => 'Ada'), 'Book the venue');

	pg_assert_same(null, $item['source_message_id'], 'no source message');
	pg_assert_same(null, $item['source_channel_id'], 'no source channel');
	pg_assert_same(null, $item['source_channel_name'], 'no channel name');
	pg_assert_same(false, $item['can_open'], 'cannot open');
	pg_assert_same('Book the venue', $item['text'], 'the frozen words');
	pg_assert_false(array_key_exists('body', $item), 'no raw body');
}

// An application's copy has no user id; a writer that is gone is null.
function test_ws_api_forward_item_sender_kinds()
{
	$app = ws_api_message_forward_item(ws_api_forward_test_row('app'), null, false, array('name' => 'Bot'), '');
	$gone = ws_api_message_forward_item(ws_api_forward_test_row(), null, false, null, '');

	pg_assert_same(array('user_id' => null, 'name' => 'Bot'), $app['sender'], 'application');
	pg_assert_same(null, $gone['sender'], 'writer gone');
}

// A message lists its own copies; a deleted message or one without any lists none.
function test_ws_api_message_forwards_picks_the_message()
{
	$map = array(7001 => array(array('text' => 'a'), array('text' => 'b')));

	pg_assert_same(2, count(ws_api_message_forwards(array('id' => 7001, 'deleted_at' => 0), $map)), 'its copies');
	pg_assert_same(array(), ws_api_message_forwards(array('id' => 7002, 'deleted_at' => 0), $map), 'no copies');
	pg_assert_same(array(), ws_api_message_forwards(array('id' => 7001, 'deleted_at' => 1767225600), $map), 'deleted message');
}

// The message declares forwards as a list of the item's keys.
function test_ws_api_forwards_are_declared_on_the_message()
{
	$schema = ws_api_message_schema();
	$item = ws_api_message_forward_item(ws_api_forward_test_row(), null, false, null, '');

	pg_assert_true(isset($schema['forwards'][0]) && is_array($schema['forwards'][0]), 'forwards is a list of objects');
	pg_assert_same(array_keys($item), array_keys($schema['forwards'][0]), 'the item keys');
}
