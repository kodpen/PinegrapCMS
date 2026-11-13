<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Checks that every value the visual designer offers in a system widget's
 * Bind Data list has a sample on the designer canvas.
 *
 * The canvas draws a bound value with a realistic sample instead of its
 * label, a token name or an empty box. A token with no sample of its own
 * falls through to the name heuristics ("Example", "1", a money amount)
 * or to its label in angle quotes, which the canvas must never show.
 * This check walks the token palettes (`SW_*TOKEN*` arrays) and the
 * canvas's sample tables and fails on a palette token none of them names,
 * so a new token arrives with its sample.
 *
 * Exempt shapes, each drawn by a rule of its own:
 *   • addresses (`*_url`, `*_link`): a link's href, drawn as `#`;
 *   • pictures (`*_image`, `*_image_<n>`, `*_photo`): the sample photo;
 *   • condition-only entries (`'cond'`): a yes/no a condition reads;
 *   • `__link_label`: the caption the designer writes on the node.
 *
 * Usage: php tools/check_canvas_samples.php
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

$root = dirname(__DIR__);
$file = 'pinegrap/assets/js/style_designer.src.js';
$js   = @file_get_contents($root . '/' . $file);

if ($js === false) {
	fwrite(STDERR, "Designer file not found: $file\n");
	exit(1);
}

// The canvas's sample tables: where _displayValueForBinding() and the
// sample rows find a value. Each entry is the text that opens the table;
// its body runs to the matching closing brace.
$tables = array(
	'var _SD_WIDGET_LABEL_TOKENS = ', 'var _SD_CURRENCY_TOKENS = ', 'var _SD_SHORT_LABEL_TOKENS = ',
	'var _SD_HTML_PREVIEW_TOKENS = ', 'var _SD_NUMERIC_TOKENS = ', 'var _SD_FLAG_TOKENS = ',
	'var _SD_STANDARD_FIELD_PREVIEWS = ', 'var _SD_AVATAR_TOKENS = ', 'var SD_LIST_SAMPLE_ROWS = ',
	'var _SD_LIST_ROW_PREVIEWS = ', 'var _SD_LOOP_POSITION_TOKENS = ', 'var _SD_REGION_SAMPLES = ',
	'var _SD_REGION_LOOP_ROWS = ',
	'function _sdOrderSampleLineTwo(', 'function _sdOrderSample(', 'function _sdProductSample(',
	'function _sdAccountSample(', 'function _sdEventSample(',
	'function _sdCommentSamples(', 'function _sdCommentsRecentSamples(',
	// Its tokens are compared by name (fieldName === '__x').
	'function _sdCommentsPageToken(',
);

// The text of a bracketed block of $js that opens at $open ('{' or '[').
function pg_ccs_block($js, $open)
{
	$opener = $js[$open];
	$closer = ($opener === '{') ? '}' : ']';
	$depth  = 0;
	$limit  = strlen($js);
	for ($at = $open; $at < $limit; $at++) {
		if ($js[$at] === $opener) {
			$depth++;
		} elseif ($js[$at] === $closer) {
			$depth--;
			if ($depth === 0) break;
		}
	}
	return substr($js, $open, $at - $open + 1);
}

// Whether $token (written as $entry in its list) needs no sample of its
// own: it has one, or its shape is drawn by a rule of its own.
function pg_ccs_exempt($token, $entry, $sampled)
{
	if (isset($sampled[$token])) return true;
	if (preg_match('/_(url|link)$/', $token)) return true;
	if (preg_match('/_(image|image_\d+|photo)$/', $token)) return true;
	if (preg_match("/,\s*'cond'\s*\]$/", $entry)) return true;
	return $token === '__link_label';
}

$sampled = array();
$errors  = array();

foreach ($tables as $marker) {
	$at = strpos($js, "\n    " . $marker);
	if ($at === false) {
		$errors[] = "sample table not found: $marker (renamed? update this check)";
		continue;
	}
	$body = pg_ccs_block($js, strpos($js, '{', $at + strlen($marker)));
	$pattern = (strpos($marker, 'PageToken') !== false) ? "/'(__[a-z0-9_]+)'/" : "/['\"]?\b(__[a-z0-9_]+)['\"]?\s*:/";
	if (preg_match_all($pattern, $body, $found)) {
		foreach ($found[1] as $token) $sampled[$token] = true;
	}
}

// The palettes: [token, label] or [token, label, 'cond'] entries.
$missing = array();
$count   = 0;

preg_match_all('/var (SW_[A-Z_]*TOKEN[A-Z_]*)\s*=\s*\[/', $js, $palettes, PREG_OFFSET_CAPTURE);

foreach ($palettes[0] as $index => $palette) {
	$name = $palettes[1][$index][0];
	$body = pg_ccs_block($js, $palette[1] + strlen($palette[0]) - 1);
	if (!preg_match_all("/\['(__[a-z0-9_]+)'[^\]\[]*\]/", $body, $entries, PREG_SET_ORDER)) continue;
	foreach ($entries as $entry) {
		$count++;
		if (!pg_ccs_exempt($entry[1], $entry[0], $sampled)) $missing[$entry[1]][$name] = true;
	}
}

// The rows of lists inside a record (SW_LIST_SOURCES, the twin of
// pg_sw_list_sources()): each source's `rows` entries.
$at = strpos($js, "\n    var SW_LIST_SOURCES = {");
if ($at === false) {
	$errors[] = 'SW_LIST_SOURCES not found (renamed? update this check)';
} else {
	$sources = pg_ccs_block($js, strpos($js, '{', $at));
	if (preg_match_all("/\n        '([a-z_]+\.[a-z_]+)'\s*:\s*\{/", $sources, $heads, PREG_OFFSET_CAPTURE)) {
		foreach ($heads[0] as $index => $head) {
			$source = $heads[1][$index][0];
			$body   = pg_ccs_block($sources, $head[1] + strlen($head[0]) - 1);
			$rows   = strpos($body, 'rows:');
			if ($rows === false) continue;
			$body = pg_ccs_block($body, strpos($body, '[', $rows));
			if (!preg_match_all("/\['(__[a-z0-9_]+)'[^\]\[]*\]/", $body, $entries, PREG_SET_ORDER)) continue;
			foreach ($entries as $entry) {
				$count++;
				if (!pg_ccs_exempt($entry[1], $entry[0], $sampled)) $missing[$entry[1]]['list ' . $source] = true;
			}
		}
	}
}

if ($count === 0) {
	$errors[] = 'no palette token found: the SW_*TOKEN* arrays changed shape (update this check)';
}

ksort($missing);

foreach ($missing as $token => $names) {
	$errors[] = "$token (" . implode(', ', array_keys($names)) . ") has no canvas sample: add it to the widget's sample record or a sample table in $file";
}

if ($errors) {
	foreach ($errors as $error) echo "MISS  $error\n";
	echo "\nFAILED: " . count($errors) . " problem(s). A bound value with no sample is drawn as its label or as \"Example\" on the canvas (docs/memory/designer/canvas-sample-overview.md).\n";
	exit(1);
}

echo "OK: every one of $count palette and list row entries has a canvas sample.\n";
exit(0);
