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

// A file over post_max_size empties the whole request; say so instead of
// drawing the form again as if nothing had been sent.
pg_upload_refuse_discarded_body();

include_once('liveform.class.php');
$user = validate_user();
validate_area_access($user, 'designer');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/design_file_form.php');
require_once(PG_FUNCTIONS_DIR . '/includes/forms/theme_file_form.php');

$liveform = new liveform('add_theme_file');

if (!$_POST) {
    // The id preselects the folder; one sent as an array (?id[]=x) counts as
    // not given instead of giving an error page.
    $folder_id = (isset($_GET['id']) && is_scalar($_GET['id'])) ? $_GET['id'] : '';

    print
    pg_page_shell([
        'title'=> lang(array('string'=>'Create {var:1}','vars'=>lang('Theme'))),
        'extra classes'=>'design pg-measure-edit',
        'icon'=>'design',
        'heading'=>lang(array('string'=>'Create {var:1}','vars'=>lang('Theme'))),
        'heading_description' => lang('Create a new theme (.css file) and place it in a public folder.'),
        'cancel'=>array('enable'=>'true','url'=>'view_themes.php')
    ,
            'breadcrumb' => array(array('label' => lang('All Themes'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_themes.php?filter=all_designer_regions'), array('label' => lang(array('string'=>'Create {var:1}','vars'=>lang('Theme'))))),
        ]) . '
<main id="content" class="container-fluid">
            <div class="row">
            <div class="col-12">
            
                ' . $liveform->output_errors() . '
                ' . $liveform->output_notices() . '
                
                <form enctype="multipart/form-data" name="form" action="add_theme_file.php" method="post" name="form">
                    ' . get_token_field() . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id', 'value'=>$folder_id)) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'id'=>'file_upload_field', 'name'=>'file_upload_field')) . '
                    ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'overwrite')) . '
                    ' . pg_design_file_form_layout(
                        '
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Options') . '
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12">
                                            <h5 class="form-label">' . lang('What type of Theme do you want to create?') . '</h5>
                                        </div>
                                        <div class="col-12 my-5">
                                            <fieldset class="form-field" disabled>
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'system', 'name'=>'theme_type', 'value'=>'system', 'class'=>'radio collapse-switcher disabled', 'data-bs-target'=>'#system_theme_row')) . '
                                                <label class="" for="system">' . lang('System') . '</label> 
                                                <label class="form-text d-block fg-secondary" for="system">' . lang('use the Theme Designer; good for adaptive page design') . '</label> 
                                            </fieldset>
                                            <div class="form-field">
                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'custom', 'name'=>'theme_type', 'value'=>'custom', 'class'=>'radio collapse-switcher', 'checked'=>'checked', 'data-bs-target'=>'#custom_theme_row')) . '
                                                <label class="" for="custom">' . lang('Custom') . '</label>
                                                <label class="form-text d-block fg-secondary" for="custom">' . lang('upload your own CSS file; good for responsive page design') . '</label> 
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="system_theme_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12">
                                                            <h5 class="form-label">' . lang('How would you like to create the System Theme?') . '</h5>
                                                        </div>
                                                        <div class="col-12 my-1">
                                                            <div class="form-field">
                                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'new_theme', 'name'=>'create_system_theme_option', 'value'=>'new', 'class'=>'radio collapse-switcher', 'checked'=>'checked', 'data-bs-target'=>'#system_theme_create_option_new_theme_row')) . '
                                                                <label class="" for="new_theme">' . lang('Create a new System Theme with the Theme Designer') . '</label>
                                                            </div>
                                                            <div>
                                                                ' . $liveform->output_field(array('type'=>'radio', 'id'=>'import_theme', 'name'=>'create_system_theme_option', 'value'=>'import', 'class'=>'radio collapse-switcher', 'data-bs-target'=>'#system_theme_create_option_import_theme_row')) . '
                                                                <label class="" for="import_theme">' . lang('Import a System Theme') . '</label>
                                                            </div>
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="system_theme_create_option_new_theme_row">
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
                                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="system_theme_create_option_import_theme_row">
                                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                                <div class="popover-body">
                                                                    <div class="row">
                                                                        <div class="col-12 my-1">
                                                                            <label class="form-label" for="system_theme_csv_file">' . lang('Local CSV Theme File') . '</label>
                                                                            <input name="system_theme_csv_file" id="system_theme_csv_file" type="file" class="form-control">
                                                                            ' . pg_upload_limit_hint() . '
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="collapse popover fade bs-popover-bottom p-0 mb-3" id="custom_theme_row">
                                                <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(18px, 0px);"></div>
                                                <div class="popover-body">
                                                    <div class="row">
                                                        <div class="col-12 my-1">
                                                            <div class="col-12 my-1">
                                                                <label class="form-label" for="custom_css_file">' . lang('Local CSS Theme File') . '</label>
                                                                <input name="custom_css_file" id="custom_css_file" type="file" accept=".css,text/css" class="form-control" required>
                                                                ' . pg_upload_limit_hint() . '
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>         
                                </div>
                            </div>
                            <div class="card my-7">
                                <div class="card-header bg-reset border-0 pg-title-section">
                                    ' . lang('Description') . '
                                </div>
                                <div class="card-body">
                                    <div class="col-12 my-3">
                                        <label for="description" class="form-label">' . lang('File Description') . '</label>
                                        ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'description', 'id'=>'description', 'rows'=>'3', 'class'=>'form-control')) . '
                                    </div>
                                </div>
                            </div>',
                        pg_design_file_form_access_card(select_folder($folder_id, 0)),
                        '
                                        <button type="submit" id="create_button" name="submit_create" value="Create" class="btn my-1 btn-solid theme-success"><span class="bi bi-plus-circle me-3"></span><span class="pg-btn-label">' . lang(array('string'=>'Create') ) . '</span></button>'
                    ) . '
                </form>
                <script>
                
                    $("input:is(.check, .radio)").on("click", function() {
                        if($("input#system").prop("checked") == true && $("input#new_theme").prop("checked") == true){
                           
                            $("#create_button").value= "Create & Continue";
                            $("#create_button .pg-btn-label").text("' . lang('Create & Continue') . '");
                        }else{
                            $("#create_button").val("Create");
                            $("#create_button .pg-btn-label").text("' . lang('Create') . '");
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
    
    // System themes are closed on the form (the Theme Designer is off); a
    // hand-made request is refused here too, so it neither writes rules nor
    // builds CSS from a template that lacks most of them.
    if (($liveform->check_form_errors() == false) && ($liveform->get_field_value('theme_type') !== 'custom')) {
        $liveform->mark_error('theme_type', lang('The Theme Type is not valid.'));
    }

    // if there is an error, forward user back to add theme file screen
    if ($liveform->check_form_errors() == true) {
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
        exit();
    }
    
    // Flag posted by the form when an existing theme file is being replaced.
    $overwrite = ($liveform->get_field_value('overwrite') == 'true');

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
                    ?,
                    ?,
                    ?,
                    'css',
                    '0',
                    ?,
                    '1',
                    '1',
                    UNIX_TIMESTAMP())";
            $result = db_q($query, array((string) $file_name, (string) $liveform->get_field_value('folder'), (string) $liveform->get_field_value('description'), (string) $user['id'])) or output_error('Query failed.');
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
article,aside,details,figcaption,figure,footer,header,hgroup,nav,section,summary{display:block}audio,canvas,video{display:inline-block;*display:inline;*zoom:1}audio:not([controls]){display:none}[hidden]{display:none}html{font-size:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}html,button,input,select,textarea{font-family:sans-serif}body{margin:0}a:focus{outline:thin dotted}a:hover,a:active{outline:0}h1{font-size:2em;margin:0.67em 0}h2{font-size:1.5em;margin:0.83em 0}h3{font-size:1.17em;margin:1em 0}h4{font-size:1em;margin:1.33em 0}h5{font-size:0.83em;margin:1.67em 0}h6{font-size:0.75em;margin:2.33em 0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:bold}blockquote{margin:1em 40px}dfn{font-style:italic}mark{background:#ff0;color:#000}p,pre{margin:1em 0}pre,code,kbd,samp{font-family:monospace, serif;_font-family:\'courier new\', monospace;font-size:1em}pre{white-space:pre;white-space:pre-wrap;word-wrap:break-word}q{quotes:none}q:before,q:after{content:\'\';content:none}small{font-size:75%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-0.5em}sub{bottom:-0.25em}dl,menu,ol,ul{margin:1em 0}dd{margin:0 0 0 40px}menu,ol,ul{padding:0 0 0 40px}nav ul,nav ol{list-style:none;list-style-image:none}img{border:0;-ms-interpolation-mode:bicubic}svg:not(:root){overflow:hidden}figure{margin:0}form{margin:0}fieldset{border:1px solid #c0c0c0;margin:0 2px;padding:0.35em 0.625em 0.75em}legend{border:0;padding:0;white-space:normal;*margin-left:-7px}button,input,select,textarea{font-size:100%;margin:0;vertical-align:baseline;*vertical-align:middle}button,input{line-height:normal}button,input[type=\"button\"],input[type=\"reset\"],input[type=\"submit\"]{cursor:pointer;-webkit-appearance:button;*overflow:visible}button[disabled],input[disabled]{cursor:default}input[type=\"checkbox\"],input[type=\"radio\"]{box-sizing:border-box;padding:0;*height:13px;*width:13px}input[type=\"search\"]{-webkit-appearance:textfield;-moz-box-sizing:content-box;-webkit-box-sizing:content-box;box-sizing:content-box}input[type=\"search\"]::-webkit-search-decoration,input[type=\"search\"]::-webkit-search-cancel-button{-webkit-appearance:none}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}textarea{overflow:auto;vertical-align:top}
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
            } else {
                $upload_limits = pg_upload_limits();
                $upload_refusal = pg_upload_file_refusal($_FILES['system_theme_csv_file'], $upload_limits['file_max']);

                if ($upload_refusal !== '') {
                    $liveform->mark_error('system_theme_csv_file', h($upload_refusal));
                }
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
            $columns = fgetcsv($handle, 100000, ",", '"', '\\');
            
            // if file is empty
            if (!$columns) {
                output_error(lang('The file was empty') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
            
            // The heading names the eight rule columns the export writes; the
            // column list is built from those names, never from the cells.
            $rule_columns = pg_theme_csv_import_columns($columns);

            if ($rule_columns === null) {
                output_error(lang('The file is not a valid System Theme CSV file') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }

            // remove the .csv extension from the name and add .css so that we can use it as the new theme name
            $file_name = mb_strtolower(mb_substr($file_name, 0, mb_strrpos($file_name, '.'))) . '.css';

            if (check_name_availability(array('name' => $file_name)) == false) {
                output_error(lang(array('string'=>'{var:1} already exists','vars'=>h($file_name))). '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }

            // The rows are read before anything is written: a file with a
            // heading and no rules is refused, not stored as an empty theme.
            // Each row's values are placed in the heading's order; a blank
            // line is skipped.
            $rule_rows = array();

            while ($row = fgetcsv($handle, 100000, ",", '"', '\\')) {
                if ($row === array(null)) {
                    continue;
                }

                $values = array();

                for ($column = 0; $column < 8; $column++) {
                    $values[] = "'" . escape((string) ($row[$column] ?? '')) . "'";
                }

                $rule_rows[] = implode(', ', $values);
            }

            // close the csv file
            fclose($handle);

            if (!$rule_rows) {
                output_error(lang('The file was empty') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
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
                    ?,
                    ?,
                    ?,
                    'css',
                    '0',
                    ?,
                    '1',
                    '1',
                    UNIX_TIMESTAMP())";
            $result = db_q($query, array((string) $file_name, (string) $liveform->get_field_value('folder'), (string) $liveform->get_field_value('description'), (string) $user['id'])) or output_error('Query failed.');
            $theme_id = mysqli_insert_id(db::$con);

            // Insert the rules. A refused insert takes the record back out,
            // so no theme without rules (and without a file) keeps the name.
            $system_theme_properties_for_sql = '';

            foreach ($rule_rows as $rule_row) {
                $system_theme_properties_for_sql .= (($system_theme_properties_for_sql !== '') ? ', ' : '') . "('" . (int) $theme_id . "', " . $rule_row . ")";
            }

            $query =
                "INSERT INTO system_theme_css_rules
                    (file_id, " . $rule_columns . ")
                VALUES " . $system_theme_properties_for_sql;

            if (!mysqli_query(db::$con, $query)) {
                db_q("DELETE FROM system_theme_css_rules WHERE file_id = ?", array((string) $theme_id));
                db_q("DELETE FROM files WHERE id = ?", array((string) $theme_id));
                output_error(lang('The file is not a valid System Theme CSV file') . '. <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
            }
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
            WHERE file_id = ?";
        $result = db_q($query, array((string) $theme_id)) or output_error('Query failed.');
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
        
        // Generate the CSS from the properties just inserted and write it as
        // a whole. When the file cannot be written, the theme's record and
        // rules are removed again, so no record names a file that is not on
        // the disk, and the user goes back to the form with the error.
        require_once(dirname(__FILE__) . '/generate_system_theme_css.php');

        if (!pg_file_write_whole(FILE_DIRECTORY_PATH . '/' . $file_name, generate_system_theme_css($css_properties))) {
            db_q("DELETE FROM system_theme_css_rules WHERE file_id = ?", array((string) $theme_id));
            db_q("DELETE FROM files WHERE id = ?", array((string) $theme_id));
            $liveform->add_error(lang(array('string' => '{var:1} could not be written to disk.', 'vars' => h($file_name))));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
            exit();
        }
        
        // update the file size now that we have a file
        $query = "UPDATE files SET size = ? WHERE id = ?";
        $result = db_q($query, array((string) filesize(FILE_DIRECTORY_PATH . '/' . $file_name), (string) $theme_id)) or output_error('Query failed.');

        // One create line carries the row with its final size.
        pg_audit_record('create', 'files', (int) $theme_id);
        
        // if a new theme was created, then log the activity, output a message and then send the user to the theme's CSS editor
        if ($liveform->get_field_value('create_system_theme_option') == 'new') {
            // log the activity
            log_activity(lang(array('string'=>'{var:1} ({var:2}) was created','vars'=>array(lang('theme'),$file_name))), $_SESSION['sessionusername']);
            // clear the liveform
            $liveform->remove_form();
            // add liveform notice to the CSS editor
            $liveform_edit_theme_css = new liveform('edit_theme_css');
            $liveform_edit_theme_css->add_notice(lang(array('string'=>'{var:1} was created successfully','vars'=>lang('Theme') )));
            
            // send the user to the theme's CSS editor, which goes back to the theme list
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_theme_css.php?id=' . $theme_id . '&send_to=' . urlencode(PATH . SOFTWARE_DIRECTORY . '/view_themes.php'));
        
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
        // Read before the name is prepared: prepare_file_name() turns an
        // empty name into "file", so a form sent without a file was answered
        // with "must be a CSS file".
        $file_missing = pg_upload_file_missing($_FILES['custom_css_file'] ?? null);
        $uploaded_name = $file_missing ? '' : trim(pg_basename((string) $_FILES['custom_css_file']['name']));
        $file_name = $file_missing ? '' : prepare_file_name($uploaded_name);

        $file_size = $file_missing ? 0 : $_FILES['custom_css_file']['size'];
        $file_temp_name = $file_missing ? '' : $_FILES['custom_css_file']['tmp_name'];
        $array_file_extension = explode('.', $file_name);
        $size_of_array = count($array_file_extension);
        $file_extension = $array_file_extension[$size_of_array - 1];
        
        // if there is not a file name, then add an error to the liveform
        if ($file_name == '') {
            $liveform->mark_error('custom_css_file', lang(array('string'=>'{var:1} is required.','vars'=>lang('File'))));
        } else {
            $upload_limits = pg_upload_limits();
            $upload_refusal = pg_upload_file_refusal($_FILES['custom_css_file'], $upload_limits['file_max']);

            if ($upload_refusal !== '') {
                $liveform->mark_error('custom_css_file', h($upload_refusal));
            }
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
        
        // A taken name is the file box's error, so the folder and description
        // come back with the form instead of a site error page.
        if (check_name_availability(array('name' => $file_name)) == false) {
            $liveform->mark_error('custom_css_file', lang(array('string' => '{var:1} already exists. Please choose a different file name.', 'vars' => h($file_name))));
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_theme_file.php');
            exit();
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
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                '1',
                '1',
                UNIX_TIMESTAMP())";
        $result = db_q($query, array((string) $file_name, (string) $liveform->get_field_value('folder'), (string) $liveform->get_field_value('description'), (string) $file_extension, (string) $file_size, (string) $user['id'])) or output_error('Query failed.');
        $theme_id = (int) mysqli_insert_id(db::$con);
        pg_audit_record('create', 'files', $theme_id);
        
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
        $uploaded_notice = lang(array('string'=>'The theme file, {var:1}, has been uploaded.','vars'=>'<a href="' . OUTPUT_PATH . h($file_name) . '" target="_blank">' . h($file_name) . '</a>'));
        // The name is the theme's address; say so when it is not the file's own.
        $saved_as = pg_file_name_saved_as_notice($uploaded_name, $file_name);
        $liveform_view_themes->add_notice($uploaded_notice . (($saved_as !== '') ? ' ' . $saved_as : ''));

        
        // send the user to the view themes screen
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_themes.php');
        

    }
}
?>
