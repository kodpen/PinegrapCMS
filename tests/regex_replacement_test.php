<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_regex_replacement() (includes/fn/core.php): text put into a
 * page through preg_replace() arrives as written. Unescaped, "$400" in a
 * meta description or a product name printed as "0" ($40 read as a back
 * reference to a group that does not exist), and "$50/yıl" as "/yıl".
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// Every form PCRE reads as a reference stays literal text.
function test_regex_replacement_keeps_references_literal()
{
	$cases = array(
		"Kurumsal web tasarım \$400'dan.",
		"Barındırma + e-posta \$50/yıl'dan başlar.",
		"Group \\1 and \\\\1",
		"Braced \${1} and \$0",
		"CSS content: \"\\201C\"",
		"C:\\1files\\",
		'',
	);

	foreach ($cases as $text) {
		pg_assert_same(
			'<head>' . $text . '</head>',
			preg_replace('/<\/head>/i', pg_regex_replacement($text) . '</head>', '<head></head>', 1),
			'inserted as written: ' . $text);
	}
}

// A reference the caller writes itself next to the text still works.
function test_regex_replacement_leaves_callers_reference()
{
	$style = "<style>a{content:\"\\201C\"}</style>";

	pg_assert_same(
		'<head><title>T</title>' . $style . '</head>',
		preg_replace('/(<\/head>)/i', pg_regex_replacement($style) . '$1', '<head><title>T</title></head>', 1),
		'$1 written by the caller');
}

// Non-strings arrive as their text.
function test_regex_replacement_casts()
{
	pg_assert_same('5', pg_regex_replacement(5), 'an integer');
	pg_assert_same('', pg_regex_replacement(null), 'null');
}

// The page assembly puts every variable replacement through the helper: a
// replacement that is not a plain literal (a quoted string without
// variables) must call pg_regex_replacement().
function test_regex_replacement_used_by_page_assembly()
{
	$files = array(
		'pinegrap/get_page_content.php',
		'pinegrap/get_page.php',
	);

	foreach ($files as $file) {
		$tokens = token_get_all(file_get_contents(dirname(__DIR__) . '/' . $file));
		$count  = count($tokens);

		for ($i = 0; $i < $count; $i++) {
			if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'preg_replace') {
				continue;
			}

			$line = $tokens[$i][2];
			$depth = 0;
			$arg = 0;
			$replacement = '';

			for ($k = $i + 1; $k < $count; $k++) {
				$text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];

				if (($text === '(') || ($text === '[')) {
					$depth++;
					if ($depth === 1) continue;
				} elseif (($text === ')') || ($text === ']')) {
					$depth--;
					if ($depth === 0) break;
				} elseif (($text === ',') && ($depth === 1)) {
					$arg++;
					continue;
				}

				if ($arg === 1) $replacement .= $text;
			}

			$replacement = trim($replacement);

			// A plain literal: a single-quoted string, or a double-quoted
			// one without variables.
			if (preg_match('/^(\'[^\']*\'|"[^"$]*")$/', $replacement)) continue;

			pg_assert_true(
				strpos($replacement, 'pg_regex_replacement(') !== false,
				$file . ':' . $line . ' passes text to preg_replace() without pg_regex_replacement()');
		}
	}
}
