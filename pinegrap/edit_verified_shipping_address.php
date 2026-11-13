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
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM verified_shipping_addresses WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/verified_shipping_address_form.php');
$liveform = new liveform('edit_verified_shipping_address');

$verified_shipping_address = db_item(
    "SELECT
        verified_shipping_addresses.company,
        verified_shipping_addresses.address_1,
        verified_shipping_addresses.address_2,
        verified_shipping_addresses.city,
        verified_shipping_addresses.state_id,
        verified_shipping_addresses.zip_code,
        verified_shipping_addresses.created_timestamp,
        verified_shipping_addresses.last_modified_timestamp,
        states.name as state_name,
        created_user.user_username AS created_username,
        last_modified_user.user_username AS last_modified_username
    FROM verified_shipping_addresses
    LEFT JOIN states ON verified_shipping_addresses.state_id = states.id
    LEFT JOIN user AS created_user ON created_user.user_id = verified_shipping_addresses.created_user_id
    LEFT JOIN user AS last_modified_user ON last_modified_user.user_id = verified_shipping_addresses.last_modified_user_id
    WHERE verified_shipping_addresses.id = '" . $id . "'");

$verified_shipping_address['name'] = pg_verified_shipping_address_form_name($verified_shipping_address['company'], $verified_shipping_address['address_1']);

// if the form has not been submitted, then output form
if (!$_POST) {

    if ($liveform->field_in_session('id') == false) {
        $liveform->assign_field_value('company', $verified_shipping_address['company']);
        $liveform->assign_field_value('address_1', $verified_shipping_address['address_1']);
        $liveform->assign_field_value('address_2', $verified_shipping_address['address_2']);
        $liveform->assign_field_value('city', $verified_shipping_address['city']);
        $liveform->assign_field_value('state_id', $verified_shipping_address['state_id']);
        $liveform->assign_field_value('zip_code', $verified_shipping_address['zip_code']);
    }

    // When and by whom the address was created and last changed.
    $status_rows = pg_verified_shipping_address_form_status_row(lang('Created'), get_relative_time(array('timestamp' => (int) $verified_shipping_address['created_timestamp'])) . ((string) $verified_shipping_address['created_username'] !== '' ? ' ' . h($verified_shipping_address['created_username']) : ''), false)
        . pg_verified_shipping_address_form_status_row(lang('Last Modified'), get_relative_time(array('timestamp' => (int) $verified_shipping_address['last_modified_timestamp'])) . ((string) $verified_shipping_address['last_modified_username'] !== '' ? ' ' . h($verified_shipping_address['last_modified_username']) : ''));

    print
        pg_page_shell([
            'title'=> lang('Edit Verified Shipping Address'),
            'extra classes'=>'products pg-measure-edit',
            'icon'=>'store',
            'heading'=>lang('Edit Verified Shipping Address'),
            'heading_description' => lang('Update this verified shipping address and assign it to any existing state.'),
            'cancel'=>array('enable'=>'true','url'=>'view_verified_shipping_addresses.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Verified Shipping Addresses'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php'), array('label' => lang('Edit Verified Shipping Address'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    
                    <form action="edit_verified_shipping_address.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $id . '">
                        ' . pg_verified_shipping_address_form_layout(
                            pg_verified_shipping_address_form_card($liveform, $status_rows),
                            '
                                        <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                        <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('verified shipping address')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>') . '
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();

    $liveform->remove_form();

// else the form has been submitted
} else {
    validate_token_field();

    $liveform->add_fields_to_session();

    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        pg_store_verified_address_delete($id) or output_error('Query failed.');
        log_activity(lang(array('string' => 'verified shipping address ({var:1}) was deleted', 'vars' => array($verified_shipping_address['name']))), $_SESSION['sessionusername']);

        $liveform_view = new liveform('view_verified_shipping_addresses');
        $liveform_view->add_notice(lang('The verified shipping address has been deleted.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php');

    } else {
        pg_verified_shipping_address_form_validate($liveform);

        if ($liveform->check_form_errors() == true) {
            go(PATH . SOFTWARE_DIRECTORY . '/edit_verified_shipping_address.php?id=' . $id);
        }

        $values = pg_verified_shipping_address_form_values($liveform);
        pg_store_verified_address_save($id, $values, $user['id']) or output_error('Query failed.');

        $name = pg_verified_shipping_address_form_name($values['company'], $values['address_1']);

        log_activity(lang(array('string' => 'verified shipping address ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);

        $liveform_view = new liveform('view_verified_shipping_addresses');
        $liveform_view->add_notice(lang('The verified shipping address has been saved.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php');
    }
}
