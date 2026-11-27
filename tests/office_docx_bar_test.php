<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the document bar's text colour, highlight, font size and
 * alignment: the look a character carries in the editor, the stretches
 * and alignments a save sends (assets/js/pg_office_view.js), and the
 * colour, highlight and size the preview draws
 * (includes/fn/office_docx_format.php).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs a script after the view in Node and answers what it printed as JSON;
// null when Node cannot run.
function office_docx_bar_test_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgdb');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, "var window = {};\n" . file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js')
		. "\nvar M = window.pgOfficeView.docMarks;\n" . $script);
	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	pg_assert_same(0, $code, 'the Node script runs: ' . implode("\n", array_slice($out, 0, 8)));

	return json_decode(implode("\n", $out), true);
}

// A colour, highlight or size the server sent rides on each character's
// look; a plain stretch keeps the bits alone.
function test_office_docx_bar_marks()
{
	$result = office_docx_bar_test_node(<<<'JS'
var out = {};
var bits = M.expandMarks([[2, 1], [2, 1, {c: 'DC2626'}], [1, 0, {h: 'yellow', s: 14}]], 'abcde');
out.plain = M.expandMarks([[2, 3]], 'ab');
out.bits = bits.map(function (value) { return value & 7; });
out.same = [bits[0] === bits[1], bits[2] === bits[3], bits[1] === bits[2]];
out.looks = bits.map(M.lookOf);
out.again = M.expandMarks([[1, 1, {c: 'DC2626'}]], 'x')[0] === bits[2];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(3, 3), $result['plain'], 'a stretch with no look of its own is its bits');
	pg_assert_same(array(1, 1, 1, 1, 0), $result['bits'], 'the bits stay in the look');
	pg_assert_same(array(true, true, false), $result['same'], 'looks compare as numbers');
	pg_assert_same(array(array(), array(), array('c' => 'DC2626'), array('c' => 'DC2626'), array('h' => 'yellow', 's' => 14)), $result['looks'], 'each character gives back its colour, highlight and size');
	pg_assert_true($result['again'], 'one look is one number');
}

// The stretches a save sends for a colour, a highlight and a size, beside
// bold; a base differing everywhere sends each one, a missing one as off.
function test_office_docx_bar_ranges()
{
	$result = office_docx_bar_test_node(<<<'JS'
var out = {};
var red = M.withLook(0, 'c', 'DC2626');
var blue = M.withLook(0, 'c', '2563EB');
var big = M.withLook(red, 's', 18);
out.colour = M.formatRanges([0, 0, 0, 0], [0, red, red, blue]);
out.size = M.formatRanges([red, red], [big, red]);
out.off = M.formatRanges([red, 1], [0, M.withLook(1, 'h', 'green')]);
out.offBy = M.withLook(big, 'c', '') === M.withLook(0, 's', 18);
out.exact = M.formatRanges(M.exactBase([0, red]), [0, red]);
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array(
		array('start' => 1, 'end' => 3, 'set' => array('color' => 'DC2626')),
		array('start' => 3, 'end' => 4, 'set' => array('color' => '2563EB')),
	), $result['colour'], 'a stretch a colour, a new one where the colour changes');
	pg_assert_same(array(
		array('start' => 0, 'end' => 1, 'set' => array('size' => 18)),
	), $result['size'], 'only the size that changed');
	pg_assert_same(array(
		array('start' => 0, 'end' => 1, 'set' => array('color' => false)),
		array('start' => 1, 'end' => 2, 'set' => array('highlight' => 'green')),
	), $result['off'], 'a colour taken off is sent as false');
	pg_assert_true($result['offBy'], 'taking one part off keeps the others');
	pg_assert_same(array(
		array('start' => 0, 'end' => 2, 'set' => array('bold' => false)),
		array('start' => 0, 'end' => 2, 'set' => array('italic' => false)),
		array('start' => 0, 'end' => 2, 'set' => array('underline' => false)),
		array('start' => 0, 'end' => 1, 'set' => array('color' => false)),
		array('start' => 1, 'end' => 2, 'set' => array('color' => 'DC2626')),
		array('start' => 0, 'end' => 2, 'set' => array('highlight' => false)),
		array('start' => 0, 'end' => 2, 'set' => array('size' => false)),
	), $result['exact'], 'text moved into a new run sends every part');
}

// A paragraph's alignment: the file's, the one asked for, and whether a
// save sends it.
function test_office_docx_bar_align()
{
	$result = office_docx_bar_test_node(<<<'JS'
var A = window.pgOfficeView.docAlign;
var out = {};
out.file = [A.alignOf({}, '3'), A.alignOf({'3': 'center'}, '3'), A.alignOf({'3': 'left'}, '3')];
out.css = ['start', 'center', 'end', 'justify'].map(A.alignCss);
out.send = [A.alignSent('center', 'center'), A.alignSent('start', 'justify'), A.alignSent('end', null)];
console.log(JSON.stringify(out));
JS
	);

	if ($result === null) {
		return;
	}

	pg_assert_same(array('start', 'center', 'start'), $result['file'], 'a paragraph the server sent no alignment for is at the start');
	pg_assert_same(array('start', 'center', 'end', 'justify'), $result['css'], 'each alignment is drawn as the CSS value of the same name');
	pg_assert_same(array(null, 'justify', null), $result['send'], 'only an alignment that differs from the file\'s is sent');
}

// The preview draws a run's own colour, highlight and size.
function test_office_docx_bar_preview_style()
{
	pg_assert_same('', pg_office_docx_look_style(array()), 'no look, no style');
	pg_assert_same('color:#DC2626', pg_office_docx_look_style(array('c' => 'DC2626')), 'a colour');
	pg_assert_same('background-color:#FFFF00;font-size:14pt', pg_office_docx_look_style(array('h' => 'yellow', 's' => 14)), 'a highlight and a size');
	pg_assert_same('font-size:10.5pt', pg_office_docx_look_style(array('s' => 10.5)), 'a half point');
	pg_assert_same('background-color:#808000', pg_office_docx_look_style(array('h' => 'darkYellow')), 'Word\'s dark yellow');
	pg_assert_same('', pg_office_docx_look_style(array('c' => 'red;x', 'h' => 'orange', 's' => 'big')), 'nothing that is not a look');

	$w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	$document = new DOMDocument('1.0', 'UTF-8');
	$document->loadXML('<w:document xmlns:w="' . $w . '"><w:body><w:p><w:r><w:rPr><w:color w:val="2563eb"/><w:sz w:val="28"/></w:rPr><w:t>Hi</w:t></w:r></w:p></w:body></w:document>', LIBXML_NONET);
	$state = array();
	$html = pg_office_docx_run($state, $document->getElementsByTagNameNS($w, 'r')->item(0));

	pg_assert_same('<span style="color:#2563EB;font-size:14pt">Hi</span>', $html, 'the run is drawn in its colour and size');
}

// The bar has the controls the step adds.
function test_office_docx_bar_source()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_office_view.js');
	$start = strpos($source, '    function renderDocument(');
	$body = substr($source, $start, strpos($source, "\n    function renderCsv(", $start) - $start);

	pg_assert_contains("barMenu(", $body, 'the colours open a menu of swatches');
	pg_assert_contains("strings.doc_highlight", $body, 'a highlight colour button');
	pg_assert_contains("strings.doc_color", $body, 'a text colour button');
	pg_assert_contains("strings.format_size", $body, 'a size choice');
	pg_assert_contains("'pg-seg'", $body, 'the alignments are a segmented control');
	pg_assert_same(0, preg_match('/\bbtn btn-/', $body), 'look A buttons only');
}
