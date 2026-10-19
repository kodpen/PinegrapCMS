<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for notes going out as files (includes/workspace/notes_io.php): the
 * YAML front matter, the file name a note gets, the tags written out for a
 * reader outside the workspace, the links to a note's own files inside the
 * zip, and the Markdown file as a whole.
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
		'user:12'    => array('type' => 'user', 'id' => 12, 'label' => 'Ayşe Yılmaz', 'url' => '', 'known' => true),
		'page:3'     => array('type' => 'page', 'id' => 3, 'label' => 'About [us]', 'url' => 'https://shop.test/about', 'known' => true),
		'contact:5'  => array('type' => 'contact', 'id' => 5, 'label' => 'Ali Veli', 'url' => '/pinegrap/edit_contact.php?id=5', 'known' => true),
	);

	$body = "See <#order:1045> and <#product:7>, ask <@user:12>; page <#page:3>; gone <#offer:99>; <#contact:5>.";
	$out = ws_note_export_tokens($body, $refs, 'https://shop.test/');

	pg_assert_contains('[Order #1045](https://shop.test/pinegrap/view_order.php?id=1045)', $out, 'a record the reader may open is a link');
	pg_assert_contains('and Product,', $out, 'one they may not see is its generic name');
	pg_assert_contains('ask @Ayşe Yılmaz;', $out, 'a person is @name');
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
