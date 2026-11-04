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
require_once(PG_FUNCTIONS_DIR . '/includes/forms/referral_source_form.php');
$user = validate_user();
validate_ecommerce_access($user);

// Stop early when the screen is opened without an existing record (stale
// link, hand-typed URL, row deleted meanwhile): a "not found" screen instead
// of a half-empty form and PHP notices from the unchecked row below.
$id = is_scalar($_REQUEST['id'] ?? null) ? (int) $_REQUEST['id'] : 0;
if (($id < 1) || ((int) db_value("SELECT COUNT(*) FROM referral_sources WHERE id = '" . $id . "'") < 1)) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_referral_sources.php">' . lang('All Referral Sources') . '</a></li>';


if (!$_POST) {
    // get referral source data
    $query = "SELECT referral_sources.name, referral_sources.code, referral_sources.sort_order, referral_sources.timestamp, user.user_username
        FROM referral_sources
        LEFT JOIN user ON referral_sources.user = user.user_id
        WHERE referral_sources.id = ?";
    $result = db_q($query, array((int) $id)) or output_error('Query failed.');
    $row = mysqli_fetch_assoc($result);
    $values = array('name' => $row['name'], 'code' => $row['code'], 'sort_order' => $row['sort_order']);
    // A source stored before the column was written has no time: show a dash.
    $last_modified = ((int) $row['timestamp'] > 0)
        ? get_relative_time(array('timestamp' => (int) $row['timestamp'])) . ((string) $row['user_username'] !== '' ? ' ' . h($row['user_username']) : '')
        : '&mdash;';

    $output =
        pg_page_shell(
            array(
                'title'=> lang('Edit Referral Source'),
                'extra classes'=>'setting',
                'icon'=>'setting',
                'heading'=>lang('Edit Referral Source'),
                'heading_description' => lang('Edit a referral source to gather marketing information during checkout. (Delete all will hide feature.)'),
                'cancel'=>array('enable'=>'true','url'=>'view_referral_sources.php'),
                'breadcrumb' => array(
                    array('label' => lang('All Referral Sources'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_referral_sources.php'),
                    array('label' => lang('Edit Referral Source')),
                ),
            )
        ) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="edit_referral_source.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                        <input type="hidden" name="id" value="' . $id . '">
                        ' . pg_referral_source_form_layout(
                            pg_referral_source_form_card($values)
                            . pg_referral_source_form_status($values, pg_referral_source_form_status_row(lang('Last Modified'), $last_modified)),
                            '
                                        <button type="submit" id="submit_save" name="submit_save" value="Save" class="btn my-1  btn-solid theme-success "><span class="material-icons me-3">save</span><span class="pg-btn-label">' . lang(array('string'=>'Save') ) . '</span></button>
                                        <button type="submit" name="submit_delete" value="Delete" formnovalidate class="btn my-1  btn-solid theme-danger " data-confirm-content="' . lang(array('string'=>'WARNING: This {var:1} will be permanently deleted.','vars'=>array(lang('referral source')))) . '"><span class="material-icons me-3">delete</span><span class="pg-btn-label" >' . lang(array('string'=>'Delete') ) . '</span></button>'
                        ) . '
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();

    print $output;

} else {
    validate_token_field();

    include_once('liveform.class.php');
    $liveform_view = new liveform('view_referral_sources');
    $stored_name = (string) db_value("SELECT name FROM referral_sources WHERE id = '" . $id . "'");

    // if referral source was selected for delete
    if (($_POST['submit_delete'] ?? '') == 'Delete') {
        pg_store_referral_source_delete($id) or output_error('Query failed.');
        log_activity(lang(array('string'=>'referral source ({var:1}) was deleted','vars'=>array($stored_name) )) , $_SESSION['sessionusername']);
        $liveform_view->add_notice(lang('The referral source has been deleted.'));
    // else referral source was not selected for delete
    } else {
        $values = pg_referral_source_form_values($_POST);
        $others = pg_store_referral_source_others($id);
        if ($others === false) {
            output_error('Query failed.');
        }
        $error = pg_referral_source_form_error($values, $others);
        if ($error !== '') {
            output_error($error . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }
        pg_store_referral_source_save($id, $values, $user['id']) or output_error('Query failed.');
        log_activity(lang(array('string'=>'referral source ({var:1}) was modified','vars'=>array($values['name']) )) , $_SESSION['sessionusername']);
        $liveform_view->add_notice(lang('The referral source has been saved.'));
    }

    // forward user to view referral sources screen
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_referral_sources.php');
}
?>