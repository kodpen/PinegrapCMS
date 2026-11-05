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

require_once(PG_FUNCTIONS_DIR . '/includes/forms/arrival_date_form.php');

if (!$_POST) {
    $output =
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Arrival Date'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Arrival Date'))),
        'heading_description' => lang('Create a new arrival date for any special occasion, or holiday that will be presented during checkout. (Delete all will hide feature.)'),
        'cancel'=>array('enable'=>'true','url'=>'view_arrival_dates.php'),
        'breadcrumb' => array(
            array('label' => lang('All Shipping Arrival Dates'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_arrival_dates.php'),
            array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Arrival Date')))),
        ),
    ]) . '
<main id="content" class="container-fluid">
    ' . get_date_picker_format() . '
    <form name="form" action="add_arrival_date.php" method="post">
        ' . get_token_field() . '
        <input type="hidden" id="last_shipping_cutoff_number" name="last_shipping_cutoff_number" value="0" />
        ' . pg_arrival_date_form_layout(pg_arrival_date_form_cards(array()), '
                                        <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3" aria-hidden="true"></span><span class="pg-btn-label">' . lang('Create') . '</span></button>') . '
        ' . pg_fields_script() . '
    </form>
</main>' .
        output_footer();

    print $output;

} else {
    validate_token_field();

    // Nothing is written while a field is wrong; the browser's back
    // button returns to the form as it was typed.
    $errors = pg_arrival_date_form_errors($_POST);

    if ($errors) {
        output_error(pg_arrival_date_form_error_text($errors) . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }


    // Create the arrival date; one saved as the default takes the switch
    // off every other one.
    $arrival_date_id = pg_store_arrival_date_save(0, pg_arrival_date_form_values($_POST, $user['id']));
    if ($arrival_date_id <= 0) {
        output_error('Query failed.');
    }
    // Cut-offs without a shipping method, with a method used twice or with a
    // time outside the dates are left out.
    $shipping_cutoff_error = pg_arrival_date_form_save_cutoffs($arrival_date_id, $_POST);

    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('arrival date'),$_POST['name']))), $_SESSION['sessionusername']);
   
    // if there was a shipping cut-off error, then add notice and forward user to edit arrival date screen
    if ($shipping_cutoff_error == TRUE) {
        include_once('liveform.class.php');
        $liveform = new liveform('edit_arrival_date');
        
        $liveform->add_notice(lang('The arrival date has been created, however one or more shipping cut-offs were removed because of an error. It is recommended that you review the shipping cut-offs. Please make sure you select a shipping method, enter a valid cut-off date &amp; time that falls between the start date and end date, and make sure you do not enter multiple shipping cut-offs for the same shipping method.'));
        
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_arrival_date.php?id=' . $arrival_date_id);
        
    // else there was not a shipping cut-off error, so forward user to view arrival dates screen
    } else {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_arrival_dates.php');
    }
}
?>