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

// A post with a blank name or a rate that is no amount of 0 or more draws
// the form again with what was typed and the message under the field;
// nothing is written.
$values = array('name' => '', 'base_rate' => '0', 'primary_weight_rate' => '0', 'secondary_weight_rate' => '0', 'item_rate' => '0');
$selected = null;
$errors = array();

if ($_POST) {
    validate_token_field();

    $values = pg_zone_form_post_values($_POST);
    $selected = $values;
    $name_error = pg_zone_form_name_error($values['name']);

    if ($name_error !== '') {
        $errors['name'] = $name_error;
    }

    $errors += pg_zone_form_rate_errors($values);
}

if (!$_POST || $errors) {

    $choices = pg_zone_form_choices(0, $selected);

    $output =
    pg_page_shell([
        'title'=> lang('Create Shipping Zone'),
        'extra classes'=>'products pg-measure-edit',
        'icon'=>'store',
        'heading'=>lang('Create Shipping Zone'),
        'heading_description' => lang('Create a new shipping zone that will be used to calculate shipping during checkout based on the products and shipping addresses.'),
        'cancel'=>array('enable'=>'true','url'=>'view_zones.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Shipping Zones'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_zones.php'), array('label' => lang('Create Shipping Zone'))),
        ]) . '
<main id="content" class="container-fluid">
    <form name="form" action="add_zone.php" method="post">
        ' . get_token_field() . '
        ' . pg_zone_form_layout(
            pg_zone_form_cards($values, $choices['countries'], $choices['states'], $errors, array('countries' => $choices['country_count'], 'states' => $choices['state_count'])),
            '
                                        <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
        ) . '
    </form>
</main>' .
    output_footer();

    print $output;

} else {
    // Rates are stored in minor units.
    $rates = array();
    foreach (pg_store_zone_rate_columns() as $column) {
        $rates[$column] = pg_zone_form_rate($_POST[$column] ?? '');
    }

    pg_store_zone_save(0, array('name' => $values['name']) + $rates, $values['allowed_countries'], $values['allowed_states'], (int) $user['id']) or output_error('Query failed.');

    log_activity(lang(array('string'=>'shipping zone ({var:1}) was created','vars'=>$_POST['name'])), $_SESSION['sessionusername']);

    // forward user to view zones screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_zones.php');
}
?>