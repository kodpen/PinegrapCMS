<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The learner's course blocks on the published page: "Mark as done",
 * "Course progress", "Course lessons" and "My courses"
 * (assets/js/style_designer_blocks_course.js). Each block carries
 * data-pg-course="<kind>", no script and is hidden in its markup; a page
 * holding one gets assets/js/pg_course.js and its settings
 * (pg_course_blocks_inject()) while the "Courses" switch is on. The script
 * asks course_action.php for the member's view and fills the blocks, so the
 * stored page is the same for everyone and may be cached. The press of
 * "Mark as done" goes to the same door; the checks it passes are decided by
 * the pure pg_course_press_refusal(). The editor's Preview loads the script
 * from its twin row in _SD_PAGE_SCRIPTS (assets/js/style_designer.src.js).
 * Loaded by get_page_content.php only when the mark is there, and by
 * course_action.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Requests an address may send to course_action.php: views (a page with
// the blocks asks once per load) and presses of "Mark as done".
define('PG_COURSE_VIEW_LIMIT', 120);
define('PG_COURSE_VIEW_WINDOW', 600);
define('PG_COURSE_PRESS_LIMIT', 30);
define('PG_COURSE_PRESS_WINDOW', 600);

/**
 * The block's mark as an attribute of its own (not data-pg-course-item and
 * not text): the Preview's row in _SD_PAGE_SCRIPTS uses the same expression.
 * Pure.
 *
 * @return string
 */
function pg_course_blocks_pattern()
{
    return '/\sdata-pg-course(?=[\s=>\/])/i';
}

/**
 * Whether a page's HTML holds a course block. Pure.
 *
 * @param string $content page HTML
 * @return bool
 */
function pg_course_blocks_on_page($content)
{
    return stripos((string) $content, 'data-pg-course') !== false
        && (bool) preg_match(pg_course_blocks_pattern(), (string) $content);
}

/**
 * The script's texts in the page's language, by their English key;
 * pg_course.js falls back to the key. %1 and %2 stand for the numbers or
 * the day the script puts in.
 *
 * @return array
 */
function pg_course_blocks_labels()
{
    return array(
        'Mark as done' => lang('Mark as done'),
        'Done'         => lang('Done'),
        'Lesson done.' => lang('Lesson done.'),
        'Open'         => lang('Open'),
        'Locked'       => lang('Locked'),
        'Opens on'     => lang(array('string' => 'Opens on {var:1}', 'vars' => '%1')),
        'Continue'     => lang('Continue'),
        'Progress'     => lang(array('string' => '{var:1} of {var:2} lessons done', 'vars' => array('%1', '%2'))),
        'Complete'     => lang('Course complete'),
        'Quiz'         => lang(array('string' => 'Pass the quiz with {var:1}% or more to finish this lesson.', 'vars' => '%1')),
        'Closed'       => lang('This lesson is not open yet.'),
        'Rate'         => lang('Too many tries. Please wait a moment.'),
        'Failed'       => lang('Something went wrong. Please try again.'),
        'None'         => lang('You have no courses yet.'),
    );
}

/**
 * The settings the script reads: the door, the page it is on and the texts.
 * Pure.
 *
 * @param string $door    course_action.php's address
 * @param int    $page_id the page being drawn
 * @param array  $labels  pg_course_blocks_labels()
 * @return array door, page, labels
 */
function pg_course_blocks_settings($door, $page_id, array $labels)
{
    return array('door' => (string) $door, 'page' => (int) $page_id, 'labels' => $labels);
}

/**
 * The script and its settings before the page's last </body>, once, for a
 * page holding a block (pg_course_blocks_on_page()). Nothing while the
 * "Courses" switch is off or the tables are missing: the blocks stay
 * hidden. Asked once every shared component and system widget is drawn
 * in, so the file follows the block wherever it was placed.
 *
 * @param string      $content  page HTML
 * @param int         $page_id  the page being drawn
 * @param array|null  $settings pg_course_blocks_settings(); null reads the
 *                              switch and builds them for this site
 * @param string|null $src      the script's address; null builds it from
 *                              the software directory and the file's time
 * @return string
 */
function pg_course_blocks_inject($content, $page_id, $settings = null, $src = null)
{
    if (!pg_course_blocks_on_page($content)) return $content;
    if (strpos($content, '/assets/js/pg_course.js') !== false) return $content;

    if ($settings === null) {
        if (!pg_course_ready() || !pg_course_switch_on()) return $content;

        $settings = pg_course_blocks_settings(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/course_action.php', $page_id, pg_course_blocks_labels());
    }

    if ($src === null) {
        $src = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pg_course.js?v='
             . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_course.js');
    }
    $file = '<script>window.pgCourseConfig = ' . encode_json($settings) . ';</script>' . "\n"
          . '<script src="' . h($src) . '" defer></script>' . "\n";

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $file, $body, 0) : $content . $file;
}

/**
 * Whether a lesson's quiz lets it be done: no quiz (a needed score of 0),
 * or a completed submission scored at or above the needed score. Pure.
 *
 * @param int|null $best the member's best score of a completed submission,
 *                       null for none
 * @param int      $need the lesson's needed score (quiz_pass_score)
 * @return bool
 */
function pg_course_quiz_passed($best, $need)
{
    if ((int) $need <= 0) {
        return true;
    }

    return ($best !== null) && ((int) $best >= (int) $need);
}

/**
 * Why a press of "Mark as done" is refused, or null when it may write.
 * Checked in this order: the method, the origin, the switch and tables,
 * the rate, a signed-in member, the token, the lesson (a lesson whose page
 * is in its course's folder), the folder access, the lesson being open,
 * the quiz. Pure.
 *
 * @param array $c method, same_origin, on, throttled, signed_in, token_ok,
 *                 lesson, access, open, quiz_ok (booleans but the method)
 * @return array|null array(status, error)
 */
function pg_course_press_refusal(array $c)
{
    $checks = array(
        array(strtoupper((string) ($c['method'] ?? '')) === 'POST', 405, 'method'),
        array(!empty($c['same_origin']), 403, 'origin'),
        array(!empty($c['on']), 404, 'off'),
        array(empty($c['throttled']), 429, 'rate'),
        array(!empty($c['signed_in']), 401, 'sign_in'),
        array(!empty($c['token_ok']), 403, 'token'),
        array(!empty($c['lesson']), 404, 'lesson'),
        array(!empty($c['access']), 403, 'access'),
        array(!empty($c['open']), 403, 'closed'),
        array(!empty($c['quiz_ok']), 403, 'quiz'),
    );

    foreach ($checks as $check) {
        if (!$check[0]) {
            return array($check[1], $check[2]);
        }
    }

    return null;
}

/**
 * A press's answer once it was let through: the lesson is done whether
 * this press wrote the row or an earlier one did, so a second press (or a
 * race) gives the same answer and writes nothing. Pure.
 *
 * @param array $marked pg_course_mark_done()
 * @param array $view   pg_course_blocks_view() after the write
 * @return array
 */
function pg_course_press_answer(array $marked, array $view)
{
    return array('done' => 1, 'completed' => !empty($marked['completed']) ? 1 : 0, 'view' => $view);
}

/**
 * The member's view of a course for the blocks. A member with the folder
 * gets every lesson's state (done, open, opens on a day, locked behind the
 * lesson before), the count, the next lesson to continue with and, on a
 * lesson page, that lesson. Anyone else gets the lessons' names only, and
 * neither the count nor the lesson. Pure.
 *
 * @param array  $course        a course row with url (its classroom page's path)
 * @param array  $lessons       the course's lessons with name and url
 * @param bool   $member        a signed-in member with the folder
 * @param array  $done_ids      the lessons the member has done
 * @param string $enrolment_day Y-m-d, '' for none yet
 * @param string $today         Y-m-d
 * @param bool   $can_edit      whether the member may edit the folder
 * @param int    $page_id       the page the blocks are on
 * @return array
 */
function pg_course_blocks_view(array $course, array $lessons, $member, array $done_ids, $enrolment_day, $today, $can_edit, $page_id)
{
    $done_ids = array_map('intval', $done_ids);
    $view = array('name' => (string) $course['name'], 'url' => (string) ($course['url'] ?? ''), 'member' => $member ? 1 : 0, 'lessons' => array());

    foreach ($lessons as $lesson) {
        $row = array('name' => (string) $lesson['name']);

        if ($member) {
            $row['url'] = (string) $lesson['url'];
            $row['state'] = 'open';
            $row['opens_on'] = '';

            if (in_array((int) $lesson['id'], $done_ids, true)) {
                $row['state'] = 'done';
            } else {
                $state = pg_course_lesson_state($lessons, (int) $lesson['id'], $done_ids, $enrolment_day, !empty($course['in_order']), $today, $can_edit);

                if (!$state['open']) {
                    $row['state'] = ($state['reason'] === 'days') ? 'opens' : 'locked';
                    $row['opens_on'] = (string) $state['opens_on'];
                }
            }
        }

        $view['lessons'][] = $row;
    }

    if (!$member) {
        return $view;
    }

    $view['total'] = count($lessons);
    $view['done'] = 0;
    $view['next'] = '';
    $view['lesson'] = null;

    foreach ($lessons as $i => $lesson) {
        $state = $view['lessons'][$i]['state'];

        if ($state === 'done') {
            $view['done']++;
        } elseif (($state === 'open') && ($view['next'] === '')) {
            $view['next'] = $view['lessons'][$i]['url'];
        }

        if ((int) $lesson['page_id'] === (int) $page_id) {
            $view['lesson'] = array('id' => (int) $lesson['id'], 'done' => ($state === 'done') ? 1 : 0, 'need' => (int) $lesson['quiz_pass_score']);
        }
    }

    $view['complete'] = ($view['total'] > 0) && ($view['done'] === $view['total']) ? 1 : 0;

    return $view;
}

/**
 * The course a page belongs to: the one it is a lesson of, else the one
 * whose classroom page it is.
 *
 * @param int $page_id
 * @return array|null the course row
 */
function pg_course_for_page($page_id)
{
    $lesson = pg_course_lesson_for_page($page_id);

    if ($lesson !== null) {
        return $lesson['course'];
    }

    if (((int) $page_id <= 0) || !pg_course_ready()) {
        return null;
    }

    $id = db_value("SELECT id FROM course WHERE classroom_page_id = '" . (int) $page_id . "' ORDER BY id ASC LIMIT 1");

    return $id ? pg_course_get((int) $id) : null;
}

/**
 * Whether the signed-in member may see a folder (access not expired).
 *
 * @param int $folder_id
 * @return bool
 */
function pg_course_member_has_folder($folder_id)
{
    if (!USER_LOGGED_IN || ((int) $folder_id <= 0)) {
        return false;
    }

    $check = check_private_access((int) $folder_id);

    return !empty($check['access']) && empty($check['expired']);
}

/**
 * The signed-in member's view of a course (pg_course_blocks_view()), read
 * from the tables.
 *
 * @param array $course  a course row
 * @param int   $page_id the page the blocks are on
 * @return array
 */
function pg_course_member_view(array $course, $page_id)
{
    $lessons = array();

    foreach (pg_course_lessons($course['id']) as $lesson) {
        $lesson['name'] = _pg_course_lesson_name($lesson['page_id']);
        $lesson['url'] = _pg_course_page_path($lesson['page_id']);
        $lessons[] = $lesson;
    }

    $course['url'] = ((int) $course['classroom_page_id'] > 0) ? _pg_course_page_path($course['classroom_page_id']) : '';
    $member = pg_course_member_has_folder($course['folder_id']);
    $done_ids = array();
    $enrolment_day = '';
    $can_edit = false;

    if ($member) {
        $progress = pg_course_member_progress((int) USER_ID, $course['id']);
        $done_ids = $progress['done_ids'];
        $enrolment_day = pg_course_member_enrolment_day((int) USER_ID, $course, $progress);
        $can_edit = (bool) check_edit_access($course['folder_id']);
    }

    return pg_course_blocks_view($course, $lessons, $member, $done_ids, $enrolment_day, date('Y-m-d'), $can_edit, $page_id);
}

/**
 * The courses whose folder the signed-in member has, each with its count,
 * for "My courses".
 *
 * @return array rows: name, url, total, done, complete
 */
function pg_course_member_courses()
{
    if (!USER_LOGGED_IN || !pg_course_ready()) {
        return array();
    }

    $out = array();
    $rows = db_items("SELECT id, name, folder_id, classroom_page_id, in_order, certificate FROM course ORDER BY name ASC, id ASC");

    foreach (is_array($rows) ? $rows : array() as $course) {
        if (!pg_course_member_has_folder($course['folder_id'])) {
            continue;
        }

        $view = pg_course_member_view($course, 0);
        $out[] = array('name' => $view['name'], 'url' => $view['url'] !== '' ? $view['url'] : $view['next'],
            'total' => $view['total'], 'done' => $view['done'], 'complete' => $view['complete']);
    }

    return $out;
}

/**
 * The member's best score of a completed submission of a quiz form, or
 * null for none.
 *
 * @param int $user_id
 * @param int $quiz_page_id the quiz form's page
 * @return int|null
 */
function pg_course_member_quiz_best($user_id, $quiz_page_id)
{
    if (((int) $user_id <= 0) || ((int) $quiz_page_id <= 0)) {
        return null;
    }

    $best = db_value(
        "SELECT MAX(quiz_score) FROM forms
        WHERE (page_id = '" . (int) $quiz_page_id . "') AND (user_id = '" . (int) $user_id . "') AND (complete = '1')");

    return ($best === null || $best === false || $best === '') ? null : (int) $best;
}

/**
 * Whether an address has sent more views or presses than its window
 * allows; a refusal is logged. False where the firewall's counter is
 * missing.
 *
 * @param string $kind 'view' or 'press'
 * @return bool
 */
function pg_course_throttled($kind)
{
    if (!function_exists('waf_rate_exceeded') || !function_exists('waf_client_ip') || !function_exists('waf_ip_subject')) {
        return false;
    }

    $ip = (string) waf_client_ip();

    if ($ip === '') {
        return false;
    }

    $press = ($kind === 'press');
    $limit = $press ? PG_COURSE_PRESS_LIMIT : PG_COURSE_VIEW_LIMIT;
    $window = $press ? PG_COURSE_PRESS_WINDOW : PG_COURSE_VIEW_WINDOW;

    if (!waf_rate_exceeded(waf_ip_subject($ip), $press ? 'course_press' : 'course_view', $limit, $window)) {
        return false;
    }

    if (function_exists('waf_log_event') && isset(db::$con) && db::$con) {
        waf_log_event('rate', 'rate-course', 'rate', 5, 'course_action.php', $limit . '/' . (int) round($window / 60) . 'min');
    }

    return true;
}
