<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that Workspace texts name the button or list they mean by its
 * label, not by its icon, its shape or its place on the screen: the tour,
 * the empty channel and note panes, the MCP tools pane, the Claude setup
 * step, the calendar address notice and the template task order.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function workspace_sensory_texts_test_source($path)
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $path);
}

function test_workspace_sensory_texts_old_wording_gone()
{
	$gone = array(
		'includes/workspace/tour.php'          => array('signpost', 'The + button', 'Point at a message to see'),
		'includes/workspace/screen.php'        => array('Choose a channel on the left'),
		'includes/workspace/notes.php'         => array('Pick a note on the left'),
		'includes/workspace/mcp.php'           => array('with the button above'),
		'includes/workspace/claude_setup.php'  => array('the eye on the request'),
		'workspace_calendar_feed.php'          => array('with the buttons below'),
		'includes/workspace/templates.php'     => array('drag the task down'),
	);

	foreach ($gone as $path => $phrases) {
		$source = workspace_sensory_texts_test_source($path);
		pg_assert_true($source !== '', $path . ' is read');

		foreach ($phrases as $phrase) {
			pg_assert_true(strpos($source, $phrase) === false, $path . ' no longer says: ' . $phrase);
		}
	}
}

function test_workspace_sensory_texts_name_the_control()
{
	$named = array(
		'includes/workspace/tour.php' => array(
			'watch it again with the Tour of the workspace button.',
			'The New channel button starts a channel:',
			'Point at a message, or move to it with the Tab key, to see what it can do:',
			'the Tour of the workspace button plays it again.',
		),
		'includes/workspace/screen.php' => array('Choose a channel from the channel list, or create one.'),
		'includes/workspace/notes.php' => array('Pick a note from the note list, or start a new one.'),
		'includes/workspace/mcp.php' => array('Ask the server for them with the Ask the server for its tools again button.'),
		'includes/workspace/claude_setup.php' => array('the eyes reaction (👀) on the request means Claude has taken it.'),
		'workspace_calendar_feed.php' => array(
			'Add it to your calendar with Add to Google Calendar or Open in Apple Calendar or Outlook.',
			'Add it to your calendar with Open in Apple Calendar or Outlook, or copy it.',
		),
		'includes/workspace/templates.php' => array('move this task below it with the Drag to change the order button: drag it, or use the arrow keys on it.'),
	);

	foreach ($named as $path => $phrases) {
		$source = workspace_sensory_texts_test_source($path);

		foreach ($phrases as $phrase) {
			pg_assert_contains($phrase, $source, $path . ' says: ' . $phrase);
		}
	}
}

function test_workspace_sensory_texts_name_real_labels()
{
	// The labels the texts name are the controls' own labels.
	pg_assert_contains("'tour_start' => lang('Tour of the workspace')", workspace_sensory_texts_test_source('includes/workspace/tour.php'), 'tour button label');
	pg_assert_contains("'new_channel'            => lang('New channel')", workspace_sensory_texts_test_source('includes/workspace/screen.php'), 'new channel label');
	pg_assert_contains("title=\"' . h(lang('Ask the server for its tools again')) . '\"", workspace_sensory_texts_test_source('includes/workspace/mcp.php'), 'refresh button label');
	pg_assert_contains("'tpl_drag'            => lang('Drag to change the order')", workspace_sensory_texts_test_source('includes/workspace/templates.php'), 'grip label');
	$feed = workspace_sensory_texts_test_source('workspace_calendar_feed.php');
	pg_assert_contains("h(lang('Add to Google Calendar'))", $feed, 'Google button label');
	pg_assert_contains("h(lang('Open in Apple Calendar or Outlook'))", $feed, 'Apple button label');
	pg_assert_contains("ws_ai_react((int) \$row['message_id'], '👀', true);", workspace_sensory_texts_test_source('includes/workspace/ai.php'), 'the taken mark is the eyes reaction');
}
