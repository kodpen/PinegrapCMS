<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Personal data export: everything the site keeps about one person, for a
 * KVKK or GDPR request. The person is looked up by e-mail address, or opened
 * from their contact or user (?contact_id=, ?user_id=); the screen shows what
 * was found section by section, and hands it out as a JSON file (the full
 * record) or a PDF summary (includes/fn/privacy_export.php). Each download
 * is a line in the audit trail.
 *
 * The address is sent with POST, never in the address bar, so it stays out
 * of server logs and the browser's history.
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

// Without the audit trail an export could not be accounted for.
if (!pg_audit_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

include_once('liveform.class.php');
$liveform = new liveform('privacy_export');

$privacy_export_input = array('email' => '', 'user_id' => 0, 'contact_id' => 0);
$privacy_export_format = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_token_field();

    $privacy_export_input['email'] = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $privacy_export_input['user_id'] = is_scalar($_POST['user_id'] ?? null) ? (int) $_POST['user_id'] : 0;
    $privacy_export_input['contact_id'] = is_scalar($_POST['contact_id'] ?? null) ? (int) $_POST['contact_id'] : 0;

    $privacy_export_download = is_string($_POST['download'] ?? null) ? $_POST['download'] : '';

    if (in_array($privacy_export_download, array('json', 'pdf'), true)) {
        $privacy_export_format = $privacy_export_download;
    }

    if (($privacy_export_input['email'] !== '') && (pg_privacy_normalize_email($privacy_export_input['email']) === '')) {
        $liveform->mark_error('email', lang('Enter one e-mail address.'));
    }
} else {
    $privacy_export_input['user_id'] = is_scalar($_GET['user_id'] ?? null) ? (int) $_GET['user_id'] : 0;
    $privacy_export_input['contact_id'] = is_scalar($_GET['contact_id'] ?? null) ? (int) $_GET['contact_id'] : 0;
}

$privacy_export_result = null;

if (!$liveform->check_form_errors()) {
    $privacy_export_result = pg_privacy_export_build(
        $privacy_export_input,
        array('role' => (int) $user['role'], 'id' => (int) $user['id']),
        array(
            'site'        => defined('HOSTNAME_SETTING') ? (string) HOSTNAME_SETTING : (string) HOSTNAME,
            'prepared_by' => (string) ($user['username'] ?? ''),
        )
    );
}

$privacy_export_pdf_ready = is_file(PG_FUNCTIONS_DIR . '/includes/dompdf/autoload.inc.php');

// A download: the file, or the screen again with the reason it was not made.
if (($privacy_export_format !== '') && is_array($privacy_export_result) && ($privacy_export_result['status'] === 'ok')) {
    $privacy_export_document = $privacy_export_result['document'];

    $privacy_export_bytes = ($privacy_export_format === 'pdf')
        ? ($privacy_export_pdf_ready ? pg_privacy_export_pdf(pg_privacy_export_summary_html($privacy_export_document)) : false)
        : pg_privacy_export_json($privacy_export_document);

    if (is_string($privacy_export_bytes) && ($privacy_export_bytes !== '')) {
        pg_privacy_export_log($privacy_export_document, $privacy_export_format);

        header('Content-Type: ' . (($privacy_export_format === 'pdf') ? 'application/pdf' : 'application/json; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . pg_privacy_export_filename($privacy_export_result['found']['person'], $privacy_export_format) . '"');
        header('Content-Length: ' . strlen($privacy_export_bytes));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');

        echo $privacy_export_bytes;
        exit();
    }

    $liveform->add_error(lang('The PDF summary could not be made on this server. The JSON file holds the same record.'));
}

$liveform->assign_field_value('email', $privacy_export_input['email']);

// The person as found, and what the site keeps about them.
$privacy_export_body = '';
$privacy_export_side = '';

if (is_array($privacy_export_result) && ($privacy_export_result['status'] === 'refused')) {

    $privacy_export_body = '
            <div class="alert theme-warning">' . h(lang('This person has a user account with a role as high as yours or higher. Ask an administrator to export their data.')) . '</div>';

} elseif (is_array($privacy_export_result) && ($privacy_export_result['status'] === 'not_found')) {

    $privacy_export_body = '
            <div class="alert theme-info">' . h(lang('The site keeps nothing for this person.')) . '</div>';

} elseif (is_array($privacy_export_result) && ($privacy_export_result['status'] === 'ok')) {

    $privacy_export_document = $privacy_export_result['document'];
    $privacy_export_found = $privacy_export_result['found'];

    $privacy_export_rows = '';

    foreach ($privacy_export_document['sections'] as $privacy_export_section) {
        $privacy_export_count = (int) $privacy_export_section['count'];

        $privacy_export_rows .= '
                        <tr' . (($privacy_export_count === 0) ? ' class="fg-secondary"' : '') . '>
                            <td>' . h($privacy_export_section['label']) . '</td>
                            <td class="text-end">' . pg_format_number($privacy_export_count, 0) . (!empty($privacy_export_section['truncated']) ? ' <span class="badge theme-warning" title="' . h(lang(array('string' => 'Cut at {var:1} records.', 'vars' => array(pg_privacy_export_row_limit())))) . '">+</span>' : '') . '</td>
                        </tr>';
    }

    $privacy_export_body = '
            <div class="card mb-12">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('What the site keeps')) . '</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>' . h(lang('Section')) . '</th>
                                <th class="text-end">' . h(lang('Records')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $privacy_export_rows . '
                        </tbody>
                    </table>
                </div>
            </div>';

    // Who was found: the addresses, and each record with the way to its screen.
    $privacy_export_people = '';

    foreach ($privacy_export_found['users'] as $privacy_export_user) {
        $privacy_export_people .= '
                        <li class="mb-1"><i class="bi bi-person me-1" aria-hidden="true"></i><a href="edit_user.php?id=' . (int) $privacy_export_user['user_id'] . '">' . h($privacy_export_user['user_username']) . '</a> <span class="fg-secondary small">' . h(pg_user_role_name($privacy_export_user['user_role'])) . '</span></li>';
    }

    foreach ($privacy_export_found['contacts'] as $privacy_export_contact) {
        $privacy_export_name = trim($privacy_export_contact['first_name'] . ' ' . $privacy_export_contact['last_name']);

        if ($privacy_export_name === '') {
            $privacy_export_name = ((string) $privacy_export_contact['company'] !== '') ? (string) $privacy_export_contact['company'] : '#' . (int) $privacy_export_contact['id'];
        }

        $privacy_export_people .= '
                        <li class="mb-1"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i><a href="edit_contact.php?id=' . (int) $privacy_export_contact['id'] . '">' . h($privacy_export_name) . '</a> <span class="fg-secondary small">' . h(lang('Contact')) . '</span></li>';
    }

    $privacy_export_emails = '';

    foreach ($privacy_export_found['person']['emails'] as $privacy_export_email) {
        $privacy_export_emails .= '<div class="text-break">' . h($privacy_export_email) . '</div>';
    }

    $privacy_export_hidden = '';

    foreach ($privacy_export_input as $privacy_export_key => $privacy_export_value) {
        $privacy_export_hidden .= '<input type="hidden" name="' . h($privacy_export_key) . '" value="' . h((string) $privacy_export_value) . '">';
    }

    $privacy_export_side = '
            <div class="position-sticky" style="top:3.3rem;">
                <div class="card mb-12">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Who this is about')) . '</span>
                    </div>
                    <div class="card-body">
                        <div class="small fg-secondary">' . h(lang('E-mail Address')) . '</div>
                        <div class="mb-5">' . (($privacy_export_emails !== '') ? $privacy_export_emails : '<span class="fg-secondary">' . h(lang('None')) . '</span>') . '</div>
                        ' . (($privacy_export_people !== '') ? '<ul class="list-unstyled mb-5">' . $privacy_export_people . '</ul>' : '') . '
                        <form action="privacy_export.php" method="post" class="disable_shortcut d-flex flex-wrap gap-3">
                            ' . get_token_field() . $privacy_export_hidden . '
                            <button type="submit" name="download" value="json" class="btn btn-sm btn-solid theme-primary rounded-pill px-5"><i class="bi bi-filetype-json me-1" aria-hidden="true"></i>' . h(lang('Download JSON')) . '</button>
                            ' . ($privacy_export_pdf_ready ? '<button type="submit" name="download" value="pdf" class="btn btn-sm btn-outline theme-secondary rounded-pill px-5"><i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>' . h(lang('Download PDF summary')) . '</button>' : '') . '
                        </form>
                        <p class="small fg-secondary mt-5 mb-0">' . h(lang('The JSON file is the full record; the PDF summary is the version to send to the person. Each download is recorded in the audit trail.')) . '</p>
                    </div>
                </div>
            </div>';
}

$privacy_export_search = '
            <div class="card mb-12">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 fg-primary fw-bold mb-0">' . h(lang('Find a person')) . '</span>
                </div>
                <div class="card-body">
                    <form action="privacy_export.php" method="post" class="disable_shortcut row g-3 align-items-end">
                        ' . get_token_field() . '
                        <div class="col-12 md:col-8 xl:col-6">
                            <label for="email" class="form-label">' . h(lang('E-mail Address')) . '</label>
                            ' . $liveform->output_field(array('type' => 'email', 'id' => 'email', 'name' => 'email', 'class' => 'form-control', 'maxlength' => '254', 'autocomplete' => 'off', 'required' => 'true')) . '
                        </div>
                        <div class="col-12 md:col-auto">
                            <button type="submit" class="btn btn-solid theme-primary rounded-pill px-5"><i class="bi bi-search me-1" aria-hidden="true"></i>' . h(lang('Find')) . '</button>
                        </div>
                        <div class="col-12 small fg-secondary">' . h(lang('The user accounts and contacts with this address are found, with the records tied to them. A contact or user can also be opened here from its own screen.')) . '</div>
                    </form>
                </div>
            </div>';

echo
pg_page_shell(array(
        'title'               => lang('Personal data export'),
        'extra classes'       => 'contact',
        'icon'                => 'contact',
        'heading'             => lang('Personal data export'),
        'heading_description' => lang('Everything the site keeps about one person, as a JSON file and a PDF summary, for a request under KVKK or GDPR.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
        </div>
        <div class="col-12 lg:col-8 2xl:col-9 order-1">
            ' . $privacy_export_search . '
            ' . $privacy_export_body . '
        </div>
        <div class="col-12 lg:col-4 2xl:col-3 order-2">
            ' . $privacy_export_side . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
