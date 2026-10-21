<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Evernote exports brought in as notes
 * (includes/workspace/notes_enex.php): the export's dates, the names its
 * attachments are kept under, ENML written the workspace's way, the Markdown
 * file a note becomes, and an export read as a stream into a stage, with the
 * files it refuses.
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

// ENML in its usual wrapping.
function workspace_notes_enex_test_enml($inner)
{
	return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<!DOCTYPE en-note SYSTEM "http://xml.evernote.com/pub/enml2.dtd">' . "\n"
		. '<en-note>' . $inner . '</en-note>';
}

function workspace_notes_enex_test_text($inner, $media = array())
{
	$markdown = ws_note_enex_markdown(workspace_notes_enex_test_enml($inner), $media);

	return $markdown['text'];
}

// A stage folder of its own, removed by the caller.
function workspace_notes_enex_test_dir()
{
	$dir = sys_get_temp_dir() . '/pg_enex_test_' . bin2hex(random_bytes(4));
	mkdir($dir, 0755);

	return $dir;
}

function workspace_notes_enex_test_remove($dir)
{
	foreach (array_merge((array) glob($dir . '/files/*'), (array) glob($dir . '/*')) as $path) {
		if (is_file($path)) {
			unlink($path);
		}
	}

	if (is_dir($dir . '/files')) {
		rmdir($dir . '/files');
	}

	if (is_dir($dir)) {
		rmdir($dir);
	}
}

// A note of an export: its title, ENML, and resources as bytes => [mime, name].
function workspace_notes_enex_test_note($title, $enml, $resources = array(), $extra = '')
{
	$xml = '<note><title>' . htmlspecialchars($title, ENT_XML1, 'UTF-8') . '</title>'
		. '<content><![CDATA[' . workspace_notes_enex_test_enml($enml) . ']]></content>'
		. '<created>20231001T101010Z</created><updated>20231002T080000Z</updated>' . $extra;

	foreach ($resources as $resource) {
		$xml .= '<resource><data encoding="base64">' . "\n" . chunk_split(base64_encode($resource[0]), 76, "\n") . '</data>'
			. '<mime>' . $resource[1] . '</mime>'
			. (($resource[2] !== '') ? '<resource-attributes><file-name>' . htmlspecialchars($resource[2], ENT_XML1, 'UTF-8') . '</file-name></resource-attributes>' : '')
			. '</resource>';
	}

	return $xml . '</note>';
}

function workspace_notes_enex_test_export($notes)
{
	return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
		. '<!DOCTYPE en-export SYSTEM "http://xml.evernote.com/pub/evernote-export4.dtd">' . "\n"
		. '<en-export export-date="20231015T123456Z" application="Evernote" version="10.60.4">' . "\n"
		. implode("\n", $notes) . "\n</en-export>\n";
}

function test_workspace_notes_enex_time()
{
	pg_assert_same(gmmktime(12, 34, 56, 10, 15, 2023), ws_note_enex_time('20231015T123456Z'), 'compact UTC');
	pg_assert_same(gmmktime(12, 34, 56, 10, 15, 2023), ws_note_enex_time(' 20231015T123456Z '), 'spaces around');
	pg_assert_same(gmmktime(12, 34, 56, 10, 15, 2023), ws_note_enex_time('2023-10-15T12:34:56Z'), 'ISO with separators');
	pg_assert_same(0, ws_note_enex_time(''), 'none');
	pg_assert_same(0, ws_note_enex_time('19800101T000000Z'), 'before notes were written');
	pg_assert_same(0, ws_note_enex_time('soon'), 'not a time');
}

function test_workspace_notes_enex_extension()
{
	pg_assert_same('png', ws_note_enex_extension('image/png'));
	pg_assert_same('jpg', ws_note_enex_extension('IMAGE/JPEG; charset=binary'), 'case and parameters');
	pg_assert_same('pdf', ws_note_enex_extension('application/pdf'));
	pg_assert_same('m4a', ws_note_enex_extension('audio/x-m4a'));
	pg_assert_same('docx', ws_note_enex_extension('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
	pg_assert_same('bin', ws_note_enex_extension('application/vnd.evernote.ink'), 'a type of its own');
	pg_assert_same('bin', ws_note_enex_extension(''), 'no type');
}

function test_workspace_notes_enex_file_name()
{
	$taken = array();

	pg_assert_same('photo.jpg', ws_note_enex_file_name('photo.JPG', 'image/jpeg', 1, $taken), 'the given name, its extension lower-cased');
	pg_assert_same('Photo (2).jpg', ws_note_enex_file_name('Photo.jpg', 'image/jpeg', 2, $taken), 'a second file of the name, whatever its case');
	pg_assert_same('attachment-3.png', ws_note_enex_file_name('', 'image/png', 3, $taken), 'no name: one from its place and type');
	pg_assert_same('scan.pdf', ws_note_enex_file_name('scan', 'application/pdf', 4, $taken), 'no extension: one from the type');
	pg_assert_same('c-d-e.pdf', ws_note_enex_file_name('a/b\\c:d*e?.pdf', 'application/pdf', 5, $taken), 'a path and what a link cannot hold');
	pg_assert_same('hidden.bin', ws_note_enex_file_name('.hidden', '', 6, $taken), 'no hidden file');
	pg_assert_same('rapor-12-Mart.docx', ws_note_enex_file_name('rapor #12 [Mart].docx', 'application/msword', 7, $taken), 'hash, percent and brackets replaced');
	pg_assert_same('Fatura 2023.pdf', ws_note_enex_file_name('Fatura 2023.pdf', 'application/pdf', 8, $taken), 'spaces and letters kept');

	$long = ws_note_enex_file_name(str_repeat('ş', 150) . '.txt', 'text/plain', 9, $taken);

	pg_assert_same(100 + 4, mb_strlen($long), 'a long name shortened to 100 characters and its extension');
}

function test_workspace_notes_enex_lines_and_marks()
{
	pg_assert_same(
		"One\nTwo **bold** and *it* and ~~gone~~ and `code`\n\nThree",
		workspace_notes_enex_test_text('<div>One</div><div>Two <b>bold</b> and <i>it</i> and <s>gone</s> and <code>code</code></div><div><br/></div><div>Three</div>'),
		'a div is a line, an empty div an empty line');

	pg_assert_same("a\nb", workspace_notes_enex_test_text('<div>a<br/>b<br/></div>'), 'a break that ends a block adds no line');
	pg_assert_same('**bold** text', workspace_notes_enex_test_text('<div><b>bold </b>text</div>'), 'marks inside the spaces');
	pg_assert_same('**twice**', workspace_notes_enex_test_text('<div><b><strong>twice</strong></b></div>'), 'never two marks');
	pg_assert_same('**B** *I* ~~S~~', workspace_notes_enex_test_text('<div><span style="font-weight: bold;">B</span> <span style="font-style:italic">I</span> <span style="text-decoration: underline line-through">S</span></div>'), 'marks in a style');
	pg_assert_same('a b & c © &unknown;', workspace_notes_enex_test_text('<div>a&nbsp;b &amp; c &copy; &unknown;</div>'), 'entities spelt out without the DTD');
	pg_assert_same('[encrypted text]', workspace_notes_enex_test_text('<div><en-crypt hint="x" cipher="AES">c2VjcmV0</en-crypt></div>'), 'encrypted text is not brought in');
}

function test_workspace_notes_enex_links_and_pictures()
{
	pg_assert_same('[Site](https://example.com/a%20b%28c%29)', workspace_notes_enex_test_text('<div><a href="https://example.com/a b(c)">Site</a></div>'), 'a web link, what would end it encoded');
	pg_assert_same('[x@y.test](mailto:x@y.test)', workspace_notes_enex_test_text('<div><a href="mailto:x@y.test"></a></div>'), 'a mail link without words');
	pg_assert_same('Other note', workspace_notes_enex_test_text('<div><a href="evernote:///view/1/s1/abc/abc/">Other note</a></div>'), 'a link into Evernote keeps its words');
	pg_assert_same('[Logo](https://example.com/l.png)', workspace_notes_enex_test_text('<div><img src="https://example.com/l.png" alt="Logo"/></div>'), 'a web picture of a clip');
	pg_assert_same('', workspace_notes_enex_test_text('<div><img src="data:image/png;base64,AAAA"/></div>'), 'an inline picture is left out');
}

function test_workspace_notes_enex_checklists()
{
	pg_assert_same(
		"- [x] Done\n- [ ] Open\nMid [ ] box",
		workspace_notes_enex_test_text('<div><en-todo checked="true"/>Done</div><div><en-todo checked="false"/>Open</div><div>Mid <en-todo/> box</div>'),
		'en-todo boxes');

	pg_assert_same(
		"- [x] A\n- [ ] B",
		workspace_notes_enex_test_text('<ul style="--en-todo:true;"><li style="--en-checked:true;"><div>A</div></li><li style="--en-checked:false;"><div>B</div></li></ul>'),
		'a checklist of the newer editor');
}

function test_workspace_notes_enex_lists_headings_and_quotes()
{
	pg_assert_same(
		"- a\n- b\n  - c\n\n3. x\n4. y",
		workspace_notes_enex_test_text('<ul><li>a</li><li><div>b</div><ul><li>c</li></ul></li></ul><ol start="3"><li>x</li><li>y</li></ol>'),
		'lists, a list inside an item indented');

	pg_assert_same(
		"## Title **x**\n\nPara one\n\nPara two\n\n> quoted",
		workspace_notes_enex_test_text('<h2>Title <b>x</b></h2><p>Para one</p><p>Para two</p><blockquote>quoted</blockquote>'),
		'headings and paragraphs as Markdown, left to the importer');

	pg_assert_same("above\n\n---\n\nbelow", workspace_notes_enex_test_text('<div>above</div><hr/><div>below</div>'), 'a rule stands apart, never under a line');
}

function test_workspace_notes_enex_tables_and_code()
{
	pg_assert_same(
		"| Name | Qty |\n| --- | --- |\n| Elma \\| yeşil | 3 |\n| Toplam |  |",
		workspace_notes_enex_test_text('<table><tr><th>Name</th><th>Qty</th></tr><tr><td>Elma | yeşil</td><td><div>3</div></td></tr><tr><td colspan="2">Toplam</td></tr></table>'),
		'a table, a | escaped, a spanning cell padded');

	pg_assert_same(
		"```\nline 1\n  indented\n```",
		workspace_notes_enex_test_text("<pre>line 1\n  indented\n</pre>"),
		'pre');

	pg_assert_same(
		"```\na = 1\n\n  b\n```",
		workspace_notes_enex_test_text('<div style="box-sizing: border-box; -en-codeblock:true;"><div>a = 1</div><div><br/></div><div>  b</div></div>'),
		'a code block of the newer editor keeps its spaces and its empty line');
}

function test_workspace_notes_enex_media()
{
	$media = array(
		array('hash' => 'aaaa', 'name' => 'pic.png', 'link' => 'attachments/1/pic.png'),
		array('hash' => 'bbbb', 'name' => 'My file.pdf', 'link' => 'attachments/1/My%20file.pdf'),
	);

	$markdown = ws_note_enex_markdown(workspace_notes_enex_test_enml('<div><en-media hash="AAAA" type="image/png"/></div><div>see <en-media hash="cccc" type="application/pdf"/>here</div>'), $media);

	pg_assert_same("[pic.png](attachments/1/pic.png)\nsee here", $markdown['text'], 'found by its hash; an unknown hash shows nothing');
	pg_assert_same(array(0), $markdown['used'], 'the files the text shows');

	$markdown = ws_note_enex_markdown(workspace_notes_enex_test_enml('<div><en-media type="application/pdf"/></div>'), $media);

	pg_assert_same('[pic.png](attachments/1/pic.png)', $markdown['text'], 'a box without a hash takes the next file');
}

// Malformed ENML is still read; a document type cannot reach a file.
function test_workspace_notes_enex_malformed_and_entities()
{
	$text = workspace_notes_enex_test_text('<div>a<b>bold</div><div>c</div>');

	pg_assert_contains('a**bold**', $text, 'read by the HTML parser');
	pg_assert_contains('c', $text);

	$markdown = ws_note_enex_markdown('<?xml version="1.0"?><!DOCTYPE en-note [<!ENTITY x SYSTEM "file:///etc/passwd">]><en-note><div>&x;</div></en-note>');

	pg_assert_same('&x;', $markdown['text'], 'an entity the note declares is its words');
}

// The Markdown file a note becomes reads back as that note.
function test_workspace_notes_enex_file_reads_back()
{
	$file = ws_note_enex_file(
		array('title' => 'Teklif: "Mart"', 'created' => gmmktime(10, 10, 10, 10, 1, 2023), 'updated' => gmmktime(8, 0, 0, 10, 2, 2023), 'tags' => array('iş', ' müşteri ', '')),
		"- [x] gönderildi\n**not**");

	pg_assert_contains("title: \"Teklif: \\\"Mart\\\"\"\n", $file, 'the title quoted');
	pg_assert_contains("created: 2023-10-01T10:10:10Z\n", $file);
	pg_assert_contains("tags:\n  - \"iş\"\n  - \"müşteri\"\n---\n", $file, 'empty tags left out');

	$parsed = ws_note_import_parse(ws_note_import_text($file), 'Teklif - Mart.md', 0);

	pg_assert_same('Teklif: "Mart"', $parsed['title']);
	pg_assert_same(gmmktime(10, 10, 10, 10, 1, 2023), $parsed['created']);
	pg_assert_same(gmmktime(8, 0, 0, 10, 2, 2023), $parsed['updated']);
	pg_assert_same("- [x] gönderildi\n**not**\n\n#iş #müşteri", $parsed['body'], 'the text, and the tags as words at its end');
}

// An export read into a stage: a Markdown file a note, every attachment a
// file of its own decoded across the reads, links pointed at them, and an
// attachment named like a note kept a file.
function test_workspace_notes_enex_stage_reads_an_export()
{
	$dir = workspace_notes_enex_test_dir();
	$picture = random_bytes(200000);
	$readme = "read me\n";

	$export = workspace_notes_enex_test_export(array(
		workspace_notes_enex_test_note('Alışveriş: liste', '<div><en-todo checked="true"/>Süt</div><div><en-media hash="' . md5($picture) . '" type="image/jpeg"/></div>',
			array(array($picture, 'image/jpeg', 'photo.JPG'), array($readme, 'text/plain', 'readme.txt')),
			'<tag>ev</tag><tag>market</tag>'),
		workspace_notes_enex_test_note('', '<div>No title</div>'),
		workspace_notes_enex_test_note('Alışveriş: liste', '<div>Again</div>', array(), '<task><title>Pay</title><taskStatus>completed</taskStatus></task>'),
	));

	file_put_contents($dir . '/source-0.enex', $export);

	$read = ws_note_enex_stage($dir, array($dir . '/source-0.enex'));

	pg_assert_true($read['ok'], 'read: ' . $read['error']);
	pg_assert_false(is_file($dir . '/source-0.enex'), 'the export is removed once read');

	$paths = array_map(function ($row) {
		return $row[0];
	}, $read['entries']);

	pg_assert_same(array('Alışveriş - liste.md', 'attachments/1/photo.jpg', 'attachments/1/readme.txt', 'note-2.md', 'Alışveriş - liste (3).md'), $paths, 'the notes and their attachments');

	$stage = array('dir' => $dir, 'zip' => false, 'entries' => $read['entries']);
	$picture_row = $read['entries'][1];

	pg_assert_same(strlen($picture), $picture_row[1], 'the picture has its size');
	pg_assert_same(1, $picture_row[4], 'an attachment is marked');
	pg_assert_same($picture, ws_note_import_read($stage, 1, strlen($picture)), 'the picture decoded whole across the reads');
	pg_assert_same($readme, ws_note_import_read($stage, 2, 100), 'the text file too');

	$index = ws_note_import_index($stage);

	pg_assert_same(array(0, 3, 4), $index['notes'], 'readme.txt is not read as a note');

	$parsed = ws_note_import_parse(ws_note_import_text(ws_note_import_read($stage, 0, WS_NOTE_IMPORT_TEXT_MAX)), $paths[0], 0);

	pg_assert_same('Alışveriş: liste', $parsed['title']);
	pg_assert_same(gmmktime(10, 10, 10, 10, 1, 2023), $parsed['created']);
	pg_assert_same("- [x] Süt\n[photo.jpg](attachments/1/photo.jpg)\n\n[readme.txt](attachments/1/readme.txt)\n\n#ev #market", $parsed['body'], 'the picture where the note shows it, the other file at the end, the tags');

	$link = array('kind' => 'link', 'label' => 'readme.txt', 'target' => 'attachments/1/readme.txt', 'path' => 'attachments/1/readme.txt');

	pg_assert_same(array('kind' => 'file', 'entry' => 2), ws_note_import_target($stage, $index, 0, $link), 'a link to it is a file to keep with the note');

	$untitled = ws_note_import_parse(ws_note_import_text(ws_note_import_read($stage, 3, WS_NOTE_IMPORT_TEXT_MAX)), $paths[3], 0);

	pg_assert_same('note-2', $untitled['title'], 'a note without a title is named after its place');
	pg_assert_same('No title', $untitled['body']);

	$again = ws_note_import_parse(ws_note_import_text(ws_note_import_read($stage, 4, WS_NOTE_IMPORT_TEXT_MAX)), $paths[4], 0);

	pg_assert_same("Again\n\n- [x] Pay", $again['body'], 'a task of the newer editor as a checklist item');

	workspace_notes_enex_test_remove($dir);
}

function test_workspace_notes_enex_stage_refuses()
{
	$cases = array(
		'a document type of its own' => '<?xml version="1.0"?><!DOCTYPE en-export [<!ENTITY a "aaaaaaaa">]><en-export><note><title>&a;</title></note></en-export>',
		'not an export'              => '<?xml version="1.0"?><html><body>x</body></html>',
		'a broken export'            => '<?xml version="1.0"?><en-export><note><title>x</note></en-export>',
		'no notes'                   => '<?xml version="1.0"?><en-export></en-export>',
	);

	foreach ($cases as $label => $xml) {
		$dir = workspace_notes_enex_test_dir();
		file_put_contents($dir . '/source-0.enex', $xml);

		$read = ws_note_enex_stage($dir, array($dir . '/source-0.enex'));

		pg_assert_false($read['ok'], $label);
		pg_assert_same(array(), $read['entries'], $label . ': nothing staged');

		if ($label === 'no notes') {
			pg_assert_same('The Evernote export holds no notes.', $read['error'], $label);
		} else {
			pg_assert_contains('This is not an Evernote export (.enex).', $read['error'], $label);
		}

		if ($label === 'a broken export') {
			pg_assert_contains('Line 1:', $read['error'], 'where the export breaks');
		}

		workspace_notes_enex_test_remove($dir);
	}
}

function test_workspace_notes_enex_stage_refuses_a_padded_prolog()
{
	$padding = '<?xml version="1.0"?><!-- <en-export> ' . str_repeat('x', 9000) . ' -->';
	$note = '<note><title>&a;</title><content><![CDATA[' . workspace_notes_enex_test_enml('<div>x</div>') . ']]></content></note>';

	$cases = array(
		'an entity declared past the first 8 KB' => $padding . '<!DOCTYPE en-export [<!ENTITY a "EXPANDED">]><en-export>' . $note . '</en-export>',
		'a root other than en-export'            => $padding . '<html><body>x</body></html>',
	);

	foreach ($cases as $label => $xml) {
		$dir = workspace_notes_enex_test_dir();
		file_put_contents($dir . '/source-0.enex', $xml);

		$read = ws_note_enex_stage($dir, array($dir . '/source-0.enex'));

		pg_assert_false($read['ok'], $label);
		pg_assert_contains('This is not an Evernote export (.enex).', $read['error'], $label);
		pg_assert_false(strpos($read['error'], 'Line') !== false, $label . ': refused before it is parsed, not as broken XML');
		pg_assert_same(array(), array_values((array) glob($dir . '/files/*')), $label . ': nothing written');

		workspace_notes_enex_test_remove($dir);
	}
}

function test_workspace_notes_enex_read_stops_at_the_attachment_limit()
{
	$dir = workspace_notes_enex_test_dir();
	mkdir($dir . '/files', 0755);
	file_put_contents($dir . '/source-0.enex', workspace_notes_enex_test_export(array(
		'<note><title>Many</title><content><![CDATA[' . workspace_notes_enex_test_enml('<div>x</div>') . ']]></content>'
			. str_repeat('<resource/>', 20) . '</note>',
	)));

	$context = array(
		'dir'      => $dir,
		'next'     => 0,
		'notes'    => 0,
		'taken'    => array(),
		'entries'  => array_fill(0, WS_NOTE_IMPORT_ENTRIES_MAX - 5, array('x.md', 0, 0, 0)),
		'file_max' => 20971520,
		'error'    => '',
	);

	pg_assert_false(ws_note_enex_read($dir . '/source-0.enex', $context), 'refused');
	pg_assert_contains('more than', $context['error'], 'the limit named');
	pg_assert_true(count((array) glob($dir . '/files/*')) <= 4, 'no file opened past the limit');

	ws_note_import_remove($dir);
	workspace_notes_enex_test_remove($dir);
}

function test_workspace_notes_enex_table_width_is_capped()
{
	$text = workspace_notes_enex_test_text('<table><tr><td colspan="2000000">a</td></tr></table>');

	pg_assert_true(strlen($text) < 1000, 'a huge colspan cut to a short line');
	pg_assert_true(strpos($text, '| a |') !== false, 'the cell kept');
}
