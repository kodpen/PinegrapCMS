<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Source test for the panel stylesheets: no selector puts a :has() on an
 * element and then styles that element's children or descendants as a whole
 * (`X:has(…) > *`, `X:has(…) > :not(…)`, `X:has(…) *`). Chromium restyles
 * the whole document on every element added or removed when such a rule
 * exists, and the visual editor redraws its panels on every click. The
 * Bootstrap 6 dist is read too: its one such rule is rewritten locally
 * (assets/lib/bootstrap-6/document.txt), and an update that brings it back
 * fails here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Splits $text at $char where it is not inside brackets or quotes.
function css_has_restyle_split($text, $char)
{
	$parts = array();
	$depth = 0;
	$quote = '';
	$start = 0;
	$length = strlen($text);
	for ($i = 0; $i < $length; $i++) {
		$c = $text[$i];
		if ($quote !== '') {
			if ($c === $quote) {
				$quote = '';
			}
			continue;
		}
		if ($c === '"' || $c === "'") {
			$quote = $c;
		} elseif ($c === '(' || $c === '[') {
			$depth++;
		} elseif ($c === ')' || $c === ']') {
			$depth--;
		} elseif ($c === $char && $depth === 0) {
			$parts[] = substr($text, $start, $i - $start);
			$start = $i + 1;
		}
	}
	$parts[] = substr($text, $start);

	return $parts;
}

// The compounds and combinators of one complex selector, outside brackets:
// array(array('compound', '>'), …), the last combinator ''.
function css_has_restyle_compounds($selector)
{
	$out = array();
	$depth = 0;
	$quote = '';
	$current = '';
	$selector = trim(preg_replace('/\s+/', ' ', $selector));
	$length = strlen($selector);
	for ($i = 0; $i < $length; $i++) {
		$c = $selector[$i];
		if ($quote !== '') {
			$current .= $c;
			if ($c === $quote) {
				$quote = '';
			}
			continue;
		}
		if ($c === '"' || $c === "'") {
			$quote = $c;
		} elseif ($c === '(' || $c === '[') {
			$depth++;
		} elseif ($c === ')' || $c === ']') {
			$depth--;
		}
		if ($depth === 0 && ($c === '>' || $c === '+' || $c === '~' || $c === ' ')) {
			$combinator = $c === ' ' ? ' ' : $c;
			// A space around an explicit combinator belongs to it.
			while ($i + 1 < $length && $selector[$i + 1] === ' ') {
				$i++;
			}
			if ($c === ' ' && $i + 1 < $length && strpos('>+~', $selector[$i + 1]) !== false) {
				continue;
			}
			if (trim($current) === '') {
				// A combinator after a space: the previous entry takes it.
				if ($out) {
					$out[count($out) - 1][1] = $combinator;
				}
				continue;
			}
			$out[] = array(trim($current), $combinator);
			$current = '';
			continue;
		}
		$current .= $c;
	}
	if (trim($current) !== '') {
		$out[] = array(trim($current), '');
	}

	return $out;
}

// True when the selector styles every child or descendant of a :has() element.
function css_has_restyle_is_slow($selector)
{
	$compounds = css_has_restyle_compounds($selector);
	$last = count($compounds) - 1;
	if ($last < 1) {
		return false;
	}
	$subject = $compounds[$last][0];
	$open = $subject === '*' || $subject === '[class]' || strpos($subject, ':not(') === 0 || strpos($subject, ':where(*)') === 0;
	if (!$open) {
		return false;
	}
	// Walk left over child and descendant combinators only.
	for ($i = $last - 1; $i >= 0; $i--) {
		$combinator = $compounds[$i][1];
		if ($combinator !== '>' && $combinator !== ' ') {
			return false;
		}
		if (strpos($compounds[$i][0], ':has(') !== false) {
			return true;
		}
	}

	return false;
}

// Every selector of the stylesheet, comments and declarations left out.
function css_has_restyle_selectors($css)
{
	$css = preg_replace('~/\*.*?\*/~s', '', $css);
	$selectors = array();
	if (!preg_match_all('/([^{};]+)\{/', $css, $m)) {
		return $selectors;
	}
	foreach ($m[1] as $prelude) {
		$prelude = trim($prelude);
		if ($prelude === '' || $prelude[0] === '@') {
			continue;
		}
		foreach (css_has_restyle_split($prelude, ',') as $one) {
			$one = trim($one);
			if ($one !== '') {
				$selectors[] = $one;
			}
		}
	}

	return $selectors;
}

function test_css_has_restyle_detector()
{
	pg_assert_true(css_has_restyle_is_slow('.x:has(> .y) > *'), 'a :has() then every child');
	pg_assert_true(css_has_restyle_is_slow('.form-field:has(> .check, > .radio) > :not(.check, .radio)'), 'a :has() then every child but some');
	pg_assert_true(css_has_restyle_is_slow('.a .row > [class]:has(> .f > :is(input, .s)) > .f > *'), 'a :has() then every grandchild');
	pg_assert_true(css_has_restyle_is_slow('.x:has(.y) *'), 'a :has() then every descendant');
	pg_assert_true(!css_has_restyle_is_slow('.x:has(> .y) > .z'), 'a :has() then a named child');
	pg_assert_true(!css_has_restyle_is_slow('.x:has(> .y) ~ .f > *'), 'a sibling after the :has()');
	pg_assert_true(!css_has_restyle_is_slow('.r > [class] > :is(.a, .a ~ *, :has(~ .a))'), 'the :has() on the subject');
	pg_assert_true(!css_has_restyle_is_slow('.x > *'), 'no :has()');
}

function test_panel_stylesheets_have_no_has_then_every_child()
{
	$dir = dirname(__DIR__) . '/pinegrap/assets/css/';
	$files = array('backend.src.css', 'style_designer.css');
	// Known and accepted: inside the Workspace's phone media query, where the
	// page is small, and no form of it without the :has() on the strip.
	$known = array('.ws-strip:has(.ws-catchup) .ws-pin-bar > :not(.ws-pin-text)');
	foreach ($files as $file) {
		$css = (string) file_get_contents($dir . $file);
		pg_assert_true($css !== '', $file . ' is read');
		$slow = array();
		foreach (css_has_restyle_selectors($css) as $selector) {
			if (css_has_restyle_is_slow($selector) && !in_array($selector, $known, true)) {
				$slow[] = $selector;
			}
		}
		pg_assert_same(array(), $slow, $file . ' styles no child of a :has() element as a whole');
	}
}

function test_bootstrap_6_dist_has_no_has_then_every_child()
{
	$dir = dirname(__DIR__) . '/pinegrap/assets/lib/bootstrap-6/css/';
	foreach (array('bootstrap.css', 'bootstrap.min.css') as $file) {
		$css = (string) file_get_contents($dir . $file);
		pg_assert_true($css !== '', $file . ' is read');
		$slow = array();
		foreach (css_has_restyle_selectors($css) as $selector) {
			if (css_has_restyle_is_slow($selector)) {
				$slow[] = $selector;
			}
		}
		pg_assert_same(array(), $slow, $file . ' styles no child of a :has() element as a whole');
	}
	$css = (string) file_get_contents($dir . 'bootstrap.css');
	pg_assert_contains('.form-field > :not(.check, .radio, .switch):is(:has(~ :is(.check, .radio, .switch)), :is(.check, .radio, .switch) ~ *) {', $css, 'the local rewrite of the check row rule is there');
}

function test_settings_card_catch_all_keeps_its_rows()
{
	$css = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/assets/css/backend.src.css');
	$catch = '.pg-set-card .row > [class] > .form-field:has(> :is(input[type="checkbox"], .switch)) ~ .form-field > * {';
	$at = strpos($css, $catch);
	pg_assert_true($at !== false, 'the full-row rule for an unexpected child is there');
	$block = substr($css, $at, strpos($css, '}', $at) - $at);
	pg_assert_true(strpos($block, 'grid-column: 1 / -1;') !== false, 'an unexpected child takes a full row');
	pg_assert_true(strpos($css, '.pg-set-card .row > [class] > :is(.form-label, .form-label ~ *, :has(~ .form-label))') !== false, 'a field row child is found by its siblings');
	pg_assert_true(strpos($css, '.pg-set-card .row > [class] > .form-field > :is(:is(input[type="checkbox"], .switch), :is(input[type="checkbox"], .switch) ~ *, :has(~ :is(input[type="checkbox"], .switch))):is(.form-field > *)') !== false, 'a switch row child is found by its siblings');
}
