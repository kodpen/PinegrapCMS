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
// link, hand-typed URL, row deleted meanwhile, an id sent as an array): a
// "not found" screen instead of PHP notices from the unchecked row below or
// an error page.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM recurring_commission_profiles WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');
$liveform = new liveform('edit_recurring_commission_profile');

$liveform->add_fields_to_session();

// get profile info
$query =
    "SELECT
        recurring_commission_profiles.id,
        recurring_commission_profiles.affiliate_code,
        recurring_commission_profiles.enabled,
        recurring_commission_profiles.amount,
        recurring_commission_profiles.order_id,
        recurring_commission_profiles.product_name,
        recurring_commission_profiles.product_short_description,
        recurring_commission_profiles.start_date,
        recurring_commission_profiles.period,
        recurring_commission_profiles.number_of_commissions,
        contacts.affiliate_name,
        orders.order_number,
        orders.status AS order_status
    FROM recurring_commission_profiles
    LEFT JOIN contacts ON recurring_commission_profiles.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON recurring_commission_profiles.order_id = orders.id
    WHERE recurring_commission_profiles.id = ?";
$result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
$recurring_commission_profile = mysqli_fetch_assoc($result);

// if the form has not just been submitted, then output form
if (!$_POST) {
    // if the form has not been submitted yet, pre-populate fields with data
    if ($liveform->field_in_session('enabled') == FALSE) {
        $liveform->assign_field_value('enabled', $recurring_commission_profile['enabled']);
    }
    
    print
    pg_page_shell([
        'title'=> lang('Edit Recurring Commission Profile'),
        'extra classes'=>'products pg-measure-edit',
        'icon'=>'store',
        'heading'=>lang('Edit Recurring Commission Profile'),
        'heading_description' => lang('Enable or disable this recurring commission profile.'),
        'cancel'=>array('enable'=>'true','url'=>'view_recurring_commission_profiles.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Commission Profiles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_recurring_commission_profiles.php'), array('label' => lang('Edit Recurring Commission Profile'))),
        ]) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <form action="edit_recurring_commission_profile.php" method="post">
        ' . get_token_field() . '
        ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$id)) . '
        ' . pg_commission_edit_layout('
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Status') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="form-field my-3">
                                        <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'enabled', 'name'=>'enabled', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                        <label for="enabled">' . lang('Enable') . '</label>
                                    </div>
                                </div>
                            </div>', $recurring_commission_profile, '
                                        <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>') . '
    </form>
</main>' .
    output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();
    
    // A hand edit is the operator's choice: it clears the mark the order's
    // cancellation left, so restoring the order does not undo it.
    $cancel_marker_sql = pg_schema_has('recurring_commission_profiles', 'disabled_by_cancel') ? "disabled_by_cancel = 0," : '';

    // update profile
    $query =
        "UPDATE recurring_commission_profiles
        SET
            enabled = ?,
            " . $cancel_marker_sql . "
            last_modified_user_id = ?,
            last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE id = ?";
    $result = db_q($query, array((string) $liveform->get_field_value('enabled'), (string) $user['id'], (string) $liveform->get_field_value('id'))) or output_error('Query failed.');
    
    log_activity(lang(array('string'=>'recurring commission profile ({var:1}) was modified','vars'=>$recurring_commission_profile['id'])) , $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_recurring_commission_profiles = new liveform('view_recurring_commission_profiles');
    $liveform_view_recurring_commission_profiles->add_notice(lang('The recurring commission profile has been saved.'));
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_recurring_commission_profiles.php');
    
    
    exit();
}
?>