<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the code editor helpers of a panel screen
 * (includes/fn/editor.php): the language a code type maps to and the JSON
 * printed into an inline script.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The names get_codemirror_javascript() takes, and CodeMirror 5's MIME
// names, land on the four grammars the facade carries.
function test_code_editor_language_maps_the_code_types()
{
	pg_assert_same('css', pg_code_editor_language('css'), 'css');
	pg_assert_same('css', pg_code_editor_language('text/css'), 'text/css');
	pg_assert_same('javascript', pg_code_editor_language('javascript'), 'javascript');
	pg_assert_same('javascript', pg_code_editor_language('js'), 'js');
	pg_assert_same('javascript', pg_code_editor_language('text/javascript'), 'text/javascript');
	pg_assert_same('json', pg_code_editor_language('application/json'), 'application/json');
	pg_assert_same('json', pg_code_editor_language('json'), 'json');
	pg_assert_same('html', pg_code_editor_language('mixed'), 'mixed');
	pg_assert_same('html', pg_code_editor_language('php'), 'php');
	pg_assert_same('html', pg_code_editor_language('xml'), 'xml');
	pg_assert_same('html', pg_code_editor_language('svg'), 'svg');
	pg_assert_same('html', pg_code_editor_language('text/plain'), 'text/plain');
}

// Case and surrounding space do not change the answer; an unknown or empty
// type is shown as HTML, which is what CodeMirror 5 fell back to.
function test_code_editor_language_tolerates_case_space_and_unknown_types()
{
	pg_assert_same('css', pg_code_editor_language(' CSS '), 'upper case with spaces');
	pg_assert_same('javascript', pg_code_editor_language('JavaScript'), 'mixed case');
	pg_assert_same('html', pg_code_editor_language(''), 'empty');
	pg_assert_same('html', pg_code_editor_language(null), 'null');
	pg_assert_same('html', pg_code_editor_language('clike.java'), 'a type the facade does not carry');
}

// A translated phrase printed into an inline <script> can never end the
// script element or start a tag.
function test_code_editor_json_escapes_markup()
{
	$json = pg_code_editor_json(array('phrases' => array('close' => '</script><b>&')));

	pg_assert_false(strpos($json, '</script>') !== false, 'no closing script tag');
	pg_assert_false(strpos($json, '<b>') !== false, 'no tag');
	pg_assert_same(array('phrases' => array('close' => '</script><b>&')), json_decode($json, true), 'the value survives the round trip');
	pg_assert_same('"ağaç"', pg_code_editor_json('ağaç'), 'UTF-8 stays readable');
}

// Plain text keeps the HTML colours but not the HTML lint: a stray < in a
// list of options is not an error.
function test_code_editor_is_plain_names_the_text_types()
{
	pg_assert_true(pg_code_editor_is_plain('plain'), 'plain');
	pg_assert_true(pg_code_editor_is_plain('text'), 'text');
	pg_assert_true(pg_code_editor_is_plain('text.txt'), 'text.txt');
	pg_assert_true(pg_code_editor_is_plain('text/plain'), 'text/plain');
	pg_assert_false(pg_code_editor_is_plain('mixed'), 'mixed');
	pg_assert_false(pg_code_editor_is_plain('php'), 'php');
	pg_assert_false(pg_code_editor_is_plain(''), 'empty');
}

// Runs the facade's tracked ranges and marks under Node: the range classes
// and fields of pg_code_editor.js and the instance's "Tracked ranges" and
// "Marks" blocks, on the engine's own state package from the bundle and a
// view that only holds and updates a state. Prints the script's JSON; null
// when there is no Node, ['error' => …] when a block is not found.
function code_editor_test_ranges_node($script)
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

	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$cut = function ($from, $to) use ($facade) {
		$a = strpos($facade, $from);
		$b = ($a !== false) ? strpos($facade, $to, $a) : false;

		return ($b !== false) ? substr($facade, $a, $b - $a) : '';
	};
	$classes = $cut('    function _rangeClasses(', '    // Sorted, merged [from, to] pairs.');
	$tracked = $cut('        // ── Tracked ranges ──', '        // ── Locks ──');
	$marks = $cut('        // ── Marks (', '        inst.destroy = function');

	if (($classes === '') || ($tracked === '') || ($marks === '')) {
		return array('error' => 'a block of the facade was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcodeeditor');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'global.window = global;' . "\n"
		. '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js') . ', "utf8"));' . "\n"
		. $classes . "\n"
		. 'var cm = { state: PgCM.state, view: PgCM.view }, fields = _rangeFields(cm), inst = {};' . "\n"
		. 'var view = { state: PgCM.state.EditorState.create({ doc: "<body>\n</body>", extensions: [fields.trackedField] }),' . "\n"
		. '    dispatch: function (spec) { this.state = this.state.update(spec).state; } };' . "\n"
		. $tracked . "\n" . $marks . "\n"
		. 'function type(at, text) { view.dispatch({ changes: { from: at, insert: text } }); }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// mark() rebuilds the set without changing the other ranges: an empty range
// that grows (the designer's page content on an empty page) still takes the
// text typed at its position, and another mark keeps its class. Before, every
// other range came back as not growing and without a class, so the first
// keystroke on an empty page dropped the content range and nothing typed
// there reached the canvas.
function test_code_editor_mark_keeps_the_other_ranges()
{
	$result = code_editor_test_ranges_node(
		'inst.track([{ id: "content", from: 7, to: 7, grow: true }, { id: "tag", from: 0, to: 6 }, { id: "hover", from: 0, to: 6, cls: "x-hover" }]);' . "\n"
		. 'inst.mark("sel", 0, 6);' . "\n"
		. 'var after = inst.tracked("content");' . "\n"
		. 'type(7, "<p>Hi</p>");' . "\n"
		. 'type(0, " ");' . "\n"
		. 'function at(id) { var r = inst.tracked(id); return r ? [r.from, r.to, !!r.grow, r.cls] : null; }' . "\n"
		. 'console.log(JSON.stringify({ grows: !!(after && after.grow), content: at("content"), tag: at("tag"), hover: at("hover"), sel: at("sel") }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_true($result['grows'] === true, 'the content range still grows after a mark');
	pg_assert_same(array(8, 17, true, ''), $result['content'], 'what is typed in the empty range is inside it');
	pg_assert_same(array(1, 7, false, ''), $result['tag'], 'a range that does not grow leaves what is typed at its start outside');
	pg_assert_same(array(1, 7, false, 'x-hover'), $result['hover'], 'another mark keeps its class');
	pg_assert_same(array(1, 7, false, 'pg-ce-mark'), $result['sel'], 'the new mark has the default class');
}

// The source of the facade's syntax linter, up to the next function of the
// facade.
function code_editor_test_linter_source()
{
	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, 'function _syntaxLinter(');

	if ($from === false) {
		return '';
	}

	$next = strpos($facade, "\n    function ", $from + 10);

	return ($next !== false) ? substr($facade, $from, $next - $from) : substr($facade, $from);
}

// An empty field is not a syntax error: the HTML parser leaves a zero-length
// error node at the start of an empty document and the JSON parser reports
// an unexpected end, so the linter checks nothing until there is text (for
// JSON, more than white space).
function test_code_editor_lint_skips_an_empty_document()
{
	$linter = code_editor_test_linter_source();

	pg_assert_true($linter !== '', 'the linter is found');

	$empty = strpos($linter, 'if (len === 0) return [];');
	$tree = strpos($linter, 'cm.language.ensureSyntaxTree(state, len, 50)');

	pg_assert_true(($empty !== false) && ($tree !== false) && ($empty < $tree), 'an empty document is not parsed for errors');

	$blank = strpos($linter, '/\S/.test(view.state.doc.toString()) ? json(view) : []');

	pg_assert_true($blank !== false, 'a blank JSON document is not handed to the JSON parser');
}

// attach() sizes its host by `width` as well as `height`, builds the editor
// in a shadow root with `isolate` (out of reach of a container that resets
// every element in it), and hands back the host as `container`.
function test_code_editor_attach_can_isolate_the_editor()
{
	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, 'function attach(');
	$attach = ($from !== false) ? substr($facade, $from, strpos($facade, "\n    window.PgCodeEditor = {", $from) - $from) : '';

	pg_assert_contains('if (opts.width) host.style.width = opts.width;', $attach, 'width');
	pg_assert_contains("if (opts.isolate && host.attachShadow) {", $attach, 'isolate asks for a shadow root');
	pg_assert_contains('host.attachShadow({ mode: \'open\' }).appendChild(mount);', $attach, 'the editor is mounted inside it');
	pg_assert_contains('return pgCeCreate(mount, merged);', $attach, 'the editor is built on the mount');
	pg_assert_contains('inst.container = host;', $attach, 'the host is handed back');
}

// The rich text editor's Source dialog resets every element inside it and
// presses OK on Enter: its code editor is isolated, as wide as the textarea
// the dialog was sized by, and keeps Enter, Tab and the keys it used.
function test_code_editor_ckeditor_source_dialog()
{
	$plugin = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_ckeditor_source.js');
	$dialog = substr($plugin, (int) strpos($plugin, "CKEDITOR.on('dialogDefinition'"));

	pg_assert_contains("{ width: (textarea.offsetWidth || 800) + 'px', isolate: true }", $dialog, 'isolated, as wide as the textarea');
	pg_assert_contains('keepKeys(instance.container || instance.host);', $dialog, 'its keys stop at the editor');
	pg_assert_contains("if ((event.key === 'Enter') || (event.key === 'Tab') || event.defaultPrevented) {", $plugin, 'Enter, Tab and the keys the editor used');
	pg_assert_contains('event.stopPropagation();', $plugin, 'do not reach the dialog');
}

// The linter reads the syntax tree only once it covers the whole document.
// The engine parses no further than 100,000 characters past the visible
// part on its own, and a tree cut there carries an error node at the cut: a
// valid line of a larger file was marked and nothing after it was checked.
// The rest is parsed in 50 ms slices with a pause between them, a result for
// a text that changed meanwhile is dropped, and at most 200 errors are
// marked.
function test_code_editor_lint_reads_the_whole_document()
{
	$linter = code_editor_test_linter_source();

	pg_assert_false(strpos($linter, 'cm.language.syntaxTree(') !== false, 'the tree as far as the background parse got is not read');
	pg_assert_contains('var tree = cm.language.ensureSyntaxTree(state, len, 50);', $linter, 'parsed to the end of the document, 50 ms at a time');
	pg_assert_contains('if (!tree) { setTimeout(slice, 0); return; }', $linter, 'the next slice after a pause');
	pg_assert_contains('if (view.state.doc !== state.doc) { resolve([]); return; }', $linter, 'a text changed meanwhile is not parsed further');
	pg_assert_contains('if (out.length >= 200) return false;', $linter, 'at most 200 errors');

	$cap = strpos($linter, 'if (out.length >= 200) return false;');
	$push = strpos($linter, 'out.push(');

	pg_assert_true(($cap !== false) && ($push !== false) && ($cap < $push), 'the cap is checked before each node, not only after a push');
}

// A lint run that is still parsing when the linter is replaced (setLanguage,
// setLint) or the editor is closed stops at its next slice without a result.
// The lint extension applies any result whose document is unchanged and adds
// its state back when the linter was switched off, so a late result would
// leave marks that no linter clears. Every linter the editor builds gets a
// new generation, and only the current one may deliver.
function test_code_editor_lint_stops_when_the_linter_is_replaced()
{
	$linter = code_editor_test_linter_source();

	pg_assert_contains('function _syntaxLinter(cm, lang, labels, alive)', $linter, 'the linter is told how to ask whether it is still current');

	$stop = strpos($linter, 'if (alive && !alive()) return;');
	$parse = strpos($linter, 'cm.language.ensureSyntaxTree(state, len, 50)');

	pg_assert_true(($stop !== false) && ($parse !== false) && ($stop < $parse), 'a replaced linter stops before the next slice is parsed');

	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, 'function pgCeCreate(');
	$create = ($from !== false) ? substr($facade, $from, strpos($facade, "\n    function open(", $from) - $from) : '';

	pg_assert_contains('var gen = ++lintGen;', $create, 'each linter built gets a new generation');
	pg_assert_contains('return !destroyed && gen === lintGen;', $create, 'only the current linter of an open editor delivers');
	pg_assert_true(substr_count($create, '_syntaxLinter(') === 1, 'every linter is built through the one generation counter');
	pg_assert_true(substr_count($create, 'lintComp.of(lintExtension())') === 1, 'the first linter');
	pg_assert_true(substr_count($create, 'lintComp.reconfigure(lintExtension())') === 2, 'setLanguage and setLint');
	pg_assert_true(preg_match('/destroyed = true;\s+lintGen\+\+;/', $create) === 1, 'closing the editor stops a running lint');
}

// An Escape the editor used (its completion list, its search panel, a full
// window a screen bound to it) stops at the editor's outer box: a Bootstrap
// modal closes on every Escape that reaches it, prevented or not, and took
// the typed code with it. The designer's code dialog leaves its full window
// on Escape before the next one cancels the dialog.
function test_code_editor_escape_it_used_stays_in_the_editor()
{
	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, 'function pgCeCreate(');
	$create = ($from !== false) ? substr($facade, $from, strpos($facade, "\n    function open(", $from) - $from) : '';

	pg_assert_contains("view.dom.addEventListener('keydown', function (event) {", $create, 'the outer box listens');
	pg_assert_contains("if ((event.key === 'Escape') && event.defaultPrevented) event.stopPropagation();", $create, 'a used Escape goes no further');

	$modal = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/codemirror_modal.js');

	pg_assert_contains("{ key: 'Escape', run: _leaveFullscreen }", $modal, 'Escape leaves the full window first');
	pg_assert_contains("if (!_modalEl || !_modalEl.classList.contains('dialog-fullscreen')) return false;", $modal, 'outside the full window it is the dialog\'s');
}

// Runs PgCodeEditor.findAll() under Node: the function cut from the facade
// and the script's JSON. Null when there is no Node.
function code_editor_test_find_all_node($script)
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

	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, '    function findAll(');
	$to = ($from !== false) ? strpos($facade, "\n    }\n", $from) : false;

	if ($to === false) {
		return array('error' => 'findAll() was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcodefind');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, substr($facade, $from, $to - $from + 6) . "\n"
		. 'function hits(r) { return r.matches.map(function (m) { return [m.from, m.to, m.line, m.col, m.lineText]; }); }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A plain search ignores case unless asked, takes regexp characters
// literally, and gives each hit its line, column and line text.
function test_code_editor_find_all_plain_search()
{
	$result = code_editor_test_find_all_node(
		'var t = ".a { color: red }\n.b { COLOR: blue }\n/* a.b */";' . "\n"
		. 'console.log(JSON.stringify({'
		. ' any: hits(findAll(t, "color")),'
		. ' cased: hits(findAll(t, { search: "COLOR", caseSensitive: true })),'
		. ' literal: hits(findAll(t, "a.b")),'
		. ' empty: findAll(t, "").matches.length,'
		. ' none: findAll(t, "zzz").matches.length }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(array(5, 10, 1, 5, '.a { color: red }'), array(23, 28, 2, 5, '.b { COLOR: blue }')), $result['any'], 'both lines, case ignored');
	pg_assert_same(array(array(23, 28, 2, 5, '.b { COLOR: blue }')), $result['cased'], 'match case');
	pg_assert_same(array(array(40, 43, 3, 3, '/* a.b */')), $result['literal'], 'the dot is literal');
	pg_assert_same(0, $result['empty'], 'an empty search finds nothing');
	pg_assert_same(0, $result['none'], 'no hit');
}

// A regexp reads ^ per line and skips empty matches; whole words leave a
// hit inside a longer word out; an invalid pattern is an error, and the
// limit cuts the list and says so.
function test_code_editor_find_all_regexp_words_and_limit()
{
	$result = code_editor_test_find_all_node(
		'var t = "var x = 1;\nvar xy = x;\n";' . "\n"
		. 'var bad = findAll(t, { search: "(", regexp: true });' . "\n"
		. 'var cut = findAll("aaaa", "a", 3);' . "\n"
		. 'console.log(JSON.stringify({'
		. ' starts: hits(findAll(t, { search: "^var", regexp: true })),'
		. ' blank: findAll(t, { search: "^", regexp: true }).matches.length,'
		. ' words: hits(findAll(t, { search: "x", wholeWord: true })),'
		. ' bad: [!!bad.error, bad.matches.length],'
		. ' cut: [cut.matches.length, cut.truncated] }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(array(0, 3, 1, 0, 'var x = 1;'), array(11, 14, 2, 0, 'var xy = x;')), $result['starts'], 'one hit per line start');
	pg_assert_same(0, $result['blank'], 'empty matches are skipped');
	pg_assert_same(array(array(4, 5, 1, 4, 'var x = 1;'), array(20, 21, 2, 9, 'var xy = x;')), $result['words'], 'xy is not the word x');
	pg_assert_same(array(true, 0), $result['bad'], 'an invalid pattern');
	pg_assert_same(array(3, true), $result['cut'], 'three hits, more left out');
}

// Runs the facade's formatter under Node: _formatText() cut from the facade
// on the bundle's window.PgCM. Null when there is no Node.
function code_editor_test_format_node($script)
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

	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, '    function _formatText(');
	$to = ($from !== false) ? strpos($facade, "\n    }\n", $from) : false;

	if ($to === false) {
		return array('error' => '_formatText() was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcodeformat');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'global.window = global;' . "\n"
		. '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js') . ', "utf8"));' . "\n"
		. substr($facade, $from, $to - $from + 6) . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// CSS, JavaScript and JSON are formatted with the editor's indent unit; HTML
// and an unknown language are not formatted (the designer's page view is
// printed by its emitter already), and the result ends without a new line
// when the text did.
function test_code_editor_format_text_by_language()
{
	$result = code_editor_test_format_node(
		'var cm = window.PgCM;' . "\n"
		. 'console.log(JSON.stringify({'
		. ' css: _formatText(cm, "css", "a{color:red;margin:0}", "    "),'
		. ' js: _formatText(cm, "javascript", "function f(a){if(a){return 1}return 2}", "  "),'
		. ' json: _formatText(cm, "json", "{\"a\":[1,2],\"b\":{\"c\":true}}", "\t"),'
		. ' newline: _formatText(cm, "css", "a{color:red}\n", "    "),'
		. ' html: _formatText(cm, "html", "<p><b>x</b></p>", "    "),'
		. ' other: _formatText(cm, "php", "x", "    ") }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same("a {\n    color: red;\n    margin: 0\n}", $result['css'], 'css, four spaces');
	pg_assert_same("function f(a) {\n  if (a) {\n    return 1\n  }\n  return 2\n}", $result['js'], 'javascript, two spaces');
	pg_assert_same("{\n\t\"a\": [1, 2],\n\t\"b\": {\n\t\t\"c\": true\n\t}\n}", $result['json'], 'json, a tab');
	pg_assert_same("a {\n    color: red\n}\n", $result['newline'], 'the closing new line stays');
	pg_assert_same(null, $result['html'], 'html is not formatted');
	pg_assert_same(null, $result['other'], 'an unknown language is not formatted');
}

// ed.format() changes nothing in a read-only editor or across a lock, and
// replaces only the part that differs in one change through the lock
// filter: one undo step that onChange hears like typing. Shift+Alt+F runs
// it in both keymaps.
function test_code_editor_format_command()
{
	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, '        inst.format = function');
	$format = ($from !== false) ? substr($facade, $from, strpos($facade, "\n        };\n", $from) - $from) : '';

	pg_assert_true($format !== '', 'ed.format() is found');
	pg_assert_contains('if (view.state.readOnly) return false;', $format, 'read-only refuses');
	pg_assert_contains('if (!inst.canEdit(from, toA)) return false;', $format, 'a lock over the changed part refuses');
	pg_assert_contains("userEvent: 'input.format'", $format, 'one change, as an edit');
	pg_assert_false(strpos($format, 'filter: false') !== false, 'the lock filter is not bypassed');
	pg_assert_false(strpos($format, 'addToHistory') !== false, 'the change is undoable');

	$keys = strpos($facade, 'function keymapExtensions()');
	$keymap = ($keys !== false) ? substr($facade, $keys, strpos($facade, "\n        }\n", $keys) - $keys) : '';
	$vscode = strpos($keymap, 'if (useVscode)');
	$shortcut = strpos($keymap, "{ key: 'Shift-Alt-f', run: function () { return inst.format(); } }");

	pg_assert_true(($vscode !== false) && ($shortcut !== false) && ($shortcut < $vscode), 'Shift+Alt+F comes before either keymap');
}

// Runs the facade's minimap part under Node: _minimapExtension() cut from
// the facade on the bundle's window.PgCM. Null when there is no Node.
function code_editor_test_minimap_node($script)
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

	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');
	$from = strpos($facade, '    function _minimapExtension(');
	$to = ($from !== false) ? strpos($facade, "\n    }\n", $from) : false;

	if ($to === false) {
		return array('error' => '_minimapExtension() was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcodeminimap');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'global.window = global;' . "\n"
		. '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js') . ', "utf8"));' . "\n"
		. substr($facade, $from, $to - $from + 6) . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// The bundle carries the minimap; an editor opened with `minimap` gets the
// engine's showMinimap facet (drawn as blocks, its overlay only on hover),
// one without it gets nothing.
function test_code_editor_minimap_extension()
{
	$result = code_editor_test_minimap_node(
		'var cm = window.PgCM;' . "\n"
		. 'function conf(on) {'
		. ' var st = cm.state.EditorState.create({ doc: "a", extensions: _minimapExtension(cm, on) });'
		. ' var c = st.facet(cm.minimap.showMinimap);'
		. ' return c ? { create: typeof c.create, text: c.displayText, overlay: c.showOverlay } : null; }' . "\n"
		. 'console.log(JSON.stringify({ bundled: !!(cm.minimap && cm.minimap.showMinimap), on: conf(true), off: conf(false) }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_true($result['bundled'], 'the bundle exports PgCM.minimap');
	pg_assert_same(array('create' => 'function', 'text' => 'blocks', 'overlay' => 'mouse-over'), $result['on'], 'minimap on');
	pg_assert_same(null, $result['off'], 'minimap off');
}

// The option is opt-in per editor, with a page default from configure(), and
// can be switched while the editor is open; the designer turns it on.
function test_code_editor_minimap_option()
{
	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');

	pg_assert_contains("if (typeof opts.minimap === 'boolean') _cfg.minimap = opts.minimap;", $facade, 'configure() takes the page default');
	pg_assert_contains('minimapComp.of(_minimapExtension(cm, _minimapOn(opts)))', $facade, 'the editor reads the option');
	pg_assert_contains('inst.setMinimap = function', $facade, 'the instance can switch it');
	pg_assert_contains('inst.hasMinimap = function () { return _minimapOn(opts); };', $facade, 'and tells whether it is on');

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_contains('PgCodeEditor.configure({ minimap: true, bundle:', $screen, 'the designer turns it on');
}

// A code field that can be edited gets a "Format code" button that follows
// the editor's language (create_file.php changes it after the start); a
// read-only field gets none. The button runs the instance's format().
// Drawn in a PHP process of its own: the field's first script names the
// facade on OUTPUT_PATH, which the tests after this one must not find defined.
function test_code_editor_attach_offers_a_format_button()
{
	if (!function_exists('exec') || !defined('PHP_BINARY') || (PHP_BINARY === '')) {
		return;
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcodeattach');

	if ($file === false) {
		return;
	}

	file_put_contents($file, '<?php' . "\n"
		. "define('PG_TEST_RUNNER', true);\n"
		. "define('OUTPUT_PATH', '/');\n"
		. "define('OUTPUT_SOFTWARE_DIRECTORY', 'pinegrap');\n"
		. 'require ' . var_export(PG_FUNCTIONS_DIR . '/functions.php', true) . ";\n"
		. "echo json_encode(array("
		. "'css' => pg_code_editor_attach(array('id' => 'code', 'code_type' => 'css')),"
		. "'ro' => pg_code_editor_attach(array('id' => 'code', 'code_type' => 'css', 'readonly' => true))));\n");

	$out = array();
	$code = 1;
	@exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);
	$res = json_decode(implode("\n", $out), true);

	pg_assert_same(0, $code, 'drawn: ' . implode("\n", array_slice($out, 0, 8)));
	if (!is_array($res)) {
		pg_assert_true(false, 'the process answers');
		return;
	}

	$css = $res['css'];
	$ro = $res['ro'];

	pg_assert_contains('pg-code-field-format', $css, 'an editable field draws the button');
	pg_assert_contains(lang('Format code'), $css, 'with a translated label');
	pg_assert_contains('editor.format()', $css, 'which runs the instance\'s format()');
	pg_assert_contains('["css","javascript","json"]', $css, 'shown only for the languages with a formatter');
	pg_assert_contains('editor.setLanguage = function', $css, 'and kept in step with a language change');
	pg_assert_false(strpos($ro, 'pg-code-field-format') !== false, 'a read-only field has no button');

	// Every field, read-only too, gets the minimap toggle, which starts from
	// the editor's own state and switches it with setMinimap().
	foreach (array('css' => $css, 'ro' => $ro) as $kind => $html) {
		pg_assert_contains('pg-code-field-minimap', $html, $kind . ': the minimap toggle is drawn');
		pg_assert_contains(lang('Minimap'), $html, $kind . ': with a translated label');
		pg_assert_contains('aria-pressed', $html, $kind . ': as a toggle button');
		pg_assert_contains('editor.hasMinimap()', $html, $kind . ': starting from the current state');
		pg_assert_contains('editor.setMinimap(', $html, $kind . ': switching the instance');
	}
}

// The code editor dialog's footer shows "Format code" only for CSS,
// JavaScript and JSON when it is not read-only; its label comes from the
// designer's PgCodeModalLabels map.
function test_code_editor_dialog_offers_a_format_button()
{
	$modal = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/codemirror_modal.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_contains('id="pgCodeEditorFormat"', $modal, 'the footer has the button');
	pg_assert_contains("_t('Format code')", $modal, 'its label is read from the map');
	pg_assert_contains('_editor.format()', $modal, 'it runs the editor\'s format()');
	pg_assert_contains("_formatBtn.classList.toggle('d-none', !!opts.readonly || !_formats(opts.mode));", $modal, 'hidden for read-only and other languages');
	pg_assert_contains("'Format code'  => lang('Format code'),", $screen, 'the designer prints the label');
}

// The designer's bottom code view offers "Format code" in its strip for a
// loaded, editable CSS, JavaScript or JSON file tab; the page tab (HTML) and
// a read-only tab get none. The button runs the tab editor's format().
function test_code_editor_designer_view_offers_a_format_button()
{
	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($js, '    function _sdCodeFormats(tab) {');
	$check = ($from !== false) ? substr($js, $from, strpos($js, "\n    }\n", $from) - $from) : '';

	pg_assert_true($check !== '', '_sdCodeFormats() is found');
	pg_assert_contains("tab.key === 'page'", $check, 'the page tab has no button');
	pg_assert_contains('tab.spec.readOnly', $check, 'a read-only tab has no button');
	pg_assert_contains('!tab.loaded', $check, 'nor a tab whose text is not loaded');
	pg_assert_contains("['css', 'javascript', 'json'].indexOf(tab.ed.getLanguage())", $check, 'only the languages with a formatter');
	pg_assert_contains('if (_sdCodeFormats(active)) {', $js, 'the strip asks it for the active tab');
	pg_assert_contains('id="sd-code-format"', $js, 'the strip draws the button');
	pg_assert_contains("esc(_sdT('Format code'))", $js, 'with a translated label');
	pg_assert_contains('active.ed.format();', $js, 'which runs the tab editor\'s format()');
}

// The dialog's footer has a minimap toggle beside "Format code": a toggle
// button (aria-pressed) that starts from the editor's state and switches it.
function test_code_editor_dialog_offers_a_minimap_toggle()
{
	$modal = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/codemirror_modal.js');
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/designer_screen.php');

	pg_assert_contains('id="pgCodeEditorMinimap"', $modal, 'the footer has the toggle');
	pg_assert_contains('aria-pressed="false"', $modal, 'as a toggle button');
	pg_assert_contains("_t('Minimap')", $modal, 'its label is read from the map');
	pg_assert_contains('_editor.setMinimap(', $modal, 'it switches the editor');
	pg_assert_contains('_editor.hasMinimap()', $modal, 'and starts from the editor\'s state');
	pg_assert_contains("'Minimap'      => lang('Minimap'),", $screen, 'the designer prints the label');
}
