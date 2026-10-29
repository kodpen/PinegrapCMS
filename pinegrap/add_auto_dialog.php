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

require_once(PG_FUNCTIONS_DIR . '/includes/forms/auto_dialog_form.php');

$liveform = new liveform('add_auto_dialog');

// If the form has not been submitted, then output it.
if (!$_POST) {
    $screen = 'create';


    echo pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Auto Dialog'))),
            'extra classes'=>'page',
            'icon'=>'page',
            'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Auto Dialog'))),
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
    
    pg_auto_dialog_form_validate($liveform, null);

    if ($liveform->check_form_errors()) {
        go($_SERVER['PHP_SELF']);
    }

    $query =
        "INSERT INTO auto_dialogs (
            name,
            enabled,
            url,
            width,
            height,
            delay,
            frequency,
            page,
            created_user_id,
            created_timestamp,
            last_modified_user_id,
            last_modified_timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, UNIX_TIMESTAMP())";
    db_q($query, array_merge(pg_auto_dialog_form_values($liveform), array((string) USER_ID, (string) USER_ID))) or output_error('Query failed.');

    $auto_dialog_id = mysqli_insert_id(db::$con);
    
    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('auto dialog'),$liveform->get_field_value('name')))), $_SESSION['sessionusername']);

    $home_page_name = db_value("SELECT page_name FROM page WHERE page_home = 'yes' ORDER BY page_timestamp DESC LIMIT 1");

    $preview_url = PATH . encode_url_path($home_page_name) . '?preview_auto_dialog=' . $auto_dialog_id;
    $liveform->remove_form();
    $liveform_view_auto_dialogs = new liveform('view_auto_dialogs');
    $liveform_view_auto_dialogs->add_notice(lang(array('string'=>'The auto dialog has been created, but it has not been enabled yet. Please <a href="{var:1}" target="_blank">preview</a> it, and then you may <a href="{var:2}">edit</a> it to enable it for all visitors.','vars'=>array(h($preview_url),'edit_auto_dialog.php?id=' . $auto_dialog_id))) );
    go(PATH . SOFTWARE_DIRECTORY . '/view_auto_dialogs.php');
}
?>