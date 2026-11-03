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
 * "çerez" matches "Çerez", "ŞUBAT" matches "şubat", "istanbul" matches
 * "İstanbul".
 *
 * PCRE's caseless mode pairs i with I only, while the row filter
 * (pg_search_lower()) finds "İstanbul" for "istanbul"; a row it found then
 * counted no match and was never replaced. Each i-letter of the term becomes
 * the letters it is a case variant of in Turkish or English: a dotted i (or
 * İ) finds i, I and İ, a dotless ı finds ı and I, and a capital I (dotless in
 * Turkish, dotted in English) finds I, i, ı and İ. Caseless mode is off inside
 * those classes, or it would add i to every I and join ı to i. A capital I
 * also finds İ: "ISTANBUL" typed on a keyboard without Turkish letters
 * counts "İstanbul".
 *
 * @param mixed $find
 * @return string
 */
function pg_search_ci_pattern($find)
{
    $pattern = strtr(preg_quote((string) $find, '/'), array(
        'i'         => '(?-i:[iIİ])',
        "\u{0130}"  => '(?-i:[iIİ])',
        "\u{0131}"  => '(?-i:[ıI])',
        'I'         => '(?-i:[Iiıİ])',
    ));

    return '/' . $pattern . '/iu';
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

/**
 * The LIKE term (escaped for LIKE, not yet for SQL) the row filter matches
 * against LOWER(column): the typed term lowercased with pg_search_lower(),
 * each capital I and dotless ı a one-letter wildcard.
 *
 * LOWER() never folds ı to i, so "IRMAK" lowercased to "irmak" fetched no
 * "ırmak" row although the counting pattern finds it. The wildcard fetches
 * both spellings; the pattern (pg_search_ci_pattern()) then counts only
 * the real matches, and a row with none is skipped.
 *
 * @param mixed $find
 * @return string
 */
function pg_search_like_term($find)
{
    $pieces = preg_split('/[I\x{0131}]/u', (string) $find);

    if ($pieces === false) {
        return escape_like(pg_search_lower($find));
    }

    foreach ($pieces as $i => $piece) {
        $pieces[$i] = escape_like(pg_search_lower($piece));
    }

    return implode('_', $pieces);
}

/**
 * The Find & Replace scope keys the request posted, kept only when they name
 * a table of the screen. Nothing posted is no table: unticking every box
 * must not search and replace everywhere.
 *
 * @param mixed $posted $_POST['scope']
 * @param array $table_defs key => definition
 * @return string[]
 */
function pg_find_replace_scope($posted, array $table_defs)
{
    if (!is_array($posted)) {
        return array();
    }

    $scope = array();
    foreach ($posted as $key) {
        if (is_string($key) && isset($table_defs[$key]) && !in_array($key, $scope, true)) {
            $scope[] = $key;
        }
    }

    return $scope;
}

/**
 * The PCRE pattern for a typed regular expression: "/" delimiters, every
 * "/" the pattern does not escape itself escaped, so "</p>" or "a/b" work.
 *
 * @param mixed $find
 * @param mixed $case_sensitive
 * @return string
 */
function pg_find_replace_regex($find, $case_sensitive)
{
    $find    = (string) $find;
    $body    = '';
    $length  = strlen($find);

    for ($i = 0; $i < $length; $i++) {
        $char = $find[$i];
        if ($char === '\\') {
            $body .= $char . ($i + 1 < $length ? $find[++$i] : '');
        } elseif ($char === '/') {
            $body .= '\/';
        } else {
            $body .= $char;
        }
    }

    return '/' . $body . '/' . ($case_sensitive ? '' : 'i') . 'u';
}

/**
 * Why a typed regular expression is refused: 'invalid' when PCRE cannot
 * compile it, 'empty' when it matches empty text ("x*", "^", "a?"), which
 * would match every row and put the replacement between every two
 * characters; '' when it can be used.
 *
 * @param mixed $find
 * @param mixed $case_sensitive
 * @return string
 */
function pg_find_replace_regex_error($find, $case_sensitive)
{
    $result = @preg_match(pg_find_replace_regex($find, $case_sensitive), '');

    if ($result === false) {
        return 'invalid';
    }

    return ($result === 1) ? 'empty' : '';
}

/**
 * True when the pattern has a zero-length match in the text, e.g. "\b" or a
 * lookahead, which pass the empty-text check but would still insert the
 * replacement at every such place.
 *
 * @param string $pattern
 * @param mixed $text
 * @return bool
 */
function pg_find_replace_has_empty_match($pattern, $text)
{
    if (!@preg_match_all($pattern, (string) $text, $matches)) {
        return false;
    }

    foreach ($matches[0] as $match) {
        if ($match === '') {
            return true;
        }
    }

    return false;
}

/**
 * True when a replaced value is longer than its column. The session is not
 * strict, so MySQL would cut it without an error.
 *
 * @param string $column
 * @param mixed $value
 * @param array $limits column => maximum characters (a table definition's max_length)
 * @return bool
 */
function pg_find_replace_too_long($column, $value, array $limits)
{
    if (!isset($limits[$column])) {
        return false;
    }

    return mb_strlen((string) $value, 'UTF-8') > (int) $limits[$column];
}
