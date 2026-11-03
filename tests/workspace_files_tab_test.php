<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's Files tab (drawFiles() in assets/js/workspace.js):
 * sound files under their own title, the row's sender and time on one line.
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

// The body of drawFiles() in workspace.js, or ''.
function workspace_files_tab_test_body()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	if (!preg_match('/\n        drawFiles: function \(\) \{\n(.*?)\n        \},\n/s', $source, $match)) {
		return '';
	}

	return $match[1];
}

function test_workspace_files_tab_lists_sounds_apart_from_documents()
{
	$body = workspace_files_tab_test_body();

	pg_assert_true($body !== '', 'drawFiles() is found');
	pg_assert_true(strpos($body, "message.file.kind === 'audio'") !== false, 'sound files are picked out');
	pg_assert_true(strpos($body, "message.file.kind !== 'video' && message.file.kind !== 'audio'") !== false, 'documents leave the sounds out');
	pg_assert_true(strpos($body, "fileRows(t('voice_and_audio'), audios);") !== false, 'the sounds have their own title');
	pg_assert_true(strpos($body, "fileRows(t('documents'), others);") !== false, 'the documents keep theirs');

	$strings = ws_js_strings();
	pg_assert_same('Voice messages and audio', $strings['voice_and_audio'] ?? '', 'the title is a screen text');
}

function test_workspace_files_tab_row_meta_stays_on_one_line()
{
	pg_assert_true(strpos(workspace_files_tab_test_body(), "'fg-secondary ws-grow ws-file-meta'") !== false, 'the sender and time carry the one-line class');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_true((bool) preg_match('/\.ws-file-row \.ws-file-meta \{[^}]*white-space: nowrap;[^}]*\}/', $css), 'the class keeps one line');
	pg_assert_true((bool) preg_match('/\.ws-file-row \.ws-file-meta \{[^}]*text-overflow: ellipsis;[^}]*\}/', $css), 'and cuts it short');
}
