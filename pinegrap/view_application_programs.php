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
            // Delete sits in the row's "more" menu, last and danger, only for a
            // draft with no entries. The menu opens on the body, above the
            // rows that follow and outside the list's clipping; its button
            // posts the list's form through its form attribute.
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="' . $url . '">' . h(lang('Open the programme')) . '</a>'
                . ((($program['status'] === 0) && ($program['entries'] === 0))
                    ? ' <span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" data-bs-container="body" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
                        . '<ul class="menu pg-menu-end"><li><button type="submit" form="aprog_list_form" name="submit_delete" value="' . $id . '" formnovalidate class="menu-item fg-emphasis-danger" data-confirm-content="' . h(lang('The programme and its settings are deleted. The form and its submissions stay.')) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></li></ul></span>'
                    : ''),
                'align-middle text-nowrap text-end'),
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

// The drawer that adds a programme: its name and its form only; it opens
// as a draft. Opened by "Add programme", and with the screen after a
// refused add (?add=1).
$programs_form_options = '<option value="">' . h(lang('Choose a form')) . '</option>';

foreach (pg_application_program_forms($user) as $page_id => $form) {
    $programs_form_options .= '<option value="' . (int) $page_id . '">' . h($form['name']) . '</option>';
}

$programs_drawer = '
    <dialog class="drawer drawer-end" id="aprog_add" aria-labelledby="aprog_add_title" style="width:min(480px,100%)"' . (isset($_GET['add']) ? ' data-open="1"' : '') . '>
        <form method="post" action="edit_application_program.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="aprog_add_title">' . h(lang('Add programme')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1 d-flex align-items-center gap-3">' . h(lang('It starts as a draft; you set it up next.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="aprog_add_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="aprog_add_name" name="name" maxlength="150" required></div>
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="aprog_add_form">' . h(lang('Form')) . '</label><select class="pg-input" id="aprog_add_form" name="page_id" required>' . $programs_form_options . '</select><div class="form-text">' . h(lang('Entries are the form\'s submissions.')) . '</div></div>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" class="pg-button pg-button-fill">' . h(lang('Add')) . '</button>
            </div>
        </form>
    </dialog>';

echo
    pg_page_shell(array(
        'title' => lang('Application programmes'),
        'extra classes' => 'form pg-measure-list',
        'icon' => 'form',
        'heading' => lang('Application programmes'),
        'help' => 'forms#submitted-forms',
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
                'primary' => '<button type="button" class="pg-button pg-button-fill" data-aprog-drawer="aprog_add"><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Add programme')) . '</button>',
                'search'  => array(pg_list_table_search_box('view_application_programs'), pg_list_sort_menu('view_application_programs.php', $programs_sort_columns, $programs_sort_levels, $programs_sort_default)),
            )) . '
            <form name="form" id="aprog_list_form" action="edit_application_program.php" method="post">
                ' . get_token_field() . '
                ' . pg_list_table_html($programs_list['source'], $programs_list['page'], lang('Application programmes')) . '
            </form>
            ' . $programs_drawer . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script>
(function () {
    var phone = window.matchMedia("(max-width: 575.98px)");

    // The drawer opens through pgUI, from the right or, on a phone, as a
    // sheet from the bottom; one carrying data-open opens with the screen.
    var open = function (drawer) {
        if (!drawer || !window.pgUI) { return; }
        drawer.classList.toggle("drawer-end", !phone.matches);
        drawer.classList.toggle("drawer-bottom", phone.matches);
        pgUI.drawer.show(drawer);
    };

    document.addEventListener("click", function (event) {
        var opener = event.target.closest("[data-aprog-drawer]");
        if (opener) { open(document.getElementById(opener.getAttribute("data-aprog-drawer"))); }
    });

    // pgUI comes with the footer, after this script.
    window.addEventListener("load", function () {
        document.querySelectorAll("dialog[data-open]").forEach(open);
    });
})();
</script>
' . output_footer();

$liveform->remove_form();
