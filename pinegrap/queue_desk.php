<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A walk-in queue place's desk (the place's Today tab, and "My desk" in the
 * menu for a place's staff member): a staff member opens a desk for their
 * shift; then the now-serving block shows the number at the desk with Call
 * next, Call again, Not here, Send to another service and Finish, Pause and
 * Close desk in its menu, and the people waiting for the desk's services
 * below it. Roles 0-2 reach every place, a role-3 user the places whose
 * staff list names them. The live part reads itself again every 15
 * seconds (?part=live).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();

if (!pg_waiting_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

$qd_scope = pg_waiting_user_desk_scope($user);

if ($qd_scope === false) {
    output_error(lang('Access denied'), 403);
}

$qd_id = (int) ($_POST['place'] ?? $_GET['place'] ?? 0);

// "My desk" of a staff member at one place may come without the place.
if (($qd_id === 0) && is_array($qd_scope) && (count($qd_scope) === 1)) {
    $qd_id = $qd_scope[0];
}

$qd_place = ($qd_id > 0) ? pg_waiting_place_get($qd_id) : null;

if ($qd_place === null) {
    output_error(lang('The place was not found.'), 404);
}

if (!pg_waiting_desk_scope_allows($qd_scope, $qd_id)) {
    output_error(lang('Access denied'), 403);
}

$qd_self = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/queue_desk.php?place=' . $qd_id;
$qd_manager = ((int) $user['role'] <= 2);
$liveform = new liveform('queue_desk');
$qd_desks = pg_waiting_desks($qd_id);
$qd_desk = pg_waiting_desk_of_user($qd_desks, $user['id']);
$qd_current = $qd_desk ? pg_waiting_desk_current($qd_desk['id']) : null;
$qd_number = function ($ticket) {
    return pg_waiting_number($ticket['letter'], $ticket['number']);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_token_field();

    if (isset($_POST['submit_open'])) {
        if ($qd_desk) {
            $liveform->add_error(h(lang(array('string' => 'You already have the desk "{var:1}" open here.', 'vars' => array($qd_desk['name'])))));
        } elseif (pg_waiting_desk_open((int) $_POST['submit_open'], $qd_id, $user['id'])) {
            log_activity('queue desk ' . (int) $_POST['submit_open'] . ' at "' . $qd_place['name'] . '" was opened');
        } else {
            $liveform->add_error(h(lang('Someone else opened this desk first.')));
        }

        go($qd_self);
    }

    if (!$qd_desk) {
        $liveform->add_error(h(lang('Open a desk first.')));
        go($qd_self);
    }

    if (isset($_POST['submit_call_next'])) {
        $qd_called = pg_waiting_call_next($qd_place, $qd_desk, $user['id']);

        if ($qd_called) {
            $liveform->add_notice(h(lang(array('string' => 'Number {var:1} is called to {var:2}.', 'vars' => array($qd_number($qd_called), $qd_desk['name'])))));
        } else {
            $liveform->add_error(h(lang('Nobody is waiting for this desk.')));
        }
    } elseif (isset($_POST['submit_pause']) || isset($_POST['submit_resume'])) {
        pg_waiting_desk_set_state($qd_desk, isset($_POST['submit_pause']) ? 'paused' : 'open');
    } elseif (isset($_POST['submit_close'])) {
        if (pg_waiting_desk_set_state($qd_desk, 'closed')) {
            log_activity('queue desk "' . $qd_desk['name'] . '" at "' . $qd_place['name'] . '" was closed');
            $liveform->add_notice(h(lang(array('string' => 'The desk "{var:1}" is closed.', 'vars' => array($qd_desk['name'])))));
        } else {
            $liveform->add_error(h(lang('Finish the number at the desk before closing it.')));
        }
    } elseif (!$qd_current) {
        $liveform->add_error(h(lang('Nobody is at the desk now.')));
    } elseif (isset($_POST['submit_call_again'])) {
        pg_waiting_call_again($qd_current);
        $liveform->add_notice(h(lang(array('string' => 'Number {var:1} is called again.', 'vars' => array($qd_number($qd_current))))));
    } elseif (isset($_POST['submit_finish'])) {
        pg_waiting_finish($qd_current);
    } elseif (isset($_POST['submit_not_here'])) {
        $qd_outcome = pg_waiting_not_here($qd_current, $qd_place);

        if ($qd_outcome === 'moved_back') {
            $liveform->add_notice(h(lang(array('string' => 'Number {var:1} waits again, once.', 'vars' => array($qd_number($qd_current))))));
        } elseif ($qd_outcome === '') {
            $liveform->add_error(h(lang(array('string' => 'Not here is offered {var:1} minutes after the call.', 'vars' => array($qd_place['settings']['not_here_minutes'])))));
        }
    } elseif (isset($_POST['submit_transfer'])) {
        $qd_moved = pg_waiting_transfer($qd_current, $qd_place, (string) ($_POST['service'] ?? ''));

        if ($qd_moved !== '') {
            $liveform->add_notice(h(lang(array('string' => 'Number {var:1} now waits as {var:2}, at the time it joined.', 'vars' => array($qd_number($qd_current), $qd_moved)))));
        } else {
            $liveform->add_error(h(lang('Choose another service.')));
        }
    }

    go($qd_self);
}

$qd_now = time();
$qd_service_names = array_column($qd_place['services'], 'name', 'key');
$qd_waiting = pg_waiting_desk_waiting($qd_place, $qd_desk ? $qd_desk['services'] : null, $qd_now);
$qd_buttons = pg_waiting_desk_buttons($qd_desk ? $qd_desk['state'] : 'closed', $qd_current, $qd_now, (int) $qd_place['settings']['not_here_minutes'], count($qd_waiting));
$qd_pill_words = array('priority' => array(lang('Priority'), 'pg-tone-amber'), 'booked' => array(lang('Booked'), 'pg-tone-blue'), 'on_way' => array(lang('On the way'), 'pg-tone-teal'));
$qd_pills = function ($ticket) use ($qd_place, $qd_pill_words) {
    $html = '';

    foreach (pg_waiting_desk_pills($ticket, $qd_place['settings']['priority']) as $pill) {
        $word = $qd_pill_words[$pill[0]];
        $html .= '<span class="pg-pill ' . $word[1] . '">' . h(($pill[1] !== '') ? $word[0] . ' · ' . $pill[1] : $word[0]) . '</span>';
    }

    return $html;
};
$qd_button = function ($key, $label, $icon, $extra = '') use ($qd_buttons) {
    return '<button type="submit" form="qd_form" name="submit_' . $key . '" value="1" class="pg-button pg-button-' . (($key === 'call_next') ? 'fill' : 'quiet') . (($extra !== '') ? ' no-submit' : '') . '"' . ($qd_buttons[$key] ? '' : ' disabled') . $extra . '><i class="bi ' . $icon . '" aria-hidden="true"></i>' . h($label) . '</button>';
};

// The now-serving block: the number at the desk, or what the desk waits for.
$qd_live = '';

if ($qd_desk) {
    if ($qd_current) {
        $qd_detail = h(($qd_service_names[$qd_current['service']] ?? '') . ' · ' . $qd_desk['name']);
        $qd_body = '<div class="pg-figure-value pg-tabular">' . h($qd_number($qd_current)) . '</div>'
            . '<div class="pg-figure-label mb-3">' . $qd_detail . '</div>'
            . '<div class="d-flex flex-wrap align-items-center gap-2 mb-5">'
            . (((string) $qd_current['name'] !== '') ? '<strong class="pg-text-2">' . h($qd_current['name']) . '</strong>' : '')
            . $qd_pills($qd_current)
            . '<span class="pg-text-meta">' . h(lang(array('string' => 'Called {var:1} min ago', 'vars' => array(pg_waiting_minutes_since($qd_current['called_at'], $qd_now))))) . '</span></div>';
    } else {
        $qd_body = '<p class="pg-card-sub mb-5">' . h(($qd_desk['state'] === 'paused')
            ? lang('The desk is paused: nobody new is called to it.')
            : ($qd_waiting ? lang('Nobody is at the desk. Call the next person when you are ready.') : lang('Nobody is waiting for this desk.'))) . '</p>';
    }

    $qd_menu = '<span class="pg-menu-wrap"><button type="button" class="pg-icon-button" data-bs-toggle="menu" data-bs-container="body" aria-expanded="false" aria-label="' . h(lang('More actions')) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button><ul class="menu pg-menu-end">'
        . ($qd_buttons['resume']
            ? '<li><button type="submit" form="qd_form" name="submit_resume" value="1" class="menu-item"><i class="bi bi-play-circle me-3" aria-hidden="true"></i>' . h(lang('Resume')) . '</button></li>'
            : '<li><button type="submit" form="qd_form" name="submit_pause" value="1" class="menu-item"' . ($qd_buttons['pause'] ? '' : ' disabled') . '><i class="bi bi-pause-circle me-3" aria-hidden="true"></i>' . h(lang('Pause')) . '</button></li>')
        . '<li><button type="submit" form="qd_form" name="submit_close" value="1" class="menu-item"' . ($qd_buttons['close'] ? '' : ' disabled') . '><i class="bi bi-door-closed me-3" aria-hidden="true"></i>' . h(lang('Close desk')) . '</button></li></ul></span>';

    $qd_live .= '<section class="pg-card p-5 mb-5" aria-labelledby="qd_now_title">'
        . '<div class="d-flex align-items-center gap-3 mb-3"><h2 class="pg-title-section mb-0 flex-grow-1" id="qd_now_title">' . h(lang('Now serving')) . '</h2>'
        . (($qd_desk['state'] === 'paused') ? pg_waiting_place_pill('paused') : '') . '</div>'
        . $qd_body
        . '<div class="d-flex flex-wrap align-items-center gap-2">'
        . $qd_button('call_next', lang('Call next'), 'bi-megaphone')
        . $qd_button('call_again', lang('Call again'), 'bi-arrow-repeat')
        . $qd_button('not_here', lang('Not here'), 'bi-person-dash', ' data-confirm-content="' . h($qd_place['settings']['move_back'] ? lang('The person waits again, once; the next time they leave the queue.') : lang('The number leaves the queue as not here.')) . '"')
        . '<button type="button" class="pg-button pg-button-quiet" data-pg-queue-drawer="qd_transfer"' . ($qd_buttons['transfer'] && pg_waiting_transfer_services($qd_place['services'], $qd_current['service'] ?? '') ? '' : ' disabled') . '><i class="bi bi-arrow-left-right" aria-hidden="true"></i>' . h(lang('Send to another service')) . '</button>'
        . $qd_button('finish', lang('Finish'), 'bi-check2-circle')
        . '<span class="flex-grow-1"></span>' . $qd_menu . '</div></section>';
} else {
    // No desk of this person's open here: the place's desks, a closed one
    // opened for the shift.
    $qd_rows = '';

    foreach ($qd_desks as $desk) {
        $state = ($desk['state'] === 'open') ? lang(array('string' => 'Open · {var:1}', 'vars' => array($desk['person']))) : (($desk['state'] === 'paused') ? lang(array('string' => 'Paused · {var:1}', 'vars' => array($desk['person']))) : lang('Closed'));
        $qd_rows .= '<div class="pg-card-inner-row flex-wrap">'
            . '<span class="pg-icon-tile" aria-hidden="true"><span class="bi bi-person-workspace"></span></span>'
            . '<div class="flex-grow-1 min-width-0"><strong class="pg-list-row-title">' . h($desk['name']) . '</strong><div class="pg-text-meta">' . h(implode(', ', array_values(array_intersect_key($qd_service_names, array_flip($desk['services']))))) . '</div></div>'
            . '<span class="pg-text-2 text-nowrap">' . h($state) . '</span>'
            . (($desk['state'] === 'closed') ? '<button type="submit" form="qd_form" name="submit_open" value="' . (int) $desk['id'] . '" class="pg-button pg-button-quiet pg-button-sm"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>' . h(lang('Open this desk')) . '</button>' : '')
            . '</div>';
    }

    $qd_live .= '<section class="pg-card p-5 mb-5" aria-labelledby="qd_open_title"><h2 class="pg-title-section mb-0" id="qd_open_title">' . h(lang('Open this desk')) . '</h2>'
        . '<p class="pg-card-sub mb-5">' . h($qd_desks ? lang('Open the desk you work at for your shift; people are then called to it.') : lang('No desks yet. A manager adds them on the place\'s Desks tab.')) . '</p>'
        . ($qd_desks ? '<div class="pg-card-inner">' . $qd_rows . '</div>' : '') . '</section>';
}

// The people waiting, in calling order.
$qd_rows = '';

foreach ($qd_waiting as $ticket) {
    $qd_rows .= '<div class="pg-card-inner-row flex-wrap">'
        . '<strong class="pg-list-row-title pg-tabular text-nowrap" style="flex:0 0 6rem">' . h($qd_number($ticket)) . '</strong>'
        . '<span class="pg-text-2 text-truncate" style="flex:0 1 12rem">' . h($qd_service_names[$ticket['service']] ?? '') . '</span>'
        . '<span class="d-flex flex-wrap gap-2 flex-grow-1">' . $qd_pills($ticket) . '</span>'
        . '<span class="pg-text-meta text-nowrap">' . h(lang(array('string' => '{var:1} min', 'vars' => array(pg_waiting_minutes_since($ticket['joined_at'], $qd_now))))) . '</span>'
        . '</div>';
}

$qd_live .= '<section class="pg-card p-5 mb-5" aria-labelledby="qd_waiting_title">'
    . '<h2 class="pg-title-section mb-0" id="qd_waiting_title">' . h(lang('Waiting')) . ' <span class="pg-count">' . count($qd_waiting) . '</span></h2>'
    . '<p class="pg-card-sub mb-5">' . h($qd_waiting ? ($qd_desk ? lang('For this desk\'s services, in the order they are called.') : lang('Everyone waiting here, in the order they are called.')) : lang('Nobody is waiting.')) . '</p>'
    . ($qd_waiting ? '<div class="pg-card-inner">' . $qd_rows . '</div>' : '') . '</section>';

if (($_GET['part'] ?? '') === 'live') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo $qd_live;
    exit();
}

// Send to another service: the place's other services, the time joined kept.
$qd_choices = '';

foreach (pg_waiting_transfer_services($qd_place['services'], $qd_current['service'] ?? '') as $index => $service) {
    $qd_choices .= '<div class="d-flex align-items-center gap-2 mb-3"><input type="radio" class="pg-radio" id="qd_service_' . h($service['key']) . '" name="service" value="' . h($service['key']) . '"' . (($index === 0) ? ' checked' : '') . '><label class="pg-text-2" for="qd_service_' . h($service['key']) . '">' . h($service['letter'] . ' ' . $service['name']) . '</label></div>';
}

$qd_transfer = '<dialog class="drawer drawer-end" id="qd_transfer" aria-labelledby="qd_transfer_title" style="width:min(480px,100%);--pg-drawer-height:60vh">
        <form method="post" action="queue_desk.php" class="d-flex flex-column h-100">
            ' . get_token_field() . '<input type="hidden" name="place" value="' . $qd_id . '">
            <div class="drawer-header">
                <div class="flex-grow-1 min-width-0">
                    <h5 class="drawer-title mb-0" id="qd_transfer_title">' . h(lang('Send to another service')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('The person waits there with a new number and keeps the time they joined.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">' . $qd_choices . '</div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center gap-3"><span class="flex-grow-1"></span>
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" name="submit_transfer" value="1" class="pg-button pg-button-fill">' . h(lang('Send')) . '</button>
            </div>
        </form>
    </dialog>';

// A manager reaches the desk as the place's Today tab.
$qd_tabs = $qd_manager
    ? '<span class="pg-seg" role="group" aria-label="' . h(lang('Place')) . '"><a class="pg-seg-item" href="edit_queue_place.php?id=' . $qd_id . '">' . h(lang('Set-up')) . '</a><a class="pg-seg-item" href="edit_queue_place.php?id=' . $qd_id . '&amp;view=desks">' . h(lang('Desks')) . ' <span class="pg-seg-count">' . count($qd_desks) . '</span></a><a class="pg-seg-item is-active" aria-current="page" href="queue_desk.php?place=' . $qd_id . '">' . h(lang('Today')) . '</a></span>'
    : '';

echo pg_page_shell(array(
    'title' => $qd_desk ? $qd_desk['name'] . ' · ' . $qd_place['name'] : $qd_place['name'],
    'extra classes' => 'calendar pg-measure-edit',
    'icon' => 'calendar',
    'help' => 'calendars',
    // output_header() prints the heading as markup; the names are staff text.
    'heading' => h($qd_desk ? $qd_desk['name'] . ' · ' . $qd_place['name'] : $qd_place['name']) . ' ' . pg_waiting_place_pill($qd_place['status']),
    'heading_description' => $qd_desk ? lang('Call people to your desk one by one.') : lang('Where people take a number and wait to be called.'),
    'breadcrumb' => $qd_manager
        ? array(array('label' => lang('Queue'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_queue_places.php'), array('label' => $qd_place['name'], 'url' => 'edit_queue_place.php?id=' . $qd_id), array('label' => lang('Today')))
        : array(array('label' => lang('My desk'))),
)) . '
<main id="content" class="container-fluid">
    ' . $liveform->get_messages() . '
    ' . (($qd_tabs !== '') ? pg_screen_toolbar(array('view' => $qd_tabs)) : '') . '
    <form id="qd_form" action="queue_desk.php" method="post">' . get_token_field() . '<input type="hidden" name="place" value="' . $qd_id . '"></form>
    <div data-pg-desk-live="queue_desk.php?place=' . $qd_id . '&amp;part=live" aria-live="polite">' . $qd_live . '</div>
    ' . $qd_transfer . '
</main>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/queue_place.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/queue_place.js') . '"></script>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/queue_desk.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/queue_desk.js') . '"></script>
' . output_footer();

$liveform->remove_form();
