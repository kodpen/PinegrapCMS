<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * One redirect rule (includes/fn/redirects.php): a new one when the screen is
 * opened without an id (?from= fills the old address), the stored one
 * otherwise. Saving goes through pg_redirect_save(), which refuses an address
 * or a target it cannot use, a second rule for an address, and a rule that
 * sends an address to itself or round a loop; the reason is shown on the
 * field it belongs to. A rule whose address a page, a file or a short link
 * answers is saved with a warning: it waits until the address is free.
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
$liveform = new liveform('edit_redirect');

$list_url = PATH . SOFTWARE_DIRECTORY . '/view_redirects.php';

$redirect_id = (isset($_REQUEST['id']) && is_scalar($_REQUEST['id'])) ? (int) $_REQUEST['id'] : 0;
$redirect = null;

if ($redirect_id > 0) {
    $redirect = pg_redirect_get($redirect_id);

    if ($redirect === null) {
        output_error(lang('Record not found.') . ' <a href="' . h($list_url) . '">' . lang('Go back') . '</a>.', 404);
    }
}

$screen_url = PATH . SOFTWARE_DIRECTORY . '/edit_redirect.php' . ($redirect ? '?id=' . (int) $redirect['id'] : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validate_token_field();

    $liveform->add_fields_to_session();

    $list_form = new liveform('view_redirects');

    if (isset($_POST['submit_delete']) && $redirect) {

        pg_redirect_delete(array((int) $redirect['id']));

        log_activity(lang(array('string' => 'redirect from {var:1} deleted', 'vars' => array($redirect['from_path']))), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $list_form->add_notice(h(lang(array('string' => 'The redirect from {var:1} was deleted.', 'vars' => array($redirect['from_path'])))));

        go($list_url);
    }

    $saved = pg_redirect_save(array(
        'id' => $redirect ? (int) $redirect['id'] : 0,
        'from_path' => $liveform->get_field_value('from_path'),
        'to_url' => $liveform->get_field_value('to_url'),
        'code' => $liveform->get_field_value('code'),
    ), (int) $user['id']);

    if (!$saved['ok']) {

        if ($saved['problem']['field'] !== '') {
            $liveform->mark_error($saved['problem']['field'], h($saved['problem']['message']));
        } else {
            $liveform->add_error(h($saved['problem']['message']));
        }

        go($screen_url);
    }

    $stored = pg_redirect_get($saved['id']);
    $stored_vars = array($stored['from_path'], $stored['to_url']);

    if ($saved['created']) {
        log_activity(lang(array('string' => 'redirect from {var:1} to {var:2} created', 'vars' => $stored_vars)), $_SESSION['sessionusername']);
        $saved_notice = lang(array('string' => 'The redirect from {var:1} was created.', 'vars' => $stored_vars));
    } else {
        log_activity(lang(array('string' => 'redirect from {var:1} to {var:2} modified', 'vars' => $stored_vars)), $_SESSION['sessionusername']);
        $saved_notice = lang(array('string' => 'The redirect from {var:1} was saved.', 'vars' => $stored_vars));
    }

    $liveform->remove_form();

    $list_form->add_notice(h($saved_notice));

    switch (pg_redirect_address_taken($stored['from_path'])) {
        case 'page':
            $list_form->add_warning(h(lang(array('string' => 'A page answers at {var:1}, so this redirect is not used until the page is renamed or deleted.', 'vars' => array($stored['from_path'])))));
            break;

        case 'file':
            $list_form->add_warning(h(lang(array('string' => 'A file answers at {var:1}, so this redirect is not used until the file is renamed or deleted.', 'vars' => array($stored['from_path'])))));
            break;

        case 'short_link':
            $list_form->add_warning(h(lang(array('string' => 'A short link answers at {var:1}, so this redirect is not used until the short link is renamed or deleted.', 'vars' => array($stored['from_path'])))));
            break;
    }

    go($list_url);
}

// The form opens with the stored rule, or for a new one with ?from= (an
// address from the list of missing addresses) and a permanent move.
if (!$liveform->field_in_session('code')) {
    if ($redirect) {
        $liveform->assign_field_value('from_path', $redirect['from_path']);
        $liveform->assign_field_value('to_url', $redirect['to_url']);
        $liveform->assign_field_value('code', (string) (int) $redirect['code']);
    } else {
        if (isset($_GET['from']) && is_string($_GET['from'])) {
            $liveform->assign_field_value('from_path', pg_redirect_normalize_path($_GET['from'], PATH));
        }

        $liveform->assign_field_value('code', '301');
    }
}

$code_options = array();

foreach (pg_redirect_codes() as $code) {
    $code_options[pg_redirect_code_label($code)] = (string) $code;
}

$screen_title = $redirect ? lang('Edit Redirect') : lang('Create Redirect');

// What the rule has done so far, beside the form.
$output_status = '';

if ($redirect) {

    $created_by = '';

    if ((int) $redirect['created_by'] > 0) {
        $created_by = (string) db_value("SELECT user_username FROM user WHERE user_id = '" . (int) $redirect['created_by'] . "'");
    }

    $output_open = '';

    if (empty($redirect['is_pattern'])) {
        $output_open =
            '<a class="btn btn-sm btn-outline-secondary mt-3" href="' . h(PATH . ltrim(encode_url_path($redirect['from_path']), '/')) . '" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>' . h(lang('Open the old address')) . '</a>';
    }

    $output_status =
        '<div class="col-12 col-lg-4 col-xxl-3 order-2">
            <div class="card my-4 position-sticky" style="top:56px;">
                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('Usage')) . '</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-6 fw-normal text-body-secondary">' . h(lang('Times Used')) . '</dt>
                        <dd class="col-6 text-end">' . h(pg_format_number((int) $redirect['hits'], 0)) . '</dd>
                        <dt class="col-6 fw-normal text-body-secondary">' . h(lang('Last Used')) . '</dt>
                        <dd class="col-6 text-end">' . (((int) $redirect['last_hit'] > 0) ? get_relative_time(array('timestamp' => (int) $redirect['last_hit'])) : h(lang('Never'))) . '</dd>
                        <dt class="col-6 fw-normal text-body-secondary">' . h(lang('Created')) . '</dt>
                        <dd class="col-6 text-end mb-0">' . get_relative_time(array('timestamp' => (int) $redirect['created_at'])) . (($created_by !== '') ? ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($created_by)))) : '') . '</dd>
                    </dl>
                    ' . $output_open . '
                </div>
            </div>
        </div>';
}

$output_delete = '';

if ($redirect) {
    $output_delete =
        '<button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-outline-warning" formnovalidate data-confirm-content="' . h(lang('This redirect will be permanently deleted. Visitors of the old address will see the page-not-found screen again.')) . '"><i class="bi bi-trash me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang('Delete')) . '</span></button>';
}

print
pg_page_shell(
    array(
        'title' => $screen_title,
        'extra classes' => 'page',
        'icon' => 'page',
        'heading' => $screen_title,
        'heading_description' => lang('Send an address that no longer exists on to where its content is now.'),
        'cancel' => array('enable' => 'true', 'url' => 'view_redirects.php'),
        'breadcrumb' => array(
            array('label' => lang('Redirects'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_redirects.php'),
            array('label' => $screen_title),
        ),
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <form name="form" action="edit_redirect.php' . ($redirect ? '?id=' . (int) $redirect['id'] : '') . '" method="post">
                ' . get_token_field() . '
                ' . ($redirect ? '<input type="hidden" name="id" value="' . (int) $redirect['id'] . '">' : '') . '
                <div class="row">
                    <div class="' . ($redirect ? 'col-12 col-lg-8 col-xxl-9 order-1' : 'col-12 order-1') . '">
                        <div class="card my-4">
                            <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">' . h(lang('Redirect')) . '</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-xl-6 my-2">
                                        <label for="from_path" class="form-label">' . h(lang('Old Address')) . '</label>
                                        ' . $liveform->output_field(array('type' => 'text', 'name' => 'from_path', 'id' => 'from_path', 'class' => 'form-control', 'maxlength' => (string) PG_REDIRECT_FROM_MAX, 'placeholder' => '/old-page', 'required' => 'required')) . '
                                        <div class="form-text" id="from_path_help">' . h(lang('The address on this site that no longer exists, for example /old-page or a full address of the old site. A * stands for any part of the address (/blog/*).')) . '</div>
                                    </div>
                                    <div class="col-12 col-xl-6 my-2">
                                        <label for="to_url" class="form-label">' . h(lang('New Address')) . '</label>
                                        ' . $liveform->output_field(array('type' => 'text', 'name' => 'to_url', 'id' => 'to_url', 'class' => 'form-control', 'maxlength' => (string) PG_REDIRECT_TO_MAX, 'placeholder' => '/new-page', 'required' => 'required')) . '
                                        <div class="form-text" id="to_url_help">' . h(lang('A path of this site that starts with / or a full address that starts with https://. $1, $2 take what each * of the old address matched (/news/$1).')) . '</div>
                                    </div>
                                    <div class="col-12 col-sm-6 col-xl-4 my-2">
                                        <label for="code" class="form-label">' . h(lang('Type')) . '</label>
                                        ' . $liveform->output_field(array('type' => 'select', 'name' => 'code', 'id' => 'code', 'class' => 'form-select', 'options' => $code_options)) . '
                                    </div>
                                    <div class="col-12 col-sm-6 col-xl-8 my-2 d-flex align-items-end">
                                        <div class="form-text" id="code_help">' . h(lang('Permanent tells search engines that the content has moved for good; they carry the standing of the old address over to the new one. Temporary keeps the old address in their results.')) . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ' . $output_status . '
                    <div class="col-12 order-3">
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="' . h(lang('Data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-success"><i class="bi bi-check-circle me-2" aria-hidden="true"></i><span class="btn-text">' . h(lang('Save')) . '</span></button>
                                    ' . $output_delete . '
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
