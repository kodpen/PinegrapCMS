<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Adds many short links to addresses at once from a CSV file, for example the
 * addresses of an old site and where each now lives (from,to,code). Each line
 * is checked as a link saved on its own screen; pg_short_link_import() in
 * includes/fn/short_link_redirects.php does the work, and the short link list
 * exports its links in the same form.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

pg_upload_refuse_discarded_body();

$user = validate_user();
validate_area_access($user, 'manager');

// The answer and the pattern flag the lines are written with come with the
// 2026.11.0 upgrade (11.0.12).
if (!pg_short_link_redirects_ready()) {
    output_error(h(lang('Redirect answers come with an upgrade this site has not run yet.')));
}

include_once('liveform.class.php');
$liveform = new liveform('import_short_links');

$list_url = PATH . SOFTWARE_DIRECTORY . '/view_short_links.php';
$screen_url = PATH . SOFTWARE_DIRECTORY . '/import_short_links.php';

// Largest file and most lines one import reads. Each line is checked against
// every link, so a much longer list belongs in several files.
$import_max_bytes = 5 * 1024 * 1024;
$import_max_rows = 10000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $liveform->add_fields_to_session();

    $upload = (isset($_FILES['file']) && is_array($_FILES['file'])) ? $_FILES['file'] : null;

    $upload_limits = pg_upload_limits();
    $upload_refusal = pg_upload_file_refusal($upload, $upload_limits['file_max']);

    if ($upload_refusal !== '') {
        $liveform->mark_error('file', h($upload_refusal));
        go($screen_url);
    }

    if (($upload === null) || !is_string($upload['tmp_name'] ?? null) || ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) || !is_uploaded_file($upload['tmp_name'])) {
        $liveform->mark_error('file', h(lang('Choose a CSV file to import.')));
        go($screen_url);
    }

    if ((int) $upload['size'] > $import_max_bytes) {
        $liveform->mark_error('file', h(lang(array('string' => 'The file is larger than {var:1}; split it into smaller files.', 'vars' => array(convert_bytes_to_string($import_max_bytes))))));
        go($screen_url);
    }

    $rows = pg_short_link_csv_rows((string) file_get_contents($upload['tmp_name']));

    if (!$rows) {
        $liveform->mark_error('file', h(lang('The file has no lines to import. Each line needs the old address and the new one, separated by a comma.')));
        go($screen_url);
    }

    if (count($rows) > $import_max_rows) {
        $liveform->mark_error('file', h(lang(array('string' => 'The file has more than {var:1} lines; split it into smaller files.', 'vars' => array(pg_format_number($import_max_rows, 0))))));
        go($screen_url);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $summary = pg_short_link_import($rows, ($liveform->get_field_value('replace') === '1'), (int) $user['id']);

    $saved_count = $summary['added'] + $summary['updated'];

    if ($saved_count > 0) {
        log_activity(lang(array('string' => '{var:1} short link(s) imported from a CSV file', 'vars' => array($saved_count))), $_SESSION['sessionusername']);
    }

    // The lines that could not be used, with their numbers: the first ones
    // in full, the rest as a count.
    $problems = array();

    foreach (array_slice($summary['errors'], 0, 20) as $error) {
        $problems[] = h(lang(array('string' => 'Line {var:1}: {var:2}', 'vars' => array($error['line'], $error['message']))));
    }

    if (count($summary['errors']) > 20) {
        $problems[] = h(lang(array('string' => 'and {var:1} more line(s) that could not be used.', 'vars' => array(count($summary['errors']) - 20))));
    }

    if ($saved_count === 0) {

        $liveform->add_error(h(lang(array(
            'string' => 'No short link was imported: {var:1} line(s) skipped because a short link has the name already, {var:2} line(s) could not be used.',
            'vars' => array($summary['skipped'], count($summary['errors'])),
        ))));

        foreach ($problems as $problem) {
            $liveform->add_error($problem);
        }

        go($screen_url);
    }

    $liveform->remove_form();

    $list_form = new liveform('view_short_links');

    $list_form->add_notice(h(lang(array(
        'string' => 'Imported: {var:1} short link(s) added, {var:2} changed, {var:3} skipped because a short link has the name already.',
        'vars' => array($summary['added'], $summary['updated'], $summary['skipped']),
    ))));

    if ($problems) {
        $list_form->add_warning(h(lang(array('string' => '{var:1} line(s) could not be used:', 'vars' => array(count($summary['errors']))))) . '<br>' . implode('<br>', $problems));
    }

    go($list_url);
}

print
pg_page_shell(
    array(
        'title' => lang('Import Short Links'),
        'extra classes' => 'page',
        'icon' => 'page',
        'heading' => lang('Import Short Links'),
        'heading_description' => lang('Add many short links at once from a CSV file, for example the addresses of an old site and where each one lives now.'),
        'cancel' => array('enable' => 'true', 'url' => 'view_short_links.php'),
        'breadcrumb' => array(
            array('label' => lang('Short Links'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_short_links.php'),
            array('label' => lang('Import Short Links')),
        ),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <form name="form" action="import_short_links.php" method="post" enctype="multipart/form-data">
                ' . get_token_field() . '
                <div class="row">
                    <div class="col-12 lg:col-8 2xl:col-9 order-1">
                        <div class="card my-7">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('CSV file')) . '</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 md:col-8 my-3">
                                        <label for="file" class="form-label">' . h(lang('File')) . '</label>
                                        <input type="file" id="file" name="file" class="form-control" accept=".csv,.txt,text/csv,text/plain" required>
                                        ' . pg_upload_limit_hint($import_max_bytes) . '
                                    </div>
                                    <div class="col-12 my-3">
                                        <div class="form-field">
                                            ' . $liveform->output_field(array('type' => 'checkbox', 'name' => 'replace', 'id' => 'replace', 'value' => '1', 'class' => 'check')) . '
                                            <label class="" for="replace">' . h(lang('Where a short link to an address already has the name, give it the new address and code from the file')) . '</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 lg:col-4 2xl:col-3 order-2">
                        <div class="card my-7 position-sticky" style="top:56px;">
                            <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . h(lang('File Format')) . '</div>
                            <div class="card-body">
                                <p>' . h(lang('One short link per line: the old address, where it sends visitors and, if you like, 301 (for good, the default), 302 (for now) or 0 (as a short link). A first line with column names is skipped.')) . '</p>
                                <pre class="bg-1 border rounded-4 p-3 small mb-5">from,to,code
/eski-sayfa,/yeni-sayfa,301
/blog/*,/yazilar/$1,301
/hakkinda.html,/hakkimizda,</pre>
                                <p class="small fg-secondary mb-0">' . h(lang('Commas, semicolons or tabs can separate the columns. The short link list exports its links in this form.')) . '</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3">
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_import" name="submit_save" value="Import" class="btn my-1 btn-solid theme-success"><i class="bi bi-upload me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Import Short Links')) . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
