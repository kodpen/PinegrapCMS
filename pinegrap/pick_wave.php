<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One pick wave: its name and status, "Print the pick list", "Close the
 * wave", and two views. Pick list: one row per product and location in
 * shelf order, the shelf address large at the left; on a phone each row is
 * a tick target, a ticked row greys and sinks to the end, and the bar reads
 * "12 of 31 picked" (a tick is posted with ajax=1 and answered in JSON).
 * Addresses: each order's ship-by day, units and pack (a short one with its
 * missing lines and "Finish the pack"), those that left the wave as grey
 * rows. Manager and up, the orders right or the "Packing only"
 * right; the order number links to the order only for those who see orders.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/pick_waves.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/pack.php');

$user = validate_user();
pg_pick_validate_access($user);

$liveform = new liveform('pick_wave');
// One id; one sent as an array finds no wave.
$pw_id = $_POST['id'] ?? $_GET['id'] ?? 0;
$pw_id = is_scalar($pw_id) ? (int) $pw_id : 0;
$pw_wave = pg_pick_wave($pw_id);
$pw_view = (($_GET['view'] ?? '') === 'addresses') ? 'addresses' : 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax'] ?? '') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $session_token = (string) ($_SESSION['software']['token'] ?? '');
    $posted_token = is_string($_POST['token'] ?? null) ? $_POST['token'] : '';

    if ($session_token === '' || !hash_equals($session_token, $posted_token)) {
        http_response_code(403);
        echo json_encode(array('ok' => false, 'message' => lang('Your session expired. Reload the page and try again.')));
        exit;
    }

    $ticked = $pw_wave ? pg_pick_wave_tick($pw_id, is_string($_POST['key'] ?? null) ? $_POST['key'] : '', ($_POST['on'] ?? '') === '1') : null;

    if ($ticked === null) {
        http_response_code(409);
        echo json_encode(array('ok' => false, 'message' => lang('This wave is closed.')));
        exit;
    }

    echo json_encode(array(
        'ok' => true,
        'line' => lang(array('string' => '{var:1} of {var:2} picked', 'vars' => array($ticked['done'], $ticked['total']))),
        'pill' => pg_pick_wave_pill($ticked['status']),
    ));
    exit;
}

if (!$pw_wave) {
    output_error(lang('The wave could not be found.') . ' <a href="view_pick_waves.php">' . h(lang('Pick waves')) . '</a>', 404);
}

$pw_self = 'pick_wave.php?id=' . $pw_id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    if (isset($_POST['submit_close']) && pg_pick_wave_close($pw_id)) {
        $liveform->add_notice(h(lang('The wave is closed. Its addresses not packed wait to be picked again.')));
    }

    go($pw_self . (($pw_view === 'addresses') ? '&view=addresses' : ''));
}

$pw_label = pg_pick_wave_label($pw_wave);
$pw_closed = ($pw_wave['status'] === PG_WAVE_CLOSED);
$pw_list = pg_pick_list_ticked(pg_pick_wave_lines($pw_id), $pw_wave['picked']);
$pw_addresses = pg_pick_wave_addresses($pw_id);
$pw_in_wave = count(array_filter($pw_addresses, function ($row) {
    return (int) $row['removed_at'] === 0;
}));

// The pick list: one row per product and location, the shelf address
// first in large type; the whole row is the tick's label.
$pw_rows = '';

foreach ($pw_list['lines'] as $index => $line) {
    $field = 'pick_line_' . $index;
    $image = ($line['image'] !== '')
        ? '<img class="pg-pick-image" src="' . h(pg_image_panel_thumb_url(PATH . encode_url_path($line['image']))) . '" alt="" loading="lazy" width="48" height="48">'
        : '<span class="pg-icon-tile pg-tone-gray pg-pick-image" aria-hidden="true"><span class="bi bi-box-seam"></span></span>';
    $details = array_filter(array_merge(array((string) $line['location'], (string) $line['code']), (array) $line['options']), 'strlen');

    $pw_rows .= '
        <li class="pg-list-row pg-pick-row' . ($line['picked'] ? ' is-read' : '') . '" data-pg-pick-row data-pg-pick-key="' . h($line['key']) . '" data-pg-pick-place="' . $index . '">
            <input type="checkbox" class="pg-checkbox" id="' . $field . '" data-pg-pick-tick' . ($line['picked'] ? ' checked' : '') . ($pw_closed ? ' disabled' : '') . '>
            <label class="pg-pick-label" for="' . $field . '">
                <span class="pg-figure-value pg-pick-bin">' . (($line['bin'] !== '') ? h($line['bin']) : '<span class="pg-text-meta">' . h(lang('No shelf')) . '</span>') . '</span>
                ' . $image . '
                <span class="pg-list-row-main">
                    <span class="pg-list-row-head"><span class="pg-list-row-title">' . h($line['name']) . '</span></span>
                    <span class="pg-list-row-text">' . h(implode(' · ', $details)) . '</span>
                </span>
                <span class="pg-pick-units">
                    <span class="pg-figure-value">' . h(pg_format_number($line['units'], 0)) . '</span>
                    <span class="pg-text-meta">' . h(lang(array('string' => 'for {var:1} addresses', 'vars' => array($line['addresses'])))) . '</span>
                </span>
            </label>
        </li>';
}

$pw_pick_html = $pw_list['total']
    ? '<div class="pg-card pg-pick-card">
        <p class="pg-pick-bar pg-text-2" role="status" aria-live="polite" data-pg-pick-bar>' . h(lang(array('string' => '{var:1} of {var:2} picked', 'vars' => array($pw_list['done'], $pw_list['total'])))) . '</p>
        <p class="pg-text-meta">' . h(lang('Tick a line once its units are in the basket; a ticked line moves to the end.')) . '</p>
        <ul class="pg-pick-rows" data-pg-pick-list data-pg-pick-url="' . h($pw_self) . '" data-pg-pick-id="' . $pw_id . '" data-pg-pick-token="' . h((string) ($_SESSION['software']['token'] ?? '')) . '">' . $pw_rows . '</ul>
    </div>'
    : '<div class="pg-card pg-look-pad"><p class="pg-text-2">' . h(lang('Nothing is left to pick: every order of this wave was cancelled.')) . '</p></div>';

// The addresses: ship-by day amber today and red when late; an order that
// left the wave is a grey row.
$pw_today = pg_pick_today();
$pw_sees_orders = pg_pick_user_sees_orders($user);
$pw_address_rows = array();

foreach ($pw_addresses as $row) {
    $gone = (int) $row['removed_at'] > 0;
    $number = h((string) $row['order_number']);
    $order = ($pw_sees_orders && !$gone) ? '<a href="view_order.php?id=' . (int) $row['order_id'] . '" data-pg-open>' . $number . '</a>' : $number;
    $tone = pg_pick_ship_by_tone($row['ship_by'], $pw_today);
    $day = ((int) $row['ship_by'] > 0)
        ? h(get_absolute_time(array('timestamp' => (int) $row['ship_by'], 'type' => 'date', 'size' => 'short', 'format' => 'plain_text')))
        : '<span class="pg-text-meta">' . h(lang('Not known')) . '</span>';

    if ($tone !== '' && !$gone) {
        $day = '<span class="pg-pill ' . (($tone === 'late') ? 'pg-tone-rose' : 'pg-tone-amber') . '">' . $day . '</span>';
    }

    $pw_address_rows[] = array(
        'id' => (int) $row['ship_to_id'],
        'class' => $gone ? 'pg-text-meta' : '',
        'cells' => array(
            'order' => pg_list_cell($order),
            'ship_by' => pg_list_cell($day, 'align-middle', array('data-pg-sort-value' => (int) $row['ship_by'])),
            'units' => pg_list_cell(h(pg_format_number($row['units'], 0)), 'align-middle', array('data-pg-sort-value' => (int) $row['units'])),
            // A short pack names its missing lines and opens the Pack
            // screen on it ("Finish the pack").
            'pack' => pg_list_cell($gone
                ? '<span class="pg-pill pg-tone-gray">' . h(lang('Cancelled, taken out')) . '</span>'
                : (((int) $row['pack_status'] === PG_PACK_SHORT) ? pg_pack_short_notice((int) $row['ship_to_id'], (string) $row['pack_missing']) : pg_pick_pack_pill($row['pack_status'])),
                'align-middle', array('data-pg-sort-value' => $gone ? 9 : (int) $row['pack_status'])),
        ),
    );
}

$pw_addresses_list = pg_list_table_printed(array(
    'list' => 'pick_wave_addresses',
    'columns' => array(
        'order' => array('heading' => lang('Order')),
        'ship_by' => array('heading' => lang('Ship-by day')),
        'units' => array('heading' => lang('Units'), 'kind' => 'number'),
        'pack' => array('heading' => lang('Pack')),
    ),
    'rows' => $pw_address_rows,
));

$pw_tab = function ($view, $label, $count) use ($pw_id, $pw_view) {
    return '<a class="pg-seg-item' . (($pw_view === $view) ? ' is-active" aria-current="page' : '') . '" href="pick_wave.php?id=' . $pw_id . (($view === 'list') ? '' : '&amp;view=' . $view) . '">'
        . h($label) . ' <span class="pg-seg-count">' . (int) $count . '</span></a>';
};

echo pg_page_shell(array(
    'title' => $pw_label,
    'extra classes' => 'ecommerce pg-measure-list',
    'icon' => 'orders',
    // output_header() prints the heading as markup; the name is staff text.
    'heading' => h($pw_label) . ' <span data-pg-pick-pill>' . pg_pick_wave_pill($pw_wave['status']) . '</span>',
    'heading_description' => lang(array('string' => 'Made {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => (int) $pw_wave['created_at'], 'size' => 'short', 'format' => 'plain_text'))))),
    'breadcrumb' => array(
        array('label' => lang('Pick waves'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pick_waves.php'),
        array('label' => $pw_label),
    ),
)) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    ' . pg_screen_toolbar(array(
        'primary' => array(
            '<a class="pg-button pg-button-fill" href="print_pick_list.php?id=' . $pw_id . '" target="_blank" rel="noopener"><i class="bi bi-printer" aria-hidden="true"></i>' . h(lang('Print the pick list')) . '</a>',
            $pw_closed ? '' : '<button type="submit" form="pw_state_form" name="submit_close" value="1" class="pg-button pg-button-quiet" data-confirm-content="' . h(lang('The addresses not packed leave the wave and wait to be picked again.')) . '"><i class="bi bi-check2-circle" aria-hidden="true"></i>' . h(lang('Close the wave')) . '</button>',
        ),
        'view' => '<span class="pg-seg" role="group" aria-label="' . h(lang('Wave')) . '">' . $pw_tab('list', lang('Picking list'), $pw_list['total']) . $pw_tab('addresses', lang('Addresses'), $pw_in_wave) . '</span>',
    )) . '
    <form id="pw_state_form" action="pick_wave.php" method="post">' . get_token_field() . '<input type="hidden" name="id" value="' . $pw_id . '"></form>
    ' . (($pw_view === 'addresses')
        ? pg_list_table_html($pw_addresses_list['source'], $pw_addresses_list['page'], lang('Addresses'))
        : $pw_pick_html) . '
</main>
' . (($pw_view === 'addresses') ? pg_js_bundle_tags('assets/js/pg_list_table.min.js') : '') . '
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pick_waves.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/pick_waves.js') . '"></script>
' . output_footer();

$liveform->remove_form();
