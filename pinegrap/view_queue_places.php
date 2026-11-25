<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Calendars › Queue: the walk-in queue's places, each with its services,
 * desks open of all, the numbers waiting now and its state. Printed whole
 * and sorted and searched in the browser by the list table driver; a place
 * opens in edit_queue_place.php, which also takes this screen's add drawer
 * and its row menu's Delete. Roles 0-2.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_waiting_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$liveform = new liveform('view_queue_places');

$places_sort_columns = array(
    'name'     => lang('Place'),
    'services' => lang('Services'),
    'desks'    => lang('Desks open'),
    'waiting'  => lang('Waiting now'),
    'status'   => lang('Status'),
);
$places_sort_default = array('sort' => 'name', 'order' => 'asc');
$places_sort_levels = pg_list_state('view_queue_places', $places_sort_columns, $places_sort_default)['sorts'];
$places_rows = array();

foreach (pg_waiting_place_list() as $place) {
    $id = (int) $place['id'];
    $url = 'edit_queue_place.php?id=' . $id;
    $services = implode(' · ', array_map(function ($service) {
        return $service['letter'] . ' ' . $service['name'];
    }, $place['services']));
    // Delete only while no number was taken there today.
    $delete = ($place['today'] === 0)
        ? '<li><button type="submit" form="queue_list_form" name="submit_delete" value="' . $id . '" formnovalidate class="menu-item fg-emphasis-danger" data-confirm-content="' . h(lang('The place and its desks are deleted. Its past numbers stay in the figures.')) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></li>'
        : '';

    $places_rows[] = array(
        'id' => $id,
        'cells' => array(
            'name' => pg_list_cell('<a href="' . $url . '" data-pg-open>' . h($place['name']) . '</a>'),
            'services' => pg_list_cell(($services !== '') ? h($services) : '<span class="pg-text-meta">' . h(lang('Not set')) . '</span>'),
            'desks' => pg_list_cell(h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array($place['desks_open'], $place['desks'])))), 'align-middle', array('data-pg-sort-value' => $place['desks_open'])),
            'waiting' => pg_list_cell(h(pg_format_number($place['waiting'], 0)), 'align-middle', array('data-pg-sort-value' => $place['waiting'])),
            'status' => pg_list_cell(pg_waiting_place_pill($place['status']), 'align-middle', array('data-pg-sort-value' => $place['status'])),
            // The menu opens on the body, above the rows that follow and
            // outside the list's clipping; Delete posts the list's form
            // through its form attribute.
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="' . $url . '">' . h(lang('Open the place')) . '</a>'
                . ' <span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" data-bs-container="body" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
                . '<ul class="menu pg-menu-end"><li><a class="menu-item" href="queue_poster.php?id=' . $id . '" target="_blank" rel="noopener"><i class="bi bi-qr-code me-3" aria-hidden="true"></i>' . h(lang('Print the QR poster')) . '</a></li>' . $delete . '</ul></span>',
                'align-middle text-nowrap text-end'),
        ),
    );
}

$places_list = pg_list_table_printed(array(
    'list' => 'view_queue_places',
    'columns' => array(
        'name' => array('heading' => lang('Place')),
        'services' => array('heading' => lang('Services')),
        'desks' => array('heading' => lang('Desks open'), 'kind' => 'number'),
        'waiting' => array('heading' => lang('Waiting now'), 'kind' => 'number'),
        'status' => array('heading' => lang('Status')),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => $places_sort_levels,
    'rows' => $places_rows,
));

// The drawer that adds a place: its name and its first service only; it
// starts closed. Opened by "Add place", and with the screen after a refused
// add (?add=1).
$places_drawer = '
    <dialog class="drawer drawer-end" id="queue_add" aria-labelledby="queue_add_title" style="width:min(480px,100%);--pg-drawer-height:70vh"' . (isset($_GET['add']) ? ' data-open="1"' : '') . '>
        <form method="post" action="edit_queue_place.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '
            <div class="drawer-header">
                <div class="flex-grow-1 min-w-0">
                    <h5 class="drawer-title mb-0" id="queue_add_title">' . h(lang('Add place')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('It starts closed; you set it up next.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="queue_add_name">' . h(lang('Name')) . '</label><input type="text" class="pg-input" id="queue_add_name" name="name" maxlength="150" required></div>
                <div class="form-field mb-5"><label class="form-label pg-field-label" for="queue_add_service">' . h(lang('First service')) . '</label><input type="text" class="pg-input" id="queue_add_service" name="service" maxlength="60" required aria-describedby="queue_add_service_help"><div class="pg-field-help" id="queue_add_service_help">' . h(lang('Its numbers start with A: A-001, A-002.')) . '</div></div>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_add" value="1" class="pg-button pg-button-fill">' . h(lang('Add')) . '</button>
            </div>
        </form>
    </dialog>';

echo
    pg_page_shell(array(
        'title' => lang('Queue'),
        'extra classes' => 'calendar pg-measure-list',
        'icon' => 'calendar',
        'heading' => lang('Queue'),
        'help' => 'calendars',
        'heading_description' => lang('Places where people take a number and wait to be called to a desk.'),
        'breadcrumb' => array(
            array('label' => lang('Calendars'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_calendars.php'),
            array('label' => lang('Queue')),
        ),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . pg_screen_toolbar(array(
                'primary' => '<button type="button" class="pg-button pg-button-fill" data-pg-queue-drawer="queue_add"><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Add place')) . '</button>',
                'search'  => array(pg_list_table_search_box('view_queue_places'), pg_list_sort_menu('view_queue_places.php', $places_sort_columns, $places_sort_levels, $places_sort_default)),
            )) . '
            <form name="form" id="queue_list_form" action="edit_queue_place.php" method="post">
                ' . get_token_field() . '
                ' . pg_list_table_html($places_list['source'], $places_list['page'], lang('Queue')) . '
            </form>
            ' . $places_drawer . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/queue_place.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/queue_place.js') . '"></script>
' . output_footer();

$liveform->remove_form();
