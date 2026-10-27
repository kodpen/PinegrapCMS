<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bootstrap 6 switch: liveform fields of panel files. liveform::output_field()
 * is shared with site pages and gives a select, checkbox or radio that names
 * no Bootstrap class v5's default (form-select, form-check-input), which v6
 * does not define. The switch writes the v6 class into each such call of a
 * panel file instead: `form-control` for a select, `check` or `radio` for the
 * input, and `no-bs` (liveform's opt-out) for an input inside a
 * `<div class="switch">` box, which v6 styles with no class. A call whose
 * type or class is not a plain string literal is left for a hand and
 * reported by tools/check_bs6.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

require_once(__DIR__ . '/bs6_rules.php');

// Class tokens that mark a field as styled: liveform's own markers, after
// which it adds no default class, and v6's check and radio (liveform still
// adds form-check-input to those, which v6 does not define).
function bs6_field_class_markers()
{
	return array('form-control', 'form-select', 'form-check-input', 'btn', 'no-bs', 'check', 'radio');
}

/**
 * The liveform field calls of a PHP source whose type is a select, checkbox
 * or radio, as arrays of: offset (of `output_field`), type, open (offset
 * just past the array's opening bracket), class (null when the call has no
 * class key, else array(offset, length, value) of its string literal; value
 * null when it is not one plain literal, with its literal pieces fourth) and
 * literal (false when the type is not a plain literal).
 */
function bs6_field_calls($source)
{
	$tokens = token_get_all($source);
	$offsets = array();
	$offset = 0;

	foreach ($tokens as $i => $token) {
		$offsets[$i] = $offset;
		$offset += strlen(is_array($token) ? $token[1] : $token);
	}

	$text = function ($i) use ($tokens) {
		return is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
	};
	$next = function ($i) use ($tokens) {
		for ($i++; isset($tokens[$i]); $i++) {
			if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) {
				return $i;
			}
		}
		return null;
	};
	$calls = array();
	$count = count($tokens);

	for ($i = 0; $i < $count; $i++) {
		if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'output_field') {
			continue;
		}

		$paren = $next($i);
		$open = ($paren !== null && $text($paren) === '(') ? $next($paren) : null;
		if ($open === null) {
			continue;
		}

		if (is_array($tokens[$open]) && $tokens[$open][0] === T_ARRAY) {
			$open = $next($open);
			if ($open === null || $text($open) !== '(') {
				continue;
			}
		} elseif ($text($open) !== '[') {
			continue;
		}

		// The top-level elements of the array: key token index, value tokens.
		$depth = 0;
		$elements = array();
		$key = null;
		$value = array();
		$arrow = false;

		for ($j = $open + 1; $j < $count; $j++) {
			$t = $text($j);
			if ($depth === 0 && ($t === ')' || $t === ']' || $t === ',')) {
				if ($key !== null) {
					$elements[] = array($key, $value);
				}
				$key = null;
				$value = array();
				$arrow = false;
				if ($t !== ',') {
					break;
				}
				continue;
			}
			if ($t === '(' || $t === '[' || $t === '{' || (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true))) {
				$depth++;
			} elseif ($t === ')' || $t === ']' || $t === '}') {
				$depth--;
			}
			if ($depth === 0 && is_array($tokens[$j]) && $tokens[$j][0] === T_DOUBLE_ARROW) {
				$arrow = true;
				continue;
			}
			if (is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT), true)) {
				continue;
			}
			if (!$arrow && $key === null && $depth === 0) {
				$key = $j;
			} elseif ($arrow) {
				$value[] = $j;
			}
		}

		$type = null;
		$literal = true;
		$class = null;

		foreach ($elements as $element) {
			list($k, $v) = $element;
			if (!is_array($tokens[$k]) || $tokens[$k][0] !== T_CONSTANT_ENCAPSED_STRING) {
				continue;
			}
			$name = substr($tokens[$k][1], 1, -1);
			$plain = (count($v) === 1 && is_array($tokens[$v[0]]) && $tokens[$v[0]][0] === T_CONSTANT_ENCAPSED_STRING && $tokens[$v[0]][1][0] === "'");
			if ($name === 'type') {
				$type = $plain ? substr($tokens[$v[0]][1], 1, -1) : null;
				$literal = $plain;
			} elseif ($name === 'class') {
				// The literal pieces of a built class, for its marker tokens.
				$parts = '';
				foreach ($v as $p) {
					if (is_array($tokens[$p]) && $tokens[$p][0] === T_CONSTANT_ENCAPSED_STRING) {
						$parts .= ' ' . substr($tokens[$p][1], 1, -1);
					}
				}
				$class = $plain
					? array($offsets[$v[0]], strlen($tokens[$v[0]][1]), stripslashes(substr($tokens[$v[0]][1], 1, -1)))
					: array($v ? $offsets[$v[0]] : $offsets[$k], 0, null, $parts);
			}
		}

		if ($literal && !in_array($type, array('select', 'checkbox', 'radio'), true)) {
			continue;
		}

		$calls[] = array(
			'offset' => $offsets[$i],
			'type' => $type,
			'open' => $offsets[$open] + 1,
			'class' => $class,
			'literal' => $literal,
		);
	}

	return $calls;
}

// Whether a field call still needs its v6 class written (or a hand).
function bs6_field_needs_class(array $call)
{
	if (!$call['literal']) {
		return true;
	}

	$value = $call['class'] === null ? '' : ($call['class'][2] !== null ? $call['class'][2] : $call['class'][3]);
	$tokens = preg_split('/\s+/', trim($value));

	return !array_intersect($tokens, bs6_field_class_markers());
}

// The class a field takes in a v6 panel; $in_switch for an input inside a
// <div class="switch"> box.
function bs6_field_v6_class($type, $in_switch)
{
	if ($type === 'select') {
		return 'form-control';
	}

	return $in_switch ? 'no-bs' : ($type === 'radio' ? 'radio' : 'check');
}

// Writes the v6 class into the liveform field calls of a panel PHP source
// (see the file header). Site-output code is left as it is.
function bs6_switch_fields($source)
{
	$site_output = bs6_site_output_ranges($source);
	$edits = array();

	foreach (bs6_field_calls($source) as $call) {
		if (!bs6_field_needs_class($call) || !$call['literal'] || ($site_output && bs6_in_ranges($call['offset'], $site_output))) {
			continue;
		}
		if ($call['class'] !== null && $call['class'][2] === null) {
			continue;
		}

		$before = substr($source, max(0, $call['offset'] - 120), min(120, $call['offset']));
		$in_switch = (bool) preg_match('/class="switch">\s*\'\s*\.\s*\$[A-Za-z_]+->$/', $before);
		$add = bs6_field_v6_class($call['type'], $in_switch);

		if ($call['class'] === null) {
			$edits[] = array($call['open'], 0, "'class' => '" . $add . "', ");
		} else {
			$value = trim($call['class'][2]);
			$edits[] = array($call['class'][0], $call['class'][1], "'" . addcslashes($value === '' ? $add : $value . ' ' . $add, "'\\") . "'");
		}
	}

	if (!$edits) {
		return bs6_switch_result($source, 'done');
	}

	usort($edits, function ($a, $b) {
		return $b[0] - $a[0];
	});
	foreach ($edits as $edit) {
		$source = substr_replace($source, $edit[2], $edit[0], $edit[1]);
	}

	return bs6_switch_result($source, 'applied');
}

// Panel PHP files the codemod converts that print liveform fields, sorted.
function bs6_switch_field_files($root)
{
	$paths = array();

	foreach (bs6_scope_files(rtrim($root, '/')) as $path => $kind) {
		if (bs6_codemod_converts($path) && preg_match('/\.php$/', $path) && strpos((string) file_get_contents(rtrim($root, '/') . '/' . $path), 'output_field') !== false) {
			$paths[] = $path;
		}
	}

	sort($paths);

	return $paths;
}
