<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Content review: a page or a collection record can keep a person (a panel
 * user who may edit it) and a date by which it is read again, with an
 * interval that moves the date when it is marked reviewed. This file holds
 * the rules: the state of a date in the site's time, the next date after a
 * review, which items count and who may be an item's person.
 *
 * Nothing here changes a page or record screen or a delete path: the rows
 * live in their own table, and the general job's sweep removes the rows of
 * items that are gone.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/** Days ahead in which a review date is "due soon" (today included). */
const PG_CONTENT_REVIEW_DUE_SOON_DAYS = 30;

/**
 * An item kind the table keeps, '' for any other.
 *
 * @param string $kind
 * @return string page or record
 */
function pg_content_review_kind($kind)
{
    return in_array($kind, array('page', 'record'), true) ? $kind : '';
}

/**
 * The intervals offered, in months; 0 is "not at all".
 *
 * @return int[]
 */
function pg_content_review_intervals()
{
    return array(0, 3, 6, 12);
}

/**
 * An interval as offered; anything else reads as 0 (not at all).
 *
 * @param mixed $months
 * @return int
 */
function pg_content_review_interval($months)
{
    $months = is_scalar($months) ? (int) $months : 0;

    return in_array($months, pg_content_review_intervals(), true) ? $months : 0;
}

/**
 * A Y-m-d date that exists, null for anything else.
 *
 * @param mixed $date
 * @return DateTime|null midnight of that day
 */
function pg_content_review_parse_date($date)
{
    if (!is_string($date) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
        || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
        return null;
    }

    return new DateTime($date . ' 00:00:00', new DateTimeZone('UTC'));
}

/**
 * Today in the site's time (PHP's zone, set from the site's settings).
 *
 * @param int $now unix time, 0 for now
 * @return string Y-m-d
 */
function pg_content_review_today($now = 0)
{
    return date('Y-m-d', ((int) $now > 0) ? (int) $now : time());
}

/**
 * The state of a review date, pure: overdue (before today), due soon
 * (today up to 30 days ahead), later, or not set (no date).
 *
 * @param string|null $review_on Y-m-d
 * @param string      $today     Y-m-d in the site's time
 * @return string overdue, due_soon, later or not_set
 */
function pg_content_review_state($review_on, $today)
{
    $date = pg_content_review_parse_date($review_on);
    $day = pg_content_review_parse_date($today);

    if (($date === null) || ($day === null)) {
        return 'not_set';
    }

    if ($date < $day) {
        return 'overdue';
    }

    $soon = clone $day;
    $soon->modify('+' . PG_CONTENT_REVIEW_DUE_SOON_DAYS . ' days');

    return ($date <= $soon) ? 'due_soon' : 'later';
}

/**
 * The review date after "Mark reviewed", pure: today plus the interval, the
 * day kept inside the target month (30 November + 3 months is 28 February);
 * null (the date cleared) without an interval.
 *
 * @param string $today  Y-m-d in the site's time
 * @param int    $months 3, 6 or 12; anything else clears the date
 * @return string|null Y-m-d
 */
function pg_content_review_next_date($today, $months)
{
    $months = pg_content_review_interval($months);
    $day = pg_content_review_parse_date($today);

    if (($months === 0) || ($day === null)) {
        return null;
    }

    $year = (int) $day->format('Y');
    $month = (int) $day->format('n') + $months;
    $year += intdiv($month - 1, 12);
    $month = (($month - 1) % 12) + 1;
    $last = (int) date('t', gmmktime(0, 0, 0, $month, 1, $year));

    return sprintf('%04d-%02d-%02d', $year, $month, min((int) $day->format('j'), $last));
}

/**
 * Whether an item counts, pure: a page that exists, or a record of a form
 * used as a collection.
 *
 * @param string $kind  page or record
 * @param array  $facts exists (bool); for a record collection (its form's flag)
 * @return bool
 */
function pg_content_review_item_counts($kind, array $facts)
{
    $kind = pg_content_review_kind($kind);

    if (($kind === '') || empty($facts['exists'])) {
        return false;
    }

    return ($kind === 'page') || !empty($facts['collection']);
}

/**
 * Whether a user may be an item's person, pure: a panel user who may edit
 * it. Roles under 3 edit every item; a user (role 3) edits a page in a
 * folder they have edit rights to, and a record when they also manage forms.
 *
 * @param string $kind      page or record
 * @param array  $user      role, manage_forms
 * @param int    $folder_id the page's folder (a record: its form's page)
 * @param array  $folders   the user's folders with edit rights, children included
 * @return bool
 */
function pg_content_review_person_may_edit($kind, array $user, $folder_id, array $folders)
{
    $kind = pg_content_review_kind($kind);

    if (($kind === '') || !isset($user['role']) || !is_numeric($user['role'])) {
        return false;
    }

    $role = (int) $user['role'];

    if ($role < 3) {
        return $role >= 0;
    }

    if ($role > 3) {
        return false;
    }

    if (($kind === 'record') && empty($user['manage_forms'])) {
        return false;
    }

    return in_array((int) $folder_id, array_map('intval', $folders), true);
}
