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

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM tax_zones WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

if (!$_POST) {

    $tax_zone = db_item("SELECT name, tax_rate FROM tax_zones WHERE id = '" . $id . "'");
    $choices = pg_tax_zone_form_choices($id);

    $output =
        pg_page_shell([
            'title'=> lang('Edit Tax Zone'),
            'extra classes'=>'products',
            'icon'=>'store',
            'heading'=>lang('Edit Tax Zone'),
            'heading_description' => lang('Edit a tax zone that will be used to calculate tax during checkout based on the products and billing address.'),
            'cancel'=>array('enable'=>'true','url'=>'view_tax_zones.php')
        ,
            'breadcrumb' => array(array('label' => lang('All Tax Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_tax_zones.php'), array('label' => lang('Edit Tax Zone'))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="edit_tax_zone.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $id . '">
                        ' . pg_tax_zone_form_cards(array('name' => $tax_zone['name'], 'tax_rate' => $tax_zone['tax_rate']), $choices['countries'], $choices['states']) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class="btn-group flex-wrap justify-content-center">
                                    <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('tax zone')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>
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

    // tax_zones.tax_rate is NOT NULL: a blank or unreadable rate is refused
    // before the zone's countries and states are dropped, and "8,25" is read
    // as 8.25, not 8.
    $tax_rate = NULL;
    if (($_POST['submit_delete'] ?? '') != 'Delete') {
        $tax_rate = parse_tax_rate($_POST['tax_rate'] ?? '');
        if ($tax_rate === NULL) {
            output_error(lang('Enter a tax rate between 0 and 100.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
    }

    // delete all existing country/state associations first (for both save and delete)
    $query = "DELETE FROM tax_zones_countries_xref WHERE tax_zone_id = ?";
    db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

    $query = "DELETE FROM tax_zones_states_xref WHERE tax_zone_id = ?";
    db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

    // if delete was requested
    if (($_POST['submit_delete'] ?? '') == 'Delete') {

        $query = "DELETE FROM tax_zones WHERE id = ?";
        db_q($query, array((string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        log_activity(lang(array('string' => 'tax zone ({var:1}) was deleted', 'vars' => $_POST['name'])), $_SESSION['sessionusername']);

    } else {

        $query = "UPDATE tax_zones SET
                    name = ?,
                    tax_rate = ?,
                    user = ?,
                    timestamp = UNIX_TIMESTAMP()
                WHERE id = ?";
        db_q($query, array((string) ($_POST['name'] ?? ''), $tax_rate, (string) $user['id'], (string) ($_POST['id'] ?? ''))) or output_error('Query failed.');

        pg_tax_zone_form_save_links($id, $_POST);

        log_activity(lang(array('string' => 'tax zone ({var:1}) was modified', 'vars' => $_POST['name'])), $_SESSION['sessionusername']);
    }

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_tax_zones.php');
}
