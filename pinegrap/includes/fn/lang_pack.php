<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Language packs: the wording check of one value against its English key,
 * and what a language other than Turkish lacks or gets wrong beside the
 * Turkish wording, the full list of keys. Used by tools/check_lang.php,
 * tools/lang_pack.php and tests/tr_placeholders_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: language pack helpers.
//
// Loaded by functions.php through require_once, never on its own.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The positions of the {var…} placeholders of $text, sorted and unique.
// {var} and its |c/|u/|l forms count as position 1; a modifier changes the
// case of the value, not which value is put in.
function pg_lang_wording_vars($text)
{
    preg_match_all('/\{var(?::(\d+)|\|(\d+))?(?:\|[cul])?\}/', (string) $text, $matches, PREG_SET_ORDER);

    $positions = array();

    foreach ($matches as $match) {
        $position = '1';

        if (isset($match[1]) && ($match[1] !== '')) {
            $position = $match[1];
        } elseif (isset($match[2]) && ($match[2] !== '')) {
            $position = $match[2];
        }

        $positions[$position] = true;
    }

    $positions = array_map('strval', array_keys($positions));
    sort($positions);

    return $positions;
}

// The {suffix…} placeholders of $text, sorted and unique.
function pg_lang_wording_suffixes($text)
{
    preg_match_all('/\{suffix(?::\d+)?\}/', (string) $text, $matches);

    $suffixes = array_values(array_unique($matches[0]));
    sort($suffixes);

    return $suffixes;
}

// The sprintf() placeholders of $text (%s, %d, %1$s, %.2f), sorted.
function pg_lang_wording_printf($text)
{
    preg_match_all('/%(?:\d+\$)?(?:\.\d+)?[sdf]\b/', (string) $text, $matches);

    $placeholders = $matches[0];
    sort($placeholders);

    return $placeholders;
}

// The lowercase named tokens of $text ({name}, {n}, {1}), sorted and
// unique. {{mustache}} templates and capitalised example names such as
// {Color} are wording, not code placeholders.
function pg_lang_wording_named($text)
{
    preg_match_all('/(?<!\{)\{([a-z][a-z0-9_]*|\d+)\}(?!\})/', (string) $text, $matches);

    $names = array();

    foreach ($matches[1] as $name) {
        if (($name !== 'var') && ($name !== 'suffix')) {
            $names[$name] = true;
        }
    }

    $names = array_map('strval', array_keys($names));
    sort($names);

    return $names;
}

// The HTML tags of $text as "name" and "/name", and the escaped entities
// that print markup as text (&lt; &gt; &quot; and numeric ones), sorted.
// &amp; and &nbsp; may become words in a translation.
function pg_lang_wording_markup($text)
{
    preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9]*)\b/', (string) $text, $tags, PREG_SET_ORDER);
    preg_match_all('/&(?:lt|gt|quot|#\d+|#x[0-9a-fA-F]+);/', (string) $text, $entities);

    $markup = array();

    foreach ($tags as $tag) {
        $markup[] = $tag[1] . strtolower($tag[2]);
    }

    foreach ($entities[0] as $entity) {
        $markup[] = $entity;
    }

    sort($markup);

    return $markup;
}

// What a translation $value breaks of its English $key, as a list of short
// names (array() when it is fine): the placeholders lang(), sprintf() and
// the JavaScript helpers replace, the tags and escaped entities the key
// prints, the space at its start or end that joins it to other text, and an
// empty value, which lang() would print as nothing. A value may drop an
// English plural {suffix}, never add one.
function pg_lang_wording_problems($key, $value)
{
    $key = (string) $key;
    $value = (string) $value;
    $problems = array();

    if (($value === '') && ($key !== '')) {
        return array('empty wording');
    }

    if (pg_lang_wording_vars($key) !== pg_lang_wording_vars($value)) {
        $problems[] = '{var} placeholders';
    }

    if (array_diff(pg_lang_wording_suffixes($value), pg_lang_wording_suffixes($key))) {
        $problems[] = '{suffix} placeholders';
    }

    if (pg_lang_wording_printf($key) !== pg_lang_wording_printf($value)) {
        $problems[] = 'sprintf placeholders';
    }

    if (pg_lang_wording_named($key) !== pg_lang_wording_named($value)) {
        $problems[] = 'named placeholders';
    }

    if (pg_lang_wording_markup($key) !== pg_lang_wording_markup($value)) {
        $problems[] = 'tags or entities';
    }

    if (preg_match('/^\s/u', $key) && !preg_match('/^\s/u', $value)) {
        $problems[] = 'leading space';
    }

    if (preg_match('/\s$/u', $key) && !preg_match('/\s$/u', $value)) {
        $problems[] = 'trailing space';
    }

    return $problems;
}

// The keys of $map as strings in strcmp order: json_decode() turns a key of
// digits into an integer.
function pg_lang_pack_keys(array $map)
{
    $keys = array_map('strval', array_keys($map));
    sort($keys, SORT_STRING);

    return $keys;
}

// The keys $turkish has and $language lacks, in key order, each as
// array('key' => English, 'tr' => its Turkish); only shard $shard when it
// is given (pg_lang_shard_of()), at most $limit when it is above 0.
function pg_lang_pack_missing(array $turkish, array $language, $shard = '', $limit = 0)
{
    $shard = (string) $shard;
    $limit = (int) $limit;
    $missing = array();

    foreach (pg_lang_pack_keys($turkish) as $key) {
        if (array_key_exists($key, $language)) {
            continue;
        }

        if (($shard !== '') && (pg_lang_shard_of($key) !== $shard)) {
            continue;
        }

        $missing[] = array('key' => $key, 'tr' => (string) $turkish[$key]);

        if (($limit > 0) && (count($missing) >= $limit)) {
            break;
        }
    }

    return $missing;
}

// The figures of $language beside $turkish a shard, shard name =>
// array('turkish', 'translated', 'missing', 'extra'), shards in name order:
// Turkish keys, those $language has, those it lacks, and its keys Turkish
// does not have.
function pg_lang_pack_counts(array $turkish, array $language)
{
    $counts = array();
    $blank = array('turkish' => 0, 'translated' => 0, 'missing' => 0, 'extra' => 0);

    foreach (pg_lang_pack_keys($turkish) as $key) {
        $shard = pg_lang_shard_of($key);

        if (!isset($counts[$shard])) {
            $counts[$shard] = $blank;
        }

        $counts[$shard]['turkish']++;
        $counts[$shard][array_key_exists($key, $language) ? 'translated' : 'missing']++;
    }

    foreach (pg_lang_pack_keys($language) as $key) {
        if (array_key_exists($key, $turkish)) {
            continue;
        }

        $shard = pg_lang_shard_of($key);

        if (!isset($counts[$shard])) {
            $counts[$shard] = $blank;
        }

        $counts[$shard]['extra']++;
    }

    ksort($counts, SORT_STRING);

    return $counts;
}

// The faults of $language, a language other than Turkish, as lines in key
// order: a key $turkish does not have (no code asks for it, so it is a typo
// or a key a later PR renamed), and a wording that breaks its key
// (pg_lang_wording_problems()). A key it lacks is no fault: lang() shows
// the English.
function pg_lang_pack_problems(array $turkish, array $language)
{
    $problems = array();

    foreach (pg_lang_pack_keys($language) as $key) {
        if (!array_key_exists($key, $turkish)) {
            $problems[] = 'not a Turkish key: ' . $key;
            continue;
        }

        $value = $language[$key];

        if (!is_string($value)) {
            $problems[] = 'value is not a string: ' . $key;
            continue;
        }

        $broken = pg_lang_wording_problems($key, $value);

        if ($broken) {
            $problems[] = implode(', ', $broken) . ': ' . $key;
        }
    }

    return $problems;
}
