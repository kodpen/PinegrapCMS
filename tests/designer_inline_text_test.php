<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for in-place text editing on the visual designer's canvas: a space
 * typed into a button label is kept (written with the editing command, read
 * back as a plain space), a label of a colour, date or file input does not
 * open the browser's chooser, bound text does not advertise a double-click
 * edit, and the canvas document carries the same <html lang> as the export.
 * The helpers are cut out of assets/js/style_designer.js and run under Node
 * with stub documents; on a machine without Node those cases are skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function designer_inline_text_test_source()
{
	return (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
}

// One function of the designer, from its declaration to the closing brace
// at its own indentation.
function designer_inline_text_test_fn($source, $name)
{
	$from = strpos($source, '    function ' . $name . '(');
	pg_assert_true($from !== false, 'the designer has ' . $name . '()');
	$to = strpos($source, "\n    }\n", $from);
	pg_assert_true($to !== false, $name . '() ends');

	return substr($source, $from, $to + 7 - $from);
}

// Runs the expressions in $cases against the in-place editing helpers and
// returns one value per case; null without Node.
function designer_inline_text_test_run($cases)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = designer_inline_text_test_source();
	$from = strpos($source, '// ── In-place text editing helpers');
	$to = strpos($source, 'function _createVirtualText(', (int) $from);
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the in-place editing helpers');

	$script = 'function _sdT(s) { return s; }'
		. 'var document = { documentElement: { lang: "tr" } };'
		// A stub text node, selection and range: enough for the hand-written
		// space of _sdInsertEditSpace().
		. 'function stubDoc(execResult, withSel) {'
		. ' var log = { exec: [], inserted: [], appended: [] };'
		. ' var range = { deleteContents: function () {}, insertNode: function (n) { log.inserted.push(n.data); },'
		. '  setStartAfter: function () {}, collapse: function () {} };'
		. ' var sel = { rangeCount: withSel ? 1 : 0, getRangeAt: function () { return range; }, removeAllRanges: function () {}, addRange: function () {} };'
		. ' var doc = { createTextNode: function (d) { return { data: d }; }, defaultView: { getSelection: function () { return sel; } } };'
		. ' if (execResult !== null) doc.execCommand = function (c, u, v) { log.exec.push(c + ":" + JSON.stringify(v)); if (execResult === "throw") throw new Error("no"); return execResult; };'
		. ' var el = { ownerDocument: doc, appendChild: function (n) { log.appended.push(n.data); } };'
		. ' return { el: el, log: log };'
		. '}'
		. 'function space(execResult, withSel) { var s = stubDoc(execResult, withSel); _sdInsertEditSpace(s.el); return s.log; }'
		. 'function lab(tag, type) { return { control: tag ? { tagName: tag, type: type } : null }; }'
		. designer_inline_text_test_fn($source, '_sdTextIsBound')
		. designer_inline_text_test_fn($source, '_sdDocLang')
		. substr($source, $from, $to - $from)
		. 'console.log(JSON.stringify([' . implode(',', array_map(function ($c) {
			return '(' . $c . ')';
		}, $cases)) . ']));';

	$file = tempnam(sys_get_temp_dir(), 'pginlinetext');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// The space goes in through the editing command, which writes it the way
// typing does; a browser without it gets a U+00A0 at the caret, or at the
// end when there is no selection. Never a plain space that collapses.
function test_designer_inline_text_space_in_button()
{
	$answer = designer_inline_text_test_run(array(
		'space(true, true)',
		'space(false, true)',
		'space("throw", true)',
		'space(null, false)',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('exec' => array('insertText:" "'), 'inserted' => array(), 'appended' => array()), $answer[0], 'the editing command writes the space');
	pg_assert_same(array('exec' => array('insertText:" "'), 'inserted' => array("\u{00a0}"), 'appended' => array()), $answer[1], 'a refused command leaves a non-breaking space at the caret');
	pg_assert_same(array("\u{00a0}"), $answer[2]['inserted'], 'a command that throws leaves a non-breaking space at the caret');
	pg_assert_same(array('exec' => array(), 'inserted' => array(), 'appended' => array("\u{00a0}")), $answer[3], 'without a selection the space goes at the end');
}

// What was typed is saved with plain spaces.
function test_designer_inline_text_plain_spaces()
{
	$answer = designer_inline_text_test_run(array(
		'_sdInlinePlainText("Hemen\u00a0Ara")',
		'_sdInlinePlainText("A\u00a0\u00a0B ")',
		'_sdInlinePlainText(null)',
		'_sdInlinePlainText("Ara")',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('Hemen Ara', 'A  B ', '', 'Ara'), $answer);
}

// Colour, date/time and file inputs open a chooser; checkbox, radio, text
// and select labels keep their native click.
function test_designer_inline_text_label_chooser()
{
	$answer = designer_inline_text_test_run(array(
		'_sdLabelOpensChooser(lab("INPUT", "color"))',
		'_sdLabelOpensChooser(lab("INPUT", "date"))',
		'_sdLabelOpensChooser(lab("INPUT", "time"))',
		'_sdLabelOpensChooser(lab("INPUT", "datetime-local"))',
		'_sdLabelOpensChooser(lab("INPUT", "month"))',
		'_sdLabelOpensChooser(lab("INPUT", "week"))',
		'_sdLabelOpensChooser(lab("INPUT", "FILE"))',
		'_sdLabelOpensChooser(lab("INPUT", "checkbox"))',
		'_sdLabelOpensChooser(lab("INPUT", "radio"))',
		'_sdLabelOpensChooser(lab("INPUT", "text"))',
		'_sdLabelOpensChooser(lab("INPUT", "range"))',
		'_sdLabelOpensChooser(lab("SELECT", "select-one"))',
		'_sdLabelOpensChooser(lab(null))',
		'_sdLabelOpensChooser(null)',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(true, true, true, true, true, true, true, false, false, false, false, false, false, false), $answer);
}

// Bound text and an element with a title or tooltip of its own get no hint.
function test_designer_inline_text_edit_hint()
{
	$answer = designer_inline_text_test_run(array(
		'_sdInlineEditHint({ props: { text: "Title", _bindings: { text: "__result_title" } } }, false)',
		'_sdInlineEditHint({ props: { text: "Title", _bindings: { href: "__result_url" } } }, false)',
		'_sdInlineEditHint({ props: { text: "Title" } }, true)',
		'_sdInlineEditHint({ props: { text: "Title", title: "Own" } }, false)',
		'_sdInlineEditHint({ props: { text: "Title", tooltipEnabled: true, tooltipTitle: "Tip" } }, false)',
		'_sdInlineEditHint({ props: { text: "Title", tooltipEnabled: true } }, false)',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('', 'Double-click to edit', 'Double-click to edit (rich text)', '', '', 'Double-click to edit'), $answer);
}

// The page language comes from the panel's <html lang>, English without one.
function test_designer_inline_text_doc_lang()
{
	$answer = designer_inline_text_test_run(array(
		'_sdDocLang()',
		'(function () { document.documentElement.lang = ""; var l = _sdDocLang(); document.documentElement.lang = "tr"; return l; })()',
	));

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('tr', 'en'), $answer);
}

// Where the helpers are wired in: the canvas document, the export and the
// code view print the same lang; both Space branches use the shared helper;
// the canvas click guard stops a chooser label; the hint skips bound text.
function test_designer_inline_text_wiring()
{
	$source = designer_inline_text_test_source();

	pg_assert_contains("doc.write('<!DOCTYPE html><html lang=\"' + esc(_sdDocLang()) + '\"' + (canvasDarkTheme", $source, 'the canvas document carries the page language');
	pg_assert_contains("<html lang=\"' + esc(_sdDocLang()) + '\">\\n\\n    <head>", $source, 'the HTML export prints the same language');
	pg_assert_contains("var lang = _sdDocLang();", $source, 'the code view prints the same language');
	pg_assert_false(strpos($source, "document.documentElement.lang || 'en'") !== false, 'no second copy of the page language');

	$virtual = designer_inline_text_test_fn($source, '_createVirtualText');
	pg_assert_contains('_sdInsertEditSpace(s);', $virtual, 'the virtual label writes the space through the helper');
	pg_assert_contains('_sdInlinePlainText(s.textContent)', $virtual, 'the virtual label is saved with plain spaces');
	pg_assert_false(strpos($virtual, "createTextNode(' ')") !== false, 'the virtual label writes no plain space node');

	$inline = designer_inline_text_test_fn($source, 'setupInlineEditing');
	pg_assert_contains('_sdInsertEditSpace(item.el);', $inline, 'the in-place edit writes the space through the helper');
	pg_assert_false(strpos($inline, "createTextNode(' ')") !== false, 'the in-place edit writes no plain space node');
	$bound = strpos($inline, 'if (_sdTextIsBound(node)) return;');
	$cursor = strrpos($inline, "item.el.style.cursor = 'text';");
	pg_assert_true(($bound !== false) && ($cursor !== false) && ($bound < $cursor), 'bound text gets no text cursor or hint');
	pg_assert_contains('_sdInlineEditHint(node, isRichText)', $inline, 'the hint comes from the shared helper');

	$value = designer_inline_text_test_fn($source, '_sdInlineEditValue');
	pg_assert_contains('_sdInlinePlainText(src.textContent).trim()', $value, 'plain text edits are read with plain spaces');

	pg_assert_contains("if (_cLab && t !== _cLab.control && _sdLabelOpensChooser(_cLab)) e.preventDefault();", $source, 'the canvas click guard keeps a chooser closed, but not for a click on the control itself');
}
