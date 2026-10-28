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
        states.name as state_name
    FROM verified_shipping_addresses
    LEFT JOIN states ON verified_shipping_addresses.state_id = states.id
    WHERE verified_shipping_addresses.id = '" . escape($_REQUEST['id']) . "'");

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

    print
        pg_page_shell([
            'title'=> lang('Edit Verified Shipping Address'),
            'extra classes'=>'products',
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
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                        ' . pg_verified_shipping_address_form_card($liveform) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('verified shipping address')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>
                                </div>
                            </div>
                        </nav>
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
        db("DELETE FROM verified_shipping_addresses WHERE id = '" . escape($liveform->get_field_value('id')) . "'");

        log_activity(lang(array('string' => 'verified shipping address ({var:1}) was deleted', 'vars' => array($verified_shipping_address['name']))), $_SESSION['sessionusername']);

        $liveform_view = new liveform('view_verified_shipping_addresses');
        $liveform_view->add_notice(lang('The verified shipping address has been deleted.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php');

    } else {
        pg_verified_shipping_address_form_validate($liveform);

        if ($liveform->check_form_errors() == true) {
            go(PATH . SOFTWARE_DIRECTORY . '/edit_verified_shipping_address.php?id=' . $liveform->get_field_value('id'));
        }

        $values = pg_verified_shipping_address_form_values($liveform);
        $set = array();
        foreach ($values as $column => $value) {
            $set[] = $column . " = '" . escape($value) . "'";
        }

        db(
            "UPDATE verified_shipping_addresses
            SET
                " . implode(",\n                ", $set) . ",
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($liveform->get_field_value('id')) . "'");

        $name = pg_verified_shipping_address_form_name($values['company'], $values['address_1']);

        log_activity(lang(array('string' => 'verified shipping address ({var:1}) was modified', 'vars' => array($name))), $_SESSION['sessionusername']);

        $liveform_view = new liveform('view_verified_shipping_addresses');
        $liveform_view->add_notice(lang('The verified shipping address has been saved.'));

        $liveform->remove_form();

        go(PATH . SOFTWARE_DIRECTORY . '/view_verified_shipping_addresses.php');
    }
}
