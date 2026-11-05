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

$user = validate_user();
validate_area_access($user, 'designer');

// The page name rules of the page screens.
require_once(PG_FUNCTIONS_DIR . '/includes/forms/page_form.php');
// pg_page_bulk_touch(): a changed page gets its Last Modified time and user.
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_bulk.php');

$liveform = new liveform('mass_edit');

// Pages in the Recycle Bin, or in a folder carried into it, are neither
// listed nor changed here: a rename would park them under a name the
// restore brings back.
$live_pages_where = ' WHERE 1 = 1' . pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false);

// The edit grid draws this many pages per screen (?screen=N).
define('MASS_EDIT_PAGES_PER_SCREEN', 50);

function checkifchecked($checkedstatus){
    if($checkedstatus == 1){
        return ' checked="checked"';
    }
}

// if the form was not just submitted, then prepare to output form
if (!$_POST) {
    // if the page parameter has tags,
    // (a list sent as an array, ?tags[]=x, counts as not given: kept in the
    // session, it made explode() refuse every later visit with an error page)
    if(isset($_GET['tags']) && is_string($_GET['tags'])){
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
    if(!isset($_SESSION['software']['mass_edit']['tags']) || !is_string($_SESSION['software']['mass_edit']['tags']) || ($_SESSION['software']['mass_edit']['tags'] ?? '') == ''){
      
        $form_column = '
            <div class="card mb-7">
                <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">
                    <label for="page_options" class="mb-0">' . lang(array('string'=>'Select one or more {var:1}','vars'=>lang('Page Area') )) . '</label>
                </h2>
                <div class="card-body pt-0">
                    <select class="form-control" data-pg-combobox id="page_options" name="page_options[]" multiple="multiple">
                        <optgroup label="' . h(lang('Main Information')) . '">
                            <option value="page_name">' . lang('Page Name') . '</option>
                        </optgroup>
                        <optgroup label="' . h(lang('SEO')) . '">
                            <option value="page_title">' . lang('Web Browser Title') . '</option>
                            <option value="page_meta_description">' . lang('Web Browser Description') . '</option>
                            <option value="site_search">' . lang('Include in Site Search') . '</option>
                            <option value="page_search_keywords">' . lang('Promote on Keyword') . '</option>
                        </optgroup>
                    </select>
                </div>
            </div>';
        $save_buttons = '
                            <button type="button" id="get_form" value="Get Form" class="btn my-1 btn-solid theme-primary" disabled><i class="bi bi-ui-checks me-3" aria-hidden="true"></i><span class="pg-btn-label">' . lang(array('string'=>'Get Edit Form') ) . '</span></button>';
        $summary_card = '';
        $screen_script = '
        <script>
            $("select#page_options").on("change", function (evt) {
                var chosen = $(this).val();
                $("button#get_form").prop("disabled", !chosen || (chosen.length == 0));
            });
            $("#get_form").on("click", function(){
                // Built from the path so a #fragment (the skip link) does not swallow the query.
                window.location.href = window.location.pathname + "?tags=" + encodeURIComponent(String($("select#page_options").val() || ""));
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


        
        
        
        
        // A tag the screen does not know (hand-typed ?tags=x) edits nothing,
        // so only the Summary and the save bar are output.
        $form_column = '';
        $summary_card = '';

        //starts of page editing
        if($output_page_editing != false){
            // One screen of pages at a time: each card carries a chips field
            // and two counters, and drawing every page of a large site froze
            // the browser tab for minutes. The save reads only posted fields.
            $count_result = (int) db("SELECT COUNT(*) FROM page" . $live_pages_where);
            $screen = pg_list_page_clamp(pg_list_page_number($_GET['screen'] ?? 1), $count_result, MASS_EDIT_PAGES_PER_SCREEN);
            $number_of_screens = max(1, (int) ceil($count_result / MASS_EDIT_PAGES_PER_SCREEN));

            $query =
                "SELECT page_id, page_name, page_title, page_meta_description, page_search, page_search_keywords
                FROM page" . $live_pages_where . "
                ORDER BY page_name ASC
                LIMIT " . (($screen - 1) * MASS_EDIT_PAGES_PER_SCREEN) . ", " . MASS_EDIT_PAGES_PER_SCREEN;
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            $output_screen_pager = '';

            if ($number_of_screens > 1) {
                $screen_link = function ($target, $label, $icon) use ($screen, $number_of_screens) {
                    $inner = ($icon === 'bi-chevron-left')
                        ? '<i class="bi ' . $icon . ' me-1" aria-hidden="true"></i>' . h($label)
                        : h($label) . '<i class="bi ' . $icon . ' ms-1" aria-hidden="true"></i>';

                    if (($target < 1) || ($target > $number_of_screens)) {
                        return '<span class="btn btn-sm btn-text theme-secondary disabled" aria-disabled="true">' . $inner . '</span>';
                    }

                    return '<a class="btn btn-sm btn-text theme-secondary" href="mass_edit.php?screen=' . $target . '">' . $inner . '</a>';
                };

                $output_screen_pager = '
                    <nav class="col-12 d-flex align-items-center justify-content-center gap-3 my-5" aria-label="' . h(lang('Pagination')) . '">
                        ' . $screen_link($screen - 1, lang('Previous page'), 'bi-chevron-left') . '
                        <span class="small fg-secondary">' . lang(array('string' => 'Page {var:1} of {var:2}', 'vars' => array($screen, $number_of_screens))) . '</span>
                        ' . $screen_link($screen + 1, lang('Next page'), 'bi-chevron-right') . '
                    </nav>';
            }

            // The Summary names what is edited, so the cards need no heading.
            $field_labels = array();
            if ($output_page_name) {
                $field_labels[] = lang('Page Name');
            }
            if ($output_page_title) {
                $field_labels[] = lang('Web Browser Title');
            }
            if ($output_page_meta_description) {
                $field_labels[] = lang('Web Browser Description');
            }
            if ($output_site_search) {
                $field_labels[] = lang('Include in Site Search');
                $field_labels[] = lang('Promote on Keyword');
            }
            $output_field_list = '';
            foreach ($field_labels as $field_label) {
                $output_field_list .= '<li>' . h($field_label) . '</li>';
            }

            $summary_card = '
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Summary') . '</h2>
                        <div class="card-body pt-0">
                            <p class="fw-semibold mb-3">' . lang(array('string'=>'{var:1} Page{suffix:1}','vars'=>$count_result,'suffix'=>(($count_result == 1) ? '' : 's'))) . '</p>
                            ' . (($number_of_screens > 1) ? '<p class="small fg-secondary mb-3">' . lang(array('string' => 'Page {var:1} of {var:2}', 'vars' => array($screen, $number_of_screens))) . '</p>' : '') . '
                            <ul class="mb-0 ps-5">' . $output_field_list . '</ul>
                        </div>
                    </div>';

            $form_column = '
                <div class="row">
                    ' . $output_screen_pager;
            while ($row = mysqli_fetch_assoc($result)) {
                //row for page
                $form_column .= '
                    <div class="col-12 2xl:col-6">
                        <div class="card mb-7">
                            <div class="card-header no-popover" style="display: flex; align-items: center; min-width: 0;" title="' . h($row['page_name']) . '">
                                <span class="bi bi-window-fullscreen me-3 flex-shrink-0" aria-hidden="true"></span>
                                <span class="text-truncate" style="min-width: 0;">' . h($row['page_name']) . '</span>
                            </div>
                            <div class="card-body"> 
                                <div class="row g-7">';

                //page name change function not integrated yet.
                if($output_page_name != false){
                    $form_column .= '
                                    <div class="col-12">
                                        <label class="form-label" for="page_name_' . $row['page_id'] . '">' . lang('Page Name') . '</label>
                                        <div class="input-group ">
                                            <label for="page_name_' . $row['page_id'] . '" class="input-group-text material-icons" aria-hidden="true">public</label>
                                            <input name="page_name_' . $row['page_id'] . '" id="page_name_' . $row['page_id'] . '" aria-describedby="page_name_' . $row['page_id'] . '_hint" type="text" value="' . h($row['page_name']) . '" placeholder="' . lang('Page Name') . '" maxlength="100" class="form-control" required="required" />
                                            <div class="invalid-feedback">' . lang('Required Area') . '</div>
                                        </div>
                                        <div class="form-text" id="page_name_' . $row['page_id'] . '_hint">' . lang('This option determines the url address of the page.') . ' <code>' . h(URL_SCHEME . HOSTNAME . OUTPUT_PATH . '{' . lang('Page Name') . '}') . '</code></div>
                                    </div>';
                }
                if($output_page_title != false){
                    $form_column .= '
                                    <div class="col-12">
                                        <label class="form-label" for="page_title_' . $row['page_id'] . '">' . lang('Web Browser Title') . '</label>
                                        <input class="form-control" type="text" id="page_title_' . $row['page_id'] . '" name="page_title_' . $row['page_id'] . '" value="' . h($row['page_title']) . '" maxlength="255" />
                                    </div>';
                }
                if($output_page_meta_description != false){
                    $form_column .= '                
                                    <div class="col-12">
                                        <label for="meta_description_' . $row['page_id'] . '" class="form-label">' . lang('Web Browser Description') . '</label>
                                        <textarea name="meta_description_' . $row['page_id'] . '" id="meta_description_' . $row['page_id'] . '" class="form-control" maxlength="255" >' . h($row['page_meta_description']) . '</textarea>
                                    </div>';
                }
                if($output_site_search != false || $output_page_search_keywords != false){
                    $form_column .= '
                                    <div class="col-12">
                                        <div class="form-field">
                                            <input type="hidden" name="page_search_' . $row['page_id'] . '" value="0" />
                                            <div class="switch"><input value="1"' . checkifchecked($row['page_search']) . ' id="page_search_' . $row['page_id'] . '" name="page_search_' . $row['page_id'] . '" class="collapse-switcher" type="checkbox"  data-bs-target="#show_or_hide_search_' . $row['page_id'] . '"/></div>
                                            <label class="" for="page_search_' . $row['page_id'] . '">' . lang('Include in Site Search') . '</label>
                                        </div>
                                        <div class="collapse popover fade bs-popover-bottom p-0 mb-3 w-100" id="show_or_hide_search_' . $row['page_id'] . '">
                                            <div class="popover-arrow" style="position: absolute; left: 0px; transform: translate(59px, 0px);"></div>
                                            <div class="popover-body">
                                                <div class="row">
                                                    <div class="col-12 mt-5 mb-3">
                                                        <label for="page_search_keywords_' . $row['page_id'] . '" class="form-label">' . lang('Promote on Keyword') . '</label>
                                                        <input value="' . h($row['page_search_keywords']) . '" type="text" name="page_search_keywords_' . $row['page_id'] . '" id="page_search_keywords_' . $row['page_id'] . '" class="form-control tagin min-height-tagin" data-pg-chips data-placeholder="' . lang('Add tags') . '"/>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>';
                }


                //ends of row
                $form_column .= '   
                                </div>
                            </div>
                        </div>
                    </div>';
            }

            $form_column .= $output_screen_pager . '
                    <input type="hidden" name="screen" value="' . $screen . '">
                </div>';
        }
        //ends of page editing

        // The save bar sits outside the form, so the button names it.
        $save_buttons = '
                            <button type="submit" form="mass_edit_form" value="Save Changes" class="btn my-1 btn-solid theme-success" data-confirm-content="' . lang('WARNING: The changes you make will be applied permanently.') . '"><span class="material-icons me-3" aria-hidden="true">send</span><span class="pg-btn-label">' . lang(array('string'=>'Apply Changes') ) . '</span></button>';
        $screen_script = '';

    }

    // --- Find & Replace card (always visible, separate form) ---
    // Title and Description are ticked on a first draw; after a refused
    // apply the boxes show what was ticked.
    $fr_defaults = $liveform->prefill() ? array('checked' => 'checked') : array();
    $output_find_replace = '
                    <div class="card mb-7">
                        <h2 class="card-header bg-reset border-0 text-uppercase h5 fg-primary fw-bold">' . lang('Find & Replace') . '</h2>
                        <div class="card-body pt-0">
                            <form method="post" action="' . h($_SERVER['PHP_SELF']) . '" class="disable_shortcut">
                                ' . get_token_field() . '
                                <input type="hidden" name="action" value="find_replace">
                                <fieldset class="mb-5">
                                    <legend class="form-label fw-semibold fs-md mb-3">' . lang('Apply to Fields') . '</legend>
                                    <div class="form-field">
                                        ' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'fr_fields[]', 'id'=>'fr_page_name', 'value'=>'page_name', 'class'=>'check')) . '
                                        <label class="" for="fr_page_name">' . lang('Page Name') . '</label>
                                    </div>
                                    <div class="form-field">
                                        ' . $liveform->output_field($fr_defaults + array('type'=>'checkbox', 'name'=>'fr_fields[]', 'id'=>'fr_page_title', 'value'=>'page_title', 'class'=>'check')) . '
                                        <label class="" for="fr_page_title">' . lang('Web Browser Title') . '</label>
                                    </div>
                                    <div class="form-field">
                                        ' . $liveform->output_field($fr_defaults + array('type'=>'checkbox', 'name'=>'fr_fields[]', 'id'=>'fr_meta_desc', 'value'=>'page_meta_description', 'class'=>'check')) . '
                                        <label class="" for="fr_meta_desc">' . lang('Web Browser Description') . '</label>
                                    </div>
                                </fieldset>
                                <div class="mb-5">
                                    <label class="form-label" for="find_text">' . lang('Find') . '</label>
                                    ' . $liveform->output_field(array('type'=>'text', 'name'=>'find_text', 'id'=>'find_text', 'class'=>'form-control font-monospace', 'placeholder'=>lang('Text to find...'), 'required'=>'required')) . '
                                </div>
                                <div class="mb-5">
                                    <label class="form-label" for="replace_text">' . lang('Replace with') . '</label>
                                    ' . $liveform->output_field(array('type'=>'text', 'name'=>'replace_text', 'id'=>'replace_text', 'class'=>'form-control font-monospace', 'placeholder'=>lang('Leave empty to delete'))) . '
                                </div>
                                <div class="form-field mb-5">
                                    <div class="switch">' . $liveform->output_field(array('type'=>'checkbox', 'name'=>'use_regex', 'id'=>'use_regex', 'value'=>'1', 'class'=>'no-bs')) . '</div>
                                    <label class="" for="use_regex">
                                        ' . lang('Regex') . '
                                        <small class="fg-secondary d-block" style="font-size:.75em;">/pattern/flags</small>
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-sm btn-solid theme-warning w-100"
                                    data-confirm-content="' . lang('WARNING: The changes you make will be applied permanently.') . '">
                                    <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i><span class="pg-btn-label">' . lang('Apply') . '</span>
                                </button>
                            </form>
                        </div>
                    </div>';

    // While fields are chosen, the toolbar offers to choose again.
    $toolbar_slots = array();
    if(isset($_SESSION['software']['mass_edit']['tags']) && ($_SESSION['software']['mass_edit']['tags'] ?? '') != ''){
        $toolbar_slots['secondary'] = array('label' => lang('Clear'), 'url' => 'mass_edit.php?tags=clear', 'icon' => 'bi-x-circle');
    }

        print
        pg_page_shell([
        'title'=> lang('Mass Edit'),
        'extra classes'=>'setting',
        'icon'=>'setting',
        'heading'=>lang('Mass Edit'),
        'heading_description' => lang('It is a special page for mass editing of Web Site Contents. Not recommended if you dont know what you are doing.'),
    ]) . '
        <main class="container-fluid" id="content" style="min-height:60vh">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . pg_screen_toolbar($toolbar_slots) . '
            <div class="row">
                <div class="col-12 lg:col-8 2xl:col-9 order-1">
                    <form name="form" id="mass_edit_form" action="' . h($_SERVER['PHP_SELF']) . '" method="post" class="disable_shortcut position-relative">
                        ' . get_token_field() . '
                        ' . $form_column . '
                        <input type="hidden" name="max_input_vars_test" value="true">
                    </form>
                </div>
                <div class="col-12 lg:col-4 2xl:col-3 order-2">
                    <!-- 3.3rem: the fixed control panel header covers the top of the window. -->
                    <div class="position-sticky" style="top:3.3rem;">
                        ' . $summary_card . '
                        ' . $output_find_replace . '
                    </div>
                </div>
                <div class="col-12 order-3 position-sticky" style="bottom:0; z-index:3;">
                    <nav class="buttons navigation text-center mb-7" aria-label="' . h(lang('data edit buttons')) . '">
                        <div class="container">
                            <div class="btn-group flex-wrap justify-content-center">' . $save_buttons . '
                            </div>
                        </div>
                    </nav>
                </div>
            </div>
        </main>
        ' . $screen_script . '
        <script>
            // The chosen fields went to the session; the address keeps only
            // the screen, replaced so Back leaves the screen in one step.
            if (window.history.replaceState && window.URL) {
                var current_url = new URL(window.location.href);
                if (current_url.searchParams.has("tags")) {
                    current_url.searchParams.delete("tags");
                    history.replaceState(history.state, "", current_url.pathname + current_url.search + current_url.hash);
                }
            }

        // Only the fields that were changed are posted, so a site with many
        // pages stays under the max_input_vars PHP setting; the save loop
        // skips a page field that is not in the post.
        $("form[name=form]").on("submit", function () {
            var form = this;
            $(form).find("[name^=page_name_],[name^=page_title_],[name^=meta_description_],[name^=page_search_keywords_]").each(function () {
                var el = this;
                if (el.value === el.defaultValue) {
                    el.disabled = true;
                }
            });
            $(form).find("input[type=checkbox][name^=page_search_]").each(function () {
                var box = this;
                if (box.checked === box.defaultChecked) {
                    box.disabled = true;
                    $(form).find("input[type=hidden][name=\"" + box.name + "\"]").prop("disabled", true);
                }
            });
        });
        // A page brought back from the history cache gets its fields back.
        $(window).on("pageshow", function () {
            $("form[name=form] :disabled").prop("disabled", false);
        });

        // SEO character counters — logic in assets/js/backend.src.js
        initSeoCounters([
            { sel: "[name^=\"page_title_\"]",       min: 50,  max: 60  },
            { sel: "[name^=\"meta_description_\"]", min: 150, max: 160 }
        ]);
        </script>
        ' . pg_fields_script() . output_footer();
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

        // Both are text: an array would reach preg_match(), preg_replace()
        // or str_replace() and end the request with a TypeError.
        if (!is_string($find) || !is_string($replace)) {
            $liveform->mark_error('find_text', lang('Invalid request.'));
        } elseif ($find === '') {
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
                $fr_long_skipped = 0;
                $fr_invalid_skipped = 0;
                $q = mysqli_query(db::$con, "SELECT page_id, page_name, page_folder, page_type, page_title, page_meta_description FROM page" . $live_pages_where) or output_error(lang('Query failed.'));
                while ($row = mysqli_fetch_assoc($q)) {
                    // One audit line per changed page: read before its first
                    // field update, recorded after its last.
                    // Each changed field marks the SEO score stale, as Edit
                    // Page does: the scheduled SEO job scores only stale rows.
                    $audit_before = null;
                    foreach ($fr_fields as $field) {
                        $current = $row[$field];
                        $new_val = $use_regex
                            ? @preg_replace($find, $replace, $current)
                            : str_replace($find, $replace, $current);
                        if ($new_val === null) continue; // regex error on value
                        if ($new_val === $current) continue; // no change

                        // page_name: cleaned and refused as the page screens
                        // do (blank, or a slash in a catalog page name),
                        // then the uniqueness check
                        if ($field === 'page_name') {
                            $new_val = pg_page_form_bulk_name($new_val, $row['page_type']);
                            if ($new_val === null) {
                                $fr_invalid_skipped++;
                                continue;
                            }
                            if ($new_val === $current) continue;
                        }

                        // page_name is varchar(100), the other two
                        // varchar(255): a longer value would be cut in
                        // silence, so it is left as it was.
                        if (pg_name_too_long($new_val, ($field === 'page_name') ? pg_name_max_length() : 255)) {
                            $fr_long_skipped++;
                            continue;
                        }

                        if ($field === 'page_name') {
                            // A 301/302 short link of the page's own does
                            // not hold its name: the rename deletes it.
                            if (!check_name_availability(array('name' => $new_val, 'ignore_item_id' => $row['page_id'], 'ignore_item_type' => 'page', 'ignore_redirects_to_page' => $row['page_id']))) {
                                $fr_name_skipped++;
                                continue;
                            }
                        }

                        $count_by_field[$field] = isset($count_by_field[$field]) ? $count_by_field[$field] + 1 : 1;
                        if ($audit_before === null) {
                            $audit_before = pg_audit_fetch('page', $row['page_id']);
                        }
                        db("UPDATE page SET `{$field}` = '" . escape($new_val) . "', seo_analysis_current = '0' WHERE page_id = '" . e($row['page_id']) . "'");

                        // A redirect of the page's own that holds the new
                        // name would loop, so it goes; no redirect from the
                        // old name is added here.
                        if ($field === 'page_name') {
                            pg_short_link_page_renamed($row['page_id'], $row['page_name'], $new_val, false, $user['id'], $row['page_folder']);
                        }
                    }
                    if ($audit_before !== null) {
                        pg_page_bulk_touch($row['page_id'], $user['id']);
                        pg_audit_record('update', 'page', $row['page_id'], $audit_before);
                    }
                }
                // Text that was found but whose change was skipped is not
                // "not found": the skip notices below say why.
                if (empty($count_by_field) && (($fr_name_skipped + $fr_invalid_skipped + $fr_long_skipped) > 0)) {
                    $liveform->add_notice(lang('No change was applied.'));
                } elseif (empty($count_by_field)) {
                    $liveform->add_notice(lang('No matching text found.'));
                } else {
                    $parts = array();
                    foreach ($count_by_field as $field => $cnt) {
                        $parts[] = $cnt . ' ' . (isset($field_labels[$field]) ? $field_labels[$field] : $field);
                    }
                    $applied = lang('Find & Replace applied') . ': ' . implode(', ', $parts);
                    $liveform->add_notice($applied);
                    log_activity(lang('Mass Edit') . ': ' . $applied, $_SESSION['sessionusername']);
                }
                if ($fr_name_skipped > 0) {
                    $liveform->add_notice($fr_name_skipped . ' ' . lang('page name(s) skipped: name already in use.'));
                }
                if ($fr_invalid_skipped > 0) {
                    $liveform->add_notice($fr_invalid_skipped . ' ' . lang('page name(s) skipped: blank, or a forward slash in a catalog or catalog detail page name.'));
                }
                if ($fr_long_skipped > 0) {
                    $liveform->add_notice($fr_long_skipped . ' ' . lang('value(s) skipped: longer than the field allows.'));
                }
            }
        }
        if ($liveform->check_form_errors() == true) {
            // A refused apply comes back with what was typed and ticked, kept
            // untrimmed because spaces count in a pattern.
            $liveform->add_fields_to_session(array('trim' => false));
        }
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/mass_edit.php');
        exit();
    }

    // The marker is the form's last field: without it PHP dropped the end of
    // the post at max_input_vars, and the pages past that point would keep
    // their old values in silence, so nothing is saved.
    if (!isset($_POST['max_input_vars_test'])) {
        output_error(lang('The server did not accept the whole form, so no change was saved. Change fewer pages at a time, or ask the server administrator to raise the max_input_vars PHP setting in the php.ini file.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $count_change = 0;
    $count_page_name_changes = 0;
    $count_page_title_changes = 0;
    $count_page_meta_description_changes = 0;
    $count_page_search_keywords_changes = 0;
    $count_page_search_changes = 0;
    $page_name_conflicts = array();
    $page_name_refused = array();
    // Pages a value was not saved for because it is longer than its column:
    // the session is not strict, so the column would cut it without a word.
    $page_too_long = array();

    // get current page data
    $query = "SELECT * FROM page" . $live_pages_where;
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

        // One audit line per changed page: read before its first field
        // update, recorded after its last.
        // Every field below is read by the SEO score, so each UPDATE also
        // marks it stale (as Edit Page does) for the scheduled SEO job.
        $audit_before = null;

        // A posted title or description over its 255 characters is not
        // saved; the page is named once in the notice.
        $too_long_for_column = function ($prefix) use ($row, &$page_too_long) {
            $value = $_POST[$prefix . $row['page_id']] ?? '';

            if (!is_scalar($value) || !pg_name_too_long((string) $value, 255)) {
                return false;
            }

            $page_too_long[$row['page_id']] = h($row['page_name']);

            return true;
        };

        
        



        // if page_name was posted, clean and save it by the rules of
        // edit_page.php: a name it refuses (blank once trimmed, or a slash
        // in a catalog page name) is listed and the page keeps its name
        if (isset($_POST['page_name_' . $page_id]) && ($_POST['page_name_' . $page_id] ?? '') !== '') {
            $new_page_name = pg_page_form_bulk_name($_POST['page_name_' . $page_id], $row['page_type']);
            if ($new_page_name === null) {
                $page_name_refused[] = h($current_page_name);
            } elseif (pg_name_too_long($new_page_name)) {
                $page_too_long[$page_id] = h($current_page_name);
            } elseif ($new_page_name !== $current_page_name) {
                // A 301/302 short link of the page's own does not hold
                // its name: the rename deletes it below.
                if (check_name_availability(array('name' => $new_page_name, 'ignore_item_id' => $page_id, 'ignore_item_type' => 'page', 'ignore_redirects_to_page' => $page_id))) {
                    $count_change++;
                    $count_page_name_changes++;
                    if ($audit_before === null) {
                        $audit_before = pg_audit_fetch('page', $page_id);
                    }
                    db("UPDATE page SET page_name = '" . escape($new_page_name) . "', seo_analysis_current = '0' WHERE page_id = '" . e($page_id) . "'");

                    // A redirect of the page's own that holds the new name
                    // would loop, so it goes; no redirect from the old name
                    // is added here.
                    pg_short_link_page_renamed($page_id, $current_page_name, $new_page_name, false, $user['id'], $row['page_folder']);
                } else {
                    $page_name_conflicts[] = h($new_page_name);
                }
            }
        }

        //if page_title posted and posted page_title not equal to current one, update it.
        if(
            isset($_POST['page_title_' . $row['page_id']])
            && $_POST['page_title_' . $row['page_id']] !== (string) $current_page_title
            && !$too_long_for_column('page_title_'))
        {
            //count how many updated.
            $count_change++;
            $count_page_title_changes++;
            //update with db function.
            if ($audit_before === null) {
                $audit_before = pg_audit_fetch('page', $page_id);
            }
            db("UPDATE page SET page_title = '" . escape($_POST['page_title_' . $row['page_id']]) . "', seo_analysis_current = '0' WHERE page_id = '" . e($page_id) . "'");
        }

        //if page_meta_description posted and posted page_meta_description not equal to current one, update it.
        if( 
            isset($_POST['meta_description_' . $row['page_id']])
            && $_POST['meta_description_' . $row['page_id']] !== (string) $current_page_meta_description
            && !$too_long_for_column('meta_description_'))
        {
            //count how many updated.
            $count_change++;
            $count_page_meta_description_changes++;
            //update with db function.
            if ($audit_before === null) {
                $audit_before = pg_audit_fetch('page', $page_id);
            }
            db("UPDATE page SET page_meta_description = '" . escape($_POST['meta_description_' . $row['page_id']]) . "', seo_analysis_current = '0' WHERE page_id = '" . e($page_id) . "'");
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
            if ($audit_before === null) {
                $audit_before = pg_audit_fetch('page', $page_id);
            }
            db("UPDATE page SET page_search = '" . escape($_POST['page_search_' . $row['page_id']]) . "', seo_analysis_current = '0' WHERE page_id = '" . e($page_id) . "'");

            $new_page_search = $_POST['page_search_' . $row['page_id']];
        }

        //if page_search_keywords posted and posted page_search_keywords not equal to current one, update it.
        if(
            isset($_POST['page_search_keywords_' . $row['page_id']])
            && $_POST['page_search_keywords_' . $row['page_id']] !== (string) $current_page_search_keywords)
        {

            //count how many updated.
            $count_change++;
            $count_page_search_keywords_changes++;
            //update with db function.
            if ($audit_before === null) {
                $audit_before = pg_audit_fetch('page', $page_id);
            }
            db("UPDATE page SET page_search_keywords = '" . escape($_POST['page_search_keywords_' . $row['page_id']]) . "', seo_analysis_current = '0' WHERE page_id = '" . e($page_id) . "'");

            $new_page_search_keywords = $_POST['page_search_keywords_' . $row['page_id']];
        }

        if ($audit_before !== null) {
            pg_page_bulk_touch($page_id, $user['id']);
            pg_audit_record('update', 'page', $page_id, $audit_before);
        }

        //if either half of what the tag cloud is built from changed, rebuild this page's entries.
        if(
            ($new_page_search != $current_page_search)
            || ($new_page_search_keywords != $current_page_search_keywords))
        {
            update_tag_cloud_keywords_for_page($page_id, $new_page_search, $new_page_search_keywords, $current_page_search, $current_page_search_keywords);
        }

    }


    // The count lines, noticed one by one and logged as one line.
    $changed = array();

    //if page name changed, notice it.
    if ($count_page_name_changes > 0) {
        $changed[] = lang(array('string' => '{var:1} Page(s) name updated.', 'vars' => array($count_page_name_changes)));
    }
    if (!empty($page_name_refused)) {
        $liveform->add_notice(lang('Some page names could not be updated because the name is blank, or a catalog or catalog detail page name holds a forward slash') . ': ' . implode(', ', $page_name_refused));
    }
    if (!empty($page_name_conflicts)) {
        $liveform->add_notice(lang('Some page names could not be updated because the name is already in use') . ': ' . implode(', ', $page_name_conflicts));
    }

    if (!empty($page_too_long)) {
        $liveform->add_notice(lang('Some values were not saved because they are longer than the field allows') . ': ' . implode(', ', $page_too_long));
    }

    //if page title changed, notice it.
    if($count_page_title_changes > 0){
        $changed[] = lang(array('string'=>'{var:1} Page(s) title updated.','vars'=>array($count_page_title_changes) ));
    }

    //if page meta description changed, notice it.
    if($count_page_meta_description_changes > 0){
        $changed[] = lang(array('string'=>'{var:1} Page(s) meta description updated.','vars'=>array($count_page_meta_description_changes) ));
    }

    //if page search changed, notice it.
    if($count_page_search_changes > 0){
        $changed[] = lang(array('string'=>'{var:1} Page(s) search status updated.','vars'=>array($count_page_search_changes) ));
    }

    //if page search keywords changed, notice it.
    if($count_page_search_keywords_changes > 0){
        $changed[] = lang(array('string'=>'{var:1} Page(s) search keywords updated.','vars'=>array($count_page_search_keywords_changes) ));
    }

    foreach ($changed as $line) {
        $liveform->add_notice($line);
    }

    if ($changed) {
        log_activity(lang('Mass Edit') . ': ' . implode(' ', $changed), $_SESSION['sessionusername']);
    }

    //if there is no change, output notice for it.
    if($count_change == 0){
        $liveform->add_notice( lang('No change detected.') );
    }

    // Back to the screen of the grid that was saved.
    $screen = pg_list_page_number($_POST['screen'] ?? 1);
    header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/mass_edit.php' . (($screen > 1) ? '?screen=' . $screen : ''));
}
