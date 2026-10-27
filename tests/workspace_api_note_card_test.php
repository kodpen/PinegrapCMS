<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the note a Workspace API message shares (note_card): the card
 * of a shared note, null once the share is taken back, null on a deleted
 * message, and the schema that declares it.
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

// One entry of ws_note_cards_map(), as the channel screen gets it.
function ws_api_note_card_test_map()
{
	return array(
		5531 => array(
			'id'         => 88,
			'name'       => 'Launch checklist',
			'excerpt'    => 'Book the venue',
			'html'       => '<p>Book the venue</p>',
			'access'     => 'view',
			'owner'      => 'Ada',
			'updated_by' => 'Ada',
			'updated'    => 'today',
		),
	);
}

// A message that shares a note carries its id, name and excerpt.
function test_ws_api_note_card_of_a_shared_note()
{
	$card = ws_api_message_note_card(array('id' => 5531, 'deleted_at' => 0), ws_api_note_card_test_map());

	pg_assert_same(array('id' => 88, 'name' => 'Launch checklist', 'excerpt' => 'Book the venue'), $card, 'the shared note');
}

// A share taken back leaves no ws_note_shares row, so the map has no entry.
function test_ws_api_note_card_is_null_once_the_share_is_taken_back()
{
	pg_assert_same(null, ws_api_message_note_card(array('id' => 5531, 'deleted_at' => 0), array()), 'share taken back');
	pg_assert_same(null, ws_api_message_note_card(array('id' => 5532, 'deleted_at' => 0), ws_api_note_card_test_map()), 'a message sharing no note');
}

// A deleted message shows no card, as on the channel screen.
function test_ws_api_note_card_is_null_on_a_deleted_message()
{
	pg_assert_same(null, ws_api_message_note_card(array('id' => 5531, 'deleted_at' => 1767225600), ws_api_note_card_test_map()), 'deleted message');
}

// The message declares the card as a nullable object of the card's keys.
function test_ws_api_note_card_is_declared_on_the_message()
{
	$schema = ws_api_message_schema();
	$objects = ws_openapi_objects();

	pg_assert_same('WorkspaceMessageNoteCard?', $schema['note_card'] ?? null, 'note_card on WorkspaceMessage');
	pg_assert_same('ws_api_message_note_card_schema', $objects['WorkspaceMessageNoteCard'] ?? null, 'the component');
	pg_assert_same(array('id', 'name', 'excerpt'), array_keys(ws_api_message_note_card_schema()), 'the card keys');
}
