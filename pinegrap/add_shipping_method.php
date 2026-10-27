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

require_once(PG_FUNCTIONS_DIR . '/includes/forms/shipping_method_form.php');

if (!$_POST) {

    $output =
    pg_page_shell([
        'title'=> lang('Create Shipping Method'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Shipping Method'),
        'heading_description' => lang('Create a new shipping method that will be made available during checkout based on the products and destination address.'),
        'cancel'=>array('enable'=>'true','url'=>'view_shipping_methods.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Methods'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_shipping_methods.php'), array('label' => lang('Create Shipping Method'))),
        ]) . '
<main id="content" class="container-fluid">
    <script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/lib/Jquery/jquery-ui-timepicker-addon-1.2.1.min.js"></script>
    ' . get_date_picker_format() . get_date_time_picker_format() . get_time_picker_format() . pg_fields_script() . '
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="add_shipping_method.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_shipping_method_form_cards(pg_shipping_method_form_new_values(), pg_shipping_method_zone_options_for(0)) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

    print $output;

} else {

    validate_token_field();

    $columns = pg_shipping_method_form_columns($_POST);
    $columns['user'] = (int) $user['id'];

    // create shipping method
    db_q(
        "INSERT INTO shipping_methods (" . implode(', ', array_keys($columns)) . ", timestamp)
        VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ", UNIX_TIMESTAMP())",
        array_values($columns)) or output_error('Query failed.');

    pg_shipping_method_save_links(mysqli_insert_id(db::$con), $_POST);

    log_activity(lang(array('string'=>'shipping method ({var:1}) was created','vars'=>$columns['name'])), $_SESSION['sessionusername']);

    // forward user to view shipping methods screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_shipping_methods.php');
}
