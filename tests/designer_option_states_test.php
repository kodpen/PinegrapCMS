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

// A new rule on the class (a state's, or Normal's for a transform, an
// effect or a cursor) goes before the class's rules by screen size, as a
// new base rule does (pg_custom_style.js): after them it would win at every
// width and undo a larger size's value.
function test_designer_option_states_new_rule_before_the_sizes()
{
	$r = designer_option_states_run(
		'var cls = "pgs-abc123", o = {};'
		. 'var md = "@media (min-width: 768px) {\n  .pgs-abc123 {\n    width: 50%;\n  }\n}\n";'
		. 'var other = ".lead {\n  color: red;\n}\n\n";'
		. 'o.normal = S.tfWrite(other + md, cls, "", "tx", "10px");'
		. 'o.hover = S.write(other + md, cls, "hover", ["bg-primary"], "bg-primary");'
		. 'o.tfHover = S.tfWrite(md, cls, "hover", "r", "5");'
		. 'o.none = S.write(other, cls, "hover", ["bg-primary"], "bg-primary");'
		. 'console.log(JSON.stringify(o));'
	);

	if ($r === null) {
		return;
	}

	$md = "@media (min-width: 768px) {\n  .pgs-abc123 {\n    width: 50%;\n  }\n}\n";
	pg_assert_same(".lead {\n  color: red;\n}\n\n.pgs-abc123 {\n  translate: 10px 0px;\n}\n\n" . $md, $r['normal'], 'Normal\'s new rule before the size rule');
	pg_assert_same(".lead {\n  color: red;\n}\n\n.pgs-abc123:hover {\n  background-color: var(--bs-primary) !important;\n}\n\n" . $md, $r['hover'], 'a state\'s new rule before it');
	pg_assert_true(strpos($r['tfHover'], ".pgs-abc123:hover {") < strpos($r['tfHover'], '@media'), 'a state\'s transform before it');
	pg_assert_same(".lead {\n  color: red;\n}\n\n.pgs-abc123:hover {\n  background-color: var(--bs-primary) !important;\n}\n", $r['none'], 'without one, at the end');
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

// The element's Transition: one rule on the custom style class inside
// @media (prefers-reduced-motion: no-preference), read back, the look's
// duration and easing until chosen, taken out with its block.
function test_designer_option_states_transition()
{
	$r = designer_option_states_run(
		'var base = ".pgs-abc123 {\n  color: red;\n}\n";'
		. 'var a = S.transWrite(base, "pgs-abc123", { what: "colors" });'
		. 'var b = S.transWrite(a, "pgs-abc123", { what: "shadow", ms: "320", ease: "ease-out" });'
		. 'var c = S.transWrite(b, "pgs-abc123", { what: "shadow", ms: 5000, ease: "bounce" });'
		. 'var own = b.replace("transition-timing-function: ease-out;", "transition-timing-function: ease-out;\n    will-change: box-shadow;");'
		. 'var custom = b.replace("transition-property: box-shadow", "transition-property: opacity");'
		. 'console.log(JSON.stringify({ a: a, readA: S.transRead(a, "pgs-abc123"), b: b, readB: S.transRead(b, "pgs-abc123"),'
		. ' readC: S.transRead(c, "pgs-abc123"), none: S.transRead(base, "pgs-abc123"), other: S.transRead(b, "pgs-zzz999"),'
		. ' out: S.transWrite(b, "pgs-abc123", { what: "" }), ownOut: S.transWrite(own, "pgs-abc123", { what: "" }),'
		. ' custom: S.transRead(custom, "pgs-abc123"), customKept: S.transRead(S.transWrite(custom, "pgs-abc123", { what: "custom", ms: 100 }), "pgs-abc123"),'
		. ' bad: S.transWrite(base, "foo", { what: "all" }) === base, badWhat: S.transWrite(base, "pgs-abc123", { what: "spin" }) === base,'
		. ' maybe: [S.transMaybe(a, "pgs-abc123"), S.transMaybe(base, "pgs-abc123")] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_true(strpos($r['a'], "@media (prefers-reduced-motion: no-preference) {\n  .pgs-abc123 {\n    transition-property: color, background-color, border-color;\n    transition-duration: var(--pg-duration, 0.15s);\n    transition-timing-function: var(--pg-ease, ease-in-out);\n  }\n}\n") !== false, 'the rule sits in the no-preference block and follows the look');
	pg_assert_true(strpos($r['a'], ".pgs-abc123 {\n  color: red;\n}") === 0, 'the base rule stays');
	pg_assert_same(array('what' => 'colors', 'ms' => '', 'ease' => '', 'raw' => ''), $r['readA']);
	pg_assert_same(array('what' => 'shadow', 'ms' => 320, 'ease' => 'ease-out', 'raw' => ''), $r['readB']);
	pg_assert_same(1, substr_count($r['b'], '@media'), 'a change rewrites the one block');
	pg_assert_same(array('what' => 'shadow', 'ms' => 1000, 'ease' => '', 'raw' => ''), $r['readC'], 'Duration runs to 1,000 ms; an unknown easing follows the look');
	pg_assert_same('', $r['none']['what']);
	pg_assert_same('', $r['other']['what'], 'another class holds none');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n}\n", $r['out'], 'None takes the rule and its block out');
	pg_assert_true(strpos($r['ownOut'], 'will-change: box-shadow;') !== false && strpos($r['ownOut'], 'transition-') === false, 'a declaration no row writes stays');
	pg_assert_same(array('what' => 'custom', 'ms' => 320, 'ease' => 'ease-out', 'raw' => 'opacity'), $r['custom']);
	pg_assert_same('opacity', $r['customKept']['raw'], 'a property list no row writes is kept');
	pg_assert_same(100, $r['customKept']['ms']);
	pg_assert_true($r['bad'], 'only a pgs- class');
	pg_assert_true($r['badWhat'], 'only the four choices');
	pg_assert_same(array(true, false), $r['maybe']);
}

// The element's Transform: Move, Scale, Rotate, Skew and Origin written on
// the custom style class or a state's rule, read back, a state's pair
// following Normal on the axis it took from it, values no row reads kept.
function test_designer_option_states_transform()
{
	$r = designer_option_states_run(
		'var c = "pgs-abc123", base = ".pgs-abc123 {\n  color: red;\n}\n";'
		. 'var a = S.tfWrite(base, c, "", "r", "-6");'
		. 'a = S.tfWrite(a, c, "", "tx", "10");'
		. 'var h = S.tfWrite(a, c, "hover", "ty", "-2");'
		. 'h = S.tfWrite(h, c, "hover", "s", "1.02");'
		. 'var m = S.tfWrite(h, c, "", "tx", "20");'
		. 'var o = S.tfWrite(S.tfWrite(a, c, "", "o", "0"), c, "", "skx", "5");'
		. 'var back = S.tfWrite(S.tfWrite(h, c, "hover", "ty", ""), c, "hover", "s", "");'
		. 'var zero = S.tfWrite(S.tfWrite(a, c, "", "r", "0"), c, "", "tx", "");'
		. 'var foreign = base.replace("color: red;", "color: red;\n  transform: translate(-50%, -50%);");'
		. 'console.log(JSON.stringify({ a: a, h: h, readA: S.tfRead(a, c, ""), readH: S.tfRead(h, c, "hover"),'
		. ' countH: S.count(S.read(h, c, "hover")), sect: S.sections(h, c).styling, readM: S.tfRead(m, c, "hover"),'
		. ' readO: S.tfRead(o, c, ""), o: o, back: back, zero: zero,'
		. ' foreign: S.tfWrite(foreign, c, "", "skx", "4") === foreign, foreignRead: S.tfRead(foreign, c, "").skx,'
		. ' em: S.tfRead(S.tfWrite(base, c, "", "ty", "1.5rem"), c, "").ty, bad: S.tfWrite(base, c, "", "tx", "wide") === base,'
		. ' big: S.tfRead(S.tfWrite(base, c, "", "s", "40"), c, "").s, maybe: [S.tfMaybe(a, c), S.tfMaybe(base, c)] }));'
	);

	if ($r === null) {
		return;
	}

	pg_assert_same(".pgs-abc123 {\n  color: red;\n  rotate: -6deg;\n  translate: 10px 0px;\n}\n", $r['a'], 'Normal writes on the class, a bare number is px');
	pg_assert_true(strpos($r['h'], ".pgs-abc123:hover {\n  translate: 10px -2px;\n  scale: 1.02;\n}") !== false, 'a state takes Normal\'s other axis');
	pg_assert_same(array('tx' => '10px', 'ty' => '0px', 's' => '', 'r' => -6, 'skx' => '', 'sky' => '', 'o' => ''), $r['readA']);
	pg_assert_same(array('tx' => '10px', 'ty' => '-2px', 's' => 1.02, 'r' => '', 'skx' => '', 'sky' => '', 'o' => ''), $r['readH']);
	pg_assert_same(2, $r['countH'], 'Move and Scale count as two rows of the state');
	pg_assert_same(array('hover'), $r['sect'], 'Shadow and effect is ringed');
	pg_assert_same('20px', $r['readM']['tx'], 'the state follows a change of Normal on the axis it took');
	pg_assert_same('-2px', $r['readM']['ty']);
	pg_assert_same(0, $r['readO']['o']);
	pg_assert_same(5, $r['readO']['skx']);
	pg_assert_true(strpos($r['o'], 'transform-origin: 0% 0%;') !== false && strpos($r['o'], 'transform: skew(5deg, 0deg);') !== false);
	pg_assert_same($r['a'], $r['back'], 'a state emptied goes with its rule');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n}\n", $r['zero'], 'Normal\'s defaults are not written');
	pg_assert_true($r['foreign'], 'a transform no row reads is not written over');
	pg_assert_same('', $r['foreignRead']);
	pg_assert_same('1.5rem', $r['em']);
	pg_assert_true($r['bad'], 'a length the field cannot read writes nothing');
	pg_assert_same(10, $r['big'], 'Scale is kept to 10');
	pg_assert_same(array(true, false), $r['maybe']);
}

function test_designer_option_states_effects()
{
	$r = designer_option_states_run(
		'var c = "pgs-abc123", base = ".pgs-abc123 {\n  color: red;\n}\n";'
		. 'var a = S.fxWrite(base, c, "", "gray", "100");'
		. 'a = S.fxWrite(a, c, "", "blur", "2");'
		. 'var h = S.fxWrite(a, c, "hover", "gray", "0");'
		. 'var m = S.fxWrite(h, c, "", "blur", "4");'
		. 'var back = S.fxWrite(h, c, "hover", "gray", "");'
		. 'var g = S.fxWrite(S.fxWrite(base, c, "", "bdblur", "12"), c, "", "blend", "multiply");'
		. 'g = S.fxWrite(g, c, "", "bgblend", "overlay");'
		. 'var none = S.fxWrite(S.fxWrite(S.fxWrite(g, c, "", "bdblur", "0"), c, "", "blend", "normal"), c, "", "bgblend", "");'
		. 'var zero = S.fxWrite(S.fxWrite(a, c, "", "gray", "0"), c, "", "blur", "");'
		. 'var foreign = base.replace("color: red;", "color: red;\n  filter: drop-shadow(0 0 2px red);");'
		. 'console.log(JSON.stringify({ a: a, h: h, readA: S.fxRead(a, c, ""), readH: S.fxRead(h, c, "hover"), readM: S.fxRead(m, c, "hover"), m: m,'
		. ' countH: S.count(S.read(h, c, "hover")), sect: S.sections(h, c).styling, back: back, g: g, readG: S.fxRead(g, c, ""),'
		. ' countG: S.count(S.read(S.fxWrite(S.fxWrite(S.fxWrite(base, c, "hover", "bdblur", "8"), c, "hover", "blend", "screen"), c, "hover", "bgblend", "multiply"), c, "hover")), none: none, zero: zero,'
		. ' foreign: S.fxWrite(foreign, c, "", "blur", "3") === foreign, foreignRead: S.fxRead(foreign, c, "").blur,'
		. ' hue: S.fxRead(S.fxWrite(base, c, "", "hue", "-400"), c, "").hue, bad: S.fxWrite(base, c, "", "blend", "glow") === base,'
		. ' css: S.fxFilter({ blur: 2, bright: 120, contrast: "", gray: "", sat: "", sepia: "", hue: 30 }),'
		. ' maybe: [S.fxMaybe(a, c), S.fxMaybe(g, c), S.fxMaybe(base, c)] }));'
	);

	if ($r === null) {
		return;
	}

	$base_text = ".pgs-abc123 {\n  color: red;\n}\n";
	pg_assert_same(".pgs-abc123 {\n  color: red;\n  filter: blur(2px) grayscale(100%);\n}\n", $r['a'], 'Normal writes one filter in a fixed order');
	pg_assert_true(strpos($r['h'], ".pgs-abc123:hover {\n  filter: blur(2px) grayscale(0%);\n}") !== false, 'a state writes the whole list with Normal\'s other values');
	pg_assert_same(array('blur' => 2, 'bright' => '', 'contrast' => '', 'gray' => 100, 'sat' => '', 'sepia' => '', 'hue' => '', 'bdblur' => '', 'blend' => '', 'bgblend' => ''), $r['readA']);
	pg_assert_same(0, $r['readH']['gray']);
	pg_assert_same('', $r['readH']['blur'], 'a state\'s value equal to Normal\'s reads as not set');
	pg_assert_true(strpos($r['m'], ".pgs-abc123:hover {\n  filter: blur(4px) grayscale(0%);\n}") !== false, 'the state follows a change of Normal on a value it took');
	pg_assert_same(0, $r['readM']['gray']);
	pg_assert_same(1, $r['countH'], 'Filter counts as one row of the state');
	pg_assert_same(array('hover'), $r['sect'], 'Shadow and effect is ringed');
	pg_assert_same($r['a'], $r['back'], 'a state emptied goes with its rule');
	pg_assert_true(strpos($r['g'], "backdrop-filter: blur(12px);") !== false && strpos($r['g'], "-webkit-backdrop-filter: blur(12px);") !== false, 'background blur with the prefixed twin');
	pg_assert_true(strpos($r['g'], "mix-blend-mode: multiply;") !== false && strpos($r['g'], "background-blend-mode: overlay;") !== false);
	pg_assert_same(12, $r['readG']['bdblur']);
	pg_assert_same('multiply', $r['readG']['blend']);
	pg_assert_same('overlay', $r['readG']['bgblend']);
	pg_assert_same(3, $r['countG'], 'background blur counts once with its twin');
	pg_assert_same($base_text, $r['none'], 'Normal\'s defaults are not written');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n}\n", $r['zero']);
	pg_assert_true($r['foreign'], 'a filter no row reads is not written over');
	pg_assert_same('', $r['foreignRead']);
	pg_assert_same(-180, $r['hue'], 'Hue is kept to a half turn');
	pg_assert_true($r['bad'], 'an unknown blend writes nothing');
	pg_assert_same('blur(2px) brightness(120%) hue-rotate(30deg)', $r['css']);
	pg_assert_same(array(true, true, false), $r['maybe']);
}

// Text › More (txWrite()/txRead()): Normal's rule only, a row's values
// written and read back, a value no row writes kept and read as 'own',
// the line limit's own display and overflow taken out with it.
function test_designer_option_states_text()
{
	$r = designer_option_states_run(
		'var c = "pgs-abc123", base = ".pgs-abc123 {\n  color: red;\n}\n";'
		. 'var a = S.txWrite(base, c, "shadow", "soft");'
		. 'a = S.txWrite(a, c, "track", "0.05");'
		. 'a = S.txWrite(a, c, "font", "heading");'
		. 'a = S.txWrite(a, c, "lines", "3");'
		. 'a = S.txWrite(a, c, "grad", "primary-info");'
		. 'a = S.txWrite(a, c, "strokec", "primary");'
		. 'var out = a; ["shadow", "track", "font", "lines", "grad", "stroke"].forEach(function (k) { out = S.txWrite(out, c, k, ""); });'
		. 'var own = base.replace("color: red;", "color: red;\n  display: flex;\n  text-shadow: 1px 1px 0 blue;\n  letter-spacing: 2px;\n  -webkit-line-clamp: 2;");'
		. 'var ownLines = S.txWrite(own, c, "lines", "");'
		. 'console.log(JSON.stringify({ a: a, read: S.txRead(a, c), out: out, own: S.txRead(own, c), ownLines: ownLines,'
		. ' noState: S.txRead(a.replace(".pgs-abc123 {", ".pgs-abc123:hover {"), c).shadow,'
		. ' bad: [S.txWrite(base, c, "shadow", "huge") === base, S.txWrite(base, c, "font", "Arial") === base, S.txWrite(base, c, "track", "wide") === base, S.txWrite(base, c, "strokec", "pink") === base],'
		. ' clamp: [S.txRead(S.txWrite(base, c, "lines", "40"), c).lines, S.txRead(S.txWrite(base, c, "track", "-3"), c).track],'
		. ' width: S.txWrite(base, c, "stroke", "1.5"), zero: S.txWrite(S.txWrite(base, c, "stroke", "2"), c, "stroke", "0"),'
		. ' maybe: [S.txMaybe(a, c), S.txMaybe(base, c)] }));'
	);

	if ($r === null) {
		return;
	}

	$base_text = ".pgs-abc123 {\n  color: red;\n}\n";
	pg_assert_same(".pgs-abc123 {\n  color: red;\n  text-shadow: 0 1px 2px rgb(0 0 0 / .25);\n  letter-spacing: 0.05em;\n  font-family: var(--pg-font-heading);\n"
		. "  display: -webkit-box;\n  -webkit-box-orient: vertical;\n  overflow: hidden;\n  -webkit-line-clamp: 3;\n  line-clamp: 3;\n"
		. "  background-image: linear-gradient(90deg, var(--bs-primary), var(--bs-info));\n  -webkit-background-clip: text;\n  background-clip: text;\n"
		. "  -webkit-text-fill-color: transparent;\n  -webkit-text-stroke: 1px var(--bs-primary);\n  paint-order: stroke fill;\n}\n", $r['a'], 'every row written on the class rule');
	pg_assert_same(array('shadow' => 'soft', 'track' => 0.05, 'font' => 'heading', 'lines' => 3, 'grad' => 'primary-info', 'stroke' => 1, 'strokec' => 'primary'), $r['read']);
	pg_assert_same($base_text, $r['out'], 'every row taken out leaves the rule as it was');
	pg_assert_same(array('shadow' => 'own', 'track' => 'own', 'font' => '', 'lines' => 2, 'grad' => '', 'stroke' => '', 'strokec' => ''), $r['own'], 'a value no row writes reads as own');
	pg_assert_true(strpos($r['ownLines'], 'display: flex;') !== false && strpos($r['ownLines'], 'line-clamp') === false, 'a display of its own stays when the limit goes');
	pg_assert_same('', $r['noState'], 'a state\'s rule is not read');
	pg_assert_same(array(true, true, true, true), $r['bad'], 'an unknown value writes nothing');
	pg_assert_same(array(10, -0.5), $r['clamp'], 'lines and letter spacing kept to their ends');
	pg_assert_true(strpos($r['width'], '-webkit-text-stroke: 1.5px currentcolor;') !== false, 'an outline without a colour takes the text\'s');
	pg_assert_same($base_text, $r['zero'], 'a width of 0 takes the outline out');
	pg_assert_same(array(true, false), $r['maybe']);
}

// Cursor (cursorWrite()/cursorRead()): Normal's rule only, the three rows
// written and read back, a cursor no row writes read as 'own' and replaced
// by a row's value, an unknown value writing nothing.
function test_designer_option_states_cursor()
{
	$r = designer_option_states_run(
		'var c = "pgs-abc123", base = ".pgs-abc123 {\n  color: red;\n}\n";'
		. 'var a = S.cursorWrite(base, c, "Pointer");'
		. 'var own = base.replace("color: red;", "color: red;\n  cursor: zoom-in;");'
		. 'console.log(JSON.stringify({ a: a, read: S.cursorRead(a, c), out: S.cursorWrite(a, c, ""),'
		. ' swap: S.cursorWrite(a, c, "not-allowed"), own: S.cursorRead(own, c), ownSwap: S.cursorWrite(own, c, "default"),'
		. ' none: S.cursorRead(base, c), noState: S.cursorRead(a.replace(".pgs-abc123 {", ".pgs-abc123:hover {"), c),'
		. ' bad: [S.cursorWrite(base, c, "grab") === base, S.cursorWrite(base, "x", "pointer") === base] }));'
	);

	if ($r === null) {
		return;
	}

	$base_text = ".pgs-abc123 {\n  color: red;\n}\n";
	pg_assert_same(".pgs-abc123 {\n  color: red;\n  cursor: pointer;\n}\n", $r['a'], 'the cursor written on the class rule');
	pg_assert_same('pointer', $r['read']);
	pg_assert_same($base_text, $r['out'], 'empty takes the cursor out');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n  cursor: not-allowed;\n}\n", $r['swap']);
	pg_assert_same('own', $r['own'], 'a cursor no row writes reads as own');
	pg_assert_same(".pgs-abc123 {\n  color: red;\n  cursor: default;\n}\n", $r['ownSwap'], 'a row replaces a cursor of its own');
	pg_assert_same('', $r['none']);
	pg_assert_same('', $r['noState'], 'a state\'s rule is not read');
	pg_assert_same(array(true, true), $r['bad'], 'an unknown value or class writes nothing');
}
