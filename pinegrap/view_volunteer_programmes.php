<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contacts › Volunteers: the volunteer programmes a user sees (roles 0-2 all
 * of them, a coordinator their own), each with its active volunteers, this
 * week's places filled of needed and its state. Printed whole and sorted
 * and searched in the browser by the list table driver; a programme opens
 * in edit_volunteer_programme.php, which also takes this screen's add
 * drawer (roles 0-2).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$user = validate_user();

if (!pg_volunteer_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

if (((int) $user['role'] > 2) && !pg_volunteer_coordinator((int) $user['id'])) {
    log_activity('access denied to volunteer programmes');
    output_error(lang('Access denied.'), 403);
}

$liveform = new liveform('view_volunteer_programmes');
$vp_staff = ((int) $user['role'] < 3);

$vp_sort_columns = array(
    'name'    => lang('Programme'),
    'active'  => lang('Active volunteers'),
    'applied' => lang('Applicants'),
    'week'    => lang('Shifts this week'),
    'state'   => lang('Status'),
);
$vp_sort_default = array('sort' => 'name', 'order' => 'asc');
$vp_sort_levels = pg_list_state('view_volunteer_programmes', $vp_sort_columns, $vp_sort_default)['sorts'];
$vp_rows = array();

foreach (pg_volunteer_programme_list($user) as $programme) {
    $id = $programme['id'];
    $url = 'edit_volunteer_programme.php?id=' . $id;

    $vp_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="' . $url . '" data-pg-open>' . h($programme['name']) . '</a>'),
            'active' => pg_list_cell(h(pg_format_number($programme['active'], 0)), 'align-middle', array('data-pg-sort-value' => $programme['active'])),
            'applied' => pg_list_cell(h(pg_format_number($programme['applied'], 0)), 'align-middle', array('data-pg-sort-value' => $programme['applied'])),
            'week' => pg_list_cell($programme['needed'] ? h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($programme['filled'], $programme['needed'])))) : '<span class="pg-text-meta">' . h(lang('No shifts')) . '</span>', 'align-middle', array('data-pg-sort-value' => $programme['needed'] ? round(100 * $programme['filled'] / $programme['needed']) : -1)),
            'state' => pg_list_cell(pg_volunteer_programme_pill($programme['state']), 'align-middle', array('data-pg-sort-value' => $programme['state'])),
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="' . $url . '">' . h(lang('Open the programme')) . '</a>', 'align-middle text-nowrap text-end'),
        ),
    );
}

$vp_list = pg_list_table_printed(array(
    'list' => 'view_volunteer_programmes',
    'columns' => array(
        'name' => array('heading' => lang('Programme')),
        'active' => array('heading' => lang('Active volunteers'), 'kind' => 'number'),
        'applied' => array('heading' => lang('Applicants'), 'kind' => 'number'),
        'week' => array('heading' => lang('Shifts this week'), 'kind' => 'number'),
        'state' => array('heading' => lang('Status')),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => $vp_sort_levels,
    'rows' => $vp_rows,
));

// The drawer that adds a programme: its name and its application form.
// Opened by "Add programme", and with the screen after a refused add
// (?add=1).
$vp_drawer = '';

if ($vp_staff) {
    $vp_form_options = '<option value="0">' . h(lang('Choose later')) . '</option>';

    foreach (pg_application_program_forms($user) as $page_id => $form) {
        $vp_form_options .= '<option value="' . (int) $page_id . '">' . h($form['name']) . '</option>';
    }

    $vp_drawer = '
    <dialog class="drawer drawer-end" id="vp_add" aria-labelledby="vp_add_title" style="width:min(480px,100%);--pg-drawer-height:70vh"' . (isset($_GET['add']) ? ' data-open="1"' : '') . '>
        <form method="post" action="edit_volunteer_programme.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '
            <div class="drawer-header">
                <div class="flex-grow-1 min-w-0">
                    <h5 class="drawer-title mb-0" id="vp_add_title">' . h(lang('Add programme')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('People apply on its form; you set up its roles next.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="vp_add_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="vp_add_name" name="name" maxlength="150" required></div>
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="vp_add_form">' . h(lang('Application form')) . '</label><select class="pg-input" id="vp_add_form" name="page_id" aria-describedby="vp_add_form_help">' . $vp_form_options . '</select><div class="pg-field-help" id="vp_add_form_help">' . h(lang('A custom form whose e-mail field makes a contact.')) . '</div></div>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_add" value="1" class="pg-button pg-button-fill">' . h(lang('Add')) . '</button>
            </div>
        </form>
    </dialog>';
}

echo
    pg_page_shell(array(
        'title' => lang('Volunteers'),
        'extra classes' => 'contacts pg-measure-list',
        'icon' => 'contacts',
        'heading' => lang('Volunteers'),
        'help' => 'contacts',
        'heading_description' => lang('Volunteer programmes: who applies, who is active, and their shifts.'),
        'breadcrumb' => array(
            array('label' => lang('Contacts'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_contacts.php'),
            array('label' => lang('Volunteers')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . pg_screen_toolbar(array(
                'primary' => $vp_staff ? '<button type="button" class="pg-button pg-button-fill" data-pg-vp-drawer="vp_add"><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Add programme')) . '</button>' : '',
                'search'  => array(pg_list_table_search_box('view_volunteer_programmes'), pg_list_sort_menu('view_volunteer_programmes.php', $vp_sort_columns, $vp_sort_levels, $vp_sort_default)),
            )) . '
            ' . pg_list_table_html($vp_list['source'], $vp_list['page'], lang('Volunteers')) . '
            ' . $vp_drawer . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/volunteer_programme.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/volunteer_programme.js') . '"></script>
' . output_footer();

$liveform->remove_form();
