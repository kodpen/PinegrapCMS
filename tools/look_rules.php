<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * What tools/check_look.php counts in a panel stylesheet, apart so the tests
 * can load it: declarations outside the look's recipes that set a font size
 * or weight off the type scale, or a colour that is not a token.
 *
 * Not counted: custom property declarations (they define tokens), the
 * look's own blocks (each opened by a banner comment naming "Look A:" and
 * running to the next banner or the end of the file), relative sizes (em,
 * %, keywords) and values read from var().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER') && (PHP_SAPI !== 'cli')) {
	http_response_code(403);
	exit('Forbidden');
}

/**
 * The panel stylesheets the check reads, relative to pinegrap/.
 *
 * @return array
 */
function pg_look_stylesheets()
{
	return array(
		'assets/css/backend.src.css',
		'assets/css/pg_dash.src.css',
		'assets/css/pg_visitors.src.css',
		'assets/css/ws_today.src.css',
		'assets/css/pg_image_editor.src.css',
		'assets/css/pg_tour.css',
		'assets/css/pg_datetime.css',
	);
}

/**
 * The font sizes of the type scale in px (look A: 11, 12, 13, 14, 15, 17,
 * 24); a rem value is read at 16 px.
 *
 * @return array
 */
function pg_look_font_sizes()
{
	return array(11, 12, 13, 14, 15, 17, 24);
}

/**
 * The font weights of the type scale.
 *
 * @return array
 */
function pg_look_font_weights()
{
	return array('400', '500', '550', '650', 'normal');
}

/**
 * The stylesheet without its comments and without the look's own blocks.
 *
 * @param string $css
 * @return string
 */
function pg_look_strip($css)
{
	$css = (string) $css;
	// A banner is a comment opening with a row of "=". A look block runs from
	// its banner to the next banner or the end of the file.
	$parts = preg_split('#(/\*\s*={10,}.*?\*/)#s', $css, -1, PREG_SPLIT_DELIM_CAPTURE);
	$kept = '';
	$in_look = false;

	foreach ($parts as $i => $part) {
		if ($i % 2 === 1) {
			$in_look = (strpos($part, 'Look A:') !== false);
			continue;
		}
		if (!$in_look) {
			$kept .= $part;
		}
	}

	return (string) preg_replace('#/\*.*?\*/#s', ' ', $kept);
}

/**
 * Declarations of a stylesheet as property => value pairs, in order;
 * custom properties left out.
 *
 * @param string $css comments already removed
 * @return array list of array(property, value)
 */
function pg_look_declarations($css)
{
	preg_match_all('/(?<=[{;])\s*(-?[a-zA-Z][a-zA-Z-]*)\s*:\s*([^;{}]*?)\s*(?=[;}])/', (string) $css, $m, PREG_SET_ORDER);
	$out = array();

	foreach ($m as $d) {
		$out[] = array(strtolower($d[1]), $d[2]);
	}

	return $out;
}

/**
 * Whether a font-size value is off the scale: a px, rem or pt length that
 * is not one of the scale's sizes. Relative sizes, keywords and var() pass.
 *
 * @param string $value
 * @return bool
 */
function pg_look_size_off_scale($value)
{
	$value = trim(preg_replace('/\s*!important\s*$/i', '', (string) $value));

	if (!preg_match('/^(\d*\.?\d+)(px|rem|pt)$/i', $value, $m)) {
		return false;
	}
	$px = (float) $m[1] * ((strtolower($m[2]) === 'rem') ? 16 : ((strtolower($m[2]) === 'pt') ? 4 / 3 : 1));

	foreach (pg_look_font_sizes() as $size) {
		if (abs($px - $size) < 0.01) {
			return false;
		}
	}

	return true;
}

/**
 * Whether a font-weight value is off the scale.
 *
 * @param string $value
 * @return bool
 */
function pg_look_weight_off_scale($value)
{
	$value = strtolower(trim(preg_replace('/\s*!important\s*$/i', '', (string) $value)));

	if ($value === '' || strpos($value, 'var(') === 0 || in_array($value, array('inherit', 'initial', 'unset', 'revert'), true)) {
		return false;
	}

	return !in_array($value, pg_look_font_weights(), true);
}

/**
 * How many colours a value writes as a literal: hex, rgb(), rgba(), hsl(),
 * hsla(), white or black.
 *
 * @param string $value
 * @return int
 */
function pg_look_colour_literals($value)
{
	return (int) preg_match_all('/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\(|\b(?:white|black)\b/i', (string) $value);
}

/**
 * The counts of one stylesheet.
 *
 * @param string $css
 * @return array 'size', 'weight', 'colour' => int
 */
function pg_look_counts($css)
{
	$counts = array('size' => 0, 'weight' => 0, 'colour' => 0);

	foreach (pg_look_declarations(pg_look_strip($css)) as $d) {
		list($property, $value) = $d;

		if ($property === 'font-size' && pg_look_size_off_scale($value)) {
			$counts['size']++;
		} elseif ($property === 'font-weight' && pg_look_weight_off_scale($value)) {
			$counts['weight']++;
		}
		$counts['colour'] += pg_look_colour_literals($value);
	}

	return $counts;
}

/**
 * Whether a file (relative to pinegrap/) is panel markup whose old button
 * and tab classes are counted: a panel PHP screen or script source by
 * bs6_scope_kind(). Site output (Bootstrap 5.3.8), files shared with the
 * site, the visual designer (style_designer*), bundles (.min.js) and
 * assets/lib are left out.
 *
 * @param string $path
 * @param string $kind bs6_scope_kind() of the path
 * @return bool
 */
function pg_look_markup_file($path, $kind)
{
	$path = str_replace('\\', '/', (string) $path);
	$base = basename($path);
	$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

	return $kind === 'panel'
		&& ($ext === 'php' || $ext === 'js')
		&& strpos($base, 'style_designer') !== 0
		&& !preg_match('/\.min\.js$/', $base)
		&& strpos($path, 'assets/lib/') !== 0;
}

/**
 * The source with its comments blanked out, offsets kept: PHP comments by
 * the tokenizer, JavaScript block comments and line comments that start a
 * line. A comment that names an old class is not markup.
 *
 * @param string $source
 * @param string $ext 'php' or 'js'
 * @return string
 */
function pg_look_blank_comments($source, $ext)
{
	$source = (string) $source;
	$blank = function ($m) {
		return preg_replace('/[^\n]/', ' ', $m[0]);
	};

	if ($ext === 'php') {
		$out = '';
		foreach (token_get_all($source) as $token) {
			if (is_array($token) && ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
				$out .= preg_replace('/[^\n]/', ' ', $token[1]);
			} else {
				$out .= is_array($token) ? $token[1] : $token;
			}
		}
		return $out;
	}

	$source = (string) preg_replace_callback('#/\*.*?\*/#s', $blank, $source);

	return (string) preg_replace_callback('#^[ \t]*//[^\n]*#m', $blank, $source);
}

/**
 * How many old Bootstrap buttons and tab strips a panel source draws:
 * 'button' counts `btn btn-`, 'tabs' the classes nav-tabs, nav-pills and
 * nav-underline. Comments and the given site-output ranges are left out.
 *
 * @param string $source
 * @param string $ext 'php' or 'js'
 * @param array $skip list of array(start, end) byte ranges not counted
 * @return array 'button', 'tabs' => int
 */
function pg_look_markup_counts($source, $ext, array $skip = array())
{
	$source = pg_look_blank_comments($source, strtolower((string) $ext));

	foreach ($skip as $range) {
		$start = max(0, (int) $range[0]);
		$end = min(strlen($source), (int) $range[1]);
		if ($end > $start) {
			$source = substr_replace($source, str_repeat(' ', $end - $start), $start, $end - $start);
		}
	}

	return array(
		'button' => (int) preg_match_all('/(?<![\w-])btn btn-/', $source),
		'tabs' => (int) preg_match_all('/(?<![\w-])nav-(?:tabs|pills|underline)(?![\w-])/', $source),
	);
}
