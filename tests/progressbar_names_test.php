<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace task progress bars say what they count: the task card drawn in
 * channel messages (ws_task_card_html() in includes/workspace/tasks.php)
 * and the channel board card (assets/js/workspace_board_channel.js) put
 * their "done of total" sentence on the role="progressbar" element as its
 * aria-label, not only in a hover title a screen reader does not read.
 *
 * Read from source: the board card is built in the browser.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_progressbar_names_task_card_bar_is_named()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/tasks.php');

	pg_assert_true(preg_match('/<div class="progress" role="progressbar" aria-label="\' \. h\(\$share_label\) \. \'"/', $source) === 1, 'the task card progress bar carries the "items done" sentence as its aria-label');
	pg_assert_true(strpos($source, "\$share_label = lang(array('string' => '{var:1} of {var:2} items done'") !== false, 'the task card bar names itself with the existing "items done" sentence');
}

function test_progressbar_names_board_card_bar_is_named()
{
	foreach (array('workspace_board_channel.js', 'workspace_board_channel.min.js') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file);

		pg_assert_true(preg_match("/setAttribute\(\s*['\"]aria-label['\"]\s*,\s*\w+\s*\)/", $source) === 1 && strpos($source, "progress_text") !== false, $file . ': the board card progress bar gets an aria-label');
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace_board_channel.js');

	pg_assert_true(strpos($source, "bar.setAttribute('aria-label', shareText)") !== false, 'the board card bar is named with the progress_text sentence');
	pg_assert_true(strpos($source, "var shareText = t('progress_text', item.progress.done, item.progress.total, item.progress.percent)") !== false, 'the board card sentence is t(\'progress_text\')');
}
