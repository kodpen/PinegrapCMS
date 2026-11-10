<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the note group a Workspace API message shares
 * (note_group_card): the card of a shared group with the notes the reader
 * reads, null on a deleted message or one sharing no group, and the schema
 * that declares it.
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

// One entry of ws_note_group_cards_map(), as the channel screen gets it.
function ws_api_note_group_card_test_map()
{
	return array(
		6101 => array(
			'group_id' => 41,
			'name'     => 'Launch',
			'hex'      => '#3b82f6',
			'owner'    => array('id' => 7, 'name' => 'Ada', 'avatar' => 'x.png'),
			'notes'    => array(
				array('id' => 88, 'name' => 'Venue', 'updated' => 'today', 'updated_at' => 1767225600),
				array('id' => 89, 'name' => 'Guests', 'updated' => 'today', 'updated_at' => 0),
			),
			'url'      => '/pinegrap/workspace_notes.php?group=41',
		),
	);
}

// A message that shares a group carries the group and the notes the reader reads, in order.
function test_ws_api_note_group_card_of_a_shared_group()
{
	$card = ws_api_message_note_group_card(array('id' => 6101, 'deleted_at' => 0), ws_api_note_group_card_test_map());

	pg_assert_same(array(
		'group_id' => 41,
		'name'     => 'Launch',
		'color'    => '#3b82f6',
		'owner'    => array('user_id' => 7, 'name' => 'Ada'),
		'notes'    => array(
			array('id' => 88, 'name' => 'Venue', 'updated_at' => '2026-01-01T00:00:00Z'),
			array('id' => 89, 'name' => 'Guests', 'updated_at' => null),
		),
	), $card, 'the shared group');
}

// No card on a message sharing no group, on a deleted message, or when the owner is gone.
function test_ws_api_note_group_card_null_and_owner_cases()
{
	$map = ws_api_note_group_card_test_map();

	pg_assert_same(null, ws_api_message_note_group_card(array('id' => 6102, 'deleted_at' => 0), $map), 'no group');
	pg_assert_same(null, ws_api_message_note_group_card(array('id' => 6101, 'deleted_at' => 1767225600), $map), 'deleted message');

	$map[6101]['owner'] = null;
	$card = ws_api_message_note_group_card(array('id' => 6101, 'deleted_at' => 0), $map);
	pg_assert_same(null, $card['owner'], 'owner gone');
}

// The message declares the card as a nullable named object of the card's keys.
function test_ws_api_note_group_card_is_declared_on_the_message()
{
	$schema = ws_api_message_schema();
	$objects = ws_openapi_objects();
	$card = ws_api_message_note_group_card(array('id' => 6101, 'deleted_at' => 0), ws_api_note_group_card_test_map());

	pg_assert_same('WorkspaceMessageNoteGroupCard?', $schema['note_group_card'] ?? null, 'note_group_card on WorkspaceMessage');
	pg_assert_same('ws_api_message_note_group_card_schema', $objects['WorkspaceMessageNoteGroupCard'] ?? null, 'the component');
	pg_assert_same(array_keys($card), array_keys(ws_api_message_note_group_card_schema()), 'the card keys');
	pg_assert_same(array_keys($card['notes'][0]), array_keys(ws_api_message_note_group_card_schema()['notes'][0]), 'the note keys');
}
