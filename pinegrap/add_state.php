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
$user = validate_user();
validate_ecommerce_access($user);

if (!$_POST) {
    $output =
        pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('State'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('State'))),
        'heading_description' => lang('Add a new state/province that can be included in any shipping zone or tax zone.'),
        'cancel'=>array('enable'=>'true','url'=>'view_states.php')
    ,
            'breadcrumb' => array(array('label' => lang('All States'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_states.php'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('State'))))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="add_state.php" method="post">
                        ' . get_token_field() . '
                        ' . pg_state_form_card(null) . '
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="data edit buttons ">
                            <div class="container">
                                <div class=" btn-group flex-wrap justify-content-center">
                                    <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-solid theme-success "><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>
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
    
    // create state
    $query = "INSERT INTO states (
                name,
                code,
                country_id,
                user,
                timestamp)
            VALUES (
                ?,
                ?,
                ?,
                ?,
                UNIX_TIMESTAMP())";
    $values = pg_state_form_values($_POST);
    $result = db_q($query, array($values['name'], $values['code'], $values['country_id'], (int) $user['id'])) or output_error('Query failed.');
    log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('state'),$_POST['name']))), $_SESSION['sessionusername']);

    // forward user to view states page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_states.php');
}
?>