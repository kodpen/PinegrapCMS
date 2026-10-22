<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the blocks the visual designer's Themes panel writes its
 * light-mode variables into. A variable Bootstrap's dark block gives a dark
 * value goes under `:root:not([data-bs-theme="dark"]), [data-bs-theme="light"]`
 * so it never overrides a dark page; every other variable (fonts, radii,
 * theme colours) stays under `:root, [data-bs-theme="light"]` so it still
 * applies in dark mode. The old combined block is split the same way when
 * the design loads and on every panel write. The panel's rule-block helpers
 * are cut out of assets/js/style_designer.js and run under Node; on a
 * machine without Node the test is skipped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Runs $steps after the Themes panel's rule-block helpers; null without Node.
// The steps also get resolve(css, html, name): the value a variable takes
// on an <html> ({ theme: 'dark' | 'light' | null }) after Bootstrap's own
// light and dark blocks and then `css`, by specificity and order. It knows
// the selectors the panel writes; any other selector does not match.
function designer_theme_light_selector_test_run($steps)
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _findRuleBlock(');
	$to = ($from !== false) ? strpos($source, '// Read the current value of a single theme var', $from) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the theme rule-block helpers');

	if (($from === false) || ($to === false)) {
		return null;
	}

	$cascade = <<<'JS'
var BS = ':root,[data-bs-theme=light]{--bs-body-bg:#fff;--bs-body-font-family:system-ui;--bs-border-radius:0.375rem;--bs-primary:#0d6efd}'
	+ '[data-bs-theme=dark]{--bs-body-bg:#212529}';
function specificity(sel, html) {
	var rest = sel.trim(), spec = 0, m;
	if (!rest) return -1;
	while (rest.length) {
		if ((m = /^:root/.exec(rest))) { spec++; }
		else if ((m = /^:not\(\[data-bs-theme=["']?(\w+)["']?\]\)/.exec(rest))) { if (html.theme === m[1]) return -1; spec++; }
		else if ((m = /^\[data-bs-theme=["']?(\w+)["']?\]/.exec(rest))) { if (html.theme !== m[1]) return -1; spec++; }
		else return -1;
		rest = rest.slice(m[0].length);
	}
	return spec;
}
function resolve(css, html, name) {
	var all = BS + '\n' + css.replace(/\/\*[\s\S]*?\*\//g, ''), re = /([^{}]+)\{([^{}]*)\}/g, m, best = null;
	while ((m = re.exec(all))) {
		var decls = _parseThemeDecls(m[2]);
		if (!(name in decls)) continue;
		var spec = -1;
		m[1].split(',').forEach(function (s) { spec = Math.max(spec, specificity(s, html)); });
		if (spec >= 0 && (!best || spec >= best.spec)) best = { spec: spec, value: decls[name] };
	}
	return best ? best.value : null;
}
var DARK = { theme: 'dark' }, LIGHT = { theme: 'light' }, PLAIN = { theme: null };
function find(css, kind) { var b = _themeFindBlock(css, kind); return b ? [b.selStart, _parseThemeDecls(b.body)] : null; }
JS;

	$file = tempnam(sys_get_temp_dir(), 'pgthemesel');
	file_put_contents($file, substr($source, $from, $to - $from) . "\n" . $cascade . "\n" . $steps);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);

	pg_assert_same(0, $code, implode("\n", $out));

	return json_decode(implode("\n", $out), true);
}

// One selector per block: the light block never matches a dark <html>, the
// shared block does, the dark block keeps Bootstrap's own selector.
function test_designer_theme_light_selector_written()
{
	$answer = designer_theme_light_selector_test_run(
		'console.log(JSON.stringify([_themeCanonicalSelector("light"), _themeCanonicalSelector("shared"), _themeCanonicalSelector("dark")]));'
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"]", $answer[0]);
	pg_assert_same(":root,\n[data-bs-theme=\"light\"]", $answer[1]);
	pg_assert_same('[data-bs-theme="dark"]', $answer[2]);
}

// A variable goes to the light block only when Bootstrap's dark block gives
// it a dark value; the list is the one in the Bootstrap 5.3.8 the designer
// ships.
function test_designer_theme_light_selector_block_per_variable()
{
	$answer = designer_theme_light_selector_test_run(<<<'JS'
console.log(JSON.stringify([
	_themeDarkOwnVars(),
	['--bs-body-bg', '--bs-body-color', '--bs-link-color', '--bs-border-color', '--bs-secondary-bg',
	 '--bs-body-font-family', '--bs-body-font-size', '--bs-border-radius', '--bs-border-width',
	 '--bs-link-decoration', '--bs-focus-ring-width', '--bs-primary', '--brand'].map(function (n) { return _themeBlockFor('light', n); }),
	_themeBlockFor('dark', '--bs-body-bg'), _themeBlockFor('dark', '--bs-body-font-family')
]));
JS
	);

	if ($answer === null) {
		return;
	}

	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css');
	pg_assert_true((bool) preg_match('/\}\[data-bs-theme=dark\]\{([^}]*)\}/', $css, $block), 'Bootstrap has its dark block');
	preg_match_all('/(--[a-z0-9-]+):/', isset($block[1]) ? $block[1] : '', $names);
	pg_assert_same($names[1], $answer[0], 'the variables Bootstrap gives a dark value');

	pg_assert_same(array('light', 'light', 'light', 'light', 'light',
		'shared', 'shared', 'shared', 'shared', 'shared', 'shared', 'shared', 'shared'), $answer[1]);
	pg_assert_same('dark', $answer[2]);
	pg_assert_same('dark', $answer[3]);
}

// Every light form is found as the block it is, from its first selector:
// the tail `[data-bs-theme="light"]` of a combined form is never taken for
// a bare light block, and the `:not()` of the light form never for the
// dark block.
function test_designer_theme_light_selector_read()
{
	$answer = designer_theme_light_selector_test_run(<<<'JS'
var all = '[data-bs-theme="dark"] {\n  --bs-body-bg: #111;\n}\n\n:root:not([data-bs-theme="dark"]),\n[data-bs-theme="light"] {\n  --bs-body-bg: #fdf6e3;\n}\n\n:root,\n[data-bs-theme="light"] {\n  --bs-border-radius: 0;\n}\n';
console.log(JSON.stringify([
	find(all, 'dark'), find(all, 'light'), find(all, 'shared'),
	find(":root:not([data-bs-theme='dark']), [data-bs-theme='light'] { --bs-body-bg: #a; }", 'light'),
	find(":root:not([data-bs-theme='dark']), [data-bs-theme='light'] { --bs-body-bg: #a; }", 'shared'),
	find(':root,\n[data-bs-theme="light"] {\n  --bs-body-bg: #b;\n}', 'shared'),
	find(':root,\n[data-bs-theme="light"] {\n  --bs-body-bg: #b;\n}', 'light'),
	find(':root { --bs-body-bg: #c; }', 'shared'),
	find('[data-bs-theme="light"] { --bs-body-bg: #d; }', 'shared'),
	find(':root:not([data-bs-theme="dark"]),\n[data-bs-theme="light"] { --bs-body-bg: #e; }', 'dark'),
	find('[data-bs-theme="dark"] .bg-primary { color: red; }', 'dark'),
	_themeModeDecls(all, 'light'), _themeModeDecls(all, 'dark')
]));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array(0, array('--bs-body-bg' => '#111')), $answer[0], 'the dark block');
	pg_assert_same(array(50, array('--bs-body-bg' => '#fdf6e3')), $answer[1], 'the light block after the dark one');
	pg_assert_same(array(139, array('--bs-border-radius' => '0')), $answer[2], 'the shared block after the light one');
	pg_assert_same(array(0, array('--bs-body-bg' => '#a')), $answer[3], 'single quotes');
	pg_assert_same(null, $answer[4], 'the tail of the light form is not a shared block');
	pg_assert_same(array(0, array('--bs-body-bg' => '#b')), $answer[5], 'the old combined form, from its first selector');
	pg_assert_same(null, $answer[6], 'the old combined form is not the light block');
	pg_assert_same(array(0, array('--bs-body-bg' => '#c')), $answer[7], 'a bare :root');
	pg_assert_same(array(0, array('--bs-body-bg' => '#d')), $answer[8], 'a bare light block');
	pg_assert_same(null, $answer[9], 'the :not() is not a dark block');
	pg_assert_same(null, $answer[10], 'a dark cascade rule is not the dark block');
	pg_assert_same(array('--bs-border-radius' => '0', '--bs-body-bg' => '#fdf6e3'), $answer[11], 'light mode shows both light blocks');
	pg_assert_same(array('--bs-body-bg' => '#111'), $answer[12], 'dark mode shows the dark block');
}

// The old combined block is split: variables Bootstrap gives a dark value
// move to the light block, the rest stay. A block of either kind alone is
// renamed or left as written, an existing light block takes the moved
// values, a shared block after the dark one moves before it, and a second
// pass changes nothing.
function test_designer_theme_light_selector_upgrade()
{
	$answer = designer_theme_light_selector_test_run(<<<'JS'
var mixed = ':root,\n[data-bs-theme="light"] {\n  --bs-body-bg: #fdf6e3;\n  --bs-body-font-family: Georgia, serif;\n  --bs-border-radius: 0;\n}\n\n[data-bs-theme="dark"] {\n  --bs-body-bg: #111;\n}\n';
var colours = ':root,\n[data-bs-theme="light"] {\n  --bs-body-bg: #fdf6e3;\n}\n';
var fonts = ':root,\n[data-bs-theme="light"] {\n  --bs-body-font-family: Georgia, serif;\n}\n\n[data-bs-theme="dark"] {\n  --bs-body-bg: #111;\n}\n';
var late = '[data-bs-theme="dark"] {\n  --bs-body-font-family: mono;\n}\n\n:root,\n[data-bs-theme="light"] {\n  --bs-body-font-family: Georgia, serif;\n}\n';
var merge = ':root:not([data-bs-theme="dark"]),\n[data-bs-theme="light"] {\n  --bs-body-bg: #new;\n}\n\n:root, [data-bs-theme="light"] { --bs-body-bg: #old; --bs-link-color: #00f; --bs-border-radius: 0 }';
var hand = ':root { --bs-body-bg: red; }\n[data-bs-theme="light"] { --bs-body-bg: blue; }\n[data-bs-theme="dark"] .bg-primary { color: red; }';
var out = [mixed, colours, fonts, late, merge].map(_themeUpgradeLightSelector);
console.log(JSON.stringify([
	out,
	out.map(function (c) { return _themeUpgradeLightSelector(c) === c; }),
	_themeUpgradeLightSelector(hand) === hand,
	_themeUpgradeLightSelector(":root , [data-bs-theme='light']{--bs-body-bg:1}"),
	_themeUpgradeLightSelector(''), _themeUpgradeLightSelector('.a { color: red; }'),
	_themeModeDecls(out[4], 'light')
]));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-font-family: Georgia, serif;\n  --bs-border-radius: 0;\n}\n\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n", $answer[0][0], 'a mixed block is split');
	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n}\n", $answer[0][1], 'a block of colours is renamed');
	pg_assert_same(":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-font-family: Georgia, serif;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n", $answer[0][2], 'a block of mode-neutral values before the dark one stays');
	pg_assert_same(":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-font-family: Georgia, serif;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-font-family: mono;\n}\n", $answer[0][3], 'a shared block after the dark one moves before it');
	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #new;\n  --bs-link-color: #00f;\n}\n\n:root, [data-bs-theme=\"light\"] { --bs-border-radius: 0 }", $answer[0][4], 'moved values join the light block, which keeps its own');
	pg_assert_same(array(true, true, true, true, true), $answer[1], 'a second pass changes nothing');
	pg_assert_true($answer[2], 'hand-written bare blocks and cascade rules stay');
	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {--bs-body-bg:1}", $answer[3], 'spacing and single quotes');
	pg_assert_same('', $answer[4]);
	pg_assert_same('.a { color: red; }', $answer[5]);
	pg_assert_same(array('--bs-border-radius' => '0', '--bs-body-bg' => '#new', '--bs-link-color' => '#00f'), $answer[6]);
}

// What a page shows: before the split a light background beat the dark
// page; after it the dark page has Bootstrap's or the design's dark
// background while the font and the radius set for light mode stay, and a
// dark value of the same variable wins on a dark <html>.
function test_designer_theme_light_selector_cascade()
{
	$answer = designer_theme_light_selector_test_run(<<<'JS'
var old = ':root,\n[data-bs-theme="light"] {\n  --bs-body-bg: #fdf6e3;\n  --bs-body-font-family: Georgia, serif;\n  --bs-border-radius: 0;\n  --bs-primary: #f60;\n}\n';
var now = _themeUpgradeLightSelector(old);
var darkFirst = _themeUpgradeLightSelector('[data-bs-theme="dark"] {\n  --bs-body-font-family: mono;\n}\n\n' + old);
var written = _themeSetDecl('', 'dark', '--bs-body-font-family', 'mono');
written = _themeSetDecl(written, 'shared', '--bs-body-font-family', 'Georgia');
written = _themeSetDecl(written, 'light', '--bs-body-bg', '#fdf6e3');
function look(css, html) {
	return ['--bs-body-bg', '--bs-body-font-family', '--bs-border-radius', '--bs-primary'].map(function (n) { return resolve(css, html, n); });
}
console.log(JSON.stringify([
	look(old, DARK), look(now, DARK), look(now, PLAIN), look(now, LIGHT),
	look(darkFirst, DARK), look(darkFirst, PLAIN), look(written, DARK), look(written, PLAIN)
]));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(array('#fdf6e3', 'Georgia, serif', '0', '#f60'), $answer[0], 'the old block: the light background beats the dark page');
	pg_assert_same(array('#212529', 'Georgia, serif', '0', '#f60'), $answer[1], 'split: dark background, the font, radius and primary stay');
	pg_assert_same(array('#fdf6e3', 'Georgia, serif', '0', '#f60'), $answer[2], 'split: the default page');
	pg_assert_same(array('#fdf6e3', 'Georgia, serif', '0', '#f60'), $answer[3], 'split: an explicit light page');
	pg_assert_same(array('#212529', 'mono', '0', '#f60'), $answer[4], 'a dark font set before the light block wins on a dark page');
	pg_assert_same(array('#fdf6e3', 'Georgia, serif', '0', '#f60'), $answer[5]);
	pg_assert_same(array('#212529', 'mono', '0.375rem', '#0d6efd'), $answer[6], 'panel writes: the dark font wins on a dark page');
	pg_assert_same(array('#fdf6e3', 'Georgia', '0.375rem', '#0d6efd'), $answer[7], 'panel writes: the light page');
}

// A panel write puts the value in its own block, takes it out of the other
// light block, creates a shared block before the dark one and drops a block
// it empties.
function test_designer_theme_light_selector_set_decl()
{
	$answer = designer_theme_light_selector_test_run(<<<'JS'
var dark = '[data-bs-theme="dark"] {\n  --bs-body-bg: #111;\n}\n';
var a = _themeSetDecl(dark, 'shared', '--bs-border-radius', '0');
var b = _themeSetDecl(a, 'light', '--bs-body-bg', '#fff');
var c = _themeSetDecl(b, 'shared', '--bs-border-radius', '');
console.log(JSON.stringify([a, b, c, _themeSetDecl(c, 'light', '--bs-body-bg', '#eee'), _themeSetDecl(dark, 'light', '--bs-body-bg', '')]));
JS
	);

	if ($answer === null) {
		return;
	}

	pg_assert_same(":root,\n[data-bs-theme=\"light\"] {\n  --bs-border-radius: 0;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n", $answer[0], 'a new shared block goes before the dark one');
	pg_assert_same($answer[0] . "\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fff;\n}\n", $answer[1], 'a new light block goes at the end');
	pg_assert_same("[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fff;\n}\n", $answer[2], 'an emptied block is dropped');
	pg_assert_contains('--bs-body-bg: #eee;', $answer[3]);
	pg_assert_same("[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n", $answer[4], 'clearing a missing value changes nothing');
}

// The panel splits the old block when the design loads and before each
// write, writes each value to its own block and removes it from the other
// light block. Comments are stripped first, so a call left in one does not
// count.
function test_designer_theme_light_selector_call_sites()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');

	$sentinels = strpos($source, 'function _ensureBootstrapSentinels(');
	$sentinels_end = ($sentinels !== false) ? strpos($source, '_ensureBootstrapSentinels();', $sentinels) : false;
	pg_assert_true(($sentinels !== false) && ($sentinels_end !== false), 'the designer has the load-time asset check');
	$load = preg_replace('~//[^\n]*~', '', substr($source, $sentinels, $sentinels_end - $sentinels));
	pg_assert_contains("var upgraded = _themeUpgradeLightSelector(cssFiles[ui].content || '');", $load, 'the old light block is split on load');
	pg_assert_contains('cssFiles[ui].content = upgraded; changedCss = true;', $load, 'the split content is kept and saved');

	$write = strpos($source, 'function _writeThemeVar(');
	$write_end = ($write !== false) ? strpos($source, 'function _findThemeVarMeta(', $write) : false;
	pg_assert_true(($write !== false) && ($write_end !== false), 'the designer has the theme variable writer');
	$body = preg_replace('~//[^\n]*~', '', substr($source, $write, $write_end - $write));
	pg_assert_contains("var content = _themeUpgradeLightSelector(entry.content || '');", $body, 'the old light block is split on write');
	pg_assert_contains('var kind = _themeBlockFor(mode, varName);', $body);
	pg_assert_contains("if (kind !== 'dark') content = _themeSetDecl(content, (kind === 'light') ? 'shared' : 'light', varName, '');", $body, 'the value leaves the other light block');
	pg_assert_contains('content = _themeSetDecl(content, kind, varName, value);', $body, 'the value goes to its own block');
	pg_assert_false(strpos($body, ':root') !== false, 'no light selector spelt out in the writer');

	// The load-time split runs before the setup reaches the theme helpers,
	// so they hold no value in a `var` of the setup scope (it would still be
	// undefined then).
	$from = strpos($source, 'function _findRuleBlock(');
	$to = ($from !== false) ? strpos($source, '// Read the current value of a single theme var', $from) : false;
	pg_assert_true(($from !== false) && ($to !== false), 'the designer has the theme rule-block helpers');
	pg_assert_same(0, preg_match_all('/^        (?:var|let|const) /m', (string) substr($source, $from, $to - $from)), 'no setup-scope variable among the helpers');
}
