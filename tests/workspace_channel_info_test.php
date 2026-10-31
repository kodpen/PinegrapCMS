<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source tests for the channel's info beside the conversation (showMembers()
 * and drawInfo() in assets/js/workspace.js): a panel in the discussion's
 * place, one of the two at a time, closed with the views that have no
 * channel; its parts in their order and its texts.
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

// The body of one method of the channel screen in workspace.js, or ''.
function workspace_channel_info_test_method($name)
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n        ' . preg_quote($name, '/') . ': function \([^)]*\) \{\n(.*?)\n        \},\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

// A panel beside the conversation, not a drawer over it; the info button
// opens and closes it, and a discussion takes its place.
function test_workspace_channel_info_is_a_panel_beside_the_conversation()
{
	$show = workspace_channel_info_test_method('showMembers');
	$draw = workspace_channel_info_test_method('drawInfo');

	pg_assert_true($show !== '' && $draw !== '', 'showMembers() and drawInfo() are found');
	pg_assert_contains('app.closeInfo();', $show, 'the info button closes it again');
	pg_assert_contains('app.closeThread();', $show, 'a discussion beside the channel makes way');
	pg_assert_true(strpos($show . $draw, 'offcanvas(') === false, 'no drawer over the screen');
	pg_assert_contains("el('aside', 'ws-info-panel')", $draw);
	pg_assert_contains("self.root.classList.add('ws-info-open');", $draw);
	pg_assert_contains("event.key === 'Escape'", $draw, 'Escape closes it');
	pg_assert_contains('app.closeInfo();', workspace_channel_info_test_method('openThread'), 'a discussion opened closes it');

	foreach (array('showHome', 'showScheduled') as $view) {
		pg_assert_contains('self.closeInfo();', workspace_channel_info_test_method($view), $view . ' has no channel: the panel goes');
	}

	pg_assert_contains('self.drawInfo(false);', workspace_channel_info_test_method('drawCenter'), 'another channel shows its own info');
}

// The parts in the drawn order: purpose, status, people, notifications,
// read aloud, time spent, settings.
function test_workspace_channel_info_parts_in_their_order()
{
	$draw = workspace_channel_info_test_method('drawInfo');
	$marks = array("section(t('purpose'))", "section(t('info_status'))", "section(t('members')", "section(t('info_notify'))", "t('voice_speak')", "t('info_time')", "t('edit_channel')");
	$last = -1;

	foreach ($marks as $mark) {
		$at = strpos($draw, $mark);

		pg_assert_true($at !== false, $mark . ' is drawn');
		pg_assert_true($at > $last, $mark . ' comes after the part before it');
		$last = (int) $at;
	}

	pg_assert_contains("api('ws_channel_notify'", $draw, 'the notification choice is the channel menu\'s');
	pg_assert_contains('CFG.task_time && CFG.task_time.ready', $draw, 'the time spent only where the Summary tab shows it');
}

function test_workspace_channel_info_texts_are_given()
{
	$texts = ws_js_strings();

	foreach (array('info_close', 'info_status', 'info_notify', 'info_time', 'info_time_line', 'notify_short_all', 'notify_short_mentions', 'notify_short_none') as $key) {
		pg_assert_true(isset($texts[$key]), $key . ' is given');
	}
}
