<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visitor journey's store: the browser key's cookie, written only with
 * the cookie notice's marketing yes (pg_consent_setcookie()), its browser
 * row (lead_browsers, the key's sha256 only), that browser's visits
 * (lead_visits) and the tie of a browser to a contact, started only from the
 * person's own act at that act's own save path (a form sent, a newsletter
 * yes, a sign-in, a click through a campaign e-mail's tracking code). A
 * refusal deletes the cookie and the browser's row with its visits not tied
 * to a contact. A user with a panel role gets no row and no tie.
 *
 * The rules are the pure ones of lead_journey.php; this file reads the
 * request and the database and acts on their codes.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/lead_journey.php');

/**
 * Whether the journey's tables are there (step 11.0.265): files land before
 * the upgrade runs, and until then nothing is written.
 *
 * @return bool
 */
function pg_lead_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('lead_browsers', 'key_hash') && pg_schema_has('lead_visits', 'browser_id');
    }

    return $ready;
}

/**
 * The cookie notice answer of this request's visitor (pg_lead_consent_state()).
 *
 * @return string
 */
function pg_lead_request_consent()
{
    return pg_lead_consent_state(pg_consent_enabled(), pg_consent_choices());
}

/**
 * Whether the person of this request has a panel role, or is staff browsing
 * as a customer: they get no browser row, no tie and no score.
 *
 * @return bool
 */
function pg_lead_request_panel_user()
{
    $software = (isset($_SESSION['software']) && is_array($_SESSION['software'])) ? $_SESSION['software'] : array();

    return pg_lead_session_is_staff($software, function_exists('pg_user_has_panel_access') && pg_user_has_panel_access());
}

/**
 * The journey's part of the session: the browser row and the visit open now.
 *
 * @return array{browser_id: int, visitor_id: int, visit_id: int, pages: array}
 */
function pg_lead_session()
{
    $state = (isset($_SESSION['software']['lead_journey']) && is_array($_SESSION['software']['lead_journey']))
        ? $_SESSION['software']['lead_journey'] : array();

    return array(
        'browser_id' => (int) ($state['browser_id'] ?? 0),
        'visitor_id' => (int) ($state['visitor_id'] ?? 0),
        'visit_id' => (int) ($state['visit_id'] ?? 0),
        'pages' => (isset($state['pages']) && is_array($state['pages'])) ? $state['pages'] : array(),
    );
}

/**
 * @param array|null $state null drops the journey's part of the session
 * @return void
 */
function pg_lead_session_store($state)
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return;
    }

    if ($state === null) {
        unset($_SESSION['software']['lead_journey']);
        return;
    }

    $_SESSION['software']['lead_journey'] = $state;
}

/**
 * A browser row by its key's hash or its id, null for none.
 *
 * @param string $key_hash
 * @param int    $browser_id used when $key_hash is ''
 * @return array|null
 */
function pg_lead_browser_find($key_hash, $browser_id = 0)
{
    if ($key_hash !== '') {
        $result = db_q("SELECT id, contact_id, consent_at FROM lead_browsers WHERE key_hash = ?", array((string) $key_hash));
    } elseif ((int) $browser_id > 0) {
        $result = db_q("SELECT id, contact_id, consent_at FROM lead_browsers WHERE id = ?", array((string) (int) $browser_id));
    } else {
        return null;
    }

    $row = $result ? mysqli_fetch_assoc($result) : null;

    return is_array($row) ? array('id' => (int) $row['id'], 'contact_id' => (int) $row['contact_id'], 'consent_at' => (int) $row['consent_at']) : null;
}

/**
 * The row of a browser that said yes: written once per key, at the time of
 * the yes as this request sees it.
 *
 * @param string $key_hash
 * @param int    $now
 * @return array|null
 */
function pg_lead_browser_add($key_hash, $now)
{
    db_q(
        "INSERT IGNORE INTO lead_browsers (key_hash, contact_id, consent_at, tied_at, last_seen_at, created_at) VALUES (?, 0, ?, 0, ?, ?)",
        array((string) $key_hash, (string) (int) $now, (string) (int) $now, (string) (int) $now)
    );

    return pg_lead_browser_find($key_hash);
}

/**
 * A browser whose yes was taken back: its row goes with its visits not tied
 * to a contact; a visit already with a contact stays with it until the keep
 * period or the person's erase.
 *
 * @param int $browser_id
 * @return void
 */
function pg_lead_browser_forget($browser_id)
{
    $browser_id = (int) $browser_id;

    if ($browser_id <= 0) {
        return;
    }

    db_q("DELETE FROM lead_visits WHERE browser_id = ? AND contact_id = 0", array((string) $browser_id));
    db_q("DELETE FROM lead_browsers WHERE id = ?", array((string) $browser_id));
}

/**
 * Deletes the browser key's cookie, in the response and for the rest of
 * this request.
 *
 * @return void
 */
function pg_lead_cookie_delete()
{
    if (!headers_sent()) {
        setcookie(PG_LEAD_COOKIE, '', time() - 86400, '/');
    }

    unset($_COOKIE[PG_LEAD_COOKIE]);
}

/**
 * Brings the browser key in line with the visitor's cookie notice answer
 * (pg_lead_key_verdict()): writes a new key with the marketing yes, keeps
 * the one there, or deletes it with its browser row. A panel user is left
 * alone and gets no row.
 *
 * @param int $now
 * @return array|null the browser row while the key may stand
 */
function pg_lead_browser_sync($now)
{
    if (!pg_lead_ready()) {
        return null;
    }

    $session = pg_lead_session();
    $cookie = $_COOKIE[PG_LEAD_COOKIE] ?? null;
    $plan = pg_lead_sync_plan(pg_lead_request_consent(), $cookie, $session['browser_id'], pg_lead_request_panel_user());

    if (!$plan['row']) {
        if ($plan['forget'] === 'key') {
            $browser = pg_lead_browser_find(pg_lead_key_hash(pg_lead_key_valid($cookie)));
            pg_lead_browser_forget($browser ? $browser['id'] : 0);
        } elseif ($plan['forget'] === 'session') {
            // The notice's script deleted the cookie already; the session
            // still names the browser this visit wrote.
            pg_lead_browser_forget($session['browser_id']);
        }

        if ($plan['delete_cookie']) {
            pg_lead_cookie_delete();
        }

        if ($plan['forget'] !== '') {
            pg_lead_session_store(null);
        }

        return null;
    }

    $key = pg_lead_key_valid($cookie);

    if ($plan['write_key']) {
        $key = bin2hex(random_bytes(32));

        if (headers_sent() || !pg_consent_setcookie('marketing', PG_LEAD_COOKIE, $key, pg_lead_cookie_expires($now))) {
            return null;
        }

        $_COOKIE[PG_LEAD_COOKIE] = $key;
    }

    $hash = pg_lead_key_hash($key);
    $browser = pg_lead_browser_find($hash);

    // A key whose row is gone (erased, trimmed) starts again from this yes.
    if ($browser === null) {
        $browser = pg_lead_browser_add($hash, $now);
    }

    if (($browser !== null) && ($session['browser_id'] !== $browser['id'])) {
        pg_lead_session_store(array('browser_id' => $browser['id'], 'visitor_id' => 0, 'visit_id' => 0, 'pages' => array()));
    }

    return $browser;
}

/**
 * This request's browser row, synced once (pg_lead_browser_sync()): called
 * from init_tracking() before the page prints anything, since the key is a
 * cookie, and read again when the visit is recorded after the page.
 *
 * @return array|null
 */
function pg_lead_request_browser()
{
    static $synced = false;
    static $browser = null;

    if (!$synced) {
        $synced = true;
        $browser = pg_lead_browser_sync(time());
    }

    return $browser;
}

/**
 * A page view of a visit with a visitors row: with a consenting browser the
 * visit gets its own lead_visits row (tied at once when the browser already
 * is) and each page its seconds. Called from update_visitor_page_data().
 *
 * @param int    $visitor_id
 * @param string $page_name
 * @return void
 */
function pg_lead_page_view($visitor_id, $page_name)
{
    $visitor_id = (int) $visitor_id;
    $now = time();
    $browser = pg_lead_request_browser();

    if (($browser === null) || ($visitor_id <= 0)) {
        return;
    }

    $session = pg_lead_session();
    // The product or product group the page showed, so its category can be
    // an interest (includes/fn/lead_score.php).
    $item = function_exists('pg_track_content') ? pg_track_content() : array();

    if (($session['visit_id'] > 0) && ($session['visitor_id'] === $visitor_id)) {
        $pages = pg_lead_pages_add($session['pages'], $page_name, $now, $item);
        db_q("UPDATE lead_visits SET pages = ? WHERE id = ? AND browser_id = ?", array(json_encode($pages), (string) $session['visit_id'], (string) $browser['id']));
        $session['pages'] = $pages;
        pg_lead_session_store($session);
        return;
    }

    $result = db_q("SELECT referring_host_name, referring_search_engine, tracking_code, utm_medium, utm_campaign FROM visitors WHERE id = ?", array((string) $visitor_id));
    $visit = $result ? mysqli_fetch_assoc($result) : null;

    if (!is_array($visit)) {
        return;
    }

    $flag = defined('PAY_PER_CLICK_FLAG') ? (string) PAY_PER_CLICK_FLAG : '';
    $copy = pg_lead_visit_copy($visit, pg_visitor_channel($visit, $flag), $page_name);
    $pages = pg_lead_pages_add(array(), $page_name, $now, $item);

    db_q(
        "INSERT INTO lead_visits (browser_id, contact_id, visitor_id, started_at, channel, campaign, referrer_host, landing_page, pages) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        array((string) $browser['id'], (string) $browser['contact_id'], (string) $visitor_id, (string) $now, $copy['channel'], $copy['campaign'], $copy['referrer_host'], $copy['landing_page'], json_encode($pages))
    );

    // Read before the next statement, which resets it.
    $visit_id = (int) mysqli_insert_id(db::$con);

    db_q("UPDATE lead_browsers SET last_seen_at = ? WHERE id = ?", array((string) $now, (string) $browser['id']));

    pg_lead_session_store(array('browser_id' => $browser['id'], 'visitor_id' => $visitor_id, 'visit_id' => $visit_id, 'pages' => $pages));
}

/**
 * Ties this request's browser to a contact after the person's own act, at
 * that act's own save path (pg_lead_tie_acts()): the browser's key points at
 * the contact, its visits since the consent and inside the keep period that
 * no contact holds yet are tied back, and the journey gets a tie row. Never
 * from an IP or a guess; nothing without the marketing yes, a key, or for a
 * panel user.
 *
 * @param string $act        one of pg_lead_tie_acts()
 * @param int    $contact_id the contact the act wrote or named
 * @param int    $ref_id     the record of the act (form, campaign, user), 0 for none
 * @return string pg_lead_tie_decision()'s verdict, 'not_ready' before the step
 */
function pg_lead_tie($act, $contact_id, $ref_id = 0)
{
    if (!pg_lead_ready()) {
        return 'not_ready';
    }

    $key = pg_lead_key_valid($_COOKIE[PG_LEAD_COOKIE] ?? '');
    $session = pg_lead_session();
    $browser = ($key !== '') ? pg_lead_browser_find(pg_lead_key_hash($key)) : pg_lead_browser_find('', $session['browser_id']);

    $decision = pg_lead_tie_decision(array(
        'consent' => pg_lead_request_consent(),
        'has_key' => $browser !== null,
        'browser_contact_id' => $browser ? $browser['contact_id'] : 0,
        'contact_id' => (int) $contact_id,
        'contact_gone' => ((int) $contact_id > 0) && ((int) db_value("SELECT COUNT(*) FROM contacts WHERE id = '" . (int) $contact_id . "'") === 0),
        'act' => (string) $act,
        'panel_user' => pg_lead_request_panel_user(),
    ));

    if (($decision['verdict'] !== 'tie') && ($decision['verdict'] !== 'move')) {
        return $decision['verdict'];
    }

    $now = time();
    $contact_id = $decision['contact_id'];

    db_q("UPDATE lead_browsers SET contact_id = ?, tied_at = ? WHERE id = ?", array((string) $contact_id, (string) $now, (string) $browser['id']));

    $result = db_q("SELECT id, started_at, contact_id FROM lead_visits WHERE browser_id = ? AND contact_id = 0", array((string) $browser['id']));
    $visits = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $visits[] = $row;
    }

    $ids = pg_lead_tie_back_ids($visits, $browser['consent_at'], pg_lead_keep_start($now, pg_lead_keep_months()));

    if ($ids) {
        db_q("UPDATE lead_visits SET contact_id = ? WHERE id IN (" . implode(',', array_map('intval', $ids)) . ") AND contact_id = 0", array((string) $contact_id));
    }

    // A move splits the open visit: the earlier contact keeps its row as it
    // stands, the new contact gets a row of its own from this page on, with
    // no campaign or referrer of the visit's start.
    $split = pg_lead_move_split($decision['verdict'], $session, $now);

    if ($split !== null) {
        // get_file.php signs in without functions.php: a visit with neither
        // campaign nor referrer is direct.
        $flag = defined('PAY_PER_CLICK_FLAG') ? (string) PAY_PER_CLICK_FLAG : '';
        $channel = function_exists('pg_visitor_channel') ? pg_visitor_channel(array(), $flag) : 'direct';
        $copy = pg_lead_visit_copy(array(), $channel, $split['landing_page']);

        db_q(
            "INSERT INTO lead_visits (browser_id, contact_id, visitor_id, started_at, channel, campaign, referrer_host, landing_page, pages) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array((string) $browser['id'], (string) $contact_id, (string) $session['visitor_id'], (string) $now, $copy['channel'], $copy['campaign'], $copy['referrer_host'], $copy['landing_page'], json_encode($split['pages']))
        );

        $session = array('browser_id' => $browser['id'], 'visitor_id' => $session['visitor_id'], 'visit_id' => (int) mysqli_insert_id(db::$con), 'pages' => $split['pages']);
        pg_lead_session_store($session);
    }

    if (pg_schema_has('contact_journey', 'kind')) {
        db_q(
            "INSERT INTO contact_journey (contact_id, kind, at, visit_id, ref_id, detail) VALUES (?, ?, ?, ?, ?, ?)",
            array((string) $contact_id, (string) PG_LEAD_JOURNEY_TIE, (string) $now, (string) $session['visit_id'], (string) max(0, (int) $ref_id), (string) $act)
        );
    }

    return $decision['verdict'];
}

/**
 * A cookie notice answer that refused marketing (consent_answer.php): the
 * browser's row goes with its visits not tied to a contact, and the cookie
 * is deleted in the reply, whether the notice's script deleted it already
 * or not.
 *
 * @param array[]|null $changes pg_consent_answer_changes()
 * @return void
 */
function pg_lead_consent_changed($changes)
{
    if (!pg_lead_ready() || !pg_ad_conversion_marketing_refused($changes)) {
        return;
    }

    $key = pg_lead_key_valid($_COOKIE[PG_LEAD_COOKIE] ?? '');
    $browser = ($key !== '') ? pg_lead_browser_find(pg_lead_key_hash($key)) : null;

    pg_lead_browser_forget($browser ? $browser['id'] : pg_lead_session()['browser_id']);
    pg_lead_session_store(null);

    if (isset($_COOKIE[PG_LEAD_COOKIE])) {
        pg_lead_cookie_delete();
    }
}

/**
 * A person's own sign-in (pg_session_sign_in()): the browser is tied to the
 * contact of the account.
 *
 * @param int $user_id
 * @return string pg_lead_tie()'s answer, 'no_key' when the browser carries none
 */
function pg_lead_sign_in($user_id)
{
    if ((pg_lead_key_valid($_COOKIE[PG_LEAD_COOKIE] ?? '') === '') && (pg_lead_session()['browser_id'] <= 0)) {
        return 'no_key';
    }

    if (!pg_lead_ready()) {
        return 'not_ready';
    }

    $contact_id = (int) db_value("SELECT user_contact FROM user WHERE user_id = '" . (int) $user_id . "'");

    return pg_lead_tie('sign_in', $contact_id, (int) $user_id);
}
