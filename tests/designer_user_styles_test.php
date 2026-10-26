<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the User Styles text helpers (assets/js/pg_user_styles.js): the
 * rules the Styles panel shows as cards, and the edits that change only the
 * characters of one rule or declaration. Runs the script under Node; skipped
 * where there is no Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script after pg_user_styles.js (U = window.PgUserStyles) and returns
// what it prints as JSON; null without Node, ['error' => …] when it fails.
function designer_user_styles_node($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pguserstyles');

	if ($file === false) {
		return null;
	}

	file_put_contents($file, 'global.window = global;' . "\n"
		. '(0, eval)(require("fs").readFileSync(' . json_encode(PG_FUNCTIONS_DIR . '/assets/js/pg_user_styles.js') . ', "utf8"));' . "\n"
		. 'var U = window.PgUserStyles;' . "\n"
		. $script);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		return array('error' => implode("\n", $out));
	}

	return json_decode(implode("\n", $out), true);
}

// A design template's User Styles: a comment, a variable block, a rule in
// @media, a font face, keyframes and a value holding ; and } in a string.
function designer_user_styles_sample()
{
	return "/* Forum look */\n"
		. ":root {\n  --pf-accent: #c2410c;\n}\n\n"
		. ".pf-card { padding: 1rem; color: red }\n\n"
		. "@media (min-width: 768px) {\n  .pf-card {\n    padding: 2rem;\n  }\n}\n\n"
		. "@font-face {\n  font-family: \"X\";\n  src: url(\"x.woff2\");\n}\n\n"
		. "@keyframes pf-in {\n  from { opacity: 0; }\n  to { opacity: 1; }\n}\n\n"
		. ".pf-icon::before {\n  content: \"a;b}\";\n  background: url(data:image/png;base64,AAA=);\n}\n";
}

// The cards: style rules at the top and inside @media, never @font-face,
// keyframe steps or comments; a ; or } inside a string or url() stays in its
// value.
function test_designer_user_styles_parse_lists_style_rules()
{
	$result = designer_user_styles_node(
		'var text = ' . json_encode(designer_user_styles_sample()) . ';' . "\n"
		. 'console.log(JSON.stringify(U.parse(text).map(function (r) {' . "\n"
		. '    return { selector: r.selector, media: r.media, decls: r.decls.map(function (d) { return d.name + "=" + d.value; }),' . "\n"
		. '             source: text.slice(r.from, r.to).slice(0, 9) };' . "\n"
		. '})));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(
		array('selector' => ':root', 'media' => '', 'decls' => array('--pf-accent=#c2410c'), 'source' => ":root {\n "),
		array('selector' => '.pf-card', 'media' => '', 'decls' => array('padding=1rem', 'color=red'), 'source' => '.pf-card '),
		array('selector' => '.pf-card', 'media' => '@media (min-width: 768px)', 'decls' => array('padding=2rem'), 'source' => '.pf-card '),
		array('selector' => '.pf-icon::before', 'media' => '', 'decls' => array('content="a;b}"', 'background=url(data:image/png;base64,AAA=)'), 'source' => '.pf-icon:'),
	), $result, 'the listed rules');
}

// Each edit changes only its own characters: the rest of the text, comments,
// @media, @font-face and @keyframes included, stays byte for byte.
function test_designer_user_styles_edits_keep_the_rest()
{
	$result = designer_user_styles_node(
		'var text = ' . json_encode(designer_user_styles_sample()) . ';' . "\n"
		. 'function rule(i) { return U.parse(text)[i]; }' . "\n"
		. 'var out = {};' . "\n"
		. 'out.selector = U.setSelector(text, rule(2), ".pf-card-lg");' . "\n"
		. 'out.value = U.setDecl(text, rule(1), 1, "color", "var(--pf-accent)");' . "\n"
		. 'out.removeDecl = U.removeDecl(text, rule(2), 0);' . "\n"
		. 'out.removeInline = U.removeDecl(text, rule(1), 0);' . "\n"
		. 'out.removeRule = U.removeRule(text, rule(1));' . "\n"
		. 'out.addInline = U.addDecl(text, rule(1), "margin", "0");' . "\n"
		. 'out.addBlock = U.addDecl(text, rule(2), "margin", "0");' . "\n"
		. 'console.log(JSON.stringify(out));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');

	$text = designer_user_styles_sample();

	pg_assert_same(str_replace("  .pf-card {\n    padding: 2rem;", "  .pf-card-lg {\n    padding: 2rem;", $text), $result['selector'], 'the selector inside @media, nothing else');
	pg_assert_same(str_replace('color: red }', 'color: var(--pf-accent) }', $text), $result['value'], 'one value');
	pg_assert_same(str_replace("  .pf-card {\n    padding: 2rem;\n  }", "  .pf-card {\n  }", $text), $result['removeDecl'], 'a declaration on its own line goes with its line');
	pg_assert_same(str_replace('{ padding: 1rem; color: red }', '{ color: red }', $text), $result['removeInline'], 'a declaration sharing its line goes with its ; and the space after');
	pg_assert_same(str_replace(".pf-card { padding: 1rem; color: red }\n\n", '', $text), $result['removeRule'], 'the rule and its line, one blank line left');
	pg_assert_same(str_replace('color: red }', 'color: red; margin: 0; }', $text), $result['addInline'], 'a one-line rule stays on one line, the missing ; added');
	pg_assert_same(str_replace("    padding: 2rem;\n  }", "    padding: 2rem;\n    margin: 0;\n  }", $text), $result['addBlock'], 'a new line with the indent of the last declaration');
}

// Create's starter rule is appended after one blank line; its first
// declaration fills the empty body.
function test_designer_user_styles_append_and_fill_a_rule()
{
	$result = designer_user_styles_node(
		'var a = U.appendRule("/* x */\n.a { color: red }\n\n\n", "#hero");' . "\n"
		. 'var b = U.addDecl(a, U.parse(a)[1], "color", "blue");' . "\n"
		. 'var c = U.appendRule("", ".b");' . "\n"
		. 'console.log(JSON.stringify([a, b, c, U.parse(a)[1].decls.length]));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same("/* x */\n.a { color: red }\n\n#hero {\n  \n}\n", $result[0], 'appended after one blank line');
	pg_assert_same("/* x */\n.a { color: red }\n\n#hero {\n  color: blue;\n}\n", $result[1], 'the first declaration fills the starter body');
	pg_assert_same(".b {\n  \n}\n", $result[2], 'into empty User Styles');
	pg_assert_same(0, $result[3], 'the starter body has no declarations');
}

// Broken text never throws: an unclosed block ends the list, a stray } or a
// statement at-rule is passed over.
function test_designer_user_styles_parse_tolerates_broken_text()
{
	$result = designer_user_styles_node(
		'console.log(JSON.stringify([' . "\n"
		. '    U.parse("@import url(a.css);\n} .a { b: c }\n.d { e: f").map(function (r) { return r.selector; }),' . "\n"
		. '    U.parse("").length, U.parse(null).length,' . "\n"
		. '    U.parse(".a { /* off: 1; */ color: red; junk; }")[0].decls.map(function (d) { return d.name; })' . "\n"
		. ']));'
	);

	if ($result === null) {
		return;
	}

	pg_assert_false(isset($result['error']), isset($result['error']) ? $result['error'] : 'ran');
	pg_assert_same(array(array('.a'), 0, 0, array('color')), $result, 'broken text');
}
