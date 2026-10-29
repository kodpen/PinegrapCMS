<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for Emmet in the code editor: the emmet core pinned in the bundle's
 * package files and built into the bundle, the facade's context lookup and
 * snippet template (run under Node on the bundle, skipped where there is no
 * Node), and the facade's wiring (Mod+E, the option, read-only and locks).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script after the facade's language, completion and Emmet blocks, on
// the bundle. The script has `expand(doc, lang)`: the document with a |
// where the cursor is, expanded the way inst.expandAbbreviation() does on a
// state; it gives { text, sel } (the text with a | at the cursor, the
// selected field's text) or null when nothing is expanded. Prints the
// script's JSON; null when there is no Node.
function code_editor_emmet_node($script)
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
	$a = strpos($facade, '    function _languageObject(');
	$b = ($a !== false) ? strpos($facade, '    // Syntax errors as diagnostics:', $a) : false;
	$c = strpos($facade, '    // ── Emmet ');
	$d = ($c !== false) ? strpos($facade, '    // ── Instance ', $c) : false;

	if (($b === false) || ($d === false)) {
		return array('error' => 'the completion or Emmet block of the facade was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgceemmet');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'global.window = global;' . "\n"
		. '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js') . ', "utf8"));' . "\n"
		. 'var cm = PgCM, _completions = {}, _rankers = {}, _cfg = { emmet: true };' . "\n"
		. substr($facade, $a, $b - $a) . "\n"
		. substr($facade, $c, $d - $c) . "\n"
		. 'function expand(doc, lang) {' . "\n"
		. '    var pos = doc.indexOf("|"); doc = doc.replace("|", "");' . "\n"
		. '    var editor = { state: cm.state.EditorState.create({ doc: doc, selection: { anchor: pos }, extensions: [lang === "css" ? cm.langCss.css() : cm.langHtml.html(), cm.language.indentUnit.of("  ")] }) };' . "\n"
		. '    editor.dispatch = function (tr) { editor.state = tr.state; };' . "\n"
		. '    cm.language.ensureSyntaxTree(editor.state, doc.length, 5000);' . "\n"
		. '    var snip = _emmetSnippet(cm, editor.state, pos);' . "\n"
		. '    if (!snip) return null;' . "\n"
		. '    cm.autocomplete.snippet(snip.template)(editor, null, snip.from, snip.to);' . "\n"
		. '    var s = editor.state, m = s.selection.main, t = s.doc.toString();' . "\n"
		. '    return { text: t.slice(0, m.head) + "|" + t.slice(m.head), sel: s.sliceDoc(m.from, m.to) };' . "\n"
		. '}' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// The emmet core and its dependencies are pinned at exact versions, all MIT;
// the plugin without a license is never among them.
function test_code_editor_emmet_package_pins()
{
	$manifest = json_decode((string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/tools/code_editor/package.json'), true);
	$lock = json_decode((string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/tools/code_editor/package-lock.json'), true);

	pg_assert_same('2.4.11', $manifest['dependencies']['emmet'], 'emmet pinned at an exact version');
	pg_assert_false(isset($manifest['dependencies']['@emmetio/codemirror6-plugin']), 'the unlicensed plugin is not a dependency');

	$expected = array(
		'node_modules/emmet' => '2.4.11',
		'node_modules/@emmetio/abbreviation' => '2.3.3',
		'node_modules/@emmetio/css-abbreviation' => '2.1.8',
		'node_modules/@emmetio/scanner' => '1.0.4',
	);

	foreach ($expected as $path => $version) {
		pg_assert_same($version, $lock['packages'][$path]['version'], $path . ' locked');
		pg_assert_same('MIT', $lock['packages'][$path]['license'], $path . ' is MIT');
	}

	pg_assert_false(isset($lock['packages']['node_modules/@emmetio/codemirror6-plugin']), 'the unlicensed plugin is not locked');
}

// The entry exports the core as PgCM.emmet and the bundle carries it, named
// in its banner.
function test_code_editor_emmet_in_the_bundle()
{
	$entry = (string) file_get_contents(dirname(PG_FUNCTIONS_DIR) . '/tools/code_editor/entry.js');
	$bundle = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js');

	pg_assert_contains("import emmetExpand, { extract as emmetExtract } from 'emmet';", $entry, 'the entry imports the core');
	pg_assert_contains('export const emmet = { expand: emmetExpand, extract: emmetExtract };', $entry, 'and exports it');
	pg_assert_contains(' *   emmet 2.4.11', substr($bundle, 0, 2000), 'the banner names emmet');
	pg_assert_contains('https://github.com/emmetio/emmet', substr($bundle, 0, 2000), 'and its home');
}

// Markup in HTML text, with the parent element as context; stylesheet in a
// CSS block, a <style> block and a style="" attribute; Emmet's fields become
// tab-stops, the first selected.
function test_code_editor_emmet_expands_by_context()
{
	$result = code_editor_emmet_node(
		'console.log(JSON.stringify({' . "\n"
		. '    list: expand("<div>\n  ul>li*2|\n</div>"),' . "\n"
		. '    img: expand("<p>img|</p>"),' . "\n"
		. '    item: expand("<ul>.item|</ul>"),' . "\n"
		. '    attr: expand("<a>a[title=x]|</a>"),' . "\n"
		. '    closed: expand("<p>a[title=x|]</p>"),' . "\n"
		. '    css: expand(".a { p10| }", "css"),' . "\n"
		. '    color: expand(".a { c| }", "css"),' . "\n"
		. '    style: expand("<style>\n.a { m0| }\n</style>"),' . "\n"
		. '    inline: expand("<div style=\"p10|\">x</div>")' . "\n"
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same("<div>\n  <ul>\n    <li>|</li>\n    <li></li>\n  </ul>\n</div>", $result['list']['text'], 'nested lines take the line indent and the indent unit');
	pg_assert_same('<p><img src="|" alt=""></p>', $result['img']['text'], 'the first field holds the cursor');
	pg_assert_same('<ul><li class="item">|</li></ul>', $result['item']['text'], 'the parent element is the context');
	pg_assert_same('<a><a href="|" title="x"></a></a>', $result['attr']['text'], 'attributes in brackets, the empty href first');
	pg_assert_same('<p><a href="|" title="x"></a></p>', $result['closed']['text'], 'an auto-closed ] after the cursor belongs to the abbreviation');
	pg_assert_same('.a { padding: 10px;| }', $result['css']['text'], 'a CSS editor expands stylesheet');
	pg_assert_same('#000', $result['color']['sel'], 'a placeholder is selected as the field');
	pg_assert_same("<style>\n.a { margin: 0;| }\n</style>", $result['style']['text'], 'inside <style>');
	pg_assert_same('<div style="padding: 10px;|">x</div>', $result['inline']['text'], 'inside style=""');
}

// Nothing is expanded inside a tag, a comment, a <script>, a CSS selector or
// a CSS editor outside a block, or with no abbreviation; `#{` in the
// expanded text stays text, not a field.
function test_code_editor_emmet_refuses_outside_its_contexts()
{
	$result = code_editor_emmet_node(
		'console.log(JSON.stringify({' . "\n"
		. '    tag: expand("<div class=\"ul|\">x</div>"),' . "\n"
		. '    name: expand("<di|"),' . "\n"
		. '    comment: expand("<!-- ul|"),' . "\n"
		. '    script: expand("<script>\nul|\n</script>"),' . "\n"
		. '    selector: expand("p10|", "css"),' . "\n"
		. '    empty: expand("<p>|</p>"),' . "\n"
		. '    hash: expand("<p>span{a#{b}c}|</p>")' . "\n"
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	foreach (array('tag', 'name', 'comment', 'script', 'selector', 'empty') as $where) {
		pg_assert_same(null, $result[$where], 'nothing expanded: ' . $where);
	}

	pg_assert_same('<p><span>a#{b}c</span>|</p>', $result['hash']['text'], '#{ in the text stays text, not a field');
}

// The facade's wiring: Mod+E (not Tab) runs the command when the option is
// on, the option has a page default and a switch, and the command refuses
// read-only editors, other languages and locked text (markup even in a
// lock's island).
function test_code_editor_emmet_facade_wiring()
{
	$facade = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_code_editor.js');

	pg_assert_contains("if (typeof opts.emmet === 'boolean') _cfg.emmet = opts.emmet;", $facade, 'configure() takes the page default');
	pg_assert_contains("if (_emmetOn(opts)) own.push({ key: 'Mod-e', run: function () { return inst.expandAbbreviation(); } });", $facade, 'Mod+E runs it in both keymaps');
	pg_assert_false(strpos($facade, "key: 'Tab', run: function () { return inst.expandAbbreviation") !== false, 'Tab does not expand');
	pg_assert_contains("if (!_emmetOn(opts) || (lang !== 'html' && lang !== 'css')) return false;", $facade, 'only HTML and CSS editors');
	pg_assert_contains('if (view.state.readOnly || view.state.selection.ranges.length !== 1) return false;', $facade, 'never in a read-only editor');
	pg_assert_contains('if (!snip || !inst.canEdit(snip.from, snip.to)) return false;', $facade, 'never over a lock');
	pg_assert_contains("if (snip.type === 'markup' && _insideLock(snip.from, snip.to)) return false;", $facade, 'no new element inside a half-locked element');
	pg_assert_contains('cm.autocomplete.snippet(snip.template)(view, null, snip.from, snip.to);', $facade, 'inserted as a snippet');
	pg_assert_contains('inst.setEmmet = function', $facade, 'the instance can switch it');
	pg_assert_contains('inst.hasEmmet = function () { return _emmetOn(opts); };', $facade, 'and tells whether it is on');
}
