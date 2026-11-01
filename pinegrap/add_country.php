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

// A post with a blank name or code, or a code another country has, draws
// the form again with what was typed and the message under the field;
// nothing is written.
$values = null;
$errors = array();

if ($_POST) {
    validate_token_field();

    $values = pg_country_form_values($_POST);
    $errors = pg_country_form_required_errors($values);
    $code_error = pg_country_form_code_error($values['code'], null, null);

    if (($code_error !== '') && !isset($errors['code'])) {
        $errors['code'] = $code_error;
    }
}

if (!$_POST || $errors) {
    $output =
        pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Country'))),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Country'))),
        'heading_description' => lang('Add a new country that can be included in any shipping zone or tax zone.'),
        'cancel'=>array('enable'=>'true','url'=>'view_countries.php'),
            'breadcrumb' => array(array('label' => lang('All Countries'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_countries.php'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Country'))))),
        ]) . '
<main id="content" class="container-fluid">
                    <div class="row">
                <div class="col-12">
                    
                    <form name="form" action="add_country.php" method="post">
                        ' . get_token_field() . '
                        ' . pg_country_form_card($values, $errors) . '
                        <input type="hidden" id="submitted_button_field" name="submitted_button_field" value="submit" />          
                        <nav class="buttons navigation text-center position-sticky mb-7" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
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

    echo $output;

} else {
    // create country
    $query = "INSERT INTO countries (
                name,
                code,
                zip_code_required,
                transit_adjustment_days,
                default_selected,
                user,
                timestamp)
            VALUES (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                UNIX_TIMESTAMP())";
    $result = db_q($query, array(
        $values['name'],
        $values['code'],
        $values['zip_code_required'],
        $values['transit_adjustment_days'],
        $values['default_selected'],
        (string) $user['id'])) or output_error('Query failed.');

    $country_id = mysqli_insert_id(db::$con);

    // if default selected was checked, then turn value off for all other countries
    if ($values['default_selected'] == 1) {
        // update country
        $query = "UPDATE countries SET default_selected = 0 WHERE id != ?";
        $result = db_q($query, array((int) $country_id)) or output_error('Query failed.');
    }

    log_activity( lang(array('string'=>'country ({var:1}) was created','vars'=>array($values['name']) )) , $_SESSION['sessionusername']);


    // forward user to view countries page
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_countries.php');
}