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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

if (!$_POST) {

    // only ever appended to below, so they have to start out empty
    $output_disallowed_countries = '';
    $output_disallowed_states = '';

    // get all countries for countries selection
    $query = "SELECT id, name FROM countries ORDER BY name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while ($row = mysqli_fetch_assoc($result)) {
        $output_disallowed_countries .= '<option value="' . $row['id'] . '">' . h($row['name']) . '</option>';
    }

    // get all states for states selection
    $query = "SELECT states.id, states.name, countries.name as country_name
             FROM states
             LEFT JOIN countries ON countries.id = states.country_id
             ORDER BY countries.name, states.name";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while ($row = mysqli_fetch_assoc($result)) {
        $output_disallowed_states .= '<option value="' . $row['id'] . '">' . h($row['country_name']) . ': ' . h($row['name']) . '</option>';
    }

    $output =
    pg_page_shell([
        'title'=> lang('Create Shipping Zone'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Create Shipping Zone'),
        'cancel'=>array('enable'=>'true','url'=>'view_zones.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_zones.php'), array('label' => lang('Create Shipping Zone'))),
        ]) . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new shipping zone that will be used to calculate shipping during checkout based on the products and shipping addresses.') . '" title="' . lang('Create Shipping Zone') . '">[' . lang('Shipping Zone Name') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_zone.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12 col-md-6 mb-5">
                            <div class="card my-4 h-100">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Zone Name') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-lg-6 my-2">
                                            <label for="name" class="form-label">' . lang('Shipping Zone Name') . '</label>
                                            <input type="text" name="name" placeholder="' . lang('Zone Name') . '" id="name" maxlength="50" class="form-control add-header-content-updater" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-5">
                            <div class="card my-4 h-100">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Shipping Zone Fees') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-lg-6 my-2">
                                            <label for="base_rate" class="form-label">' . lang('Base Rate') . '</label>
                                            <div class="input-group">
                                                <input value="0" type="text" name="base_rate" id="base_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                <label class="input-group-text" for="base_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-6 my-2">
                                            <label for="primary_weight_rate" class="form-label">' . lang('Primary Weight Rate') . '</label>
                                            <div class="input-group">
                                                <input value="0" type="text" name="primary_weight_rate" id="primary_weight_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                <label class="input-group-text" for="primary_weight_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-6 my-2">
                                            <label for="secondary_weight_rate" class="form-label">' . lang('Secondary Weight Rate') . '</label>
                                            <div class="input-group">
                                                <input value="0" type="text" name="secondary_weight_rate" id="secondary_weight_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                <label class="input-group-text" for="secondary_weight_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-6 my-2">
                                            <label for="item_rate" class="form-label">' . lang('Item Rate') . '</label>
                                            <div class="input-group">
                                                <input value="0" type="text" name="item_rate" id="item_rate" class="form-control" maxlength="12" inputmode="numeric" data-inputmask-alias="currency" data-inputmask-groupSeparator="," data-inputmask-digits="2" data-inputmask-digitsOptional="false" data-inputmask-placeholder="0"  style="text-align: right;" />
                                                <label class="input-group-text" for="item_rate">' . BASE_CURRENCY_SYMBOL . '</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-5">
                            <div class="card my-4 h-100">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Shipping Zone Countries') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <label for="allowed_countries" class="form-label">' . lang('Allowed Countries') . '</label>
                                            <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_countries" name="allowed_countries[]" multiple="multiple">' . $output_disallowed_countries . '</select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-5">
                            <div class="card my-4 h-100">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Shipping Zone States or Provinces') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 my-1">
                                            <label for="allowed_states" class="form-label">' . lang('Allowed States') . '</label>
                                            <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select shipping zone(s)') . '" id="allowed_states" name="allowed_states[]" multiple="multiple">' . $output_disallowed_states . '</select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
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
    

    // remove commas and spaces from price
    $base_rate = str_replace(',', '', $_POST['base_rate']);
    $base_rate = str_replace(' ', '',$base_rate); 
    $primary_weight_rate = str_replace(',', '', $_POST['primary_weight_rate']);
    $primary_weight_rate = str_replace(' ', '',$primary_weight_rate); 
    $secondary_weight_rate = str_replace(',', '', $_POST['secondary_weight_rate']);
    $secondary_weight_rate = str_replace(' ', '',$secondary_weight_rate); 
    $item_rate = str_replace(',', '', $_POST['item_rate']);
    $item_rate = str_replace(' ', '',$item_rate); 

    // convert rates from dollars to cents
    $base_rate = $base_rate * 100;
    $primary_weight_rate = $primary_weight_rate * 100;
    $secondary_weight_rate = $secondary_weight_rate * 100;
    $item_rate = $item_rate * 100;
    
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
                '" . escape($_POST['name'] ?? '') . "',
                '" . escape($base_rate) . "',
                '" . escape($primary_weight_rate) . "',
                '" . escape($secondary_weight_rate) . "',
                '" . escape($item_rate) . "',
                " . $user['id'] . ",
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $zone_id = mysqli_insert_id(db::$con);

    // load all allowed countries in array by exploding string that has allowed country ids separated by commas
    
    $allowed_countries = $_POST['allowed_countries'];
    
    // foreach allowed country insert row in zones_countries_xref table
    foreach ($allowed_countries as $country_id) {
        // if country id is not blank, insert row
        if ($country_id) {
            $query = "INSERT INTO zones_countries_xref (zone_id, country_id) VALUES ($zone_id, '" . escape($country_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    // load all allowed states in array by exploding string that has allowed state ids separated by commas
    $allowed_states = $_POST['allowed_states'];

    // foreach allowed state insert row in zones_states_xref table
    foreach ($allowed_states as $state_id) {
        // if state id is not blank, insert row
        if ($state_id) {
            $query = "INSERT INTO zones_states_xref (zone_id, state_id) VALUES ($zone_id, '" . escape($state_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    log_activity(lang(array('string'=>'shipping zone ({var:1}) was created','vars'=>$_POST['name'])), $_SESSION['sessionusername']);

    // forward user to view zones screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_zones.php');
}
?>