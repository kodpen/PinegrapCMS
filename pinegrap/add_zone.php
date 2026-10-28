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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/zone_form.php');
$user = validate_user();
validate_ecommerce_access($user);

if (!$_POST) {

    $choices = pg_zone_form_choices();

    $output =
    pg_page_shell([
        'title'=> lang('Create Shipping Zone'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Shipping Zone'),
        'heading_description' => lang('Create a new shipping zone that will be used to calculate shipping during checkout based on the products and shipping addresses.'),
        'cancel'=>array('enable'=>'true','url'=>'view_zones.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_zones.php'), array('label' => lang('Create Shipping Zone'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="add_zone.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_zone_form_cards(array('name' => '', 'base_rate' => '0', 'primary_weight_rate' => '0', 'secondary_weight_rate' => '0', 'item_rate' => '0'), $choices['countries'], $choices['states']) . '
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
    

    // Rates are stored in minor units.
    $base_rate = pg_zone_form_rate($_POST['base_rate'] ?? '');
    $primary_weight_rate = pg_zone_form_rate($_POST['primary_weight_rate'] ?? '');
    $secondary_weight_rate = pg_zone_form_rate($_POST['secondary_weight_rate'] ?? '');
    $item_rate = pg_zone_form_rate($_POST['item_rate'] ?? '');
    
    // create zone
    $query = "INSERT INTO zones (
                name,
                base_rate,        
                primary_weight_rate,
                secondary_weight_rate,
                item_rate,
                user,
                timestamp)
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                UNIX_TIMESTAMP())";
    $result = db_q($query, array((string) ($_POST['name'] ?? ''), (string) $base_rate, (string) $primary_weight_rate, (string) $secondary_weight_rate, (string) $item_rate, (int) $user['id'])) or output_error('Query failed.');

    $zone_id = mysqli_insert_id(db::$con);

    // load all allowed countries in array by exploding string that has allowed country ids separated by commas
    
    $allowed_countries = $_POST['allowed_countries'];
    
    // foreach allowed country insert row in zones_countries_xref table
    foreach ($allowed_countries as $country_id) {
        // if country id is not blank, insert row
        if ($country_id) {
            $query = "INSERT INTO zones_countries_xref (zone_id, country_id) VALUES (?, ?)";
            $result = db_q($query, array((int) $zone_id, (string) $country_id)) or output_error('Query failed.');
        }
    }

    // load all allowed states in array by exploding string that has allowed state ids separated by commas
    $allowed_states = $_POST['allowed_states'];

    // foreach allowed state insert row in zones_states_xref table
    foreach ($allowed_states as $state_id) {
        // if state id is not blank, insert row
        if ($state_id) {
            $query = "INSERT INTO zones_states_xref (zone_id, state_id) VALUES (?, ?)";
            $result = db_q($query, array((int) $zone_id, (string) $state_id)) or output_error('Query failed.');
        }
    }

    log_activity(lang(array('string'=>'shipping zone ({var:1}) was created','vars'=>$_POST['name'])), $_SESSION['sessionusername']);

    // forward user to view zones screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_zones.php');
}
?>