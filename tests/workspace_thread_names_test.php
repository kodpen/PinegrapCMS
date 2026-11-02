<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for how a Workspace discussion (a ws_channels row of kind thread) is
 * named outside its panel: a bell line names the channel the discussion is
 * in and says it was a discussion, never the discussion's title as a
 * channel; the discussion panel's writing box has its own placeholder.
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

// A channel is named as itself; a discussion by the channel it is in, and
// flagged; a discussion whose channel is gone is still flagged, unnamed.
function test_workspace_thread_names_inbox_place()
{
	pg_assert_same(array('thread' => false, 'name' => '#sales'), ws_inbox_place(array('id' => 4, 'kind' => 'public', 'name' => 'sales'), null));
	pg_assert_same(array('thread' => true, 'name' => '#sales'), ws_inbox_place(array('id' => 9, 'kind' => 'thread', 'name' => 'Talk about the price'), array('id' => 4, 'kind' => 'public', 'name' => 'sales')));
	pg_assert_same(array('thread' => true, 'name' => ''), ws_inbox_place(array('id' => 9, 'kind' => 'thread', 'name' => 'Talk about the price'), null));
	pg_assert_same(array('thread' => false, 'name' => ''), ws_inbox_place(null, null));
}

// The bell lines that name a place say "a discussion in #channel" for a
// discussion; the discussion panel's box does not say "Write to #<title>".
function test_workspace_thread_names_sources()
{
	$notify = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notify.php');
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_contains("'{var:1} mentioned you in a discussion in {var:2}'", $notify);
	pg_assert_contains("'{var:1} asked you to say you read a message in a discussion in {var:2}'", $notify);
	pg_assert_false(strpos($notify, "\$channel_name = \$channel ? '#' . \$channel['name'] : '';") !== false, 'a discussion title is never written as a channel name');
	pg_assert_contains("'thread_composer_placeholder'", $screen);
	pg_assert_contains("var placeholder = self.isThread ? t('thread_composer_placeholder') : t('composer_placeholder', self.channel.name);", $js);
	pg_assert_false(strpos($js, "placeholder: t('composer_placeholder', self.channel.name)") !== false, 'the rich box takes the shared placeholder');
}
