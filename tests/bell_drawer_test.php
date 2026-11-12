<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the bell's drawer: a Workspace row's short title and the place
 * on its second line (ws_bell_title()), the drawer's markup at the right
 * edge, and the rules that open and close it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');

// Who did what, and the channel apart from it.
function test_bell_drawer_short_title_names_who_and_what()
{
	$channel = array('thread' => false, 'name' => '#sales');

	pg_assert_same(array('title' => 'Ada mentioned you', 'place' => '#sales'), ws_bell_title('mention', 'Ada', $channel));
	pg_assert_same(array('title' => 'Stock bot asked you', 'place' => '#sales'), ws_bell_title('question_asked', 'Stock bot', $channel));
	pg_assert_same(array('title' => 'Ada asked you to say you read it', 'place' => '#sales'), ws_bell_title('ack_reminder', 'Ada', $channel));
	pg_assert_same(array('title' => 'Guest wrote', 'place' => '#sales'), ws_bell_title('guest_message', 'Guest', $channel));
	pg_assert_same(array('title' => 'Ada added you to a channel', 'place' => '#sales'), ws_bell_title('invited', 'Ada', $channel));
	pg_assert_same(array('title' => '@github answered you', 'place' => '#sales'), ws_bell_title('agent_answer', '@github', $channel));
}

// A discussion is named by the channel it is in, and said to be one.
function test_bell_drawer_short_title_names_a_discussion()
{
	pg_assert_same(
		array('title' => 'Ada mentioned you', 'place' => 'A discussion in #sales'),
		ws_bell_title('mention', 'Ada', array('thread' => true, 'name' => '#sales'))
	);

	// A discussion whose channel is gone has no place.
	pg_assert_same(
		array('title' => 'Ada mentioned you', 'place' => ''),
		ws_bell_title('mention', 'Ada', array('thread' => true, 'name' => ''))
	);
	pg_assert_same(array('title' => 'Ada mentioned you', 'place' => ''), ws_bell_title('mention', 'Ada', null));
}

// A kind without a short form, or nobody named, keeps the whole sentence.
function test_bell_drawer_short_title_falls_back()
{
	$channel = array('thread' => false, 'name' => '#sales');
	$none = array('title' => '', 'place' => '');

	pg_assert_same($none, ws_bell_title('assigned', 'Ada', $channel));
	pg_assert_same($none, ws_bell_title('flow_finished', 'Ada', $channel));
	pg_assert_same($none, ws_bell_title('mention', '', $channel));
	pg_assert_same($none, ws_bell_title('mention', '   ', $channel));
}

// The describe cases hand the short title what it needs, the endpoint
// sends it as the row's title, and the push banner keeps the sentence.
function test_bell_drawer_short_title_reaches_the_row()
{
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
	$agent = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/mcp_agent.php');
	$display = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/notifications.php');
	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/notifications.php');

	pg_assert_contains("\$short = ws_bell_title(\$kind, (string) (\$described['who'] ?? ''), \$described['place'] ?? null);", $notify);
	pg_assert_contains("'short' => \$short['title'],", $notify);
	pg_assert_contains("ws_mcp_agent_inbox_describe(\$viewer, \$row, \$channel_name, \$base) + array('place' => \$place);", $notify);
	pg_assert_contains("'who'   => '@' . (\$server ? \$server['alias'] : 'mcp'),", $agent);
	pg_assert_contains("\$display['short']       = h((string) (\$described['short'] ?? ''));", $display);
	pg_assert_contains("\$display['title']       = h(\$described['title']);", $display);
	pg_assert_contains("'title' => ((\$display['short'] !== '') ? \$display['short'] : \$display['title']),", $endpoint);
	pg_assert_contains("'place' => \$display['place'],", $endpoint);
}
