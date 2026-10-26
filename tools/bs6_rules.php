<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bootstrap 5.3 to 6.0 class rules for the panel migration, shared by
 * tools/bs6_migrate.php (rewrites), tools/check_bs6.php (report) and
 * tests/bs6_migrate_test.php.
 *
 * The rules follow the class tables of the bootstrap-v5-v6-migration skill
 * (.claude/skills) and keep today's sizes: v6 gives the spacer, font-size and
 * radius scales new values, so `p-3` (1rem in v5) becomes `p-5` (1rem in v6).
 * Those value rules are therefore applied once to Bootstrap 5 markup; a second
 * run over converted markup would grow the sizes again. Component markup that
 * changes shape (modal, offcanvas, dropdown, form-check, accordion, btn-check,
 * breadcrumb, range) and classes without a v6 counterpart are reported, never
 * rewritten.
 *
 * Scope: only panel files are rewritten. Site output stays on Bootstrap 5,
 * files shared by panel and site are handled per function, and the visual
 * editor's style_designer.js is rewritten outside the code marked
 * `/* @pg-site-output *\/` (docs/memory/ui/bootstrap-6-codemod.md).
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

// Theme colours that have a v6 `.theme-*` class and colour utilities. v5's
// light and dark have none: their uses are reported.
function bs6_theme_colours()
{
	return array('primary', 'secondary', 'success', 'danger', 'warning', 'info');
}

// v5 breakpoint names and their v6 prefixes.
function bs6_breakpoints()
{
	return array('sm' => 'sm', 'md' => 'md', 'lg' => 'lg', 'xl' => 'xl', 'xxl' => '2xl');
}

// Class stems that take a breakpoint infix in v5 (`col-md-6`, `d-lg-none`,
// `navbar-expand-lg`). Size suffixes such as `btn-sm` or `modal-lg` are not
// breakpoints and are not listed. Longest first, so `row-cols` wins over
// `row-gap` checks and the like.
function bs6_responsive_stems()
{
	static $stems = null;

	if ($stems === null) {
		$stems = array(
			'list-group-horizontal', 'justify-content', 'table-responsive', 'navbar-expand',
			'align-content', 'align-items', 'align-self', 'column-gap', 'object-fit',
			'container', 'row-cols', 'row-gap', 'offset', 'sticky', 'order', 'float',
			'flex', 'text', 'gap', 'col',
			'mt', 'mb', 'ms', 'me', 'mx', 'my', 'pt', 'pb', 'ps', 'pe', 'px', 'py', 'gx', 'gy',
			'm', 'p', 'g', 'd',
		);
		usort($stems, function ($a, $b) {
			return strlen($b) - strlen($a);
		});
	}

	return $stems;
}

// v5 spacer key => v6 key of the same size (0.5rem, 1rem, 1.5rem, 3rem).
function bs6_spacer_map()
{
	return array('0' => '0', '1' => '1', '2' => '3', '3' => '5', '4' => '7', '5' => '12');
}

// v5 radius key => v6 key of the same size. v5 rounded-5 (2rem) has no v6
// size: v6 ends at 1rem (rounded-9).
function bs6_radius_map()
{
	return array('0' => '0', '1' => '3', '2' => '4', '3' => '5', '4' => '9');
}

/**
 * Splits a v5 responsive class into its breakpoint and its plain class:
 * `col-md-6` gives array('md', 'col-6'), `container-sm` array('sm', 'container'),
 * `sticky-lg-top` array('lg', 'sticky-top'). Returns null for any other class.
 */
function bs6_split_responsive($token)
{
	$breakpoints = bs6_breakpoints();

	// Stems whose bare breakpoint form is a v5 class (`col-md`, `container-sm`,
	// `navbar-expand-lg`). For the others `text-lg` or `p-sm` is not a v5
	// responsive class (v6 even has its own `.text-lg`).
	$bare = array('col', 'container', 'navbar-expand', 'table-responsive', 'list-group-horizontal');

	foreach (bs6_responsive_stems() as $stem) {
		$prefix = $stem . '-';

		if (strncmp($token, $prefix, strlen($prefix)) !== 0) {
			continue;
		}

		$rest = (string) substr($token, strlen($prefix));

		foreach ($breakpoints as $v5 => $v6) {
			if ($rest === $v5) {
				return in_array($stem, $bare, true) ? array($v6, $stem) : null;
			}

			if (strncmp($rest, $v5 . '-', strlen($v5) + 1) === 0) {
				$value = (string) substr($rest, strlen($v5) + 1);

				if ($value === '') {
					return null;
				}

				return array($v6, $stem . '-' . $value);
			}
		}
	}

	return null;
}

/**
 * Maps one Bootstrap 5 class without a breakpoint.
 *
 * Returns array('tokens' => string[], 'review' => string|null):
 *   - unchanged: tokens holds the class itself;
 *   - renamed:   tokens holds the v6 class or classes (none when the class is
 *                dropped, e.g. `has-validation`);
 *   - review:    tokens holds the class unchanged and review says why it
 *                needs a hand (structure, no v6 counterpart).
 */
function bs6_map_plain_token($token)
{
	$colours = implode('|', bs6_theme_colours());
	$keep = array('tokens' => array($token), 'review' => null);

	// Spacing: margins, paddings, gutters and gaps share the spacer scale.
	if (preg_match('/^(m|mt|mb|ms|me|mx|my|p|pt|pb|ps|pe|px|py|g|gx|gy|gap|row-gap|column-gap)-([0-5])$/', $token, $m)) {
		$map = bs6_spacer_map();
		return array('tokens' => array($m[1] . '-' . $map[$m[2]]), 'review' => null);
	}

	if (preg_match('/^(m|mt|mb|ms|me|mx|my)-n([1-5])$/', $token, $m)) {
		// v6 keeps two negative margins, and only inline: ms--1/-2, me--1/-2.
		if (in_array($m[1], array('ms', 'me'), true) && in_array($m[2], array('1', '2'), true)) {
			return array('tokens' => array($m[1] . '--' . $m[2]), 'review' => null);
		}

		return array('tokens' => array($token), 'review' => 'negative margin with no v6 class (only ms--1/-2, me--1/-2 exist)');
	}

	if (preg_match('/^fs-([1-6])$/', $token, $m)) {
		$sizes = array('1' => '4xl', '2' => '3xl', '3' => '2xl', '4' => 'xl', '5' => 'lg', '6' => 'md');
		return array('tokens' => array('fs-' . $sizes[$m[1]]), 'review' => null);
	}

	if (preg_match('/^rounded(-(top|end|bottom|start))?(-([0-5]))?$/', $token, $m)) {
		$side = isset($m[2]) ? $m[2] : '';
		$key = isset($m[4]) ? $m[4] : '';
		$base = 'rounded' . ($side !== '' ? '-' . $side : '');
		$map = bs6_radius_map();

		if ($key === '') {
			// v5's plain radius is 0.375rem, v6's 0.5rem.
			return array('tokens' => array($base . '-4'), 'review' => null);
		}

		if (!isset($map[$key])) {
			return array('tokens' => array($token), 'review' => 'radius over 1rem has no v6 class');
		}

		return array('tokens' => array($base . '-' . $map[$key]), 'review' => null);
	}

	// Text colours become foreground utilities.
	if (preg_match('/^text-(' . $colours . ')$/', $token, $m)) {
		return array('tokens' => array('fg-' . $m[1]), 'review' => null);
	}

	if (preg_match('/^text-(' . $colours . ')-emphasis$/', $token, $m)) {
		return array('tokens' => array('fg-emphasis-' . $m[1]), 'review' => null);
	}

	$renames = array(
		'text-muted' => array('fg-secondary'),
		'text-body-secondary' => array('fg-secondary'),
		'text-body' => array('fg-body'),
		'text-white' => array('fg-white'),
		'text-black' => array('fg-black'),
		'form-select' => array('form-control'),
		'form-select-sm' => array('form-control-sm'),
		'form-select-lg' => array('form-control-lg'),
		'clearfix' => array('d-flow-root'),
		'has-validation' => array(),
		'lh-base' => array('lh-md'),
		'mh-100' => array('max-h-100'),
		'mw-100' => array('max-w-100'),
		'valid-tooltip' => array('tooltip', 'valid-tooltip'),
		'invalid-tooltip' => array('tooltip', 'invalid-tooltip'),
	);

	if (isset($renames[$token])) {
		return array('tokens' => $renames[$token], 'review' => null);
	}

	if (preg_match('/^text-bg-(' . $colours . ')$/', $token, $m)) {
		return array('tokens' => array('bg-' . $m[1], 'fg-contrast-' . $m[1]), 'review' => null);
	}

	if (preg_match('/^(bg|border)-(' . $colours . ')-subtle$/', $token, $m)) {
		return array('tokens' => array($m[1] . '-subtle-' . $m[2]), 'review' => null);
	}

	if (preg_match('/^btn-(' . $colours . ')$/', $token, $m)) {
		return array('tokens' => array('btn-solid', 'theme-' . $m[1]), 'review' => null);
	}

	if (preg_match('/^btn-outline-(' . $colours . ')$/', $token, $m)) {
		return array('tokens' => array('btn-outline', 'theme-' . $m[1]), 'review' => null);
	}

	if (preg_match('/^alert-(' . $colours . ')$/', $token, $m)) {
		return array('tokens' => array('theme-' . $m[1]), 'review' => null);
	}

	// Link helpers: offsets and underline colours keep their meaning under
	// new names; the opacity steps 10-100 exist, 0 and 25/75 do not.
	if (preg_match('/^link-offset-([1-3])(-hover)?$/', $token, $m)) {
		return array('tokens' => array((isset($m[2]) && $m[2] !== '' ? 'hover:' : '') . 'underline-offset-' . $m[1]), 'review' => null);
	}

	if (preg_match('/^link-underline-(' . $colours . ')$/', $token, $m)) {
		return array('tokens' => array('underline-' . $m[1]), 'review' => null);
	}

	if (preg_match('/^link-(underline-)?opacity-(10|20|30|40|50|60|70|80|90|100)(-hover)?$/', $token, $m)) {
		$hover = (isset($m[3]) && $m[3] !== '') ? 'hover:' : '';
		$name = ($m[1] !== '') ? 'underline' : 'link';
		return array('tokens' => array($hover . $name . '-' . $m[2]), 'review' => null);
	}

	// Classes v6 removed or whose markup changes shape.
	$review = array(
		'/^(text|bg|btn|btn-outline|alert|border|link|text-bg|table|list-group-item)-(light|dark)$/' => 'light/dark variant: v6 has no light or dark theme class (neutral surfaces, colour modes or theme-inverse)',
		'/^text-(body-tertiary|body-emphasis|reset|black-50|white-50)$/' => 'text colour with no v6 class',
		'/^(text|bg|border|link)-opacity-\d+$/' => 'colour opacity utility: v6 uses the numeric ladder (fg-50, bg-70, border-30)',
		'/^bg-(body-secondary|body-tertiary|gradient)$/' => 'surface with no v6 class (bg-1..bg-4)',
		'/^link-(' . $colours . '|body-emphasis|underline)$/' => 'link colour helper with no v6 class',
		'/^link-(underline-)?opacity-(0|25|75)(-hover)?$/' => 'link opacity step with no v6 class',
		'/^opacity-\d+-hover$/' => 'hover opacity with no v6 class in 6.0.0-alpha.1',
		'/^d-print-/' => 'print display utility with no v6 class in 6.0.0-alpha.1',
		'/^(display-[1-6]|lead)$/' => 'removed: compose with fs-* and fw-*',
		'/^(btn-close-white|alert-dismissible|navbar-light|navbar-dark)$/' => 'removed in v6',
		'/^(needs-validation|was-validated)$/' => 'validation: data-bs-validate on the form, :user-invalid instead of the JS class',
		'/^modal(-.+)?$/' => 'modal becomes a <dialog class="dialog">: structural',
		'/^offcanvas(-.+)?$/' => 'offcanvas becomes a <dialog class="drawer">: structural',
		'/^(dropdown|dropup|dropend|dropstart)(-.+)?$/' => 'dropdown becomes a sibling .menu: structural',
		'/^(form-check(-.+)?|form-switch)$/' => 'checkbox, radio and switch markup changes (.check, .radio, .switch)',
		'/^btn-check$/' => 'toggle button: the input moves inside the label',
		'/^accordion-(button|collapse)$/' => 'accordion becomes <details>/<summary>',
		'/^form-range$/' => 'range: .form-range moves to a wrapper',
		'/^carousel-(control-prev|control-next|caption|dark|item-start|item-end|item-next|item-prev)(-icon)?$/' => 'carousel class removed in v6',
	);

	foreach ($review as $pattern => $reason) {
		if (preg_match($pattern, $token)) {
			return array('tokens' => array($token), 'review' => $reason);
		}
	}

	return $keep;
}

/**
 * The classes of the Bootstrap 6 dist while a converted tree is read, else
 * null. On a converted tree `p-5` or `rounded-4` is a v6 class, not a v5 one
 * to rewrite again: tools/check_bs6.php sets the dist's classes after the
 * switch, and bs6_map_token() then keeps any class v6 defines. The codemod
 * never sets it (it reads Bootstrap 5 markup). Pass false to clear.
 */
function bs6_converted_tree_classes($classes = null)
{
	static $set = null;

	if ($classes === false) {
		$set = null;
	} elseif (is_array($classes)) {
		$set = $classes;
	}

	return $set;
}

// True when the panel head (output_control_panel_header_includes()) loads the
// Bootstrap 6 stylesheet: the tree has been switched.
function bs6_panel_head_is_v6($product)
{
	$output = (string) @file_get_contents($product . '/includes/fn/output.php');

	return strpos($output, '/assets/lib/bootstrap-6/css/bootstrap.min.css') !== false;
}

/**
 * Maps one class token, breakpoint infix included: `p-md-3` gives `md:p-5`,
 * `col-xxl-4` gives `2xl:col-4`. Same return shape as bs6_map_plain_token().
 */
function bs6_map_token($token)
{
	$v6 = bs6_converted_tree_classes();

	if ($v6 !== null && isset($v6[$token])) {
		return array('tokens' => array($token), 'review' => null);
	}

	$split = bs6_split_responsive($token);

	if ($split === null) {
		return bs6_map_plain_token($token);
	}

	list($prefix, $plain) = $split;
	$mapped = bs6_map_plain_token($plain);
	$tokens = array();

	foreach ($mapped['tokens'] as $one) {
		$tokens[] = $prefix . ':' . $one;
	}

	if ($mapped['review'] !== null) {
		return array('tokens' => array($token), 'review' => $mapped['review']);
	}

	return array('tokens' => $tokens, 'review' => null);
}

// A class token the rules may rewrite: letters, digits, '-', '_' and the v6
// prefix colon. Anything else (quotes, `$x`, `${x}`, `' . $y . '`, `<?=`) is
// code that builds the class and is left alone.
function bs6_is_literal_token($token)
{
	// A class starts with a letter or digit (`2xl:col-3`); a lone ':' or '-'
	// is an operator of the code around it.
	return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9_:-]*$/', $token);
}

// True when a piece of code that builds a class names a Bootstrap 5 stem
// whose classes change in v6 (`'btn-' . $variant`, `"col-md-" + n`).
function bs6_is_dynamic_v5_stem($piece)
{
	return (bool) preg_match('/(^|[^A-Za-z0-9_-])(btn|btn-outline|text|bg|alert|col-(sm|md|lg|xl|xxl)|(d|p[trblxyse]?|m[trblxyse]?|g[xy]?)-(sm|md|lg|xl|xxl)|fs|rounded|text-bg|border)-[\'"$`{+]/', $piece)
		|| (bool) preg_match('/(^|[^A-Za-z0-9_-])(m|p)[tbsexy]?-[\'"$`{+]/', $piece);
}

/**
 * Converts a whitespace-separated class list, keeping its spacing and any
 * code inside it. Returns array('value', 'changed' => int, 'reviews' =>
 * array of array('token', 'reason')).
 *
 * In a list holding `badge`, `bg-<colour>` and `text-bg-<colour>` become
 * `theme-<colour>` (v6 badges take the theme class).
 *
 * With $one_class_each (an argument of classList or addClass/removeClass/
 * toggleClass) a class that would become two or none is kept and reported:
 * classList takes one class per argument, and removing or toggling half of
 * `btn-solid theme-primary` would break the button's other variants.
 */
function bs6_convert_class_list($value, $one_class_each = false)
{
	$parts = preg_split('/(\s+)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
	$literal = array();
	$has_code = false;

	foreach ($parts as $i => $part) {
		if (($i % 2) === 0 && $part !== '') {
			if (bs6_is_literal_token($part)) {
				$literal[] = $part;
			} else {
				$has_code = true;
			}
		}
	}

	$is_badge = in_array('badge', $literal, true);
	$colours = implode('|', bs6_theme_colours());
	$changed = 0;
	$reviews = array();
	$seen = array();
	$themes = array();

	foreach ($parts as $i => $part) {
		if (($i % 2) === 1 || $part === '') {
			continue;
		}

		if (!bs6_is_literal_token($part)) {
			if (bs6_is_dynamic_v5_stem($part)) {
				$reviews[] = array($part, 'class built in code from a Bootstrap 5 stem');
			} elseif (preg_match_all('/[\'"]([A-Za-z0-9][A-Za-z0-9_:-]*)|([A-Za-z0-9][A-Za-z0-9_:-]*)[\'"]/', $part, $quoted, PREG_SET_ORDER)) {
				// A class inside the code (`? 'text-success' : ''`) is left as
				// written and reported when the rules would change it.
				foreach ($quoted as $q) {
					$inner = ($q[1] !== '') ? $q[1] : $q[2];
					$mapped = bs6_map_token($inner);
					if ($mapped['review'] !== null || $mapped['tokens'] !== array($inner)) {
						$reviews[] = array($inner . ($mapped['review'] === null ? ' -> ' . implode(' ', $mapped['tokens']) : ''), 'class inside code: convert it by hand');
					}
				}
			}
			continue;
		}

		$v6 = bs6_converted_tree_classes();

		if ($v6 !== null && isset($v6[$part])) {
			$mapped = array('tokens' => array($part), 'review' => null);
		} elseif ($is_badge && preg_match('/^(text-bg|bg)-(' . $colours . ')$/', $part, $m)) {
			$mapped = array('tokens' => array('theme-' . $m[2]), 'review' => null);
		} else {
			$mapped = bs6_map_token($part);
		}

		if ($one_class_each && $mapped['review'] === null && count($mapped['tokens']) !== 1) {
			$reviews[] = array($part . ' -> ' . (count($mapped['tokens']) ? implode(' ', $mapped['tokens']) : '(none)'), 'a class API call takes one class: convert it by hand');
			$mapped = array('tokens' => array($part), 'review' => null);
		}

		if ($mapped['review'] !== null) {
			$reviews[] = array($part, $mapped['review']);
		}

		if ($mapped['tokens'] !== array($part)) {
			$changed++;
		}

		// A class already in the list is not written twice (`btn btn-primary`
		// next to an existing `btn-solid`). Not in a list with code in it:
		// the branches of a condition may each name the same class.
		$fresh = array();
		foreach ($mapped['tokens'] as $one) {
			if ($has_code || !isset($seen[$one])) {
				$seen[$one] = true;
				$fresh[] = $one;
				if (strpos($one, 'theme-') === 0) {
					$themes[] = $one;
				}
			}
		}

		$parts[$i] = implode(' ', $fresh);
	}

	if (!$has_code && count($themes) > 1) {
		$reviews[] = array(implode(' ', $themes), 'two theme classes in one list: the element takes one theme');
	}

	return array('value' => bs6_join_class_parts($value, $parts), 'changed' => $changed, 'reviews' => $reviews);
}

/**
 * Joins the parts of a class list after conversion. Every even part is a
 * token (possibly emptied: a dropped class), every odd part the whitespace
 * between two tokens. An emptied token takes the whitespace after it, or
 * before it when it was the last token, so the list keeps single spacing.
 */
function bs6_join_class_parts($value, $parts)
{
	$original = preg_split('/(\s+)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
	$count = count($parts);
	$skip = array();

	for ($i = 0; $i < $count; $i += 2) {
		if ($parts[$i] !== '' || $original[$i] === '') {
			continue;
		}

		if (isset($parts[$i + 1]) && ($i + 2) < $count) {
			$skip[$i + 1] = true;
		} elseif ($i > 0) {
			$skip[$i - 1] = true;
		}
	}

	$out = '';
	foreach ($parts as $i => $part) {
		if (!isset($skip[$i])) {
			$out .= $part;
		}
	}

	return $out;
}

/**
 * Finds the class lists in PHP, JS or HTML source. Each match is
 * array('offset' => int, 'length' => int, 'value' => string, 'kind' => string)
 * for the class list text only, so the code around it is never touched.
 *
 * Contexts: a `class="..."` / `class='...'` attribute (escaped quotes too),
 * PHP array keys ending in "class" or "classes" (`'class' => '...'`), JS
 * object keys `class:` / `className:`, `.className = '...'`, jQuery
 * `addClass` / `removeClass` / `toggleClass` / `hasClass` and the
 * `classList` methods with string arguments.
 */
function bs6_find_class_lists($source)
{
	$found = array();

	$patterns = array(
		// Not `$class = '...'`: a PHP variable assignment is code, not markup.
		'attribute' => '/(?<![$\w])class\s*=\s*(\\\\?)(["\'])(.*?)\1\2/s',
		'php-key' => '/([\'"])[A-Za-z_ ]*class(?:es)?\1\s*=>\s*([\'"])(.*?)(?<!\\\\)\2/',
		'js-key' => '/\b(?:class|className)\s*:\s*([\'"])(.*?)(?<!\\\\)\1/',
		'class-name' => '/\.className\s*\+?=\s*([\'"])(.*?)(?<!\\\\)\1/',
		'jquery' => '/\.(?:addClass|removeClass|toggleClass|hasClass)\(\s*([\'"])(.*?)(?<!\\\\)\1/',
	);

	foreach ($patterns as $kind => $pattern) {
		if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($matches as $m) {
			$group = ($kind === 'attribute' || $kind === 'php-key') ? 3 : 2;

			if ($m[$group][1] < 0) {
				continue;
			}

			$found[] = array('offset' => $m[$group][1], 'length' => strlen($m[$group][0]), 'value' => $m[$group][0], 'kind' => $kind);
		}
	}

	if (preg_match_all('/classList\.(?:add|remove|toggle|contains|replace)\(([^()]*)\)/', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		foreach ($matches as $m) {
			$args = $m[1][0];
			$base = $m[1][1];

			if (preg_match_all('/([\'"])(.*?)(?<!\\\\)\1/', $args, $strings, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
				foreach ($strings as $s) {
					$found[] = array('offset' => $base + $s[2][1], 'length' => strlen($s[2][0]), 'value' => $s[2][0], 'kind' => 'class-list');
				}
			}
		}
	}

	// One span is converted once even when two patterns see it.
	usort($found, function ($a, $b) {
		return $a['offset'] - $b['offset'];
	});

	$unique = array();
	$end = -1;
	foreach ($found as $one) {
		if ($one['offset'] < $end) {
			continue;
		}
		$unique[] = $one;
		$end = $one['offset'] + $one['length'];
	}

	return $unique;
}

// 1-based line number of a byte offset.
function bs6_line_of($source, $offset)
{
	return substr_count($source, "\n", 0, $offset) + 1;
}

/**
 * Byte ranges of site-output code: markup Bootstrap 5.3.8 draws (written
 * into a page, or drawn in the editor's canvas), which the codemod and
 * check_bs6.php leave alone. A line holding only the marker
 * `/* @pg-site-output *\/` covers the declaration below it: up to the next
 * line at the declaration's indentation or less that is not a closing
 * bracket. The marker at the end of a code line covers that line.
 *
 * Returns a list of array(start, end) byte offsets, end exclusive.
 */
function bs6_site_output_ranges($source)
{
	$marker = '/* @pg-site-output */';

	if (strpos($source, $marker) === false) {
		return array();
	}

	$ranges = array();
	$lines = explode("\n", $source);
	$offsets = array();
	$offset = 0;

	foreach ($lines as $i => $line) {
		$offsets[$i] = $offset;
		$offset += strlen($line) + 1;
	}

	$count = count($lines);

	for ($i = 0; $i < $count; $i++) {
		if (strpos($lines[$i], $marker) === false) {
			continue;
		}

		if (trim($lines[$i]) !== $marker) {
			$ranges[] = array($offsets[$i], $offsets[$i] + strlen($lines[$i]));
			continue;
		}

		$start = $i + 1;
		if ($start >= $count) {
			continue;
		}

		$indent = strlen($lines[$start]) - strlen(ltrim($lines[$start]));
		$end = $start + 1;

		while ($end < $count) {
			$line = $lines[$end];
			$text = ltrim($line);
			$depth = strlen($line) - strlen($text);

			if ($text !== '' && $depth <= $indent && strpos('}])', $text[0]) === false) {
				break;
			}

			$end++;
		}

		$ranges[] = array($offsets[$i], $offsets[$end - 1] + strlen($lines[$end - 1]));
		$i = $end - 1;
	}

	return $ranges;
}

// Whether a byte offset lies in one of bs6_site_output_ranges().
function bs6_in_ranges($offset, array $ranges)
{
	foreach ($ranges as $range) {
		if ($offset >= $range[0] && $offset < $range[1]) {
			return true;
		}
	}

	return false;
}

/**
 * Converts the class lists in a source file. With $lines (a set of 1-based
 * line numbers as array keys) only the lists starting on those lines change:
 * that is how a branch older than the switch converts the lines it added.
 *
 * Class lists in site-output code (bs6_site_output_ranges()) stay as they
 * are and are counted in 'kept'.
 *
 * Returns array('source', 'changed' => int, 'kept' => int, 'reviews' =>
 * array of array('line', 'token', 'reason')).
 */
function bs6_convert_source($source, $lines = null)
{
	$out = '';
	$position = 0;
	$changed = 0;
	$kept = 0;
	$reviews = array();
	$site_output = bs6_site_output_ranges($source);

	foreach (bs6_find_class_lists($source) as $list) {
		$line = bs6_line_of($source, $list['offset']);

		if ($lines !== null && !isset($lines[$line])) {
			continue;
		}

		if ($site_output && bs6_in_ranges($list['offset'], $site_output)) {
			$kept++;
			continue;
		}

		$result = bs6_convert_class_list($list['value'], in_array($list['kind'], array('class-list', 'jquery'), true));

		foreach ($result['reviews'] as $review) {
			$reviews[] = array($line, $review[0], $review[1]);
		}

		$out .= substr($source, $position, $list['offset'] - $position) . $result['value'];
		$position = $list['offset'] + $list['length'];
		$changed += $result['changed'];
	}

	$out .= substr($source, $position);

	return array('source' => $out, 'changed' => $changed, 'kept' => $kept, 'reviews' => $reviews);
}

/**
 * What a path (relative to pinegrap/) is for the migration:
 *   'panel'  - rewritten by the codemod;
 *   'site'   - site output, stays on Bootstrap 5, never rewritten;
 *   'shared' - prints for panel and site: converted per function by hand;
 *   'editor' - the visual editor: only the files of
 *              bs6_marked_editor_files() are rewritten, outside their
 *              site-output code; the rest is converted by hand;
 *   'adapter' - assets/js/pg_ui.js, which names both versions on purpose;
 *   'vendor' - third-party, generated or data files;
 *   'other'  - no markup the rules read (images, fonts, json, ...).
 */
function bs6_scope_kind($path)
{
	$path = str_replace('\\', '/', $path);
	$base = basename($path);
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

	if (!in_array($ext, array('php', 'js', 'css', 'html', 'htm'), true)) {
		return 'other';
	}

	if (preg_match('#^(assets/lib|assets/fonts|data|includes/local|includes/migrations|includes/(phpmailer|stripe|iyzipay-php|phpexcel|boxpacker|dompdf|qrcode))/#', $path)
		|| preg_match('/\.min\.(js|css)$/', $base)) {
		return 'vendor';
	}

	if ($path === 'assets/js/pg_ui.js') {
		return 'adapter';
	}

	// The visual editor's files. The smart completion's vocabulary and
	// predictor (designer_vocab.php, pg_style_predict.js) describe the
	// Bootstrap 5 API that site pages load, so their Bootstrap names are data.
	$editor = array(
		'assets/js/style_designer.js', 'assets/css/style_designer.css', 'includes/designer_screen.php',
		'includes/fn/designer.php', 'includes/designer_ai.php', 'assets/js/pg_style_suggest.js',
		'assets/js/pg_style_predict.js', 'includes/fn/designer_vocab.php', 'assets/js/pg_style_complete.js',
	);
	if (in_array($path, $editor, true)) {
		return 'editor';
	}

	$shared = array(
		'get_login.php', 'liveform.class.php', 'toolbar.php', 'chat.php', 'seo.php',
		'includes/fn/output.php', 'includes/fn/ecommerce.php', 'includes/fn/forms.php', 'includes/fn/editor.php',
		'includes/fn/content.php', 'includes/fn/calendar.php', 'includes/fn/auth.php', 'includes/fn/seo.php',
		'includes/fn/legal_documents.php',
	);
	if (in_array($path, $shared, true)) {
		return 'shared';
	}

	if (strpos($path, 'includes/design_templates/') === 0 || strpos($path, 'assets/css/themes/') === 0) {
		return 'site';
	}

	if (strpos($path, 'includes/templates/') === 0) {
		$panel_templates = array(
			'duplicate_folder', 'edit_auto_dialog', 'edit_key_code', 'edit_offer', 'edit_products', 'generate_layout',
			'view_auto_dialogs', 'view_key_codes', 'view_offers', 'shipping_method_service',
		);
		return in_array(substr($base, 0, -4), $panel_templates, true) ? 'panel' : 'site';
	}

	// Every system widget module (widgets.php, widgets_*.php) draws site
	// output, the ones added later included.
	$site_fn = array(
		'consent.php', 'language_redirect.php', 'maintenance_mode.php', 'meta_tags.php', 'reviews.php',
		'visitor_texts.php',
	);
	if (strpos($path, 'includes/fn/') === 0 && (in_array($base, $site_fn, true) || preg_match('/^widgets(_[a-z0-9_]+)?\.php$/', $base))) {
		return 'site';
	}

	$site_assets = array(
		'assets/js/payment_hosted.js', 'assets/js/pg_ai_summary.js', 'assets/js/pg_consent.js', 'assets/js/pg_lightbox.js',
		'assets/js/pg_search_highlight.js', 'assets/js/pg_civ_variants.js', 'assets/js/pg_scroller.js',
		'assets/js/pg_theme_switcher.js', 'assets/css/pg_carousel_layout.css', 'assets/css/pg_consent.css',
		'assets/css/pg_lightbox.css',
	);
	if (in_array($path, $site_assets, true) || preg_match('/^(frontend|chat_site)\.(src|min)\.js$/', $base) || preg_match('#^assets/js/chat_site#', $path)) {
		return 'site';
	}

	if (strpos($path, '/') === false) {
		$site_root = array(
			'add_to_cart.php', 'cart_action.php', 'billing_information.php', 'shipping_address_and_arrival.php',
			'shipping_method.php', 'shopping_cart.php', 'order_form.php', 'order_preview.php', 'express_order.php',
			'submit_order.php', 'update_address_book.php', 'custom_form.php', 'email_a_friend.php',
			'email_preferences.php', 'forgot_password.php', 'change_password.php', 'set_password.php',
			'registration_entrance.php', 'registration_confirmation.php', 'membership_entrance.php',
			'membership_confirmation.php', 'my_account.php', 'my_account_profile.php', 'affiliate_sign_up_form.php',
			'affiliate_sign_up_confirmation.php', 'order_history_delete_order.php', 'order_history_reorder.php',
			'order_history_retrieve_order.php', 'order_history_view_order.php', 'order_return.php',
			'retrieve_order.php', 'remove_gift_card_from_order.php', 'remove_item_from_cart.php',
			'remove_recipient.php', 'reserve_calendar_event.php', 'check_for_products_in_cart.php',
			'catalog_detail.php', 'legal_document.php', 'short_link_gone.php', 'index.php', 'init.php',
			'functions.php', 'feed.php', 'manifest.php', 'manifest_icon.php', 'waf.php', 'api.php', 'mcp.php',
			'oauth.php', 'health.php', 'get_page.php', 'get_page_content.php', 'myself_upsell.php', 'kiosk.php',
			'cancel_order.php', 'signature_receipt.php', 'account_security.php', 'excel_reader2.php',
			'pclzip.lib.php', 'url_to_absolute.php', 'mysqldump.php', 'csp_report.php',
			'generate_system_theme_css.php', 'sw.js',
		);
		$panel_get = array(
			'get_erp_drawer.php', 'get_erp_contacts.php', 'get_erp_products.php', 'get_erp_edoc_test.php',
			'get_erp_edoc_document.php', 'get_erp_inbox_file.php', 'get_erp_rate.php', 'get_files_for_picker.php',
			'get_seo_analysis.php', 'get_sitemap_info.php', 'get_theme_designer_module.php', 'get_help_url.php',
			'get_product_group_for_product.php', 'get_custom_form_fields.php',
		);

		if (in_array($base, $site_root, true)) {
			return 'site';
		}

		if (strpos($base, 'get_') === 0 && !in_array($base, $panel_get, true)) {
			return 'site';
		}

		if (substr($base, -8) === '_job.php') {
			return 'site';
		}
	}

	return 'panel';
}

/**
 * Class names defined in a Bootstrap 6 stylesheet, unescaped (`md:col-6`,
 * `2xl:col-3`), as array keys. The tools use it to report a rewrite whose
 * result the shipped dist does not define.
 */
function bs6_dist_classes($css)
{
	$classes = array();

	if (preg_match_all('/\.((?:\\\\[0-9a-fA-F]{1,6} ?|\\\\.|[A-Za-z0-9_-])+)/', $css, $matches)) {
		foreach ($matches[1] as $raw) {
			$name = preg_replace_callback('/\\\\([0-9a-fA-F]{1,6}) ?|\\\\(.)/', function ($m) {
				if (isset($m[1]) && $m[1] !== '') {
					$code = hexdec($m[1]);
					return ($code < 128) ? chr($code) : '';
				}
				return $m[2];
			}, $raw);
			$classes[$name] = true;
		}
	}

	return $classes;
}

/**
 * The lines a unified diff adds, per file: array(path => array(line => true))
 * with paths as git prints them after `+++ b/` and new-file line numbers.
 * Read from `git diff -U0`, so every hunk header carries the added range.
 */
function bs6_parse_added_lines($diff)
{
	$files = array();
	$current = null;

	foreach (preg_split('/\r?\n/', $diff) as $row) {
		if (strncmp($row, '+++ ', 4) === 0) {
			$path = substr($row, 4);
			$current = ($path === '/dev/null') ? null : preg_replace('#^b/#', '', $path);
			if ($current !== null && !isset($files[$current])) {
				$files[$current] = array();
			}
			continue;
		}

		if ($current !== null && preg_match('/^@@ -\d+(?:,\d+)? \+(\d+)(?:,(\d+))? @@/', $row, $m)) {
			$start = (int) $m[1];
			$count = (isset($m[2]) && $m[2] !== '') ? (int) $m[2] : 1;

			for ($line = $start; $line < $start + $count; $line++) {
				$files[$current][$line] = true;
			}
		}
	}

	return array_filter($files);
}

// Runs a command without a shell (the array form of proc_open from PHP
// 7.4): array('status', 'out', 'err').
function bs6_run(array $command)
{
	$command = array_map('strval', $command);

	if (PHP_VERSION_ID < 70400) {
		$command = implode(' ', array_map('escapeshellarg', $command));
	}

	$error_file = tempnam(sys_get_temp_dir(), 'bs6');
	$error_target = ($error_file !== false) ? $error_file : ((DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null');
	$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('file', $error_target, 'w'));
	$process = @proc_open($command, $descriptors, $pipes, null, null, array('bypass_shell' => true));

	if (!is_resource($process)) {
		if ($error_file !== false) {
			@unlink($error_file);
		}
		return array('status' => -1, 'out' => '', 'err' => 'the command could not be started');
	}

	fclose($pipes[0]);
	$out = (string) stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$status = proc_close($process);
	$err = ($error_file !== false) ? trim((string) @file_get_contents($error_file)) : '';

	if ($error_file !== false) {
		@unlink($error_file);
	}

	return array('status' => (int) $status, 'out' => $out, 'err' => $err);
}

// Runs git with $args in $repository.
function bs6_git($repository, array $args)
{
	return bs6_run(array_merge(array('git', '-C', (string) $repository), $args));
}

/**
 * Null when converted source still parses, else the parser's message: PHP
 * through `php -l`, JavaScript through `node --check` when Node is there.
 * The codemod never writes a file this refuses.
 */
function bs6_syntax_error($path, $source)
{
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

	if ($ext === 'php') {
		$command = array(PHP_BINARY, '-l');
	} elseif ($ext === 'js') {
		$node = bs6_run(array('node', '--version'));
		if ($node['status'] !== 0) {
			return null;
		}
		$command = array('node', '--check');
	} else {
		return null;
	}

	$temp = tempnam(sys_get_temp_dir(), 'bs6');
	if ($temp === false) {
		return 'no temporary file for the syntax check';
	}

	$file = $temp . '.' . $ext;
	file_put_contents($file, $source);
	$command[] = $file;
	$result = bs6_run($command);
	@unlink($file);
	@unlink($temp);

	if ($result['status'] === 0) {
		return null;
	}

	return trim($result['err'] . ' ' . $result['out']);
}

// The visual editor's files whose site-output code carries the
// `/* @pg-site-output */` marker, so the codemod may rewrite the rest.
function bs6_marked_editor_files()
{
	return array('assets/js/style_designer.js');
}

// Whether --all, --diff and a named path rewrite this file without --force.
function bs6_codemod_converts($path)
{
	$kind = bs6_scope_kind($path);

	return $kind === 'panel' || ($kind === 'editor' && in_array(str_replace('\\', '/', $path), bs6_marked_editor_files(), true));
}

// Every file under $root (the pinegrap/ folder) with its scope kind, as
// array(relative path => kind), vendor and other files left out.
function bs6_scope_files($root)
{
	$root = rtrim(str_replace('\\', '/', $root), '/');
	$files = array();
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

	foreach ($iterator as $file) {
		if (!$file->isFile()) {
			continue;
		}

		$path = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
		$kind = bs6_scope_kind($path);

		if ($kind !== 'vendor' && $kind !== 'other' && $kind !== 'adapter') {
			$files[$path] = $kind;
		}
	}

	ksort($files);

	return $files;
}
