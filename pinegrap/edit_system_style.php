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

include_once('liveform.class.php');
$liveform = new liveform('edit_system_style', $_REQUEST['id']);
$liveform->assign_field_value('id', $_REQUEST['id']);
$liveform->assign_field_value('send_to', $_REQUEST['send_to']);

// get style information
$query =
    "SELECT
       style.style_name as name,
       style.style_layout as layout,
       style.style_empty_cell_width_percentage AS empty_cell_width_percentage,
       style.style_head AS style_head,
       style.social_networking_position,
       style.theme_id,
       style.additional_body_classes,
       style.collection,
       style.style_tree_json,
       style.style_timestamp as last_modified_timestamp,
       user.user_username as last_modified_username
   FROM style
   LEFT JOIN user ON style.style_user = user.user_id
   WHERE style.style_id = '" . escape($liveform->get_field_value('id')) . "'";
$result = mysqli_query(db::$con, $query) or output_error('Query failed.');
$row = mysqli_fetch_assoc($result);

$name = trim($row['name']);
$layout = $row['layout'];
$empty_cell_width_percentage = $row['empty_cell_width_percentage'];
$style_head = isset($row['style_head']) ? $row['style_head'] : '';
$social_networking_position = $row['social_networking_position'];
$theme_id = $row['theme_id'];
$additional_body_classes = $row['additional_body_classes'];
$collection = $row['collection'];
$style_tree_json = isset($row['style_tree_json']) ? $row['style_tree_json'] : '';
$last_modified_timestamp = $row['last_modified_timestamp'];
$last_modified_username = $row['last_modified_username'];

// Find the system page linked to this style (used in both GET and POST branches).
// Primary match: page_style = style_id AND page_name = style_name (exact link).
// Fallback match: page_name = style_name only — for styles created before the
// per-page link was introduced; auto-links by setting page_style on that page.
$page_row = false;
$_style_id_esc = escape($liveform->get_field_value('id'));
$_pr_cols = "page_id, page_name, page_folder, page_title,
             page_meta_description,
             page_search, page_search_keywords, sitemap, page_home,
             " . (pg_page_noindex_ready() ? "noindex, nofollow," : "'0' AS noindex, '0' AS nofollow,") . "
             comments, comments_label, comments_allow_new_comments, comments_rating,
             page_custom_css, page_custom_js, page_custom_fonts";
$_pr = mysqli_query(db::$con,
    "SELECT $_pr_cols FROM page
     WHERE page_style = '$_style_id_esc'
       AND page_name = '" . escape($name) . "'
     LIMIT 1") or output_error('Query failed.');
if ($_pr_row = mysqli_fetch_assoc($_pr)) {
    $page_row = $_pr_row;
} else {
    // Fallback: find by page_name only (page might exist but not yet linked via page_style)
    $_pr2 = mysqli_query(db::$con,
        "SELECT $_pr_cols FROM page
         WHERE page_name = '" . escape($name) . "'
           AND layout_type = 'system'
         LIMIT 1") or output_error('Query failed.');
    if ($_pr2_row = mysqli_fetch_assoc($_pr2)) {
        $page_row = $_pr2_row;
        // Auto-link: update page_style so future lookups find it via the primary path
        db("UPDATE page SET page_style = '$_style_id_esc' WHERE page_id = '" . (int)$_pr2_row['page_id'] . "'");
    }
}

// if the form was not just submitted, then prepare to output form
if (!$_POST) {
    // if the form has not been submitted yet, then store data in liveform
    if ($liveform->field_in_session('name') == FALSE) {
        $liveform->assign_field_value('name', $name);
        $liveform->assign_field_value('theme_id', $theme_id);
        $liveform->assign_field_value('additional_body_classes', $additional_body_classes);
        $liveform->assign_field_value('collection', $collection);
        $liveform->assign_field_value('style_head', $style_head);
        $liveform->assign_field_value('style_empty_cell_width_percentage', $empty_cell_width_percentage);

        if (SOCIAL_NETWORKING == TRUE) {
            $liveform->assign_field_value('social_networking_position', $social_networking_position);
        }

        // Page settings — pre-fill from linked system page
        if ($page_row) {
            $liveform->assign_field_value('page_folder',           $page_row['page_folder']);
            // Comments settings
            $liveform->assign_field_value('pg_comments',           isset($page_row['comments'])                  ? ($page_row['comments']                  ? '1' : '0') : '0');
            $liveform->assign_field_value('pg_comments_label',     isset($page_row['comments_label'])            ? $page_row['comments_label']            : '');
            $liveform->assign_field_value('pg_comments_allow_new', isset($page_row['comments_allow_new_comments']) ? ($page_row['comments_allow_new_comments'] ? '1' : '0') : '1');
            $liveform->assign_field_value('pg_comments_rating',    isset($page_row['comments_rating'])           ? ($page_row['comments_rating']           ? '1' : '0') : '0');
            $liveform->assign_field_value('page_title',            $page_row['page_title']);
            $liveform->assign_field_value('page_meta_description', $page_row['page_meta_description']);
            $liveform->assign_field_value('page_search',           $page_row['page_search'] ? '1' : '0');
            $liveform->assign_field_value('page_search_keywords',  $page_row['page_search_keywords']);
            $liveform->assign_field_value('page_sitemap',          $page_row['sitemap'] ? '1' : '0');
            $liveform->assign_field_value('page_noindex',          $page_row['noindex'] ? '1' : '0');
            $liveform->assign_field_value('page_nofollow',         $page_row['nofollow'] ? '1' : '0');
            // page_home is a 'yes'/'' string in DB (legacy); we render it as a
            // 1/0 switch for consistency with the Search / Sitemap toggles.
            $liveform->assign_field_value('page_home',             ($page_row['page_home'] == 'yes') ? '1' : '0');
            // Custom code assets — filter out managed-file entries whose
            // on-disk file was deleted out of band (e.g. via View Files or
            // the designer's "Sil (kalıcı)" without a follow-up Save). The
            // filter returns the JSON unchanged if nothing was orphaned.
            $liveform->assign_field_value('page_custom_css',   _filter_orphan_designer_files(isset($page_row['page_custom_css']) ? $page_row['page_custom_css'] : ''));
            $liveform->assign_field_value('page_custom_js',    _filter_orphan_designer_files(isset($page_row['page_custom_js'])  ? $page_row['page_custom_js']  : ''));
            $liveform->assign_field_value('page_custom_fonts', isset($page_row['page_custom_fonts']) ? $page_row['page_custom_fonts'] : '');
        } else {
            $liveform->assign_field_value('page_search',           '1');
            $liveform->assign_field_value('page_sitemap',          '1');
            $liveform->assign_field_value('page_noindex',          '0');
            $liveform->assign_field_value('page_nofollow',         '0');
            $liveform->assign_field_value('page_home',             '0');
            $liveform->assign_field_value('pg_comments',           '0');
            $liveform->assign_field_value('pg_comments_allow_new', '1');
            $liveform->assign_field_value('pg_comments_rating',    '0');
            $liveform->assign_field_value('pg_comments_label',     '');
            $liveform->assign_field_value('page_custom_css',   '');
            $liveform->assign_field_value('page_custom_js',    '');
            $liveform->assign_field_value('page_custom_fonts', '');
        }
    }
    
    // if a last modified username was not found, then set it to be unknown
    if ($last_modified_username == '') {
        $last_modified_username = '[Unknown]';
    }

    $social_networking_position_options = array();
    $output_modal_social = '';

    if (SOCIAL_NETWORKING == TRUE) {
        $social_networking_position_options = array(
            'Top Left'    => 'top_left',
            'Top Right'   => 'top_right',
            'Bottom Left' => 'bottom_left',
            'Bottom Right'=> 'bottom_right',
            'Disabled'    => 'disabled',
        );
        $output_modal_social =
            '<div class="mb-3">
                <label class="form-label small">' . lang('Social Networking') . '</label>
                ' . $liveform->output_field(array('type'=>'select', 'name'=>'social_networking_position', 'options'=>$social_networking_position_options, 'class'=>'form-select form-select-sm')) . '
            </div>';
    }

    // Prepare region data as JSON for the style designer
    $region_data_json = get_style_designer_regions_as_json();

    // Prepare tree data for the editor.
    // IMPORTANT: raw tree_json must NEVER be echo'd directly into a <script> block — it can contain
    // </script> substrings (e.g. in content nodes) that would prematurely close the tag and break JS.
    // Always round-trip through json_decode → json_encode(JSON_HEX_TAG) to produce safe output.
    $tree_data_js = 'var treeData = null;'; // safe default
    $_raw_tree = '';
    if ($liveform->field_in_session('style_tree_json') && $liveform->get_field_value('style_tree_json') != '') {
        $_raw_tree = $liveform->get_field_value('style_tree_json');
    } elseif (!empty($style_tree_json)) {
        $_raw_tree = $style_tree_json;
    }
    $tree_load_warning = false; // D2: PHP sets true if stored tree could not be parsed
    if ($_raw_tree !== '') {
        $_tree_decoded = json_decode($_raw_tree);
        if ($_tree_decoded !== null) {
            $tree_data_js = 'var treeData = ' .
                json_encode($_tree_decoded, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
        } else {
            // D2: Stored tree is corrupted — start with empty canvas, warn via JS toast
            $tree_load_warning = true;
            log_activity(lang(array('string' => 'style ({var:1}) tree JSON could not be parsed on load — empty canvas shown', 'vars' => array($name))), isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '?');
        }
    }

    // if there is a send to, then set the cancel button URL to the send to
    if ($liveform->get_field_value('send_to') != '') {
        $output_cancel_button_url = h(escape_javascript($liveform->get_field_value('send_to')));
    } else {
        $output_cancel_button_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_styles.php';
    }
    
    // Find if style is being used by a folder
    $query =
        "SELECT
            COUNT(folder_id)
        FROM folder
        WHERE
            (folder_style = '" . escape($liveform->get_field_value('id')) . "')
            OR (mobile_style_id = '" . escape($liveform->get_field_value('id')) . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $folders_using_style = $row[0];
    
    // Find if style is being used by a page
    $query =
        "SELECT
            COUNT(page_id)
        FROM page
        WHERE
            (page_style = '" . escape($liveform->get_field_value('id')) . "')
            OR (mobile_style_id = '" . escape($liveform->get_field_value('id')) . "')";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $row = mysqli_fetch_row($result);
    $pages_using_style = $row[0];

    // if style is being used by either a folder or a page
    if (($folders_using_style > 0) || ($pages_using_style > 0)) {
        $output_delete_button = '<button type="button" class="sd-icon-btn sd-icon-btn-danger disabled" title="' . lang('You may not delete this page style because it is being used by at least one folder or page.') . '"><span class="bi bi-trash"></span></button>';
    } else {
        $output_delete_button = '<button type="submit" name="submit_delete" value="Delete" class="sd-icon-btn sd-icon-btn-danger" title="' . lang('Delete') . '" data-loading-content="' . lang('Deleting') . '" data-confirm-content="' . lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('page style')))) . '"><span class="bi bi-trash"></span></button>';
    }
    
    // Search engine indexing. Only built where the columns behind it exist: a
    // switch that saves nothing is worse than no switch at all.
    //
    // The Visual Editor gives a page no type of its own - everything it creates
    // is 'standard' / 'system' - so unlike the site map switch on the page
    // screen there is no page type to hide this block for.
    $output_noindex_switches = '';
    $output_noindex_hint = '';

    if (pg_page_noindex_ready() == TRUE) {
        $noindex_on = ($liveform->get_field_value('page_noindex') == '1');

        // nofollow qualifies the noindex directive and is meaningless without
        // it, so the master switch is what opens it.
        $nofollow_disabled = ($noindex_on == TRUE) ? '' : ' disabled="disabled"';

        $output_noindex_switches =
            '<div class="form-check form-switch">
                <input type="hidden" name="page_noindex" value="0">
                <input class="form-check-input" type="checkbox" name="page_noindex" id="pg_page_noindex" value="1"
                       ' . (($noindex_on == TRUE) ? 'checked' : '') . '>
                <label class="form-check-label small" for="pg_page_noindex">' . lang('Close to Search Engines (noindex)') . '</label>
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="page_nofollow" value="0">
                <input class="form-check-input" type="checkbox" name="page_nofollow" id="pg_page_nofollow" value="1"
                       ' . (($liveform->get_field_value('page_nofollow') == '1') ? 'checked' : '') . $nofollow_disabled . '>
                <label class="form-check-label small" for="pg_page_nofollow">' . lang('Do Not Follow Links on This Page (nofollow)') . '</label>
            </div>';

        $output_noindex_hint =
            '<div class="form-text small mb-3" id="pg_noindex_hint" style="display: ' . (($noindex_on == TRUE) ? 'block' : 'none') . '">
                ' . lang('The page is served with a noindex robots tag, is blocked in robots.txt and is left out of the site map.') . '
                <span class="text-warning d-block">' . lang('A page blocked in robots.txt is not crawled, so the noindex tag on it is never read. Use this before a page reaches the results; a page that is already listed can take a while to drop out.') . '</span>
            </div>';
    }

    print
        pg_page_shell([
            'title'        => lang('Visual Pinegrap Editor'),
            'extra classes'=> 'design',
            'icon'         => 'design',
            'heading'      => lang('Visual Pinegrap Editor'),
            'hide_menu'    => true,
            'cancel'=>array('enable'=>'true','url'=>pg_safe_back_url($liveform->get_field_value('send_to'), 'view_styles.php')),
            'auto_main'    => false,
        ]) . '
        <link rel="stylesheet" href="assets/fonts/bootstrap-icons/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/style_designer.css?v=' . time() . '">
        <main id="content" style="padding:0; max-width:100%;">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <form id="style_designer_form" name="style_designer_form" action="edit_system_style.php" method="post" style="height:100%;">
                ' . get_token_field() . '
                ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'id')) . '
                ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'send_to')) . '
                <input type="hidden" id="style_tree_json" name="style_tree_json" value="">
                <input type="hidden" id="areas" name="areas" value="">
                <!-- Comments settings — managed by the options panel when a comments_block region is selected -->
                <input type="hidden" name="pg_comments" value="' . h($liveform->get_field_value('pg_comments')) . '">
                <input type="hidden" name="pg_comments_label" value="' . h($liveform->get_field_value('pg_comments_label')) . '">
                <input type="hidden" name="pg_comments_allow_new" value="' . h($liveform->get_field_value('pg_comments_allow_new')) . '">
                <input type="hidden" name="pg_comments_rating" value="' . h($liveform->get_field_value('pg_comments_rating')) . '">
                <!-- Per-page custom code assets — managed by the bottom Assets panel -->
                <input type="hidden" name="page_custom_css" id="sd-custom-css-field" value="' . h($liveform->get_field_value('page_custom_css')) . '">
                <input type="hidden" name="page_custom_js" id="sd-custom-js-field" value="' . h($liveform->get_field_value('page_custom_js')) . '">
                <input type="hidden" name="page_custom_fonts" id="sd-custom-fonts-field" value="' . h($liveform->get_field_value('page_custom_fonts')) . '">

                <div class="sd-wrapper">
                    <!-- Toolbar -->
                    <div class="sd-toolbar">
                        <div class="sd-toolbar-group">
                            <button type="button" id="sd-main-undo" class="sd-vb-btn" onclick="StyleDesigner.undo()" title="' . lang('Undo') . ' (Ctrl+Z)" disabled><span class="bi bi-arrow-counterclockwise"></span> <span class="sd-vb-txt">' . lang('Undo') . '</span></button>
                            <button type="button" id="sd-main-redo" class="sd-vb-btn" onclick="StyleDesigner.redo()" title="' . lang('Redo') . ' (Ctrl+Y)" disabled><span class="bi bi-arrow-clockwise"></span> <span class="sd-vb-txt">' . lang('Redo') . '</span></button>
                        </div>
                        <div class="sd-toolbar-title">
                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'name', 'id'=>'sd-style-name', 'class'=>'sd-title-input', 'maxlength'=>'100', 'placeholder'=>lang('Page name...'))) . '
                        </div>
                        <div class="sd-toolbar-group sd-toolbar-actions">
                            <button type="button" id="sd-ajax-save" name="submit_save" value="Save" class="sd-icon-btn sd-icon-btn-primary" title="' . lang('Save') . '"><span class="bi bi-floppy"></span></button>
                            <a href="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/duplicate_style.php?id=' . h(escape_javascript($liveform->get_field_value('id'))) . get_token_query_string_field() . '" class="sd-icon-btn" title="' . lang('Duplicate') . '"><span class="bi bi-copy"></span></a>
                            <button type="button" class="sd-icon-btn" data-bs-toggle="modal" data-bs-target="#styleSettingsModal" title="' . lang('Settings') . '"><span class="bi bi-gear"></span></button>
                            ' . $output_delete_button . '
                        </div>
                    </div>

                    <!-- 3-Panel Body -->
                    <div class="sd-body">
                        <div class="sd-panel-left" id="sd-components"></div>
                        <div class="sd-canvas-wrapper">
                            <div class="sd-canvas" id="sd-canvas"></div>
                        </div>
                        <div class="sd-panel-right" id="sd-panel-right">
                            <div id="sd-properties"></div>
                        </div>
                    </div>

                    <!-- Status Bar -->
                    <div class="sd-statusbar" id="sd-statusbar">
                        <span class="text-muted small" id="sd-last-modified-label">' . get_relative_time(array('timestamp' => $last_modified_timestamp)) . ' ' . lang(array('string' => 'by {var:1}', 'vars' => array(h($last_modified_username)))) . '</span>
                    </div>
                </div>

                <!-- Settings Modal — full-screen, left-side tabs -->
                <div class="modal fade" id="styleSettingsModal" tabindex="-1" aria-labelledby="styleSettingsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-fullscreen">
                        <div class="modal-content" style="background:#1e2127; color:#c9d1d9; border:none;">

                            <!-- Header -->
                            <div class="modal-header" style="border-color:#30363d; flex-shrink:0;">
                                <h5 class="modal-title fs-6" id="styleSettingsModalLabel"><span class="bi bi-gear me-2"></span>' . lang('Style Settings') . '</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>

                            <!-- Body: left nav + right pane -->
                            <div class="modal-body p-0 d-flex overflow-hidden" style="flex:1 1 auto;">

                                <!-- Left tab nav -->
                                <div style="width:200px; flex-shrink:0; background:#161b22; border-right:1px solid #30363d; padding:1rem 0; overflow-y:auto;">
                                    <nav class="nav flex-column nav-pills px-2" id="settingsTabs" role="tablist">
                                        <button class="nav-link active text-start mb-1" id="tab-style-btn"
                                                data-bs-toggle="pill" data-bs-target="#tab-style"
                                                type="button" role="tab" style="color:#c9d1d9; font-size:.85rem;">
                                            <span class="bi bi-palette me-2"></span>' . lang('Style') . '
                                        </button>
                                        <button class="nav-link text-start mb-1" id="tab-page-btn"
                                                data-bs-toggle="pill" data-bs-target="#tab-page"
                                                type="button" role="tab" style="color:#c9d1d9; font-size:.85rem;">
                                            <span class="bi bi-file-earmark me-2"></span>' . lang('Page') . '
                                        </button>
                                    </nav>
                                </div>

                                <!-- Right tab content -->
                                <div class="tab-content flex-grow-1 overflow-y-auto p-4" style="max-width:640px;">

                                    <!-- Tab: Style -->
                                    <div class="tab-pane fade show active" id="tab-style" role="tabpanel">
                                        <div class="mb-3">
                                            <label class="form-label small">' . lang('Theme') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'theme_id', 'options'=>get_theme_options(), 'class'=>'form-select form-select-sm')) . '
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small">' . lang('Collection') . '</label>
                                            ' . $liveform->output_field(array('type'=>'select', 'name'=>'collection', 'options'=>array('A'=>'a', 'B'=>'b'), 'class'=>'form-select form-select-sm')) . '
                                        </div>
                                        ' . $output_modal_social . '
                                        <div class="mb-3">
                                            <label class="form-label small">' . lang('Additional Body Classes') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'additional_body_classes', 'class'=>'form-control form-control-sm')) . '
                                        </div>
                                        ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'style_head')) . '
                                        ' . $liveform->output_field(array('type'=>'hidden', 'name'=>'style_empty_cell_width_percentage')) . '
                                    </div>

                                    <!-- Tab: Page -->
                                    <div class="tab-pane fade" id="tab-page" role="tabpanel">
                                        ' . ($page_row ? '' : '<div class="alert alert-secondary py-2 small mb-3">' . lang('No linked page found. A page will be created automatically on save.') . '</div>') . '
                                        <div class="mb-3">
                                            <label class="form-label small">' . lang('Page Title') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'page_title', 'id'=>'pg_page_title', 'class'=>'form-control form-control-sm')) . '
                                            <div id="seo_c_pg_page_title"></div>
                                            <small class="form-text">' . lang('Appears in browser tab and search results. (30–60 chars recommended)') . '</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small">' . lang('Page Folder') . '</label>
                                            <select name="page_folder" class="form-select form-select-sm" style="background-color:#0d1117; color:#c9d1d9; border-color:#30363d;">
                                                ' . select_folder((int)$liveform->get_field_value('page_folder')) . '
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small">' . lang('Meta Description') . '</label>
                                            ' . $liveform->output_field(array('type'=>'textarea', 'name'=>'page_meta_description', 'id'=>'pg_page_meta_description', 'class'=>'form-control form-control-sm', 'rows'=>'3')) . '
                                            <div id="seo_c_pg_page_meta_description"></div>
                                            <small class="form-text">' . lang('Appears in search engine results as page summary. (150–160 chars recommended)') . '</small>
                                        </div>
                                        <div class="mb-3 d-flex flex-wrap gap-4">
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="page_search" value="0">
                                                <input class="form-check-input" type="checkbox" name="page_search" id="pg_page_search" value="1"
                                                       ' . ($liveform->get_field_value('page_search') == '1' ? 'checked' : '') . '
                                                       onchange="document.getElementById(\'pg_search_kw_row\').style.display=this.checked?\'block\':\'none\'">
                                                <label class="form-check-label small" for="pg_page_search">' . lang('Search') . '</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input type="hidden" name="page_sitemap" value="0">
                                                <input class="form-check-input" type="checkbox" name="page_sitemap" id="pg_page_sitemap" value="1"
                                                       ' . ($liveform->get_field_value('page_sitemap') == '1' ? 'checked' : '') . '>
                                                <label class="form-check-label small" for="pg_page_sitemap">' . lang('Sitemap') . '</label>
                                            </div>
                                            ' . (($user['role'] < 3) ? '<div class="form-check form-switch">
                                                <input type="hidden" name="page_home" value="0">
                                                <input class="form-check-input" type="checkbox" name="page_home" id="pg_page_home" value="1"
                                                       ' . ($liveform->get_field_value('page_home') == '1' ? 'checked' : '') . '>
                                                <label class="form-check-label small" for="pg_page_home">' . lang('Set As Homepage') . '</label>
                                            </div>' : '') . '
                                            ' . $output_noindex_switches . '
                                        </div>
                                        ' . $output_noindex_hint . '
                                        <div id="pg_search_kw_row" class="mb-0" style="display:' . ($liveform->get_field_value('page_search') == '1' ? 'block' : 'none') . ';">
                                            <label class="form-label small">' . lang('Search Keywords') . '</label>
                                            <input type="text" value="' . h($liveform->get_field_value('page_search_keywords')) . '" name="page_search_keywords" id="pg_search_keywords" class="form-control form-control-sm tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '">
                                            <script>if(document.body.contains(document.querySelector("#pg_search_keywords"))){tagin(document.querySelector("#pg_search_keywords"));}</script>
                                            <small class="form-text">' . lang('Keywords used for site-wide search indexing.') . '</small>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="modal-footer" style="border-color:#30363d; flex-shrink:0;">
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">' . lang('Close') . '</button>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">' . lang('Apply') . '</button>
                            </div>

                        </div>
                    </div>
                </div>
            </form>
        </main>
        ' . get_codemirror_includes() . '
        <script src="assets/codemirror_modal.js?v=' . time() . '"></script>
        <script src="assets/class_suggestions.js?v=' . time() . '"></script>
        <script src="assets/style_designer.js?v=' . time() . '"></script>
        <script>
            // Expose Pinegrap native URL path prefix to JS (e.g. "/" or "/subfolder/")
            window.OUTPUT_PATH = "' . h(escape_javascript(OUTPUT_PATH)) . '";
            var sdRegionData = ' . $region_data_json . ';
            ' . $tree_data_js . '
            $(document).ready(function() {
                StyleDesigner.init({
                    regionData: sdRegionData,
                    treeData: treeData,
                    treeLoadWarning: ' . ($tree_load_warning ? 'true' : 'false') . '
                });

                // Init SEO counters when settings modal is shown
                document.getElementById("styleSettingsModal").addEventListener("shown.bs.modal", function () {
                    if (typeof initSeoCounters === "function") {
                        initSeoCounters([
                            { sel: "#pg_page_title",            counterId: "seo_c_pg_page_title",            min: 30,  max: 60  },
                            { sel: "#pg_page_meta_description", counterId: "seo_c_pg_page_meta_description", min: 150, max: 160 }
                        ]);
                    }
                    if (typeof bindPageIndexingSwitches === "function") {
                        bindPageIndexingSwitches({
                            noindex:  "pg_page_noindex",
                            nofollow: "pg_page_nofollow",
                            sitemap:  "pg_page_sitemap",
                            hint:     "pg_noindex_hint"
                        });
                    }
                });

                // AJAX save: button is type="button" now — fetch + toast, no
                // page navigation. Server detects ajax=1 and replies with JSON.
                var _saveBtn = document.getElementById("sd-ajax-save");
                if (_saveBtn) {
                    _saveBtn.addEventListener("click", function (e) {
                        e.preventDefault();
                        StyleDesigner.saveAjax({ button: _saveBtn });
                    });
                }
            });
        </script>';
    
    $liveform->remove_form();

// else the form has been submitted, so process the form
} else {
    validate_token_field();

    $liveform->add_fields_to_session();

    // AJAX mode: when the designer's Save button POSTs via fetch, the page
    // stays mounted — so we emit JSON and skip every redirect below. Detected
    // via either the standard XHR header or an explicit `ajax=1` form field.
    $is_ajax = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || !empty($_POST['ajax'])
    );
    
    // if the user selected to delete the style, then delete it
    if ($liveform->get_field_value('submit_delete') == 'Delete') {
        // Find if style is being used by a folder
        $query =
            "SELECT
                COUNT(folder_id)
            FROM folder
            WHERE
                (folder_style = '" . escape($liveform->get_field_value('id')) . "')
                OR (mobile_style_id = '" . escape($liveform->get_field_value('id')) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $folders_using_style = $row[0];
        
        // Find if style is being used by a page
        $query =
            "SELECT
                COUNT(page_id)
            FROM page
            WHERE
                (page_style = '" . escape($liveform->get_field_value('id')) . "')
                OR (mobile_style_id = '" . escape($liveform->get_field_value('id')) . "')";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
        $row = mysqli_fetch_row($result);
        $pages_using_style = $row[0];

        // if style is being used by either a folder or a page, output error
        if (($folders_using_style > 0) || ($pages_using_style > 0)) {
            output_error(lang('You may not delete this page style because it is being used by at least one folder or page.'));
        
        // else the style is not being used by a folder or page, so delete the style
        } else {
            $query = "DELETE FROM style WHERE style_id = '" . escape($liveform->get_field_value('id')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
            
            $query = "DELETE FROM system_style_cells WHERE style_id = '" . escape($liveform->get_field_value('id')) . "'";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            db("DELETE FROM preview_styles WHERE style_id = '" . escape($liveform->get_field_value('id')) . "'");
            
            log_activity(lang(array('string' => 'style ({var:1}) was deleted', 'vars' => array($name))), $_SESSION['sessionusername']);
            $notice = lang('The style has been deleted.');
        }
        
    // else the user selected to save the style, so save it
    } else {
        $liveform->validate_required_field('name', lang('Name is required.'));

        // if there is not already an error for the name field, then check if name is already in use
        if ($liveform->check_field_error('name') == FALSE) {
            $query = "SELECT style_id FROM style WHERE (style_name = '" . escape($liveform->get_field_value('name')) . "') AND (style_id != '" . escape($liveform->get_field_value('id')) . "')";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // if name is already in use by a different style, prepare error
            if (mysqli_num_rows($result) > 0) {
                $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
            }
        }
        
        // Page name = style name — validate page name uniqueness (excluding linked page)
        if ($liveform->check_field_error('name') == FALSE) {
            $_pn_check_sql = "SELECT page_id FROM page WHERE page_name = '" . escape(trim($liveform->get_field_value('name'))) . "'"
                           . ($page_row ? " AND page_id != '" . (int)$page_row['page_id'] . "'" : '');
            $_pn_result = mysqli_query(db::$con, $_pn_check_sql) or output_error('Query failed.');
            if (mysqli_num_rows($_pn_result) > 0) {
                $liveform->mark_error('name', lang('The page name that you entered is already in use.'));
            }
        }

        // D5: Server-side tree validation (safety net for bypassed JS validation)
        $_tree_raw = $liveform->get_field_value('style_tree_json');
        $_vt = _validate_and_clean_tree_json($_tree_raw);
        if (!$_vt['ok']) {
            $liveform->add_error($_vt['error']);
        }
        if (!empty($_vt['warnings'])) {
            foreach ($_vt['warnings'] as $_w) { $liveform->add_notice($_w); }
        }

        // if there is an error, forward user back to previous screen
        if ($liveform->check_form_errors() == TRUE) {
            if ($is_ajax) {
                // Collect every field-level error message into a flat array so the
                // JS toast can show them. Pulling directly from the liveform session
                // because there is no public accessor for the full error list.
                $_ajax_errors = array();
                $_ajax_session = isset($_SESSION['software']['liveforms']['edit_system_style'][$liveform->index])
                                    ? $_SESSION['software']['liveforms']['edit_system_style'][$liveform->index]
                                    : array();
                if (is_array($_ajax_session)) {
                    foreach ($_ajax_session as $_field) {
                        if (is_array($_field) && !empty($_field['error']) && !empty($_field['error_message'])) {
                            $_ajax_errors[] = $_field['error_message'];
                        }
                    }
                }
                header('Content-Type: application/json; charset=utf-8');
                echo encode_json(array(
                    'status'  => 'error',
                    'message' => $_ajax_errors ? implode(' ', $_ajax_errors) : lang('An error occurred'),
                    'errors'  => $_ajax_errors,
                ));
                exit();
            }
            header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . $liveform->get_field_value('id') . '&send_to=' . urlencode($liveform->get_field_value('send_to')));
            exit();
        }

        // Use cleaned_json (orphans removed) if available
        $_tree_to_save = (!empty($_vt['cleaned_json'])) ? $_vt['cleaned_json'] : $_tree_raw;

        save_system_style(array(
            'style_id'                          => $liveform->get_field_value('id'),
            'name'                              => trim($liveform->get_field_value('name')),
            'style_tree_json'                   => $_tree_to_save,
            'theme_id'                          => $liveform->get_field_value('theme_id'),
            'additional_body_classes'           => $liveform->get_field_value('additional_body_classes'),
            'collection'                        => $liveform->get_field_value('collection'),
            'social_networking_position'        => $liveform->get_field_value('social_networking_position'),
            'style_head'                        => $liveform->get_field_value('style_head'),
            'style_empty_cell_width_percentage' => $liveform->get_field_value('style_empty_cell_width_percentage'),
            'user_id'                           => $user['id'],
        ));
        
        log_activity(lang(array('string' => 'style ({var:1}) was modified', 'vars' => array($liveform->get_field_value('name')))), $_SESSION['sessionusername']);

        // Save or create linked system page (page name always = style name)
        $edit_page_name = trim($liveform->get_field_value('name'));
        // Read checkbox fields directly from $_POST (same pattern as page_custom_css above)
        // because the hidden+checkbox pattern and tagin library may not propagate reliably
        // through liveform's session for fields not registered via output_field().
        $pg_search  = (isset($_POST['page_search'])  ? $_POST['page_search']  : $liveform->get_field_value('page_search'))  == '1' ? 1 : 0;
        $pg_sitemap = (isset($_POST['page_sitemap']) ? $_POST['page_sitemap'] : $liveform->get_field_value('page_sitemap')) == '1' ? 1 : 0;

        $pg_noindex = 0;
        $pg_nofollow = 0;

        // nofollow qualifies the noindex directive and is never emitted on its
        // own, so it is only stored while the page is closed to search engines.
        if (
            ((isset($_POST['page_noindex']) ? $_POST['page_noindex'] : $liveform->get_field_value('page_noindex')) == '1')
            && (pg_page_noindex_ready() == TRUE)
        ) {
            $pg_noindex = 1;

            if ((isset($_POST['page_nofollow']) ? $_POST['page_nofollow'] : $liveform->get_field_value('page_nofollow')) == '1') {
                $pg_nofollow = 1;
            }

            // A page that is closed to search engines has no business in the
            // site map. The switch is disabled on screen while noindex is on,
            // but a POST does not have to come from that screen.
            $pg_sitemap = 0;
        }

        $sql_noindex_set = '';
        $sql_noindex_columns = '';
        $sql_noindex_values = '';

        if (pg_page_noindex_ready() == TRUE) {
            $sql_noindex_set = "noindex = '" . $pg_noindex . "', nofollow = '" . $pg_nofollow . "',";
            $sql_noindex_columns = 'noindex, nofollow,';
            $sql_noindex_values = "'" . $pg_noindex . "', '" . $pg_nofollow . "',";
        }
        // page_home is gated to role < 3 (admin/manager/designer); contributors
        // never have the switch rendered, but we still cross-check role here so
        // a crafted POST cannot promote the page to home. DB column stores the
        // legacy 'yes' / '' string.
        $pg_home_post = (isset($_POST['page_home']) ? $_POST['page_home'] : $liveform->get_field_value('page_home')) == '1' ? 1 : 0;
        $pg_home_str  = ((int)$user['role'] < 3 && $pg_home_post) ? 'yes' : '';
        $pg_folder  = (int) $liveform->get_field_value('page_folder');
        $pg_search_keywords_raw = isset($_POST['page_search_keywords']) ? $_POST['page_search_keywords'] : $liveform->get_field_value('page_search_keywords');
        $pg_search_keywords = ($pg_search == 1) ? $pg_search_keywords_raw : '';

        $edit_comments       = ($liveform->get_field_value('pg_comments')           == '1') ? 1 : 0;
        $edit_comments_allow = ($liveform->get_field_value('pg_comments_allow_new') == '1') ? 1 : 0;
        $edit_comments_rate  = ($liveform->get_field_value('pg_comments_rating')    == '1') ? 1 : 0;
        $edit_comments_label = trim($liveform->get_field_value('pg_comments_label'));
        // These hidden inputs are not registered via output_field() so liveform doesn't
        // pick them up from $_POST — read directly from $_POST to get the submitted value.
        $edit_custom_css     = isset($_POST['page_custom_css'])   ? $_POST['page_custom_css']   : $liveform->get_field_value('page_custom_css');
        $edit_custom_js      = isset($_POST['page_custom_js'])    ? $_POST['page_custom_js']    : $liveform->get_field_value('page_custom_js');
        $edit_custom_fonts   = isset($_POST['page_custom_fonts']) ? $_POST['page_custom_fonts'] : $liveform->get_field_value('page_custom_fonts');
        // page_title / meta_description: read from POST directly as well (safe after remove_form() AJAX cycle)
        $edit_page_title     = isset($_POST['page_title'])            ? $_POST['page_title']            : $liveform->get_field_value('page_title');
        $edit_page_meta_desc = isset($_POST['page_meta_description']) ? $_POST['page_meta_description'] : $liveform->get_field_value('page_meta_description');

        if ($page_row) {
            // Update existing linked page. page_home only updated for users
            // with permission (role < 3); other roles keep the existing flag.
            // page_type stays 'standard' for system-layout pages — keeping it
            // distinct from legacy types ('catalog', 'catalog detail') means
            // the legacy renderer's hardcoded product card / cart form does
            // NOT bleed into a system-widget detail page. URL parsing for
            // detail-page address_name segments is handled by the system
            // widget itself instead.
            $pg_home_set = ((int)$user['role'] < 3) ? "page_home = '" . e($pg_home_str) . "'," : '';
            db("UPDATE page SET
                    page_name                   = '" . e($edit_page_name) . "',
                    page_folder                 = '" . $pg_folder . "',
                    page_title                  = '" . e($edit_page_title) . "',
                    page_meta_description       = '" . e($edit_page_meta_desc) . "',
                    page_search                 = '" . $pg_search . "',
                    page_search_keywords        = '" . e($pg_search_keywords) . "',
                    sitemap                     = '" . $pg_sitemap . "',
                    " . $sql_noindex_set . "
                    " . $pg_home_set . "
                    comments                    = '" . $edit_comments . "',
                    comments_label              = '" . e($edit_comments_label) . "',
                    comments_allow_new_comments = '" . $edit_comments_allow . "',
                    comments_rating             = '" . $edit_comments_rate . "',
                    page_custom_css             = '" . e($edit_custom_css) . "',
                    page_custom_js              = '" . e($edit_custom_js) . "',
                    page_custom_fonts           = '" . e($edit_custom_fonts) . "',
                    page_timestamp              = UNIX_TIMESTAMP(),
                    page_user                   = '" . (int)$user['id'] . "'
                WHERE page_id = '" . (int)$page_row['page_id'] . "'");

            // The tag cloud is built from the promote on keyword list of the pages that
            // are in the site search, so it has to follow both of those.
            update_tag_cloud_keywords_for_page(
                $page_row['page_id'],
                $pg_search,
                $pg_search_keywords,
                $page_row['page_search'],
                $page_row['page_search_keywords']);

            log_activity(lang(array('string' => 'page ({var:1}) was modified', 'vars' => array($edit_page_name))), $_SESSION['sessionusername']);
        } else {
            // No linked page yet — create one automatically
            db("INSERT INTO page (page_name, page_folder, page_type, layout_type, page_home, page_search,
                    page_search_keywords, page_timestamp, page_user, page_style, page_title,
                    page_meta_description, comments_disallow_new_comment_message, sitemap,
                    $sql_noindex_columns
                    comments, comments_label, comments_allow_new_comments, comments_rating,
                    page_custom_css, page_custom_js, page_custom_fonts)
                VALUES (
                    '" . e($edit_page_name) . "',
                    '" . $pg_folder . "',
                    'standard', 'system', '" . e($pg_home_str) . "',
                    '" . $pg_search . "',
                    '" . e($pg_search_keywords) . "',
                    UNIX_TIMESTAMP(),
                    '" . e($user['id']) . "',
                    '" . e($liveform->get_field_value('id')) . "',
                    '" . e($edit_page_title) . "',
                    '" . e($edit_page_meta_desc) . "',
                    '',
                    '" . $pg_sitemap . "',
                    $sql_noindex_values
                    '" . $edit_comments . "',
                    '" . e($edit_comments_label) . "',
                    '" . $edit_comments_allow . "',
                    '" . $edit_comments_rate . "',
                    '" . e($edit_custom_css) . "',
                    '" . e($edit_custom_js) . "',
                    '" . e($edit_custom_fonts) . "'
                )");

            update_tag_cloud_keywords_for_page(mysqli_insert_id(db::$con), $pg_search, $pg_search_keywords, 0, '');

            log_activity(lang(array('string' => 'page ({var:1}) was created', 'vars' => array($edit_page_name))), $_SESSION['sessionusername']);
        }

        $notice = lang('The style has been saved.');

        // Sync inline CSS / JS / JSON asset files to the physical file system.
        // IMPORTANT: uses @mysqli_query() directly (NOT db()/db_value()) so that any
        // MySQL error is silently ignored and does NOT call output_error() — which would
        // output HTML before the AJAX JSON response and break the save flow entirely.
        if (defined('FILE_DIRECTORY_PATH') && db::$con) {
            foreach (array($edit_custom_css, $edit_custom_js) as $_json_field) {
                $_assets = @json_decode($_json_field, true);
                if (!is_array($_assets)) continue;
                foreach ($_assets as $_asset) {
                    if (empty($_asset['type']) || empty($_asset['name']) || !isset($_asset['content'])) continue;
                    if (!in_array($_asset['type'], array('css', 'js', 'json'))) continue; // skip external-* links
                    $_ext = strtolower(pathinfo($_asset['name'], PATHINFO_EXTENSION));
                    if (!in_array($_ext, array('css', 'js', 'json'))) continue;
                    // Write file to disk (error suppressed — non-fatal)
                    @file_put_contents(FILE_DIRECTORY_PATH . '/' . $_asset['name'], $_asset['content']);
                    $_size = (int) strlen($_asset['content']);
                    $_nm   = mysqli_real_escape_string(db::$con, $_asset['name']);
                    $_ex   = mysqli_real_escape_string(db::$con, $_ext);
                    $_uid  = (int) $user['id'];
                    // Check if file record already exists
                    $_chk = @mysqli_query(db::$con, "SELECT name FROM files WHERE name = '$_nm' LIMIT 1");
                    if ($_chk && mysqli_num_rows($_chk) === 0) {
                        @mysqli_query(db::$con,
                            "INSERT INTO files (name, folder, description, type, size, user, timestamp)
                             VALUES ('$_nm', '0', '', '$_ex', '$_size', '$_uid', UNIX_TIMESTAMP())");
                    } else {
                        @mysqli_query(db::$con,
                            "UPDATE files SET size = '$_size', timestamp = UNIX_TIMESTAMP()
                             WHERE name = '$_nm'");
                    }
                }
            }
        }
    }

    // AJAX save: skip every redirect — the designer keeps the user on the page
    // and shows a toast based on the JSON response. Liveform is cleared so the
    // next page load doesn't carry stale field state.
    if ($is_ajax) {
        $liveform->remove_form();
        header('Content-Type: application/json; charset=utf-8');
        echo encode_json(array(
            'status'     => 'success',
            'message'    => $notice,
            'saved_ts'   => time(),
            'saved_by'   => isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '',
        ));
        exit();
    }

    // if there is a send to set, then forward user to send to
    if ($liveform->get_field_value('send_to') != '') {
        header('Location: ' . URL_SCHEME . HOSTNAME . $liveform->get_field_value('send_to'));

    // else there is not a send to set, so forward user to view styles screen
    } else {
        $liveform_view_styles = new liveform('view_styles');
        $liveform_view_styles->add_notice($notice);
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_styles.php');
    }

    $liveform->remove_form();
}
?>