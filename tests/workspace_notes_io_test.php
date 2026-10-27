<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for notes going out as files and coming back in
 * (includes/workspace/notes_io.php): the YAML front matter, the file name a
 * note gets, the tags written out for a reader outside the workspace, the
 * links to a note's own files inside the zip, the Markdown file as a whole;
 * then a file read as a note: its text and dates, the Markdown written the
 * workspace's way, its links, and a changed export laid back over its note.
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

function test_workspace_notes_io_yaml_quotes_and_escapes()
{
	pg_assert_same('"Teklif: #12"', ws_note_export_yaml('Teklif: #12'), 'a colon and a hash stay inside the quotes');
	pg_assert_same('"a \\"b\\" c"', ws_note_export_yaml('a "b" c'), 'quotes escaped');
	pg_assert_same('"x\\\\y"', ws_note_export_yaml('x\\y'), 'a backslash escaped');
	pg_assert_same('"one\\ntwo"', ws_note_export_yaml("one\ntwo"), 'a line break written out');
	pg_assert_same('"Şükrü"', ws_note_export_yaml('Şükrü'), 'UTF-8 untouched');
}

function test_workspace_notes_io_file_name_is_safe_and_unique()
{
	$taken = array();

	pg_assert_same('Teklif hazırla', ws_note_export_name('Teklif hazırla', 7, $taken), 'a plain title');
	pg_assert_same('teklif Hazırla (8)', ws_note_export_name('teklif Hazırla', 8, $taken), 'the same title again, by the note id');
	pg_assert_same('Fiyat - sipariş - not', ws_note_export_name('Fiyat / sipariş: not?', 9, $taken), 'slashes, colons and question marks replaced');
	pg_assert_same('note-10', ws_note_export_name('...', 10, $taken), 'nothing left: the id');
	pg_assert_same(WS_NOTE_EXPORT_NAME_MAX, mb_strlen(ws_note_export_name(str_repeat('a', 200), 11, $taken)), 'cut to the limit');
}

function test_workspace_notes_io_tokens_become_links_and_names()
{
	$refs = array(
		'order:1045' => array('type' => 'order', 'id' => 1045, 'label' => 'Order #1045', 'url' => 'https://shop.test/pinegrap/view_order.php?id=1045', 'known' => true),
		'product:7'  => array('type' => 'product', 'id' => 7, 'label' => 'Product', 'url' => '', 'known' => false),
		'user:12'    => array('type' => 'user', 'id' => 12, 'label' => '@Ayşe Yılmaz', 'url' => '', 'known' => true),
		'dept:2'     => array('type' => 'dept', 'id' => 2, 'label' => '@Satış', 'url' => '', 'known' => true),
		'page:3'     => array('type' => 'page', 'id' => 3, 'label' => 'About [us]', 'url' => 'https://shop.test/about', 'known' => true),
		'contact:5'  => array('type' => 'contact', 'id' => 5, 'label' => 'Ali Veli', 'url' => '/pinegrap/edit_contact.php?id=5', 'known' => true),
	);

	$body = "See <#order:1045> and <#product:7>, ask <@user:12>; page <#page:3>; gone <#offer:99>; <#contact:5>; team <@dept:2>.";
	$out = ws_note_export_tokens($body, $refs, 'https://shop.test/');

	pg_assert_contains('[Order #1045](https://shop.test/pinegrap/view_order.php?id=1045)', $out, 'a record the reader may open is a link');
	pg_assert_contains('and Product,', $out, 'one they may not see is its generic name');
	pg_assert_contains('ask @Ayşe Yılmaz;', $out, 'a person is @name, its @ written once');
	pg_assert_contains('team @Satış.', $out, 'a department is @name too');
	pg_assert_false(strpos($out, '@@') !== false, 'no doubled @');
	pg_assert_contains('[About (us)](https://shop.test/about)', $out, 'brackets in a label are made round');
	pg_assert_contains('gone ;', $out, 'an unknown tag goes away');
	pg_assert_contains('[Ali Veli](https://shop.test/pinegrap/edit_contact.php?id=5)', $out, 'a link from the site root gets the host');
	pg_assert_false(strpos($out, '<#') !== false || strpos($out, '<@') !== false, 'no token left');
}

function test_workspace_notes_io_attachment_links_point_into_the_zip()
{
	$map = array('https://shop.test/files/brief.pdf' => 'attachments/12/brief.pdf', 'https://shop.test/files/a%20b.png' => '../attachments/12/a b.png');
	$body = "The brief: [brief.pdf](https://shop.test/files/brief.pdf) and [a b.png](https://shop.test/files/a%20b.png); outside: [site](https://example.test/x).";
	$out = ws_note_export_attachment_links($body, $map);

	pg_assert_contains('[brief.pdf](attachments/12/brief.pdf)', $out, 'a file of the note is a relative path');
	pg_assert_contains('[a b.png](../attachments/12/a%20b.png)', $out, 'a space in the path is encoded');
	pg_assert_contains('[site](https://example.test/x)', $out, 'another link is left alone');
	pg_assert_same($body, ws_note_export_attachment_links($body, array()), 'nothing to point at: untouched');
}

function test_workspace_notes_io_markdown_has_front_matter_then_the_body()
{
	$viewer = array('id' => 3, 'role' => 2);
	$note = array('id' => 12, 'user_id' => 0, 'title' => 'Teklif: hazırla', 'body' => "Line one\r\n- [ ] do <#order:1045>\r\n", 'created_at' => 1760000000, 'updated_at' => 1760003600);
	$refs = array('order:1045' => array('type' => 'order', 'id' => 1045, 'label' => 'Order #1045', 'url' => 'https://shop.test/o/1045', 'known' => true));

	$md = ws_note_export_markdown($viewer, $note, $refs, array(), array('source' => '#satış', 'empty' => ''));
	$lines = explode("\n", $md);

	pg_assert_same('---', $lines[0], 'opens the front matter');
	pg_assert_same('title: "Teklif: hazırla"', $lines[1], 'the title, quoted');
	pg_assert_same('created: ' . date('c', 1760000000), $lines[2], 'created as ISO 8601');
	pg_assert_same('updated: ' . date('c', 1760003600), $lines[3], 'updated as ISO 8601');
	pg_assert_contains("\nsource: \"#satış\"\n", $md, 'an extra line with a value');
	pg_assert_false(strpos($md, 'empty:') !== false, 'an extra line without a value is left out');
	pg_assert_contains("\npinegrap_note: 12\n---\n\n", $md, 'the note id, then the block closes and a blank line follows');
	pg_assert_contains("Line one\n- [ ] do [Order #1045](https://shop.test/o/1045)\n", $md, 'the body with its tags written out and LF line endings');
	pg_assert_true(substr($md, -1) === "\n" && substr($md, -2) !== "\n\n", 'ends with one line break');
}

function test_workspace_notes_io_import_text_is_utf8_with_lf()
{
	pg_assert_same("a\nb\nc", ws_note_import_text("\xEF\xBB\xBFa\r\nb\rc"), 'byte order mark dropped, CRLF and CR made LF');
	pg_assert_same('Şubat ığdır', ws_note_import_text("\xDEubat \xFD\xF0d\xFDr"), 'the Turkish Windows code page read as such');
	pg_assert_same('ab', ws_note_import_text("\xFF\xFEa\x00b\x00"), 'UTF-16 converted');
	pg_assert_same("x\ty", ws_note_import_text("x\ty\x00\x07"), 'control characters other than the tab dropped');
}

function test_workspace_notes_io_import_front_matter()
{
	$read = ws_note_import_front_matter("---\ntitle: \"Teklif: \\\"ekim\\\"\"\ntags:\n  - iş\n  - 'plan'\naliases: [a, \"b c\"]\nnote: plain # a comment\n---\nBody\n");

	pg_assert_same('Teklif: "ekim"', $read['meta']['title'], 'a quoted title with escapes');
	pg_assert_same(array('iş', 'plan'), $read['meta']['tags'], 'a list under its key');
	pg_assert_same(array('a', 'b c'), $read['meta']['aliases'], 'a flow list');
	pg_assert_same('plain', $read['meta']['note'], 'a comment after a plain value dropped');
	pg_assert_same("Body\n", $read['body'], 'the text under the block');

	$rule = "---\nJust a rule, then words\n---\nmore";
	pg_assert_same(array(), ws_note_import_front_matter($rule)['meta'], 'a block that is not YAML is no front matter');
	pg_assert_same($rule, ws_note_import_front_matter($rule)['body'], 'and the file is kept whole');
	pg_assert_same('x', ws_note_import_front_matter("---\n---\nx")['body'], 'an empty block');
}

function test_workspace_notes_io_import_times()
{
	pg_assert_same(1714545000, ws_note_import_time('2024-05-01T09:30:00+03:00'), 'ISO 8601');
	pg_assert_same(1714545000, ws_note_import_time('1714545000'), 'seconds');
	pg_assert_same(1714545000, ws_note_import_time('1714545000000'), 'milliseconds');
	pg_assert_same(0, ws_note_import_time('1970-01-02'), 'before 1990 is none');
	pg_assert_same(0, ws_note_import_time(date('c', time() + 3 * 86400)), 'days ahead is none');
	pg_assert_same(0, ws_note_import_time(array('x')), 'a list is none');
	pg_assert_same(0, ws_note_import_time('soon'), 'words are none');
}

function test_workspace_notes_io_import_markdown_written_the_workspace_way()
{
	$in = implode("\n", array(
		'## Agenda',
		'* one *two* __three__ ==four== `*five*`',
		'+ [ ] todo',
		'Setext',
		'===',
		'',
		'***',
		'```math',
		'x = *y*',
		'```',
		'~~~python title="a.py"',
		'print(1)',
		'~~~',
		'Inline ```code``` stays',
		'![](img/a%20b.png) ![logo](<pics/x y.png> "Logo")',
		'\\# not a heading \\[x\\]',
		'| a<br>b | c |',
		'#tag line',
	));

	$out = explode("\n", ws_note_import_convert($in));

	pg_assert_same('**Agenda**', $out[0], 'a heading is a bold line');
	pg_assert_same('- one _two_ **three** ==four== `*five*`', $out[1], 'list marker, italic, bold; a highlight kept; code untouched');
	pg_assert_same('- [ ] todo', $out[2], 'a checklist item marked +');
	pg_assert_same('**Setext**', $out[3], 'an underlined heading');
	pg_assert_same('---', $out[5], 'a rule stays a rule');
	pg_assert_same('```latex', $out[6], 'a math block is not a calculation here');
	pg_assert_same('x = *y*', $out[7], 'code is kept as written');
	pg_assert_same('```python', $out[9], 'a ~~~ fence becomes ``` without what follows the language');
	pg_assert_same('```', $out[11], 'and closes the same way');
	pg_assert_same('Inline ```code``` stays', $out[12], 'backticks closed on the line open no block');
	pg_assert_same('[a b.png](img/a%20b.png) [logo](pics/x%20y.png)', $out[13], 'pictures become links; a title and angle brackets go');
	pg_assert_same('# not a heading [x]', $out[14], 'escapes of characters that mean nothing here undone');
	pg_assert_same('| a b | c |', $out[15], 'a line break in a table cell keeps the row whole');
	pg_assert_same('#tag line', $out[16], 'a #tag is not a heading');
}

function test_workspace_notes_io_import_parse_title_tags_dates()
{
	$md = "---\ntitle: Toplantı\ntags: [iş planı, ekim]\ncreated: 2024-05-01T09:30:00+03:00\n---\n# Toplantı\n\nText <@user:3> and <#order:5>.\n";
	$note = ws_note_import_parse($md, 'Vault/x.md', 0);

	pg_assert_same('Toplantı', $note['title'], 'the front matter title');
	pg_assert_same("Text @user:3 and #order:5.\n\n#işplanı #ekim", $note['body'], 'the repeated heading taken out, tags kept as #words, nothing read as a tag');
	pg_assert_same(1714545000, $note['created'], 'created from the front matter');
	pg_assert_same(1714545000, $note['updated'], 'updated is created when missing');
	pg_assert_same(0, $note['origin'], 'not one of ours');
	pg_assert_false($note['named'], 'the title is not the file name');

	$heading = ws_note_import_parse("\n# Plan *A*\nbody", 'Plan 0123456789abcdef0123456789abcdef.md', 1714545000);
	pg_assert_same('Plan *A*', $heading['title'], 'a first heading is the title when there is none');
	pg_assert_same('body', $heading['body'], 'and leaves the text');
	pg_assert_same(1714545000, $heading['created'], 'the file time when the file says nothing');

	$named = ws_note_import_parse('', 'Notion Page 0123456789abcdef0123456789abcdef.md');
	pg_assert_same('Notion Page', $named['title'], 'the file name without Notion\'s page id');
	pg_assert_true($named['named'], 'named after the file');

	$text = ws_note_import_parse("# kept\n*as is*", 'notes.txt');
	pg_assert_same("# kept\n*as is*", $text['body'], 'a .txt is taken as written');

	$ours = ws_note_import_parse("---\ntitle: \"A\"\ncreated: 2024-05-01T09:30:00+03:00\npinegrap_note: 12\n---\n\n```math\n2*3\n```\n", 'A.md');
	pg_assert_same(12, $ours['origin'], 'the note it was exported from');
	pg_assert_same(1714545000, $ours['origin_created'], 'and that note\'s birth');
	pg_assert_same("```math\n2*3\n```", $ours['body'], 'a file of ours keeps its text');
}

function test_workspace_notes_io_import_paths()
{
	pg_assert_same('Vault/img/a.png', ws_note_import_path('Vault/notes', '../img/./a.png'), '.. and . worked out');
	pg_assert_same('img/a.png', ws_note_import_path('Vault/notes', '/img/a.png'), 'a leading / is the top of the upload');
	pg_assert_same('', ws_note_import_path('', '../a.png'), 'climbing out of the upload goes nowhere');
	pg_assert_same('a/b.md', ws_note_import_entry_name('./a\\b.md'), 'an entry name with backslashes and ./');
	pg_assert_false(ws_note_import_visible('Vault/.obsidian/app.json'), 'a hidden folder is not looked at');
	pg_assert_false(ws_note_import_visible('__MACOSX/Vault/._a.md'), 'nor what a Mac adds');
	pg_assert_true(ws_note_import_is_note('Vault/a.MD'), 'a Markdown file is a note');
	pg_assert_false(ws_note_import_is_note('Vault/a.png'), 'a picture is not');
}

function test_workspace_notes_io_import_zip_names_without_the_utf8_flag()
{
	// Names as Windows' "Compressed folder" and 7-Zip write them on a Turkish
	// system: the OEM code page CP857, no UTF-8 flag on the entry.
	$meeting = hex2bin('546f706c616e748d206e6f746c61728d2e6d64');
	$spec    = hex2bin('456b6c65722f9f6172746e616d652e706466');
	$plan    = hex2bin('598d6c6c8d6b20706c616e2e6d64');

	pg_assert_same('Toplantı notları.md', ws_note_import_zip_name($meeting, 'Toplantì notlarì.md'), 'ı from CP857, not libzip\'s CP437 guess');
	pg_assert_same('Ekler/şartname.pdf', ws_note_import_zip_name($spec, 'Ekler/ƒartname.pdf'), 'ş in a folder path');
	pg_assert_same('Yıllık plan.md', ws_note_import_zip_name($plan, 'Yìllìk plan.md'), 'two ı');
	pg_assert_same('İş.md', ws_note_import_zip_name(hex2bin('989f2e6d64'), ''), 'İ and ş');
	pg_assert_same('Ğ.md', ws_note_import_zip_name(hex2bin('a62e6d64'), ''), 'Ğ');
	pg_assert_same('Résumé.md', ws_note_import_zip_name(hex2bin('528273756d822e6d64'), ''), 'a Western European name shares its bytes with CP437 and CP850');
	pg_assert_same("a\u{FFFD}b.md", ws_note_import_zip_name(hex2bin('61d5622e6d64'), ''), 'a byte CP857 does not define');

	pg_assert_same('Toplantı notları.md', ws_note_import_zip_name('Toplantı notları.md', 'Toplantı notları.md'), 'a UTF-8 name (flagged or not) is kept as it is');
	pg_assert_same('Ekler/plan.pdf', ws_note_import_zip_name('Ekler/plan.pdf', 'Ekler/plan.pdf'), 'ASCII');
	pg_assert_same('Toplantì notlarì.md', ws_note_import_zip_name(false, 'Toplantì notlarì.md'), 'no raw name: what libzip guessed');
	pg_assert_same('', ws_note_import_zip_name('', ''), 'nothing');

	pg_assert_same('Toplantı notları.md', ws_note_import_entry_name(ws_note_import_zip_name($meeting, '')), 'a decoded name is a valid entry name');
}

function test_workspace_notes_io_import_links_found_and_replaced()
{
	$body = "[[Plan]] [[Plan#Goals|the goals]] ![[pic.png]] [spec](../docs/my%20spec.pdf#p2) [web](https://x.test/a) [mail](mailto:a@b.test) [top](#here)\n```\n[[not a link]]\n```\n`[[code]]`";
	$seen = array();
	$out = ws_note_import_relink($body, function ($link) use (&$seen) {
		$seen[] = $link;

		return '<' . (($link['kind'] === 'wiki') ? $link['name'] . '|' . $link['label'] . ($link['embed'] ? '|embed' : '') : $link['path'] . '|' . $link['label']) . '>';
	});

	pg_assert_same(4, count($seen), 'four links; web, mail and anchors are not, nor what is code');
	pg_assert_same('<Plan|Plan> <Plan|the goals> <pic.png|pic.png|embed> <../docs/my spec.pdf|spec> [web](https://x.test/a) [mail](mailto:a@b.test) [top](#here)', explode("\n", $out)[0], 'each replaced by what the resolver says');
	pg_assert_contains("```\n[[not a link]]\n```\n`[[code]]`", $out, 'code left alone');

	pg_assert_same('[spec](Vault/docs/my%20spec.pdf)', ws_note_import_root_links('[spec](../docs/my%20spec.pdf#p2)', 'Vault/notes'), 'a link pointed from the top of the upload, the part after # dropped');
	pg_assert_same('[x](../../out.png)', ws_note_import_root_links('[x](../../out.png)', 'Vault'), 'one that climbs out is left as written');
}

function test_workspace_notes_io_import_merge_keeps_unchanged_lines_and_tags()
{
	$refs = array(
		'user:12'  => array('type' => 'user', 'id' => 12, 'label' => '@Ayşe', 'url' => '', 'known' => true),
		'order:5'  => array('type' => 'order', 'id' => 5, 'label' => 'Order #5', 'url' => 'https://shop.test/o/5', 'known' => true),
		'product:7' => array('type' => 'product', 'id' => 7, 'label' => 'Product', 'url' => '', 'known' => false),
	);
	$original = "Ask <@user:12> about <#order:5>.\nA <#product:7> line\n[brief.pdf](https://shop.test/files/brief.pdf)\nOld line";
	$files = array(array('https://shop.test/files/brief.pdf' => 'attachments/9/brief.pdf'));

	$export = "Ask @Ayşe about [Order #5](https://shop.test/o/5).\nA Product line\n[brief.pdf](attachments/9/brief.pdf)\nOld line";
	pg_assert_same($original, ws_note_import_merge($export, $original, $refs, '', $files), 'an export as it was is the note again, tags and file links included');

	$single = "Ask @Ayşe about [Order #5](https://shop.test/o/5).\nA Product line\n[brief.pdf](https://shop.test/files/brief.pdf)\nOld line";
	pg_assert_same($original, ws_note_import_merge($single, $original, $refs, '', $files), 'a single note\'s export, its files not mapped, too');

	$edited = "Ask @Ayşe about [Order #5](https://shop.test/o/5).\nA Product line\n[brief.pdf](attachments/9/brief.pdf)\nNew: @Ayşe, [Order #5](https://shop.test/o/5) and @Ayşegül";
	$merged = explode("\n", ws_note_import_merge($edited, $original, $refs, '', $files));

	pg_assert_same('A <#product:7> line', $merged[1], 'an unchanged line keeps a tag that went out as a bare word');
	pg_assert_same('New: <@user:12>, <#order:5> and @Ayşegül', $merged[3], 'a changed line gets back the tags it still holds, not a longer name');
}

// libzip writes no file for an archive with no entries: close() deletes it.
// So a person without notes is told so before a zip is opened, the vault
// checks the file is there after close(), and the endpoint does not stream
// a file that is gone (filesize()/readfile() warnings and a 0-byte zip).
function test_workspace_notes_io_vault_refuses_an_empty_export()
{
	if (class_exists('ZipArchive')) {
		$path = tempnam(sys_get_temp_dir(), 'pg_test_');
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$zip->close();

		pg_assert_false(is_file($path), 'an archive with no entries leaves no file behind');
		@unlink($path);
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/notes_io.php');
	$start = strpos($source, 'function ws_note_export_vault(');
	$vault = substr($source, $start, strpos($source, "\nfunction ", $start + 1) - $start);

	pg_assert_contains("lang('There are no notes to export.')", $vault, 'no notes: an error, not an empty zip');
	pg_assert_true(strpos($vault, "lang('There are no notes to export.')") < strpos($vault, '$zip->open('), 'said before a zip is opened');
	pg_assert_contains('!is_file($zip_path)', $vault, 'the file is checked after close()');

	$endpoint = (string) file_get_contents(PG_FUNCTIONS_DIR . '/workspace_notes_export.php');

	pg_assert_contains('!is_file($path)', $endpoint, 'the endpoint does not stream a missing file');
}
