<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Front-end translation - the store: texts, their hashes, the translations of
 * a language, where a text is used, and the languages themselves.
 *
 * The unit is the text (a segment). Every source text is normalised, its
 * SHA-1 is its key, and it is kept once in translation_strings however many
 * pages carry it. A translation is one row per string and language. Nothing
 * here touches a page: the engines and the panel only write to these tables.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Normalisation and hashing
   --------------------------------------------------------------------------- */

/**
 * The canonical form of a text, the one that is hashed and shown.
 *
 * Whitespace is trimmed and collapsed. An inline text (a heading's words
 * with a <strong> in them) is additionally reduced to the inline tags the
 * editor itself allows, with its text nodes escaped the way the editor
 * escapes them, so two spellings of the same markup are one segment.
 *
 * @param string $text
 * @param string $format text | inline
 * @return string
 */
function pg_tr_normalize($text, $format = 'text')
{
    $text = (string) $text;
    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    $text = preg_replace('/[ \t\x0B\f]+/u', ' ', $text);
    $text = preg_replace('/ *\n */u', "\n", $text);
    $text = trim($text);

    if ($text === '') {
        return '';
    }

    if ($format === 'inline') {
        // Line breaks inside inline markup are spaces to the browser.
        $text = preg_replace('/\s*\n\s*/u', ' ', $text);

        if ((strpos($text, '<') !== false) || (strpos($text, '&') !== false)) {
            $text = pg_tr_clean_inline($text);
        }
    }

    return $text;
}

/**
 * The key of a text: the format is part of it, because "Welcome" as plain
 * text and "Welcome" as inline markup are escaped differently on output.
 */
function pg_tr_hash($normalized, $format = 'text')
{
    return sha1($format . "\n" . $normalized);
}

/**
 * Whether a text is worth translating: it has letters, and it is not only a
 * token the renderer fills in later.
 */
function pg_tr_translatable($text)
{
    $plain = trim(strip_tags((string) $text));

    if ($plain === '') {
        return false;
    }

    // ^^field^^ tokens and {var:1} placeholders are data, not words.
    $plain = preg_replace('/\^\^[a-z0-9_]+\^\^(%%[^%]*%%)?/i', '', $plain);
    $plain = preg_replace('/\{[a-z_]+(:[0-9]+)?(\|[a-z])?\}/i', '', $plain);

    // A code - a product's stock code, a model number (D10, KT-1, M20/B) - is
    // one word of capitals and digits with a digit in it, and an engine
    // would only spoil it.
    if (preg_match('/^[\p{Lu}0-9._\/-]+$/u', trim($plain)) && preg_match('/[0-9]/', $plain)) {
        return false;
    }

    return (bool) preg_match('/\p{L}/u', $plain);
}

/**
 * The inline tags a translated text may carry: the editor's own list.
 */
function pg_tr_inline_tags()
{
    static $tags = array('b' => 1, 'strong' => 1, 'i' => 1, 'em' => 1, 'u' => 1, 's' => 1, 'small' => 1,
                         'span' => 1, 'sup' => 1, 'sub' => 1, 'code' => 1, 'mark' => 1, 'abbr' => 1,
                         'br' => 1, 'wbr' => 1, 'a' => 1, 'time' => 1, 'kbd' => 1, 'q' => 1, 'cite' => 1);

    return $tags;
}

/**
 * Inline markup reduced to the allowed tags and attributes, text escaped.
 *
 * Mirrors the editor's cleaner (pg_design_ai_clean_inline()): a block element
 * inside words keeps its words and loses the element; attributes other than
 * the harmless few are dropped; `&` is escaped only where it would read as an
 * entity. Self-contained so a front-end request does not have to load the
 * editor's assistant to draw a heading.
 *
 * @param string $html
 * @return string
 */
function pg_tr_clean_inline($html)
{
    $html = (string) $html;

    if (!class_exists('DOMDocument')) {
        return pg_tr_text_escape(strip_tags($html));
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $ok = $doc->loadHTML('<?xml encoding="UTF-8"><div id="pg-tr-root">' . $html . '</div>', LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $ok ? $doc->getElementById('pg-tr-root') : null;

    if (!$root) {
        return pg_tr_text_escape(strip_tags($html));
    }

    return trim(pg_tr_clean_inline_walk($root));
}

function pg_tr_clean_inline_walk($node)
{
    $inline = pg_tr_inline_tags();
    $keep_attrs = array('class', 'title', 'href', 'target', 'rel', 'aria-label', 'aria-hidden', 'lang', 'dir', 'datetime', 'style');
    $out = '';

    foreach ($node->childNodes as $child) {
        if (($child->nodeType === XML_TEXT_NODE) || ($child->nodeType === XML_CDATA_SECTION_NODE)) {
            $out .= pg_tr_text_escape((string) $child->nodeValue);
            continue;
        }

        if ($child->nodeType !== XML_ELEMENT_NODE) {
            continue;
        }

        $tag = strtolower($child->nodeName);

        // Code and embedded content go with their words; any other block
        // element inside words keeps its words and loses the element.
        if (in_array($tag, array('script', 'style', 'template', 'iframe', 'object', 'embed', 'noscript', 'svg', 'math'), true)) {
            continue;
        }

        if (!isset($inline[$tag])) {
            $out .= pg_tr_clean_inline_walk($child);
            continue;
        }

        $attrs = '';

        foreach ($child->attributes as $attr) {
            $name = strtolower($attr->name);

            if (!in_array($name, $keep_attrs, true)) {
                continue;
            }

            $value = (string) $attr->value;

            // A link may not run code.
            if (($name === 'href') && preg_match('/^\s*(javascript|data|vbscript):/i', $value)) {
                continue;
            }

            $attrs .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
        }

        if (($tag === 'br') || ($tag === 'wbr')) {
            $out .= '<' . $tag . $attrs . '>';
            continue;
        }

        $out .= '<' . $tag . $attrs . '>' . pg_tr_clean_inline_walk($child) . '</' . $tag . '>';
    }

    return $out;
}

/**
 * Words for markup: < and > always escaped, & only where it would read as an
 * entity, so "Ev & Yaşam" stays as it was typed.
 */
function pg_tr_text_escape($text)
{
    $text = preg_replace('/&(?=[a-zA-Z][a-zA-Z0-9]*;|#[0-9]+;|#x[0-9a-fA-F]+;)/', '&amp;', (string) $text);

    return str_replace(array('<', '>'), array('&lt;', '&gt;'), $text);
}

/**
 * A translation that came from outside, made safe for the page: the software's
 * own markers and pseudo tags defused (the renderers emit a heading's text
 * raw, so a marker typed into one would be expanded), tags reduced to the
 * inline set for an inline text, all tags removed for a plain one.
 *
 * @param string $text
 * @param string $format
 * @return string
 */
function pg_tr_sanitize_incoming($text, $format = 'text')
{
    $text = (string) $text;

    // <!--pg-…--> is expanded by the page pipeline; "<!-- pg-" is inert.
    if (stripos($text, '<!--pg-') !== false) {
        $text = str_ireplace('<!--pg-', '<!-- pg-', $text);
    }

    // The region pseudo tags get_page_content.php scans for afterwards, and the
    // conditional tag, must not come in through a translation.
    $text = preg_replace('/<\/?(cregion|pregion|dregion|menu|menu_sequence|system|login|ad|cart|tcloud|pdf|mobile_switch|if|else|php)\b[^>]*>/i', '', $text);

    if ($format === 'inline') {
        return pg_tr_normalize($text, 'inline');
    }

    // A plain text: the renderer escapes it, so it is kept as words. Markup
    // that came back anyway is stripped; entities the engine wrote are
    // resolved so the page does not show "&amp;".
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return pg_tr_normalize($text, 'text');
}

/* ---------------------------------------------------------------------------
   Languages
   --------------------------------------------------------------------------- */

/**
 * The languages the settings screen offers, with the defaults a new target
 * language starts with. Codes are BCP 47 as Google and the browser spell
 * them. The number format is the "1,234.56" sample lang() uses, the date a
 * PHP date() format.
 *
 * @return array code => array(label, native, og_locale, number, date, rtl)
 */
function pg_tr_known_languages()
{
    static $list = null;

    if ($list !== null) {
        return $list;
    }

    $list = array(
        'en'    => array('English', 'English', 'en_US', '1,234.56', 'n/j/Y', 0),
        'tr'    => array('Turkish', 'Türkçe', 'tr_TR', '1.234,56', 'j.n.Y', 0),
        'de'    => array('German', 'Deutsch', 'de_DE', '1.234,56', 'j.n.Y', 0),
        'fr'    => array('French', 'Français', 'fr_FR', '1 234,56', 'j/n/Y', 0),
        'es'    => array('Spanish', 'Español', 'es_ES', '1.234,56', 'j/n/Y', 0),
        'it'    => array('Italian', 'Italiano', 'it_IT', '1.234,56', 'j/n/Y', 0),
        'pt'    => array('Portuguese', 'Português', 'pt_PT', '1 234,56', 'j/n/Y', 0),
        'pt-BR' => array('Portuguese (Brazil)', 'Português (Brasil)', 'pt_BR', '1.234,56', 'j/n/Y', 0),
        'nl'    => array('Dutch', 'Nederlands', 'nl_NL', '1.234,56', 'j-n-Y', 0),
        'ru'    => array('Russian', 'Русский', 'ru_RU', '1 234,56', 'j.n.Y', 0),
        'uk'    => array('Ukrainian', 'Українська', 'uk_UA', '1 234,56', 'j.n.Y', 0),
        'pl'    => array('Polish', 'Polski', 'pl_PL', '1 234,56', 'j.n.Y', 0),
        'cs'    => array('Czech', 'Čeština', 'cs_CZ', '1 234,56', 'j. n. Y', 0),
        'sk'    => array('Slovak', 'Slovenčina', 'sk_SK', '1 234,56', 'j. n. Y', 0),
        'hu'    => array('Hungarian', 'Magyar', 'hu_HU', '1 234,56', 'Y. n. j.', 0),
        'ro'    => array('Romanian', 'Română', 'ro_RO', '1.234,56', 'j.n.Y', 0),
        'bg'    => array('Bulgarian', 'Български', 'bg_BG', '1 234,56', 'j.n.Y', 0),
        'el'    => array('Greek', 'Ελληνικά', 'el_GR', '1.234,56', 'j/n/Y', 0),
        'sv'    => array('Swedish', 'Svenska', 'sv_SE', '1 234,56', 'Y-m-d', 0),
        'da'    => array('Danish', 'Dansk', 'da_DK', '1.234,56', 'j.n.Y', 0),
        'fi'    => array('Finnish', 'Suomi', 'fi_FI', '1 234,56', 'j.n.Y', 0),
        'no'    => array('Norwegian', 'Norsk', 'nb_NO', '1 234,56', 'j.n.Y', 0),
        'sr'    => array('Serbian', 'Srpski', 'sr_RS', '1.234,56', 'j.n.Y.', 0),
        'hr'    => array('Croatian', 'Hrvatski', 'hr_HR', '1.234,56', 'j.n.Y.', 0),
        'sl'    => array('Slovenian', 'Slovenščina', 'sl_SI', '1.234,56', 'j. n. Y', 0),
        'sq'    => array('Albanian', 'Shqip', 'sq_AL', '1 234,56', 'j.n.Y', 0),
        'bs'    => array('Bosnian', 'Bosanski', 'bs_BA', '1.234,56', 'j.n.Y.', 0),
        'mk'    => array('Macedonian', 'Македонски', 'mk_MK', '1.234,56', 'j.n.Y', 0),
        'lt'    => array('Lithuanian', 'Lietuvių', 'lt_LT', '1 234,56', 'Y-m-d', 0),
        'lv'    => array('Latvian', 'Latviešu', 'lv_LV', '1 234,56', 'j.n.Y', 0),
        'et'    => array('Estonian', 'Eesti', 'et_EE', '1 234,56', 'j.n.Y', 0),
        'ar'    => array('Arabic', 'العربية', 'ar_AR', '1,234.56', 'j/n/Y', 1),
        'he'    => array('Hebrew', 'עברית', 'he_IL', '1,234.56', 'j.n.Y', 1),
        'fa'    => array('Persian', 'فارسی', 'fa_IR', '1,234.56', 'Y/n/j', 1),
        'ur'    => array('Urdu', 'اردو', 'ur_PK', '1,234.56', 'j/n/Y', 1),
        'hi'    => array('Hindi', 'हिन्दी', 'hi_IN', '1,234.56', 'j/n/Y', 0),
        'bn'    => array('Bengali', 'বাংলা', 'bn_BD', '1,234.56', 'j/n/Y', 0),
        'id'    => array('Indonesian', 'Bahasa Indonesia', 'id_ID', '1.234,56', 'j/n/Y', 0),
        'ms'    => array('Malay', 'Bahasa Melayu', 'ms_MY', '1,234.56', 'j/n/Y', 0),
        'th'    => array('Thai', 'ไทย', 'th_TH', '1,234.56', 'j/n/Y', 0),
        'vi'    => array('Vietnamese', 'Tiếng Việt', 'vi_VN', '1.234,56', 'j/n/Y', 0),
        'zh-CN' => array('Chinese (Simplified)', '简体中文', 'zh_CN', '1,234.56', 'Y/n/j', 0),
        'zh-TW' => array('Chinese (Traditional)', '繁體中文', 'zh_TW', '1,234.56', 'Y/n/j', 0),
        'ja'    => array('Japanese', '日本語', 'ja_JP', '1,234.56', 'Y/n/j', 0),
        'ko'    => array('Korean', '한국어', 'ko_KR', '1,234.56', 'Y. n. j.', 0),
        'az'    => array('Azerbaijani', 'Azərbaycanca', 'az_AZ', '1.234,56', 'j.n.Y', 0),
        'kk'    => array('Kazakh', 'Қазақша', 'kk_KZ', '1 234,56', 'j.n.Y', 0),
        'uz'    => array('Uzbek', 'Oʻzbekcha', 'uz_UZ', '1 234,56', 'j.n.Y', 0),
        'ka'    => array('Georgian', 'ქართული', 'ka_GE', '1 234,56', 'j.n.Y', 0),
        'hy'    => array('Armenian', 'Հայերեն', 'hy_AM', '1,234.56', 'j.n.Y', 0),
        'ku'    => array('Kurdish', 'Kurdî', 'ku_TR', '1.234,56', 'j.n.Y', 0),
        'sw'    => array('Swahili', 'Kiswahili', 'sw_KE', '1,234.56', 'j/n/Y', 0),
        'af'    => array('Afrikaans', 'Afrikaans', 'af_ZA', '1 234,56', 'Y-m-d', 0),
        'ca'    => array('Catalan', 'Català', 'ca_ES', '1.234,56', 'j/n/Y', 0),
        'gl'    => array('Galician', 'Galego', 'gl_ES', '1.234,56', 'j/n/Y', 0),
        'eu'    => array('Basque', 'Euskara', 'eu_ES', '1.234,56', 'Y/n/j', 0),
        'ga'    => array('Irish', 'Gaeilge', 'ga_IE', '1,234.56', 'j/n/Y', 0),
        'is'    => array('Icelandic', 'Íslenska', 'is_IS', '1.234,56', 'j.n.Y', 0),
        'mt'    => array('Maltese', 'Malti', 'mt_MT', '1,234.56', 'j/n/Y', 0),
        'tl'    => array('Filipino', 'Filipino', 'tl_PH', '1,234.56', 'n/j/Y', 0),
    );

    return $list;
}

/**
 * The display label of a language code, from its row or the known list.
 */
function pg_tr_language_label($code)
{
    $row = pg_tr_language_row($code);

    if (is_array($row) && ($row['label'] !== '')) {
        return (string) $row['label'];
    }

    $known = pg_tr_known_languages();

    return isset($known[$code]) ? $known[$code][1] : (string) $code;
}

/**
 * Writes config.translation_prefixes from the enabled languages, so the
 * router (which reads that one column with raw mysqli) sees the same list the
 * settings screen saved. The source language never gets a prefix; a save
 * that just changed it passes the new one, since the constant still holds
 * the old.
 *
 * @param string|null $source the source language code; null = the current one
 */
function pg_tr_prefixes_rewrite($source = null)
{
    if (!pg_tr_ready()) {
        return;
    }

    $pairs = array();

    if ($source === null) {
        $source = pg_tr_source_language();
    }

    foreach (pg_tr_languages(true, true) as $code => $row) {
        if ($code === $source) {
            continue;
        }

        $prefix = ($row['prefix'] !== '') ? $row['prefix'] : $code;
        $pairs[] = $prefix . '=' . $code;
    }

    db("UPDATE config SET translation_prefixes = '" . e(implode(',', $pairs)) . "'");
}

/**
 * The Google Cloud key, decrypted for the request that calls Google; '' when
 * none is stored.
 */
function pg_tr_google_key()
{
    if (!defined('TRANSLATION_GOOGLE_KEY_ENC') || (TRANSLATION_GOOGLE_KEY_ENC === '')) {
        return '';
    }

    $stored = (string) TRANSLATION_GOOGLE_KEY_ENC;

    if (strpos($stored, ':') === false) {
        return '';
    }

    list($cipher, $iv) = explode(':', $stored, 2);

    return (string) decode_ssl_keys($cipher, $iv);
}

/**
 * Stores the Google Cloud key encrypted (cipher:iv), or clears it.
 */
function pg_tr_google_key_store($plain)
{
    $plain = trim((string) $plain);
    $value = '';

    if ($plain !== '') {
        list($cipher, $iv) = encrypt_string_with_iv($plain);
        $value = $cipher . ':' . $iv;
    }

    db("UPDATE config SET translation_google_key = '" . e($value) . "'");
}

/* ---------------------------------------------------------------------------
   Strings and translations
   --------------------------------------------------------------------------- */

/**
 * The id of a source text, inserted when it is new. The text is stored in its
 * normalised form; last_seen moves on every extraction that finds it.
 *
 * @param string $normalized
 * @param string $format
 * @param string $kind
 * @return int
 */
function pg_tr_string_upsert($normalized, $format = 'text', $kind = 'content')
{
    $hash = pg_tr_hash($normalized, $format);
    $now = time();

    db("INSERT INTO translation_strings (hash, source_text, format, kind, chars, first_seen, last_seen)
        VALUES ('" . e($hash) . "', '" . e($normalized) . "', '" . e($format) . "', '" . e($kind) . "', '" . mb_strlen($normalized) . "', '$now', '$now')
        ON DUPLICATE KEY UPDATE last_seen = '$now', id = LAST_INSERT_ID(id)");

    return (int) mysqli_insert_id(db::$con);
}

/**
 * The rows of translation_strings for a set of hashes.
 *
 * @param array $hashes
 * @return array hash => row
 */
function pg_tr_strings_by_hash($hashes)
{
    $out = array();
    $hashes = array_values(array_unique(array_filter(array_map('strval', (array) $hashes))));

    if (!$hashes) {
        return $out;
    }

    foreach (array_chunk($hashes, 500) as $chunk) {
        $list = "'" . implode("','", array_map('e', $chunk)) . "'";
        $rows = db_items("SELECT * FROM translation_strings WHERE hash IN ($list)");

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $out[$row['hash']] = $row;
            }
        }
    }

    return $out;
}

/**
 * Every translation of a language, keyed by the string's hash. Loaded once per
 * request, the first time a render asks for it: a page draws many texts and a
 * query per text would be the slow part of the page.
 *
 * Only the translations of texts something still uses: a text taken out of
 * the store's reach (a stock code, a field no longer extracted, a sentence
 * since reworded) keeps its translation for when it comes back, but is no
 * longer swapped where the same characters happen to be printed.
 *
 * @param string $language
 * @param bool   $reload
 * @return array hash => array(text, engine, status)
 */
function pg_tr_map($language, $reload = false)
{
    static $maps = array();

    if (isset($maps[$language]) && !$reload) {
        return $maps[$language];
    }

    $map = array();
    $rows = db_items("SELECT s.hash, t.text, t.engine, t.status
                      FROM translations t
                      INNER JOIN translation_strings s ON s.id = t.string_id
                      WHERE t.language = '" . e($language) . "'
                        AND EXISTS (SELECT 1 FROM translation_uses u WHERE u.string_id = t.string_id)");

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $map[$row['hash']] = array('text' => $row['text'], 'engine' => $row['engine'], 'status' => $row['status']);
        }
    }

    $maps[$language] = $map;

    return $map;
}

/**
 * The translation of one hash in a language, or null. Reads the loaded map
 * when there is one, otherwise a single indexed query (titles, menus).
 *
 * @return array|null array(text, engine, status)
 */
function pg_tr_lookup($hash, $language)
{
    static $single = array();

    $map = pg_tr_map_if_loaded($language);

    if ($map !== null) {
        return isset($map[$hash]) ? $map[$hash] : null;
    }

    $key = $language . '|' . $hash;

    if (array_key_exists($key, $single)) {
        return $single[$key];
    }

    $row = db_item("SELECT t.text, t.engine, t.status
                    FROM translations t
                    INNER JOIN translation_strings s ON s.id = t.string_id
                    WHERE s.hash = '" . e($hash) . "' AND t.language = '" . e($language) . "'
                      AND EXISTS (SELECT 1 FROM translation_uses u WHERE u.string_id = t.string_id) LIMIT 1");

    $single[$key] = is_array($row) ? array('text' => $row['text'], 'engine' => $row['engine'], 'status' => $row['status']) : null;

    return $single[$key];
}

/**
 * The map of a language if a render already loaded it; null otherwise. Lets a
 * title lookup stay a single query on a request that never drew a tree.
 */
function pg_tr_map_if_loaded($language, $set = null)
{
    static $loaded = array();

    if ($set !== null) {
        $loaded[$language] = true;
    }

    if (empty($loaded[$language])) {
        return null;
    }

    return pg_tr_map($language);
}

/**
 * Loads the whole map of a language and marks it loaded for pg_tr_lookup().
 */
function pg_tr_map_load($language)
{
    pg_tr_map_if_loaded($language, true);

    return pg_tr_map($language);
}

/**
 * Writes the translation of a string in a language, replacing the one there.
 * A reviewed translation is not overwritten by a machine one unless $force
 * says so: the operator's correction outlives the next engine run.
 *
 * @param int    $string_id
 * @param string $language
 * @param string $text       already sanitised (pg_tr_sanitize_incoming)
 * @param string $engine
 * @param string $status     machine | reviewed
 * @param int    $user_id
 * @param int    $suspicious
 * @param bool   $force
 * @return bool whether a row was written
 */
function pg_tr_save_translation($string_id, $language, $text, $engine, $status = 'machine', $user_id = 0, $suspicious = 0, $force = false)
{
    $string_id = (int) $string_id;
    $status = ($status === 'reviewed') ? 'reviewed' : 'machine';
    $now = time();

    if (!$force && ($status === 'machine')) {
        $current = db_value("SELECT status FROM translations WHERE string_id = '$string_id' AND language = '" . e($language) . "' LIMIT 1");

        if ($current === 'reviewed') {
            return false;
        }
    }

    db("INSERT INTO translations (string_id, language, text, engine, status, suspicious, updated_by, created_at, updated_at)
        VALUES ('$string_id', '" . e($language) . "', '" . e($text) . "', '" . e($engine) . "', '$status', '" . ((int) $suspicious) . "', '" . ((int) $user_id) . "', '$now', '$now')
        ON DUPLICATE KEY UPDATE
            text = VALUES(text), engine = VALUES(engine), status = VALUES(status), suspicious = VALUES(suspicious),
            updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)");

    pg_tr_invalidate_pages($language);

    return true;
}

/**
 * Marks a translation reviewed (or back to machine) without changing its text.
 */
function pg_tr_set_status($string_id, $language, $status, $user_id = 0)
{
    $status = ($status === 'reviewed') ? 'reviewed' : 'machine';

    db("UPDATE translations SET status = '$status', updated_by = '" . ((int) $user_id) . "', updated_at = '" . time() . "'
        WHERE string_id = '" . ((int) $string_id) . "' AND language = '" . e($language) . "'");

    pg_tr_invalidate_pages($language);
}

/**
 * The machine translations of a language in a scope of the Translations
 * screen ('all', 'page:12', 'group:catalog') that one click may mark
 * reviewed. The ones flagged suspicious are left to be looked at one by one.
 *
 * @return string SQL condition on translations t
 */
function pg_tr_review_all_where($language, $scope = 'all')
{
    $owner_where = pg_tr_owner_where(pg_tr_scope_owners($scope));

    return "t.language = '" . e($language) . "' AND t.status = 'machine' AND t.suspicious = 0
        AND EXISTS (SELECT 1 FROM translation_uses u WHERE u.string_id = t.string_id"
        . (($owner_where !== '') ? " AND ($owner_where)" : '') . ')';
}

/**
 * How many translations pg_tr_review_all() would mark.
 *
 * @return int
 */
function pg_tr_review_all_count($language, $scope = 'all')
{
    return (int) db_value('SELECT COUNT(*) FROM translations t WHERE ' . pg_tr_review_all_where($language, $scope));
}

/**
 * Marks the machine translations of a language in a scope reviewed in one
 * go, for a translation that is trusted as it came from the engine.
 *
 * @return int how many were marked
 */
function pg_tr_review_all($language, $scope = 'all', $user_id = 0)
{
    db("UPDATE translations t SET t.status = 'reviewed', t.updated_by = '" . ((int) $user_id) . "', t.updated_at = '" . time() . "'
        WHERE " . pg_tr_review_all_where($language, $scope));

    $count = (int) mysqli_affected_rows(db::$con);

    if ($count > 0) {
        pg_tr_invalidate_pages($language);
    }

    return $count;
}

/**
 * Drops the drawn bodies of a language (or of one page in every language),
 * so the next visit draws them again with the translations as they are now.
 * Many writes in one request drop them once.
 */
function pg_tr_invalidate_pages($language = '', $page_id = 0)
{
    static $done = array();

    $key = $language . '|' . (int) $page_id;

    if (isset($done[$key])) {
        return;
    }

    $done[$key] = true;

    $where = array();

    if ($language !== '') {
        $where[] = "language = '" . e($language) . "'";
    }

    if ((int) $page_id > 0) {
        $where[] = "page_id = '" . (int) $page_id . "'";
    }

    db("DELETE FROM page_translations" . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
}

/* ---------------------------------------------------------------------------
   Uses
   --------------------------------------------------------------------------- */

/**
 * Rewrites where an owner's texts are. The previous rows of the owner are
 * read first so a text that changed in place (same node, same field, a new
 * hash) remembers the string it replaced: the review screen shows the old
 * translation beside the new source, and the engines get it as context.
 *
 * @param string $owner_type page | shared | menu_item | page_seo | widget_config
 * @param int    $owner_id
 * @param array  $segments   array of array(string_id, node_id, field, position)
 */
function pg_tr_uses_rewrite($owner_type, $owner_id, $segments)
{
    $owner_id = (int) $owner_id;
    $now = time();

    $previous = array();
    $rows = db_items("SELECT string_id, node_id, field, prev_string_id FROM translation_uses
                      WHERE owner_type = '" . e($owner_type) . "' AND owner_id = '$owner_id'");

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $previous[$row['node_id'] . '|' . $row['field']] = $row;
        }
    }

    db("DELETE FROM translation_uses WHERE owner_type = '" . e($owner_type) . "' AND owner_id = '$owner_id'");

    $values = array();

    foreach ($segments as $segment) {
        $string_id = (int) $segment['string_id'];
        $node_id = (string) $segment['node_id'];
        $field = (string) $segment['field'];
        $position = (int) $segment['position'];
        $prev = 0;
        $key = $node_id . '|' . $field;

        if (isset($previous[$key])) {
            if ((int) $previous[$key]['string_id'] !== $string_id) {
                $prev = (int) $previous[$key]['string_id'];
            } else {
                $prev = (int) $previous[$key]['prev_string_id'];
            }
        }

        $values[] = "('$string_id', '" . e($owner_type) . "', '$owner_id', '" . e($node_id) . "', '" . e($field) . "', '$position', '$prev', '$now')";
    }

    foreach (array_chunk($values, 200) as $chunk) {
        db("INSERT INTO translation_uses (string_id, owner_type, owner_id, node_id, field, position, prev_string_id, seen_at)
            VALUES " . implode(',', $chunk));
    }
}

/**
 * The strings that have no translation in a language, among those an owner
 * uses (or among every used string).
 *
 * @param string $language
 * @param array  $owners    array of array(owner_type, owner_id); empty = all
 * @return array string ids
 */
function pg_tr_pending_string_ids($language, $owners = array())
{
    $where = pg_tr_owner_where($owners);

    $rows = db_items("SELECT DISTINCT u.string_id
                      FROM translation_uses u
                      INNER JOIN translation_strings s ON s.id = u.string_id
                      LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = '" . e($language) . "'
                      WHERE t.id IS NULL" . ($where !== '' ? " AND ($where)" : ''));

    $ids = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $ids[] = (int) $row['string_id'];
        }
    }

    return $ids;
}

/**
 * SQL for a set of owners, '' for all.
 */
function pg_tr_owner_where($owners)
{
    $parts = array();

    foreach ((array) $owners as $owner) {
        if (!is_array($owner) || empty($owner[0])) {
            continue;
        }

        // "*" for every owner of the type (a group of the Translations
        // screen: the whole catalog).
        $parts[] = ($owner[1] === '*')
            ? "(u.owner_type = '" . e($owner[0]) . "')"
            : "(u.owner_type = '" . e($owner[0]) . "' AND u.owner_id = '" . (int) $owner[1] . "')";
    }

    return implode(' OR ', $parts);
}

/**
 * The owners a page draws: itself, its SEO fields, the shared components and
 * widgets it references and the menus it shows. Read from translation_uses,
 * so it reflects the last extraction.
 *
 * @param int $page_id
 * @return array of array(owner_type, owner_id)
 */
function pg_tr_page_owners($page_id)
{
    $page_id = (int) $page_id;
    $owners = array(array('page', $page_id), array('page_seo', $page_id));

    // Stored as rows of owner_type 'page_ref' with string_id 0, whose node_id
    // carries the referenced owner's type and field its id (see
    // pg_tr_extract_page()).
    $rows = db_items("SELECT node_id AS ref_type, field AS ref_id FROM translation_uses
                      WHERE owner_type = 'page_ref' AND owner_id = '$page_id'");

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $owners[] = array((string) $row['ref_type'], (int) $row['ref_id']);
        }
    }

    return $owners;
}

/**
 * Per-page counts for the Translations screen: how many texts the page
 * draws, how many have a translation, how many of those are reviewed.
 *
 * @param string $language
 * @return array page_id => array(total, translated, reviewed, seen_at)
 */
function pg_tr_page_stats($language)
{
    $stats = array();

    $rows = db_items("SELECT u.owner_id AS page_id,
                             COUNT(DISTINCT u.string_id) AS total,
                             COUNT(DISTINCT t.string_id) AS translated,
                             COUNT(DISTINCT CASE WHEN t.status = 'reviewed' THEN t.string_id END) AS reviewed,
                             MAX(u.seen_at) AS seen_at
                      FROM translation_uses u
                      LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = '" . e($language) . "'
                      WHERE u.owner_type IN ('page', 'page_seo')
                      GROUP BY u.owner_id");

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $stats[(int) $row['page_id']] = array(
                'total'      => (int) $row['total'],
                'translated' => (int) $row['translated'],
                'reviewed'   => (int) $row['reviewed'],
                'seen_at'    => (int) $row['seen_at'],
            );
        }
    }

    return $stats;
}

/**
 * Site-wide counts for one language.
 *
 * @return array total, translated, reviewed, machine, pending
 */
function pg_tr_language_stats($language)
{
    $row = db_item("SELECT COUNT(DISTINCT u.string_id) AS total,
                           COUNT(DISTINCT t.string_id) AS translated,
                           COUNT(DISTINCT CASE WHEN t.status = 'reviewed' THEN t.string_id END) AS reviewed
                    FROM translation_uses u
                    LEFT JOIN translations t ON t.string_id = u.string_id AND t.language = '" . e($language) . "'
                    WHERE u.string_id > 0");

    $total = is_array($row) ? (int) $row['total'] : 0;
    $translated = is_array($row) ? (int) $row['translated'] : 0;
    $reviewed = is_array($row) ? (int) $row['reviewed'] : 0;

    return array(
        'total'      => $total,
        'translated' => $translated,
        'reviewed'   => $reviewed,
        'machine'    => $translated - $reviewed,
        'pending'    => max(0, $total - $translated),
    );
}

/* ---------------------------------------------------------------------------
   Names a language directory would shadow
   --------------------------------------------------------------------------- */

/**
 * The pages, files and short links whose name is one of the given names:
 * each would be shadowed by a language directory of that name, so a language
 * is not added while any exist.
 *
 * @param array $names codes and prefixes
 * @return array of array(kind, name) - kind is page | file | short_link
 */
function pg_tr_name_conflicts($names)
{
    $conflicts = array();
    $names = array_values(array_unique(array_filter(array_map('trim', (array) $names))));

    if (!$names) {
        return $conflicts;
    }

    $list = "'" . implode("','", array_map('e', $names)) . "'";

    foreach (array('page' => array('page', 'page_name'), 'file' => array('files', 'name'), 'short_link' => array('short_links', 'name')) as $kind => $spec) {
        $rows = db_items("SELECT " . $spec[1] . " AS name FROM " . $spec[0] . " WHERE " . $spec[1] . " IN ($list)");

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $conflicts[] = array($kind, (string) $row['name']);
            }
        }
    }

    return $conflicts;
}
