<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the Options panel's states (assets/js/pg_option_states.js):
 * the declarations a Style row's class stands for, a state's rule on the
 * element's custom style class written, read back to the row's class,
 * cleared and counted, the states an element takes and the sections a
 * state changes. Run under Node with pg_user_styles.js; skipped on a
 * machine without Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $script with the model as S; returns what it prints as JSON, null
// without Node.
function designer_option_states_run($script)
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

	$file = @tempnam(sys_get_temp_dir(), 'pgstates');

	if ($file === false) {
		return null;
	}

	$dir = PG_FUNCTIONS_DIR . '/assets/js';
	file_put_contents($file,
		"global.window = {};\n"
		. 'require(' . json_encode($dir . '/pg_user_styles.js') . ");\n"
		. 'var S = require(' . json_encode($dir . '/pg_option_states.js') . ");\n"
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

function test_designer_option_states_decls()
{
	$r = designer_option_states_run(
		'var o = {};'
		. '["text-white", "text-primary-emphasis", "text-muted", "bg-primary", "bg-info-subtle", "bg-transparent", "bg-gradient",'
		. ' "opacity-50", "border", "border-0", "border-start", "border-3", "border-warning", "border-danger-subtle",'
		. ' "rounded-pill", "rounded-3", "rounded-top-2", "shadow", "shadow-none",'
		. ' "text-center", "text-opacity-50", "bg-opacity-25", "border-opacity-50", "p-3"].forEach(function (c) { o[c] = S.decls(c); });'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(array(array('color', 'var(--bs-white)')), $r['text-white']);
	pg_assert_same(array(array('color', 'var(--bs-primary-text-emphasis)')), $r['text-primary-emphasis']);
	pg_assert_same(array(array('color', 'var(--bs-secondary-color)')), $r['text-muted']);
	pg_assert_same(array(array('background-color', 'var(--bs-primary)')), $r['bg-primary']);
	pg_assert_same(array(array('background-color', 'var(--bs-info-bg-subtle)')), $r['bg-info-subtle']);
	pg_assert_same(array(array('background-color', 'transparent')), $r['bg-transparent']);
	pg_assert_same(array(array('background-image', 'var(--bs-gradient)')), $r['bg-gradient']);
	pg_assert_same(array(array('opacity', '0.5')), $r['opacity-50']);
	pg_assert_same(array(array('border', 'var(--bs-border-width) var(--bs-border-style) var(--bs-border-color)')), $r['border']);
	pg_assert_same(array(array('border', '0')), $r['border-0']);
	pg_assert_same('border-inline-start', $r['border-start'][0][0], 'Start is the logical side');
	pg_assert_same(array(array('border-width', '3px')), $r['border-3']);
	pg_assert_same(array(array('border-color', 'var(--bs-warning)')), $r['border-warning']);
	pg_assert_same(array(array('border-color', 'var(--bs-danger-border-subtle)')), $r['border-danger-subtle']);
	pg_assert_same(array(array('border-radius', 'var(--bs-border-radius-pill)')), $r['rounded-pill']);
	pg_assert_same(array(array('border-radius', 'var(--bs-border-radius-lg)')), $r['rounded-3']);
	pg_assert_same(array(array('border-top-left-radius', 'var(--bs-border-radius)'), array('border-top-right-radius', 'var(--bs-border-radius)')), $r['rounded-top-2']);
	pg_assert_same(array(array('box-shadow', 'var(--bs-box-shadow)')), $r['shadow']);
	pg_assert_same(array(array('box-shadow', 'none')), $r['shadow-none']);

	foreach (array('text-center', 'text-opacity-50', 'bg-opacity-25', 'border-opacity-50', 'p-3') as $c) {
		pg_assert_same(null, $r[$c], $c . ' is no state value');
	}
}

function test_designer_option_states_write_read()
{
	$r = designer_option_states_run(
		'var o = {}, cls = "pgs-abc123";'
		. 'var bg = ["bg-primary", "bg-success", "bg-white"], side = ["border", "border-0", "border-top"], col = ["border-primary", "border-warning"];'
		. 'var base = ".pgs-abc123 {\n  color: red;\n}\n";'
		. 'o.one = S.write(base, cls, "hover", bg, "bg-success");'
		. 'o.two = S.write(o.one, cls, "hover", col, "border-warning");'
		. 'o.three = S.write(o.two, cls, "hover", side, "border");'
		. 'o.read = S.read(o.three, cls, "hover");'
		. 'o.matchBg = S.match(o.read, bg);'
		. 'o.matchSide = S.match(o.read, side);'
		. 'o.matchNone = S.match(S.read(o.three, cls, "focus"), bg);'
		. 'o.holds = [S.holds(o.read, bg), S.holds(o.read, ["shadow", "shadow-lg"])];'
		. 'o.count = S.count(o.read);'
		. 'o.swap = S.write(o.three, cls, "hover", bg, "bg-white");'
		. 'o.reset = S.write(o.three, cls, "hover", bg, "");'
		. 'o.empty = S.write(S.write(o.reset, cls, "hover", col, ""), cls, "hover", side, "");'
		. 'o.focus = S.write(base, cls, "focus", ["shadow-sm", "shadow"], "shadow");'
		. 'o.pressed = S.write(base, cls, "pressed", ["opacity-50"], "opacity-50");'
		. 'o.clear = S.clear(o.two, cls, "hover");'
		. 'var own = ".pgs-abc123:hover {\n  outline: 1px dotted red;\n  color: var(--bs-white) !important;\n}\n";'
		. 'o.own = S.write(own, cls, "hover", bg, "bg-primary");'
		. 'o.ownCount = S.count(S.read(own, cls, "hover"));'
		. 'o.bad = S.write(base, "abc", "hover", bg, "bg-primary") === base;'
		. 'o.badState = S.write(base, cls, "visited", bg, "bg-primary") === base;'
		. 'o.sections = S.sections(o.three + o.focus.slice(base.length), cls);'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(".pgs-abc123 {\n  color: red;\n}\n\n.pgs-abc123:hover {\n  background-color: var(--bs-success) !important;\n}\n", $r['one'], 'a new state rule after the base rule');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n}\n\n.pgs-abc123:hover {\n  background-color: var(--bs-success) !important;\n  border: var(--bs-border-width) var(--bs-border-style) var(--bs-border-color) !important;\n  border-color: var(--bs-warning) !important;\n}\n", $r['three'], 'the shorthand goes before border-color');
	pg_assert_same(array('background-color' => 'var(--bs-success)', 'border' => 'var(--bs-border-width) var(--bs-border-style) var(--bs-border-color)', 'border-color' => 'var(--bs-warning)'), $r['read'], 'read without !important');
	pg_assert_same('bg-success', $r['matchBg']);
	pg_assert_same('border', $r['matchSide']);
	pg_assert_same('', $r['matchNone'], 'a state with nothing set matches nothing');
	pg_assert_same(array(true, false), $r['holds']);
	pg_assert_same(3, $r['count']);
	pg_assert_true(strpos($r['swap'], 'background-color: var(--bs-white) !important;') !== false, 'a new choice replaces the row\'s value');
	pg_assert_true(strpos($r['swap'], 'var(--bs-success)') === false);
	pg_assert_true(strpos($r['reset'], 'background-color') === false, 'the row taken out');
	pg_assert_true(strpos($r['reset'], 'border-color: var(--bs-warning)') !== false, 'the other rows stay');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n}\n", rtrim($r['empty']) . "\n", 'the last value out removes the rule');
	pg_assert_true(strpos($r['focus'], ".pgs-abc123:focus-visible {\n  box-shadow: var(--bs-box-shadow) !important;\n}") !== false, 'Focus is :focus-visible');
	pg_assert_true(strpos($r['pressed'], ".pgs-abc123:active {\n  opacity: 0.5 !important;\n}") !== false, 'Pressed is :active');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n}\n", rtrim($r['clear']) . "\n", 'Reset this state removes the rule');
	pg_assert_same(".pgs-abc123:hover {\n  outline: 1px dotted red;\n  color: var(--bs-white) !important;\n  background-color: var(--bs-primary) !important;\n}\n", $r['own'], 'a declaration no row writes stays first');
	pg_assert_same(2, $r['ownCount'], 'a declaration no row writes counts as a row');
	pg_assert_true($r['bad'], 'only a pgs- class');
	pg_assert_true($r['badState'], 'only the three states');
	pg_assert_same(array('colors' => array('hover'), 'border' => array('hover'), 'styling' => array('focus')), $r['sections']);
}

// The states an element takes (_sdStatesFor(), the editor's block of the
// Options states' pure helpers).
function test_designer_option_states_for()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($src, '// ── Options states: pure helpers ──');
	$to = strpos($src, '// ── end of the Options states pure helpers');
	pg_assert_true($from !== false && $to !== false && $to > $from, 'the editor has the block of the Options states');

	if ($from === false || $to === false || $to < $from) {
		return;
	}

	$r = designer_option_states_run(substr($src, $from, $to - $from)
		. 'console.log(JSON.stringify([_sdStatesFor("A", ""), _sdStatesFor("div", "btn btn-primary"), _sdStatesFor("DIV", "card"),'
		. ' _sdStatesFor("BUTTON", ""), _sdStatesFor("p", "nav-link"), _sdStatesFor("", "")]));'
	);

	if ($r === null) {
		return;
	}

	$all = array('hover', 'focus', 'pressed');
	pg_assert_same(array($all, $all, array('hover'), $all, $all, array('hover')), $r, 'Focus and Pressed only where a visitor can focus and press');
}
