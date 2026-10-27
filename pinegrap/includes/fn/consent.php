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
 * Refusing is offered exactly like accepting: the first view of the notice
 * draws Settings, Reject all and Accept all alike, and no optional category
 * is switched on for the visitor. Google tags a site adds without holding
 * them hear the answer through Google Consent Mode v2: the page's <head>
 * opens with a "default" from the stored answer (everything denied until the
 * visitor allows it) and the notice's script sends each answer as an
 * "update" before any held script starts.
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
 * How many answers one address may record in the window, counted with the
 * firewall's counters, which work with the firewall off. A visitor answers a
 * few times an hour at most; a script posting in a loop is stopped here.
 */
define('PG_CONSENT_ANSWER_WRITE_LIMIT', 20);
define('PG_CONSENT_ANSWER_WRITE_WINDOW', 3600);

/**
 * The longest pg_consent value an answer post may carry. A value the script
 * writes is a few bytes per category; a longer one is refused before it is
 * split.
 */
define('PG_CONSENT_ANSWER_MAX_LENGTH', 32);

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
 * Whether a value is one the notice's script writes: version 1, then each
 * known category letter at most once with 0 or 1. Stricter than
 * pg_consent_parse(), which reads what it can: an answer posted to
 * consent_answer.php that the script could not have written records nothing.
 *
 * @param mixed $raw
 * @return bool
 */
function pg_consent_answer_valid($raw)
{
    if (!is_string($raw) || (strlen($raw) > PG_CONSENT_ANSWER_MAX_LENGTH)
        || (preg_match('/^1((?:\.[a-z][01])*)$/', $raw, $match) !== 1)) {
        return false;
    }

    $known = array_flip(pg_consent_category_letters());
    $seen = array();

    foreach (($match[1] === '') ? array() : explode('.', substr($match[1], 1)) as $part) {
        $letter = $part[0];

        if (!isset($known[$letter]) || isset($seen[$letter])) {
            return false;
        }

        $seen[$letter] = true;
    }

    return true;
}

/**
 * The consent_log rows an answer from the notice needs: one per category
 * whose answer differs from the cookie value before it. A category the answer
 * leaves out is no change; a value before it that cannot be read counts as no
 * answer at all. The baseline is the visitor's own cookie, not the log: an
 * anonymous visitor has no rows to compare with, and every browser answers
 * for itself.
 *
 * @param mixed $before the pg_consent value before the answer
 * @param mixed $answer the value the notice wrote
 * @return array[]|null  list of array('channel' => 'cookie', 'purpose' => ..,
 *                       'status' => granted | refused), or null when the
 *                       answer is malformed (pg_consent_answer_valid()) or
 *                       the value before it is longer than any the script
 *                       writes
 */
function pg_consent_answer_changes($before, $answer)
{
    if (!pg_consent_answer_valid($answer) || (is_string($before) && (strlen($before) > PG_CONSENT_ANSWER_MAX_LENGTH))) {
        return null;
    }

    $old = pg_consent_parse($before);
    $new = pg_consent_parse($answer);
    $changes = array();

    // In the order of the categories, whatever the order in the value.
    foreach (array_keys(pg_consent_category_letters()) as $category) {
        if (!isset($new[$category]) || (isset($old[$category]) && ($old[$category] === $new[$category]))) {
            continue;
        }

        $changes[] = array('channel' => 'cookie', 'purpose' => $category, 'status' => $new[$category] ? 'granted' : 'refused');
    }

    return $changes;
}

/**
 * Whether this request's address has recorded too many answers in the
 * window (PG_CONSENT_ANSWER_WRITE_LIMIT). The same-origin check stops other
 * sites' pages, not scripts, and the firewall's own limits refuse nothing
 * while it is off or in monitor mode, so the endpoint counts for itself.
 *
 * @return bool
 */
function pg_consent_answer_throttled()
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if ($ip === '') {
        return false;
    }

    $exceeded = waf_rate_exceeded(waf_ip_subject($ip), 'consent_answer', PG_CONSENT_ANSWER_WRITE_LIMIT, PG_CONSENT_ANSWER_WRITE_WINDOW);

    if (function_exists('waf_rate_sweep') && (mt_rand(1, 50) === 1)) {
        waf_rate_sweep();
    }

    if (!$exceeded) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-consent-answer', 'rate', 5, 'consent_answer.php', PG_CONSENT_ANSWER_WRITE_LIMIT . '/' . (int) round(PG_CONSENT_ANSWER_WRITE_WINDOW / 60) . 'min');
    }

    if (function_exists('waf_text_log')) {
        waf_text_log('DENY', $ip, array('status' => 429, 'rule' => 'rate-consent-answer'));
    }

    return true;
}

/**
 * Whether a post comes from a page of this site. The answer endpoint is
 * anonymous and a cached page carries no form token, so this stands in for
 * the CSRF check: Sec-Fetch-Site when the browser sends it, otherwise the
 * Origin's host (and port) against the Host header. A post with neither is
 * refused; every browser that runs the notice sends Origin on a POST.
 *
 * @param array $server $_SERVER
 * @return bool
 */
function pg_consent_answer_same_origin(array $server)
{
    if (isset($server['HTTP_SEC_FETCH_SITE']) && is_string($server['HTTP_SEC_FETCH_SITE'])) {
        return ($server['HTTP_SEC_FETCH_SITE'] === 'same-origin');
    }

    $origin = isset($server['HTTP_ORIGIN']) && is_string($server['HTTP_ORIGIN']) ? $server['HTTP_ORIGIN'] : '';
    $host = isset($server['HTTP_HOST']) && is_string($server['HTTP_HOST']) ? strtolower(trim($server['HTTP_HOST'])) : '';

    if (($origin === '') || ($host === '')) {
        return false;
    }

    $parts = parse_url($origin);

    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }

    $from = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');

    return hash_equals($host, $from);
}

/**
 * The answers this request's visitor has given, from the pg_consent cookie.
 *
 * @return array category => bool, only for the categories answered
 */
function pg_consent_choices()
{
    $raw = (isset($_COOKIE['pg_consent']) && is_string($_COOKIE['pg_consent'])) ? $_COOKIE['pg_consent'] : '';

    return pg_consent_parse($raw);
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

    $choices = pg_consent_choices();

    return !empty($choices[$category]);
}

/**
 * The Google Consent Mode v2 states for a set of answers: statistics decides
 * analytics_storage, marketing the three advertising types. A category not
 * answered yet, or refused, is denied. pg_consent.js maps an answer the same
 * way when it sends the update.
 *
 * @param array $choices category => bool (pg_consent_parse())
 * @return array state name => granted | denied
 */
function pg_consent_mode_states($choices)
{
    $marketing = !empty($choices['marketing']) ? 'granted' : 'denied';

    return array(
        'ad_storage' => $marketing,
        'ad_user_data' => $marketing,
        'ad_personalization' => $marketing,
        'analytics_storage' => !empty($choices['analytics']) ? 'granted' : 'denied',
    );
}

/**
 * The inline script that sets the Consent Mode default. It only queues the
 * command on window.dataLayer, the way Google's own snippet does, and leaves
 * window.gtag alone: a page without Google tags gets nothing else from it,
 * and gtag.js or Tag Manager reads the queue in order when it loads.
 *
 * @param array $choices category => bool (pg_consent_parse())
 * @return string
 */
function pg_consent_mode_script($choices)
{
    return '<script data-pg-consent-mode>window.dataLayer=window.dataLayer||[];'
        . '(function(){function gtag(){dataLayer.push(arguments);}'
        . 'gtag("consent","default",' . json_encode(pg_consent_mode_states($choices)) . ');})();</script>';
}

/**
 * Where the Consent Mode default goes: straight after the page's <head> tag,
 * ahead of every script of the page, the software's own included, since a
 * Google tag that runs before the default is not bound by it. A <meta> with
 * the character set that opens the head stays first.
 *
 * @param string $content the page
 * @return int|false byte offset, false when the page has no <head> tag
 */
function pg_consent_mode_position($content)
{
    if (!preg_match('/<head\b[^>]*>(?:\s*<meta\b[^>]*\bcharset\s*=[^>]*>)?/i', (string) $content, $match, PREG_OFFSET_CAPTURE)) {
        return false;
    }

    return $match[0][1] + strlen($match[0][0]);
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
            // Written on every page for a signed-in user (get_page.php,
            // update_view_page_mode.php).
            array('software[view_page_mode]', true, lang('For a signed-in user, remembers whether pages show their editing tools.')),
        ),
        'analytics' => array(),
        'marketing' => array(),
    );

    // The language redirect remembers the language a visitor moved to
    // (pg_lang_redirect_remember()); it writes the cookie only when it is on.
    if (function_exists('pg_lang_redirect_enabled') && pg_lang_redirect_enabled()) {
        $catalogue['necessary'][] = array(PG_LANGUAGE_COOKIE, true, lang('Remembers the language you chose for the site.'));
    }

    // The page cache marks a session with a cart, a sign-in or a message
    // (pg_page_cache_marker_hook()); it writes the cookie only when it is on.
    if (function_exists('pg_page_cache_ttl') && (pg_page_cache_ttl() > 0)) {
        $catalogue['necessary'][] = array(PG_PAGE_CACHE_MARKER, true, lang('Tells a cache in front of the site to pass your requests through while your visit holds a cart, a sign-in or a message.'));
    }

    // A visitor who is not signed in keeps their favourites under this
    // cookie, set only when they save a product (pg_wishlist_owner()).
    if (function_exists('pg_wishlist_ready') && pg_wishlist_ready()) {
        $catalogue['necessary'][] = array(PG_WISHLIST_COOKIE, true, lang('Remembers the products you saved to your favourites before signing in.'));
    }

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

    // The CAPTCHA provider of the Security settings guards the site's forms
    // and a form cannot be sent without it, so it is not held for consent.
    $captcha_provider = (defined('CAPTCHA') && CAPTCHA && function_exists('pg_captcha_provider')) ? pg_captcha_provider() : '';

    if ($captcha_provider !== '') {
        $catalogue['necessary'][] = array(pg_captcha_provider_definition($captcha_provider)['label'], false, lang('Checks that a form is sent by a person; loaded only on pages with a form.'));
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

        // Optional categories start switched off: consent is what the visitor
        // turns on, never a box ticked for them. pg_consent.js switches on
        // what the stored answer allows when the notice opens.
        $output_switch = $locked
            ? '<span class="pg-cc-always">' . h(lang('Always on')) . '</span>'
            : '<input type="checkbox" class="pg-cc-switch" role="switch" id="' . $id . '" data-pg-cc-category="' . $category . '"'
                . ' data-pg-cc-revoke="' . h(implode(' ', isset($revoke[$category]) ? $revoke[$category] : array())) . '" aria-describedby="' . $id . '-text">';

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

    $output_settings_button = '<button type="button" class="pg-cc-btn" data-pg-cc="settings">' . h(lang('Settings')) . '</button>';

    if ($optional) {
        $intro = lang('This site uses the cookies it needs to work. Other cookies are used only if you allow them; you can choose them in Settings.');

        // Refusing must be as easy as accepting: both answers sit side by
        // side in the first view, drawn the same and as wide as each other
        // (.pg-cc-choices), neither of them marked as the one to press.
        $output_intro_actions =
            '<div class="pg-cc-actions pg-cc-choices">
                ' . $output_settings_button . '
                <button type="button" class="pg-cc-btn" data-pg-cc="reject">' . h(lang('Reject all')) . '</button>
                <button type="button" class="pg-cc-btn" data-pg-cc="accept">' . h(lang('Accept all')) . '</button>
            </div>';

        $output_prefs_actions =
            '<button type="button" class="pg-cc-btn" data-pg-cc="reject">' . h(lang('Only necessary')) . '</button>
            <button type="button" class="pg-cc-btn pg-cc-primary" data-pg-cc="save">' . h(lang('Save choices')) . '</button>';
    } else {
        $intro = lang('This site only uses the cookies it needs to work.');

        $output_intro_actions =
            '<div class="pg-cc-actions">
                ' . $output_settings_button . '
                <button type="button" class="pg-cc-btn pg-cc-primary" data-pg-cc="accept">' . h(lang('OK')) . '</button>
            </div>';

        $output_prefs_actions =
            '<button type="button" class="pg-cc-btn pg-cc-primary" data-pg-cc="save">' . h(lang('OK')) . '</button>';
    }

    $close_label = $optional ? lang('Close and keep only the necessary cookies') : lang('Close');
    $path = defined('PATH') ? PATH : '/';

    // Always defined on a request; guarded so the notice can be drawn where
    // init.php has not run (tests/consent_test.php).
    $assets = (defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/assets';

    // Where the script records an answer in consent_log (consent_answer.php);
    // with nothing optional to ask there is no answer to record.
    $output_log = $optional
        ? ' data-log="' . h((defined('OUTPUT_PATH') ? OUTPUT_PATH : '/') . (defined('OUTPUT_SOFTWARE_DIRECTORY') ? OUTPUT_SOFTWARE_DIRECTORY : '') . '/consent_answer.php') . '"'
        : '';

    return '
<link rel="stylesheet" href="' . $assets . '/css/pg_consent.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/css/pg_consent.css') . '">
<div id="pg-cc" class="pg-cc" data-path="' . h($path) . '" data-categories="' . h(implode(',', $optional)) . '"' . $output_log . '>
    <button type="button" class="pg-cc-fab" hidden aria-label="' . h(lang('Cookie settings')) . '" title="' . h(lang('Cookie settings')) . '" aria-controls="pg-cc-card" aria-expanded="false">' . pg_consent_icon() . '</button>
    <div class="pg-cc-card" id="pg-cc-card" role="dialog" aria-modal="false" aria-labelledby="pg-cc-title" hidden>
        <div class="pg-cc-head">
            <span class="pg-cc-badge">' . pg_consent_icon() . '</span>
            <strong class="pg-cc-title" id="pg-cc-title">' . h(lang('Cookies')) . '</strong>
            <button type="button" class="pg-cc-x" data-pg-cc="close" aria-label="' . h($close_label) . '" title="' . h($close_label) . '"><svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false"><path d="M3.5 3.5l9 9m0-9l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
        </div>
        <div class="pg-cc-view" data-pg-cc-view="intro">
            <p class="pg-cc-text">' . h($intro) . $output_policy . '</p>
            ' . $output_intro_actions . '
        </div>
        <div class="pg-cc-view" data-pg-cc-view="prefs" hidden>
            <div class="pg-cc-cats">' . $output_categories . '</div>
            <div class="pg-cc-actions">' . $output_prefs_actions . '</div>
        </div>
    </div>
</div>
<script src="' . $assets . '/js/pg_consent.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_consent.js') . '" defer></script>
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
        'For a signed-in user, remembers whether pages show their editing tools.',
        'Remembers the language you chose for the site.',
        'Tells a cache in front of the site to pass your requests through while your visit holds a cart, a sign-in or a message.',
        'Remembers the products you saved to your favourites before signing in.',
        'Counts your visits, to tell new visitors from returning ones.',
        'Remembers the tracking code of the link you arrived with.',
        'Remembers the campaign (UTM) that brought you to the site.',
        'Google Analytics: tells visits apart to measure how the site is used.',
        'Remembers the partner who referred you, so the partner can be credited.',
        'Checks that a form is sent by a person; loaded only on pages with a form.',
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
        'Settings',
        'This site uses the cookies it needs to work. Other cookies are used only if you allow them; you can choose them in Settings.',
        'Reject all',
        'Accept all',
        'Only necessary',
        'Save choices',
        'This site only uses the cookies it needs to work.',
        'OK',
        'Close and keep only the necessary cookies',
        'Close',
        'Cookie settings',
        'Cookies',
    );
}

/**
 * The notice and the Consent Mode default added to a visitor page
 * (pg_consent_insert()). Nothing for a crawler.
 *
 * @param string $content
 * @return string
 */
function pg_consent_inject($content)
{
    if (!pg_consent_enabled()) {
        return $content;
    }

    if (defined('IS_BOT') && IS_BOT) {
        // A page without the notice must never be shared with people.
        if (function_exists('pg_page_cache_personal')) {
            pg_page_cache_personal('crawler');
        }

        return $content;
    }

    return pg_consent_insert($content, pg_consent_choices());
}

/**
 * The notice just before the page's last </body>, and the Consent Mode
 * default for the visitor's answers at the top of its <head>
 * (pg_consent_mode_position()). Nothing for a document that is not a whole
 * HTML page (no </body>); a page without a <head> tag gets the notice only,
 * whose script still sends each answer as an update.
 *
 * @param string $content the page
 * @param array $choices category => bool, the stored answers (pg_consent_choices())
 * @return string
 */
function pg_consent_insert($content, $choices)
{
    $content = (string) $content;
    $body = strripos($content, '</body>');

    if ($body === false) {
        return $content;
    }

    $head = pg_consent_mode_position($content);

    // The notice first: it goes further in, so the head offset still holds.
    $content = substr_replace($content, pg_consent_markup($content), $body, 0);

    if (($head !== false) && ($head <= $body)) {
        $content = substr_replace($content, pg_consent_mode_script($choices), $head, 0);
    }

    return $content;
}
