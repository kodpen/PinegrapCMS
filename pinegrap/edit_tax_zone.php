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

    // get tax zone data
    $tax_zone = db_item("SELECT * FROM tax_zones WHERE id = '" . escape($_GET['id']) . "'");

    $name = $tax_zone['name'];
    $tax_rate = $tax_zone['tax_rate'];

    // get all countries, mark allowed ones as selected
    $countries = db_items("SELECT id, name FROM countries ORDER BY name");

    $output_countries = '';

    foreach ($countries as $country) {
        $is_allowed = (bool) db_value(
            "SELECT country_id FROM tax_zones_countries_xref
            WHERE tax_zone_id = '" . escape($_GET['id']) . "'
            AND country_id = '" . escape($country['id']) . "'");

        $selected = $is_allowed ? ' selected="selected"' : '';
        $output_countries .= '<option value="' . $country['id'] . '"' . $selected . '>' . h($country['name']) . '</option>';
    }

    // get all states, mark allowed ones as selected
    $states = db_items(
        "SELECT states.id, states.name, countries.name AS country_name
        FROM states
        LEFT JOIN countries ON countries.id = states.country_id
        ORDER BY countries.name, states.name");

    $output_states = '';

    foreach ($states as $state) {
        $is_allowed = (bool) db_value(
            "SELECT state_id FROM tax_zones_states_xref
            WHERE tax_zone_id = '" . escape($_GET['id']) . "'
            AND state_id = '" . escape($state['id']) . "'");

        $selected = $is_allowed ? ' selected="selected"' : '';
        $output_states .= '<option value="' . $state['id'] . '"' . $selected . '>' . h($state['country_name']) . ': ' . h($state['name']) . '</option>';
    }

    $output =
        pg_page_shell([
            'title'=> lang('Edit Tax Zone'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Edit Tax Zone'),
            'cancel'=>array('enable'=>'true','url'=>'view_tax_zones.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Tax Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_tax_zones.php'), array('label' => lang('Edit Tax Zone'))),
        ]) . '
                    <div class="row">
                <div class="col-12">
                    <div class="row mb-2 flex-wrap">
                        <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Edit a tax zone that will be used to calculate tax during checkout based on the products and billing address.') . '" title="' . lang('Edit Tax Zone') . '">[' . h($name) . ']</h2>
                        </div>
                    </div>
                    <form name="form" action="edit_tax_zone.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . h($_GET['id']) . '">
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
                                                <input type="text" name="name" id="name" maxlength="50" value="' . h($name) . '" class="form-control" />
                                            </div>
                                            <div class="col-12 col-md-6 col-lg-4 my-2">
                                                <label for="tax_rate" class="form-label">' . lang('Tax Rate') . ' (%)</label>
                                                <input type="text" name="tax_rate" id="tax_rate" maxlength="10" value="' . h($tax_rate) . '" class="form-control" />
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
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-success" data-loading-content="' . lang('Saving') . '"><span class="material-icons me-2">save</span><span class="btn-text">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-danger" data-loading-content="' . lang('Deleting') . '" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('tax zone')))) . '"><span class="material-icons me-2">delete</span><span class="btn-text">' . lang('Delete') . '</span></button>
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

    // delete all existing country/state associations first (for both save and delete)
    $query = "DELETE FROM tax_zones_countries_xref WHERE tax_zone_id = '" . escape($_POST['id'] ?? '') . "'";
    mysqli_query(db::$con, $query) or output_error('Query failed.');

    $query = "DELETE FROM tax_zones_states_xref WHERE tax_zone_id = '" . escape($_POST['id'] ?? '') . "'";
    mysqli_query(db::$con, $query) or output_error('Query failed.');

    // if delete was requested
    if (($_POST['submit_delete'] ?? '') == 'Delete') {

        $query = "DELETE FROM tax_zones WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        mysqli_query(db::$con, $query) or output_error('Query failed.');

        log_activity(lang(array('string' => 'tax zone ({var:1}) was deleted', 'vars' => $_POST['name'])), $_SESSION['sessionusername']);

    } else {

        $query = "UPDATE tax_zones SET
                    name = '" . escape($_POST['name'] ?? '') . "',
                    tax_rate = '" . escape($_POST['tax_rate'] ?? '') . "',
                    user = '" . $user['id'] . "',
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = '" . escape($_POST['id'] ?? '') . "'";
        mysqli_query(db::$con, $query) or output_error('Query failed.');

        $allowed_countries = isset($_POST['allowed_countries']) ? $_POST['allowed_countries'] : array();

        foreach ($allowed_countries as $country_id) {
            if ($country_id) {
                $query = "INSERT INTO tax_zones_countries_xref (tax_zone_id, country_id) VALUES ('" . escape($_POST['id'] ?? '') . "', '" . escape($country_id) . "')";
                mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }

        $allowed_states = isset($_POST['allowed_states']) ? $_POST['allowed_states'] : array();

        foreach ($allowed_states as $state_id) {
            if ($state_id) {
                $query = "INSERT INTO tax_zones_states_xref (tax_zone_id, state_id) VALUES ('" . escape($_POST['id'] ?? '') . "', '" . escape($state_id) . "')";
                mysqli_query(db::$con, $query) or output_error('Query failed.');
            }
        }

        log_activity(lang(array('string' => 'tax zone ({var:1}) was modified', 'vars' => $_POST['name'])), $_SESSION['sessionusername']);
    }

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_tax_zones.php');
}
