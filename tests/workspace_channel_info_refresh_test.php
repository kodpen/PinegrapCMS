<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests on the Workspace screen script, that need no browser: a change made
 * from the channel info panel refreshes it in place (the channel is not
 * opened again, so the focus stays in the panel), its buttons say whom and
 * what they act on, and "Who has seen it" with nobody but the writer shows
 * one line.
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
function workspace_channel_info_refresh_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$start = strpos($source, "\n        " . $name . ': function');

	if ($start === false) {
		return '';
	}

	$end = strpos($source, "\n        },\n", $start);

	return substr($source, $start, ($end === false) ? 4000 : $end - $start);
}

function test_workspace_channel_info_refresh_in_place()
{
	$info = workspace_channel_info_refresh_test_method('drawInfo');

	pg_assert_true($info !== '', 'drawInfo() found');
	pg_assert_false(strpos($info, 'self.open(') !== false, 'the panel does not open the channel again');
	pg_assert_false(strpos($info, "ws_channel_notify', { channel_id: channel.id, notify: option[0] }).then(function () { self.reloadChannels") !== false, 'the notification choice does not reload the channel');
	pg_assert_true(substr_count($info, 'self.refreshInfo(') >= 3, 'notify, add and remove refresh the panel');
	pg_assert_contains("t('member_remove', member.name)", $info, 'the remove button names the person');
	pg_assert_contains("t('add_people_confirm')", $info, 'the add button differs from the one that opens the picker');

	$refresh = workspace_channel_info_refresh_test_method('refreshInfo');

	pg_assert_contains("api('ws_channel_get'", $refresh, 'the channel is read without opening it');
	pg_assert_contains('data-info-key', $refresh, 'the focus is put back by its key');
}

function test_workspace_channel_info_refresh_seen_by_alone()
{
	$seen = workspace_channel_info_refresh_test_method('seenBy');

	pg_assert_contains("t('seen_by_alone')", $seen, 'one line when nobody but the writer is in the channel');

	$texts = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	foreach (array('seen_by_alone', 'member_remove', 'add_people_confirm') as $key) {
		pg_assert_contains("'" . $key . "'", $texts, $key . ' has its text');
	}
}
