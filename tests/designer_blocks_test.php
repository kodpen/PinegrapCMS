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
 * Two blocks keep a checked shape: scroller-cards' previous / next buttons
 * (tied to their row, mirrored on RTL pages), and the weather strip's cities
 * are look-driven chips.
 *
 * The markup of every block and Components-tab item follows the design: no
 * builder writes a fixed colour class that ignores the colour palette and
 * dark mode; no builder carousel plays by itself; every component type a
 * builder writes exists; every block form can be submitted; every field a
 * builder writes is named by a label (or, outside a form, an aria-label); no
 * button, label, span or heading a builder writes holds a paragraph or a
 * block; no block skips a heading level; and image-carousel's controls stay
 * light over its photos in a dark design.
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
		$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
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

// array(start, end) of the body of createFromPalette(), or null. Cached like
// the source: walking the whole body takes about 0.06 s, and the per-block
// checks ask for it once per palette entry.
function _test_blocks_cfp_range()
{
	static $range = false;
	if ($range === false) {
		$at = strpos(_test_blocks_js(), 'function createFromPalette(');
		$range = $at === false ? null : _test_blocks_block($at);
	}
	return $range;
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

// String literals of createFromPalette() and of makeCarouselSlide(): the
// markup every block and Components-tab item is built from.
function _test_blocks_all_builder_strings()
{
	$out = array();
	$range = _test_blocks_cfp_range();
	$at = strpos(_test_blocks_js(), 'function makeCarouselSlide(');
	$slide = $at === false ? null : _test_blocks_block($at);
	foreach (array($range, $slide) as $block) {
		if ($block) {
			$scan = _test_blocks_scan(_test_blocks_js(), $block[0] + 1);
			$out = array_merge($out, $scan['strings']);
		}
	}
	return $out;
}

// Class names a builder spells across a '+': a literal ending in a partial
// class ('py-5 bg-') joined to the operand right after the '+', a literal
// ('dark') or a parenthesised expression whose literals each complete it
// ((cond ? 'dark' : 'light')), gives 'bg-dark'. A variable operand
// ('bg-' + tone) gives nothing. $src is JavaScript; strings and comments are
// skipped as _test_blocks_scan() skips them.
function _test_blocks_concat_classes($src)
{
	$items = array();
	$len = strlen($src);
	for ($i = 0; $i < $len; $i++) {
		$c = $src[$i];
		if ($c === "'" || $c === '"' || $c === '`') {
			$start = $i + 1;
			for ($i++; $i < $len && $src[$i] !== $c; $i++) {
				if ($src[$i] === '\\') {
					$i++;
				}
			}
			$items[] = array('s', substr($src, $start, $i - $start));
		} elseif ($c === '/' && $i + 1 < $len && $src[$i + 1] === '/') {
			$nl = strpos($src, "\n", $i);
			$i = $nl === false ? $len : $nl;
		} elseif ($c === '/' && $i + 1 < $len && $src[$i + 1] === '*') {
			$close = strpos($src, '*/', $i + 2);
			$i = $close === false ? $len : $close + 1;
		} elseif (!ctype_space($c)) {
			$items[] = array('c', $c);
		}
	}
	$out = array();
	$count = count($items);
	foreach ($items as $k => $item) {
		if ($item[0] !== 's' || !preg_match('/(?:^|\s)([a-z0-9-]*-)$/', $item[1], $head)
			|| !isset($items[$k + 2]) || $items[$k + 1] !== array('c', '+')) {
			continue;
		}
		$operand = array();
		if ($items[$k + 2][0] === 's') {
			$operand[] = $items[$k + 2][1];
		} elseif ($items[$k + 2] === array('c', '(')) {
			for ($j = $k + 3, $depth = 1; $j < $count && $depth > 0; $j++) {
				if ($items[$j] === array('c', '(')) {
					$depth++;
				} elseif ($items[$j] === array('c', ')')) {
					$depth--;
				} elseif ($items[$j][0] === 's') {
					$operand[] = $items[$j][1];
				}
			}
		}
		foreach ($operand as $str) {
			if (preg_match('/^[a-z0-9-]+/', $str, $tail)) {
				$out[] = $head[1] . $tail[0];
			}
		}
	}
	return $out;
}

// A block follows the design's colour palette and dark mode only through
// classes that read the theme variables. These ones are fixed colours:
// bg-dark stays dark in a light design, bg-white stays white in a dark one,
// text-white is unreadable on an amber primary, text-bg-light is a light
// patch with dark text in a dark design. A dark band is written
// data-bs-theme="dark" + bg-body text-body, a primary surface text-bg-primary
// (docs/memory/designer/designer-block-theme-colours.md).
function test_designer_blocks_use_theme_driven_colours()
{
	$fixed = array('bg-white', 'bg-light', 'bg-dark', 'bg-black', 'text-white', 'text-white-50', 'text-dark',
		'text-black', 'text-black-50', 'text-muted', 'btn-light', 'btn-dark', 'btn-outline-light', 'btn-outline-dark',
		'link-light', 'link-dark', 'navbar-dark', 'navbar-light', 'bg-primary', 'bg-secondary',
		'text-light', 'text-bg-light', 'text-bg-dark');
	$strings = _test_blocks_all_builder_strings();
	pg_assert_true(count($strings) > 1000, 'the string literals of the builders are read');

	$js = _test_blocks_js();
	$at = strpos($js, 'function makeCarouselSlide(');
	$bodies = array(
		'createFromPalette()' => _test_blocks_cfp_range(),
		'makeCarouselSlide()' => $at === false ? null : _test_blocks_block($at),
	);
	pg_assert_same(array('bg-dark', 'text-white', 'text-body'),
		_test_blocks_concat_classes("a: 'py-5 bg-' + 'dark', b: 'text-' + (c ? 'white' : \"body\"), d: 'bg-' + tone + '-subtle' // 'bg-' + 'light'"),
		"classes spelt across a '+' are read");
	$joined = array();
	foreach ($bodies as $block) {
		if ($block) {
			$joined = array_merge($joined, _test_blocks_concat_classes(substr($js, $block[0], $block[1] - $block[0])));
		}
	}

	$found = array();
	foreach (array_merge($strings, $joined) as $str) {
		foreach (preg_split('/\s+/', $str) as $token) {
			if (in_array($token, $fixed, true)) {
				$found[$token] = true;
			}
		}
	}
	pg_assert_same(array(), array_keys($found), 'fixed colour classes written by createFromPalette() or makeCarouselSlide()');

	$read = 0;
	foreach ($bodies as $name => $block) {
		if (!$block) {
			continue;
		}
		$read++;
		preg_match_all("/variant:\\s*'((?:outline-)?(?:light|dark))'/", substr($js, $block[0], $block[1] - $block[0]), $m);
		pg_assert_same(array(), array_values(array_unique($m[1])), 'light/dark button variants written by ' . $name);
	}
	pg_assert_same(2, $read, 'createFromPalette() and makeCarouselSlide() are read');
}

// Bootstrap's carousel keeps cycling under keyboard focus and reduced motion
// and has no pause button (WCAG 2.2.2): no block or Components-tab carousel
// plays by itself. Carousel Options > Autoplay switches it on per carousel.
function test_designer_blocks_carousels_do_not_autoplay()
{
	$strings = _test_blocks_all_builder_strings();
	pg_assert_true(count($strings) > 1000, 'the string literals of the builders are read');
	pg_assert_false(in_array('data-bs-ride', $strings, true), 'no builder writes data-bs-ride');
	pg_assert_false(in_array('data-bs-interval', $strings, true), 'no builder writes data-bs-interval');
}

// Carousel Options > Add slide copies the carousel's last slide. A
// makeCarouselSlide() surface is light, drawn for the dark controls of
// .carousel-dark: added to image-carousel (white controls over photos) it hid
// the controls, and in testimonial-carousel it was a hero among quotes.
function test_designer_blocks_added_slide_copies_the_last_slide()
{
	$js = _test_blocks_js();
	$at = strpos($js, "if (action === 'add-slide') {");
	$block = $at === false ? null : _test_blocks_block($at);
	pg_assert_true($block !== null, 'the Add slide branch is found');
	$body = $block ? substr($js, $block[0], $block[1] - $block[0]) : '';
	pg_assert_contains('copyCarouselSlide(', $body, 'Add slide builds the slide with copyCarouselSlide()');
	pg_assert_false(strpos($body, 'makeCarouselSlide(') !== false, 'Add slide does not draw a fresh light surface');

	// The copy must not repeat an element id on the page, and only the
	// first slide and indicator are the current ones.
	$at = strpos($js, 'function _sdCopyCarouselNode(');
	$block = $at === false ? null : _test_blocks_block($at);
	pg_assert_true($block !== null, '_sdCopyCarouselNode() is found');
	$copy = $block ? preg_replace('/\\s+/', ' ', substr($js, $block[0], $block[1] - $block[0])) : '';
	pg_assert_contains('delete p.id;', $copy, 'the copy drops every id prop');
	pg_assert_contains("return a.name !== 'id';", $copy, 'the copy drops every id attribute');
	pg_assert_contains("return c && c !== 'active';", $copy, 'the copied slide and indicator are not active');
	pg_assert_contains("return a.name !== 'aria-current';", $copy, 'the copied indicator carries no aria-current');
}

// Keys of the COMPONENTS registry: the component types the emitter can draw.
function _test_blocks_component_keys()
{
	$at = strpos(_test_blocks_js(), 'var COMPONENTS = {');
	$block = $at === false ? null : _test_blocks_block($at);
	if (!$block) {
		return array();
	}
	$body = substr(_test_blocks_js(), $block[0], $block[1] - $block[0]);
	preg_match_all("/^        '?([a-z0-9_-]+)'?\\s*:\\s*\\{/m", $body, $m);
	return array_values(array_unique($m[1]));
}

// A component node whose type has no COMPONENTS entry is emitted as nothing:
// navbar-search and footer-newsletter asked for 'form-search' and
// 'form-email', and their forms were saved without a field.
function test_designer_blocks_component_types_exist()
{
	$keys = _test_blocks_component_keys();
	pg_assert_true(in_array('btn', $keys, true), 'the COMPONENTS registry is read');
	$range = _test_blocks_cfp_range();
	$body = $range ? substr(_test_blocks_js(), $range[0], $range[1] - $range[0]) : '';
	preg_match_all("/createNode\\('component',\\s*\\{\\s*componentType:\\s*'([a-z0-9_-]+)'/", $body, $m);
	pg_assert_true(count($m[1]) > 10, 'the component nodes of createFromPalette() are read');
	pg_assert_same(array(), array_values(array_diff(array_unique($m[1]), $keys)), 'component types createFromPalette() writes without a COMPONENTS entry');
}

// Offset of the ')' closing the call whose '(' is at $open, skipping string
// literals and line comments; -1 when it is not found.
function _test_blocks_call_end($js, $open)
{
	$len = strlen($js);
	$depth = 0;
	for ($i = $open; $i < $len; $i++) {
		$c = $js[$i];
		if ($c === "'" || $c === '"' || $c === '`') {
			for ($i++; $i < $len && $js[$i] !== $c; $i++) {
				if ($js[$i] === '\\') {
					$i++;
				}
			}
			continue;
		}
		if ($c === '/' && $i + 1 < $len && $js[$i + 1] === '/') {
			$nl = strpos($js, "\n", $i);
			$i = $nl === false ? $len : $nl;
			continue;
		}
		if ($c === '(') {
			$depth++;
		} elseif ($c === ')') {
			$depth--;
			if ($depth === 0) {
				return $i;
			}
		}
	}
	return -1;
}

// Every createNode('semantic', { tag: … }) call inside createFromPalette()
// whose tag is one of $tags: a list of array('tag', 'start', 'end', 'src',
// 'line'), 'end' being the offset of the call's closing ')'.
function _test_blocks_semantic_calls($tags)
{
	$js = _test_blocks_js();
	$range = _test_blocks_cfp_range();
	$out = array();
	if (!$range) {
		return $out;
	}
	$re = "/createNode\\('semantic',\\s*\\{\\s*tag:\\s*'(" . implode('|', $tags) . ")'/";
	preg_match_all($re, substr($js, $range[0], $range[1] - $range[0]), $m, PREG_OFFSET_CAPTURE);
	foreach ($m[0] as $i => $hit) {
		$at = $range[0] + $hit[1];
		$end = _test_blocks_call_end($js, strpos($js, '(', $at));
		$out[] = array(
			'tag'   => $m[1][$i][0],
			'start' => $at,
			'end'   => $end,
			'src'   => $end < 0 ? '' : substr($js, $at, $end - $at + 1),
			'line'  => substr_count($js, "\n", 0, $at) + 1,
		);
	}
	return $out;
}

// The JavaScript expression a node gives as its id: an 'id' entry of its
// _attrs, or its id prop. Whitespace is removed so that the same expression
// written twice compares equal; '' when the node has no id.
function _test_blocks_node_id($src)
{
	if (preg_match("/name:\\s*'id',\\s*value:\\s*([^}]+?)\\s*\\}/", $src, $m)
		|| preg_match("/[{,]\\s*id:\\s*([^,}]+?)\\s*[,}]/", $src, $m)) {
		return preg_replace('/\\s+/', '', $m[1]);
	}
	return '';
}

// array(start, end) of the innermost builder test or function inside
// createFromPalette() whose block holds $offset: the scope a field and the
// label naming it are written in (a builder, or a field helper such as
// contact-form's _cfField).
function _test_blocks_scope_of($offset)
{
	$js = _test_blocks_js();
	$range = _test_blocks_cfp_range();
	$head = substr($js, $range[0], $offset - $range[0]);
	preg_match_all("/if \\(ctype === '[a-z0-9-]+'\\)[^{\\n]*\\{|function\\s*\\([^)]*\\)\\s*\\{/", $head, $m, PREG_OFFSET_CAPTURE);
	for ($i = count($m[0]) - 1; $i >= 0; $i--) {
		$block = _test_blocks_block($range[0] + $m[0][$i][1]);
		if ($block && $block[1] > $offset) {
			return $block;
		}
	}
	return $range;
}

// A Button component is an <a href="#"> unless it says otherwise, and a form
// whose only button is a link cannot be sent: every Button component inside a
// block form is a <button>, and every block form has a submit button. The
// submit is a Button component whose own btnType is 'submit' (an _attrs type
// beside btnType 'button' is printed second and ignored), or a semantic
// <button>/<input> whose _attrs type is submit.
function test_designer_blocks_forms_can_be_submitted()
{
	$forms = _test_blocks_semantic_calls(array('form'));
	$controls = _test_blocks_semantic_calls(array('button', 'input'));
	pg_assert_true(count($forms) > 10, 'the forms of createFromPalette() are read');
	$links = array();
	$no_submit = array();
	foreach ($forms as $form) {
		$src = $form['src'];
		$line = $form['line'];
		$submits = 0;
		preg_match_all("/componentType:\\s*'btn'[^}]*\\}/", $src, $btns);
		foreach ($btns[0] as $btn) {
			if (!preg_match("/btnElement:\\s*'(button|input)'/", $btn)) {
				$links[] = 'line ' . $line;
			} elseif (preg_match("/btnType:\\s*'submit'/", $btn)) {
				$submits++;
			}
		}
		foreach ($controls as $control) {
			if ($control['start'] > $form['start'] && $control['start'] < $form['end']
				&& preg_match("/^[^\\[]*_attrs:\\s*\\[[^\\]]*name:\\s*'type',\\s*value:\\s*'submit'/", $control['src'])) {
				$submits++;
			}
		}
		if ($submits === 0) {
			$no_submit[] = 'line ' . $line;
		}
	}
	pg_assert_same(array(), array_values(array_unique($links)), 'block forms holding a Button component drawn as a link');
	pg_assert_same(array(), $no_submit, 'block forms without a submit button');
}

// A block form has no action until the operator wires it, and a form with no
// method is sent with GET: the login blocks put the typed password into the
// address bar and the server logs. Every block form says method="post" in its
// own _attrs, except a search form (role="search"), whose query belongs in
// the address. Read on every 'form' a builder writes, through
// createNode('semantic', { tag: 'form', … }) or the family's _bkTag('form',
// …, attrs); a form written any other way fails the count.
function test_designer_blocks_forms_post_unless_search()
{
	$js = _test_blocks_js();
	$range = _test_blocks_cfp_range();
	pg_assert_true($range !== null, 'createFromPalette() is found');
	$body = substr($js, $range[0], $range[1] - $range[0]);
	$post = "name:\\s*'method',\\s*value:\\s*'post'";
	$read = 0;
	$wrong = array();

	preg_match_all("/createNode\\('semantic',\\s*\\{\\s*tag:\\s*'form'/", $body, $m, PREG_OFFSET_CAPTURE);
	foreach ($m[0] as $hit) {
		$open = strpos($js, '{', $range[0] + $hit[1]);
		$scan = _test_blocks_scan($js, $open + 1);
		$props = substr($js, $open, $scan['end'] - $open + 1);
		$line = substr_count($js, "\n", 0, $range[0] + $hit[1]) + 1;
		$search = (bool) preg_match("/name:\\s*'role',\\s*value:\\s*'search'/", $props);
		if ($search === (bool) preg_match('/' . $post . '/', $props)) {
			$wrong[] = 'line ' . $line . ($search ? ' (search form posts)' : ' (no method post)');
		}
		$read++;
	}

	preg_match_all("/_bkTag\\('form',/", $body, $m, PREG_OFFSET_CAPTURE);
	foreach ($m[0] as $hit) {
		$at = $range[0] + $hit[1];
		$end = _test_blocks_call_end($js, strpos($js, '(', $at));
		$call = $end < 0 ? '' : substr($js, $at, $end - $at + 1);
		if (!preg_match("/\\]\\s*,\\s*\\[\\s*\\{\\s*" . $post . "\\s*\\}\\s*\\]\\s*\\)$/", $call)) {
			$wrong[] = 'line ' . (substr_count($js, "\n", 0, $at) + 1) . ' (no method post)';
		}
		$read++;
	}

	pg_assert_true($read >= 15, 'the forms of createFromPalette() are read');
	pg_assert_same($read, preg_match_all("/'form'/", $body), 'every form of createFromPalette() is written one of the two read ways');
	pg_assert_same(array(), $wrong, 'block forms whose method does not match their kind');
}

// A <button>, <label>, <span> or heading holds phrasing content only: a
// paragraph or a <div> inside one is invalid HTML (image-carousel's control
// labels were a <p> in a visually-hidden <span> in the <button>). Checked on
// the nodes a builder writes with createNode('semantic', …) itself.
function test_designer_blocks_phrasing_holds_no_blocks()
{
	$calls = _test_blocks_semantic_calls(array('button', 'label', 'span', 'small', 'strong', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'));
	pg_assert_true(count($calls) > 50, 'the inline nodes of createFromPalette() are read');
	$flow = "/contentType:\\s*'paragraph'|createNode\\('(?:semantic',\\s*\\{\\s*tag:\\s*'(?:div|p|ul|ol|section|form|table|blockquote|figure|nav)'|container'|row'|col')|_bk(?:P|Div|Row|Col)\\(/";
	$found = array();
	foreach ($calls as $call) {
		// Children start at the first '[' after the props object; the
		// node's own _attrs list is skipped.
		$props_end = _test_blocks_props_end($call['src']);
		$kids = $props_end < 0 ? '' : substr($call['src'], $props_end);
		if (preg_match($flow, $kids)) {
			$found[] = $call['tag'] . ' at line ' . $call['line'];
		}
	}
	pg_assert_same(array(), $found, 'inline nodes of createFromPalette() holding a paragraph or a block');
}

// image-carousel's controls and indicators stay white over its photos and
// caption scrim in a dark design (Bootstrap paints them black there, about
// 1.2:1 on the scrim), unless Carousel Options > Dark asks for .carousel-dark.
function test_designer_blocks_photo_carousel_controls_stay_light()
{
	$rules = _test_blocks_css_rules();
	$needs = _test_blocks_css_needs();
	pg_assert_true(in_array('pg-carousel-photo', _test_blocks_builder_classes('image-carousel'), true), 'image-carousel writes pg-carousel-photo');
	pg_assert_true(isset($needs['image-carousel']) && in_array('pg-carousel-photo', $needs['image-carousel'], true), 'image-carousel needs the pg-carousel-photo rule');
	$rule = isset($rules['pg-carousel-photo']) ? $rules['pg-carousel-photo'] : '';
	pg_assert_contains('.pg-carousel-photo:not(.carousel-dark)', $rule, 'pg-carousel-photo yields to Carousel Options > Dark');
	pg_assert_contains('--bs-carousel-indicator-active-bg: var(--bs-white)', $rule, 'pg-carousel-photo keeps the indicators white');
	pg_assert_contains('--bs-carousel-control-icon-filter: none', $rule, 'pg-carousel-photo keeps the control icons white');
}

// The 'h1'…'h6' literals of $code that are not a cssClass value: a tag prop,
// a _bkH() tag, a tag handed to a helper or its default (tag || 'h4').
function _test_blocks_heading_literals($code)
{
	$levels = array();
	preg_match_all("/(cssClass:\\s*)?'h([1-6])'/", $code, $m);
	foreach ($m[2] as $i => $level) {
		if ($m[1][$i] === '') {
			$levels[] = (int) $level;
		}
	}
	return $levels;
}

// Helpers declared inside createFromPalette() as `var _name = function`:
// name => list of array('at', 'levels', 'scope'), 'scope' being the range of
// the innermost builder test holding the declaration (null for a helper
// shared by a whole family, such as _bkIntro() or _bkMenuItem()).
function _test_blocks_helpers()
{
	static $helpers = null;
	if ($helpers !== null) {
		return $helpers;
	}
	$helpers = array();
	$js = _test_blocks_js();
	$range = _test_blocks_cfp_range();
	if (!$range) {
		return $helpers;
	}
	$cfp = substr($js, $range[0], $range[1] - $range[0]);
	$builders = array();
	preg_match_all("/if \\(ctype === '[a-z0-9-]+'\\)[^{\\n]*\\{/", $cfp, $tests, PREG_OFFSET_CAPTURE);
	foreach ($tests[0] as $test) {
		$block = _test_blocks_block($range[0] + $test[1]);
		if ($block) {
			$builders[] = $block;
		}
	}
	preg_match_all('/var (_[A-Za-z0-9]+) = function\\s*\\(/', $cfp, $defs, PREG_OFFSET_CAPTURE);
	foreach ($defs[0] as $i => $def) {
		$at = $range[0] + $def[1];
		$body = _test_blocks_block($at);
		if (!$body) {
			continue;
		}
		$scope = null;
		foreach ($builders as $builder) {
			if ($builder[0] < $at && $builder[1] > $at && ($scope === null || $builder[0] > $scope[0])) {
				$scope = $builder;
			}
		}
		$helpers[$defs[1][$i][0]][] = array(
			'at'     => $at,
			'levels' => _test_blocks_heading_literals(substr($js, $body[0], $body[1] - $body[0])),
			'scope'  => $scope,
		);
	}
	return $helpers;
}

// Heading levels a builder writes: the heading literals of its body and of
// the helpers it calls that are declared outside it, in a scope holding it
// (_bkIntro()'s h2, _bkMenuItem()'s default h4). Sorted, unique.
function _test_blocks_heading_levels($block)
{
	$src = substr(_test_blocks_js(), $block[0], $block[1] - $block[0]);
	$levels = _test_blocks_heading_literals($src);
	$helpers = _test_blocks_helpers();
	preg_match_all('/\\b(_[A-Za-z0-9]+)\\(/', $src, $calls);
	foreach (array_unique($calls[1]) as $name) {
		foreach (isset($helpers[$name]) ? $helpers[$name] : array() as $helper) {
			$outside = $helper['at'] < $block[0] || $helper['at'] > $block[1];
			$visible = $helper['scope'] === null || ($helper['scope'][0] <= $block[0] && $helper['scope'][1] >= $block[1]);
			if ($outside && $visible) {
				$levels = array_merge($levels, $helper['levels']);
			}
		}
	}
	$levels = array_values(array_unique($levels));
	sort($levels);
	return $levels;
}

// A heading level a block jumps over is read aloud as a missing section
// (WCAG 1.3.1) and the editor's audit reports it (heading_skip): the item
// titles of a block sit one level below its title and take their size from
// an h-class (tag 'h3', cssClass 'h5 …'), never from a smaller tag.
function test_designer_blocks_heading_levels_are_not_skipped()
{
	pg_assert_same(array(2, 3, 4), _test_blocks_heading_levels(_test_blocks_block(_test_blocks_builder_at('menu-tabs'))), 'menu-tabs: the h2 of _bkIntro(), the h3 it hands to _bkMenuItem() and that helper\'s default h4 are read');
	$checked = 0;
	$skips = array();
	foreach (array_unique(_test_blocks_palette_ctypes()) as $ctype) {
		$at = _test_blocks_builder_at($ctype);
		$block = $at === false ? null : _test_blocks_block($at);
		if (!$block) {
			continue;
		}
		$levels = _test_blocks_heading_levels($block);
		$checked += count($levels) > 1 ? 1 : 0;
		for ($i = 1, $n = count($levels); $i < $n; $i++) {
			if ($levels[$i] > $levels[$i - 1] + 1) {
				$skips[] = $ctype . ': h' . implode(' h', $levels);
				break;
			}
		}
	}
	pg_assert_true($checked > 40, 'the heading levels of the builders are read');
	pg_assert_same(array(), $skips, 'blocks whose heading levels skip one');
}

// Offset in a createNode(…) call's source just after its props object (the
// '}' closing the first '{'), or -1.
function _test_blocks_props_end($src)
{
	$open = strpos($src, '{');
	if ($open === false) {
		return -1;
	}
	$depth = 0;
	$len = strlen($src);
	for ($i = $open; $i < $len; $i++) {
		$c = $src[$i];
		if ($c === "'" || $c === '"' || $c === '`') {
			for ($i++; $i < $len && $src[$i] !== $c; $i++) {
				if ($src[$i] === '\\') {
					$i++;
				}
			}
		} elseif ($c === '{') {
			$depth++;
		} elseif ($c === '}' && --$depth === 0) {
			return $i + 1;
		}
	}
	return -1;
}

// A field without a name is read aloud as "edit text" (WCAG 1.3.1, 4.1.2) and
// a label whose 'for' names no field labels nothing. Every input, select and
// textarea a builder writes is named by a label whose 'for' is the field's id
// expression, written in the same builder or field helper; outside a form an
// aria-label also names it (the Components-tab input group, the theme switch).
// Every label 'for' names a field of its scope. Buttons, hidden and submit
// inputs carry their own name or none.
function test_designer_blocks_fields_have_labels()
{
	$fields = _test_blocks_semantic_calls(array('input', 'select', 'textarea'));
	$labels = _test_blocks_semantic_calls(array('label'));
	$forms = _test_blocks_semantic_calls(array('form'));
	pg_assert_true(count($fields) > 20, 'the fields of createFromPalette() are read');
	pg_assert_true(count($labels) > 20, 'the labels of createFromPalette() are read');
	$label_for = array();
	foreach ($labels as $i => $label) {
		$label_for[$i] = preg_match("/name:\\s*'for',\\s*value:\\s*([^}]+?)\\s*\\}/", $label['src'], $m) ? preg_replace('/\\s+/', '', $m[1]) : '';
	}
	$unnamed = array();
	$field_ids = array();
	foreach ($fields as $field) {
		if (preg_match("/name:\\s*'type',\\s*value:\\s*'(hidden|submit|button|reset|image)'/", $field['src'])) {
			continue;
		}
		$id = _test_blocks_node_id($field['src']);
		$scope = _test_blocks_scope_of($field['start']);
		if ($id !== '') {
			$field_ids[] = array($scope, $id);
		}
		$labelled = false;
		foreach ($labels as $i => $label) {
			if ($id !== '' && $label_for[$i] === $id && $label['start'] > $scope[0] && $label['start'] < $scope[1]) {
				$labelled = true;
				break;
			}
		}
		$in_form = false;
		foreach ($forms as $form) {
			if ($field['start'] > $form['start'] && $field['start'] < $form['end']) {
				$in_form = true;
				break;
			}
		}
		if (!$labelled && ($in_form || !preg_match("/name:\\s*'aria-label(?:ledby)?',\\s*value:\\s*(?!'')\\S/", $field['src']))) {
			$unnamed[] = 'line ' . $field['line'] . ($in_form ? ' (in a form)' : '');
		}
	}
	pg_assert_same(array(), $unnamed, 'fields of createFromPalette() without a label naming them');

	$dangling = array();
	foreach ($labels as $i => $label) {
		$found = false;
		foreach ($field_ids as $pair) {
			if ($pair[1] === $label_for[$i] && $label['start'] > $pair[0][0] && $label['start'] < $pair[0][1]) {
				$found = true;
				break;
			}
		}
		if (!$found) {
			$dangling[] = 'line ' . $label['line'] . ($label_for[$i] === '' ? ' (no for)' : ' (for ' . $label_for[$i] . ')');
		}
	}
	pg_assert_same(array(), $dangling, 'labels of createFromPalette() whose for names no field');

	// A label without text names its field with nothing: some node of the
	// label (the label itself or a span inside it, written as a text prop or
	// with the _bkH(tag, text) helper) carries a non-empty text; '' and
	// _sdT('') are empty.
	$empty = array();
	$none = "(?!''|\"\"|_sdT\\(\\s*(?:''|\"\")\\s*\\))";
	foreach ($labels as $label) {
		if (!preg_match("/\\btext:\\s*" . $none . "[^\\s,}]|_bkH\\('[a-z0-9]+',\\s*" . $none . "[^\\s,)]/", $label['src'])) {
			$empty[] = 'line ' . $label['line'];
		}
	}
	pg_assert_same(array(), $empty, 'labels of createFromPalette() without text');
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

// scroller-cards' previous / next buttons: real buttons with no script of
// their own, named for screen readers and tied to the row, which carries the
// marker that brings assets/js/pg_scroller.js (pg_scroller_inject(), the
// preview's _SD_PAGE_SCRIPTS, the canvas's _sdScrollers()).
function test_designer_blocks_scroller_buttons()
{
	$strings = _test_blocks_builder_strings('scroller-cards');
	pg_assert_true(count($strings) > 0, 'the scroller-cards builder is found');
	foreach (array('button', 'type', 'data-pg-scroll', 'prev', 'next', 'aria-controls', 'aria-label', 'data-pg-scroller', 'aria-hidden') as $str) {
		pg_assert_true(in_array($str, $strings, true), 'scroller-cards writes ' . $str);
	}
	pg_assert_true(in_array('Previous', $strings, true) && in_array('Next', $strings, true), 'the buttons are named Previous and Next');
	pg_assert_true(!in_array('Scroll sideways', $strings, true), 'the plain-text scroll hint is gone');
	pg_assert_true(!in_array('bi-arrow-left-right', $strings, true), 'the hint\'s two-headed arrow is gone');

	// The row's id is the buttons' aria-controls.
	$at = _test_blocks_builder_at('scroller-cards');
	$block = $at === false ? null : _test_blocks_block($at);
	$body = $block ? substr(_test_blocks_js(), $block[0], $block[1] - $block[0]) : '';
	pg_assert_true((bool) preg_match("/name:\\s*'aria-controls',\\s*value:\\s*_crScRowId\\b/", $body), 'aria-controls names the row id');
	pg_assert_true(strpos($body, '_crScRow.props.id = _crScRowId;') !== false, 'the row carries that id');

	// The script reads what the builder writes; the canvas and the preview load it.
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_scroller.js');
	foreach (array('[data-pg-scroller]', '[data-pg-scroll]', "'prev'", 'aria-controls', 'prefers-reduced-motion', 'pgScrollerBind', 'pgScrollerEditing') as $hook) {
		pg_assert_true(strpos($script, $hook) !== false, 'pg_scroller.js reads ' . $hook);
	}
	$js = _test_blocks_js();
	pg_assert_true(strpos($js, "_sdThemeSwitchers();\n        _sdScrollers();") !== false, 'renderCanvas() binds the scrollers after every render');
	pg_assert_true(strpos($js, "file: 'assets/js/pg_scroller.js'") !== false, 'the preview loads pg_scroller.js');
}

// The chevrons of scroller-cards point the reading direction: every
// one-way icon of the block is mirrored on an RTL page (pg-flip-rtl), where
// the previous / next buttons swap sides.
function test_designer_blocks_scroller_chevrons_follow_direction()
{
	$strings = _test_blocks_builder_strings('scroller-cards');
	$one_way = array();
	foreach ($strings as $str) {
		foreach (preg_split('/\s+/', trim($str)) as $token) {
			if (_test_blocks_is_one_way_icon($token)) {
				$one_way[] = $token;
			}
		}
	}
	pg_assert_same(array('bi-chevron-left', 'bi-chevron-right'), $one_way, 'one-way icons of scroller-cards');
	pg_assert_true(in_array('pg-flip-rtl', $strings, true), 'scroller-cards mirrors its chevrons on RTL pages');
	$rules = _test_blocks_css_rules();
	$needs = _test_blocks_css_needs();
	pg_assert_true(isset($needs['scroller-cards']) && in_array('pg-flip-rtl', $needs['scroller-cards'], true), 'scroller-cards needs pg-flip-rtl');
	$rule = isset($rules['pg-flip-rtl']) ? $rules['pg-flip-rtl'] : '';
	pg_assert_contains('[dir="rtl"] .pg-flip-rtl', $rule, 'pg-flip-rtl applies on RTL pages only');
	pg_assert_contains('scaleX(-1)', $rule, 'pg-flip-rtl mirrors');
	pg_assert_contains('inline-block', $rule, 'pg-flip-rtl gives the inline icon a box a transform applies to');
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
