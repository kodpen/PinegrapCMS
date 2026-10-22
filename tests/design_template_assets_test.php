<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the CSS a design template ships: pg_designer_block_css() writes
 * the ready-made blocks' rules byte for byte as the editor's palette drop
 * (_sdEnsureBlockStyles() in style_designer.js) appends them, so a later drop
 * finds them and adds nothing; pg_design_template_user_styles() wraps them
 * in the one User Styles entry; _pg_tpl_preview_assets() prints a template's
 * fonts and CSS in the installer preview the way a published page prints
 * them; and every shipped template carries the block rules its trees use.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The CSS text of one _SD_BLOCK_CSS entry, read from the JS source line by
// line and decoded as JSON strings: a reading independent of the product's
// parser. The entry is its key's line plus the lines that continue it with +.
function _test_dta_js_rule($name)
{
	static $lines = null;
	if ($lines === null) {
		$lines = explode("\n", (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js'));
	}
	$css = '';
	$inside = false;
	foreach ($lines as $line) {
		if (!$inside) {
			if (strpos(ltrim($line), "'" . $name . "':") !== 0) {
				continue;
			}
			$inside = true;
			$line = substr(ltrim($line), strlen("'" . $name . "':"));
		}
		if (!preg_match("/'((?:[^'\\\\]|\\\\.)*)'\\s*([+,]?)\\s*$/", $line, $m)) {
			break;
		}
		$text = json_decode('"' . str_replace(array("\\'", '"'), array("'", '\\"'), $m[1]) . '"');
		$css .= is_string($text) ? $text : '';
		if ($m[2] !== '+') {
			break;
		}
	}
	return $css;
}

// The class names a template's trees use: cssClass props, class attributes
// in _attrs, and class="…" inside HTML strings.
function _test_dta_tree_classes($value, &$out)
{
	if (is_array($value)) {
		foreach ($value as $key => $item) {
			if ($key === 'assets') {
				continue;
			}
			if ($key === 'cssClass' && is_string($item)) {
				foreach (preg_split('/\\s+/', $item, -1, PREG_SPLIT_NO_EMPTY) as $class) {
					$out[$class] = true;
				}
				continue;
			}
			if (is_array($item) && isset($item['name'], $item['value']) && $item['name'] === 'class' && is_string($item['value'])) {
				foreach (preg_split('/\\s+/', $item['value'], -1, PREG_SPLIT_NO_EMPTY) as $class) {
					$out[$class] = true;
				}
			}
			_test_dta_tree_classes($item, $out);
		}
		return;
	}
	if (is_string($value) && preg_match_all('/\\bclass\\s*=\\s*"([^"]*)"/i', $value, $m)) {
		foreach ($m[1] as $list) {
			foreach (preg_split('/\\s+/', $list, -1, PREG_SPLIT_NO_EMPTY) as $class) {
				$out[$class] = true;
			}
		}
	}
}

// What is wrong with a template's User Styles text for the classes its trees
// use, as a list of messages ([] when nothing is):
// - a class that is an _SD_BLOCK_CSS key needs that rule's full text, as the
//   drop writes it; a mere mention of the class is what makes a later drop
//   skip the rule, so it does not count;
// - another pg-* class a block rule defines (pg-marquee-track) needs the
//   full text of one rule whose selectors name it;
// - the template's own CSS (what is left after the marker and the shipped
//   rules) names no _SD_BLOCK_CSS key's class, since the drop would take
//   that mention for the rule.
function _test_dta_user_styles_problems($used, $user_styles)
{
	$source = _pg_designer_block_css_source();
	$problems = array();
	foreach ($used as $class) {
		if (isset($source['rules'][$class])) {
			if (strpos($user_styles, $source['rules'][$class]) === false) {
				$problems[] = 'the rule for .' . $class . ' is missing';
			}
			continue;
		}
		// Only the blocks' own classes: a rule scoped as `.pg-x .btn` does
		// not define Bootstrap's .btn.
		if (strpos($class, 'pg-') !== 0) {
			continue;
		}
		$defining = 0;
		$present = false;
		foreach ($source['rules'] as $css) {
			if (preg_match('/\\.' . preg_quote($class, '/') . '(?![\\w-])/', $css) !== 1) {
				continue;
			}
			$defining++;
			if (strpos($user_styles, $css) !== false) {
				$present = true;
			}
		}
		if ($defining > 0 && !$present) {
			$problems[] = 'the rule for .' . $class . ' is missing';
		}
	}

	$own = str_replace($source['mark'] . "\n", '', $user_styles);
	foreach ($source['rules'] as $css) {
		$own = str_replace($css . "\n", '', $own);
	}
	foreach (array_keys($source['rules']) as $key) {
		if (preg_match('/\\.' . preg_quote($key, '/') . '(?![\\w-])/', $own) === 1) {
			$problems[] = 'the template\'s own CSS names the block class .' . $key;
		}
	}
	return $problems;
}

function test_design_template_assets_reader_matches_editor()
{
	$marquee = _test_dta_js_rule('pg-marquee');
	$shade = _test_dta_js_rule('pg-shade-bottom');
	pg_assert_contains('.pg-marquee-track {', $marquee, 'the pg-marquee entry is read from the JS source');
	pg_assert_contains('.pg-shade-bottom {', $shade, 'the pg-shade-bottom entry is read from the JS source');
	pg_assert_contains('[dir="rtl"] .pg-marquee-track', $marquee, 'quotes inside a rule survive');

	// _sdEnsureBlockStyles() on empty User Styles: the marker, then each
	// rule followed by a newline.
	$expected = "/* Pinegrap block styles */\n" . $marquee . "\n" . $shade . "\n";
	pg_assert_same($expected, pg_designer_block_css(array('pg-marquee', 'pg-shade-bottom')), 'byte-identical to a palette drop');
	pg_assert_same(1, substr_count(pg_designer_block_css(array('pg-marquee', 'pg-shade-bottom', 'pg-marquee')), '.pg-shade-bottom {'), 'a name asked for twice is written once');
}

function test_design_template_assets_reader_reads_every_rule()
{
	$source = _pg_designer_block_css_source();
	pg_assert_same('/* Pinegrap block styles */', $source['mark'], 'the marker is read from _SD_BLOCK_CSS_MARK');
	pg_assert_true(count($source['rules']) > 0, '_SD_BLOCK_CSS is found');
	pg_assert_true(count($source['needs']) > 0, '_SD_BLOCK_CSS_NEEDS is found');
	foreach ($source['rules'] as $name => $css) {
		pg_assert_same(_test_dta_js_rule($name), $css, $name . ' is read as the JS source holds it');
	}
}

function test_design_template_assets_reader_resolves_block_types()
{
	$source = _pg_designer_block_css_source();
	pg_assert_same(array('pg-ratio-lead', 'pg-shade-bottom', 'pg-ratio-16x9'), $source['needs']['news-lead-grid'], 'the needs of news-lead-grid are read');
	pg_assert_same(
		pg_designer_block_css(array('pg-ratio-lead', 'pg-shade-bottom', 'pg-ratio-16x9')),
		pg_designer_block_css(array('news-lead-grid')),
		'a block type stands for the rules it needs'
	);
	pg_assert_same(
		pg_designer_block_css(array('pg-marquee', 'pg-marquee-fade', 'pg-logo-44', 'pg-ratio-1x1')),
		pg_designer_block_css(array('marquee-logos', 'news-ticker', 'pg-ratio-1x1')),
		'block types and rule names mix, a rule shared by two blocks is written once'
	);
	foreach ($source['needs'] as $ctype => $names) {
		pg_assert_true(pg_designer_block_css(array($ctype)) !== '', $ctype . ' resolves to its rules');
	}
}

function test_design_template_assets_reader_ignores_unknown_names()
{
	pg_assert_same('', pg_designer_block_css(array()), 'nothing asked, nothing written');
	pg_assert_same('', pg_designer_block_css(array('pg-no-such-rule', 'no-such-block', '', null, array('pg-marquee'))), 'unknown names write nothing, not even the marker');
	pg_assert_same(pg_designer_block_css(array('pg-shade-bottom')), pg_designer_block_css(array('pg-no-such-rule', 'pg-shade-bottom')), 'an unknown name is skipped');
}

function test_design_template_assets_reader_skips_rules_already_there()
{
	$first = pg_designer_block_css(array('news-ticker'));
	pg_assert_same('', pg_designer_block_css(array('news-ticker'), $first), 'a second drop of the same block adds nothing');

	$more = pg_designer_block_css(array('marquee-logos'), $first);
	pg_assert_same(pg_designer_block_css(array('pg-marquee-fade', 'pg-logo-44'), $first), $more, 'only the missing rules are added');
	pg_assert_false(strpos($more, '/* Pinegrap block styles */') !== false, 'the marker is not written twice');
	pg_assert_false(strpos($more, '.pg-marquee-viewport') !== false, 'the shared rule is not written again');

	pg_assert_same('', pg_designer_block_css(array('pg-shade-bottom'), ".pg-shade-bottom { background: none; }\n"), 'an edited rule is kept');
	pg_assert_contains('.pg-ratio-16x9 {', pg_designer_block_css(array('pg-ratio-16x9'), '.pg-ratio-16x9-alt { color: red; }'), 'a longer class is not the rule\'s class');
}

function test_design_template_assets_user_styles_entry()
{
	$names = array('pg-marquee', 'pg-shade-bottom');
	$list = json_decode(pg_design_template_user_styles($names, "\n.news-band { gap: 1rem; }\n\n"), true);
	pg_assert_true(is_array($list) && count($list) === 1, 'one entry in a JSON list');
	$entry = $list[0];
	pg_assert_same(array('id', 'name', 'type', 'content', 'enabled', 'locked', 'meta'), array_keys($entry), 'the shape the editor stores');
	pg_assert_same('user-styles', $entry['meta'], 'it is the Styles panel\'s entry');
	pg_assert_same('css', $entry['type'], 'inline CSS');
	pg_assert_same(lang('User Styles'), $entry['name'], 'named as the editor names it');
	pg_assert_same(true, $entry['enabled'], 'enabled');
	pg_assert_same(false, $entry['locked'], 'not locked');
	pg_assert_same(pg_designer_block_css($names) . ".news-band { gap: 1rem; }\n", $entry['content'], 'block rules, then the template\'s own');

	$plain = json_decode(pg_design_template_user_styles($names), true);
	pg_assert_same(pg_designer_block_css($names), $plain[0]['content'], 'without own rules the content is the block rules alone');
}

function test_design_template_assets_install_adds_framework_rows()
{
	$fw = pg_design_framework('bootstrap5');
	$styles = pg_design_template_user_styles(array('pg-marquee'), 'body { font-family: serif; }');
	$list = json_decode(pg_design_template_asset_sentinels($styles, 'bootstrap5'), true);
	pg_assert_same(array('bootstrap-css', 'bootstrap-icons', 'user-styles'), array_map(function ($e) { return $e['meta']; }, $list), 'the framework rows come first, User Styles after them');
	pg_assert_same(array('id' => 'tpl-bootstrap-css', 'name' => 'bootstrap.min.css', 'type' => 'external-css', 'content' => $fw['css'], 'enabled' => true, 'locked' => true, 'meta' => 'bootstrap-css'), $list[0], 'the bootstrap-css row as the editor writes it');
	pg_assert_same(array('id' => 'tpl-bootstrap-icons', 'name' => 'bootstrap-icons.min.css', 'type' => 'external-css', 'content' => pg_design_icons_url(), 'enabled' => true, 'locked' => true, 'meta' => 'bootstrap-icons'), $list[1], 'the bootstrap-icons row as the editor writes it');
	$own = json_decode($styles, true);
	pg_assert_same($own[0], $list[2], 'the User Styles entry is kept as it was');
	pg_assert_same(pg_design_template_asset_sentinels($styles, ''), pg_design_template_asset_sentinels($styles, 'bootstrap5'), 'an empty framework reads as Bootstrap 5');

	$from_design = json_encode(array(
		array('id' => '1', 'name' => 'bootstrap.min.css', 'type' => 'external-css', 'content' => $fw['css'], 'enabled' => true, 'locked' => true, 'meta' => 'bootstrap-css'),
		array('id' => '2', 'name' => 'User Styles', 'type' => 'css', 'content' => '.a{}', 'enabled' => true, 'locked' => false, 'meta' => 'user-styles'),
	));
	pg_assert_same($from_design, pg_design_template_asset_sentinels($from_design, 'bootstrap5'), 'a list with a bootstrap-css row is left alone');

	$icons_only = json_encode(array(
		array('id' => '1', 'name' => 'bootstrap-icons.min.css', 'type' => 'external-css', 'content' => pg_design_icons_url(), 'enabled' => false, 'locked' => true, 'meta' => 'bootstrap-icons'),
		array('id' => '2', 'name' => 'User Styles', 'type' => 'css', 'content' => '.a{}', 'enabled' => true, 'locked' => false, 'meta' => 'user-styles'),
	));
	$list = json_decode(pg_design_template_asset_sentinels($icons_only, 'bootstrap5'), true);
	pg_assert_same(array('bootstrap-css', 'bootstrap-icons', 'user-styles'), array_map(function ($e) { return $e['meta']; }, $list), 'an icons row of its own is not added twice');
	pg_assert_same(false, $list[1]['enabled'], 'a switched-off icons row stays off');

	pg_assert_same($styles, pg_design_template_asset_sentinels($styles, 'custom'), 'a framework without a stylesheet gets no rows');
	pg_assert_same('', pg_design_template_asset_sentinels('', 'bootstrap5'), 'no CSS stays no CSS');
	pg_assert_same('[]', pg_design_template_asset_sentinels('[]', 'bootstrap5'), 'an empty list stays empty');
	pg_assert_same('body { color: red; }', pg_design_template_asset_sentinels('body { color: red; }', 'bootstrap5'), 'legacy plain text is left alone');
}

function test_design_template_assets_preview_head()
{
	$tpl = array('assets' => array(
		'fonts' => json_encode(array(
			array('family' => 'Source Serif 4', 'weights' => array('400', '700'), 'enabled' => true),
			array('family' => 'Off Font', 'enabled' => false),
		)),
		'css' => json_encode(array(
			array('name' => 'User Styles', 'type' => 'css', 'content' => '.a { color: red; } </style><script>', 'enabled' => true, 'meta' => 'user-styles'),
			array('name' => 'bootstrap.min.css', 'type' => 'external-css', 'content' => 'https://cdn.example.com/bootstrap.css', 'enabled' => true, 'meta' => 'bootstrap-css'),
			array('name' => 'theme.css', 'type' => 'external-css', 'content' => 'https://cdn.example.com/theme.css', 'enabled' => true),
			array('name' => 'page.css', 'type' => 'css', 'content' => '.b { color: blue; }', 'enabled' => true),
			array('name' => 'off.css', 'type' => 'css', 'content' => '.off { color: red; }', 'enabled' => false),
			array('name' => 'about.css', 'type' => 'css', 'content' => '.about { color: red; }', 'enabled' => true, 'pages' => array('key:about')),
			array('name' => 'home.css', 'type' => 'css', 'content' => '.home { color: red; }', 'enabled' => true, 'pages' => array('key:home')),
		)),
	));
	$head = _pg_tpl_preview_assets($tpl, 'home');
	pg_assert_contains('<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source%20Serif%204:wght@400;700&amp;display=swap">', $head, 'a font is linked as a published page links it');
	pg_assert_false(strpos($head, 'Off%20Font') !== false, 'a disabled font is left out');
	pg_assert_false(strpos($head, 'bootstrap.css') !== false, 'a framework sentinel is left out');
	pg_assert_contains('<link rel="stylesheet" href="https://cdn.example.com/theme.css">', $head, 'an external stylesheet is linked');
	pg_assert_contains("<style id=\"pg-page-css\">\n/* page.css */\n.b { color: blue; }\n", $head, 'inline CSS is printed as pg-page-css');
	pg_assert_contains('.home {', $head, 'an entry limited to this page is printed');
	pg_assert_false(strpos($head, '.about {') !== false, 'an entry limited to another page is left out');
	pg_assert_false(strpos($head, '.off {') !== false, 'a disabled entry is left out');
	pg_assert_contains('<style id="pg-user-styles">' . "\n" . '.a { color: red; } <\\/style><script>' . "\n</style>", $head, 'User Styles cannot close their element');
	pg_assert_true(strrpos($head, '<style id="pg-user-styles">') > strrpos($head, '<style id="pg-page-css">'), 'User Styles come last');
	pg_assert_same('', _pg_tpl_preview_assets(array('pages' => array()), 'home'), 'a template without assets prints nothing');
}

function test_design_template_assets_shipped_templates_carry_their_rules()
{
	$files = glob(PG_FUNCTIONS_DIR . '/includes/design_templates/*.php');
	pg_assert_true(is_array($files) && count($files) > 0, 'the shipped templates are found');
	foreach ($files ? $files : array() as $file) {
		$id = basename($file, '.php');
		$tpl = _pg_tpl_include($file);
		if (!is_array($tpl)) {
			continue;
		}
		$user_styles = '';
		$css = (isset($tpl['assets']['css']) && is_string($tpl['assets']['css'])) ? $tpl['assets']['css'] : '';
		if ($css !== '') {
			$list = json_decode($css, true);
			pg_assert_true(is_array($list), $id . ': assets css is a JSON list');
			$found = 0;
			foreach (is_array($list) ? $list : array() as $entry) {
				if (is_array($entry) && isset($entry['meta']) && $entry['meta'] === 'user-styles') {
					$found++;
					$user_styles .= isset($entry['content']) ? (string) $entry['content'] : '';
				}
			}
			pg_assert_same(1, $found, $id . ': one User Styles entry');
		}
		$used = array();
		_test_dta_tree_classes($tpl, $used);
		pg_assert_same(array(), _test_dta_user_styles_problems(array_keys($used), $user_styles), $id . ': its User Styles carry the block rules its trees use');
	}
}

function test_design_template_assets_guard_catches_missing_rules()
{
	$source = _pg_designer_block_css_source();
	$content = function ($names, $extra) {
		$list = json_decode(pg_design_template_user_styles($names, $extra), true);
		return $list[0]['content'];
	};

	pg_assert_same(array(), _test_dta_user_styles_problems(array('pg-marquee', 'pg-marquee-track', 'pg-shade-bottom', 'np-lead'), $content(array('pg-marquee', 'pg-shade-bottom'), '.np-lead { color: red; }')), 'rules shipped through the reader pass');
	pg_assert_same(array(), _test_dta_user_styles_problems(array('pg-shade-bottom'), $content(array('news-lead-grid'), '')), 'a block type stands for the rules it needs');
	pg_assert_same(array(), _test_dta_user_styles_problems(array('np-lead'), ''), 'a template without block classes needs no User Styles');

	// A block class overridden in the template's own CSS without its rule:
	// the class is mentioned, so a later drop would skip the rule for good.
	$override = $content(array('news-ticker'), '.np-lead .pg-shade-bottom { padding-top: 6rem; }');
	pg_assert_true(preg_match('/\\.pg-shade-bottom(?![\\w-])/', $override) === 1, 'the override mentions the class');
	pg_assert_false(strpos($override, $source['rules']['pg-shade-bottom']) !== false, 'the override lacks the rule');
	pg_assert_same(array(
		'the rule for .pg-shade-bottom is missing',
		'the template\'s own CSS names the block class .pg-shade-bottom',
	), _test_dta_user_styles_problems(array('pg-shade-bottom'), $override), 'an override in place of the rule is caught');

	// The rule shipped, plus an override of its class in the own CSS.
	pg_assert_same(array(
		'the template\'s own CSS names the block class .pg-shade-bottom',
	), _test_dta_user_styles_problems(array('pg-shade-bottom'), $content(array('pg-shade-bottom'), '.np-lead .pg-shade-bottom { padding-top: 6rem; }')), 'an override next to the rule is caught');

	// A class that is not a rule name (pg-marquee-track) needs a rule whose
	// selectors define it.
	pg_assert_same(array('the rule for .pg-marquee-track is missing'), _test_dta_user_styles_problems(array('pg-marquee-track'), $content(array('pg-shade-bottom'), '')), 'a sub-class without its rule is caught');
	pg_assert_same(array(
		'the rule for .pg-marquee is missing',
		'the template\'s own CSS names the block class .pg-marquee',
	), _test_dta_user_styles_problems(array('pg-marquee'), '.pg-marquee { overflow: hidden; }'), 'a hand-written rule in place of the block rule is caught');
}
