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

$liveform = new liveform('mass_edit');

function checkifchecked($checkedstatus){
    if($checkedstatus == 1){
        return ' checked="checked"';
    }
}

// if the form was not just submitted, then prepare to output form
if (!$_POST) {
    // if the page parameter has tags,
    if(isset($_GET['tags'])){
        //check if clear mode or not
        if($_GET['tags'] == 'clear'){
            //if clear redirect user to tag selection menu
            $_SESSION['software']['mass_edit']['tags'] = '';
        }else{
            //else user coming from tag selection menu
            $_SESSION['software']['mass_edit']['tags'] = $_GET['tags'];
        }
        
    }

    //if session tags are cleared or empty output tag  selection menu
    if(!isset($_SESSION['software']['mass_edit']['tags']) || ($_SESSION['software']['mass_edit']['tags'] ?? '') == ''){
      
        $outputs = '
        <div class="row justify-content-center mt-5 pt-5">
            <div class="col position-relative mb-5">
                <label for="page_options" class="form-label">' . lang(array('string'=>'Select one or more {var:1}','vars'=>'Page Area' )) . '</label>
                <select class="select2 form-select" id="page_options" name="page_options[]" multiple="multiple">
                    <optgroup value="" label="' . lang('Main Informations') . '">
                        <option value="page_name">' . lang('Page Name') . '</option>
                    </optgroup>
                    <optgroup value="" label="' . lang('SEO') . '">
                        <option value="page_title">' . lang('Web Browser Title') . '</option>
                        <option value="page_meta_description">' . lang('Web Browser Description') . '</option>
                        <option value="site_search">' . lang('Include in Site Search') . '</option>
                        <option value="page_search_keywords">' . lang('Promote on Keyword') . '</option>
                    </optgroup>
                </select>
            </div>
            <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data select buttons">
                <div class="container">
                    <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                        <button type="button" id="get_form" value="Get Form" class=" btn mb-1 mt-1 btn-primary disabled" data-loading-content="' . lang(array('string'=>'Loading') ) . '"><span class="material-icons me-2">save</span>' . lang(array('string'=>'Get Edit Form') ) . '</button>
                    </div>
                </div>
            </nav>
        </div>
        <script>
            $("select#page_options").on("select2:select select2:unselect", function (evt) {
                if($(this).val() == ""){
                    $("button#get_form").addClass("disabled");
                }else{
                    $("button#get_form").removeClass("disabled");
                }
            });
            $("#get_form").on("click", function(){
                window.location.href += "?tags=" + $("select#page_options").val();
            });

        </script>';
    }else{

        $tags = explode(',',($_SESSION['software']['mass_edit']['tags'] ?? ''));

        $output_page_editing = false;

        $output_page_name = false;
        $output_page_title = false;
        $output_page_meta_description = false;
        $output_site_search = false;
        $output_page_search_keywords = false;
        

        foreach($tags as $tag){
            switch($tag){
                case 'page_name':
                case 'page_title':        
                case 'page_meta_description':
                case 'site_search':
                case 'page_search_keywords':
                    // get all pages
                    $query ="SELECT * FROM page ORDER BY page_name asc";
                    $count_result = db('SELECT COUNT(*) FROM page');
                    
                    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
                    $output_page_editing = true;
                    break;
            }

            switch($tag){
                case 'page_name':
                    $output_page_name = true;
                    break;
                case 'page_title':        
                    $output_page_title = true;
                    break;
                case 'page_meta_description':
                    $output_page_meta_description = true;
                    break;
                case 'site_search':
                case 'page_search_keywords':
                    $output_site_search = true;
                    $output_page_search_keywords = true;
                    break;
            }
        }


        
        
        
        
        //starts of page editing
        if($output_page_editing != false){
            $outputs = '
                <div class="row">
                    <div class="col-12">
                        <h5>' . lang(array('string'=>'{var:1} Page{suffix:1}','vars'=>$count_result)) . '</h5>
                    </div>';
            while ($row = mysqli_fetch_assoc($result)) {
                //row for page
                $outputs .= '
                    <div class="col-12 col-lg-6">
                        <div class="card my-4">
                            <div class="card-header no-popover " title="' . $row['page_name'] . '">
                                <span class="bi bi-window-fullscreen me-2"></span>
                                <span class="text-truncate">' . $row['page_name'] . '</span>
                            </div>
                            <div class="card-body"> 
                                <div class="row g-4">';

                //page name change function not integrated yet.
                if($output_page_name != false){
                    $outputs .= '
                                    <div class="col-12">
                                        <label class="form-label" for="page_name_' . $row['page_id'] . '">' . lang('Page Name') . '</label>
                                        <div class="input-group ">
                                            <label for="page_name_' . $row['page_id'] . '" class="input-group-text material-icons" title="' . lang('This option determines the url address of the page.') . '" data-bs-content="' . URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Page Name') . '}">public</label>
                                            <input name="page_name_' . $row['page_id'] . '" id="page_name_' . $row['page_id'] . '" type="text" value="' . h($row['page_name']) . '" placeholder="' . lang('Page Name') . '" maxlength="100" class="form-control add-header-content-updater" required="required" />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                    </div>';
                }
                if($output_page_title != false){
                    $outputs .= '
                                    <div class="col-12">
                                        <label class="form-label" for="page_title_' . $row['page_id'] . '">' . lang('Web Browser Title') . '</label>
                                        <input class="form-control" type="text" id="page_title_' . $row['page_id'] . '" name="page_title_' . $row['page_id'] . '" value="' . h($row['page_title']) . '" />
                                    </div>';
                }
                if($output_page_meta_description != false){
                    $outputs .= '                
                                    <div class="col-12">
                                        <label for="meta_description_' . $row['page_id'] . '" class="form-label">' . lang('Web Browser Description') . '</label>
                                        <textarea name="meta_description_' . $row['page_id'] . '" id="meta_description_' . $row['page_id'] . '" class="form-control" maxlength="255" >' . h($row['page_meta_description']) . '</textarea>
                                    </div>';
                }
                if($output_site_search != false || $output_page_search_keywords != false){
                    $outputs .= '
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input type="hidden" name="page_search_' . $row['page_id'] . '" value="0" />
                                            <input value="1"' . checkifchecked($row['page_search']) . ' id="page_search_' . $row['page_id'] . '" name="page_search_' . $row['page_id'] . '" class="form-check-input collapse-switcher" type="checkbox"  data-bs-target="#show_or_hide_search_' . $row['page_id'] . '"/>
                                            <label class="form-check-label" for="page_search_' . $row['page_id'] . '">' . lang('Include in Site Search') . '</label>
                                        </div>
                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-2 w-100" id="show_or_hide_search_' . $row['page_id'] . '">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                            <div class="popover-body">
                                                <div class="row">
                                                    <div class="col-12 mt-3 mb-2">
                                                        <label for="page_search_keywords_' . $row['page_id'] . '" class="form-label">' . lang('Promote on Keyword') . '</label>
                                                        <input value="' . h($row['page_search_keywords']) . '" type="text" name="page_search_keywords_' . $row['page_id'] . '" id="page_search_keywords_' . $row['page_id'] . '" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '"/>
                                                        <script>tagin( document.querySelector("#page_search_keywords_' . $row['page_id'] . '") );</script>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';
                }


                //ends of row
                $outputs .= '   
                                </div>
                            </div>
                        </div>
                    </div>';
            }

            $outputs .= '
                </div>';
        }
        //ends of page editing

        $outputs .= '
                <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="data edit buttons ">
                    <div class="container">
                        <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">
                            <button type="submit" value="Save Changes" class=" btn mb-1 mt-1 btn-success" data-loading-content="' . lang(array('string'=>'Saving') ) . '" data-confirm-content="' . lang('WARNING: The changes you make will be applied permanently.') . '"><span class="material-icons me-2">send</span>' . lang(array('string'=>'Apply Changes') ) . '</button>
                        </div>
                    </div>
                </nav>';
    }

    // --- Find & Replace card (always visible, separate form) ---
    $output_find_replace = '
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left-right"></i>
                <strong>' . lang('Find & Replace') . '</strong>
            </div>
            <div class="card-body">
                <form method="post" action="' . $_SERVER['PHP_SELF'] . '" class="disable_shortcut">
                    ' . get_token_field() . '
                    <input type="hidden" name="action" value="find_replace">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-auto">
                            <label class="form-label fw-semibold mb-2">' . lang('Apply to Fields') . '</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="fr_fields[]"
                                    value="page_name" id="fr_page_name">
                                <label class="form-check-label" for="fr_page_name">' . lang('Page Name') . '</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="fr_fields[]"
                                    value="page_title" id="fr_page_title" checked>
                                <label class="form-check-label" for="fr_page_title">' . lang('Web Browser Title') . '</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="fr_fields[]"
                                    value="page_meta_description" id="fr_meta_desc" checked>
                                <label class="form-check-label" for="fr_meta_desc">' . lang('Web Browser Description') . '</label>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 col-md">
                            <label class="form-label" for="find_text">' . lang('Find') . '</label>
                            <input type="text" name="find_text" id="find_text"
                                class="form-control font-monospace"
                                placeholder="' . lang('Text to find...') . '">
                        </div>
                        <div class="col-12 col-sm-6 col-md">
                            <label class="form-label" for="replace_text">' . lang('Replace with') . '</label>
                            <input type="text" name="replace_text" id="replace_text"
                                class="form-control font-monospace"
                                placeholder="' . lang('Leave empty to delete') . '">
                        </div>
                        <div class="col-12 col-md-auto d-flex flex-column gap-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="use_regex" id="use_regex" value="1">
                                <label class="form-check-label" for="use_regex">
                                    ' . lang('Regex') . '
                                    <small class="text-secondary d-block" style="font-size:.75em;">/pattern/flags</small>
                                </label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-warning"
                                data-confirm-content="' . lang('WARNING: The changes you make will be applied permanently.') . '">
                                <i class="bi bi-arrow-left-right me-1"></i>' . lang('Apply') . '
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>';

    $output_clear_button = '';
    if(isset($_SESSION['software']['mass_edit']['tags']) && ($_SESSION['software']['mass_edit']['tags'] ?? '') != ''){
        $output_clear_button = '
        <li class="nav-item me-1 mb-1">
            <a href="?tags=clear" class="btn btn-sm"><i class="bi bi-trash me-2"></i>' . lang('Clear') . '</a>
        </li>';
    }

    $output_button_bar = '
    <nav class="navbar navbar-expand px-3 py-2 flex-column flex-md-row">
        <ul class="navbar-nav flex-column flex-md-row">
            ' . $output_clear_button . '
        </ul>
    </nav>';



        print
        pg_page_shell([
        'title'=> lang('Mass Edit'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('Mass Edit'),
        'auto_main'=>false,
    ]) . '
        <main class="container" id="content" style="min-height:60vh">
            <div class="row">
                <div class="col-12">
                    ' . $liveform->output_errors() . '
                    ' . $liveform->get_warnings() . '
                    ' . $liveform->output_notices() . '
                    <div class="row mb-2  flex-wrap">
                        <div class="col-12 text-center text-md-start">
                            <h2 class="d-inline-block " data-bs-content="' . lang('It is a special page for mass editing of Web Site Contents. Not recommended if you dont know what you are doing.') . '" title="' . lang('Mass Edit') . '">' . lang('Mass Edit') . '</h2>
                            ' . $output_button_bar . '
                        </div>
                    </div>
                    ' . $output_find_replace . '
                    <div class="card border-0 shadow-none">
                        <div class="card-body position-relative">
                        <form name="form" action="' . $_SERVER['PHP_SELF'] . '" method="post" class="disable_shortcut">
                            ' . get_token_field() . '
                            ' . $outputs . '
                        </form>
                    </div>
                </div>
            </div>
        </main>
        <script>
            var CURRENT_URL = window.location.href;
            //Remove Parameter from url
            function removeParam(key, sourceURL) {
                var rtn = sourceURL.split("?")[0],
                    param,
                    params_arr = [],
                    queryString = (sourceURL.indexOf("?") !== -1) ? sourceURL.split("?")[1] : "";
                if (queryString !== "") {
                    params_arr = queryString.split("&");
                    for (var i = params_arr.length - 1; i >= 0; i -= 1) {
                        param = params_arr[i].split("=")[0];
                        if (param === key) {
                            params_arr.splice(i, 1);
                        }
                    }
                    rtn = rtn + "?" + params_arr.join("&");
                }
                return rtn;
            }
            tags_removed_url = removeParam("tags", CURRENT_URL);
            if (window.history.replaceState) {
                history.pushState({}, null, String(tags_removed_url).replace( "?", "" ));
            }

        // SEO character counters — logic in assets/backend.src.js
        initSeoCounters([
            { sel: "[name^=\"page_title_\"]",       min: 50,  max: 60  },
            { sel: "[name^=\"meta_description_\"]", min: 150, max: 160 }
        ]);
        </script>
        ' . output_footer();
        $liveform->remove_form('mass_edit');

}else{
    validate_token_field();

    /* ---------------------------------------------------------
       Find & Replace action
       --------------------------------------------------------- */
    if (isset($_POST['action']) && $_POST['action'] === 'find_replace') {
        $find      = isset($_POST['find_text'])    ? $_POST['find_text']    : '';
        $replace   = isset($_POST['replace_text']) ? $_POST['replace_text'] : '';
        $use_regex = isset($_POST['use_regex']) && $_POST['use_regex'] == '1';
        $fr_fields = (isset($_POST['fr_fields']) && is_array($_POST['fr_fields'])) ? $_POST['fr_fields'] : array();

        // Whitelist — only these fields are safe to bulk-replace
        $allowed_fr = array('page_name', 'page_title', 'page_meta_description');
        $fr_fields  = array_values(array_intersect($fr_fields, $allowed_fr));

        if ($find === '') {
            $liveform->mark_error('find_text', lang('Find field cannot be empty.'));
        } elseif (empty($fr_fields)) {
            $liveform->mark_error('fr_fields', lang('Please select at least one field.'));
        } else {
            $valid = true;
            if ($use_regex && @preg_match($find, '') === false) {
                $liveform->mark_error('find_text', lang('Invalid regex pattern.'));
                $valid = false;
            }
            if ($valid) {
                $field_labels = array(
                    'page_name'             => lang('Page Name'),
                    'page_title'            => lang('Web Browser Title'),
                    'page_meta_description' => lang('Web Browser Description'),
                );
                $count_by_field  = array();
                $fr_name_skipped = 0;
                $q = mysqli_query(db::$con, "SELECT page_id, page_name, page_title, page_meta_description FROM page") or output_error(lang('Query failed.'));
                while ($row = mysqli_fetch_assoc($q)) {
                    foreach ($fr_fields as $field) {
                        $current = $row[$field];
                        $new_val = $use_regex
                            ? @preg_replace($find, $replace, $current)
                            : str_replace($find, $replace, $current);
                        if ($new_val === null) continue; // regex error on value
                        if ($new_val === $current) continue; // no change

                        // page_name: sanitize + uniqueness check
                        if ($field === 'page_name') {
                            if (trim($new_val) === '') continue;
                            $new_val = str_replace(' ', '_', $new_val);
                            $new_val = str_replace('&', '_', $new_val);
                            if (!check_name_availability(array('name' => $new_val, 'ignore_item_id' => $row['page_id'], 'ignore_item_type' => 'page'))) {
                                $fr_name_skipped++;
                                continue;
                            }
                        }

                        $count_by_field[$field] = isset($count_by_field[$field]) ? $count_by_field[$field] + 1 : 1;
                        db("UPDATE page SET `{$field}` = '" . escape($new_val) . "' WHERE page_id = '" . e($row['page_id']) . "'");
                    }
                }
                if (empty($count_by_field)) {
                    $liveform->add_notice(lang('No matching text found.'));
                } else {
                    $parts = array();
                    foreach ($count_by_field as $field => $cnt) {
                        $parts[] = $cnt . ' ' . (isset($field_labels[$field]) ? $field_labels[$field] : $field);
                    }
                    $liveform->add_notice(lang('Find & Replace applied') . ': ' . implode(', ', $parts));
                }
                if ($fr_name_skipped > 0) {
                    $liveform->add_notice($fr_name_skipped . ' ' . lang('page name(s) skipped: name already in use.'));
                }
            }
        }
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/mass_edit.php');
        exit();
    }

    $count_change = 0;
    $count_page_name_changes = 0;
    $count_page_title_changes = 0;
    $count_page_meta_description_changes = 0;
    $count_page_search_keywords_changes = 0;
    $count_page_search_changes = 0;
    $page_name_conflicts = array();

    // get current page data
    $query = "SELECT * FROM page";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    while ($row = mysqli_fetch_assoc($result)) {
        //Page id
        $page_id = $row['page_id'];
        //get current page db values we will need to comparing with posts.
        $current_page_name = $row['page_name'];
        $current_page_title = $row['page_title'];
        $current_page_meta_description = $row['page_meta_description'];
        $current_page_search = $row['page_search'];
        $current_page_search_keywords = $row['page_search_keywords'];

        //the tag cloud is built from the promote on keyword list of the pages that are in the
        //site search, so remember what those two end up as and refresh it once, after both
        //of them have had a chance to change in this submit.
        $new_page_search = $current_page_search;
        $new_page_search_keywords = $current_page_search_keywords;

        
        



        // if page_name was posted, sanitize and save (same rules as edit_page.php)
        if (isset($_POST['page_name_' . $page_id]) && ($_POST['page_name_' . $page_id] ?? '') !== '') {
            $new_page_name = trim($_POST['page_name_' . $page_id]);
            $new_page_name = str_replace(' ', '_', $new_page_name);
            $new_page_name = str_replace('&', '_', $new_page_name);
            if ($new_page_name !== $current_page_name) {
                if (check_name_availability(array('name' => $new_page_name, 'ignore_item_id' => $page_id, 'ignore_item_type' => 'page'))) {
                    $count_change++;
                    $count_page_name_changes++;
                    db("UPDATE page SET page_name = '" . escape($new_page_name) . "' WHERE page_id = '" . e($page_id) . "'");
                } else {
                    $page_name_conflicts[] = h($new_page_name);
                }
            }
        }

        //if page_title posted and posted page_title not equal to current one, update it.
        if(
            $_POST['page_title_' . $row['page_id']]
            && $_POST['page_title_' . $row['page_id']] != $current_page_title)
        {
            //count how many updated.
            $count_change++;
            $count_page_title_changes++;
            //update with db function.
            db("UPDATE page SET page_title = '" . escape($_POST['page_title_' . $row['page_id']]) . "' WHERE page_id = '" . e($page_id) . "'");
        }

        //if page_meta_description posted and posted page_meta_description not equal to current one, update it.
        if( 
            $_POST['meta_description_' . $row['page_id']]
            && $_POST['meta_description_' . $row['page_id']] != $current_page_meta_description)
        {
            //count how many updated.
            $count_change++;
            $count_page_meta_description_changes++;
            //update with db function.
            db("UPDATE page SET page_meta_description = '" . escape($_POST['meta_description_' . $row['page_id']]) . "' WHERE page_id = '" . e($page_id) . "'");
        }
       
        //if page_search posted and posted page_search not equal to current one, update it.
        if(
            isset($_POST['page_search_' . $row['page_id']])
            && $_POST['page_search_' . $row['page_id']] != NULL
            && $_POST['page_search_' . $row['page_id']] != ''
            && $_POST['page_search_' . $row['page_id']] != $current_page_search){
            //count how many updated.
            $count_change++;
            $count_page_search_changes++;
            //update with db function.
            db("UPDATE page SET page_search = '" . escape($_POST['page_search_' . $row['page_id']]) . "' WHERE page_id = '" . e($page_id) . "'");

            $new_page_search = $_POST['page_search_' . $row['page_id']];
        }

        //if page_search_keywords posted and posted page_search_keywords not equal to current one, update it.
        if(
            $_POST['page_search_keywords_' . $row['page_id']]
            && $_POST['page_search_keywords_' . $row['page_id']] != $current_page_search_keywords)
        {

            //count how many updated.
            $count_change++;
            $count_page_search_keywords_changes++;
            //update with db function.
            db("UPDATE page SET page_search_keywords = '" . escape($_POST['page_search_keywords_' . $row['page_id']]) . "' WHERE page_id = '" . e($page_id) . "'");

            $new_page_search_keywords = $_POST['page_search_keywords_' . $row['page_id']];
        }

        //if either half of what the tag cloud is built from changed, rebuild this page's entries.
        if(
            ($new_page_search != $current_page_search)
            || ($new_page_search_keywords != $current_page_search_keywords))
        {
            update_tag_cloud_keywords_for_page($page_id, $new_page_search, $new_page_search_keywords, $current_page_search, $current_page_search_keywords);
        }

    }


    //if page name changed, notice it.
    if ($count_page_name_changes > 0) {
        $liveform->add_notice(lang(array('string' => '{var:1} Page(s) name updated.', 'vars' => array($count_page_name_changes))));
    }
    if (!empty($page_name_conflicts)) {
        $liveform->add_notice(lang('Some page names could not be updated because the name is already in use') . ': ' . implode(', ', $page_name_conflicts));
    }

    //if page title changed, notice it.
    if($count_page_title_changes > 0){
        $liveform->add_notice( lang(array('string'=>'{var:1} Page(s) title updated.','vars'=>array($count_page_title_changes) )) );
    }

    //if page meta description changed, notice it.    
    if($count_page_meta_description_changes > 0){
        $liveform->add_notice( lang(array('string'=>'{var:1} Page(s) meta description updated.','vars'=>array($count_page_meta_description_changes) )) );
    }

    //if page search changed, notice it.    
    if($count_page_search_changes > 0){
        $liveform->add_notice( lang(array('string'=>'{var:1} Page(s) search status updated.','vars'=>array($count_page_search_changes) )) );
    }

    //if page search keywords changed, notice it.    
    if($count_page_search_keywords_changes > 0){
        $liveform->add_notice( lang(array('string'=>'{var:1} Page(s) search keywords updated.','vars'=>array($count_page_search_keywords_changes) )) );
    }

    //if there is no change, output notice for it.
    if($count_change == 0){
        $liveform->add_notice( lang('No change detected.') );
    }

    //redirect user to page.
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/mass_edit.php');
}
