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
 * Whether an item counts, pure: a page outside the Recycle Bin, or a record
 * of a form used as a collection. The one rule of the screen, the announce
 * and the API: a binned page or a record of a form no longer a collection
 * keeps its row but is not listed, announced or returned.
 *
 * @param string $kind  page or record
 * @param array  $facts exists (bool); for a page in_bin; for a record
 *                      collection (its form's flag)
 * @return bool
 */
function pg_content_review_item_counts($kind, array $facts)
{
    $kind = pg_content_review_kind($kind);

    if (($kind === '') || empty($facts['exists'])) {
        return false;
    }

    if ($kind === 'page') {
        return empty($facts['in_bin']);
    }

    return !empty($facts['collection']);
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

/** The sweep's state file under data/temp/ (once a day). */
const PG_CONTENT_REVIEW_SWEEP_STATE = 'content_review_sweep.json';

/**
 * Whether the table exists. Asked once a request; every read and write
 * waits for it, so a site whose files landed before the upgrade runs as
 * before.
 *
 * @return bool
 */
function pg_content_review_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = pg_schema_has('content_review');
    }

    return $ready;
}

/**
 * What the item is today: whether it exists, its folder (a record: its form
 * page's folder) and, for a record, its form's collection flag.
 *
 * @param string $kind
 * @param int    $item_id
 * @return array exists, folder_id, in_bin (a page), collection (a record)
 */
function pg_content_review_item_facts($kind, $item_id)
{
    $kind = pg_content_review_kind($kind);
    $item_id = (int) $item_id;
    $none = array('exists' => false, 'folder_id' => 0, 'in_bin' => false, 'collection' => 0);

    if (($kind === '') || ($item_id < 1)) {
        return $none;
    }

    if ($kind === 'page') {
        $row = db_item("SELECT page_id, page_folder FROM page WHERE page_id = '" . $item_id . "' LIMIT 1");

        if (!$row) {
            return $none;
        }

        $bin = function_exists('pg_recycle_bin_folder_ids') ? pg_recycle_bin_folder_ids() : array();

        return array(
            'exists'     => true,
            'folder_id'  => (int) $row['page_folder'],
            'in_bin'     => in_array((int) $row['page_folder'], array_map('intval', $bin), true),
            'collection' => 0,
        );
    }

    $row = db_item(
        "SELECT forms.id, page.page_folder, custom_form_pages.collection
        FROM forms
        LEFT JOIN custom_form_pages ON custom_form_pages.page_id = forms.page_id
        LEFT JOIN page ON page.page_id = forms.page_id
        WHERE forms.id = '" . $item_id . "'
        LIMIT 1");

    return $row
        ? array('exists' => true, 'folder_id' => (int) $row['page_folder'], 'in_bin' => false, 'collection' => (int) $row['collection'])
        : $none;
}

/**
 * Whether a panel user may edit the item now: an enabled account whose role
 * and folder rights reach it (pg_content_review_person_may_edit()).
 *
 * @param int    $user_id
 * @param string $kind
 * @param array  $facts   pg_content_review_item_facts()
 * @param array  $folders a cache of each user's folders, filled on the way
 * @return bool
 */
function pg_content_review_user_may_edit($user_id, $kind, array $facts, array &$folders = array())
{
    $user_id = (int) $user_id;

    if (($user_id < 1) || empty($facts['exists'])) {
        return false;
    }

    $disabled = pg_user_has_disabled_at() ? ', user_disabled_at' : '';
    $row = db_item("SELECT user_role, user_manage_forms" . $disabled . " FROM user WHERE user_id = '" . $user_id . "' LIMIT 1");

    if (!$row || (!empty($row['user_disabled_at']))) {
        return false;
    }

    $user = array('role' => $row['user_role'], 'manage_forms' => ($row['user_manage_forms'] === 'yes'));

    if (((int) $user['role'] === 3) && !isset($folders[$user_id])) {
        $folders[$user_id] = get_folders_that_user_has_access_to($user_id);
    }

    return pg_content_review_person_may_edit($kind, $user, $facts['folder_id'], isset($folders[$user_id]) ? $folders[$user_id] : array());
}

/**
 * An item's row, null when it keeps nothing (or before the table).
 *
 * @param string $kind
 * @param int    $item_id
 * @return array|null
 */
function pg_content_review_get($kind, $item_id)
{
    $kind = pg_content_review_kind($kind);

    if (($kind === '') || ((int) $item_id < 1) || !pg_content_review_ready()) {
        return null;
    }

    $result = db_q("SELECT * FROM content_review WHERE item_kind = ? AND item_id = ? LIMIT 1", array($kind, (string) (int) $item_id));
    $row = $result ? mysqli_fetch_assoc($result) : null;

    return $row ? $row : null;
}

/**
 * The checks of a write, pure: '' when the values may be kept, else the
 * reason (item, person, date).
 *
 * @param bool       $item_counts  pg_content_review_item_counts()
 * @param int        $user_id      0 for nobody
 * @param bool       $person_ok    whether that user may edit the item
 * @param string|null $review_on   Y-m-d or null
 * @return string
 */
function pg_content_review_write_problem($item_counts, $user_id, $person_ok, $review_on)
{
    if (!$item_counts) {
        return 'item';
    }

    if (((int) $user_id > 0) && !$person_ok) {
        return 'person';
    }

    if (($review_on !== null) && (pg_content_review_parse_date($review_on) === null)) {
        return 'date';
    }

    return '';
}

/**
 * Keeps an item's person, date and interval; an item left with nothing
 * (no person, no date, no interval, never reviewed) loses its row.
 *
 * @param string      $kind
 * @param int         $item_id
 * @param int         $user_id   0 for nobody
 * @param string|null $review_on Y-m-d, null for no date
 * @param int         $months    0, 3, 6 or 12
 * @return string '' when kept, else item, person, date or table
 */
function pg_content_review_save($kind, $item_id, $user_id, $review_on, $months)
{
    $kind = pg_content_review_kind($kind);
    $item_id = (int) $item_id;
    $user_id = max(0, (int) $user_id);
    $review_on = (($review_on === null) || ($review_on === '')) ? null : (string) $review_on;
    $months = pg_content_review_interval($months);

    if (!pg_content_review_ready()) {
        return 'table';
    }

    $facts = pg_content_review_item_facts($kind, $item_id);
    $person_ok = ($user_id > 0) ? pg_content_review_user_may_edit($user_id, $kind, $facts) : true;
    $problem = pg_content_review_write_problem(pg_content_review_item_counts($kind, $facts), $user_id, $person_ok, $review_on);

    if ($problem !== '') {
        return $problem;
    }

    $existing = pg_content_review_get($kind, $item_id);

    if (($user_id === 0) && ($review_on === null) && ($months === 0) && (!$existing || ((int) $existing['reviewed_at'] === 0))) {
        pg_content_review_clear($kind, $item_id);
        return '';
    }

    $ok = db_q(
        "INSERT INTO content_review (item_kind, item_id, user_id, review_on, interval_months)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), review_on = VALUES(review_on),
            interval_months = VALUES(interval_months)",
        array($kind, (string) $item_id, (string) $user_id, $review_on, (string) $months));

    return $ok ? '' : 'table';
}

/**
 * "Mark reviewed": when and by whom; the date moves to today plus the
 * interval, or is cleared without one (pg_content_review_next_date()). The
 * row stays either way: it keeps when and by whom.
 *
 * @param string   $kind
 * @param int      $item_id
 * @param int      $by  the user who marks it
 * @param int|null $now unix time; now when null
 * @return string '' when written, else item or table
 */
function pg_content_review_mark_reviewed($kind, $item_id, $by, $now = null)
{
    $kind = pg_content_review_kind($kind);
    $item_id = (int) $item_id;
    $now = ($now === null) ? time() : (int) $now;

    if (!pg_content_review_ready()) {
        return 'table';
    }

    if (!pg_content_review_item_counts($kind, pg_content_review_item_facts($kind, $item_id))) {
        return 'item';
    }

    $existing = pg_content_review_get($kind, $item_id);
    $months = $existing ? pg_content_review_interval($existing['interval_months']) : 0;
    $next = pg_content_review_next_date(pg_content_review_today($now), $months);

    $ok = db_q(
        "INSERT INTO content_review (item_kind, item_id, review_on, reviewed_at, reviewed_by)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE review_on = VALUES(review_on), reviewed_at = VALUES(reviewed_at),
            reviewed_by = VALUES(reviewed_by)",
        array($kind, (string) $item_id, $next, (string) $now, (string) max(0, (int) $by)));

    return $ok ? '' : 'table';
}

/**
 * "Clear": the item keeps nothing, its row goes.
 *
 * @param string $kind
 * @param int    $item_id
 * @return bool
 */
function pg_content_review_clear($kind, $item_id)
{
    $kind = pg_content_review_kind($kind);

    if (($kind === '') || !pg_content_review_ready()) {
        return false;
    }

    return (bool) db_q("DELETE FROM content_review WHERE item_kind = ? AND item_id = ?", array($kind, (string) (int) $item_id));
}

/**
 * The sweep's bounded statements, pure: the rows of a page whose row is
 * gone and of a record whose forms row is gone are deleted, and a reviewer
 * whose account is gone is cleared (reviewed_at stays: the screen still
 * says when, no longer who). Single-table forms with NOT EXISTS, as a
 * multi-table DELETE takes no LIMIT. A page in the Recycle Bin and a record
 * whose form stopped being a collection keep their rows: both can come
 * back, and they are not counted meanwhile.
 *
 * @param int $limit
 * @return string[]
 */
function pg_content_review_sweep_sql($limit = 5000)
{
    $limit = max(1, (int) $limit);

    return array(
        "DELETE FROM content_review WHERE item_kind = 'page'
            AND NOT EXISTS (SELECT 1 FROM page WHERE page.page_id = content_review.item_id) LIMIT " . $limit,
        "DELETE FROM content_review WHERE item_kind = 'record'
            AND NOT EXISTS (SELECT 1 FROM forms WHERE forms.id = content_review.item_id) LIMIT " . $limit,
        "DELETE FROM content_review WHERE item_kind NOT IN ('page', 'record') LIMIT " . $limit,
        "UPDATE content_review SET reviewed_by = 0 WHERE reviewed_by > 0
            AND NOT EXISTS (SELECT 1 FROM user WHERE user.user_id = content_review.reviewed_by) LIMIT " . $limit,
    );
}

/**
 * @return string
 */
function pg_content_review_sweep_file()
{
    return PG_FUNCTIONS_DIR . '/data/temp/' . PG_CONTENT_REVIEW_SWEEP_STATE;
}

/**
 * Whether the daily sweep is owed: it has not run in the last day.
 *
 * @return bool
 */
function pg_content_review_sweep_due()
{
    if (!pg_content_review_ready()) {
        return false;
    }

    $state = pg_content_review_sweep_state();

    return $state['done_at'] < (time() - 86400);
}

/**
 * The sweep's saved state: when a pass last got through and, while one is
 * under way, the key it reached in the person check.
 *
 * @return array done_at, kind, id
 */
function pg_content_review_sweep_state()
{
    $state = @json_decode((string) @file_get_contents(pg_content_review_sweep_file()), true);

    return array(
        'done_at' => (is_array($state) && isset($state['done_at'])) ? (int) $state['done_at'] : 0,
        'kind'    => (is_array($state) && isset($state['kind']) && is_string($state['kind'])) ? pg_content_review_kind($state['kind']) : '',
        'id'      => (is_array($state) && isset($state['id'])) ? max(0, (int) $state['id']) : 0,
    );
}

/**
 * @param array $state done_at, kind, id
 * @return void
 */
function pg_content_review_sweep_save(array $state)
{
    $file = pg_content_review_sweep_file();

    if (!is_dir(dirname($file))) {
        @mkdir(dirname($file), 0755, true);
    }

    @file_put_contents($file, json_encode($state), LOCK_EX);
}

/**
 * The daily pass (job.php): the rows of items that are gone go, a reviewer
 * whose account is gone is cleared, and an item whose person was removed,
 * disabled or may no longer edit it becomes nobody's (user_id 0; who
 * reviewed it stays). The person check walks the primary key from where the
 * last run stopped, so a large table is reached to its end across runs; the
 * pass is through, and owed again a day later, once it reached the end.
 *
 * @param int $seconds
 * @return array removed, released (persons cleared)
 */
function pg_content_review_sweep($seconds = 5)
{
    $out = array('removed' => 0, 'released' => 0);

    if (!pg_content_review_ready()) {
        return $out;
    }

    $deadline = microtime(true) + max(1, (int) $seconds);
    $state = pg_content_review_sweep_state();

    foreach (pg_content_review_sweep_sql() as $index => $sql) {
        do {
            if (db_q($sql) === false) {
                return $out;
            }

            $affected = max(0, (int) mysqli_affected_rows(db::$con));

            if ($index < 3) {
                $out['removed'] += $affected;
            }

            if (microtime(true) >= $deadline) {
                return $out;
            }
        } while ($affected >= 5000);
    }

    $folders = array();
    $after_kind = $state['kind'];
    $after_id = $state['id'];

    while (true) {
        $rows = db_items(
            "SELECT item_kind, item_id, user_id FROM content_review
            WHERE user_id > 0 AND (item_kind > '" . e($after_kind) . "' OR (item_kind = '" . e($after_kind) . "' AND item_id > '" . (int) $after_id . "'))
            ORDER BY item_kind, item_id
            LIMIT 500");

        if (!$rows) {
            break;
        }

        foreach ($rows as $row) {
            $after_kind = $row['item_kind'];
            $after_id = (int) $row['item_id'];
            $facts = pg_content_review_item_facts($row['item_kind'], $row['item_id']);

            if ($facts['exists'] && !pg_content_review_user_may_edit($row['user_id'], $row['item_kind'], $facts, $folders)) {
                if (db_q("UPDATE content_review SET user_id = 0 WHERE item_kind = ? AND item_id = ? AND user_id = ?",
                    array($row['item_kind'], (string) $after_id, (string) (int) $row['user_id']))) {
                    $out['released'] += max(0, (int) mysqli_affected_rows(db::$con));
                }
            }

            if (microtime(true) >= $deadline) {
                pg_content_review_sweep_save(array('done_at' => $state['done_at'], 'kind' => $after_kind, 'id' => $after_id));
                return $out;
            }
        }
    }

    pg_content_review_sweep_save(array('done_at' => time(), 'kind' => '', 'id' => 0));

    return $out;
}

/** The most rows one general job tick announces; the rest wait for the next. */
const PG_CONTENT_REVIEW_ANNOUNCE_LIMIT = 200;

/**
 * The claim of one row, pure: the UPDATE that writes announced_on =
 * review_on only where the date has passed and was not announced yet (or
 * was announced for another date), with its values. Only a write that
 * changed the row announces, so two job runs at once announce an item once;
 * a new date (Mark reviewed, a date set) differs from announced_on again.
 *
 * @param string $kind
 * @param int    $item_id
 * @param string $review_on the date the row was read with
 * @param string $today     Y-m-d, pg_content_review_today()
 * @return array array('sql', 'params')
 */
function pg_content_review_claim_sql($kind, $item_id, $review_on, $today)
{
    return array(
        'sql' => 'UPDATE content_review SET announced_on = review_on'
            . ' WHERE item_kind = ? AND item_id = ? AND review_on = ? AND review_on < ?'
            . ' AND (announced_on IS NULL OR announced_on <> review_on)',
        'params' => array((string) $kind, (string) (int) $item_id, (string) $review_on, (string) $today),
    );
}

/**
 * The rows whose date has passed and that were not announced for that date,
 * oldest date first. Today is a bound value, never CURDATE().
 *
 * @param string $today
 * @param int    $limit
 * @return array item_kind, item_id, user_id, review_on each
 */
function pg_content_review_due_rows($today, $limit = PG_CONTENT_REVIEW_ANNOUNCE_LIMIT)
{
    $result = db_q(
        'SELECT item_kind, item_id, user_id, review_on FROM content_review
        WHERE review_on IS NOT NULL AND review_on < ? AND (announced_on IS NULL OR announced_on <> review_on)
        ORDER BY review_on, item_kind, item_id
        LIMIT ' . max(1, (int) $limit),
        array((string) $today));

    $rows = array();

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * An item's name as the screen shows it: a page's name, a record's title
 * field, else its reference code.
 *
 * @param string $kind
 * @param int    $item_id
 * @return string
 */
function pg_content_review_item_name($kind, $item_id)
{
    $item_id = (int) $item_id;

    if ($kind === 'page') {
        return (string) db_value("SELECT page_name FROM page WHERE page_id = '" . $item_id . "'");
    }

    $title = (string) db_value(
        "SELECT form_data.data FROM form_data
        JOIN form_fields ON form_data.form_field_id = form_fields.id
        WHERE form_data.form_id = '" . $item_id . "' AND form_fields.rss_field = 'title' AND form_fields.office_use_only = 0
        LIMIT 1");

    return (trim($title) !== '') ? $title : (string) db_value("SELECT reference_code FROM forms WHERE id = '" . $item_id . "'");
}

/**
 * What content.review_due carries, pure: the item (id, kind, name), its
 * person (user_id, 0 for nobody, and their username) and the date passed.
 *
 * @param array  $row         item_kind, item_id, user_id, review_on
 * @param string $name        the item's name
 * @param string $person_name the person's username, '' for nobody
 * @return array
 */
function pg_content_review_due_payload(array $row, $name, $person_name)
{
    return array(
        'id'        => (int) $row['item_id'],
        'kind'      => (string) $row['item_kind'],
        'name'      => (string) $name,
        'user_id'   => (int) $row['user_id'],
        'person'    => ((int) $row['user_id'] > 0) ? (string) $person_name : '',
        'review_on' => (string) $row['review_on'],
    );
}

/**
 * One announce run over the rows read, pure but for the callables: each
 * row is claimed first and announced only when the claim changed it and the
 * item still counts (a page outside the Recycle Bin, a record of a
 * collection). An item that is gone or no longer counts is claimed but not
 * announced, so it is not read again every run.
 *
 * @param array    $rows     pg_content_review_due_rows()
 * @param callable $claim    row => bool, whether the claim changed the row
 * @param callable $counts   row => bool, whether the item counts
 * @param callable $announce row => void
 * @return int the items announced
 */
function pg_content_review_announce_run(array $rows, callable $claim, callable $counts, callable $announce)
{
    $announced = 0;

    foreach ($rows as $row) {
        if (($row['review_on'] ?? null) === null || ((string) $row['review_on'] === '')) {
            continue;
        }

        if (!$claim($row) || !$counts($row)) {
            continue;
        }

        $announce($row);
        $announced++;
    }

    return $announced;
}

/**
 * The general job's announce (job.php): content.review_due once per item on
 * the first run after its review date has passed, through pg_announce(), so
 * a flow can start from it and a webhook can carry it.
 *
 * @param int|null $now unix time; now when null
 * @return int the items announced
 */
function pg_content_review_announce_due($now = null)
{
    if (!pg_content_review_ready() || !function_exists('pg_announce')) {
        return 0;
    }

    $today = pg_content_review_today(($now === null) ? 0 : (int) $now);

    return pg_content_review_announce_run(pg_content_review_due_rows($today),
        function ($row) use ($today) {
            $claim = pg_content_review_claim_sql($row['item_kind'], $row['item_id'], $row['review_on'], $today);

            return (db_q($claim['sql'], $claim['params']) !== false) && ((int) mysqli_affected_rows(db::$con) === 1);
        },
        function ($row) {
            return pg_content_review_item_counts($row['item_kind'], pg_content_review_item_facts($row['item_kind'], $row['item_id']));
        },
        function ($row) {
            $person = ((int) $row['user_id'] > 0) ? (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $row['user_id'] . "'") : '';

            pg_announce('content.review_due', pg_content_review_due_payload($row, pg_content_review_item_name($row['item_kind'], $row['item_id']), $person));
        });
}

/**
 * Whether the Workspace is on, its files loaded for "Make a task" (they are
 * not loaded on every panel screen).
 *
 * @return bool
 */
function pg_content_review_workspace()
{
    if (!defined('WORKSPACE_ENABLED') || !WORKSPACE_ENABLED || !is_file(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php')) {
        return false;
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

    return function_exists('ws_followup_task_open') && function_exists('ws_rights');
}

/**
 * Whether "Make a task" is offered on an item, pure: only with the
 * Workspace on, an item with a person, and that person a member of the
 * Workspace's team.
 *
 * @param bool $workspace     WORKSPACE_ENABLED
 * @param int  $person_id     the item's person, 0 for nobody
 * @param bool $person_member whether that person is a member
 * @return bool
 */
function pg_content_review_task_offered($workspace, $person_id, $person_member)
{
    return $workspace && ((int) $person_id > 0) && $person_member;
}

/**
 * The follow-up "Make a task" opens, pure (ws_followup_task_data()'s
 * input): a task for the item's person, tagged with the item (a page, or
 * a record as a form), due on the review date, or today without one. A
 * date already here or passed sends no reminder: the task is due at once.
 *
 * @param string      $kind
 * @param int         $item_id
 * @param string      $name
 * @param int         $person_id
 * @param string|null $review_on
 * @param string      $today
 * @return array
 */
function pg_content_review_task_input($kind, $item_id, $name, $person_id, $review_on, $today)
{
    $due = (($review_on !== null) && ($review_on !== '')) ? (string) $review_on : (string) $today;
    $input = array(
        'title'       => lang(array('string' => 'Review {var:1}', 'vars' => array((string) $name))),
        'description' => lang('Read the item again and mark it reviewed on the Content review screen.'),
        'refs'        => array(array('type' => ($kind === 'record') ? 'form' : 'page', 'id' => (int) $item_id)),
        'assignees'   => array((int) $person_id),
        'channel_id'  => 0,
        'due_date'    => $due,
    );

    if ($due <= (string) $today) {
        $input['remind_minutes'] = null;
    }

    return $input;
}

/**
 * "Make a task": opens the follow-up of an item as whoever pressed, for the
 * item's person; pressed again while that task is open, the open one is
 * handed back (ws_followup_task_open() with $once).
 *
 * @param array  $user    the signed-in user
 * @param string $kind
 * @param int    $item_id
 * @return array ok, error, task_id, existing
 */
function pg_content_review_task_open(array $user, $kind, $item_id)
{
    $fail = function ($error) {
        return array('ok' => false, 'error' => $error, 'task_id' => 0, 'existing' => false);
    };

    $kind = pg_content_review_kind($kind);
    $row = pg_content_review_get($kind, $item_id);
    $workspace = pg_content_review_workspace();
    $person_id = $row ? (int) $row['user_id'] : 0;
    $person = ($workspace && ($person_id > 0)) ? ws_change_viewer_for_id($person_id) : null;

    if (!pg_content_review_task_offered($workspace, $person_id, is_array($person) && !empty($person['member']))) {
        return $fail(lang('A task can be made only for an item whose person is on the Workspace team.'));
    }

    if (!pg_content_review_item_counts($kind, pg_content_review_item_facts($kind, $item_id))) {
        return $fail(lang('This item is no longer listed.'));
    }

    $input = pg_content_review_task_input($kind, $item_id, pg_content_review_item_name($kind, $item_id), $person_id, $row['review_on'], pg_content_review_today());
    $opened = ws_followup_task_open(ws_viewer($user), $input, true);

    return $opened['ok']
        ? array('ok' => true, 'error' => '', 'task_id' => (int) $opened['task_id'], 'existing' => !empty($opened['existing']))
        : $fail((string) $opened['error']);
}
