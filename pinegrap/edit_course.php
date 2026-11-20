<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A course: its name, the private folder a product already opens, its
 * classroom page, "Lessons in order", the certificate and its ordered
 * lessons, each a page of the folder that opens some days after enrolment
 * and may ask a quiz to be passed. Its second view lists the learners with
 * a CSV file and "Reset progress". Saves through includes/fn/courses_admin.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/courses_admin.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_course_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

if (!pg_course_switch_on()) {
    output_error(lang('Courses are switched off. Switch them on in Site Settings, Features.'));
}

$liveform = new liveform('edit_course');
$course_id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
$course = ($course_id > 0) ? pg_course_get($course_id) : null;

if (($course_id > 0) && ($course === null)) {
    output_error(lang('The course was not found.'), 404);
}

$course_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_course.php';
$course_view = (($course !== null) && (($_GET['view'] ?? '') === 'learners')) ? 'learners' : 'course';
$course_input = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    if (($course !== null) && isset($_POST['submit_delete'])) {
        pg_course_delete($course_id);
        log_activity('course "' . $course['name'] . '" was deleted');
        $list_form = new liveform('view_courses');
        $list_form->add_notice(h(lang(array('string' => 'The course "{var:1}" was deleted.', 'vars' => array($course['name'])))));
        go(OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_courses.php');
    }

    if (($course !== null) && isset($_POST['reset_user'])) {
        pg_course_reset_progress($course_id, (int) $_POST['reset_user']);
        log_activity('the progress of user ' . (int) $_POST['reset_user'] . ' in course "' . $course['name'] . '" was reset');
        $liveform->add_notice(h(lang('The member\'s progress was reset.')));
        go($course_self . '?id=' . $course_id . '&view=learners');
    }

    $course_input = pg_course_input($_POST);

    if (!$course_input['errors']) {
        $saved = pg_course_save($course_id, $course_input, (int) $user['id']);
        $course_input['errors'] = $saved['errors'];

        if (!$saved['errors']) {
            log_activity('course "' . $course_input['course']['name'] . '" was ' . (($course_id > 0) ? 'modified' : 'created'));
            $liveform->add_notice(h(lang('The course was saved.')));
            go($course_self . '?id=' . (int) $saved['id']);
        }
    }

    foreach ($course_input['errors'] as $course_error) {
        $liveform->add_error(h($course_error));
    }
}

// What the form shows: the posted values after a refused save, else the
// stored course.
$course_values = ($course_input !== null) ? $course_input['course']
    : (($course !== null) ? $course : array('name' => '', 'folder_id' => 0, 'classroom_page_id' => 0, 'in_order' => 0, 'certificate' => 0));
$course_lessons = ($course_input !== null) ? $course_input['lessons'] : (($course !== null) ? pg_course_lessons($course_id) : array());
$course_learners = ($course !== null) ? pg_course_learners($course) : array();

if (($course_view === 'learners') && (($_GET['csv'] ?? '') === '1')) {
    pg_list_excel_capture(array(2 => 'date', 3 => 'number', 4 => 'number', 5 => 'date', 6 => 'date'), lang('Learners'));
    pg_csv_download_start('course-' . $course_id . '-learners.csv');

    foreach (pg_course_learners_csv_rows($course_learners) as $csv_row) {
        echo '"' . implode('","', array_map('csv_cell', $csv_row)) . '"' . "\r\n";
    }

    exit();
}

$course_title = ($course !== null) ? $course['name'] : lang('Add course');
$course_toolbar = array('grow' => '');

if ($course !== null) {
    $course_toolbar['view'] = '<span class="pg-seg">'
        . '<a class="pg-seg-item' . (($course_view === 'course') ? ' is-active" aria-current="page' : '') . '" href="edit_course.php?id=' . $course_id . '">' . h(lang('Course')) . '</a>'
        . '<a class="pg-seg-item' . (($course_view === 'learners') ? ' is-active" aria-current="page' : '') . '" href="edit_course.php?id=' . $course_id . '&amp;view=learners">' . h(lang('Learners')) . ' <span class="pg-seg-count">' . count($course_learners) . '</span></a>'
        . '</span>';
}

if ($course_view === 'learners') {
    $course_toolbar['search'] = pg_list_table_search_box('edit_course_learners');
    $course_toolbar['secondary'] = array(
        array('label' => lang('Download CSV'), 'url' => 'edit_course.php?id=' . $course_id . '&view=learners&csv=1', 'icon' => 'bi-download'),
        pg_list_excel_action('edit_course.php?id=' . $course_id . '&view=learners&csv=1'),
    );
}

$course_shell_properties = array(
    'title' => $course_title,
    'extra classes' => 'courses pg-measure-edit',
    'icon' => 'page',
    // output_header() prints the heading as markup; the name is staff text.
    'heading' => h($course_title),
    'heading_description' => ($course_view === 'learners')
        ? lang('Every member with the course\'s folder: when they enrolled, how far they are and when they completed.')
        : lang('The private folder the course\'s product opens, and its pages as lessons in their order.'),
    'breadcrumb' => array(
        array('label' => lang('Courses'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_courses.php'),
        array('label' => $course_title),
    ),
);

// The learners view is a list and takes the list width.
if ($course_view === 'learners') {
    $course_shell_properties['extra classes'] = 'courses pg-measure-list';
}

$course_shell = pg_page_shell($course_shell_properties);

// The learners view: a printed list, searched and sorted in the browser.
if ($course_view === 'learners') {
    $learner_rows = array();

    foreach ($course_learners as $learner) {
        $learner_rows[] = array(
            'id' => $learner['user_id'],
            'cells' => array(
                'member' => pg_list_cell('<a href="edit_user.php?id=' . $learner['user_id'] . '">' . h($learner['name']) . '</a>'),
                'email' => pg_list_cell(h($learner['email'])),
                'enrolled' => pg_list_cell(($learner['enrolled'] !== '') ? h(get_absolute_time(array('timestamp' => strtotime($learner['enrolled'] . ' 12:00:00'), 'type' => 'date', 'format' => 'plain_text', 'timezone_type' => 'site'))) : '', 'align-middle', array('data-pg-sort-value' => str_replace('-', '', $learner['enrolled']))),
                'done' => pg_list_cell(h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($learner['done'], $learner['lessons'])))), 'align-middle', array('data-pg-sort-value' => $learner['done'])),
                'last' => ($learner['last_at'] > 0) ? pg_list_table_when_cell($learner['last_at']) : pg_list_cell('<span class="pg-text-meta">' . h(lang('Not started yet')) . '</span>', 'align-middle', array('data-pg-sort-value' => 0)),
                'completed' => pg_list_cell(($learner['completed_at'] > 0) ? '<span class="pg-pill pg-tone-teal">' . h(get_absolute_time(array('timestamp' => $learner['completed_at'], 'type' => 'date', 'format' => 'plain_text'))) . '</span>' : '', 'align-middle', array('data-pg-sort-value' => $learner['completed_at'])),
                'reset' => pg_list_cell(($learner['done'] + $learner['last_at'] > 0)
                    ? '<button type="button" class="pg-button pg-button-ghost pg-button-sm no-submit" data-pg-course-reset="' . $learner['user_id'] . '" data-pg-course-question="' . h(lang(array('string' => 'Reset the progress of {var:1}? The lessons done and the completion are removed, and the member starts again from the first lesson.', 'vars' => array($learner['name'])))) . '">' . h(lang('Reset progress')) . '</button>'
                    : '', 'align-middle text-end'),
            ),
        );
    }

    $learner_columns = array(
            'member' => array('heading' => lang('Member')),
            'email' => array('heading' => lang('E-mail')),
            'enrolled' => array('heading' => lang('Enrolled'), 'kind' => 'date'),
            'done' => array('heading' => lang('Lessons done'), 'kind' => 'number'),
            'last' => array('heading' => lang('Last activity'), 'kind' => 'date'),
            'completed' => array('heading' => lang('Completed'), 'kind' => 'date'),
            'reset' => array('heading' => lang('Progress'), 'kind' => 'actions'),
    );
    $learner_sort_columns = array_map(function ($column) {
        return $column['heading'];
    }, $learner_columns);
    unset($learner_sort_columns['reset']);

    $learner_list = pg_list_table_printed(array(
        'list' => 'edit_course_learners',
        'columns' => $learner_columns,
        'levels' => pg_list_state('edit_course_learners', $learner_sort_columns, array('sort' => 'member', 'order' => 'asc'))['sorts'],
        'rows' => $learner_rows,
    ));

    echo $course_shell . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . pg_screen_toolbar($course_toolbar) . '
            ' . pg_list_table_html($learner_list['source'], $learner_list['page'], lang('Learners')) . '
            <form method="post" action="edit_course.php" id="course_reset_form" hidden>
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $course_id . '" />
                <input type="hidden" name="reset_user" value="" />
            </form>
        </div>
    </div>
</main>
<script>
    // A row\'s Reset progress asks first, then posts the hidden form.
    document.addEventListener(\'click\', function (event) {
        var button = event.target.closest(\'[data-pg-course-reset]\');
        var form = document.getElementById(\'course_reset_form\');

        if (!button || !form || !window.confirm(button.getAttribute(\'data-pg-course-question\'))) {
            return;
        }

        form.elements.reset_user.value = button.getAttribute(\'data-pg-course-reset\');
        form.submit();
    });
</script>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' . output_footer();

    $liveform->remove_form();
    exit();
}

// The course view. Pages are offered from every private folder, each
// option naming its folder; the script shows only the chosen folder's.
$course_folders = pg_course_folder_options();
$course_pages = pg_course_folder_pages(array_keys($course_folders), array_merge(array_column($course_lessons, 'page_id'), array($course_values['classroom_page_id'])));
$course_quizzes = pg_course_quiz_pages();
$course_folder_id = (int) $course_values['folder_id'];

$course_folder_options = '<option value="0">' . h(lang('Choose a folder')) . '</option>';

foreach ($course_folders as $folder_id => $folder_name) {
    $course_folder_options .= '<option value="' . $folder_id . '"' . (($folder_id === $course_folder_id) ? ' selected' : '') . '>' . h($folder_name) . '</option>';
}

$course_page_options = function ($chosen, $none) use ($course_pages, $course_folder_id) {
    $out = '<option value="0">' . h($none) . '</option>';

    foreach ($course_pages as $page) {
        $other = ($page['folder_id'] !== $course_folder_id) && ($page['page_id'] !== (int) $chosen);
        $out .= '<option value="' . $page['page_id'] . '" data-folder="' . $page['folder_id'] . '"'
            . (($page['page_id'] === (int) $chosen) ? ' selected' : '') . ($other ? ' hidden disabled' : '') . '>' . h($page['name']) . '</option>';
    }

    return $out;
};

$course_quiz_options = function ($chosen) use ($course_quizzes) {
    $out = '<option value="0">' . h(lang('No quiz')) . '</option>';

    foreach ($course_quizzes as $page_id => $page_name) {
        $out .= '<option value="' . $page_id . '"' . (($page_id === (int) $chosen) ? ' selected' : '') . '>' . h($page_name) . '</option>';
    }

    return $out;
};

$course_lesson_row = function (array $lesson) use ($course_page_options, $course_quiz_options) {
    return pg_course_lesson_row_html($lesson, $course_page_options($lesson['page_id'], lang('Choose a page')), $course_quiz_options($lesson['quiz_page_id']));
};

$course_rows = '';

foreach ($course_lessons as $lesson) {
    $course_rows .= $course_lesson_row($lesson);
}

$course_empty_lesson = array('id' => 0, 'page_id' => 0, 'open_days' => 0, 'quiz_page_id' => 0, 'quiz_pass_score' => 0);

// The status column: the course's figures and what keeps it from working.
$course_status = '<p class="pg-text-2 fg-secondary mb-0">' . h(lang('Save the course to see its learners and whether it works.')) . '</p>';

if ($course !== null) {
    $course_problems = pg_course_read_problems($course, pg_course_lessons($course_id));
    $course_completed = count(array_filter(array_column($course_learners, 'completed_at')));
    $course_status = '<dl class="row pg-text-2 mb-0">
            <dt class="col-7 fw-normal fg-secondary">' . h(lang('Lessons')) . '</dt><dd class="col-5 text-end pg-tabular">' . count(pg_course_lessons($course_id)) . '</dd>
            <dt class="col-7 fw-normal fg-secondary">' . h(lang('Learners')) . '</dt><dd class="col-5 text-end pg-tabular"><a href="edit_course.php?id=' . $course_id . '&amp;view=learners">' . count($course_learners) . '</a></dd>
            <dt class="col-7 fw-normal fg-secondary">' . h(lang('Completed')) . '</dt><dd class="col-5 text-end pg-tabular">' . $course_completed . '</dd>
        </dl>';

    if ($course_problems) {
        $course_status .= '<div class="mt-5 d-flex flex-column gap-3" role="list" aria-label="' . h(lang('What to check')) . '">';

        foreach ($course_problems as $problem) {
            $course_status .= '<div role="listitem" class="pg-text-2"><span class="pg-pill ' . (pg_course_problem_blocks($problem) ? 'pg-tone-rose' : 'pg-tone-amber') . ' me-2">'
                . h(pg_course_problem_blocks($problem) ? lang('Closes nothing') : lang('Needs a look')) . '</span>'
                . h(pg_course_problem_text($problem, ($problem['page_id'] > 0) ? _pg_course_lesson_name($problem['page_id']) : '')) . '</div>';
        }

        $course_status .= '</div>';
    } else {
        $course_status .= '<p class="mt-5 mb-0"><span class="pg-pill pg-tone-teal">' . h(lang('Working')) . '</span></p>';
    }
}

echo $course_shell . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . (($course !== null) ? pg_screen_toolbar($course_toolbar) : '') . '
            <form name="form" action="edit_course.php" method="post" id="course_form">
                ' . get_token_field() . '
                <input type="hidden" name="id" value="' . $course_id . '" />
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Course')) . '</h2>
                            <div class="card-body pt-0">
                                <div class="row gy-5">
                                    <div class="col-12">
                                        <label class="pg-field-label" for="course_name">' . h(lang('Name')) . '</label>
                                        <input type="text" class="pg-input" id="course_name" name="name" maxlength="255" required value="' . h($course_values['name']) . '" />
                                    </div>
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="course_folder">' . h(lang('Private folder')) . '</label>
                                        <select class="pg-input" id="course_folder" name="folder_id" aria-describedby="course_folder_help">' . $course_folder_options . '</select>
                                        <p class="pg-field-help pg-text-meta fg-secondary" id="course_folder_help">' . h(lang('The folder the course\'s product gives access to. Its pages are the lessons.')) . '</p>
                                    </div>
                                    <div class="col-12 md:col-6">
                                        <label class="pg-field-label" for="course_classroom">' . h(lang('Classroom page')) . '</label>
                                        <select class="pg-input" id="course_classroom" name="classroom_page_id" aria-describedby="course_classroom_help" data-pg-course-page>' . $course_page_options($course_values['classroom_page_id'], lang('None')) . '</select>
                                        <p class="pg-field-help pg-text-meta fg-secondary" id="course_classroom_help">' . h(lang('Where a member is sent, with a notice, when a lesson is not open yet.')) . '</p>
                                    </div>
                                    <div class="col-12 d-flex align-items-start gap-3">
                                        <input type="checkbox" class="pg-switch" id="course_in_order" name="in_order" value="1"' . ($course_values['in_order'] ? ' checked' : '') . ' aria-describedby="course_in_order_help" />
                                        <div><label class="pg-text-2" for="course_in_order">' . h(lang('Lessons in order')) . '</label>
                                        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="course_in_order_help">' . h(lang('A lesson opens only when the one before it is done.')) . '</p></div>
                                    </div>
                                    <div class="col-12 d-flex align-items-start gap-3">
                                        <input type="checkbox" class="pg-switch" id="course_certificate" name="certificate" value="1"' . ($course_values['certificate'] ? ' checked' : '') . ' aria-describedby="course_certificate_help" />
                                        <div><label class="pg-text-2" for="course_certificate">' . h(lang('Certificate')) . '</label>
                                        <p class="pg-field-help pg-text-meta fg-secondary mb-0" id="course_certificate_help">' . h(lang('A member who completed every lesson gets a certificate to print, with a code anyone can check.')) . '</p></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card mb-7">
                            <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Lessons')) . '</h2>
                            <div class="card-body pt-0">
                                <p class="pg-text-2 fg-secondary mt-0">' . h(lang('Pages of the folder in the order they are taken. A lesson opens the given number of days after enrolment (0: at once); with a quiz it is done only when the member scored at least the score needed.')) . '</p>
                                <div class="table-responsive">
                                    <table class="table align-middle mb-3 pg-course-lessons">
                                        <colgroup><col class="pg-course-col-number" /><col /><col class="pg-course-col-days" /><col class="pg-course-col-quiz" /><col class="pg-course-col-score" /><col class="pg-course-col-actions" /></colgroup>
                                        <thead><tr>
                                            <th scope="col"><span class="visually-hidden">' . h(lang('Order')) . '</span></th>
                                            <th scope="col"><span class="pg-field-label mb-0">' . h(lang('Lesson page')) . '</span></th>
                                            <th scope="col"><span class="pg-field-label mb-0">' . h(lang('Opens after')) . '</span></th>
                                            <th scope="col"><span class="pg-field-label mb-0">' . h(lang('Quiz to pass')) . '</span></th>
                                            <th scope="col"><span class="pg-field-label mb-0">' . h(lang('Score needed')) . '</span></th>
                                            <th scope="col"><span class="visually-hidden">' . h(lang('Actions')) . '</span></th>
                                        </tr></thead>
                                        <tbody id="course_lessons">' . $course_rows . '</tbody>
                                    </table>
                                </div>
                                <template id="course_lesson_template">' . $course_lesson_row($course_empty_lesson) . '</template>
                                <button type="button" class="pg-button pg-button-quiet pg-button-sm no-submit" id="course_add_lesson"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add lesson')) . '</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <div class="position-sticky" style="top:3.3rem;">
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">' . h(lang('Summary')) . '</h2>
                                <div class="card-body pt-0">' . $course_status . '</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                        <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="d-flex flex-wrap justify-content-center gap-3">
                                    <button type="submit" name="submit_save" value="Save" class="pg-button pg-button-fill"><i class="bi bi-check2" aria-hidden="true"></i>' . h(lang('Save')) . '</button>' . (($course !== null) ? '
                                    <button type="submit" name="submit_delete" value="Delete" formnovalidate class="pg-button pg-button-danger" data-confirm-content="' . h(lang('The course, its lessons and every member\'s progress in it are deleted. The folder, its pages and the members\' access stay.')) . '"><i class="bi bi-trash" aria-hidden="true"></i>' . h(lang('Delete')) . '</button>' : '') . '
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>
<script>
    // The lessons table: add, remove and move rows, number them, show only
    // the chosen folder\'s pages and ask a score only of a quiz lesson.
    (function () {
        var body = document.getElementById(\'course_lessons\');
        var template = document.getElementById(\'course_lesson_template\');
        var folder = document.getElementById(\'course_folder\');

        if (!body || !template || !folder) {
            return;
        }

        function number() {
            body.querySelectorAll(\'[data-pg-course-number]\').forEach(function (cell, index) {
                cell.textContent = index + 1;
            });
        }

        function filter(scope) {
            var chosen = folder.value;

            scope.querySelectorAll(\'select[data-pg-course-page] option[data-folder]\').forEach(function (option) {
                var other = (option.getAttribute(\'data-folder\') !== chosen) && !option.selected;

                option.hidden = other;
                option.disabled = other;
            });
        }

        document.getElementById(\'course_add_lesson\').addEventListener(\'click\', function () {
            body.appendChild(template.content.cloneNode(true));
            filter(body.lastElementChild);
            number();
            body.lastElementChild.querySelector(\'select\').focus();
        });

        body.addEventListener(\'click\', function (event) {
            var button = event.target.closest(\'[data-pg-course-move], [data-pg-course-remove]\');
            var row = button ? button.closest(\'tr\') : null;

            if (!row) {
                return;
            }

            if (button.hasAttribute(\'data-pg-course-remove\')) {
                row.remove();
            } else if (button.getAttribute(\'data-pg-course-move\') === \'-1\') {
                if (row.previousElementSibling) {
                    body.insertBefore(row, row.previousElementSibling);
                }
            } else if (row.nextElementSibling) {
                body.insertBefore(row.nextElementSibling, row);
            }

            number();

            if (row.isConnected) {
                button.focus();
            }
        });

        body.addEventListener(\'change\', function (event) {
            if (!event.target.matches(\'[data-pg-course-quiz]\')) {
                return;
            }

            // Read-only, never disabled: a disabled field is left out of the
            // post and the rows\' scores would shift.
            var score = event.target.closest(\'tr\').querySelector(\'[data-pg-course-score]\');
            var none = (event.target.value === \'0\');

            score.readOnly = none;
            score.tabIndex = none ? -1 : 0;

            if (none) {
                score.value = \'\';
                score.setAttribute(\'aria-disabled\', \'true\');
            } else {
                score.removeAttribute(\'aria-disabled\');
            }
        });

        folder.addEventListener(\'change\', function () {
            filter(document.getElementById(\'course_form\'));
        });

        number();
    }());
</script>
' . output_footer();

$liveform->remove_form();
