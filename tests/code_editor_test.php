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

	$empty = strpos($linter, 'if (view.state.doc.length === 0) return out;');
	$tree = strpos($linter, 'cm.language.syntaxTree(view.state)');

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
	pg_assert_contains("if (!dlg || !dlg.classList.contains('modal-fullscreen')) return false;", $modal, 'outside the full window it is the dialog\'s');
}
