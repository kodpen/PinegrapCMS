<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the completion part of the code editor facade
 * (assets/js/pg_code_editor.js): the slots the engine asks its sources
 * through, the lookup cache reset before each source, CSS and JavaScript
 * sources inside an HTML editor, rankers and colour swatches. They run the
 * facade's own code under Node on the engine from the bundle and are
 * skipped where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script after the facade's language and completion blocks, on the
// engine's packages from the bundle. The script has `ask(doc, prep)`: the
// document with a | where the cursor is, an HTML editor state with the
// registered sources, `prep(tree, pos)` run first (to leave the tree's lookup
// cache somewhere), and every slot's result merged into
// { labels: [...], options: { label: option } }. Prints the script's JSON;
// null when there is no Node, ['error' => …] when a block is not found.
function code_editor_completion_node($script)
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

	if ($b === false) {
		return array('error' => 'the completion block of the facade was not found');
	}

	$file = @tempnam(sys_get_temp_dir(), 'pgcecompletion');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'global.window = global;' . "\n"
		. '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/lib/code_editor/cm6.bundle.js') . ', "utf8"));' . "\n"
		// The engine's CSS property list is read from document.body.style.
		. 'global.document = { body: { style: { color: "", columns: "", columnGap: "", margin: "" } } };' . "\n"
		. 'var cm = PgCM, _completions = {}, _rankers = {};' . "\n"
		. substr($facade, $a, $b - $a) . "\n"
		. 'function ask(doc, prep, lang) {' . "\n"
		. '    var pos = doc.indexOf("|"); doc = doc.replace("|", "");' . "\n"
		. '    lang = lang || "html";' . "\n"
		. '    var state = cm.state.EditorState.create({ doc: doc, extensions: [lang === "css" ? cm.langCss.css() : cm.langHtml.html(), _completionExtensions(cm, lang)] });' . "\n"
		. '    var tree = cm.language.ensureSyntaxTree(state, doc.length, 5000);' . "\n"
		. '    var out = { labels: [], options: {} };' . "\n"
		. '    _completionSlots(cm).forEach(function (slot) {' . "\n"
		. '        if (prep) prep(tree, pos);' . "\n"
		. '        var r = slot(new cm.autocomplete.CompletionContext(state, pos, true));' . "\n"
		. '        if (!r) return;' . "\n"
		. '        r.options.forEach(function (o) { out.labels.push(o.label); out.options[o.label] = o; });' . "\n"
		. '    });' . "\n"
		. '    return out;' . "\n"
		. '}' . "\n"
		. 'function has(r, label) { return r.labels.indexOf(label) >= 0; }' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// The engine's CSS completions work inside a style="" attribute after an
// earlier lookup left the tree's lookup cache at the attribute value (here
// its opening quote, at 11). resolveInner() starting from that node stayed
// on the attribute value instead of entering the CSS mounted over it, and
// the CSS source offered nothing.
function test_code_editor_completion_primes_the_lookup_cache()
{
	$result = code_editor_completion_node(
		'var leave = function (tree) { tree.resolveInner(11, 1); };' . "\n"
		. 'var attr = ask("<div style=\"col|: red\">x</div>", leave);' . "\n"
		. 'var block = ask("<style>\n.a { col| }\n</style>", leave);' . "\n"
		. 'console.log(JSON.stringify({ attr: has(attr, "color"), block: has(block, "color"), tag: has(attr, "div") }));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_true($result['attr'] === true, 'CSS properties inside style="" after a lookup at the attribute value');
	pg_assert_true($result['block'] === true, 'CSS properties inside <style>');
	pg_assert_true($result['tag'] === false, 'no HTML tags inside style=""');
}

// A source registered for 'css' or 'javascript' fires inside an HTML editor
// where that language is spoken, and an 'html' one stays out of it.
function test_code_editor_completion_nested_sources_in_html()
{
	$result = code_editor_completion_node(
		'function src(label) { return function (context, info) { return { from: context.pos, options: [{ label: label, detail: info.node.name }] }; }; }' . "\n"
		. '_completions = { html: [src("pg-html")], css: [src("pg-css")], javascript: [src("pg-js")] };' . "\n"
		. 'function which(doc) { var r = ask(doc); return ["pg-html", "pg-css", "pg-js"].filter(function (l) { return has(r, l); }).join(","); }' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '    text: which("<p>a|</p>"),' . "\n"
		. '    attr: which("<p style=\"col|: red\">a</p>"),' . "\n"
		. '    style: which("<style>\n.a { col| }\n</style>"),' . "\n"
		. '    script: which("<script>\nvar x = do|;\n</script>"),' . "\n"
		. '    handler: which("<p onclick=\"ale|\">a</p>"),' . "\n"
		. '    node: ask("<p style=\"col|: red\">a</p>").options["pg-css"].detail,' . "\n"
		. '    cssEditor: (function () { var r = ask(".a { col| }", null, "css"); return ["pg-html", "pg-css", "pg-js"].filter(function (l) { return has(r, l); }).join(","); })()' . "\n"
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same('pg-html', $result['text'], 'HTML text: the HTML source');
	pg_assert_same('pg-css', $result['attr'], 'style="": the CSS source');
	pg_assert_same('pg-css', $result['style'], '<style>: the CSS source');
	pg_assert_same('pg-js', $result['script'], '<script>: the JavaScript source');
	pg_assert_same('pg-js', $result['handler'], 'onclick="": the JavaScript source');
	pg_assert_same('PropertyName', $result['node'], 'the source is handed the node at the cursor');
	pg_assert_same('pg-css', $result['cssEditor'], 'a CSS editor carries only the CSS sources');
}

// A ranker of the region's language boosts the options it names, the
// engine's own included; the sum stays within -99..99, an option it leaves
// at 0 is passed on as it is, and a ranker of another language or one that
// throws changes nothing.
function test_code_editor_completion_rankers_boost_options()
{
	$result = code_editor_completion_node(
		'_rankers = {' . "\n"
		. '    css: [function (o, ctx, info) { return o.label === "color" ? 60 : (o.label === "columns" ? -5 : 0); },' . "\n"
		. '          function (o) { return o.label === "color" ? 60 : 0; },' . "\n"
		. '          function () { throw new Error("broken ranker"); }],' . "\n"
		. '    html: [function () { return 40; }]' . "\n"
		. '};' . "\n"
		. 'var seen = null;' . "\n"
		. '_rankers.css.push(function (o, ctx, info) { seen = info.language + ":" + info.node.name; return 0; });' . "\n"
		. 'var r = ask("<div style=\"col|: red\">x</div>");' . "\n"
		. 'var t = ask("<d|");' . "\n"
		. 'console.log(JSON.stringify({' . "\n"
		. '    color: r.options.color.boost, columns: r.options.columns.boost,' . "\n"
		. '    other: r.options["column-gap"].boost === undefined, seen: seen,' . "\n"
		. '    tag: t.options.div ? t.options.div.boost : null' . "\n"
		. '}));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(99, $result['color'], 'two boosts summed and capped at 99');
	pg_assert_same(-5, $result['columns'], 'a negative boost');
	pg_assert_true($result['other'], 'an option no ranker moves is passed on unchanged');
	pg_assert_same('css:PropertyName', $result['seen'], 'the ranker is told the language and the node');
	pg_assert_same(40, $result['tag'], 'the HTML ranker ranks HTML options');
}

// The swatch beside an option: its own `swatch`, a colour literal label, and
// nothing for other labels (named colours need the browser's CSS.supports).
function test_code_editor_completion_swatch_color()
{
	$result = code_editor_completion_node(
		'console.log(JSON.stringify([' . "\n"
		. '    _swatchColor({ label: "primary", swatch: "#0d6efd" }),' . "\n"
		. '    _swatchColor({ label: "#fff" }), _swatchColor({ label: "#11223344" }),' . "\n"
		. '    _swatchColor({ label: "rgb(1, 2, 3)" }), _swatchColor({ label: "hsl(10 50% 50%)" }),' . "\n"
		. '    _swatchColor({ label: "#ggg" }), _swatchColor({ label: "margin" }),' . "\n"
		. '    _swatchColor({ label: "red", type: "constant" }), _swatchColor(null)' . "\n"
		. ']));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array('#0d6efd', '#fff', '#11223344', 'rgb(1, 2, 3)', 'hsl(10 50% 50%)', '', '', '', ''), $result, 'swatch colours');
}
