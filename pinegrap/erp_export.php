<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - export accounts, invoices and receipts to a file.
 *
 * One screen for every destination: the operator picks a profile - a plain
 * CSV, or the spreadsheet an accounting program imports - and a range, and
 * gets a file. What went out is written to erp_export_log so the next run
 * can leave it out, and the last runs are listed underneath the form.
 *
 * The file is built and staged on the submit, the screen comes back with the
 * counts and any warnings, and the browser starts the download from there.
 * Streaming the file straight from the submit would leave nowhere to say how
 * many rows it holds or what was left out.
 *
 * The shapes themselves live in includes/erp/export.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
if (!validate_erp_access($user)) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_export');

$self_url = PATH . SOFTWARE_DIRECTORY . '/erp_export.php';

erp_export_sweep();

/**
 * The staged files this session may download.
 *
 * @return array  token => ['path' => string, 'name' => string, 'mime' => string]
 */
function erp_export_staged()
{
    $staged = isset($_SESSION['software']['erp_export']) ? $_SESSION['software']['erp_export'] : array();

    return is_array($staged) ? $staged : array();
}

// A finished file: send it and forget it. The token has to be one this
// session staged, so a guessed address downloads nothing.
if (isset($_GET['download'])) {
    $token = (string) $_GET['download'];
    $staged = erp_export_staged();

    if (!isset($staged[$token]) || !is_file($staged[$token]['path'])) {
        $liveform->mark_error('_error', lang('That file is no longer available. Run the export again.'));
        go($self_url);
    }

    $file = $staged[$token];
    unset($staged[$token]);
    $_SESSION['software']['erp_export'] = $staged;

    header('Content-Type: ' . $file['mime']);
    header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
    header('Content-Length: ' . filesize($file['path']));
    header('Cache-Control: no-store');
    readfile($file['path']);
    @unlink($file['path']);
    exit();
}

$profiles = erp_export_profiles();

// The form has been submitted: build the file, stage it, report.
if ($_POST) {
    validate_token_field();

    $liveform->add_fields_to_session();

    $profile = erp_export_profile((string) $liveform->get_field_value('profile'));

    if ($profile === null) {
        $liveform->mark_error('profile', lang('Choose a format.'));
    }

    $from = trim((string) $liveform->get_field_value('from'));
    $to = trim((string) $liveform->get_field_value('to'));

    if (($from !== '') && (validate_date($from) == false)) {
        $liveform->mark_error('from', lang('Please enter a valid date.'));
    }
    if (($to !== '') && (validate_date($to) == false)) {
        $liveform->mark_error('to', lang('Please enter a valid date.'));
    }

    if ($liveform->check_form_errors() == true) {
        go($self_url);
    }

    $filters = erp_export_filters(array(
        'from' => ($from !== '') ? prepare_form_data_for_input($from, 'date') : '',
        'to' => ($to !== '') ? prepare_form_data_for_input($to, 'date') : '',
        'direction' => $liveform->get_field_value('direction'),
        'include_drafts' => $liveform->get_field_value('include_drafts'),
        'include_passive' => $liveform->get_field_value('include_passive'),
        'not_exported' => $liveform->get_field_value('not_exported'),
    ));

    $built = erp_export_build($profile, $filters);

    if (empty($built['rows'])) {
        $liveform->mark_error('_error', lang('Nothing matched the filters, so no file was made.'));
        foreach ($built['warnings'] as $warning) {
            $liveform->add_warning($warning);
        }
        go($self_url);
    }

    $token = erp_export_new_token();
    $path = erp_export_temp_path($token, $profile['format']);

    if (($path === '') || !erp_export_write($profile, $built, $path)) {
        $liveform->mark_error('_error', lang('The file could not be written. Check that data/temp is writable.'));
        go($self_url);
    }

    if ($liveform->get_field_value('mark_exported') === '1') {
        if (!erp_export_log_run($built['entity'], $built['ids'], $profile, $token, (int) $user['id'])) {
            $liveform->add_warning(lang('The file was made, but the export could not be recorded.'));
        }
    }

    $staged = erp_export_staged();
    $staged[$token] = array(
        'path' => $path,
        'name' => erp_export_file_name($profile, date('Y-m-d_Hi')),
        'mime' => erp_export_mime($profile['format']),
    );
    $_SESSION['software']['erp_export'] = $staged;

    $liveform->add_notice(lang(array(
        'string' => '{var:1}: {var:2} record(s), {var:3} row(s). The download starts now.',
        'vars' => array($profile['label'], count($built['ids']), count($built['rows'])),
    )));

    foreach ($built['warnings'] as $warning) {
        $liveform->add_warning($warning);
    }

    log_activity(lang(array('string' => 'erp export ({var:1}) was made with {var:2} record(s)', 'vars' => array($profile['id'], count($built['ids'])))), $_SESSION['sessionusername']);

    go($self_url . '?done=' . $token);
}

// -- the form --------------------------------------------------------------------

// A link from a list screen preselects the matching generic profile; the
// generic CSV is the default otherwise, so the first choice is not tied to
// one country's program.
if ($liveform->field_in_session('profile') == false) {
    $entity = isset($_GET['entity']) ? (string) $_GET['entity'] : 'invoices';
    $liveform->assign_field_value('profile', ($entity === 'accounts') ? 'csv_accounts' : (($entity === 'receipts') ? 'csv_receipts' : 'csv_invoices'));
    $liveform->assign_field_value('direction', 'sales');
    $liveform->assign_field_value('not_exported', '1');
    $liveform->assign_field_value('mark_exported', '1');
}

$done_token = isset($_GET['done']) ? (string) $_GET['done'] : '';
$staged = erp_export_staged();
$download_url = (($done_token !== '') && isset($staged[$done_token])) ? ('erp_export.php?download=' . h($done_token)) : '';

$group_labels = array('generic' => lang('Generic'), 'parasut' => lang('Paraşüt'));
$current_profile = (string) $liveform->get_field_value('profile');

$output_profile_options = '';
foreach ($group_labels as $group => $group_label) {
    $output_profile_options .= '<optgroup label="' . h($group_label) . '">';
    foreach ($profiles as $id => $definition) {
        if ($definition['group'] !== $group) {
            continue;
        }
        $output_profile_options .= '<option value="' . h($id) . '" data-entity="' . h($definition['entity']) . '"' . (($id === $current_profile) ? ' selected="selected"' : '') . '>' . h($definition['label']) . '</option>';
    }
    $output_profile_options .= '</optgroup>';
}

$direction_options = array();
$direction_options[lang('Sales')] = 'sales';
$direction_options[lang('Purchase')] = 'purchase';

// The last runs. The list is short and the names are looked up once.
$runs = erp_export_recent_runs(20);
$user_names = array();
$user_ids = array();
foreach ($runs as $run) {
    $user_ids[(int) $run['created_by']] = true;
}
if (!empty($user_ids)) {
    foreach ((array) db_items("SELECT user_id, user_username FROM user WHERE user_id IN (" . implode(',', array_map('intval', array_keys($user_ids))) . ")") as $row) {
        $user_names[(int) $row['user_id']] = (string) $row['user_username'];
    }
}

$output_runs = '';
foreach ($runs as $run) {
    $profile_label = isset($profiles[$run['profile']]) ? $profiles[$run['profile']]['label'] : (string) $run['profile'];
    $output_runs .= '
                                    <tr>
                                        <td class="text-nowrap">' . h(prepare_form_data_for_output(date('Y-m-d H:i:s', (int) $run['created_at']), 'date and time')) . '</td>
                                        <td>' . h($profile_label) . '</td>
                                        <td class="text-end">' . (int) $run['rows_written'] . '</td>
                                        <td>' . h(isset($user_names[(int) $run['created_by']]) ? $user_names[(int) $run['created_by']] : '') . '</td>
                                    </tr>';
}

if ($output_runs === '') {
    $output_runs = '<tr><td colspan="4" class="text-body-secondary">' . lang('No export has been recorded yet.') . '</td></tr>';
}

$output_download = '';
if ($download_url !== '') {
    $output_download = '
            <div class="alert alert-success d-flex align-items-center gap-3">
                <i class="bi bi-download fs-4"></i>
                <div>' . lang('If the download did not start, use this link:') . ' <a href="' . $download_url . '" id="export_download_link" class="alert-link">' . lang('Download the file') . '</a></div>
            </div>
            <script>setTimeout(function () { window.location.href = ' . json_encode($download_url) . '; }, 600);</script>';
}

echo
pg_page_shell([
    'title' => lang('Export'),
    'extra_classes' => 'erp erp_export',
    'icon' => 'store',
    'heading' => lang('Export'),
    'heading_description' => lang('Hand accounts, invoices and receipts to another program as a CSV file or as the spreadsheet it imports.'),
    'cancel' => array('enable' => 'true', 'url' => 'erp_dashboard.php'),
    'breadcrumb' => array(
        array('label' => 'ERP', 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_dashboard.php'),
        array('label' => lang('Export')),
    ),
]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_download . '

            <form name="form" action="erp_export.php" method="post">
                ' . get_token_field() . '
                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Format') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-lg-6 my-2">
                                <label for="profile" class="form-label">' . lang('Format') . '</label>
                                <select name="profile" id="profile" class="form-select">' . $output_profile_options . '</select>
                                <div class="form-text">' . lang('The generic CSV files load back through the import screens. The Paraşüt files are that program\'s own import templates, filled from their fourth row.') . '</div>
                            </div>
                            <div class="col-12 col-lg-6 my-2">
                                <label for="direction" class="form-label">' . lang('Direction') . '</label>
                                ' . $liveform->output_field(array('type' => 'select', 'id' => 'direction', 'name' => 'direction', 'class' => 'form-select', 'options' => $direction_options)) . '
                                <div class="form-text">' . lang('Invoices only.') . '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card my-4">
                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                        ' . lang('Filters') . '
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="from" class="form-label">' . lang('From') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'from', 'name' => 'from', 'class' => 'form-control', 'size' => '10', 'maxlength' => '10', 'autocomplete' => 'off')) . '
                            </div>
                            <div class="col-12 col-sm-6 col-lg-3 my-2">
                                <label for="to" class="form-label">' . lang('To') . '</label>
                                ' . $liveform->output_field(array('type' => 'text', 'id' => 'to', 'name' => 'to', 'class' => 'form-control', 'size' => '10', 'maxlength' => '10', 'autocomplete' => 'off')) . '
                                ' . get_date_picker_format() . '
                                <script>$("#from, #to").datepicker(datetimepicker_options);</script>
                            </div>
                            <div class="col-12 col-lg-6 my-2">
                                <div class="form-text pt-lg-4">' . lang('The document date for invoices and receipts. Accounts are not filtered by date.') . '</div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 col-lg-6 my-2">
                                <div class="form-check form-switch">
                                    ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'not_exported', 'name' => 'not_exported', 'value' => '1', 'class' => 'form-check-input')) . '
                                    <label class="form-check-label" for="not_exported">' . lang('Only records not yet exported in this format') . '</label>
                                </div>
                                <div class="form-check form-switch">
                                    ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'mark_exported', 'name' => 'mark_exported', 'value' => '1', 'class' => 'form-check-input')) . '
                                    <label class="form-check-label" for="mark_exported">' . lang('Record this run, so the next one can leave these out') . '</label>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6 my-2">
                                <div class="form-check form-switch">
                                    ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'include_drafts', 'name' => 'include_drafts', 'value' => '1', 'class' => 'form-check-input')) . '
                                    <label class="form-check-label" for="include_drafts">' . lang('Include draft invoices') . '</label>
                                </div>
                                <div class="form-check form-switch">
                                    ' . $liveform->output_field(array('type' => 'checkbox', 'id' => 'include_passive', 'name' => 'include_passive', 'value' => '1', 'class' => 'form-check-input')) . '
                                    <label class="form-check-label" for="include_passive">' . lang('Include passive accounts') . '</label>
                                </div>
                                <div class="form-text">' . lang('Cancelled invoices are never exported. Return documents are counted and left out.') . '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <button type="submit" name="submit_export" value="Export" class="btn my-1 btn-success" data-loading-content="' . lang(array('string' => 'Generating')) . '"><span class="bi bi-box-arrow-up me-2"></span><span class="btn-text">' . lang(array('string' => 'Create the file')) . '</span></button>
                        </div>
                    </div>
                </nav>
            </form>

            <div class="card my-4">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                    ' . lang('Recent exports') . '
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="export_runs">
                            <thead>
                                <tr>
                                    <th>' . lang('Date') . '</th>
                                    <th>' . lang('Format') . '</th>
                                    <th class="text-end">' . lang('Records') . '</th>
                                    <th>' . lang('User') . '</th>
                                </tr>
                            </thead>
                            <tbody>' . $output_runs . '
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>' .
output_footer();

$liveform->remove_form();
