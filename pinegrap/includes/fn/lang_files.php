<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Language files kept as shards: which shard a key belongs to, the exact
 * text of a language file, the three-way merge of two edits of one, and the
 * checks a shard must pass, and the compiled cache pg_lang_map() keeps in
 * data/temp. Used by tools/tr_add.php, tools/tr_resolve.php,
 * tools/check_lang.php and pg_lang_map().
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: language file helpers.
//
// Loaded by functions.php through require_once, never on its own.
//
// A language kept as shards lives in includes/local/<code>/: a.json ... z.json
// for keys starting with that letter (either case), 0.json for a digit and
// _.json for anything else. Each shard is a JSON object with its keys in
// strcmp order, one per line. A new key then lands among its alphabetical
// neighbours, so two branches adding different keys touch different lines
// and git merges them; a single file that every branch appends to conflicts
// on every pair. lang() and every other reader take a language through
// pg_lang_map() (includes/fn/lang.php): the folder when it holds a shard,
// else includes/local/<code>.json.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The shard (file name without .json) that a key belongs to.
function pg_lang_shard_of($key)
{
    $key = (string) $key;

    if ($key === '') {
        return '_';
    }

    $byte = ord($key[0]);

    if (($byte >= 65) && ($byte <= 90)) {
        return chr($byte + 32);
    }

    if (($byte >= 97) && ($byte <= 122)) {
        return chr($byte);
    }

    if (($byte >= 48) && ($byte <= 57)) {
        return '0';
    }

    return '_';
}

// The exact text of a language file holding $map. Sorted (a shard): keys in
// strcmp order. Not sorted (the single includes/local/<code>.json): keys in
// the map's order, which keeps that file's lines where they are. Four-space
// indent, unescaped Unicode, slashes and line separators, a final newline.
// JSON_FORCE_OBJECT keeps an object when every key is numeric ("10001"
// decodes to an integer key).
function pg_lang_file_encode(array $map, $sorted = true)
{
    $clean = array();

    foreach ($map as $key => $value) {
        $clean[(string) $key] = (string) $value;
    }

    if ($sorted) {
        uksort($clean, function ($a, $b) {
            return strcmp((string) $a, (string) $b);
        });
    }

    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT;

    if (defined('JSON_UNESCAPED_LINE_TERMINATORS')) {
        $flags |= JSON_UNESCAPED_LINE_TERMINATORS;
    }

    return json_encode($clean, $flags) . "\n";
}

// A language map split into shards: shard name => map in strcmp order, the
// shards in name order.
function pg_lang_split(array $map)
{
    $shards = array();

    foreach ($map as $key => $value) {
        $shards[pg_lang_shard_of($key)][(string) $key] = (string) $value;
    }

    ksort($shards, SORT_STRING);

    foreach ($shards as $name => $shard) {
        uksort($shard, function ($a, $b) {
            return strcmp((string) $a, (string) $b);
        });
        $shards[$name] = $shard;
    }

    return $shards;
}

// Adds $pairs (key => value) to $map. A key already there with the same
// value is skipped; with another value it is refused unless $replace. New
// keys go at the end of the map. Returns array('map', 'added', 'replaced',
// 'skipped', 'refused'), the last four lists of keys.
function pg_lang_add(array $map, array $pairs, $replace = false)
{
    $result = array('map' => $map, 'added' => array(), 'replaced' => array(), 'skipped' => array(), 'refused' => array());

    foreach ($pairs as $key => $value) {
        $key = (string) $key;
        $value = (string) $value;

        if (!array_key_exists($key, $result['map'])) {
            $result['map'][$key] = $value;
            $result['added'][] = $key;
        } elseif ((string) $result['map'][$key] === $value) {
            $result['skipped'][] = $key;
        } elseif ($replace) {
            $result['map'][$key] = $value;
            $result['replaced'][] = $key;
        } else {
            $result['refused'][] = $key;
        }
    }

    return $result;
}

// Three-way merge of a language file: $base is the common ancestor, $dev the
// development side and $branch the branch being merged. The result starts
// from $dev and takes the branch's own edits: the keys it added, the values
// it changed (the branch's value wins when both changed one key) and the keys
// it removed that development left as they were. New keys follow $dev's, so
// the single file keeps development's lines and gains the branch's at the end.
function pg_lang_merge3(array $base, array $branch, array $dev)
{
    $result = $dev;

    foreach ($branch as $key => $value) {
        $value = (string) $value;

        if (!array_key_exists($key, $base) || ((string) $base[$key] !== $value)) {
            $result[$key] = $value;
        }
    }

    foreach ($base as $key => $value) {
        if (!array_key_exists($key, $branch) && array_key_exists($key, $result) && ((string) $result[$key] === (string) $value)) {
            unset($result[$key]);
        }
    }

    return $result;
}

// What is wrong with one shard's text, as a list of lines (empty when it is
// fine): not a JSON object, a value that is not a string, a key that belongs
// in another shard, or text that differs from pg_lang_file_encode() of its own
// keys (key order, indent, escaping, a key written twice, the final newline).
function pg_lang_shard_problems($name, $text)
{
    $text = (string) $text;
    $map = json_decode($text, true);

    if (!is_array($map) || (ltrim($text) === '') || (ltrim($text)[0] !== '{')) {
        return array('not a JSON object' . (json_last_error() ? ': ' . json_last_error_msg() : ''));
    }

    $problems = array();

    foreach ($map as $key => $value) {
        if (!is_string($value)) {
            $problems[] = 'value is not a string: ' . $key;
        }

        $belongs = pg_lang_shard_of($key);

        if ($belongs !== (string) $name) {
            $problems[] = 'key belongs in ' . $belongs . '.json: ' . $key;
        }
    }

    if ($text !== pg_lang_file_encode($map)) {
        $problems[] = 'not in its exact form (key order, indent, escaping, a key written twice or the final newline); php tools/tr_add.php --normalize rewrites it';
    }

    return $problems;
}

// Writes a language file through a temporary file and rename(): lang() reads
// the file on every request, and a half-written one does not decode, so every
// text of that page would fall back to English. Returns true on success.
function pg_lang_write_file($path, $text)
{
    $temporary = $path . '.tmp-' . getmypid();

    if (@file_put_contents($temporary, $text) !== strlen($text)) {
        @unlink($temporary);
        return false;
    }

    if (!@rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

// Where the compiled cache of language $code is kept: $temp (by default
// data/temp) /lang_<code>.php; null when $code is not a plain name, so no
// path is ever built from one.
function pg_lang_cache_path($code, $temp = null)
{
    $code = (string) $code;

    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $code)) {
        return null;
    }

    $temp = rtrim(($temp === null) ? PG_FUNCTIONS_DIR . '/data/temp' : (string) $temp, '/\\');

    return $temp . '/lang_' . $code . '.php';
}

// What the compiled cache was built from: $stats is file name => array(mtime,
// size) for every file of the language (its shards, or its single file). Any
// file added, gone, renamed, rewritten or copied in with an older time gives
// another signature, so the cache is rebuilt; a plain "newer than" check would
// miss a shard an update writes with the package's older time.
function pg_lang_cache_signature(array $stats)
{
    ksort($stats, SORT_STRING);

    $lines = array('pg_lang_cache 1');

    foreach ($stats as $name => $stat) {
        $lines[] = $name . '|' . (int) $stat[0] . '|' . (int) $stat[1];
    }

    return sha1(implode("\n", $lines));
}

// The text of the compiled cache: a PHP file returning array('signature' =>
// $signature, 'map' => $map). opcache keeps its array in shared memory, so a
// request reads the language without decoding any JSON. The gate stops it
// from running as a page; var_export() writes every string so that it reads
// back byte for byte.
function pg_lang_cache_text($signature, array $map)
{
    return "<?php\n"
        . "/**\n"
        . " * Pinegrap - Enterprise Website Platform\n"
        . " *\n"
        . " * Compiled language cache written by pg_lang_map() from the language\n"
        . " * files in includes/local. Rebuilt when they change; safe to delete.\n"
        . " *\n"
        . " * @author      Erdal Güral (Kodpen)\n"
        . " * @link        https://kodpen.com\n"
        . " * @copyright   2017–2026 Kodpen\n"
        . " * @license     https://opensource.org/licenses/mit-license.html MIT License\n"
        . " */\n"
        . "\n"
        . "if (!defined('PG_FUNCTIONS_DIR')) {\n"
        . "    exit;\n"
        . "}\n"
        . "\n"
        . 'return ' . var_export(array('signature' => (string) $signature, 'map' => $map), true) . ";\n";
}
