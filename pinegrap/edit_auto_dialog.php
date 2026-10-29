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
validate_area_access($user, 'manager');

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM auto_dialogs WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php');

$liveform = new liveform('edit_auto_dialog');

$auto_dialog = db_item(
    "SELECT
        id,
        name,
        enabled,
        url,
        width,
        height,
        delay,
        frequency,
        page
    FROM auto_dialogs
    WHERE id = '" . e($_REQUEST['id']) . "'");

// If the form has not been submitted, then output it.
if (!$_POST) {

    // If the form has not been submitted yet, then pre-populate fields with data.
    if (!$liveform->field_in_session('id')) {
        $liveform->assign_field_value('name', $auto_dialog['name']);
        $liveform->assign_field_value('enabled', $auto_dialog['enabled']);
        $liveform->assign_field_value('url', $auto_dialog['url']);

        // If there is a width then prefill it.
        // If the value is just zero, then we just want the field to be blank.
        if ($auto_dialog['width']) {
            $liveform->assign_field_value('width', $auto_dialog['width']);
        }

        // If there is a height then prefill it.
        // If the value is just zero, then we just want the field to be blank.
        if ($auto_dialog['height']) {
            $liveform->assign_field_value('height', $auto_dialog['height']);
        }

        // If there is a delay then prefill it.
        // If the value is just zero, then we just want the field to be blank.
        if ($auto_dialog['delay']) {
            $liveform->assign_field_value('delay', $auto_dialog['delay']);
        }

        // If there is a frequency then prefill it.
        // If the value is just zero, then we just want the field to be blank.
        if ($auto_dialog['frequency']) {
            $liveform->assign_field_value('frequency', $auto_dialog['frequency']);
        }

        $liveform->assign_field_value('page', $auto_dialog['page']);
    }

    $screen = 'edit';

    $home_page_name = db_value("SELECT page_name FROM page WHERE page_home = 'yes' ORDER BY page_timestamp DESC LIMIT 1");

    $preview_url = PATH . encode_url_path($home_page_name) . '?preview_auto_dialog=' . $auto_dialog['id'];

    echo pg_page_shell(
        array(
            'title'=> lang('Edit Auto Dialog'),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang('Edit Auto Dialog'),
            'cancel'=>array('enable'=>'true','url'=>'view_auto_dialogs.php'),
        )
    );

    require('includes/templates/edit_auto_dialog.php');

    echo output_footer();
    
    $liveform->remove_form();

// Otherwise the form has been submitted so process it.
} else {

    validate_token_field();
    
    $liveform->add_fields_to_session();

    // If the user selected to delete this auto dialog, then delete it.
    if ($liveform->field_in_session('delete')) {
        db("DELETE FROM auto_dialogs WHERE id = '" . e($liveform->get_field_value('id')) . "'");
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array( lang('auto dialog'),$auto_dialog['name'] ) )), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_auto_dialogs = new liveform('view_auto_dialogs');
        $liveform_view_auto_dialogs->add_notice( lang(array('string'=>'The {var:1} has been deleted.','vars'=>lang('auto dialog') )) );

    // Otherwise the user selected to save the auto dialog, so save it.
    } else {
        pg_auto_dialog_form_validate($liveform, (string) $id);

        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors()) {
            go($_SERVER['PHP_SELF'] . '?id=' . $id);
        }

        $query =
            "UPDATE auto_dialogs
            SET
                name = ?,
                enabled = ?,
                url = ?,
                width = ?,
                height = ?,
                delay = ?,
                frequency = ?,
                page = ?,
                last_modified_user_id = ?,
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        db_q($query, array_merge(pg_auto_dialog_form_values($liveform), array((string) USER_ID, (string) $id))) or output_error('Query failed.');

        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('auto dialog'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_auto_dialogs = new liveform('view_auto_dialogs');
        $liveform_view_auto_dialogs->add_notice( lang(array('string'=>'The {var:1} has been saved.','vars'=>lang('auto dialog') )) );
    }

    

    go(PATH . SOFTWARE_DIRECTORY . '/view_auto_dialogs.php');
    
}
?>