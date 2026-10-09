<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Cookie consent: the notice in the bottom-left corner of a visitor page, the
 * visitor's choice, and the gate the optional cookies go through.
 *
 * The choice lives in one first-party cookie, pg_consent, written by the
 * notice's script (assets/js/pg_consent.js) and read here. Its value is a
 * version followed by one flag per category the visitor has answered:
 * "1.a1.m0" is statistics yes, marketing no. A category the value does not
 * mention is unanswered, so a category that appears on the site later is
 * asked about again without losing the answers already given.
 *
 * Necessary cookies are never gated. The statistics and marketing cookies the
 * software sets itself go through pg_consent_setcookie(); third-party scripts
 * are written as inert <script type="text/plain" data-pg-consent="..."> and
 * the notice's script starts them in place once the visitor agrees, so no
 * reload is needed. With the setting off nothing is held back and every
 * cookie is set as it always was.
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
 * Whether the site asks for consent. False before the schema step has run.
 */
function pg_consent_enabled()
{
    return defined('COOKIE_CONSENT') && COOKIE_CONSENT;
}

/**
 * The optional categories and the letter each one has in the cookie value.
 * The notice's script carries the same map.
 *
 * @return array category => letter
 */
function pg_consent_category_letters()
{
    return array('analytics' => 'a', 'marketing' => 'm');
}

/**
 * The answers a pg_consent cookie value holds. An unknown version or a
 * malformed value counts as no answer at all; an unknown part is skipped.
 *
 * @param mixed $raw the cookie value
 * @return array category => bool, only for the categories answered
 */
function pg_consent_parse($raw)
{
    $choices = array();

    if (!is_string($raw) || ($raw === '')) {
        return $choices;
    }

    $parts = explode('.', $raw);

    if (array_shift($parts) !== '1') {
        return $choices;
    }

    $categories = array_flip(pg_consent_category_letters());

    foreach ($parts as $part) {
        if (preg_match('/^([a-z])([01])$/', $part, $match) && isset($categories[$match[1]])) {
            $choices[$categories[$match[1]]] = ($match[2] === '1');
        }
    }

    return $choices;
}

/**
 * Whether a cookie of an optional category may be set on this request: always
 * when the site does not ask for consent, otherwise only after the visitor
 * said yes to that category.
 *
 * @param string $category analytics | marketing
 * @return bool
 */
function pg_consent_allows($category)
{
    if (!pg_consent_enabled()) {
        return true;
    }

    $raw = (isset($_COOKIE['pg_consent']) && is_string($_COOKIE['pg_consent'])) ? $_COOKIE['pg_consent'] : '';
    $choices = pg_consent_parse($raw);

    return !empty($choices[$category]);
}

/**
 * setcookie() for a cookie of an optional category: set only when the visitor
 * allowed the category. The value the caller also keeps in the session still
 * serves the visit; only remembering it beyond the visit waits for consent.
 *
 * @return bool whether the cookie was sent
 */
function pg_consent_setcookie($category, $name, $value, $expires)
{
    if (!pg_consent_allows($category)) {
        return false;
    }

    return setcookie($name, $value, $expires, '/');
}

/**
 * Turns every <script> tag of a piece of markup into an inert one held for a
 * category: the browser neither runs it nor fetches its src (kept as
 * data-pg-src) until the notice's script starts it. Meant for the software's
 * own tags, which the notice lists itself: data-pg-consent-builtin keeps them
 * out of pg_consent_page_services().
 *
 * @param string $html
 * @param string $category analytics | marketing
 * @return string
 */
function pg_consent_hold_scripts($html, $category)
{
    return preg_replace_callback('/<script\b([^>]*)>/i', function ($match) use ($category) {
        $attributes = preg_replace('/\s(?:type|data-pg-consent|data-pg-consent-builtin)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $match[1]);
        $attributes = preg_replace('/\ssrc\s*=/i', ' data-pg-src=', $attributes);

        return '<script type="text/plain" data-pg-consent="' . h($category) . '" data-pg-consent-builtin="1"' . $attributes . '>';
    }, (string) $html);
}

/**
 * The scripts a page holds back on its own: a designer writes a third-party
 * script as <script type="text/plain" data-pg-consent="marketing"
 * data-pg-consent-name="Meta Pixel"> and the notice lists it under its
 * category. A script without a name is listed as an unnamed service.
 *
 * @param string $content the page
 * @return array category => list of names ('' for an unnamed one), each name once
 */
function pg_consent_page_services($content)
{
    $services = array_fill_keys(array_keys(pg_consent_category_letters()), array());

    if (!preg_match_all('/<script\b[^>]*\bdata-pg-consent\s*=\s*("|\')(analytics|marketing)\1[^>]*>/i', (string) $content, $tags, PREG_SET_ORDER)) {
        return $services;
    }

    foreach ($tags as $tag) {
        if (preg_match('/\bdata-pg-consent-builtin\s*=/i', $tag[0])) {
            continue;
        }

        $category = strtolower($tag[2]);
        $name = '';

        if (preg_match('/\bdata-pg-consent-name\s*=\s*("|\')(.*?)\1/is', $tag[0], $named)) {
            $name = trim(html_entity_decode($named[2], ENT_QUOTES, 'UTF-8'));
        }

        if (!in_array($name, $services[$category], true)) {
            $services[$category][] = $name;
        }
    }

    return $services;
}

/**
 * What the notice lists: the necessary cookies, and for each optional
 * category what this site and this page actually use. An empty optional
 * category is not shown at all.
 *
 * @param string $content the page, for the scripts it holds back itself
 * @return array category => list of array(label, is cookie name, purpose)
 */
function pg_consent_catalogue($content)
{
    $session_name = session_name();

    $catalogue = array(
        'necessary' => array(
            array(($session_name !== '' && $session_name !== false) ? $session_name : 'PHPSESSID', true, lang('Keeps your visit together: the cart, signing in and the steps of a form.')),
            array('software[auth]', true, lang('Keeps you signed in.')),
            array('software[remember_me]', true, lang('Remembers whether you chose to stay signed in.')),
            array('software[device_type]', true, lang('Remembers the desktop or mobile view you picked.')),
            array('pg_consent', true, lang('Remembers your cookie choices.')),
        ),
        'analytics' => array(),
        'marketing' => array(),
    );

    $tracking = defined('VISITOR_TRACKING') && VISITOR_TRACKING;

    if ($tracking) {
        $catalogue['analytics'][] = array('software[number_of_visits]', true, lang('Counts your visits, to tell new visitors from returning ones.'));
        $catalogue['analytics'][] = array('software[tracking_code]', true, lang('Remembers the tracking code of the link you arrived with.'));
        $catalogue['analytics'][] = array('lsid', true, lang('Remembers the campaign (UTM) that brought you to the site.'));
    }

    if (defined('GOOGLE_ANALYTICS') && GOOGLE_ANALYTICS && defined('GOOGLE_ANALYTICS_WEB_PROPERTY_ID') && (GOOGLE_ANALYTICS_WEB_PROPERTY_ID != '')) {
        $catalogue['analytics'][] = array('_ga, _ga_*', true, lang('Google Analytics: tells visits apart to measure how the site is used.'));
    }

    if ($tracking && defined('AFFILIATE_PROGRAM') && AFFILIATE_PROGRAM) {
        $catalogue['marketing'][] = array('software[affiliate_code]', true, lang('Remembers the partner who referred you, so the partner can be credited.'));
    }

    foreach (pg_consent_page_services($content) as $category => $names) {
        foreach ($names as $name) {
            $catalogue[$category][] = array(($name !== '') ? $name : lang('Other services'), false, lang('A service added by this site. It starts only if you allow it.'));
        }
    }

    return $catalogue;
}

/**
 * The cookies the notice's script deletes when a category is turned off
 * again. A trailing * matches by prefix (Google Analytics names one cookie
 * per property).
 *
 * @return array category => list of cookie names
 */
function pg_consent_revoke_names()
{
    return array(
        'analytics' => array('software[number_of_visits]', 'software[tracking_code]', 'lsid', '_ga', '_ga_*', '_gid', '_gat*'),
        'marketing' => array('software[affiliate_code]'),
    );
}

/**
 * The cookie icon (inline, so a page needs no icon font for it).
 */
function pg_consent_icon()
{
    return '<svg class="pg-cc-cookie" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">'
        . '<path d="M20.96 11.2A9 9 0 1 1 12.8 3.04a3 3 0 0 0 4.08 4.08a3 3 0 0 0 4.08 4.08z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>'
        . '<circle cx="8.2" cy="9.2" r="1.15" fill="currentColor"/>'
        . '<circle cx="12.6" cy="12.8" r="1.05" fill="currentColor"/>'
        . '<circle cx="8.6" cy="15.6" r="1.15" fill="currentColor"/>'
        . '<circle cx="15.9" cy="16.4" r="1.05" fill="currentColor"/>'
        . '<circle cx="17.3" cy="12.9" r=".8" fill="currentColor"/>'
        . '</svg>';
}

/**
 * The notice: its stylesheet, its markup and its script. Built with lang(),
 * so on a page under a language directory (/en/, /ko/) it is drawn in that
 * language like the rest of the software's wording.
 *
 * @param string $content the page, for the scripts it holds back itself
 * @return string
 */
function pg_consent_markup($content)
{
    $catalogue = pg_consent_catalogue($content);
    $revoke = pg_consent_revoke_names();

    $labels = array(
        'necessary' => array(lang('Necessary'), lang('Needed for the site to work, so they cannot be turned off.')),
        'analytics' => array(lang('Statistics'), lang('Show us how visitors use the site, so we can improve it.')),
        'marketing' => array(lang('Marketing'), lang('Credit the partner or campaign that brought you here and measure advertising.')),
    );

    $optional = array();
    $output_categories = '';

    foreach ($labels as $category => $label) {
        if (empty($catalogue[$category])) {
            continue;
        }

        $locked = ($category === 'necessary');

        if (!$locked) {
            $optional[] = $category;
        }

        $output_items = '';

        foreach ($catalogue[$category] as $item) {
            $output_items .= '<li>' . ($item[1] ? '<code>' . h($item[0]) . '</code>' : '<strong>' . h($item[0]) . '</strong>')
                . '<span>' . h($item[2]) . '</span></li>';
        }

        $id = 'pg-cc-' . $category;

        // Optional categories start switched on; nothing of theirs runs
        // until the visitor saves or accepts (pg_consent.js).
        $output_switch = $locked
            ? '<span class="pg-cc-always">' . h(lang('Always on')) . '</span>'
            : '<input type="checkbox" class="pg-cc-switch" role="switch" id="' . $id . '" data-pg-cc-category="' . $category . '"'
                . ' data-pg-cc-revoke="' . h(implode(' ', isset($revoke[$category]) ? $revoke[$category] : array())) . '" checked aria-describedby="' . $id . '-text">';

        $output_categories .=
            '<div class="pg-cc-cat">
                <div class="pg-cc-cat-row">
                    <label class="pg-cc-cat-name"' . ($locked ? '' : ' for="' . $id . '"') . '>' . h($label[0]) . '</label>
                    ' . $output_switch . '
                </div>
                <p class="pg-cc-cat-text" id="' . $id . '-text">' . h($label[1]) . '</p>
                <details class="pg-cc-list">
                    <summary>' . h(lang('Show cookies')) . '</summary>
                    <ul>' . $output_items . '</ul>
                </details>
            </div>';
    }

    $output_policy = '';
    $policy_url = defined('COOKIE_CONSENT_POLICY_URL') ? escape_url(trim((string) COOKIE_CONSENT_POLICY_URL)) : false;

    if ($policy_url !== false) {
        $output_policy = ' <a class="pg-cc-link" href="' . h($policy_url) . '">' . h(lang('Cookie policy')) . '</a>';
    }

    if ($optional) {
        $intro = lang('This site uses the cookies it needs to work. Other cookies are used only if you allow them; you can choose them in Settings.');
        $accept_label = lang('Accept all');
        $output_prefs_actions =
            '<button type="button" class="pg-cc-btn" data-pg-cc="reject">' . h(lang('Only necessary')) . '</button>
            <button type="button" class="pg-cc-btn pg-cc-primary" data-pg-cc="save">' . h(lang('Save choices')) . '</button>';
    } else {
        $intro = lang('This site only uses the cookies it needs to work.');
        $accept_label = lang('OK');
        $output_prefs_actions =
            '<button type="button" class="pg-cc-btn pg-cc-primary" data-pg-cc="save">' . h(lang('OK')) . '</button>';
    }

    $close_label = $optional ? lang('Close and keep only the necessary cookies') : lang('Close');
    $path = defined('PATH') ? PATH : '/';

    return '
<link rel="stylesheet" href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/css/pg_consent.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/css/pg_consent.css') . '">
<div id="pg-cc" class="pg-cc" data-path="' . h($path) . '" data-categories="' . h(implode(',', $optional)) . '">
    <button type="button" class="pg-cc-fab" hidden aria-label="' . h(lang('Cookie settings')) . '" title="' . h(lang('Cookie settings')) . '" aria-controls="pg-cc-card" aria-expanded="false">' . pg_consent_icon() . '</button>
    <div class="pg-cc-card" id="pg-cc-card" role="dialog" aria-modal="false" aria-labelledby="pg-cc-title" hidden>
        <div class="pg-cc-head">
            <span class="pg-cc-badge">' . pg_consent_icon() . '</span>
            <strong class="pg-cc-title" id="pg-cc-title">' . h(lang('Cookies')) . '</strong>
            <button type="button" class="pg-cc-x" data-pg-cc="close" aria-label="' . h($close_label) . '" title="' . h($close_label) . '"><svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9m0-9l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
        </div>
        <div class="pg-cc-view" data-pg-cc-view="intro">
            <p class="pg-cc-text">' . h($intro) . $output_policy . '</p>
            <div class="pg-cc-actions">
                <button type="button" class="pg-cc-btn" data-pg-cc="settings">' . h(lang('Settings')) . '</button>
                <button type="button" class="pg-cc-btn pg-cc-primary" data-pg-cc="accept">' . h($accept_label) . '</button>
            </div>
        </div>
        <div class="pg-cc-view" data-pg-cc-view="prefs" hidden>
            <div class="pg-cc-cats">' . $output_categories . '</div>
            <div class="pg-cc-actions">' . $output_prefs_actions . '</div>
        </div>
    </div>
</div>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_consent.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_consent.js') . '" defer></script>
';
}

/**
 * Every lang() key the notice can print, in the order it appears in this
 * file. In a language with no language file these, and only these, of the
 * software's wording are translated through the site's translations: the
 * "Cookie window" group of the Translations screen (pg_tr_ui_text()). The
 * rest of the software's wording comes from the language files alone.
 * tests/consent_test.php keeps the list in step with the lang() calls above.
 *
 * @return array
 */
function pg_consent_ui_keys()
{
    return array(
        'Keeps your visit together: the cart, signing in and the steps of a form.',
        'Keeps you signed in.',
        'Remembers whether you chose to stay signed in.',
        'Remembers the desktop or mobile view you picked.',
        'Remembers your cookie choices.',
        'Counts your visits, to tell new visitors from returning ones.',
        'Remembers the tracking code of the link you arrived with.',
        'Remembers the campaign (UTM) that brought you to the site.',
        'Google Analytics: tells visits apart to measure how the site is used.',
        'Remembers the partner who referred you, so the partner can be credited.',
        'Other services',
        'A service added by this site. It starts only if you allow it.',
        'Necessary',
        'Needed for the site to work, so they cannot be turned off.',
        'Statistics',
        'Show us how visitors use the site, so we can improve it.',
        'Marketing',
        'Credit the partner or campaign that brought you here and measure advertising.',
        'Always on',
        'Show cookies',
        'Cookie policy',
        'This site uses the cookies it needs to work. Other cookies are used only if you allow them; you can choose them in Settings.',
        'Accept all',
        'Only necessary',
        'Save choices',
        'This site only uses the cookies it needs to work.',
        'OK',
        'Close and keep only the necessary cookies',
        'Close',
        'Cookie settings',
        'Cookies',
        'Settings',
    );
}

/**
 * The notice added to a visitor page, just before its last </body>. Nothing
 * for a crawler, and nothing for a document that is not a whole HTML page.
 *
 * @param string $content
 * @return string
 */
function pg_consent_inject($content)
{
    if (!pg_consent_enabled() || (defined('IS_BOT') && IS_BOT)) {
        return $content;
    }

    $position = strripos($content, '</body>');

    if ($position === false) {
        return $content;
    }

    return substr_replace($content, pg_consent_markup($content), $position, 0);
}
