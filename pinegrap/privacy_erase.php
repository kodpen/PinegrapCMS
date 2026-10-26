<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Personal data erasure: removing one person for a KVKK or GDPR request. The
 * person is looked up by e-mail address, or opened from their contact or
 * user (?contact_id=, ?user_id=), as on the export screen. The first step is
 * a dry run: what will be deleted and what will be kept with the personal
 * fields replaced (orders and ERP invoices stay for the legal retention
 * period). The erasure itself needs a confirmation and is one line in the
 * audit trail (includes/fn/privacy.php).
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

// Without the audit trail an erasure could not be accounted for.
if (!pg_audit_ready()) {
    output_error(lang('The database has not been upgraded yet. Run the software update first.'));
}

include_once('liveform.class.php');
$liveform = new liveform('privacy_erase');

$privacy_erase_input = array('email' => '', 'user_id' => 0, 'contact_id' => 0);
$privacy_erase_apply = false;
$privacy_erase_email_ok = true;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validate_token_field();

    $privacy_erase_input['email'] = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $privacy_erase_input['user_id'] = is_scalar($_POST['user_id'] ?? null) ? (int) $_POST['user_id'] : 0;
    $privacy_erase_input['contact_id'] = is_scalar($_POST['contact_id'] ?? null) ? (int) $_POST['contact_id'] : 0;

    if (($privacy_erase_input['email'] !== '') && (pg_privacy_normalize_email($privacy_erase_input['email']) === '')) {
        $liveform->mark_error('email', lang('Enter one e-mail address.'));
        $privacy_erase_email_ok = false;
    }

    // Without the box ticked the dry run is shown again.
    if ($privacy_erase_email_ok && (($_POST['erase'] ?? '') === '1')) {
        if (($_POST['confirm'] ?? '') === '1') {
            $privacy_erase_apply = true;
        } else {
            $liveform->mark_error('confirm', lang('Confirm that the erasure cannot be undone.'));
        }
    }
} else {
    $privacy_erase_input['user_id'] = is_scalar($_GET['user_id'] ?? null) ? (int) $_GET['user_id'] : 0;
    $privacy_erase_input['contact_id'] = is_scalar($_GET['contact_id'] ?? null) ? (int) $_GET['contact_id'] : 0;
}

$privacy_erase_result = null;

if ($privacy_erase_email_ok) {
    $privacy_erase_result = pg_privacy_erase(
        $privacy_erase_input,
        array('role' => (int) $user['role'], 'id' => (int) $user['id']),
        $privacy_erase_apply
    );
}

$privacy_erase_done = $privacy_erase_apply && is_array($privacy_erase_result) && ($privacy_erase_result['status'] === 'ok');

if ($privacy_erase_done) {
    log_activity(lang('personal data of a person was erased'), $_SESSION['sessionusername']);
    $liveform->add_notice(lang('The person has been erased. The records below were deleted or kept with their personal fields replaced.'));
}

$liveform->assign_field_value('email', $privacy_erase_done ? '' : $privacy_erase_input['email']);

$privacy_erase_body = '';
$privacy_erase_side = '';

if (is_array($privacy_erase_result) && in_array($privacy_erase_result['status'], array('refused', 'self'), true)) {

    $privacy_erase_body = '
            <div class="alert alert-warning">' . h(($privacy_erase_result['status'] === 'self')
                ? lang('This is your own account. Ask another administrator to erase it.')
                : lang('This person has a user account with a role as high as yours or higher. Ask an administrator to erase their data.')) . '</div>';

} elseif (is_array($privacy_erase_result) && ($privacy_erase_result['status'] === 'not_found')) {

    $privacy_erase_body = '
            <div class="alert alert-info">' . h(lang('The site keeps nothing for this person.')) . '</div>';

} elseif (is_array($privacy_erase_result) && ($privacy_erase_result['status'] === 'ok')) {

    $privacy_erase_tables = pg_privacy_erase_tables();
    $privacy_erase_labels = pg_privacy_erase_labels();
    $privacy_erase_rows = array('delete' => '', 'anonymise' => '');

    foreach ($privacy_erase_result['tables'] as $privacy_erase_table => $privacy_erase_done_table) {
        $privacy_erase_fields = array_keys($privacy_erase_tables[$privacy_erase_table]['fields']);

        $privacy_erase_rows[$privacy_erase_done_table['action']] .= '
                        <tr>
                            <td>' . h($privacy_erase_labels[$privacy_erase_table] ?? $privacy_erase_table)
                                . ($privacy_erase_fields ? '<div class="small text-body-secondary text-break">' . h(implode(', ', $privacy_erase_fields)) . '</div>' : '') . '</td>
                            <td class="text-end">' . pg_format_number((int) $privacy_erase_done_table['rows'], 0) . '</td>
                        </tr>';
    }

    foreach (array(
        'delete'    => $privacy_erase_done ? lang('Deleted') : lang('Will be deleted'),
        'anonymise' => $privacy_erase_done ? lang('Kept with personal fields replaced') : lang('Will be kept with personal fields replaced'),
    ) as $privacy_erase_action => $privacy_erase_title) {
        if ($privacy_erase_rows[$privacy_erase_action] === '') {
            continue;
        }

        $privacy_erase_body .= '
            <div class="card mb-5">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h($privacy_erase_title) . '</span>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>' . h(lang('Section')) . '</th>
                                <th class="text-end">' . h(lang('Records')) . '</th>
                            </tr>
                        </thead>
                        <tbody>' . $privacy_erase_rows[$privacy_erase_action] . '
                        </tbody>
                    </table>
                </div>
            </div>';
    }

    // Who was found, and the erasure itself once the dry run has been read.
    $privacy_erase_found = $privacy_erase_result['found'];
    $privacy_erase_people = '';

    foreach ($privacy_erase_found['users'] as $privacy_erase_user) {
        $privacy_erase_people .= '
                        <li class="mb-1"><i class="bi bi-person me-1" aria-hidden="true"></i>' . h($privacy_erase_user['user_username']) . ' <span class="text-body-secondary small">' . h(pg_user_role_name($privacy_erase_user['user_role'])) . '</span></li>';
    }

    foreach ($privacy_erase_found['contacts'] as $privacy_erase_contact) {
        $privacy_erase_name = trim($privacy_erase_contact['first_name'] . ' ' . $privacy_erase_contact['last_name']);

        $privacy_erase_people .= '
                        <li class="mb-1"><i class="bi bi-person-vcard me-1" aria-hidden="true"></i>' . h(($privacy_erase_name !== '') ? $privacy_erase_name : '#' . (int) $privacy_erase_contact['id']) . ' <span class="text-body-secondary small">' . h(lang('Contact')) . '</span></li>';
    }

    $privacy_erase_emails = '';

    foreach ($privacy_erase_found['person']['emails'] as $privacy_erase_email) {
        $privacy_erase_emails .= '<div class="text-break">' . h($privacy_erase_email) . '</div>';
    }

    $privacy_erase_hidden = '';

    foreach ($privacy_erase_input as $privacy_erase_key => $privacy_erase_value) {
        $privacy_erase_hidden .= '<input type="hidden" name="' . h($privacy_erase_key) . '" value="' . h((string) $privacy_erase_value) . '">';
    }

    $privacy_erase_form = '
                        <form action="privacy_erase.php" method="post" class="disable_shortcut">
                            ' . get_token_field() . $privacy_erase_hidden . '
                            <input type="hidden" name="erase" value="1">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="confirm" name="confirm" value="1" required>
                                <label class="form-check-label" for="confirm">' . h(lang('I understand that the erasure cannot be undone.')) . '</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3" data-confirm-content="' . h(lang('WARNING: This person\'s data will be erased for good.')) . '"><i class="bi bi-person-x me-1" aria-hidden="true"></i>' . h(lang('Erase')) . '</button>
                        </form>
                        <p class="small text-body-secondary mt-3 mb-0">' . h(lang('Export the person\'s data first if they asked for a copy. Orders and invoices are kept for the legal retention period without the person\'s name, address and contact details. The erasure is recorded in the audit trail without them.')) . '</p>';

    $privacy_erase_side = $privacy_erase_done ? '' : '
            <div class="position-sticky" style="top:1rem">
                <div class="card mb-5">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Who this is about')) . '</span>
                    </div>
                    <div class="card-body">
                        <div class="small text-body-secondary">' . h(lang('E-mail Address')) . '</div>
                        <div class="mb-3">' . (($privacy_erase_emails !== '') ? $privacy_erase_emails : '<span class="text-body-secondary">' . h(lang('None')) . '</span>') . '</div>
                        ' . (($privacy_erase_people !== '') ? '<ul class="list-unstyled mb-3">' . $privacy_erase_people . '</ul>' : '') . '
                        ' . $privacy_erase_form . '
                    </div>
                </div>
            </div>';
}

$privacy_erase_search = '
            <div class="card mb-5">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="text-uppercase h5 text-primary fw-bold mb-0">' . h(lang('Find a person')) . '</span>
                </div>
                <div class="card-body">
                    <form action="privacy_erase.php" method="post" class="disable_shortcut row g-2 align-items-end">
                        ' . get_token_field() . '
                        <div class="col-12 col-md-8 col-xl-6">
                            <label for="email" class="form-label">' . h(lang('E-mail Address')) . '</label>
                            ' . $liveform->output_field(array('type' => 'email', 'id' => 'email', 'name' => 'email', 'class' => 'form-control', 'maxlength' => '254', 'autocomplete' => 'off', 'required' => 'true')) . '
                        </div>
                        <div class="col-12 col-md-auto">
                            <button type="submit" class="btn btn-primary rounded-pill px-3"><i class="bi bi-search me-1" aria-hidden="true"></i>' . h(lang('Find')) . '</button>
                        </div>
                        <div class="col-12 small text-body-secondary">' . h(lang('Nothing is changed until the erasure is confirmed: first the screen lists what will be deleted and what will be kept with the personal fields replaced.')) . '</div>
                    </form>
                </div>
            </div>';

echo
pg_page_shell(array(
        'title'               => lang('Personal data erasure'),
        'extra classes'       => 'contact',
        'icon'                => 'contact',
        'heading'             => lang('Personal data erasure'),
        'heading_description' => lang('Erase one person for a request under KVKK or GDPR: their account and contact go, orders and invoices stay without their personal fields.'),
        'cancel'              => true,
    )) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
        </div>
        <div class="col-12 col-lg-8 col-xxl-9 order-1">
            ' . $privacy_erase_search . '
            ' . $privacy_erase_body . '
        </div>
        <div class="col-12 col-lg-4 col-xxl-3 order-2">
            ' . $privacy_erase_side . '
        </div>
    </div>
</main>
' .
output_footer();

$liveform->remove_form();
