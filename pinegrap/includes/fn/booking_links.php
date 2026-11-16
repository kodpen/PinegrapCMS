<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A booking staff member's meeting link: a "book a time with me" address
 * only the member hands out. Nothing is stored for it: the code is the
 * staff row's id and an HMAC of the row and the site's key, checked by
 * working it out again, so it cannot be guessed and cannot be renewed by
 * itself. A wrong code and a member who cannot be booked get one answer.
 *
 * The link offers the member's own services, without a deposit, and its
 * free times are the booking engine's for that member alone
 * (pg_booking_busy_between(), pg_booking_day_times()): bookings and the
 * Workspace events the member is in are busy, a private plan never is.
 *
 * The member's meeting address (ws_profiles.meeting_url) is cleaned as a
 * meeting's is (ws_meeting_url_clean()) and read when a booking made
 * through the link is shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** bookings.source of a booking made through a meeting link. */
define('PG_BOOKING_LINK_SOURCE', 'link');

/**
 * The link's code of a staff member: '<staff id>-<32 hex>', the hex an HMAC
 * of the row's id and user under the site's key. '' without a key. Pure.
 *
 * @param array  $member booking_staff row with id, user_id
 * @param string $key
 * @return string
 */
function pg_booking_link_code($member, $key)
{
    $id = isset($member['id']) ? (int) $member['id'] : 0;
    $user_id = isset($member['user_id']) ? (int) $member['user_id'] : 0;

    if (((string) $key === '') || ($id <= 0) || ($user_id <= 0)) {
        return '';
    }

    return $id . '-' . substr(hash_hmac('sha256', 'booking-link|' . $id . '|' . $user_id, (string) $key), 0, 32);
}

/**
 * The staff id a code names, 0 when the code is not written as one. Only
 * the shape is read; pg_booking_link_code_valid() says whether it is the
 * member's. Pure.
 *
 * @param mixed $code
 * @return int
 */
function pg_booking_link_code_staff_id($code)
{
    if (!is_string($code) || !preg_match('/^([1-9][0-9]{0,9})-[a-f0-9]{32}$/D', $code, $match)) {
        return 0;
    }

    return (int) $match[1];
}

/**
 * Whether a code is the member's link. Pure.
 *
 * @param mixed  $code
 * @param array  $member
 * @param string $key
 * @return bool
 */
function pg_booking_link_code_valid($code, $member, $key)
{
    $expected = pg_booking_link_code($member, $key);

    return ($expected !== '') && is_string($code) && hash_equals($expected, $code);
}

/**
 * Whether a staff row can be booked through a link: switched on, a user who
 * still exists below role 3, and at least one service. Pure.
 *
 * @param mixed $member booking_staff row with user_role
 * @return bool
 */
function pg_booking_link_bookable($member)
{
    return is_array($member)
        && !empty($member['enabled'])
        && !empty($member['user_id'])
        && isset($member['user_role']) && ((int) $member['user_role'] < 3)
        && (pg_booking_service_ids(isset($member['service_ids']) ? $member['service_ids'] : '') !== array());
}

/**
 * The member a code names, from the row its id reads: null for a wrong code
 * and for a member who cannot be booked alike. Pure.
 *
 * @param mixed      $code
 * @param array|null $member the row of pg_booking_link_code_staff_id()
 * @param string     $key
 * @return array|null
 */
function pg_booking_link_member_pick($code, $member, $key)
{
    if (!pg_booking_link_bookable($member) || !pg_booking_link_code_valid($code, $member, $key)) {
        return null;
    }

    return $member;
}

/**
 * The staff row of a booking staff member with their user's role and the
 * contact's name, null when there is none.
 *
 * @param int $staff_id
 * @return array|null
 */
function pg_booking_link_staff_row($staff_id)
{
    if (((int) $staff_id <= 0) || !pg_booking_ready()) {
        return null;
    }

    $row = db_item(
        "SELECT booking_staff.*, user.user_role, contacts.first_name, contacts.last_name
        FROM booking_staff
        INNER JOIN user ON user.user_id = booking_staff.user_id
        LEFT JOIN contacts ON contacts.id = user.user_contact
        WHERE booking_staff.id = '" . (int) $staff_id . "'");

    if (!is_array($row)) {
        return null;
    }

    // The contact's name only: a login name never reaches the public page.
    $name = pg_contact_display_name($row['first_name'], $row['last_name']);
    $row['name'] = ($name !== '') ? $name : lang('Staff member');

    return $row;
}

/**
 * The member a link's code names, null for a wrong code and for a member
 * who cannot be booked alike (the page answers both with the same 404).
 *
 * @param mixed $code
 * @return array|null booking_staff row with user_role and name
 */
function pg_booking_link_member($code)
{
    $staff_id = pg_booking_link_code_staff_id($code);

    return pg_booking_link_member_pick($code, ($staff_id > 0) ? pg_booking_link_staff_row($staff_id) : null, pg_booking_key());
}

/**
 * The link's address of a member, '' when the site has no key.
 *
 * @param array $member
 * @return string
 */
function pg_booking_link_url($member)
{
    $code = pg_booking_link_code($member, pg_booking_key());

    // book_with.php sits beside the other root files, in the software folder.
    return ($code === '') ? '' : URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/book_with.php?c=' . $code;
}

/**
 * The services a link offers: those of the bookable list the member takes,
 * a service with a deposit left out. Pure.
 *
 * @param array $services booking_services rows (pg_booking_widget_data())
 * @param array $member
 * @return array
 */
function pg_booking_link_services($services, $member)
{
    $own = pg_booking_service_ids(isset($member['service_ids']) ? $member['service_ids'] : '');
    $offered = array();

    foreach ((array) $services as $service) {
        if (is_array($service) && in_array((int) $service['id'], $own, true) && (pg_booking_deposit_due($service) === null)) {
            $offered[] = $service;
        }
    }

    return $offered;
}

/**
 * One service a link offers, null when it does not offer it. Pure.
 *
 * @param array $services
 * @param array $member
 * @param int   $service_id
 * @return array|null
 */
function pg_booking_link_service($services, $member, $service_id)
{
    foreach (pg_booking_link_services($services, $member) as $service) {
        if ((int) $service['id'] === (int) $service_id) {
            return $service;
        }
    }

    return null;
}

/**
 * The free starts of a day for the member alone, as the engine works them
 * out. Pure.
 *
 * @param array  $service
 * @param array  $member
 * @param string $day    'YYYY-MM-DD'
 * @param array  $busy   staff id => ranges (pg_booking_link_busy())
 * @param int    $now
 * @param array  $policy pg_booking_widget_policy()
 * @return int[]
 */
function pg_booking_link_times($service, $member, $day, $busy, $now, $policy)
{
    $starts = array();

    foreach (pg_booking_day_times($service, array($member), $day, $busy, $now, $policy) as $time) {
        $starts[] = (int) $time['start'];
    }

    return $starts;
}

/**
 * The member's busy ranges between two times, read by the engine only.
 *
 * @param array $member
 * @param int   $from
 * @param int   $until
 * @param int   $now
 * @return array staff id => ranges
 */
function pg_booking_link_busy($member, $from, $until, $now)
{
    return pg_booking_busy_between(array($member), $from, $until, $now);
}

/**
 * Has the database the column of a member's meeting address?
 *
 * @return bool
 */
function pg_booking_link_meeting_ready()
{
    return pg_schema_has('ws_profiles', 'meeting_url');
}

/**
 * A meeting address as it is kept: '' for none, false when it is not a
 * web address. Pure.
 *
 * @param mixed $value
 * @return string|false
 */
function pg_booking_link_meeting_url_clean($value)
{
    // The public page runs without the Workspace bootstrap.
    if (!function_exists('ws_meeting_url_clean')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/meetings.php');
    }

    return ws_meeting_url_clean($value);
}

/**
 * A member's meeting address, '' when they have none or the column is not
 * there yet.
 *
 * @param int $user_id
 * @return string
 */
function pg_booking_link_meeting_url($user_id)
{
    if (((int) $user_id <= 0) || !pg_booking_link_meeting_ready()) {
        return '';
    }

    $url = pg_booking_link_meeting_url_clean(db_value("SELECT meeting_url FROM ws_profiles WHERE user_id = '" . (int) $user_id . "'"));

    return ($url === false) ? '' : $url;
}

/**
 * Keeps a member's meeting address; an empty one is kept as NULL (none). A
 * member with no workspace profile yet gets one, its other fields at their
 * defaults.
 *
 * @param int   $user_id
 * @param mixed $value
 * @return array ok, error, url
 */
function pg_booking_link_meeting_url_save($user_id, $value)
{
    if (!pg_booking_link_meeting_ready()) {
        return array('ok' => false, 'error' => lang('The database has not been upgraded yet.'), 'url' => '');
    }

    $url = pg_booking_link_meeting_url_clean($value);

    if ($url === false) {
        return array('ok' => false, 'error' => lang('The meeting link is not a web address.'), 'url' => '');
    }

    $empty = ($url === '');
    $values = $empty ? array((int) $user_id, time()) : array((int) $user_id, $url, time());

    db_q("INSERT INTO ws_profiles (user_id, meeting_url, updated_at)
        VALUES (?, " . ($empty ? 'NULL' : '?') . ", ?)
        ON DUPLICATE KEY UPDATE meeting_url = VALUES(meeting_url), updated_at = VALUES(updated_at)", $values);

    return array('ok' => true, 'error' => '', 'url' => $url);
}

/**
 * The meeting address shown with a booking: the member's, read when shown,
 * for a booking made through the link only. Pure.
 *
 * @param array       $booking with source
 * @param string|null $member_url the member's kept address
 * @return string
 */
function pg_booking_link_booking_meeting_url($booking, $member_url)
{
    if (!isset($booking['source']) || ((string) $booking['source'] !== PG_BOOKING_LINK_SOURCE)) {
        return '';
    }

    $url = pg_booking_link_meeting_url_clean((string) $member_url);

    return ($url === false) ? '' : $url;
}

/* ---------------------------------------------------------------------------
   The page (book_with.php)
   --------------------------------------------------------------------------- */

/**
 * The headers of the link's page: never kept by a browser, proxy or the
 * page cache, never indexed, and the address with its code never handed to
 * another site as a referrer. Pure.
 *
 * @return string[]
 */
function pg_booking_link_page_headers()
{
    return array(
        'Cache-Control: no-store, private',
        'X-Robots-Tag: noindex, nofollow',
        'Referrer-Policy: no-referrer',
    );
}

/**
 * Why the page refuses a request before reading it, null when it does not:
 * a POST from another site (pg_request_cross_site()) and an address over
 * the bookings' rate limit. A GET from another site is a link someone
 * followed and is answered. The form token is checked after this
 * (validate_token_field()). Pure.
 *
 * @param bool $post
 * @param bool $cross_site
 * @param int  $throttled pg_booking_throttled(): 0 or the seconds to wait
 * @return array|null status, message, retry (seconds, 0 for none)
 */
function pg_booking_link_refusal($post, $cross_site, $throttled)
{
    if ($post && $cross_site) {
        return array('status' => 403, 'message' => lang('Invalid request.'), 'retry' => 0);
    }

    if ((int) $throttled > 0) {
        return array('status' => 429, 'message' => lang('Too many requests from your address. Please wait a moment and try again.'), 'retry' => (int) $throttled);
    }

    return null;
}

/**
 * The times a range of busy ranges must cover for a month or a day of the
 * site's zone: a day more on each side, as the widget reads them. Pure.
 *
 * @param string $first 'YYYY-MM-DD' of the first day
 * @param string $step  '+1 month' or '+1 day'
 * @param string $zone
 * @return int[] from, until
 */
function pg_booking_link_busy_window($first, $step, $zone)
{
    $start = new DateTime($first . ' 00:00:00', new DateTimeZone($zone));
    $from = $start->getTimestamp() - 86400;

    return array($from, $start->modify($step)->getTimestamp() + 86400);
}

/**
 * The days of a month with a free time for the member alone, as the engine
 * works them out. Pure.
 *
 * @param array  $service
 * @param array  $member
 * @param string $month  'YYYY-MM'
 * @param array  $busy   staff id => ranges (pg_booking_link_busy())
 * @param int    $now
 * @param array  $policy pg_booking_widget_policy()
 * @return string[] 'YYYY-MM-DD'
 */
function pg_booking_link_days($service, $member, $month, $busy, $now, $policy)
{
    return pg_booking_month_days($service, array($member), $month, $busy, $now, $policy);
}

/**
 * The time this session holds through the member's link, null when it holds
 * none or one that is not this link's (another member's, or a service the
 * link no longer offers). Pure: the session's values come in.
 *
 * @param mixed $hold_id   $_SESSION['software']['booking_hold']
 * @param mixed $hold_slot $_SESSION['software']['booking_hold_slot']
 * @param array $member
 * @param array $services  pg_booking_link_services()
 * @return array|null id, service, start
 */
function pg_booking_link_held($hold_id, $hold_slot, $member, $services)
{
    if (((int) $hold_id <= 0) || !is_array($hold_slot) || (count($hold_slot) !== 3)) {
        return null;
    }

    list($service_id, $staff_id, $start) = array_map('intval', array_values($hold_slot));

    if (($staff_id !== (int) $member['id']) || ($start <= 0)) {
        return null;
    }

    foreach ((array) $services as $service) {
        if ((int) $service['id'] === $service_id) {
            return array('id' => (int) $hold_id, 'service' => $service, 'start' => $start);
        }
    }

    return null;
}

/**
 * What the page says when a hold or a confirmation is not written, '' for a
 * written one. Pure apart from the site's own words for a taken time.
 *
 * @param string $answer pg_booking_hold() / pg_booking_confirm() answer
 * @return string
 */
function pg_booking_link_answer_text($answer)
{
    if ($answer === 'ok') {
        return '';
    }

    if ($answer === 'taken') {
        return pg_booking_widget_taken_text();
    }

    if ($answer === 'expired') {
        return lang('The time we kept for you ran out and is no longer free. Please pick another one.');
    }

    return lang('Something went wrong. Please try again.');
}

/**
 * The details a visitor sent with the link's form, cleaned and checked as
 * the widget's are; the consent box is always on this form, so an unticked
 * one is an error. Pure.
 *
 * @param array $post
 * @return array values, errors (pg_booking_widget_details_clean())
 */
function pg_booking_link_details($post)
{
    $post = is_array($post) ? $post : array();
    $input = array('consent' => !empty($post['consent']));

    foreach (array('name', 'email', 'phone', 'notes') as $key) {
        $input[$key] = (isset($post[$key]) && is_string($post[$key])) ? $post[$key] : '';
    }

    return pg_booking_widget_details_clean($input);
}
