<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'designer');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM style WHERE style_id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');

// send_to goes into the form and back out through urlencode(): one value.
pg_request_single_values(array('send_to'));

$liveform = new liveform('view_system_style_source', ($_REQUEST['id'] ?? ''));
$liveform->assign_field_value('id', ($_REQUEST['id'] ?? ''));
$liveform->assign_field_value('send_to', ($_REQUEST['send_to'] ?? ''));

// get style information
$style = db_item(
    "SELECT
        style.style_name AS name,
        style.style_head AS head,
        style.style_code AS code,
        style.style_timestamp AS last_modified_timestamp,
        user.user_username AS last_modified_username
    FROM style
    LEFT JOIN user ON style.style_user = user.user_id
    WHERE style.style_id = '" . escape($liveform->get_field_value('id')) . "'");

$name = $style['name'];
$head = $style['head'];
$code = $style['code'];
$last_modified_timestamp = $style['last_modified_timestamp'];
$last_modified_username = $style['last_modified_username'];

// The screen is only reached by its URL; without a send_to it returns to
// the style's own edit screen, not to the site's home page or to the style
// list (which leaves visual designs out).
$edit_style_path = 'edit_system_style.php?id=' . $id;

// if the form was not just submitted, then output the form
if (!$_POST) {

    // if there is no head content, add a placeholder comment
    if ($head == '') {
        $code = str_replace('</head>', '    <!-- Additional Head Content Will Go Here -->' . "\n" . '            </head>', $code);
    } else {
        $code = str_replace('</head>', '    <!-- Additional Head Content Starts Here -->' . "\n" . $head . "\n" . '                <!-- Additional Head Content Ends Here -->' . "\n" . '            </head>', $code);
    }

    $liveform->assign_field_value('head', $head);
    $liveform->assign_field_value('code', $code);

    $output_last_modified = get_relative_time(array('timestamp' => $last_modified_timestamp));

    if ($last_modified_username != '') {
        $output_last_modified .= ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($last_modified_username))));
    }

    $back_url = pg_safe_back_url($liveform->get_field_value('send_to'), $edit_style_path);

    // The read-only source sits outside the form (its buttons reach it with
    // form=), so the whole page code is never posted nor kept in the form's
    // session with the head content.
    print
        pg_page_shell([
            'title'=> h($name) . ' | ' . lang('View Source'),
            'extra classes'=>'design',
            'icon'=>'design',
            'heading'=>lang('View Source'),
            'heading_description' => lang('View the HTML Source for this System Page Style and optionally insert additional head content.'),
            'cancel' => array('enable' => 'true', 'url' => $back_url),
        ]) . '
<main id="content" class="container-fluid">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->get_warnings() . '
                    ' . $liveform->output_notices() . '
                    <div class="row">
                        <div class="col-12 lg:col-8 2xl:col-9 order-1">
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    <label for="head" class="mb-0">' . h(lang('Additional Head Content')) . '</label>
                                </h2>
                                <div class="card-body pt-0">
                                    <form action="view_system_style_source.php" method="post" id="system_style_source_form">
                                        ' . get_token_field() . '
                                        ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'id')) . '
                                        ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'send_to')) . '
                                        ' . $liveform->output_field(array('type' => 'textarea', 'name' => 'head', 'id' => 'head')) . '
                                        ' . pg_code_editor_attach(array('id' => 'head', 'code_type' => 'mixed', 'height' => '150px', 'readonly' => false)) . '
                                    </form>
                                </div>
                            </div>
                            <div class="card my-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    <label for="code" class="mb-0">' . h(lang('HTML Source (read-only)')) . '</label>
                                </h2>
                                <div class="card-body pt-0">
                                    ' . $liveform->output_field(array('type' => 'textarea', 'name' => 'code', 'id' => 'code')) . '
                                    ' . pg_code_editor_attach(array('id' => 'code', 'code_type' => 'mixed', 'height' => '400px', 'readonly' => true)) . '
                                </div>
                            </div>
                        </div>
                        <div class="col-12 lg:col-4 2xl:col-3 order-2">
                            <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                            <div class="position-sticky" style="top:3.3rem;">
                                <div class="card my-7">
                                    <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                        ' . h(lang('Summary')) . '
                                    </h2>
                                    <div class="card-body pt-0">
                                        <dl class="mb-3">
                                            <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
                                                <dt class="fw-normal">' . h(lang('Name')) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . h($name) . '</dd>
                                            </div>
                                            <div class="d-flex justify-content-between gap-3 py-2">
                                                <dt class="fw-normal">' . h(lang('Last Modified')) . '</dt>
                                                <dd class="mb-0 fw-bold text-end text-break">' . $output_last_modified . '</dd>
                                            </div>
                                        </dl>
                                        <p class="small fg-secondary mb-0">' . h(lang('The additional head content goes just before the closing head tag on every page that uses this style; the source shows where.')) . '</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                            <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                                <div class="container">
                                    <div class="btn-group flex-wrap justify-content-center">
                                        <button type="submit" name="submit_save_and_return" value="Save &amp; Return" form="system_style_source_form" class="btn my-1 btn-solid theme-primary"><i class="bi bi-save me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Save & Return')) . '</span></button>
                                        <button type="submit" name="submit_save" value="Save" form="system_style_source_form" class="btn my-1 btn-outline theme-secondary"><i class="bi bi-check2 me-3" aria-hidden="true"></i><span class="pg-btn-label">' . h(lang('Save')) . '</span></button>
                                    </div>
                                </div>
                            </nav>
                        </div>
                    </div>
</main>' .
        output_footer();

    $liveform->remove_form();

// else the form has been submitted, so process it
} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    $query =
        "UPDATE style
        SET
            style_head = ?,
            style_user = ?,
            style_timestamp = UNIX_TIMESTAMP()
        WHERE style_id = ?";
    $result = db_q($query, array(
        (string) $liveform->get_field_value('head'),
        (string) $user['id'],
        (string) (int) $liveform->get_field_value('id'),
    )) or output_error('Query failed.');

    log_activity(lang(array('string' => 'additional head content for style ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);

    $notice = lang('The additional head content has been saved.');

    // if the user clicked Save, stay on this page
    if ($liveform->get_field_value('submit_save') == 'Save') {
        $liveform->add_notice($notice);

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_system_style_source.php?id=' . (int) $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));

    // else return to edit system style screen
    } else {
        $liveform_edit_system_style = new liveform('edit_system_style', $liveform->get_field_value('id'));
        $liveform_edit_system_style->add_notice($notice);

        $send_to = (string) $liveform->get_field_value('send_to');

        header('Location: ' . URL_SCHEME . HOSTNAME . (($send_to !== '') ? pg_safe_redirect_path($send_to) : PATH . SOFTWARE_DIRECTORY . '/' . $edit_style_path));

        $liveform->remove_form();
    }
}
