<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Case-insensitive text search in the panel's older search boxes (Themes,
 * Comments, the editor's image and link pickers, Find & Replace).
 *
 * mb_strtolower() turns a capital İ into "i" followed by U+0307 COMBINING
 * DOT ABOVE (PHP 7.3+). MySQL's LIKE compares one character at a time, and
 * that stray mark matches no stored letter, so a term lowercased with
 * mb_strtolower() alone found no "İstanbul" or "İade" row.
 *
 * str_ireplace() folds ASCII letters only, so Find & Replace counted a
 * "Çerez" for the term "çerez" and then did not replace it. One Unicode
 * pattern now counts, highlights and replaces.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * A typed search term lowercased for LOWER(column) LIKE: the U+0307 that a
 * lowercased capital İ keeps is removed, so "İstanbul" searches "istanbul".
 *
 * @param mixed $term
 * @return string
 */
function pg_search_lower($term)
{
    return str_replace("\u{0307}", '', mb_strtolower((string) $term));
}

/**
 * The Unicode case-insensitive pattern that finds a plain (not regex) term:
 * "çerez" matches "Çerez", "ŞUBAT" matches "şubat".
 *
 * @param mixed $find
 * @return string
 */
function pg_search_ci_pattern($find)
{
    return '/' . preg_quote((string) $find, '/') . '/iu';
}

/**
 * How many times a plain term occurs in a text, ignoring letter case.
 *
 * @param mixed $text
 * @param mixed $find
 * @return int
 */
function pg_search_ci_count($text, $find)
{
    if ((string) $find === '') {
        return 0;
    }

    return (int) @preg_match_all(pg_search_ci_pattern($find), (string) $text);
}

/**
 * Replaces every occurrence of a plain term, ignoring letter case, with a
 * literal replacement ("$40" and backslashes stay as typed).
 *
 * @param mixed $find
 * @param mixed $replace
 * @param mixed $text
 * @param int $count set to the number of replacements
 * @return string the text unchanged when nothing matched or the text is not valid UTF-8
 */
function pg_search_ci_replace($find, $replace, $text, &$count = 0)
{
    $count = 0;

    if ((string) $find === '') {
        return (string) $text;
    }

    $result = @preg_replace(pg_search_ci_pattern($find), pg_regex_replacement($replace), (string) $text, -1, $count);

    if ($result === null) {
        $count = 0;
        return (string) $text;
    }

    return $result;
}
