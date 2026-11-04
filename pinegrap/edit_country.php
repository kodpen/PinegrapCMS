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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/zone_form.php');
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

// A save with a blank name or code, or a code another country has, draws
// the form again with what was typed and the message under the field;
// nothing is written. Delete is never held back by them.
$values = pg_country_form_values($row);
$errors = array();

if ($_POST) {
    validate_token_field();

    $values = pg_country_form_values($_POST);

    if (($_POST['submit_delete'] ?? '') != 'Delete') {
        $errors = pg_country_form_required_errors($values);
        $code_error = pg_country_form_code_error($values['code'], (string) $row['code'], $id);

        if (($code_error !== '') && !isset($errors['code'])) {
            $errors['code'] = $code_error;
        }
    }
}

if (!$_POST || $errors) {
    // The Summary's counts; the zone counts also word the Delete confirmation.
    $usage = pg_zone_form_member_delete_counts('country', $id);
    $summary_result = db_q("SELECT
            (SELECT COUNT(*) FROM states WHERE country_id = ?) AS state_count,
            (SELECT user_username FROM user WHERE user_id = ?) AS username", array($id, (int) ($row['user'] ?? 0)));
    $summary = $summary_result ? mysqli_fetch_assoc($summary_result) : null;
    $change_user = (string) ($summary['username'] ?? '');
    $status_rows = pg_country_form_edit_status_rows((int) ($summary['state_count'] ?? 0), $usage[0], $usage[1],
        get_relative_time(array('timestamp' => (int) ($row['timestamp'] ?? 0))) . ($change_user !== '' ? ' ' . h($change_user) : ''));

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
                    <form name="form" action="edit_country.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="id" value="' . $id . '">
                        ' . pg_country_form_layout(
                            pg_country_form_card($values, $errors, $status_rows),
                            '
                                        <button type="submit" name="submit_save" value="Save" class="btn my-1 btn-solid theme-success"><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang('Save') . '</span></button>
                                    <button type="submit" name="submit_delete" value="Delete" class="btn my-1 btn-solid theme-danger" data-confirm-content="' . h(implode(' ', array_map('lang', pg_country_form_delete_confirm(...$usage)))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label">' . lang('Delete') . '</span></button>'
                        ) . '
                    </form>
</main>' .
        output_footer();

    echo $output;

} else {
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        // The country, its states and everything tied to them.
        pg_store_country_delete($id) or output_error('Query failed.');

        log_activity(lang(array('string' => 'country ({var:1}) was deleted', 'vars' => array($values['name']))), $_SESSION['sessionusername']);

    } else {
        pg_store_country_save($id, array_merge($values, array(
            'transit_adjustment_days' => pg_country_form_transit_days($values['transit_adjustment_days']),
        )), (int) $user['id']) or output_error('Query failed.');

        log_activity(lang(array('string' => 'country ({var:1}) was modified', 'vars' => array($values['name']))), $_SESSION['sessionusername']);
    }

    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_countries.php');
}
