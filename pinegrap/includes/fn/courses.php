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

// The kinds of a progress row (course_progress.kind). A query names them
// through these, never as bare numbers.
const PG_COURSE_KIND_DONE = 1;
const PG_COURSE_KIND_FIRST_OPEN = 2;
const PG_COURSE_KIND_COMPLETE = 3;

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
    $kinds = array(
        'done'       => PG_COURSE_KIND_DONE,
        'first_open' => PG_COURSE_KIND_FIRST_OPEN,
        'complete'   => PG_COURSE_KIND_COMPLETE,
    );

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
 * @return array rows: id, course_id, page_id, sort_order, open_days, quiz_page_id, quiz_pass_score
 *               (the score the lesson needs, 0 with no quiz)
 */
function pg_course_lessons($course_id)
{
    if (((int) $course_id <= 0) || !pg_course_ready()) {
        return array();
    }

    $rows = db_items(
        "SELECT id, course_id, page_id, sort_order, open_days, quiz_page_id, quiz_pass_score
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
        "SELECT id, course_id, page_id, sort_order, open_days, quiz_page_id, quiz_pass_score
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

        if ($kind === PG_COURSE_KIND_DONE) {
            $out['done_ids'][] = (int) $row['lesson_id'];
        } elseif ($kind === PG_COURSE_KIND_FIRST_OPEN) {
            $out['first_open_at'] = (int) $row['created_at'];
        } elseif ($kind === PG_COURSE_KIND_COMPLETE) {
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
 * was the last lesson left. A completion row is never rewritten, not even
 * when lessons are added later: the certificate's day and code hang on it. The caller has checked the folder access, that
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
 * The course a page is already a lesson of, for the writer that adds a
 * lesson: a page is a lesson of one course only (course_lesson.idx_page),
 * so the gate has one classroom to send a closed lesson to.
 *
 * @param int $page_id
 * @return array|null the course row, or null when the page is free
 */
function pg_course_page_taken($page_id)
{
    $lesson = pg_course_lesson_for_page($page_id);

    return ($lesson === null) ? null : $lesson['course'];
}

/**
 * Deletes a lesson with its done rows. Used when the lesson is removed, its
 * page is deleted or the page leaves the course's folder. A completion row
 * already written stays.
 *
 * @param int $lesson_id
 * @return bool
 */
function pg_course_lesson_delete($lesson_id)
{
    $lesson_id = (int) $lesson_id;

    if (($lesson_id <= 0) || !pg_course_ready()) {
        return false;
    }

    if (!db_q("DELETE FROM course_progress WHERE (lesson_id = ?) AND (kind = ?)", array((string) $lesson_id, (string) PG_COURSE_KIND_DONE))) {
        return false;
    }

    return (bool) db_q("DELETE FROM course_lesson WHERE id = ?", array((string) $lesson_id));
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

/**
 * The settings text of the "Courses" switch (Site Settings, Features): '1'
 * on, missing off, so every site and a new install start off.
 */
define('PG_COURSE_SWITCH', 'courses');

/**
 * Whether the "Courses" switch is on.
 *
 * @return bool
 */
function pg_course_switch_on()
{
    if (!function_exists('pg_config_text')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return pg_config_text(PG_COURSE_SWITCH) === '1';
}

/**
 * Saves the "Courses" switch: '1' on, the row removed when off.
 *
 * @param bool $on
 * @return bool
 */
function pg_course_switch_save($on)
{
    if (!function_exists('pg_config_text_save')) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/config_text.php');
    }

    return (bool) pg_config_text_save(PG_COURSE_SWITCH, $on ? '1' : '');
}

/**
 * Whether a lesson's page still counts for its course: it sits right in the
 * course's folder. A page that left the folder gates nothing (Z17). Pure.
 *
 * @param int $page_folder_id   the page's folder, 0 for a page that is gone
 * @param int $course_folder_id
 * @return bool
 */
function pg_course_lesson_in_folder($page_folder_id, $course_folder_id)
{
    return ((int) $course_folder_id > 0) && ((int) $page_folder_id === (int) $course_folder_id);
}

/**
 * The gate's answer for a page a signed-in member may already see: whether
 * the lesson it is stays closed, why, and where the member is sent. Only a
 * lesson of a private folder is ever closed: a lesson in a public folder
 * is a free preview. A classroom page is never sent to itself (0: the
 * caller answers in place). Pure.
 *
 * @param bool       $switch_on     the "Courses" switch
 * @param string     $access_type   the page folder's access control type
 * @param array|null $lesson        pg_course_lesson_for_page(), null for no lesson
 * @param array      $lessons       the course's lessons
 * @param array      $done_ids      the lessons the member has done
 * @param string     $enrolment_day Y-m-d, '' for none yet
 * @param string     $today         Y-m-d
 * @param bool       $can_edit      whether the member may edit the page
 * @return array closed (bool), reason, opens_on, before_id (as
 *               pg_course_lesson_state()), classroom_page_id
 */
function pg_course_gate_answer($switch_on, $access_type, $lesson, array $lessons, array $done_ids, $enrolment_day, $today, $can_edit)
{
    $pass = array('closed' => false, 'reason' => '', 'opens_on' => '', 'before_id' => 0, 'classroom_page_id' => 0);

    if (!$switch_on || ((string) $access_type !== 'private') || !is_array($lesson) || !isset($lesson['course']) || !is_array($lesson['course'])) {
        return $pass;
    }

    $course = $lesson['course'];
    $state = pg_course_lesson_state($lessons, $lesson['id'] ?? 0, $done_ids, $enrolment_day, !empty($course['in_order']), $today, $can_edit);

    if ($state['open']) {
        return $pass;
    }

    $classroom = (int) ($course['classroom_page_id'] ?? 0);

    if ($classroom === (int) ($lesson['page_id'] ?? 0)) {
        $classroom = 0;
    }

    return array(
        'closed'            => true,
        'reason'            => $state['reason'],
        'opens_on'          => $state['opens_on'],
        'before_id'         => (int) $state['before_id'],
        'classroom_page_id' => max(0, $classroom),
    );
}

/**
 * The notice a closed lesson gives, plain text. It is read on the
 * classroom page, where every lesson is listed, so it names the lesson:
 * the closed lesson and the day it opens, or the lesson to finish first
 * and the lesson it opens. Pure but for lang().
 *
 * @param array  $answer      pg_course_gate_answer() of a closed lesson
 * @param string $name        the closed lesson's name
 * @param string $before_name the lesson to finish first, '' for unknown
 * @param string $day         the day it opens, as the site writes a date
 * @return string
 */
function pg_course_gate_notice_text(array $answer, $name, $before_name, $day)
{
    $name = trim((string) $name);
    $before_name = trim((string) $before_name);

    if ($answer['reason'] === 'days') {
        return lang(array('string' => '"{var:1}" opens on {var:2}.', 'vars' => array($name, (string) $day)));
    }

    if ($before_name === '') {
        return lang(array('string' => '"{var:1}" opens when the lesson before it is done.', 'vars' => $name));
    }

    return lang(array('string' => 'Finish "{var:1}" to open "{var:2}".', 'vars' => array($before_name, $name)));
}

/**
 * A lesson's name: its page's title, else its page name.
 *
 * @param int $page_id
 * @return string
 */
function _pg_course_lesson_name($page_id)
{
    $page = db_item("SELECT page_name, page_title FROM page WHERE page_id = '" . (int) $page_id . "'");

    if (!is_array($page) || !$page) {
        return '';
    }

    $title = trim((string) $page['page_title']);

    return ($title !== '') ? $title : (string) $page['page_name'];
}

/**
 * The notice a closed lesson gives, plain text, with the names and the
 * day read.
 *
 * @param array $answer  pg_course_gate_answer() of a closed lesson
 * @param array $lesson  pg_course_lesson_for_page()
 * @param array $lessons the course's lessons
 * @return string
 */
function pg_course_gate_notice(array $answer, array $lesson, array $lessons)
{
    $day = '';
    $before_name = '';

    if ($answer['reason'] === 'days') {
        $day = get_absolute_time(array(
            'timestamp'     => strtotime($answer['opens_on'] . ' 12:00:00'),
            'type'          => 'date',
            'format'        => 'plain_text',
            'size'          => 'long',
            'timezone_type' => 'site',
        ));
    } else {
        foreach ($lessons as $row) {
            if ((int) $row['id'] === (int) $answer['before_id']) {
                $before_name = _pg_course_lesson_name($row['page_id']);
                break;
            }
        }
    }

    return pg_course_gate_notice_text($answer, _pg_course_lesson_name($lesson['page_id']), $before_name, $day);
}

/**
 * The notice's markup on the classroom page, '' for no text. Its look is
 * its own, scoped by its class, so it reads on a theme without Bootstrap;
 * a site in dark mode (data-bs-theme on <html> or <body>) gets the dark
 * tones. Pure.
 *
 * @param string $text plain text
 * @return string
 */
function pg_course_notice_html($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return '';
    }

    $css = '.pg-course-notice{box-sizing:border-box;margin:0;padding:.75rem 1rem;background:#e8f1fb;color:#0a3a63;border-bottom:1px solid #b9d3ee;font-family:inherit;font-size:1rem;line-height:1.5;text-align:center}'
        . '[data-bs-theme="dark"] .pg-course-notice,.pg-course-notice[data-bs-theme="dark"]{background:#132a40;color:#d6e8fa;border-bottom-color:#2b4c6d}';

    return '<style>' . pg_style_tag_css($css) . '</style>'
        . '<div class="pg-course-notice" role="status">' . h($text) . '</div>';
}

/**
 * Puts the notice right after the page's <body> tag, or first in a page
 * without one. Pure.
 *
 * @param string $content the page
 * @param string $html    pg_course_notice_html()
 * @return string
 */
function pg_course_notice_insert($content, $html)
{
    $content = (string) $content;

    if ((string) $html === '') {
        return $content;
    }

    if (preg_match('/<body\b[^>]*>/i', $content, $match, PREG_OFFSET_CAPTURE)) {
        $at = $match[0][1] + strlen($match[0][0]);

        return substr($content, 0, $at) . $html . substr($content, $at);
    }

    return $html . $content;
}

/**
 * The address of a page for a redirect, in the language being drawn.
 *
 * @param int $page_id
 * @return string a path from PATH, '' for no page
 */
function _pg_course_page_path($page_id)
{
    $name = (string) get_page_name((int) $page_id);

    if ($name === '') {
        return '';
    }

    $path = PATH . encode_url_path($name);

    if (function_exists('pg_tr_active') && pg_tr_active()) {
        pg_tr_load();
        $path = html_entity_decode(pg_tr_rewrite_url($path, pg_tr_prefix(pg_tr_language()), true), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    return $path;
}

/**
 * The gate in the page's access path (get_page.php, after the access
 * switch has let the member in): a closed lesson sends the member to its
 * course's classroom page with a notice that names the day it opens or the
 * lesson to finish first; without a classroom page it answers 403 with the
 * notice. A member given the folder by hand gets the first-open row here,
 * the day their days count from.
 *
 * @param int    $page_id
 * @param int    $folder_id
 * @param string $access_type the folder's access control type
 * @return void
 */
function pg_course_gate($page_id, $folder_id, $access_type)
{
    if (((string) $access_type !== 'private') || !USER_LOGGED_IN || !pg_course_ready() || !pg_course_switch_on()) {
        return;
    }

    $lesson = pg_course_lesson_for_page($page_id);

    if ($lesson === null) {
        return;
    }

    $course = $lesson['course'];

    // A lesson whose page left the course's folder gates nothing; the
    // Courses screen names it.
    if (!pg_course_lesson_in_folder($folder_id, $course['folder_id'])) {
        return;
    }

    $user_id = (int) USER_ID;
    $can_edit = (bool) check_edit_access($folder_id);
    $progress = pg_course_member_progress($user_id, $course['id']);
    $enrolment_day = pg_course_member_enrolment_day($user_id, $course, $progress);

    // The folder was given by hand (no counting order): the first open is
    // the day the member's days count from.
    if (!$can_edit && ($enrolment_day === '') && pg_course_record_first_open($user_id, $course['id'], true)) {
        $enrolment_day = date('Y-m-d');
    }

    $lessons = pg_course_lessons($course['id']);
    $answer = pg_course_gate_answer(true, $access_type, $lesson, $lessons, $progress['done_ids'], $enrolment_day, date('Y-m-d'), $can_edit);

    if (!$answer['closed']) {
        return;
    }

    $notice = pg_course_gate_notice($answer, $lesson, $lessons);
    $path = ($answer['classroom_page_id'] > 0) ? _pg_course_page_path($answer['classroom_page_id']) : '';

    if ($path === '') {
        output_error(h($notice), 403);
    }

    $_SESSION['software']['course_notice'] = array('page_id' => (int) $answer['classroom_page_id'], 'text' => $notice);

    header('Location: ' . URL_SCHEME . HOSTNAME . $path);
    exit();
}

/**
 * The classroom page with the notice a closed lesson left for it: taken
 * once, on that page only. The page is then drawn for this member alone.
 *
 * @param string $content the page
 * @param int    $page_id the page being drawn
 * @return string
 */
function pg_course_notice_inject($content, $page_id)
{
    $notice = $_SESSION['software']['course_notice'] ?? null;

    if (!is_array($notice) || ((int) ($notice['page_id'] ?? 0) !== (int) $page_id)) {
        return $content;
    }

    unset($_SESSION['software']['course_notice']);

    if (function_exists('pg_page_cache_personal')) {
        pg_page_cache_personal('course_notice');
    }

    return pg_course_notice_insert($content, pg_course_notice_html($notice['text'] ?? ''));
}
