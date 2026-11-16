<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Courses: a course (course) is a private folder a product already opens,
 * its classroom page and its ordered lessons (course_lesson), each a page of
 * that folder. A member's progress is one row per fact (course_progress): a
 * lesson done, the first open of a course given by hand, the completion.
 * Each fact is written once: the table's key holds it and the writers use
 * INSERT IGNORE, so a second press or a race writes nothing.
 *
 * When a lesson is open is one pure rule (pg_course_lesson_state()): its
 * days since the enrolment day, counted at read, and with "Lessons in
 * order" the lesson before it done; a done lesson stays open and an editor
 * always passes. The enrolment day is read from the member's first counting
 * order that opens the folder, or else from the first-open row: no grant
 * row names its order, and none is kept per grant.
 *
 * The certificate's code names the completion (course, member, moment) and
 * carries a signature made with the site's ENCRYPTION_KEY, so nothing more
 * is stored for it.
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
 * Whether the courses tables exist (2026.11.0, step 11.0.234). Files land
 * before the upgrade runs, so every reader asks this first.
 *
 * @return bool
 */
function pg_course_ready()
{
    return pg_schema_has('course') && pg_schema_has('course_lesson') && pg_schema_has('course_progress');
}

/**
 * The number a kind of progress row is stored under
 * (course_progress.kind): done (a lesson), first_open (a course given by
 * hand, opened for the first time), complete (the course). 0 for any other
 * name. Pure.
 *
 * @param string $name
 * @return int
 */
function pg_course_kind($name)
{
    $kinds = array('done' => 1, 'first_open' => 2, 'complete' => 3);

    return $kinds[(string) $name] ?? 0;
}

/**
 * Whether an order's status makes it count for the enrolment day: a placed
 * order that was not cancelled. Pure.
 *
 * @param string $status orders.status
 * @return bool
 */
function pg_course_order_counts($status)
{
    return in_array((string) $status, array('complete', 'exported'), true);
}

/**
 * The member's enrolment day: the day of the first counting order among
 * $orders, else the day of the first open, else ''. Pure.
 *
 * @param array $orders        rows with status and order_date (Unix time)
 * @param int   $first_open_at the first-open row's time, 0 for none
 * @return string Y-m-d or ''
 */
function pg_course_enrolment_day(array $orders, $first_open_at)
{
    $first = 0;

    foreach ($orders as $order) {
        $at = (int) ($order['order_date'] ?? 0);

        if (($at > 0) && pg_course_order_counts($order['status'] ?? '') && (($first === 0) || ($at < $first))) {
            $first = $at;
        }
    }

    if ($first === 0) {
        $first = (int) $first_open_at;
    }

    return ($first > 0) ? date('Y-m-d', $first) : '';
}

/**
 * Whether a lesson is open for a member, and if not why. Checked in this
 * order: an editor passes; a lesson that is no lesson of the course is not
 * closed; a done lesson stays open; its days since the enrolment day (a
 * member with no day yet counts from today, the day of the first open); with
 * "Lessons in order" the lesson before it done. Days are counted in calendar
 * days (pg_calendar_days_later()), so a daylight saving change moves no
 * lesson. Pure.
 *
 * @param array  $lessons      the course's lessons: id, sort_order, open_days
 * @param int    $lesson_id
 * @param array  $done_ids     the lessons the member has done
 * @param string $enrolment_day Y-m-d, '' for none yet
 * @param bool   $in_order     the course's "Lessons in order"
 * @param string $today        Y-m-d
 * @param bool   $can_edit     whether the member may edit the page
 * @return array open (bool), reason ('', 'days' or 'order'), opens_on (Y-m-d
 *               for days), before_id (the lesson to finish first for order)
 */
function pg_course_lesson_state(array $lessons, $lesson_id, array $done_ids, $enrolment_day, $in_order, $today, $can_edit)
{
    $open = array('open' => true, 'reason' => '', 'opens_on' => '', 'before_id' => 0);
    $lesson_id = (int) $lesson_id;
    $done = array_map('intval', $done_ids);

    if ($can_edit || in_array($lesson_id, $done, true)) {
        return $open;
    }

    usort($lessons, function ($a, $b) {
        return array((int) $a['sort_order'], (int) $a['id']) <=> array((int) $b['sort_order'], (int) $b['id']);
    });

    $before_id = 0;
    $lesson = null;

    foreach ($lessons as $row) {
        if ((int) $row['id'] === $lesson_id) {
            $lesson = $row;
            break;
        }

        $before_id = (int) $row['id'];
    }

    if ($lesson === null) {
        return $open;
    }

    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $enrolment_day) ? (string) $enrolment_day : (string) $today;
    // Noon keeps the day whatever a daylight saving change does to its length.
    $opens_on = date('Y-m-d', pg_calendar_days_later(max(0, (int) $lesson['open_days']), strtotime($start . ' 12:00:00')));

    if ($opens_on > (string) $today) {
        return array('open' => false, 'reason' => 'days', 'opens_on' => $opens_on, 'before_id' => 0);
    }

    if ($in_order && ($before_id > 0) && !in_array($before_id, $done, true)) {
        return array('open' => false, 'reason' => 'order', 'opens_on' => '', 'before_id' => $before_id);
    }

    return $open;
}

/**
 * A progress row, or null for a visitor or a missing id. Pure.
 *
 * @param string $kind      a name of pg_course_kind()
 * @param int    $user_id
 * @param int    $course_id
 * @param int    $lesson_id 0 for a row about the course
 * @param int    $now
 * @return array|null
 */
function _pg_course_row($kind, $user_id, $course_id, $lesson_id, $now)
{
    if (((int) $user_id <= 0) || ((int) $course_id <= 0)) {
        return null;
    }

    return array(
        'user_id'    => (int) $user_id,
        'course_id'  => (int) $course_id,
        'lesson_id'  => (int) $lesson_id,
        'kind'       => pg_course_kind($kind),
        'created_at' => (int) $now,
    );
}

/**
 * The first-open row of a course: only for a signed-in member who has the
 * folder. Pure.
 *
 * @param int  $user_id    0 for a visitor
 * @param int  $course_id
 * @param bool $has_folder
 * @param int  $now
 * @return array|null
 */
function pg_course_first_open_row($user_id, $course_id, $has_folder, $now)
{
    return $has_folder ? _pg_course_row('first_open', $user_id, $course_id, 0, $now) : null;
}

/**
 * The row of a lesson done. Pure.
 *
 * @param int $user_id
 * @param int $course_id
 * @param int $lesson_id
 * @param int $now
 * @return array|null
 */
function pg_course_done_row($user_id, $course_id, $lesson_id, $now)
{
    return ((int) $lesson_id > 0) ? _pg_course_row('done', $user_id, $course_id, $lesson_id, $now) : null;
}

/**
 * The completion row once every lesson of the course is done, or null: a
 * lesson left, the course already complete, or a course without lessons.
 * Pure.
 *
 * @param int   $user_id
 * @param int   $course_id
 * @param array $lesson_ids the course's lessons
 * @param array $done_ids   the lessons the member has done
 * @param bool  $complete   whether the completion row exists
 * @param int   $now
 * @return array|null
 */
function pg_course_completion_row($user_id, $course_id, array $lesson_ids, array $done_ids, $complete, $now)
{
    $lesson_ids = array_map('intval', $lesson_ids);

    if ($complete || !$lesson_ids || array_diff($lesson_ids, array_map('intval', $done_ids))) {
        return null;
    }

    return _pg_course_row('complete', $user_id, $course_id, 0, $now);
}

/**
 * The statement every progress row is written with. The key (user_id,
 * course_id, kind, lesson_id) holds one row per fact, so IGNORE makes a
 * second write a no-op. Pure.
 *
 * @return string
 */
function pg_course_progress_insert_sql()
{
    return "INSERT IGNORE INTO course_progress (user_id, course_id, lesson_id, kind, created_at) VALUES (?, ?, ?, ?, ?)";
}

/**
 * Writes a progress row once.
 *
 * @param array|null $row from the row builders above
 * @return bool whether a new row was written
 */
function pg_course_write_row($row)
{
    if (!is_array($row) || !pg_course_ready()) {
        return false;
    }

    $values = array();

    foreach (array('user_id', 'course_id', 'lesson_id', 'kind', 'created_at') as $column) {
        $values[] = (string) (int) $row[$column];
    }

    if (!db_q(pg_course_progress_insert_sql(), $values)) {
        return false;
    }

    return mysqli_affected_rows(db::$con) === 1;
}

/**
 * A course row.
 *
 * @param int $course_id
 * @return array|null id, name, folder_id, classroom_page_id, in_order, certificate
 */
function pg_course_get($course_id)
{
    if (((int) $course_id <= 0) || !pg_course_ready()) {
        return null;
    }

    $row = db_item("SELECT id, name, folder_id, classroom_page_id, in_order, certificate FROM course WHERE id = '" . (int) $course_id . "'");

    return is_array($row) && $row ? $row : null;
}

/**
 * A course's lessons in their order.
 *
 * @param int $course_id
 * @return array rows: id, course_id, page_id, sort_order, open_days, quiz_page_id, quiz_score
 */
function pg_course_lessons($course_id)
{
    if (((int) $course_id <= 0) || !pg_course_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT id, course_id, page_id, sort_order, open_days, quiz_page_id, quiz_score
        FROM course_lesson
        WHERE course_id = '" . (int) $course_id . "'
        ORDER BY sort_order ASC, id ASC");

    return is_array($rows) ? $rows : array();
}

/**
 * The lesson a page is, with its course, or null for a page that is no
 * lesson.
 *
 * @param int $page_id
 * @return array|null the lesson's columns and course (the course row)
 */
function pg_course_lesson_for_page($page_id)
{
    if (((int) $page_id <= 0) || !pg_course_ready()) {
        return null;
    }

    $lesson = db_item(
        "SELECT id, course_id, page_id, sort_order, open_days, quiz_page_id, quiz_score
        FROM course_lesson
        WHERE page_id = '" . (int) $page_id . "'
        ORDER BY id ASC
        LIMIT 1");

    if (!is_array($lesson) || !$lesson) {
        return null;
    }

    $course = pg_course_get($lesson['course_id']);

    if ($course === null) {
        return null;
    }

    $lesson['course'] = $course;

    return $lesson;
}

/**
 * A member's progress rows of a course.
 *
 * @param int $user_id
 * @param int $course_id
 * @return array done_ids, first_open_at (0 for none), completed_at (0 for none)
 */
function pg_course_member_progress($user_id, $course_id)
{
    $out = array('done_ids' => array(), 'first_open_at' => 0, 'completed_at' => 0);

    if (((int) $user_id <= 0) || ((int) $course_id <= 0) || !pg_course_ready()) {
        return $out;
    }

    $rows = db_items(
        "SELECT lesson_id, kind, created_at
        FROM course_progress
        WHERE (user_id = '" . (int) $user_id . "') AND (course_id = '" . (int) $course_id . "')");

    foreach (is_array($rows) ? $rows : array() as $row) {
        $kind = (int) $row['kind'];

        if ($kind === pg_course_kind('done')) {
            $out['done_ids'][] = (int) $row['lesson_id'];
        } elseif ($kind === pg_course_kind('first_open')) {
            $out['first_open_at'] = (int) $row['created_at'];
        } elseif ($kind === pg_course_kind('complete')) {
            $out['completed_at'] = (int) $row['created_at'];
        }
    }

    return $out;
}

/**
 * The member's orders with a line that opens a folder: their status and
 * date, for pg_course_enrolment_day().
 *
 * @param int $user_id
 * @param int $folder_id
 * @return array rows: status, order_date
 */
function pg_course_member_orders($user_id, $folder_id)
{
    if (((int) $user_id <= 0) || ((int) $folder_id <= 0)) {
        return array();
    }

    $rows = db_items(
        "SELECT DISTINCT orders.id, orders.status, orders.order_date
        FROM orders
        INNER JOIN order_items ON order_items.order_id = orders.id
        INNER JOIN products ON products.id = order_items.product_id
        WHERE
            (orders.user_id = '" . (int) $user_id . "')
            AND (products.grant_private_access = '1')
            AND (products.private_folder = '" . (int) $folder_id . "')" . pg_order_item_active_sql() . "
        ORDER BY orders.order_date ASC");

    return is_array($rows) ? $rows : array();
}

/**
 * A member's enrolment day of a course.
 *
 * @param int   $user_id
 * @param array $course   a course row
 * @param array $progress pg_course_member_progress()
 * @return string Y-m-d or ''
 */
function pg_course_member_enrolment_day($user_id, array $course, array $progress)
{
    return pg_course_enrolment_day(pg_course_member_orders($user_id, $course['folder_id'] ?? 0), $progress['first_open_at'] ?? 0);
}

/**
 * Keeps the first open of a course for a member given the folder by hand:
 * written once, never for a visitor or a member without the folder.
 *
 * @param int  $user_id
 * @param int  $course_id
 * @param bool $has_folder
 * @return bool whether the row was written now
 */
function pg_course_record_first_open($user_id, $course_id, $has_folder)
{
    return pg_course_write_row(pg_course_first_open_row($user_id, $course_id, $has_folder, time()));
}

/**
 * Marks a lesson done for a member, once, and completes the course when it
 * was the last lesson left. The caller has checked the folder access, that
 * the lesson is open and, for a quiz lesson, the score.
 *
 * @param int $user_id
 * @param int $course_id
 * @param int $lesson_id
 * @return array done (a new done row), completed (a new completion row)
 */
function pg_course_mark_done($user_id, $course_id, $lesson_id)
{
    $now = time();
    $out = array('done' => pg_course_write_row(pg_course_done_row($user_id, $course_id, $lesson_id, $now)), 'completed' => false);

    $progress = pg_course_member_progress($user_id, $course_id);
    $lesson_ids = array_map('intval', array_column(pg_course_lessons($course_id), 'id'));

    $out['completed'] = pg_course_write_row(pg_course_completion_row($user_id, $course_id, $lesson_ids, $progress['done_ids'], $progress['completed_at'] > 0, $now));

    return $out;
}

/**
 * Base 36 of a positive whole number, in capitals. Pure.
 *
 * @param int $number
 * @return string
 */
function _pg_course_base36($number)
{
    return strtoupper(base_convert((string) max(0, (int) $number), 10, 36));
}

/**
 * The signature of a certificate's three parts, 12 capital hex characters.
 * Pure.
 *
 * @param string $body course-member-moment, base 36
 * @param string $key
 * @return string
 */
function _pg_course_certificate_signature($body, $key)
{
    return strtoupper(substr(hash_hmac('sha256', 'course-certificate:' . $body, (string) $key), 0, 12));
}

/**
 * A certificate's code: the course, the member and the moment of completion
 * in base 36, then a signature made with the site's key. Pure.
 *
 * @param int    $user_id
 * @param int    $course_id
 * @param int    $completed_at
 * @param string $key the site's ENCRYPTION_KEY
 * @return string '' without a key or an id
 */
function pg_course_certificate_code($user_id, $course_id, $completed_at, $key)
{
    if (((string) $key === '') || ((int) $user_id <= 0) || ((int) $course_id <= 0) || ((int) $completed_at <= 0)) {
        return '';
    }

    $body = _pg_course_base36($course_id) . '-' . _pg_course_base36($user_id) . '-' . _pg_course_base36($completed_at);

    return $body . '-' . _pg_course_certificate_signature($body, $key);
}

/**
 * Reads a code back, typed in any letter case; null when it is no code or
 * any part was changed. The caller still checks that the completion row
 * exists. Pure.
 *
 * @param string $code
 * @param string $key the site's ENCRYPTION_KEY
 * @return array|null user_id, course_id, completed_at
 */
function pg_course_certificate_parse($code, $key)
{
    $code = strtoupper(trim((string) $code));

    if (((string) $key === '') || !preg_match('/^([0-9A-Z]{1,13})-([0-9A-Z]{1,13})-([0-9A-Z]{1,13})-([0-9A-F]{12})$/', $code, $parts)) {
        return null;
    }

    $body = $parts[1] . '-' . $parts[2] . '-' . $parts[3];

    if (!hash_equals(_pg_course_certificate_signature($body, $key), $parts[4])) {
        return null;
    }

    $out = array(
        'user_id'      => (int) base_convert(strtolower($parts[2]), 36, 10),
        'course_id'    => (int) base_convert(strtolower($parts[1]), 36, 10),
        'completed_at' => (int) base_convert(strtolower($parts[3]), 36, 10),
    );

    return (($out['user_id'] > 0) && ($out['course_id'] > 0) && ($out['completed_at'] > 0)) ? $out : null;
}
