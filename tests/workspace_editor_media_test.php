<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for a file of this site on a note line of its own in the writing
 * box (assets/js/workspace_editor.js): mediaLine() and fileKind() read a
 * line as ws_note_media_line() and ws_file_kind() do on the server,
 * parseBlocks() makes such a line a media card only in a box given the
 * reader, and the notes screen puts added files on a line of their own and
 * lists only the recordings without their own player.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_editor_link_keys_test.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/render.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/files.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/note_media.php');

// The box and the server read the same lines as files of this site.
function test_workspace_editor_media_line_twin()
{
	$lines = array(
		'[Plan.pdf](https://example.com/plan.pdf)',
		'  [Photo](http://Example.com/my%20file.png)  ',
		'[voice-20261108.webm](https://example.com/voice-20261108.webm)',
		'[x](https://example.com/a%zz.pdf)',
		'[x](https://other.com/plan.pdf)',
		'[x](https://example.com.evil.com/a.pdf)',
		'[x](https://example.com/folder/a.pdf)',
		'[x](https://example.com/a.pdf?download=1)',
		'[x](https://example.com/about)',
		'[x](https://example.com/.htaccess)',
		'[x](https://example.com/a%2Fb.pdf)',
		'See [x](https://example.com/a.pdf)',
		'[x](https://example.com/a.pdf) here',
		'- [x](https://example.com/a.pdf)',
		'https://example.com/a.pdf',
		'[clip](https://example.com/clip.MP4)',
		'[all](https://example.com/all.zip)',
		"[x](https://example.com/it's.pdf)",
	);
	$answer = workspace_editor_link_keys_test_run(array('fileKind', 'mediaLine'), 'cases.map(function (line) { return mediaLine(line, "https://example.com/"); })', $lines);

	if ($answer === null) {
		return;
	}

	foreach ($lines as $index => $line) {
		$server = ws_note_media_line($line, 'example.com/');

		if ($server !== null) {
			$server['kind'] = ws_file_kind($server['name']);
		}

		pg_assert_same($server, $answer[$index], 'mediaLine: ' . json_encode($line));
	}
}

// Only a note's box given the reader makes the line a card.
function test_workspace_editor_media_parse_blocks()
{
	$names = array('fenceOpen', 'fenceInfo', 'fenceClose', 'tableRow', 'tableAlignments', 'tableCells', 'startsBlock', 'listLine', 'textBlocks', 'parseBlocks', 'headingLine', 'ruleLine', 'fileKind', 'mediaLine');
	$text = "Intro\n[Cat](https://example.com/cat.jpg)\nSee [Cat](https://example.com/cat.jpg)\n[Away](https://other.com/a.jpg)";
	$vars = '';

	foreach (array('CALC_LANGUAGES', 'CHECK', 'TITLE', 'LISTS') as $name) {
		$vars .= workspace_editor_lists_test_var($name);
	}

	$tail = '(function () { (0, eval)(' . json_encode($vars) . '); var reader = function (line) { return mediaLine(line, "https://example.com/"); };'
		. ' return cases.map(function (one) { return parseBlocks(one, true, reader).map(function (block) { return [block.kind, block.markup]; }).concat([["plain"]], parseBlocks(one, true).map(function (block) { return [block.kind, block.markup]; })); }); })()';
	$answer = workspace_editor_link_keys_test_run($names, $tail, array($text));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(
		array('text', 'Intro'),
		array('media', '[Cat](https://example.com/cat.jpg)'),
		array('text', 'See [Cat](https://example.com/cat.jpg)'),
		array('text', '[Away](https://other.com/a.jpg)'),
		array('plain'),
		array('text', 'Intro'),
		array('text', '[Cat](https://example.com/cat.jpg)'),
		array('text', 'See [Cat](https://example.com/cat.jpg)'),
		array('text', '[Away](https://other.com/a.jpg)'),
	), $answer[0]);
}

// The notes screen wires the cards, puts added files on a line of their own
// and keeps a lone recording out of "Recordings in this note".
function test_workspace_editor_media_wiring()
{
	$editor = workspace_editor_lists_test_source();
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');
	$config = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/screen.php');

	pg_assert_true(strpos($editor, 'self.mediaOf = (self.options.headings && self.options.media)') !== false, 'only a note box with media reads lines as files');
	pg_assert_true(strpos($editor, 'mediaLine: mediaLine') !== false, 'the reader is shared with the screen');
	pg_assert_true(strpos($screen, 'media: options.media') !== false, 'richField() passes media on');
	pg_assert_true(strpos($screen, 'field.rich.insertLink(') === false, 'notes add files with insertFile()');
	pg_assert_same(2, substr_count($screen, 'field.rich.insertFile('), 'attach() and drawCanvas()');
	pg_assert_true(strpos($screen, '!inline[match[2]]') !== false, 'a lone recording is not listed again');
	pg_assert_true(strpos($config, "'files'     => URL_SCHEME . HOSTNAME_SETTING . PATH") !== false, 'the site\'s file address is in the config');
}
