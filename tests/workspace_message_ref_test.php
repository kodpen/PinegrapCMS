<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Workspace tag of another message (<#message:N>,
 * includes/workspace/message_refs.php): a token like the record tags, what
 * a reader of the message's channel sees (sender, time, channel, kind, a
 * short text), what anybody else sees (its number and "You cannot see this
 * message."), a deleted message, the words of ws_plain_text() and the
 * export, the popup behind the same right, and that the tag is no answer.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');
require_once(__DIR__ . '/helpers/source_reader.php');

// What a reader of the channel was given of message 42.
function workspace_message_ref_test_shown(array $over = array())
{
	return ws_message_ref(42, 'shown', array_merge(array(
		'channel_id' => 7, 'channel' => 'sales', 'sender' => 'Ayşe Yılmaz', 'timestamp' => 0,
		'kind' => 'message', 'file_kind' => '', 'poll' => false, 'text' => 'Which picture do you mean?',
	), $over), '/pg/');
}

function test_workspace_message_ref_is_a_tag_kind()
{
	pg_assert_contains('message', ws_tag_type_keys(), 'a tag kind');
	pg_assert_false(in_array('message', ws_record_type_keys(), true), 'not a site record: no change, no drawer, no customer');

	$tokens = ws_tokens('Look at <#message:42> and <#order:7>');
	pg_assert_same(array('sigil' => '#', 'type' => 'message', 'id' => 42), $tokens[0], 'found in a text');
	pg_assert_same('a  b', ws_tokens_normalise('a <@message:42> b'), 'never with @');
	pg_assert_same('<#message:42>', ws_tokens_normalise('<#message:42>'));
}

function test_workspace_message_ref_shown_to_a_reader_of_the_channel()
{
	$ref = workspace_message_ref_test_shown();

	pg_assert_true($ref['known'], 'known');
	pg_assert_false(!empty($ref['gone']), 'not gone');
	pg_assert_same('message', $ref['type']);
	pg_assert_same(lang(array('string' => 'Message #{var:1}', 'vars' => 42)), $ref['label'], 'its number');
	pg_assert_same('/pg/workspace.php?channel=7&message=42', $ref['url'], 'Copy link\'s address');
	pg_assert_same('Ayşe Yılmaz · #sales', $ref['meta'], 'sender and channel');
	pg_assert_same('Ayşe Yılmaz', $ref['message']['sender']);
	pg_assert_same('sales', $ref['message']['channel']);
	pg_assert_same(7, $ref['message']['channel_id']);
	pg_assert_same('message', $ref['message']['kind']);
	pg_assert_same('Which picture do you mean?', $ref['message']['text']);
	pg_assert_same($ref['label'] . ' (' . lang(array('string' => '{var:1} in {var:2}', 'vars' => array('Ayşe Yılmaz', '#sales'))) . ': “Which picture do you mean?”)',
		$ref['plain'], 'the words of push, e-mail and the API');

	$file = workspace_message_ref_test_shown(array('file_kind' => 'image', 'poll' => true, 'kind' => 'decision', 'sender' => '', 'text' => ''));
	pg_assert_same('image', $file['message']['file_kind'], 'a picture says so');
	pg_assert_true($file['message']['poll'], 'a poll says so');
	pg_assert_same('decision', $file['message']['kind']);
	pg_assert_same($file['label'] . ' (#sales)', $file['plain'], 'no sender, no words: the channel alone');
}

function test_workspace_message_ref_text_is_cut()
{
	$long = str_repeat('word ', 100);
	$text = workspace_message_ref_test_shown(array('text' => $long))['message']['text'];

	pg_assert_same(WS_MESSAGE_REF_TEXT, mb_strlen($text), 'cut to its length');
	pg_assert_same('…', mb_substr($text, -1), 'with an ellipsis');
	pg_assert_same('a b', ws_message_ref_cut(" a \n\n b "), 'one line');
}

function test_workspace_message_ref_hidden_from_anybody_else()
{
	$ref = ws_message_ref(42, 'hidden', array('channel' => 'secret', 'sender' => 'Ayşe', 'text' => 'secret words', 'channel_id' => 9), '/pg/');

	pg_assert_false($ref['known'], 'unknown');
	pg_assert_false(!empty($ref['gone']), 'not said to be gone');
	pg_assert_same(lang('You cannot see this message.'), $ref['meta']);
	pg_assert_same('', $ref['url'], 'no way to it');
	pg_assert_false(isset($ref['message']), 'nothing for the popup');
	pg_assert_false(strpos(json_encode($ref), 'secret') !== false, 'nothing of the channel or the words');
	pg_assert_false(strpos(json_encode($ref), 'Ay') !== false, 'nothing of the sender');
	pg_assert_same($ref['label'] . ' (' . lang('You cannot see this message.') . ')', $ref['plain']);
}

function test_workspace_message_ref_gone_says_so()
{
	$ref = ws_message_ref(42, 'gone');

	pg_assert_false($ref['known']);
	pg_assert_true($ref['gone'], 'gone');
	pg_assert_same(lang('This message was deleted.'), $ref['meta']);
	pg_assert_false(isset($ref['message']));
	pg_assert_same($ref['label'] . ' (' . lang('This message was deleted.') . ')', $ref['plain']);
}

// ws_plain_text() reads a message tag as its plain words; every other tag
// keeps its label.
function test_workspace_message_ref_plain_text_words()
{
	$refs = array(
		'message:42' => workspace_message_ref_test_shown(),
		'message:43' => ws_message_ref(43, 'hidden'),
		'order:7'    => array('type' => 'order', 'id' => 7, 'label' => 'Order #1007', 'url' => '', 'meta' => '', 'status' => '', 'known' => true, 'icon' => ''),
	);
	$text = ws_plain_text(array('id' => 1, 'role' => 0), 'See <#message:42>, <#message:43> and <#order:7>', $refs);

	pg_assert_contains($refs['message:42']['plain'], $text, 'the shown message with its words');
	pg_assert_contains($refs['message:43']['plain'], $text, 'the hidden one with its sentence');
	pg_assert_contains('and Order #1007', $text, 'a record tag as before');

	$export = ws_note_export_tokens('See <#message:42>', $refs, 'https://example.com');
	pg_assert_contains('[' . $refs['message:42']['label'] . '](https://example.com/pg/workspace.php?channel=7&message=42)', $export, 'the export links its number');
	pg_assert_contains('“Which picture do you mean?”', $export, 'and keeps its words');
}

// A lone message tag stays a chip: its popup draws the message itself.
function test_workspace_message_ref_no_tag_card()
{
	pg_assert_same('', ws_tag_card_html('<#message:42>', array('message:42' => workspace_message_ref_test_shown())));
}

// The reader's right is the channel's, decided before anything of the
// message is read out; deleted, deleted for the reader and a channel they
// cannot open are told apart; another message tag in the words is not
// followed; nothing is written. Its queries need a database: read from
// source.
function test_workspace_message_ref_resolve_reads_the_channel_right()
{
	$source = pg_test_function_source('ws_message_refs_resolve');

	pg_assert_contains('ws_can_read_channel($viewer, $channel)', $source, 'the channel\'s own right');
	pg_assert_contains('ws_message_hidden_ids(', $source, 'deleted for the reader');
	pg_assert_contains("\$state = (\$row && !\$channels[(int) \$row['channel_id']]) ? 'hidden' : 'gone';", $source, 'a deleted message of a closed channel stays unknown');
	pg_assert_contains("if (\$token['type'] === 'message') {", $source, 'nested message tags are not resolved again');
	pg_assert_contains('ws_thread_parent_channel(', $source, 'a discussion named after its channel');

	foreach (array('INSERT ', 'UPDATE ', 'DELETE ', '_notify', 'ws_event(') as $word) {
		pg_assert_false(strpos($source, $word) !== false, 'reading a tag writes and tells nothing: ' . $word);
	}

	$resolve = pg_test_function_source('ws_refs_resolve');
	pg_assert_contains("ws_message_refs_resolve(\$viewer, \$ids['message'])", $resolve, 'ws_refs_resolve() hands message tags over');
}

// The popup reads the message's payload only after the tag's right.
function test_workspace_message_ref_popup_behind_the_right()
{
	$source = pg_test_function_source('ws_message_ref_popup');
	$right = strpos($source, "if (empty(\$ref['known']))");
	$payload = strpos($source, 'ws_message_payloads(');

	pg_assert_true(($right !== false) && ($payload !== false) && ($right < $payload), 'the right first, then the payload');
	pg_assert_contains("'message' => null", $source, 'nothing for a reader without it');

	$actions = file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/actions.php');
	pg_assert_contains("'ws_message_ref'", $actions, 'a panel action');
	pg_assert_contains("ws_message_ref_popup(\$viewer, (int) (\$request['message_id'] ?? 0))", file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php'));
}

// A reference is no answer and no mention: the mention notifier and the
// application tell take people, departments and applications only.
function test_workspace_message_ref_is_not_a_mention()
{
	pg_assert_same(array(), ws_mentioned_app_ids(ws_tokens('<#message:42>')), 'no application is told');
	pg_assert_contains('<#message:', file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/api.php'), 'the API names the token');
}
