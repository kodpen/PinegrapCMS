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

// ---------------------------------------------------------------- download
if (isset($_GET['download'])) {
    $package = erp_accountant_package((int) $_GET['download']);

    if (!is_array($package) || !erp_accountant_stream($package)) {
        $liveform->mark_error('_error', lang('That pack is no longer there. Build it again.'));
        go($self_url);
    }

    erp_accountant_count_download((int) $package['id']);
    exit();
}

// ------------------------------------------------------------------ actions
if ($_POST) {
    validate_token_field();

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

        $liveform->add_notice(h(lang(array(
            'string' => 'The pack for {var:1} is ready.',
            'vars' => erp_accountant_period_label($period['from'], $period['to']),
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
                'vars' => erp_accountant_period_label((string) $package['period_from'], (string) $package['period_to']),
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
                $liveform->mark_error('accountant_email', h($saved['error']));
                $liveform->assign_field_value('accountant_email', (string) ($_POST['accountant_email'] ?? ''));
                go($self_url);
            }

            $liveform->add_notice(lang('The accountant\'s settings were saved.'));
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
    $output_link = '
            <div class="alert alert-success my-4" role="status">
                <div class="fw-bold mb-2"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang(array(
                    'string' => 'A new link to the pack. It works until {var:1}, without a login; the link made before it no longer works.',
                    'vars' => prepare_form_data_for_output(date('Y-m-d', (int) $new_link['expires']), 'date', false),
                ))) . '</div>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace" id="erp_accountant_link" value="' . h((string) $new_link['url']) . '" readonly aria-label="' . h(lang('Link')) . '" />
                    <button type="button" class="btn btn-success" id="erp_accountant_link_copy"><i class="bi bi-clipboard me-1" aria-hidden="true"></i>' . lang('Copy') . '</button>
                </div>
                <div class="form-text">' . lang('It is shown only now. Anyone who has it can download the pack, so send it to the accountant only.') . '</div>
            </div>
            <script>
            (function () {
                var button = document.getElementById("erp_accountant_link_copy");
                var input = document.getElementById("erp_accountant_link");
                if (!button || !input) { return; }
                button.addEventListener("click", function () {
                    var done = function () {
                        var original = button.innerHTML;
                        button.innerHTML = "<i class=\"bi bi-check-lg\" aria-hidden=\"true\"></i>";
                        setTimeout(function () { button.innerHTML = original; }, 1500);
                    };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(input.value).then(done);
                        return;
                    }
                    // Plain http has no clipboard API; select the text and copy it the old way.
                    input.select();
                    try { document.execCommand("copy"); done(); } catch (e) {}
                });
            })();
            </script>';
}

$output_rows = '';

foreach ($packages as $package) {
    $summary = json_decode((string) $package['summary'], true);
    $summary = is_array($summary) ? $summary : array();
    $period_label = erp_accountant_period_label((string) $package['period_from'], (string) $package['period_to']);
    $file_exists = is_file(erp_accountant_directory() . '/' . basename((string) $package['file_name']));
    $sent = ((int) $package['sent_at'] > 0)
        ? h((string) $package['sent_to']) . '<div class="small text-body-secondary">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $package['sent_at']), 'date and time')) . '</div>'
        : '<span class="text-body-secondary">—</span>';

    $output_send = '';

    if (!$readonly && $file_exists) {
        $output_send = '
                <form method="post" action="erp_accountant.php" class="d-flex gap-1 mt-1" style="min-width:16rem">
                    ' . get_token_field() . '
                    <input type="hidden" name="package_id" value="' . (int) $package['id'] . '" />
                    <input type="email" name="to" class="form-control form-control-sm" value="' . h($settings['email']) . '" placeholder="' . h(lang('Accountant\'s e-mail')) . '" aria-label="' . h(lang('Accountant\'s e-mail')) . '" required />
                    <button type="submit" name="erp_action" value="send" class="btn btn-sm btn-outline-secondary text-nowrap" data-loading-content="' . lang(array('string' => 'Sending')) . '"><i class="bi bi-send me-1" aria-hidden="true"></i>' . lang('Send link') . '</button>
                    <button type="submit" name="erp_action" value="link" formnovalidate class="btn btn-sm btn-outline-secondary" title="' . h(lang('Make a link to copy')) . '" aria-label="' . h(lang('Make a link to copy')) . '"><i class="bi bi-link-45deg" aria-hidden="true"></i></button>
                </form>';
    }

    $output_rows .= '
        <tr>
            <td class="align-middle text-nowrap" data-sort="' . h(str_replace('-', '', (string) $package['period_from'])) . '">'
                . h(prepare_form_data_for_output((string) $package['period_from'], 'date', false)) . ' – ' . h(prepare_form_data_for_output((string) $package['period_to'], 'date', false))
                . '<div class="small text-body-secondary font-monospace">' . h($period_label) . '</div></td>
            <td class="align-middle text-nowrap" data-sort="' . (int) $package['created_at'] . '">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $package['created_at']), 'date and time'))
                . '<div class="small text-body-secondary">' . ((string) $package['source'] === 'monthly' ? lang('Monthly, by itself') : h((string) ($package['created_by_name'] ?? ''))) . '</div></td>
            <td class="align-middle text-end" data-sort="' . (int) $package['documents'] . '">' . (int) $package['documents'] . '<div class="small text-body-secondary">' . h(lang(array('string' => '{var:1} file(s)', 'vars' => (int) $package['files_added']))) . '</div></td>
            <td class="align-middle text-end text-nowrap" data-sort="' . (int) ($summary['output_vat'] ?? 0) . '">' . $money($summary['output_vat'] ?? 0) . '</td>
            <td class="align-middle text-end text-nowrap" data-sort="' . (int) ($summary['input_vat'] ?? 0) . '">' . $money($summary['input_vat'] ?? 0) . '</td>
            <td class="align-middle" data-sort="' . (int) $package['sent_at'] . '">' . $sent . '</td>
            <td class="align-middle text-end" data-sort="' . (int) $package['downloads'] . '">' . (int) $package['downloads'] . '</td>
            <td class="align-middle">'
                . ($file_exists
                    ? '<a class="btn btn-sm btn-outline-primary text-nowrap" href="' . h($self_url . '?download=' . (int) $package['id']) . '"><i class="bi bi-download me-1" aria-hidden="true"></i>' . lang('Download') . ' <span class="small text-body-secondary">' . h(number_format(((int) $package['file_size']) / 1048576, 1, erp_number_separators()['decimal'], '')) . ' MB</span></a>'
                    : '<span class="text-body-secondary small">' . lang('The file is no longer there.') . '</span>')
                . $output_send . '</td>
        </tr>';
}

if ($output_rows === '') {
    $output_rows = '<tr data-pg-sort-fixed><td colspan="8" class="text-center text-body-secondary py-4">' . lang('No pack has been built yet.') . '</td></tr>';
}

$lock_default = $lock_date;
if ($lock_default === '') {
    $lock_default = $last_month['to'];
}

$output_lock_state = ($lock_date !== '')
    ? '<div class="h5 mb-1"><i class="bi bi-lock-fill text-warning me-2" aria-hidden="true"></i>' . h(lang(array('string' => 'Locked up to {var:1}', 'vars' => prepare_form_data_for_output($lock_date, 'date', false)))) . '</div>'
        . '<div class="form-text mt-0">' . lang('Documents and receipts dated on or before this day can no longer be issued, cancelled, returned or paid. A return made now is dated now, in the open period.') . '</div>'
    : '<div class="h5 mb-1"><i class="bi bi-unlock text-body-secondary me-2" aria-hidden="true"></i>' . lang('No period is locked') . '</div>'
        . '<div class="form-text mt-0">' . lang('Lock a period once its VAT return and books have been filed, so nothing in it changes afterwards.') . '</div>';

$output_settings_card = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Accountant') . '</div>
                <div class="card-body">
                    <form method="post" action="erp_accountant.php">
                        ' . get_token_field() . '
                        <input type="hidden" name="erp_action" value="settings" />
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="accountant_email">' . lang('Accountant\'s e-mail') . '</label>
                                <input type="email" class="form-control" id="accountant_email" name="accountant_email" value="' . h($settings['email']) . '" maxlength="255" autocomplete="off"' . ($can_settings ? '' : ' disabled') . ' />
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="accountant_monthly" name="accountant_monthly" value="1"' . ($settings['monthly'] ? ' checked' : '') . ($can_settings ? '' : ' disabled') . ' />
                                    <label class="form-check-label" for="accountant_monthly">' . lang('Send last month\'s pack by itself') . '</label>
                                </div>
                                <div class="form-text">' . h(lang(array('string' => 'On the first days of every month the scheduled job builds the month before and e-mails the accountant a link that works for {var:1} days.', 'vars' => ERP_ACCOUNTANT_LINK_DAYS))) . '</div>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="accountant_pdfs" name="accountant_pdfs" value="1"' . ($settings['pdfs'] ? ' checked' : '') . ($can_settings ? '' : ' disabled') . ' />
                                    <label class="form-check-label" for="accountant_pdfs">' . lang('Put the documents in the monthly pack') . '</label>
                                </div>
                            </div>
                        </div>
                        ' . ($can_settings
                            ? '<button type="submit" class="btn btn-sm btn-success mt-3" data-loading-content="' . lang(array('string' => 'Saving')) . '"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>' . lang(array('string' => 'Save')) . '</button>'
                            : '<div class="form-text mt-3">' . lang('Changing this needs the right to change ERP settings.') . '</div>') . '
                    </form>
                </div>
            </div>';

$output_lock_card = '
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Period lock') . '</div>
                <div class="card-body">
                    ' . $output_lock_state . '
                    ' . ($can_settings ? '
                    <form method="post" action="erp_accountant.php" class="row g-2 align-items-end mt-2">
                        ' . get_token_field() . '
                        <div class="col-12 col-sm-7">
                            <label class="form-label" for="lock_date">' . lang('Lock up to and including') . '</label>
                            <input type="date" class="form-control" id="lock_date" name="lock_date" value="' . h($lock_default) . '" max="' . h(date('Y-m-d', strtotime('-1 day'))) . '" required />
                        </div>
                        <div class="col-12 col-sm-5 d-flex gap-2">
                            <button type="submit" name="erp_action" value="lock" class="btn btn-sm btn-warning text-nowrap"><i class="bi bi-lock me-1" aria-hidden="true"></i>' . lang('Lock') . '</button>
                            ' . (($lock_date !== '') ? '<button type="submit" name="erp_action" value="unlock" formnovalidate class="btn btn-sm btn-outline-secondary text-nowrap">' . lang('Lift the lock') . '</button>' : '') . '
                        </div>
                    </form>' : '<div class="form-text">' . lang('Changing this needs the right to change ERP settings.') . '</div>') . '
                </div>
            </div>';

echo
pg_page_shell(array(
        'title' => lang('Accountant pack'),
        'extra classes' => 'erp erp_accountant',
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
        <div class="col-12 col-xl-8">
            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Build a pack') . '</div>
                <div class="card-body">
                    <form method="post" action="erp_accountant.php">
                        ' . get_token_field() . '
                        <input type="hidden" name="erp_action" value="build" />
                        <div class="row g-3 align-items-end">
                            <div class="col-12 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="range" id="range_month" value="month" checked />
                                    <label class="form-check-label" for="range_month">' . lang('A month') . '</label>
                                </div>
                                <select class="form-select mt-1" id="month" name="month" aria-label="' . h(lang('A month')) . '">' . $output_months . '</select>
                            </div>
                            <div class="col-12 col-md-5">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="range" id="range_dates" value="dates" />
                                    <label class="form-check-label" for="range_dates">' . lang('From - to') . '</label>
                                </div>
                                <div class="d-flex gap-2 mt-1">
                                    <input type="date" class="form-control" name="from" value="' . h($last_month['from']) . '" aria-label="' . h(lang('From')) . '" />
                                    <input type="date" class="form-control" name="to" value="' . h($last_month['to']) . '" aria-label="' . h(lang('To')) . '" />
                                </div>
                            </div>
                            <div class="col-12 col-md-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="pdfs" name="pdfs" value="1" checked />
                                    <label class="form-check-label" for="pdfs">' . lang('With the documents') . '</label>
                                </div>
                                <button type="submit" class="btn btn-primary w-100" data-loading-content="' . lang(array('string' => 'Please Wait')) . '"' . ($ready ? '' : ' disabled') . '><i class="bi bi-file-earmark-zip me-1" aria-hidden="true"></i>' . lang('Build the pack') . '</button>
                            </div>
                        </div>
                        <div class="form-text mt-3">' . lang('Amounts are in the base currency unless a column names the document\'s own; cancelled documents are listed and left out of the totals. A document that has no kept PDF yet is rendered and kept now.') . ($can_cash ? '' : ' ' . lang('The till sheets are left out: your account does not see the tills.')) . '</div>
                    </form>
                </div>
            </div>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . lang('Packs') . '</div>
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
                <div class="form-text px-3 pb-3">' . h(lang(array('string' => 'A link opens its pack without a login for {var:1} days. Send it by e-mail, or make one to copy into a message; each new link makes the one before it stop working.', 'vars' => ERP_ACCOUNTANT_LINK_DAYS))) . '</div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            ' . $output_lock_card . '
            ' . $output_settings_card . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
