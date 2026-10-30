<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * lang() and the readers of the language files it depends on, the choice of
 * the software language from config.php and the config row, and the language
 * a site log line is written in.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the translation function and its loader.
//
// Loaded by functions.php through require_once. get_file.php, which the
// router dispatches without functions.php, loads it on its own together with
// includes/fn/lang_files.php (the compiled cache helpers pg_lang_map() uses)
// and calls pg_software_language_define() for the constant init.php sets.

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The software language a request without a chosen language speaks, in the
// order init.php reads it: the language config.php enforces, else the one
// the site settings store (config.software_language; "undefined" is what an
// old settings screen saved for none), else config.php's default. '' when
// none is set, and lang() then speaks English.
function pg_software_language_pick($enforcement, $stored, $default)
{
    foreach (array($enforcement, $stored, $default) as $code) {
        $code = (string) $code;

        if (($code !== '') && ($code !== 'undefined')) {
            return $code;
        }
    }

    return '';
}

// Defines SOFTWARE_LANGUAGE for a request that does not pass through
// init.php, from config.php's constants and $stored, the config row's
// software_language. Nothing changes when the constant is already defined.
function pg_software_language_define($stored = '')
{
    if (defined('SOFTWARE_LANGUAGE')) {
        return;
    }

    $code = pg_software_language_pick(
        defined('ENFORCEMENT_SOFTWARE_LANGUAGE') ? ENFORCEMENT_SOFTWARE_LANGUAGE : '',
        $stored,
        defined('DEFAULT_SOFTWARE_LANGUAGE') ? DEFAULT_SOFTWARE_LANGUAGE : ''
    );

    if ($code !== '') {
        define('SOFTWARE_LANGUAGE', $code);
    }
}

// The language map kept as shards in $dir (includes/local/<code>/): every
// *.json there in name order, merged; null when $dir is not a folder or holds
// no shard, so the caller falls back to the single <code>.json file. A shard
// that is not valid JSON is skipped, as a broken single file is.
function pg_lang_read_folder($dir)
{
    $dir = rtrim((string) $dir, '/\\');

    if (($dir === '') || !is_dir($dir)) {
        return null;
    }

    $files = glob($dir . '/*.json');

    if (!is_array($files) || (count($files) === 0)) {
        return null;
    }

    sort($files, SORT_STRING);

    $map = array();

    foreach ($files as $file) {
        $decoded = @json_decode((string) @file_get_contents($file), true);

        if (!is_array($decoded)) {
            continue;
        }

        foreach ($decoded as $key => $value) {
            $map[$key] = $value;
        }
    }

    return $map;
}

// Where language $code keeps its wording under $local (by default
// includes/local): its folder of shards when that holds a *.json, else its
// single <code>.json; null when it has neither, or when $code is not a plain
// name (no path may be built from it). A <code>.json left beside a folder by
// an update that could not delete it is never read: the folder wins.
function pg_lang_source($code, $local = null)
{
    $code = (string) $code;

    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $code)) {
        return null;
    }

    $local = rtrim(($local === null) ? PG_FUNCTIONS_DIR . '/includes/local' : (string) $local, '/\\');

    $shards = glob($local . '/' . $code . '/*.json');

    if (is_array($shards) && (count($shards) > 0)) {
        return $local . '/' . $code;
    }

    return is_file($local . '/' . $code . '.json') ? $local . '/' . $code . '.json' : null;
}

// Every file language $source (a folder of shards or a single file) is read
// from, file name => array(mtime, size), for pg_lang_cache_signature().
function pg_lang_source_stats($source)
{
    $files = is_dir($source) ? glob(rtrim($source, '/\\') . '/*.json') : array($source);
    $stats = array();

    foreach ((is_array($files) ? $files : array()) as $file) {
        $stat = @stat($file);

        if (is_array($stat)) {
            $stats[basename($file)] = array($stat['mtime'], $stat['size']);
        }
    }

    return $stats;
}

// The map in compiled cache file $path when it was built from files with
// $signature; null when there is no such file, it does not parse, or the
// language files changed since it was written.
function pg_lang_cache_read($path, $signature)
{
    if (!is_file($path)) {
        return null;
    }

    try {
        $data = @include $path;
    } catch (Throwable $e) {
        return null;
    }

    if (!is_array($data) || !isset($data['signature'], $data['map']) || !is_array($data['map'])
        || ((string) $data['signature'] !== (string) $signature)) {
        return null;
    }

    return $data['map'];
}

// Writes the compiled cache of $map to $path through a temporary file and
// rename(), so a request never includes a half-written file, and drops the
// old copy from opcache (which may not check file times at all). false when
// the folder is missing or not writable: lang() then decodes the JSON each
// request, as it did before the cache.
function pg_lang_cache_write($path, $signature, array $map)
{
    $dir = dirname($path);

    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    if (!pg_lang_write_file($path, pg_lang_cache_text($signature, $map))) {
        return false;
    }

    if (function_exists('opcache_invalidate')) {
        @opcache_invalidate($path, true);
    }

    return true;
}

// The wording of language $code as lang() reads it, key => text, from where
// pg_lang_source() finds it. Every reader of a language file goes through
// here, so none of them can read a stale single file beside the folder.
// array() when the language has no file, or its single file does not decode.
//
// Decoding the Turkish shards (about 2.8 MB of JSON) costs every request
// tens of milliseconds, so the map is also compiled into a PHP array in
// $temp (by default data/temp) and read from there while its signature
// matches the language files' names, times and sizes. A caller that names
// its own $local gets the cache only when it names a $temp as well.
function pg_lang_map($code, $local = null, $temp = null)
{
    $source = pg_lang_source($code, $local);

    if ($source === null) {
        return array();
    }

    $cache = (($local === null) || ($temp !== null)) ? pg_lang_cache_path($code, $temp) : null;
    $signature = '';

    if ($cache !== null) {
        $signature = pg_lang_cache_signature(pg_lang_source_stats($source));
        $map = pg_lang_cache_read($cache, $signature);

        if ($map !== null) {
            return $map;
        }
    }

    $map = is_dir($source) ? pg_lang_read_folder($source) : @json_decode((string) @file_get_contents($source), true);

    if (!is_array($map)) {
        return array();
    }

    if ($cache !== null) {
        pg_lang_cache_write($cache, $signature, $map);
    }

    return $map;
}

// Software localization / translation helper.
// Usage scenarios:
//   echo lang('Hello World');
//   echo lang(array('string' => 'Hello {var:1}', 'vars' => array('World')));
//   echo lang(array('string' => 'Welcome {var:1|c}', 'vars' => array('john doe'))); // capitalize variable
//   echo lang(array('string' => 'Count: {var:1}', 'vars' => 5));
//   $lang = lang(array('info' => true)); // returns enforced or configured SOFTWARE_LANGUAGE (e.g. 'en' or 'tr')
//   echo lang(array('string' => 'Save', 'language' => 'tr')); // one language explicitly
//   $tr = lang(array('string' => 'Save', 'language' => 'tr', 'if_known' => true)); // the file's wording, null when it has none
//
// Notes:
// - Works on PHP 7.0 up to PHP 8.5.
// - Reads a language through pg_lang_map(): its shards in includes/local/{lang}/, else includes/local/{lang}.json.
// - If mbstring is not available, falls back to single-byte string helpers.
// - in english language file you can overwrite strings.
function lang($properties = false)
{
    static $translations = array(); // cache translations per language

    // Helper: safe multibyte case conversion with fallbacks
    $safe_case = function ($text, $mode) {
        if (!is_string($text)) {
            $text = (string) $text;
        }
        if (function_exists('mb_convert_case')) {
            switch ($mode) {
                case 'title':
                    return mb_convert_case($text, MB_CASE_TITLE, "UTF-8");
                case 'upper':
                    return mb_convert_case($text, MB_CASE_UPPER, "UTF-8");
                case 'lower':
                    return mb_convert_case($text, MB_CASE_LOWER, "UTF-8");
                default:
                    return $text;
            }
        }
        // Fallbacks when mbstring not available
        switch ($mode) {
            case 'title':
                return ucwords(strtolower($text));
            case 'upper':
                return strtoupper($text);
            case 'lower':
                return strtolower($text);
            default:
                return $text;
        }
    };

    // If explicit info request (get current software language)
    if (is_array($properties) && isset($properties['info'])) {
        // A front-end page drawn in another language (router.php found a
        // language prefix) speaks that language, whatever the panel is set to.
        if (defined('FRONTEND_LANGUAGE') && FRONTEND_LANGUAGE && !(function_exists('pg_tr_suspend') && pg_tr_suspend())) {
            return FRONTEND_LANGUAGE;
        }
        if (defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && ENFORCEMENT_SOFTWARE_LANGUAGE) {
            return ENFORCEMENT_SOFTWARE_LANGUAGE;
        }
        if (defined('SOFTWARE_LANGUAGE') && SOFTWARE_LANGUAGE) {
            return SOFTWARE_LANGUAGE;
        }
        return 'en';
    }

    // Normalize input: allow lang('text') or lang(array(...))
    $string = '';
    $vars = null;
    $suffixes = null;
    if (is_array($properties)) {
        if (isset($properties['string'])) {
            $string = (string) $properties['string'];
        }
        if (isset($properties['vars'])) {
            $vars = $properties['vars'];
        }
        if (isset($properties['suffix'])) {
            $suffixes = $properties['suffix'];
        }
    } else {
        // properties may be scalar string or null/false
        $string = ($properties === null || $properties === false) ? '' : (string) $properties;
    }

    // quick return for empty string
    if ($string === '') {
        return '';
    }

    // Determine selected language (en default). A front-end page drawn in
    // another language comes first: the enforced language is the panel's.
    $selected_software_language = 'en';
    $frontend_language_selected = false;
    if (defined('FRONTEND_LANGUAGE') && FRONTEND_LANGUAGE && !(function_exists('pg_tr_suspend') && pg_tr_suspend())) {
        $selected_software_language = FRONTEND_LANGUAGE;
        $frontend_language_selected = true;
    } elseif (defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && ENFORCEMENT_SOFTWARE_LANGUAGE) {
        $selected_software_language = ENFORCEMENT_SOFTWARE_LANGUAGE;
    } elseif (defined('SOFTWARE_LANGUAGE') && SOFTWARE_LANGUAGE) {
        $selected_software_language = SOFTWARE_LANGUAGE;
    }

    // A caller may ask for one language explicitly - the source language of
    // the site, say, to recognise a label the designer saved in it.
    if (is_array($properties) && isset($properties['language']) && is_string($properties['language'])
        && preg_match('/^[a-z]{2,3}(-[A-Za-z]{2,4})?$/', $properties['language'])) {
        $selected_software_language = $properties['language'];
        $frontend_language_selected = false;
    }

    // Load translations for language (cache)
    if (!isset($translations[$selected_software_language])) {
        // The shards in includes/local/<code>/, else includes/local/<code>.json;
        // an empty map when the language has neither or it does not decode.
        $translations[$selected_software_language] = pg_lang_map($selected_software_language);
    }

    // if_known: the wording the language's file has for the string, or null
    // when the file does not know it - nothing filled in.
    if (is_array($properties) && !empty($properties['if_known'])) {
        return isset($translations[$selected_software_language][$string]) ? $translations[$selected_software_language][$string] : null;
    }

    // If translation exists, replace string
    if (isset($translations[$selected_software_language][$string])) {
        $string = $translations[$selected_software_language][$string];
    } elseif ($frontend_language_selected && function_exists('pg_tr_ui_text')) {
        // A front-end page in a language with no language file: the site's
        // own translation of the wording, while it has one.
        $ui_template = pg_tr_ui_text($string);
        if ($ui_template !== null) {
            $string = $ui_template;
        }
    }


    // Replace variables {var}, {var:1}, with optional modifiers |c |u |l
    if ($vars !== null) {
        // Normalize single scalar to first var
        if (!is_array($vars)) {
            $vars = array($vars);
        }

        foreach ($vars as $index => $value) {
            $pos = $index + 1;
            $replacement = $value;

            // Look for modifier patterns {var:N|c} {var:N|u} {var:N|l}
            if (strpos($string, '{var:' . $pos . '|c}') !== false || strpos($string, '{var|' . ($pos) . '|c}') !== false) {
                $replacement = $safe_case($replacement, 'title');
                $string = str_replace('{var:' . $pos . '|c}', '{var:' . $pos . '}', $string);
                $string = str_replace('{var|' . ($pos) . '|c}', '{var:' . $pos . '}', $string);
            } elseif (strpos($string, '{var:' . $pos . '|u}') !== false || strpos($string, '{var|' . ($pos) . '|u}') !== false) {
                $replacement = $safe_case($replacement, 'upper');
                $string = str_replace('{var:' . $pos . '|u}', '{var:' . $pos . '}', $string);
                $string = str_replace('{var|' . ($pos) . '|u}', '{var:' . $pos . '}', $string);
            } elseif (strpos($string, '{var:' . $pos . '|l}') !== false || strpos($string, '{var|' . ($pos) . '|l}') !== false) {
                $replacement = $safe_case($replacement, 'lower');
                $string = str_replace('{var:' . $pos . '|l}', '{var:' . $pos . '}', $string);
                $string = str_replace('{var|' . ($pos) . '|l}', '{var:' . $pos . '}', $string);
            }

            // Also support shorthand {var|c} or {var} when only one var placeholder exists
            if ($pos === 1) {
                if (strpos($string, '{var|c}') !== false) {
                    $replacement = $safe_case($replacement, 'title');
                    $string = str_replace('{var|c}', '{var:1}', $string);
                } elseif (strpos($string, '{var|u}') !== false) {
                    $replacement = $safe_case($replacement, 'upper');
                    $string = str_replace('{var|u}', '{var:1}', $string);
                } elseif (strpos($string, '{var|l}') !== false) {
                    $replacement = $safe_case($replacement, 'lower');
                    $string = str_replace('{var|l}', '{var:1}', $string);
                }
            }

            // Final replacements
            $string = str_replace('{var:' . $pos . '}', $replacement, $string);
        }
    }

    // Replace suffix placeholders {suffix} and {suffix:N}
    if ($suffixes !== null) {
        if (!is_array($suffixes)) {
            $suffixes = array($suffixes);
        }
        foreach ($suffixes as $index => $sval) {
            $pos = $index + 1;
            $string = str_replace('{suffix:' . $pos . '}', $sval, $string);
        }
        // generic {suffix} => first suffix if exists
        if (strpos($string, '{suffix}') !== false) {
            $first = isset($suffixes[0]) ? $suffixes[0] : '';
            $string = str_replace('{suffix}', $first, $string);
        }
    }

    return $string;
}

/**
 * A site log line written by a request a visitor makes. A page drawn in
 * another site language (FRONTEND_LANGUAGE) would write the line in that
 * language; the log is read in the panel, so the line takes the software
 * language like the panel's own lines. Where the translation module is not
 * loaded (get_file.php) the language is named instead of held.
 *
 * @param array $properties lang()'s array form ('string', 'vars', 'suffix').
 * @return string
 */
function pg_log_lang($properties)
{
    $held = function_exists('pg_tr_suspend');

    if ($held) {
        pg_tr_suspend(true);
    } elseif (defined('FRONTEND_LANGUAGE') && FRONTEND_LANGUAGE) {
        $properties['language'] = (defined('ENFORCEMENT_SOFTWARE_LANGUAGE') && ENFORCEMENT_SOFTWARE_LANGUAGE)
            ? ENFORCEMENT_SOFTWARE_LANGUAGE
            : ((defined('SOFTWARE_LANGUAGE') && SOFTWARE_LANGUAGE) ? SOFTWARE_LANGUAGE : 'en');
    }

    $text = lang($properties);

    if ($held) {
        pg_tr_suspend(false);
    }

    return $text;
}
