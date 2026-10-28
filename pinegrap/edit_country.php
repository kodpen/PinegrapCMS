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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/country_form.php');
$user = validate_user();
validate_ecommerce_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
$result = ($id > 0) ? db_q("SELECT * FROM countries WHERE id = ?", array($id)) : false;
$row = $result ? mysqli_fetch_assoc($result) : null;
if (!$row) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

if (!$_POST) {
    $output =
        pg_page_shell([
            'title'=> lang('Edit Country'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Edit Country'),
            'heading_description' => lang('Edit a country that can be included in any shipping zone or tax zone.'),
            'cancel'=>array('enable'=>'true','url'=>'view_countries.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Countries'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_countries.php'), array('label' => lang('Edit Country'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="edit_country.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $id . '">
                        ' . pg_country_form_card(pg_country_form_values($row)) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang('WARNING: This country and all states and verified shipping addresses in this country will be permanently deleted.') . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();

    echo $output;

} else {
    validate_token_field();

    $values = pg_country_form_values($_POST);

    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // Clear everything tied to each state of the country before the states themselves.
        $result = db_q("SELECT id FROM states WHERE country_id = ?", array($id)) or output_error('Query failed.');
        $state_ids = array();
        while ($state = mysqli_fetch_assoc($result)) {
            $state_ids[] = (int) $state['id'];
        }

        foreach ($state_ids as $state_id) {
            db_q("DELETE FROM zones_states_xref WHERE state_id = ?", array($state_id));
            db_q("DELETE FROM tax_zones_states_xref WHERE state_id = ?", array($state_id));
            db_q("DELETE FROM verified_shipping_addresses WHERE state_id = ?", array($state_id));
        }

        db_q("DELETE FROM states WHERE country_id = ?", array($id));
        db_q("DELETE FROM zones_countries_xref WHERE country_id = ?", array($id));
        db_q("DELETE FROM tax_zones_countries_xref WHERE country_id = ?", array($id));
        db_q("DELETE FROM countries WHERE id = ?", array($id));

        log_activity(lang(array('string' => 'country ({var:1}) was deleted', 'vars' => array($values['name']))), $_SESSION['sessionusername']);

    } else {
        $query = "UPDATE countries SET
                name = ?,
                code = ?,
                zip_code_required = ?,
                transit_adjustment_days = ?,
                default_selected = ?,
                user = ?,
                timestamp = UNIX_TIMESTAMP()
            WHERE id = ?";
        $result = db_q($query, array(
            $values['name'],
            $values['code'],
            $values['zip_code_required'],
            $values['transit_adjustment_days'],
            $values['default_selected'],
            (int) $user['id'],
            $id)) or output_error('Query failed.');

        // if default selected was checked, then turn value off for all other countries
        if ($values['default_selected'] == 1) {
            db_q("UPDATE countries SET default_selected = 0 WHERE id != ?", array($id));
        }

        log_activity(lang(array('string' => 'country ({var:1}) was modified', 'vars' => array($values['name']))), $_SESSION['sessionusername']);
    }

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_countries.php');
}
