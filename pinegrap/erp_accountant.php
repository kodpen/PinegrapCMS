<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - the accountant's pack.
 *
 * One screen for what goes to the accountant: build a period's pack, download
 * it or send the accountant a link to it, say where the monthly pack goes, and
 * lock a period once it has been filed. The pack itself is built in
 * includes/erp/accountant.php, the lock lives in includes/erp/lock.php.
 *
 * Whoever may use the ERP builds and downloads packs, the read-only right (the
 * accountant's) included; sending a link needs an account that may change
 * things, and the settings and the lock need the ERP settings right.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user, 'accountant')) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_accountant');

$self_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accountant.php';
$readonly = defined('USER_ERP_READONLY') && USER_ERP_READONLY;
$can_settings = defined('USER_MANAGE_ERP_SETTINGS') && USER_MANAGE_ERP_SETTINGS && !$readonly;
$can_cash = defined('USER_MANAGE_ERP_CASH') && USER_MANAGE_ERP_CASH;
$cash_refusal = lang('This pack carries the till sheets: downloading or sending it needs the right to see the tills.');

// ---------------------------------------------------------------- download
// One number: an array (?download[]=x) cast to 1 and sent the first pack;
// it is not given, so the screen opens as usual.
$download_id = is_scalar($_GET['download'] ?? null) ? (int) $_GET['download'] : null;

if ($download_id !== null) {
    $package = erp_accountant_package($download_id);

    // Any pack can be asked for by its number: one built by someone who sees
    // the tills (or by the monthly job) is not handed to someone who does not.
    if (is_array($package) && !$can_cash && erp_accountant_package_has_cash($package)) {
        $liveform->mark_error('_error', $cash_refusal);
        go($self_url);
    }

    if (!is_array($package) || !erp_accountant_stream($package)) {
        $liveform->mark_error('_error', lang('That pack is no longer there. Build it again.'));
        go($self_url);
    }

    erp_accountant_count_download((int) $package['id']);

    if (function_exists('log_activity')) {
        log_activity(lang(array(
            'string' => 'Accountant pack for {var:1} downloaded.',
            'vars' => erp_profit_period_title((string) $package['period_from'], (string) $package['period_to']),
        )));
    }

    exit();
}

// ------------------------------------------------------------------ actions
if ($_POST) {
    validate_token_field();

    // Every field of the forms is one value. One posted as an array counts as
    // not given: (int) read an array pack as pack 1 and linked or e-mailed
    // that one, (string) read the dates and the address as "Array".
    pg_request_single_values(array('erp_action', 'range', 'month', 'from', 'to', 'pdfs', 'package_id', 'accountant_email', 'accountant_monthly',
        'accountant_pdfs', 'lock_date'));

    $action = (string) ($_POST['erp_action'] ?? '');

    if ($action === 'build') {
        $period = erp_accountant_period(array(
            'month' => ((string) ($_POST['range'] ?? 'month') === 'month') ? (string) ($_POST['month'] ?? '') : '',
            'from' => (string) ($_POST['from'] ?? ''),
            'to' => (string) ($_POST['to'] ?? ''),
        ));

        if ($period['error'] !== '') {
            $liveform->mark_error('month', $period['error']);
            go($self_url);
        }

        $built = erp_accountant_build($period['from'], $period['to'], array(
            'pdfs' => !empty($_POST['pdfs']),
            // The till sheets are for someone who may see the tills.
            'cash' => $can_cash,
            'created_by' => (int) $user['id'],
        ));

        if (!$built['success']) {
            $liveform->mark_error('_error', h($built['error']));
            go($self_url);
        }

        $period_title = erp_profit_period_title($period['from'], $period['to']);

        if (function_exists('log_activity')) {
            log_activity(lang(array(
                'string' => !empty($_POST['pdfs']) ? 'Accountant pack for {var:1} built with the documents.' : 'Accountant pack for {var:1} built without the documents.',
                'vars' => $period_title,
            )));
        }

        $liveform->add_notice(h(lang(array(
            'string' => 'The pack for {var:1} is ready.',
            'vars' => $period_title,
        ))) . ' <a class="alert-link" href="' . h($self_url . '?download=' . (int) $built['id']) . '">' . lang('Download') . '</a>');

        foreach ($built['warnings'] as $warning) {
            $liveform->add_warning(h($warning));
        }

        go($self_url);
    }

    if ($action === 'link') {
        if ($readonly) {
            $liveform->mark_error('_error', lang('Your account can read the ERP but not change anything in it.'));
            go($self_url);
        }

        $package = erp_accountant_package((int) ($_POST['package_id'] ?? 0));

        if (!is_array($package) || !$package['exists']) {
            $liveform->mark_error('_error', lang('That pack is no longer there. Build it again.'));
            go($self_url);
        }

        if (!$can_cash && erp_accountant_package_has_cash($package)) {
            $liveform->mark_error('_error', $cash_refusal);
            go($self_url);
        }

        $link = erp_accountant_new_link((int) $package['id']);

        if ($link === '') {
            $liveform->mark_error('_error', lang('The link could not be made.'));
            go($self_url);
        }

        // Only the hash is kept, so the link is shown this once.
        $_SESSION['software']['erp_accountant_link'] = array(
            'id' => (int) $package['id'],
            'url' => $link,
            'expires' => time() + ERP_ACCOUNTANT_LINK_DAYS * 86400,
        );

        if (function_exists('log_activity')) {
            log_activity(lang(array(
                'string' => 'A new link was made for the accountant pack of {var:1}.',
                'vars' => erp_profit_period_title((string) $package['period_from'], (string) $package['period_to']),
            )));
        }

        go($self_url);
    }

    if ($action === 'send') {
        if ($readonly) {
            $liveform->mark_error('_error', lang('Your account can read the ERP but not change anything in it.'));
            go($self_url);
        }

        $to = trim((string) ($_POST['to'] ?? ''));
        $package = erp_accountant_package((int) ($_POST['package_id'] ?? 0));

        if (is_array($package) && !$can_cash && erp_accountant_package_has_cash($package)) {
            $liveform->mark_error('_error', $cash_refusal);
            go($self_url);
        }

        $sent = erp_accountant_send((int) ($_POST['package_id'] ?? 0), $to, (int) $user['id']);

        if (!$sent['success']) {
            $liveform->mark_error('_error', h($sent['error']));
            go($self_url);
        }

        $liveform->add_notice(h(lang(array('string' => 'A link to the pack was e-mailed to {var:1}.', 'vars' => $to))));
        go($self_url);
    }

    if (in_array($action, array('settings', 'lock', 'unlock'), true)) {
        if (!$can_settings) {
            $liveform->mark_error('_error', lang('Changing this needs the right to change ERP settings.'));
            go($self_url);
        }

        if ($action === 'settings') {
            $saved = erp_accountant_save_settings(array(
                'email' => (string) ($_POST['accountant_email'] ?? ''),
                'monthly' => !empty($_POST['accountant_monthly']),
                'pdfs' => !empty($_POST['accountant_pdfs']),
            ));

            if (!$saved['success']) {
                // Keep the typed address and both switches, so the card shows
                // what was refused instead of the stored settings.
                $liveform->add_fields_to_session();
                $liveform->mark_error('accountant_email', h($saved['error']));
                go($self_url);
            }

            $liveform->add_notice(lang('The accountant\'s settings were saved.'));
            go($self_url);
        }

        // An empty date on Lock is a mistake, not a request to lift the lock:
        // erp_lock_set() reads an empty date as lifting it, which only the
        // Lift the lock button asks for.
        if (($action === 'lock') && (trim((string) ($_POST['lock_date'] ?? '')) === '')) {
            $liveform->mark_error('lock_date', lang('Enter the lock date as a date.'));
            go($self_url);
        }

        $locked = erp_lock_set(($action === 'lock') ? (string) ($_POST['lock_date'] ?? '') : '', (int) $user['id']);

        if (!$locked['success']) {
            $liveform->mark_error('lock_date', h($locked['error']));
            go($self_url);
        }

        $liveform->add_notice(($action === 'lock')
            ? h(lang(array('string' => 'The books are locked up to {var:1}.', 'vars' => prepare_form_data_for_output(erp_lock_date(), 'date', false))))
            : lang('The period lock was lifted.'));
        go($self_url);
    }

    go($self_url);
}

// ------------------------------------------------------------------ display
$ready = erp_accountant_ready();

if (!$ready) {
    $liveform->mark_error('', lang('The accountant pack comes with the software update; run the update to use it.'));
}

$settings = erp_accountant_settings();
$last_month = erp_accountant_last_month();
$packages = erp_accountant_packages(36);
$lock_date = function_exists('erp_lock_date') ? erp_lock_date() : '';

// The months offered: the last eighteen, the latest closed month first.
$output_months = '';
for ($i = 1; $i <= 18; $i++) {
    $month = date('Y-m', strtotime(date('Y-m-01') . ' -' . $i . ' month'));
    $label = get_month_name_from_number(substr($month, 5, 2)) . ' ' . substr($month, 0, 4);
    $output_months .= '<option value="' . h($month) . '"' . (($i === 1) ? ' selected' : '') . '>' . h($label) . '</option>';
}

$money = function ($kurus) {
    return h(erp_money_out((int) $kurus));
};

// A link made a moment ago, shown once.
$output_link = '';
$new_link = $_SESSION['software']['erp_accountant_link'] ?? null;
unset($_SESSION['software']['erp_accountant_link']);

if (is_array($new_link) && !empty($new_link['url'])) {
    // Shown in a window that opens by itself, like the File Manager's
    // one-time link. The QR code is drawn here as inline SVG so the token
    // never goes into an image URL; the download is a data: URI for the same
    // reason. Closing the window leaves nothing behind: the session copy was
    // spent above.
    $link_url = (string) $new_link['url'];
    $link_qr_options = array('label' => lang('QR code for the link'), 'class' => 'd-block w-100');
    $link_qr = pg_qr_svg($link_url, 0, $link_qr_options);

    $output_link_qr = ($link_qr !== '')
        ? '<div class="text-center mb-5"><div class="d-inline-block border rounded-4 p-3 bg-white" style="width:200px">' . $link_qr . '</div></div>'
        : '';

    $output_link_download = ($link_qr !== '')
        ? '<a class="small" download="accountant-pack-qr.svg" href="' . h(pg_qr_svg_data_uri($link_url, 0, $link_qr_options)) . '"><i class="bi bi-download me-1" aria-hidden="true"></i>' . h(lang('Download SVG')) . '</a>'
        : '';

    $output_link = '
            <dialog class="dialog pg-dialog-centered" id="erp_accountant_link_modal" aria-labelledby="erp_accountant_link_modal_label">
                        <div class="dialog-header">
                            <h5 class="dialog-title" id="erp_accountant_link_modal_label"><i class="bi bi-link-45deg me-3" aria-hidden="true"></i>' . h(lang('Link')) . '</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                        </div>
                        <div class="dialog-body">
                            <p class="mb-5">' . h(lang(array(
                                'string' => 'A new link to the pack. It works until {var:1}, without a login; the link made before it no longer works.',
                                'vars' => prepare_form_data_for_output(date('Y-m-d', (int) $new_link['expires']), 'date', false),
                            ))) . '</p>
                            ' . $output_link_qr . '
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="erp_accountant_link" value="' . h($link_url) . '" readonly aria-label="' . h(lang('Link')) . '" />
                                <button type="button" class="btn btn-outline theme-primary" id="erp_accountant_link_copy"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . h(lang('Copy')) . '</button>
                            </div>
                            <span class="visually-hidden" role="status" aria-live="polite" id="erp_accountant_link_status" data-copied="' . h(lang('Copied')) . '" data-refused="' . h(lang('The browser did not let the text be copied. Select it and copy it from here.')) . '"></span>
                            <div class="form-text">' . h(lang('It is shown only now. Anyone who has it can download the pack, so send it to the accountant only.')) . '</div>
                            ' . (($output_link_download !== '') ? '<div class="mt-3">' . $output_link_download . '</div>' : '') . '
                        </div>
                        <div class="dialog-footer">
                            <button type="button" class="btn btn-solid theme-primary" data-bs-dismiss="dialog">' . h(lang('Done')) . '</button>
                        </div>
            </dialog>
            <script>
            (function () {
                var modal = document.getElementById("erp_accountant_link_modal");
                var button = document.getElementById("erp_accountant_link_copy");
                var input = document.getElementById("erp_accountant_link");
                var status = document.getElementById("erp_accountant_link_status");
                if (!modal || !button || !input || !status) { return; }
                input.addEventListener("focus", function () { input.select(); });
                button.addEventListener("click", function () {
                    // Only the icon changes, so the focused button keeps its
                    // name; the live region says the copy happened.
                    var done = function () {
                        var icon = button.querySelector("i");
                        icon.className = "bi bi-check-lg me-1";
                        status.textContent = status.getAttribute("data-copied");
                        setTimeout(function () {
                            icon.className = "bi bi-clipboard me-1";
                            status.textContent = "";
                        }, 1500);
                    };
                    // Plain http has no clipboard API and a write can be
                    // refused: select the text and copy it the old way, and
                    // when that fails too, leave it selected and say so.
                    var fallback = function () {
                        var copied = false;
                        input.focus();
                        input.select();
                        try { copied = document.execCommand("copy"); } catch (e) {}
                        if (copied) {
                            done();
                        } else {
                            status.textContent = status.getAttribute("data-refused");
                        }
                    };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(input.value).then(done, fallback);
                        return;
                    }
                    fallback();
                });
                // The link exists only in this page; once the window is closed
                // it is taken out of the page as well.
                pgUI.dialog.on(modal, "hidden", function () {
                    pgUI.dialog.dispose(modal);
                    modal.parentNode.removeChild(modal);
                });
                // Waits for Bootstrap too: Bootstrap 6 loads as a deferred module.
                var open = function () { pgUI.ready(function () { pgUI.dialog.show(modal); }); };
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", open);
                } else {
                    open();
                }
            })();
            </script>';
}

$output_rows = '';

foreach ($packages as $package) {
    $summary = json_decode((string) $package['summary'], true);
    $summary = is_array($summary) ? $summary : array();
    $period_dates = prepare_form_data_for_output((string) $package['period_from'], 'date', false) . ' – ' . prepare_form_data_for_output((string) $package['period_to'], 'date', false);
    $period_title = erp_profit_period_title((string) $package['period_from'], (string) $package['period_to']);
    $file_exists = is_file(erp_accountant_directory() . '/' . basename((string) $package['file_name']));
    $cash_locked = !$can_cash && erp_accountant_package_has_cash($package);
    $sent = ((int) $package['sent_at'] > 0)
        ? '<span class="text-break">' . h((string) $package['sent_to']) . '</span><div class="small fg-secondary">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $package['sent_at']), 'date and time')) . '</div>'
        : '<span class="fg-secondary">—</span>';

    // Sending opens one shared window below the table, so the row stays as
    // narrow as its two buttons.
    if (!$file_exists) {
        $output_actions = '<span class="fg-secondary small">' . lang('The file is no longer there.') . '</span>';
    } elseif ($cash_locked) {
        $output_actions = '<span class="fg-secondary small">' . h(lang('Needs the right to see the tills.')) . '</span>';
    } else {
        $output_actions = '<div class="d-flex flex-wrap gap-1 justify-content-end">'
            . '<a class="btn btn-sm btn-outline theme-primary text-nowrap" href="' . h($self_url . '?download=' . (int) $package['id']) . '"><i class="bi bi-download me-1" aria-hidden="true"></i>' . lang('Download') . ' <span class="small fg-secondary">' . h(erp_accountant_size_text((int) $package['file_size'])) . '</span></a>'
            . (!$readonly
                ? '<button type="button" class="btn btn-sm btn-outline theme-secondary text-nowrap" data-erp-pack-send="' . (int) $package['id'] . '" data-erp-pack-period="' . h($period_title) . '"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Send link') . '</button>'
                : '')
            . '</div>';
    }

    $output_rows .= '
        <tr>
            <td class="align-middle" data-sort="' . h(str_replace('-', '', (string) $package['period_from'])) . '">'
                . (($period_title !== $period_dates) ? h($period_title) . '<div class="small fg-secondary">' . h($period_dates) . '</div>' : h($period_dates)) . '</td>
            <td class="align-middle" data-sort="' . (int) $package['created_at'] . '">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $package['created_at']), 'date and time'))
                . '<div class="small fg-secondary">' . ((string) $package['source'] === 'monthly' ? lang('Monthly, by itself') : h((string) ($package['created_by_name'] ?? ''))) . '</div></td>
            <td class="align-middle text-end" data-sort="' . (int) $package['documents'] . '">' . (int) $package['documents'] . '<div class="small fg-secondary">' . h(lang(array('string' => '{var:1} file(s)', 'vars' => (int) $package['files_added']))) . '</div></td>
            <td class="align-middle text-end text-nowrap" data-sort="' . (int) ($summary['output_vat'] ?? 0) . '">' . $money($summary['output_vat'] ?? 0) . '</td>
            <td class="align-middle text-end text-nowrap" data-sort="' . (int) ($summary['input_vat'] ?? 0) . '">' . $money($summary['input_vat'] ?? 0) . '</td>
            <td class="align-middle" data-sort="' . (int) $package['sent_at'] . '">' . $sent . '</td>
            <td class="align-middle text-end" data-sort="' . (int) $package['downloads'] . '">' . (int) $package['downloads'] . '</td>
            <td class="align-middle text-end">' . $output_actions . '</td>
        </tr>';
}

// One window for sending any pack: the row's button fills in which one.
$output_send_dialog = '';

if (!$readonly) {
    $output_send_dialog = '
            <dialog class="dialog pg-dialog-centered" id="erp_accountant_send_modal" aria-labelledby="erp_accountant_send_modal_label">
                <form method="post" action="erp_accountant.php">
                    ' . get_token_field() . '
                    <input type="hidden" name="package_id" id="erp_accountant_send_package" value="" />
                    <div class="dialog-header">
                        <h5 class="dialog-title" id="erp_accountant_send_modal_label"><i class="bi bi-send me-3" aria-hidden="true"></i>' . lang('Send link') . ' <span class="fg-secondary" id="erp_accountant_send_period"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' . h(lang('Close')) . '"></button>
                    </div>
                    <div class="dialog-body">
                        <label class="form-label" for="erp_accountant_send_to">' . lang('Accountant\'s e-mail') . '</label>
                        <input type="email" name="to" id="erp_accountant_send_to" class="form-control" value="' . h($settings['email']) . '" maxlength="255" required />
                        <div class="form-text">' . h(lang(array('string' => 'A link opens its pack without a login for {var:1} days. Send it by e-mail, or make one to copy into a message; each new link makes the one before it stop working.', 'vars' => ERP_ACCOUNTANT_LINK_DAYS))) . '</div>
                    </div>
                    <div class="dialog-footer">
                        <button type="submit" name="erp_action" value="link" formnovalidate class="btn btn-outline theme-secondary"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . lang('Make a link to copy') . '</button>
                        <button type="submit" name="erp_action" value="send" class="btn btn-solid theme-primary"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Send link') . '</button>
                    </div>
                </form>
            </dialog>
            <script>
            (function () {
                var modal = document.getElementById("erp_accountant_send_modal");
                if (!modal) { return; }
                document.addEventListener("click", function (event) {
                    var button = event.target.closest("[data-erp-pack-send]");
                    if (!button) { return; }
                    document.getElementById("erp_accountant_send_package").value = button.getAttribute("data-erp-pack-send");
                    document.getElementById("erp_accountant_send_period").textContent = button.getAttribute("data-erp-pack-period");
                    pgUI.ready(function () { pgUI.dialog.show(modal); });
                });
            })();
            </script>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="8" class="text-center fg-secondary py-7">' . lang('No pack has been built yet.') . '</td></tr>';
}

$lock_default = $lock_date;
if ($lock_default === '') {
    $lock_default = $last_month['to'];
}

$output_lock_state = ($lock_date !== '')
    ? '<div class="h5 mb-1"><i class="bi bi-lock-fill fg-warning me-3" aria-hidden="true"></i>' . h(lang(array('string' => 'Locked up to {var:1}', 'vars' => prepare_form_data_for_output($lock_date, 'date', false)))) . '</div>'
        . '<div class="form-text mt-0">' . lang('Documents and receipts dated on or before this day can no longer be issued, cancelled, returned or paid. A return made now is dated now, in the open period.') . '</div>'
    : '<div class="h5 mb-1"><i class="bi bi-unlock fg-secondary me-3" aria-hidden="true"></i>' . lang('No period is locked') . '</div>'
        . '<div class="form-text mt-0">' . lang('Lock a period once its VAT return and books have been filed, so nothing in it changes afterwards.') . '</div>';

// After a refused save the card shows what was sent; otherwise what is stored.
$settings_form = $settings;
if (!$liveform->prefill() && ((string) $liveform->get_field_value('erp_action') === 'settings')) {
    $settings_form['email'] = (string) $liveform->get_field_value('accountant_email');
    $settings_form['monthly'] = ((string) $liveform->get_field_value('accountant_monthly') !== '');
    $settings_form['pdfs'] = ((string) $liveform->get_field_value('accountant_pdfs') !== '');
}

// A refused address is marked on its box, with the reason under it.
$email_error = null;
if ($liveform->check_field_error('accountant_email')) {
    $email_error = (string) ($liveform->field_error_texts()['accountant_email'] ?? '');
}

$output_settings_card = '
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Accountant') . '</div>
                <div class="card-body">
                    <form method="post" action="erp_accountant.php">
                        ' . get_token_field() . '
                        <input type="hidden" name="erp_action" value="settings" />
                        <div class="row g-5">
                            <div class="col-12">
                                <label class="form-label" for="accountant_email">' . lang('Accountant\'s e-mail') . '</label>
                                <input type="email" class="form-control' . ($email_error !== null ? ' is-invalid' : '') . '" id="accountant_email" name="accountant_email" value="' . h($settings_form['email']) . '" maxlength="255" autocomplete="off"' . ($email_error !== null ? ' aria-invalid="true" aria-describedby="accountant_email_error"' : '') . ($can_settings ? '' : ' disabled') . ' />
                                ' . ($email_error !== null ? '<div class="invalid-feedback" id="accountant_email_error">' . h($email_error) . '</div>' : '') . '
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input class="" type="checkbox" id="accountant_monthly" name="accountant_monthly" value="1"' . ($settings_form['monthly'] ? ' checked' : '') . ($can_settings ? '' : ' disabled') . ' /></div>
                                    <label class="" for="accountant_monthly">' . lang('Send last month\'s pack by itself') . '</label>
                                </div>
                                <div class="form-text">' . h(lang(array('string' => 'On the first days of every month the scheduled job builds the month before and e-mails the accountant a link that works for {var:1} days.', 'vars' => ERP_ACCOUNTANT_LINK_DAYS))) . '</div>
                            </div>
                            <div class="col-12">
                                <div class="form-field">
                                    <div class="switch"><input class="" type="checkbox" id="accountant_pdfs" name="accountant_pdfs" value="1"' . ($settings_form['pdfs'] ? ' checked' : '') . ($can_settings ? '' : ' disabled') . ' /></div>
                                    <label class="" for="accountant_pdfs">' . lang('Put the documents in the monthly pack') . '</label>
                                </div>
                            </div>
                        </div>
                        ' . ($can_settings
                            ? '<button type="submit" class="btn btn-sm btn-solid theme-success mt-5"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>' . lang(array('string' => 'Save')) . '</button>'
                            : '<div class="form-text mt-5">' . lang('Changing this needs the right to change ERP settings.') . '</div>') . '
                    </form>
                </div>
            </div>';

$output_lock_card = '
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Period lock') . '</div>
                <div class="card-body">
                    ' . $output_lock_state . '
                    ' . ($can_settings ? '
                    <form method="post" action="erp_accountant.php" class="row g-3 align-items-end mt-3">
                        ' . get_token_field() . '
                        <div class="col-12 sm:col-7">
                            <label class="form-label" for="lock_date">' . lang('Lock up to and including') . '</label>
                            <input type="date" data-pg-date class="form-control" id="lock_date" name="lock_date" value="' . h($lock_default) . '" max="' . h(date('Y-m-d', strtotime('-1 day'))) . '" required />
                        </div>
                        <div class="col-12 sm:col-5 d-flex gap-3">
                            <button type="submit" name="erp_action" value="lock" class="btn btn-sm btn-solid theme-warning text-nowrap"><i class="bi bi-lock me-1" aria-hidden="true"></i>' . lang('Lock') . '</button>
                            ' . (($lock_date !== '') ? '<button type="submit" name="erp_action" value="unlock" formnovalidate class="btn btn-sm btn-outline theme-secondary text-nowrap">' . lang('Lift the lock') . '</button>' : '') . '
                        </div>
                    </form>' : '<div class="form-text">' . lang('Changing this needs the right to change ERP settings.') . '</div>') . '
                </div>
            </div>';

echo
pg_page_shell(array(
        'title' => lang('Accountant pack'),
        'extra classes' => 'erp erp_accountant pg-measure-edit pg-measure-single',
        'icon' => 'erp',
        'heading' => lang('Accountant pack'),
        'heading_description' => lang('A period\'s records for the accountant in one ZIP: a workbook of sales, purchases, returns, VAT by rate, receipts, balances and stock value, and the documents as they were issued.'),
        'cancel' => false,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_link . '
        </div>
        <div class="col-12 xl:col-8">
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Build a pack') . '</div>
                <div class="card-body">
                    <form method="post" action="erp_accountant.php">
                        ' . get_token_field() . '
                        <input type="hidden" name="erp_action" value="build" />
                        <div class="row g-5 align-items-end">
                            <div class="col-12 md:col-4">
                                <div class="form-field">
                                    <input class="radio" type="radio" name="range" id="range_month" value="month" checked />
                                    <label class="" for="range_month">' . lang('A month') . '</label>
                                </div>
                                <select class="form-control mt-1" id="month" name="month" aria-label="' . h(lang('A month')) . '">' . $output_months . '</select>
                            </div>
                            <div class="col-12 md:col-5">
                                <div class="form-field">
                                    <input class="radio" type="radio" name="range" id="range_dates" value="dates" />
                                    <label class="" for="range_dates">' . lang('From - to') . '</label>
                                </div>
                                <div class="d-flex gap-3 mt-1" id="range_dates_fields">
                                    <input type="date" data-pg-date class="form-control" name="from" value="' . h($last_month['from']) . '" aria-label="' . h(lang('Start')) . '" />
                                    <input type="date" data-pg-date class="form-control" name="to" value="' . h($last_month['to']) . '" aria-label="' . h(lang('End')) . '" />
                                </div>
                            </div>
                            <div class="col-12 md:col-3">
                                <div class="form-field mb-3">
                                    <div class="switch"><input class="" type="checkbox" id="pdfs" name="pdfs" value="1" checked /></div>
                                    <label class="" for="pdfs">' . lang('With the documents') . '</label>
                                </div>
                                <button type="submit" class="btn btn-solid theme-primary w-100"' . ($ready ? '' : ' disabled') . '><i class="bi bi-file-earmark-zip me-1" aria-hidden="true"></i>' . lang('Build the pack') . '</button>
                            </div>
                        </div>
                        <script>
                        (function () {
                            // Touching the dates picks the date range, changing
                            // the month picks the month: the radio left as it
                            // was sent the other period without a word.
                            var month = document.getElementById("month");
                            var dates = document.getElementById("range_dates_fields");
                            var pick = function (id) { document.getElementById(id).checked = true; };
                            if (!month || !dates) { return; }
                            month.addEventListener("change", function () { pick("range_month"); });
                            month.addEventListener("focus", function () { pick("range_month"); });
                            dates.addEventListener("focusin", function () { pick("range_dates"); });
                            dates.addEventListener("change", function () { pick("range_dates"); });
                            dates.addEventListener("input", function () { pick("range_dates"); });
                        })();
                        </script>
                        <div class="form-text mt-5">' . lang('Amounts are in the base currency unless a column names the document\'s own; cancelled documents are listed and left out of the totals. A document that has no kept PDF yet is rendered and kept now.') . ($can_cash ? '' : ' ' . lang('The till sheets are left out: your account does not see the tills.')) . '</div>
                    </form>
                </div>
            </div>

        </div>
        <div class="col-12 xl:col-4">
            ' . $output_lock_card . '
            ' . $output_settings_card . '
        </div>
        <div class="col-12">
            <div class="card my-7">
                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Packs') . '</div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover align-middle mb-0" data-pg-sort id="erp_accountant_packs">
                        <thead>
                            <tr>
                                <th>' . lang('Period') . '</th>
                                <th>' . lang('Built') . '</th>
                                <th class="text-end">' . lang('Documents') . '</th>
                                <th class="text-end">' . h(erp_tax_label('calculated')) . '</th>
                                <th class="text-end">' . lang('Deductible') . '</th>
                                <th>' . lang('Sent to') . '</th>
                                <th class="text-end">' . lang('Downloads') . '</th>
                                <th data-pg-sort="none"></th>
                            </tr>
                        </thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
                <div class="form-text px-5 pb-5">' . h(lang(array('string' => 'A link opens its pack without a login for {var:1} days. Send it by e-mail, or make one to copy into a message; each new link makes the one before it stop working.', 'vars' => ERP_ACCOUNTANT_LINK_DAYS))) . '</div>
            </div>
            ' . $output_send_dialog . '
        </div>
    </div>
</main>
' . get_date_picker_format() . pg_fields_script() .
output_footer();

$liveform->remove_form();
