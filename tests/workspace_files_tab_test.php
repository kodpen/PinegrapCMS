<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a channel's Files tab (drawFiles() in assets/js/workspace.js):
 * sound files under their own title, the row's sender and time on one line,
 * the gallery drawn a page at a time, its tiles from small copies, the files
 * read from the server a page at a time and the count beside the tab's name.
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
	pg_assert_contains("more.addEventListener('click', showMore);", $body, 'and draws them');
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

function test_workspace_files_tab_tiles_ask_a_small_copy()
{
	pg_assert_same('/files/photo.jpg?w=320', ws_file_thumb_url('/files/photo.jpg'), 'a JPEG asks its 320 px copy');
	pg_assert_same('/files/shot.PNG?w=320', ws_file_thumb_url('/files/shot.PNG'), 'a PNG too, whatever the case');
	pg_assert_same('/files/a.webp?w=320', ws_file_thumb_url('/files/a.webp'), 'and a WebP');
	pg_assert_same('/files/canvas.png?v=3p&w=320', ws_file_thumb_url('/files/canvas.png?v=3p'), 'a canvas version keeps its mark');
	pg_assert_same('/files/moving.gif', ws_file_thumb_url('/files/moving.gif'), 'a GIF may move and stays');
	pg_assert_same('/files/logo.svg', ws_file_thumb_url('/files/logo.svg'), 'a vector stays');
	pg_assert_same('/files/a.jpg?w=960', ws_file_thumb_url('/files/a.jpg?w=960'), 'a width already asked stays');
	pg_assert_same(320, ws_file_thumb_width(), 'the copy get_file.php makes for a workspace file');

	$get_file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');
	pg_assert_contains("&& ((\$workspace_file === false) || isset(\$_GET['w']))", $get_file, 'get_file.php makes a workspace file\'s copy');
	pg_assert_contains("\$derivative_request = array('width' => 320, 'format' => '');", $get_file, 'only at 320 px in its own format');

	$body = workspace_files_tab_test_body();
	pg_assert_contains('img.src = message.file.thumb || message.file.url;', $body, 'a tile draws the small copy');
	pg_assert_contains('return { src: message.file.url,', $body, 'the viewer opens the original');

	$messages = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/messages.php');
	pg_assert_contains("'thumb'    => (\$kind === 'image') ? ws_file_thumb_url(", $messages, 'a picture\'s payload carries the copy\'s address');
}

function test_workspace_files_tab_reads_the_server_a_page_at_a_time()
{
	$rows = array();

	for ($id = 10; $id >= 1; $id--) {
		$rows[] = array('id' => $id);
	}

	$page = ws_channel_files_page_cut($rows, 4);
	pg_assert_same(array(10, 9, 8, 7), array_column($page['rows'], 'id'), 'a page keeps the newest rows');
	pg_assert_true($page['more'], 'and says older ones are left');
	pg_assert_same(7, $page['before'], 'the next page starts below the last row kept');

	$last = ws_channel_files_page_cut(array_slice($rows, 6), 4);
	pg_assert_same(array(4, 3, 2, 1), array_column($last['rows'], 'id'), 'the last page holds what is left');
	pg_assert_false($last['more'], 'and says nothing is left');

	$empty = ws_channel_files_page_cut(array(), 4);
	pg_assert_same(array(), $empty['rows'], 'an empty channel has an empty page');
	pg_assert_same(0, $empty['before'], 'and no cursor');
	pg_assert_same(200, ws_channel_files_page_size(), 'a page is 200 files');

	$actions = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/actions.php');
	pg_assert_contains("ws_channel_files_page(\$viewer, \$channel['id'], (int) (\$request['before'] ?? 0))", $actions, 'the tab\'s action reads a page');

	$body = workspace_files_tab_test_body();
	pg_assert_contains('params.before = cursor;', $body, 'the next page asks from where the last one ended');
	pg_assert_contains('cursor = data.before || 0;', $body, 'the cursor is the server\'s');
	pg_assert_contains('loaded = loaded.concat(data.messages || []);', $body, 'pages add up');
	pg_assert_contains("t('files_older')", $body, 'Show older files asks the next page');

	$strings = ws_file_edit_js_strings();
	pg_assert_same('Show older files', $strings['files_older'] ?? '', 'the button is a screen text');
}
