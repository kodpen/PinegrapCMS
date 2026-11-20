<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The door of the learner's course blocks (includes/fn/course_blocks.php).
 *
 * GET page=<id>: the view the blocks on that page are filled from: the
 * page's course (the one it is a lesson of, or whose classroom page it is)
 * as pg_course_member_view() gives it, and "My courses" for a signed-in
 * member. A signed-in member's answer carries the form token the press
 * sends back: the stored page is the same for everyone and holds none.
 *
 * POST lesson=<id>, token: "Mark as done". Refused, in the
 * order pg_course_press_refusal() checks, for another site's post, the
 * switch off, an address over its rate, a visitor, a wrong token, a lesson
 * whose page left its course's folder, a member without the folder, a
 * lesson not open yet and a quiz lesson without a completed submission at
 * its needed score. A let-through press writes the done row once
 * (pg_course_mark_done()) and answers the view again.
 *
 * While the "Courses" switch is off, or the tables are missing, every
 * request is 404 and nothing is written.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/course_blocks.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
$on = pg_course_ready() && pg_course_switch_on();
$status = 200;
$reply = array();

if ($method === 'GET') {
    $page_id = (isset($_GET['page']) && is_scalar($_GET['page'])) ? (int) $_GET['page'] : 0;

    if (!$on) {
        $status = 404;
        $reply = array('error' => 'off');
    } elseif (pg_course_throttled('view')) {
        $status = 429;
        $reply = array('error' => 'rate');
    } else {
        $course = pg_course_for_page($page_id);
        $reply = array('course' => ($course === null) ? null : pg_course_member_view($course, $page_id));

        if (USER_LOGGED_IN) {
            $reply['mine'] = pg_course_member_courses();
            $reply['token'] = (string) ($_SESSION['software']['token'] ?? '');
        }
    }
} else {
    $lesson_id = (isset($_POST['lesson']) && is_scalar($_POST['lesson'])) ? (int) $_POST['lesson'] : 0;
    $session_token = (string) ($_SESSION['software']['token'] ?? '');
    $posted_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';
    $same_origin = pg_consent_answer_same_origin($_SERVER);
    $throttled = ($method === 'POST') && $same_origin && $on && pg_course_throttled('press');

    $lesson = null;
    $access = false;
    $open = false;
    $quiz_ok = false;

    // The lesson and the member's rights are read only for a signed-in
    // press that came this far.
    if (($method === 'POST') && $same_origin && $on && !$throttled && USER_LOGGED_IN
        && ($session_token !== '') && hash_equals($session_token, $posted_token)) {
        $lesson = pg_course_lesson_for_page((int) db_value("SELECT page_id FROM course_lesson WHERE id = '" . $lesson_id . "'"));

        if (($lesson !== null) && ((int) $lesson['id'] === $lesson_id)
            && pg_course_lesson_in_folder(db_value("SELECT page_folder FROM page WHERE page_id = '" . (int) $lesson['page_id'] . "'"), $lesson['course']['folder_id'])) {
            $course = $lesson['course'];
            $access = pg_course_member_has_folder($course['folder_id']);

            if ($access) {
                $progress = pg_course_member_progress((int) USER_ID, $course['id']);
                $state = pg_course_lesson_state(pg_course_lessons($course['id']), $lesson_id, $progress['done_ids'],
                    pg_course_member_enrolment_day((int) USER_ID, $course, $progress), !empty($course['in_order']), date('Y-m-d'),
                    (bool) check_edit_access($course['folder_id']));
                $open = !empty($state['open']);
                $quiz_ok = pg_course_quiz_passed(pg_course_member_quiz_best((int) USER_ID, $lesson['quiz_page_id']), $lesson['quiz_pass_score']);
            }
        } else {
            $lesson = null;
        }
    }

    $refusal = pg_course_press_refusal(array(
        'method' => $method, 'same_origin' => $same_origin, 'on' => $on, 'throttled' => $throttled,
        'signed_in' => USER_LOGGED_IN, 'token_ok' => ($session_token !== '') && hash_equals($session_token, $posted_token),
        'lesson' => $lesson !== null, 'access' => $access, 'open' => $open, 'quiz_ok' => $quiz_ok,
    ));

    if ($refusal) {
        list($status, $error) = $refusal;
        $reply = array('error' => $error);

        if ($status === 405) {
            header('Allow: GET, POST');
        } elseif ($error === 'quiz') {
            $reply['need'] = (int) $lesson['quiz_pass_score'];
        }
    } else {
        $marked = pg_course_mark_done((int) USER_ID, $lesson['course']['id'], $lesson_id);
        $reply = pg_course_press_answer($marked, pg_course_member_view($lesson['course'], (int) $lesson['page_id']));
    }
}

http_response_code($status);
echo encode_json($reply);
