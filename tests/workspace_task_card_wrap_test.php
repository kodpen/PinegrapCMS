<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A task card in a channel (ws_task_card_html() in
 * includes/workspace/tasks.php) wraps a long word or link in its title
 * inside the card, as the message body beside it does, instead of running
 * past the card's border.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_workspace_task_card_title_breaks_a_long_word()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/tasks.php');

	pg_assert_contains('<div class="ws-task-card-title text-break">\' . h($brief[\'title\']) . \'</div>', $source, 'the title breaks a word wider than the card');

	// The panel's Bootstrap 6 still ships the utility the title relies on.
	$bootstrap = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-6/css/bootstrap.min.css');
	pg_assert_contains('.text-break{word-wrap:break-word;word-break:break-word}', $bootstrap, 'text-break breaks a long word');
}
