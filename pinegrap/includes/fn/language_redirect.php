<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Automatic language redirect: a visitor who arrives on a page in the source
 * language is sent to the same page in the language they read, when the site
 * is served in it (includes/translate/, the language directories /en/...).
 *
 * What the language is, in order:
 *
 *   1. the visitor's own choice, remembered in the pg_language cookie when
 *      they moved from a page in one language to a page in another (the
 *      language switcher, any link between the directories);
 *   2. the browser's Accept-Language list, read the way the standard reads
 *      it: by preference, with region variants (de-AT) falling back to their
 *      language (de);
 *   3. the country the visitor is in (includes/geo.php), through the
 *      languages spoken there;
 *   4. the fallback language the operator chose, when the visitor's
 *      languages are all ones the site is not served in; otherwise the page
 *      stays as it is.
 *
 * The settings say which of 2 and 3 are read, and whether only the home page
 * or every page of a visual design is redirected. Whatever they say, only the
 * first page of a visit is redirected (a visitor who followed a link inside
 * the site is not), only a page in the source language (an address in a
 * language directory is always served as asked), and only a person's browser:
 * crawlers, signed-in staff, forms and frames are left alone. The redirect is
 * a 302 that no cache keeps, so the address a search engine indexes stays the
 * page itself, which is what the x-default alternate names.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The cookie that keeps the visitor's own choice of language. A preference
// the visitor set by choosing, so it is not one of the optional categories
// of the cookie notice (includes/fn/consent.php).
define('PG_LANGUAGE_COOKIE', 'pg_language');

/**
 * Whether the redirect is switched on and the translation schema is there.
 */
function pg_lang_redirect_enabled()
{
    return defined('LANGUAGE_REDIRECT') && LANGUAGE_REDIRECT && function_exists('pg_tr_ready') && pg_tr_ready();
}

/**
 * The languages a visitor can be sent to: the source language and every
 * enabled target, as codes.
 *
 * @return array
 */
function pg_lang_redirect_codes()
{
    $source = pg_tr_source_language();
    $codes = array($source);

    foreach (pg_tr_languages() as $code => $row) {
        if ($code !== $source) {
            $codes[] = (string) $code;
        }
    }

    return $codes;
}

/**
 * The language tags of an Accept-Language header, most preferred first: by
 * weight, and in the order written where weights are equal. A tag weighted
 * q=0 (not acceptable), the wildcard and anything malformed are dropped.
 *
 * @param string $header
 * @return array lower-case tags
 */
function pg_lang_accept_parse($header)
{
    $items = array();
    $position = 0;

    foreach (explode(',', substr((string) $header, 0, 512)) as $part) {
        $pieces = explode(';', trim($part));
        $tag = strtolower(trim((string) array_shift($pieces)));
        $weight = 1.0;

        foreach ($pieces as $parameter) {
            $parameter = trim($parameter);

            if (strncasecmp($parameter, 'q=', 2) === 0) {
                $weight = (float) substr($parameter, 2);
            }
        }

        if (($tag === '') || ($tag === '*') || ($weight <= 0) || !preg_match('/^[a-z]{1,8}(-[a-z0-9]{1,8})*$/', $tag)) {
            continue;
        }

        $items[] = array($tag, min(1.0, $weight), $position++);

        if (count($items) >= 20) {
            break;
        }
    }

    usort($items, function ($a, $b) {
        if ($a[1] == $b[1]) {
            return $a[2] - $b[2];
        }

        return ($a[1] > $b[1]) ? -1 : 1;
    });

    $tags = array();

    foreach ($items as $item) {
        $tags[] = $item[0];
    }

    return $tags;
}

/**
 * The site language a language tag is read in, '' when none is.
 *
 * The tag's own form first (en-GB, then en); for Chinese the script or the
 * region decides between Simplified and Traditional, for Portuguese Brazil
 * between pt-BR and pt; old and alternative codes are read as the ones the
 * site uses (iw is he, nb and nn are no). Last, another variety of the same
 * language (pt-PT on a site that has only pt-BR).
 *
 * @param string $tag   a language tag, any case
 * @param array  $codes the site's language codes
 * @return string one of $codes, or ''
 */
function pg_lang_match($tag, $codes)
{
    $subtags = explode('-', strtolower(trim((string) $tag)));
    $language = (string) array_shift($subtags);
    $script = '';
    $region = '';

    foreach ($subtags as $subtag) {
        if (($script === '') && ($region === '') && preg_match('/^[a-z]{4}$/', $subtag)) {
            $script = $subtag;
        } elseif (($region === '') && preg_match('/^([a-z]{2}|[0-9]{3})$/', $subtag)) {
            $region = $subtag;
        }
    }

    $aliases = array('iw' => 'he', 'in' => 'id', 'nb' => 'no', 'nn' => 'no', 'fil' => 'tl');
    $language = isset($aliases[$language]) ? $aliases[$language] : $language;

    if ($language === '') {
        return '';
    }

    $lower = array();

    foreach ($codes as $code) {
        $lower[strtolower((string) $code)] = (string) $code;
    }

    if ($language === 'zh') {
        $candidates = array((($script === 'hant') || in_array($region, array('tw', 'hk', 'mo'), true)) ? 'zh-tw' : 'zh-cn');
    } elseif ($language === 'pt') {
        $candidates = array(($region === 'br') ? 'pt-br' : 'pt');
    } else {
        $candidates = ($region !== '') ? array($language . '-' . $region, $language) : array($language);
    }

    foreach ($candidates as $candidate) {
        if (isset($lower[$candidate])) {
            return $lower[$candidate];
        }
    }

    foreach ($lower as $code_lower => $code) {
        if (strtok($code_lower, '-') === $language) {
            return $code;
        }
    }

    return '';
}

/**
 * The languages of a country, the most widely read first: its official and
 * national languages among the ones the site can be served in
 * (pg_tr_known_languages()). A country none of whose languages is on that
 * list is left out.
 *
 * @param string $country two letters
 * @return array language codes
 */
function pg_lang_country_languages($country)
{
    static $map = array(
        'AD' => 'ca,es,fr', 'AE' => 'ar,en', 'AF' => 'fa', 'AG' => 'en', 'AI' => 'en', 'AL' => 'sq', 'AM' => 'hy',
        'AO' => 'pt', 'AR' => 'es', 'AS' => 'en', 'AT' => 'de', 'AU' => 'en', 'AW' => 'nl', 'AX' => 'sv', 'AZ' => 'az',
        'BA' => 'bs,hr,sr', 'BB' => 'en', 'BD' => 'bn', 'BE' => 'nl,fr,de', 'BF' => 'fr', 'BG' => 'bg', 'BH' => 'ar',
        'BI' => 'fr', 'BJ' => 'fr', 'BL' => 'fr', 'BM' => 'en', 'BN' => 'ms', 'BO' => 'es', 'BQ' => 'nl', 'BR' => 'pt-BR',
        'BS' => 'en', 'BW' => 'en', 'BY' => 'ru', 'BZ' => 'en,es', 'CA' => 'en,fr', 'CD' => 'fr', 'CF' => 'fr', 'CG' => 'fr',
        'CH' => 'de,fr,it', 'CI' => 'fr', 'CK' => 'en', 'CL' => 'es', 'CM' => 'fr,en', 'CN' => 'zh-CN', 'CO' => 'es',
        'CR' => 'es', 'CU' => 'es', 'CV' => 'pt', 'CW' => 'nl', 'CY' => 'el,tr', 'CZ' => 'cs', 'DE' => 'de', 'DJ' => 'fr,ar',
        'DK' => 'da', 'DM' => 'en', 'DO' => 'es', 'DZ' => 'ar,fr', 'EC' => 'es', 'EE' => 'et', 'EG' => 'ar', 'EH' => 'ar',
        'ER' => 'ar,en', 'ES' => 'es,ca,gl,eu', 'FI' => 'fi,sv', 'FJ' => 'en', 'FK' => 'en', 'FM' => 'en', 'FO' => 'da',
        'FR' => 'fr', 'GA' => 'fr', 'GB' => 'en', 'GD' => 'en', 'GE' => 'ka', 'GF' => 'fr', 'GG' => 'en', 'GH' => 'en',
        'GI' => 'en', 'GL' => 'da', 'GM' => 'en', 'GN' => 'fr', 'GP' => 'fr', 'GQ' => 'es,fr,pt', 'GR' => 'el', 'GT' => 'es',
        'GU' => 'en', 'GW' => 'pt', 'GY' => 'en', 'HK' => 'zh-TW,en', 'HN' => 'es', 'HR' => 'hr', 'HT' => 'fr', 'HU' => 'hu',
        'ID' => 'id', 'IE' => 'en,ga', 'IL' => 'he,ar', 'IM' => 'en', 'IN' => 'hi,en', 'IQ' => 'ar,ku', 'IR' => 'fa',
        'IS' => 'is', 'IT' => 'it', 'JE' => 'en', 'JM' => 'en', 'JO' => 'ar', 'JP' => 'ja', 'KE' => 'sw,en', 'KG' => 'ru',
        'KI' => 'en', 'KM' => 'fr,ar', 'KN' => 'en', 'KP' => 'ko', 'KR' => 'ko', 'KW' => 'ar', 'KY' => 'en', 'KZ' => 'kk,ru',
        'LB' => 'ar,fr', 'LC' => 'en', 'LI' => 'de', 'LR' => 'en', 'LS' => 'en', 'LT' => 'lt', 'LU' => 'fr,de', 'LV' => 'lv',
        'LY' => 'ar', 'MA' => 'ar,fr', 'MC' => 'fr', 'MD' => 'ro', 'ME' => 'sr', 'MF' => 'fr', 'MG' => 'fr', 'MH' => 'en',
        'MK' => 'mk,sq', 'ML' => 'fr', 'MO' => 'zh-TW,pt', 'MP' => 'en', 'MQ' => 'fr', 'MR' => 'ar,fr', 'MS' => 'en',
        'MT' => 'mt,en', 'MU' => 'en,fr', 'MV' => 'en', 'MW' => 'en', 'MX' => 'es', 'MY' => 'ms,en', 'MZ' => 'pt',
        'NA' => 'en,af', 'NC' => 'fr', 'NE' => 'fr', 'NF' => 'en', 'NG' => 'en', 'NI' => 'es', 'NL' => 'nl', 'NO' => 'no',
        'NR' => 'en', 'NU' => 'en', 'NZ' => 'en', 'OM' => 'ar', 'PA' => 'es', 'PE' => 'es', 'PF' => 'fr', 'PG' => 'en',
        'PH' => 'tl,en', 'PK' => 'ur,en', 'PL' => 'pl', 'PM' => 'fr', 'PR' => 'es,en', 'PS' => 'ar', 'PT' => 'pt', 'PW' => 'en',
        'PY' => 'es', 'QA' => 'ar', 'RE' => 'fr', 'RO' => 'ro', 'RS' => 'sr', 'RU' => 'ru', 'RW' => 'fr,en,sw', 'SA' => 'ar',
        'SB' => 'en', 'SC' => 'fr,en', 'SD' => 'ar,en', 'SE' => 'sv', 'SG' => 'en,zh-CN,ms', 'SH' => 'en', 'SI' => 'sl',
        'SJ' => 'no', 'SK' => 'sk', 'SL' => 'en', 'SM' => 'it', 'SN' => 'fr', 'SO' => 'ar', 'SR' => 'nl', 'SS' => 'en',
        'ST' => 'pt', 'SV' => 'es', 'SX' => 'nl,en', 'SY' => 'ar', 'SZ' => 'en', 'TC' => 'en', 'TD' => 'fr,ar', 'TF' => 'fr',
        'TG' => 'fr', 'TH' => 'th', 'TL' => 'pt', 'TN' => 'ar,fr', 'TO' => 'en', 'TR' => 'tr', 'TT' => 'en', 'TV' => 'en',
        'TW' => 'zh-TW', 'TZ' => 'sw,en', 'UA' => 'uk', 'UG' => 'en,sw', 'UM' => 'en', 'US' => 'en', 'UY' => 'es',
        'UZ' => 'uz', 'VA' => 'it', 'VC' => 'en', 'VE' => 'es', 'VG' => 'en', 'VI' => 'en', 'VN' => 'vi', 'VU' => 'fr,en',
        'WF' => 'fr', 'WS' => 'en', 'XK' => 'sq', 'YE' => 'ar', 'YT' => 'fr', 'ZA' => 'en,af', 'ZM' => 'en', 'ZW' => 'en',
    );

    $country = strtoupper((string) $country);

    return isset($map[$country]) ? explode(',', $map[$country]) : array();
}

/**
 * The language a visitor reads, from the signals the mode allows.
 *
 * @param array           $tags     from pg_lang_accept_parse()
 * @param string|callable $country  two letters, or a function that returns
 *                                  them (asked only when the country is needed)
 * @param array           $codes    the site's language codes
 * @param string          $mode     language | country | both
 * @param string          $fallback a site language for a visitor whose
 *                                  languages the site has none of; '' for none
 * @return array code ('' when nothing decides), reason (language | country |
 *               fallback | none)
 */
function pg_lang_pick($tags, $country, $codes, $mode, $fallback = '')
{
    $informed = false;

    if (($mode === 'language') || ($mode === 'both')) {
        foreach ($tags as $tag) {
            $code = pg_lang_match($tag, $codes);

            if ($code !== '') {
                return array('code' => $code, 'reason' => 'language');
            }
        }

        $informed = (count($tags) > 0);
    }

    if (($mode === 'country') || ($mode === 'both')) {
        $country = is_callable($country) ? (string) call_user_func($country) : (string) $country;

        if ($country !== '') {
            foreach (pg_lang_country_languages($country) as $language) {
                $code = pg_lang_match($language, $codes);

                if ($code !== '') {
                    return array('code' => $code, 'reason' => 'country');
                }
            }

            $informed = true;
        }
    }

    // A visitor the site says nothing to in their own language reads the
    // fallback; one who gave nothing to go on stays where they are.
    if ($informed && ($fallback !== '') && in_array($fallback, $codes, true)) {
        return array('code' => $fallback, 'reason' => 'fallback');
    }

    return array('code' => '', 'reason' => 'none');
}

/**
 * The language of the page a referring address names, when it is a page of
 * this site: the language of its directory, or the source language. Null for
 * an address on another host (or none at all) - the visit starts here.
 *
 * @param string $referer
 * @param array  $hosts    this site's host names, with the port where one is used
 * @param string $path     PATH
 * @param array  $prefixes prefix => language code
 * @param string $source
 * @return string|null
 */
function pg_lang_referer_language($referer, $hosts, $path, $prefixes, $source)
{
    $parts = ($referer !== '') ? parse_url((string) $referer) : false;

    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $host = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    $own = false;

    foreach ($hosts as $candidate) {
        if (($candidate !== '') && ((strtolower((string) $candidate) === $host) || (strtolower((string) $candidate) === strtolower($parts['host'])))) {
            $own = true;
            break;
        }
    }

    if (!$own) {
        return null;
    }

    $referer_path = isset($parts['path']) ? (string) $parts['path'] : '/';

    if (strpos($referer_path, (string) $path) !== 0) {
        return $source;
    }

    $first = strtok(substr($referer_path, strlen((string) $path)), '/');

    if (($first !== false) && isset($prefixes[$first])) {
        return (string) $prefixes[$first];
    }

    return $source;
}

/**
 * Whether the request is a person's browser opening a page: a GET for a
 * top-level document from a client that is not a crawler.
 */
function pg_lang_redirect_is_browser_visit()
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return false;
    }

    // A page drawn in a frame (the editor's preview, an embed elsewhere)
    // keeps the address it was given.
    $destination = strtolower((string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));

    if (($destination !== '') && ($destination !== 'document')) {
        return false;
    }

    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest') {
        return false;
    }

    if (defined('IS_BOT') && IS_BOT) {
        return false;
    }

    if (function_exists('waf_classify_bot')) {
        $client = waf_classify_bot((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if ($client['class'] !== 'human') {
            return false;
        }
    }

    return true;
}

/**
 * The visitor's own choice of language, when it is one the site is served in.
 *
 * @param array $codes
 * @return string
 */
function pg_lang_redirect_choice($codes)
{
    $value = (isset($_COOKIE[PG_LANGUAGE_COOKIE]) && is_string($_COOKIE[PG_LANGUAGE_COOKIE])) ? $_COOKIE[PG_LANGUAGE_COOKIE] : '';

    return in_array($value, $codes, true) ? $value : '';
}

/**
 * Remembers the visitor's choice for a year. HttpOnly: only the server reads
 * it.
 *
 * @param string $code
 */
function pg_lang_redirect_remember($code)
{
    if (headers_sent()) {
        return;
    }

    $expires = time() + 31536000;
    $secure = function_exists('pg_request_is_https') && pg_request_is_https();

    if (PHP_VERSION_ID >= 70300) {
        setcookie(PG_LANGUAGE_COOKIE, $code, array('expires' => $expires, 'path' => PATH, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    } else {
        setcookie(PG_LANGUAGE_COOKIE, $code, $expires, PATH . '; samesite=Lax', '', $secure, true);
    }

    $_COOKIE[PG_LANGUAGE_COOKIE] = $code;
}

/**
 * What the redirect decides for the request as it is: the language the
 * visitor would be sent to and why. Used by the page hook and by the
 * settings screen, which shows the operator what their own browser gets.
 *
 * @param string|null $mode     null for the saved setting
 * @param string|null $fallback null for the saved setting
 * @return array code, reason (choice | language | country | fallback | none),
 *               tags, country (code and source, when it was asked)
 */
function pg_lang_redirect_decide($mode = null, $fallback = null)
{
    $codes = pg_lang_redirect_codes();
    $mode = ($mode !== null) ? $mode : (defined('LANGUAGE_REDIRECT_MODE') ? LANGUAGE_REDIRECT_MODE : 'both');
    $fallback = ($fallback !== null) ? $fallback : (defined('LANGUAGE_REDIRECT_FALLBACK') ? LANGUAGE_REDIRECT_FALLBACK : '');
    $tags = pg_lang_accept_parse((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    $asked = null;

    $choice = pg_lang_redirect_choice($codes);

    if ($choice !== '') {
        return array('code' => $choice, 'reason' => 'choice', 'tags' => $tags, 'country' => null);
    }

    $country = function () use (&$asked) {
        $asked = function_exists('pg_geo_country') ? pg_geo_country() : array('code' => '', 'source' => '');

        return $asked['code'];
    };

    $pick = pg_lang_pick($tags, $country, $codes, $mode, $fallback);
    $pick['tags'] = $tags;
    $pick['country'] = $asked;

    return $pick;
}

/**
 * The page hook (get_page.php), called once the page is known and before
 * anything is printed. Remembers a change of language and redirects the
 * first page of a visit; returns when the page is to be drawn as asked.
 *
 * @param int  $page_id
 * @param bool $is_home whether the site root was asked for
 */
function pg_lang_redirect_page($page_id, $is_home)
{
    if (!pg_lang_redirect_enabled()) {
        return;
    }

    $codes = pg_lang_redirect_codes();

    // An address this redirect may answer for depends on who asks (browser,
    // Accept-Language, country, cookie, referer, staff), so its page is never
    // kept by a shared cache, whichever way this visitor is answered.
    if ((count($codes) >= 2) && !pg_tr_active() && function_exists('pg_page_cache_personal')
        && ($is_home || (defined('LANGUAGE_REDIRECT_SCOPE') && (LANGUAGE_REDIRECT_SCOPE === 'all')))) {
        pg_page_cache_personal('language_redirect');
    }

    if (!pg_lang_redirect_is_browser_visit()) {
        return;
    }

    $source = pg_tr_source_language();

    if (count($codes) < 2) {
        return;
    }

    $current = pg_tr_active() ? pg_tr_language() : $source;

    $referer_language = pg_lang_referer_language(
        (string) ($_SERVER['HTTP_REFERER'] ?? ''),
        array((string) ($_SERVER['HTTP_HOST'] ?? ''), defined('HOSTNAME') ? (string) HOSTNAME : ''),
        (string) PATH,
        pg_tr_prefix_map(),
        $source);

    // From a page in one language to a page in another: the visitor chose.
    if (($referer_language !== null) && ($referer_language !== $current)) {
        if (pg_lang_redirect_choice($codes) !== $current) {
            pg_lang_redirect_remember($current);
        }

        return;
    }

    // An address in a language directory is served as asked, a visitor who
    // is already moving around the site is left where they went, and staff
    // see the page they opened.
    if (pg_tr_active() || ($referer_language !== null) || isset($_SESSION['sessionusername'])) {
        return;
    }

    if (!$is_home && (!defined('LANGUAGE_REDIRECT_SCOPE') || (LANGUAGE_REDIRECT_SCOPE !== 'all'))) {
        return;
    }

    $decision = pg_lang_redirect_decide();

    if (($decision['code'] === '') || ($decision['code'] === $source)) {
        return;
    }

    // Only a page drawn from a layout tree exists in the other languages: the
    // same test the translated body is drawn by (includes/translate/render.php).
    // layout_type alone is not it - the legacy system layouts carry 'system' too.
    $tree_expression = pg_page_tree_sql_expr();
    $has_tree = db_value("SELECT ($tree_expression IS NOT NULL AND $tree_expression <> '')
                          FROM page
                          INNER JOIN style ON page.page_style = style.style_id
                          WHERE page.page_id = '" . (int) $page_id . "' AND page.layout_type = 'system' LIMIT 1");

    if (!$has_tree) {
        return;
    }

    pg_tr_load();

    $request = (string) REQUEST_URL;
    $question = strpos($request, '?');
    $path = rawurldecode(($question === false) ? $request : substr($request, 0, $question));
    $query = ($question === false) ? '' : substr($request, $question);

    $target = pg_tr_prefix_path($path, pg_tr_prefix($decision['code']));

    if ($target === $path) {
        return;
    }

    // Kept by no cache: the next visitor of the same address may read
    // another language.
    header('Vary: Accept-Language, Cookie', false);
    header('Cache-Control: private, no-store, max-age=0');
    header('Location: ' . URL_SCHEME . HOSTNAME . pg_tr_encode_path($target) . $query, true, 302);
    exit();
}
