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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_area_access($user, 'designer');

include_once('liveform.class.php');
$liveform = new liveform('view_system_style_source', $_REQUEST['id']);
$liveform->assign_field_value('id', $_REQUEST['id']);
$liveform->assign_field_value('send_to', $_REQUEST['send_to']);

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

    $output_last_modified = '';

    if ($last_modified_username != '') {
        $output_last_modified = lang(array('string' => 'by {var:1}', 'vars' => array(h($last_modified_username))));
    }

    print
        pg_page_shell([
            'title'=> lang('View Source'),
            'extra classes'=>'design',
            'icon'=>'design',
            'heading'=>lang('View Source')
        ]) . '
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->get_warnings() . '
                    ' . $liveform->output_notices() . '

                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 text-center text-md-start">
                            <h2 class="d-inline-block" data-bs-content="' . lang('View the HTML Source for this System Page Style and optionally insert additional head content.') . '" title="' . lang('View Source') . '">' . h($name) . '</h2>
                            <p class="text-muted small">' . get_relative_time(array('timestamp' => $last_modified_timestamp)) . ' ' . $output_last_modified . '</p>
                        </div>
                    </div>
                    <div class="card my-4">
                        <div class="card-body">
                            <form action="view_system_style_source.php" method="post">
                                ' . get_token_field() . '
                                ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'id')) . '
                                ' . $liveform->output_field(array('type' => 'hidden', 'name' => 'send_to')) . '

                                <h5 class="card-title mb-3">' . lang('Additional Head Content') . '</h5>
                                <div class="mb-3">
                                    ' . $liveform->output_field(array('type' => 'textarea', 'name' => 'head', 'id' => 'head')) . '
                                </div>
                                ' . get_codemirror_includes() . '
                                ' . get_codemirror_javascript(array('id' => 'head', 'code_type' => 'mixed', 'height' => '150px', 'readonly' => false)) . '
                                <div class="d-flex gap-2 mt-3">
                                    <button type="submit" name="submit_save_and_return" value="Save &amp; Return" class="btn btn-primary">' . lang('Save & Return') . '</button>
                                    <button type="submit" name="submit_save" value="Save" class="btn btn-outline-secondary">' . lang('Save') . '</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="window.location.href=\'' . h(escape_javascript($liveform->get_field_value('send_to'))) . '\'">' . lang('Cancel') . '</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card my-4">
                        <div class="card-body">
                            <h5 class="card-title mb-3">' . lang('HTML Source (read-only)') . '</h5>
                            <div class="mb-3">
                                ' . $liveform->output_field(array('type' => 'textarea', 'name' => 'code', 'id' => 'code')) . '
                            </div>
                            ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed', 'height' => '400px', 'readonly' => true)) . '
                        </div>
                    </div>
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
            style_head = '" . escape($liveform->get_field_value('head')) . "',
            style_user = '" . $user['id'] . "',
            style_timestamp = UNIX_TIMESTAMP()
        WHERE style_id = '" . $liveform->get_field_value('id') . "'";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    log_activity(lang(array('string' => 'additional head content for style ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);

    $notice = lang('The additional head content has been saved.');

    // if the user clicked Save, stay on this page
    if ($liveform->get_field_value('submit_save') == 'Save') {
        $liveform->add_notice($notice);

        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_system_style_source.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));

    // else return to edit system style screen
    } else {
        $liveform_edit_system_style = new liveform('edit_system_style', $liveform->get_field_value('id'));
        $liveform_edit_system_style->add_notice($notice);

        header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));

        $liveform->remove_form();
    }
}
