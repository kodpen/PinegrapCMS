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

$output_breadcrumb_link = '<li class="breadcrumb-item"><a class="pg-link-secondary " href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_referral_sources.php">' . lang('All Referral Sources') . '</a></li>';

if (!$_POST) {
    // How many sources checkout already lists, so the new sort order has a context.
    $other_sources = number_format((int) db_value("SELECT COUNT(*) FROM referral_sources"));

    $output =
        pg_page_shell(
            array(
                'title'=> lang('Create Referral Source'),
                'extra classes'=>'setting',
                'icon'=>'setting',
                'heading'=>lang('Create Referral Source'),
                'heading_description' => lang('Create a new referral source to gather marketing information during checkout. (Delete all will hide feature.)'),
                'cancel'=>array('enable'=>'true','url'=>'view_referral_sources.php'),
                'breadcrumb' => array(
                    array('label' => lang('All Referral Sources'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_referral_sources.php'),
                    array('label' => lang('Create Referral Source')),
                ),
            )
        ) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="add_referral_source.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" id="send_to" name="send_to" value="' . (isset($_REQUEST['send_to']) ? h($_REQUEST['send_to']) : '') . '" />
                        <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />
                        ' . pg_referral_source_form_layout(
                            pg_referral_source_form_card(null)
                            . pg_referral_source_form_status(null, pg_referral_source_form_status_row(lang('Other Referral Sources'), $other_sources)),
                            '
                                        <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
                        ) . '
                    </form>
                </div>
            </div>
        
</main>' .
        output_footer();

    print $output;

} else {
    validate_token_field();
    
    $values = pg_referral_source_form_values($_POST);
    $others = pg_store_referral_source_others(null);
    if ($others === false) {
        output_error('Query failed.');
    }
    $error = pg_referral_source_form_error($values, $others);
    if ($error !== '') {
        output_error($error . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }
    $referral_source_id = pg_store_referral_source_save(0, $values, $user['id']);
    if ($referral_source_id <= 0) {
        output_error('Query failed.');
    }

    log_activity(lang(array('string'=>'referral source ({var:1}) was created','vars'=>array($values['name']) )) , $_SESSION['sessionusername']);

    include_once('liveform.class.php');
    $liveform_view = new liveform('view_referral_sources');
    $liveform_view->add_notice(lang('The referral source has been created.'));

    // forward user to view referral sources page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_referral_sources.php');
}
?>