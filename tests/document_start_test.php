<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The designer's "From a document" start: a file's refusal and the answer
 * the rows are drawn from (includes/fn/document_start.php), the start in
 * the New design window, the rows' pure parts (assets/js/pg_document_rows.js)
 * and the page built from them (assets/js/pg_document_page.js). The
 * JavaScript runs in Node; skipped where Node cannot run.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/document_start.php');
require_once(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

// Runs `$code` in Node with the reader (R), the rows (W), the sketch builder
// and the page (P) loaded, and a starter maker `make` answering small
// trees with sample words. `out` comes back decoded; null without Node.
function document_start_test_node($code)
{
	if (!function_exists('exec')) {
		return null;
	}

	$out = array();
	$status = 1;
	@exec('node --version 2>&1', $out, $status);

	if ($status !== 0) {
		return null;
	}

	$script = "var window = {};\n";
	foreach (array('pg_document_reader', 'pg_document_rows', 'pg_sketch_builder', 'pg_document_page') as $file) {
		$script .= file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/' . $file . '.js') . "\n";
	}
	$script .= <<<'JS'
var R = window.PGDocumentReader, W = window.PGDocumentRows, P = window.PGDocumentPage, out = null;
function n(type, props, kids) { return {_id: 'st', type: type, props: props || {}, children: kids || []}; }
function c(kind, text, more) { var p = {contentType: kind, text: text}; for (var k in (more || {})) p[k] = more[k]; return n('content', p); }
function make(type) {
	var col = n('col', {xs: '12', lg: '4'}, type === 'image-grid' ? [c('image', '', {src: 'sample.jpg'})] : [c('heading', 'Sample title', {tag: 'h3'}), c('paragraph', 'Sample text'), c('link', 'Learn more')]);
	var kids = [c('heading', 'Starter ' + type, {tag: 'h2'}), c('paragraph', 'Starter lead')];
	if (type === 'faq-twocol') {
		var block = n('semantic', {tag: 'div'}, [n('semantic', {tag: 'h3'}, [c('icon', ''), c('heading', 'Q?', {tag: 'span'})]), c('paragraph', 'A.')]);
		kids.push(n('row', {}, [n('col', {lg: '6'}, [block, block]), n('col', {lg: '6'}, [block])]));
	} else if (type !== 'hero-centered') {
		kids.push(n('row', {gutter: '4'}, [col, col, col]));
	} else {
		kids.push(c('link', 'Get started'));
	}
	return n('semantic', {tag: 'section', cssClass: 'py-5'}, [n('container', {}, kids)]);
}
function texts(node, acc) {
	if (node.type === 'content' && node.props.text) acc.push(node.props.text);
	(node.children || []).forEach(function (k) { texts(k, acc); });
	return acc;
}

JS;
	$script .= $code . "\nconsole.log(JSON.stringify(out));";

	$file = @tempnam(sys_get_temp_dir(), 'pgdocstart');
	if ($file === false) {
		return null;
	}
	file_put_contents($file, $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $status);
	@unlink($file);

	pg_assert_same(0, $status, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A file is refused in the form's words: its kind, its size, a file that
// cannot be opened, one without text.
function test_document_start_refusals()
{
	pg_assert_same('This file cannot be read', pg_document_start_refusal('brief.doc', 10)['title'], 'an older .doc');
	pg_assert_same('The file is larger than 8 MB', pg_document_start_refusal('brief.docx', 8 * 1024 * 1024 + 1)['title'], 'over 8 MB');
	pg_assert_same(null, pg_document_start_refusal('brief.docx', 8 * 1024 * 1024), 'at 8 MB');
	pg_assert_same(null, pg_document_start_refusal('notes.md', 10), 'a Markdown file');
	pg_assert_same('The file could not be opened', pg_document_start_refusal('brief.docx', 10, array('ok' => false))['title'], 'damaged or protected');
	pg_assert_same('No text was found', pg_document_start_refusal('a.txt', 10, array('ok' => true, 'paragraphs' => array(), 'text' => "  \n"))['title'], 'no text');
	pg_assert_same(null, pg_document_start_refusal('a.txt', 10, array('ok' => true, 'paragraphs' => array(), 'text' => 'Hello')), 'a text');
}

// The answer keeps the pictures' place in the package on the server.
function test_document_start_answer()
{
	$answer = pg_document_start_answer(array(
		'paragraphs' => array(array('text' => 'A', 'style' => 'paragraph')), 'text' => '', 'cut' => 'paragraphs',
		'read' => array('paragraphs' => 2000, 'percent' => 64), 'pictures_lost' => 1,
		'pictures' => array(array('part' => 'word/media/image1.png', 'name' => 'image1.png', 'mime' => 'image/png', 'size' => 300, 'alt' => 'Lake')),
	), 'brief.docx', 1234, 'abc');

	pg_assert_same(array(array('n' => 0, 'name' => 'image1.png', 'alt' => 'Lake', 'size' => 300)), $answer['pictures'], 'the pictures without their part');
	pg_assert_same(array('success', 'brief.docx', 1234, 'paragraphs', 64, 1, 'abc'),
		array($answer['status'], $answer['name'], $answer['size'], $answer['cut'], $answer['read']['percent'], $answer['pictures_lost'], $answer['keep']), 'the rest of the answer');
}

// Every kind has its label in the texts; the form's own words are there.
function test_document_start_texts()
{
	$texts = pg_document_start_texts();
	foreach (array('intro', 'text', 'cards', 'questions', 'figures', 'testimonial', 'table', 'prices', 'contact', 'picture', 'gallery') as $kind) {
		pg_assert_true(isset($texts['kinds'][$kind]), $kind . ': a label');
	}
	foreach (array('read', 'build', 'couldBe', 'other', 'putBack', 'cut', 'pictures', 'noHeadings') as $key) {
		pg_assert_true(isset($texts[$key]) && $texts[$key] !== '', $key . ': a text');
	}
}

// The start in the New design window: after Figma, before ZIP file; it
// opens the design with ?start=document and loads its part.
function test_document_start_in_the_window()
{
	$keys = array_map(function ($k) { return $k['key']; }, pg_designer_start_kinds(true, true));
	pg_assert_same(array('blank', 'template', 'figma', 'document', 'import', 'paste'), $keys, 'its place');
	pg_assert_same('add_system_style.php?framework=bootstrap5&start=document&from=pages', pg_designer_start_go_href('document', 'bootstrap5', true), 'its address');
	$svg = pg_designer_home_drawing('document');
	pg_assert_same(0, preg_match('/#[0-9a-f]{3,8}\b|style=/i', $svg), 'its drawing has no colour of its own');
}

// The rows: an unsure row's list asks "It could be" first, then the other
// kinds its words fill; choosing takes the mark away and the kind's items.
function test_document_start_rows_choices()
{
	$result = document_start_test_node('var p = [{style: "h1", text: "Spa"}, {text: "Rest."}, {style: "h2", text: "Gallery"}, {style: "item", text: "Massage: 60 minutes", lead: "Massage"}, {style: "item", text: "Sauna: 30 minutes", lead: "Sauna"}];'
		. 'var rows = W.rowsOf(R.read(p), p, R); var r = rows[1]; var before = [r.kind, r.unsure, W.choices(r)];'
		. 'W.choose(r, "text"); out = [before, r.kind, r.unsure, r.items[0].text, W.choices(rows[0]), W.choose(r, "gallery")];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array('cards', true, array('could' => array('cards'), 'other' => array('text'))), $result[0], 'the heading names a kind the words cannot fill: not listed');
	pg_assert_same(array('text', false, 'Massage: 60 minutes'), array($result[1], $result[2], $result[3]), 'chosen: text items, no mark');
	pg_assert_same(array('could' => array(), 'other' => array('intro', 'text')), $result[4], 'a sure row has no captions');
	pg_assert_same(false, $result[5], 'a kind the words cannot fill is refused');
}

// Moved, counted, previewed; the opened row's fields and their edits.
function test_document_start_rows_parts()
{
	$result = document_start_test_node('var row = {kind: "questions", heading: "FAQ", items: [{question: "Parking?", answer: "Yes."}, {question: "Pets?", answer: "No."}]};'
		. 'var list = [1, 2, 3]; var moved = [W.move(list, 0, 2), list.slice(), W.move(list, 0, 3)];'
		. 'var f = W.fields(row); W.set(row, f[2][0], "Yes, free.");'
		. 'out = [moved, W.count(row), W.preview(row), f.map(function (x) { return x[1]; }), row.items[0].answer,'
		. ' W.count({kind: "text", items: [{text: "one two"}, {text: "three"}]}), W.count({kind: "prices", items: [{rows: [["a", "1"], ["b", "2"]]}]})];');

	if ($result === null) {
		return;
	}

	pg_assert_same(array(true, array(2, 3, 1), false), $result[0], 'a row moved; out of range refused');
	pg_assert_same(array('questions', 2), $result[1], 'how many questions');
	pg_assert_same('Parking? · Pets?', $result[2], 'the beginning of its words');
	pg_assert_same(array('heading', 'question', 'answer', 'question', 'answer'), $result[3], 'the fields in the kind\'s shape');
	pg_assert_same('Yes, free.', $result[4], 'a field corrected');
	pg_assert_same(array('words', 3), $result[5], 'a text counts words');
	pg_assert_same(array('rows', 2), $result[6], 'prices count rows');
}

// The page: the words go in as written, the starters' sample words go,
// each section wears the section rhythm, left-out rows are not built.
function test_document_start_page_words()
{
	$result = document_start_test_node('var rows = ['
		. '{id: "p0", kind: "intro", heading: "Still Water", items: [{type: "paragraph", text: "Two nights <by> the lake."}]},'
		. '{id: "p2", kind: "cards", heading: "Included", items: [{title: "Sauna", text: "Wood fired."}, {title: "Boat", text: "Row out."}]},'
		. '{id: "p5", kind: "figures", heading: "", items: [{value: "12", label: "rooms"}]},'
		. '{id: "p6", kind: "questions", heading: "Before you come", items: [{question: "Kids?", answer: "Yes."}, {question: "Dogs?", answer: "No."}, {question: "Wifi?", answer: "Some."}]},'
		. '{id: "p9", kind: "prices", heading: "Prices", items: [{rows: [["Night", "900 TL"]]}]},'
		. '{id: "p10", kind: "contact", heading: "", items: [{type: "email", value: "hi@lake.com"}, {type: "address", value: "Shore 1"}]},'
		. '{id: "p11", kind: "text", heading: "Out", out: true, items: [{type: "paragraph", text: "Gone"}]},'
		. '{id: "p12", kind: "gallery", heading: "", items: [{src: "picture:0", alt: "Lake"}, {src: "javascript:x", alt: "Bad"}]}'
		. ']; var nodes = P.build(rows, {make: make, src: function (s) { return s === "picture:0" ? "/lake.png" : ""; }});'
		. 'out = [nodes.length, nodes.map(function (x) { return texts(x, []); }), nodes.every(function (x) { return /--pg-var-space_section/.test(x.props.inlineStyle); }),'
		. ' JSON.stringify(nodes[5]).indexOf("mailto:hi@lake.com") !== -1, JSON.stringify(nodes[6]).indexOf("/lake.png") !== -1];');

	if ($result === null) {
		return;
	}

	pg_assert_same(7, $result[0], 'seven sections: the left-out row is not built');
	pg_assert_same(array('Still Water', 'Two nights &lt;by&gt; the lake.'), $result[1][0], 'the intro in its words, escaped, the sample button gone');
	pg_assert_same(array('Included', 'Sauna', 'Wood fired.', 'Boat', 'Row out.'), $result[1][1], 'a card a column, the samples gone');
	pg_assert_same(array('12', 'rooms'), $result[1][2], 'a figure, no heading');
	pg_assert_same(array('Before you come', 'Kids?', 'Yes.', 'Dogs?', 'No.', 'Wifi?', 'Some.'), $result[1][3], 'the questions in two columns');
	pg_assert_same(array('Prices', 'Night', '900 TL'), $result[1][4], 'the prices table');
	pg_assert_same(array('hi@lake.com', 'Shore 1'), $result[1][5], 'the contact lines');
	pg_assert_same(true, $result[2], 'the section rhythm on each');
	pg_assert_same(true, $result[3], 'an e-mail is a mailto link');
	pg_assert_same(true, $result[4], 'a stored picture in the gallery');
}
