<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks that the visual designer's data-binding dropdowns and the system
 * widget renderers agree on the token vocabulary.
 *
 * Two contracts are enforced:
 *   1. Every `__token` a dropdown offers is produced by a renderer. An option
 *      nobody answers binds an element to nothing; the designer sees a
 *      sensible label and gets an empty node.
 *   2. Every `__token` a renderer produces is offered by a dropdown. A token
 *      missing from the list has to be typed by hand through "Custom", which
 *      is how invented spellings (`__subject`, `__submitted_at`) got into
 *      saved trees in the first place.
 *
 * The same two contracts hold for the visibility flags ("Show only when",
 * SW_VISIBILITY_FLAGS): every flag listed is evaluated by a renderer, and
 * every flag a renderer evaluates is listed.
 *
 * Not every token belongs in a dropdown, so two shapes are exempt:
 *   • `*_inner_html` — the section-binding pass writes these into the tree
 *     itself; the designer marks a wrapper, never picks the token.
 *   • `__link_label` — a sentinel the catalog renderer expands into
 *     `__link_label_0`, `__link_label_1`, … one per bound node.
 *
 * Usage:  php tools/check_bindings.php [directory]     (default: pinegrap/)
 * Exit:   0 when both sides agree, 1 otherwise.
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

$designer_file = 'assets/js/style_designer.js';

$renderer_glob = 'includes/fn/widgets*.php';

// Tokens a dropdown is not expected to carry, and why.
$exempt_suffix = '_inner_html';   // written by the section-binding pass
$exempt_exact  = array('__link_label');   // sentinel, expands per bound node

$root = isset($argv[1]) ? rtrim($argv[1], '/\\') : dirname(__DIR__) . '/pinegrap';

$root_real = realpath($root);

if ($root_real === false) {
	fwrite(STDERR, "Directory not found: $root\n");
	exit(1);
}

// ── What the renderers produce ──────────────────────────────────────────
// Four shapes, because the renderers build their maps four ways: a literal
// map entry, the same with the carets baked into the key, a direct
// str_replace, and a later conditional assignment onto the built map.
$emitted = array();

$renderer_patterns = array(
	"/'(__[a-z0-9_]+)'\s*=>/",
	"/'\^\^(__[a-z0-9_]+)\^\^'\s*=>/",
	"/str_replace\('\^\^(__[a-z0-9_]+)\^\^/",
	"/\\\$[A-Za-z_][A-Za-z0-9_]*\['(__[a-z0-9_]+)'\]\s*=[^=>]/",
);

$renderer_files = glob($root_real . '/' . $renderer_glob);

if (!$renderer_files) {
	fwrite(STDERR, "No renderer files matched: $renderer_glob\n");
	exit(1);
}

foreach ($renderer_files as $file) {

	$source = file_get_contents($file);
	$short  = basename($file);

	foreach ($renderer_patterns as $pattern) {

		if (preg_match_all($pattern, $source, $matches)) {

			foreach ($matches[1] as $token) {
				$emitted[$token][$short] = true;
			}

		}

	}

}

// ── What the designer offers ────────────────────────────────────────────
// Each palette is a `var SW_…_TOKEN_GROUPS = [ … ];` literal; the options are
// `['__token', label]` pairs inside it. Bracket-match to the palette's end so
// a nested array doesn't cut the scan short.
$offered = array();

$designer_source = file_get_contents($root_real . '/' . $designer_file);

if ($designer_source === false) {
	fwrite(STDERR, "Designer file not found: $designer_file\n");
	exit(1);
}

$palette_re = '/var (SW_[A-Z_]*TOKEN[A-Z_]*|SW_STANDARD_FIELD_OPTIONS|SW_BUILTIN_FIELD_OPTIONS'
            . '|SW_FORM_ITEM_VIEW_BUILTIN_OPTIONS)\s*=\s*\[/';

preg_match_all($palette_re, $designer_source, $palettes, PREG_OFFSET_CAPTURE);

foreach ($palettes[0] as $index => $palette) {

	$name  = $palettes[1][$index][0];
	$start = $palette[1] + strlen($palette[0]) - 1;   // the opening bracket
	$depth = 0;
	$at    = $start;
	$limit = strlen($designer_source);

	while ($at < $limit) {

		if ($designer_source[$at] === '[') {
			$depth++;
		} elseif ($designer_source[$at] === ']') {
			$depth--;
			if ($depth === 0) break;
		}

		$at++;

	}

	$body = substr($designer_source, $start, $at - $start);

	if (preg_match_all("/\['(__[a-z0-9_]+)'/", $body, $matches)) {

		foreach ($matches[1] as $token) {
			$offered[$token][$name] = true;
		}

	}

}

// ── Compare ─────────────────────────────────────────────────────────────
$problems = 0;

foreach ($offered as $token => $palettes_using) {

	if (isset($emitted[$token])) continue;
	if (in_array($token, $exempt_exact, true)) continue;

	echo "OFFERED  $token\n";
	echo "         no renderer produces it; offered by: " . implode(', ', array_keys($palettes_using)) . "\n";
	$problems++;

}

foreach ($emitted as $token => $files_using) {

	if (isset($offered[$token])) continue;
	if (in_array($token, $exempt_exact, true)) continue;
	if (substr($token, -strlen($exempt_suffix)) === $exempt_suffix) continue;

	echo "EMITTED  $token\n";
	echo "         in no dropdown, so it has to be typed by hand; produced by: "
	   . implode(', ', array_keys($files_using)) . "\n";
	$problems++;

}

echo "\nRenderers produce " . count($emitted) . " token(s); the designer offers " . count($offered) . ".\n";

// ── Visibility flags ────────────────────────────────────────────────────
// The "Show only when" list (SW_VISIBILITY_FLAGS) against the flags the
// renderers evaluate. A flag the list offers that no renderer knows drops
// its element on every page; a flag a renderer knows that the list does
// not offer can only be written by hand into a saved tree.
//
// A renderer hands its flags to _eo_apply_visibility_bindings(),
// _pg_sw_resolve_visibility() or _pg_acct_render_signed_out() - as an
// array literal, or as a variable built from one ($flags = array(...),
// $out['flags'] = array(...)). Each literal's top-level keys are its flags.

// Top-level 'key' => entries of the array(...) literal whose "(" is at
// $open in $source. Read with the tokenizer, so a comment or a string
// inside the literal (an apostrophe in "wouldn't") cannot derail it.
$array_keys_at = function ($source, $open) {
	$tokens = token_get_all('<?php ' . substr($source, $open));
	$keys   = array();
	$depth  = 0;
	$count  = count($tokens);
	for ($i = 1; $i < $count; $i++) {
		$t = $tokens[$i];
		if ($t === '(' || $t === '[') { $depth++; continue; }
		if ($t === ')' || $t === ']') { $depth--; if ($depth === 0) break; continue; }
		if ($depth !== 1 || !is_array($t) || $t[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
		for ($j = $i + 1; $j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true); $j++);
		if ($j < $count && is_array($tokens[$j]) && $tokens[$j][0] === T_DOUBLE_ARROW) {
			$word = substr($t[1], 1, -1);
			if (preg_match('/^[a-z][a-z0-9_]*$/', $word)) $keys[] = $word;
		}
	}
	return $keys;
};

$evaluated = array();
$flag_vars = array();

foreach ($renderer_files as $file) {

	$source = file_get_contents($file);
	$short  = basename($file);
	$calls  = '/(?:_eo_apply_visibility_bindings|_pg_sw_resolve_visibility)\(\s*[^,()]+(?:\([^()]*\))?\s*,\s*'
	        . '|_pg_acct_render_signed_out\(\s*[^,]+,\s*[^,]+,\s*/';

	if (!preg_match_all($calls, $source, $found, PREG_OFFSET_CAPTURE)) continue;

	foreach ($found[0] as $hit) {

		$arg = substr($source, $hit[1] + strlen($hit[0]), 200);

		if (preg_match('/^array\s*\(/', $arg)) {
			$open = $hit[1] + strlen($hit[0]) + strpos($arg, '(');
			foreach ($array_keys_at($source, $open) as $flag) $evaluated[$flag][$short] = true;
		} elseif (preg_match('/^\$([A-Za-z_][A-Za-z0-9_]*)(?:\[\'([a-z_]+)\'\])?/', $arg, $var)) {
			$flag_vars[] = array($var[1], isset($var[2]) ? $var[2] : '');
		}

	}

}

// Variables handed over: the array literals they are built from, in any
// renderer file ($name = array(...), or 'key' => array(...) / ['key'] = array(...)).
foreach ($flag_vars as $var) {

	list($name, $key) = $var;
	$pattern = ($key !== '')
		? "/(?:'" . preg_quote($key, '/') . "'\s*=>|\['" . preg_quote($key, '/') . "'\]\s*=)\s*array\s*\(/"
		: '/\$' . preg_quote($name, '/') . '\s*=\s*array\s*\(/';

	foreach ($renderer_files as $file) {

		$source = file_get_contents($file);
		if (!preg_match_all($pattern, $source, $found, PREG_OFFSET_CAPTURE)) continue;

		foreach ($found[0] as $hit) {
			$open = $hit[1] + strlen($hit[0]) - 1;
			foreach ($array_keys_at($source, $open) as $flag) $evaluated[$flag][basename($file)] = true;
		}

	}

}

// What the designer offers: SW_VISIBILITY_FLAGS = { type: [ ['flag', label], … ], … }.
$listed = array();

if (preg_match('/var SW_VISIBILITY_FLAGS\s*=\s*\{/', $designer_source, $m, PREG_OFFSET_CAPTURE)) {

	$start = $m[0][1] + strlen($m[0][0]) - 1;
	$depth = 0;
	$limit = strlen($designer_source);
	for ($at = $start; $at < $limit; $at++) {
		if ($designer_source[$at] === '{') $depth++;
		if ($designer_source[$at] === '}') { $depth--; if ($depth === 0) break; }
	}
	$body = substr($designer_source, $start, $at - $start);

	if (preg_match_all('/(\w+)\s*:\s*\[(.*?)\n\s*\]/s', $body, $types, PREG_SET_ORDER)) {
		foreach ($types as $type) {
			if (preg_match_all("/\['([a-z][a-z0-9_]*)'\s*,/", $type[2], $flags)) {
				foreach ($flags[1] as $flag) $listed[$flag][$type[1]] = true;
			}
		}
	}

} else {

	echo "FLAGS    SW_VISIBILITY_FLAGS not found in $designer_file\n";
	$problems++;

}

foreach ($listed as $flag => $types_using) {

	if (isset($evaluated[$flag])) continue;

	echo "LISTED   $flag\n";
	echo "         no renderer evaluates it; listed for: " . implode(', ', array_keys($types_using)) . "\n";
	$problems++;

}

foreach ($evaluated as $flag => $files_using) {

	if (isset($listed[$flag])) continue;

	echo "UNLISTED $flag\n";
	echo "         evaluated but not in \"Show only when\"; in: " . implode(', ', array_keys($files_using)) . "\n";
	$problems++;

}

echo "Renderers evaluate " . count($evaluated) . " visibility flag(s); the designer lists " . count($listed) . ".\n";

if ($problems > 0) {
	echo "FAILED: $problems name(s) known to one side only.\n";
	exit(1);
}

echo "OK: both sides carry the same tokens and flags.\n";
exit(0);
