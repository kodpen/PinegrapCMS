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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();
validate_area_access($user, 'designer');

// If the form was not just submitted, then continue to output the page.
if (!$_POST) {
    
    $output_header = pg_page_shell([
        'title'=> lang('Create Custom Page Style'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Create Custom Page Style'),
        'cancel'=>array('enable'=>'true','url'=>'view_styles.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Page Styles'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php'), array('label' => lang('Create Custom Page Style'))),
        ]);
    
    $name = '';
        
    $code =
'<!DOCTYPE html>
<html lang="' . lang(array('info'=>'')) . '">
    <head>
        <meta charset="utf-8">
        <title></title>
        <meta_tags></meta_tags>
        <stylesheet></stylesheet>
    </head>
    <body>
        <pregion></pregion>
        <system></system>
        <pregion></pregion>
    </body>
</html>';
    

    $output_social_networking_position = '';

    // If social networking is enabled, then output position pick list.
    if (SOCIAL_NETWORKING == TRUE) {
        $output_social_networking_position =
            '<div class="col-12 col-md-6 col-lg-4 my-2">
                <label for="social_networking_position" class="form-label">' . lang('Social Networking Position') . '</label>
                <select id="social_networking_position" name="social_networking_position" class="form-select">
                    <option value="top_left">' . lang('Top Left') . '</option>
                    <option value="top_right">' . lang('Top Right') . '</option>
                    <option value="bottom_left" selected="selected">' . lang('Bottom Left') . '</option>
                    <option value="bottom_right">' . lang('Bottom Right') . '</option>
                    <option value="disabled">' . lang('Disabled') . '</option>
                </select>
            </div>';
    }
   
    print
    $output_header . '
            <div class="row">
            <div class="col-12">
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new HTML template that can be associated with one or many Pages.') . '" title="' . lang('Create Custom Page Style') . '">[' . lang('new page style') . ']</h2>
                    </div>
                </div>
                <form name="form" action="add_custom_style.php" method="post">
                    ' . get_token_field() . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Page Style Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 col-md-8 col-lg-6 my-2">
                                            <label for="name" class="form-label">' . lang('Name') . '</label>
                                            <input value="' . h($name) . '" name="name" id="name" type="text" placeholder="' . lang('new page style') . '" class="form-control add-header-content-updater" maxlength="100" required />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="col-12 my-2">
                                            <label class="form-label">' . lang('HTML Page with embedded Tags') . '</label>
                                            <div id="edit_custom">
                                                <textarea name="code" id="code" rows="25" cols="60" wrap="off">' . h($code) . '</textarea>
                                                ' . get_codemirror_includes() . '
                                                ' . get_codemirror_javascript(array('id' => 'code', 'code_type' => 'mixed')) . '
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Additional Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 mt-2">
                                            <h5>'. lang('Collection') . '</h5>
                                        </div>
                                        <div class="col-12 mb-2">
                                            <div class="form-check form-check-inline">
                                                <input type="radio" name="collection" id="collection_a" value="a" checked="checked" class="form-check-input" />
                                                <label class="form-check-label" for="collection_a">' . lang('Collection') . ' A</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input type="radio" name="collection" id="collection_b" value="b" class="form-check-input" />
                                                <label class="form-check-label" for="collection_b">' . lang('Collection') . ' B</label>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6 col-lg-4 my-2">
                                            <label for="layout_type" class="form-label">' . lang('Override Layout Type') . '</label>
                                            <select id="layout_type" name="layout_type" class="form-select">
                                                <option value=""></option>
                                                <option value="system">' . lang('System') . '</option>
                                                <option value="custom">' . lang('Custom') . '</option>
                                            </select>
                                        </div>
                                        ' . $output_social_networking_position . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " ><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
            </div>
        </div>
    </main>' .
    output_footer();

// else save the page style
} else {
    validate_token_field();
    
    $name = trim($_POST['name']);

    $sql_field_social_networking_position = "";
    $sql_value_social_networking_position = "";

    // If social networking is enabled, then update position value.
    if (SOCIAL_NETWORKING == TRUE) {
        $sql_field_social_networking_position = "social_networking_position,";
        $sql_value_social_networking_position = "'" . escape($_POST['social_networking_position'] ?? '') . "',";
    }
    
    // insert row into style table
    $result=mysqli_query(db::$con, "INSERT INTO style (style_name, style_code, " . $sql_field_social_networking_position . "collection, layout_type, style_timestamp, style_user) VALUES ('" . escape($name) . "', '" . escape($_POST['code'] ?? '') . "', " . $sql_value_social_networking_position . "'" . escape($_POST['collection'] ?? '') . "', '" . e($_POST['layout_type'] ?? '') . "', UNIX_TIMESTAMP(), '$user[id]')") or output_error('Query failed');

    log_activity(lang(array('string'=>'style ({var:1}) was created','vars'=>$name)), $_SESSION['sessionusername']);
    include_once('liveform.class.php');
    $notice = lang('The style was created successfully.');
    $liveform_view_styles = new liveform('view_styles');
    $liveform_view_styles->add_notice($notice);
    header('Location: ' . URL_SCHEME . $_SERVER['HTTP_HOST'] . PATH . SOFTWARE_DIRECTORY . '/view_styles.php');
}
?>