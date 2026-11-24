<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Orders › Pack: the check of what goes into a parcel. First one large
 * field, "Scan the order's code", kept in focus, a camera button beside it
 * and "or pick an address" under it. An address open: its order number,
 * ship-by day and "7 of 9 units", the lines as cards, and the same field
 * for each product scanned; a refused scan turns the field's row red with
 * the reason. Every unit scanned closes the pack: the label, the
 * marketplace package and order.packed follow (includes/fn/pack.php), and
 * "Next order" waits in focus. "Short…" opens a drawer of the lines not
 * full and closes the pack short, a location's count set to what was found
 * where the packer asks; "Finish the pack" opens it again. Scans are posted to this file with
 * request=… and answered in JSON (assets/js/pack.js). Manager and up, the
 * orders right or the "Packing only" right; no price, total or e-mail.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/pack.php');

$request = (isset($_POST['request']) && is_string($_POST['request'])) ? $_POST['request'] : '';

/**
 * Sends one JSON answer and stops.
 *
 * @param array $data
 * @param int   $status
 */
function pack_json(array $data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    exit();
}

// A script waiting for JSON gets its refusal in JSON, not the login page.
if (($request !== '') && !USER_LOGGED_IN) {
    pack_json(array('ok' => false, 'message' => lang('Your session has expired. Reload the page and sign in again.')), 401);
}

$user = validate_user();

if (($request !== '') && (!pg_pick_user_can($user) || !pg_pick_waves_ready())) {
    pack_json(array('ok' => false, 'message' => lang('Access denied.')), 403);
}

pg_pick_validate_access($user);

$pack_count_text = function (array $pack) {
    return lang(array('string' => '{var:1} of {var:2} units', 'vars' => array((int) $pack['scanned'], (int) $pack['units'])));
};

if ($request !== '') {
    $post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';
    $session_token = (string) ($_SESSION['software']['token'] ?? '');

    if (($session_token === '') || !hash_equals($session_token, $post_token)) {
        pack_json(array('ok' => false, 'message' => lang('Your session expired. Reload the page and try again.')), 403);
    }

    $code = (isset($_POST['code']) && is_string($_POST['code'])) ? $_POST['code'] : '';

    if ($request === 'find') {
        $ids = pg_pack_find_code($code);

        if (!$ids) {
            pack_json(array('ok' => false, 'message' => lang('No paid order waiting to be sent has this code.')));
        }

        // An order sent to two addresses is two parcels: the packer picks one.
        $choices = array();

        foreach ($ids as $index => $id) {
            $choices[] = array('url' => 'pack.php?ship_to=' . $id, 'label' => lang(array('string' => 'Address {var:1}', 'vars' => array($index + 1))));
        }

        pack_json(array('ok' => true, 'url' => (count($ids) === 1) ? $choices[0]['url'] : '', 'choices' => $choices));
    }

    if ($request === 'scan') {
        $ship_to_id = (isset($_POST['ship_to']) && is_string($_POST['ship_to']) && ctype_digit($_POST['ship_to'])) ? (int) $_POST['ship_to'] : 0;
        $address = pg_pack_address($ship_to_id);

        if (!$address) {
            pack_json(array('ok' => false, 'message' => lang('This address can no longer be packed: its order was cancelled or changed.')), 404);
        }

        $result = pg_pack_scan($address, $code, (int) $user['id']);

        if (!$result['ok']) {
            pack_json(array(
                'ok' => false,
                'reload' => ($result['error'] === 'closed'),
                'message' => ($result['error'] === 'closed') ? lang('This pack is closed already.') : lang('Another scan of this address is being counted. Scan again.'),
            ), 409);
        }

        $verdict = $result['verdict'];
        $line = ($verdict['line'] !== null && isset($result['pack']['lines'][$verdict['line']])) ? $result['pack']['lines'][$verdict['line']] : null;

        pack_json(array(
            'ok' => true,
            'good' => ($verdict['verdict'] === 'good'),
            'text' => pg_pack_verdict_text($verdict, $result['refusal']),
            'line' => ($verdict['verdict'] === 'good') ? (int) $verdict['line'] : null,
            'scanned' => $line ? (int) $line['scanned'] : 0,
            'count' => $pack_count_text($result['pack']),
            'done' => $result['closed'] ? pg_pack_after_packed($address, $result['pack'], (int) $user['id']) : null,
        ));
    }

    if ($request === 'short' || $request === 'reopen') {
        $ship_to_id = (isset($_POST['ship_to']) && is_string($_POST['ship_to']) && ctype_digit($_POST['ship_to'])) ? (int) $_POST['ship_to'] : 0;
        $address = pg_pack_address($ship_to_id);

        if (!$address) {
            pack_json(array('ok' => false, 'message' => lang('This address can no longer be packed: its order was cancelled or changed.')), 404);
        }

        // "Finish the pack": the scans are kept, the missing units scanned.
        if ($request === 'reopen') {
            pg_pack_reopen($address);
            pack_json(array('ok' => true, 'url' => 'pack.php?ship_to=' . (int) $address['ship_to_id']));
        }

        $result = pg_pack_close_short($address, (int) $user['id']);

        if (!$result['ok']) {
            $messages = array(
                'busy' => lang('Another scan of this address is being counted. Try again.'),
                'closed' => lang('This pack is closed already.'),
                'not_short' => lang('No unit is missing from this pack.'),
            );

            pack_json(array('ok' => false, 'reload' => ($result['error'] !== 'busy'), 'message' => $messages[$result['error']]), 409);
        }

        $pack = $result['pack'];
        $fixes = pg_pack_count_fixes($_POST['on'] ?? array(), $_POST['found'] ?? array(), $pack['missing'], pg_pack_tracked_products(array_keys($pack['missing'])));
        $rows = array(array('tone' => 'amber', 'text' => lang(array('string' => '{var:1} of {var:2} units in the box; the address waits for the rest.', 'vars' => array((int) $pack['scanned'], (int) $pack['units'])))));

        pack_json(array('ok' => true, 'done' => array_merge($rows, pg_pack_after_short($address, $pack, $fixes, (int) $user['id']))));
    }

    pack_json(array('ok' => false, 'message' => lang('Unknown request.')), 400);
}

$texts = array(
    'scan' => lang('Scan with camera'),
    'stop' => lang('Stop camera'),
    'aim' => lang('Hold the barcode in front of the camera.'),
    'no_reader' => lang('This browser cannot read barcodes with the camera. Type the code instead.'),
    'no_camera' => lang('The camera could not be opened. Allow it for this site, or type the code.'),
    'failed' => lang('No answer came. Check the connection, then scan again.'),
    'print' => lang('Print the label'),
    'packed' => lang('Packed'),
    'next' => lang('Next order'),
    'short' => lang('Short'),
    'missing' => lang('{var:1} missing'),
);
$token = h((string) ($_SESSION['software']['token'] ?? ''));

// The scan field and its camera, the same in both states.
$scan_field = function ($label, $help) use ($texts) {
    return '
        <form class="pg-pack-scan" data-pg-pack-scan autocomplete="off">
            <label class="pg-field-label" for="pack_code">' . h($label) . '</label>
            <div class="pg-pack-scan-row">
                <input type="text" class="pg-input pg-pack-code" id="pack_code" spellcheck="false" autocapitalize="off" autofocus aria-describedby="pack_answer">
                <button type="button" class="pg-icon-button" data-pg-pack-camera aria-label="' . h($texts['scan']) . '"><span class="bi bi-camera" aria-hidden="true"></span></button>
            </div>
            <p class="pg-field-help" id="pack_answer" role="status" aria-live="assertive">' . h($help) . '</p>
            <video class="pg-pack-video d-none" muted playsinline aria-label="' . h(lang('Camera')) . '"></video>
        </form>';
};

/**
 * The "Short…" drawer: a row for each line, those full hidden by the script
 * when it opens, each with its missing units and, for a product whose stock
 * is counted, the switch that sets the location's count to what was found.
 *
 * @param array $pack pg_pack_read()
 * @return string HTML
 */
function pack_short_drawer(array $pack)
{
    $tracked = pg_pack_tracked_products(array_column($pack['lines'], 'product_id'));
    $rows = '';

    foreach ($pack['lines'] as $index => $line) {
        $left = pg_pack_line_left($line);
        $id = (int) $line['product_id'];
        $fix = '';

        if ($id > 0 && in_array($id, $tracked, true)) {
            $fix = '
                <div class="d-flex align-items-center gap-3 mt-2">
                    <input type="checkbox" class="pg-switch" id="pack_fix_' . $id . '" name="on[' . $id . ']" value="1" aria-describedby="pack_fix_' . $id . '_help">
                    <label class="pg-text-2 flex-grow-1" for="pack_fix_' . $id . '">' . h(lang('Set this location\'s count to what was found')) . '</label>
                    <label class="visually-hidden" for="pack_found_' . $id . '">' . h(lang('Units found')) . '</label>
                    <input type="number" class="pg-input pg-pack-found" id="pack_found_' . $id . '" name="found[' . $id . ']" value="0" min="0" max="' . PG_PACK_COUNT_MAX . '" step="1" inputmode="numeric">
                </div>
                <div class="pg-field-help" id="pack_fix_' . $id . '_help">' . h(lang('The units still on the shelf.')) . '<br>' . h(lang('The units of orders waiting to be sent are taken off.')) . '</div>';
        }

        $rows .= '
            <li class="pg-card pg-look-pad' . (($left === 0) ? ' d-none' : '') . '" data-pg-pack-short-line="' . (int) $index . '">
                <span class="pg-list-row-title">' . h($line['name']) . '</span>
                ' . ($line['options'] ? '<strong class="pg-text-2 d-block">' . h(implode(' · ', $line['options'])) . '</strong>' : '') . '
                <span class="pg-text-meta d-block" data-pg-pack-short-left>' . h(lang(array('string' => '{var:1} missing', 'vars' => array($left)))) . '</span>
                ' . $fix . '
            </li>';
    }

    return '
    <dialog class="drawer drawer-end" id="pack_short" aria-labelledby="pack_short_title" style="width:min(480px,100%);--pg-drawer-height:80vh">
        <form class="d-flex flex-column h-100" data-pg-pack-short>
            <div class="drawer-header">
                <div class="flex-grow-1 min-w-0">
                    <h5 class="drawer-title mb-0" id="pack_short_title">' . h(lang('Units missing')) . '</h5>
                    <p class="small fg-2 mb-0 mt-1">' . h(lang('The pack closes with these units missing and the address waits until it is finished.')) . '</p>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="drawer" aria-label="' . h(lang('Close')) . '"></button>
            </div>
            <div class="drawer-body flex-grow-1 overflow-auto">
                <ul class="pg-pack-lines">' . $rows . '</ul>
            </div>
            <div class="drawer-footer border-top p-5 d-flex align-items-center justify-content-end gap-3">
                <button type="button" class="pg-button pg-button-quiet" data-bs-dismiss="drawer">' . h(lang('Cancel')) . '</button>
                <button type="submit" class="pg-button pg-button-fill">' . h(lang('Close as short')) . '</button>
            </div>
        </form>
    </dialog>';
}

$ship_to_id = (isset($_GET['ship_to']) && is_string($_GET['ship_to']) && ctype_digit($_GET['ship_to'])) ? (int) $_GET['ship_to'] : 0;
$address = ($ship_to_id > 0) ? pg_pack_address($ship_to_id) : null;
$heading = lang('Pack');
$description = lang('Scan the order\'s code on its packing slip or pick list, then each product as it goes into the box.');
$foot = '';

if ($ship_to_id > 0 && !$address) {
    $body = '<div class="pg-card pg-look-pad"><p class="pg-text-2">' . h(lang('This address can no longer be packed: its order was cancelled or changed.')) . '</p>
        <a class="pg-button pg-button-fill" href="pack.php">' . h(lang('Next order')) . '</a></div>';
} elseif ($address) {
    $pack = pg_pack_read($address);
    $heading = lang(array('string' => 'Order {var:1}', 'vars' => array($address['order_number'])));
    $description = ($address['ship_by'] > 0)
        ? lang(array('string' => 'Ship by {var:1}', 'vars' => array(get_absolute_time(array('timestamp' => $address['ship_by'], 'type' => 'date', 'size' => 'short', 'format' => 'plain_text')))))
        : lang('Ship-by day not known');

    // A full line turns green and sinks to the end.
    $cards = '';
    $order = array_keys($pack['lines']);
    usort($order, function ($a, $b) use ($pack) {
        return array(pg_pack_line_left($pack['lines'][$a]) === 0, $a) <=> array(pg_pack_line_left($pack['lines'][$b]) === 0, $b);
    });

    foreach ($order as $index) {
        $line = $pack['lines'][$index];
        $full = (pg_pack_line_left($line) === 0);
        $image = ($line['image'] !== '')
            ? '<img class="pg-pick-image" src="' . h(pg_image_panel_thumb_url(PATH . encode_url_path($line['image']))) . '" alt="" loading="lazy" width="48" height="48">'
            : '<span class="pg-icon-tile pg-tone-gray pg-pick-image" aria-hidden="true"><span class="bi bi-box-seam"></span></span>';

        $cards .= '
            <li class="pg-card pg-pack-line' . ($full ? ' is-full' : '') . '" data-pg-pack-line="' . (int) $index . '" data-pg-pack-want="' . (int) $line['quantity'] . '" data-pg-pack-place="' . (int) $index . '">
                ' . $image . '
                <span class="pg-list-row-main">
                    <span class="pg-list-row-title">' . h($line['name']) . '</span>
                    ' . ($line['options'] ? '<strong class="pg-text-2">' . h(implode(' · ', $line['options'])) . '</strong>' : '') . '
                    <span class="pg-text-meta">' . h($line['code']) . '</span>
                </span>
                <span class="pg-figure-value pg-pack-units"><span data-pg-pack-scanned>' . (int) $line['scanned'] . '</span> / ' . (int) $line['quantity'] . '</span>
            </li>';
    }

    if ($pack['status'] === PG_PACK_OPEN) {
        $body = '
        <div class="pg-card pg-look-pad mb-4">
            <p class="pg-title-section" data-pg-pack-count>' . h($pack_count_text($pack)) . '</p>
            ' . $scan_field(lang('Scan each product'), lang('A product scanned or its code typed counts one unit.')) . '
        </div>
        <ul class="pg-pack-lines" data-pg-pack-lines>' . $cards . '</ul>';
        $foot = '<div class="pg-pack-foot">
            <button type="button" class="pg-button pg-button-quiet" data-pg-pack-short-open>' . h(lang('Short…')) . '</button>
            <a class="pg-button pg-button-quiet" href="pack.php">' . h(lang('Leave this order')) . '</a>
        </div>' . pack_short_drawer($pack);
    } elseif ($pack['status'] === PG_PACK_SHORT) {
        $missing = pg_pack_missing_decode($pack['missing']);
        $list = pg_pack_missing_list($missing, pg_pack_product_names(array_keys($missing)), lang('A removed product'));
        $body = '
        <div class="pg-card pg-look-pad mb-4">
            <span class="pg-pill pg-tone-rose">' . h(lang('Short')) . '</span>
            <p class="pg-text-2 mt-3">' . h(lang('This parcel was closed with units missing.')) . '</p>
            ' . ($list ? '<p class="pg-text-meta">' . h(lang(array('string' => 'Missing: {var:1}', 'vars' => array(implode(', ', $list))))) . '</p>' : '') . '
            <div class="pg-pack-foot">
                <button type="button" class="pg-button pg-button-quiet" data-pg-pack-reopen>' . h(lang('Finish the pack')) . '</button>
                <a class="pg-button pg-button-fill" href="pack.php" autofocus>' . h(lang('Next order')) . '</a>
            </div>
        </div>
        <ul class="pg-pack-lines">' . $cards . '</ul>';
    } else {
        $body = '
        <div class="pg-card pg-look-pad mb-4">
            <span class="pg-pill pg-tone-teal">' . h(lang('Packed')) . '</span>
            <p class="pg-text-2 mt-3">' . h(lang('This parcel is packed and checked.')) . '</p>
            <a class="pg-button pg-button-fill" href="pack.php" autofocus>' . h(lang('Next order')) . '</a>
        </div>
        <ul class="pg-pack-lines">' . $cards . '</ul>';
    }
} else {
    $options = '<option value="">' . h(lang('Choose…')) . '</option>';

    foreach (pg_pack_choices() as $choice) {
        $day = ($choice['ship_by'] > 0) ? get_absolute_time(array('timestamp' => $choice['ship_by'], 'type' => 'date', 'size' => 'short', 'format' => 'plain_text')) : lang('Not known');
        $options .= '<option value="' . (int) $choice['ship_to_id'] . '">'
            . h(lang(array('string' => 'Order {var:1}', 'vars' => array($choice['order_number']))) . ' · ' . $day . ' · ' . lang(array('string' => '{var:1} units', 'vars' => array($choice['units'])))) . '</option>';
    }

    $body = '
    <div class="pg-card pg-look-pad">
        ' . $scan_field(lang('Scan the order\'s code'), lang('The code is printed on the packing slip and the pick list; typing the order number works the same.')) . '
        <div class="pg-pack-choices" data-pg-pack-choices></div>
        <label class="pg-field-label mt-4" for="pack_pick">' . h(lang('or pick an address')) . '</label>
        <select class="pg-input" id="pack_pick" data-pg-pack-pick>' . $options . '</select>
    </div>';
}

echo pg_page_shell(array(
    'title' => lang('Pack'),
    'extra classes' => 'ecommerce',
    'icon' => 'orders',
    'heading' => h($heading),
    'heading_description' => $description,
    'breadcrumb' => array(
        array('label' => lang('Pack'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/pack.php'),
    ),
)) . '
<main id="content" class="container-fluid">
    <div class="pg-pack" id="pack" data-pg-pack data-token="' . $token . '" data-ship-to="' . ($address ? (int) $address['ship_to_id'] : 0) . '"
        data-texts="' . h(json_encode($texts, JSON_UNESCAPED_UNICODE)) . '">
        ' . $body . '
        ' . $foot . '
    </div>
</main>
<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/pack.js?v=' . filemtime(PG_FUNCTIONS_DIR . '/assets/js/pack.js') . '"></script>
' . output_footer();
