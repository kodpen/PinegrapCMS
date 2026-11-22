<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Orders › Pick waves: the waves of addresses picked together, each with
 * who made it, its location, addresses, units, status and how many are
 * packed. "Make a wave" opens a drawer that makes one by a rule (carrier,
 * marketplace, location, ship-by day, single-item addresses), its live line
 * read from ?preview=1. A wave opens in pick_wave.php; its row menu deletes
 * it while none of its addresses is packed. Printed whole and sorted in the
 * browser by the list table driver. Manager and up, the orders right or the
 * "Packing only" right.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_waves.php');

$user = validate_user();
pg_pick_validate_access($user);

$liveform = new liveform('view_pick_waves');

// The drawer's live line: what the rule would take now.
if (($_GET['preview'] ?? '') === '1') {
    $preview = pg_pick_wave_preview(pg_pick_wave_rule_clean($_GET, pg_pick_today()));
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array(
        'addresses' => $preview['addresses'],
        'line' => lang(array('string' => '{var:1} addresses, {var:2} units match', 'vars' => array(
            pg_format_number($preview['addresses'], 0),
            pg_format_number($preview['units'], 0),
        ))),
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    if (isset($_POST['submit_make'])) {
        $made = pg_pick_wave_make(pg_pick_wave_rule_clean($_POST, pg_pick_today()), (int) $user['id']);

        if ($made['ok']) {
            go('pick_wave.php?id=' . $made['id']);
        }

        $liveform->add_error(h(($made['error'] === 'busy')
            ? lang('Another wave is being made. Try again in a moment.')
            : lang('No address waiting to be picked matches these choices.')));
        go('view_pick_waves.php');
    }

    if (isset($_POST['submit_delete'])) {
        $wave = pg_pick_wave((int) $_POST['submit_delete']);

        if ($wave && pg_pick_wave_delete($wave['id'])) {
            $liveform->add_notice(h(lang(array('string' => '{var:1} was deleted; its orders wait to be picked again.', 'vars' => array(pg_pick_wave_label($wave))))));
        } elseif ($wave) {
            $liveform->add_error(h(lang('A wave with a packed address cannot be deleted. Close it instead.')));
        }

        go('view_pick_waves.php');
    }
}

$choices = pg_pick_wave_choices();
$waves_sort_columns = array(
    'wave' => lang('Wave'),
    'made' => lang('Made on'),
    'location' => lang('Stock location'),
    'addresses' => lang('Addresses'),
    'units' => lang('Units'),
    'status' => lang('Status'),
    'packed' => lang('Packed'),
);
$waves_sort_default = array('sort' => 'made', 'order' => 'desc');
$waves_sort_levels = pg_list_state('view_pick_waves', $waves_sort_columns, $waves_sort_default)['sorts'];
$waves_rows = array();

foreach (pg_pick_wave_list() as $wave) {
    $id = (int) $wave['id'];
    $url = 'pick_wave.php?id=' . $id;
    $location = ((int) $wave['location_id'] > 0 && isset($choices['locations'][(int) $wave['location_id']]))
        ? h($choices['locations'][(int) $wave['location_id']])
        : '<span class="pg-text-meta">' . h(lang('Any')) . '</span>';
    $delete = ((int) $wave['packed'] === 0 && pg_pick_wave_deletable($id))
        ? '<li><button type="submit" form="pick_waves_form" name="submit_delete" value="' . $id . '" formnovalidate class="menu-item fg-emphasis-danger" data-confirm-content="' . h(lang('The wave is deleted and its orders wait to be picked again.')) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i>' . h(lang('Delete')) . '</button></li>'
        : '';

    $waves_rows[] = array(
        'id' => $id,
        'cells' => array(
            'wave' => pg_list_cell('<a href="' . $url . '" data-pg-open>' . h(pg_pick_wave_label($wave)) . '</a>'
                . (!empty($wave['single_item']) ? ' <span class="pg-text-meta">' . h(lang('Single item')) . '</span>' : '')),
            'made' => pg_list_table_when_cell($wave['created_at'], (string) $wave['created_by_name']),
            'location' => pg_list_cell($location),
            'addresses' => pg_list_cell(h(pg_format_number($wave['addresses'], 0)), 'align-middle', array('data-pg-sort-value' => (int) $wave['addresses'])),
            'units' => pg_list_cell(h(pg_format_number($wave['units'], 0)), 'align-middle', array('data-pg-sort-value' => (int) $wave['units'])),
            'status' => pg_list_cell(pg_pick_wave_pill($wave['status']), 'align-middle', array('data-pg-sort-value' => (int) $wave['status'])),
            'packed' => pg_list_cell(h(lang(array('string' => '{var:1} of {var:2}', 'vars' => array((int) $wave['packed'], (int) $wave['addresses'])))), 'align-middle', array('data-pg-sort-value' => (int) $wave['packed'])),
            'actions' => pg_list_cell('<a class="pg-button pg-button-quiet pg-button-sm" href="' . $url . '">' . h(lang('Open')) . '</a>'
                . ' <a class="pg-button pg-button-quiet pg-button-sm" href="print_pick_list.php?id=' . $id . '" target="_blank" rel="noopener">' . h(lang('Print the pick list')) . '</a>'
                . ($delete !== ''
                    ? ' <span class="pg-menu-wrap"><button type="button" class="pg-icon-button pg-icon-button-sm" data-bs-toggle="menu" data-bs-container="body" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>'
                        . '<ul class="menu pg-menu-end">' . $delete . '</ul></span>'
                    : ''),
                'align-middle text-nowrap text-end'),
        ),
    );
}

$waves_list = pg_list_table_printed(array(
    'list' => 'view_pick_waves',
    'columns' => array(
        'wave' => array('heading' => lang('Wave')),
        'made' => array('heading' => lang('Made on')),
        'location' => array('heading' => lang('Stock location')),
        'addresses' => array('heading' => lang('Addresses'), 'kind' => 'number'),
        'units' => array('heading' => lang('Units'), 'kind' => 'number'),
        'status' => array('heading' => lang('Status')),
        'packed' => array('heading' => lang('Packed'), 'kind' => 'number'),
        'actions' => array('heading' => lang('Actions'), 'kind' => 'actions'),
    ),
    'levels' => $waves_sort_levels,
    'rows' => $waves_rows,
));

// One select of the drawer; left out when there is nothing to choose.
$waves_select = function ($name, $label, array $options, $help) {
    if (!$options) {
        return '';
    }

    $html = '<option value="">' . h(lang('Any')) . '</option>';

    foreach ($options as $value => $text) {
        $html .= '<option value="' . h((string) $value) . '">' . h($text) . '</option>';
    }

    return '<div class="form-field mb-5"><label class="form-label pg-field-label" for="pick_wave_' . $name . '">' . h($label) . '</label>'
        . '<select class="pg-input" id="pick_wave_' . $name . '" name="' . $name . '" aria-describedby="pick_wave_' . $name . '_help" data-pg-pick-rule>' . $html . '</select>'
        . '<div class="pg-field-help" id="pick_wave_' . $name . '_help">' . h($help) . '</div></div>';
};

$waves_drawer = '
    <dialog class="drawer drawer-end" id="pick_wave_make" aria-labelledby="pick_wave_make_title" style="width:min(480px,100%);--pg-drawer-height:80vh">
        <form method="post" action="view_pick_waves.php" class="d-flex flex-column h-100" data-pg-pick-preview="view_pick_waves.php?preview=1">
            ' . get_token_field() . '
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="pick_wave_make_title">' . h(lang('Make a wave')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('Paid addresses not packed yet, oldest ship-by day first, at most 200.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                ' . $waves_select('carrier', lang('Carrier'), $choices['carriers'], lang('Only addresses sent with this carrier.')) . '
                ' . $waves_select('marketplace', lang('Marketplace'), $choices['marketplaces'], lang('Only orders from this marketplace account.')) . '
                ' . $waves_select('location_id', lang('Stock location'), $choices['locations'], lang('Only orders picked at this location.')) . '
                ' . $waves_select('ship_by', lang('Ship-by day'), array('today' => lang('Due today or late'), 'tomorrow' => lang('Due by tomorrow')), lang('The day the parcel should leave.')) . '
                <div class="form-field mb-5 d-flex align-items-center gap-3">
                    <input type="checkbox" class="pg-switch" id="pick_wave_single_item" name="single_item" value="1" aria-describedby="pick_wave_single_item_help" data-pg-pick-rule>
                    <div><label class="pg-text-2" for="pick_wave_single_item">' . h(lang('Single-item addresses only')) . '</label>
                    <div class="pg-field-help" id="pick_wave_single_item_help">' . h(lang('Each one is packed straight from the pick.')) . '</div></div>
                </div>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3">
                <span class="flex-grow-1 pg-text-2" role="status" aria-live="polite" data-pg-pick-line></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_make" value="1" class="pg-button pg-button-fill" data-pg-pick-make>' . h(lang('Make the wave')) . '</button>
            </div>
        </form>
    </dialog>';

echo
    pg_page_shell(array(
        'title' => lang('Pick waves'),
        'extra classes' => 'ecommerce pg-measure-list',
        'icon' => 'orders',
        'heading' => lang('Pick waves'),
        'heading_description' => lang('Orders picked together from the shelves, in shelf order.'),
        'breadcrumb' => pg_pick_user_sees_orders($user)
            ? array(array('label' => lang('Orders'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_orders.php'), array('label' => lang('Pick waves')))
            : array(array('label' => lang('Pick waves'))),
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->get_messages() . '
            ' . pg_screen_toolbar(array(
                'primary' => '<button type="button" class="pg-button pg-button-fill" data-pg-pick-drawer="pick_wave_make"><i class="bi bi-plus-circle" aria-hidden="true"></i>' . h(lang('Make a wave')) . '</button>',
                'search'  => array(pg_list_table_search_box('view_pick_waves'), pg_list_sort_menu('view_pick_waves.php', $waves_sort_columns, $waves_sort_levels, $waves_sort_default)),
            )) . '
            <form name="form" id="pick_waves_form" action="view_pick_waves.php" method="post">
                ' . get_token_field() . '
                ' . pg_list_table_html($waves_list['source'], $waves_list['page'], lang('Pick waves')) . '
            </form>
            ' . $waves_drawer . '
        </div>
    </div>
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pick_waves.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/pick_waves.js') . '"></script>
' . output_footer();

$liveform->remove_form();
