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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM zones WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

if (!$_POST) {
    // get zone data
    $query = "SELECT * FROM zones WHERE id = ?";
    $result = db_q($query, array((string) $_GET['id'])) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);

    $values = array(
        'name' => $row['name'],
        'base_rate' => pg_format_input_amount($row['base_rate'] / 100),
        'primary_weight_rate' => pg_format_input_amount($row['primary_weight_rate'] / 100),
        'secondary_weight_rate' => pg_format_input_amount($row['secondary_weight_rate'] / 100),
        'item_rate' => pg_format_input_amount($row['item_rate'] / 100),
    );

    $choices = pg_zone_form_choices($id);

    $output =
    pg_page_shell([
        'title'=> lang('Edit Shipping Zone'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('Edit Shipping Zone'),
        'heading_description' => lang('Edit a shipping zone that will be used to calculate shipping during checkout based on the products and shipping addresses.'),
        'cancel'=>array('enable'=>'true','url'=>'view_zones.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_zones.php'), array('label' => lang('Edit Shipping Zone'))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
                
                <form name="form" action="edit_zone.php" method="post">
                    ' . get_token_field() . '
                    ' . pg_zone_form_cards($values, $choices['countries'], $choices['states']) . '
                    <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label" >' . lang(array('string'=>'Save') ) . '</span></button>
                                <button type="submit" name="submit_delete" value="Delete" class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('zone')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                    <input type="hidden" name="id" value="' . h($_GET['id']) . '">
                </form>
            </div>
        </div>
    
</main>' .
    output_footer();

    print $output;

} else {
    validate_token_field();
    
    // delete zone references in zones_countries_xref (we do this reguardless of whether we are deleting the zone or updating the zone)
    $query = "DELETE FROM zones_countries_xref ".
             "WHERE zone_id = ?";
    $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

    // delete zone references in zones_states_xref (we do this reguardless of whether we are deleting the zone or updating the zone)
    $query = "DELETE FROM zones_states_xref ".
             "WHERE zone_id = ?";
    $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

    // if zone was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // delete zone
        $query = "DELETE FROM zones WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        // delete zone references in shipping_methods_zones_xref
        $query = "DELETE FROM shipping_methods_zones_xref WHERE zone_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');
        
        // delete zone references in products_zones_xref
        $query = "DELETE FROM products_zones_xref WHERE zone_id = ?";
        $result = db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        log_activity(lang(array('string'=>'shipping zone ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
    // else zone was not selected for delete
    } else {

        // Rates are stored in minor units.
        $base_rate = pg_zone_form_rate($_POST['base_rate'] ?? '');
        $primary_weight_rate = pg_zone_form_rate($_POST['primary_weight_rate'] ?? '');
        $secondary_weight_rate = pg_zone_form_rate($_POST['secondary_weight_rate'] ?? '');
        $item_rate = pg_zone_form_rate($_POST['item_rate'] ?? '');

        // update zone
        $query = "UPDATE zones SET
                    name = ?,
                    base_rate = ?,
                    primary_weight_rate = ?,
                    secondary_weight_rate = ?,
                    item_rate = ?,
                    user = ?,
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = ?";
        $result = db_q($query, array((string) ($_POST['name'] ?? ''), (string) $base_rate, (string) $primary_weight_rate, (string) $secondary_weight_rate, (string) $item_rate, (string) $user['id'], (string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        // load all allowed countries in array by exploding string that has allowed country ids separated by commas
        $allowed_countries = $_POST['allowed_countries'];

        // foreach allowed country insert row in zones_countries_xref table
        foreach ($allowed_countries as $country_id) {
            // if country id is not blank, insert row
            if ($country_id) {
                $query = "INSERT INTO zones_countries_xref (zone_id, country_id) VALUES (?, ?)";
                $result = db_q($query, array((string) ($_POST['id'] ?? ''), (string) $country_id)) or output_error('Query failed.');
            }
        }

        // load all allowed states in array by exploding string that has allowed state ids separated by commas
        $allowed_states = $_POST['allowed_states'];

        // foreach allowed state insert row in zones_states_xref table
        foreach ($allowed_states as $state_id) {
            // if state id is not blank, insert row
            if ($state_id) {
                $query = "INSERT INTO zones_states_xref (zone_id, state_id) VALUES (?, ?)";
                $result = db_q($query, array((string) ($_POST['id'] ?? ''), (string) $state_id)) or output_error('Query failed.');
            }
        }

        log_activity(lang(array('string'=>'shipping zone ({var:1}) was modified','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
    }

    // forward user to view zones screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_zones.php');
}
?>