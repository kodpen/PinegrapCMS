<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - My meeting link: the signed-in booking staff member's own
 * "book a time with me" address (book_with.php), with Copy and its QR code,
 * one short address of the site that points at it, the services the link
 * offers, and the meeting address (an online meeting room) that bookings
 * made through the link show. Who is staff, their services and hours stay
 * the administrator's in Settings, Bookings.
 *
 * The link is made and checked in includes/fn/booking_links.php; a short
 * address is checked by the short links' own rules
 * (includes/forms/short_link_form.php, pg_short_link_redirect_columns()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
include_once('liveform.class.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();

require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

$viewer = ws_screen_gate($user);

// Only a booking staff member has a link; for anybody else the screen is
// not there, as its entry in the Workspace list is not.
$member = pg_booking_link_screen_member((int) $user['id']);

if ($member === null) {
    output_error(lang('Only a booking staff member has a meeting link. An administrator adds staff in Settings, Bookings.'), 404);
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/short_link_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/recycle_bin.php');
require_once(PG_FUNCTIONS_DIR . '/includes/explorer/short_links.php');

$liveform = new liveform('workspace_meeting_link');

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/workspace_meeting_link.php';

$user_id = (int) $user['id'];
$target = pg_booking_link_short_target($member, pg_booking_key(), PATH);
$short = pg_booking_link_short_link($target);

/* ---------------------------------------------------------------------------
   Writes
   --------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $liveform->add_fields_to_session();

    pg_liveform_single_values($liveform, array('meeting_action', 'name', 'meeting_url'));

    $action = (string) $liveform->get_field_value('meeting_action');

    if ($action === 'meeting') {

        $saved = pg_booking_link_meeting_url_save($user_id, (string) $liveform->get_field_value('meeting_url'));

        if ($saved['ok']) {
            $liveform->remove_form();
            $liveform = new liveform('workspace_meeting_link');
            $liveform->add_notice(($saved['url'] === '') ? lang('Your meeting address was removed.') : lang('Your meeting address was saved.'));
        } else {
            $liveform->mark_error('meeting_url', h($saved['error']));
        }

        header('Location: ' . $screen_url . '#ws-meeting-address');

        exit();

    }

    if (($action === 'short') && ($target !== '')) {

        // The short links' own checks: the name rule, a pasted address read
        // as its path, free across pages, files and other short links, and
        // no loop with the links already there.
        $name = pg_short_link_form_validate_name($liveform, $short ? (int) $short['id'] : null);

        $answer = array('columns' => array(), 'problem' => null);

        if ($liveform->check_form_errors() == false) {
            $answer = pg_short_link_redirect_columns(
                array('id' => $short ? (int) $short['id'] : 0, 'name' => $name, 'destination_type' => 'url', 'url' => $target),
                null,
                $short ? (int) ($short['redirect_code'] ?? 0) : 0);

            if ($answer['problem'] !== null) {
                $liveform->mark_error('name', h($answer['problem']['message']));
            }
        }

        if ($liveform->check_form_errors() == false) {

            $set = array_merge(array('name' => $name), $answer['columns']);
            $columns = array();
            $values = array();

            foreach ($set as $column => $value) {
                $columns[] = $column;
                $values[] = (string) $value;
            }

            if ($short) {

                db_q("UPDATE short_links SET " . implode(' = ?, ', $columns) . " = ?,
                    last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP()
                    WHERE id = ?", array_merge($values, array((string) $user_id, (string) (int) $short['id'])));

                log_activity(lang(array('string' => 'short link ({var:1}) was modified', 'vars' => $name)), $_SESSION['sessionusername']);

            } else {

                if (pg_short_link_modes_ready()) {
                    $columns[] = 'link_mode';
                    $values[] = 'permanent';
                }

                db_q("INSERT INTO short_links (" . implode(', ', $columns) . ", destination_type, url, tracking_code,
                        created_user_id, created_timestamp, last_modified_user_id, last_modified_timestamp)
                    VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", 'url', ?, '', ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())",
                    array_merge($values, array($target, (string) $user_id, (string) $user_id)));

                log_activity(lang(array('string' => 'short link ({var:1}) was created', 'vars' => $name)), $_SESSION['sessionusername']);

            }

            $liveform->remove_form();
            $liveform = new liveform('workspace_meeting_link');
            $liveform->add_notice(lang('Your short address was saved.'));
        }

        header('Location: ' . $screen_url . '#ws-meeting-short');

        exit();

    }

    if (($action === 'short_remove') && $short) {

        if (pg_short_link_recycle_ready()) {
            pg_short_link_to_bin((int) $short['id'], $user);

            log_activity(lang(array('string' => 'The short link, {var:1}, was moved to the Recycle Bin.', 'vars' => $short['name'])), $_SESSION['sessionusername']);
        } else {
            db_q("DELETE FROM short_links WHERE id = ?", array((string) (int) $short['id']));

            log_activity(lang(array('string' => 'short link ({var:1}) was deleted', 'vars' => $short['name'])), $_SESSION['sessionusername']);
        }

        $liveform->remove_form();
        $liveform = new liveform('workspace_meeting_link');
        $liveform->add_notice(lang('The short address was removed. Your meeting link still works.'));

    }

    header('Location: ' . $screen_url);

    exit();

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$bookable = pg_booking_link_bookable($member);
$link_url = $bookable ? pg_booking_link_url($member) : '';
$short_url = ($short && $link_url !== '') ? URL_SCHEME . HOSTNAME_SETTING . PATH . encode_url_path((string) $short['name']) : '';

list($all_services) = pg_booking_widget_data();
$services = pg_booking_link_screen_services($all_services, $member);

if ($liveform->field_in_session('meeting_url') == false) {
    $liveform->assign_field_value('meeting_url', pg_booking_link_meeting_url($user_id));
}

if (($liveform->field_in_session('name') == false) && $short) {
    $liveform->assign_field_value('name', (string) $short['name']);
}

// An address shown whole (it wraps, never cut off in a field) with a Copy
// button beside it, under it on a narrow screen.
$copy_field = function ($id, $label, $value) {
    return '
                <p class="form-label small fw-semibold mb-1" id="' . h($id) . '_label">' . h($label) . '</p>
                <div class="row g-3 align-items-start mb-3">
                    <div class="col-12 md:col">
                        <div class="form-control font-monospace text-break user-select-all" id="' . h($id) . '" aria-labelledby="' . h($id) . '_label">' . h($value) . '</div>
                    </div>
                    <div class="col-12 md:col-auto">
                        <button type="button" class="btn btn-outline theme-secondary ws-meeting-copy" data-copy="' . h($id) . '"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
                    </div>
                </div>';
};

$link_card = '';
$short_card = '';

if (!$bookable) {

    $link_card = '
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-person-video3 me-3" aria-hidden="true"></i>' . h(lang('Your meeting link')) . '</h2>
                <div class="alert theme-warning small mb-0">' . h(lang('You have no services to be booked for yet, so your link does not work. An administrator gives you services in Settings, Bookings.')) . '</div>
            </div>
        </div>';

} elseif ($link_url === '') {

    $link_card = '
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-person-video3 me-3" aria-hidden="true"></i>' . h(lang('Your meeting link')) . '</h2>
                <div class="alert theme-warning small mb-0">' . h(lang('This site has no encryption key, so it cannot make meeting links.')) . '</div>
            </div>
        </div>';

} else {

    // The QR code always carries the link itself: a short address can be
    // renamed or removed, and a printed code must keep working.
    $qr_text = $link_url;

    $link_card = '
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-person-video3 me-3" aria-hidden="true"></i>' . h(lang('Your meeting link')) . '</h2>
                <p class="small fg-secondary">' . h(lang('Send it to anyone who wants a time with you: they pick one of your services and a free time, and the booking lands on your Work Calendar.')) . '</p>
                ' . $copy_field('ws_meeting_link_url', lang('Your link'), $link_url) . '
                ' . (($short_url !== '') ? $copy_field('ws_meeting_short_url', lang('Your short address'), $short_url) : '') . '
                <span class="visually-hidden" role="status" aria-live="polite" id="ws_meeting_copy_status" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></span>
                <div class="d-flex flex-wrap align-items-center gap-5 mt-5">
                    <div class="d-inline-block p-3 bg-white rounded-3" id="ws_meeting_qr">' . pg_qr_svg($qr_text, 176, array('label' => lang('QR code for the link'), 'class' => 'd-block')) . '</div>
                    <div class="d-flex flex-column align-items-start gap-3">
                        <a class="btn btn-sm btn-outline theme-primary" href="' . h($link_url) . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang('Open the page')) . '</a>
                        <a class="btn btn-sm btn-outline theme-secondary" href="' . h(pg_qr_svg_data_uri($qr_text, 600)) . '" download="meeting-link-qr.svg"><i class="bi bi-qr-code me-1" aria-hidden="true"></i>' . h(lang('Download the QR code')) . '</a>
                    </div>
                </div>
            </div>
        </div>';

    $short_card = '
        <div class="card mb-5" id="ws-meeting-short">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-link-45deg me-3" aria-hidden="true"></i>' . h(lang('Short address')) . '</h2>
                <p class="small fg-secondary">' . h(lang('A name of your choice on this site that opens your link, easier to say and to type.')) . '</p>
                <form method="post" action="workspace_meeting_link.php" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="meeting_action" value="short">
                    <label class="form-label small fw-semibold" for="name">' . h(lang('Name')) . '</label>
                    <div class="font-monospace small fg-secondary text-break mb-1">' . h(HOSTNAME_SETTING . PATH) . '</div>
                    ' . $liveform->output_field(array('type' => 'text', 'name' => 'name', 'id' => 'name', 'class' => 'form-control mb-3', 'maxlength' => pg_short_link_name_max(), 'required' => 'required')) . '
                    <div class="d-flex flex-wrap gap-3">
                        <button type="submit" class="btn btn-solid theme-primary">' . h($short ? lang('Save') : lang('Make the short address')) . '</button>
                        ' . ($short ? '<button type="submit" class="btn btn-outline theme-danger" form="ws_meeting_short_remove"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>' . h(lang('Remove')) . '</button>' : '') . '
                    </div>
                </form>
                ' . ($short ? '<form method="post" action="workspace_meeting_link.php" id="ws_meeting_short_remove" class="disable_shortcut d-none" data-confirm="' . h(lang('The short address will stop working. Your meeting link stays as it is. Remove it?')) . '">
                    ' . get_token_field() . '
                    <input type="hidden" name="meeting_action" value="short_remove">
                </form>' : '') . '
            </div>
        </div>';

}

$meeting_card = '
        <div class="card mb-5" id="ws-meeting-address">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-camera-video me-3" aria-hidden="true"></i>' . h(lang('Meeting address')) . '</h2>
                <p class="small fg-secondary">' . h(lang('The address of your online meeting room (Zoom, Google Meet, Teams). Bookings made through your link show it in the confirmation, the reminders and the calendar file. Leave it empty for none.')) . '</p>
                <form method="post" action="workspace_meeting_link.php" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="meeting_action" value="meeting">
                    <label class="form-label small fw-semibold" for="meeting_url">' . h(lang('Meeting address')) . '</label>
                    ' . $liveform->output_field(array('type' => 'url', 'name' => 'meeting_url', 'id' => 'meeting_url', 'class' => 'form-control mb-3', 'maxlength' => '500', 'placeholder' => 'https://')) . '
                    <button type="submit" class="btn btn-solid theme-primary">' . h(lang('Save')) . '</button>
                </form>
            </div>
        </div>';

// The member's services: those on the link and those left out, with why.
$service_rows = '';

foreach ($services as $service) {
    $price = (int) $service['price'];
    $facts = array(lang(array('string' => '{var:1} min', 'vars' => (int) $service['length_minutes'])));

    if ($price > 0) {
        $facts[] = html_entity_decode(prepare_price_for_output($price, false, 0, 'plain_text'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $service_rows .= '
                    <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <span>
                            <span class="fw-semibold">' . h($service['name']) . '</span>
                            <span class="d-block small fg-secondary">' . h(implode(' · ', $facts)) . '</span>
                        </span>
                        ' . ($service['offered']
                            ? '<span class="badge theme-success">' . h(lang('On your link')) . '</span>'
                            : '<span class="badge theme-secondary" title="' . h(lang('A service with a deposit is booked through the booking form of the site, where the deposit is paid.')) . '">' . h(lang('Not on the link: it takes a deposit')) . '</span>') . '
                    </li>';
}

$services_card = '
        <div class="card mb-5">
            <div class="card-body">
                <h2 class="h6 mb-3"><i class="bi bi-list-check me-3" aria-hidden="true"></i>' . h(lang('Services on your link')) . '</h2>
                ' . (($service_rows !== '')
                    ? '<ul class="list-group mb-3">' . $service_rows . '
                </ul>'
                    : '<p class="small fg-secondary">' . h(lang('You have no services yet.')) . '</p>') . '
                <p class="small fg-secondary mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>' . h(lang('Your services, working hours and days off are set by an administrator in Settings, Bookings. The times offered are your free times: bookings and team events you are in are busy, your private plans are not.')) . '</p>
            </div>
        </div>';

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$liveform_messages = ws_screen_messages($liveform);

$liveform->remove_form();

echo pg_page_shell(array(
    'title'               => lang('My meeting link'),
    'extra classes'       => 'workspace workspace_meeting_link',
    'icon'                => 'workspace',
    'heading'             => lang('My meeting link'),
    'heading_description' => lang('An address where anyone can book a time with you.'),
)) . '

<main id="content" class="container-fluid p-0">
<div class="ws-shell">
<div class="ws-shell-main">
    ' . $liveform_messages . '
    <div class="row g-5">
        <div class="col-12 xl:col-7">
            ' . $link_card . '
            ' . $short_card . '
            ' . $meeting_card . '
        </div>
        <div class="col-12 xl:col-5">
            ' . $services_card . '
        </div>
    </div>
</div>
</div>
</main>

<script>
(function () {

    // The button keeps its label; only the icon changes and the status line
    // says whether the copy happened.
    document.addEventListener("click", function (e) {
        var copy = e.target.closest(".ws-meeting-copy");
        if (!copy) { return; }
        var field = document.getElementById(copy.dataset.copy);
        var status = document.getElementById("ws_meeting_copy_status");
        var icon = copy.querySelector("i");
        var done = function () {
            icon.className = "bi bi-check2 me-1";
            status.textContent = status.getAttribute("data-copied");
            setTimeout(function () {
                icon.className = "bi bi-clipboard me-1";
                status.textContent = "";
            }, 1500);
        };
        // Plain http has no clipboard API and a write can be refused: copy
        // the selected text the old way; when that fails too, leave it
        // selected and say so.
        var fallback = function () {
            var copied = false;
            var range = document.createRange();
            var selection = window.getSelection();
            range.selectNodeContents(field);
            selection.removeAllRanges();
            selection.addRange(range);
            try { copied = document.execCommand("copy"); } catch (error) {}
            if (copied) {
                done();
            } else {
                status.textContent = status.getAttribute("data-refused");
            }
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(field.textContent).then(done, fallback);
        } else {
            fallback();
        }
    });

    // Removing the short address asks first.
    document.addEventListener("submit", function (e) {
        var form = e.target;
        if ((form.id === "ws_meeting_short_remove") && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
        }
    });

})();
</script>' . output_footer();
