<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the designer's per-element custom style model
 * (assets/js/pg_custom_style.js): the element's pgs- class, a new class,
 * the base rule read as the field shows it, the User Styles text after a
 * write (create, change, empty, before the class's @media rules, a refused
 * brace), a split, the users of a class and the warnings. Run under Node
 * with pg_user_styles.js; skipped on a machine without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as C; returns what it prints as JSON, null
// without Node.
function designer_custom_style_run($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgcustom');

	if ($file === false) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	file_put_contents($file,
		"global.window = {};\n"
		. 'require(' . json_encode($dir . '/pg_user_styles.js') . ");\n"
		. 'var C = require(' . json_encode($dir . '/pg_custom_style.js') . ");\n"
		. $script
	);

	$out = array();
	@exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	@unlink($file);

	if ($code !== 0) {
		pg_assert_false(true, implode("\n", $out));

		return null;
	}

	return json_decode(implode("\n", $out), true);
}

function test_designer_custom_style_class()
{
	$r = designer_custom_style_run(
		'var seq = [0, 0, 0, 0, 0, 0, 0.99, 0.5, 0.5, 0.5, 0.5, 0.5], i = 0;'
		. 'console.log(JSON.stringify({'
		. '  of: C.classOf("btn pgs-a1b2c3 pgs-zzzzzz"), none: C.classOf("pgs-abc pg-x"),'
		. '  fresh: C.newClass(["pgs-aaaaaa"], function () { return seq[i++ % seq.length]; }),'
		. '  shape: C.CLASS_RE.test(C.newClass({})),'
		. '  users: C.users(["a pgs-x", "pgs-x", "", null, ["pgs-x"]], "pgs-x")'
		. '}));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same('pgs-a1b2c3', $r['of'], 'the first pgs- class');
	pg_assert_same('', $r['none'], 'six characters or nothing');
	pg_assert_same('pgs-9sssss', $r['fresh'], 'a taken class is drawn again');
	pg_assert_true($r['shape']);
	pg_assert_same(3, $r['users']);
}

function test_designer_custom_style_write()
{
	$r = designer_custom_style_run(
		'var o = {}, cls = "pgs-abc123";'
		. 'var base = "/* kept */\n.a { color: red }\n";'
		. 'o.create = C.write(base, cls, "color: blue\nmargin: 0 /* m */\nfont-family: a,\n  b\n");'
		. 'o.body = C.body(o.create, cls);'
		. 'o.change = C.write(o.create, cls, "padding: 1px;");'
		. 'o.empty = C.write(o.change, cls, "  ");'
		. 'o.emptyNone = C.write(base, cls, "");'
		. 'var media = ".a{}\n@media (min-width: 768px) { .pgs-abc123 { color: red; } }\n";'
		. 'o.media = C.write(media, cls, "color: blue");'
		. 'o.mediaBody = C.body(media, cls);'
		. 'o.brace = C.write(base, cls, "color: red } .b { color: blue") === base;'
		. 'o.braceInString = C.write("", cls, "content: \"}\"");'
		. 'o.badClass = C.write(base, "abc", "color: red") === base;'
		. 'o.split = C.split(o.change, cls, "pgs-zzz999");'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same("/* kept */\n.a { color: red }\n\n.pgs-abc123 {\n  color: blue;\n  margin: 0; /* m */\n  font-family: a,\n  b;\n}\n", $r['create'], 'each declaration ends, a list over lines stays one');
	pg_assert_same("color: blue;\nmargin: 0; /* m */\nfont-family: a,\nb;", $r['body'], 'the field shows one line each');
	pg_assert_same("/* kept */\n.a { color: red }\n\n.pgs-abc123 {\n  padding: 1px;\n}\n", $r['change'], 'only the rule body changes');
	pg_assert_same("/* kept */\n.a { color: red }\n", rtrim($r['empty']) . "\n", 'an empty value removes the rule');
	pg_assert_same("/* kept */\n.a { color: red }\n", $r['emptyNone']);
	pg_assert_same(".a{}\n.pgs-abc123 {\n  color: blue;\n}\n\n@media (min-width: 768px) { .pgs-abc123 { color: red; } }\n", $r['media'], 'the base rule goes before the class\'s @media rules');
	pg_assert_same('', $r['mediaBody'], 'a rule inside @media is not the base rule');
	pg_assert_true($r['brace'], 'a brace is refused');
	pg_assert_same(".pgs-abc123 {\n  content: \"}\";\n}\n", $r['braceInString'], 'a brace inside a string is fine');
	pg_assert_true($r['badClass']);
	pg_assert_same($r['change'] . "\n.pgs-zzz999 {\n  padding: 1px;\n}\n", $r['split'], 'a split copies the rule');
}

function test_designer_custom_style_warnings()
{
	$r = designer_custom_style_run(
		'console.log(JSON.stringify([C.warnings("color: red !important"), C.warnings("a: b ! important; c: }"), C.warnings("/* { */ color: red"), C.warnings("")]));'
	);

	if ($r !== null) {
		pg_assert_same(array(array('important'), array('important', 'brace'), array(), array()), $r);
	}
}

// Where the field is off (_sdCustomStyleOff() in style_designer.js), with
// stand-ins for the widget and shared component lookups.
function test_designer_custom_style_off()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _sdCustomStyleOff(');
	pg_assert_true($from !== false, '_sdCustomStyleOff is in the designer');

	if ($from === false) {
		return;
	}

	$end = strpos($source, "\n    }\n", $from);
	$fn = substr($source, $from, $end - $from + 6);

	$r = designer_custom_style_run(
		'function _isInsideSystemWidget(n) { return n.w ? 7 : 0; }'
		. 'function _sdSharedOwnerOf(n) { return (n.w || n.s) ? 7 : 0; }'
		. $fn
		. 'console.log(JSON.stringify([_sdCustomStyleOff({ props: {} }), _sdCustomStyleOff({ type: "shared_ref", props: {} }),'
		. ' _sdCustomStyleOff({ props: {}, w: 1 }), _sdCustomStyleOff({ props: {}, s: 1 }), _sdCustomStyleOff(null)]));'
	);

	if ($r !== null) {
		pg_assert_same(array('', 'shared', 'widget', 'shared', 'none'), $r, 'a page element only; a widget is named before the shared component it is');
	}
}
