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
validate_ecommerce_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM key_codes WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

require_once(PG_FUNCTIONS_DIR . '/includes/forms/key_code_form.php');

$liveform = new liveform('edit_key_code');

$key_code = db_item(
    "SELECT
        code,
        offer_code,
        enabled,
        expiration_date,
        notes,
        single_use,
        report
    FROM key_codes
    WHERE id = '" . e($_REQUEST['id']) . "'");

if (!$_POST) {
    
    // If the form has not been submitted yet, then pre-populate fields with data.
    if (!$liveform->field_in_session('id')) {
        $liveform->assign_field_value('code', $key_code['code']);
        $liveform->assign_field_value('offer_code', $key_code['offer_code']);
        $liveform->assign_field_value('enabled', $key_code['enabled']);
        
        if ($key_code['expiration_date'] != '0000-00-00') {
            $liveform->assign_field_value('expiration_date', prepare_form_data_for_output($key_code['expiration_date'], 'date'));
        }

        $liveform->set('notes', $key_code['notes']);
        $liveform->set('single_use', $key_code['single_use']);
        $liveform->set('report', $key_code['report']);
    }

    $screen = 'edit';

    echo pg_page_shell([
        'title'=> lang('Edit Key Code'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Key Code'),
        'cancel'=>array('enable'=>'true','url'=>'view_key_codes.php'),
    ]);

    require('includes/templates/edit_key_code.php');

    echo output_footer();
    
    $liveform->remove_form();

} else {

    validate_token_field();

    $liveform->add_fields_to_session();

    // If the user selected to delete this key code, then delete it.
    if ($liveform->field_in_session('delete')) {

        db("DELETE FROM key_codes WHERE id = '" . e($liveform->get_field_value('id')) . "'");
        
        log_activity(lang(array('string'=>'key code ({var:1}) was deleted','vars'=>$key_code['code'])), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_key_codes = new liveform('view_key_codes');
        $liveform_view_key_codes->add_notice(lang('The key code has been deleted.'));

    // Otherwise the user selected to save the key code, so save it.
    } else {

        $liveform->validate_required_field('code', lang('Key Code is required.'));
        pg_key_code_form_validate($liveform, $id);

        // If there is an error, forward user back to previous screen.
        if ($liveform->check_form_errors()) {
            go($_SERVER['PHP_SELF'] . '?id=' . $id);
        }

        db_q(
            "UPDATE key_codes
            SET
                code = ?,
                offer_code = ?,
                enabled = ?,
                expiration_date = ?,
                notes = ?,
                single_use = ?,
                report = ?,
                user = ?,
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?",
            array_merge(pg_key_code_form_values($liveform, $liveform->get_field_value('code')), array((string) USER_ID, (string) $id))) or output_error('Query failed.');

        log_activity(lang(array('string'=>'key code ({var:1}) was modified','vars'=>$liveform->get_field_value('code'))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_key_codes = new liveform('view_key_codes');
        $liveform_view_key_codes->add_notice(lang('The key code has been saved.'));
    }

    go(PATH . SOFTWARE_DIRECTORY . '/view_key_codes.php');
}