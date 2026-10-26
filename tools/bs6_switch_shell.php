<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The dialog shell of the Bootstrap 6 switch, run by tools/bs6_switch.php:
 * modal markup to one <dialog class="dialog">, offcanvas markup to
 * <dialog class="drawer">, dropdown classes to the v6 menu, and the data
 * attributes and selector strings of the three renamed components.
 *
 * Markup is found as text, in PHP, HTML and JavaScript strings alike: a tag
 * is matched with its closing tag by counting <div> and </div>, and a
 * conversion is made only when the tags it joins sit next to each other
 * (nothing but whitespace, string quotes and joins between them). Anything
 * else is left as it is and stays on check_bs6.php's list for a hand.
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

// True when $text only joins two pieces of markup: whitespace, string
// quotes and concatenation, `\n` escapes, PHP open and close tags, echo,
// `$x .=`, HTML comments.
function bs6_shell_is_glue($text)
{
	$text = preg_replace('/<!--.*?-->/s', '', $text);
	$text = preg_replace('/<\?php\b|<\?=|\?>|\becho\b|\$[A-Za-z_][A-Za-z0-9_]*\s*\.?=|\\\\n|\\\\t|\\\\(?=\r?\n)/', '', $text);

	return (bool) preg_match('/^[\s\'"+.;,`]*$/', $text);
}

// The `<div` openings in $source from $from on, as array(start, end) with
// end after the tag's '>'. Tags whose end cannot be found are skipped.
function bs6_shell_open_tags($source, $tag, $from = 0)
{
	$tags = array();

	if (preg_match_all('/<' . $tag . '(?=[\s>\\\\])/i', $source, $matches, PREG_OFFSET_CAPTURE, $from)) {
		foreach ($matches[0] as $match) {
			$end = bs6_shell_tag_end($source, $match[1]);
			if ($end !== null) {
				$tags[] = array($match[1], $end);
			}
		}
	}

	return $tags;
}

// End offset (after '>') of the tag starting at $start. A '>' after '-',
// '=' or '?' (PHP arrow, end of a PHP block) or inside brackets is skipped.
function bs6_shell_tag_end($source, $start)
{
	$length = strlen($source);
	$depth = 0;

	for ($i = $start + 1; $i < $length; $i++) {
		$char = $source[$i];

		if ($char === '(') {
			$depth++;
		} elseif ($char === ')') {
			$depth = max(0, $depth - 1);
		} elseif ($char === '<' && $depth === 0 && substr($source, $i, 2) !== '<?') {
			return null;
		} elseif ($char === '>' && $depth === 0 && strpos('-=?', $source[$i - 1]) === false) {
			return $i + 1;
		}
	}

	return null;
}

// The closing tag that matches an opening tag ending at $open_end, as
// array(start, end), or null. `<\/div>` (escaped in a script) counts.
function bs6_shell_close_of($source, $open_end, $tag = 'div')
{
	$depth = 1;

	if (!preg_match_all('#<' . $tag . '(?=[\s>\\\\])|<\\\\?/' . $tag . '\s*>#i', $source, $matches, PREG_OFFSET_CAPTURE, $open_end)) {
		return null;
	}

	foreach ($matches[0] as $match) {
		if ($match[0][1] === '/' || $match[0][1] === '\\') {
			if (--$depth === 0) {
				return array($match[1], $match[1] + strlen($match[0]));
			}
		} else {
			$depth++;
		}
	}

	return null;
}

// The class attribute of a tag's text: array(offset in the tag, length,
// value), or null. Escaped quotes (`class=\"…\"`) count.
function bs6_shell_class_attr($tag_text)
{
	if (!preg_match('/\sclass=(\\\\?)(["\'])(.*?)\1\2/s', $tag_text, $m, PREG_OFFSET_CAPTURE)) {
		return null;
	}

	return array($m[3][1], strlen($m[3][0]), $m[3][0]);
}

// Class tokens of a list (code pieces are tokens too).
function bs6_shell_tokens($value)
{
	return preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY);
}

// The attributes of a tag besides its class, as name => full text.
function bs6_shell_other_attrs($tag_text)
{
	$attrs = array();
	$inner = preg_replace('/^<[a-zA-Z]+|\\\\?\/?>$/', '', $tag_text);

	if (preg_match_all('/\s([a-zA-Z][a-zA-Z0-9:_-]*)(=(\\\\?)(["\']).*?\3\4)?/s', $inner, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $m) {
			if (strtolower($m[1]) !== 'class') {
				$attrs[strtolower($m[1])] = $m[0];
			}
		}
	}

	return $attrs;
}

// v6 classes for the classes of a v5 .modal-dialog box.
function bs6_shell_dialog_box_classes($tokens)
{
	$out = array();

	foreach ($tokens as $token) {
		if (in_array($token, array('modal-dialog', 'modal-dialog-centered'), true)) {
			// A v6 dialog is centred by its margin: auto.
			continue;
		}

		if (preg_match('/^modal-(sm|lg|xl|fullscreen)$/', $token, $m)) {
			$out[] = 'dialog-' . $m[1];
		} elseif ($token === 'modal-dialog-scrollable') {
			$out[] = 'dialog-scrollable';
		} elseif (preg_match('/^modal-fullscreen-(sm|md|lg|xl|xxl)-down$/', $token, $m)) {
			$out[] = ($m[1] === 'xxl' ? '2xl' : $m[1]) . '-down:dialog-fullscreen';
		} else {
			$out[] = $token;
		}
	}

	return $out;
}

// Removes the text from $start to $end, and the whole line when the text is
// alone on it.
function bs6_shell_cut_range($source, $start, $end)
{
	// A whole string literal ('<div class="modal-dialog">') goes with its
	// quotes and the join after or before it, not left as ''.
	$quote = ($start > 0) ? $source[$start - 1] : '';

	if (($quote === '\'' || $quote === '"') && $end < strlen($source) && $source[$end] === $quote) {
		$from = $start - 1;
		$to = $end + 1;

		if (preg_match('/^\s*[+.]\s*/', substr($source, $to, 200), $m)) {
			return array($from, $to + strlen($m[0]));
		}

		if (preg_match('/\s*[+.]\s*$/', substr($source, max(0, $from - 200), min(200, $from)), $m)) {
			return array($from - strlen($m[0]), $to);
		}
	}

	$line_start = strrpos(substr($source, 0, $start), "\n");
	$line_start = ($line_start === false) ? 0 : $line_start + 1;
	$line_end = strpos($source, "\n", $end);
	$line_end = ($line_end === false) ? strlen($source) : $line_end;

	// A trailing backslash continues a script string over the line.
	if (trim(substr($source, $line_start, $start - $line_start)) === '' && in_array(trim(substr($source, $end, $line_end - $end)), array('', '\\'), true)) {
		return array($line_start, min(strlen($source), $line_end + 1));
	}

	return array($start, $end);
}

// Cuts a box and its first child box, opening and closing tags, whole lines
// where they are alone on them.
function bs6_shell_cut_pair($source, array $box, array $child, array $child_close, array $box_close)
{
	$edits = array();
	$cut = bs6_shell_cut_range($source, $box[0], $box[1]);

	if ($cut[1] > $child[0] || trim(substr($source, $box[1], $child[0] - $box[1])) === '') {
		$edits[] = array_merge(bs6_shell_cut_range($source, $box[0], $child[1]), array(''));
	} else {
		$edits[] = array_merge($cut, array(''));
		$edits[] = array_merge(bs6_shell_cut_range($source, $child[0], $child[1]), array(''));
	}

	$cut_close = bs6_shell_cut_range($source, $child_close[0], $box_close[1]);
	$edits[] = array_merge($cut_close, array(''));

	return $edits;
}

// A tag's text with $old replaced by $new in its class list, or a class
// attribute holding $new added after the tag name.
function bs6_shell_set_class($tag_text, array $tokens, $old, $new)
{
	$class = bs6_shell_class_attr($tag_text);

	if ($class === null) {
		$quote = (strpos($tag_text, '=\\"') !== false) ? '\\"' : '"';
		return preg_replace('/^<([a-zA-Z]+)/', '<$1 class=' . $quote . $new . $quote, $tag_text);
	}

	$out = array();
	foreach ($tokens as $token) {
		$out[] = ($token === $old) ? $new : $token;
	}

	if (!in_array($new, $out, true)) {
		array_unshift($out, $new);
	}

	return substr($tag_text, 0, $class[0]) . implode(' ', array_values(array_unique($out))) . substr($tag_text, $class[0] + $class[1]);
}

// Applies edits array(start, end, text), none overlapping, from the end.
function bs6_shell_apply($source, array $edits)
{
	usort($edits, function ($a, $b) {
		return $b[0] - $a[0];
	});

	$last = PHP_INT_MAX;

	foreach ($edits as $edit) {
		if ($edit[1] > $last) {
			continue;
		}
		$source = substr($source, 0, $edit[0]) . $edit[2] . substr($source, $edit[1]);
		$last = $edit[0];
	}

	return $source;
}

/**
 * Rewrites a root tag as a <dialog>: the tag name, the class list (the
 * given tokens), and the attributes a native dialog does without
 * (tabindex="-1", aria-hidden, role="dialog", aria-modal) dropped. $extra
 * attributes (from the dropped boxes) are added when the root lacks them.
 */
function bs6_shell_dialog_tag($tag_text, array $tokens, array $extra = array())
{
	$class = bs6_shell_class_attr($tag_text);
	$value = implode(' ', array_values(array_unique($tokens)));
	$out = substr($tag_text, 0, $class[0]) . $value . substr($tag_text, $class[0] + $class[1]);
	$out = preg_replace('/^<div/i', '<dialog', $out);
	$out = preg_replace('/\s(tabindex=(\\\\?)(["\'])-1\2\3|aria-hidden=(\\\\?)(["\'])true\4\5|aria-modal=(\\\\?)(["\'])true\6\7|role=(\\\\?)(["\'])dialog\8\9)/', '', $out);

	$have = bs6_shell_other_attrs($out);
	$add = '';

	foreach ($extra as $name => $text) {
		if (!isset($have[$name]) && $name !== 'role') {
			$add .= $text;
		}
	}

	if ($add !== '') {
		$out = preg_replace('/(\\\\?\/?>)$/', $add . '$1', $out);
	}

	return $out;
}

/**
 * Modal markup to a v6 dialog. For each .modal-dialog box whose .modal-content
 * box follows it and whose .modal root (a tag, or in a script the element
 * an `el('div', 'modal …')` call made for `x.innerHTML = '…'`) is next to it:
 * the root becomes <dialog class="dialog …"> with the box classes (sizes
 * renamed, centring dropped), and both boxes and their closing tags go.
 */
function bs6_switch_dialogs($source)
{
	$site_output = bs6_site_output_ranges($source);
	$edits = array();
	$left = 0;
	$made = 0;

	foreach (bs6_shell_open_tags($source, 'div') as $box) {
		$box_text = substr($source, $box[0], $box[1] - $box[0]);
		$box_class = bs6_shell_class_attr($box_text);

		if ($box_class === null || !in_array('modal-dialog', bs6_shell_tokens($box_class[2]), true)) {
			continue;
		}

		if ($site_output && bs6_in_ranges($box[0], $site_output)) {
			continue;
		}

		$converted = bs6_shell_dialog_at($source, $box);

		if ($converted === null) {
			$left++;
			continue;
		}

		$edits = array_merge($edits, $converted);
		$made++;
	}

	if ($made === 0) {
		return array('source' => $source, 'status' => 'done', 'left' => $left);
	}

	return array('source' => bs6_shell_apply($source, $edits), 'status' => 'applied', 'left' => $left);
}

// The edits for one modal whose .modal-dialog tag is $box, or null. The box
// holds a .modal-content box, a <form class="modal-content">, or a <form>
// whose .modal-content box comes after its hidden fields. A form stays,
// with `d-contents` so the dialog's header, body and footer lay out as its
// own children.
function bs6_shell_dialog_at($source, array $box)
{
	$box_close = bs6_shell_close_of($source, $box[1]);

	if ($box_close === null || !preg_match('/<(div|form)(?=[\s>\\\\])/i', $source, $first, PREG_OFFSET_CAPTURE, $box[1])
		|| !bs6_shell_is_glue(substr($source, $box[1], $first[0][1] - $box[1]))) {
		return null;
	}

	$child_tag = strtolower($first[1][0]);
	$child = array($first[0][1], bs6_shell_tag_end($source, $first[0][1]));

	if ($child[1] === null) {
		return null;
	}

	$child_text = substr($source, $child[0], $child[1] - $child[0]);
	$child_class = bs6_shell_class_attr($child_text);
	$child_tokens = $child_class === null ? array() : bs6_shell_tokens($child_class[2]);
	$child_close = bs6_shell_close_of($source, $child[1], $child_tag);

	if ($child_close === null || $child_close[1] > $box_close[0]
		|| !bs6_shell_is_glue(substr($source, $child_close[1], $box_close[0] - $child_close[1]))) {
		return null;
	}

	$box_text = substr($source, $box[0], $box[1] - $box[0]);
	$box_class = bs6_shell_class_attr($box_text);
	$classes = bs6_shell_dialog_box_classes(bs6_shell_tokens($box_class[2]));
	$extra = bs6_shell_other_attrs($box_text);
	$edits = array();

	if ($child_tag === 'div') {
		if (!in_array('modal-content', $child_tokens, true)) {
			return null;
		}

		foreach ($child_tokens as $token) {
			if ($token !== 'modal-content') {
				$classes[] = $token;
			}
		}

		$extra = array_merge($extra, bs6_shell_other_attrs($child_text));
		$edits = array_merge($edits, bs6_shell_cut_pair($source, $box, $child, $child_close, $box_close));
	} else {
		$inner = null;

		if (!in_array('modal-content', $child_tokens, true)) {
			// <form> … <div class="modal-content"> … </div></form>
			foreach (bs6_shell_open_tags($source, 'div', $child[1]) as $tag) {
				if ($tag[0] >= $child_close[0]) {
					break;
				}
				$tag_class = bs6_shell_class_attr(substr($source, $tag[0], $tag[1] - $tag[0]));
				if ($tag_class !== null && in_array('modal-content', bs6_shell_tokens($tag_class[2]), true)) {
					$inner = $tag;
					$inner_class = $tag_class;
					break;
				}
			}

			$inner_close = $inner !== null ? bs6_shell_close_of($source, $inner[1]) : null;

			if ($inner === null || $inner_close === null || $inner_close[1] > $child_close[0]
				|| !bs6_shell_is_glue(substr($source, $inner_close[1], $child_close[0] - $inner_close[1]))
				|| bs6_shell_other_attrs(substr($source, $inner[0], $inner[1] - $inner[0]))) {
				return null;
			}

			foreach (bs6_shell_tokens($inner_class[2]) as $token) {
				if ($token !== 'modal-content') {
					$classes[] = $token;
				}
			}

			$edits[] = array_merge(bs6_shell_cut_range($source, $inner[0], $inner[1]), array(''));
			$edits[] = array_merge(bs6_shell_cut_range($source, $inner_close[0], $inner_close[1]), array(''));
		}

		$edits[] = array($child[0], $child[1], bs6_shell_set_class($child_text, $child_tokens, 'modal-content', 'd-contents'));
		$edits[] = array_merge(bs6_shell_cut_range($source, $box[0], $box[1]), array(''));
		$edits[] = array_merge(bs6_shell_cut_range($source, $box_close[0], $box_close[1]), array(''));
	}

	// The root as a tag right before the box.
	$before = substr($source, 0, $box[0]);
	$root = null;

	if (preg_match_all('/<div(?=[\s>\\\\])/i', $before, $openings, PREG_OFFSET_CAPTURE)) {
		$last = end($openings[0]);
		$end = bs6_shell_tag_end($source, $last[1]);

		if ($end !== null && $end <= $box[0] && bs6_shell_is_glue(substr($source, $end, $box[0] - $end))) {
			$root = array($last[1], $end);
		}
	}

	if ($root !== null) {
		$root_text = substr($source, $root[0], $root[1] - $root[0]);
		$root_class = bs6_shell_class_attr($root_text);

		if ($root_class === null || !in_array('modal', bs6_shell_tokens($root_class[2]), true)) {
			return null;
		}

		$root_close = bs6_shell_close_of($source, $root[1]);

		if ($root_close === null || $root_close[0] < $box_close[1] || !bs6_shell_is_glue(substr($source, $box_close[1], $root_close[0] - $box_close[1]))) {
			return null;
		}

		if (in_array('show', bs6_shell_tokens($root_class[2]), true)) {
			return bs6_shell_open_sheet($source, $root, $root_text, $root_class, $box, $box_close, $classes, $extra);
		}

		$tokens = array('dialog');
		foreach (bs6_shell_tokens($root_class[2]) as $token) {
			if (!in_array($token, array('modal', 'fade', 'show'), true)) {
				$tokens[] = $token;
			}
		}

		$edits[] = array($root[0], $root[1], bs6_shell_dialog_tag($root_text, array_merge($tokens, $classes), $extra));
		$edits[] = array($root_close[0], $root_close[1], (strpos(substr($source, $root_close[0], 3), '\\') !== false ? '<\/' : '</') . 'dialog>');

		return $edits;
	}

	// In a script: `name.innerHTML = '<div class="modal-dialog …` and the
	// element made by `name = el('div', 'modal …')` earlier in the file. The
	// box classes go into a string literal there: code among them cannot.
	foreach ($classes as $token) {
		if (!bs6_is_literal_token($token)) {
			return null;
		}
	}

	if ($extra || !preg_match('/([A-Za-z_$][A-Za-z0-9_$]*)\.innerHTML\s*=\s*[\'"]$/', $before, $assign)) {
		return null;
	}

	$pattern = '/\b' . preg_quote($assign[1], '/') . '\s*=\s*el\(\s*([\'"])div\1\s*,\s*([\'"])modal\b([^\'"]*)\2\s*\)/';

	if (!preg_match_all($pattern, $before, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		return bs6_shell_dialog_created($before, $assign[1], $classes, $edits);
	}

	$call = end($calls);
	$tokens = array('dialog');
	foreach (bs6_shell_tokens($call[3][0]) as $token) {
		if (!in_array($token, array('fade', 'show'), true)) {
			$tokens[] = $token;
		}
	}

	$text = $assign[1] . ' = el(' . $call[1][0] . 'dialog' . $call[1][0] . ', ' . $call[2][0] . implode(' ', array_values(array_unique(array_merge($tokens, $classes)))) . $call[2][0] . ')';
	$edits[] = array($call[0][1], $call[0][1] + strlen($call[0][0]), $text);

	return $edits;
}

// In a script: `name = document.createElement('div')` and then
// `name.className = 'modal …'` before `name.innerHTML = '…'`.
function bs6_shell_dialog_created($before, $name, array $classes, array $edits)
{
	$var = preg_quote($name, '/');

	if (!preg_match_all('/\b' . $var . '\s*=\s*document\.createElement\(\s*([\'"])div\1\s*\)/', $before, $creates, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)
		|| !preg_match_all('/\b' . $var . '\.className\s*=\s*([\'"])modal\b([^\'"]*)\1/', $before, $names, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		return null;
	}

	$create = end($creates);
	$class = end($names);

	if ($class[0][1] < $create[0][1]) {
		return null;
	}

	$tokens = array('dialog');
	foreach (bs6_shell_tokens($class[2][0]) as $token) {
		if (!in_array($token, array('fade', 'show'), true)) {
			$tokens[] = $token;
		}
	}

	$quote = $create[1][0];
	$edits[] = array($create[0][1], $create[0][1] + strlen($create[0][0]), $name . ' = document.createElement(' . $quote . 'dialog' . $quote . ')');
	$edits[] = array($class[0][1], $class[0][1] + strlen($class[0][0]), $name . '.className = ' . $class[1][0] . implode(' ', array_values(array_unique(array_merge($tokens, $classes)))) . $class[1][0]);

	return $edits;
}

// v6 classes of an offcanvas root's class list.
function bs6_shell_drawer_classes(array $tokens)
{
	$out = array();

	foreach ($tokens as $token) {
		if ($token === 'offcanvas') {
			$out[] = 'drawer';
		} elseif (preg_match('/^offcanvas-(start|end|top|bottom)$/', $token, $m)) {
			$out[] = 'drawer-' . $m[1];
		} elseif (preg_match('/^offcanvas-(sm|md|lg|xl|xxl)$/', $token, $m)) {
			$out[] = ($m[1] === 'xxl' ? '2xl' : $m[1]) . ':drawer';
		} elseif ($token !== 'show' && $token !== 'showing') {
			$out[] = $token;
		}
	}

	if (!in_array('drawer', $out, true)) {
		array_unshift($out, 'drawer');
	}

	return array_values(array_unique($out));
}

/**
 * Offcanvas markup to a v6 drawer: a `<div class="offcanvas …">` tag becomes
 * `<dialog class="drawer …">` and its closing tag `</dialog>`; in a script,
 * `el('div', 'offcanvas …')` becomes `el('dialog', 'drawer …')`.
 */
function bs6_switch_drawers($source)
{
	$site_output = bs6_site_output_ranges($source);
	$edits = array();
	$left = 0;

	foreach (bs6_shell_open_tags($source, 'div') as $tag) {
		$text = substr($source, $tag[0], $tag[1] - $tag[0]);
		$class = bs6_shell_class_attr($text);

		if ($class === null || !preg_grep('/^offcanvas(-(sm|md|lg|xl|xxl))?$/', bs6_shell_tokens($class[2]))) {
			continue;
		}

		if ($site_output && bs6_in_ranges($tag[0], $site_output)) {
			continue;
		}

		$close = bs6_shell_close_of($source, $tag[1]);

		if ($close === null) {
			$left++;
			continue;
		}

		$edits[] = array($tag[0], $tag[1], bs6_shell_dialog_tag($text, bs6_shell_drawer_classes(bs6_shell_tokens($class[2]))));
		$edits[] = array($close[0], $close[1], (strpos(substr($source, $close[0], 3), '\\') !== false ? '<\/' : '</') . 'dialog>');
	}

	if (preg_match_all('/\bel\(\s*([\'"])div\1\s*,\s*([\'"])(offcanvas\b[^\'"]*)\2/', $source, $calls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
		foreach ($calls as $call) {
			if ($site_output && bs6_in_ranges($call[0][1], $site_output)) {
				continue;
			}

			$q = $call[1][0];
			$edits[] = array($call[0][1], $call[0][1] + strlen($call[0][0]), 'el(' . $q . 'dialog' . $q . ', ' . $call[2][0] . implode(' ', bs6_shell_drawer_classes(bs6_shell_tokens($call[3][0]))) . $call[2][0]);
		}
	}

	if (!$edits) {
		return array('source' => $source, 'status' => 'done', 'left' => $left);
	}

	return array('source' => bs6_shell_apply($source, $edits), 'status' => 'applied', 'left' => $left);
}

/**
 * Names of the three renamed components outside their root markup: class
 * names in markup and selector strings (`modal-body` to `dialog-body`,
 * `offcanvas-title` to `drawer-title`, `dropdown-item` to `menu-item`), the
 * data attributes (`data-bs-toggle="modal"` to "dialog", "offcanvas" to
 * "drawer", "dropdown" to "menu"), setAttribute calls naming them, and
 * open-state selectors (`.modal.show` to `.dialog[open]`).
 *
 * `dropdown-toggle` becomes `pg-menu-caret` (v6 draws no caret; the panel
 * stylesheet draws v5's), `dropdown-item-sm` `pg-menu-item-sm`, the
 * `dropdown` wrapper `pg-menu-wrap`. A menu aligned to its end
 * (`dropdown-menu-end`) gives its toggle `data-bs-placement="bottom-end"`,
 * since v6 places a menu from its toggle's options (bs6_switch_menus()).
 */
function bs6_switch_component_names($source)
{
	$site_output = bs6_site_output_ranges($source);
	$count = 0;

	$renames = array(
		'/(?<![\w-])modal-(header|body|footer|title)(?![\w-])/' => 'dialog-$1',
		'/(?<![\w-])offcanvas-(header|body|footer|title)(?![\w-])/' => 'drawer-$1',
		'/(?<![\w-])dropdown-item-text(?![\w-])/' => 'menu-text',
		'/(?<![\w-])dropdown-item-sm(?![\w-])/' => 'pg-menu-item-sm',
		'/(?<![\w-])dropdown-(item|divider|header)(?![\w-])/' => 'menu-$1',
		'/(?<![\w-])dropdown-menu(?![\w-])/' => 'menu',
		'/(?<![\w-])dropdown-toggle-split(?![\w-])/' => 'menu-toggle-split',
		'/(?<![\w-])dropdown-toggle(?![\w-])/' => 'pg-menu-caret',
		'/\.modal\.show\s+\.modal-content(?![\w-])/' => '.dialog[open]',
		'/\.modal\.show(?![\w-])/' => '.dialog[open]',
		'/\.offcanvas\.show(?![\w-])/' => '.drawer[open]',
		'/(?<=[\'"\s,(>~+])\.modal(?![\w(-])/' => '.dialog',
		'/(?<=[\'"\s,(>~+])\.offcanvas(?![\w(-])/' => '.drawer',
		'/(data-bs-(?:toggle|dismiss)=\\\\?["\'])modal(?=\\\\?["\'])/' => '$1dialog',
		'/(data-bs-(?:toggle|dismiss)=\\\\?["\'])offcanvas(?=\\\\?["\'])/' => '$1drawer',
		'/(data-bs-toggle=\\\\?["\'])dropdown(?=\\\\?["\'])/' => '$1menu',
		'/(setAttribute\(\s*([\'"])data-bs-(?:toggle|dismiss)\2\s*,\s*([\'"]))modal(?=\3)/' => '$1dialog',
		'/(setAttribute\(\s*([\'"])data-bs-(?:toggle|dismiss)\2\s*,\s*([\'"]))offcanvas(?=\3)/' => '$1drawer',
		'/(setAttribute\(\s*([\'"])data-bs-toggle\2\s*,\s*([\'"]))dropdown(?=\3)/' => '$1menu',
	) + bs6_shell_variable_renames();

	$pieces = bs6_shell_split_ranges($source, $site_output);

	foreach ($pieces as $i => $piece) {
		if ($piece[1]) {
			continue;
		}

		foreach ($renames as $pattern => $replacement) {
			$piece[0] = preg_replace($pattern, $replacement, $piece[0], -1, $n);
			$count += $n;
		}

		$pieces[$i] = $piece;
	}

	$out = '';
	foreach ($pieces as $piece) {
		$out .= $piece[0];
	}

	return array('source' => $out, 'status' => $count > 0 ? 'applied' : 'done', 'left' => 0);
}

// $source cut at the site-output ranges: array(text, is site output).
function bs6_shell_split_ranges($source, $ranges)
{
	if (!$ranges) {
		return array(array($source, false));
	}

	$pieces = array();
	$at = 0;

	foreach ($ranges as $range) {
		$pieces[] = array(substr($source, $at, $range[0] - $at), false);
		$pieces[] = array(substr($source, $range[0], $range[1] - $range[0]), true);
		$at = $range[1];
	}

	$pieces[] = array(substr($source, $at), false);

	return $pieces;
}

/**
 * Dropdown markup in class lists: `dropdown-menu` becomes `menu`; its end or
 * start alignment, and a `dropup`/`dropend`/`dropstart` wrapper, become
 * `data-bs-placement` on the toggle before it (v6 reads placement from the
 * toggle) and `pg-menu-end`/`pg-menu-start` on the menu; the wrappers
 * (`dropdown`, `dropup`, …) become `pg-menu-wrap`. A menu whose toggle is
 * not found in the text before it is counted as left for a hand.
 */
function bs6_switch_menus($source)
{
	$site_output = bs6_site_output_ranges($source);
	$wrappers = array('dropdown', 'dropup', 'dropend', 'dropstart', 'dropup-center', 'dropdown-center');
	$edits = array();
	$left = 0;
	$wrap_at = array();

	foreach (bs6_find_class_lists($source) as $list) {
		if ($site_output && bs6_in_ranges($list['offset'], $site_output)) {
			continue;
		}

		$tokens = bs6_shell_tokens($list['value']);

		foreach (array_intersect($tokens, $wrappers) as $wrapper) {
			$wrap_at[] = array($list['offset'], $wrapper);
		}

		if (!array_intersect($tokens, array_merge($wrappers, array('dropdown-menu')))) {
			continue;
		}

		$side = null;
		$out = array();

		foreach ($tokens as $token) {
			if (in_array($token, $wrappers, true)) {
				$out[] = 'pg-menu-wrap';
			} elseif ($token === 'dropdown-menu') {
				$out[] = 'menu';
			} elseif (in_array($token, array('dropdown-menu-end', 'dropdown-menu-right'), true)) {
				// The class stays for the panel's own rules on a menu shown
				// without Floating UI (data-bs-display="static").
				$side = 'end';
				$out[] = 'pg-menu-end';
			} elseif (in_array($token, array('dropdown-menu-start', 'dropdown-menu-left'), true)) {
				$side = 'start';
				$out[] = 'pg-menu-start';
			} else {
				$out[] = $token;
			}
		}

		if (in_array('menu', $out, true)) {
			$placement = bs6_shell_menu_placement($source, $list['offset'], $wrap_at, $side);

			if ($placement !== null) {
				$toggle = bs6_shell_menu_toggle($source, $list['offset']);

				if ($toggle === null) {
					// No toggle in sight: the placement is left for a hand.
					$left++;
				} elseif ($toggle['placement'] === false) {
					$edits[] = array($toggle['at'], $toggle['at'], ' data-bs-placement=' . $toggle['quote'] . $placement . $toggle['quote']);
				}
			}
		}

		$edits[] = array($list['offset'], $list['offset'] + $list['length'], bs6_shell_keep_spacing($list['value'], array_values(array_filter(array_unique($out), 'strlen'))));
	}

	if (!$edits) {
		return array('source' => $source, 'status' => 'done', 'left' => 0);
	}

	return array('source' => bs6_shell_apply($source, $edits), 'status' => 'applied', 'left' => $left);
}

// A class list's new tokens, keeping its value as written when only spaces
// differ (a list with code in it keeps its own spacing where it can).
function bs6_shell_keep_spacing($value, array $tokens)
{
	$lead = preg_match('/^\s+/', $value, $m) ? $m[0] : '';
	$trail = preg_match('/\s+$/', $value, $m) ? $m[0] : '';

	return $lead . implode(' ', $tokens) . $trail;
}

// The Floating UI placement for a menu at $offset: from its alignment and
// the nearest wrapper before it. Null for the default (bottom-start).
function bs6_shell_menu_placement($source, $offset, array $wrap_at, $side)
{
	$wrapper = 'dropdown';

	foreach ($wrap_at as $wrap) {
		if ($wrap[0] < $offset && $offset - $wrap[0] < 3000) {
			$wrapper = $wrap[1];
		}
	}

	$end = ($side === 'end');

	switch ($wrapper) {
		case 'dropup':
			return $end ? 'top-end' : 'top-start';
		case 'dropup-center':
			return 'top';
		case 'dropdown-center':
			return 'bottom';
		case 'dropend':
			return 'end-start';
		case 'dropstart':
			return 'start-start';
	}

	return $end ? 'bottom-end' : null;
}

// The toggle before a menu at $offset: the last `data-bs-toggle="dropdown"`
// (or "menu") within 2,000 bytes with no other menu between. Returns
// array('at' => offset after the attribute, 'quote', 'placement' => whether
// the tag already has one) or null.
function bs6_shell_menu_toggle($source, $offset)
{
	$from = max(0, $offset - 2000);
	$before = substr($source, $from, $offset - $from);

	if (!preg_match_all('/data-bs-toggle=(\\\\?["\'])(dropdown|menu)\1/', $before, $matches, PREG_OFFSET_CAPTURE)) {
		return null;
	}

	$last = end($matches[0]);
	$quote = $matches[1][count($matches[1]) - 1][0];
	$after = substr($before, $last[1]);

	if (preg_match('/(?<![\w-])(dropdown-menu|menu)(?![\w-])\s*["\\\\]/', $after)) {
		return null;
	}

	$tag_start = strrpos(substr($before, 0, $last[1]), '<');
	$tag_text = $tag_start === false ? '' : substr($before, $tag_start, $last[1] - $tag_start + 200);

	return array(
		'at' => $from + $last[1] + strlen($last[0]),
		'quote' => $quote,
		'placement' => (bool) preg_match('/^[^>]*data-bs-placement=/', $tag_text),
	);
}

// Component variables v6 renames: patterns for preg_replace().
function bs6_shell_variable_renames()
{
	return array(
		'/--bs-dropdown-link-(hover|active|disabled)-/' => '--bs-menu-item-$1-',
		'/--bs-dropdown-header-/' => '--bs-menu-header-',
		'/--bs-dropdown-/' => '--bs-menu-',
		'/--bs-offcanvas-/' => '--bs-drawer-',
		'/--bs-modal-/' => '--bs-dialog-',
	);
}

// The component variables of a stylesheet (bs6_shell_variable_renames()).
function bs6_switch_component_variables($source)
{
	$count = 0;

	foreach (bs6_shell_variable_renames() as $pattern => $replacement) {
		$source = preg_replace($pattern, $replacement, $source, -1, $n);
		$count += $n;
	}

	return array('source' => $source, 'status' => $count > 0 ? 'applied' : 'done', 'left' => 0);
}

/**
 * A modal printed open (`modal fade show` with `display:block`, shown with no
 * script): the root stays a <div>, now `.pg-open-sheet` (a fixed scrim, in
 * the shell rules), the box becomes `<dialog class="dialog …" open>`, a
 * non-modal dialog shown where it stands; the .modal-content box goes.
 */
function bs6_shell_open_sheet($source, array $root, $root_text, array $root_class, array $box, array $box_close, array $classes, array $extra)
{
	$content = bs6_shell_open_tags($source, 'div', $box[1]);

	if (!$content || $extra) {
		return null;
	}

	$content = $content[0];
	$content_class = bs6_shell_class_attr(substr($source, $content[0], $content[1] - $content[0]));
	$content_close = bs6_shell_close_of($source, $content[1]);

	if ($content_class === null || !in_array('modal-content', bs6_shell_tokens($content_class[2]), true) || $content_close === null
		|| !bs6_shell_is_glue(substr($source, $box[1], $content[0] - $box[1]))
		|| !bs6_shell_is_glue(substr($source, $content_close[1], $box_close[0] - $content_close[1]))) {
		return null;
	}

	$tokens = array();
	foreach (bs6_shell_tokens($root_class[2]) as $token) {
		if (!in_array($token, array('modal', 'fade', 'show'), true)) {
			$tokens[] = $token;
		}
	}
	array_unshift($tokens, 'pg-open-sheet');

	$root_new = substr($root_text, 0, $root_class[0]) . implode(' ', $tokens) . substr($root_text, $root_class[0] + $root_class[1]);
	$root_new = preg_replace('/\s(tabindex=(\\\\?)(["\'])-1\2\3|role=(\\\\?)(["\'])dialog\4\5|aria-modal=(\\\\?)(["\'])true\6\7)/', '', $root_new);
	$root_new = preg_replace('/display:\s*block;?\s*/', '', $root_new);

	$box_text = substr($source, $box[0], $box[1] - $box[0]);
	$box_class = bs6_shell_class_attr($box_text);
	$quote = (strpos($box_text, '=\\"') !== false) ? '\\"' : '"';

	foreach (bs6_shell_tokens($content_class[2]) as $token) {
		if ($token !== 'modal-content') {
			$classes[] = $token;
		}
	}

	$dialog = '<dialog class=' . $quote . implode(' ', array_values(array_unique(array_merge(array('dialog'), $classes)))) . $quote . ' open>';

	return array(
		array($root[0], $root[1], $root_new),
		array($box[0], $box[1], $dialog),
		array_merge(bs6_shell_cut_range($source, $content[0], $content[1]), array('')),
		array_merge(bs6_shell_cut_range($source, $content_close[0], $content_close[1]), array('')),
		array($box_close[0], $box_close[1], (strpos(substr($source, $box_close[0], 3), '\\') !== false ? '<\/' : '</') . 'dialog>'),
	);
}

/**
 * The visual editor's own dialogs in style_designer.js. Its chrome and its
 * canvas code (Bootstrap 5 inside the page being edited) share members, so
 * the names are renamed only inside the statements that build a chrome
 * dialog (`x.innerHTML = '<div class="modal-dialog …` or
 * `var html = '<div class="modal fade …`, up to the `';` that ends it), then
 * the dialogs are converted as anywhere else.
 */
function bs6_switch_editor_dialogs($source)
{
	$site_output = bs6_site_output_ranges($source);
	$spans = array();
	$start = '/(?:[A-Za-z_$][\w$.]*\.innerHTML|\bvar [A-Za-z_$][\w$]*)\s*=\s*[\'"]<div class="modal(?:-dialog\b| fade\b)/';

	if (preg_match_all($start, $source, $matches, PREG_OFFSET_CAPTURE)) {
		foreach ($matches[0] as $match) {
			if ((!$site_output || !bs6_in_ranges($match[1], $site_output))
				&& preg_match('/[\'"];[ \t]*\r?\n/', $source, $end, PREG_OFFSET_CAPTURE, $match[1])) {
				$spans[] = array($match[1], $end[0][1] + 2);
			}
		}
	}

	$edits = array();

	foreach ($spans as $span) {
		$text = substr($source, $span[0], $span[1] - $span[0]);
		$renamed = bs6_switch_component_names($text)['source'];

		if ($renamed !== $text) {
			$edits[] = array($span[0], $span[1], $renamed);
		}
	}

	$named = bs6_shell_apply($source, $edits);
	$result = bs6_switch_dialogs($named);

	return array('source' => $result['source'], 'status' => ($edits || $result['status'] === 'applied') ? 'applied' : 'done', 'left' => $result['left']);
}

// Bootstrap 5's lg, xl and xxl breakpoints in a media query, with their
// Bootstrap 6 values (lg 1024, xl 1280, 2xl 1536).
function bs6_shell_breakpoint_map()
{
	return array(
		'991.98px' => '1023.98px', '991px' => '1023px', '992px' => '1024px',
		'1199.98px' => '1279.98px', '1199px' => '1279px', '1200px' => '1280px',
		'1399.98px' => '1535.98px', '1399px' => '1535px', '1400px' => '1536px',
	);
}

/**
 * The panel's own media queries at Bootstrap 5's lg, xl and xxl widths move
 * to Bootstrap 6's, so a rule written beside a `lg:` class or a
 * `lg-down:dialog-fullscreen` dialog switches at the same width as it.
 * Only `@media` preludes are read, outside site-output code.
 */
function bs6_switch_breakpoints($source)
{
	$site_output = bs6_site_output_ranges($source);
	$map = bs6_shell_breakpoint_map();
	$count = 0;

	$edits = array();

	if (preg_match_all('/@media\b[^{;\'"]*\{/', $source, $matches, PREG_OFFSET_CAPTURE)) {
		foreach ($matches[0] as $match) {
			if ($site_output && bs6_in_ranges($match[1], $site_output)) {
				continue;
			}

			$out = preg_replace_callback('/\b(\d{3,4}(?:\.98)?px)/', function ($n) use ($map, &$count) {
				if (isset($map[$n[1]])) {
					$count++;
					return $map[$n[1]];
				}
				return $n[1];
			}, $match[0]);

			if ($out !== $match[0]) {
				$edits[] = array($match[1], $match[1] + strlen($match[0]), $out);
			}
		}
	}

	$source = bs6_shell_apply($source, $edits);

	return array('source' => $source, 'status' => $count > 0 ? 'applied' : 'done', 'left' => 0);
}

// Bootstrap 5 classes with no Bootstrap 6 counterpart that the panel keeps
// as its own: pattern => replacement.
function bs6_shell_own_class_renames()
{
	return array(
		'/(?<![\w-])link-(primary|secondary|success|danger|warning|info|body-emphasis)(?![\w-])/' => 'pg-link-$1',
		'/(?<![\w-])d-print-(none|inline|inline-block|block|grid|inline-grid|table|table-row|table-cell|flex|inline-flex)(?![\w-])/' => 'pg-print-$1',
	);
}

/**
 * Bootstrap 5's link colour helpers (`link-secondary`, `link-body-emphasis`,
 * …) and print display utilities (`d-print-none`, …) have no class in
 * 6.0.0-alpha.1. The panel already draws the link colours in its own ink
 * tones (backend.src.css), so both become the panel's own classes
 * (`pg-link-*`, `pg-print-*`) in markup, scripts and selectors alike; the
 * shell rules draw `pg-link-body-emphasis` and the print ones as v5 did.
 * Site-output code stays.
 */
function bs6_switch_own_classes($source)
{
	$site_output = bs6_site_output_ranges($source);
	$count = 0;
	$out = '';

	foreach (bs6_shell_split_ranges($source, $site_output) as $piece) {
		if (!$piece[1]) {
			foreach (bs6_shell_own_class_renames() as $pattern => $replacement) {
				$piece[0] = preg_replace($pattern, $replacement, $piece[0], -1, $n);
				$count += $n;
			}
		}
		$out .= $piece[0];
	}

	return array('source' => $out, 'status' => $count > 0 ? 'applied' : 'done', 'left' => 0);
}
