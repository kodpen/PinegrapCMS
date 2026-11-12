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

// A save with a blank name or a rate that is no amount of 0 or more draws
// the form again with what was typed and the message under the field;
// nothing is written. Delete is never held
// back by it.
$selected = null;
$errors = array();

if ($_POST) {
    validate_token_field();

    if (($_POST['submit_delete'] ?? '') != 'Delete') {
        $values = pg_zone_form_post_values($_POST);
        $name_error = pg_zone_form_name_error($values['name']);

        if ($name_error !== '') {
            $errors['name'] = $name_error;
        }

        $errors += pg_zone_form_rate_errors($values);

        if ($errors) {
            $selected = $values;
        }
    }
}

if (!$_POST || $errors) {
    if (!$errors) {
        // get zone data
        $query = "SELECT * FROM zones WHERE id = ?";
        $result = db_q($query, array($id)) or output_error('Query failed.');
        $row = mysqli_fetch_assoc($result);

        $values = array(
            'name' => $row['name'],
            'base_rate' => pg_format_input_amount($row['base_rate'] / 100),
            'primary_weight_rate' => pg_format_input_amount($row['primary_weight_rate'] / 100),
            'secondary_weight_rate' => pg_format_input_amount($row['secondary_weight_rate'] / 100),
            'item_rate' => pg_format_input_amount($row['item_rate'] / 100),
        );
    }

    $choices = pg_zone_form_choices($id, $selected);
    // Products, methods, and those left with no other zone: the summary
    // column and the Delete confirmation read them.
    $usage = pg_zone_form_delete_counts($id);

    $output =
    pg_page_shell([
        'title'=> lang('Edit Shipping Zone'),
        'extra classes'=>'products pg-measure-edit',
        'icon'=>'store',
        'heading'=>lang('Edit Shipping Zone'),
        'heading_description' => lang('Edit a shipping zone that will be used to calculate shipping during checkout based on the products and shipping addresses.'),
        'cancel'=>array('enable'=>'true','url'=>'view_zones.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_zones.php'), array('label' => lang('Edit Shipping Zone'))),
        ]) . '
<main id="content" class="container-fluid">
    <form name="form" action="edit_zone.php" method="post">
        ' . get_token_field() . '
        ' . pg_zone_form_layout(
            pg_zone_form_cards($values, $choices['countries'], $choices['states'], $errors, array('countries' => $choices['country_count'], 'states' => $choices['state_count'], 'products' => $usage[0], 'methods' => $usage[1])),
            '
                                        <button type="submit" id="save_button" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><i class="bi bi-floppy me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                        <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" formnovalidate data-confirm-content="' . h(implode(' ', array_map('lang', pg_zone_form_delete_confirm(...$usage)))) . '"><i class="bi bi-trash me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string'=>'Delete') ) . '</span></button>'
        ) . '
        <input type="hidden" name="id" value="' . h($id) . '">
    </form>
</main>' .
    output_footer();

    print $output;

} else {
    $zone_id = $id;

    // if zone was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        pg_store_zone_delete($zone_id, (string) ($_POST['name'] ?? '')) or output_error('Query failed.');

        log_activity(lang(array('string'=>'shipping zone ({var:1}) was deleted','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
    // else zone was not selected for delete
    } else {
        // Rates are stored in minor units.
        $rates = array();
        foreach (pg_store_zone_rate_columns() as $column) {
            $rates[$column] = pg_zone_form_rate($_POST[$column] ?? '');
        }

        pg_store_zone_save($zone_id, array('name' => $values['name']) + $rates, $values['allowed_countries'], $values['allowed_states'], (int) $user['id']) or output_error('Query failed.');

        log_activity(lang(array('string'=>'shipping zone ({var:1}) was modified','vars'=>$_POST['name'])), $_SESSION['sessionusername']);
    }

    // forward user to view zones screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_zones.php');
}
?>