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

    // get all countries for selection
    $countries = db_items("SELECT id, name FROM countries ORDER BY name");
    $output_countries = '';
    foreach ($countries as $country) {
        $output_countries .= '<option value="' . $country['id'] . '">' . h($country['name']) . '</option>';
    }

    // get all states for selection
    $states = db_items(
        "SELECT states.id, states.name, countries.name AS country_name
        FROM states
        LEFT JOIN countries ON countries.id = states.country_id
        ORDER BY countries.name, states.name");
    $output_states = '';
    foreach ($states as $state) {
        $output_states .= '<option value="' . $state['id'] . '">' . h($state['country_name']) . ': ' . h($state['name']) . '</option>';
    }

    $output =
        pg_page_shell([
            'title'=> lang('Create Tax Zone'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Create Tax Zone'),
            'cancel'=>array('enable'=>'true','url'=>'view_tax_zones.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Tax Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_tax_zones.php'), array('label' => lang('Create Tax Zone'))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new tax zone that will be used to calculate tax during checkout based on the products and billing address.') . '" title="' . lang('Create Tax Zone') . '">[' . lang('Tax Zone Name') . ']</h2>
                        </div>
                    </div>
                    <form name="form" action="add_tax_zone.php" method="post">
                        ' . get_token_field() . '
                        <div class="row">
                            <div class="col-12 mb-5">
                                <div class="card my-4 h-100">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Options') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="name" class="form-label">' . lang('Tax Zone Name') . '</label>
                                                <input type="text" name="name" id="name" maxlength="50" placeholder="' . lang('Tax Zone Name') . '" class="form-control add-header-content-updater" />
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="tax_rate" class="form-label">' . lang('Tax Rate') . ' (%)</label>
                                                <input type="text" name="tax_rate" id="tax_rate" maxlength="10" class="form-control" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mb-5">
                                <div class="card my-4 h-100">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Tax Zone Countries') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                                <label for="allowed_countries" class="form-label">' . lang('Allowed Countries') . '</label>
                                                <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select country(s)') . '" id="allowed_countries" name="allowed_countries[]" multiple="multiple">' . $output_countries . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 mb-5">
                                <div class="card my-4 h-100">
                                    <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                        ' . lang('Tax Zone States or Provinces') . '
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-12 my-1">
                                                <label for="allowed_states" class="form-label">' . lang('Allowed States') . '</label>
                                                <select style="width:100%" class="select2 form-select" data-placeholder="' . lang('Click to select state(s)') . '" id="allowed_states" name="allowed_states[]" multiple="multiple">' . $output_states . '</select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-success" data-loading-content="' . lang('Creating') . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang('Create') . '</span></button>
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

    $query = "INSERT INTO tax_zones (name, tax_rate, user, timestamp)
              VALUES (
                '" . escape($_POST['name'] ?? '') . "',
                '" . escape($_POST['tax_rate'] ?? '') . "',
                " . $user['id'] . ",
                UNIX_TIMESTAMP())";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    $tax_zone_id = mysqli_insert_id(db::$con);

    $allowed_countries = isset($_POST['allowed_countries']) ? $_POST['allowed_countries'] : array();

    foreach ($allowed_countries as $country_id) {
        if ($country_id) {
            $query = "INSERT INTO tax_zones_countries_xref (tax_zone_id, country_id) VALUES ($tax_zone_id, '" . escape($country_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    $allowed_states = isset($_POST['allowed_states']) ? $_POST['allowed_states'] : array();

    foreach ($allowed_states as $state_id) {
        if ($state_id) {
            $query = "INSERT INTO tax_zones_states_xref (tax_zone_id, state_id) VALUES ($tax_zone_id, '" . escape($state_id) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        }
    }

    log_activity(lang(array('string' => 'tax zone ({var:1}) was created', 'vars' => $_POST['name'])), $_SESSION['sessionusername']);

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_tax_zones.php');
}
