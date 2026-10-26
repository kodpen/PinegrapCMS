<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the typed fill of system widget tokens.
//
// Loaded by functions.php through require_once, never on its own.
//
// A bound value may carry a format after its token, written by
// _apply_bindings() from props._bindFormats:
//
//   ^^__event_date^^%%Y-m-d%%      a date format, as the form views read it
//   ^^section^^%%upper%%            a text filter
//   ^^__price^^%%number:2%%         a filter that takes a number
//   ^^__excerpt^^%%truncate:120 nl2br%%   filters run left to right
//
// The suffix is a filter chain when every space-separated word in it is a
// filter of pg_sw_fill_filters(); anything else is a date format, as it was
// before filters existed, so a stored tree keeps its meaning. The fills
// replace the token and its suffix in one pass and never print the suffix.
// A value reaches a filter as plain text (an escaped value is decoded
// first) and leaves it escaped once; a value holding markup (a raw key, a
// link list, a relative date's tooltip) is never filtered.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The text filters, in the order the editor lists them: whether each takes
// a number (name:N) and the range that number is clamped to.
function pg_sw_fill_filters()
{
    return array(
        'upper'      => array('arg' => false),
        'lower'      => array('arg' => false),
        'capitalize' => array('arg' => false),
        'number'     => array('arg' => true, 'min' => 0, 'max' => 6),
        'truncate'   => array('arg' => true, 'min' => 1, 'max' => 1000),
        'yesno'      => array('arg' => false),
        'nl2br'      => array('arg' => false),
        'url'        => array('arg' => false),
    );
}

// The filters a suffix names, as array(array(name, number or null), ...),
// or null when the suffix is not a filter chain: then it is a date format
// ('d.m.Y', 'j F Y, H:i', 'relative') or empty. Each word is a known filter,
// written 'name' or 'name:N' as the filter requires; one word that is not
// makes the whole suffix a date format.
function pg_sw_fill_parse($mods)
{
    $mods = trim((string)$mods, ' ');
    if ($mods === '') return null;

    $known   = pg_sw_fill_filters();
    $filters = array();
    foreach (preg_split('/ +/', $mods) as $word) {
        if (!preg_match('/^([a-z][a-z0-9]*)(?::([0-9]+))?$/', $word, $m) || !isset($known[$m[1]])) return null;
        $filter  = $known[$m[1]];
        $has_arg = isset($m[2]) && $m[2] !== '';
        if ($has_arg !== $filter['arg']) return null;

        $arg = null;
        if ($has_arg) {
            // Nine digits fit an int everywhere; a longer number is above
            // every range anyway.
            $n   = (strlen(ltrim($m[2], '0')) > 9) ? $filter['max'] : (int)$m[2];
            $arg = max($filter['min'], min($filter['max'], $n));
        }
        $filters[] = array($m[1], $arg);
    }
    return $filters;
}

// Whether $lang ('tr', 'tr-TR', 'az') writes the Turkish i: a dotted i with
// the capital İ and a dotless ı with the capital I.
function _pg_sw_fill_turkic($lang)
{
    $base = strtolower((string)preg_replace('/[-_].*$/', '', (string)$lang));
    return ($base === 'tr' || $base === 'az');
}

// $text in capitals ('upper'), small letters ('lower'), or with its first
// letter a capital ('capitalize'; the letters before it, a quote or a
// figure, stay). In Turkish and Azerbaijani the i pairs are mapped first, so
// 'istanbul' gives 'İSTANBUL' and 'IRMAK' gives 'ırmak'; mbstring alone
// would give 'ISTANBUL' and 'irmak'.
function pg_sw_fill_case($text, $mode, $lang)
{
    $text   = (string)$text;
    $turkic = _pg_sw_fill_turkic($lang);

    if ($mode === 'upper') {
        if ($turkic) $text = str_replace(array('i', 'ı'), array('İ', 'I'), $text);
        return mb_strtoupper($text, 'UTF-8');
    }
    if ($mode === 'lower') {
        if ($turkic) $text = str_replace(array('İ', 'I'), array('i', 'ı'), $text);
        return mb_strtolower($text, 'UTF-8');
    }
    if ($mode === 'capitalize') {
        $out = preg_replace_callback('/^(\P{L}*)(\p{L})/u', function ($m) use ($lang) {
            return $m[1] . pg_sw_fill_case($m[2], 'upper', $lang);
        }, $text);
        // Not valid UTF-8: the text as it is rather than nothing.
        return ($out === null) ? $text : $out;
    }
    return $text;
}

// Plain $text through $filters (pg_sw_fill_parse()), left to right, then
// escaped once; line breaks, when asked, are added after the escaping. Its
// carets are written as &#94; too, as the comments widget and the list
// filters write a visitor's text, so a filtered value never forms a
// ^^token^^ that a later fill or the sweep would act on.
//
//   number    the figure the text shows (pg_sw_cond_number(): '-₺12,50',
//             '1.234,56') written with the site's separators and N
//             decimals; a text that shows no number stays as it is
//   truncate  the first N characters and '…', only when it cut
//   yesno     No for '', 0, no, false and off (any case), else Yes
//   url       the text encoded for an address (rawurlencode())
function pg_sw_fill_apply($text, $filters, $lang)
{
    $text  = (string)$text;
    $nl2br = false;
    foreach ((array)$filters as $filter) {
        $name = isset($filter[0]) ? $filter[0] : '';
        $arg  = isset($filter[1]) ? (int)$filter[1] : 0;
        switch ($name) {
            case 'upper':
            case 'lower':
            case 'capitalize':
                $text = pg_sw_fill_case($text, $name, $lang);
                break;

            case 'number':
                $n = pg_sw_cond_number($text);
                if ($n !== null) $text = pg_format_number($n, $arg);
                break;

            case 'truncate':
                if (mb_strlen($text, 'UTF-8') > $arg) $text = mb_substr($text, 0, $arg, 'UTF-8') . '…';
                break;

            case 'yesno':
                $folded = mb_strtolower(trim($text), 'UTF-8');
                $text = in_array($folded, array('', '0', 'no', 'false', 'off'), true) ? lang('No') : lang('Yes');
                break;

            case 'url':
                $text = rawurlencode($text);
                break;

            case 'nl2br':
                $nl2br = true;
                break;
        }
    }

    $html = str_replace('^', '&#94;', h($text));
    return $nl2br ? nl2br($html) : $html;
}

// The language a filter writes letters in: the page's, or the language the
// pages are written in.
function pg_sw_fill_lang()
{
    $lang = function_exists('pg_tr_language') ? pg_tr_language() : '';
    if ($lang === '' && function_exists('pg_tr_source_language')) $lang = pg_tr_source_language();
    return $lang;
}

// What an escaped value shows with its suffix $mods (what stood between
// %%...%%, entities decoded). The suffix itself never shows.
//
// A filter chain filters the value read as plain text. A value holding
// markup comes back as it is, except the line breaks the form views write
// into a multi-line value (prepare_form_data_for_output()): those are read
// as line breaks and written back after the filters.
//
// A date format formats $typed = array('type' => 'date' | 'date and time',
// 'data' => 'Y-m-d[ H:i:s]') the way the form views do, 'relative'
// included; a value without one comes back as it is.
function pg_sw_fill_value($escaped, $mods, $typed = null)
{
    $escaped = (string)$escaped;
    $filters = pg_sw_fill_parse($mods);

    if ($filters !== null) {
        $breaks = false;
        $plain  = $escaped;
        if (strpos($plain, '<') !== false) {
            $plain  = (string)preg_replace('#<br />(\r\n|\n\r|\n|\r)#', '$1', $plain);
            $breaks = ($plain !== $escaped);
            if (strpos($plain, '<') !== false) return $escaped;
        }
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($breaks) {
            $asked = false;
            foreach ($filters as $filter) $asked = $asked || ($filter[0] === 'nl2br');
            if (!$asked) $filters[] = array('nl2br', null);
        }
        return pg_sw_fill_apply($plain, $filters, pg_sw_fill_lang());
    }

    $format = (string)$mods;
    if ($format !== '' && is_array($typed) && isset($typed['type'], $typed['data'])
        && ($typed['type'] === 'date' || $typed['type'] === 'date and time')) {
        return (string)prepare_form_data_for_output((string)$typed['data'], $typed['type'], true, $format);
    }
    return $escaped;
}

// Fill ^^token^^ slots, with their %%...%% suffix, from an already-escaped
// map in one pass: a value is never read again, as with strtr(). A key of
// $values (exact case) gives its value through pg_sw_fill_value() when a
// suffix follows, else the value. $typed names the keys that hold a date
// (key => array('type', 'data')) for a date format. Any other token comes
// back as it was, suffix included, for the caller's sweep.
function pg_sw_fill_typed($html, $values, $typed = array())
{
    if (!is_string($html) || $html === '' || !$values) return $html;
    if (!is_array($typed)) $typed = array();

    $out = preg_replace_callback('/\^\^([^\^]+?)\^\^(?:%%(.*?)%%)?/', function ($m) use ($values, $typed) {
        $key = $m[1];
        if (!array_key_exists($key, $values)) return $m[0];
        $value = (string)$values[$key];
        if (!isset($m[2])) return $value;
        // A format typed into the rich-text editor may carry entities.
        return pg_sw_fill_value($value, unhtmlspecialchars($m[2]), isset($typed[$key]) ? $typed[$key] : null);
    }, $html);

    return ($out === null) ? $html : $out;
}

// Fill ^^token^^ slots from an already-escaped map the way the shop widgets
// (catalog, catalog detail, cart, express order, order view) always did:
// str_replace(), longest key first, so '^^__foo_bar^^' is never cut short
// by '^^__foo^^'. A template with a format after a token (^^x^^%%upper%%)
// is filled in one typed pass instead (pg_sw_fill_typed()), so the format
// is read, never printed; one without fills byte for byte as before. A
// token the map does not hold keeps its suffix for a later pass or the
// sweep. $typed as in pg_sw_fill_typed().
function pg_sw_fill_longest($html, $values, $typed = array())
{
    if (!is_string($html) || $html === '' || !is_array($values) || !$values) return $html;
    if (strpos($html, '^^%%') !== false) return pg_sw_fill_typed($html, $values, $typed);
    $keys = array_keys($values);
    usort($keys, function ($a, $b) { return strlen($b) - strlen($a); });
    foreach ($keys as $k) {
        $html = str_replace('^^' . $k . '^^', (string)$values[$k], $html);
    }
    return $html;
}

// The same for a map keyed by the whole token ('^^__cart_total^^' => …), as
// the express order builds its own: strtr() when no format follows a token,
// as before, else the typed pass.
function pg_sw_fill_token_map($html, $map, $typed = array())
{
    if (!is_string($html) || $html === '' || !is_array($map) || !$map) return $html;
    if (strpos($html, '^^%%') === false) return strtr($html, $map);
    $values = array();
    foreach ($map as $token => $value) {
        $token = (string)$token;
        if (strlen($token) > 4 && substr($token, 0, 2) === '^^' && substr($token, -2) === '^^') {
            $values[substr($token, 2, -2)] = $value;
        }
    }
    return pg_sw_fill_typed($html, $values, $typed);
}
