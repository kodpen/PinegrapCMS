<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The panel's languages: the one shipped list every place that offers,
 * accepts or stores a software language reads (Site Settings, the
 * installer and its command line, router.php), and the codes
 * config.software_language can hold.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The installer loads this file before functions.php, so INSTALL_OR_UPDATE
// opens it as well.
if (!defined('PG_FUNCTIONS_DIR') && !defined('INSTALL_OR_UPDATE')) {
    exit;
}

// Every code the ENUM of config.software_language holds, in its order
// (upgrade_2026_11_0_software_language_codes(), 11.0.281). A code here may
// be stored; only a code of pg_software_languages() is offered. A released
// migration never changes, so a further code takes a new step and a new
// line here.
function pg_software_language_codes()
{
    return array('en', 'tr', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'pl', 'ru', 'uk', 'ar', 'fa', 'he', 'ja', 'ko', 'zh');
}

// The shipped list: code => its own name, its writing direction and the
// first day of its week (0 Sunday, 1 Monday). A language joins it when its
// wording ships; English needs none, its keys are the wording.
function pg_software_language_catalog()
{
    return array(
        'en' => array('name' => 'English', 'dir' => 'ltr', 'first_day' => 0),
        'tr' => array('name' => 'Türkçe', 'dir' => 'ltr', 'first_day' => 1),
        'de' => array('name' => 'Deutsch', 'dir' => 'ltr', 'first_day' => 1),
    );
}

// Whether $local (a includes/local folder) holds wording for $code: a
// folder of *.json shards, or a single <code>.json, as pg_lang_source()
// reads it. English always has its wording.
function pg_software_language_has_wording($code, $local)
{
    $code = (string) $code;

    if ($code === 'en') {
        return true;
    }

    if (!preg_match('/^[a-z]{2}$/', $code)) {
        return false;
    }

    $local = rtrim((string) $local, '/\\');
    $shards = glob($local . '/' . $code . '/*.json');

    return (is_array($shards) && (count($shards) > 0)) || is_file($local . '/' . $code . '.json');
}

// The offered languages, code => array('code', 'name', 'dir', 'first_day'),
// in the shipped list's order: the catalogue cut to the codes the ENUM can
// store and whose wording is on disk under $local (by default
// includes/local, read once a request).
function pg_software_languages($local = null)
{
    static $cache = null;

    $default = ($local === null);

    if ($default && ($cache !== null)) {
        return $cache;
    }

    if ($default) {
        $local = (defined('PG_FUNCTIONS_DIR') ? PG_FUNCTIONS_DIR : dirname(dirname(dirname(__FILE__)))) . '/includes/local';
    }

    $storable = pg_software_language_codes();
    $languages = array();

    foreach (pg_software_language_catalog() as $code => $language) {
        if (in_array($code, $storable, true) && pg_software_language_has_wording($code, $local)) {
            $languages[$code] = array(
                'code' => $code,
                'name' => $language['name'],
                'dir' => $language['dir'],
                'first_day' => (int) $language['first_day'],
            );
        }
    }

    if ($default) {
        $cache = $languages;
    }

    return $languages;
}

// Whether $code is an offered language (pg_software_languages()).
function pg_software_language_offered($code, $local = null)
{
    return is_string($code) && isset(pg_software_languages($local)[$code]);
}

// The first offered language of an Accept-Language header, by the order of
// its tags (weights are not read), else 'en'.
function pg_software_language_from_header($header, $local = null)
{
    foreach (explode(',', (string) $header) as $tag) {
        $code = strtolower(substr(trim($tag), 0, 2));

        if (pg_software_language_offered($code, $local)) {
            return $code;
        }
    }

    return 'en';
}

// The first day of language $code's week for a date picker: 1 Monday, 0
// Sunday; a language outside the shipped list starts on Sunday.
function pg_software_language_first_day($code)
{
    $catalog = pg_software_language_catalog();

    return isset($catalog[(string) $code]) ? (int) $catalog[(string) $code]['first_day'] : 0;
}

// The <option> list of a software language select, $selected chosen.
function pg_software_language_options_html($selected)
{
    $html = '';

    foreach (pg_software_languages() as $code => $language) {
        $html .= '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"'
            . (($code === (string) $selected) ? ' selected' : '')
            . ' lang="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8') . '</option>';
    }

    return $html;
}
