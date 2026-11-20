<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Ad click ids: the id an ad service adds to the landing address when a
 * visitor comes from one of its ads (fbclid, gclid, ttclid), held for the ad
 * conversions of includes/fn/ad_conversions.php.
 *
 * - Captured when a visitor page opens (get_page.php,
 *   pg_ad_click_ids_capture()): only the listed parameter of each service
 *   (pg_ad_click_id_parameters()), only a value inside the bound
 *   (pg_ad_click_id_value()), with the time of the click, which Meta's fbc
 *   value carries.
 * - Held only in the session ($_SESSION['ad_click_ids']): before consent it
 *   is written to no table and no cookie. A cookie notice answer that refuses
 *   marketing, or a stored refusal read on a later page, removes it.
 * - At the payment step, for a buyer who consented, the service's id goes
 *   into the ids field of its purchase row (pg_ad_click_ids_held_for(), the
 *   one call in pg_ad_conversion_payment_step()). A sender reads it from the
 *   row, never from here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The longest click id kept; a longer value is dropped, never cut.
define('PG_AD_CLICK_ID_MAX_LENGTH', 500);

// The session key the ids wait under.
define('PG_AD_CLICK_IDS_SESSION_KEY', 'ad_click_ids');

/**
 * The parameter of the landing address read for each service; no other
 * parameter is read. Pure.
 *
 * @return array service => parameter
 */
function pg_ad_click_id_parameters()
{
    return array(
        'meta_capi' => 'fbclid',
        'ga4_mp' => 'gclid',
        'tiktok_events' => 'ttclid',
    );
}

/**
 * A click id inside the bound: a string of 1 to PG_AD_CLICK_ID_MAX_LENGTH
 * letters, digits, dots, dashes and underscores; '' for anything else, which
 * is dropped. Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_ad_click_id_value($value)
{
    if (!is_string($value) || ($value === '') || (strlen($value) > PG_AD_CLICK_ID_MAX_LENGTH)) {
        return '';
    }

    return preg_match('/^[A-Za-z0-9._-]+$/D', $value) ? $value : '';
}

/**
 * The click ids of a landing address: for each service whose parameter
 * carries a value inside the bound, the id and the time of the click. Pure.
 *
 * @param array $query $_GET
 * @param int   $now   the time of the click
 * @return array service => array('id' => string, 'at' => int)
 */
function pg_ad_click_ids_found(array $query, $now)
{
    $found = array();

    foreach (pg_ad_click_id_parameters() as $service => $parameter) {
        $id = pg_ad_click_id_value($query[$parameter] ?? '');

        if ($id !== '') {
            $found[$service] = array('id' => $id, 'at' => (int) $now);
        }
    }

    return $found;
}

/**
 * The held ids with the ones just found: a new click of a service replaces
 * its earlier one; a held entry that is not well formed is dropped. Pure.
 *
 * @param mixed $held  what the session holds
 * @param array $found pg_ad_click_ids_found()
 * @return array service => array('id', 'at')
 */
function pg_ad_click_ids_hold($held, array $found)
{
    $kept = array();
    $services = pg_ad_click_id_parameters();

    foreach (array_merge(is_array($held) ? $held : array(), $found) as $service => $click) {
        if (!isset($services[$service]) || !is_array($click)) {
            continue;
        }

        $id = pg_ad_click_id_value($click['id'] ?? '');
        $at = (int) ($click['at'] ?? 0);

        if (($id !== '') && ($at > 0)) {
            $kept[$service] = array('id' => $id, 'at' => $at);
        }
    }

    return $kept;
}

/**
 * Whether a click id may wait in the session: not after a stored refusal of
 * marketing while the cookie notice is on. Before any answer it waits, used
 * only if consent comes. Pure.
 *
 * @param bool  $notice_on pg_consent_enabled()
 * @param array $choices   category => bool (pg_consent_choices())
 * @return bool
 */
function pg_ad_click_ids_may_wait($notice_on, array $choices)
{
    return !$notice_on || !isset($choices['marketing']) || ($choices['marketing'] !== false);
}

/**
 * Meta's fbc value from a click: fb.<subdomain index>.<click time in
 * milliseconds>.<fbclid>, the subdomain index 1 as Meta's pixel writes it on
 * the site's own domain. Pure.
 *
 * @param string $id fbclid
 * @param int    $at the time of the click, seconds
 * @return string
 */
function pg_ad_click_ids_meta_fbc($id, $at)
{
    return 'fb.1.' . ((int) $at * 1000) . '.' . $id;
}

/**
 * The fields a service's purchase row gets from the held click ids: Meta's
 * fbc, GA4's gclid, TikTok's ttclid; nothing for a service without a held
 * click. Pure.
 *
 * @param string $service
 * @param mixed  $held pg_ad_click_ids_hold()
 * @return array
 */
function pg_ad_click_ids_for($service, $held)
{
    $held = pg_ad_click_ids_hold($held, array());

    if (!isset($held[$service])) {
        return array();
    }

    $click = $held[$service];

    if ($service === 'meta_capi') {
        return array('fbc' => pg_ad_click_ids_meta_fbc($click['id'], $click['at']));
    }

    return array(pg_ad_click_id_parameters()[$service] => $click['id']);
}

/**
 * Captures the click ids of the landing address into the session, when a
 * visitor page opens. A crawler gets nothing; after a stored refusal of
 * marketing the held ids are removed instead.
 *
 * @param array $query $_GET
 * @return void
 */
function pg_ad_click_ids_capture(array $query)
{
    if (!isset($_SESSION) || !is_array($_SESSION) || (defined('IS_BOT') && IS_BOT)) {
        return;
    }

    if (!pg_ad_click_ids_may_wait(pg_consent_enabled(), pg_consent_choices())) {
        unset($_SESSION[PG_AD_CLICK_IDS_SESSION_KEY]);

        return;
    }

    $found = pg_ad_click_ids_found($query, time());

    // A page without a click id leaves the session as it is: an anonymous
    // session stays one the page cache may serve.
    if ($found === array()) {
        return;
    }

    $_SESSION[PG_AD_CLICK_IDS_SESSION_KEY] = pg_ad_click_ids_hold($_SESSION[PG_AD_CLICK_IDS_SESSION_KEY] ?? null, $found);
}

/**
 * The ids reader: the fields of a service's purchase row from the click ids
 * the session holds. Called only for a buyer who consented.
 *
 * @param string $service
 * @return array
 */
function pg_ad_click_ids_held_for($service)
{
    return pg_ad_click_ids_for($service, $_SESSION[PG_AD_CLICK_IDS_SESSION_KEY] ?? null);
}

/**
 * A cookie notice answer that refused marketing removes the held click ids
 * from the session.
 *
 * @param array[]|null $changes pg_consent_answer_changes()
 * @return void
 */
function pg_ad_click_ids_consent_changed($changes)
{
    if (isset($_SESSION) && is_array($_SESSION) && pg_ad_conversion_marketing_refused($changes)) {
        unset($_SESSION[PG_AD_CLICK_IDS_SESSION_KEY]);
    }
}
