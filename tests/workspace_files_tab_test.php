<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's Files tab (drawFiles() in assets/js/workspace.js):
 * sound files under their own title, the row's sender and time on one line,
 * the gallery drawn a page at a time and the count beside the tab's name.
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

function test_workspace_files_tab_gallery_is_drawn_a_page_at_a_time()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_contains('var FILE_PAGE = 60;', $source, 'a page is 60 pictures');

	$body = workspace_files_tab_test_body();
	pg_assert_contains('images.slice(shown, shown + FILE_PAGE).forEach(', $body, 'only the next page of pictures is drawn');
	pg_assert_false(strpos($body, 'images.forEach(') !== false, 'never every picture at once');
	pg_assert_contains("t('files_more_pictures', Math.min(FILE_PAGE, images.length - shown))", $body, 'Show more says how many come next');
	pg_assert_contains("more.addEventListener('click', drawPage);", $body, 'and draws them');
	pg_assert_contains('viewImages(gallery, index);', $body, 'the viewer still steps through every picture');
	pg_assert_contains("img.decoding = 'async';", $body, 'a picture is decoded off the main thread');
	pg_assert_contains("img.loading = 'lazy';", $body, 'and loaded when it comes near');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');
	pg_assert_true((bool) preg_match('/\n\.ws-gallery-tile \{[^}]*aspect-ratio: 4 \/ 3;/', $css), 'a tile keeps its size before its picture arrives');

	$strings = ws_file_edit_js_strings();
	pg_assert_same('Show {1} more pictures', $strings['files_more_pictures'] ?? '', 'the button is a screen text');
}

function test_workspace_files_tab_shows_its_count()
{
	$channels = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/channels.php');
	preg_match('/\nfunction ws_channel_tab_counts\(\$channel\)\n\{\n(.*?)\n\}\n/s', $channels, $match);
	$counts = $match[1] ?? '';
	pg_assert_contains("'files'     => (int) db_value(\"SELECT COUNT(*) FROM ws_messages", $counts, 'the server counts the channel\'s files');
	pg_assert_contains("file_id > 0 AND deleted_at = 0", $counts, 'as the Files tab reads them');

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	pg_assert_contains("['files', counts.files, 'tab_files_count']", $source, 'the Files tab carries the mark');

	$strings = ws_file_edit_js_strings();
	pg_assert_same('{1} files', $strings['tab_files_count'] ?? '', 'its title is a screen text');
}
