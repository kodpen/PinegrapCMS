<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The CSS minifier behind tools/build_css.php, apart so the tests can load it.
 *
 * It only removes what CSS never reads: comments, runs of whitespace (one
 * space is kept where one was, so a descendant combinator, a hex escape's
 * closing space or a value list keeps its meaning) and the whitespace beside
 * { } and ; where it never separates two tokens. A comma keeps its space: a
 * custom property holds its value as written, and scripts read that text.
 * Quoted strings are copied as they are. Nothing is reordered, merged or
 * rewritten, so the browser builds the same rules from either file.
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
 * The CSS without comments and with only the whitespace that counts.
 *
 * @param string $css
 * @return string
 */
function pg_css_minify($css)
{
	$css = str_replace(array("\r\n", "\r"), "\n", (string) $css);
	$length = strlen($css);
	$out = '';
	// A whitespace run or a comment was passed and not yet written.
	$gap = false;
	$tight = '{};';

	for ($i = 0; $i < $length; $i++) {
		$char = $css[$i];

		// A comment separates tokens like whitespace does.
		if (($char === '/') && ($i + 1 < $length) && ($css[$i + 1] === '*')) {
			$end = strpos($css, '*/', $i + 2);
			$i = ($end === false) ? $length : $end + 1;
			$gap = true;
			continue;
		}

		if (($char === ' ') || ($char === "\t") || ($char === "\n") || ($char === "\f")) {
			$gap = true;
			continue;
		}

		if ($gap) {
			$last = ($out === '') ? '' : substr($out, -1);

			// An escape's closing space stays even before { } ; , : "\31 "
			// ends at the space, and the space is part of the escape.
			$escaped = (bool) preg_match('/\\\\[0-9a-fA-F]{1,6}$/', substr($out, -7));

			if (($last !== '') && ($escaped || ((strpos($tight, $last) === false) && (strpos($tight, $char) === false)))) {
				$out .= ' ';
			}

			$gap = false;
		}

		// A quoted string, escapes included, as it is.
		if (($char === '"') || ($char === "'")) {
			$start = $i;

			for ($i++; $i < $length; $i++) {
				if ($css[$i] === '\\') {
					$i++;
					continue;
				}

				if (($css[$i] === $char) || ($css[$i] === "\n")) {
					break;
				}
			}

			$out .= substr($css, $start, $i - $start + 1);
			continue;
		}

		// An escape outside a string: the escaped character is never
		// whitespace to collapse or a comment start.
		if (($char === '\\') && ($i + 1 < $length)) {
			$out .= $char . $css[$i + 1];
			$i++;
			continue;
		}

		$out .= $char;
	}

	return $out;
}
