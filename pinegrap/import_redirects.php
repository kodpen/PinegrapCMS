<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Redirects from a CSV file (includes/fn/redirects.php): one line per old
 * address with the new address and, optionally, 301 or 302 - the file the
 * redirect list exports, or a list from the old site. Every line is checked
 * like a rule saved on its own screen; the lines that cannot be used are
 * listed with their line number and the others are saved. A line for an
 * address that already has a redirect is skipped unless the operator asks for
 * the stored one to be replaced.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

if (pg_post_body_discarded()) {
    output_error(lang('The request was too large for this server and arrived empty.'), 413);
}

$user = validate_user();
validate_area_access($user, 'manager');

if (!pg_redirects_ready()) {
    output_error(h(lang('The redirect tables are added by the software update. Run the update to use redirects.')));
}

include_once('liveform.class.php');
$liveform = new liveform('import_redirects');

$list_url = PATH . SOFTWARE_DIRECTORY . '/view_redirects.php';
$screen_url = PATH . SOFTWARE_DIRECTORY . '/import_redirects.php';

// Largest file and most lines one import reads. Each line is checked against
// every rule, so a much longer list belongs in several files.
$import_max_bytes = 5 * 1024 * 1024;
$import_max_rows = 10000;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $liveform->add_fields_to_session();

    $upload = (isset($_FILES['file']) && is_array($_FILES['file'])) ? $_FILES['file'] : null;

    if (($upload === null) || !is_string($upload['tmp_name'] ?? null) || ((int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) || !is_uploaded_file($upload['tmp_name'])) {
        $liveform->mark_error('file', h(lang('Choose a CSV file to import.')));
        go($screen_url);
    }

    if ((int) $upload['size'] > $import_max_bytes) {
        $liveform->mark_error('file', h(lang(array('string' => 'The file is larger than {var:1}; split it into smaller files.', 'vars' => array(convert_bytes_to_string($import_max_bytes))))));
        go($screen_url);
    }

    $rows = pg_redirect_csv_rows((string) file_get_contents($upload['tmp_name']));

    if (!$rows) {
        $liveform->mark_error('file', h(lang('The file has no redirects. Each line needs the old address and the new one, separated by a comma.')));
        go($screen_url);
    }

    if (count($rows) > $import_max_rows) {
        $liveform->mark_error('file', h(lang(array('string' => 'The file has more than {var:1} lines; split it into smaller files.', 'vars' => array(pg_format_number($import_max_rows, 0))))));
        go($screen_url);
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $summary = pg_redirect_import($rows, ($liveform->get_field_value('replace') === '1'), (int) $user['id']);

    $saved_count = $summary['added'] + $summary['updated'];

    if ($saved_count > 0) {
        log_activity(lang(array('string' => '{var:1} redirect(s) imported from a CSV file', 'vars' => array($saved_count))), $_SESSION['sessionusername']);
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
            'string' => 'No redirect was imported: {var:1} line(s) skipped because a redirect for the address already exists, {var:2} line(s) could not be used.',
            'vars' => array($summary['skipped'], count($summary['errors'])),
        ))));

        foreach ($problems as $problem) {
            $liveform->add_error($problem);
        }

        go($screen_url);
    }

    $liveform->remove_form();

    $list_form = new liveform('view_redirects');

    $list_form->add_notice(h(lang(array(
        'string' => 'Imported: {var:1} redirect(s) added, {var:2} changed, {var:3} skipped because a redirect for the address already exists.',
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
        'title' => lang('Import Redirects'),
        'extra classes' => 'page',
        'icon' => 'page',
        'heading' => lang('Import Redirects'),
        'heading_description' => lang('Add many redirects at once from a CSV file, for example the list of addresses of an old site.'),
        'cancel' => array('enable' => 'true', 'url' => 'view_redirects.php'),
        'breadcrumb' => array(
            array('label' => lang('Redirects'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_redirects.php'),
            array('label' => lang('Import Redirects')),
        ),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <form name="form" action="import_redirects.php" method="post" enctype="multipart/form-data">
                ' . get_token_field() . '
                <div class="row">
                    <div class="col-12 col-lg-8 col-xxl-9 order-1">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('CSV File')) . '</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-md-8 my-2">
                                        <label for="file" class="form-label">' . h(lang('File')) . '</label>
                                        <input type="file" id="file" name="file" class="form-control" accept=".csv,.txt,text/csv,text/plain" required>
                                    </div>
                                    <div class="col-12 my-2">
                                        <div class="form-check">
                                            ' . $liveform->output_field(array('type' => 'checkbox', 'name' => 'replace', 'id' => 'replace', 'value' => '1', 'class' => 'form-check-input')) . '
                                            <label class="form-check-label" for="replace">' . h(lang('Replace the new address and the type of redirects that already exist for an address in the file')) . '</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4 col-xxl-3 order-2">
                        <div class="card my-4 position-sticky" style="top:56px;">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('File Format')) . '</div>
                            <div class="card-body">
                                <p>' . h(lang('One redirect per line: the old address, the new address and, if you like, 301 (permanent, the default) or 302 (temporary). A first line with column names is skipped.')) . '</p>
                                <pre class="bg-body-tertiary border rounded p-2 small mb-3">from,to,code
/old-page,/new-page,301
/blog/*,/news/$1,301
https://old.site/about,/about,</pre>
                                <p class="small text-body-secondary mb-0">' . h(lang('Commas, semicolons or tabs can separate the columns. The redirect list exports its rules in this form.')) . '</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 order-3">
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="' . h(lang('Data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="submit_import" name="submit_save" value="Import" class="btn my-1 btn-success"><i class="bi bi-upload me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang('Import Redirects')) . '</span></button>
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
