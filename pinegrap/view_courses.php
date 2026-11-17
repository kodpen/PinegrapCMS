<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Courses: every course with its folder, lessons, learners and completions,
 * and what keeps it from working (a folder that is not private, a lesson
 * whose page left the folder). Printed whole and sorted and searched in the
 * browser by the list table driver; a course opens in edit_course.php.
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

$liveform = new liveform('view_courses');

$courses_sort_columns = array(
    'name'        => lang('Course'),
    'folder'      => lang('Folder'),
    'lessons'     => lang('Lessons'),
    'learners'    => lang('Learners'),
    'completions' => lang('Completed'),
    'state'       => lang('Status'),
);
$courses_sort_default = array('sort' => 'name', 'order' => 'asc');
$courses_sort_levels = pg_list_state('view_courses', $courses_sort_columns, $courses_sort_default)['sorts'];

$courses_rows = array();

foreach (pg_course_list() as $course) {
    $id = (int) $course['id'];
    $problems = $course['problems'];
    $blocked = (bool) array_filter($problems, 'pg_course_problem_blocks');
    $problem_lines = '';

    if ($problems) {
        $page_names = array();

        foreach ($problems as $problem) {
            if ($problem['page_id'] > 0) {
                $page_names[$problem['page_id']] = _pg_course_lesson_name($problem['page_id']);
            }
        }

        foreach ($problems as $problem) {
            $problem_lines .= '<div class="pg-text-meta fg-secondary">' . h(pg_course_problem_text($problem, $page_names[$problem['page_id']] ?? '')) . '</div>';
        }
    }

    if ($blocked) {
        $state = '<span class="pg-pill pg-tone-rose">' . h(lang('Closes nothing')) . '</span>';
    } elseif ($problems) {
        $state = '<span class="pg-pill pg-tone-amber">' . h(lang('Needs a look')) . '</span>';
    } else {
        $state = '<span class="pg-pill pg-tone-teal">' . h(lang('Working')) . '</span>';
    }

    $courses_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="edit_course.php?id=' . $id . '" data-pg-open>' . h($course['name']) . '</a>' . $problem_lines),
            'folder' => pg_list_cell(($course['folder_name'] !== null) ? '<i class="bi bi-folder me-2" aria-hidden="true"></i>' . h($course['folder_name']) : ''),
            'lessons' => pg_list_cell(h(pg_format_number($course['lessons'], 0)), 'align-middle', array('data-pg-sort-value' => $course['lessons'])),
            'learners' => pg_list_cell('<a href="edit_course.php?id=' . $id . '&amp;view=learners">' . h(pg_format_number($course['learners'], 0)) . '</a>', 'align-middle', array('data-pg-sort-value' => $course['learners'])),
            'completions' => pg_list_cell(h(pg_format_number($course['completions'], 0)), 'align-middle', array('data-pg-sort-value' => $course['completions'])),
            'state' => pg_list_cell($state, 'align-middle', array('data-pg-sort-value' => $blocked ? 0 : ($problems ? 1 : 2))),
        ),
    );
}

$courses_list = pg_list_table_printed(array(
    'list' => 'view_courses',
    'columns' => array(
        'name' => array('heading' => lang('Course')),
        'folder' => array('heading' => lang('Folder')),
        'lessons' => array('heading' => lang('Lessons'), 'kind' => 'number'),
        'learners' => array('heading' => lang('Learners'), 'kind' => 'number'),
        'completions' => array('heading' => lang('Completed'), 'kind' => 'number'),
        'state' => array('heading' => lang('Status')),
    ),
    'levels' => $courses_sort_levels,
    'rows' => $courses_rows,
));

echo
    pg_page_shell(array(
        'title' => lang('Courses'),
        'extra classes' => 'courses pg-measure-list',
        'icon' => 'page',
        'heading' => lang('Courses'),
        'heading_description' => lang('Private folders sold as courses: their lessons, who is learning and who has completed.'),
        'breadcrumb' => array(
            array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
            array('label' => lang('Courses')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Add course'), 'url' => 'edit_course.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_courses'), pg_list_sort_menu('view_courses.php', $courses_sort_columns, $courses_sort_levels, $courses_sort_default)),
            )) . '
            ' . pg_list_table_html($courses_list['source'], $courses_list['page'], lang('Courses')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' . output_footer();

$liveform->remove_form();
