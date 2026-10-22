<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the ready-made blocks of the Visual Page Editor's block palette:
 * every entry of _sdBlockPaletteItems() has a builder inside
 * createFromPalette() that its family branch can reach, and a group in
 * _sdBlockPaletteGroups(); every rule a block asks for in _SD_BLOCK_CSS_NEEDS
 * exists in _SD_BLOCK_CSS and names its own key as a class (otherwise
 * _sdEnsureBlockStyles() appends it again on every drop); every pg-* class a
 * builder writes is defined by a rule the block needs; and no block rule
 * uses !important (Bootstrap utilities would lose the override war silently).
 * Two blocks keep a checked shape: scroller-cards' scroll hint points both
 * ways, and the weather strip's cities are look-driven chips.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function _test_blocks_js()
{
	static $js = null;
	if ($js === null) {
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.js');
	}
	return $js;
}

// The source between $start_marker and the first $end_marker after it, or ''.
function _test_blocks_slice($start_marker, $end_marker)
{
	$js = _test_blocks_js();
	$start = strpos($js, $start_marker);
	$end = $start === false ? false : strpos($js, $end_marker, $start);
	if ($start === false || $end === false) {
		return '';
	}
	return substr($js, $start, $end - $start);
}

// Body of _sdBlockPaletteItems(), up to the end of the function.
function _test_blocks_palette_body()
{
	return _test_blocks_slice('function _sdBlockPaletteItems()', "\n    }\n");
}

// componentType of every palette entry, in palette order.
function _test_blocks_palette_ctypes()
{
	preg_match_all("/componentType:\\s*'([a-z0-9-]+)'/", _test_blocks_palette_body(), $m);
	return $m[1];
}

// Walks JavaScript from $from (just after an opening brace) to its matching
// closing brace, skipping strings, comments and regex literals. Returns the
// offset of that brace (or -1) and the contents of the string literals seen.
function _test_blocks_scan($js, $from)
{
	$len = strlen($js);
	$depth = 1;
	$strings = array();
	$prev = '{';
	for ($i = $from; $i < $len; $i++) {
		$c = $js[$i];
		if ($c === "'" || $c === '"' || $c === '`') {
			$start = $i + 1;
			for ($i++; $i < $len && $js[$i] !== $c; $i++) {
				if ($js[$i] === '\\') {
					$i++;
				}
			}
			$strings[] = substr($js, $start, $i - $start);
			$prev = $c;
			continue;
		}
		if ($c === '/' && $i + 1 < $len && $js[$i + 1] === '/') {
			$nl = strpos($js, "\n", $i);
			$i = $nl === false ? $len : $nl;
			continue;
		}
		if ($c === '/' && $i + 1 < $len && $js[$i + 1] === '*') {
			$close = strpos($js, '*/', $i + 2);
			$i = $close === false ? $len : $close + 1;
			continue;
		}
		if ($c === '/' && strpos('(,=:[!&|?{};', $prev) !== false) {
			$in_class = false;
			for ($i++; $i < $len; $i++) {
				$r = $js[$i];
				if ($r === '\\') {
					$i++;
				} elseif ($r === '[') {
					$in_class = true;
				} elseif ($r === ']') {
					$in_class = false;
				} elseif ($r === '/' && !$in_class) {
					break;
				}
			}
			$prev = '/';
			continue;
		}
		if ($c === '{') {
			$depth++;
		} elseif ($c === '}') {
			$depth--;
			if ($depth === 0) {
				return array('end' => $i, 'strings' => $strings);
			}
		}
		if (!ctype_space($c)) {
			$prev = $c;
		}
	}
	return array('end' => -1, 'strings' => $strings);
}

// array(start, end) offsets of the block that opens at the first '{' at or
// after $at, or null.
function _test_blocks_block($at)
{
	$js = _test_blocks_js();
	$open = strpos($js, '{', $at);
	if ($open === false) {
		return null;
	}
	$scan = _test_blocks_scan($js, $open + 1);
	return $scan['end'] < 0 ? null : array($open, $scan['end']);
}

// array(start, end) of the body of createFromPalette(), or null.
function _test_blocks_cfp_range()
{
	$at = strpos(_test_blocks_js(), 'function createFromPalette(');
	return $at === false ? null : _test_blocks_block($at);
}

// The local-business family branch of createFromPalette(): the prefixes of
// its /^(…)-/ test, the ctypes it also accepts by name, and its body range.
function _test_blocks_family()
{
	$js = _test_blocks_js();
	if (!preg_match('/if \\(\\/\\^\\(((?:[a-z-]+\\|)*services(?:\\|[a-z-]+)*)\\)-\\/\\.test\\(ctype\\)([^{]*)\\{/', $js, $m, PREG_OFFSET_CAPTURE)) {
		return null;
	}
	preg_match_all("/ctype === '([a-z0-9-]+)'/", $m[2][0], $named);
	return array(
		'prefixes' => explode('|', $m[1][0]),
		'named'    => $named[1],
		'range'    => _test_blocks_block($m[0][1]),
	);
}

// Offset of the builder test for $ctype inside createFromPalette(), or false.
function _test_blocks_builder_at($ctype)
{
	$range = _test_blocks_cfp_range();
	if (!$range) {
		return false;
	}
	// Prefer the builder's own test: the family branch's header also names
	// a few ctypes, and its block is the whole family.
	$js = _test_blocks_js();
	$at = strpos($js, "if (ctype === '" . $ctype . "')", $range[0]);
	if ($at === false || $at > $range[1]) {
		$at = strpos($js, "ctype === '" . $ctype . "'", $range[0]);
	}
	return ($at === false || $at > $range[1]) ? false : $at;
}

// The string literals of $ctype's builder body, in source order.
function _test_blocks_builder_strings($ctype)
{
	$at = _test_blocks_builder_at($ctype);
	$block = $at === false ? null : _test_blocks_block($at);
	if (!$block) {
		return array();
	}
	$scan = _test_blocks_scan(_test_blocks_js(), $block[0] + 1);
	return $scan['strings'];
}

// True when one string literal of $ctype's builder carries every class in
// $classes as a whole token.
function _test_blocks_builder_has_classes($ctype, array $classes)
{
	foreach (_test_blocks_builder_strings($ctype) as $str) {
		$tokens = preg_split('/\s+/', trim($str));
		if (!array_diff($classes, $tokens)) {
			return true;
		}
	}
	return false;
}

// The pg-* classes written in the string literals of $ctype's builder body.
function _test_blocks_builder_classes($ctype)
{
	$out = array();
	foreach (_test_blocks_builder_strings($ctype) as $str) {
		if (preg_match_all('/(?<![\\w-])pg-[a-z0-9-]*[a-z0-9]/', $str, $m)) {
			$out = array_merge($out, $m[0]);
		}
	}
	return array_values(array_unique($out));
}

// Every prefix listed in the match arrays of _sdBlockPaletteGroups().
function _test_blocks_group_prefixes()
{
	$body = _test_blocks_slice('function _sdBlockPaletteGroups()', 'var other');
	preg_match_all('/match:\s*\[([^\]]*)\]/', $body, $lists);
	$out = array();
	foreach ($lists[1] as $list) {
		preg_match_all("/'([a-z0-9-]+)'/", $list, $m);
		$out = array_merge($out, $m[1]);
	}
	return $out;
}

// Body of the _SD_BLOCK_CSS object literal.
function _test_blocks_css_body()
{
	return _test_blocks_slice('var _SD_BLOCK_CSS = {', "\n    };");
}

// Rule names defined in _SD_BLOCK_CSS.
function _test_blocks_css_names()
{
	preg_match_all("/^\\s*'(pg-[a-z0-9-]+)'\\s*:/m", _test_blocks_css_body(), $m);
	return $m[1];
}

// ctype => rule names it lists in _SD_BLOCK_CSS_NEEDS.
function _test_blocks_css_needs()
{
	$body = _test_blocks_slice('var _SD_BLOCK_CSS_NEEDS = {', "\n    };");
	preg_match_all("/^\\s*'([a-z0-9-]+)'\\s*:\\s*\\[([^\\]]*)\\]/m", $body, $rows, PREG_SET_ORDER);
	$out = array();
	foreach ($rows as $row) {
		preg_match_all("/'([a-z0-9-]+)'/", $row[2], $m);
		$out[$row[1]] = $m[1];
	}
	return $out;
}

// Rule name => the CSS text of its entry (its string literals joined).
function _test_blocks_css_rules()
{
	$body = _test_blocks_css_body();
	preg_match_all("/^\\s*'(pg-[a-z0-9-]+)'\\s*:/m", $body, $keys, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
	$out = array();
	foreach ($keys as $i => $key) {
		$from = $key[0][1] + strlen($key[0][0]);
		$to = isset($keys[$i + 1]) ? $keys[$i + 1][0][1] : strlen($body);
		preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", substr($body, $from, $to - $from), $parts);
		$out[$key[1][0]] = str_replace('\\n', "\n", implode('', $parts[1]));
	}
	return $out;
}

// True when $css uses $class as a class selector (.pg-logo-4 is not .pg-logo-48).
function _test_blocks_css_has_class($css, $class)
{
	return preg_match('/\\.' . preg_quote($class, '/') . '(?![\\w-])/', $css) === 1;
}

function test_designer_blocks_palette_is_found()
{
	$ctypes = _test_blocks_palette_ctypes();
	pg_assert_true(count($ctypes) > 0, '_sdBlockPaletteItems() is found in style_designer.js');
	pg_assert_same(substr_count(_test_blocks_palette_body(), 'componentType:'), count($ctypes), 'every componentType of the palette is read');
	pg_assert_same(array_values(array_unique($ctypes)), $ctypes, 'no block is listed twice');
	pg_assert_true(_test_blocks_cfp_range() !== null, 'the body of createFromPalette() is found');
	pg_assert_true(_test_blocks_family() !== null && _test_blocks_family()['range'] !== null, 'the family branch of createFromPalette() is found');
	pg_assert_true(count(_test_blocks_group_prefixes()) > 0, '_sdBlockPaletteGroups() is found');
	pg_assert_true(count(_test_blocks_css_names()) > 0, '_SD_BLOCK_CSS is found');
	pg_assert_true(count(_test_blocks_css_needs()) > 0, '_SD_BLOCK_CSS_NEEDS is found');
}

function test_designer_blocks_every_palette_entry_has_a_builder()
{
	$missing = array();
	foreach (_test_blocks_palette_ctypes() as $ctype) {
		if (_test_blocks_builder_at($ctype) === false) {
			$missing[] = $ctype;
		}
	}
	pg_assert_same(array(), $missing, 'palette entries without a builder inside createFromPalette()');
}

// A builder inside the family branch runs only when the branch's own test
// lets its ctype in.
function test_designer_blocks_family_branch_reaches_its_builders()
{
	$family = _test_blocks_family();
	if ($family === null || $family['range'] === null) {
		pg_assert_true(false, 'the family branch of createFromPalette() is found');
		return;
	}
	$re = '/^(' . implode('|', array_map(function ($p) { return preg_quote($p, '/'); }, $family['prefixes'])) . ')-/';
	$inside = 0;
	$unreachable = array();
	foreach (_test_blocks_palette_ctypes() as $ctype) {
		$at = _test_blocks_builder_at($ctype);
		if ($at === false || $at < $family['range'][0] || $at > $family['range'][1]) {
			continue;
		}
		$inside++;
		if (!preg_match($re, $ctype) && !in_array($ctype, $family['named'], true)) {
			$unreachable[] = $ctype;
		}
	}
	pg_assert_true($inside > 0, 'builders are found inside the family branch');
	pg_assert_same(array(), $unreachable, 'builders inside the family branch that its test never lets in');
}

function test_designer_blocks_every_palette_entry_has_a_group()
{
	$prefixes = _test_blocks_group_prefixes();
	$orphans = array();
	foreach (_test_blocks_palette_ctypes() as $ctype) {
		$home = false;
		foreach ($prefixes as $prefix) {
			if (strpos($ctype, $prefix) === 0) {
				$home = true;
				break;
			}
		}
		if (!$home) {
			$orphans[] = $ctype;
		}
	}
	pg_assert_same(array(), $orphans, 'palette entries that would fall into "Other"');
}

function test_designer_blocks_every_needed_rule_exists()
{
	$names = _test_blocks_css_names();
	$missing = array();
	foreach (_test_blocks_css_needs() as $ctype => $rules) {
		foreach ($rules as $rule) {
			if (!in_array($rule, $names, true)) {
				$missing[] = $ctype . ' => ' . $rule;
			}
		}
	}
	pg_assert_same(array(), $missing, '_SD_BLOCK_CSS_NEEDS names that are not keys of _SD_BLOCK_CSS');
}

function test_designer_blocks_rules_never_use_important()
{
	pg_assert_false(stripos(_test_blocks_css_body(), '!important') !== false, 'no _SD_BLOCK_CSS rule uses !important');
}

// _sdEnsureBlockStyles() skips a rule when its key already appears as a
// class in the user styles; a rule that never names its key is appended
// again on every drop.
function test_designer_blocks_every_rule_names_its_key()
{
	$rules = _test_blocks_css_rules();
	pg_assert_same(_test_blocks_css_names(), array_keys($rules), 'every _SD_BLOCK_CSS entry is read');
	$silent = array();
	foreach ($rules as $name => $css) {
		if (!_test_blocks_css_has_class($css, $name)) {
			$silent[] = $name;
		}
	}
	pg_assert_same(array(), $silent, '_SD_BLOCK_CSS entries whose rule does not use their key as a class');
}

// A pg-* class with no rule behind it does nothing on the published page.
// Classes written by shared helpers outside a builder's own body are not
// seen here. The theme switcher's classes are hooks its script looks up.
function test_designer_blocks_builder_classes_are_needed()
{
	$rules = _test_blocks_css_rules();
	$needs = _test_blocks_css_needs();
	$hooks = array('pg-active-icon', 'pg-check');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_theme_switcher.js');
	foreach ($hooks as $hook) {
		pg_assert_true(strpos($script, $hook) !== false, $hook . ' is still read by pg_theme_switcher.js');
	}
	$seen = 0;
	$uncovered = array();
	foreach (_test_blocks_palette_ctypes() as $ctype) {
		$css = '';
		foreach (isset($needs[$ctype]) ? $needs[$ctype] : array() as $name) {
			$css .= isset($rules[$name]) ? $rules[$name] . "\n" : '';
		}
		foreach (_test_blocks_builder_classes($ctype) as $class) {
			$seen++;
			if (!in_array($class, $hooks, true) && !_test_blocks_css_has_class($css, $class)) {
				$uncovered[] = $ctype . ' => ' . $class;
			}
		}
	}
	pg_assert_true($seen > 0, 'pg-* classes are found in the builders');
	pg_assert_same(array(), $uncovered, 'pg-* classes a builder writes without a needed rule defining them');
}

// True when the Bootstrap Icons class $token is an arrow, chevron or caret
// that points one way along the line (left or right, also diagonally,
// doubled, short, filled or boxed); the two-headed bi-arrow-left-right and
// the vertical ones are not.
function _test_blocks_is_one_way_icon($token)
{
	if (strpos($token, 'left-right') !== false) {
		return false;
	}
	return (bool) preg_match('/^bi-(?:[a-z0-9]+-)*(?:arrow|chevron|caret)(?:-[a-z0-9]+)*-(?:left|right)(?:-[a-z0-9]+)*$/', $token);
}

function test_designer_blocks_one_way_icon_matcher()
{
	foreach (array('bi-arrow-right', 'bi-arrow-left', 'bi-chevron-right', 'bi-chevron-left', 'bi-caret-right',
		'bi-caret-left-fill', 'bi-arrow-right-short', 'bi-chevron-double-right', 'bi-arrow-up-right',
		'bi-arrow-right-circle-fill', 'bi-box-arrow-right', 'bi-arrow-bar-left') as $icon) {
		pg_assert_true(_test_blocks_is_one_way_icon($icon), $icon . ' points one way');
	}
	foreach (array('bi-arrow-left-right', 'bi-arrow-up', 'bi-chevron-down', 'bi-caret-up-fill', 'bi-cloud-drizzle',
		'bi-arrow-repeat', 'arrow-right') as $icon) {
		pg_assert_true(!_test_blocks_is_one_way_icon($icon), $icon . ' does not point left or right');
	}
}

// The scroll hint of scroller-cards points both ways: the row scrolls both
// ways, and a one-way arrow points against the reading direction on an RTL
// page.
function test_designer_blocks_scroll_hint_is_direction_neutral()
{
	$strings = _test_blocks_builder_strings('scroller-cards');
	pg_assert_true(count($strings) > 0, 'the scroller-cards builder is found');
	pg_assert_true(in_array('bi-arrow-left-right', $strings, true), 'scroller-cards draws the two-headed arrow');
	$one_way = array();
	foreach ($strings as $str) {
		foreach (preg_split('/\s+/', trim($str)) as $token) {
			if (_test_blocks_is_one_way_icon($token)) {
				$one_way[] = $token;
			}
		}
	}
	pg_assert_same(array(), $one_way, 'one-way arrows in scroller-cards');
}

// The weather strip's city builder (_nbWsCity inside the news-weather-strip
// builder): the class tokens of the element it returns (null when it does
// not start by returning one _bkDiv) and how many times the strip calls it.
function _test_blocks_weather_city()
{
	$out = array('classes' => null, 'calls' => 0);
	$at = _test_blocks_builder_at('news-weather-strip');
	$block = $at === false ? null : _test_blocks_block($at);
	if (!$block) {
		return $out;
	}
	$js = _test_blocks_js();
	$def = strpos($js, 'var _nbWsCity = function', $block[0]);
	$fn = ($def === false || $def > $block[1]) ? null : _test_blocks_block($def);
	if (!$fn) {
		return $out;
	}
	$body = substr($js, $fn[0], $fn[1] - $fn[0] + 1);
	if (preg_match("/^\\{\\s*return _bkDiv\\('([^'\\\\]*)'/", $body, $m)) {
		$out['classes'] = preg_split('/\s+/', trim($m[1]));
	}
	$out['calls'] = preg_match_all('/(?<![\w$])_nbWsCity\(/', substr($js, $fn[1], $block[1] - $fn[1]));
	return $out;
}

// True when the class token $token overrides the look's corner radius or
// border width with a fixed one (or removes a side's border or rounding).
function _test_blocks_freezes_shape($token)
{
	return (bool) preg_match('/^(?:(?:rounded|border)(?:-(?:top|end|bottom|start))?-0|rounded-(?:pill|circle)|border-[1-5])$/', $token);
}

function test_designer_blocks_shape_override_matcher()
{
	foreach (array('rounded-0', 'border-0', 'border-top-0', 'rounded-end-0', 'rounded-pill', 'rounded-circle', 'border-2') as $token) {
		pg_assert_true(_test_blocks_freezes_shape($token), $token . ' fixes the shape');
	}
	foreach (array('rounded', 'border', 'border-bottom', 'rounded-top', 'border-primary', 'border-opacity-50', 'bg-body') as $token) {
		pg_assert_true(!_test_blocks_freezes_shape($token), $token . ' leaves the shape to the look');
	}
}

// The weather strip's cities are outlined chips on the body colour, so the
// look's radius and border width reach the band: the element each city is
// built as carries them, and nothing on it fixes either.
function test_designer_blocks_weather_cities_follow_the_look()
{
	$city = _test_blocks_weather_city();
	pg_assert_true(is_array($city['classes']), 'the weather strip builds each city as one _bkDiv in _nbWsCity');
	pg_assert_same(5, $city['calls'], 'cities the weather strip builds with _nbWsCity');
	$classes = is_array($city['classes']) ? $city['classes'] : array();
	pg_assert_same(array(), array_values(array_diff(array('bg-body', 'border', 'rounded', 'text-nowrap'), $classes)), 'chip classes a city lacks');
	pg_assert_same(array(), array_values(array_filter($classes, '_test_blocks_freezes_shape')), 'classes on a city that fix its shape');
}
