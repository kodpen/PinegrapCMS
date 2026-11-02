<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that saving a channel's settings (the channel menu's and the info
 * panel's Edit channel) refreshes the screen in place instead of opening the
 * channel again: an open discussion and its &thread= address stay, and the
 * header's title (name, topic, icon, colour, tags) is drawn anew (#5190).
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
function workspace_channel_save_test_method($source, $name)
{
	$start = strpos($source, "\n        " . $name . ': function');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", $start);

	return substr($source, $start, ($end === false) ? 4000 : $end - $start);
}

function test_workspace_channel_save_in_place()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$menu = workspace_channel_save_test_method($js, 'channelMenu');
	$info = workspace_channel_save_test_method($js, 'drawInfo');
	$center = workspace_channel_save_test_method($js, 'drawCenter');
	$head = workspace_channel_save_test_method($js, 'refreshHead');
	$title = workspace_channel_save_test_method($js, 'headTitle');

	pg_assert_true($menu !== '' && $info !== '' && $center !== '' && $head !== '', 'methods found');
	pg_assert_false(strpos($menu, "channelForm(channel, {}, function (id) { self.reloadChannels(id); });") !== false, 'the menu\'s Edit channel does not open the channel again');
	pg_assert_false(strpos($info, "channelForm(channel, {}, function (id) { self.reloadChannels(id); });") !== false, 'the info panel\'s Edit channel does not open the channel again');
	pg_assert_true(substr_count($menu . $info, "channelForm(channel, {}, function () { self.refreshInfo(); });") === 2, 'both refresh in place');
	pg_assert_true($title !== '', 'the header title has its own method');
	pg_assert_contains('self.headTitle(channel)', $center, 'the header draws it');
	pg_assert_contains('self.headTitle(channel)', $head, 'refreshHead() draws it anew');
	pg_assert_contains("'--ws-ch'", $head, 'the colour follows');
}
