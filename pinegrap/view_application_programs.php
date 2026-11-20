<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Forms › Application programmes: every programme on a form the user may
 * run (validate_forms_access() and the folder rule), with its period,
 * status, entries and round. Printed whole and sorted and searched in the
 * browser by the list table driver; a programme opens in
 * edit_application_program.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$user = validate_user();
validate_forms_access($user);

if (!pg_application_program_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('view_application_programs');

$programs_sort_columns = array(
    'name'    => lang('Programme'),
    'form'    => lang('Form'),
    'opens'   => lang('Opens'),
    'closes'  => lang('Closes'),
    'status'  => lang('Status'),
    'entries' => lang('Entries received'),
    'round'   => lang('Round'),
);
$programs_sort_default = array('sort' => 'name', 'order' => 'asc');
$programs_sort_levels = pg_list_state('view_application_programs', $programs_sort_columns, $programs_sort_default)['sorts'];

$programs_status_pills = array(
    0 => '<span class="pg-pill pg-tone-gray">' . h(lang('Draft')) . '</span>',
    1 => '<span class="pg-pill pg-tone-teal">' . h(lang('Open')) . '</span>',
    2 => '<span class="pg-pill pg-tone-gray">' . h(lang('Closed')) . '</span>',
);
$programs_when = function ($time) {
    return ($time > 0)
        ? pg_list_cell(h(get_absolute_time(array('timestamp' => $time, 'format' => 'plain_text'))), 'align-middle', array('data-pg-sort-value' => $time))
        : pg_list_cell('<span class="pg-text-meta">' . h(lang('Not set')) . '</span>', 'align-middle', array('data-pg-sort-value' => 0));
};
$programs_rows = array();

foreach (pg_application_program_list($user) as $program) {
    $id = (int) $program['id'];
    $url = 'edit_application_program.php?id=' . $id;

    $programs_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="' . $url . '" data-pg-open>' . h($program['name']) . '</a>'),
            'form' => pg_list_cell(h($program['form_name'])),
            'opens' => $programs_when($program['opens_at']),
            'closes' => $programs_when($program['closes_at']),
            'status' => pg_list_cell($programs_status_pills[$program['status']] ?? '', 'align-middle', array('data-pg-sort-value' => $program['status'])),
            'entries' => pg_list_cell(h(pg_format_number($program['entries'], 0)), 'align-middle', array('data-pg-sort-value' => $program['entries'])),
            'round' => pg_list_cell(h(pg_format_number($program['round'], 0)), 'align-middle', array('data-pg-sort-value' => $program['round'])),
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="' . $url . '">' . h(lang('Open the programme')) . '</a>', 'align-middle text-end'),
        ),
    );
}

$programs_list = pg_list_table_printed(array(
    'list' => 'view_application_programs',
    'columns' => array(
        'name' => array('heading' => lang('Programme')),
        'form' => array('heading' => lang('Form')),
        'opens' => array('heading' => lang('Opens')),
        'closes' => array('heading' => lang('Closes')),
        'status' => array('heading' => lang('Status')),
        'entries' => array('heading' => lang('Entries received'), 'kind' => 'number'),
        'round' => array('heading' => lang('Round'), 'kind' => 'number'),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => $programs_sort_levels,
    'rows' => $programs_rows,
));

echo
    pg_page_shell(array(
        'title' => lang('Application programmes'),
        'extra classes' => 'form pg-measure-list',
        'icon' => 'form',
        'heading' => lang('Application programmes'),
        'heading_description' => lang('Forms run as an application: a period, criteria, reviewers and decisions.'),
        'breadcrumb' => array(
            array('label' => lang('Forms'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_submitted_forms.php'),
            array('label' => lang('Application programmes')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . pg_screen_toolbar(array(
                'primary' => array('label' => lang('Add programme'), 'url' => 'edit_application_program.php', 'icon' => 'bi-plus-circle'),
                'search'  => array(pg_list_table_search_box('view_application_programs'), pg_list_sort_menu('view_application_programs.php', $programs_sort_columns, $programs_sort_levels, $programs_sort_default)),
            )) . '
            ' . pg_list_table_html($programs_list['source'], $programs_list['page'], lang('Application programmes')) . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' . output_footer();

$liveform->remove_form();
