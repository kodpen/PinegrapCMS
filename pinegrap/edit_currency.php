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
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM currencies WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

include_once('liveform.class.php');
$liveform = new liveform('edit_currency', $_REQUEST['id']);

if (!$_POST) {
    // if edit currency screen has not been submitted already, pre-populate fields with data
    if (isset($_SESSION['software']['liveforms']['edit_currency'][$_GET['id']]) == false) {
        // get currency information
        $query =
            "SELECT
                name,
                base,
                code,
                symbol,
                exchange_rate
            FROM currencies
            WHERE id = '" . escape($_GET['id']) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        
        // Assign the values to the fields.
        $liveform->assign_field_value('name', $row['name']);
        $liveform->assign_field_value('base', $row['base']);
        $liveform->assign_field_value('code', $row['code']);
        $liveform->assign_field_value('symbol', $row['symbol']);
        $liveform->assign_field_value('exchange_rate', pg_format_input_amount($row['exchange_rate'], 5));
    }

    print
    pg_page_shell(
        array(
            'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Currency'))),
            'extra classes'=>'setting',
            'icon'=>'setting', 
            'heading'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Currency'))),
            'heading_description' => lang('Edit this currency conversion selectable by customers.'),
            'cancel'=>array('enable'=>'true','url'=>'view_currencies.php')
        ,
            'breadcrumb' => array(array('label' => lang('Currencies'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_currencies.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Currency'))))),
        )
    ) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                ' . $liveform->output_errors() . '
                ' . $liveform->get_warnings() . '
                ' . $liveform->output_notices() . '
                
                <form name="form" action="edit_currency.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '" />
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 md:col-6 my-3">
                                            <label for="name" class="form-label">' . lang(array('string'=>'{var:1} Name','vars'=>lang('Currency'))) . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="code" class="form-label">' . lang('Code') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'code', 'id'=>'code', 'class'=>'form-control', 'maxlength'=>'3')) . '
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="symbol" class="form-label">' . lang('Symbol') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'symbol', 'id'=>'symbol', 'class'=>'form-control', 'maxlength'=>'10')) . '
                                        </div>
                                        <div class="col-12 md:col-3 my-3">
                                            <label for="exchange_rate" class="form-label">' . lang('Exchange Rate') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'exchange_rate', 'id'=>'exchange_rate', 'class'=>'form-control', 'maxlength'=>'12','inputmode'=>'decimal','style'=>'text-align: right;') + pg_money_mask_attributes(null, 5)) . '
                                        </div>

                                        <div class="col-12 my-5">
                                            <div class="form-field">
                                                <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'id'=>'base', 'name'=>'base', 'value'=>'1', 'class'=>'')) . '</div>
                                                <label class="" for="base">' . lang('Base') . '</label>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('currency')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </form>
        </div>
</main>' .
        output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    // if currency was selected for delete
    if (isset($_POST['submit_delete']) && ($_POST['submit_delete'] ?? '') === 'Delete') {
        // get currency name for the log
        $query = "SELECT name FROM currencies WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $currency_name = $row['name'];
        
        // delete currency
        $query = "DELETE FROM currencies WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('currency'), $currency_name) )), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_view_currencies = new liveform('view_currencies');
        $liveform_view_currencies->add_notice(lang(array('string'=>'The {var:1} has been deleted.','vars'=>array(lang('Currency') . ' (' . $currency_name . ')'))));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_currencies.php');
        
        
    // else the currency was not selected for delete
    } else {
        $liveform->add_fields_to_session();
        
        $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Name')) )));
        $liveform->validate_required_field('code', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Code')) )));

        // if there is an error, forward user back to edit currency screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_currency.php?id=' . $_POST['id']);
            exit();
        }
        
        // check to see if code is already in use by a different currency
        $query =
            "SELECT id
            FROM currencies
            WHERE
                (code = '" . escape($liveform->get_field_value('code')) . "')
                AND (id != '" . escape($_POST['id'] ?? '') . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if code is already in use, prepare error and forward user back to screen
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('code', lang('The code that you entered is already in use by another currency, please enter a different code.'));
            // forward user to edit currency screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_currency.php?id=' . $_POST['id']);
            exit();
        }

        // The mask posts the rate in the panel language (1.234,56 or 1,234.56).
        $exchange_rate = pg_normalize_amount($liveform->get_field_value('exchange_rate'));

        // Five decimals, the column's scale and what update_exchange_rates.php
        // writes; a plain float cast would write a small rate as "6.0E-5".
        if (is_numeric($exchange_rate)) {
            $exchange_rate = number_format((float) $exchange_rate, 5, '.', '');
        }

        // If this currency was set as the base currency then set exchange rate to 1.
        if ($liveform->get_field_value('base') == 1) {
            $exchange_rate = 1;
        }
        
        // update currency
        $query =
            "UPDATE currencies
            SET
                name = '" . escape($liveform->get_field_value('name')) . "',
                base = '" . escape($liveform->get_field_value('base')) . "',
                code = '" . escape($liveform->get_field_value('code')) . "',
                symbol = '" . escape($liveform->get_field_value('symbol')) . "',
                exchange_rate = '" . escape($exchange_rate) . "',
                last_modified_user_id = '" . $user['id'] . "',
                last_modified_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // If this currency was set as the base currency then update all other currencies
        // in order to make sure that none are set as the base currency.
        if ($liveform->get_field_value('base') == 1) {
            db(
                "UPDATE currencies
                SET base = '0'
                WHERE
                    (base = '1')
                    AND (id != '" . escape($_POST['id'] ?? '') . "')");
        }
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('Currency'),$liveform->get_field_value('name') ))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_currencies = new liveform('view_currencies');
        $liveform_view_currencies->add_notice(lang('The currency has been saved.'));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_currencies.php');
        
        
    }
}
?>