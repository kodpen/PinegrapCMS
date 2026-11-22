<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Orders › Delivery problems: the parcels the hourly pass found stuck, never
 * handed over, with a delivery problem or returned (shipment_problems). Open,
 * Closed and All as segments, kind and carrier as selects; a problem's
 * drawer gives it to a person, keeps a note and closes it with a reason;
 * with rows chosen the bulk bar assigns or closes them together. Nothing is
 * added here and a problem is never deleted. Users with the orders right;
 * the head's Settings drawer (Manager and up) switches each kind on or off
 * and holds the two day counts. The work is in
 * includes/fn/delivery_problems.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_ecommerce_access($user);

require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/delivery_problems.php');

include_once('liveform.class.php');
$liveform = new liveform('view_delivery_problems');

$problems_ready = (ECOMMERCE === true) && pg_delivery_problems_ready();

// A change: checked, saved, and the screen again with the same filters.
if ($problems_ready && ($_SERVER['REQUEST_METHOD'] === 'POST')) {
    validate_token_field();

    $problems_back = is_string($_POST['back'] ?? null) ? (string) parse_url($_POST['back'], PHP_URL_QUERY) : '';
    parse_str($problems_back, $problems_back_query);
    $problems_back_filters = pg_delivery_problem_filters((array) $problems_back_query);
    $problems_action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $problems_one = (int) ($_POST['problem'] ?? 0);
    $problems_ids = ($problems_one > 0) ? array($problems_one) : array_map('intval', array_filter((array) ($_POST['problems'] ?? array()), 'is_scalar'));
    $problems_input = array();

    // The settings drawer: Manager and up.
    if ($problems_action === 'settings') {
        if ((int) $user['role'] < 3) {
            if (pg_delivery_problems_settings_save(pg_delivery_problem_settings_input($_POST))) {
                log_activity(lang('The delivery problem settings were saved.'), $_SESSION['sessionusername']);
                $liveform->add_notice(lang('The settings were saved.'));
            } else {
                $liveform->add_error(lang('The settings could not be saved.'));
            }
        }

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . pg_delivery_problem_url($problems_back_filters));
        exit();
    }

    if ($problems_action === 'save') {
        $problems_input = array('assigned_to' => (int) ($_POST['assigned_to'] ?? 0), 'note' => is_string($_POST['note'] ?? null) ? $_POST['note'] : '');
    } elseif ($problems_action === 'assign') {
        $problems_input = array('assigned_to' => (int) ($_POST['assigned_to'] ?? 0));
    } elseif ($problems_action === 'close') {
        $problems_input = array('close' => (int) ($_POST['close'] ?? 0), 'close_note' => is_string($_POST['close_note'] ?? null) ? $_POST['close_note'] : '');

        if ($problems_one > 0) {
            $problems_input['assigned_to'] = (int) ($_POST['assigned_to'] ?? 0);
            $problems_input['note'] = is_string($_POST['note'] ?? null) ? $_POST['note'] : '';
        }
    }

    if (!$problems_input || !$problems_ids) {
        $liveform->add_error(lang('Choose at least one problem.'));
    } else {
        $problems_saved = pg_delivery_problems_save($problems_ids, $problems_input, (int) $user['id']);

        if ($problems_saved['error'] === 'reason') {
            $liveform->add_error(lang('Choose why it is closed.'));
        } elseif (($problems_saved['saved'] === 0) && ($problems_saved['skipped'] > 0)) {
            $liveform->add_error(lang('This problem is already closed.'));
        } elseif ($problems_action === 'close') {
            $liveform->add_notice(($problems_saved['saved'] === 1) ? lang('The problem was closed.') : lang(array('string' => '{var:1} problems were closed.', 'vars' => array($problems_saved['saved']))));
        } else {
            $liveform->add_notice(($problems_saved['saved'] === 1) ? lang('The problem was saved.') : lang(array('string' => '{var:1} problems were saved.', 'vars' => array($problems_saved['saved']))));
        }
    }

    // A saved problem's drawer opens again; a closed one shows its reason.
    $problems_more = (($problems_one > 0) && ($problems_action === 'save')) ? array('problem' => $problems_one) : array();

    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/' . pg_delivery_problem_url($problems_back_filters, $problems_more));
    exit();
}

$problems_now = time();
$problems_filters = pg_delivery_problem_filters($_GET);

// The list.
$problems_rows = array();

if ($problems_ready) {
    foreach (pg_delivery_problems_list($problems_filters) as $problems_row) {
        $problems_rows[] = array('id' => (int) $problems_row['id'], 'cells' => pg_delivery_problem_list_cells($problems_row, $problems_filters, $problems_now));
    }
}

$problems_list = pg_list_table_printed(array(
    'list'      => 'delivery_problems',
    'columns'   => pg_delivery_problem_list_columns(),
    'levels'    => array(),
    'rows'      => $problems_rows,
    'ids_field' => 'problems[]',
));

// The filters: the view as link segments, kind and carrier as selects.
$problems_segments = '<nav class="pg-seg" aria-label="' . h(lang('Filter')) . '">';

foreach (array('open' => lang('Open'), 'closed' => lang('Closed'), 'all' => lang('All')) as $problems_key => $problems_label) {
    $problems_active = ($problems_key === $problems_filters['view']);
    $problems_segments .= '<a class="pg-seg-item' . ($problems_active ? ' is-active' : '') . '" href="' . h(pg_delivery_problem_url(array('view' => $problems_key) + $problems_filters)) . '"' . ($problems_active ? ' aria-current="true"' : '') . '>' . h($problems_label) . '</a>';
}

$problems_segments .= '</nav>';

$problems_kinds = array('0' => lang('Every kind'));

foreach (pg_delivery_problem_kinds() as $problems_code => $problems_kind) {
    $problems_kinds[(string) $problems_code] = $problems_kind[0];
}

$problems_carriers = array('' => lang('Every carrier')) + ($problems_ready ? pg_delivery_problems_carriers() : array());

if (($problems_filters['carrier'] !== '') && !isset($problems_carriers[$problems_filters['carrier']])) {
    $problems_carriers[$problems_filters['carrier']] = pg_delivery_problem_carrier_name($problems_filters['carrier']);
}

$problems_selects = pg_filter_select('kind', $problems_kinds, (string) $problems_filters['kind'], array('keep' => array('view', 'carrier'), 'label' => lang('Kind')))
    . pg_filter_select('carrier', $problems_carriers, $problems_filters['carrier'], array('keep' => array('view', 'kind'), 'label' => lang('Carrier')));

// The head's Settings, for Manager and up.
$problems_settings_button = '';
$problems_settings_drawer = '';

if ($problems_ready && ((int) $user['role'] < 3)) {
    $problems_settings_button = '<button type="button" class="pg-button pg-button-quiet" data-delivery-drawer="delivery_problems_settings"><i class="bi bi-gear" aria-hidden="true"></i>' . h(lang('Settings')) . '</button>';
    $problems_settings_drawer = pg_delivery_problem_settings_drawer(pg_delivery_tower_settings(), $problems_filters);
}

// The bulk bar's two buttons, each opening its drawer.
$problems_bulk = '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-delivery-drawer="delivery_problems_assign">' . h(lang('Assign to…')) . '</button>'
    . '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-delivery-drawer="delivery_problems_close">' . h(lang('Close…')) . '</button>';

// The list, or what is watched when it is empty.
if (!$problems_ready) {
    $problems_body = '<div class="pg-card-inner"><div class="ws-empty py-5"><i class="bi bi-truck" aria-hidden="true"></i>' . h(lang('Delivery problems come with the software update that adds their table.')) . '</div></div>';
} elseif (!$problems_rows && ($problems_filters['view'] === 'open') && ($problems_filters['kind'] === 0) && ($problems_filters['carrier'] === '')) {
    $problems_body = '<div class="pg-card-inner"><div class="ws-empty py-5"><i class="bi bi-truck" aria-hidden="true"></i><strong class="d-block">' . h(lang('No open delivery problems')) . '</strong>'
        . '<span class="pg-text-2">' . h(lang('Every hour, parcels long in transit, never handed to the carrier, with a delivery problem or returned are listed here.')) . '</span></div></div>';
} else {
    $problems_body = '<form method="post" action="view_delivery_problems.php" id="delivery_problems_form">'
        . get_token_field()
        . '<input type="hidden" name="back" value="' . h(pg_delivery_problem_url($problems_filters)) . '">'
        . pg_list_table_html($problems_list['source'], $problems_list['page'], lang('Delivery problems'), $problems_bulk)
        . '</form>'
        . pg_delivery_problem_bulk_drawers('delivery_problems_form');
}

// The drawer of the problem the address names.
$problems_open = $problems_ready ? pg_delivery_problem_get((int) ($_GET['problem'] ?? 0)) : null;
$problems_drawer = ($problems_open !== null) ? pg_delivery_problem_drawer($problems_open, $problems_filters, pg_delivery_tower_summary(), $problems_now) : '';

echo
pg_page_shell(array(
        'title'               => lang('Delivery problems'),
        'extra classes'       => 'ecommerce pg-measure-list',
        'icon'                => 'orders',
        'heading'             => lang('Delivery problems'),
        'heading_description' => lang('Parcels that are late, never handed over, in trouble or coming back, until somebody closes them.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    ' . pg_delivery_tower_segments_html('problems', $user, (ECOMMERCE == true) && (ECOMMERCE_SHIPPING == true)) . '
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    ' . pg_screen_toolbar(array(
        'search' => array($problems_segments, $problems_selects),
        'view'   => $problems_settings_button,
    )) . '
    ' . $problems_body . '
    ' . $problems_drawer . '
    ' . $problems_settings_drawer . '
</main>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
<script>
(function () {
    var phone = window.matchMedia("(max-width: 575.98px)");

    // A drawer opens through pgUI, from the right or, on a phone, as a
    // sheet from the bottom; one carrying data-open opens with the screen.
    var open = function (drawer) {
        if (!drawer || !window.pgUI) { return; }
        drawer.classList.toggle("drawer-end", !phone.matches);
        drawer.classList.toggle("drawer-bottom", phone.matches);
        pgUI.drawer.show(drawer);
    };

    // "Close the problem" swaps the foot: the reason and its note show, Save
    // gives its place to Close, so one filled button stays in view.
    var closing = function (drawer, on) {
        var panel = drawer.querySelector("[data-delivery-close-panel]");
        var toggle = function (selector, show) {
            var item = drawer.querySelector(selector);
            if (item) { item.hidden = !show; }
        };
        if (!panel) { return; }
        panel.hidden = !on;
        toggle("[data-delivery-close-open]", !on);
        toggle("[data-delivery-close-back]", on);
        toggle("[data-delivery-save]", !on);
        toggle("[data-delivery-close-submit]", on);
        if (on) {
            panel.scrollIntoView({ block: "end" });
            panel.querySelector("[data-delivery-reason]").focus();
        } else {
            drawer.querySelector("[data-delivery-close-open]").focus();
        }
    };

    document.addEventListener("click", function (event) {
        var opener = event.target.closest("[data-delivery-drawer]");
        var reason = event.target.closest("[data-delivery-reason]");
        var drawer = event.target.closest("dialog");

        if (opener) { open(document.getElementById(opener.getAttribute("data-delivery-drawer"))); }
        if (drawer && event.target.closest("[data-delivery-close-open]")) { closing(drawer, true); }
        if (drawer && event.target.closest("[data-delivery-close-back]")) { closing(drawer, false); }

        // A reason segment: pressed alone, its code in the hidden field.
        if (reason && drawer) {
            drawer.querySelectorAll("[data-delivery-reason]").forEach(function (item) {
                item.setAttribute("aria-pressed", (item === reason) ? "true" : "false");
            });
            drawer.querySelector("[data-delivery-reason-value]").value = reason.getAttribute("data-delivery-reason");
            drawer.querySelector("[data-delivery-reason-error]").hidden = true;
        }

        // Close without a reason is held back with the line that asks for one.
        var submit = event.target.closest("[data-delivery-close-submit]");
        if (submit && drawer && (drawer.querySelector("[data-delivery-reason-value]").value === "0")) {
            event.preventDefault();
            drawer.querySelector("[data-delivery-reason-error]").hidden = false;
            drawer.querySelector("[data-delivery-reason]").focus();
        }
    });

    // The Bootstrap module loads after this script: the drawer the address
    // names opens once pgUI says it is ready.
    if (window.pgUI) {
        pgUI.ready(function () {
            document.querySelectorAll("dialog[data-open]").forEach(open);
        });
    }
})();
</script>
' .
output_footer();

$liveform->remove_form();
