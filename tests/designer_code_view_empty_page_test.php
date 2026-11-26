<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the page document of the visual designer's code view
 * (_sdCodeDocument()) and the scope a typed span falls in
 * (_sdCodeScopeOf()): an empty page has a line to type on, and what is typed
 * at the end of the <body> line, on that line or before the scripts belongs
 * to the page content, while the body tag's own text stays the body's. Both
 * functions are cut out of assets/js/style_designer_code.js and run under
 * Node with the editor's tracked ranges mapped the way the facade maps them;
 * on a machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $steps after the document builder and the scope finder; null
// without Node.
function designer_code_view_empty_page_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer_code.js');
	$parts = array(
		array('function _sdCodeDocument(', '// The islands of a half-locked node'),
		array('function _sdCodeScopeOf(', '// The node an element of the text stands for'),
	);

	// The frame of a page with no head rows and one foot script; a node
	// prints as its `html`, recorded in the emitter's sink.
	$script = 'function esc(s) { return String(s).replace(/&/g, "&amp;").replace(/"/g, "&quot;"); }'
		. 'var K = { tree: null };'
		. 'var bodyAttrs = [];'
		. 'function _sdDocLang() { return "en"; }'
		. 'function _sdPageFrameRows() { return { head: [], foot: [{ tag: "script" }] }; }'
		. 'function _sdFrameRowText(r) { return "<script src=\"b.js\"></script>"; }'
		. 'function _sdPageBodyAttrs() { return bodyAttrs; }'
		. 'function _sdCodeLockOf() { return { level: "", attrs: true, text: true }; }'
		. 'function _sdCodeOpenTag() { return null; }'
		. 'function _sdCodeTextKind() { return "plain"; }'
		. 'function _sdCodeIslands() { return []; }'
		. 'function _sdEmitNode(n, indent, base, sink) { var s = "    " + n.html + "\n"; sink.ranges.push({ node: n, sid: 0, from: base, to: base + s.length }); return s; }'
		// The editor: the document's ranges, moved by one insertion the way
		// the facade's tracked ranges move (a growing range keeps text typed
		// at either edge, another one keeps neither).
		. 'var live = [];'
		. 'var _sdCode = { ed: { trackedAt: function (pos) {'
		. '  var out = live.filter(function (e) { return e.from <= pos && pos <= e.to; }).map(function (e) { return { id: e.key, from: e.from, to: e.to, data: e }; });'
		. '  out.sort(function (a, b) { return (a.to - a.from) - (b.to - b.from); }); return out; } } };'
		. 'function typeAt(doc, pos, n) {'
		. '  live = doc.entries.map(function (e) {'
		. '    var c = Object.assign({}, e);'
		. '    if (e.from > pos || (e.from === pos && !e.grow)) c.from += n;'
		. '    if (e.to > pos || (e.to === pos && e.grow)) c.to += n;'
		. '    return c; });'
		. '  var s = _sdCodeScopeOf({ from: pos, to: pos + n });'
		. '  return s ? s.entry.role : null; }';

	$empty_line = array();
	if (preg_match('/^\s*var _SD_CODE_EMPTY_LINE = [^;]+;/m', $source, $empty_line)) {
		$script .= $empty_line[0];
	}

	foreach ($parts as $part) {
		$from = strpos($source, $part[0]);
		$to = ($from !== false) ? strpos($source, $part[1], $from) : false;
		pg_assert_true(($from !== false) && ($to !== false), 'the code part has ' . $part[0]);

		if (($from === false) || ($to === false)) {
			return null;
		}

		$script .= substr($source, $from, $to - $from);
	}

	$script .= $steps;

	$file = tempnam(sys_get_temp_dir(), 'pgcodeempty');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// An empty page: one indented empty line between the body tag and the
// scripts, and the content's range runs from the body tag's ">" over it.
function test_designer_code_view_empty_page_has_a_line()
{
	$answer = designer_code_view_empty_page_test_run('
		K.tree = { type: "root", children: [] };
		var doc = _sdCodeDocument();
		var lines = doc.text.split("\n");
		var at = lines.indexOf("<body>");
		var content = doc.entries.filter(function (e) { return e.role === "content"; })[0];
		var tagEnd = doc.text.indexOf("<body>") + 6;
		console.log(JSON.stringify({
			next: lines[at + 1], after: lines[at + 2],
			from: content.from - tagEnd, to: content.to - doc.text.indexOf("    <script")
		}));
	');

	if ($answer === null) {
		return;
	}

	pg_assert_same('    ', $answer['next'], 'an empty line follows the body tag');
	pg_assert_same('    <script src="b.js"></script>', $answer['after'], 'then the scripts');
	pg_assert_same(0, $answer['from'], 'the content starts at the body tag\'s ">"');
	pg_assert_same(0, $answer['to'], 'and ends where the scripts start');
}

// What is typed at the end of the <body> line, on the empty line or at the
// start of the scripts line is the page content; what is typed inside the
// body tag is the body's.
function test_designer_code_view_end_of_body_line_is_content()
{
	$answer = designer_code_view_empty_page_test_run('
		var out = {};
		K.tree = { type: "root", children: [] };
		var doc = _sdCodeDocument();
		var tagEnd = doc.text.indexOf("<body>") + 6;
		out.emptyEndOfBody = typeAt(doc, tagEnd, 23);
		out.emptyLine = typeAt(doc, tagEnd + 5, 23);
		out.emptyBeforeScripts = typeAt(doc, doc.text.indexOf("    <script"), 23);
		out.emptyInsideTag = typeAt(doc, tagEnd - 1, 8);

		K.tree = { type: "root", children: [{ type: "semantic", html: "<p>x</p>" }] };
		doc = _sdCodeDocument();
		tagEnd = doc.text.indexOf("<body>") + 6;
		out.fullNoEmptyLine = doc.text.indexOf("<body>\n    <p>x</p>\n    <script") !== -1;
		out.fullEndOfBody = typeAt(doc, tagEnd, 9);
		console.log(JSON.stringify(out));
	');

	if ($answer === null) {
		return;
	}

	pg_assert_same('content', $answer['emptyEndOfBody'], 'the end of the <body> line on an empty page');
	pg_assert_same('content', $answer['emptyLine'], 'the empty line');
	pg_assert_same('content', $answer['emptyBeforeScripts'], 'the start of the scripts line');
	pg_assert_same('body', $answer['emptyInsideTag'], 'inside the body tag');
	pg_assert_true($answer['fullNoEmptyLine'], 'a page with content gets no empty line');
	pg_assert_same('content', $answer['fullEndOfBody'], 'the end of the <body> line on a page with content');
}
