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

        if ($undo['done']) {
            log_activity(lang(array('string' => 'import {var:1} was undone', 'vars' => array($import_id))), $_SESSION['sessionusername'] ?? '');
        }

        $import_json(200, array('ok' => true, 'removed' => $undo['removed'], 'left' => $undo['left'], 'kept' => $undo['kept'], 'done' => $undo['done']));
    }

    $import_json(400, array('ok' => false, 'text' => lang('Invalid action.')));
}

// The choices of each mapping row type, for the rows a reader asks for.
$import_choices = function ($type) {

    $choices = array();

    if ($type === 'form') {
        foreach ((array) db_items("SELECT custom_form_pages.page_id, custom_form_pages.form_name, page.page_name, page.page_folder
            FROM custom_form_pages
            JOIN page ON custom_form_pages.page_id = page.page_id
            ORDER BY custom_form_pages.form_name, page.page_name") as $row) {
            if (check_edit_access($row['page_folder'])) {
                $choices[(string) (int) $row['page_id']] = ((string) $row['form_name'] !== '') ? (string) $row['form_name'] : (string) $row['page_name'];
            }
        }
    } elseif ($type === 'form_field') {
        foreach ((array) db_items("SELECT id, page_id, label, name FROM form_fields WHERE page_id > 0 ORDER BY page_id, sort_order, id") as $row) {
            $choices[(string) (int) $row['id']] = array('label' => ((string) $row['label'] !== '') ? (string) $row['label'] : (string) $row['name'], 'form' => (int) $row['page_id']);
        }
    } elseif ($type === 'folder') {
        foreach ((array) db_items("SELECT folder_id, folder_name FROM folder WHERE (folder_archived IS NULL) OR (folder_archived <> '1') ORDER BY folder_name") as $row) {
            if (check_edit_access($row['folder_id'])) {
                $choices[(string) (int) $row['folder_id']] = (string) $row['folder_name'];
            }
        }
    } elseif ($type === 'product_group') {
        foreach ((array) db_items("SELECT id, name FROM product_groups WHERE 1 = 1" . pg_catalog_bin_live_sql('product_groups') . " ORDER BY name") as $row) {
            $choices[(string) (int) $row['id']] = (string) $row['name'];
        }
    }

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

    return '<table class="table"><thead><tr>' . $head . '</tr></thead><tbody>' . $rows . '</tbody></table>';
};

$body = '';

if ($import === null) {

    $body .= '<form name="form" action="import.php" method="post" enctype="multipart/form-data">
        ' . get_token_field() . '
        <input type="hidden" name="action" value="upload">
        ' . $card(lang('Export file'), '
            <label for="file" class="form-label">' . h(lang('File')) . '</label>
            <input type="file" id="file" name="file" class="form-control" accept=".xml,.csv,.txt,text/xml,application/xml,text/csv" required>
            ' . pg_upload_limit_hint() . '
            <p class="small fg-secondary mt-5 mb-12">' . h(lang('A WordPress export (Tools, Export), a WooCommerce products CSV or a Shopify products or customers CSV. The file is read first and nothing is written until you start.')) . '</p>
            <button type="submit" class="btn btn-solid theme-success"><i class="bi bi-upload me-5" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Upload and check')) . '</span></button>') . '
    </form>';

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
        $body .= $card(lang('Earlier imports'), '<table class="table"><thead><tr>
            <th scope="col">' . h(lang('File')) . '</th>
            <th scope="col">' . h(lang('Kind')) . '</th>
            <th scope="col">' . h(lang('Status')) . '</th>
            <th scope="col" class="text-end">' . h(lang('Made')) . '</th>
            <th scope="col">' . h(lang('Uploaded')) . '</th>
        </tr></thead><tbody>' . $list . '</tbody></table>');
    }

} else {

    $status = $import['status'];
    $body .= '<p class="mb-0">' . h(lang(array('string' => '{var:1}, {var:2}: {var:3}', 'vars' => array($import['file_name'], $kinds[$import['kind']] ?? $import['kind'], $status_labels[$status] ?? $status)))) . '</p>';

    if ($status === 'dry_run') {

        $dry = (array) ($import['report']['dry'] ?? array());

        $body .= $card(lang('What the file holds'), $counts_table($dry['counts'] ?? array(), array('made' => lang('New'), 'skipped' => lang('Imported already')))
            . (empty($dry['complete']) ? '<p class="small fg-secondary mb-0">' . h(lang('The file is large; only its first part was counted. The import reads all of it.')) . '</p>' : ''));

        $sample = '';

        foreach ((array) ($dry['lines'] ?? array()) as $line) {
            $sample .= '<tr>
                <td>' . h($writer_kinds[$line['kind'] ?? ''] ?? ($line['kind'] ?? '')) . '</td>
                <td>' . h((string) ($line['title'] ?? '')) . '</td>
                <td>' . h((string) ($line['detail'] ?? '')) . '</td>
                <td>' . (!empty($line['seen']) ? h(lang('Imported already')) : '') . '</td>
            </tr>';
        }

        if ($sample !== '') {
            $body .= $card(lang('First items'), '<table class="table"><thead><tr>
                <th scope="col">' . h(lang('Kind')) . '</th>
                <th scope="col">' . h(lang('Title')) . '</th>
                <th scope="col">' . h(lang('Details')) . '</th>
                <th scope="col"></th>
            </tr></thead><tbody>' . $sample . '</tbody></table>');
        }

        $mapping = '';

        foreach ($import_mapping_rows($import['kind']) as $name => $row) {

            $options = '<option value="">' . h(empty($row['required']) ? lang('Leave out') : lang('Choose')) . '</option>';

            foreach ($row['choices'] as $value => $choice) {
                $label = is_array($choice) ? (string) $choice['label'] : (string) $choice;
                $form_attr = is_array($choice) ? ' data-form="' . (int) $choice['form'] . '"' : '';
                $options .= '<option value="' . h((string) $value) . '"' . $form_attr . '>' . h($label) . '</option>';
            }

            $for = ($row['type'] === 'form_field') ? ' data-pg-import-form="map_' . h((string) ($row['form'] ?? '')) . '"' : '';

            $mapping .= '<div class="row my-5">
                <label for="map_' . h($name) . '" class="col-12 md:col-4 form-label">' . h((string) ($row['label'] ?? $name)) . '</label>
                <div class="col-12 md:col-8"><select id="map_' . h($name) . '" name="map[' . h($name) . ']" class="form-control"' . $for . (!empty($row['required']) ? ' required' : '') . '>' . $options . '</select></div>
            </div>';
        }

        $body .= '<form action="import.php" method="post">
            ' . get_token_field() . '
            <input type="hidden" name="action" value="start">
            <input type="hidden" name="id" value="' . (int) $import_id . '">
            ' . $card(lang('Where it goes'), ($mapping !== '' ? $mapping : '<p>' . h(lang('Nothing to choose for this kind of export.')) . '</p>')
                . '<p class="small fg-secondary">' . h(lang('Nothing is overwritten: an item imported already is skipped and named in the report. Everything the import makes can be undone for 30 days.')) . '</p>
                <button type="submit" class="btn btn-solid theme-success"><i class="bi bi-play me-5" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Start the import')) . '</span></button>') . '
        </form>';

    } elseif ($status === 'running') {

        $totals = pg_import_totals($import['counts']);

        $body .= $card(lang('Importing'), '
            <p id="pg_import_progress" role="status" aria-live="polite">' . h(lang(array('string' => '{var:1} item(s) read: {var:2} made, {var:3} skipped, {var:4} failed.', 'vars' => array((int) $import['cursor'], $totals['made'], $totals['skipped'], $totals['failed'])))) . '</p>
            <p class="small fg-secondary mb-0">' . h(lang('You can leave this screen; the general job carries on.')) . '</p>');

    } else {

        $body .= $card(lang('Report'), $counts_table($import['counts'], array('made' => lang('Made'), 'skipped' => lang('Skipped'), 'failed' => lang('Failed'))));

        $lines = '';
        $line_results = array('made' => lang('Made, with a note'), 'skipped' => lang('Skipped'), 'failed' => lang('Failed'));

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
            $body .= $card(lang('Notes, skipped and failed'), '<table class="table"><thead><tr>
                <th scope="col">' . h(lang('Result')) . '</th>
                <th scope="col">' . h(lang('Kind')) . '</th>
                <th scope="col">' . h(lang('Item')) . '</th>
                <th scope="col">' . h(lang('Why')) . '</th>
            </tr></thead><tbody>' . $lines . '</tbody></table>'
                . (($more > 0) ? '<p class="mb-0">' . h(lang(array('string' => 'and {var:1} more.', 'vars' => array(pg_format_number($more, 0))))) . '</p>' : ''));
        }

        if (pg_import_undo_open($status, $import['finished_at'], time())) {
            $body .= $card(lang('Undo'), '
                <p>' . h(lang('Removes everything this import made. Items that were changed since are removed too.')) . '</p>
                <p id="pg_import_undo_progress" role="status" aria-live="polite"></p>
                <button type="button" id="pg_import_undo" class="btn btn-outline theme-danger"><i class="bi bi-arrow-counterclockwise me-5" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Undo the import')) . '</span></button>');
        }
    }
}

$import_texts = array(
    'progress' => lang('{var:1} item(s) read: {var:2} made, {var:3} skipped, {var:4} failed.'),
    'undo'     => lang('{var:1} removed, {var:2} left.'),
    'kept'     => lang('{var:1} item(s) of a kind this site cannot remove yet stay.'),
    'confirm'  => lang('Remove everything this import made?'),
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
    <div class="row">
        <div class="col-12 lg:col-10 2xl:col-8">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            ' . $body . '
        </div>
    </div>
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

    // A field row lists only the fields of the form chosen above it.
    document.querySelectorAll("[data-pg-import-form]").forEach(function (select) {
        var form = document.getElementById(select.getAttribute("data-pg-import-form"));
        if (!form) { return; }
        var sync = function () {
            select.querySelectorAll("option[data-form]").forEach(function (option) {
                var other = option.getAttribute("data-form") !== form.value;
                option.hidden = other;
                option.disabled = other;
                if (other && option.selected) { select.value = ""; }
            });
        };
        form.addEventListener("change", sync);
        sync();
    });

    var progress = document.getElementById("pg_import_progress");
    if (progress) {
        var run = function () {
            post("run").then(function (a) {
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
                status.textContent = fill(texts.undo, [a.removed, a.left]);
                if (a.done) {
                    if (a.kept > 0) { status.textContent = fill(texts.kept, [a.kept]); }
                    window.location.reload();
                } else {
                    setTimeout(step, 200);
                }
            }, function () { status.textContent = texts.error; undo.disabled = false; });
        };
        undo.addEventListener("click", function () {
            if (!window.confirm(texts.confirm)) { return; }
            undo.disabled = true;
            step();
        });
    }
})();
</script>
' .
output_footer();

$liveform->remove_form();
