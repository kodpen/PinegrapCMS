<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pasting into the Workspace writing boxes
 * (assets/js/workspace_editor.js). Run under Node: pasteMarkup() turns the
 * HTML another program put on the clipboard into the box's stored markup
 * (marks, links, lists, quotes, checklists, code, tables; everything else as
 * its words, nothing of the HTML itself), pasteSheet() reads a range copied
 * from a sheet as a table, pastedUrl() answers whether a pasted text is one
 * address alone. The HTML is parsed here by PHP's DOM and handed to Node as a
 * tree, as the browser's DOMParser hands its document to the walk. The rest
 * holds the wiring the browser runs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/workspace_editor_lists_test.php');

// A parsed node as the plain tree the Node script turns back into nodes.
function workspace_editor_paste_test_tree($node)
{
	if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
		return array('t' => 3, 'v' => $node->nodeValue);
	}

	if ($node->nodeType !== XML_ELEMENT_NODE) {
		return array('t' => 8);
	}

	$attributes = array();

	foreach ($node->attributes as $attribute) {
		$attributes[strtolower($attribute->nodeName)] = $attribute->nodeValue;
	}

	$children = array();

	foreach ($node->childNodes as $child) {
		$children[] = workspace_editor_paste_test_tree($child);
	}

	return array('t' => 1, 'n' => strtoupper($node->nodeName), 'a' => (object) $attributes, 'c' => $children);
}

// The body of an HTML fragment as a tree; null without PHP's DOM.
function workspace_editor_paste_test_body($html)
{
	if (!class_exists('DOMDocument')) {
		return null;
	}

	$document = new DOMDocument();
	$previous = libxml_use_internal_errors(true);
	$document->loadHTML('<?xml encoding="utf-8" ?><html><body>' . $html . '</body></html>');
	libxml_clear_errors();
	libxml_use_internal_errors($previous);
	$body = $document->getElementsByTagName('body')->item(0);

	return $body ? workspace_editor_paste_test_tree($body) : null;
}

// Runs the paste helpers under Node on the steps given and answers what each
// gave; null when Node or PHP's DOM is missing.
function workspace_editor_paste_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0 || !class_exists('DOMDocument')) {
		return null;
	}

	$script = "'use strict';\n";

	foreach (array('CALC_LANGUAGES', 'CHECK', 'TITLE', 'LISTS', 'PASTE_DROP', 'PASTE_BLOCKS', 'PASTE_LINK', 'PASTE_MARKS') as $name) {
		$part = workspace_editor_lists_test_var($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	foreach (array('fenceOpen', 'fenceInfo', 'fenceClose', 'tableRow', 'tableAlignments', 'tableCells', 'startsBlock', 'listLine', 'textBlocks', 'parseBlocks', 'wrapLines',
		'pasteStyle', 'pasteMarks', 'pasteWords', 'pasteRunsMarkup', 'pasteRunMarkup', 'pasteLink', 'pasteFlush', 'pasteOut', 'pasteLine', 'pasteWalk', 'pasteItems',
		'pasteItem', 'pasteCheck', 'pasteRows', 'pasteTableRows', 'pasteMarkup', 'pasteSheet', 'pastedUrl') as $name) {
		$part = workspace_editor_lists_test_function($name);
		pg_assert_true($part !== '', $name . ' is found');
		$script .= $part;
	}

	foreach ($steps as $index => $step) {
		if ($step[0] === 'html') {
			$steps[$index][1] = workspace_editor_paste_test_body($step[1]);
		}
	}

	$script .= "\nfunction shim(o) {\n"
		. "  return { nodeType: o.t, nodeName: o.n || '', nodeValue: (o.v === undefined) ? null : o.v,\n"
		. "    childNodes: (o.c || []).map(shim),\n"
		. "    getAttribute: function (k) { return (o.a && Object.prototype.hasOwnProperty.call(o.a, k)) ? o.a[k] : null; } };\n"
		. "}\n"
		. "var steps = " . json_encode($steps) . ";\n"
		. "console.log(JSON.stringify(steps.map(function (step) {\n"
		. "  if (step[0] === 'html') { return pasteMarkup(shim(step[1])); }\n"
		. "  if (step[0] === 'sheet') { return pasteSheet(step[1]); }\n"
		. "  if (step[0] === 'url') { return pastedUrl(step[1]); }\n"
		. "  if (step[0] === 'blocks') { return parseBlocks(step[1]).map(function (b) { return b.kind; }); }\n"
		. "  return null;\n"
		. "})));\n";

	$file = tempnam(sys_get_temp_dir(), 'pgwspaste');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	$printed = implode("\n", $out);
	pg_assert_same(0, $code, $printed);
	$answer = json_decode($printed, true);
	pg_assert_true(is_array($answer), 'Node printed JSON: ' . $printed);

	return is_array($answer) ? $answer : array();
}

// Each [html, markup]: the HTML pasted gives that stored markup.
function workspace_editor_paste_test_cases($cases)
{
	$steps = array();

	foreach ($cases as $case) {
		$steps[] = array('html', $case[0]);
	}

	$answer = workspace_editor_paste_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($cases as $index => $case) {
		pg_assert_same($case[1], $answer[$index], 'pasted: ' . $case[0]);
	}
}

// Marks and links come over as the box writes them.
function test_workspace_editor_paste_marks()
{
	workspace_editor_paste_test_cases(array(
		array('<p>Plain words</p>', 'Plain words'),
		array('<p>a <b>bold</b> and <strong>strong</strong></p>', 'a **bold** and **strong**'),
		array('<p><i>it</i> <em>em</em> <s>gone</s> <del>del</del> <strike>old</strike></p>', '_it_ _em_ ~~gone~~ ~~del~~ ~~old~~'),
		array('<p>see <mark>this</mark> and <code>x = 1</code></p>', 'see ==this== and `x = 1`'),
		array('<p><b>one </b><b>two</b></p>', '**one two**'),
		array('<p><b>a<br>b</b></p>', "**a**\n**b**"),
		array('<p><b><i>both</i></b></p>', '**_both_**'),
		array('<p><b> spaced </b>end</p>', '**spaced** end'),
		array('<p><b>2*3</b></p>', '2*3'),
		array('<p><a href="https://example.com/a?b=1">the site</a> here</p>', '[the site](https://example.com/a?b=1) here'),
		array('<p><a href="mailto:a@b.co">mail</a></p>', '[mail](mailto:a@b.co)'),
		array('<p><a href="https://x.co"><b>bold</b> link</a></p>', '[**bold** link](https://x.co)'),
		array('<p><a href="https://x.co"></a>x</p>', 'x'),
		array('<h2>Title</h2><p>body</p>', "**Title**\nbody"),
		array('<p>a</p><p>&nbsp;</p><p>&nbsp;</p><p>b</p>', "a\n\nb"),
		array('<p>a</p>' . "\n  " . '<p>b</p>', "a\nb"),
		array('<div>one<br>two</div>', "one\ntwo"),
		array('<p>x<sup>2</sup> <u>under</u></p>', 'x2 under'),
	));
}

// What the box cannot store stays as its words, and nothing dangerous
// survives: scripts, styles, event attributes, other addresses.
function test_workspace_editor_paste_dangerous()
{
	workspace_editor_paste_test_cases(array(
		array('<p>a<script>alert(1)</script>b</p>', 'ab'),
		array('<style>p{color:red}</style><p>text</p>', 'text'),
		array('<p onclick="alert(1)" style="color:red">safe</p>', 'safe'),
		array('<p><a href="javascript:alert(1)">click</a></p>', 'click'),
		array('<p><a href=" JavaScript:alert(1)">click</a></p>', 'click'),
		array('<p><a href="data:text/html,x">data</a> <a href="/relative">rel</a> <a href="https://a.co/x)y">paren</a></p>', 'data rel paren'),
		array('<p><img src="x" onerror="alert(1)">pic</p>', 'pic'),
		array('<iframe src="https://evil.example"></iframe><p>after</p>', 'after'),
		array('<p><span onmouseover="x()">hover</span></p>', 'hover'),
		array('<p><a href="https://ok.co" onclick="x()">[a]</a></p>', '[a]'),
		array('<p>&lt;b&gt;not a tag&lt;/b&gt;</p>', '<b>not a tag</b>'),
		array('<svg><text>svg</text></svg><p>ok</p>', 'ok'),
		array('<form><input value="secret"><button>Go</button><p>form words</p></form>', 'form words'),
		array('<div><div><span><span><b><i><u>deep</u></i></b></span></span></div></div>', '**_deep_**'),
	));
}

// Lists, quotes, checklists and code blocks.
function test_workspace_editor_paste_lists()
{
	workspace_editor_paste_test_cases(array(
		array('<ul><li>milk</li><li><b>bread</b></li></ul>', "- milk\n- **bread**"),
		array('<ol><li>one</li><li>two</li><li>three</li></ol>', "1. one\n2. two\n3. three"),
		array('<ul><li>a<ul><li>sub</li></ul></li><li>b</li></ul>', "- a\n- sub\n- b"),
		array('<ol><li>a<ol><li>sub</li></ol></li><li>b</li></ol>', "1. a\n2. sub\n3. b"),
		array('<ul><li>a</li></ul><p>after</p>', "- a\n\nafter"),
		array('<p>Shopping:</p><ul><li>milk</li></ul>', "Shopping:\n- milk"),
		array('<ul><li></li><li>x</li></ul>', '- x'),
		array('<blockquote><p>said</p><p>more</p></blockquote>', "> said\n> more"),
		array('<ul><li><input type="checkbox" checked> done</li><li><input type="checkbox"> todo</li></ul>', "- [x] done\n- [ ] todo"),
		array('<ul><li role="checkbox" aria-checked="true">bought</li><li role="checkbox" aria-checked="false">left</li></ul>', "- [x] bought\n- [ ] left"),
		array('<pre>line 1' . "\n" . '  indented</pre>', "```\nline 1\n  indented\n```"),
		array('<pre><code>a &lt; b</code></pre><p>x</p>', "```\na < b\n```\nx"),
		array('<pre>' . "\n\n" . '</pre><p>x</p>', 'x'),
		array('<li>stray</li>', '- stray'),
	));
}

// Word and Google Docs write marks in styles and lists as paragraphs.
function test_workspace_editor_paste_office()
{
	workspace_editor_paste_test_cases(array(
		// Google Docs: one <b> of normal weight around everything.
		array('<meta charset="utf-8"><b style="font-weight:normal;" id="docs-internal-guid-1"><p dir="ltr"><span style="font-weight:400;">plain </span><span style="font-weight:700;">bold</span><span style="font-style:italic;"> it</span></p></b>',
			'plain **bold** _it_'),
		array('<span style="background-color:#ffff00">yellow</span> <span style="background-color:transparent">clear</span> <span style="text-decoration:line-through">old</span>',
			'==yellow== clear ~~old~~'),
		array('<span style="background-color: rgb(255, 255, 255)">white</span>', 'white'),
		// Word: highlight, list paragraphs with their bullets.
		array("<p class=MsoNormal><span style='background:yellow;mso-highlight:yellow'>marked</span><o:p></o:p></p>", '==marked=='),
		array("<p class=MsoListParagraphCxSpFirst style='mso-list:l0 level1 lfo1'><span style='font-family:Symbol;mso-list:Ignore'>·<span>&nbsp;&nbsp;</span></span>first</p>"
			. "<p class=MsoListParagraphCxSpLast style='mso-list:l0 level1 lfo1'><span style='mso-list:Ignore'>·<span>&nbsp;</span></span>second</p><p class=MsoNormal>after</p>",
			"- first\n- second\n\nafter"),
		array("<p style='mso-list:l1 level1 lfo2'><span style='mso-list:Ignore'>1.<span>&nbsp;</span></span>one</p><p style='mso-list:l1 level1 lfo2'><span style='mso-list:Ignore'>2.<span>&nbsp;</span></span>two</p>",
			"1. one\n2. two"),
		array("<p class=MsoNormal><b><span style='font-size:12pt'>Heading words<o:p></o:p></span></b></p>", '**Heading words**'),
		array('<!--StartFragment--><p>frag</p><!--EndFragment-->', 'frag'),
		// Code from an editor: spaces kept where the source keeps them.
		array('<div style="white-space: pre;"><div><span>if (a) {</span></div><div><span>    b();</span></div><div><span>}</span></div></div>', "if (a) {\n    b();\n}"),
	));
}

// Tables become the box's table; layout tables stay words.
function test_workspace_editor_paste_tables()
{
	workspace_editor_paste_test_cases(array(
		array('<table><tr><th>Name</th><th>Qty</th></tr><tr><td>Tea</td><td>2</td></tr></table>', "| Name | Qty |\n| --- | --- |\n| Tea | 2 |"),
		array('<table><tbody><tr><td>a|b</td><td><b>x</b></td></tr><tr><td>c</td></tr></tbody></table>', "| a\\|b | **x** |\n| --- | --- |\n| c |  |"),
		array('<google-sheets-html-origin><style>td{}</style><table><tr><td>1</td><td>2</td></tr><tr><td>3</td><td>4</td></tr></table></google-sheets-html-origin>', "| 1 | 2 |\n| --- | --- |\n| 3 | 4 |"),
		array('<table><tr><td><p>only</p><p>cell</p></td></tr></table>', "only\ncell"),
		array('<table><tr><td>a</td><td>b</td></tr></table>', "a\nb"),
		array('<p>before</p><table><tr><td>1</td><td>2</td></tr><tr><td>3</td><td>4</td></tr></table><p>after</p>', "before\n| 1 | 2 |\n| --- | --- |\n| 3 | 4 |\nafter"),
	));

	$answer = workspace_editor_paste_test_run(array(
		array('blocks', "before\n| 1 | 2 |\n| --- | --- |\n| 3 | 4 |\nafter"),
		array('blocks', "| 1 | 2 |\n| --- | --- |\n| 3 | 4 |"),
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('text', 'table', 'text'), $answer[0], 'a pasted table is a card among the words');
	pg_assert_same(array('table'), $answer[1], 'a table alone is one block: the table window opens');
}

// A sheet range is tab-separated rows of one width.
function test_workspace_editor_paste_sheet()
{
	$cases = array(
		array("Name\tQty\nTea\t2\nCoffee\t3\n", "| Name | Qty |\n| --- | --- |\n| Tea | 2 |\n| Coffee | 3 |"),
		array("a\tb\r\nc\td\r\n", "| a | b |\n| --- | --- |\n| c | d |"),
		array("a\t\n\tb", "| a |  |\n| --- | --- |\n|  | b |"),
		array("x|y\tz\n1\t2", "| x\\|y | z |\n| --- | --- |\n| 1 | 2 |"),
		array("one\ttwo", ''),
		array("one\ntwo", ''),
		array("a\tb\nc", ''),
		array("a\tb\tc\nd\te", ''),
		array("plain words", ''),
		array(implode("\t", range(1, 13)) . "\n" . implode("\t", range(1, 13)), ''),
	);
	$steps = array();

	foreach ($cases as $case) {
		$steps[] = array('sheet', $case[0]);
	}

	$answer = workspace_editor_paste_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($cases as $index => $case) {
		pg_assert_same($case[1], $answer[$index], 'sheet: ' . json_encode($case[0]));
	}
}

// One address alone links the words it is pasted over.
function test_workspace_editor_paste_url()
{
	$cases = array(
		array('https://example.com/path?q=1', 'https://example.com/path?q=1'),
		array("  http://a.co\n", 'http://a.co'),
		array('mailto:me@a.co', 'mailto:me@a.co'),
		array('javascript:alert(1)', ''),
		array('https://a.co and more', ''),
		array('see https://a.co', ''),
		array('ftp://a.co/file', ''),
		array('https://a.co/x(y)', ''),
		array('', ''),
	);
	$steps = array();

	foreach ($cases as $case) {
		$steps[] = array('url', $case[0]);
	}

	$answer = workspace_editor_paste_test_run($steps);

	if ($answer === null) {
		return;
	}

	foreach ($cases as $index => $case) {
		pg_assert_same($case[1], $answer[$index], 'url: ' . json_encode($case[0]));
	}
}

// The browser's side: HTML read in a detached document only, the box's own
// nodes put in, plain paste kept for cells and Ctrl+Shift+V, pictures only
// for a box that takes them (a note).
function test_workspace_editor_paste_wiring()
{
	$source = workspace_editor_lists_test_source();
	$paste = workspace_editor_lists_test_function('paste');
	$html = workspace_editor_lists_test_function('pasteHtml');
	$smart = workspace_editor_lists_test_method('pasteSmart');
	$insert = workspace_editor_lists_test_method('insertMarkup');
	$place = workspace_editor_lists_test_method('placeBlocks');

	pg_assert_true(strpos($html, "new DOMParser().parseFromString(html, 'text/html')") !== false, 'HTML is read by DOMParser');
	pg_assert_true(strpos($html, 'pasteMarkup(doc.body)') !== false, 'only the mapped markup leaves pasteHtml()');
	foreach (array($paste, $html, $smart, $insert, $place) as $part) {
		pg_assert_true(strpos($part, 'innerHTML') === false && strpos($part, 'insertHTML') === false, 'pasted HTML is never inserted');
	}

	pg_assert_true(strpos($insert, 'self.placeBlocks(blocks') !== false, 'insertMarkup() places the blocks');
	pg_assert_true(($place !== '') && (strpos($place, 'markupNodes(blocks') !== false), 'the box builds its own nodes from the markup');
	pg_assert_true(strpos($smart, 'pastedUrl(text)') !== false && strpos($smart, 'linkSelection(url)') !== false, 'an address over words links them');
	pg_assert_true(strpos($smart, 'pasteSheet(text)') !== false && strpos($smart, 'this.pasteTable(') !== false, 'a sheet range opens the table window');
	pg_assert_true(strpos($paste, 'singleLine ? null') !== false, 'a one-line surface keeps plain paste');
	pg_assert_true(strpos($paste, '!plain && editor.pasteSmart(') !== false, 'Ctrl+Shift+V keeps plain paste');
	pg_assert_true(strpos($paste, 'editor.options.pasteFiles') !== false, 'pictures only where the box takes them');
	pg_assert_true(strpos($source, "surface.addEventListener('paste', function (event) {\n            paste(surface, event, singleLine);") !== false, 'surfaces paste through paste()');
	pg_assert_true(strpos(workspace_editor_lists_test_method('setMarkup'), 'markupNodes(blocks, labels)') !== false, 'a stored text and a paste are read alike');

	$workspace = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/workspace.js');

	pg_assert_true(strpos($workspace, 'pasteFiles: options.pasteFiles') !== false, 'richField() passes pasteFiles on');
	pg_assert_true(strpos($workspace, 'pasteFiles: function (files) { attach(files); }') !== false, 'a note attaches a pasted picture');
	pg_assert_same(1, substr_count($workspace, 'pasteFiles: function'), 'only the note takes pasted pictures');
}
