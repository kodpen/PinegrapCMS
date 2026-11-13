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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/currency_form.php');
$liveform = new liveform('edit_currency', $_REQUEST['id']);

// Whether the stored row is the base currency: it can be neither deleted nor
// switched off, or the store falls back to USD for every price and payment.
$stored_base = (int) db_value("SELECT base FROM currencies WHERE id = '" . $id . "'");

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
            WHERE id = ?";
        $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
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
            'extra classes'=>'setting pg-measure-edit',
            'icon'=>'setting', 
            'heading'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('Currency'))),
            'heading_description' => lang('Edit this currency conversion selectable by customers.'),
            'cancel'=>array('enable'=>'true','url'=>'view_currencies.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Currencies'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_currencies.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('Currency'))))),
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
                    ' . pg_currency_form_layout(
                        pg_currency_form_cards($liveform, $stored_base == 1),
                        '
                                <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                ' . (($stored_base == 1) ? '' : '<button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('currency')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>')
                    ) . '
                </form>
            </div>
        </div>
</main>' .
        output_footer();
    
    $liveform->remove_form();

} else {
    validate_token_field();
    
    // if currency was selected for delete
    if (isset($_POST['submit_delete']) && ($_POST['submit_delete'] ?? '') === 'Delete') {
        $delete_error = pg_currency_form_delete_error($stored_base);

        if ($delete_error !== '') {
            $liveform->mark_error('base', $delete_error);
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_currency.php?id=' . $id);
            exit();
        }

        // get currency name for the log
        $query = "SELECT name FROM currencies WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);
        $currency_name = $row['name'];
        
        if (!pg_store_currency_delete($id)) {
            output_error('Query failed.');
        }
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('currency'), $currency_name) )), $_SESSION['sessionusername']);

        $liveform->remove_form();
        $liveform_view_currencies = new liveform('view_currencies');
        $liveform_view_currencies->add_notice(lang(array('string'=>'The currency ({var:1}) has been deleted.','vars'=>array(h(pg_currency_label($currency_name))))));
        
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_currencies.php');
        
        
    // else the currency was not selected for delete
    } else {
        $liveform->add_fields_to_session();
        $liveform->assign_field_value('code', pg_currency_form_code($liveform->get_field_value('code')));
        
        $liveform->validate_required_field('name', lang(array('string'=>'{var:1} is required','vars'=>array(lang(array('string'=>'{var:1} Name','vars'=>lang('Currency')))) )));
        $liveform->validate_required_field('code', lang(array('string'=>'{var:1} is required','vars'=>array(lang('Code')) )));

        if (!$liveform->check_field_error('code') && (($code_error = pg_currency_form_code_error($liveform->get_field_value('code'))) !== '')) {
            $liveform->mark_error('code', $code_error);
        }

        $base_error = pg_currency_form_base_error($stored_base, $liveform->get_field_value('base'));

        if ($base_error !== '') {
            $liveform->mark_error('base', $base_error);
        }

        // if there is an error, forward user back to edit currency screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_currency.php?id=' . $_POST['id']);
            exit();
        }
        
        // if code is already in use, prepare error and forward user back to screen
        if (pg_currency_form_code_in_use($liveform->get_field_value('code'), (string) ($_POST['id'] ?? ''))) {
            $liveform->mark_error('code', lang('The code that you entered is already in use by another currency, please enter a different code.'));
            // forward user to edit currency screen
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_currency.php?id=' . $_POST['id']);
            exit();
        }

        // a non-base currency needs a rate above zero: checkout divides by it
        $exchange_rate_error = pg_currency_form_exchange_rate_error($liveform->get_field_value('exchange_rate'), $liveform->get_field_value('base'));

        if ($exchange_rate_error !== '') {
            $liveform->mark_error('exchange_rate', $exchange_rate_error);
            header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/edit_currency.php?id=' . $id);
            exit();
        }

        $exchange_rate = pg_currency_form_exchange_rate($liveform->get_field_value('exchange_rate'), $liveform->get_field_value('base'));
        
        // The shared write path (includes/fn/store_currencies.php) also keeps
        // a single base currency and announces the change.
        $saved = pg_store_currency_save($id, array(
            'name' => (string) $liveform->get_field_value('name'),
            'base' => (int) $liveform->get_field_value('base'),
            'code' => (string) $liveform->get_field_value('code'),
            'symbol' => (string) $liveform->get_field_value('symbol'),
            'exchange_rate' => (string) $exchange_rate,
        ), (int) $user['id']);

        if ($saved <= 0) {
            output_error('Query failed.');
        }
        
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('Currency'),$liveform->get_field_value('name') ))), $_SESSION['sessionusername']);
        $liveform->remove_form();
        $liveform_view_currencies = new liveform('view_currencies');
        $liveform_view_currencies->add_notice(lang('The currency has been saved.'));
        header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_currencies.php');
        
        
    }
}
?>