<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Import from another platform: a WordPress export (WXR) or a WooCommerce or
 * Shopify CSV is uploaded, its kind told from its content, read once as a
 * dry run, mapped onto a form, a folder or a product group, then written in
 * short requests (and by the general job while the screen is closed). The
 * report lists what was skipped or failed and why; Undo removes what the run
 * made within 30 days. The work is in includes/fn/importers.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

require_once(PG_FUNCTIONS_DIR . '/includes/fn/importers.php');

// The progress and undo steps (ajax=1) are read by the screen's script as
// JSON: an ended session, a missing right or a stale token is answered here
// in that shape, as validate_user(), validate_area_access() and
// validate_token_field() would answer with HTML.
$import_ajax = (($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['ajax']) && ($_POST['ajax'] === '1'));

$import_json = function ($status, $body) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
};

if ($import_ajax) {

    if (!defined('USER_LOGGED_IN') || !USER_LOGGED_IN) {
        $import_json(401, array('ok' => false, 'text' => lang('Your session has expired. Reload the page and sign in again.')));
    }

    if (!defined('USER_ROLE') || ((int) USER_ROLE > 2)) {
        $import_json(403, array('ok' => false, 'text' => lang('Access denied.')));
    }

    $import_session_token = (string) ($_SESSION['software']['token'] ?? '');
    $import_post_token = (isset($_POST['token']) && is_string($_POST['token'])) ? $_POST['token'] : '';

    if (($import_session_token === '') || !hash_equals($import_session_token, $import_post_token)) {
        $import_json(403, array('ok' => false, 'text' => lang('Your session expired. Reload the page and try again.')));
    }

} else {
    pg_upload_refuse_discarded_body();
}

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_import_ready()) {
    if ($import_ajax) {
        $import_json(409, array('ok' => false, 'text' => lang('Importing comes with an upgrade this site has not run yet.')));
    }

    output_error(h(lang('Importing comes with an upgrade this site has not run yet.')));
}

include_once('liveform.class.php');
$liveform = new liveform('import');

$screen_url = PATH . SOFTWARE_DIRECTORY . '/import.php';
$import_id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$import = ($import_id > 0) ? pg_import_get($import_id) : null;

if (($import_id > 0) && ($import === null)) {
    if ($import_ajax) {
        $import_json(404, array('ok' => false, 'text' => lang('The import was not found.')));
    }

    output_error(h(lang('The import was not found.')));
}

// The redirects an import made as a CSV file (from,to,code), the form the
// short links screen exports and imports.
if (($import !== null) && (($_GET['export'] ?? '') === 'redirects') && pg_import_writer_load('redirect')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="import-' . (int) $import['id'] . '-redirects.csv"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store, private');

    echo pg_short_link_csv(pg_import_redirect_links((int) $import['id']), PATH);
    exit();
}

// The short requests: a few seconds of work, then the screen asks again. The
// session is closed first so other panel tabs do not wait behind them.
if ($import_ajax) {

    session_write_close();

    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    $import_step = (string) ($_POST['step'] ?? '');

    if ($import_step === 'run') {
        $run = pg_import_run($import_id, 8);
        $import_json(200, array('ok' => true, 'busy' => $run['busy'], 'status' => $run['status'], 'position' => $run['position'], 'totals' => pg_import_totals($run['counts'])));
    }

    if ($import_step === 'undo') {
        $undo = pg_import_undo($import_id, 8);

        if ($undo['done'] && !$undo['closed']) {
            log_activity(lang(array('string' => 'import {var:1} was undone', 'vars' => array($import_id))), $_SESSION['sessionusername'] ?? '');
        }

        $import_json(200, array('ok' => true, 'removed' => $undo['removed'], 'left' => $undo['left'], 'kept' => $undo['kept'], 'done' => $undo['done'], 'closed' => $undo['closed'], 'busy' => $undo['busy']));
    }

    $import_json(400, array('ok' => false, 'text' => lang('Invalid action.')));
}

// The choices of each mapping row type, for the rows a reader asks for.
$import_choices = function ($type) {

    // Several rows share a type (seven field rows): read once.
    static $cache = array();

    if (isset($cache[$type])) {
        return $cache[$type];
    }

    $choices = array();

    // The Recycle Bin and the Workspace's folder, each with what is below
    // it, are no destination: what the import put there nobody would see.
    $tree = pg_folder_access_tree();
    $workspace = (int) pg_workspace_folder_id();
    $hidden_folder = function ($folder_id) use ($tree, $workspace) {
        return pg_folder_in_bin_chain($tree['rows'], (int) $folder_id, (int) $tree['bin'])
            || pg_folder_in_bin_chain($tree['rows'], (int) $folder_id, $workspace);
    };

    if ($type === 'form') {
        foreach ((array) db_items("SELECT custom_form_pages.page_id, custom_form_pages.form_name, page.page_name, page.page_folder
            FROM custom_form_pages
            JOIN page ON custom_form_pages.page_id = page.page_id
            ORDER BY custom_form_pages.form_name, page.page_name") as $row) {
            if (!$hidden_folder($row['page_folder']) && check_edit_access($row['page_folder'])) {
                $form_name = (string) $row['form_name'];
                $page_name = (string) $row['page_name'];
                $choices[(string) (int) $row['page_id']] = (($form_name !== '') && ($form_name !== $page_name)) ? $form_name . ' (' . $page_name . ')' : $page_name;
            }
        }
    } elseif ($type === 'form_field') {
        foreach ((array) db_items("SELECT id, page_id, label, name, type FROM form_fields WHERE page_id > 0 ORDER BY page_id, sort_order, id") as $row) {
            $choices[(string) (int) $row['id']] = array('label' => ((string) $row['label'] !== '') ? (string) $row['label'] : (string) $row['name'], 'form' => (int) $row['page_id'], 'type' => (string) $row['type']);
        }
    } elseif ($type === 'folder') {
        $rows = array();
        $left_out = array_filter(array((int) $tree['bin'], $workspace));

        foreach ((array) db_items("SELECT folder_id, folder_name, folder_parent, folder_archived FROM folder ORDER BY folder_level, folder_order, folder_name") as $row) {
            $rows[(int) $row['folder_id']] = array('parent' => (int) $row['folder_parent'], 'name' => (string) $row['folder_name']);

            if ((string) $row['folder_archived'] === '1') {
                $left_out[] = (int) $row['folder_id'];
            }
        }

        foreach (pg_import_tree_choices($rows, $left_out) as $folder_id => $label) {
            if (check_edit_access($folder_id)) {
                $choices[(string) $folder_id] = $label;
            }
        }
    } elseif ($type === 'product_group') {
        $rows = array();

        foreach ((array) db_items("SELECT id, name, parent_id FROM product_groups WHERE 1 = 1" . pg_catalog_bin_live_sql('product_groups') . " ORDER BY name") as $row) {
            $rows[(int) $row['id']] = array('parent' => (int) $row['parent_id'], 'name' => (string) $row['name']);
        }

        $choices = pg_import_tree_choices($rows, array());
    }

    $cache[$type] = $choices;

    return $choices;
};

// The reader's mapping rows with their choices filled in.
$import_mapping_rows = function ($kind) use ($import_choices) {

    $rows = array();

    if (!pg_import_reader_load($kind) || !function_exists('pg_import_' . $kind . '_mapping')) {
        return $rows;
    }

    foreach ((array) call_user_func('pg_import_' . $kind . '_mapping') as $name => $row) {
        $type = (string) ($row['type'] ?? 'select');
        $row['type'] = $type;
        $row['choices'] = ($type === 'select') ? (array) ($row['choices'] ?? array()) : $import_choices($type);

        // A field row offers only the field types its writer fills; the
        // choices are the save's whitelist too.
        if ($type === 'form_field') {
            $types = (array) ($row['field_types'] ?? array());
            $row['choices'] = array_filter($row['choices'], function ($choice) use ($types) {
                return pg_import_field_fits($choice['type'] ?? '', $types);
            });
        }

        $rows[(string) $name] = $row;
    }

    return $rows;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $import_action = (string) ($_POST['action'] ?? '');

    if ($import_action === 'upload') {

        $upload = (isset($_FILES['file']) && is_array($_FILES['file'])) ? $_FILES['file'] : null;
        $upload_limits = pg_upload_limits();
        $upload_refusal = pg_upload_file_refusal($upload, $upload_limits['file_max']);

        if ($upload_refusal !== '') {
            $liveform->mark_error('file', h($upload_refusal));
            go($screen_url);
        }

        if (($upload === null) || !is_string($upload['tmp_name'] ?? null) || ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) || !is_uploaded_file($upload['tmp_name'])) {
            $liveform->mark_error('file', h(lang('Choose an export file to import.')));
            go($screen_url);
        }

        $handle = fopen($upload['tmp_name'], 'rb');
        $head = ($handle !== false) ? (string) fread($handle, 8192) : '';

        if ($handle !== false) {
            fclose($handle);
        }

        $kind = pg_import_detect_kind($head);

        if ($kind === '') {
            $liveform->mark_error('file', h(lang('The file is not an export this screen knows: a WordPress export (XML), a WooCommerce products CSV or a Shopify products or customers CSV.')));
            go($screen_url);
        }

        if (!pg_import_reader_load($kind)) {
            $liveform->mark_error('file', h(lang(array('string' => 'The file is a {var:1}, which this site cannot read yet.', 'vars' => array(pg_import_kinds()[$kind])))));
            go($screen_url);
        }

        $new_id = pg_import_create($kind, (string) ($upload['name'] ?? ''), (int) $user['id']);
        $new_import = pg_import_get($new_id);

        if (($new_id <= 0) || ($new_import === null) || !move_uploaded_file($upload['tmp_name'], pg_import_source_path($new_id, $new_import['file_name']))) {
            if ($new_id > 0) {
                db("DELETE FROM imports WHERE id = '" . (int) $new_id . "'");
            }

            $liveform->mark_error('file', h(lang('The file could not be kept on the server; check that the data folder is writable.')));
            go($screen_url);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(60);
        }

        pg_import_dry_run($new_id, 20);

        $liveform->remove_form();
        go($screen_url . '?id=' . $new_id);
    }

    // A checked upload that is not wanted: nothing was written, so the row
    // and the kept file go.
    if (($import_action === 'discard') && ($import !== null)) {
        if (pg_import_discard($import_id)) {
            log_activity(lang(array('string' => 'import {var:1} ({var:2}) was discarded', 'vars' => array($import_id, $import['file_name']))), $_SESSION['sessionusername']);
            go($screen_url);
        }

        go($screen_url . '?id=' . $import_id);
    }

    if (($import_action === 'start') && ($import !== null) && ($import['status'] === 'dry_run')) {

        $rows = $import_mapping_rows($import['kind']);
        $posted = (isset($_POST['map']) && is_array($_POST['map'])) ? $_POST['map'] : array();
        $clean = pg_import_mapping_clean($rows, $posted);

        // A field row belongs to the form row it names: a field of another
        // form is dropped.
        foreach ($rows as $name => $row) {
            if (($row['type'] === 'form_field') && isset($clean['mapping'][$name])) {
                $form_value = (int) ($clean['mapping'][(string) ($row['form'] ?? '')] ?? 0);

                if ((int) $row['choices'][$clean['mapping'][$name]]['form'] !== $form_value) {
                    unset($clean['mapping'][$name]);

                    if (!empty($row['required'])) {
                        $clean['missing'][] = $name;
                    }
                }
            }
        }

        if ($clean['missing']) {
            foreach ($clean['missing'] as $name) {
                $liveform->add_error(h(lang(array('string' => 'Choose {var:1}.', 'vars' => array((string) ($rows[$name]['label'] ?? $name))))));
            }

            go($screen_url . '?id=' . $import_id);
        }

        pg_import_start($import_id, $clean['mapping']);

        log_activity(lang(array('string' => 'import {var:1} ({var:2}) was started', 'vars' => array($import_id, $import['file_name']))), $_SESSION['sessionusername']);

        go($screen_url . '?id=' . $import_id);
    }

    go($screen_url . (($import !== null) ? '?id=' . $import_id : ''));
}

// ---- The screen ----------------------------------------------------------

$kinds = pg_import_kinds();
$writer_kinds = pg_import_writer_kinds();

$status_labels = array(
    'uploaded' => lang('Uploaded'),
    'dry_run'  => lang('Checked, not started'),
    'running'  => lang('Running'),
    'done'     => lang('Done'),
    'failed'   => lang('Stopped'),
    'undone'   => lang('Undone'),
);

$card = function ($title, $body) {
    return '<div class="card my-7">
        <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h($title) . '</div>
        <div class="card-body">' . $body . '</div>
    </div>';
};

$counts_table = function ($counts, $labels) use ($writer_kinds) {
    $rows = '';

    foreach ((array) $counts as $kind => $row) {
        $rows .= '<tr><th scope="row">' . h($writer_kinds[$kind] ?? $kind) . '</th>';

        foreach (array_keys($labels) as $key) {
            $rows .= '<td class="text-end">' . h(pg_format_number((int) ($row[$key] ?? 0), 0)) . '</td>';
        }

        $rows .= '</tr>';
    }

    if ($rows === '') {
        return '<p class="mb-0">' . h(lang('Nothing to import was found in the file.')) . '</p>';
    }

    $head = '<th scope="col">' . h(lang('Kind')) . '</th>';

    foreach ($labels as $label) {
        $head .= '<th scope="col" class="text-end">' . h($label) . '</th>';
    }

    return '<div class="table-responsive"><table class="table"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
};

// The Undo card, also on a running import, which it stops first.
$undo_card = function ($label) use ($card) {
    return $card(lang('Undo'), '
        <p>' . h(lang('Removes everything this import made. Items that were changed since are removed too.')) . '</p>
        <p id="pg_import_undo_progress" role="status" aria-live="polite"></p>
        <button type="button" id="pg_import_undo" class="btn btn-outline theme-danger"><i class="bi bi-arrow-counterclockwise me-5" aria-hidden="true"></i><span class="pg-btn-label">' . h($label) . '</span></button>');
};

// The screen is a form column (what the operator fills or acts on) and a
// sticky status column written after it (the file, what it holds or what
// was made); a step with something to send has its buttons in a sticky bar
// below both. A form wraps the whole row so the bar's buttons submit it.
$main = '';
$side = '';
$buttons = '';
$form_open = '';
$form_close = '';
$before = '';
$field_list = array();

// One row of the status column's Summary: a label and its plain value.
$summary_row = function ($label, $value, $last = false) {
    return '
                <div class="d-flex justify-content-between gap-3 py-2' . ($last ? '' : ' border-bottom') . '">
                    <dt class="fw-normal">' . h($label) . '</dt>
                    <dd class="mb-0 fw-bold text-end text-break">' . h($value) . '</dd>
                </div>';
};

if ($import === null) {

    $form_open = '<form name="form" action="import.php" method="post" enctype="multipart/form-data">
        ' . get_token_field() . '
        <input type="hidden" name="action" value="upload">';
    $form_close = '</form>';

    $main .= $card(lang('Export file'), '
            <label for="file" class="form-label">' . h(lang('File')) . '</label>
            <input type="file" id="file" name="file" class="' . $liveform->create_error_class('file', 'form-control') . '" accept=".xml,.csv,.txt,text/xml,application/xml,text/csv" required>
            ' . pg_upload_limit_hint());

    $buttons = '<button type="submit" class="btn my-1 btn-solid theme-success"><i class="bi bi-upload me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Upload and check')) . '</span></button>';

    $side .= $card(lang('What Is Imported'), '
            <ul class="small ps-5 mb-0">
                <li class="mb-2">' . h(lang('A WordPress export (Tools, Export), a WooCommerce products CSV or a Shopify products or customers CSV. The file is read first and nothing is written until you start.')) . '</li>
                <li>' . h(lang('Nothing is overwritten: an item imported already is skipped and named in the report. Everything the import makes can be undone for 30 days.')) . '</li>
            </ul>');

    $list = '';

    foreach ((array) db_items("SELECT id, kind, file_name, status, counts, created_at FROM imports ORDER BY id DESC LIMIT 50") as $row) {
        $totals = pg_import_totals((array) json_decode((string) $row['counts'], true));
        $list .= '<tr>
            <td><a href="import.php?id=' . (int) $row['id'] . '">' . h($row['file_name']) . '</a></td>
            <td>' . h($kinds[$row['kind']] ?? $row['kind']) . '</td>
            <td>' . h($status_labels[$row['status']] ?? $row['status']) . '</td>
            <td class="text-end">' . h(pg_format_number($totals['made'], 0)) . '</td>
            <td>' . h(get_absolute_time(array('timestamp' => (int) $row['created_at'], 'format' => 'plain_text'))) . '</td>
        </tr>';
    }

    if ($list !== '') {
        $main .= $card(lang('Earlier imports'), '<div class="table-responsive"><table class="table"><thead><tr>
            <th scope="col">' . h(lang('File')) . '</th>
            <th scope="col">' . h(lang('Kind')) . '</th>
            <th scope="col">' . h(lang('Status')) . '</th>
            <th scope="col" class="text-end">' . h(lang('Made')) . '</th>
            <th scope="col">' . h(lang('Uploaded')) . '</th>
        </tr></thead><tbody>' . $list . '</tbody></table></div>');
    }

} else {

    $status = $import['status'];
    $summary = $card(lang('Summary'), '<dl class="mb-0">'
        . $summary_row(lang('File'), (string) $import['file_name'])
        . $summary_row(lang('Kind'), (string) ($kinds[$import['kind']] ?? $import['kind']))
        . $summary_row(lang('Status'), (string) ($status_labels[$status] ?? $status), true) . '
            </dl>');

    if ($status === 'dry_run') {

        $dry = (array) ($import['report']['dry'] ?? array());

        // The dry run tallies what the writer will refuse under failed.
        $side .= $summary . $card(lang('What the file holds'), $counts_table($dry['counts'] ?? array(), array('made' => lang('New'), 'skipped' => lang('Imported already'), 'failed' => lang('Will be skipped')))
            . (empty($dry['complete']) ? '<p class="small fg-secondary mb-0">' . h(lang('The file is large; only its first part was counted. The import reads all of it.')) . '</p>' : '')
            . '<p class="small fg-secondary mt-5 mb-0">' . h(lang('Nothing is overwritten: an item imported already is skipped and named in the report. Everything the import makes can be undone for 30 days.')) . '</p>
            <p class="small fg-secondary mt-3 mb-0">' . h(lang(array('string' => 'An upload that is not started is removed with its file after {var:1} days.', 'vars' => array(PG_IMPORT_UNSTARTED_DAYS)))) . '</p>');

        $mapping = '';

        // The fields of the offered forms go to the script once, by form,
        // and fill a field row's select when a form is chosen, keeping the
        // types the row takes: printed as options in every row they weighed
        // a megabyte on a site with many forms.
        $mapping_rows = $import_mapping_rows($import['kind']);

        foreach ($mapping_rows as $name => $row) {

            $options = '<option value="">' . h(empty($row['required']) ? lang('Leave out') : lang('Choose')) . '</option>';
            $for = '';

            if ($row['type'] === 'form_field') {
                $forms = (array) ($mapping_rows[(string) ($row['form'] ?? '')]['choices'] ?? array());

                foreach ($row['choices'] as $value => $choice) {
                    if (isset($forms[(string) (int) $choice['form']])) {
                        $field_list[(string) (int) $choice['form']][(string) $value] = array((string) $value, (string) $choice['label'], (string) $choice['type']);
                    }
                }

                $for = ' data-pg-import-form="map_' . h((string) ($row['form'] ?? '')) . '" data-pg-import-types="' . h(json_encode(array_values((array) ($row['field_types'] ?? array())))) . '"';
            } else {
                foreach ($row['choices'] as $value => $choice) {
                    $options .= '<option value="' . h((string) $value) . '">' . h((string) $choice) . '</option>';
                }
            }

            $mapping .= '
                <div class="col-12 md:col-6 my-3">
                    <label for="map_' . h($name) . '" class="form-label">' . h((string) ($row['label'] ?? $name)) . '</label>
                    <select id="map_' . h($name) . '" name="map[' . h($name) . ']" class="form-control"' . $for . (!empty($row['required']) ? ' required' : '') . '>' . $options . '</select>
                </div>';
        }

        // Discard is its own form, outside the start form (forms do not
        // nest); its button in the bar names it with the form attribute.
        $before = '<form id="pg_import_discard" action="import.php" method="post">
            ' . get_token_field() . '
            <input type="hidden" name="action" value="discard">
            <input type="hidden" name="id" value="' . (int) $import_id . '">
        </form>';

        $form_open = '<form action="import.php" method="post">
            ' . get_token_field() . '
            <input type="hidden" name="action" value="start">
            <input type="hidden" name="id" value="' . (int) $import_id . '">';
        $form_close = '</form>';

        $main .= $card(lang('Where it goes'), ($mapping !== '' ? '<div class="row">' . $mapping . '
            </div>' : '<p class="mb-0">' . h(lang('Nothing to choose for this kind of export.')) . '</p>'));

        $sample = '';

        foreach ((array) ($dry['lines'] ?? array()) as $line) {
            $sample .= '<tr>
                <td>' . h($writer_kinds[$line['kind'] ?? ''] ?? ($line['kind'] ?? '')) . '</td>
                <td>' . h((string) ($line['title'] ?? '')) . '</td>
                <td>' . h((string) ($line['detail'] ?? '')) . '</td>
                <td>' . (!empty($line['seen']) ? h(lang('Imported already')) : h((string) ($line['refusal'] ?? ''))) . '</td>
            </tr>';
        }

        if ($sample !== '') {
            $main .= $card(lang('First items'), '<div class="table-responsive"><table class="table"><thead><tr>
                <th scope="col">' . h(lang('Kind')) . '</th>
                <th scope="col">' . h(lang('Title')) . '</th>
                <th scope="col">' . h(lang('Details')) . '</th>
                <th scope="col">' . h(lang('Note')) . '</th>
            </tr></thead><tbody>' . $sample . '</tbody></table></div>');
        }

        $buttons = '<button type="submit" class="btn my-1 btn-solid theme-success"><i class="bi bi-play me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Start the import')) . '</span></button>
                <button type="submit" form="pg_import_discard" class="btn my-1 btn-outline theme-secondary"><i class="bi bi-x-lg me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Discard the upload')) . '</span></button>';

    } elseif ($status === 'running') {

        $totals = pg_import_totals($import['counts']);

        $side .= $summary;

        $main .= $card(lang('Importing'), '
            <p id="pg_import_progress" role="status" aria-live="polite">' . h(lang(array('string' => '{var:1} item(s) read: {var:2} made, {var:3} skipped, {var:4} failed.', 'vars' => array((int) $import['cursor'], $totals['made'], $totals['skipped'], $totals['failed'])))) . '</p>
            <p class="small fg-secondary mb-0">' . h(lang('You can leave this screen; the general job carries on.')) . '</p>');

        $main .= $undo_card(lang('Stop and undo the import'));

    } else {

        $report = $card(lang('Report'), $counts_table($import['counts'], array('made' => lang('Made'), 'skipped' => lang('Skipped'), 'failed' => lang('Failed'))));

        $redirects = (int) ($import['counts']['redirect']['made'] ?? 0);

        if (($redirects > 0) && ($status !== 'undone')) {
            $main .= $card(lang('Redirects'), '
                <p>' . h(lang(array('string' => '{var:1} old address(es) now send visitors to what they became (301). They are listed with the short links.', 'vars' => array(pg_format_number($redirects, 0))))) . '</p>
                <a class="btn btn-outline theme-secondary" href="' . h($screen_url . '?id=' . (int) $import['id'] . '&export=redirects') . '"><i class="bi bi-download me-5" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Download the redirects (CSV)')) . '</span></a>');
        }

        $lines = '';
        $line_results = array('made' => lang('Made, with a note'), 'skipped' => lang('Skipped'), 'failed' => lang('Failed'), 'kept' => lang('Kept'));

        foreach ((array) ($import['report']['lines'] ?? array()) as $line) {
            $lines .= '<tr>
                <td>' . h($line_results[$line['result'] ?? ''] ?? lang('Skipped')) . '</td>
                <td>' . h($writer_kinds[$line['kind'] ?? ''] ?? ($line['kind'] ?? '')) . '</td>
                <td>' . h((string) (($line['title'] ?? '') !== '' ? $line['title'] : ($line['key'] ?? ''))) . '</td>
                <td>' . h((string) ($line['reason'] ?? '')) . '</td>
            </tr>';
        }

        if ($lines !== '') {
            $more = (int) ($import['report']['more'] ?? 0);
            $main .= $card(lang('Notes, skipped and failed'), '<div class="table-responsive"><table class="table"><thead><tr>
                <th scope="col">' . h(lang('Result')) . '</th>
                <th scope="col">' . h(lang('Kind')) . '</th>
                <th scope="col">' . h(lang('Item')) . '</th>
                <th scope="col">' . h(lang('Why')) . '</th>
            </tr></thead><tbody>' . $lines . '</tbody></table></div>'
                . (($more > 0) ? '<p class="mb-0">' . h(lang(array('string' => 'and {var:1} more.', 'vars' => array(pg_format_number($more, 0))))) . '</p>' : ''));
        }

        if (pg_import_undo_open($status, $import['finished_at'], time())) {
            $main .= $undo_card(lang('Undo the import'));
        }

        // The counts sit beside the notes and Undo; with neither (undone,
        // or past its 30 days with nothing to note) they fill the form
        // column instead of leaving it empty.
        if ($main === '') {
            $main = $report;
            $side .= $summary;
        } else {
            $side .= $summary . $report;
        }
    }
}

$import_texts = array(
    'progress' => lang('{var:1} item(s) read: {var:2} made, {var:3} skipped, {var:4} failed.'),
    'undo'     => lang('{var:1} removed, {var:2} left.'),
    'kept'     => lang('{var:1} item(s) of a kind this site cannot remove yet stay.'),
    'confirm'  => lang('Remove everything this import made?'),
    'confirm_button' => lang('Undo the import'),
    'error'    => lang('The request did not get an answer; try again.'),
);

print
pg_page_shell(
    array(
        'title' => lang('Import'),
        'extra classes' => 'page',
        'icon' => 'page',
        'heading' => lang('Import from another platform'),
        'heading_description' => lang('Bring in posts, pages, files, comments, products and customers from a WordPress, WooCommerce or Shopify export.'),
        'breadcrumb' => ($import === null)
            ? array(array('label' => lang('Import')))
            : array(array('label' => lang('Import'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/import.php'), array('label' => $import['file_name'])),
    )
) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->output_notices() . '
    ' . $before . '
    ' . $form_open . '
    <div class="row">
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            ' . $main . '
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            <div class="position-sticky" style="top:3.3rem;">
                ' . $side . '
            </div>
        </div>
        ' . (($buttons !== '') ? '<div class="col-12 order-3">
            <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                <div class="container">
                    <div class="btn-group flex-wrap justify-content-center">
                        ' . $buttons . '
                    </div>
                </div>
            </nav>
        </div>' : '') . '
    </div>
    ' . $form_close . '
</main>
<script>
(function () {
    var id = ' . (int) $import_id . ';
    var token = ' . json_encode((string) ($_SESSION['software']['token'] ?? '')) . ';
    var texts = ' . json_encode($import_texts) . ';
    var fill = function (text, vars) {
        return text.replace(/\{var:(\d+)\}/g, function (m, n) { return String(vars[n - 1]); });
    };
    var post = function (step) {
        var body = new URLSearchParams();
        body.set("ajax", "1");
        body.set("id", String(id));
        body.set("token", token);
        body.set("step", step);
        return fetch("import.php", {method: "POST", body: body, credentials: "same-origin"}).then(function (r) { return r.json(); });
    };

    // A field row lists only the fields of the form chosen above it, of the
    // types the row takes (an empty list takes every type).
    var fields = ' . (json_encode((object) array_map('array_values', $field_list), JSON_HEX_TAG | JSON_HEX_AMP) ?: '{}') . ';
    document.querySelectorAll("[data-pg-import-form]").forEach(function (select) {
        var form = document.getElementById(select.getAttribute("data-pg-import-form"));
        if (!form) { return; }
        var types = JSON.parse(select.getAttribute("data-pg-import-types") || "[]");
        var first = select.options[0];
        var sync = function () {
            var kept = select.value;
            while (select.options.length > 1) { select.remove(1); }
            (fields[form.value] || []).forEach(function (field) {
                if (types.length && types.indexOf(field[2]) === -1) { return; }
                var option = new Option(field[1], field[0]);
                select.add(option);
            });
            select.value = kept;
            if (select.value !== kept) { select.value = first.value; }
        };
        form.addEventListener("change", sync);
        sync();
    });

    var undoing = false;
    var progress = document.getElementById("pg_import_progress");
    if (progress) {
        var run = function () {
            if (undoing) { return; }
            post("run").then(function (a) {
                if (undoing) { return; }
                if (!a.ok) { progress.textContent = a.text || texts.error; return; }
                if (a.status === "running" || a.busy) {
                    if (!a.busy) { progress.textContent = fill(texts.progress, [a.position, a.totals.made, a.totals.skipped, a.totals.failed]); }
                    setTimeout(run, a.busy ? 3000 : 200);
                } else {
                    window.location.reload();
                }
            }, function () { progress.textContent = texts.error; });
        };
        run();
    }

    var undo = document.getElementById("pg_import_undo");
    if (undo) {
        var status = document.getElementById("pg_import_undo_progress");
        var step = function () {
            post("undo").then(function (a) {
                if (!a.ok) { status.textContent = a.text || texts.error; undo.disabled = false; return; }
                // Undone already or past its 30 days (a stale tab): the
                // reloaded screen shows where it stands.
                if (a.closed) { window.location.reload(); return; }
                // A run step or another undo holds the import: ask later.
                if (a.busy) { setTimeout(step, 3000); return; }
                status.textContent = fill(texts.undo, [a.removed, a.left]);
                if (a.done) {
                    // The kept count is read before the screen reloads.
                    if (a.kept > 0) {
                        status.textContent = fill(texts.kept, [a.kept]);
                        setTimeout(function () { window.location.reload(); }, 4000);
                    } else {
                        window.location.reload();
                    }
                } else {
                    setTimeout(step, 200);
                }
            }, function () { status.textContent = texts.error; undo.disabled = false; });
        };
        var ask = (typeof window.pgConfirm === "function")
            ? function () { return window.pgConfirm({message: texts.confirm, confirmText: texts.confirm_button, variant: "danger"}); }
            : function () { return Promise.resolve(window.confirm(texts.confirm)); };
        undo.addEventListener("click", function () {
            ask().then(function (yes) {
                if (!yes) { return; }
                undoing = true;
                undo.disabled = true;
                step();
            });
        });
    }
})();
</script>
' .
output_footer();

$liveform->remove_form();
