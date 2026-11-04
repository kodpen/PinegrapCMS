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
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM commissions WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/commission_list.php');
$liveform = new liveform('edit_commission');

$liveform->add_fields_to_session();

// get commission info
$query =
    "SELECT
        commissions.affiliate_code,
        commissions.reference_code,
        commissions.status,
        commissions.amount,
        commissions.order_id,
        contacts.affiliate_name,
        orders.order_number,
        orders.status AS order_status
    FROM commissions
    LEFT JOIN contacts ON commissions.affiliate_code = contacts.affiliate_code
    LEFT JOIN orders ON commissions.order_id = orders.id
    WHERE commissions.id = ?";
$result = db_q($query, array((string) $liveform->get_field_value('id'))) or output_error('Query failed.');
$commission = mysqli_fetch_assoc($result);

// if the form has not just been submitted, then output form
if (!$_POST) {
    // if the form has not been submitted yet, pre-populate fields with data
    if ($liveform->field_in_session('status') == FALSE) {
        $liveform->assign_field_value('status', $commission['status']);
    }
    
    $status_options =
        array(
            lang('Pending') => 'pending',
            lang('Payable') => 'payable',
            lang('Ineligible') => 'ineligible',
            lang('Paid') => 'paid'
        );
    
    print
    pg_page_shell([
        'title'=> lang('Edit Commission'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Commission'),
        'heading_description' => lang('Update the status of this commission.'),
        'cancel'=>array('enable'=>'true','url'=>'view_commissions.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Commissions'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_commissions.php'), array('label' => lang('Edit Commission'))),
        ]) . '
<main id="content" class="container-fluid">
    ' . $liveform->output_errors() . '
    ' . $liveform->get_warnings() . '
    ' . $liveform->output_notices() . '
    <form action="edit_commission.php" method="post">
        ' . get_token_field() . '
        ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$id)) . '
        ' . pg_commission_edit_layout('
                            <div class="card mb-7">
                                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Status') . '
                                </h2>
                                <div class="card-body pt-0">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="status" class="form-label">' . lang('Status') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'status', 'id'=>'status', 'class'=>'form-control', 'options'=>$status_options)) . '
                                        </div>
                                    </div>
                                </div>
                            </div>', $commission, '
                                        <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3" aria-hidden="true">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>') . '
    </form>
</main>' .
    output_footer();
    
    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();

    // Only a stored status is saved (an enum column under a non-strict
    // sql_mode would take anything else as '').
    $status = pg_commission_posted_status($_POST['status'] ?? null);

    if ($status === '') {
        $liveform->mark_error('status', lang('Please select a status.'));
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_commission.php?id=' . $id);
        exit();
    }

    // The write the external API shares (includes/fn/commission_list.php):
    // it clears the mark the order's cancellation left and announces the
    // move.
    if (pg_commission_status_save($id, $status, $user['id']) === false) {
        output_error('Query failed.');
    }

    log_activity(lang(array('string'=>'commission ({var:1}) was modified','vars'=>$commission['reference_code'])) , $_SESSION['sessionusername']);
    
    $liveform->remove_form();
    $liveform_view_commissions = new liveform('view_commissions');
    $liveform_view_commissions->add_notice(lang('The commission has been saved.'));
    
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_commissions.php');
    
    
    exit();
}
?>