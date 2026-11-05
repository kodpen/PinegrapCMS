<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * ERP - import accounts from a CSV file.
 *
 * Three steps on one address. The file is uploaded and staged; its columns
 * are mapped onto account fields and the first rows are shown with what the
 * import would do to each; then the whole file is written and the outcome
 * listed row by row. Nothing reaches erp_accounts before the third step, so
 * a file that maps badly costs a look and not a clean-up.
 *
 * Reading, mapping and checking live in includes/erp/import.php; this file
 * only moves the upload about and draws the tables.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
// The whole screen writes accounts: the read-only accountant is told on
// arrival, not after choosing a file and mapping its columns.
if (!validate_erp_access($user, 'write')) {
    exit();
}

require_once(PG_FUNCTIONS_DIR . '/includes/erp/bootstrap.php');
include_once('liveform.class.php');
$liveform = new liveform('erp_accounts_import');

$list_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/erp_accounts.php';
$self_url = PATH . SOFTWARE_DIRECTORY . '/erp_accounts_import.php';

$preview_rows = 20;

// The template is a file, not a page: send it and stop.
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="erp-accounts-template.csv"');
    header('Cache-Control: no-store');
    echo erp_import_template_csv();
    exit();
}

erp_import_sweep();

$kind_options = array();
$kind_options[lang('Customer')] = 'customer';
$kind_options[lang('Supplier')] = 'supplier';
$kind_options[lang('Customer and supplier')] = 'both';

$fields = erp_import_fields();

/**
 * The page around a step.
 *
 * @param liveform $liveform
 * @param string   $body
 * @param string   $list_url
 * @return string
 */
function erp_import_page($liveform, $body, $list_url)
{
    return pg_page_shell([
        'title' => lang('Import Accounts from CSV'),
        'extra classes' => 'erp erp_accounts',
        'icon' => 'erp',
        'heading' => lang('Import Accounts from CSV'),
        'heading_description' => lang('Upload a list of customers and suppliers. Matching accounts can be updated or left alone; nothing is written until the last step.'),
        'cancel' => array('enable' => 'true', 'url' => 'erp_accounts.php', 'title' => lang('Accounts')),
        'breadcrumb' => array(
            array('label' => lang('Accounts'), 'url' => $list_url),
            array('label' => lang('Import Accounts from CSV')),
        ),
    ]) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $body . '
        </div>
    </div>
</main>' .
    output_footer();
}

/**
 * The step indicator above each card.
 *
 * @param int $current  1, 2 or 3
 * @return string
 */
function erp_import_steps($current)
{
    $labels = array(1 => lang('Upload'), 2 => lang('Map and preview'), 3 => lang('Result'));
    // Bootstrap's stepper colours only .active items: a finished step is
    // green, the current one takes the primary colour, a later one stays grey.
    $output = '<ol class="stepper stepper-horizontal mt-7 mb-0">';

    foreach ($labels as $number => $label) {
        $class = ($number < $current) ? ' active theme-success' : (($number === $current) ? ' active' : '');
        $current_attribute = ($number === $current) ? ' aria-current="step"' : '';
        $output .= '<li class="stepper-item' . $class . '"' . $current_attribute . '>' . h($label) . '</li>';
    }

    return $output . '</ol>';
}

/**
 * The staged file this session is working on, or null when there is none.
 *
 * @param string $token  The token posted back by the form
 * @return array|null  ['token' => string, 'path' => string, 'delimiter' => string, 'name' => string]
 */
function erp_import_session_file($token)
{
    $staged = isset($_SESSION['software']['erp_import']) ? $_SESSION['software']['erp_import'] : null;

    if (!is_array($staged) || (($staged['token'] ?? '') === '') || ((string) $token !== (string) $staged['token'])) {
        return null;
    }

    $path = erp_import_temp_path($staged['token']);

    if (($path === '') || !is_file($path)) {
        return null;
    }

    return array(
        'token' => $staged['token'],
        'path' => $path,
        'delimiter' => (string) ($staged['delimiter'] ?? ';'),
        'name' => (string) ($staged['name'] ?? ''),
    );
}

/**
 * Forget the staged file and remove it.
 *
 * @return void
 */
function erp_import_discard()
{
    $staged = isset($_SESSION['software']['erp_import']) ? $_SESSION['software']['erp_import'] : null;

    if (is_array($staged) && (($staged['token'] ?? '') !== '')) {
        $path = erp_import_temp_path($staged['token']);
        if (($path !== '') && is_file($path)) {
            @unlink($path);
        }
    }

    unset($_SESSION['software']['erp_import']);
}

/**
 * Step 1: the upload form.
 *
 * @param liveform $liveform
 * @param array    $kind_options
 * @return string
 */
function erp_import_step_upload($liveform, $kind_options)
{
    if ($liveform->field_in_session('default_kind') == false) {
        $liveform->assign_field_value('default_kind', 'customer');
    }

    return erp_import_steps(1) . '
            <form name="form" action="erp_accounts_import.php" method="post" enctype="multipart/form-data">
                ' . get_token_field() . '
                <input type="hidden" name="step" value="upload" />
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . h(lang('Import CSV')) . '
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 my-3">
                                        <label for="file" class="form-label">' . h(lang('CSV file')) . '</label>
                                        ' . $liveform->output_field(array('type' => 'file', 'id' => 'file', 'name' => 'file', 'class' => 'form-control', 'accept' => '.csv,.txt,text/csv')) . '
                                        <div class="form-text">' . h(lang('Upload a CSV file. The first row must hold the column names.')) . ' ' . h(lang(array('string' => 'Up to {var:1} MB.', 'vars' => erp_upload_mb_text(erp_upload_max_bytes(ERP_IMPORT_MAX_BYTES))))) . '</div>
                                    </div>
                                    <div class="col-12 lg:col-6 my-3">
                                        <label for="default_kind" class="form-label">' . h(lang('Default type for rows without one')) . '</label>
                                        ' . $liveform->output_field(array('type' => 'select', 'id' => 'default_kind', 'name' => 'default_kind', 'class' => 'form-control', 'options' => $kind_options)) . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <div class="card my-7 position-sticky" style="top:3.3rem;">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('What Is Imported')) . '</div>
                            <div class="card-body">
                                <ul class="small ps-5 mb-3">
                                    <li class="mb-2">' . h(lang('The delimiter and the character set are detected; Excel files saved as CSV in Turkish work as they are.')) . '</li>
                                    <li class="mb-2">' . h(lang('Columns are matched by name; you can correct the matching in the next step.')) . '</li>
                                    <li>' . h(lang('Opening balances are not imported; enter them on the account.')) . '</li>
                                </ul>
                                <a href="erp_accounts_import.php?template=1" class="pg-link-body-emphasis small"><i class="bi bi-download me-1" aria-hidden="true"></i>' . h(lang('Download template')) . '</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3">
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_upload" value="Continue" class="btn my-1 btn-solid theme-success"><i class="bi bi-upload me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Continue')) . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>';
}

/**
 * Step 2: the mapping and the preview.
 *
 * @param array  $staged    From erp_import_session_file()
 * @param array  $read      From erp_import_read()
 * @param array  $mapping   column index => field
 * @param array  $plan      From erp_import_plan()
 * @param string $mode      'update' or 'skip'
 * @param string $kind      Default kind
 * @param array  $kind_options
 * @param array  $fields    From erp_import_fields()
 * @param int    $total_rows
 * @param array  $clashes   column index => ['field', 'with'] for a column
 *                          mapped to a field an earlier column already feeds;
 *                          its select keeps the choice and says why it is
 *                          left out
 * @return string
 */
function erp_import_step_map($staged, $read, $mapping, $plan, $mode, $kind, $kind_options, $fields, $total_rows, $clashes = array())
{
    $delimiter_names = array(';' => lang('semicolon'), ',' => lang('comma'), "\t" => lang('tab'));
    $delimiter_name = isset($delimiter_names[$staged['delimiter']]) ? $delimiter_names[$staged['delimiter']] : $staged['delimiter'];

    // One select per file column. The header cell is the label; the field it
    // feeds is the value.
    $output_mapping = '';
    foreach ($read['header'] as $index => $name) {
        $chosen = isset($mapping[$index]) ? $mapping[$index] : '';
        $clash = isset($clashes[$index]) ? $clashes[$index] : null;
        if ($clash !== null) {
            $chosen = $clash['field'];
        }
        $options = '<option value=""' . (($chosen === '') ? ' selected="selected"' : '') . '>' . h(lang('Skip this column')) . '</option>';
        foreach ($fields as $field => $definition) {
            $options .= '<option value="' . h($field) . '"' . (($chosen === $field) ? ' selected="selected"' : '') . '>' . h($definition['label']) . (($field === 'title') ? ' *' : '') . '</option>';
        }
        $output_mapping .= '
                                <div class="col-12 sm:col-6 lg:col-4 xl:col-3 my-3">
                                    <label for="map_' . (int) $index . '" class="form-label text-truncate d-block" title="' . h($name) . '">' . h(($name !== '') ? $name : lang(array('string' => 'Column {var:1}', 'vars' => $index + 1))) . '</label>
                                    <select name="map[' . (int) $index . ']" id="map_' . (int) $index . '" class="form-control form-control-sm' . (($clash !== null) ? ' is-invalid' : '') . '"' . (($clash !== null) ? ' aria-describedby="map_' . (int) $index . '_clash"' : '') . '>' . $options . '</select>
                                    ' . (($clash !== null) ? '<div id="map_' . (int) $index . '_clash" class="invalid-feedback d-block">' . h(lang(array('string' => 'This field is already mapped to the column “{var:1}”.', 'vars' => array($clash['with'])))) . '</div>' : '') . '
                                </div>';
    }

    $has_title = in_array('title', $mapping, true);

    $output_kind_options = '';
    foreach ($kind_options as $label => $value) {
        $output_kind_options .= '<option value="' . h($value) . '"' . (($value === $kind) ? ' selected="selected"' : '') . '>' . h($label) . '</option>';
    }

    // The preview shows the mapped fields only, in the order of the fields.
    $shown = array();
    foreach ($fields as $field => $definition) {
        if (in_array($field, $mapping, true) && ($field !== 'notes')) {
            $shown[] = $field;
        }
    }

    $output_head = '';
    foreach ($shown as $field) {
        $output_head .= '<th class="text-nowrap">' . h($fields[$field]['label']) . '</th>';
    }

    $output_rows = '';
    $counts = array('create' => 0, 'match' => 0, 'duplicate' => 0, 'error' => 0);

    foreach ($plan as $entry) {
        $badge = '';
        $detail = '';

        if (!empty($entry['errors'])) {
            $counts['error']++;
            $badge = '<span class="badge theme-danger">' . h(lang('Error')) . '</span>';
            $detail = h(implode(' ', $entry['errors']));
        } elseif ($entry['duplicate_of'] > 0) {
            $counts['duplicate']++;
            $badge = '<span class="badge theme-secondary">' . h(lang('Skip')) . '</span>';
            $detail = h(lang(array('string' => 'Duplicate of row {var:1} in this file.', 'vars' => $entry['duplicate_of'])));
        } elseif ($entry['existing_id'] > 0) {
            $counts['match']++;
            $badge = '<span class="badge theme-warning">' . h(lang(array('string' => 'Already exists (#{var:1}).', 'vars' => $entry['existing_id']))) . '</span>';
            if ($entry['match_by'] === 'title') {
                $badge .= ' <span class="badge badge-subtle theme-secondary border" title="' . h(lang('No tax number or e-mail on the row; the name alone was matched.')) . '"><i class="bi bi-exclamation-triangle me-1"></i>' . h(lang('matched by name')) . '</span>';
            } elseif ($entry['match_by'] === 'email') {
                $badge .= ' <span class="badge badge-subtle theme-secondary border">' . h(lang('matched by e-mail')) . '</span>';
            } else {
                $badge .= ' <span class="badge badge-subtle theme-secondary border">' . h(lang('matched by tax number')) . '</span>';
            }
        } else {
            $counts['create']++;
            $badge = '<span class="badge theme-success">' . h(lang('Will be created')) . '</span>';
        }

        $output_cells = '';
        foreach ($shown as $field) {
            $value = erp_import_preview_cell($field, $entry['account'], $kind_options);
            $output_cells .= '<td class="text-truncate" style="max-width:200px" title="' . h($value) . '">' . h($value) . '</td>';
        }

        $output_rows .= '
                                    <tr>
                                        <td class="text-end fg-secondary">' . (int) $entry['line'] . '</td>
                                        <td class="text-nowrap">' . $badge . ($detail !== '' ? '<div class="small fg-secondary">' . $detail . '</div>' : '') . '</td>
                                        ' . $output_cells . '
                                    </tr>';
    }

    $output_summary = '<span><span class="badge theme-success">' . (int) $counts['create'] . '</span> ' . h(lang('Will be created')) . '</span>'
        . '<span><span class="badge theme-warning">' . (int) $counts['match'] . '</span> ' . h(lang('Already on file')) . '</span>'
        . '<span><span class="badge theme-secondary">' . (int) $counts['duplicate'] . '</span> ' . h(lang('Repeated in the file')) . '</span>'
        . '<span><span class="badge theme-danger">' . (int) $counts['error'] . '</span> ' . h(lang('Error')) . '</span>';

    $title_warning = $has_title ? '' : '<div class="alert theme-warning">' . h(lang('Map a column to Name to continue.')) . '</div>';

    return erp_import_steps(2) . '
            <form name="form" id="erp_import_map_form" action="erp_accounts_import.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="step" value="map" />
                <input type="hidden" name="import_token" value="' . h($staged['token']) . '" />
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . h(lang('Column mapping')) . '
                            </div>
                            <div class="card-body">
                                <div class="row">' . $output_mapping . '
                                </div>
                                ' . $title_warning . '
                            </div>
                        </div>

                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                ' . h(lang(array('string' => 'Preview (first {var:1} rows)', 'vars' => count($plan)))) . '
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0" data-pg-sort>
                                        <thead>
                                            <tr>
                                                <th class="text-end">' . h(lang('Row')) . '</th>
                                                <th>' . h(lang('Result')) . '</th>
                                                ' . $output_head . '
                                            </tr>
                                        </thead>
                                        <tbody>' . $output_rows . '
                                        </tbody>
                                    </table>
                                </div>
                                <div class="form-text mt-3">' . h(lang('Only the first rows are shown; the whole file is checked the same way when the import runs.')) . '</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <div class="position-sticky" style="top:3.3rem;">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('Summary')) . '</div>
                                <div class="card-body">
                                    <p class="small mb-1 text-break"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>' . h($staged['name']) . '</p>
                                    <p class="small fg-secondary mb-3">' . h(lang(array('string' => '{var:1} data rows', 'vars' => $total_rows))) . ' — ' . h(lang(array('string' => 'Detected delimiter: {var:1}', 'vars' => $delimiter_name))) . '</p>
                                    <div class="small d-flex flex-column gap-2">' . $output_summary . '</div>
                                </div>
                            </div>
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . h(lang('Options')) . '
                                </div>
                                <div class="card-body">
                                    <fieldset class="my-3">
                                        <legend class="form-label fs-md">' . lang('If the account already exists') . '</legend>
                                        <div class="form-field">
                                            <input class="radio" type="radio" name="duplicate_mode" id="duplicate_mode_update" value="update"' . (($mode === 'update') ? ' checked="checked"' : '') . '>
                                            <label class="" for="duplicate_mode_update">' . h(lang('Update it')) . '</label>
                                        </div>
                                        <div class="form-field">
                                            <input class="radio" type="radio" name="duplicate_mode" id="duplicate_mode_skip" value="skip"' . (($mode !== 'update') ? ' checked="checked"' : '') . '>
                                            <label class="" for="duplicate_mode_skip">' . h(lang('Skip it')) . '</label>
                                        </div>
                                    </fieldset>
                                    <div class="form-text">' . h(lang('Matched by tax number, then e-mail, then exact name.')) . ' ' . h(lang('An update writes only the columns the file supplies; balances are never touched.')) . '</div>
                                    <div class="my-3">
                                        <label for="default_kind" class="form-label">' . h(lang('Default type for rows without one')) . '</label>
                                        <select name="default_kind" id="default_kind" class="form-control">' . $output_kind_options . '</select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3">
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_recheck" value="Re-check" class="btn my-1 btn-outline theme-secondary"><i class="bi bi-arrow-repeat me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Re-check')) . '</span></button>
                                    <button type="submit" name="submit_run" value="Import" class="btn my-1 btn-solid theme-success"' . ($has_title ? '' : ' disabled="disabled"') . '><i class="bi bi-check2-circle me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Start import')) . '</span></button>
                                </div>
                                <p class="small fg-secondary mt-2 mb-0" id="erp_import_map_status" role="status" aria-live="polite"></p>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
            <script>
            (function () {
                // The form is sent once: the first submit goes, any later one
                // is cancelled until the page leaves. Start import writes the
                // whole file, far longer than the window guard\'s 3 seconds;
                // a second press would wait for the first run, find the file
                // already discarded and never show the result. The buttons
                // are never disabled, so the pressed one travels. Listening
                // on window lets a handler that cancels a submit keep the
                // form usable.
                var erp_import_sent = false;
                var form = document.getElementById("erp_import_map_form");
                var status = document.getElementById("erp_import_map_status");
                if (!form) { return; }
                window.addEventListener("submit", function (event) {
                    if (event.target !== form) { return; }
                    if (erp_import_sent) { event.preventDefault(); return; }
                    if (event.defaultPrevented) { return; }
                    erp_import_sent = true;
                    if (event.submitter && event.submitter.name === "submit_run") {
                        form.setAttribute("aria-busy", "true");
                        if (status) { status.textContent = "' . escape_javascript(lang('Importing…')) . '"; }
                    }
                });
                window.addEventListener("pageshow", function (event) {
                    if (!event.persisted) { return; }
                    erp_import_sent = false;
                    form.removeAttribute("aria-busy");
                    if (status) { status.textContent = ""; }
                });
            })();
            </script>';
}

/**
 * Step 3: what happened.
 *
 * @param array  $outcome  From erp_import_run()
 * @return string
 */
function erp_import_step_result($outcome)
{
    $counts = $outcome['counts'];

    $output_counts = '
                        <div class="row text-center">
                            <div class="col-6 md:col-3 my-3"><div class="pg-display-6 fg-success">' . (int) $counts['created'] . '</div><div class="small fg-secondary">' . lang('Newly created') . '</div></div>
                            <div class="col-6 md:col-3 my-3"><div class="pg-display-6 fg-primary">' . (int) $counts['updated'] . '</div><div class="small fg-secondary">' . lang('Updated') . '</div></div>
                            <div class="col-6 md:col-3 my-3"><div class="pg-display-6 fg-secondary">' . (int) $counts['skipped'] . '</div><div class="small fg-secondary">' . lang('Skipped') . '</div></div>
                            <div class="col-6 md:col-3 my-3"><div class="pg-display-6 fg-danger">' . (int) $counts['failed'] . '</div><div class="small fg-secondary">' . lang('Failed') . '</div></div>
                        </div>';

    $output_rows = '';
    foreach ($outcome['results'] as $line => $result) {
        if (($result['status'] === 'created') || ($result['status'] === 'updated')) {
            continue;
        }
        $badge = ($result['status'] === 'failed') ? '<span class="badge theme-danger">' . h(lang('Failed')) . '</span>' : '<span class="badge theme-secondary">' . h(lang('Skipped')) . '</span>';
        $output_rows .= '
                                    <tr>
                                        <td class="text-end fg-secondary">' . (int) $line . '</td>
                                        <td>' . $badge . '</td>
                                        <td>' . h($result['reason']) . ($result['id'] > 0 ? ' <a href="edit_erp_account.php?id=' . (int) $result['id'] . '" class="pg-link-body-emphasis">#' . (int) $result['id'] . '</a>' : '') . '</td>
                                    </tr>';
    }

    $output_table = '';
    if ($output_rows !== '') {
        $output_table = '
                <div class="card my-7">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('Rows that were not written') . '
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0" data-pg-sort>
                                <thead>
                                    <tr>
                                        <th class="text-end">' . lang('Row') . '</th>
                                        <th>' . lang('Result') . '</th>
                                        <th>' . lang('Reason') . '</th>
                                    </tr>
                                </thead>
                                <tbody>' . $output_rows . '
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>';
    }

    return erp_import_steps(3) . '
                <div class="card my-7">
                    <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                        ' . lang('Import complete.') . '
                    </div>
                    <div class="card-body">' . $output_counts . '
                    </div>
                </div>
                ' . $output_table . '
                <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                    <div class="container">
                        <div class="btn-group flex-wrap justify-content-center">
                            <a href="erp_accounts_import.php" class="btn my-1 btn-solid theme-primary"><i class="bi bi-upload me-3" aria-hidden="true"></i>' . lang('Import another file') . '</a>
                        </div>
                    </div>
                </nav>';
}

// -- request handling ---------------------------------------------------------

// Every field of the steps but the mapping is one value. One posted as an
// array counts as not given: (string) read it as "Array" with a notice.
pg_request_single_values(array('step', 'default_kind', 'import_token', 'duplicate_mode', 'submit_recheck', 'submit_run'));

$step = isset($_POST['step']) ? (string) $_POST['step'] : '';

// A file over post_max_size makes PHP throw the whole request away; without
// this the wizard started over as if nothing had been sent.
if (pg_post_body_discarded()) {
    $liveform->mark_error('file', h(erp_upload_discarded_message()));
    go($self_url);
}

// No submission: the upload form. Any file left from an earlier visit is
// dropped so a token cannot be reused after the operator started over.
if (!$_POST) {
    erp_import_discard();

    echo erp_import_page($liveform, erp_import_step_upload($liveform, $kind_options), $list_url);
    $liveform->remove_form();
    exit();
}

validate_token_field();

$default_kind = isset($_POST['default_kind']) ? (string) $_POST['default_kind'] : 'customer';
$defaults = erp_import_defaults($default_kind);

// Two columns on one field, set by a re-check; an upload maps by itself.
$clashes = array();

// Step 1 submitted: stage the file, then fall through to the mapping screen.
if ($step === 'upload') {

    $liveform->add_fields_to_session();

    $upload = isset($_FILES['file']) && is_array($_FILES['file']) ? $_FILES['file'] : array('name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0);

    $refusal = erp_upload_refusal($upload, ERP_IMPORT_MAX_BYTES);

    if (((string) $upload['name'] === '') || ((int) $upload['error'] === UPLOAD_ERR_NO_FILE)) {
        $liveform->mark_error('file', lang('Please select a file.'));
    } elseif ($refusal !== '') {
        $liveform->mark_error('file', h($refusal));
    } elseif (((int) $upload['error'] !== UPLOAD_ERR_OK) || !is_uploaded_file($upload['tmp_name'])) {
        $liveform->mark_error('file', lang('The file could not be read.'));
    } elseif (!in_array(strtolower((string) pathinfo($upload['name'], PATHINFO_EXTENSION)), array('csv', 'txt'), true)) {
        $liveform->mark_error('file', lang('Only CSV files are accepted.'));
    }

    if ($liveform->check_form_errors() == true) {
        go($self_url);
    }

    $bytes = @file_get_contents($upload['tmp_name']);

    if (($bytes === false) || (erp_import_temp_dir() === '')) {
        $liveform->mark_error('file', lang('The file could not be read.'));
        go($self_url);
    }

    $encoding = '';
    $bytes = erp_import_to_utf8($bytes, $encoding);

    $first_line = strtok($bytes, "\n");
    $delimiter = erp_import_detect_delimiter(($first_line === false) ? '' : $first_line);

    erp_import_discard();

    $token = erp_import_new_token();
    $path = erp_import_temp_path($token);

    if (($path === '') || (@file_put_contents($path, $bytes) === false)) {
        $liveform->mark_error('file', lang('The file could not be read.'));
        go($self_url);
    }

    $_SESSION['software']['erp_import'] = array(
        'token' => $token,
        'delimiter' => $delimiter,
        'name' => mb_substr((string) $upload['name'], 0, 120),
        'time' => time(),
    );

    $staged = erp_import_session_file($token);
    $read = erp_import_read($path, $delimiter, $preview_rows);

    if (($read === null) || empty($read['rows'])) {
        erp_import_discard();
        $liveform->mark_error('file', ($read === null) ? lang('The file was empty.') : lang('The file has no data rows.'));
        go($self_url);
    }

    $mapping = erp_import_guess_mapping($read['header']);
    $mode = 'update';

// Step 2 submitted, either to look again or to run: the mapping comes from
// the form and the file from the session.
} elseif (($step === 'map') && (isset($_POST['submit_recheck']) || isset($_POST['submit_run']))) {

    $staged = erp_import_session_file(isset($_POST['import_token']) ? (string) $_POST['import_token'] : '');

    if ($staged === null) {
        $liveform->mark_error('file', lang('The upload has expired. Start again.'));
        go($self_url);
    }

    $read = erp_import_read($staged['path'], $staged['delimiter'], isset($_POST['submit_run']) ? 0 : $preview_rows);

    if (($read === null) || empty($read['rows'])) {
        erp_import_discard();
        $liveform->mark_error('file', lang('The file has no data rows.'));
        go($self_url);
    }

    $mapping = array();
    $posted = isset($_POST['map']) && is_array($_POST['map']) ? $_POST['map'] : array();
    $taken = array();
    $clashes = array();
    foreach ($read['header'] as $index => $unused) {
        // A column's field is one name; one posted as an array (map[0][]=x)
        // leaves the column unmapped.
        $field = (isset($posted[$index]) && is_scalar($posted[$index])) ? (string) $posted[$index] : '';
        if (($field === '') || !isset($fields[$field])) {
            $field = '';
        } elseif (isset($taken[$field])) {
            // A second column on one field is left out of the rows, but the
            // screen keeps the choice and names the column that holds it.
            $with = (string) $read['header'][$taken[$field]];
            $clashes[$index] = array(
                'field' => $field,
                'with' => ($with !== '') ? $with : lang(array('string' => 'Column {var:1}', 'vars' => $taken[$field] + 1)),
            );
            $field = '';
        }
        if ($field !== '') {
            $taken[$field] = $index;
        }
        $mapping[$index] = $field;
    }

    $mode = ((isset($_POST['duplicate_mode']) ? (string) $_POST['duplicate_mode'] : 'skip') === 'update') ? 'update' : 'skip';

    // Step 3: write the file and show the outcome. A run refused for want
    // of a Name column draws the mapping again from what was posted: going
    // back to the address would discard the staged file and every choice.
    if (isset($_POST['submit_run']) && !in_array('title', $mapping, true)) {
        $liveform->mark_error('map', lang('Map a column to Name to continue.'));
    } elseif (!empty($clashes)) {
        // Never run with a column quietly dropped.
        $liveform->mark_error('map', lang('Two columns are mapped to one field. Choose another field for one of them, or skip it.'));
    } elseif (isset($_POST['submit_run'])) {

        $plan = erp_import_plan($read['rows'], $mapping, $defaults);
        $outcome = erp_import_run($plan, $mode, (int) $user['id']);

        erp_import_discard();

        log_activity(lang(array(
            'string' => 'erp accounts were imported from CSV ({var:1} created, {var:2} updated, {var:3} skipped, {var:4} failed)',
            'vars' => array($outcome['counts']['created'], $outcome['counts']['updated'], $outcome['counts']['skipped'], $outcome['counts']['failed']),
        )), $_SESSION['sessionusername']);

        echo erp_import_page($liveform, erp_import_step_result($outcome), $list_url);
        $liveform->remove_form();
        exit();
    }

} else {
    go($self_url);
}

// The mapping screen, reached from step 1 and from a re-check.
$total_rows = 0;
$all = erp_import_read($staged['path'], $staged['delimiter'], 0);
if (is_array($all)) {
    $total_rows = count($all['rows']);
}

$plan = erp_import_plan(array_slice($read['rows'], 0, $preview_rows), $mapping, $defaults);

echo erp_import_page($liveform, erp_import_step_map($staged, $read, $mapping, $plan, $mode, $defaults['kind'], $kind_options, $fields, $total_rows, $clashes), $list_url);
$liveform->remove_form();
