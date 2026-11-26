<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document typed on a page (renderDocument() in
 * assets/js/pg_office_view.js): a paragraph's text read back from what is
 * typed into it, a point of that text found in its drawing again, and the
 * parts of the source a unit test cannot press: no box, plain paste, the
 * white sheet.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs a Node script after the view's source with a small stand-in for the
// DOM (text nodes, elements with a class, br); its JSON output, or null
// when Node cannot run.
function office_document_page_test_node($script)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgdp');

	if ($file === false) {
		return null;
	}

	$dom = <<<'JS'
function link(parent, list) {
    parent.childNodes = list;
    parent.firstChild = list[0] || null;
    list.forEach(function (child, index) {
        child.parentNode = parent;
        child.nextSibling = list[index + 1] || null;
    });
    return parent;
}
function T(text) { return { nodeType: 3, nodeName: '#text', nodeValue: text }; }
function E(name, className, list) {
    return link({ nodeType: 1, nodeName: name.toUpperCase(), className: className || '' }, list || []);
}
function BR() { return E('br'); }
function TAB() { return E('span', 'office-tabchar', [T(' ')]); }
var D = window.pgOfficeView.docText;
JS;

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\n" . $dom . "\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

function test_office_document_page_paragraph_text()
{
	$result = office_document_page_test_node(<<<'JS'
var out = {};
out.plain = D.paragraphText(E('p', '', [T('Hello '), E('strong', '', [T('big')]), T(' world')]));
out.tabs = D.paragraphText(E('p', '', [T('a'), TAB(), T('b'), BR(), T('c')]));
out.typedLine = D.paragraphText(E('p', '', [T('one\ntwo')]));
out.emptied = D.paragraphText(E('p', '', [BR()]));
out.empty = D.paragraphText(E('p', '', []));
out.nested = D.paragraphText(E('li', '', [E('a', '', [E('em', '', [T('x')])]), T('y')]));
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same('Hello big world', $result['plain'], 'the text of every run in reading order');
	pg_assert_same("a\tb\nc", $result['tabs'], 'a drawn tab is a tab, a br a line break');
	pg_assert_same("one\ntwo", $result['typedLine'], 'a line break typed as a character stays one');
	pg_assert_same('', $result['emptied'], 'the br a browser leaves in an emptied paragraph is no text');
	pg_assert_same('', $result['empty'], 'an empty paragraph is empty');
	pg_assert_same('xy', $result['nested'], 'text inside links and marks is read');
}

function test_office_document_page_text_point()
{
	$result = office_document_page_test_node(<<<'JS'
var bold = T('big');
var tail = T(' world');
var p = E('p', '', [T('Hello '), E('strong', '', [bold]), tail]);
var tabbed = E('p', '', [T('a'), TAB(), T('b'), BR(), T('c')]);
function where(root, offset) {
    var point = D.textPoint(root, offset);
    return [point.node.nodeType === 3 ? point.node.nodeValue : point.node.nodeName, point.offset];
}
var out = {};
out.start = where(p, 0);
out.inBold = where(p, 7);
out.end = where(p, 15);
out.past = where(p, 99);
out.afterTab = where(tabbed, 2);
out.afterBreak = where(tabbed, 4);
out.round = [0, 3, 6, 7, 9, 10, 15].map(function (offset) {
    var point = D.textPoint(p, offset);
    return D.textOffset(p, point.node, point.offset);
});
out.roundTab = [0, 1, 2, 3, 4, 5].map(function (offset) {
    var point = D.textPoint(tabbed, offset);
    return D.textOffset(tabbed, point.node, point.offset);
});
out.empty = where(E('p', '', []), 0);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('Hello ', 0), $result['start'], 'the start is in the first text');
	pg_assert_same(array('big', 1), $result['inBold'], 'a point inside a bold run is in its text');
	pg_assert_same(array(' world', 6), $result['end'], 'the end is after the last character');
	pg_assert_same(array(' world', 6), $result['past'], 'a point past the text is its end');
	pg_assert_same(array('b', 0), $result['afterTab'], 'after a tab, the text that follows it');
	pg_assert_same(array('c', 0), $result['afterBreak'], 'after a line break, the next line');
	pg_assert_same(array(0, 3, 6, 7, 9, 10, 15), $result['round'], 'a point found is counted back to the same offset');
	pg_assert_same(array(0, 1, 2, 3, 4, 5), $result['roundTab'], 'also over tabs and line breaks');
	pg_assert_same(array('P', 0), $result['empty'], 'an empty paragraph holds its own start');
}

// Enter splits a paragraph at the caret, a selection taken out first: the
// text and the look of each character go with their half.
function test_office_document_page_cut_look()
{
	$result = office_document_page_test_node(<<<'JS'
var M = window.pgOfficeView.docMarks;
var out = {};
out.middle = M.cutLook('Hello world', [0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1], 5, 5);
out.selection = M.cutLook('Hello world', [0, 0, 0, 0, 0, 0, 1, 1, 1, 1, 1], 4, 7);
out.end = M.cutLook('Hi', [2, 2], 2, 2);
out.start = M.cutLook('Hi', [4, 0], 0, 0);
out.exact = M.exactBase([0, 1, 7, 5]);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('head' => 'Hello', 'tail' => ' world', 'headBits' => array(0, 0, 0, 0, 0), 'tailBits' => array(0, 1, 1, 1, 1, 1)), $result['middle'], 'the text and its look are cut at the caret');
	pg_assert_same(array('head' => 'Hell', 'tail' => 'orld', 'headBits' => array(0, 0, 0, 0), 'tailBits' => array(1, 1, 1, 1)), $result['selection'], 'a selection is taken out before the cut');
	pg_assert_same(array('head' => 'Hi', 'tail' => '', 'headBits' => array(2, 2), 'tailBits' => array()), $result['end'], 'at the end the new paragraph is empty');
	pg_assert_same(array('head' => '', 'tail' => 'Hi', 'headBits' => array(), 'tailBits' => array(4, 0)), $result['start'], 'at the start all goes on');
	pg_assert_same(array(7, 6, 0, 2), $result['exact'], 'a base differing in every bit makes a save send each one');
}

// What a unit test cannot type: each paragraph that may change is typed
// where it stands (no textarea), paste is plain text, the sheet is white in
// both themes.
function test_office_document_page_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	$start = strpos($source, '    function renderDocument(');
	$body = substr($source, $start, strpos($source, "\n    function renderCsv(", $start) - $start);

	pg_assert_true($start !== false, 'renderDocument() is found');
	pg_assert_same(false, strpos($body, "'textarea'"), 'no box to type in');
	pg_assert_contains("'plaintext-only'", $body, 'a paragraph takes plain text where the browser can');
	pg_assert_contains("getData('text/plain')", $body, 'paste is plain text');
	pg_assert_contains("paper.setAttribute('data-bs-theme', 'light');", $body, 'the sheet is white in both themes');
	pg_assert_contains('paper.contentEditable = typeMode;', $body, 'the sheet is the one place typed in, so a selection crosses paragraphs');
	pg_assert_contains('splitParagraph();', $body, 'Enter cuts the paragraph');
	pg_assert_contains('joinParagraphs(from, true);', $body, 'Backspace at a paragraph\'s start joins it to the one before');
	pg_assert_same(false, strpos($body, 'strings.doc_add'), 'the bar has no Add a paragraph button');
	pg_assert_same(0, preg_match('/\bvar (select|enter|follow|refuse)\b/', $body), 'no variable hides a function of the view');

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	pg_assert_contains('max-width: 816px;', $css, 'the sheet is a page wide at most');
	pg_assert_same(false, strpos($css, '.office-para-editor'), 'the box\'s rules are gone');
}
