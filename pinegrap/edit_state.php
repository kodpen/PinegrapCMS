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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/state_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/zone_form.php');
$user = validate_user();
validate_ecommerce_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM states WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// get state data
$query = "SELECT * FROM states WHERE id = ?";
$result = db_q($query, array((int) $id)) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

// A save with a blank name or code, or a code another state of the same
// country has, draws the form again with what was typed and the message
// under the field; nothing is written. Delete is never held back by them.
$values = array('name' => $row['name'], 'code' => $row['code'], 'country_id' => $row['country_id']);
$errors = array();

if ($_POST) {
    validate_token_field();

    $values = pg_state_form_values($_POST);

    if (($_POST['submit_delete'] ?? '') != 'Delete') {
        $errors = pg_state_form_required_errors($values);
        $code_error = pg_state_form_code_error($values, $row, $id);

        if (($code_error !== '') && !isset($errors['code'])) {
            $errors['code'] = $code_error;
        }

        $country_error = pg_state_form_country_error($values['country_id']);

        if ($country_error !== '') {
            $errors['country_id'] = $country_error;
        }
    }
} else {
    // A state whose country is gone is shown with an empty country choice
    // and the message, so a save cannot move it to another country unseen.
    $country_error = pg_state_form_country_error($values['country_id']);

    if ($country_error !== '') {
        $errors['country_id'] = $country_error;
    }
}

if (!$_POST || $errors) {

    // What the state is in, under the Summary; the Delete confirmation
    // names the same counts.
    $delete_counts = pg_state_form_delete_counts($id);
    $last_change = db_item(
        "SELECT states.timestamp, user.user_username
        FROM states
        LEFT JOIN user ON user.user_id = states.user
        WHERE states.id = '" . $id . "'");
    $status_rows = pg_state_form_status_row(lang('Shipping Zones'), (string) $delete_counts[1], false)
        . pg_state_form_status_row(lang('Tax Zones'), (string) $delete_counts[2], false)
        . pg_state_form_status_row(lang('Verified Shipping Addresses'), (string) $delete_counts[0], false)
        . pg_state_form_status_row(lang('Last Modified'), get_relative_time(array('timestamp' => (int) ($last_change['timestamp'] ?? 0))) . ((string) ($last_change['user_username'] ?? '') !== '' ? ' ' . h($last_change['user_username']) : ''));

    $output =
    pg_page_shell([
        'title'=> lang(array('string'=>'Edit {var:1}','vars'=>lang('State'))),
        'extra classes'=>'products pg-measure-edit',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Edit {var:1}','vars'=>lang('State'))),
        'heading_description' => lang('Edit a state/province that can be included in any shipping zone or tax zone.'),
        'cancel'=>array('enable'=>'true','url'=>'view_states.php')
    ,
            'breadcrumb' => array(array('label' => lang('All States'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_states.php'), array('label' => lang(array('string'=>'Edit {var:1}','vars'=>lang('State'))))),
        ]) . '
<main id="content" class="container-fluid">
                <form name="form" action="edit_state.php" method="post">
                    ' . get_token_field() . '
                    <input type="hidden" name="id" value="' . $id . '">
                    ' . pg_state_form_layout(
                        pg_state_form_cards($values, $errors, $status_rows),
                        '
                                        <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                        <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" formnovalidate data-confirm-content="' . h(implode(' ', array_map('lang', pg_state_form_delete_confirm(...$delete_counts)))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>') . '
                </form>
</main>' .
    output_footer();

    print $output;

} else {
    // if state was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // The state, its places in shipping and tax zones and its verified
        // shipping addresses.
        pg_store_state_delete($id) or output_error('Query failed.');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was deleted','vars'=>array(lang('state'), $_POST['name']) )), $_SESSION['sessionusername']);

    // else state was not selected for delete
    } else {
        pg_store_state_save($id, $values, (int) $user['id']) or output_error('Query failed.');
        log_activity(lang(array('string'=>'{var:1} ({var:2}) was modified','vars'=>array(lang('state'), $_POST['name']) )), $_SESSION['sessionusername']);

    }

    // forward user to view states screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_states.php');
}
?>