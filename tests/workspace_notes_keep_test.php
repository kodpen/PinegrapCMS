<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Google Keep notes brought in from a Takeout export
 * (includes/workspace/notes_keep.php): the times Keep writes, what makes a
 * file a Keep note, the title of an untitled note, and the Markdown a note's
 * text, checklist and web links become.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/refs.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_import.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_enex.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/notes_keep.php');

// A note as Takeout writes it, with the fields a test leaves out filled in.
function workspace_notes_keep_test_json($fields)
{
	return json_encode(array_merge(array(
		'color'                   => 'DEFAULT',
		'isTrashed'               => false,
		'isPinned'                => false,
		'isArchived'              => false,
		'title'                   => '',
		'userEditedTimestampUsec' => 1696155010000000,
		'createdTimestampUsec'    => 1696150000000000,
	), $fields), JSON_UNESCAPED_UNICODE);
}

function test_workspace_notes_keep_time()
{
	pg_assert_same(1696155010, ws_note_keep_time(1696155010123456), 'microseconds as a number');
	pg_assert_same(1696155010, ws_note_keep_time('1696155010123456'), 'and as a string');
	pg_assert_same(0, ws_note_keep_time(0), 'none');
	pg_assert_same(0, ws_note_keep_time('soon'), 'not a time');
	pg_assert_same(0, ws_note_keep_time(-5), 'before 1970');
}

function test_workspace_notes_keep_parse_tells_a_keep_note()
{
	pg_assert_same(null, ws_note_keep_parse('{"name":"package","version":"1.0"}'), 'another JSON file');
	pg_assert_same(null, ws_note_keep_parse('not json'), 'not JSON');
	pg_assert_same(null, ws_note_keep_parse('[1,2]'), 'a list');
	pg_assert_same(null, ws_note_keep_parse('{"textContent":"no time"}'), 'text without a time');

	$note = ws_note_keep_parse(workspace_notes_keep_test_json(array(
		'title'       => ' Alışveriş ',
		'textContent' => "Süt\r\nEkmek",
		'labels'      => array(array('name' => 'ev'), array('name' => ' '), array('name' => 'market')),
		'attachments' => array(array('filePath' => 'a1b2.jpeg', 'mimetype' => 'image/jpeg'), array('mimetype' => 'image/png')),
		'annotations' => array(array('source' => 'WEBLINK', 'title' => 'Tarif', 'url' => 'https://example.com/a b')),
	)));

	pg_assert_true(is_array($note), 'a Keep note');
	pg_assert_same('Alışveriş', $note['title']);
	pg_assert_same("Süt\nEkmek", $note['text'], 'line ends made plain');
	pg_assert_same(1696150000, $note['created']);
	pg_assert_same(1696155010, $note['updated']);
	pg_assert_same(array('ev', 'market'), $note['tags'], 'empty labels dropped');
	pg_assert_same(array(array('path' => 'a1b2.jpeg', 'mime' => 'image/jpeg')), $note['files'], 'an attachment without a path dropped');
	pg_assert_same(array(array('title' => 'Tarif', 'url' => 'https://example.com/a b')), $note['links']);
	pg_assert_false($note['trashed']);

	$trashed = ws_note_keep_parse(workspace_notes_keep_test_json(array('textContent' => 'x', 'isTrashed' => true)));

	pg_assert_true($trashed['trashed'], 'a note in the bin says so');

	$edited_only = ws_note_keep_parse(json_encode(array('textContent' => 'old', 'userEditedTimestampUsec' => 1500000000000000)));

	pg_assert_same(1500000000, $edited_only['created'], 'an older export without the creation time takes the edit time');
}

function test_workspace_notes_keep_title_of_an_untitled_note()
{
	pg_assert_same('Kept', ws_note_keep_title(array('title' => 'Kept', 'text' => 'First', 'list' => array())));
	pg_assert_same('First line', ws_note_keep_title(array('title' => '', 'text' => "\n  First line  \nSecond", 'list' => array())), 'the first line of the text');
	pg_assert_same('Milk', ws_note_keep_title(array('title' => '', 'text' => '', 'list' => array(array('text' => 'Milk', 'checked' => false)))), 'or the first item of a list');
	pg_assert_same('', ws_note_keep_title(array('title' => '', 'text' => '', 'list' => array())), 'nothing to take');
	pg_assert_same(80, mb_strlen(ws_note_keep_title(array('title' => '', 'text' => str_repeat('ğ', 200), 'list' => array()))), 'cut to 80 letters');
}

function test_workspace_notes_keep_body()
{
	$list = ws_note_keep_parse(workspace_notes_keep_test_json(array(
		'listContent' => array(
			array('text' => 'Süt', 'isChecked' => true),
			array('text' => "Ekmek\nkepekli", 'isChecked' => false),
			array('text' => ' ', 'isChecked' => false),
		),
		'annotations' => array(
			array('title' => 'Tarif [yeni]', 'url' => 'https://example.com/a b'),
			array('title' => '', 'url' => 'http://example.org'),
			array('title' => 'Bad', 'url' => 'javascript:alert(1)'),
		),
	)));

	pg_assert_same("- [x] Süt\n- [ ] Ekmek kepekli\n\n[Tarif (yeni)](https://example.com/a%20b)\n[http://example.org](http://example.org)", ws_note_keep_body($list), 'checklist items, then the web links; a script address is dropped');

	$plain = ws_note_keep_parse(workspace_notes_keep_test_json(array('textContent' => "Line one\n\nLine two\n")));

	pg_assert_same("Line one\n\nLine two", ws_note_keep_body($plain), 'plain text as written');

	$rich = ws_note_keep_parse(workspace_notes_keep_test_json(array(
		'textContent'     => 'Bold and plain',
		'textContentHtml' => '<p dir="ltr"><span style="font-weight:700;">Bold</span> and plain</p><p dir="ltr">Second</p>',
	)));

	pg_assert_same("**Bold** and plain\nSecond", ws_note_keep_body($rich), 'the rich text keeps its bold');
}

// A zip of the given files in a stage folder of its own, listed the way
// ws_note_import_stage() lists one; the caller removes the folder.
function workspace_notes_keep_test_zip($files)
{
	$dir = sys_get_temp_dir() . '/pg_keep_test_' . bin2hex(random_bytes(4));
	mkdir($dir, 0755);

	$archive = new ZipArchive();
	$archive->open($dir . '/source.zip', ZipArchive::CREATE);

	foreach ($files as $name => $bytes) {
		$archive->addFromString($name, $bytes);
	}

	$archive->close();
	$archive->open($dir . '/source.zip');

	$entries = array();

	for ($index = 0; $index < $archive->numFiles; $index++) {
		$stat = $archive->statIndex($index);
		$entries[] = array($stat['name'], (int) $stat['size'], (int) $stat['mtime'], $index);
	}

	return array($dir, $archive, $entries);
}

function workspace_notes_keep_test_remove($dir, $archive)
{
	$archive->close();

	foreach (array_merge((array) glob($dir . '/files/*'), (array) glob($dir . '/*')) as $path) {
		if (is_file($path)) {
			unlink($path);
		}
	}

	if (is_dir($dir . '/files')) {
		rmdir($dir . '/files');
	}

	rmdir($dir);
}

function test_workspace_notes_keep_stage_reads_a_takeout()
{
	$picture = random_bytes(5000);

	list($dir, $archive, $entries) = workspace_notes_keep_test_zip(array(
		'Takeout/Keep/Alışveriş.json' => workspace_notes_keep_test_json(array(
			'title'       => 'Alışveriş',
			'listContent' => array(array('text' => 'Süt', 'isChecked' => true)),
			'labels'      => array(array('name' => 'ev')),
			'attachments' => array(array('filePath' => 'a1.jpeg', 'mimetype' => 'image/jpeg'), array('filePath' => 'gone.png', 'mimetype' => 'image/png')),
		)),
		'Takeout/Keep/Alışveriş.html'                    => '<html>not read</html>',
		'Takeout/Keep/a1.jpg'                            => $picture,
		'Takeout/Keep/2023-10-01T10_00_00.000+03_00.json' => workspace_notes_keep_test_json(array('textContent' => "First line\nSecond line")),
		'Takeout/Keep/Çöp.json'                          => workspace_notes_keep_test_json(array('title' => 'Çöp', 'textContent' => 'binned', 'isTrashed' => true)),
		'Takeout/Keep/Labels.txt'                        => "ev\n",
	));

	$read = ws_note_keep_stage($dir, $archive, $entries);

	pg_assert_true($read['keep'], 'a Takeout of Keep notes');
	pg_assert_true($read['ok'], 'read: ' . $read['error']);

	$paths = array_map(function ($row) {
		return $row[0];
	}, $read['entries']);

	pg_assert_same(array('Alışveriş.md', 'attachments/1/a1.jpeg', 'First line.md'), $paths, 'the notes and the picture; the HTML copy, the labels list and the binned note are not read');

	$stage = array('dir' => $dir, 'zip' => false, 'entries' => $read['entries']);

	pg_assert_same(1, $read['entries'][1][4], 'the picture is marked as an attachment');
	pg_assert_same($picture, ws_note_import_read($stage, 1, strlen($picture)), 'the picture found under its other extension and copied whole');
	pg_assert_same(array(0, 2), ws_note_import_index($stage)['notes']);

	$list = ws_note_import_parse(ws_note_import_text(ws_note_import_read($stage, 0, WS_NOTE_IMPORT_TEXT_MAX)), $paths[0], 0);

	pg_assert_same('Alışveriş', $list['title']);
	pg_assert_same(1696150000, $list['created']);
	pg_assert_same("- [x] Süt\n\n[a1.jpeg](attachments/1/a1.jpeg)\n[gone.png](attachments/1/gone.png)\n\n#ev", $list['body'], 'the checklist, then the pictures (the missing one keeps its link), then the label');

	$untitled = ws_note_import_parse(ws_note_import_text(ws_note_import_read($stage, 2, WS_NOTE_IMPORT_TEXT_MAX)), $paths[2], 0);

	pg_assert_same('First line', $untitled['title'], 'an untitled note is named by its first line');
	pg_assert_same("First line\nSecond line", $untitled['body']);

	workspace_notes_keep_test_remove($dir, $archive);
}

function test_workspace_notes_keep_stage_caps_the_bytes_it_copies()
{
	$picture = random_bytes(3000);

	// One picture named three times: each name would copy it out again.
	list($dir, $archive, $entries) = workspace_notes_keep_test_zip(array(
		'Takeout/Keep/Pictures.json' => workspace_notes_keep_test_json(array(
			'title'       => 'Pictures',
			'textContent' => '',
			'attachments' => array(
				array('filePath' => 'p.png', 'mimetype' => 'image/png'),
				array('filePath' => 'p.png', 'mimetype' => 'image/png'),
				array('filePath' => 'p.png', 'mimetype' => 'image/png'),
			),
		)),
		'Takeout/Keep/p.png' => $picture,
	));

	$read = ws_note_keep_stage($dir, $archive, $entries, 7000);

	pg_assert_true($read['ok'], 'read: ' . $read['error']);
	pg_assert_same(4, count($read['entries']), 'the note and its three pictures');

	$stage = array('dir' => $dir, 'zip' => false, 'entries' => $read['entries']);

	pg_assert_same($picture, ws_note_import_read($stage, 1, 3000), 'the first copy');
	pg_assert_same($picture, ws_note_import_read($stage, 2, 3000), 'the second, still under the ceiling');
	pg_assert_false(ws_note_import_read($stage, 3, 3000), 'the third would pass the ceiling: counted, not copied');
	pg_assert_same(3, count(glob($dir . '/files/*')), 'two pictures and the note on disk');

	workspace_notes_keep_test_remove($dir, $archive);
}

function test_workspace_notes_keep_stage_leaves_other_zips()
{
	list($dir, $archive, $entries) = workspace_notes_keep_test_zip(array(
		'vault/note.md'      => "# Note\n",
		'vault/package.json' => '{"name":"x","version":"1.0"}',
	));

	$read = ws_note_keep_stage($dir, $archive, $entries);

	pg_assert_false($read['keep'], 'a zip without a Keep note is read as it is');
	pg_assert_false(is_dir($dir . '/files'), 'and nothing is written for it');

	workspace_notes_keep_test_remove($dir, $archive);

	list($dir, $archive, $entries) = workspace_notes_keep_test_zip(array(
		'Takeout/Keep/Çöp.json' => workspace_notes_keep_test_json(array('textContent' => 'binned', 'isTrashed' => true)),
	));

	$read = ws_note_keep_stage($dir, $archive, $entries);

	pg_assert_true($read['keep']);
	pg_assert_false($read['ok'], 'only binned notes: nothing to bring in');
	pg_assert_same(lang('The Google Keep export holds no notes outside the bin.'), $read['error']);

	workspace_notes_keep_test_remove($dir, $archive);
}
