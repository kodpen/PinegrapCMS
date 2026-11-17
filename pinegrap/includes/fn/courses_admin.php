<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Courses screen's side of courses (view_courses.php, edit_course.php):
 * who may open it, a course's input read and checked, its save with its
 * lessons, the problems a course is named with (a folder that is not
 * private, a lesson whose page left the folder), the learners list with
 * its CSV file, and a member's progress reset.
 *
 * The rules of an open lesson and the progress writers are in
 * includes/fn/courses.php; this file only reads and edits the course rows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The longest number of days a lesson may wait after enrolment (ten years).
const PG_COURSE_OPEN_DAYS_MAX = 3650;

// The most lessons one course holds.
const PG_COURSE_LESSONS_MAX = 500;

/**
 * Whether a panel user may open the Courses screen: managers and above,
 * who already manage folder access and members.
 *
 * @param array $user validate_user()
 * @return bool
 */
function pg_course_staff_allowed(array $user)
{
    return isset($user['role']) && ((int) $user['role'] < 3);
}

/**
 * Reads a course and its lessons from a posted form and checks them. Pure.
 *
 * Lessons come as parallel lists (lesson_id[], lesson_page[],
 * lesson_days[], lesson_quiz[], lesson_score[]) in the order shown; a row
 * without a page is skipped. A quiz lesson needs a score from 1 to 100; a
 * lesson without a quiz keeps 0.
 *
 * @param array $post
 * @return array course (name, folder_id, classroom_page_id, in_order,
 *               certificate), lessons (id, page_id, sort_order, open_days,
 *               quiz_page_id, quiz_pass_score), errors (field => message)
 */
function pg_course_input(array $post)
{
    $name = trim(preg_replace('/\s+/u', ' ', (string) ($post['name'] ?? '')));
    $course = array(
        'name'              => mb_substr($name, 0, 255),
        'folder_id'         => max(0, (int) ($post['folder_id'] ?? 0)),
        'classroom_page_id' => max(0, (int) ($post['classroom_page_id'] ?? 0)),
        'in_order'          => empty($post['in_order']) ? 0 : 1,
        'certificate'       => empty($post['certificate']) ? 0 : 1,
    );
    $errors = array();

    if ($course['name'] === '') {
        $errors['name'] = lang('Give the course a name.');
    }

    if ($course['folder_id'] === 0) {
        $errors['folder_id'] = lang('Choose the private folder the course\'s product opens.');
    }

    $pages = array_values((array) ($post['lesson_page'] ?? array()));
    $ids = array_values((array) ($post['lesson_id'] ?? array()));
    $days = array_values((array) ($post['lesson_days'] ?? array()));
    $quizzes = array_values((array) ($post['lesson_quiz'] ?? array()));
    $scores = array_values((array) ($post['lesson_score'] ?? array()));
    $lessons = array();
    $seen = array();

    foreach ($pages as $i => $page) {
        $page_id = (int) $page;

        if ($page_id <= 0) {
            continue;
        }

        if (isset($seen[$page_id])) {
            $errors['lessons'] = lang('A page can be one lesson only.');
            continue;
        }

        $seen[$page_id] = true;
        $open_days = trim((string) ($days[$i] ?? '0'));
        $quiz_page_id = max(0, (int) ($quizzes[$i] ?? 0));
        $score = trim((string) ($scores[$i] ?? ''));

        if (($open_days !== '') && (!ctype_digit($open_days) || ((int) $open_days > PG_COURSE_OPEN_DAYS_MAX))) {
            $errors['lessons'] = lang(array('string' => 'Days after enrolment are a whole number from 0 to {var:1}.', 'vars' => array(PG_COURSE_OPEN_DAYS_MAX)));
        }

        if (($quiz_page_id > 0) && (!ctype_digit($score) || ((int) $score < 1) || ((int) $score > 100))) {
            $errors['lessons'] = lang('A quiz lesson needs a score from 1 to 100.');
        }

        $lessons[] = array(
            'id'              => max(0, (int) ($ids[$i] ?? 0)),
            'page_id'         => $page_id,
            'sort_order'      => count($lessons) + 1,
            'open_days'       => min(PG_COURSE_OPEN_DAYS_MAX, (int) $open_days),
            'quiz_page_id'    => $quiz_page_id,
            'quiz_pass_score' => ($quiz_page_id > 0) ? min(100, max(0, (int) $score)) : 0,
        );
    }

    if (count($lessons) > PG_COURSE_LESSONS_MAX) {
        $errors['lessons'] = lang(array('string' => 'A course holds at most {var:1} lessons.', 'vars' => array(PG_COURSE_LESSONS_MAX)));
    }

    return array('course' => $course, 'lessons' => $lessons, 'errors' => $errors);
}

/**
 * The problems a course is named with on the Courses screen. Pure.
 *
 * @param array  $course       a course row (folder_id, classroom_page_id)
 * @param string $folder_access the folder's access type, '' for a folder that is gone
 * @param array  $lessons      the course's lessons (id, page_id)
 * @param array  $page_folders page_id => its folder id, for the lessons' and the classroom's pages
 * @return array list of code ('folder_gone', 'folder_not_private', 'no_lessons',
 *               'no_classroom', 'classroom_outside', 'lesson_outside') and page_id
 */
function pg_course_problems(array $course, $folder_access, array $lessons, array $page_folders)
{
    $folder_id = (int) ($course['folder_id'] ?? 0);
    $problems = array();

    if ((string) $folder_access === '') {
        $problems[] = array('code' => 'folder_gone', 'page_id' => 0);
    } elseif ((string) $folder_access !== 'private') {
        $problems[] = array('code' => 'folder_not_private', 'page_id' => 0);
    }

    if (!$lessons) {
        $problems[] = array('code' => 'no_lessons', 'page_id' => 0);
    }

    $classroom = (int) ($course['classroom_page_id'] ?? 0);

    if ($classroom === 0) {
        $problems[] = array('code' => 'no_classroom', 'page_id' => 0);
    } elseif (!pg_course_lesson_in_folder($page_folders[$classroom] ?? 0, $folder_id)) {
        $problems[] = array('code' => 'classroom_outside', 'page_id' => $classroom);
    }

    foreach ($lessons as $lesson) {
        $page_id = (int) $lesson['page_id'];

        if (!pg_course_lesson_in_folder($page_folders[$page_id] ?? 0, $folder_id)) {
            $problems[] = array('code' => 'lesson_outside', 'page_id' => $page_id);
        }
    }

    return $problems;
}

/**
 * A problem said in a sentence.
 *
 * @param array  $problem   from pg_course_problems()
 * @param string $page_name the page it names, '' for none
 * @return string
 */
function pg_course_problem_text(array $problem, $page_name)
{
    $page_name = ((string) $page_name !== '') ? (string) $page_name : lang('a deleted page');

    switch ($problem['code']) {
        case 'folder_gone':
            return lang('Its folder was deleted: no lesson is closed.');
        case 'folder_not_private':
            return lang('Its folder is not private: no lesson is closed.');
        case 'no_lessons':
            return lang('It has no lessons yet.');
        case 'no_classroom':
            return lang('It has no classroom page: a closed lesson shows its notice on a page of its own.');
        case 'classroom_outside':
            return lang(array('string' => 'The classroom page "{var:1}" is not in the course\'s folder.', 'vars' => array($page_name)));
        case 'lesson_outside':
            return lang(array('string' => 'The lesson "{var:1}" left the course\'s folder: it is not closed.', 'vars' => array($page_name)));
    }

    return '';
}

/**
 * Whether a problem keeps the course from closing any lesson (rather than
 * only one lesson, or nothing). Pure.
 *
 * @param array $problem
 * @return bool
 */
function pg_course_problem_blocks(array $problem)
{
    return in_array($problem['code'], array('folder_gone', 'folder_not_private'), true);
}

/**
 * The folders a course can sit on: every folder whose access, its own or
 * inherited, is private, by name.
 *
 * @return array folder_id => folder name
 */
function pg_course_folder_options()
{
    $out = array();

    foreach ((array) db_items("SELECT folder_id, folder_name FROM folder ORDER BY folder_name ASC") as $row) {
        if (get_access_control_type($row['folder_id']) === 'private') {
            $out[(int) $row['folder_id']] = (string) $row['folder_name'];
        }
    }

    return $out;
}

/**
 * The pages a course's lessons are chosen from: the pages right in the
 * private folders, with their folder.
 *
 * @param array $folder_ids
 * @return array rows: page_id, folder_id, name (title, else page name)
 */
function pg_course_folder_pages(array $folder_ids)
{
    $folder_ids = array_values(array_filter(array_map('intval', $folder_ids)));

    if (!$folder_ids) {
        return array();
    }

    $rows = db_items(
        "SELECT page_id, page_folder AS folder_id, page_name, page_title
        FROM page
        WHERE page_folder IN (" . implode(',', $folder_ids) . ")
        ORDER BY page_name ASC");

    $out = array();

    foreach ((array) $rows as $row) {
        $title = trim((string) $row['page_title']);
        $out[] = array(
            'page_id'   => (int) $row['page_id'],
            'folder_id' => (int) $row['folder_id'],
            'name'      => ($title !== '') ? $title : (string) $row['page_name'],
        );
    }

    return $out;
}

/**
 * The quiz form pages a lesson can ask to pass: pages whose custom form has
 * quiz on.
 *
 * @return array page_id => page name
 */
function pg_course_quiz_pages()
{
    if (!pg_schema_has('custom_form_pages', 'quiz')) {
        return array();
    }

    $out = array();
    $rows = db_items(
        "SELECT page.page_id, page.page_name
        FROM custom_form_pages
        INNER JOIN page ON page.page_id = custom_form_pages.page_id
        WHERE custom_form_pages.quiz = '1'
        ORDER BY page.page_name ASC");

    foreach ((array) $rows as $row) {
        $out[(int) $row['page_id']] = (string) $row['page_name'];
    }

    return $out;
}

/**
 * The folder each page sits in.
 *
 * @param array $page_ids
 * @return array page_id => folder id (a missing page is left out)
 */
function pg_course_page_folders(array $page_ids)
{
    $page_ids = array_values(array_unique(array_filter(array_map('intval', $page_ids))));

    if (!$page_ids) {
        return array();
    }

    $out = array();

    foreach ((array) db_items("SELECT page_id, page_folder FROM page WHERE page_id IN (" . implode(',', $page_ids) . ")") as $row) {
        $out[(int) $row['page_id']] = (int) $row['page_folder'];
    }

    return $out;
}

/**
 * A course's problems read from the database.
 *
 * @param array $course  a course row
 * @param array $lessons its lessons
 * @return array from pg_course_problems()
 */
function pg_course_read_problems(array $course, array $lessons)
{
    $folder_id = (int) $course['folder_id'];
    $exists = ($folder_id > 0) && (bool) db_value("SELECT COUNT(*) FROM folder WHERE folder_id = '" . $folder_id . "'");
    $page_ids = array_column($lessons, 'page_id');
    $page_ids[] = (int) $course['classroom_page_id'];

    return pg_course_problems($course, $exists ? (string) get_access_control_type($folder_id) : '', $lessons, pg_course_page_folders($page_ids));
}

/**
 * Every course with its figures and problems, for the Courses list.
 *
 * @return array rows: the course's columns, folder_name, lessons, learners,
 *               completions, problems
 */
function pg_course_list()
{
    if (!pg_course_ready()) {
        return array();
    }

    $courses = db_items(
        "SELECT course.id, course.name, course.folder_id, course.classroom_page_id, course.in_order, course.certificate,
            course.updated_at, folder.folder_name
        FROM course
        LEFT JOIN folder ON folder.folder_id = course.folder_id
        ORDER BY course.name ASC, course.id ASC");

    $figures = array();

    foreach ((array) db_items(
        "SELECT course_id, COUNT(DISTINCT user_id) AS learners, SUM(kind = '" . PG_COURSE_KIND_COMPLETE . "') AS completions
        FROM course_progress
        GROUP BY course_id") as $row) {
        $figures[(int) $row['course_id']] = $row;
    }

    $out = array();

    foreach ((array) $courses as $course) {
        $lessons = pg_course_lessons($course['id']);
        $course['lessons'] = count($lessons);
        $course['learners'] = pg_course_member_count((int) $course['folder_id'], (int) $course['id']);
        $course['completions'] = (int) ($figures[(int) $course['id']]['completions'] ?? 0);
        $course['problems'] = pg_course_read_problems($course, $lessons);
        $out[] = $course;
    }

    return $out;
}

/**
 * The members a course's learners list shows: every member given the
 * folder (private access, an editor's rights left out) and every member
 * with progress in the course, whose access may have ended.
 *
 * @param int $folder_id
 * @param int $course_id
 * @return array user ids
 */
function pg_course_member_ids($folder_id, $course_id)
{
    $ids = array();

    foreach ((array) db_items("SELECT aclfolder_user FROM aclfolder WHERE (aclfolder_folder = '" . (int) $folder_id . "') AND (aclfolder_rights = '1')") as $row) {
        $ids[(int) $row['aclfolder_user']] = true;
    }

    foreach ((array) db_items("SELECT DISTINCT user_id FROM course_progress WHERE course_id = '" . (int) $course_id . "'") as $row) {
        $ids[(int) $row['user_id']] = true;
    }

    unset($ids[0]);

    return array_keys($ids);
}

/**
 * How many learners a course has.
 *
 * @param int $folder_id
 * @param int $course_id
 * @return int
 */
function pg_course_member_count($folder_id, $course_id)
{
    return count(pg_course_member_ids($folder_id, $course_id));
}

/**
 * One learner's row from what was read for the list. Pure.
 *
 * @param array $member         user_id, username, email, expiration_date ('' for none)
 * @param array $progress       done, first_open_at, completed_at, last_at
 * @param int   $first_order_at the first counting order's time, 0 for none
 * @param int   $lesson_count
 * @return array user_id, name, email, enrolled (Y-m-d or ''), done, lessons,
 *               last_at, completed_at
 */
function pg_course_learner_row(array $member, array $progress, $first_order_at, $lesson_count)
{
    $orders = ((int) $first_order_at > 0) ? array(array('status' => 'complete', 'order_date' => (int) $first_order_at)) : array();

    return array(
        'user_id'      => (int) $member['user_id'],
        'name'         => (string) ($member['username'] ?? ''),
        'email'        => (string) ($member['email'] ?? ''),
        'enrolled'     => pg_course_enrolment_day($orders, (int) ($progress['first_open_at'] ?? 0)),
        'done'         => min((int) $lesson_count, (int) ($progress['done'] ?? 0)),
        'lessons'      => (int) $lesson_count,
        'last_at'      => (int) ($progress['last_at'] ?? 0),
        'completed_at' => (int) ($progress['completed_at'] ?? 0),
    );
}

/**
 * A course's learners.
 *
 * @param array $course a course row
 * @return array rows from pg_course_learner_row()
 */
function pg_course_learners(array $course)
{
    $course_id = (int) $course['id'];
    $folder_id = (int) $course['folder_id'];
    $ids = pg_course_member_ids($folder_id, $course_id);

    if (!$ids) {
        return array();
    }

    $in = implode(',', array_map('intval', $ids));
    $progress = array();

    foreach ((array) db_items(
        "SELECT user_id,
            SUM(kind = '" . PG_COURSE_KIND_DONE . "') AS done,
            MAX(IF(kind = '" . PG_COURSE_KIND_FIRST_OPEN . "', created_at, 0)) AS first_open_at,
            MAX(IF(kind = '" . PG_COURSE_KIND_COMPLETE . "', created_at, 0)) AS completed_at,
            MAX(created_at) AS last_at
        FROM course_progress
        WHERE course_id = '" . $course_id . "'
        GROUP BY user_id") as $row) {
        $progress[(int) $row['user_id']] = $row;
    }

    // The first counting order of each member that opens the folder: the
    // enrolment day, read the way pg_course_member_orders() reads it.
    $first_orders = array();

    if ($folder_id > 0) {
        foreach ((array) db_items(
            "SELECT orders.user_id, MIN(orders.order_date) AS first_at
            FROM orders
            INNER JOIN order_items ON order_items.order_id = orders.id
            INNER JOIN products ON products.id = order_items.product_id
            WHERE
                (orders.user_id IN (" . $in . "))
                AND (orders.status IN ('complete', 'exported'))
                AND (products.grant_private_access = '1')
                AND (products.private_folder = '" . $folder_id . "')" . pg_order_item_active_sql() . "
            GROUP BY orders.user_id") as $row) {
            $first_orders[(int) $row['user_id']] = (int) $row['first_at'];
        }
    }

    $lesson_count = count(pg_course_lessons($course_id));
    $out = array();

    foreach ((array) db_items("SELECT user_id, user_username AS username, user_email AS email FROM user WHERE user_id IN (" . $in . ") ORDER BY user_username ASC") as $member) {
        $user_id = (int) $member['user_id'];
        $out[] = pg_course_learner_row($member, $progress[$user_id] ?? array(), $first_orders[$user_id] ?? 0, $lesson_count);
    }

    return $out;
}

/**
 * The learners list as CSV rows, a heading row first; the caller writes
 * each cell through csv_cell(). Pure.
 *
 * @param array $rows from pg_course_learners()
 * @return array lists of cell values
 */
function pg_course_learners_csv_rows(array $rows)
{
    $out = array(array(lang('Member'), lang('E-mail'), lang('Enrolled'), lang('Lessons done'), lang('Lessons'), lang('Last activity'), lang('Completed')));

    foreach ($rows as $row) {
        $out[] = array(
            $row['name'],
            $row['email'],
            $row['enrolled'],
            (string) $row['done'],
            (string) $row['lessons'],
            ($row['last_at'] > 0) ? pg_csv_export_time($row['last_at']) : '',
            ($row['completed_at'] > 0) ? date('Y-m-d', $row['completed_at']) : '',
        );
    }

    return $out;
}

/**
 * Saves a course and its lessons. A lesson left out is deleted with its done
 * rows; a page that is another course's lesson is refused and names it.
 *
 * @param int   $course_id 0 for a new course
 * @param array $input     pg_course_input() without errors
 * @param int   $user_id   who saves
 * @return array id (the course), errors (list of messages)
 */
function pg_course_save($course_id, array $input, $user_id)
{
    $course_id = (int) $course_id;
    $course = $input['course'];
    $errors = array();

    foreach ($input['lessons'] as $lesson) {
        $taken = pg_course_page_taken($lesson['page_id']);

        if (($taken !== null) && ((int) $taken['id'] !== $course_id)) {
            $errors[] = lang(array('string' => 'The page "{var:1}" is already a lesson of the course "{var:2}".', 'vars' => array(_pg_course_lesson_name($lesson['page_id']), $taken['name'])));
        }
    }

    if ($errors) {
        return array('id' => $course_id, 'errors' => $errors);
    }

    $values = array($course['name'], (string) $course['folder_id'], (string) $course['classroom_page_id'], (string) $course['in_order'], (string) $course['certificate'], (string) time(), (string) (int) $user_id);

    if ($course_id > 0) {
        $values[] = (string) $course_id;
        $ok = db_q("UPDATE course SET name = ?, folder_id = ?, classroom_page_id = ?, in_order = ?, certificate = ?, updated_at = ?, updated_by = ? WHERE id = ?", $values);
    } else {
        $ok = db_q("INSERT INTO course (name, folder_id, classroom_page_id, in_order, certificate, created_at, created_by, updated_at, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array_merge($values, array($values[5], $values[6])));
        $course_id = $ok ? (int) mysqli_insert_id(db::$con) : 0;
    }

    if (!$ok || ($course_id <= 0)) {
        return array('id' => $course_id, 'errors' => array(lang('The course could not be saved.')));
    }

    // A lesson left out, or one whose page was changed (a different lesson
    // now, so its done rows go), is deleted first: the page key is unique,
    // so a page moved to a new row must be free before that row is written.
    $posted = array();

    foreach ($input['lessons'] as $lesson) {
        if ($lesson['id'] > 0) {
            $posted[$lesson['id']] = $lesson['page_id'];
        }
    }

    $kept = array();

    foreach (pg_course_lessons($course_id) as $old) {
        $old_id = (int) $old['id'];

        if (isset($posted[$old_id]) && ($posted[$old_id] === (int) $old['page_id'])) {
            $kept[$old_id] = true;
        } else {
            pg_course_lesson_delete($old_id);
        }
    }

    foreach ($input['lessons'] as $lesson) {
        $fields = array((string) $lesson['page_id'], (string) $lesson['sort_order'], (string) $lesson['open_days'], (string) $lesson['quiz_page_id'], (string) $lesson['quiz_pass_score']);

        if (isset($kept[$lesson['id']])) {
            db_q("UPDATE course_lesson SET page_id = ?, sort_order = ?, open_days = ?, quiz_page_id = ?, quiz_pass_score = ? WHERE id = ?", array_merge($fields, array((string) $lesson['id'])));
        } else {
            db_q("INSERT INTO course_lesson (page_id, sort_order, open_days, quiz_page_id, quiz_pass_score, course_id) VALUES (?, ?, ?, ?, ?, ?)", array_merge($fields, array((string) $course_id)));
        }
    }

    return array('id' => $course_id, 'errors' => array());
}

/**
 * Deletes a course with its lessons and every member's progress in it.
 *
 * @param int $course_id
 * @return bool
 */
function pg_course_delete($course_id)
{
    $course_id = (string) (int) $course_id;

    return db_q("DELETE FROM course_progress WHERE course_id = ?", array($course_id))
        && db_q("DELETE FROM course_lesson WHERE course_id = ?", array($course_id))
        && db_q("DELETE FROM course WHERE id = ?", array($course_id));
}

/**
 * Resets a member's progress in a course: the lessons done, the first open
 * and the completion, so the certificate's code no longer checks.
 *
 * @param int $course_id
 * @param int $user_id
 * @return bool
 */
function pg_course_reset_progress($course_id, $user_id)
{
    if (((int) $course_id <= 0) || ((int) $user_id <= 0)) {
        return false;
    }

    return (bool) db_q("DELETE FROM course_progress WHERE (course_id = ?) AND (user_id = ?)", array((string) (int) $course_id, (string) (int) $user_id));
}
