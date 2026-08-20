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
include_once('liveform.class.php');
$user = validate_user();
validate_area_access($user, 'designer');

$liveform = new liveform('add_theme_file');

if (!$_POST) {

    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Theme'))),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Theme'))),
        'cancel'=>array('enable'=>'true','url'=>'view_themes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Themes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_themes.php?filter=all_designer_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Theme'))))),
        ]) . '
            <div class="row">
            <div class="col-12">
            
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                <div class="row mb-2  flex-wrap">
                    <div class="col-12 col-sm-12 text-center text-md-start">
<h2 class="d-inline-block text-break header-content-for-add-page" data-bs-content="' . lang('Create a new theme (.css file) and place it in a public folder.') . '" title="' . lang(array('string'=>'Create {var:1}','vars'=>lang('Theme'))) . '">[' . lang(array('string'=>'new {var:1}','vars'=>lang('theme'))) . ']</h2>
                    </div>
                </div>
                <form enctype="multipart/form-data" name="form" action="add_theme_file.php" method="post" name="form">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>(isset($_GET['id']) ? $_GET['id'] : ''))) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'id'=>'file_upload_field', 'name'=>'file_upload_field')) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'overwrite')) . '
                    <div class="row">
                        <div class="col-12">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <h5 class="form-label">' . lang('What type of Theme do you want to create?') . '</h5>
                                        </div>
                                        <div class="col-12 my-3">
                                            <fieldset class="form-check" disabled>
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'system', 'name'=>'theme_type', 'value'=>'system', 'class'=>'form-check-input collapse-switcher disabled', 'data-bs-target'=>'#system_theme_row')) . '
                                                <label class="form-check-label" for="system">' . lang('System') . '</label> 
                                                <label class="form-text d-block text-muted" for="system">' . lang('use the Theme Designer; good for adaptive page design') . '</label> 
                                            </fieldset>
                                            <div class="form-check">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'custom', 'name'=>'theme_type', 'value'=>'custom', 'class'=>'form-check-input collapse-switcher', 'checked'=>'checked', 'data-bs-target'=>'#custom_theme_row')) . '
                                                <label class="form-check-label" for="custom">' . lang('Custom') . '</label>
                                                <label class="form-text d-block text-muted" for="custom">' . lang('upload your own CSS file; good for responsive page design') . '</label> 
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="system_theme_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <h5 class="form-label">' . lang('How would you like to create the System Theme?') . '</h5>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-check">
                                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'new_theme', 'name'=>'create_system_theme_option', 'value'=>'new', 'class'=>'form-check-input collapse-switcher', 'checked'=>'checked', 'data-bs-target'=>'#system_theme_create_option_new_theme_row')) . '
                                                                <label class="form-check-label" for="new_theme">' . lang('Create a new System Theme with the Theme Designer') . '</label>
                                                            </div>
                                                            <div>
                                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'import_theme', 'name'=>'create_system_theme_option', 'value'=>'import', 'class'=>'form-check-input collapse-switcher', 'data-bs-target'=>'#system_theme_create_option_import_theme_row')) . '
                                                                <label class="form-check-label" for="import_theme">' . lang('Import a System Theme') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="system_theme_create_option_new_theme_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <label class="form-label" for="name">' . lang('New Theme File Name') . '</label>
                                                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'name', 'class'=>'form-control', 'maxlength'=>'100')) . '
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="system_theme_create_option_import_theme_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <label class="form-label" for="name">' . lang('Local CSV Theme File') . '</label>
                                                                            <input name="system_theme_csv_file" id="system_theme_csv_file" type="file" class="form-control">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-2" id="custom_theme_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <div class="col-12 my-1">
                                                                <label class="form-label" for="name">' . lang('Local CSS Theme File') . '</label>
                                                                <input name="custom_css_file" id="custom_css_file" type="file" class="form-control">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>         
                                </div>         
                            </div>         
                        </div>            
                        <div class="col-12 col-md-6">
                            <div class="card my-4">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Theme File Access Control') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-2">
                                        <label for="folder" class="form-label">' . lang('Folder') . '</label>
                                        <select class="form-select" id="folder" name="folder">' . select_folder(isset($_GET['id']) ? $_GET['id'] : '', 0) . '</select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="card my-4 ">
                                <div class="card-header bg-reset border-0 text-uppercase h5 text-primary fw-bold">
                                    ' . lang('Description') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-2">
                                        <label for="description" class="form-label">' . lang('File Description') . '</label>
                                        ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'description', 'id'=>'description', 'rows'=>'3', 'class'=>'form-control')) . '
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <nav class="buttons navigation text-center position-sticky mb-4" style="bottom:.5rem;" aria-label="data edit buttons ">
                        <div class="container">
                            <div class=" btn-group flex-wrap justify-content-center">
                                <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1  btn-success " data-loading-content="' . lang(array('string'=>'Creating') ) . '"><span class="bi bi-plus-circle me-2"></span><span class="btn-text">' . lang(array('string'=>'Create') ) . '</span></button>
                            </div>
                        </div>
                    </nav>
                </form>
                <script>
                
                    $("input.form-check-input").on("click", function() {
                        if($("input#system").prop("checked") == true && $("input#new_theme").prop("checked") == true){
                           
                            $("#create_button").value= "Create & Continue";
                            $("#create_button .btn-text").text("' . lang('Create & Continue') . '");
                        }else{
                            $("#create_button").val("Create");
                            $("#create_button .btn-text").text("' . lang('Create') . '");
                        } 
                    });
                </script>
            </div>
        </div>
    </main>' .
    output_footer();
    
    $liveform->remove_form('add_theme_file');
    
} else {
    validate_token_field();
    
    $liveform->add_fields_to_session();
    
    // validate the theme type field
    $liveform->validate_required_field('theme_type', lang(array('string'=>'{var:1} is required.','vars'=>lang('A Theme Type') )) );
    
    // if there is an error, forward user back to add theme file screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
        exit();
    }
    
    // if the theme type was "system", then create a new system theme file
    if ($liveform->get_field_value('theme_type') == 'system') {
        // if the user didn't select a method, then output an error
        if ($liveform->get_field_value('create_system_theme_option') == '') {
            $liveform->mark_error('create_system_theme_option', lang('Please specify how you would like to create the new Theme.'));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
            exit();
        }
        
        // if the user selected to create a new system theme, then create a new one
        if ($liveform->get_field_value('create_system_theme_option') == 'new') {
            // require a theme name
            $liveform->validate_required_field('name', lang('Name is required.'));
            
            // if there is an error, forward user back to add theme file screen
            if ($liveform->check_form_errors() == true) {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
                exit();
            }
            
            $file_name = $liveform->get_field_value('name');

            // if the theme name does not end with .css, then add .css to the end of the name
            if (mb_strtolower(mb_substr($file_name, -4)) != '.css') {
                $file_name = $file_name . '.css';
            }

            $file_name = prepare_file_name($file_name);

            // Update file name in liveform.
            $liveform->assign_field_value('name', $file_name);

            if (check_name_availability(array('name' => $file_name)) == false) {
                $liveform->mark_error('name', lang(array('string'=>'The Theme ({var:1}) already exists.','vars'=>$file_name)) );
            }
            
            // if there is an error, forward user back to add theme file screen
            if ($liveform->check_form_errors() == true) {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
                exit();
            }
            
            // insert file data into files table
            $query =
                "INSERT INTO files (
                    name,
                    folder,
                    description,
                    type,
                    size,
                    user,
                    design,
                    theme,
                    timestamp)
                VALUES (
                    '" . escape($file_name) . "',
                    '" . escape($liveform->get_field_value('folder')) . "',
                    '" . escape($liveform->get_field_value('description')) . "',
                    'css',
                    '0',
                    '$user[id]',
                    '1',
                    '1',
                    UNIX_TIMESTAMP())";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $theme_id = mysqli_insert_id(db::$con);
            
            // insert the css template into the database
            $query =
                "INSERT INTO system_theme_css_rules 
                    (file_id, area, `row`, col, module, region_type, region_name, property, value) 
                VALUES

($theme_id,'','0','0','menu_item','menu','main-menu','padding_bottom','.5em'),
($theme_id,'site_wide','0','0','text','','','font_color','000000'),
($theme_id,'site_header','0','0','text','','','font_color','000000'),
($theme_id,'site_wide','0','0','primary_buttons','','','font_color','FFFFFF'),
($theme_id,'','0','0','submenu_menu_item','menu','main-menu','width','150px'),
($theme_id,'','0','0','layout','menu','main-menu','position','center'),
($theme_id,'','0','0','layout','menu','main-menu','menu_orientation','horizontal'),
($theme_id,'','0','0','layout','menu','mobile-menu','position','left'),
($theme_id,'','0','0','layout','menu','mobile-menu','menu_orientation','vertical'),
($theme_id,'','0','0','menu','ad','home-ad-region','position','bottom_right'),
($theme_id,'','0','0','layout','ad','home-ad-region','height','450px'),
($theme_id,'','0','0','layout','ad','home-ad-region','width','960px'),
($theme_id,'site_border','0','0','','','','width','960px'),
($theme_id,'site_border','0','0','','','','position','center'),
($theme_id,'email_border','0','0','','','','width','700px'),
($theme_id,'email_border','0','0','','','','position','center'),
($theme_id,'','0','0','menu_item','menu','main-menu','padding_top','.5em'),
($theme_id,'','0','0','menu_item','menu','main-menu','padding_left','.5em'),
($theme_id,'','0','0','menu_item','menu','main-menu','padding_right','.5em'),
($theme_id,'site_wide','0','0','','','','secondary_color','DDDDDD'),
($theme_id,'site_wide','0','0','','','','primary_color','000000'),
($theme_id,'site_wide',0,0,'','','','pre_styling','/* normalize.css (minified) */
article,aside,details,figcaption,figure,footer,header,hgroup,nav,section,summary{display:block}audio,canvas,video{display:inline-block;*display:inline;*zoom:1}audio:not([controls]){display:none}[hidden]{display:none}html{font-size:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}html,button,input,select,textarea{font-family:sans-serif}body{margin:0}a:focus{outline:thin dotted}a:hover,a:active{outline:0}h1{font-size:2em;margin:0.67em 0}h2{font-size:1.5em;margin:0.83em 0}h3{font-size:1.17em;margin:1em 0}h4{font-size:1em;margin:1.33em 0}h5{font-size:0.83em;margin:1.67em 0}h6{font-size:0.75em;margin:2.33em 0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:bold}blockquote{margin:1em 40px}dfn{font-style:italic}mark{background:#ff0;color:#000}p,pre{margin:1em 0}pre,code,kbd,samp{font-family:monospace, serif;_font-family:\'courier new\', monospace;font-size:1em}pre{white-space:pre;white-space:pre-wrap;word-wrap:break-word}q{quotes:none}q:before,q:after{content:\'\';content:none}small{font-size:75%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-0.5em}sub{bottom:-0.25em}dl,menu,ol,ul{margin:1em 0}dd{margin:0 0 0 40px}menu,ol,ul{padding:0 0 0 40px}nav ul,nav ol{list-style:none;list-style-image:none}img{border:0;-ms-interpolation-mode:bicubic}svg:not(:root){overflow:hidden}figure{margin:0}form{margin:0}fieldset{border:1px solid #c0c0c0;margin:0 2px;padding:0.35em 0.625em 0.75em}legend{border:0;padding:0;white-space:normal;*margin-left:-7px}button,input,select,textarea{font-size:100%;margin:0;vertical-align:baseline;*vertical-align:middle}button,input{line-height:normal}button,input[type=\"\"button\"\"],input[type=\"\"reset\"\"],input[type=\"\"submit\"\"]{cursor:pointer;-webkit-appearance:button;*overflow:visible}button[disabled],input[disabled]{cursor:default}input[type=\"\"checkbox\"\"],input[type=\"\"radio\"\"]{box-sizing:border-box;padding:0;*height:13px;*width:13px}input[type=\"\"search\"\"]{-webkit-appearance:textfield;-moz-box-sizing:content-box;-webkit-box-sizing:content-box;box-sizing:content-box}input[type=\"\"search\"\"]::-webkit-search-decoration,input[type=\"\"search\"\"]::-webkit-search-cancel-button{-webkit-appearance:none}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}textarea{overflow:auto;vertical-align:top}
'),
($theme_id,'site_wide','0','0','','','','advanced_styling','.theme-name:after {
    content: \'Blank Theme\';
}
/* Added for v8.5 Themes */
.clr:after {clear: both;}
.clr:before,.clr:after {content: \'\';display: table;}
')";

            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // else if the user selected to import a new theme, then import it
        } elseif ($liveform->get_field_value('create_system_theme_option') == 'import') {
            $file_name = prepare_file_name($_FILES['system_theme_csv_file']['name']);

            $file_type = $_FILES['system_theme_csv_file']['type'];
            $file_size = $_FILES['system_theme_csv_file']['size'];
            $file_temp_name = $_FILES['system_theme_csv_file']['tmp_name'];
            $array_file_extension = explode('.', $file_name);
            $size_of_array = count($array_file_extension);
            $file_extension = $array_file_extension[$size_of_array - 1];
            
            // if there is not a file name, then add an error to the liveform
            if ($file_name == '') {
                $liveform->mark_error('system_theme_csv_file', lang(array('string'=>'{var:1} is required.','vars'=>lang('File'))) );
            }
            
            // if there are not any errors already, and if the file extension is not .csv, then output an error
            if (($liveform->check_form_errors() == false) && (mb_strtolower(mb_substr($file_name, mb_strrpos($file_name, '.'))) != '.csv')) {
                $liveform->mark_error('system_theme_csv_file', lang('The file must be a CSV file.'));
            }
            
            // if there is an error, forward user back to the add theme file screen
            if ($liveform->check_form_errors() == true) {
                header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
                exit();
            }
            
            // get file handle for uploaded CSV file
            $handle = fopen($_FILES['system_theme_csv_file']['tmp_name'], "r");
            
            // get column names from first row of CSV file
            $columns = fgetcsv($handle, 100000, ",");
            
            // if file is empty
            if (!$columns) {
                output_error(lang('The file was empty') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
            
            // loop through the headings to verify that the columns are not blank, or not valid
            foreach ($columns as $key => $value) {
                // if the column is invalid, remove from column list
                if ($value === FALSE) {
                    unset($columns[$key]);
                
                // else if there is value, then verify that the heading is one of the correct headings,
                // if it isn't, then output an error
                } else {
                    if (preg_match('/area|row|col|module|region_type|region_name|property|value/i', $value) == 0) {
                        output_error(lang('The file is not a valid System Theme CSV file') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '' . lang('Go back') . '</a>.');
                    }
                }
            }
            
            // remove the .csv extension from the name and add .css so that we can use it as the new theme name
            $file_name = mb_strtolower(mb_substr($file_name, 0, mb_strrpos($file_name, '.'))) . '.css';

            if (check_name_availability(array('name' => $file_name)) == false) {
                output_error(lang(array('string'=>'{var:1} already exists','vars'=>h($file_name))). '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
            
            // insert file data into files table
            $query =
                "INSERT INTO files (
                    name,
                    folder,
                    description,
                    type,
                    size,
                    user,
                    design,
                    theme,
                    timestamp)
                VALUES (
                    '" . escape($file_name) . "',
                    '" . escape($liveform->get_field_value('folder')) . "',
                    '" . escape($liveform->get_field_value('description')) . "',
                    'css',
                    '0',
                    '$user[id]',
                    '1',
                    '1',
                    UNIX_TIMESTAMP())";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            $theme_id = mysqli_insert_id(db::$con);
            
            $data_list = '';
            
            // loop through the columns to build the sql columns to insert into string
            foreach ($columns as $key => $value) {
                if ($data_list != '') {
                    $data_list .= ', ';
                }
                
                // prepare value and add it to the data list
                $value = escape($value);
                $data_list .= "$value";
            }
            
            $system_theme_properties_for_sql = '';
            
            // loop through the rows in the csv file to create an sql statement that will insert the properties
            while ($row = fgetcsv($handle, 100000, ",")) {
                if ($system_theme_properties_for_sql != '') {
                    $system_theme_properties_for_sql .= ',';
                }
                
                $system_theme_properties_for_sql .= "('$theme_id', '" . escape($row[0]) . "', '" . escape($row[1]) . "', '" . escape($row[2]) . "', '" . escape($row[3]) . "', '" . escape($row[4]) . "', '" . escape($row[5]) . "', '" . escape($row[6]) . "', '" . escape($row[7]) . "')";
            }
            
            // insert the css properties into the database
            $query =
                "INSERT INTO system_theme_css_rules 
                    (file_id, $data_list)
                VALUES " . $system_theme_properties_for_sql;
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            // close the csv file
            fclose($handle);
        }
        
        /* Generate the CSS file */
        
        $css_properties = array();
            
        // get the new CSS properties from the database and store them into any array so we can send them to the generate system theme css function
        $query = 
            "SELECT
                area,
                `row`, # Backticks for reserved word.
                col,
                module,
                property,
                value,
                region_type,
                region_name
            FROM system_theme_css_rules 
            WHERE file_id = '" . escape($theme_id) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        while($row = mysqli_fetch_array($result)) {
            // if this is an ad region, then set the properties in the ad regions area of the array
            if ($row['region_type'] == 'ad') {
                $css_properties['ad_region'][$row['region_name']][$row['module']][$row['property']] = $row['value'];
            
            // else if this is a menu region, then set the properties in the menu regions area of the array
            } elseif ($row['region_type'] == 'menu') {
                $css_properties['menu_region'][$row['region_name']][$row['module']][$row['property']] = $row['value'];
                
            } else {

                // if there is a row then output the object
                if ($row['row'] != 0) {
                    $object = 'r' . $row['row'] . 'c' . $row['col'];
                    
                // else set the object as the base object
                } else {
                    $object = 'base_object';
                }
                
                // if the module is not blank, then set the module
                if ($row['module'] != '') {
                    $module = $row['module'];
                    
                // else set the module to the base module
                } else {
                    $module = 'base_module';
                }
                
                // add the property to the css properties array
                $css_properties[$row['area']][$object][$module][$row['property']] = $row['value'];
            }
        }
        
        // generate the css based on the properties we just inserted
        $handle = fopen(FILE_DIRECTORY_PATH . '/' . $file_name, 'w');

        require_once(dirname(__FILE__) . '/generate_system_theme_css.php');

        fwrite($handle, generate_system_theme_css($css_properties));
        fclose($handle);
        
        // update the file size now that we have a file
        $query = "UPDATE files SET size = '" . escape(filesize(FILE_DIRECTORY_PATH . '/' . $file_name)) . "' WHERE id = '" . $theme_id . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if a new theme was created, then log the activity, output a message and then send the user to the theme designer
        if ($liveform->get_field_value('create_system_theme_option') == 'new') {
            // log the activity
            log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('theme'),$file_name))), $_SESSION['sessionusername']);
            // clear the liveform
            $liveform->remove_form();
            // add liveform notice to theme designer
            $liveform_theme_designer = new liveform('theme_designer');
            $liveform_theme_designer->add_notice(lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Theme') )));
            
            // send the user to the theme designer
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/theme_designer.php?id=' . $theme_id . '&clear_theme_designer_session=true');
        
        // else a theme was imported, so log the activity, output a message and then send the user to the view themes screen
        } else {
            // if overwrite is true, then log the appropriate notice
            if ($overwrite == true) {
                log_activity(lang(array('string'=>'theme file ({var:1}) was overwritten','vars'=>$file_name)), $_SESSION['sessionusername']);
            } else {
                log_activity(lang(array('string'=>'theme file ({var:1}) was imported','vars'=>$file_name)), $_SESSION['sessionusername']);
            }
            // clear the liveform
            $liveform->remove_form();
            // output a notice to the view themes screen
            $liveform_view_themes = new liveform('view_themes');
            $liveform_view_themes->add_notice(lang(array('string'=>'The theme file, {var:1}, has been imported.','vars'=>'<a href="' . OUTPUT_PATH . h($file_name) . '" target="_blank">' . h($file_name) . '</a>')) );
            
            // send the user to the view themes screen
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_themes.php');
        }
        
        
        
        exit();
        
    // else upload and create a custom theme file
    } else {
        $file_name = prepare_file_name($_FILES['custom_css_file']['name']);

        $file_type = $_FILES['custom_css_file']['type'];
        $file_size = $_FILES['custom_css_file']['size'];
        $file_temp_name = $_FILES['custom_css_file']['tmp_name'];
        $array_file_extension = explode('.', $file_name);
        $size_of_array = count($array_file_extension);
        $file_extension = $array_file_extension[$size_of_array - 1];
        
        // if there is not a file name, then add an error to the liveform
        if ($file_name == '') {
            $liveform->mark_error('custom_css_file', 'File is required.');
        }
        
        // if there are not any errors, and if this is not a css file or if it is an htaccess file, then add an error to the liveform
        if (
            ($liveform->check_form_errors() == false) 
            && 
            (
                (mb_strtolower(mb_substr($file_name, mb_strrpos($file_name, '.'))) != '.css')
                || ($file_name == '.htaccess')
            )
        ) {
            $liveform->mark_error('custom_css_file', lang('The file must be a CSS file.'));
        }
        
        // if there is an error, forward user back to the add theme file screen
        if ($liveform->check_form_errors() == true) {
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
            exit();
        }
        
        if (check_name_availability(array('name' => $file_name)) == false) {
            output_error(lang(array('string'=>'{var:1} already exists','vars'=>h($file_name))). '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
        }

        // create file
        copy($file_temp_name, FILE_DIRECTORY_PATH . '/' . $file_name) or output_error(lang('Copy did not work.'));

        // insert file data into file table
        $query =
            "INSERT INTO files (
                name,
                folder,
                description,
                type,
                size,
                user,
                design,
                theme,
                timestamp)
            VALUES (
                '" . escape($file_name) . "',
                '" . escape($liveform->get_field_value('folder')) . "',
                '" . escape($liveform->get_field_value('description')) . "',
                '" . escape($file_extension) . "',
                '" . escape($file_size) . "',
                '$user[id]',
                '1',
                '1',
                UNIX_TIMESTAMP())";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        
        // if overwrite is true, then log the appropriate notice
        if ($overwrite == true) {
            log_activity(lang(array('string'=>'theme file ({var:1}) was overwritten','vars'=>$file_name)), $_SESSION['sessionusername']);
        } else {
            log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('theme'),$file_name))), $_SESSION['sessionusername']);
        }
        // clear the liveform
        $liveform->remove_form();
        // output a notice to the view themes screen
        $liveform_view_themes = new liveform('view_themes');
        $liveform_view_themes->add_notice(lang(array('string'=>'The theme file, {var:1}, has been uploaded.','vars'=>'<a href="' . OUTPUT_PATH . h($file_name) . '" target="_blank">' . h($file_name) . '</a>')) );

        
        // send the user to the view themes screen
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_themes.php');
        

    }
}
?>
