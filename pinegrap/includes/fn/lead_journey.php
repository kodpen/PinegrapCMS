<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor journey's pure rules: when a browser may carry the journey
 * key (only with the cookie notice's marketing yes), when a person's own
 * act ties that browser to a contact and when a later act by another
 * contact moves it (a shared computer), which earlier visits are tied back
 * (after the consent, inside the keep period), and the interest score, whose
 * points fade by a half-life.
 *
 * Nothing here reads the database, writes a cookie or prints text: callers
 * pass the facts in and act on the returned codes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Default keep period of the journey, in months. */
const PG_LEAD_KEEP_MONTHS = 13;

/** Default half-life of an interest point, in days. */
const PG_LEAD_HALF_LIFE_DAYS = 30;

/** A visit this many days after the one before counts as a quick return. */
const PG_LEAD_QUICK_RETURN_DAYS = 7;

/**
 * What to do with the browser's journey key for a cookie notice answer.
 *
 * Only the marketing category's yes keeps a key; a refusal, no answer yet
 * or the notice turned off means no key, and an existing one is deleted.
 *
 * @param string $consent 'yes', 'no', 'unanswered' or 'off'
 * @param bool   $has_key whether the browser carries a key now
 * @return string 'write', 'keep', 'delete' or 'none'
 */
function pg_lead_key_verdict($consent, $has_key)
{
    if ($consent === 'yes') {
        return $has_key ? 'keep' : 'write';
    }

    return $has_key ? 'delete' : 'none';
}

/**
 * The person's own acts that tie a browser to a contact. A newsletter yes
 * counts after the double opt-in when that is on; a campaign click only
 * through the e-mail's existing tracking code.
 *
 * @return string[]
 */
function pg_lead_tie_acts()
{
    return array('form', 'newsletter', 'sign_in', 'order', 'booking', 'campaign_click');
}

/**
 * Whether an act ties the browser to a contact, and to which one.
 *
 * $facts: 'consent' (as pg_lead_key_verdict()), 'has_key' (bool),
 * 'browser_contact_id' (the contact the key points at, 0 for none),
 * 'contact_id' (the contact of the act), 'act' (one of pg_lead_tie_acts()),
 * 'panel_user' (bool, the person has a panel role), 'contact_gone' (bool,
 * the act's contact no longer exists; optional).
 *
 * Verdicts: 'tie' (first contact), 'same' (already this contact), 'move'
 * (another contact on the same browser: the key points at the new one, the
 * earlier contact keeps its visits), or a refusal: 'panel_user',
 * 'no_consent', 'no_key', 'not_an_act', 'no_contact'.
 *
 * @param array $facts
 * @return array{verdict: string, contact_id: int, moved_from: int}
 */
function pg_lead_tie_decision(array $facts)
{
    $refuse = function ($verdict) {
        return array('verdict' => $verdict, 'contact_id' => 0, 'moved_from' => 0);
    };

    // Staff are never tied or scored, whatever else holds.
    if (!empty($facts['panel_user'])) {
        return $refuse('panel_user');
    }

    if (!isset($facts['consent']) || $facts['consent'] !== 'yes') {
        return $refuse('no_consent');
    }

    if (empty($facts['has_key'])) {
        return $refuse('no_key');
    }

    $act = isset($facts['act']) ? (string) $facts['act'] : '';
    if (!in_array($act, pg_lead_tie_acts(), true)) {
        return $refuse('not_an_act');
    }

    $contact_id = isset($facts['contact_id']) ? (int) $facts['contact_id'] : 0;
    // A record can still name a contact deleted since (a campaign recipient,
    // an old form): tying to it would move the browser away from a real one.
    if (($contact_id <= 0) || !empty($facts['contact_gone'])) {
        return $refuse('no_contact');
    }

    $current = isset($facts['browser_contact_id']) ? (int) $facts['browser_contact_id'] : 0;

    if ($current <= 0) {
        return array('verdict' => 'tie', 'contact_id' => $contact_id, 'moved_from' => 0);
    }

    if ($current === $contact_id) {
        return array('verdict' => 'same', 'contact_id' => $contact_id, 'moved_from' => 0);
    }

    return array('verdict' => 'move', 'contact_id' => $contact_id, 'moved_from' => $current);
}

/**
 * The start of the keep period: $months calendar months before $now, in
 * UTC, with a day the shorter month lacks falling to its last day.
 *
 * @param int $now
 * @param int $months a value under 1 falls back to PG_LEAD_KEEP_MONTHS
 * @return int
 */
function pg_lead_keep_start($now, $months = PG_LEAD_KEEP_MONTHS)
{
    $now = (int) $now;
    $months = (int) $months;
    if ($months < 1) {
        $months = PG_LEAD_KEEP_MONTHS;
    }

    $index = (int) gmdate('Y', $now) * 12 + (int) gmdate('n', $now) - 1 - $months;
    $year = intdiv($index, 12);
    $month = $index % 12 + 1;
    $day = min((int) gmdate('j', $now), (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year)));

    return gmmktime((int) gmdate('G', $now), (int) gmdate('i', $now), (int) gmdate('s', $now), $month, $day, $year);
}

/**
 * The ids of a browser's visits to tie back to its contact: those not tied
 * to any contact yet, started at or after the consent and not before the
 * keep period's start. A visit already with a contact stays with it.
 *
 * @param array[] $visits     rows with 'id', 'started_at', 'contact_id'
 * @param int     $consent_at when the browser said yes
 * @param int     $keep_start pg_lead_keep_start()'s answer
 * @return int[] in the order given
 */
function pg_lead_tie_back_ids(array $visits, $consent_at, $keep_start)
{
    $from = max((int) $consent_at, (int) $keep_start);
    $ids = array();

    foreach ($visits as $visit) {
        if (!empty($visit['contact_id'])) {
            continue;
        }

        $started_at = isset($visit['started_at']) ? (int) $visit['started_at'] : 0;
        if ($started_at < $from || $started_at <= 0) {
            continue;
        }

        $ids[] = (int) $visit['id'];
    }

    return $ids;
}

/**
 * Whether a visit is a return within PG_LEAD_QUICK_RETURN_DAYS of the
 * browser's visit before it.
 *
 * @param int $previous_at the visit before, 0 for none
 * @param int $visit_at
 * @return bool
 */
function pg_lead_is_quick_return($previous_at, $visit_at)
{
    $previous_at = (int) $previous_at;
    $visit_at = (int) $visit_at;

    return $previous_at > 0 && $visit_at > $previous_at
        && $visit_at - $previous_at <= PG_LEAD_QUICK_RETURN_DAYS * 86400;
}

/**
 * The points each behaviour kind is worth, with a site's own weights over
 * the code's defaults. Unknown kinds are dropped; no weight is below 0.
 *
 * @param array $site kind => points
 * @return array<string, int>
 */
function pg_lead_score_weights(array $site = array())
{
    $weights = array(
        'pricing_page' => 10,
        'contact_page' => 10,
        'return_7d' => 10,
        'form' => 25,
        'booking' => 30,
        'campaign_click' => 10,
        'download' => 15,
    );

    foreach ($site as $kind => $points) {
        if (isset($weights[$kind]) && is_numeric($points)) {
            $weights[$kind] = max(0, (int) $points);
        }
    }

    return $weights;
}

/**
 * What is left of $points after $age_seconds with the given half-life.
 * A negative age (a time in the future) counts as now.
 *
 * @param int|float $points
 * @param int       $age_seconds
 * @param int|float $half_life_days a value under or at 0 falls back to PG_LEAD_HALF_LIFE_DAYS
 * @return float
 */
function pg_lead_score_decay($points, $age_seconds, $half_life_days = PG_LEAD_HALF_LIFE_DAYS)
{
    if ($half_life_days <= 0) {
        $half_life_days = PG_LEAD_HALF_LIFE_DAYS;
    }

    $age = max(0, (int) $age_seconds);

    return (float) $points * pow(0.5, $age / ($half_life_days * 86400));
}

/**
 * The interest score of a contact: each event's points faded by the
 * half-life, summed, rounded and held between 0 and 100, with the three
 * kinds that gave the most points as reasons (codes, not texts).
 *
 * @param array[]   $events         rows with 'kind' and 'at'
 * @param int       $now
 * @param array     $site_weights   kind => points, see pg_lead_score_weights()
 * @param int|float $half_life_days
 * @return array{score: int, reasons: array[], points: array} points: each kind's faded points, most first
 */
function pg_lead_interest_score(array $events, $now, array $site_weights = array(), $half_life_days = PG_LEAD_HALF_LIFE_DAYS)
{
    $weights = pg_lead_score_weights($site_weights);
    $by_kind = array();
    $total = 0.0;

    foreach ($events as $event) {
        $kind = isset($event['kind']) ? (string) $event['kind'] : '';
        if (!isset($weights[$kind])) {
            continue;
        }

        $at = isset($event['at']) ? (int) $event['at'] : 0;
        $points = pg_lead_score_decay($weights[$kind], (int) $now - $at, $half_life_days);
        $by_kind[$kind] = (isset($by_kind[$kind]) ? $by_kind[$kind] : 0.0) + $points;
        $total += $points;
    }

    $kinds = array_keys($by_kind);
    usort($kinds, function ($a, $b) use ($by_kind) {
        if ($by_kind[$a] == $by_kind[$b]) {
            return strcmp($a, $b);
        }

        return $by_kind[$a] < $by_kind[$b] ? 1 : -1;
    });

    $reasons = array();
    foreach (array_slice($kinds, 0, 3) as $kind) {
        if ($by_kind[$kind] <= 0) {
            continue;
        }
        $reasons[] = array('kind' => $kind, 'points' => round($by_kind[$kind], 1));
    }

    $points = array();
    foreach ($kinds as $kind) {
        $points[$kind] = round($by_kind[$kind], 1);
    }

    return array(
        'score' => (int) max(0, min(100, round($total))),
        'reasons' => $reasons,
        'points' => $points,
    );
}

/** The name of the browser key's first-party cookie (marketing category). */
const PG_LEAD_COOKIE = 'pg_lead';

/** How long the browser key's cookie lives, in months. */
const PG_LEAD_COOKIE_MONTHS = 13;

/** At most this many pages are kept on one visit's row. */
const PG_LEAD_PAGES_MAX = 50;

/** contact_journey.kind of a tie: the browser was tied to the contact. */
const PG_LEAD_JOURNEY_TIE = 1;

/**
 * The cookie notice answer pg_lead_key_verdict() and pg_lead_tie_decision()
 * read, from the notice's switch and the visitor's choices: 'off' while the
 * site does not ask, 'yes' or 'no' for an answered marketing category,
 * 'unanswered' otherwise.
 *
 * @param bool  $enabled pg_consent_enabled()
 * @param array $choices category => bool (pg_consent_parse())
 * @return string
 */
function pg_lead_consent_state($enabled, array $choices)
{
    if (!$enabled) {
        return 'off';
    }

    if (!array_key_exists('marketing', $choices)) {
        return 'unanswered';
    }

    return ($choices['marketing'] === true) ? 'yes' : 'no';
}

/**
 * The browser key as the cookie carries it: 64 lowercase hex characters
 * (32 random bytes), or '' for anything else.
 *
 * @param mixed $raw
 * @return string
 */
function pg_lead_key_valid($raw)
{
    return (is_string($raw) && preg_match('/^[0-9a-f]{64}$/D', $raw)) ? $raw : '';
}

/**
 * What is stored of a browser key: its sha256, so the key itself is kept
 * only in the visitor's cookie.
 *
 * @param string $key
 * @return string
 */
function pg_lead_key_hash($key)
{
    return hash('sha256', (string) $key);
}

/**
 * When the browser key's cookie runs out: PG_LEAD_COOKIE_MONTHS calendar
 * months after $now, in UTC, a day the shorter month lacks falling to its
 * last day.
 *
 * @param int $now
 * @return int
 */
function pg_lead_cookie_expires($now)
{
    $now = (int) $now;
    $index = (int) gmdate('Y', $now) * 12 + (int) gmdate('n', $now) - 1 + PG_LEAD_COOKIE_MONTHS;
    $year = intdiv($index, 12);
    $month = $index % 12 + 1;
    $day = min((int) gmdate('j', $now), (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year)));

    return gmmktime((int) gmdate('G', $now), (int) gmdate('i', $now), (int) gmdate('s', $now), $month, $day, $year);
}

/**
 * A visit's own copy of what the journey needs, cut to its columns: the
 * channel (pg_visitor_channel()'s answer, given), the campaign (the UTM
 * campaign, else the tracking code), the referring host and the landing page.
 *
 * @param array  $visit   referring_host_name, tracking_code, utm_campaign
 * @param string $channel
 * @param string $landing_page
 * @return array{channel: string, campaign: string, referrer_host: string, landing_page: string}
 */
function pg_lead_visit_copy(array $visit, $channel, $landing_page)
{
    $cut = function ($value, $length) {
        $value = trim((string) $value);

        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    };

    $campaign = trim((string) ($visit['utm_campaign'] ?? ''));
    if ($campaign === '') {
        $campaign = (string) ($visit['tracking_code'] ?? '');
    }

    return array(
        'channel' => $cut($channel, 20),
        'campaign' => $cut($campaign, 100),
        'referrer_host' => $cut(strtolower((string) ($visit['referring_host_name'] ?? '')), 150),
        'landing_page' => $cut($landing_page, 255),
    );
}

/**
 * A visit's pages with one more seen: the page before it gets the seconds
 * up to $now, the new one starts at 0. Rows are array(page, seconds, at),
 * and on a catalogue page array(page, seconds, at, item type, item id) with
 * the product or product group it showed (pg_track_content()); past
 * PG_LEAD_PAGES_MAX the newest pages are dropped, never the landing one.
 *
 * @param array[] $pages
 * @param string  $page
 * @param int     $now
 * @param array   $item pg_track_content()'s answer: type, id
 * @return array[]
 */
function pg_lead_pages_add(array $pages, $page, $now, array $item = array())
{
    $now = (int) $now;
    $page = function_exists('mb_substr') ? mb_substr((string) $page, 0, 255, 'UTF-8') : substr((string) $page, 0, 255);
    $last = count($pages) - 1;

    if ($last >= 0 && isset($pages[$last][2])) {
        $pages[$last][1] = max(0, $now - (int) $pages[$last][2]);
    }

    if (count($pages) >= PG_LEAD_PAGES_MAX || $page === '') {
        return $pages;
    }

    $row = array($page, 0, $now);
    $type = (string) ($item['type'] ?? '');
    $id = (int) ($item['id'] ?? 0);

    if (($id > 0) && in_array($type, pg_lead_page_item_types(), true)) {
        $row[] = $type;
        $row[] = $id;
    }

    $pages[] = $row;

    return $pages;
}

/**
 * The catalogue items a visit's page row names: a product and a product
 * group, as pg_track_content() registers them.
 *
 * @return string[]
 */
function pg_lead_page_item_types()
{
    return array('product', 'product_group');
}

/**
 * What a page view does with the browser key and its row, from the cookie
 * notice answer (pg_lead_consent_state()), the cookie as sent (any value),
 * the browser row the session names and whether the person has a panel role.
 *
 * 'verdict' is pg_lead_key_verdict()'s, or 'panel_user' (nothing is read or
 * written); 'forget' names the row to delete with its untied visits: 'key'
 * (found by the cookie's key), 'session' (the cookie is gone already, the
 * session still names the row) or ''; 'delete_cookie' and 'write_key' say
 * whether the cookie is deleted or a new key written; 'row' whether the
 * browser row is read or added.
 *
 * @param string $consent
 * @param mixed  $cookie
 * @param int    $session_browser_id
 * @param bool   $panel_user
 * @return array{verdict: string, forget: string, delete_cookie: bool, write_key: bool, row: bool}
 */
function pg_lead_sync_plan($consent, $cookie, $session_browser_id, $panel_user)
{
    $plan = array('verdict' => 'panel_user', 'forget' => '', 'delete_cookie' => false, 'write_key' => false, 'row' => false);

    if ($panel_user) {
        return $plan;
    }

    $key = pg_lead_key_valid($cookie);
    $plan['verdict'] = pg_lead_key_verdict((string) $consent, $cookie !== null && $cookie !== '');

    if (($plan['verdict'] === 'delete') || ($plan['verdict'] === 'none')) {
        $plan['forget'] = ($key !== '') ? 'key' : (((int) $session_browser_id > 0) ? 'session' : '');
        $plan['delete_cookie'] = $plan['verdict'] === 'delete';

        return $plan;
    }

    // 'write', or 'keep' of a cookie that is no key of ours.
    $plan['write_key'] = $key === '';
    $plan['row'] = true;

    return $plan;
}

/**
 * Whether the person of a request counts as staff for the journey: a panel
 * role, or staff browsing as a customer (the panel's "Log in as this user",
 * which leaves logged_in_as_different_user in the session, or a ghost
 * session). Their browsing is never the customer's own.
 *
 * @param array $software     $_SESSION['software']
 * @param bool  $panel_access pg_user_has_panel_access()
 * @return bool
 */
function pg_lead_session_is_staff(array $software, $panel_access)
{
    return !empty($software['ghost']) || !empty($software['logged_in_as_different_user']) || (bool) $panel_access;
}
