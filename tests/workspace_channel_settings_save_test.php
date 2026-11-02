<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for saving the open channel's settings in workspace.js: the
 * channel is refreshed in place (refreshInfo()), not opened again, so an
 * open discussion and its &thread= address stay; the header's title (name,
 * icon, colour, purpose, tags) is drawn anew from the saved channel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The body of one method of the screen object in workspace.js.
function workspace_channel_settings_save_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($source, "\n        " . $name . ': function');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", $start);

	return substr($source, $start, ($end === false) ? 4000 : $end - $start);
}

function test_workspace_channel_settings_save_keeps_the_channel_open()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_same(0, preg_match('/channelForm\(channel, \{\}, function \(id\) \{ self\.reloadChannels\(id\); \}\)/', $source), 'saving a channel\'s settings does not reopen it');
	pg_assert_same(2, preg_match_all('/channelForm\(channel, \{\}, function \(id\) \{ self\.channelSaved\(id\); \}\)/', $source), 'the menu and the info panel go through channelSaved()');

	$saved = workspace_channel_settings_save_test_method('channelSaved');

	pg_assert_true($saved !== '', 'channelSaved() found');
	pg_assert_contains('refreshInfo(', $saved, 'the open channel is refreshed in place');
	pg_assert_contains('reloadChannels(', $saved, 'another channel is still opened');
}

function test_workspace_channel_settings_save_redraws_the_title()
{
	$head = workspace_channel_settings_save_test_method('refreshHead');

	pg_assert_contains('headTitle(', $head, 'the header title is drawn anew');
	pg_assert_contains('.ws-head-title', $head, 'the old title is replaced');

	$center = workspace_channel_settings_save_test_method('drawCenter');

	pg_assert_contains('headTitle(', $center, 'drawCenter() draws the same title');
}
