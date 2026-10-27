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
		// v6 keeps two negative margins, and only inline: ms--1/-2, me--1/-2;
		// the others become the panel's own pg-<side>-n<key> (v5's sizes).
		if (in_array($m[1], array('ms', 'me'), true) && in_array($m[2], array('1', '2'), true)) {
			return array('tokens' => array($m[1] . '--' . $m[2]), 'review' => null);
		}

		return array('tokens' => array('pg-' . $token), 'review' => null);
	}

	if (preg_match('/^display-([1-6])$/', $token, $m)) {
		return array('tokens' => array('pg-display-' . $m[1]), 'review' => null);
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
		// v6 neutrals the panel's token bridge sets to v5's values.
		'text-reset' => array('fg-reset'),
		'text-body-tertiary' => array('fg-3'),
		'bg-body-tertiary' => array('bg-1'),
		'bg-body-secondary' => array('bg-2'),
		// v6's close icon is drawn in currentcolor.
		'btn-close-white' => array('fg-white'),
		// A Bootstrap 4 class: neither v5 nor v6 draws it.
		'dropdown-menu-right' => array(),
		// No v6 class: the panel's own, drawn as v5 did (the switch's shell
		// rules in backend.src.css).
		'text-body-emphasis' => array('pg-fg-emphasis'),
		'table-light' => array('pg-table-light'),
		'alert-dismissible' => array('pg-alert-dismissible'),
		'lead' => array('pg-lead'),
	);

	if (isset($renames[$token])) {
		return array('tokens' => $renames[$token], 'review' => null);
	}

	// Light and dark have no theme in v6: the nearest neutral keeps the look
	// (v5's light is a pale grey surface, its dark a near-black one, in both
	// colour modes).
	$light_dark = array(
		'text-dark' => array('fg-black'),
		'text-light' => array('fg-white'),
		'bg-light' => array('bg-1'),
		'bg-dark' => array('bg-black'),
		'text-bg-light' => array('bg-1', 'fg-body'),
		'text-bg-dark' => array('bg-black', 'fg-white'),
		'alert-light' => array('theme-secondary'),
		'btn-light' => array('btn-subtle', 'theme-secondary'),
		'btn-dark' => array('btn-solid', 'theme-inverse'),
		'border-dark' => array('border-inverse'),
		'border-light' => array('border-subtle'),
	);

	if (isset($light_dark[$token])) {
		return array('tokens' => $light_dark[$token], 'review' => null);
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
		'/^(navbar-light|navbar-dark)$/' => 'removed in v6',
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
 * `theme-<colour>` (v6 badges take the theme class); a light badge becomes
 * `badge-subtle theme-secondary`, a dark one `theme-inverse`.
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
			} elseif (preg_match_all('/[\'"]([A-Za-z0-9][A-Za-z0-9_:-]*)|([A-Za-z0-9][A-Za-z0-9_:-]*)[\'"]|\?>([A-Za-z0-9][A-Za-z0-9_:-]*)(?=<\?)/', $part, $quoted, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
				// A class quoted in the code (`? 'text-success' : ''`), or
				// printed between two PHP blocks (a class right after a PHP close tag), is
				// text the code prints into the list: it is rewritten in
				// place. One that needs a hand, or one that would split in a
				// class API call, is reported.
				$rewritten = $part;

				foreach (array_reverse($quoted) as $q) {
					$hit = ($q[1][0] !== '') ? $q[1] : ((isset($q[2]) && $q[2][0] !== '') ? $q[2] : $q[3]);
					$inner = $hit[0];
					$mapped = ($is_badge && preg_match('/^(text-bg|bg)-(' . $colours . ')$/', $inner, $m))
						? array('tokens' => array('theme-' . $m[2]), 'review' => null)
						: bs6_map_token($inner);

					if ($mapped['review'] === null && $mapped['tokens'] === array($inner)) {
						continue;
					}

					if ($mapped['review'] !== null || !$mapped['tokens'] || ($one_class_each && count($mapped['tokens']) !== 1)) {
						$reviews[] = array($inner . ($mapped['review'] === null ? ' -> ' . implode(' ', $mapped['tokens']) : ''), 'class inside code: convert it by hand');
						continue;
					}

					$rewritten = substr($rewritten, 0, $hit[1]) . implode(' ', $mapped['tokens']) . substr($rewritten, $hit[1] + strlen($inner));
					$changed++;
				}

				$parts[$i] = $rewritten;
			}
			continue;
		}

		$v6 = bs6_converted_tree_classes();

		if ($v6 !== null && isset($v6[$part])) {
			$mapped = array('tokens' => array($part), 'review' => null);
		} elseif ($is_badge && preg_match('/^(text-bg|bg)-(' . $colours . ')$/', $part, $m)) {
			$mapped = array('tokens' => array('theme-' . $m[2]), 'review' => null);
		} elseif ($is_badge && preg_match('/^(text-bg|bg)-(light|dark)$/', $part, $m)) {
			$mapped = array('tokens' => $m[2] === 'light' ? array('badge-subtle', 'theme-secondary') : array('theme-inverse'), 'review' => null);
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
 * `addClass` / `removeClass` / `toggleClass` / `hasClass`, the
 * `classList` methods with string arguments, and the element helpers of the
 * panel scripts (`el('div', 'mb-3')`, `h.el(…)`, `make(…)`, `button('btn …')`,
 * `icon('bi-x', 'me-2')`).
 */
function bs6_find_class_lists($source, $strings = false)
{
	$found = array();

	if ($strings) {
		$found = bs6_find_class_strings($source);
	}

	$patterns = array(
		// Not `$class = '...'`: a PHP variable assignment is code, not markup.
		'attribute' => '/(?<![$\w])class\s*=\s*(\\\\?)(["\'])(.*?)\1\2/s',
		'php-key' => '/([\'"])[A-Za-z_ ]*class(?:es)?\1\s*=>\s*([\'"])(.*?)(?<!\\\\)\2/',
		'js-key' => '/\b(?:class|className)\s*:\s*([\'"])(.*?)(?<!\\\\)\1/',
		'class-name' => '/\.className\s*\+?=\s*([\'"])(.*?)(?<!\\\\)\1/',
		'jquery' => '/\.(?:addClass|removeClass|toggleClass|hasClass)\(\s*([\'"])(.*?)(?<!\\\\)\1/',
		// Element helpers of the panel scripts: el('div', 'mb-3'),
		// h.el(…), make(…), element(…), svgEl(…); button('btn …', label);
		// icon('bi-x', 'me-2').
		'helper' => '/(?<![\w$])(?:[A-Za-z_$][\w$]*\.)?(?:el|element|make|svgEl)\(\s*([\'"])[a-zA-Z][\w-]*\1\s*,\s*([\'"])(.*?)(?<!\\\\)\2/',
		'helper-first' => '/(?<![\w$])(?:[A-Za-z_$][\w$]*\.)?button\(\s*()([\'"])(.*?)(?<!\\\\)\2/',
		'helper-icon' => '/(?<![\w$])(?:[A-Za-z_$][\w$]*\.)?icon\(\s*([\'"])[^\'"]*\1\s*,\s*([\'"])(.*?)(?<!\\\\)\2/',
	);

	foreach ($patterns as $kind => $pattern) {
		if (!preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
			continue;
		}

		foreach ($matches as $m) {
			$group = in_array($kind, array('attribute', 'php-key', 'helper', 'helper-first', 'helper-icon'), true) ? 3 : 2;

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

// Whether the codemod reads class names in plain string literals of a file
// (bs6_find_class_strings()): not in the editor's script, whose chrome code
// names the canvas's Bootstrap 5 classes as data.
function bs6_reads_class_strings($path)
{
	return str_replace('\\', '/', $path) !== 'assets/js/style_designer.js';
}

/**
 * String literals in code that hold only class names, at least one of them a
 * Bootstrap 5 class the rules change or report: a status map
 * (`'expired' => 'text-bg-warning'`), a function's return value, a JS
 * object of classes. Every token has a hyphen or is a class without one
 * (`btn`, `badge`, …), so text and keys are not taken. Kind 'string'; a
 * single class in one is converted like a class API argument (a class that
 * would become two is reported), since the code may add it with classList.
 */
function bs6_find_class_strings($source)
{
	$bare = array('btn', 'badge', 'small', 'row', 'col', 'card', 'border', 'rounded', 'shadow', 'active', 'disabled', 'show', 'fade', 'container', 'table', 'alert', 'collapse', 'clearfix', 'invisible', 'visible');
	$found = array();

	// One space may lead or end the string (`' text-success'`, added to a
	// list in code); the class list is what lies between.
	if (!preg_match_all('/([\'"]) ?([A-Za-z][A-Za-z0-9_:-]*(?: [A-Za-z][A-Za-z0-9_:-]*)*) ?\1/', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		return $found;
	}

	foreach ($matches as $m) {
		// An attribute value other than class (`id="modal-contact"`) or an
		// element id in code names no class.
		if (preg_match('/(?:\s(?:id|for|name|form|list|role|type|value|href|src|target|rel|title|alt|placeholder|aria-[\w-]+)=\\\\?|getElementById\(\s*)$/', substr($source, max(0, $m[0][1] - 40), min(40, $m[0][1])))) {
			continue;
		}

		$tokens = explode(' ', $m[2][0]);
		$v5 = false;

		foreach ($tokens as $token) {
			// A word with neither a hyphen nor an underscore (an own class
			// such as `status_enabled`) is not a class: the string is text.
			if (strpos($token, '-') === false && strpos($token, '_') === false && !in_array($token, $bare, true)) {
				continue 2;
			}

			$mapped = bs6_map_token($token);

			if ($mapped['review'] !== null || $mapped['tokens'] !== array($token)) {
				$v5 = true;
			}
		}

		if ($v5) {
			$found[] = array('offset' => $m[2][1], 'length' => strlen($m[2][0]), 'value' => $m[2][0], 'kind' => 'string');
		}
	}

	return $found;
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
function bs6_site_output_ranges($source, $path = null)
{
	$marker = '/* @pg-site-output */';
	$path = ($path !== null) ? $path : bs6_current_path();
	$functions = bs6_site_functions();

	$panel = bs6_shared_panel_functions();
	$blocks = bs6_shared_panel_blocks();
	if ($path !== null && (isset($panel[$path]) || isset($blocks[$path]))) {
		// Everything outside the panel functions and blocks is site output.
		$inside = isset($panel[$path]) ? bs6_function_ranges($source, $panel[$path]) : array();
		if (isset($blocks[$path])) {
			foreach ($blocks[$path] as $function => $anchor) {
				$inside = array_merge($inside, bs6_block_ranges($source, $function, $anchor));
			}
		}
		usort($inside, function ($a, $b) {
			return $a[0] - $b[0];
		});
		$ranges = array();
		$from = 0;
		foreach ($inside as $range) {
			if ($range[0] > $from) {
				$ranges[] = array($from, $range[0]);
			}
			$from = $range[1];
		}
		$ranges[] = array($from, strlen($source));
		return $ranges;
	}

	if ($path !== null && isset($functions[$path])) {
		$ranges = bs6_function_ranges($source, $functions[$path]);
		$marked = (strpos($source, $marker) === false) ? array() : bs6_marker_ranges($source);
		$all = array_merge($ranges, $marked);
		usort($all, function ($a, $b) {
			return $a[0] - $b[0];
		});
		return $all;
	}

	if (strpos($source, $marker) === false) {
		return array();
	}

	return bs6_marker_ranges($source);
}

/**
 * The file the tools are reading now (relative to pinegrap/), set by the
 * codemod, check_bs6.php and the switch for each file, so
 * bs6_site_output_ranges() can add the file's site functions
 * (bs6_site_functions()). Pass false to clear.
 */
function bs6_current_path($path = null)
{
	static $current = null;

	if ($path === false) {
		$current = null;
	} elseif ($path !== null) {
		$current = str_replace('\\', '/', $path);
	}

	return $current;
}

/**
 * Functions of panel files that print into site pages (called from site
 * renderers): their markup stays Bootstrap 5 like any site output. Found by
 * tests/bs6_site_output_test.php, which fails on a site-called function the
 * codemod would change that is not listed here. The OAuth consent screens
 * are not listed: they print under output_header_secure(), on Bootstrap 6.
 */
function bs6_site_functions()
{
	return array(
		'includes/fn/custom_form.php' => array('pg_cf_validation_script'),
		'includes/fn/design_themes.php' => array('pg_design_looks'),
		'includes/fn/passkeys.php' => array('pg_passkey_account_section', 'pg_passkey_add_form_script'),
		'includes/fn/returns.php' => array('pg_return_actions_html', 'pg_return_badge_class', 'pg_return_form_html', 'pg_returns_list_html'),
	);
}

/**
 * Functions of files shared with the site (bs6_scope_kind() 'shared') that
 * print only panel markup: they are converted with the panel, and every
 * other function of the file is read as site output. A shared function that
 * prints on both sides (liveform's fields and notices, get_calendar(),
 * pg_seo_page_toolbar()) is not listed: it chooses its classes at run time,
 * and its panel-only blocks are listed in bs6_shared_panel_blocks().
 */
function bs6_shared_panel_functions()
{
	return array(
		'chat.php' => array('pg_chat_render_backend_launcher'),
		'includes/fn/auth.php' => array('get_acl_folder_tree', 'pg_login_captcha_screen'),
		'includes/fn/content.php' => array('get_checkboxes_for_items_user_can_edit'),
		'includes/fn/ecommerce.php' => array('pg_key_code_list_cells', 'pg_order_list_status_html'),
		'includes/fn/editor.php' => array('pg_code_editor_attach'),
		'includes/fn/forms.php' => array('get_page_type_checkboxes_and_labels'),
		'includes/fn/legal_documents.php' => array('pg_legal_documents_panel_card'),
		'seo.php' => array(
			'pg_seo_render_impact', 'pg_seo_render_bar', 'pg_seo_render_checklist', 'pg_seo_render_run_buttons',
			'pg_seo_render_group_row', 'pg_seo_render_detail_offcanvas', 'pg_seo_render_badge',
		),
	);
}

/**
 * Blocks of shared functions that print on both sides and choose their
 * markup at run time: the brace block opened by each `<anchor>` inside the
 * named function prints only in the panel and is converted with it; the
 * rest of the function and the file stays site output.
 */
function bs6_shared_panel_blocks()
{
	return array(
		// The calendar screen and the dashboard calendar widget pass the
		// signed-in user; site pages pass none.
		'includes/fn/calendar.php' => array('get_calendar' => 'if ($user) {'),
	);
}

/**
 * Byte ranges, as array(start, end), of the brace blocks opened by $anchor
 * (text ending in `{`) inside the top-level function $function: from the
 * anchor to the matching closing brace.
 */
function bs6_block_ranges($source, $function, $anchor)
{
	$ranges = array();
	$outer = bs6_function_ranges($source, array($function));
	if (!$outer) {
		return $ranges;
	}

	// Offsets of the brace tokens, so braces inside strings are skipped.
	$braces = array();
	$offset = 0;
	foreach (token_get_all($source) as $token) {
		$text = is_array($token) ? $token[1] : $token;
		if ($text === '{' || $text === '}' || (is_array($token) && ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES))) {
			$braces[] = array($offset + strlen($text) - 1, $text === '}' ? -1 : 1);
		}
		$offset += strlen($text);
	}

	list($from, $to) = $outer[0];
	$at = $from;
	while (($at = strpos($source, $anchor, $at)) !== false && $at < $to) {
		$open = $at + strlen($anchor) - 1;
		$depth = 0;
		foreach ($braces as $brace) {
			if ($brace[0] < $open) {
				continue;
			}
			$depth += $brace[1];
			if ($depth === 0) {
				$ranges[] = array($at, $brace[0] + 1);
				break;
			}
		}
		$at = $open + 1;
	}

	return $ranges;
}

/**
 * Byte ranges of the named top-level functions of a PHP source, as
 * array(start, end): from the `function` keyword to the closing brace.
 */
function bs6_function_ranges($source, array $names)
{
	$ranges = array();
	$offset = 0;
	$depth = 0;
	$start = null;
	$name = null;
	$current = null;
	$want_name = false;

	foreach (token_get_all($source) as $token) {
		$text = is_array($token) ? $token[1] : $token;

		if (is_array($token) && $token[0] === T_FUNCTION && $depth === 0) {
			$want_name = true;
			$start = $offset;
		} elseif ($want_name && is_array($token) && $token[0] === T_STRING) {
			$name = $token[1];
			$want_name = false;
		} elseif ($want_name && $text === '(') {
			$want_name = false;
		}

		if ($text === '{' || (is_array($token) && ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES))) {
			if ($depth === 0 && $name !== null) {
				$current = $name;
				$name = null;
			}
			$depth++;
		} elseif ($text === '}') {
			$depth--;
			if ($depth === 0 && $current !== null) {
				if (in_array($current, $names, true)) {
					$ranges[] = array($start, $offset + 1);
				}
				$current = null;
			}
		}

		$offset += strlen($text);
	}

	return $ranges;
}

// Ranges covered by `/* @pg-site-output */` markers (bs6_site_output_ranges()).
function bs6_marker_ranges($source)
{
	$marker = '/* @pg-site-output */';

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

// The PHP code of a .php file as array(start, end) byte ranges: from each
// PHP open tag to its close tag, or to the end of the file.
function bs6_php_code_ranges($source)
{
	$ranges = array();
	$offset = 0;

	while (preg_match('/<\?(?:php\b|=)/', $source, $m, PREG_OFFSET_CAPTURE, $offset)) {
		$start = $m[0][1];
		$end = strpos($source, '?>', $start);
		$end = ($end === false) ? strlen($source) : $end;
		$ranges[] = array($start, $end);
		$offset = $end + 2;
	}

	return $ranges;
}

// True when a lone class string is printed into a class list rather than
// handed to a class API: in PHP code (`$x ? 'btn-success' : 'btn-danger'`,
// a helper's argument; PHP has no class API), or in a parenthesised choice
// joined to a class string (`'btn btn-sm ' + (on ? 'btn-primary' :
// 'btn-outline-secondary')`). Such a string takes the whole v6 class list.
// The argument of a class API call (`classList.add(`, `addClass(`) still
// takes one class.
function bs6_is_markup_string($source, $offset, array $php_code)
{
	$before = substr($source, max(0, $offset - 40), min(40, $offset));

	if (preg_match('/(?:classList\.\w+|[Cc]lass)\(\s*\\?[\'"]$/', $before)) {
		return false;
	}

	if ($php_code && bs6_in_ranges($offset, $php_code)) {
		return true;
	}

	$line_start = strrpos(substr($source, 0, $offset), "\n");
	$line = substr($source, $line_start === false ? 0 : $line_start + 1, $offset - ($line_start === false ? 0 : $line_start + 1));

	return (bool) preg_match('/[\'"]\s*\+\s*\([^()]*$/', $line);
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
function bs6_convert_source($source, $lines = null, $strings = false)
{
	$out = '';
	$position = 0;
	$changed = 0;
	$kept = 0;
	$reviews = array();
	$site_output = bs6_site_output_ranges($source);
	$path = bs6_current_path();
	$php_code = ($path !== null && substr($path, -4) === '.php') ? bs6_php_code_ranges($source) : array();

	foreach (bs6_find_class_lists($source, $strings) as $list) {
		$line = bs6_line_of($source, $list['offset']);

		if ($lines !== null && !isset($lines[$line])) {
			continue;
		}

		if ($site_output && bs6_in_ranges($list['offset'], $site_output)) {
			$kept++;
			continue;
		}

		$one_each = in_array($list['kind'], array('class-list', 'jquery'), true)
			|| ($list['kind'] === 'string' && strpos(trim($list['value']), ' ') === false && !bs6_is_markup_string($source, $list['offset'], $php_code));

		// A lone `text-bg-<colour>` string is a status badge's colour (code
		// adds it after 'badge '): the badge's theme class.
		if ($list['kind'] === 'string' && preg_match('/^text-bg-(' . implode('|', bs6_theme_colours()) . ')$/', $list['value'], $m)) {
			$result = array('value' => 'theme-' . $m[1], 'changed' => 1, 'reviews' => array());
		} else {
			$result = bs6_convert_class_list($list['value'], $one_each);
		}

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

	// includes/fn/output.php is not among them: every class list in it is
	// panel markup (the panel head, the main and account menus, the footer,
	// list screens' helpers); output_toolbar() prints nothing.
	$shared = array(
		'get_login.php', 'liveform.class.php', 'toolbar.php', 'chat.php', 'seo.php',
		'includes/fn/ecommerce.php', 'includes/fn/forms.php', 'includes/fn/editor.php',
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
	// output, the ones added later included. layout_convert.php writes the
	// elements of a custom_form widget tree.
	$site_fn = array(
		'consent.php', 'language_redirect.php', 'layout_convert.php', 'maintenance_mode.php', 'meta_tags.php',
		'reviews.php', 'visitor_texts.php',
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

// The visual editor's files the codemod may rewrite: style_designer.js
// outside the code marked `/* @pg-site-output */`, and the editor screen
// (designer_screen.php), whose markup is all panel chrome printed under
// pg_page_shell().
function bs6_marked_editor_files()
{
	return array('assets/js/style_designer.js', 'includes/designer_screen.php');
}

// Whether --all, --diff and a named path rewrite this file without --force.
function bs6_codemod_converts($path)
{
	$kind = bs6_scope_kind($path);

	$path = str_replace('\\', '/', $path);

	return $kind === 'panel' || ($kind === 'editor' && in_array($path, bs6_marked_editor_files(), true))
		|| ($kind === 'shared' && (isset(bs6_shared_panel_functions()[$path]) || isset(bs6_shared_panel_blocks()[$path])));
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
