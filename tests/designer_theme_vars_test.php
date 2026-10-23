<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for includes/fn/designer_theme_vars.php, the PHP twin of the visual
 * designer's Themes panel helpers the upgrade uses to split the old combined
 * light block (`:root, [data-bs-theme="light"]`) of stored designs. The
 * editor's helpers are cut out of assets/js/style_designer.js and run under
 * Node over the same inputs; the PHP result must be the same text, byte for
 * byte. On a machine without Node the comparisons are skipped and the PHP
 * results are still checked against their expected values.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// User Styles texts: name => CSS.
function designer_theme_vars_test_css()
{
	$nbsp = "\xC2\xA0";
	$many = '';

	for ($i = 0; $i < 110; $i++) {
		$many .= ':root,[data-bs-theme="light"]{--bs-body-bg:#' . sprintf('%03d', $i) . '}';
	}

	return array(
		'colours only' => ":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n  --bs-body-color: #333;\n}\n",
		'colours, fonts and radii' => ":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n  --bs-body-color: #586e75;\n  --bs-body-font-family: Georgia, serif;\n  --bs-border-radius: 0;\n  --bs-primary: #b58900;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #002b36;\n}\n\n.btn-primary { --bs-btn-bg: #b58900; }\n",
		'after the dark block' => "[data-bs-theme=\"dark\"] {\n  --bs-body-font-family: mono;\n  --bs-body-bg: #111;\n}\n\n:root,\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n  --bs-body-font-family: Georgia, serif;\n}\n",
		'colours only after the dark block' => "[data-bs-theme=\"dark\"] { --bs-body-bg: #111; }\n:root, [data-bs-theme=\"light\"] { --bs-body-bg: #fff; }",
		'single quotes' => ":root, [data-bs-theme='light'] {\n  --bs-body-bg: #fdf6e3;\n  --bs-border-radius: .5rem;\n}\n[data-bs-theme='dark'] { --bs-body-bg: #000; }",
		'spacing' => ":root , [data-bs-theme='light']{--bs-body-bg:1}",
		'already split' => ":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-font-family: Georgia, serif;\n}\n\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n",
		'fonts only before the dark block' => ":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-font-family: Georgia, serif;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #111;\n}\n",
		'bare blocks' => ":root { --bs-body-bg: red; }\n[data-bs-theme=\"light\"] { --bs-body-bg: blue; }\n[data-bs-theme=\"dark\"] .bg-primary { color: red; }",
		'comments' => "/* Theme \xE2\x80\x94 T\xC3\xBCrk\xC3\xA7e a\xC3\xA7\xC4\xB1klama \xE2\x9C\x93 */\n:root,\n[data-bs-theme=\"light\"] {\n  /* page */\n  --bs-body-bg: #fdf6e3; /* cream; warm */\n  --bs-body-font-family: Georgia, serif;\n  /* radius */ --bs-border-radius: 0;\n}\n",
		'minified' => ':root,[data-bs-theme="light"]{--bs-body-bg:#fdf6e3;--bs-body-color:#222;--bs-body-font-family:Georgia,serif;--bs-border-radius:0}[data-bs-theme="dark"]{--bs-body-bg:#111}.x{color:red}',
		'no theme block' => ".hero { padding: 2rem; }\nbody { margin: 0; }\n",
		'empty' => '',
		'non-breaking spaces' => ':root,' . $nbsp . '[data-bs-theme="light"]' . $nbsp . '{' . $nbsp . '--bs-body-bg:' . $nbsp . '#fdf6e3;' . $nbsp . '--bs-border-radius:' . $nbsp . '0;' . $nbsp . '}',
		'into the light block' => ":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #new;\n}\n\n:root, [data-bs-theme=\"light\"] { --bs-body-bg: #old; --bs-link-color: #00f; --bs-border-radius: 0 }",
		'into a minified light block' => ':root:not([data-bs-theme="dark"]),[data-bs-theme="light"]{--bs-body-bg:#new}:root,[data-bs-theme="light"]{--bs-link-color:#00f}',
		'zero values' => ":root:not([data-bs-theme=\"dark\"]), [data-bs-theme=\"light\"] { --bs-body-color: #000; }\n:root, [data-bs-theme=\"light\"] { --bs-body-bg: 0; --bs-border-width: 0 }",
		'line feeds and carriage returns' => ":root,\r\n[data-bs-theme=\"light\"] {\r\n  --bs-body-bg: #fdf6e3;\r\n  --bs-border-radius: 0;\r\n}\r\n",
		'inside a media query' => "@media (min-width: 768px) {\n  :root,\n  [data-bs-theme=\"light\"] {\n    --bs-body-bg: #eee;\n    --bs-body-font-size: 1.1rem;\n  }\n}\n",
		'other declarations only' => ':root, [data-bs-theme="light"] { color-scheme: light; }',
		'characters beyond ASCII' => "/* \xF0\x9F\x8C\x99 */ :root, [data-bs-theme=\"light\"] { --bs-body-font-family: \"Noto Sans\", \"\xC3\x9Cnl\xC3\xBC Yaz\xC4\xB1\"; --bs-body-bg: #fdf6e3; }",
		'more blocks than the guard' => $many,
		'old theme file' => ":root,\n[data-bs-theme=\"light\"] {\n    --bs-body-bg: #fdf6e3;\n    --bs-body-font-family: Georgia, serif;\n}\n\n[data-bs-theme=\"dark\"] {\n    --bs-body-bg: #111;\n}",
	);
}

// Design asset lists (the cssFiles JSON the editor stores): name => entries.
function designer_theme_vars_test_lists()
{
	$css = designer_theme_vars_test_css();

	return array(
		'a design' => array(
			array('id' => 'a1', 'name' => 'bootstrap.min.css', 'type' => 'external-css', 'content' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css', 'enabled' => true, 'locked' => true, 'meta' => 'bootstrap-css'),
			array('id' => 'a2', 'name' => 'site.css', 'type' => 'external-css', 'content' => '/site.css', 'enabled' => true, 'locked' => false, 'pages' => array()),
			array('id' => 'a3', 'name' => "Kullan\xC4\xB1c\xC4\xB1 Stilleri", 'type' => 'css', 'content' => $css['colours, fonts and radii'] . ".q::before { content: \"\\201C\"; } /* \xE2\x80\xA8 </style> */\n", 'enabled' => true, 'locked' => false, 'meta' => 'user-styles'),
			array('id' => 'a4', 'name' => 'extra', 'type' => 'css', 'content' => '', 'enabled' => false, 'locked' => false, 'options' => new stdClass()),
		),
		'two entries' => array(
			array('id' => 'b1', 'type' => 'css', 'content' => $css['colours only'], 'meta' => 'user-styles'),
			array('id' => 'b2', 'type' => 'css', 'content' => $css['minified'], 'meta' => 'user-styles'),
		),
		'already split' => array(
			array('id' => 'c1', 'type' => 'css', 'content' => $css['already split'], 'meta' => 'user-styles'),
		),
		'no user styles' => array(
			array('id' => 'd1', 'type' => 'css', 'content' => $css['colours only'], 'meta' => ''),
		),
	);
}

// The editor's answers for every input, or null without Node:
// css: name => {split, again, theme}; json: name => {before, after}.
function designer_theme_vars_test_js()
{
	static $answer = false;

	if ($answer !== false) {
		return $answer;
	}

	$answer = null;
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);

	if ($code !== 0) {
		return null;
	}

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	$from = strpos($source, 'function _findRuleBlock(');
	$to = ($from !== false) ? strpos($source, '// Read the current value of a single theme var', $from) : false;
	$theme = strpos($source, 'function _sdThemeCssFromUserStyles(');
	$theme_end = ($theme !== false) ? strpos($source, '// Header of the Themes tab', $theme) : false;
	pg_assert_true(($from !== false) && ($to !== false) && ($theme !== false) && ($theme_end !== false), 'the designer has the theme helpers');

	if (($from === false) || ($to === false) || ($theme === false) || ($theme_end === false)) {
		return null;
	}

	// The User Styles entries are split as _ensureBootstrapSentinels() splits
	// them when a design is opened (its loop is checked in
	// tests/designer_theme_light_selector_test.php).
	$steps = <<<'JS'
var cssFiles = [];
var input = JSON.parse(require('fs').readFileSync(process.argv[2], 'utf8'));
var out = { css: {}, json: {} };
Object.keys(input.css).forEach(function (k) {
	var c = input.css[k];
	var split = _themeUpgradeLightSelector(c);
	cssFiles = [{ meta: 'user-styles', content: c }];
	out.css[k] = { split: split, again: _themeUpgradeLightSelector(split), theme: _sdThemeCssFromUserStyles() };
});
Object.keys(input.lists).forEach(function (k) {
	var list = input.lists[k];
	var before = JSON.stringify(list);
	for (var ui = 0; ui < list.length; ui++) {
		if (list[ui].meta !== 'user-styles') continue;
		var upgraded = _themeUpgradeLightSelector(list[ui].content || '');
		if (upgraded !== (list[ui].content || '')) { list[ui].content = upgraded; }
	}
	out.json[k] = { before: before, after: JSON.stringify(list) };
});
console.log(JSON.stringify(out));
JS;

	$script = tempnam(sys_get_temp_dir(), 'pgthemevars');
	$data = tempnam(sys_get_temp_dir(), 'pgthemevarsin');
	file_put_contents($script, substr($source, $from, $to - $from) . "\n" . substr($source, $theme, $theme_end - $theme) . "\n" . $steps);
	file_put_contents($data, json_encode(array('css' => designer_theme_vars_test_css(), 'lists' => designer_theme_vars_test_lists()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	$out = array();
	exec('node ' . escapeshellarg($script) . ' ' . escapeshellarg($data) . ' 2>&1', $out, $code);
	unlink($script);
	unlink($data);

	pg_assert_same(0, $code, implode("\n", $out));
	$answer = json_decode(implode("\n", $out), true);
	pg_assert_true(is_array($answer), 'the editor answered');

	return is_array($answer) ? $answer : null;
}

// The split gives the editor's text for every input, byte for byte.
function test_designer_theme_vars_split_matches_editor()
{
	$js = designer_theme_vars_test_js();

	if ($js === null) {
		return;
	}

	foreach (designer_theme_vars_test_css() as $name => $css) {
		pg_assert_same($js['css'][$name]['split'], pg_theme_vars_upgrade_light_selector($css), $name);
	}
}

// A second pass changes nothing, in PHP and in the editor.
function test_designer_theme_vars_split_twice()
{
	$js = designer_theme_vars_test_js();

	foreach (designer_theme_vars_test_css() as $name => $css) {
		$once = pg_theme_vars_upgrade_light_selector($css);

		if ($name === 'more blocks than the guard') {
			// One pass stops after 100 blocks, as the editor's does.
			continue;
		}

		pg_assert_same($once, pg_theme_vars_upgrade_light_selector($once), $name);

		if ($js !== null) {
			pg_assert_same($js['css'][$name]['split'], $js['css'][$name]['again'], 'editor: ' . $name);
		}
	}
}

// What the split does, checked without Node too.
function test_designer_theme_vars_split_results()
{
	$css = designer_theme_vars_test_css();

	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n  --bs-body-color: #333;\n}\n",
		pg_theme_vars_upgrade_light_selector($css['colours only']), 'a block of colours is renamed');
	pg_assert_same(":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-font-family: Georgia, serif;\n  --bs-border-radius: 0;\n  --bs-primary: #b58900;\n}\n\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n  --bs-body-color: #586e75;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-bg: #002b36;\n}\n\n.btn-primary { --bs-btn-bg: #b58900; }\n",
		pg_theme_vars_upgrade_light_selector($css['colours, fonts and radii']), 'colours move, fonts, radii and theme colours stay');
	pg_assert_same(":root,\n[data-bs-theme=\"light\"] {\n  --bs-body-font-family: Georgia, serif;\n}\n\n[data-bs-theme=\"dark\"] {\n  --bs-body-font-family: mono;\n  --bs-body-bg: #111;\n}\n\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #fdf6e3;\n}\n",
		pg_theme_vars_upgrade_light_selector($css['after the dark block']), 'the shared block moves before the dark block');
	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n  --bs-body-bg: #new;\n  --bs-link-color: #00f;\n}\n\n:root, [data-bs-theme=\"light\"] { --bs-border-radius: 0 }",
		pg_theme_vars_upgrade_light_selector($css['into the light block']), 'moved values join the light block, which keeps its own');
	pg_assert_contains('--bs-body-bg: 0;', pg_theme_vars_upgrade_light_selector($css['zero values']), 'a value of 0 is a value');

	foreach (array('already split', 'fonts only before the dark block', 'bare blocks', 'no theme block', 'empty', 'other declarations only') as $name) {
		pg_assert_same($css[$name], pg_theme_vars_upgrade_light_selector($css[$name]), 'left as it is: ' . $name);
	}

	pg_assert_same(":root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {--bs-body-bg:1}", pg_theme_vars_upgrade_light_selector($css['spacing']), 'spacing and single quotes');
}

// "Save these colours as a theme" writes the same stylesheet as the editor.
function test_designer_theme_vars_theme_css_matches_editor()
{
	$css = designer_theme_vars_test_css();
	$js = designer_theme_vars_test_js();

	pg_assert_same(":root,\n[data-bs-theme=\"light\"] {\n    --bs-body-font-family: Georgia, serif;\n}\n\n:root:not([data-bs-theme=\"dark\"]),\n[data-bs-theme=\"light\"] {\n    --bs-body-bg: #fdf6e3;\n}\n\n[data-bs-theme=\"dark\"] {\n    --bs-body-bg: #111;\n}",
		pg_theme_vars_theme_css($css['old theme file']), 'an old theme file in the three blocks');

	if ($js === null) {
		return;
	}

	foreach ($css as $name => $text) {
		pg_assert_same($js['css'][$name]['theme'], pg_theme_vars_theme_css($text), $name);
	}
}

// Only a stylesheet in the shape the old "Save these colours as a theme"
// wrote is taken for an old theme file: not a palette file, not a theme
// file written now, not one with a rule added by hand.
function test_designer_theme_vars_old_theme_file()
{
	$css = designer_theme_vars_test_css();
	$old = $css['old theme file'];

	pg_assert_true(pg_theme_vars_is_old_theme_file($old), 'the old file');
	pg_assert_true(pg_theme_vars_is_old_theme_file($old . "\n"), 'with a newline at the end');
	pg_assert_true(pg_theme_vars_is_old_theme_file(":root,\n[data-bs-theme=\"light\"] {\n    --bs-body-bg: #fdf6e3;\n}"), 'light only');
	pg_assert_false(pg_theme_vars_is_old_theme_file($old . "\n\n.btn { color: red; }"), 'a rule added by hand');
	pg_assert_false(pg_theme_vars_is_old_theme_file(pg_theme_vars_theme_css($old)), 'a theme file written now');
	pg_assert_false(pg_theme_vars_is_old_theme_file("[data-bs-theme=\"dark\"] {\n    --bs-body-bg: #111;\n}"), 'dark only: nothing to split');

	$palette = pg_design_palette_css('#0d6efd', '#6c757d', 'Test');
	pg_assert_true(is_string($palette) && strpos($palette, ":root,\n[data-bs-theme=light]") !== false, 'a palette file keeps its selector');
	pg_assert_false(pg_theme_vars_is_old_theme_file((string) $palette), 'a palette file');
	pg_assert_same((string) $palette, pg_theme_vars_upgrade_light_selector((string) $palette), 'a palette file is not split');
}

// A design's asset list comes out as the editor stores it after the split;
// a list with nothing to split, or no list at all, gives null.
function test_designer_theme_vars_assets_json()
{
	$js = designer_theme_vars_test_js();

	foreach (array('', 'body { color: red; }', '{"a":1}', '[{"meta":"user-styles","content":', 'null', '[]') as $text) {
		pg_assert_same(null, pg_theme_vars_upgrade_assets_json($text), 'no list: ' . $text);
	}

	pg_assert_same(null, pg_theme_vars_upgrade_assets_json('[{"meta":"user-styles","content":null},{"meta":"user-styles","content":7}]'), 'no text to split');

	if ($js === null) {
		return;
	}

	foreach (designer_theme_vars_test_lists() as $name => $list) {
		$before = $js['json'][$name]['before'];
		$after = $js['json'][$name]['after'];
		$php = pg_theme_vars_upgrade_assets_json($before);

		if ($before === $after) {
			pg_assert_same(null, $php, 'nothing to split: ' . $name);
		} else {
			pg_assert_same($after, $php, $name);
			pg_assert_same(null, pg_theme_vars_upgrade_assets_json((string) $php), 'a second pass: ' . $name);
		}
	}

	pg_assert_contains('"options":{}', $js['json']['a design']['after'], 'an empty object stays an object');
}

// A design template made from a design has its asset list split the same
// way; the rest of the document is kept.
function test_designer_theme_vars_template_json()
{
	$js = designer_theme_vars_test_js();

	if ($js === null) {
		return;
	}

	$doc = array(
		'name' => "Kafe \xE2\x80\x94 \xC3\xA7ay",
		'pages' => array(array('key' => 'home', 'tree' => array('type' => 'body', 'props' => new stdClass(), 'children' => array()))),
		'assets' => array('css' => $js['json']['a design']['before'], 'js' => '[]', 'fonts' => '', 'head' => '', 'body_classes' => ''),
		'ratio' => 0.75,
	);
	$json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	$php = pg_theme_vars_upgrade_template_json($json);
	pg_assert_true(is_string($php), 'the document is rewritten');

	$read = json_decode((string) $php, true);
	pg_assert_same($js['json']['a design']['after'], $read['assets']['css'], 'its asset list is split');
	pg_assert_same(str_replace(json_encode($doc['assets']['css'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), json_encode($read['assets']['css'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $json), $php, 'nothing else changes');
	pg_assert_same(null, pg_theme_vars_upgrade_template_json((string) $php), 'a second pass');
	pg_assert_same(null, pg_theme_vars_upgrade_template_json('{"pages":[]}'), 'no asset list');
	pg_assert_same(null, pg_theme_vars_upgrade_template_json('{'), 'a broken document');
}
