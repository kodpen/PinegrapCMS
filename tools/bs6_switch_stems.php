<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Classes the panel builds in code from a Bootstrap 5 stem, rewritten by
 * the Bootstrap 6 switch (tools/bs6_switch.php) where the result is known:
 *
 *   `col-lg-' . $n . '`            the prefix moves: `lg:col-' . $n . '`
 *   `alert alert-' . $tone . '`    an alert takes the theme class
 *   `badge text-bg-' . $tone . '`  a badge takes the theme class
 *   `btn btn-' . $variant . '`     a button: `btn-solid theme-…`, when every
 *                                  value the code can give is a colour
 *   `text-' . $tone . '`           `fg-…`, when every value is a colour
 *   `bg-' . $tone . '-subtle`      `bg-subtle-…` (and the -emphasis and
 *                                  border-…-subtle pairs), likewise
 *
 * "Every value" is read from the code itself: the string literals of the
 * expression (`($ok ? 'success' : 'danger')`), or of every assignment to the
 * variable in the file (`$tone = 'warning';`, `$tone = $x ? 'a' : 'b';`).
 * A class whose values cannot be read stays on check_bs6.php's list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

require_once(__DIR__ . '/bs6_rules.php');

// An expression that prints a class part between two string joins: a
// variable (with array keys), a bare JS name, or a bracketed expression,
// h() of any of them. The bracket group is defined at the end of the
// pattern (BS6_STEM_GROUP), so it shifts no group number.
const BS6_STEM_EXPR = '(?:h(?&pgparen)|(?&pgparen)|\$\w+(?:\[[^\]\[]+\])*|[A-Za-z_]\w*)';
const BS6_STEM_GROUP = '(?(DEFINE)(?<pgparen>\((?:[^()\'"]++|\'[^\']*\'|"[^"]*"|(?&pgparen))*\)))';

// Panel files the codemod converts whose code joins a Bootstrap 5 stem with
// a value, sorted.
function bs6_switch_stem_files($root)
{
	$paths = array();

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if (!bs6_codemod_converts($path) || $path === 'assets/js/style_designer.js' || !preg_match('/\.(php|js)$/', $path)) {
			continue;
		}

		if (preg_match('/(?:col-(?:sm|md|lg|xl|xxl)|alert|text-bg|btn|text|bg|border)-[\'"]\s*[.+]/', (string) file_get_contents(rtrim($root, '/') . '/' . $path))) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return $paths;
}

// The string values an expression can print, or null when it holds anything
// else than literals, a variable whose every assignment in $source is
// literals, h() and the ternary's own conditions.
function bs6_stem_values($source, $expr)
{
	$expr = trim($expr);

	if (preg_match('/^(?:h\(\s*)?(\$\w+)\s*\)?$/', $expr, $m) || preg_match('/^([A-Za-z_]\w*)$/', $expr, $m)) {
		$var = $m[1];
		$values = array();
		$name = preg_quote($var, '/');

		if (!preg_match_all('/(?<![\w$.])(?:var\s+|let\s+|const\s+)?' . $name . '\s*=(?!=)([^;]*);/', $source, $sets)) {
			return null;
		}

		foreach ($sets[1] as $value) {
			$found = bs6_stem_literals($value);

			if ($found === null) {
				return null;
			}

			$values = array_merge($values, $found);
		}

		return array_values(array_unique($values));
	}

	if ($expr !== '' && $expr[0] === '(' || strpos($expr, 'h(') === 0) {
		return bs6_stem_literals($expr);
	}

	return null;
}

// The values of a literal or a ternary of literals; the conditions (left of
// `?`) may hold anything. Null when a branch is not a literal.
function bs6_stem_literals($code)
{
	$code = trim($code);
	$code = preg_replace('/^h\(\s*(.*)\s*\)$/s', '$1', $code);

	while ($code !== '' && $code[0] === '(' && bs6_stem_close_of($code) === strlen($code) - 1) {
		$code = trim(substr($code, 1, -1));
	}

	if (preg_match('/^([\'"])([a-z][a-z0-9-]*)\1$/', $code, $m)) {
		return array($m[2]);
	}

	// cond ? a : b, where a and b are literals or nested ternaries.
	$depth = 0;
	$question = null;
	$length = strlen($code);

	for ($i = 0; $i < $length; $i++) {
		$c = $code[$i];

		if ($c === '\'' || $c === '"') {
			$end = strpos($code, $c, $i + 1);
			if ($end === false) {
				return null;
			}
			$i = $end;
		} elseif ($c === '(') {
			$depth++;
		} elseif ($c === ')') {
			$depth--;
		} elseif ($c === '?' && $depth === 0 && $question === null) {
			$question = $i;
		} elseif ($c === ':' && $depth === 0 && $question !== null) {
			$a = bs6_stem_literals(substr($code, $question + 1, $i - $question - 1));
			$b = bs6_stem_literals(substr($code, $i + 1));

			return ($a === null || $b === null) ? null : array_values(array_unique(array_merge($a, $b)));
		}
	}

	return null;
}

// Offset of the bracket that closes the one at offset 0, quotes skipped.
function bs6_stem_close_of($code)
{
	$depth = 0;
	$length = strlen($code);

	for ($i = 0; $i < $length; $i++) {
		$c = $code[$i];

		if ($c === '\'' || $c === '"') {
			$end = strpos($code, $c, $i + 1);
			if ($end === false) {
				return null;
			}
			$i = $end;
		} elseif ($c === '(') {
			$depth++;
		} elseif ($c === ')' && --$depth === 0) {
			return $i;
		}
	}

	return null;
}

// True when every value is one of the theme colours.
function bs6_stem_all_colours($values)
{
	return is_array($values) && $values && !array_diff($values, bs6_theme_colours());
}

/**
 * The fix: each stem joined with a value inside a class list (PHP `'stem-'
 * . $x . '` or JS `"stem-" + x + "`) is rewritten when its result is known
 * (file header). Others are left as they are.
 */
function bs6_switch_stems($source)
{
	$pattern = '/(?<=[\s"\'])(col-(?:sm|md|lg|xl|xxl)|alert|text-bg|btn|text|bg|border)-([\'"]\s*[.+]\s*(' . BS6_STEM_EXPR . ')\s*[.+]\s*[\'"])(-subtle|-emphasis)?' . BS6_STEM_GROUP . '/';
	$edits = array();

	if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		return array('source' => $source, 'status' => 'done');
	}

	$site_output = bs6_site_output_ranges($source);

	foreach ($matches as $m) {
		if ($site_output && bs6_in_ranges($m[0][1], $site_output)) {
			continue;
		}

		$stem = $m[1][0];
		$join = $m[2][0];
		$suffix = isset($m[4]) && $m[4][1] >= 0 ? $m[4][0] : '';
		$start = $m[0][1];
		$end = $start + strlen($m[0][0]);
		// The class list the stem sits in: back to the quote that opens it.
		$list = substr($source, max(0, $start - 120), min(120, $start));
		$list = (string) substr($list, (int) max(strrpos($list, '"'), strrpos($list, '\'')) + 1);
		$new = null;

		if (preg_match('/^col-(sm|md|lg|xl|xxl)$/', $stem, $bp) && $suffix === '') {
			$new = ($bp[1] === 'xxl' ? '2xl' : $bp[1]) . ':col-' . $join;
		} else {
			$values = bs6_stem_values($source, $m[3][0]);
			$colours = bs6_stem_all_colours($values);

			if ($suffix === '' && ($colours || $values === null) && (($stem === 'alert' && preg_match('/(^|\s)alert(\s|$)/', $list)) || ($stem === 'text-bg' && preg_match('/(^|\s)badge(\s|$)/', $list)))) {
				// An alert's or a badge's tone is a theme colour wherever the
				// panel builds one, also when the code cannot show it.
				$new = 'theme-' . $join;
			} elseif ($colours && $suffix === '' && $stem === 'btn') {
				$new = 'btn-solid theme-' . $join;
			} elseif ($colours && $stem === 'text' && $suffix === '') {
				$new = 'fg-' . $join;
			} elseif ($colours && $stem === 'text' && $suffix === '-emphasis') {
				$new = 'fg-emphasis-' . $join;
			} elseif ($colours && ($stem === 'bg' || $stem === 'border') && $suffix === '-subtle') {
				$new = $stem . '-subtle-' . $join;
			}
		}

		if ($new !== null) {
			$edits[] = array($start, $end, $new);
		}
	}

	if (!$edits) {
		return array('source' => $source, 'status' => 'done');
	}

	foreach (array_reverse($edits) as $edit) {
		$source = substr($source, 0, $edit[0]) . $edit[2] . substr($source, $edit[1]);
	}

	return array('source' => $source, 'status' => 'applied');
}
