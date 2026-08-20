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
$liveform = new liveform('add_system_style');

// if the form was not just submitted, then prepare to output form
if (!$_POST) {
    // if the form has not been submitted yet, then add fields to session and set default values
    if (isset($_SESSION['software']['liveforms']['add_system_style'][0]) == FALSE) {
        $liveform->add_fields_to_session();

        // If social networking is enabled, then set default value for position field.
        if (SOCIAL_NETWORKING == TRUE) {
            $liveform->assign_field_value('social_networking_position', 'bottom_left');
        }

        // Default page settings
        $liveform->assign_field_value('page_search', '1');
        $liveform->assign_field_value('page_sitemap', '1');
        $liveform->assign_field_value('page_home',   '0');
        $liveform->assign_field_value('page_noindex',  '0');
        $liveform->assign_field_value('page_nofollow', '0');

        // Default comments settings
        $liveform->assign_field_value('pg_comments', '0');
        $liveform->assign_field_value('pg_comments_allow_new', '1');
        $liveform->assign_field_value('pg_comments_rating', '0');
        $liveform->assign_field_value('pg_comments_label', '');
        $liveform->assign_field_value('page_custom_css',   '');
        $liveform->assign_field_value('page_custom_js',    '');
        $liveform->assign_field_value('page_custom_fonts', '');
    }

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

    // Prepare region data for JavaScript using existing function
    $region_data_json = get_style_designer_regions_as_json();

    // If there is a saved tree JSON (session restore after validation failure), decode and
    // re-encode with JSON_HEX_TAG so </script> inside content nodes never breaks the page.
    $tree_json = 'null';
    if ($liveform->field_in_session('style_tree_json') && $liveform->get_field_value('style_tree_json') != '') {
        $_raw_tree = $liveform->get_field_value('style_tree_json');
        $_tree_decoded = json_decode($_raw_tree);
        if ($_tree_decoded !== null) {
            $tree_json = json_encode($_tree_decoded, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    // Detect if opened from pages list — used to redirect back after save.
    // Also check liveform session so the hidden field stays correct after a validation error redirect.
    $from_pages = (isset($_GET['from']) && ($_GET['from'] ?? '') === 'pages')
               || ($liveform->field_in_session('from') && $liveform->get_field_value('from') === 'pages');

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

    // Output page
    echo
        pg_page_shell([
            'title'        => lang('Visual Pinegrap Editor'),
            'extra classes'=> 'design',
            'icon'         => 'design',
            'heading'      => lang('Visual Pinegrap Editor'),
            'hide_menu'    => true,
            'cancel'=>array('enable'=>'true','url'=>($from_pages ? 'view_pages.php' : 'view_styles.php')),
            'auto_main'    => false,
        ]) . '
        <link rel="stylesheet" href="assets/fonts/bootstrap-icons/bootstrap-icons.min.css">
        <link rel="stylesheet" href="assets/style_designer.css?v=' . time() . '">
        <main id="content" style="padding:0; max-width:100%;">
            ' . $liveform->output_errors() . '
            ' . $liveform->output_notices() . '
            <form id="style_designer_form" name="style_designer_form" action="add_system_style.php" method="post" style="height:100%;">
                ' . get_token_field() . '
                <input type="hidden" id="style_tree_json" name="style_tree_json" value="">
                <input type="hidden" id="areas" name="areas" value="">
                <input type="hidden" name="layout" value="visual_designer">
                <input type="hidden" name="from" value="' . ($from_pages ? 'pages' : '') . '">
                <!-- Comments settings — managed by the options panel when a comments_block region is selected -->
                <input type="hidden" name="pg_comments" value="' . $liveform->get_field_value('pg_comments') . '">
                <input type="hidden" name="pg_comments_label" value="' . h($liveform->get_field_value('pg_comments_label')) . '">
                <input type="hidden" name="pg_comments_allow_new" value="' . $liveform->get_field_value('pg_comments_allow_new') . '">
                <input type="hidden" name="pg_comments_rating" value="' . $liveform->get_field_value('pg_comments_rating') . '">
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
                            <button type="button" id="sd-ajax-save" class="sd-icon-btn sd-icon-btn-primary" title="' . lang('Create and Save') . '"><span class="bi bi-floppy"></span></button>
                            <button type="button" class="sd-icon-btn" data-bs-toggle="modal" data-bs-target="#styleSettingsModal" title="' . lang('Settings') . '"><span class="bi bi-gear"></span></button>
                        </div>
                    </div>

                    <!-- 3-panel body -->
                    <div class="sd-body">
                        <div class="sd-panel-left" id="sd-components"></div>
                        <div class="sd-canvas-wrapper">
                            <div class="sd-canvas" id="sd-canvas"></div>
                        </div>
                        <div class="sd-panel-right" id="sd-panel-right">
                            <div id="sd-properties"></div>
                        </div>
                    </div>

                    <!-- Status bar -->
                    <div class="sd-statusbar" id="sd-statusbar"></div>
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
                                            <span class="bi bi-file-earmark-plus me-2"></span>' . lang('Page') . '
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
                                    </div>

                                    <!-- Tab: Page -->
                                    <div class="tab-pane fade" id="tab-page" role="tabpanel">
                                        <div class="mb-3">
                                            <label class="form-label small">' . lang('Page Title') . '</label>
                                            ' . $liveform->output_field(array('type'=>'text', 'name'=>'page_title', 'id'=>'pg_page_title', 'class'=>'form-control form-control-sm')) . '
                                            <div id="seo_c_pg_page_title"></div>
                                            <small class="form-text">' . lang('Appears in browser tab and search results. (30–60 chars recommended)') . '</small>
                                        </div>
                                        <div class="mb-3">
                                            <label for="page_folder" class="form-label">' . lang('Folder') . '</label>
                                            <select class="form-select" id="page_folder" name="page_folder">' . select_folder((int)$liveform->get_field_value('page_folder')) . '</select>
                                            <div class="form-text">' . lang('Page Access Control, Design &amp; Common Content') . '</div>
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
                                            <label class="form-label" for="page_search_keywords">' . lang('Search Keywords') . '</label>
                                            <input value="' . h($liveform->get_field_value('page_search_keywords')) . '" type="text" name="page_search_keywords" id="page_search_keywords" class="form-control tagin min-height-tagin" data-placeholder="' . lang('Add tags') . '">
                                            <div class="form-text">' . lang('Keywords used for site-wide search indexing.') . '</div>
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
            var sdRegionData = ' . $region_data_json . ';
            document.addEventListener("DOMContentLoaded", function() {
                StyleDesigner.init({
                    regionData: sdRegionData,
                    treeData: ' . $tree_json . '
                });
            });
            // AJAX save: button is type="button" — fetch + toast, no full page
            // reload on validation errors so the in-progress design is preserved.
            // Server detects ajax=1 and replies with JSON; on success it returns
            // a redirect_url that points to the new edit_system_style.php?id=...
            document.addEventListener("DOMContentLoaded", function () {
                var _saveBtn = document.getElementById("sd-ajax-save");
                if (_saveBtn) {
                    _saveBtn.addEventListener("click", function (e) {
                        e.preventDefault();
                        StyleDesigner.saveAjax({ button: _saveBtn });
                    });
                }
            });
            // Init SEO counters and tagin when the settings modal is shown
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
                if (typeof tagin === "function") {
                    var kwInput = document.querySelector("#page_search_keywords");
                    // tagin inserts its wrapper as the next sibling of the input
                    // (insertAdjacentHTML afterend), so a closest/parent check
                    // never matches and a fresh wrapper was added every time
                    // the modal opened. Guard via nextElementSibling instead.
                    var alreadyInited = kwInput
                        && kwInput.nextElementSibling
                        && kwInput.nextElementSibling.classList.contains("tagin-wrapper");
                    if (kwInput && !alreadyInited) {
                        tagin(kwInput);
                    }
                }
            });
        </script>';

    $liveform->remove_form();

// else the form has been submitted, so process the form
} else {
    validate_token_field();

    $liveform->add_fields_to_session();

    // AJAX mode: when the designer's Save button POSTs via fetch, we keep the
    // user on the page so the in-progress design is never lost on a validation
    // error. Detected via either the standard XHR header or `ajax=1` field.
    $is_ajax = (
        (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || !empty($_POST['ajax'])
    );

    // Clear synthetic form-level errors from any previous submit; the checks
    // below re-mark them only if the new POST is still invalid. Without this,
    // a fixed AJAX retry would still see stale errors and bounce.
    $liveform->unmark_error('_form_folder');
    $liveform->unmark_error('_form_tree');

    $liveform->validate_required_field('name', lang('Name is required.'));

    // if there is not already an error for the name field, then check if name is already in use
    if ($liveform->check_field_error('name') == FALSE) {
        $query = "SELECT style_id FROM style WHERE style_name = '" . escape($liveform->get_field_value('name')) . "'";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // if name is already in use by a different style, prepare error
        if (mysqli_num_rows($result) > 0) {
            $liveform->mark_error('name', lang('The name that you entered is already in use, so please enter a different name.'));
        }
    }

    // Folder is required — pages must always belong to a folder.
    // Use mark_error() with a synthetic field name because the liveform class
    // has no form-level add_error(); mark_error stores the message and makes
    // check_form_errors() return TRUE so the redirect/AJAX branch fires.
    if ((int)$liveform->get_field_value('page_folder') < 1) {
        $liveform->mark_error('_form_folder', lang('Folder is required.'));
    }

    // Page name is always the same as the style name — validate uniqueness
    $new_page_name = trim($liveform->get_field_value('name'));
    if ($new_page_name !== '' && $liveform->check_field_error('name') == FALSE) {
        $page_check = mysqli_query(db::$con, "SELECT page_id FROM page WHERE page_name = '" . escape($new_page_name) . "'") or output_error('Query failed.');
        if (mysqli_num_rows($page_check) > 0) {
            $liveform->mark_error('name', lang('The page name that you entered is already in use.'));
        }
    }

    $tree_json_raw = $liveform->get_field_value('style_tree_json');

    // D5: Server-side tree validation before saving — same synthetic-field
    // pattern as the folder check above.
    $_vt = _validate_and_clean_tree_json($tree_json_raw);
    if (!$_vt['ok']) {
        $liveform->mark_error('_form_tree', $_vt['error']);
    }
    if (!empty($_vt['warnings'])) {
        foreach ($_vt['warnings'] as $_w) { $liveform->add_notice($_w); }
    }

    // if there is an error, return JSON for AJAX or redirect back for legacy submits
    if ($liveform->check_form_errors() == TRUE) {
        if ($is_ajax) {
            // Collect every field-level error message into a flat array so the
            // JS toast can show them. Pulling directly from the liveform session
            // because there is no public accessor for the full error list.
            $_ajax_errors = array();
            $_ajax_session = isset($_SESSION['software']['liveforms']['add_system_style'][$liveform->index])
                                ? $_SESSION['software']['liveforms']['add_system_style'][$liveform->index]
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
        header('Location: ' . URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/add_system_style.php');
        exit();
    }

    // Use cleaned_json (orphans removed) if available
    $tree_json_raw = (!empty($_vt['cleaned_json'])) ? $_vt['cleaned_json'] : $tree_json_raw;

    $tree_data = json_decode($tree_json_raw, true);

    $style_id = save_system_style(array(
        'name'                              => $liveform->get_field_value('name'),
        'style_tree_json'                   => $tree_json_raw,
        'theme_id'                          => $liveform->get_field_value('theme_id'),
        'additional_body_classes'           => $liveform->get_field_value('additional_body_classes'),
        'collection'                        => $liveform->get_field_value('collection'),
        'social_networking_position'        => $liveform->get_field_value('social_networking_position'),
        'style_empty_cell_width_percentage' => $liveform->get_field_value('style_empty_cell_width_percentage'),
        'user_id'                           => $user['id'],
    ));

    if ($tree_data && is_array($tree_data)) {
        save_tree_cells_to_db($tree_data, $style_id);
    }

    log_activity(lang(array('string' => 'style ({var:1}) was created', 'vars' => array($liveform->get_field_value('name')))), $_SESSION['sessionusername']);

    // Always create a page linked to this style (page name = style name)
    // Checkboxes only POST when checked; hidden field sends 0 when unchecked
    $pg_search  = ($liveform->get_field_value('page_search')  == '1') ? 1 : 0;
    $pg_sitemap = ($liveform->get_field_value('page_sitemap') == '1') ? 1 : 0;

    $pg_noindex = 0;
    $pg_nofollow = 0;

    // nofollow qualifies the noindex directive and is never emitted on its own,
    // so it is only stored while the page is closed to search engines.
    if (($liveform->get_field_value('page_noindex') == '1') && (pg_page_noindex_ready() == TRUE)) {
        $pg_noindex = 1;

        if ($liveform->get_field_value('page_nofollow') == '1') {
            $pg_nofollow = 1;
        }

        // A page that is closed to search engines has no business in the site
        // map. The switch is disabled on screen while noindex is on, but a POST
        // does not have to come from that screen.
        $pg_sitemap = 0;
    }

    $sql_noindex_columns = '';
    $sql_noindex_values = '';

    if (pg_page_noindex_ready() == TRUE) {
        $sql_noindex_columns = 'noindex, nofollow,';
        $sql_noindex_values = "'" . $pg_noindex . "', '" . $pg_nofollow . "',";
    }
    // page_home: switch is gated to role < 3; cross-check role server-side so
    // a crafted POST cannot promote a page authored by a contributor. DB
    // column stores the legacy 'yes' / '' string.
    $pg_home_post = ($liveform->get_field_value('page_home') == '1') ? 1 : 0;
    $pg_home_str  = ((int)$user['role'] < 3 && $pg_home_post) ? 'yes' : '';
    $pg_folder  = (int) $liveform->get_field_value('page_folder');
    $pg_search_keywords = ($pg_search == 1) ? $liveform->get_field_value('page_search_keywords') : '';

    $pg_comments       = ($liveform->get_field_value('pg_comments')       == '1') ? 1 : 0;
    $pg_comments_allow = ($liveform->get_field_value('pg_comments_allow_new') == '1') ? 1 : 0;
    $pg_comments_rate  = ($liveform->get_field_value('pg_comments_rating')   == '1') ? 1 : 0;
    $pg_comments_label = trim($liveform->get_field_value('pg_comments_label'));
    $pg_custom_css     = $liveform->get_field_value('page_custom_css');
    $pg_custom_js      = $liveform->get_field_value('page_custom_js');
    $pg_custom_fonts   = $liveform->get_field_value('page_custom_fonts');

    db("INSERT INTO page (page_name, page_folder, page_type, layout_type, page_home, page_search,
            page_search_keywords, page_timestamp, page_user, page_style, page_title,
            page_meta_description, comments_disallow_new_comment_message, sitemap,
            $sql_noindex_columns
            comments, comments_label, comments_allow_new_comments, comments_rating,
            page_custom_css, page_custom_js, page_custom_fonts)
        VALUES (
            '" . e($new_page_name) . "',
            '" . $pg_folder . "',
            'standard',
            'system',
            '" . e($pg_home_str) . "',
            '" . $pg_search . "',
            '" . e($pg_search_keywords) . "',
            UNIX_TIMESTAMP(),
            '" . e($user['id']) . "',
            '" . e($style_id) . "',
            '" . e($liveform->get_field_value('page_title')) . "',
            '" . e($liveform->get_field_value('page_meta_description')) . "',
            '',
            '" . $pg_sitemap . "',
            $sql_noindex_values
            '" . $pg_comments . "',
            '" . e($pg_comments_label) . "',
            '" . $pg_comments_allow . "',
            '" . $pg_comments_rate . "',
            '" . e($pg_custom_css) . "',
            '" . e($pg_custom_js) . "',
            '" . e($pg_custom_fonts) . "'
        )");

    // The page carries a promote on keyword list, and that list is what the tag cloud
    // is built from, so the new page has to be registered in it right away.
    update_tag_cloud_keywords_for_page(mysqli_insert_id(db::$con), $pg_search, $pg_search_keywords, 0, '');

    log_activity(lang(array('string' => 'page ({var:1}) was created', 'vars' => array($new_page_name))), $_SESSION['sessionusername']);

    // Sync inline CSS / JS / JSON asset files to the physical file system and files table.
    // Mirrors the same logic in edit_system_style.php so files created at creation time
    // are available immediately without needing to re-save via the editor.
    if (defined('FILE_DIRECTORY_PATH') && db::$con) {
        foreach (array($pg_custom_css, $pg_custom_js) as $_json_field) {
            $_assets = @json_decode($_json_field, true);
            if (!is_array($_assets)) continue;
            foreach ($_assets as $_asset) {
                if (empty($_asset['type']) || empty($_asset['name']) || !isset($_asset['content'])) continue;
                if (!in_array($_asset['type'], array('css', 'js', 'json'))) continue; // skip external-* links
                $_ext = strtolower(pathinfo($_asset['name'], PATHINFO_EXTENSION));
                if (!in_array($_ext, array('css', 'js', 'json'))) continue;
                @file_put_contents(FILE_DIRECTORY_PATH . '/' . $_asset['name'], $_asset['content']);
                $_size = (int) strlen($_asset['content']);
                $_nm   = mysqli_real_escape_string(db::$con, $_asset['name']);
                $_ex   = mysqli_real_escape_string(db::$con, $_ext);
                $_uid  = (int) $user['id'];
                $_chk  = @mysqli_query(db::$con, "SELECT name FROM files WHERE name = '$_nm' LIMIT 1");
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

    // Redirect: go to pages list if came from there; else jump to the edit
    // screen for the just-created style so the user can keep refining.
    $redirect_from = $liveform->get_field_value('from');
    if ($redirect_from === 'pages') {
        $redirect_url = URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/view_pages.php';
        $next_liveform = new liveform('view_pages');
        $next_liveform->add_notice(lang('The style was created successfully.'));
    } else {
        $redirect_url = URL_SCHEME . HOSTNAME . PATH . SOFTWARE_DIRECTORY . '/edit_system_style.php?id=' . (int)$style_id;
    }

    // AJAX save: clear liveform, return JSON with the redirect target so the
    // designer can navigate without losing the unload-warning suppression.
    if ($is_ajax) {
        $liveform->remove_form();
        header('Content-Type: application/json; charset=utf-8');
        echo encode_json(array(
            'status'       => 'success',
            'message'      => lang('The style was created successfully.'),
            'saved_ts'     => time(),
            'saved_by'     => isset($_SESSION['sessionusername']) ? $_SESSION['sessionusername'] : '',
            'style_id'     => (int)$style_id,
            'redirect_url' => $redirect_url,
        ));
        exit();
    }

    header('Location: ' . $redirect_url);
    $liveform->remove_form();
}
?>