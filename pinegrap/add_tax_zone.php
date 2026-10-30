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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/tax_zone_form.php');

$user = validate_user();
validate_ecommerce_access($user);

if (!$_POST) {

    $choices = pg_tax_zone_form_choices();

    $output =
        pg_page_shell([
            'title'=> lang('Create Tax Zone'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Create Tax Zone'),
            'heading_description' => lang('Create a new tax zone that will be used to calculate tax during checkout based on the products and billing address.'),
            'cancel'=>array('enable'=>'true','url'=>'view_tax_zones.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Tax Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_tax_zones.php'), array('label' => lang('Create Tax Zone'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="add_tax_zone.php" method="post">
                        ' . get_token_field() . '
                        ' . pg_tax_zone_form_cards(null, $choices['countries'], $choices['states']) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang('Create') . '</span></button>
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

    // tax_zones.tax_rate is NOT NULL: a blank or unreadable rate is refused,
    // never stored as 0, and "8,25" is read as 8.25, not 8.
    $tax_rate = parse_tax_rate($_POST['tax_rate'] ?? '');
    if ($tax_rate === NULL) {
        output_error(lang('Enter a tax rate between 0 and 100.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $query = "INSERT INTO tax_zones (name, tax_rate, user, timestamp)
              VALUES (
                ?,
                ?,
                ?,
                UNIX_TIMESTAMP())";
    $result = db_q($query, array((string) ($_POST['name'] ?? ''), $tax_rate, (int) $user['id'])) or output_error('Query failed.');

    $tax_zone_id = mysqli_insert_id(db::$con);

    pg_tax_zone_form_save_links($tax_zone_id, $_POST);

    log_activity(lang(array('string' => 'tax zone ({var:1}) was created', 'vars' => $_POST['name'])), $_SESSION['sessionusername']);

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_tax_zones.php');
}
